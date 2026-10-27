<?php
/** Explicit preparation only: wp eval-file wordpress/tools/prepare-launch.php. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( 'WooCommerce' ) || ! shortcode_exists( 'belmains_contact' ) ) {
    throw new RuntimeException( 'Use WP-CLI with WooCommerce and Belmains CRM active.' );
}

/** Preserve all existing page content; this tool only creates missing pages. */
$ensure_page = static function ( $option, $title, $slug, $content, $status ) {
    $id = absint( get_option( $option ) );
    $page = $id ? get_post( $id ) : get_page_by_path( $slug, OBJECT, 'page' );
    if ( $page ) {
        if ( 'page' !== $page->post_type || 'trash' === $page->post_status ) { throw new RuntimeException( 'Review existing page: ' . $page->ID ); }
        update_option( $option, $page->ID, false );
        return $page->ID;
    }
    $id = wp_insert_post( array( 'post_type' => 'page', 'post_title' => $title, 'post_name' => $slug, 'post_content' => $content, 'post_status' => $status ), true );
    if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
    update_post_meta( $id, '_belmains_preparation_page', 'yes' );
    update_option( $option, $id, false );
    return $id;
};

$contact_id = $ensure_page( 'belmains_contact_page_id', 'Contactez Belmains', 'contact',
    '[belmains_contact]', 'publish' );
if ( 'publish' !== get_post_status( $contact_id ) || ! has_shortcode( (string) get_post_field( 'post_content', $contact_id ), 'belmains_contact' ) ) {
    throw new RuntimeException( 'Existing Contact page needs review; it must be published and contain the contact shortcode. Its content has been preserved.' );
}

$notice = '<p><strong>Document de travail — à compléter et valider avant publication.</strong> Les éléments entre crochets ne sont pas des informations confirmées.</p>';
$legal = $notice . '<h2>Éditeur du site</h2><p>Belmains est une marque exploitée par [nom légal], [forme juridique], [capital si applicable], dont le siège est situé [adresse professionnelle].</p><p>Identifiants : [SIREN / SIRET], [registre et ville si applicable], [numéro de TVA si applicable].</p><p>Responsable de la publication : [nom et qualité]. Contact : [e-mail et téléphone professionnels].</p><h2>Hébergement</h2><p>[Nom, adresse et téléphone de l’hébergeur choisi].</p><h2>À vérifier avant publication</h2><p>Compléter les informations correspondant à la forme juridique du vendeur et au domaine retenu. Les crédits graphiques ne remplacent pas l’identité du vendeur.</p>';
$terms = $notice . '<h2>Vendeur et champ des ventes</h2><p>[Identité et coordonnées du vendeur]. Définir les pays desservis et les destinataires des offres.</p><h2>Produits et prix</h2><p>Gant Belmains gris. Offres préparées : un gant à 89,99 € ; deux gants à 149,99 €. Confirmer le traitement de la TVA, les caractéristiques, les conditions de remise et l’affichage des prix de référence.</p><h2>Commande et paiement</h2><p>[Moyens de paiement effectivement activés, étapes de validation et confirmation de commande].</p><h2>Livraison</h2><p>[Territoire desservi, transporteur, délais confirmés, adresse ou point relais effectivement disponible et frais].</p><h2>Rétractation et retours</h2><p>Prévoir les modalités du droit de rétractation applicable aux ventes à distance, son formulaire type, l’adresse de retour, les frais et les modalités de remboursement. Faire vérifier toute exception envisagée ; aucune exclusion n’est présumée pour le gant.</p><h2>Garanties et réclamations</h2><p>Intégrer les informations applicables sur les garanties légales et la procédure de réclamation. [Coordonnées du médiateur de la consommation choisi et modalités de saisine].</p>';
$privacy = $notice . '<h2>Responsable du traitement</h2><p>[Identité du vendeur et coordonnées pour exercer les droits].</p><h2>Commandes et comptes</h2><p>WooCommerce conserve les coordonnées nécessaires aux commandes, à leur traitement et au compte client. [Bases légales, durées de conservation et destinataires à confirmer].</p><h2>Demandes au service client</h2><p>Le formulaire enregistre le nom, l’e-mail, le sujet, le message et la référence de commande facultative dans le SAV privé. Il ne crée pas d’inscription publicitaire et n’envoie pas automatiquement d’e-mail. [Base légale, durée de conservation et organisation du traitement à confirmer].</p><h2>Mesure d’audience</h2><p>La mesure locale est déclenchée après acceptation. Le refus laisse la navigation possible ; le bouton « Mes choix de mesure d’audience » permet de changer de choix. Les événements ne contiennent ni e-mail client, ni IP brute, ni URL avec ses paramètres complets. Vérifier les durées effectives dans les réglages du CRM et les destinataires d’accès.</p><h2>Prestataires et droits</h2><p>[Hébergeur, prestataire de paiement, transporteur et service d’envoi d’e-mails réellement retenus ; transferts éventuels et garanties]. Décrire les droits et leurs conditions d’exercice, le contact responsable ainsi que la possibilité de saisir la CNIL.</p>';
$returns = $notice . '<h2>Livraison</h2><p>La boutique est préparée pour la livraison offerte à domicile en France. Le point relais reste à connecter. [Confirmer territoire exact et délais avant publication].</p><h2>Demande de retour</h2><p>[Adresse de retour, e-mail de contact, frais et étapes]. Compléter les modalités applicables à la rétractation, aux défauts et aux garanties. Ne pas confondre le droit légal applicable avec une garantie commerciale de 30 jours, qui n’est pas proposée.</p><h2>Formulaire de rétractation</h2><p>[Insérer un formulaire type adapté à l’identité du vendeur et aux coordonnées de retour].</p>';
foreach ( array(
    array( 'belmains_legal_page_id', 'Mentions légales', 'mentions-legales', $legal ),
    array( 'belmains_terms_page_id', 'Conditions générales de vente', 'conditions-generales-de-vente', $terms ),
    array( 'belmains_privacy_page_id', 'Politique de confidentialité', 'confidentialite', $privacy ),
    array( 'belmains_returns_page_id', 'Livraison et retours', 'livraison-et-retours', $returns ),
) as $page ) { $ensure_page( $page[0], $page[1], $page[2], $page[3], 'draft' ); }

// Presentation only: no sender address, mail provider, tax or gateway is guessed.
if ( ! get_option( 'belmains_launch_presentation_prepared' ) ) {
    foreach ( array(
        'woocommerce_email_from_name' => 'Belmains',
        'woocommerce_email_header_image' => get_theme_file_uri( 'assets/belmains-logo.png' ),
        'woocommerce_email_header_image_width' => '200',
        'woocommerce_email_header_alignment' => 'center',
        'woocommerce_email_base_color' => '#63182e',
        'woocommerce_email_background_color' => '#f5f0e7',
        'woocommerce_email_body_background_color' => '#ffffff',
        'woocommerce_email_text_color' => '#181d25',
        'woocommerce_email_footer_text_color' => '#6c625d',
    ) as $option => $value ) { update_option( $option, $value ); }
    update_option( 'belmains_launch_presentation_prepared', current_time( 'mysql' ), false );
}
// This script prepares a staging site; indexing is a separate launch decision.
update_option( 'blog_public', 0 );
$sample = get_page_by_path( 'sample-page', OBJECT, 'page' );
if ( $sample && 'Sample Page' === $sample->post_title && str_contains( $sample->post_content, 'This is an example page.' ) && 'publish' === $sample->post_status ) {
    wp_update_post( array( 'ID' => $sample->ID, 'post_status' => 'draft' ) );
}
flush_rewrite_rules( false );
WP_CLI::success( 'Contact page ready (' . $contact_id . '). Legal drafts preserved, email presentation prepared, indexing disabled. No mail sent and no stock/payment/tax settings changed.' );
