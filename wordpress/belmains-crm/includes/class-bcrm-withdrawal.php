<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Online withdrawal function for distance contracts concluded on the site.
 *
 * The link labelled LINK_LABEL leads to a statement form (name, order, e-mail for the receipt); the
 * statement is sent with the button labelled CONFIRM_LABEL and acknowledged at once, on screen and by
 * e-mail, with its date, time and content. Any statement is recorded: nothing here tells a visitor
 * whether an order exists.
 */
class BCRM_Withdrawal extends BCRM_Contact {
    public const LINK_LABEL = 'Renoncer au contrat ici';
    public const CONFIRM_LABEL = 'Confirmer la rétractation';
    private const COOKIE = 'belmains_withdrawal_feedback';
    private const WINDOW = 15 * MINUTE_IN_SECONDS;
    private const LIMITS = array( 'name' => 100, 'email' => 200, 'order_reference' => 80, 'details' => 500 );

    public static function register() {
        add_shortcode( 'belmains_retractation', array( __CLASS__, 'shortcode' ) );
        add_action( 'admin_post_bcrm_withdrawal_submit', array( __CLASS__, 'handle' ) );
        add_action( 'admin_post_nopriv_bcrm_withdrawal_submit', array( __CLASS__, 'handle' ) );
        add_action( 'template_redirect', array( __CLASS__, 'no_cache' ) );
        add_action( 'woocommerce_email_after_order_table', array( __CLASS__, 'email_notice' ), 20, 3 );
        add_action( 'woocommerce_order_details_after_order_table', array( __CLASS__, 'order_notice' ), 20 );
    }

    /** Empty until the page is published: no link is shown to a function that cannot be used. */
    public static function url() {
        $page = absint( get_option( 'belmains_withdrawal_page_id' ) );
        return $page && 'publish' === get_post_status( $page ) && ! self::closed() ? get_permalink( $page ) : '';
    }

    private static function closed() { return (bool) apply_filters( 'belmains_catalog_only', false ); }

    public static function no_cache() {
        $post = get_queried_object();
        if ( $post instanceof WP_Post && has_shortcode( $post->post_content, 'belmains_retractation' ) ) {
            if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
            nocache_headers();
        }
    }

    private static function clean( array $input ) {
        $values = array();
        foreach ( self::LIMITS as $field => $limit ) {
            $value = isset( $input[ $field ] ) && is_string( $input[ $field ] ) ? $input[ $field ] : '';
            $value = 'details' === $field ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
            $values[ $field ] = mb_substr( trim( $value ), 0, $limit );
        }
        return $values;
    }

    /** Date and time of receipt as written on the acknowledgement, in the shop's time zone. */
    public static function stamp( $time ) { return wp_date( 'd/m/Y à H:i:s (T)', (int) $time ); }

    private static function shop_email() {
        $address = get_option( 'woocommerce_email_from_address' );
        return is_string( $address ) && is_email( $address ) ? $address : get_option( 'admin_email' );
    }

    private static function sign( $time, $mailed ) { return substr( self::hash( 'withdrawal-receipt:' . (int) $time . ':' . (int) $mailed ), 0, 32 ); }

    /** The order is attached only when its number and billing e-mail both match the statement. */
    private static function matching_order( array $values ) {
        $number = preg_replace( '/^(?:n°|no|#)\s*/iu', '', $values['order_reference'] );
        if ( ! function_exists( 'wc_get_order' ) || ! preg_match( '/^\d{1,12}$/D', (string) $number ) ) { return null; }
        $order = wc_get_order( (int) $number );
        return $order instanceof WC_Order && 0 === strcasecmp( (string) $order->get_billing_email(), $values['email'] ) ? $order : null;
    }

    /** Takes unslashed fields. Returns the time of receipt and whether the acknowledgement e-mail left. */
    public static function submit( array $input, array $server ) {
        if ( self::closed() ) { return self::error( 'La boutique n’est pas encore ouverte : aucune commande ne peut faire l’objet d’une rétractation.', 503 ); }
        if ( 'POST' !== ( $server['REQUEST_METHOD'] ?? '' ) ) { return self::error( 'Utilisez le formulaire pour transmettre votre rétractation.', 405 ); }
        if ( isset( $server['HTTP_ORIGIN'] ) ) {
            $origin = is_string( $server['HTTP_ORIGIN'] ) ? self::origin( $server['HTTP_ORIGIN'] ) : '';
            if ( ! $origin || ! in_array( $origin, array( self::origin( home_url() ), self::origin( site_url() ) ), true ) ) { return self::error( 'Rechargez la page avant de réessayer.', 403 ); }
        }
        if ( 'cross-site' === ( $server['HTTP_SEC_FETCH_SITE'] ?? '' ) ) { return self::error( 'Rechargez la page avant de réessayer.', 403 ); }
        foreach ( array_merge( array( 'request_token', 'bcrm_withdrawal_nonce', 'website' ), array_keys( self::LIMITS ) ) as $field ) {
            if ( isset( $input[ $field ] ) && ! is_string( $input[ $field ] ) ) { return self::error( 'Le formulaire contient une valeur invalide.' ); }
        }
        $token = $input['request_token'] ?? '';
        if ( ! preg_match( '/^[a-f0-9]{32}$/D', $token ) || ! wp_verify_nonce( $input['bcrm_withdrawal_nonce'] ?? '', 'bcrm_withdrawal_' . $token ) ) {
            return self::error( 'Votre formulaire a expiré. Vérifiez vos informations puis confirmez à nouveau.', 403 );
        }
        if ( '' !== trim( $input['website'] ?? '' ) ) { return self::error( 'Votre déclaration n’a pas été enregistrée. Rechargez le formulaire et réessayez.', 400 ); }

        $values = self::clean( $input );
        $fields = array();
        foreach ( self::LIMITS as $field => $limit ) {
            if ( mb_strlen( trim( $input[ $field ] ?? '' ) ) > $limit ) { $fields[ $field ] = sprintf( 'Limitez ce champ à %d caractères.', $limit ); }
        }
        if ( '' === $values['name'] ) { $fields['name'] = 'Indiquez votre nom.'; }
        if ( ! is_email( $values['email'] ) || $values['email'] !== trim( $input['email'] ?? '' ) ) { $fields['email'] = 'Indiquez une adresse e-mail valide : l’accusé de réception y sera envoyé.'; }
        if ( '' === $values['order_reference'] ) { $fields['order_reference'] = 'Indiquez le numéro de la commande concernée.'; }
        if ( $fields ) { return self::error( 'Vérifiez les champs indiqués ci-dessous.', 400, $fields ); }

        $fingerprint = self::hash( wp_json_encode( $values ) );
        $key = 'bcrm_withdrawal_done_' . self::hash( $token );
        $replay = static function () use ( $key, $fingerprint ) {
            $done = get_transient( $key );
            if ( ! is_array( $done ) ) { return null; }
            return hash_equals( (string) $done['fingerprint'], $fingerprint )
                ? array( 'success' => true, 'received' => (int) $done['received'], 'mailed' => (bool) $done['mailed'] )
                : self::error( 'Cette déclaration a déjà été transmise. Rechargez la page pour en faire une autre.', 409 );
        };
        $done = $replay();
        if ( null !== $done ) { return $done; }

        // Same lease scheme as the contact form: one owner per token and per rate bucket.
        $lock = 'bcrm_contact_lock_' . self::hash( 'withdrawal:' . $token );
        $lease = self::acquire_lock( $lock );
        if ( ! $lease ) { return self::error( 'Un envoi est déjà en cours. Patientez quelques secondes avant de réessayer.', 409 ); }
        $locks = array( $lock => $lease );
        try {
            $done = $replay();
            if ( null !== $done ) { return $done; }
            $buckets = array( 'bcrm_withdrawal_ip_' . self::hash( self::ip( $server ) ) => 5, 'bcrm_withdrawal_email_' . self::hash( strtolower( $values['email'] ) ) => 3 );
            ksort( $buckets );
            foreach ( $buckets as $bucket => $limit ) {
                $bucket_lock = 'bcrm_contact_lock_' . self::hash( 'rate:' . $bucket );
                $bucket_lease = self::acquire_lock( $bucket_lock );
                if ( ! $bucket_lease ) { return self::error( 'Un envoi est déjà en cours. Patientez quelques secondes avant de réessayer.', 409 ); }
                $locks[ $bucket_lock ] = $bucket_lease;
            }
            foreach ( $buckets as $bucket => $limit ) {
                if ( (int) get_transient( $bucket ) >= $limit ) { return self::error( 'Plusieurs déclarations ont déjà été transmises. Réessayez dans 15 minutes ou écrivez-nous.', 429 ); }
            }

            $received = time();
            $stamp = self::stamp( $received );
            $order = self::matching_order( $values );
            $record = 'Déclaration de rétractation reçue le ' . $stamp . " par le formulaire en ligne.\n"
                . 'Nom : ' . $values['name'] . "\n"
                . 'Numéro de commande indiqué : ' . $values['order_reference'] . ' — '
                . ( $order ? 'rattachée à la commande n° ' . $order->get_order_number() . '.' : 'à vérifier : aucune commande ne correspond à ce numéro et à cette adresse e-mail.' ) . "\n"
                . 'Précisions : ' . ( '' !== $values['details'] ? $values['details'] : 'aucune' );
            $ticket = BCRM_Support::create( array( 'subject' => mb_substr( 'Rétractation — commande ' . $values['order_reference'], 0, 200 ), 'customer_email' => $values['email'], 'message' => $record, 'order_id' => $order ? $order->get_id() : 0, 'status' => 'open', 'priority' => 'high' ) );
            if ( is_wp_error( $ticket ) ) { return self::error( 'L’enregistrement a échoué : votre déclaration n’a pas été transmise. Réessayez dans quelques instants ou écrivez-nous.', 503 ); }

            $terms = function_exists( 'wc_terms_and_conditions_page_id' ) && wc_terms_and_conditions_page_id() ? get_permalink( wc_terms_and_conditions_page_id() ) : '';
            $body = 'Bonjour ' . $values['name'] . ",\n\n"
                . "Nous accusons réception de votre déclaration de rétractation, transmise par le formulaire en ligne de notre site.\n\n"
                . 'Date et heure de réception : ' . $stamp . "\n"
                . 'Nom : ' . $values['name'] . "\n"
                . 'Numéro de commande indiqué : ' . $values['order_reference'] . "\n"
                . 'Adresse e-mail : ' . $values['email'] . "\n"
                . 'Précisions : ' . ( '' !== $values['details'] ? $values['details'] : 'aucune' ) . "\n\n"
                . "Nous revenons vers vous à cette adresse avec les modalités de retour du produit. Le remboursement est effectué dans les conditions prévues par nos conditions générales de vente" . ( $terms ? ' : ' . $terms : '.' ) . "\n\n"
                . "Conservez cet e-mail : il constitue l’accusé de réception de votre déclaration.\n\n"
                . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . ' — ' . self::shop_email() . "\n";
            $mailed = (bool) wp_mail( $values['email'], 'Accusé de réception de votre rétractation — commande ' . $values['order_reference'], $body );
            // The shop must act within statutory time limits: it is told at once, apart from the inbox.
            wp_mail( self::shop_email(), 'Rétractation reçue — commande ' . $values['order_reference'], $record . "\n\nDemande n° " . (int) $ticket['id'] . ' dans le SAV du CRM. Accusé de réception envoyé au client : ' . ( $mailed ? 'oui' : 'NON, à lui adresser manuellement' ) . ".\n", array( 'Reply-To: ' . $values['email'] ) );
            if ( ! $mailed ) {
                global $wpdb;
                $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}bcrm_tickets SET priority = 'urgent', message = CONCAT(message, %s) WHERE id = %d", "\n\nL’accusé de réception n’a pas pu être envoyé par e-mail : l’adresser manuellement au client.", (int) $ticket['id'] ) );
            }
            if ( $order ) { $order->add_order_note( 'Déclaration de rétractation reçue le ' . $stamp . ' par le formulaire en ligne (demande SAV n° ' . (int) $ticket['id'] . ').' ); }

            set_transient( $key, array( 'fingerprint' => $fingerprint, 'received' => $received, 'mailed' => $mailed ), DAY_IN_SECONDS );
            foreach ( $buckets as $bucket => $limit ) { set_transient( $bucket, (int) get_transient( $bucket ) + 1, self::WINDOW ); }
            return array( 'success' => true, 'received' => $received, 'mailed' => $mailed );
        } finally {
            foreach ( array_reverse( $locks, true ) as $name => $held ) { self::release_lock( $name, $held ); }
        }
    }

    public static function handle() {
        nocache_headers();
        $input = wp_unslash( $_POST );
        $result = self::submit( is_array( $input ) ? $input : array(), $_SERVER );
        $page = absint( get_option( 'belmains_withdrawal_page_id' ) );
        $back = $page ? get_permalink( $page ) : home_url( '/' );
        $cookie = '';
        if ( is_wp_error( $result ) ) {
            $data = $result->get_error_data();
            $args = array( 'retractation' => array( 403 => 'expiree', 405 => 'invalide', 409 => 'en-cours', 429 => 'limitee', 503 => 'indisponible' )[ $data['status'] ?? 400 ] ?? 'invalide' );
            if ( ! empty( $data['fields'] ) ) {
                // Reached only with a valid nonce from this site: one feedback slot per address.
                $slot = self::hash( 'withdrawal:' . self::ip( $_SERVER ) );
                $secret = str_replace( '-', '', wp_generate_uuid4() );
                set_transient( 'bcrm_withdrawal_flash_' . $slot, array( 'message' => $result->get_error_message(), 'fields' => $data['fields'], 'values' => self::clean( is_array( $input ) ? $input : array() ), 'secret' => self::hash( $secret ) ), 10 * MINUTE_IN_SECONDS );
                $cookie = $slot . '.' . $secret;
            }
        } else {
            $args = array( 'retractation' => 'recue', 'le' => $result['received'], 'mail' => (int) $result['mailed'], 'preuve' => self::sign( $result['received'], $result['mailed'] ) );
        }
        setcookie( self::COOKIE, $cookie, array( 'expires' => $cookie ? time() + 10 * MINUTE_IN_SECONDS : time() - HOUR_IN_SECONDS, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
        wp_safe_redirect( add_query_arg( $args, $back ) . '#belmains-retractation', 303 );
        exit;
    }

    public static function shortcode() {
        $contact = esc_html( self::shop_email() );
        if ( self::closed() ) {
            return '<div id="belmains-retractation" class="belmains-contact"><p class="belmains-contact-notice">La boutique n’est pas encore ouverte : aucune commande ne peut faire l’objet d’une rétractation pour le moment.</p></div>';
        }
        $get = static function ( $name ) { return isset( $_GET[ $name ] ) && is_string( $_GET[ $name ] ) ? wp_unslash( $_GET[ $name ] ) : ''; };
        $status = $get( 'retractation' );
        if ( 'recue' === $status && ctype_digit( $get( 'le' ) ) && in_array( $get( 'mail' ), array( '0', '1' ), true ) && hash_equals( self::sign( $get( 'le' ), $get( 'mail' ) ), $get( 'preuve' ) ) ) {
            $text = 'Votre déclaration de rétractation a bien été reçue le ' . esc_html( self::stamp( $get( 'le' ) ) ) . '. '
                . ( '1' === $get( 'mail' )
                    ? 'Un accusé de réception reprenant son contenu vient d’être envoyé à l’adresse e-mail indiquée. Nous revenons vers vous avec les modalités de retour.'
                    : 'L’accusé de réception n’a pas pu être envoyé par e-mail : conservez une copie de cette page et écrivez-nous à ' . $contact . ' pour le recevoir.' );
            return '<div id="belmains-retractation" class="belmains-contact"><div class="woocommerce-message belmains-contact-success" role="status" tabindex="-1">' . $text . '</div></div>';
        }

        $feedback = array();
        $cookie = isset( $_COOKIE[ self::COOKIE ] ) && is_string( $_COOKIE[ self::COOKIE ] ) ? $_COOKIE[ self::COOKIE ] : '';
        if ( preg_match( '/^([a-f0-9]{64})\.([a-f0-9]{32})$/D', $cookie, $parts ) ) {
            $stored = get_transient( 'bcrm_withdrawal_flash_' . $parts[1] );
            if ( is_array( $stored ) && isset( $stored['secret'] ) && hash_equals( $stored['secret'], self::hash( $parts[2] ) ) ) {
                $feedback = $stored;
                delete_transient( 'bcrm_withdrawal_flash_' . $parts[1] );
            }
        }
        $messages = array( 'expiree' => 'Rechargez le formulaire avant de réessayer.', 'invalide' => 'Votre déclaration n’a pas été enregistrée. Vérifiez le formulaire et réessayez.', 'en-cours' => 'Un envoi est déjà en cours. Patientez quelques secondes avant de réessayer.', 'limitee' => 'Plusieurs déclarations ont déjà été transmises. Réessayez dans 15 minutes ou écrivez-nous à ' . self::shop_email() . '.', 'indisponible' => 'L’enregistrement a échoué. Réessayez dans quelques instants ou écrivez-nous à ' . self::shop_email() . '.' );
        if ( ! $feedback && isset( $messages[ $status ] ) ) { $feedback = array( 'message' => $messages[ $status ] ); }
        $values = self::clean( $feedback['values'] ?? array( 'order_reference' => $get( 'commande' ) ) );
        $fields = $feedback['fields'] ?? array();
        $token = str_replace( '-', '', wp_generate_uuid4() );
        $inputs = array(
            'name' => array( 'Votre nom', 'text', 'name', '' ),
            'email' => array( 'Votre e-mail', 'email', 'email', 'L’accusé de réception de votre rétractation sera envoyé à cette adresse.' ),
            'order_reference' => array( 'Numéro de commande', 'text', 'off', 'Il figure dans l’e-mail de confirmation de votre commande.' ),
            'details' => array( 'Précisions (facultatif)', 'textarea', 'off', 'Par exemple si vous ne renvoyez qu’une partie de la commande. 500 caractères maximum.' ),
        );
        ob_start();
        ?>
        <div id="belmains-retractation" class="belmains-contact">
            <p>Vous disposez de 14 jours à compter de la réception de votre commande pour vous rétracter, sans avoir à motiver votre décision. Remplissez cette déclaration puis cliquez sur « <?php echo esc_html( self::CONFIRM_LABEL ); ?> » : un accusé de réception indiquant la date et l’heure de votre déclaration vous est envoyé aussitôt par e-mail.</p>
            <?php if ( ! empty( $feedback['message'] ) ) : ?>
                <div class="woocommerce-error belmains-contact-error" role="alert" tabindex="-1"><p><?php echo esc_html( $feedback['message'] ); ?></p><?php if ( $fields ) : ?><ul><?php foreach ( $fields as $field => $error ) : ?><li><a href="#bcrm-withdrawal-<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $error ); ?></a></li><?php endforeach; ?></ul><?php endif; ?></div>
            <?php endif; ?>
            <form class="belmains-contact-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
                <input type="hidden" name="action" value="bcrm_withdrawal_submit">
                <input type="hidden" name="request_token" value="<?php echo esc_attr( $token ); ?>">
                <?php wp_nonce_field( 'bcrm_withdrawal_' . $token, 'bcrm_withdrawal_nonce', false ); ?>
                <p class="belmains-contact-honeypot" aria-hidden="true" style="position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%);"><label for="bcrm-withdrawal-website">Laissez ce champ vide</label><input id="bcrm-withdrawal-website" name="website" type="text" value="" tabindex="-1" autocomplete="off"></p>
                <p class="belmains-contact-required">Les champs marqués d’un astérisque sont obligatoires.</p>
                <?php foreach ( $inputs as $field => $config ) :
                    $required = 'details' !== $field;
                    $described = array_filter( array( $config[3] ? 'bcrm-withdrawal-' . $field . '-help' : '', isset( $fields[ $field ] ) ? 'bcrm-withdrawal-' . $field . '-error' : '' ) );
                    $attributes = ' id="bcrm-withdrawal-' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" maxlength="' . (int) self::LIMITS[ $field ] . '"' . ( $required ? ' required' : '' ) . ( isset( $fields[ $field ] ) ? ' aria-invalid="true"' : '' ) . ( $described ? ' aria-describedby="' . esc_attr( implode( ' ', $described ) ) . '"' : '' );
                    ?>
                    <p class="form-row form-row-wide<?php echo isset( $fields[ $field ] ) ? ' woocommerce-invalid' : ''; ?>">
                        <label for="bcrm-withdrawal-<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $config[0] ); ?><?php if ( $required ) : ?> <span aria-hidden="true">*</span><?php endif; ?></label>
                        <?php if ( 'textarea' === $config[1] ) : ?>
                            <textarea class="input-text" rows="4"<?php echo $attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts above. ?>><?php echo esc_textarea( $values[ $field ] ); ?></textarea>
                        <?php else : ?>
                            <input class="input-text" type="<?php echo esc_attr( $config[1] ); ?>" autocomplete="<?php echo esc_attr( $config[2] ); ?>" value="<?php echo esc_attr( $values[ $field ] ); ?>"<?php echo $attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                        <?php endif; ?>
                        <?php if ( $config[3] ) : ?><small id="bcrm-withdrawal-<?php echo esc_attr( $field ); ?>-help"><?php echo esc_html( $config[3] ); ?></small><?php endif; ?>
                        <?php if ( isset( $fields[ $field ] ) ) : ?><span class="belmains-contact-field-error" id="bcrm-withdrawal-<?php echo esc_attr( $field ); ?>-error"><?php echo esc_html( $fields[ $field ] ); ?></span><?php endif; ?>
                    </p>
                <?php endforeach; ?>
                <p class="belmains-contact-privacy">Ces informations servent uniquement à enregistrer votre rétractation et à vous en accuser réception.<?php $privacy = get_privacy_policy_url(); if ( $privacy ) : ?> <a href="<?php echo esc_url( $privacy ); ?>">Confidentialité</a><?php endif; ?></p>
                <button type="submit" class="button alt"><?php echo esc_html( self::CONFIRM_LABEL ); ?></button>
            </form>
            <p>Vous pouvez aussi nous adresser votre décision par tout autre moyen dénué d’ambiguïté, par exemple par e-mail à <?php echo $contact; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>.</p>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function order_link( $order ) {
        $url = self::url();
        return $url && $order instanceof WC_Order ? add_query_arg( 'commande', rawurlencode( $order->get_order_number() ), $url ) : '';
    }

    /** Customer e-mails carry the function's address for the whole withdrawal period. */
    public static function email_notice( $order, $sent_to_admin = false, $plain_text = false ) {
        $url = $sent_to_admin ? '' : self::order_link( $order );
        if ( ! $url ) { return; }
        $text = 'Droit de rétractation : vous disposez de 14 jours à compter de la réception de votre commande pour vous rétracter, sans avoir à motiver votre décision.';
        if ( $plain_text ) { echo "\n" . $text . ' ' . self::LINK_LABEL . ' : ' . esc_url_raw( $url ) . "\n\n"; return; }
        echo '<p>' . esc_html( $text ) . ' <a href="' . esc_url( $url ) . '">' . esc_html( self::LINK_LABEL ) . '</a></p>';
    }

    /** Order confirmation and "my account" order pages. */
    public static function order_notice( $order ) {
        $url = self::order_link( $order );
        if ( $url ) { echo '<p class="belmains-withdrawal-link">Vous changez d’avis ? Vous disposez de 14 jours à compter de la réception de votre commande. <a href="' . esc_url( $url ) . '">' . esc_html( self::LINK_LABEL ) . '</a></p>'; }
    }
}
