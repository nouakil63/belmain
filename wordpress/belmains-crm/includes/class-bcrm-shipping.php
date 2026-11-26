<?php
/** Multi-parcel logistics. Order/payment status is never changed by this module. */
defined( 'ABSPATH' ) || exit;

final class BCRM_Shipping {
    private const META = '_bcrm_shipping_v2';
    private const MAX_PARCELS = 50;
    private const MAX_EVENTS = 1000;
    public const STATUSES = array(
        'pending' => 'À préparer', 'label_created' => 'Numéro de suivi reçu', 'shipped' => 'Expédié',
        'in_transit' => 'En transit', 'out_for_delivery' => 'En cours de livraison',
        'relay' => 'Disponible en relais', 'delivered' => 'Livré', 'exception' => 'Incident',
        'returned' => 'Retourné', 'cancelled' => 'Annulé',
    );

    public static function carriers() {
        return array( 'chronopost' => 'Chronopost', 'colissimo' => 'Colissimo', 'laposte' => 'La Poste',
            'mondial_relay' => 'Mondial Relay', 'dpd' => 'DPD', 'dhl' => 'DHL', 'ups' => 'UPS',
            'gls' => 'GLS', 'other' => 'Autre transporteur' );
    }

    public static function returns_address( $carrier ) {
        // Confirmed by Iziship, ticket 782933, 14 October 2026. These are not dispatch addresses.
        $addresses = array(
            'chronopost' => "INNOVACION BUSINESS SYSTEM\n4 Rue Lucien Vidie\n66600 Rivesaltes\nFrance",
            'colissimo' => "Mazet pour IBS\n10 rue des oliviers de serres\n66600 RIVESALTES\nFrance",
        );
        return $addresses[ $carrier ] ?? '';
    }

    public static function carrier_code( $name ) {
        $name = strtolower( trim( remove_accents( (string) $name ) ) );
        foreach ( self::carriers() as $code => $label ) {
            if ( $name === $code || $name === strtolower( remove_accents( $label ) ) ) { return $code; }
        }
        return 'other';
    }

    public static function tracking_url( $carrier, $number ) {
        // La Poste's official unified tool supports Colissimo, Chronopost and tracked letters.
        if ( in_array( $carrier, array( 'chronopost', 'colissimo', 'laposte' ), true ) && '' !== $number ) {
            return 'https://www.laposte.fr/outils/suivre-vos-envois?code=' . rawurlencode( preg_replace( '/\s+/u', '', $number ) );
        }
        return '';
    }

    private static function error( $message, $status = 400 ) {
        return new WP_Error( 'bcrm_shipping', $message, array( 'status' => $status ) );
    }
    private static function now() { return current_datetime()->format( DATE_ATOM ); }
    private static function text( $value, $max = 200 ) {
        if ( ! is_scalar( $value ) ) { return ''; }
        $text = sanitize_text_field( (string) $value );
        return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max ) : substr( $text, 0, $max );
    }
    private static function date( $value ) {
        if ( '' === $value ) { return ''; }
        $date = is_string( $value ) ? DateTimeImmutable::createFromFormat( '!Y-m-d', $value, wp_timezone() ) : false;
        return $date && $date->format( 'Y-m-d' ) === $value ? $value : false;
    }
    private static function url( $value ) {
        if ( '' === $value ) { return ''; }
        if ( ! is_string( $value ) || strlen( $value ) > 2048 || preg_match( '/[\x00-\x20\x7f]/', $value ) || ! filter_var( $value, FILTER_VALIDATE_URL ) ) { return false; }
        $p = wp_parse_url( $value );
        $host = strtolower( trim( $p['host'] ?? '', '[]' ) );
        if ( 'https' !== ( $p['scheme'] ?? '' ) || isset( $p['user'] ) || isset( $p['pass'] ) || ! str_contains( $host, '.' ) || preg_match( '/\.(local|localhost|internal|test|invalid)$/', $host ) ) { return false; }
        if ( filter_var( $host, FILTER_VALIDATE_IP ) && ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) { return false; }
        return esc_url_raw( $value, array( 'https' ) );
    }
    private static function blank( $id ) {
        return array( 'id' => $id, 'direction' => 'outbound', 'number' => '', 'carrier_code' => 'other',
            'carrier' => '', 'url' => '', 'status' => 'pending', 'shipped_at' => '', 'delivered_at' => '',
            'expected_at' => '', 'relay_name' => '', 'relay_address' => '', 'note' => '', 'label_url' => '',
            'source' => 'manual', 'updated_at' => '', 'status_at' => '', 'archived' => false );
    }

    /** Lazy compatibility view: reading old orders never writes or discards old metadata. */
    public static function document( $order ) {
        $stored = $order->get_meta( self::META, true );
        if ( is_array( $stored ) && isset( $stored['parcels'], $stored['revision'], $stored['events'] ) ) { return $stored; }
        $doc = array( 'revision' => 0, 'parcels' => array(), 'events' => array() );
        $legacy = BCRM_Tracking::legacy( $order );
        if ( $legacy['number'] || 'pending' !== $legacy['status'] ) {
            $p = array_replace( self::blank( 'legacy-main' ), array_intersect_key( $legacy, self::blank( '' ) ) );
            $p['carrier_code'] = self::carrier_code( $p['carrier'] );
            $doc['parcels'][] = $p;
        }
        foreach ( (array) $order->get_meta( '_wc_shipment_tracking_items', true ) as $index => $item ) {
            if ( ! is_array( $item ) || empty( $item['tracking_number'] ) || ! is_scalar( $item['tracking_number'] ) ) { continue; }
            $number = self::text( $item['tracking_number'], 120 );
            if ( in_array( $number, array_column( $doc['parcels'], 'number' ), true ) ) { continue; }
            $p = self::blank( 'import-' . substr( hash( 'sha256', $number ), 0, 24 ) );
            $p['number'] = $number;
            $p['carrier'] = self::text( $item['custom_tracking_provider'] ?? $item['tracking_provider'] ?? '' );
            $p['carrier_code'] = self::carrier_code( $p['carrier'] );
            $p['url'] = self::url( $item['custom_tracking_link'] ?? '' ) ?: '';
            $p['status'] = 'label_created'; $p['source'] = 'imported';
            $p['shipped_at'] = ! empty( $item['date_shipped'] ) && is_numeric( $item['date_shipped'] ) ? wp_date( 'Y-m-d', (int) $item['date_shipped'] ) : '';
            $doc['parcels'][] = $p;
        }
        return $doc;
    }

    public static function has_document( $order ) { return is_array( $order->get_meta( self::META, true ) ); }

    public static function present( $parcel ) {
        $p = array_replace( self::blank( $parcel['id'] ?? '' ), $parcel );
        $p['status_label'] = self::STATUSES[ $p['status'] ] ?? 'À vérifier';
        $p['url'] = self::tracking_url( $p['carrier_code'], $p['number'] ) ?: ( self::url( $p['url'] ) ?: '' );
        $p['return_address'] = 'return' === $p['direction'] ? self::returns_address( $p['carrier_code'] ) : '';
        $p['overdue'] = false;
        if ( ! $p['archived'] && ! in_array( $p['status'], array( 'pending', 'label_created', 'delivered', 'returned', 'cancelled' ), true ) ) {
            $limit = $p['expected_at'];
            if ( ! $limit && self::date( $p['shipped_at'] ) ) {
                $days = (int) ( BCRM_App::settings()['shipping_sla_days'] ?? 3 );
                $limit = ( new DateTimeImmutable( $p['shipped_at'], wp_timezone() ) )->modify( '+' . $days . ' days' )->format( 'Y-m-d' );
            }
            $p['overdue'] = $limit && $limit < wp_date( 'Y-m-d' );
        }
        return $p;
    }

    public static function detail( $order ) {
        $doc = self::document( $order );
        $doc['parcels'] = array_map( array( __CLASS__, 'present' ), $doc['parcels'] );
        $doc['events_total'] = count( $doc['events'] );
        $doc['events'] = array_reverse( array_slice( $doc['events'], -100 ) );
        $doc['carriers'] = self::carriers();
        $doc['statuses'] = self::STATUSES;
        $doc['import_error'] = $order->get_meta( '_bcrm_shipping_import_error', true ) ?: null;
        return $doc;
    }

    public static function summary( $order ) {
        $parcels = array_values( array_filter( self::document( $order )['parcels'], static function( $p ) { return ! $p['archived'] && 'outbound' === $p['direction'] && 'cancelled' !== $p['status']; } ) );
        if ( ! $parcels ) { return BCRM_Tracking::legacy( false ); }
        $p = self::present( $parcels[0] );
        $states = array_column( $parcels, 'status' );
        if ( count( array_unique( $states ) ) > 1 ) {
            $p['status'] = 'pending';
            foreach ( array( 'exception', 'partial', 'out_for_delivery', 'relay', 'in_transit', 'shipped', 'returned', 'label_created', 'pending' ) as $candidate ) {
                if ( in_array( $candidate, $states, true ) || ( 'partial' === $candidate && in_array( 'delivered', $states, true ) ) ) { $p['status'] = $candidate; break; }
            }
            $p['status_label'] = 'partial' === $p['status'] ? 'Partiellement livré' : ( self::STATUSES[ $p['status'] ] ?? '' );
        }
        $p['parcels_count'] = count( $parcels );
        $p['delivered_count'] = count( array_filter( $states, static function( $s ) { return 'delivered' === $s; } ) );
        $p['status_is_manual'] = 'manual' === $p['source'];
        $p['observed_at'] = $p['updated_at'];
        return $p;
    }

    /** All writes to the canonical document are serialized, then refreshed from Woo CRUD. */
    private static function mutate( $id, $callback ) {
        global $wpdb;
        $key = 'bcrm_shipping_lock_' . absint( $id );
        $token = wp_json_encode( array( 'expires' => time() + 60, 'token' => wp_generate_uuid4() ) );
        if ( ! add_option( $key, $token, '', false ) ) {
            $old = get_option( $key ); $decoded = json_decode( (string) $old, true );
            if ( is_array( $decoded ) && (int) ( $decoded['expires'] ?? PHP_INT_MAX ) < time() ) {
                $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s", $key, $old ) );
                wp_cache_delete( $key, 'options' );
            }
            if ( ! add_option( $key, $token, '', false ) ) { return self::error( 'Une mise à jour est en cours. Réessayez dans quelques instants.', 409 ); }
        }
        try {
            $order = wc_get_order( absint( $id ) );
            if ( ! $order instanceof WC_Order || 'shop_order' !== $order->get_type() || 'trash' === $order->get_status() ) { return self::error( 'Commande introuvable.', 404 ); }
            $order->read_meta_data( true );
            $doc = self::document( $order );
            $next = $callback( $doc, $order );
            if ( is_wp_error( $next ) ) { return $next; }
            if ( $next !== $doc ) {
                $next['revision'] = (int) $doc['revision'] + 1;
                $order->update_meta_data( self::META, $next );
                $order->save_meta_data();
            }
            return array( 'success' => true, 'revision' => $next['revision'] );
        } finally {
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s", $key, $token ) );
            wp_cache_delete( $key, 'options' );
        }
    }

    private static function normalize( $data, $old ) {
        $p = array_replace( self::blank( $old['id'] ), $old );
        $text_fields = array( 'number' => 120, 'carrier' => 120, 'relay_name' => 200, 'relay_address' => 500, 'note' => 4000 );
        foreach ( $text_fields as $key => $max ) {
            if ( ! array_key_exists( $key, $data ) ) { continue; }
            if ( ! is_string( $data[ $key ] ) || ( function_exists( 'mb_strlen' ) ? mb_strlen( $data[ $key ] ) : strlen( $data[ $key ] ) ) > $max ) { return self::error( 'Champ invalide ou trop long : ' . $key ); }
            $p[ $key ] = 'note' === $key ? sanitize_textarea_field( $data[ $key ] ) : self::text( $data[ $key ], $max );
        }
        if ( array_key_exists( 'archived', $data ) ) {
            if ( ! is_bool( $data['archived'] ) ) { return self::error( 'La valeur d’archivage est invalide.' ); }
            $p['archived'] = $data['archived'];
        }
        foreach ( array( 'direction' => array( 'outbound', 'return' ), 'carrier_code' => array_keys( self::carriers() ), 'status' => array_keys( self::STATUSES ) ) as $key => $choices ) {
            if ( ! array_key_exists( $key, $data ) ) { continue; }
            if ( ! is_string( $data[ $key ] ) || ! in_array( $data[ $key ], $choices, true ) ) { return self::error( 'Valeur invalide : ' . $key ); }
            $p[ $key ] = $data[ $key ];
        }
        if ( 'other' !== $p['carrier_code'] ) { $p['carrier'] = self::carriers()[ $p['carrier_code'] ]; }
        if ( $p['number'] !== $old['number'] || $p['carrier_code'] !== $old['carrier_code'] ) {
            if ( ! isset( $data['url'] ) ) { $p['url'] = ''; }
            if ( ! isset( $data['label_url'] ) ) { $p['label_url'] = ''; }
            if ( $p['number'] !== $old['number'] ) {
                foreach ( array( 'shipped_at', 'delivered_at', 'expected_at', 'relay_name', 'relay_address' ) as $key ) { if ( ! isset( $data[ $key ] ) ) { $p[ $key ] = ''; } }
                if ( ! isset( $data['status'] ) ) { $p['status'] = $p['number'] ? 'label_created' : 'pending'; }
            }
        }
        foreach ( array( 'url', 'label_url' ) as $key ) {
            if ( ! array_key_exists( $key, $data ) ) { continue; }
            $value = self::url( $data[ $key ] );
            if ( false === $value ) { return self::error( 'Utilisez un lien HTTPS public valide, sans identifiants.' ); }
            $p[ $key ] = $value;
        }
        foreach ( array( 'shipped_at', 'delivered_at', 'expected_at' ) as $key ) {
            if ( ! array_key_exists( $key, $data ) ) { continue; }
            $value = self::date( $data[ $key ] );
            if ( false === $value ) { return self::error( 'Date invalide : ' . $key . '. Format attendu : AAAA-MM-JJ.' ); }
            $p[ $key ] = $value;
        }
        if ( $p['shipped_at'] && $p['delivered_at'] && $p['delivered_at'] < $p['shipped_at'] ) { return self::error( 'La livraison ne peut pas précéder l’expédition.' ); }
        if ( $p['expected_at'] && $p['shipped_at'] && $p['expected_at'] < $p['shipped_at'] ) { return self::error( 'La date prévue ne peut pas précéder l’expédition.' ); }
        if ( ! $p['number'] && ! in_array( $p['status'], array( 'pending', 'cancelled' ), true ) ) { return self::error( 'Renseignez le numéro de suivi pour ce statut.' ); }
        if ( 'delivered' !== $p['status'] ) { $p['delivered_at'] = ''; }
        if ( $p['shipped_at'] > wp_date( 'Y-m-d' ) || $p['delivered_at'] > wp_date( 'Y-m-d' ) ) { return self::error( 'Une date d’expédition ou de livraison constatée ne peut pas être dans le futur.' ); }
        return $p;
    }

    private static function append_event( &$doc, $p, $message, $event_id = '', $occurred_at = '', $fingerprint = '' ) {
        if ( count( $doc['events'] ) >= self::MAX_EVENTS ) { return self::error( 'La limite de 1 000 événements est atteinte pour cette commande. Exportez et contactez le support.' ); }
        $doc['events'][] = array( 'id' => $event_id ?: wp_generate_uuid4(), 'parcel_id' => $p['id'], 'number' => $p['number'],
            'status' => $p['status'], 'source' => $p['source'], 'at' => $occurred_at ?: self::now(),
            'received_at' => self::now(), 'message' => self::text( $message, 500 ),
            'actor' => 'manual' === $p['source'] ? get_current_user_id() : 0, 'fingerprint' => $fingerprint,
            'snapshot' => array_intersect_key( $p, array_flip( array( 'number', 'carrier', 'carrier_code', 'direction', 'status', 'shipped_at', 'delivered_at', 'expected_at', 'relay_name', 'relay_address', 'archived' ) ) ) );
        return true;
    }

    public static function save( $id, $data ) {
        if ( ! is_array( $data ) || ! isset( $data['revision'] ) || ! is_int( $data['revision'] ) || ! isset( $data['parcel'] ) || ! is_array( $data['parcel'] ) ) { return self::error( 'Actualisez la commande avant de modifier un colis.' ); }
        return self::mutate( $id, static function( $doc ) use ( $data ) {
            if ( $doc['revision'] !== $data['revision'] ) { return self::error( 'Le suivi a changé depuis son ouverture. Rechargez la commande avant d’enregistrer.', 409 ); }
            $input = $data['parcel']; $index = null;
            if ( ! empty( $input['id'] ) ) {
                foreach ( $doc['parcels'] as $i => $p ) { if ( $p['id'] === $input['id'] ) { $index = $i; break; } }
                if ( null === $index ) { return self::error( 'Colis introuvable.', 404 ); }
            }
            if ( null === $index && count( $doc['parcels'] ) >= self::MAX_PARCELS ) { return self::error( 'Cette commande a atteint la limite de 50 colis.' ); }
            $old = null === $index ? self::blank( wp_generate_uuid4() ) : $doc['parcels'][ $index ];
            $p = self::normalize( $input, $old );
            if ( is_wp_error( $p ) ) { return $p; }
            foreach ( $doc['parcels'] as $other ) {
                if ( $p['number'] && $other['id'] !== $p['id'] && $other['number'] === $p['number'] && $other['carrier_code'] === $p['carrier_code'] && $other['direction'] === $p['direction'] && ! $other['archived'] && ! $p['archived'] ) { return self::error( 'Ce numéro est déjà utilisé par un autre colis de cette commande.' ); }
            }
            if ( $p === $old && null !== $index ) { return $doc; }
            $p['source'] = 'manual'; $p['updated_at'] = self::now(); $p['status_at'] = self::now();
            $message = null === $index ? 'Colis ajouté' : ( $p['archived'] !== $old['archived'] ? ( $p['archived'] ? 'Colis archivé' : 'Colis restauré' ) : 'Suivi mis à jour manuellement' );
            $added = self::append_event( $doc, $p, $message ); if ( is_wp_error( $added ) ) { return $added; }
            if ( null === $index ) { $doc['parcels'][] = $p; } else { $doc['parcels'][ $index ] = $p; }
            return $doc;
        } );
    }

    /** The legacy Woo field remains supported; subsequent numbers become additional parcels. */
    public static function capture_legacy( $order, $manual = false, $previous_number = '' ) {
        $legacy = BCRM_Tracking::legacy( $order );
        if ( ! $legacy['number'] && ! $manual ) { return; }
        return self::mutate( $order->get_id(), static function( $doc ) use ( $legacy, $manual, $previous_number ) {
            $index = null;
            foreach ( $doc['parcels'] as $i => $p ) { if ( 'outbound' === $p['direction'] && $p['number'] === ( $manual && $previous_number ? $previous_number : $legacy['number'] ) ) { $index = $i; break; } }
            if ( null === $index && count( $doc['parcels'] ) >= self::MAX_PARCELS ) { return self::error( 'Limite de colis atteinte.' ); }
            $p = null === $index ? self::blank( wp_generate_uuid4() ) : $doc['parcels'][ $index ];
            $p['number'] = $legacy['number'];
            if ( $manual || null === $index || 'legacy-main' === $p['id'] ) {
                foreach ( array( 'carrier', 'url', 'status', 'shipped_at', 'delivered_at' ) as $key ) { $p[ $key ] = $legacy[ $key ]; }
                $p['carrier_code'] = self::carrier_code( $p['carrier'] );
            }
            // Repeated reception of the same tracking number must not roll back a delivery event.
            if ( ! $manual && null !== $index && $doc['revision'] > 0 ) { return $doc; }
            $p['source'] = $manual ? 'manual' : 'woocommerce'; $p['updated_at'] = self::now(); $p['status_at'] = self::now();
            $ok = self::append_event( $doc, $p, $manual ? 'Suivi manuel enregistré' : 'Numéro reçu via WooCommerce' );
            if ( is_wp_error( $ok ) ) { return $ok; }
            if ( null === $index ) { $doc['parcels'][] = $p; } else { $doc['parcels'][ $index ] = $p; }
            return $doc;
        } );
    }

    /** Optional adapter contract. This is not a claim about Iziship's payload format. */
    public static function import_events( $id, $events ) {
        if ( ! is_array( $events ) || ! $events || array_keys( $events ) !== range( 0, count( $events ) - 1 ) || count( $events ) > 50 ) { return self::error( 'Le lot doit contenir de 1 à 50 événements.' ); }
        return self::mutate( $id, static function( $doc ) use ( $events ) {
            foreach ( $events as $event ) {
                if ( ! is_array( $event ) || ! is_string( $event['event_id'] ?? null ) || ! preg_match( '/^[a-zA-Z0-9._:-]{1,100}$/D', $event['event_id'] ) || ! is_string( $event['parcel_id'] ?? null ) || ! preg_match( '/^[a-zA-Z0-9._:-]{1,80}$/D', $event['parcel_id'] ) || ! is_string( $event['occurred_at'] ?? null ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D', $event['occurred_at'] ) ) { return self::error( 'Événement invalide : identifiants stables et date ISO avec fuseau requis.' ); }
                try { $date = new DateTimeImmutable( $event['occurred_at'] ); $errors = DateTimeImmutable::getLastErrors(); }
                catch ( Exception $e ) { return self::error( 'Date d’événement invalide.' ); }
                if ( ( $errors && ( $errors['warning_count'] || $errors['error_count'] ) ) || $date->getTimestamp() > time() + 300 ) { return self::error( 'Date d’événement invalide ou future.' ); }
                if ( ! is_string( $event['status'] ?? null ) || ! isset( self::STATUSES[ $event['status'] ] ) ) { return self::error( 'Statut d’événement inconnu.' ); }
                ksort( $event ); $fingerprint = hash( 'sha256', wp_json_encode( $event ) );
                $duplicate = false;
                foreach ( $doc['events'] as $previous ) {
                    if ( 'external:' . $event['event_id'] === $previous['id'] ) {
                        if ( $fingerprint !== $previous['fingerprint'] ) { return self::error( 'Cet identifiant d’événement existe avec un contenu différent.', 409 ); }
                        $duplicate = true; break;
                    }
                }
                if ( $duplicate ) { continue; }
                $index = null;
                foreach ( $doc['parcels'] as $i => $p ) { if ( $p['id'] === $event['parcel_id'] ) { $index = $i; break; } }
                if ( null === $index && count( $doc['parcels'] ) >= self::MAX_PARCELS ) { return self::error( 'Limite de colis atteinte.' ); }
                $old = null === $index ? self::blank( $event['parcel_id'] ) : $doc['parcels'][ $index ];
                $input = array_intersect_key( $event, array_flip( array( 'number', 'carrier_code', 'carrier', 'url', 'direction', 'status', 'shipped_at', 'delivered_at', 'expected_at', 'relay_name', 'relay_address' ) ) );
                if ( 'shipped' === $event['status'] && empty( $old['shipped_at'] ) && ! isset( $input['shipped_at'] ) ) { $input['shipped_at'] = $date->setTimezone( wp_timezone() )->format( 'Y-m-d' ); }
                if ( 'delivered' === $event['status'] && ! isset( $input['delivered_at'] ) ) { $input['delivered_at'] = $date->setTimezone( wp_timezone() )->format( 'Y-m-d' ); }
                $p = self::normalize( $input, $old ); if ( is_wp_error( $p ) ) { return $p; }
                foreach ( $doc['parcels'] as $other ) {
                    if ( $p['number'] && $p['id'] !== $other['id'] && $p['number'] === $other['number'] && $p['carrier_code'] === $other['carrier_code'] && $p['direction'] === $other['direction'] && ! $other['archived'] ) { return self::error( 'Un autre colis utilise déjà ce numéro. Réutilisez son parcel_id.', 409 ); }
                }
                if ( $old['archived'] ) { return self::error( 'Le colis est archivé. Restaurez-le avant une nouvelle importation.', 409 ); }
                $p['source'] = 'woocommerce'; $p['updated_at'] = self::now(); $p['status_at'] = $date->format( DATE_ATOM );
                $ok = self::append_event( $doc, $p, 'Événement reçu via WooCommerce', 'external:' . $event['event_id'], $date->format( DATE_ATOM ), $fingerprint );
                if ( is_wp_error( $ok ) ) { return $ok; }
                // Preserve newer state, while retaining late events in the auditable history.
                if ( $old['status_at'] && strtotime( $old['status_at'] ) > $date->getTimestamp() ) { continue; }
                if ( null === $index ) { $doc['parcels'][] = $p; } else { $doc['parcels'][ $index ] = $p; }
            }
            return $doc;
        } );
    }

    public static function observe_events( $order, $request ) {
        if ( ! $order instanceof WC_Order || ! $request instanceof WP_REST_Request ) { return; }
        foreach ( (array) $request->get_param( 'meta_data' ) as $entry ) {
            if ( ! is_array( $entry ) || '_belmains_shipping_events' !== ( $entry['key'] ?? '' ) ) { continue; }
            $result = self::import_events( $order->get_id(), $entry['value'] ?? null );
            $fresh = wc_get_order( $order->get_id() ); $fresh->read_meta_data( true );
            if ( is_wp_error( $result ) ) {
                $fresh->update_meta_data( '_bcrm_shipping_import_error', array( 'at' => self::now(), 'message' => $result->get_error_message() ) );
            } else {
                $fresh->delete_meta_data( '_bcrm_shipping_import_error' );
                update_option( 'bcrm_shipping_events_last_received', self::now(), false );
            }
            // The canonical history is the source of truth; don't keep a second unbounded raw payload.
            $fresh->delete_meta_data( '_belmains_shipping_events' ); $fresh->save_meta_data();
        }
    }

    public static function protect_document( $order, $request ) {
        $protected_ids = array();
        if ( $order instanceof WC_Order ) {
            foreach ( $order->get_meta_data() as $meta ) { $item = $meta->get_data(); if ( in_array( $item['key'], array( self::META, '_bcrm_shipping_import_error' ), true ) ) { $protected_ids[] = (int) $item['id']; } }
        }
        foreach ( (array) $request->get_param( 'meta_data' ) as $entry ) {
            if ( is_array( $entry ) && ( in_array( $entry['key'] ?? '', array( self::META, '_bcrm_shipping_import_error' ), true ) || ( ! empty( $entry['id'] ) && in_array( (int) $entry['id'], $protected_ids, true ) ) ) ) {
                return self::error( 'Utilisez le contrat _belmains_shipping_events pour le connecteur ou l’éditeur de colis pour une modification manuelle.', 403 );
            }
        }
        return $order;
    }

    /** Customer projection explicitly excludes notes, labels, actor IDs and connector errors. */
    public static function customer_parcels( $order ) {
        $result = array();
        foreach ( self::document( $order )['parcels'] as $p ) {
            if ( $p['archived'] || 'cancelled' === $p['status'] || ! $p['number'] ) { continue; }
            $p = self::present( $p );
            $result[] = array_intersect_key( $p, array_flip( array( 'id', 'number', 'carrier', 'url', 'status', 'status_label', 'direction', 'shipped_at', 'delivered_at', 'expected_at', 'relay_name', 'relay_address' ) ) );
        }
        return $result;
    }

    public static function listing( $from, $to, $page = 1, $search = '', $status = '', $carrier = '', $direction = '', $attention = '', $export = false ) {
        $scan = BCRM_Commerce::scan_orders( $from, $to ); if ( is_wp_error( $scan ) ) { return $scan; }
        if ( $status && ! isset( self::STATUSES[ $status ] ) ) { return self::error( 'Statut inconnu.' ); }
        if ( $carrier && ! isset( self::carriers()[ $carrier ] ) ) { return self::error( 'Transporteur inconnu.' ); }
        if ( $direction && ! in_array( $direction, array( 'outbound', 'return' ), true ) ) { return self::error( 'Sens du colis inconnu.' ); }
        if ( $attention && ! in_array( $attention, array( 'overdue', 'incident', 'missing_tracking', 'import_error' ), true ) ) { return self::error( 'Filtre d’attention inconnu.' ); }
        $metrics = array_fill_keys( array( 'parcels', 'pending', 'shipped', 'in_transit', 'out_for_delivery', 'relay', 'delivered', 'exception', 'returned', 'cancelled', 'label_created', 'overdue', 'returns', 'missing_tracking', 'orders_without_parcels' ), 0 );
        $items = array(); $search = remove_accents( self::text( $search ) );
        foreach ( $scan['orders'] as $order ) {
            $doc = self::document( $order );
            $active = array_values( array_filter( $doc['parcels'], static function( $p ) { return ! $p['archived']; } ) );
            if ( ! $active && in_array( $order->get_status(), array( 'processing', 'on-hold', 'completed' ), true ) ) {
                $physical = false;
                foreach ( $order->get_items() as $line ) { $product = $line->get_product(); if ( ! $product || ! $product->is_virtual() ) { $physical = true; break; } }
                if ( $physical ) { ++$metrics['orders_without_parcels']; $active[] = self::blank( '' ); }
            }
            foreach ( $active as $p ) {
                $p = self::present( $p );
                if ( $p['id'] ) { ++$metrics['parcels']; ++$metrics[ $p['status'] ]; }
                if ( 'return' === $p['direction'] ) { ++$metrics['returns']; }
                if ( $p['overdue'] ) { ++$metrics['overdue']; }
                if ( ! $p['number'] && 'cancelled' !== $p['status'] ) { ++$metrics['missing_tracking']; }
                $import_error = $order->get_meta( '_bcrm_shipping_import_error', true );
                if ( ( $status && $p['status'] !== $status ) || ( $carrier && $p['carrier_code'] !== $carrier ) || ( $direction && $p['direction'] !== $direction ) ) { continue; }
                if ( ( 'overdue' === $attention && ! $p['overdue'] ) || ( 'incident' === $attention && 'exception' !== $p['status'] ) || ( 'missing_tracking' === $attention && ( $p['number'] || 'cancelled' === $p['status'] ) ) || ( 'import_error' === $attention && ! $import_error ) ) { continue; }
                $haystack = implode( ' ', array( $order->get_order_number(), $order->get_formatted_billing_full_name(), $order->get_billing_email(), $p['number'], $p['carrier'] ) );
                if ( $search && false === stripos( remove_accents( $haystack ), $search ) ) { continue; }
                $items[] = array( 'order_id' => $order->get_id(), 'order_number' => $order->get_order_number(), 'customer' => $order->get_formatted_billing_full_name(),
                    'email' => $order->get_billing_email(), 'order_status' => $order->get_status(), 'revision' => $doc['revision'], 'parcel' => $p, 'import_error' => $import_error ?: null );
            }
        }
        usort( $items, static function( $a, $b ) {
            $score = static function( $row ) { return ( $row['import_error'] ? 8 : 0 ) + ( 'exception' === $row['parcel']['status'] ? 4 : 0 ) + ( $row['parcel']['overdue'] ? 2 : 0 ) + ( ! $row['parcel']['number'] ? 1 : 0 ); };
            return $score( $b ) <=> $score( $a ) ?: $b['order_id'] <=> $a['order_id'];
        } );
        $total = count( $items ); $pages = (int) ceil( $total / 20 ); $page = max( 1, min( (int) $page, max( 1, $pages ) ) );
        return array( 'items' => $export ? array_slice( $items, 0, 5001 ) : array_slice( $items, ( $page - 1 ) * 20, 20 ), 'total' => $total, 'pages' => $pages, 'page' => $page, 'metrics' => $metrics,
            'partial' => ! empty( $scan['capped'] ), 'warnings' => array_merge( $scan['warnings'] ?? array(), array( 'Période basée sur la création des commandes. Compteurs de tous les colis actifs de cette période ; les filtres agissent sur la liste.', 'Un retard est un repère interne en jours calendaires, pas une confirmation d’incident transporteur.' ) ), 'carriers' => self::carriers(), 'statuses' => self::STATUSES );
    }

    public static function export( $request ) {
        $range = BCRM_App::range( $request ); if ( is_wp_error( $range ) ) { return $range; }
        $result = self::listing( $range[0], $range[1], 1, (string) $request['search'], (string) $request['status'], (string) $request['carrier'], (string) $request['direction'], (string) $request['attention'], true );
        if ( is_wp_error( $result ) ) { return $result; }
        if ( $result['partial'] || $result['total'] > 5000 ) { return self::error( 'Réduisez la période ou les filtres pour exporter au maximum 5 000 colis complets.' ); }
        $fp = fopen( 'php://temp', 'r+' ); fwrite( $fp, "\xEF\xBB\xBF" );
        fputcsv( $fp, array( 'Commande', 'Client', 'Sens', 'Transporteur', 'Numéro', 'Statut', 'Expédition', 'Prévue', 'Livraison', 'Retard', 'Lien', 'Source' ), ';' );
        foreach ( $result['items'] as $item ) {
            $p = $item['parcel']; $row = array( $item['order_number'], $item['customer'], 'return' === $p['direction'] ? 'Retour' : 'Envoi', $p['carrier'], $p['number'], $p['status_label'], $p['shipped_at'], $p['expected_at'], $p['delivered_at'], $p['overdue'] ? 'Oui' : 'Non', $p['url'], $p['source'] );
            $row = array_map( static function( $v ) { $v = (string) $v; return preg_match( '/^[\s]*[=+\-@\t\r\n]/u', $v ) ? "'" . $v : $v; }, $row );
            fputcsv( $fp, $row, ';' );
        }
        rewind( $fp ); $csv = stream_get_contents( $fp ); fclose( $fp );
        return array( 'filename' => 'belmains-colis-' . $range[0] . '-' . $range[1] . '.csv', 'csv' => $csv );
    }

    public static function history( $id ) {
        $order = wc_get_order( absint( $id ) ); if ( ! $order instanceof WC_Order ) { return self::error( 'Commande introuvable.', 404 ); }
        $doc = self::document( $order );
        foreach ( $doc['events'] as &$event ) { unset( $event['fingerprint'] ); } unset( $event );
        return array( 'order_id' => $order->get_id(), 'exported_at' => self::now(), 'shipping' => $doc );
    }
}
