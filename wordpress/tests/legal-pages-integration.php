<?php
/** Isolated validation of tools/publish-legal.php. Run through WP-CLI eval-file only. */
global $wpdb;
if ( 'bcrm_validation_' !== $wpdb->prefix || 'local' !== wp_get_environment_type() ) {
    throw new RuntimeException( 'Legal page tests require the isolated local validation tables.' );
}
if ( ! class_exists( 'BCRM_Withdrawal' ) ) { throw new RuntimeException( 'Belmains CRM 0.5.0 or later must be active.' ); }
define( 'BELMAINS_LEGAL_LIBRARY', true );
$tools = dirname( __DIR__ ) . '/tools';
require_once $tools . '/publish-legal.php';

$checks = 0;
$check = static function ( $condition, $label ) use ( &$checks ) {
    if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $label ); }
    ++$checks; echo 'PASS ' . $label . "\n";
};
$refuses = static function ( callable $action, $needle, $label ) use ( $check ) {
    try { $action(); $message = ''; } catch ( RuntimeException $error ) { $message = $error->getMessage(); }
    $check( '' !== $message && false !== strpos( $message, $needle ), $label );
};
$subjects = static function ( Belmains_Legal_Pages $tool, $level ) { return array_values( array_map( static function ( $line ) { return $line[1]; }, array_filter( $tool->lines(), static function ( $line ) use ( $level ) { return $level === $line[0]; } ) ) ); };
$kinds = array( 'legal', 'terms', 'privacy', 'returns' );
$saved = array( 'user' => get_current_user_id() );
foreach ( array_merge( $kinds, array( 'withdrawal' ) ) as $kind ) { $saved['options'][ $kind ] = get_option( 'belmains_' . $kind . '_page_id', null ); }
$state = dirname( (string) realpath( rtrim( ABSPATH, '/' ) ), 2 ) . '/belmains-deployment';
$made_state = ! is_dir( $state );
$known_backups = is_dir( $state . '/legal-backups' ) ? scandir( $state . '/legal-backups' ) : array();
$pages = array();
$repository = require $tools . '/legal-facts.php';
$complete = array_merge( $repository, array( 'host_phone' => '+41 00 000 00 00', 'mediator_name' => 'Médiateur de validation', 'mediator_address' => '1 rue de la Validation, 75001 Paris', 'mediator_url' => 'https://mediateur-validation.example' ) );
$raw = static function ( $id ) { clean_post_cache( $id ); return (string) get_post_field( 'post_content', $id, 'raw' ); };
$placeholders = static function ( $content ) {
    preg_match_all( '/\[([^\[\]]{3,200})\]/u', $content, $matches );
    return array_values( array_filter( $matches[1], static function ( $inside ) { return ! shortcode_exists( strtolower( (string) strtok( trim( $inside, '/ ' ), " \t\n=" ) ) ); } ) );
};

try {
    // The command line run has no logged-in user: the texts must survive WordPress's own filtering.
    wp_set_current_user( 0 );
    if ( $made_state ) { mkdir( $state, 0700, true ); }
    $template = '<p><strong>Document de travail — à compléter et valider avant publication.</strong> Les éléments entre crochets ne sont pas des informations confirmées.</p><p>[Identité du vendeur]</p>';
    foreach ( $kinds as $kind ) {
        $id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => Belmains_Legal_Pages::PAGES[ $kind ][0] . ' (validation)', 'post_name' => 'validation-' . Belmains_Legal_Pages::PAGES[ $kind ][1], 'post_content' => $template ), true );
        if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
        $pages[ $kind ] = $id; update_option( 'belmains_' . $kind . '_page_id', $id, false );
    }
    delete_option( 'belmains_withdrawal_page_id' );

    // A fact that is not known is never replaced by a guess.
    $tool = new Belmains_Legal_Pages( array_merge( $complete, array( 'mediator_name' => null, 'return_costs' => 'maybe', 'mediator_url' => 'pas une adresse' ) ) );
    $named = array_keys( $tool->missing() ); sort( $named );
    $check( array( 'mediator_name', 'mediator_url', 'return_costs' ) === $named, 'missing or unusable facts are named' );
    $tool->check();
    $check( 3 === count( array_keys( $subjects( $tool, 'BLOQUANT' ), 'Information manquante', true ) ), 'the report blocks on each missing fact' );
    $refuses( static function () use ( $tool ) { $tool->apply(); }, 'Rien n’a été modifié', 'nothing is written while a fact is missing' );
    $check( $template === $raw( $pages['terms'] ) && ! get_option( 'belmains_withdrawal_page_id' ), 'a refusal leaves the pages untouched' );
    $check( false !== strpos( $tool->show(), 'À COMPLÉTER : nom du médiateur' ), 'the reading copy marks what is still missing' );

    // Texts.
    $tool = new Belmains_Legal_Pages( $complete );
    $texts = $tool->render();
    $plain = array_map( 'wp_strip_all_tags', $texts );
    $check( array( 'legal', 'terms', 'privacy', 'returns', 'withdrawal' ) === array_keys( $texts ) && '[belmains_retractation]' === $texts['withdrawal'], 'four texts and the withdrawal form page are produced' );
    $ready = true;
    foreach ( $kinds as $kind ) { $ready = $ready && mb_strlen( $plain[ $kind ] ) > 400 && false === stripos( $plain[ $kind ], 'Document de travail' ) && false === strpos( $plain[ $kind ], 'À COMPLÉTER' ) && array() === $placeholders( $texts[ $kind ] ); }
    $check( $ready, 'no text keeps a template notice, a bracket field or a missing fact' );
    $check( false !== strpos( $plain['legal'], 'ECO EXPRESS LIMITED' ) && false !== strpos( $plain['legal'], '05080017' ) && false !== strpos( $plain['legal'], 'Isabelle Ecolivet' ) && false !== strpos( $plain['legal'], '06 51 21 27 40' ) && false !== strpos( $plain['legal'], 'Infomaniak Network SA' ) && false !== strpos( $plain['legal'], '+41 00 000 00 00' ), 'legal notice: publisher, registration, publication manager, telephone and host' );
    $check( false !== strpos( $plain['terms'], 'Renoncer au contrat ici' ) && false !== strpos( $texts['terms'], home_url( '/retractation/' ) ) && false !== strpos( $plain['terms'], 'quatorze jours' ) && false !== strpos( $plain['terms'], 'frais directs de renvoi du produit sont à votre charge' ), 'terms: withdrawal right, online function with its address, return costs borne by the customer' );
    $check( false !== strpos( $plain['terms'], 'Le consommateur dispose d’un délai de deux ans à compter de la délivrance du bien' ) && false !== strpos( $plain['terms'], 'articles 1641 à 1649 du code civil' ) && false !== strpos( $plain['terms'], 'extension de six mois' ), 'terms: statutory box on the legal guarantees' );
    $check( false !== strpos( $plain['terms'], 'Médiateur de validation' ) && false !== strpos( $texts['terms'], 'href="https://mediateur-validation.example"' ) && false !== strpos( $plain['terms'], '48 à 72 heures' ) && false !== strpos( $plain['terms'], 'Revolut Pay' ), 'terms: mediator, delivery time and payment methods' );
    $check( false !== strpos( $plain['privacy'], 'Meta Platforms Ireland' ) && false !== strpos( $plain['privacy'], (int) BCRM_App::settings()['retention_days'] . ' jours' ) && false !== strpos( $plain['privacy'], 'CNIL' ) && false !== strpos( $plain['privacy'], 'Renoncer au contrat ici' ), 'privacy: Meta, the audience retention actually configured, the withdrawal form and the CNIL' );
    $check( false !== strpos( $plain['returns'], '4 Rue Lucien Vidie, 66600 Rivesaltes' ) && false !== strpos( $plain['returns'], '10 rue des oliviers de serres' ) && false !== strpos( $plain['returns'], 'Je/nous (*) vous notifie/notifions (*)' ) && false !== strpos( $plain['returns'], 'À l’attention de ECO EXPRESS LIMITED' ), 'delivery and returns: both return addresses and the model withdrawal form' );
    $seller_pays = ( new Belmains_Legal_Pages( array_merge( $complete, array( 'return_costs' => 'seller' ) ) ) )->render();
    $check( false !== strpos( $seller_pays['terms'], 'Nous prenons en charge les frais de renvoi' ) && false === strpos( $seller_pays['returns'], 'à votre charge' ), 'the return costs sentence follows the recorded decision' );

    // Application.
    $tool->check();
    $check( array() === $subjects( $tool, 'BLOQUANT' ) && $template === $raw( $pages['legal'] ), 'the report with complete facts blocks nothing and writes nothing' );
    $tool = new Belmains_Legal_Pages( $complete );
    $result = $tool->apply();
    $created = absint( get_option( 'belmains_withdrawal_page_id' ) ); $pages['withdrawal'] = $created;
    sort( $result['written'] ); $expected = array_values( array_intersect_key( $pages, array_flip( $kinds ) ) ); sort( $expected );
    $check( $expected === $result['written'] && array( $created ) === $result['created'] && array() === $result['published'] && array() === $result['kept'], 'the four texts are written and the withdrawal page is created' );
    $check( 'draft' === get_post_status( $created ) && '[belmains_retractation]' === $raw( $created ) && 'retractation' === get_post_field( 'post_name', $created ), 'the withdrawal page waits as a draft for the opening' );
    $statuses = array_unique( array_map( 'get_post_status', $expected ) );
    $stored_ok = true;
    foreach ( $kinds as $kind ) { $content = $raw( $pages[ $kind ] ); $stored_ok = $stored_ok && wp_strip_all_tags( $content ) === $plain[ $kind ] && array() === $placeholders( $content ); }
    $check( array( 'draft' ) === array_values( $statuses ) && $stored_ok, 'statuses are kept and the stored texts read exactly as written' );
    $check( false !== strpos( $raw( $pages['terms'] ), 'border:1px solid #63182e' ) && false !== strpos( $raw( $pages['terms'] ), 'href="' . home_url( '/retractation/' ) . '"' ), 'the guarantee box and the links survive saving without an administrator session' );
    $backup = json_decode( (string) file_get_contents( $state . '/legal-backups/' . $result['backup'] . '/pages.json' ), true );
    $check( is_array( $backup ) && $template === $backup['terms']['content'] && 'draft' === $backup['terms']['status'] && $pages['terms'] === $backup['terms']['id'] && '0600' === substr( sprintf( '%o', fileperms( $state . '/legal-backups/' . $result['backup'] . '/pages.json' ) ), -4 ), 'the previous contents are saved privately before any change' );

    $tool = new Belmains_Legal_Pages( $complete );
    $again = $tool->apply();
    $check( array() === $again['written'] && array() === $again['created'] && 5 === count( array_filter( $tool->lines(), static function ( $line ) { return false !== strpos( $line[2], 'déjà à jour' ); } ) ), 'a second run changes nothing' );

    // A page edited by hand is kept unless its replacement is asked for; a changed fact updates the others.
    wp_update_post( array( 'ID' => $pages['returns'], 'post_content' => $raw( $pages['returns'] ) . '<p>Précision ajoutée dans l’éditeur.</p>' ) );
    $changed = array_merge( $complete, array( 'phone' => '01 00 00 00 00' ) );
    $tool = new Belmains_Legal_Pages( $changed );
    $update = $tool->apply();
    $check( array( $pages['returns'] ) === $update['kept'] && in_array( 'Livraison et retours', $subjects( $tool, 'ATTENTION' ), true ) && false !== strpos( $raw( $pages['returns'] ), 'Précision ajoutée' ), 'a page edited by hand is kept and reported' );
    $check( in_array( $pages['legal'], $update['written'], true ) && false !== strpos( $raw( $pages['legal'] ), '01 00 00 00 00' ) && false === strpos( $raw( $pages['legal'] ), '06 51 21 27 40' ), 'a corrected fact is rewritten in the pages that carry it' );
    $forced = ( new Belmains_Legal_Pages( $changed ) )->apply( true, true );
    $check( in_array( $pages['returns'], $forced['written'], true ) && false === strpos( $raw( $pages['returns'] ), 'Précision ajoutée' ), 'the replacement of an edited page must be asked for explicitly' );
    sort( $forced['published'] );
    $check( $expected === $forced['published'] && 'draft' === get_post_status( $created ), 'publishing is a separate decision and leaves the withdrawal page to the opening' );
    $revisions = wp_get_post_revisions( $pages['returns'] );
    $check( count( $revisions ) >= 2, 'WordPress keeps the earlier versions as revisions' );
    echo $checks . " legal page checks passed.\n";
} finally {
    wp_set_current_user( $saved['user'] );
    foreach ( $pages as $id ) { wp_delete_post( $id, true ); }
    foreach ( $saved['options'] as $kind => $value ) { if ( null === $value ) { delete_option( 'belmains_' . $kind . '_page_id' ); } else { update_option( 'belmains_' . $kind . '_page_id', $value, false ); } }
    foreach ( is_dir( $state . '/legal-backups' ) ? array_diff( scandir( $state . '/legal-backups' ), $known_backups, array( '.', '..' ) ) : array() as $name ) {
        @unlink( $state . '/legal-backups/' . $name . '/pages.json' ); @rmdir( $state . '/legal-backups/' . $name );
    }
    if ( is_dir( $state . '/legal-backups' ) && 2 === count( scandir( $state . '/legal-backups' ) ) ) { rmdir( $state . '/legal-backups' ); }
    if ( $made_state && 2 === count( scandir( $state ) ) ) { rmdir( $state ); }
}
