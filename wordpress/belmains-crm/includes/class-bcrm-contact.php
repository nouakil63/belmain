<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Public, mail-free entry point into the private support inbox. */
class BCRM_Contact {
    private const COOKIE = 'belmains_contact_feedback';
    private const WINDOW = 15 * MINUTE_IN_SECONDS;

    public static function register() {
        add_shortcode( 'belmains_contact', array( __CLASS__, 'shortcode' ) );
        add_action( 'admin_post_bcrm_contact_submit', array( __CLASS__, 'handle' ) );
        add_action( 'admin_post_nopriv_bcrm_contact_submit', array( __CLASS__, 'handle' ) );
        add_action( 'template_redirect', array( __CLASS__, 'no_cache' ) );
        add_action( 'bcrm_daily_cleanup', array( __CLASS__, 'cleanup' ) );
    }

    public static function url() {
        $page = absint( get_option( 'belmains_contact_page_id' ) );
        return $page && 'publish' === get_post_status( $page ) ? get_permalink( $page ) : home_url( '/contact/' );
    }

    public static function no_cache() {
        $post = get_queried_object();
        if ( $post instanceof WP_Post && has_shortcode( $post->post_content, 'belmains_contact' ) ) {
            if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
            nocache_headers();
        }
    }

    private static function error( $message, $status = 400, $fields = array() ) {
        return new WP_Error( 'bcrm_contact', $message, array( 'status' => $status, 'fields' => $fields ) );
    }

    private static function hash( $value ) { return hash_hmac( 'sha256', $value, wp_salt( 'auth' ) ); }

    private static function ip( array $server ) {
        return is_string( $server['REMOTE_ADDR'] ?? null ) && filter_var( $server['REMOTE_ADDR'], FILTER_VALIDATE_IP ) ? $server['REMOTE_ADDR'] : 'unknown';
    }

    private static function release_lock( $key, $lease ) {
        global $wpdb;
        // Compare-and-delete cannot release another request's replacement lease.
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $key, $lease ) );
        wp_cache_delete( $key, 'options' );
        wp_cache_delete( 'notoptions', 'options' );
    }

    private static function acquire_lock( $key ) {
        global $wpdb;
        $lease = ( time() + MINUTE_IN_SECONDS ) . ':' . wp_generate_uuid4();
        // add_option() can upsert a competing value after a stale absence check.
        // The unique option_name index plus INSERT IGNORE grants one owner only.
        $insert = static function() use ( $wpdb, $key, $lease ) {
            $created = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", $key, $lease ) );
            if ( 1 !== $created ) { return false; }
            wp_cache_delete( $key, 'options' );
            wp_cache_delete( 'notoptions', 'options' );
            return true;
        };
        if ( $insert() ) { return $lease; }
        $old = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key ) );
        if ( $old && (int) $old < time() ) {
            self::release_lock( $key, $old );
            if ( $insert() ) { return $lease; }
        }
        return false;
    }

    private static function origin( $url ) {
        $parts = wp_parse_url( $url );
        if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) || ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) { return ''; }
        return strtolower( $parts['scheme'] . '://' . $parts['host'] ) . ':' . ( $parts['port'] ?? ( 'https' === strtolower( $parts['scheme'] ) ? 443 : 80 ) );
    }

    /** Takes unslashed fields. Never returns a ticket identifier or customer data. */
    public static function submit( array $input, array $server ) {
        if ( 'POST' !== ( $server['REQUEST_METHOD'] ?? '' ) ) { return self::error( 'Utilisez le formulaire pour envoyer votre message.', 405 ); }
        if ( isset( $server['HTTP_ORIGIN'] ) ) {
            $origin = is_string( $server['HTTP_ORIGIN'] ) ? self::origin( $server['HTTP_ORIGIN'] ) : '';
            if ( ! $origin || ! in_array( $origin, array( self::origin( home_url() ), self::origin( site_url() ) ), true ) ) { return self::error( 'Rechargez la page Contact avant de réessayer.', 403 ); }
        }
        if ( 'cross-site' === ( $server['HTTP_SEC_FETCH_SITE'] ?? '' ) ) { return self::error( 'Rechargez la page Contact avant de réessayer.', 403 ); }
        foreach ( array( 'request_token', 'bcrm_contact_nonce', 'name', 'email', 'subject', 'message', 'order_reference', 'website' ) as $field ) {
            if ( isset( $input[ $field ] ) && ! is_string( $input[ $field ] ) ) { return self::error( 'Le formulaire contient une valeur invalide.' ); }
        }
        $token = $input['request_token'] ?? '';
        if ( ! preg_match( '/^[a-f0-9]{32}$/D', $token ) || ! wp_verify_nonce( $input['bcrm_contact_nonce'] ?? '', 'bcrm_contact_' . $token ) ) {
            return self::error( 'Votre formulaire a expiré. Vérifiez votre message puis renvoyez-le.', 403 );
        }
        if ( '' !== trim( $input['website'] ?? '' ) ) { return self::error( 'Votre message n’a pas été enregistré. Rechargez le formulaire et réessayez.', 400 ); }

        $values = self::safe_values( $input );
        $fields = array();
        foreach ( array( 'name' => 100, 'email' => 200, 'subject' => 160, 'message' => 5000, 'order_reference' => 80 ) as $field => $limit ) {
            if ( mb_strlen( trim( $input[ $field ] ?? '' ) ) > $limit ) { $fields[ $field ] = sprintf( 'Limitez ce champ à %d caractères.', $limit ); }
        }
        if ( '' === $values['name'] ) { $fields['name'] = 'Indiquez votre nom.'; }
        if ( ! is_email( $values['email'] ) || $values['email'] !== trim( $input['email'] ?? '' ) ) { $fields['email'] = 'Indiquez une adresse e-mail valide.'; }
        if ( mb_strlen( $values['subject'] ) < 3 ) { $fields['subject'] = 'Précisez le sujet de votre demande (3 caractères minimum).'; }
        if ( mb_strlen( $values['message'] ) < 10 ) { $fields['message'] = 'Décrivez votre demande en au moins 10 caractères.'; }
        if ( $fields ) { return self::error( 'Vérifiez les champs indiqués ci-dessous.', 400, $fields ); }

        $fingerprint = self::hash( wp_json_encode( $values ) );
        $key = 'bcrm_contact_done_' . self::hash( $token );
        $done = get_transient( $key );
        if ( $done ) { return hash_equals( $done, $fingerprint ) ? array( 'success' => true ) : self::error( 'Ce formulaire a déjà été envoyé. Rechargez la page pour une nouvelle demande.', 409 ); }

        // Unique option insertion serializes concurrent retries; it stores no PII.
        $lock = 'bcrm_contact_lock_' . self::hash( $token );
        $lease = self::acquire_lock( $lock );
        if ( ! $lease ) {
            return self::error( 'Un envoi est déjà en cours. Patientez quelques secondes avant de réessayer.', 409 );
        }
        $locks = array( $lock => $lease );
        try {
            // A completed request may have arrived between the first check and lock.
            $done = get_transient( $key );
            if ( $done ) { return hash_equals( $done, $fingerprint ) ? array( 'success' => true ) : self::error( 'Ce formulaire a déjà été envoyé. Rechargez la page pour une nouvelle demande.', 409 ); }
            $buckets = array( 'bcrm_contact_ip_' . self::hash( self::ip( $server ) ) => 5, 'bcrm_contact_email_' . self::hash( strtolower( $values['email'] ) ) => 3 );
            ksort( $buckets );
            // Every token sharing an IP or email must hold the same bucket leases.
            // Read, create and increment remain in this one critical section.
            foreach ( $buckets as $bucket => $limit ) {
                $bucket_lock = 'bcrm_contact_lock_' . self::hash( 'rate:' . $bucket );
                $bucket_lease = self::acquire_lock( $bucket_lock );
                if ( ! $bucket_lease ) { return self::error( 'Un envoi est déjà en cours. Patientez quelques secondes avant de réessayer.', 409 ); }
                $locks[ $bucket_lock ] = $bucket_lease;
            }
            foreach ( $buckets as $bucket => $limit ) {
                if ( (int) get_transient( $bucket ) >= $limit ) { return self::error( 'Plusieurs messages ont déjà été envoyés. Réessayez dans 15 minutes.', 429 ); }
            }
            $message = 'Nom : ' . $values['name'] . "\n";
            if ( $values['order_reference'] ) { $message .= 'Référence indiquée par le client (à vérifier) : ' . $values['order_reference'] . "\n"; }
            $message .= "\n" . $values['message'];
            $result = BCRM_Support::create( array( 'subject' => $values['subject'], 'customer_email' => $values['email'], 'message' => $message, 'order_id' => 0, 'status' => 'open', 'priority' => 'normal' ) );
            if ( is_wp_error( $result ) ) { return self::error( 'L’enregistrement a échoué. Votre demande n’a pas été envoyée ; réessayez dans quelques instants.', 503 ); }
            set_transient( $key, $fingerprint, DAY_IN_SECONDS );
            foreach ( $buckets as $bucket => $limit ) { set_transient( $bucket, (int) get_transient( $bucket ) + 1, self::WINDOW ); }
            return array( 'success' => true );
        } finally {
            foreach ( array_reverse( $locks, true ) as $key => $lease ) { self::release_lock( $key, $lease ); }
        }
    }

    private static function safe_values( array $input ) {
        $values = array();
        foreach ( array( 'name' => 100, 'email' => 200, 'subject' => 160, 'message' => 5000, 'order_reference' => 80 ) as $field => $limit ) {
            $value = isset( $input[ $field ] ) && is_string( $input[ $field ] ) ? $input[ $field ] : '';
            $value = 'message' === $field ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
            $values[ $field ] = mb_substr( trim( $value ), 0, $limit );
        }
        return $values;
    }

    private static function response( array $input, array $server ) {
        $result = self::submit( $input, $server );
        $cookie = '';
        $code = 'received';
        if ( is_wp_error( $result ) ) {
            $data = $result->get_error_data();
            $code = array( 403 => 'expired', 405 => 'invalid', 409 => 'busy', 429 => 'limited', 503 => 'unavailable' )[ $data['status'] ?? 400 ] ?? 'invalid';
            if ( ! empty( $data['fields'] ) ) {
                // Only a validated-origin, valid-nonce, non-honeypot request can
                // reach field errors. Repeated invalid forms replace one slot per
                // IP, rather than allocating unlimited rows for arbitrary tokens.
                $slot = self::hash( self::ip( $server ) );
                $secret = str_replace( '-', '', wp_generate_uuid4() );
                $feedback = array( 'message' => $result->get_error_message(), 'fields' => $data['fields'], 'values' => self::safe_values( $input ), 'secret' => self::hash( $secret ) );
                set_transient( 'bcrm_contact_flash_' . $slot, $feedback, 10 * MINUTE_IN_SECONDS );
                $cookie = $slot . '.' . $secret;
            }
        }
        // Security, method, quota and success responses allocate no flash data.
        return array( 'cookie' => $cookie, 'redirect' => add_query_arg( 'contact_status', $code, self::url() ) . '#belmains-contact' );
    }

    public static function handle() {
        nocache_headers();
        $response = self::response( wp_unslash( $_POST ), $_SERVER );
        setcookie( self::COOKIE, $response['cookie'], array( 'expires' => $response['cookie'] ? time() + 10 * MINUTE_IN_SECONDS : time() - HOUR_IN_SECONDS, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
        wp_safe_redirect( $response['redirect'], 303 );
        exit;
    }

    public static function shortcode() {
        $feedback = array();
        $cookie = isset( $_COOKIE[ self::COOKIE ] ) && is_string( $_COOKIE[ self::COOKIE ] ) ? $_COOKIE[ self::COOKIE ] : '';
        if ( preg_match( '/^([a-f0-9]{64})\.([a-f0-9]{32})$/D', $cookie, $parts ) ) {
            $key = 'bcrm_contact_flash_' . $parts[1];
            $stored = get_transient( $key );
            if ( is_array( $stored ) && isset( $stored['secret'] ) && hash_equals( $stored['secret'], self::hash( $parts[2] ) ) ) {
                $feedback = $stored;
                delete_transient( $key );
            }
        }
        $status = isset( $_GET['contact_status'] ) && is_string( $_GET['contact_status'] ) ? $_GET['contact_status'] : '';
        if ( ! $feedback && $status ) {
            $messages = array( 'expired' => 'Rechargez le formulaire avant de réessayer.', 'invalid' => 'Votre demande n’a pas été enregistrée. Vérifiez le formulaire et réessayez.', 'busy' => 'Un envoi est déjà en cours. Patientez quelques secondes avant de réessayer.', 'limited' => 'Plusieurs messages ont déjà été envoyés. Réessayez dans 15 minutes.', 'unavailable' => 'L’enregistrement a échoué. Réessayez dans quelques instants.' );
            if ( 'received' === $status ) { $feedback = array( 'success' => true ); }
            elseif ( isset( $messages[ $status ] ) ) { $feedback = array( 'message' => $messages[ $status ] ); }
        }
        $values = self::safe_values( $feedback['values'] ?? array() );
        $fields = $feedback['fields'] ?? array();
        $token = str_replace( '-', '', wp_generate_uuid4() );
        ob_start();
        ?>
        <div id="belmains-contact" class="belmains-contact">
            <p>Une question sur le gant, une commande ou une livraison ? Écrivez-nous ici. Votre demande sera transmise à notre service client.</p>
            <?php if ( ! empty( $feedback['success'] ) ) : ?>
                <div class="woocommerce-message belmains-contact-success" role="status" tabindex="-1">Votre demande a bien été enregistrée auprès du service client. Nous vous répondrons à l’adresse e-mail indiquée.</div>
            <?php elseif ( ! empty( $feedback['message'] ) ) : ?>
                <div class="woocommerce-error belmains-contact-error" role="alert" tabindex="-1"><p><?php echo esc_html( $feedback['message'] ); ?></p><?php if ( $fields ) : ?><ul><?php foreach ( $fields as $field => $error ) : ?><li><a href="#bcrm-contact-<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $error ); ?></a></li><?php endforeach; ?></ul><?php endif; ?></div>
            <?php endif; ?>
            <form class="belmains-contact-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
                <input type="hidden" name="action" value="bcrm_contact_submit">
                <input type="hidden" name="request_token" value="<?php echo esc_attr( $token ); ?>">
                <?php wp_nonce_field( 'bcrm_contact_' . $token, 'bcrm_contact_nonce', false ); ?>
                <p class="belmains-contact-honeypot" aria-hidden="true" style="position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%);"><label for="bcrm-contact-website">Laissez ce champ vide</label><input id="bcrm-contact-website" name="website" type="text" value="" tabindex="-1" autocomplete="off"></p>
                <p class="belmains-contact-required">Les champs marqués d’un astérisque sont obligatoires.</p>
                <?php foreach ( array( 'name' => array( 'Votre nom', 100, 'text', 'name' ), 'email' => array( 'Votre e-mail', 200, 'email', 'email' ), 'subject' => array( 'Sujet de votre demande', 160, 'text', 'off' ), 'order_reference' => array( 'Numéro de commande (facultatif)', 80, 'text', 'off' ), 'message' => array( 'Votre message', 5000, 'textarea', 'off' ) ) as $field => $config ) :
                    $required = 'order_reference' !== $field;
                    $described = array();
                    if ( 'order_reference' === $field ) { $described[] = 'bcrm-contact-order-help'; }
                    if ( 'message' === $field ) { $described[] = 'bcrm-contact-message-help'; }
                    if ( isset( $fields[ $field ] ) ) { $described[] = 'bcrm-contact-' . $field . '-error'; }
                    ?>
                    <p class="form-row form-row-wide<?php echo isset( $fields[ $field ] ) ? ' woocommerce-invalid' : ''; ?>">
                        <label for="bcrm-contact-<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $config[0] ); ?><?php if ( $required ) : ?> <span aria-hidden="true">*</span><?php endif; ?></label>
                        <?php if ( 'textarea' === $config[2] ) : ?>
                            <textarea class="input-text" id="bcrm-contact-message" name="message" rows="7" minlength="10" maxlength="5000" required <?php if ( isset( $fields[ $field ] ) ) { echo 'aria-invalid="true" '; } ?>aria-describedby="<?php echo esc_attr( implode( ' ', $described ) ); ?>"><?php echo esc_textarea( $values[ $field ] ); ?></textarea>
                        <?php else : ?>
                            <input class="input-text" id="bcrm-contact-<?php echo esc_attr( $field ); ?>" name="<?php echo esc_attr( $field ); ?>" type="<?php echo esc_attr( $config[2] ); ?>" maxlength="<?php echo (int) $config[1]; ?>" autocomplete="<?php echo esc_attr( $config[3] ); ?>" value="<?php echo esc_attr( $values[ $field ] ); ?>" <?php if ( $required ) { echo 'required '; } if ( isset( $fields[ $field ] ) ) { echo 'aria-invalid="true" '; } if ( $described ) { echo 'aria-describedby="' . esc_attr( implode( ' ', $described ) ) . '"'; } ?>>
                        <?php endif; ?>
                        <?php if ( 'order_reference' === $field ) : ?><small id="bcrm-contact-order-help">Si votre demande concerne une commande, indiquez sa référence.</small><?php endif; ?>
                        <?php if ( 'message' === $field ) : ?><small id="bcrm-contact-message-help">De 10 à 5 000 caractères. Ne transmettez pas de mot de passe ni de coordonnées bancaires.</small><?php endif; ?>
                        <?php if ( isset( $fields[ $field ] ) ) : ?><span class="belmains-contact-field-error" id="bcrm-contact-<?php echo esc_attr( $field ); ?>-error"><?php echo esc_html( $fields[ $field ] ); ?></span><?php endif; ?>
                    </p>
                <?php endforeach; ?>
                <p class="belmains-contact-privacy">Les informations saisies sont enregistrées pour traiter votre demande et vous répondre. Elles ne vous inscrivent à aucune liste publicitaire.<?php $privacy = get_privacy_policy_url(); if ( $privacy ) : ?> <a href="<?php echo esc_url( $privacy ); ?>">Confidentialité</a><?php endif; ?></p>
                <button type="submit" class="button alt">Envoyer ma demande</button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /** Expired locks are only possible after an interrupted server request. */
    public static function cleanup() {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED) < %d", $wpdb->esc_like( 'bcrm_contact_lock_' ) . '%', time() ), ARRAY_A );
        foreach ( $rows as $row ) { self::release_lock( $row['option_name'], $row['option_value'] ); }
    }
}
