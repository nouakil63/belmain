<?php
/**
 * Writes the reviewed legal texts (tools/legal-pages.php, facts in tools/legal-facts.php) into the
 * WordPress pages, and prepares the page of the online withdrawal function. Command line only.
 *
 *   BELMAINS_LEGAL=check    read-only report: missing facts, state of each page (default)
 *   BELMAINS_LEGAL=show     print the texts as they would be written, changes nothing
 *   BELMAINS_LEGAL=apply    save the current contents, then write the texts; statuses are kept
 *
 * Optional, for "apply":
 *   BELMAINS_LEGAL_PUBLISH=1     also publish the four text pages now; otherwise the opening tool
 *                                publishes them. The withdrawal page is always left to the opening.
 *   BELMAINS_LEGAL_OVERWRITE=1   replace a page edited by hand since the last application
 *
 * Run from the reviewed checkout ~/belmains-deployment/source/wordpress/tools, with
 * `php publish-legal.php` or `wp eval-file publish-legal.php`. Only page contents change: no file,
 * no setting, no payment, tax or shipping option. Never loaded by a web request.
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

if ( ! class_exists( 'Belmains_Legal_Pages' ) ) :
final class Belmains_Legal_Pages {
    /** kind => title, default slug */
    public const PAGES = array(
        'legal' => array( 'Mentions légales', 'mentions-legales' ),
        'terms' => array( 'Conditions générales de vente', 'conditions-generales-de-vente' ),
        'privacy' => array( 'Politique de confidentialité', 'confidentialite' ),
        'returns' => array( 'Livraison et retours', 'livraison-et-retours' ),
        'withdrawal' => array( 'Rétractation', 'retractation' ),
    );
    private const REQUIRED = array(
        'publication_director' => 'responsable de la publication', 'phone' => 'téléphone du vendeur', 'host_phone' => 'téléphone de l’hébergeur',
        'delivery_delay' => 'délai de livraison', 'return_costs' => 'frais de retour', 'payment_methods' => 'moyens de paiement',
        'mediator_name' => 'nom du médiateur de la consommation', 'mediator_address' => 'adresse du médiateur', 'mediator_url' => 'site du médiateur',
        'version_date' => 'date de la version',
    );
    private const SOURCE = '_belmains_legal_source_sha256';
    private const STORED = '_belmains_legal_stored_sha256';

    private array $facts;
    /** @var array<int, array{0:string,1:string,2:string}> level, subject, detail */
    private array $lines = array();

    public function __construct( ?array $facts = null ) { $this->facts = $facts ?? require __DIR__ . '/legal-facts.php'; }

    private function note( string $level, string $subject, string $detail ): void { $this->lines[] = array( $level, $subject, $detail ); }
    public function lines(): array { return $this->lines; }
    public function count( string $level ): int { return count( array_filter( $this->lines, static function ( $line ) use ( $level ) { return $level === $line[0]; } ) ); }

    /** @return array<string,string> missing or unusable fact => what it is */
    public function missing(): array {
        $missing = array();
        foreach ( self::REQUIRED as $key => $label ) {
            if ( ! is_string( $this->facts[ $key ] ?? null ) || '' === trim( $this->facts[ $key ] ) ) { $missing[ $key ] = $label; }
        }
        if ( ! isset( $missing['return_costs'] ) && ! in_array( $this->facts['return_costs'], array( 'customer', 'seller' ), true ) ) { $missing['return_costs'] = 'frais de retour (customer ou seller)'; }
        if ( ! isset( $missing['mediator_url'] ) && ! preg_match( '#\Ahttps://[a-z0-9.-]+\.[a-z]{2,}(?:/\S*)?\z#i', $this->facts['mediator_url'] ) ) { $missing['mediator_url'] = 'site du médiateur (adresse complète en https://)'; }
        return $missing;
    }

    private function page( string $kind ): ?WP_Post {
        $id = absint( get_option( 'belmains_' . $kind . '_page_id' ) );
        $page = $id ? get_post( $id ) : null;
        return $page && 'page' === $page->post_type && in_array( $page->post_status, array( 'draft', 'publish' ), true ) ? $page : null;
    }

    /** Address the page has, or will have once published. Without readable addresses, a page that does not exist yet has none. */
    private function address( string $kind ): string {
        $page = $this->page( $kind );
        if ( ! get_option( 'permalink_structure' ) ) { return $page ? (string) get_permalink( $page ) : home_url( '/' ); }
        return home_url( user_trailingslashit( $page && '' !== $page->post_name ? $page->post_name : self::PAGES[ $kind ][1] ) );
    }

    /** @return array<string,string> kind => HTML. Unknown facts are shown as such, for reading only. */
    public function render( bool $draft = false ): array {
        $facts = $this->facts;
        if ( $draft ) { foreach ( $this->missing() as $key => $label ) { $facts[ $key ] = 'mediator_url' === $key ? 'https://a-completer.invalid' : '⚠ À COMPLÉTER : ' . $label; } }
        elseif ( $this->missing() ) { throw new RuntimeException( 'Informations manquantes : ' . implode( ', ', $this->missing() ) . '.' ); }
        $urls = array();
        foreach ( array_keys( self::PAGES ) as $kind ) { $urls[ $kind ] = $this->address( $kind ); }
        $render = require __DIR__ . '/legal-pages.php';
        return $render( $facts, array(
            'host' => (string) wp_parse_url( home_url(), PHP_URL_HOST ), 'urls' => $urls,
            'retention_days' => class_exists( 'BCRM_App' ) ? (int) BCRM_App::settings()['retention_days'] : 90,
            'consent_label' => class_exists( 'BCRM_Meta' ) && '' !== BCRM_Meta::pixel_id() ? 'Gérer mes choix de confidentialité' : 'Mes choix de mesure d’audience',
        ) );
    }

    /** template: untouched model; applied: written by this tool and untouched since; edited: changed by hand. */
    private function state( string $kind, ?WP_Post $page, ?string $source ): string {
        if ( ! $page ) { return 'absent'; }
        $stored = hash( 'sha256', $page->post_content );
        if ( $stored === get_post_meta( $page->ID, self::STORED, true ) ) {
            return null !== $source && hash( 'sha256', $source ) === get_post_meta( $page->ID, self::SOURCE, true ) ? 'current' : 'applied';
        }
        if ( 'withdrawal' === $kind ) { return has_shortcode( $page->post_content, 'belmains_retractation' ) ? 'current' : 'edited'; }
        return false !== stripos( $page->post_content, 'Document de travail' ) ? 'template' : 'edited';
    }

    /** @return array<string, array{page:?WP_Post, state:string, source:?string}> */
    private function inspect(): array {
        $missing = $this->missing();
        foreach ( $missing as $label ) { $this->note( 'BLOQUANT', 'Information manquante', $label . ' — à renseigner dans tools/legal-facts.php.' ); }
        if ( ! $missing ) { $this->note( 'OK', 'Informations', 'toutes les informations obligatoires sont renseignées.' ); }
        if ( ! shortcode_exists( 'belmains_retractation' ) ) { $this->note( 'BLOQUANT', 'Rétractation en ligne', 'fonction absente du site : publier d’abord Belmains CRM 0.5.0 ou plus récent.' ); }
        $sources = $missing ? array() : $this->render();
        $plan = array();
        foreach ( self::PAGES as $kind => $definition ) {
            $page = $this->page( $kind );
            $state = $this->state( $kind, $page, $sources[ $kind ] ?? null );
            $plan[ $kind ] = array( 'page' => $page, 'state' => $state, 'source' => $sources[ $kind ] ?? null );
            $where = $page ? 'page ' . $page->ID . ' (' . ( 'publish' === $page->post_status ? 'publiée' : 'brouillon' ) . ')' : 'page absente';
            if ( 'absent' === $state && 'withdrawal' !== $kind ) { $this->note( 'BLOQUANT', $definition[0], 'page absente : la créer avec tools/prepare-launch.php.' ); }
            elseif ( 'absent' === $state ) { $this->note( 'OK', $definition[0], 'page absente : elle sera créée en brouillon.' ); }
            elseif ( 'edited' === $state ) { $this->note( 'ATTENTION', $definition[0], $where . ' modifiée à la main : conservée telle quelle, sauf BELMAINS_LEGAL_OVERWRITE=1.' ); }
            elseif ( 'current' === $state ) { $this->note( 'OK', $definition[0], $where . ' déjà à jour.' ); }
            else { $this->note( 'OK', $definition[0], $where . ( 'template' === $state ? ' encore à l’état de modèle : le texte sera écrit.' : ' à mettre à jour avec le texte relu.' ) ); }
        }
        return $plan;
    }

    public function check(): void { $this->inspect(); }

    private static function atomic_write( string $path, string $bytes ): void {
        $temp = dirname( $path ) . '/.' . basename( $path ) . '-' . bin2hex( random_bytes( 6 ) ) . '.tmp';
        if ( strlen( $bytes ) !== file_put_contents( $temp, $bytes, LOCK_EX ) ) { @unlink( $temp ); throw new RuntimeException( 'Sauvegarde impossible.' ); }
        chmod( $temp, 0600 );
        if ( ! rename( $temp, $path ) ) { unlink( $temp ); throw new RuntimeException( 'Sauvegarde impossible.' ); }
    }

    /** @return array{status:string, written:int[], created:int[], published:int[], kept:int[], backup:string} */
    public function apply( bool $publish = false, bool $overwrite = false ): array {
        global $wpdb;
        $isolated = 'local' === wp_get_environment_type() && 'bcrm_validation_' === $wpdb->prefix;
        if ( ! $isolated && 'https://belmains.com' !== untrailingslashit( home_url() ) ) { throw new RuntimeException( 'Cet outil n’agit que sur https://belmains.com.' ); }
        $plan = $this->inspect();
        if ( $this->count( 'BLOQUANT' ) ) { throw new RuntimeException( 'Rien n’a été modifié : ' . $this->count( 'BLOQUANT' ) . ' point(s) bloquant(s) ci-dessus.' ); }
        $state = dirname( (string) realpath( rtrim( ABSPATH, '/' ) ), 2 ) . '/belmains-deployment';
        if ( ! is_dir( $state ) || is_link( $state ) ) { throw new RuntimeException( 'Dossier privé de publication introuvable : aucune sauvegarde possible, rien n’a été modifié.' ); }
        $dir = $state . '/legal-backups/' . gmdate( 'Ymd\THis\Z' ) . '-' . bin2hex( random_bytes( 3 ) );
        if ( ! is_dir( $dir ) && ! mkdir( $dir, 0700, true ) ) { throw new RuntimeException( 'Sauvegarde impossible, rien n’a été modifié.' ); }
        $saved = array();
        foreach ( $plan as $kind => $item ) {
            if ( $item['page'] ) { $saved[ $kind ] = array( 'id' => $item['page']->ID, 'title' => $item['page']->post_title, 'status' => $item['page']->post_status, 'modified' => $item['page']->post_modified_gmt, 'content' => $item['page']->post_content ); }
        }
        self::atomic_write( $dir . '/pages.json', wp_json_encode( $saved, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n" );

        $result = array( 'status' => 'legal_pages_written', 'written' => array(), 'created' => array(), 'published' => array(), 'kept' => array(), 'backup' => basename( $dir ) );
        if ( ! $plan['withdrawal']['page'] ) {
            // The texts print the address of this page: it must exist before they are rendered.
            $id = wp_insert_post( wp_slash( array( 'post_type' => 'page', 'post_title' => self::PAGES['withdrawal'][0], 'post_name' => self::PAGES['withdrawal'][1], 'post_content' => $plan['withdrawal']['source'], 'post_status' => 'draft' ) ), true );
            if ( is_wp_error( $id ) ) { throw new RuntimeException( 'Création impossible (' . self::PAGES['withdrawal'][0] . ') : ' . $id->get_error_message() ); }
            update_option( 'belmains_withdrawal_page_id', $id, false );
            $result['created'][] = $id;
            $report = $this->lines;
            $plan = $this->inspect();
            $this->lines = $report;
        }
        foreach ( $plan as $kind => $item ) {
            $page = $item['page'];
            if ( 'edited' === $item['state'] && ! $overwrite ) {
                $result['kept'][] = $page->ID; continue;
            } elseif ( 'current' === $item['state'] ) {
                $id = $page->ID;
            } else {
                $id = wp_update_post( wp_slash( array( 'ID' => $page->ID, 'post_content' => $item['source'] ) ), true );
                if ( is_wp_error( $id ) ) { throw new RuntimeException( 'Écriture impossible (page ' . $page->ID . ') : ' . $id->get_error_message() . '. Sauvegarde : ' . basename( $dir ) ); }
                $result['written'][] = $id;
            }
            clean_post_cache( $id );
            $content = (string) get_post_field( 'post_content', $id, 'raw' );
            if ( 'withdrawal' !== $kind && mb_strlen( wp_strip_all_tags( $content ) ) < 0.9 * mb_strlen( wp_strip_all_tags( $item['source'] ) ) ) {
                throw new RuntimeException( 'Le texte enregistré est tronqué (page ' . $id . '). Sauvegarde : ' . basename( $dir ) );
            }
            update_post_meta( $id, self::SOURCE, hash( 'sha256', $item['source'] ) );
            update_post_meta( $id, self::STORED, hash( 'sha256', $content ) );
            if ( $publish && 'withdrawal' !== $kind && 'publish' !== get_post_status( $id ) ) {
                $done = wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ), true );
                if ( is_wp_error( $done ) || 'publish' !== get_post_status( $id ) ) { throw new RuntimeException( 'Publication impossible (page ' . $id . ').' ); }
                $result['published'][] = $id;
            }
        }
        return $result;
    }

    /** Plain reading copy of the texts, with unknown facts marked. */
    public function show(): string {
        $out = '';
        foreach ( $this->render( true ) as $kind => $html ) {
            $text = preg_replace( '#<(?:/p|/h2|/li|br ?/?|/div|/ul)>#', "\n", $html );
            $out .= "\n================ " . self::PAGES[ $kind ][0] . " ================\n" . trim( html_entity_decode( wp_strip_all_tags( preg_replace( '#<h2>#', "\n## ", $text ) ), ENT_QUOTES ) ) . "\n";
        }
        return $out;
    }
}
endif;

if ( defined( 'BELMAINS_LEGAL_LIBRARY' ) ) { return; }

$belmains_mode = getenv( 'BELMAINS_LEGAL' ) ?: 'check';
$belmains_tool = new Belmains_Legal_Pages();
$belmains_print = static function ( Belmains_Legal_Pages $tool ): void {
    foreach ( $tool->lines() as $line ) { echo str_pad( $line[0], 10 ) . ' ' . $line[1] . ' : ' . $line[2] . "\n"; }
};
try {
    if ( 'check' === $belmains_mode ) {
        $belmains_tool->check(); $belmains_print( $belmains_tool );
        echo "\n" . ( $belmains_tool->count( 'BLOQUANT' ) ? $belmains_tool->count( 'BLOQUANT' ) . ' point(s) bloquant(s) : les pages ne peuvent pas encore être écrites.' : 'Prêt : BELMAINS_LEGAL=apply écrira les textes.' ) . " Rien n’a été modifié.\n";
        exit( $belmains_tool->count( 'BLOQUANT' ) ? 1 : 0 );
    }
    if ( 'show' === $belmains_mode ) { echo $belmains_tool->show(); exit( 0 ); }
    if ( 'apply' !== $belmains_mode ) { throw new RuntimeException( 'BELMAINS_LEGAL doit valoir check, show ou apply.' ); }
    try { $belmains_result = $belmains_tool->apply( '1' === getenv( 'BELMAINS_LEGAL_PUBLISH' ), '1' === getenv( 'BELMAINS_LEGAL_OVERWRITE' ) ); }
    finally { $belmains_print( $belmains_tool ); }
    echo "\n" . wp_json_encode( $belmains_result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
    echo "Textes écrits. Sauvegarde des contenus précédents : ~/belmains-deployment/legal-backups/" . $belmains_result['backup'] . "\n";
} catch ( Throwable $belmains_error ) {
    fwrite( STDERR, "\nREFUS : " . $belmains_error->getMessage() . "\n" );
    exit( 1 );
}
