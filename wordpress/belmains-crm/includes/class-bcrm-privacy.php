<?php
/**
 * Support tickets in WordPress' native personal-data export/erasure tools.
 *
 * These callbacks are registered only with the native privacy workflow, whose
 * administrator permissions, request verification and nonces remain in core.
 * There is no public endpoint, automatic erasure or change to WooCommerce data.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BCRM_Privacy {
    private const PAGE_SIZE = 50;

    public static function register() {
        add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'exporters' ) );
        add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'erasers' ) );
    }

    public static function exporters( $exporters ) {
        $exporters['belmains-support-tickets'] = array(
            'exporter_friendly_name' => __( 'Belmains — demandes au service client', 'belmains-crm' ),
            'callback' => array( __CLASS__, 'export' ),
        );
        return $exporters;
    }

    public static function erasers( $erasers ) {
        $erasers['belmains-support-tickets'] = array(
            'eraser_friendly_name' => __( 'Belmains — demandes au service client', 'belmains-crm' ),
            'callback' => array( __CLASS__, 'erase' ),
        );
        return $erasers;
    }

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'bcrm_tickets';
    }

    private static function email( $email_address ) {
        if ( ! is_string( $email_address ) ) {
            return new WP_Error( 'bcrm_privacy_email', __( 'Adresse e-mail invalide.', 'belmains-crm' ) );
        }
        $email_address = trim( $email_address );
        // Do not sanitize an invalid address into another customer's address.
        if ( strlen( $email_address ) > 200 || ! is_email( $email_address ) ) {
            return new WP_Error( 'bcrm_privacy_email', __( 'Adresse e-mail invalide.', 'belmains-crm' ) );
        }
        return strtolower( $email_address );
    }

    private static function page( $page ) {
        $maximum = intdiv( PHP_INT_MAX, self::PAGE_SIZE );
        if ( ! is_int( $page ) && ! is_string( $page ) ) {
            return new WP_Error( 'bcrm_privacy_page', __( 'Page de données invalide.', 'belmains-crm' ) );
        }
        $page = filter_var( $page, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1, 'max_range' => $maximum ) ) );
        return false === $page ? new WP_Error( 'bcrm_privacy_page', __( 'Page de données invalide.', 'belmains-crm' ) ) : $page;
    }

    private static function database_error() {
        return new WP_Error( 'bcrm_privacy_database', __( 'Le traitement des demandes au service client a échoué. Réessayez pour le terminer.', 'belmains-crm' ) );
    }

    public static function export( $email_address, $page = 1 ) {
        global $wpdb;
        $email = self::email( $email_address );
        if ( is_wp_error( $email ) ) { return $email; }
        $page = self::page( $page );
        if ( is_wp_error( $page ) ) { return $page; }

        $table = self::table();
        $tickets = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, subject, customer_email, order_id, status, priority, message, created_at, updated_at FROM $table WHERE LOWER(customer_email) = %s ORDER BY id ASC LIMIT %d OFFSET %d",
            $email, self::PAGE_SIZE + 1, ( $page - 1 ) * self::PAGE_SIZE
        ), ARRAY_A );
        if ( $wpdb->last_error ) { return self::database_error(); }

        $labels = array(
            'id' => __( 'Numéro de demande', 'belmains-crm' ),
            'subject' => __( 'Sujet', 'belmains-crm' ),
            'customer_email' => __( 'Adresse e-mail', 'belmains-crm' ),
            'order_id' => __( 'Référence interne de commande', 'belmains-crm' ),
            'status' => __( 'Statut', 'belmains-crm' ),
            'priority' => __( 'Priorité', 'belmains-crm' ),
            'message' => __( 'Message et notes du service client', 'belmains-crm' ),
            'created_at' => __( 'Date de création', 'belmains-crm' ),
            'updated_at' => __( 'Date de mise à jour', 'belmains-crm' ),
        );
        $data = array();
        foreach ( array_slice( $tickets, 0, self::PAGE_SIZE ) as $ticket ) {
            $fields = array();
            foreach ( $labels as $key => $label ) {
                // Core allows limited HTML in export values. Keep ticket text
                // literal, including any markup entered by a customer or staff.
                $fields[] = array( 'name' => $label, 'value' => esc_html( (string) $ticket[ $key ] ) );
            }
            $data[] = array(
                'group_id' => 'belmains-support-tickets',
                'group_label' => __( 'Demandes au service client Belmains', 'belmains-crm' ),
                'group_description' => __( 'Demandes et notes associées à cette adresse e-mail.', 'belmains-crm' ),
                'item_id' => 'belmains-support-ticket-' . (int) $ticket['id'],
                'data' => $fields,
            );
        }
        return array( 'data' => $data, 'done' => count( $tickets ) <= self::PAGE_SIZE );
    }

    public static function erase( $email_address, $page = 1 ) {
        global $wpdb;
        $email = self::email( $email_address );
        if ( is_wp_error( $email ) ) { return $email; }
        $page = self::page( $page );
        if ( is_wp_error( $page ) ) { return $page; }

        $table = self::table();
        // Always consume the first remaining batch. An OFFSET would skip rows
        // because successful anonymization removes their matching email.
        $ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT id FROM $table WHERE LOWER(customer_email) = %s ORDER BY id ASC LIMIT %d",
            $email, self::PAGE_SIZE
        ) );
        if ( $wpdb->last_error ) { return self::database_error(); }

        $removed = false;
        foreach ( $ids as $id ) {
            $updated = $wpdb->query( $wpdb->prepare(
                "UPDATE $table SET customer_email = '', subject = %s, message = %s, order_id = 0 WHERE id = %d AND LOWER(customer_email) = %s",
                __( 'Demande anonymisée', 'belmains-crm' ),
                __( 'Données personnelles effacées à la demande du client.', 'belmains-crm' ),
                (int) $id, $email
            ) );
            // Stop and let the administrator retry, rather than reporting an
            // incomplete request as finished or looping forever on one row.
            if ( false === $updated ) { return self::database_error(); }
            $removed = $removed || $updated > 0;
        }
        $remaining = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $table WHERE LOWER(customer_email) = %s LIMIT 1", $email
        ) );
        if ( $wpdb->last_error ) { return self::database_error(); }

        // Status, priority and dates are operational metadata. Order records,
        // invoices and WooCommerce retention rules belong to its own eraser.
        return array(
            'items_removed' => $removed,
            'items_retained' => false,
            'messages' => array(),
            'done' => null === $remaining,
        );
    }
}
