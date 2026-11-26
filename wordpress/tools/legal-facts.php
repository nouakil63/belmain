<?php
/**
 * Facts printed in the legal pages. A value left at null is not known yet: tools/publish-legal.php
 * then refuses to write the pages rather than publish a guess. Edit here, never in the texts.
 */
if ( 'cli' !== PHP_SAPI ) { http_response_code( 404 ); exit; }

return array(
    // Seller, as registered at Companies House.
    'seller_name'           => 'ECO EXPRESS LIMITED',
    'seller_form'           => 'société à responsabilité limitée de droit anglais (Private Limited Company)',
    'seller_address'        => '15 Naseby Street, Liverpool, L4 5TT, Royaume-Uni',
    'seller_registration'   => 'Companies House (Angleterre et pays de Galles) sous le numéro 05080017',
    'seller_vat'            => 'GB925143538',
    'email'                 => 'contact@belmains.com',

    // Confirmed by Norman on 26 November 2026.
    'publication_director'  => 'Isabelle Ecolivet, dirigeante',
    'phone'                 => '06 51 21 27 40',
    'delivery_delay'        => '48 à 72 heures (jours ouvrés)',
    'return_costs'          => 'customer', // 'customer' or 'seller'
    'payment_methods'       => 'carte bancaire, Apple Pay, Google Pay, Revolut Pay et virement instantané depuis votre banque (« Pay by Bank »)',

    // Taken from the working drafts validated by Norman.
    'territory'             => 'France métropolitaine, Corse comprise',
    'shipping_origin'       => 'notre plateforme logistique située en Espagne',
    'return_addresses'      => array(
        'Chronopost' => 'INNOVACION BUSINESS SYSTEM, 4 Rue Lucien Vidie, 66600 Rivesaltes, France',
        'Colissimo'  => 'Mazet pour IBS, 10 rue des oliviers de serres, 66600 Rivesaltes, France',
    ),

    // Host, from its own legal notice (infomaniak.com/fr/cgv/mentions-legales). The notice gives no telephone number.
    'host_name'             => 'Infomaniak Network SA',
    'host_address'          => 'Rue Eugène-Marziano 25, 1227 Les Acacias (Genève), Suisse',
    'host_phone'            => null,

    // Mandatory consumer mediator: the one the seller has actually subscribed to.
    'mediator_name'         => null,
    'mediator_address'      => null,
    'mediator_url'          => null,

    // Extension of the legal guarantee after a repair, as printed in the statutory box.
    'repair_extension'      => 'six mois',

    'version_date'          => '26 novembre 2026',

    // Optional: left out of the pages while null.
    'eu_vat'                => null,
    'eu_representative'     => null,
);
