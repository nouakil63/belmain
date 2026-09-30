<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class BCRM_Support {
    private static function table() { global $wpdb; return $wpdb->prefix . 'bcrm_tickets'; }
    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table(); $collate = $wpdb->get_charset_collate();
        dbDelta( "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            subject varchar(200) NOT NULL,
            customer_email varchar(200) NOT NULL DEFAULT '',
            order_id bigint(20) unsigned NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'open',
            priority varchar(20) NOT NULL DEFAULT 'normal',
            message longtext NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY order_id (order_id)
        ) $collate;" );
    }
    private static function error( $message, $status = 400 ) { return new WP_Error( 'bcrm_ticket', $message, array( 'status' => $status ) ); }
    private static function validate( $data ) {
        if ( ! in_array( $data['status'] ?? 'open', array( 'open', 'pending', 'closed' ), true ) ) { return self::error( 'Statut SAV invalide.' ); }
        if ( ! in_array( $data['priority'] ?? 'normal', array( 'low', 'normal', 'high', 'urgent' ), true ) ) { return self::error( 'Priorité invalide.' ); }
        return true;
    }
    public static function listing( $status = '' ) {
        global $wpdb; $table = self::table();
        if ( $status && ! in_array( $status, array( 'open', 'pending', 'closed' ), true ) ) { return self::error( 'Statut SAV invalide.' ); }
        $where = $status ? $wpdb->prepare( ' WHERE status = %s', $status ) : '';
        $items = $wpdb->get_results( "SELECT * FROM $table $where ORDER BY updated_at DESC, id DESC LIMIT 200", ARRAY_A );
        $counts = array( 'open' => 0, 'pending' => 0, 'closed' => 0 );
        foreach ( $wpdb->get_results( "SELECT status, COUNT(*) AS count FROM $table GROUP BY status", ARRAY_A ) as $row ) { $counts[ $row['status'] ] = (int) $row['count']; }
        foreach ( $items as &$item ) { $item['id'] = (int) $item['id']; $item['order_id'] = (int) $item['order_id']; }
        return array( 'items' => $items, 'counts' => $counts, 'limit' => 200, 'total' => $status ? $counts[ $status ] : array_sum( $counts ) );
    }
    public static function create( $data ) {
        global $wpdb;
        $valid = self::validate( $data ); if ( is_wp_error( $valid ) ) { return $valid; }
        $subject = sanitize_text_field( $data['subject'] ?? '' );
        $message = sanitize_textarea_field( $data['message'] ?? '' );
        $email = trim( (string) ( $data['customer_email'] ?? '' ) );
        if ( ! $subject || mb_strlen( $subject ) > 200 || ! $message || mb_strlen( $message ) > 6000 ) { return self::error( 'Précisez un sujet (200 caractères maximum) et un message (6 000 caractères maximum).' ); }
        if ( $email && ! is_email( $email ) ) { return self::error( 'Adresse e-mail invalide.' ); }
        $order_id = absint( $data['order_id'] ?? 0 );
        if ( $order_id && ( ! function_exists( 'wc_get_order' ) || ! wc_get_order( $order_id ) ) ) { return self::error( 'Cette commande est introuvable.' ); }
        $saved = $wpdb->insert( self::table(), array(
            'subject' => $subject, 'customer_email' => sanitize_email( $email ), 'order_id' => $order_id,
            'status' => $data['status'] ?? 'open', 'priority' => $data['priority'] ?? 'normal', 'message' => $message,
            'created_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ),
        ), array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' ) );
        return false === $saved ? self::error( 'Enregistrement impossible.', 500 ) : array( 'success' => true, 'id' => $wpdb->insert_id );
    }
    public static function update( $id, $data ) {
        global $wpdb; $table = self::table();
        $valid = self::validate( $data ); if ( is_wp_error( $valid ) ) { return $valid; }
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ), ARRAY_A );
        if ( ! $row ) { return self::error( 'Demande introuvable.', 404 ); }
        $change = array( 'updated_at' => current_time( 'mysql' ) );
        foreach ( array( 'status', 'priority' ) as $key ) { if ( isset( $data[ $key ] ) ) { $change[ $key ] = $data[ $key ]; } }
        if ( ! empty( $data['note'] ) ) {
            $note = sanitize_textarea_field( $data['note'] );
            if ( mb_strlen( $note ) > 3000 || mb_strlen( $row['message'] ) + mb_strlen( $note ) > 50000 ) { return self::error( 'La note est trop longue.' ); }
            $change['message'] = $row['message'] . "\n\n[" . wp_date( 'd/m/Y H:i' ) . '] ' . sanitize_text_field( wp_get_current_user()->display_name ) . "\n" . $note;
        }
        $saved = $wpdb->update( $table, $change, array( 'id' => $id ) );
        return false === $saved ? self::error( 'Mise à jour impossible.', 500 ) : array( 'success' => true );
    }
}
