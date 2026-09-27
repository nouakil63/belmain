<?php
// Désinstallation : on conserve les données (commandes, retours, historique) par sécurité.
// Seules les options de réglage sont supprimées.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
delete_option( 'bm_crm_settings' );
delete_option( 'bm_crm_db_version' );
