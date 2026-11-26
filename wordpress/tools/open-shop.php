<?php
/**
 * Controlled opening of the Belmains shop, and its reversal. Command line only.
 *
 *   BELMAINS_SHOP=check                                    read-only readiness report, changes nothing
 *   BELMAINS_SHOP=open   BELMAINS_SHOP_REVISION=<sha>      open sales
 *   BELMAINS_SHOP=close  BELMAINS_SHOP_REVISION=<sha>      put the pre-opening showcase back
 *
 * Optional, for "open" only:
 *   BELMAINS_SHOP_ENABLE_GATEWAYS=id,id   enable these registered payment gateways while opening
 *   BELMAINS_SHOP_INDEX=1                 let search engines index the site
 *   BELMAINS_SHOP_ALLOW=taxes,google      accept a named blocking point after a deliberate decision
 *
 * Run from the reviewed checkout ~/belmains-deployment/source/wordpress/tools, with
 * `wp eval-file open-shop.php` or directly with `php open-shop.php`. The pre-opening guard is
 * replaced through the deployment receiver's own state, so later publications keep working.
 * Never loaded by the theme, a plugin or a web request.
 */
if ( 'cli' !== PHP_SAPI ) { http_response_code( 404 ); exit; }

if ( ! defined( 'ABSPATH' ) ) {
    // Direct `php` run: find the single site served next to the private deployment state.
    $belmains_state = dirname( __DIR__, 3 );
    $belmains_sites = glob( dirname( $belmains_state ) . '/sites/*/belmains-deploy-check.php' ) ?: array();
    if ( 'belmains-deployment' !== basename( $belmains_state ) || 1 !== count( $belmains_sites ) ) {
        fwrite( STDERR, "Lancer cet outil depuis ~/belmains-deployment/source/wordpress/tools.\n" ); exit( 2 );
    }
    $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'belmains.com';
    $_SERVER['REQUEST_URI'] = '/'; $_SERVER['HTTPS'] = 'on'; $_SERVER['REQUEST_METHOD'] = 'GET';
    require dirname( $belmains_sites[0] ) . '/wp-load.php';
}

if ( ! class_exists( 'Belmains_Shop_Opening' ) ) :
final class Belmains_Shop_Opening {
    private const LEGAL = array(
        'legal' => 'Mentions légales', 'terms' => 'Conditions générales de vente',
        'privacy' => 'Politique de confidentialité', 'returns' => 'Livraison et retours',
    );
    private const ALLOWABLE = array( 'taxes', 'google' );

    /** @var array<int, array{0:string,1:string,2:string}> level, subject, detail */
    private array $lines = array();
    private string $site; private string $state; private string $guard; private string $current; private string $trusted;
    private array $allow;

    public function __construct( array $allow = array() ) {
        $this->site = (string) realpath( rtrim( ABSPATH, '/' ) );
        $this->state = dirname( $this->site, 2 ) . '/belmains-deployment';
        $this->guard = $this->site . '/wp-content/mu-plugins/belmains-staging-guard.php';
        $this->current = $this->state . '/current.json';
        $this->trusted = $this->state . '/deploy-runtime.php';
        $this->allow = array_values( array_intersect( self::ALLOWABLE, $allow ) );
    }

    /** Only a disposable validation copy may stand in for belmains.com. */
    private static function isolated(): bool {
        global $wpdb;
        return 'local' === wp_get_environment_type() && 'bcrm_validation_' === $wpdb->prefix;
    }

    private function note( string $level, string $subject, string $detail ): void { $this->lines[] = array( $level, $subject, $detail ); }
    private function ok( string $subject, string $detail ): void { $this->note( 'OK', $subject, $detail ); }
    private function warn( string $subject, string $detail ): void { $this->note( 'ATTENTION', $subject, $detail ); }
    private function block( string $subject, string $detail ): void { $this->note( 'BLOQUANT', $subject, $detail ); }

    public function lines(): array { return $this->lines; }
    public function count( string $level ): int { return count( array_filter( $this->lines, static function ( $line ) use ( $level ) { return $level === $line[0]; } ) ); }

    private static function atomic_write( string $path, string $bytes, int $mode ): void {
        $temp = dirname( $path ) . '/.' . basename( $path ) . '-' . bin2hex( random_bytes( 6 ) ) . '.tmp';
        $handle = fopen( $temp, 'x' );
        if ( false === $handle ) { throw new RuntimeException( 'Cannot create a staging file.' ); }
        try {
            chmod( $temp, $mode );
            if ( strlen( $bytes ) !== fwrite( $handle, $bytes ) || ! fflush( $handle ) ) { throw new RuntimeException( 'Staging write failed.' ); }
            if ( function_exists( 'fsync' ) ) { fsync( $handle ); }
        } catch ( Throwable $error ) { fclose( $handle ); unlink( $temp ); throw $error; }
        fclose( $handle );
        if ( ! rename( $temp, $path ) ) { unlink( $temp ); throw new RuntimeException( 'Atomic replacement failed.' ); }
    }

    /** Head of the reviewed checkout, read without running Git. */
    private function source_head(): string {
        $git = $this->state . '/source/.git';
        $head = is_file( $git . '/HEAD' ) ? trim( (string) file_get_contents( $git . '/HEAD' ) ) : '';
        if ( preg_match( '#\Aref: (refs/heads/[A-Za-z0-9._/-]+)\z#', $head, $match ) ) {
            $ref = $git . '/' . $match[1];
            $head = is_file( $ref ) ? trim( (string) file_get_contents( $ref ) ) : '';
            if ( '' === $head && is_file( $git . '/packed-refs' ) ) {
                foreach ( file( $git . '/packed-refs', FILE_IGNORE_NEW_LINES ) as $line ) {
                    if ( preg_match( '/\A([a-f0-9]{40}) ' . preg_quote( $match[1], '/' ) . '\z/', $line, $packed ) ) { $head = $packed[1]; break; }
                }
            }
        }
        return preg_match( '/\A[a-f0-9]{40}\z/', $head ) ? $head : '';
    }

    /** Receiver side: recorded revision and mode, drift, reviewed checkout. Returns the baseline or null. */
    private function receiver(): ?array {
        foreach ( array( $this->state, $this->guard, $this->current, $this->site . '/wp-content/mu-plugins' ) as $path ) {
            if ( ! file_exists( $path ) || is_link( $path ) ) {
                $this->block( 'Récepteur de publication', 'élément attendu introuvable : ' . basename( $path ) . '. L’ouverture passe par le récepteur installé sur le serveur.' );
                return null;
            }
        }
        if ( realpath( __DIR__ ) !== realpath( $this->state . '/source/wordpress/tools' ) ) {
            $this->block( 'Copie relue', 'cet outil doit être lancé depuis ~/belmains-deployment/source/wordpress/tools.' );
            return null;
        }
        try {
            $baseline = json_decode( (string) file_get_contents( $this->current ), true, 512, JSON_THROW_ON_ERROR );
            $engine = new Belmains_Deploy_Runtime( $this->site, $this->state );
            $status = $engine->status();
        } catch ( Throwable $error ) {
            $this->block( 'Récepteur de publication', 'les fichiers en ligne ne correspondent plus à l’état enregistré (' . $error->getMessage() . '). À réconcilier avant toute ouverture.' );
            return null;
        }
        if ( file_exists( $this->state . '/pending.json' ) ) { $this->block( 'Récepteur de publication', 'une reprise de publication est en attente.' ); return null; }
        $this->ok( 'Récepteur de publication', 'révision ' . substr( $status['revision'], 0, 12 ) . ', boutique ' . ( 'open' === $status['mode'] ? 'ouverte' : 'en préparation' ) . ', aucune dérive.' );
        $head = $this->source_head();
        if ( $head !== $status['revision'] ) {
            $this->block( 'Copie relue', 'la copie ~/belmains-deployment/source est sur ' . ( $head ? substr( $head, 0, 12 ) : 'une révision illisible' ) . ' alors que le site est sur ' . substr( $status['revision'], 0, 12 ) . '. La mettre à jour avant d’ouvrir.' );
        } else { $this->ok( 'Copie relue', 'identique à la révision publiée.' ); }
        $installed = is_file( $this->trusted ) && ! is_link( $this->trusted ) ? hash_file( 'sha256', $this->trusted ) : '';
        if ( $installed !== hash_file( 'sha256', __DIR__ . '/deploy-runtime.php' ) ) {
            $this->warn( 'Moteur de publication', 'la copie de confiance du serveur est antérieure : elle sera remplacée pendant l’ouverture, sinon les publications suivantes seraient annulées.' );
        } else { $this->ok( 'Moteur de publication', 'copie de confiance à jour.' ); }
        return $baseline;
    }

    private function payments(): void {
        $enabled = array();
        foreach ( WC()->payment_gateways()->payment_gateways() as $gateway ) {
            if ( 'yes' === $gateway->enabled ) { $enabled[] = $gateway->id; }
        }
        $requested = $this->requested_gateways();
        if ( ! $enabled && ! $requested ) {
            $this->block( 'Paiement', 'aucun moyen de paiement activé. L’activer dans WooCommerce → Paiements, ou le nommer avec BELMAINS_SHOP_ENABLE_GATEWAYS.' );
            return;
        }
        $this->ok( 'Paiement', 'activé : ' . ( $enabled ? implode( ', ', $enabled ) : 'aucun pour l’instant' ) . ( $requested ? ' ; à activer à l’ouverture : ' . implode( ', ', $requested ) : '' ) . '.' );
        $revolut = get_option( 'woocommerce_revolut_settings' );
        $uses_revolut = (bool) array_filter( array_merge( $enabled, $requested ), static function ( $id ) { return 0 === strpos( $id, 'revolut' ); } );
        if ( $uses_revolut && is_array( $revolut ) && isset( $revolut['mode'] ) && 'live' !== $revolut['mode'] ) {
            $this->warn( 'Paiement', 'Revolut est en mode « ' . sanitize_key( (string) $revolut['mode'] ) . ' » : aucun encaissement réel.' );
        }
        $this->warn( 'Paiement', 'aucun paiement réel n’a encore été testé sur ce site. Passer une vraie commande puis la rembourser juste après l’ouverture.' );
    }

    /** Gateways named for activation; an unknown name blocks rather than being ignored. */
    private function requested_gateways(): array {
        $names = array_values( array_filter( array_map( 'sanitize_key', explode( ',', (string) getenv( 'BELMAINS_SHOP_ENABLE_GATEWAYS' ) ) ) ) );
        $known = array_keys( WC()->payment_gateways()->payment_gateways() );
        foreach ( array_diff( $names, $known ) as $unknown ) { $this->block( 'Paiement', 'moyen de paiement inconnu : ' . $unknown . '. Connus : ' . implode( ', ', $known ) . '.' ); }
        return array_values( array_intersect( $names, $known ) );
    }

    private function taxes(): void {
        global $wpdb;
        $rates = $wpdb->get_results( "SELECT tax_rate_country AS country, tax_rate AS rate FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_class = '' ORDER BY tax_rate_country", ARRAY_A ) ?: array();
        $list = implode( ', ', array_map( static function ( $rate ) { return ( $rate['country'] ?: 'tous pays' ) . ' ' . rtrim( rtrim( number_format( (float) $rate['rate'], 2, ',', '' ), '0' ), ',' ) . ' %'; }, $rates ) );
        $active = 'yes' === get_option( 'woocommerce_calc_taxes' );
        $detail = 'calcul ' . ( $active ? 'activé' : 'DÉSACTIVÉ' ) . ', ' . count( $rates ) . ' taux standard' . ( $list ? ' (' . $list . ')' : '' ) . ', prix saisis ' . ( 'yes' === get_option( 'woocommerce_prices_include_tax' ) ? 'TTC' : 'HT' ) . ', taxe selon ' . get_option( 'woocommerce_tax_based_on' ) . '.';
        if ( $active && $rates ) { $this->ok( 'TVA', $detail ); return; }
        if ( in_array( 'taxes', $this->allow, true ) ) { $this->warn( 'TVA', $detail . ' Ouverture acceptée sans TVA calculée (BELMAINS_SHOP_ALLOW=taxes).' ); return; }
        $this->block( 'TVA', $detail . ' Les commandes seraient enregistrées sans TVA. Appliquer d’abord tools/configure-vat.php, ou accepter avec BELMAINS_SHOP_ALLOW=taxes.' );
    }

    private function shipping(): void {
        $zones = WC_Shipping_Zones::get_zones();
        $zones[] = array( 'zone_name' => 'Reste du monde', 'id' => 0 );
        $found = array();
        foreach ( $zones as $entry ) {
            $zone = new WC_Shipping_Zone( $entry['id'] ?? 0 );
            $methods = array_map( static function ( $method ) { return $method->get_title(); }, $zone->get_shipping_methods( true ) );
            if ( $methods ) { $found[] = $entry['zone_name'] . ' : ' . implode( ', ', $methods ); }
        }
        if ( ! $found ) { $this->block( 'Livraison', 'aucune méthode de livraison active : la commande serait impossible.' ); return; }
        $this->ok( 'Livraison', implode( ' | ', $found ) . '. Vente vers : ' . implode( ', ', array_keys( WC()->countries->get_allowed_countries() ) ) . '.' );
    }

    private function product(): void {
        $id = absint( get_option( 'belmains_product_id' ) );
        $product = $id ? wc_get_product( $id ) : false;
        if ( ! $product ) { $this->block( 'Produit', 'aucun produit Belmains configuré.' ); return; }
        $problems = array();
        if ( 'publish' !== $product->get_status() ) { $problems[] = 'statut « ' . $product->get_status() . ' »'; }
        if ( (float) $product->get_price( 'edit' ) <= 0 ) { $problems[] = 'prix absent'; }
        if ( 'instock' !== $product->get_stock_status( 'edit' ) || ( $product->get_manage_stock( 'edit' ) && (int) $product->get_stock_quantity( 'edit' ) < 1 ) ) { $problems[] = 'stock épuisé'; }
        if ( 'EUR' !== get_woocommerce_currency() ) { $problems[] = 'devise ' . get_woocommerce_currency(); }
        $detail = '« ' . $product->get_name() . ' », référence ' . ( $product->get_sku( 'edit' ) ?: 'absente' ) . ', ' . wc_format_localized_price( $product->get_price( 'edit' ) ) . ' €, stock ' . ( $product->get_manage_stock( 'edit' ) ? (int) $product->get_stock_quantity( 'edit' ) : 'non suivi' ) . '.';
        if ( $problems ) { $this->block( 'Produit', $detail . ' Problème : ' . implode( ', ', $problems ) . '.' ); } else { $this->ok( 'Produit', $detail ); }
    }

    /** Text between brackets that is not a registered shortcode: an unfilled template field. */
    private static function placeholders( string $content ): array {
        preg_match_all( '/\[([^\[\]]{3,200})\]/u', $content, $matches );
        return array_values( array_filter( $matches[1], static function ( $inside ) {
            $tag = strtolower( (string) strtok( trim( $inside, '/ ' ), " \t\n=" ) );
            return ! shortcode_exists( $tag );
        } ) );
    }

    /** @return array<string,int> page identifiers still in draft, ready to publish */
    private function legal_pages(): array {
        $drafts = array();
        foreach ( self::LEGAL as $kind => $title ) {
            $id = absint( get_option( 'belmains_' . $kind . '_page_id' ) );
            $page = $id ? get_post( $id ) : null;
            if ( ! $page || 'page' !== $page->post_type || ! in_array( $page->post_status, array( 'draft', 'publish' ), true ) ) {
                $this->block( $title, 'page absente ou hors d’usage. La créer et la rédiger avant l’ouverture.' ); continue;
            }
            $text = wp_strip_all_tags( $page->post_content );
            $missing = self::placeholders( $page->post_content );
            if ( false !== stripos( $text, 'Document de travail' ) || $missing ) {
                $this->block( $title, 'page ' . $id . ' encore à l’état de modèle' . ( $missing ? ' : ' . count( $missing ) . ' champ(s) entre crochets, par exemple « [' . mb_substr( $missing[0], 0, 60 ) . '] »' : '' ) . '. La compléter avant de la publier.' );
                continue;
            }
            if ( mb_strlen( $text ) < 400 ) { $this->block( $title, 'page ' . $id . ' presque vide (' . mb_strlen( $text ) . ' caractères).' ); continue; }
            if ( 'privacy' === $kind && class_exists( 'BCRM_Meta' ) && '' !== BCRM_Meta::pixel_id() && false === stripos( $text, 'Meta' ) ) {
                $this->block( $title, 'page ' . $id . ' : aucune mention de Meta alors que le pixel est connecté. Reprendre le texte proposé dans Réglages → Confidentialité.' );
                continue;
            }
            if ( 'terms' === $kind && false === stripos( $text, 'Renoncer au contrat' ) ) {
                $this->block( $title, 'page ' . $id . ' : aucune mention de la fonction de rétractation en ligne « Renoncer au contrat ici », que les conditions de vente doivent signaler.' );
                continue;
            }
            if ( 'draft' === $page->post_status ) { $drafts[ $kind ] = $id; $this->ok( $title, 'page ' . $id . ' rédigée, en brouillon : elle sera publiée à l’ouverture.' ); }
            else { $this->ok( $title, 'page ' . $id . ' déjà publiée.' ); }
        }
        return $drafts + $this->withdrawal_page();
    }

    /** Online withdrawal function, mandatory for distance contracts since 19 June 2026. */
    private function withdrawal_page(): array {
        $subject = 'Rétractation en ligne';
        $id = absint( get_option( 'belmains_withdrawal_page_id' ) );
        $page = $id ? get_post( $id ) : null;
        if ( ! shortcode_exists( 'belmains_retractation' ) ) { $this->block( $subject, 'fonction absente du site : publier Belmains CRM 0.5.0 ou plus récent.' ); return array(); }
        if ( ! $page || 'page' !== $page->post_type || ! in_array( $page->post_status, array( 'draft', 'publish' ), true ) || ! has_shortcode( $page->post_content, 'belmains_retractation' ) ) {
            $this->block( $subject, 'page du formulaire « Renoncer au contrat ici » absente. La créer avec tools/publish-legal.php avant l’ouverture.' ); return array();
        }
        if ( 'publish' === $page->post_status ) { $this->ok( $subject, 'page ' . $id . ' publiée.' ); return array(); }
        $this->ok( $subject, 'page ' . $id . ' prête, en brouillon : elle sera publiée à l’ouverture.' );
        return array( 'withdrawal' => $id );
    }

    /** What visitors will read once the showcase no longer overrides the editor's own texts. */
    private function storefront(): void {
        if ( ! class_exists( 'Belmains_Builder' ) ) { return; }
        $values = Belmains_Builder::view()['values'] ?? array();
        $excerpt = static function ( $value ) { return mb_substr( trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( is_string( $value ) ? $value : wp_json_encode( $value, JSON_UNESCAPED_UNICODE ) ) ) ), 0, 170 ); };
        foreach ( array( 'announcement' => 'Bandeau du haut', 'delivery_text' => 'Texte « Livraison et retours »', 'legal_text' => 'Texte « Mentions légales »' ) as $key => $label ) {
            $text = $excerpt( $values[ $key ] ?? '' );
            if ( preg_match( '/ouverture prochaine|lors de l’ouverture|lors de l\'ouverture|point relais|48\s?h/iu', $text ) ) { $this->warn( $label, 'à relire dans l’éditeur, il deviendra public : « ' . $text . ' »' ); }
            else { $this->ok( $label, '« ' . $text . ' »' ); }
        }
        if ( ! empty( $values['reviews_enabled'] ) && 'real' !== ( $values['reviews_kind'] ?? 'demo' ) ) {
            $this->warn( 'Témoignages', 'affichés comme « exemples pour la maquette ». Les passer en avis réels dans l’éditeur, ou les masquer.' );
        }
    }

    private function settings(): void {
        global $wpdb;
        $soon = get_option( 'woocommerce_coming_soon' );
        $this->note( 'yes' === $soon ? 'ATTENTION' : 'OK', 'Mode « bientôt disponible »', 'yes' === $soon ? 'actif dans WooCommerce : il sera désactivé à l’ouverture.' : 'inactif.' );
        $public = (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'blog_public' ) );
        $this->note( '1' === $public ? 'OK' : 'ATTENTION', 'Référencement', '1' === $public ? 'le site est indexable.' : 'le site reste masqué aux moteurs de recherche' . ( '1' === getenv( 'BELMAINS_SHOP_INDEX' ) ? ' : il sera rendu indexable à l’ouverture.' : ' (ajouter BELMAINS_SHOP_INDEX=1 pour le rendre indexable).' ) );
        $from = (string) get_option( 'woocommerce_email_from_address' );
        $this->note( $from && function_exists( 'wp_mail_smtp' ) ? 'OK' : 'ATTENTION', 'E-mails', 'expéditeur ' . ( $from ?: 'absent' ) . ', WP Mail SMTP ' . ( function_exists( 'wp_mail_smtp' ) ? 'actif' : 'inactif' ) . '. Les e-mails de commande partiront dès l’ouverture.' );
        $contact = absint( get_option( 'belmains_contact_page_id' ) );
        if ( ! $contact || 'publish' !== get_post_status( $contact ) ) { $this->warn( 'Contact', 'page de contact non publiée.' ); }
        if ( class_exists( 'BCRM_Meta' ) ) {
            $pixel = BCRM_Meta::pixel_id();
            $this->ok( 'Publicité Meta', $pixel ? 'pixel ' . $pixel . ' connecté : il mesurera les visiteurs qui acceptent la publicité dès l’ouverture.' : 'extension non connectée : aucune mesure.' );
        }
        $google = (bool) array_filter( (array) get_option( 'active_plugins', array() ), static function ( $plugin ) { return 0 === strpos( (string) $plugin, 'google-listings-and-ads/' ); } );
        if ( $google ) {
            $linked = absint( get_option( 'gla_ads_id' ) );
            $detail = 'extension Google for WooCommerce active' . ( $linked ? ' et reliée à Google Ads : sa balise se chargerait pour tous les visiteurs, sans passer par le bandeau de consentement.' : ', sans compte Google Ads relié.' );
            if ( $linked && ! in_array( 'google', $this->allow, true ) ) { $this->block( 'Google', $detail . ' La désactiver, ou accepter avec BELMAINS_SHOP_ALLOW=google.' ); }
            else { $this->warn( 'Google', $detail ); }
        }
        if ( class_exists( 'ActionScheduler' ) && function_exists( 'as_get_scheduled_actions' ) ) {
            $ids = as_get_scheduled_actions( array( 'status' => ActionScheduler_Store::STATUS_PENDING, 'date' => as_get_datetime_object(), 'date_compare' => '<=', 'per_page' => 200 ), 'ids' );
            $hooks = array();
            foreach ( $ids as $id ) { $hook = ActionScheduler::store()->fetch_action( $id )->get_hook(); $hooks[ $hook ] = ( $hooks[ $hook ] ?? 0 ) + 1; }
            arsort( $hooks );
            if ( $ids ) { $this->warn( 'Tâches en attente', count( $ids ) . ( 200 === count( $ids ) ? ' ou plus' : '' ) . ' tâche(s) en retard s’exécuteront à l’ouverture : ' . implode( ', ', array_map( static function ( $hook, $count ) { return $hook . ' ×' . $count; }, array_keys( array_slice( $hooks, 0, 8, true ) ), array_slice( $hooks, 0, 8, true ) ) ) . '.' ); }
        }
    }

    /** Every check, in reading order. Returns the baseline and the legal drafts to publish. */
    private function inspect(): array {
        $baseline = $this->receiver();
        $this->payments(); $this->taxes(); $this->shipping(); $this->product();
        $drafts = $this->legal_pages();
        $this->storefront(); $this->settings();
        return array( $baseline, $drafts );
    }

    public function check(): void { $this->inspect(); }

    private function requirements( string $revision ): void {
        $host = wp_parse_url( home_url(), PHP_URL_HOST ); $scheme = wp_parse_url( home_url(), PHP_URL_SCHEME );
        if ( ! self::isolated() && ( 'belmains.com' !== $host || 'https' !== $scheme ) ) { throw new RuntimeException( 'Cet outil n’agit que sur https://belmains.com.' ); }
        if ( ! preg_match( '/\A[a-f0-9]{40}\z/', $revision ) ) { throw new RuntimeException( 'BELMAINS_SHOP_REVISION doit être la révision complète publiée (40 caractères).' ); }
        if ( ! class_exists( 'WooCommerce' ) || 'belmains' !== get_stylesheet() ) { throw new RuntimeException( 'WooCommerce et le thème Belmains doivent être actifs.' ); }
    }

    /** Both receiver locks, in the receiver's own order, so no publication runs during the change. */
    private function locked( callable $work ) {
        $trigger = fopen( $this->state . '/trigger.lock', 'c' ); $deployment = fopen( $this->state . '/lock', 'c' );
        if ( false === $trigger || false === $deployment ) { throw new RuntimeException( 'Verrous du récepteur inaccessibles.' ); }
        try {
            if ( ! flock( $trigger, LOCK_EX | LOCK_NB ) || ! flock( $deployment, LOCK_EX | LOCK_NB ) ) { throw new RuntimeException( 'Une publication est en cours : réessayer dans quelques minutes.' ); }
            return $work();
        } finally { fclose( $deployment ); fclose( $trigger ); }
    }

    private function backup_dir( string $kind ): string {
        $root = $this->state . '/opening-backups';
        if ( is_link( $root ) || ( ! is_dir( $root ) && ! mkdir( $root, 0700 ) ) ) { throw new RuntimeException( 'Dossier de sauvegarde privé indisponible.' ); }
        chmod( $root, 0700 );
        $dir = $root . '/' . gmdate( 'Ymd-His' ) . '-' . $kind . '-' . bin2hex( random_bytes( 4 ) );
        if ( ! mkdir( $dir, 0700 ) ) { throw new RuntimeException( 'Sauvegarde privée impossible.' ); }
        return $dir;
    }

    /** Swap the guard and the recorded state together; the receiver must accept the result. */
    private function switch_guard( string $candidate_file, string $mode, array $baseline ): void {
        $candidate = (string) file_get_contents( $candidate_file );
        if ( '' === $candidate ) { throw new RuntimeException( 'Garde candidat illisible.' ); }
        token_get_all( $candidate, TOKEN_PARSE );
        $runtime = (string) file_get_contents( __DIR__ . '/deploy-runtime.php' );
        if ( ! is_file( $this->trusted ) || hash( 'sha256', $runtime ) !== hash_file( 'sha256', $this->trusted ) ) {
            token_get_all( $runtime, TOKEN_PARSE );
            self::atomic_write( $this->trusted, $runtime, is_file( $this->trusted ) ? fileperms( $this->trusted ) & 0777 : 0600 );
        }
        $next = $baseline; $next['guard_sha256'] = hash( 'sha256', $candidate );
        if ( 'open' === $mode ) { $next['mode'] = 'open'; } else { unset( $next['mode'] ); }
        self::atomic_write( $this->guard, $candidate, 0644 );
        self::atomic_write( $this->current, json_encode( $next, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ), 0600 );
        if ( is_callable( 'opcache_invalidate' ) ) { @opcache_invalidate( $this->guard, true ); @opcache_invalidate( $this->trusted, true ); }
        $status = ( new Belmains_Deploy_Runtime( $this->site, $this->state ) )->status();
        if ( $mode !== $status['mode'] || $next['guard_sha256'] !== $status['guard_sha256'] || hash_file( 'sha256', $this->trusted ) !== hash( 'sha256', $runtime ) ) {
            throw new RuntimeException( 'Le récepteur ne reconnaît pas le nouvel état.' );
        }
    }

    public function open( string $revision ): array {
        $this->requirements( $revision );
        return $this->locked( function () use ( $revision ) {
            list( $baseline, $drafts ) = $this->inspect();
            if ( null === $baseline ) { throw new RuntimeException( 'Ouverture refusée : le récepteur de publication n’est pas dans un état utilisable. Rien n’a été modifié.' ); }
            if ( $revision !== $baseline['revision'] ) { throw new RuntimeException( 'La révision indiquée n’est pas celle qui est publiée.' ); }
            if ( 'open' === Belmains_Deploy_Runtime::mode( $baseline ) ) { throw new RuntimeException( 'La boutique est déjà ouverte.' ); }
            if ( $this->count( 'BLOQUANT' ) ) { throw new RuntimeException( 'Ouverture refusée : ' . $this->count( 'BLOQUANT' ) . ' point(s) bloquant(s). Rien n’a été modifié.' ); }
            $gateways = $this->requested_gateways();
            $before = array(
                'guard' => (string) file_get_contents( $this->guard ), 'state' => (string) file_get_contents( $this->current ),
                'runtime' => is_file( $this->trusted ) ? (string) file_get_contents( $this->trusted ) : null,
                'options' => array(), 'pages' => array(), 'gateways' => array(),
            );
            $dir = $this->backup_dir( 'open' );
            self::atomic_write( $dir . '/guard.php', $before['guard'], 0600 );
            self::atomic_write( $dir . '/current.json', $before['state'], 0600 );
            if ( null !== $before['runtime'] ) { self::atomic_write( $dir . '/deploy-runtime.php', $before['runtime'], 0600 ); }
            $options = array( 'woocommerce_coming_soon' => 'no' );
            if ( '1' === getenv( 'BELMAINS_SHOP_INDEX' ) ) { $options['blog_public'] = '1'; }
            // The checkout and the consent banner link to these two pages: an empty or unpublished target is replaced.
            foreach ( array( 'woocommerce_terms_page_id' => 'terms', 'wp_page_for_privacy_policy' => 'privacy' ) as $name => $kind ) {
                $configured = absint( get_option( $name ) ); $own = absint( get_option( 'belmains_' . $kind . '_page_id' ) );
                if ( $configured !== $own && 'publish' !== get_post_status( $configured ) ) { $options[ $name ] = $own; }
            }
            global $wpdb; $sentinel = '__belmains_absent__';
            foreach ( $options as $name => $_ ) {
                $stored = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );
                $before['options'][ $name ] = null === $stored ? $sentinel : $stored;
            }
            foreach ( $drafts as $id ) { $before['pages'][ $id ] = 'draft'; }
            foreach ( $gateways as $id ) { $before['gateways'][ $id ] = WC()->payment_gateways()->payment_gateways()[ $id ]->enabled; }
            self::atomic_write( $dir . '/settings.json', wp_json_encode( array( 'revision' => $revision, 'options' => $before['options'], 'pages' => $before['pages'], 'gateways' => $before['gateways'] ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ), 0600 );
            try {
                $this->switch_guard( __DIR__ . '/open-guard.php', 'open', $baseline );
                foreach ( $options as $name => $value ) {
                    update_option( $name, $value );
                    if ( (string) $value !== (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) ) ) { throw new RuntimeException( 'Réglage non enregistré : ' . $name ); }
                }
                foreach ( $drafts as $id ) {
                    $result = wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ), true );
                    if ( is_wp_error( $result ) || 'publish' !== get_post_status( $id ) ) { throw new RuntimeException( 'Page légale non publiée : ' . $id ); }
                }
                foreach ( $gateways as $id ) {
                    $gateway = WC()->payment_gateways()->payment_gateways()[ $id ];
                    $gateway->update_option( 'enabled', 'yes' );
                    if ( 'yes' !== $gateway->get_option( 'enabled' ) ) { throw new RuntimeException( 'Moyen de paiement non activé : ' . $id ); }
                }
            } catch ( Throwable $error ) {
                $failed = $this->restore( $before, $sentinel );
                if ( $failed ) { throw new RuntimeException( 'Ouverture échouée et retour arrière incomplet (' . implode( ', ', $failed ) . ') : sauvegarde ' . basename( $dir ), 0, $error ); }
                throw new RuntimeException( 'Ouverture annulée, état précédent rétabli : ' . $error->getMessage(), 0, $error );
            }
            return array( 'status' => 'shop_open', 'revision' => $revision, 'published_pages' => array_values( $drafts ), 'enabled_gateways' => $gateways, 'backup' => basename( $dir ), 'rest' => $this->probe( 'open' ) );
        } );
    }

    public function close( string $revision ): array {
        $this->requirements( $revision );
        return $this->locked( function () use ( $revision ) {
            $baseline = $this->receiver();
            if ( null === $baseline || $revision !== $baseline['revision'] || $this->count( 'BLOQUANT' ) ) { throw new RuntimeException( 'Fermeture refusée : le récepteur ou la révision ne correspondent pas.' ); }
            if ( 'open' !== Belmains_Deploy_Runtime::mode( $baseline ) ) { throw new RuntimeException( 'La boutique n’est pas ouverte.' ); }
            $before = array( 'guard' => (string) file_get_contents( $this->guard ), 'state' => (string) file_get_contents( $this->current ), 'runtime' => (string) file_get_contents( $this->trusted ), 'options' => array(), 'pages' => array(), 'gateways' => array() );
            $dir = $this->backup_dir( 'close' );
            self::atomic_write( $dir . '/guard.php', $before['guard'], 0600 );
            self::atomic_write( $dir . '/current.json', $before['state'], 0600 );
            try { $this->switch_guard( __DIR__ . '/public-catalog-guard.php', 'preparation', $baseline ); }
            catch ( Throwable $error ) {
                $failed = $this->restore( $before, '' );
                throw new RuntimeException( $failed ? 'Fermeture échouée et retour arrière incomplet : sauvegarde ' . basename( $dir ) : 'Fermeture annulée, la boutique reste ouverte : ' . $error->getMessage(), 0, $error );
            }
            return array( 'status' => 'shop_closed', 'revision' => $revision, 'backup' => basename( $dir ), 'rest' => $this->probe( 'preparation' ) );
        } );
    }

    /** @return string[] what could not be put back */
    private function restore( array $before, string $sentinel ): array {
        $failed = array();
        try {
            self::atomic_write( $this->guard, $before['guard'], 0644 );
            self::atomic_write( $this->current, $before['state'], 0600 );
            if ( null !== $before['runtime'] ) { self::atomic_write( $this->trusted, $before['runtime'], fileperms( $this->trusted ) & 0777 ); }
            if ( is_callable( 'opcache_invalidate' ) ) { @opcache_invalidate( $this->guard, true ); @opcache_invalidate( $this->trusted, true ); }
        } catch ( Throwable $error ) { $failed[] = 'garde/état'; }
        // Each remaining step is attempted even when another one fails; what could not be put back is named.
        foreach ( $before['options'] as $name => $value ) {
            try { if ( $sentinel === $value ) { delete_option( $name ); } else { update_option( $name, maybe_unserialize( $value ) ); } }
            catch ( Throwable $error ) { $failed[] = 'réglage ' . $name; }
        }
        foreach ( $before['pages'] as $id => $status ) {
            try { if ( is_wp_error( wp_update_post( array( 'ID' => $id, 'post_status' => $status ), true ) ) ) { $failed[] = 'page ' . $id; } }
            catch ( Throwable $error ) { $failed[] = 'page ' . $id; }
        }
        foreach ( $before['gateways'] as $id => $enabled ) {
            try { WC()->payment_gateways()->payment_gateways()[ $id ]->update_option( 'enabled', $enabled ); }
            catch ( Throwable $error ) { $failed[] = 'paiement ' . $id; }
        }
        return $failed;
    }

    /**
     * What visitors get: the web server keeps compiled PHP for a few seconds, so the new guard is
     * awaited for up to half a minute. Skipped on the isolated copy, which has no web server.
     */
    private function probe( string $mode ): string {
        if ( self::isolated() ) { return 'non vérifié (copie isolée)'; }
        $seen = 'aucune réponse';
        for ( $attempt = 0; $attempt < 10; $attempt++ ) {
            $response = wp_remote_get( home_url( '/wp-json/?belmains_probe=' . time() . $attempt ), array( 'timeout' => 20, 'redirection' => 0 ) );
            if ( is_wp_error( $response ) ) { $seen = 'injoignable : ' . $response->get_error_message(); }
            else {
                $status = (int) wp_remote_retrieve_response_code( $response ); $seen = 'HTTP ' . $status;
                if ( Belmains_Deploy_Runtime::rest_matches_mode( $mode, $status, (string) wp_remote_retrieve_body( $response ) ) ) { return $seen . ', conforme'; }
            }
            sleep( 3 );
        }
        return $seen . ' — pas encore conforme : revérifier dans une minute avec BELMAINS_SHOP=check';
    }

    public function render( string $title ): string {
        $out = "\n== " . $title . " ==\n";
        foreach ( $this->lines as $line ) { $out .= str_pad( '[' . $line[0] . ']', 12 ) . $line[1] . ' : ' . $line[2] . "\n"; }
        return $out . sprintf( "\n%d point(s) bloquant(s), %d point(s) d’attention.\n", $this->count( 'BLOQUANT' ), $this->count( 'ATTENTION' ) );
    }

    public static function main(): int {
        $action = (string) getenv( 'BELMAINS_SHOP' );
        $tool = new self( array_map( 'trim', explode( ',', (string) getenv( 'BELMAINS_SHOP_ALLOW' ) ) ) );
        try {
            require_once __DIR__ . '/deploy-runtime.php';
            if ( 'check' === $action ) {
                $tool->check();
                echo $tool->render( 'Belmains — état avant ouverture (lecture seule, rien n’a été modifié)' );
                return $tool->count( 'BLOQUANT' ) ? 1 : 0;
            }
            if ( 'open' === $action || 'close' === $action ) {
                $result = 'open' === $action ? $tool->open( (string) getenv( 'BELMAINS_SHOP_REVISION' ) ) : $tool->close( (string) getenv( 'BELMAINS_SHOP_REVISION' ) );
                echo $tool->render( 'open' === $action ? 'Belmains — contrôles avant ouverture' : 'Belmains — fermeture' );
                echo "\n" . ( 'open' === $action ? 'BOUTIQUE OUVERTE.' : 'VITRINE D’AVANT OUVERTURE REMISE EN PLACE.' ) . "\n" . wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
                return 0;
            }
            fwrite( STDERR, "Indiquer BELMAINS_SHOP=check, open ou close.\n" );
            return 2;
        } catch ( Throwable $error ) {
            echo $tool->render( 'Belmains — contrôles' );
            fwrite( STDERR, "\nARRÊT : " . $error->getMessage() . "\n" );
            return 1;
        }
    }
}
endif;

if ( ! defined( 'BELMAINS_SHOP_LIBRARY' ) ) {
    $belmains_exit = Belmains_Shop_Opening::main();
    if ( defined( 'WP_CLI' ) && WP_CLI ) { if ( $belmains_exit ) { WP_CLI::halt( $belmains_exit ); } } else { exit( $belmains_exit ); }
}
