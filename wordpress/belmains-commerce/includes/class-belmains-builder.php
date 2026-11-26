<?php
/** Product content editor. Drafts never influence a public purchase. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Belmains_Builder {
    private const CONTENT = '_belmains_builder_published';
    private const DRAFT = '_belmains_builder_draft';
    private const REVISION = '_belmains_builder_revision';
    private const LOCK = 'belmains_builder_write_lock';

    public static function register() {
        if ( is_file( __DIR__ . '/builder-schema.php' ) ) { require_once __DIR__ . '/builder-schema.php'; }
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
        add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
        add_action( 'template_redirect', array( __CLASS__, 'protect_preview' ), -10 );
        add_filter( 'wp_robots', array( __CLASS__, 'preview_robots' ) );
    }

    public static function schema() { return function_exists( 'belmains_builder_schema' ) ? belmains_builder_schema() : array(); }

    public static function schema_defaults() {
        $defaults = array();
        foreach ( self::schema() as $section ) {
            foreach ( $section['fields'] as $field ) { $defaults[ $field['key'] ] = $field['default'] ?? ''; }
        }
        return $defaults;
    }

    public static function menu() {
        add_menu_page( 'Ma boutique', 'Ma boutique', 'manage_woocommerce', 'belmains-builder', array( __CLASS__, 'page' ), 'dashicons-store', 55 );
    }

    public static function page() {
        if ( ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'edit_products' ) ) { wp_die( 'Accès non autorisé.', '', array( 'response' => 403 ) ); }
        echo '<div class="wrap"><div id="belmains-builder"><p>Chargement de votre boutique…</p></div><noscript>Activez JavaScript pour utiliser l’éditeur de boutique.</noscript></div>';
    }

    public static function assets( $hook ) {
        if ( 'toplevel_page_belmains-builder' !== $hook ) { return; }
        wp_enqueue_media();
        $url = plugin_dir_url( dirname( __FILE__ ) ) . 'assets/';
        wp_enqueue_style( 'belmains-builder', $url . 'builder-admin.css', array(), Belmains_Commerce::VERSION );
        wp_enqueue_script( 'belmains-builder', $url . 'builder-admin.js', array(), Belmains_Commerce::VERSION, true );
        wp_localize_script( 'belmains-builder', 'BelmainsBuilder', array(
            'api' => rest_url( 'belmains-builder/v1/' ), 'nonce' => wp_create_nonce( 'wp_rest' ),
            'activeId' => Belmains_Commerce::product_id(), 'adminUrl' => admin_url(), 'homeUrl' => home_url( '/' ),
        ) );
    }

    public static function routes() {
        $permission = array( __CLASS__, 'permission' );
        register_rest_route( 'belmains-builder/v1', '/products', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'products' ), 'permission_callback' => $permission ) );
        register_rest_route( 'belmains-builder/v1', '/products/(?P<id>\d+)', array(
            array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'get_product' ), 'permission_callback' => $permission ),
            array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'save' ), 'permission_callback' => $permission ),
        ) );
        foreach ( array( 'duplicate', 'discard' ) as $action ) {
            register_rest_route( 'belmains-builder/v1', '/products/(?P<id>\d+)/' . $action, array( 'methods' => 'POST', 'callback' => array( __CLASS__, $action ), 'permission_callback' => $permission ) );
        }
    }

    public static function permission( $request ) {
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'edit_products' ) ) {
            return new WP_Error( 'belmains_forbidden', 'Connectez-vous avec un compte autorisé à modifier la boutique.', array( 'status' => 403 ) );
        }
        // Explicitly verify the REST cookie nonce even for internal REST dispatch.
        if ( ! wp_verify_nonce( (string) $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
            return new WP_Error( 'belmains_nonce', 'Votre session a expiré. Actualisez la page.', array( 'status' => 403 ) );
        }
        if ( $request['id'] && ! current_user_can( 'edit_post', absint( $request['id'] ) ) ) {
            return new WP_Error( 'belmains_forbidden', 'Vous ne pouvez pas modifier ce produit.', array( 'status' => 403 ) );
        }
        return true;
    }

    private static function eligible( $id ) {
        $product = wc_get_product( absint( $id ) );
        if ( ! $product || ! $product->is_type( 'simple' ) || $product->is_virtual() || $product->is_downloadable() || 'trash' === $product->get_status() ) {
            return new WP_Error( 'belmains_product', 'Choisissez un produit physique simple.', array( 'status' => 404 ) );
        }
        return $product;
    }

    private static function published_values( $product ) {
        $content = $product->get_meta( self::CONTENT, true );
        $values = array_replace( self::schema_defaults(), is_array( $content ) ? $content : array() );
        if ( ! is_array( $content ) && $product->get_id() !== Belmains_Commerce::product_id() ) {
            // Import an existing Woo product without borrowing claims or glove photographs.
            foreach ( self::schema() as $section ) {
                if ( in_array( $section['id'], array( 'brand', 'services' ), true ) ) { continue; }
                foreach ( $section['fields'] as $field ) {
                    if ( in_array( $field['type'], array( 'image', 'video' ), true ) ) { $values[ $field['key'] ] = array( 'id' => 0, 'url' => '', 'alt' => '' ); }
                    elseif ( 'repeater' === $field['type'] ) { $values[ $field['key'] ] = array(); }
                    elseif ( 'checkbox' === $field['type'] ) { $values[ $field['key'] ] = false; }
                    elseif ( in_array( $field['type'], array( 'text', 'textarea', 'rich' ), true ) ) { $values[ $field['key'] ] = ''; }
                }
            }
            $values['product_description'] = sanitize_textarea_field( $product->get_short_description() ?: $product->get_description() );
            $values['color'] = $product->get_attribute( 'Couleur' );
            $values['hero_title'] = $product->get_name();
            $values['footer_product_label'] = $product->get_name();
            $values['brand_ribbon_enabled'] = false;
            $ids = array_unique( array_filter( array_merge( array( $product->get_image_id() ), $product->get_gallery_image_ids() ) ) );
            foreach ( $ids as $id ) {
                $url = wp_get_attachment_url( $id );
                if ( ! $url ) { continue; }
                $values['gallery'][] = array( 'image' => array( 'id' => (int) $id, 'url' => $url, 'alt' => get_post_meta( $id, '_wp_attachment_image_alt', true ) ), 'label' => get_the_title( $id ) );
            }
        }
        $offer = Belmains_Commerce::offer_for_product( $product );
        $values['product_name'] = $product->get_name( 'edit' );
        $values['regular_price'] = $product->get_regular_price( 'edit' );
        $values['sale_price'] = $product->get_sale_price( 'edit' );
        $values['duo_enabled'] = $offer['duo_enabled'];
        $values['duo_price'] = number_format( $offer['duo_cents'] / 100, 2, '.', '' );
        $values['duo_compare_price'] = $offer['duo_regular_cents'] ? number_format( $offer['duo_regular_cents'] / 100, 2, '.', '' ) : '';
        return $values;
    }

    private static function values( $product, $draft = true ) {
        $values = self::published_values( $product );
        $saved = $product->get_meta( self::DRAFT, true );
        return $draft && is_array( $saved ) ? array_replace( $values, $saved ) : $values;
    }

    private static function revision( $product ) {
        // Include native WooCommerce edits and featured-product changes in conflict detection.
        return hash( 'sha256', wp_json_encode( array( $product->get_id(), (int) $product->get_meta( self::REVISION, true ),
            $product->get_name( 'edit' ), $product->get_regular_price( 'edit' ), $product->get_sale_price( 'edit' ),
            $product->get_status(), $product->get_meta( '_belmains_duo_enabled', true ),
            $product->get_meta( '_belmains_duo_price', true ), $product->get_meta( '_belmains_duo_compare_price', true ),
            $product->get_short_description( 'edit' ), $product->get_image_id( 'edit' ), $product->get_gallery_image_ids( 'edit' ),
            $product->get_date_on_sale_from( 'edit' ) ? $product->get_date_on_sale_from( 'edit' )->getTimestamp() : null,
            $product->get_date_on_sale_to( 'edit' ) ? $product->get_date_on_sale_to( 'edit' )->getTimestamp() : null,
            $product->get_attribute( 'Couleur' ), Belmains_Commerce::product_id() ) ) );
    }

    private static function payload( $product ) {
        return array( 'id' => $product->get_id(), 'revision' => self::revision( $product ), 'values' => self::values( $product ),
            'schema' => self::schema(), 'active' => $product->get_id() === Belmains_Commerce::product_id(), 'status' => $product->get_status(),
            'has_draft' => is_array( $product->get_meta( self::DRAFT, true ) ), 'stock_quantity' => $product->get_stock_quantity(),
            'edit_url' => get_edit_post_link( $product->get_id(), 'raw' ),
            'preview_url' => add_query_arg( array( 'belmains_preview' => $product->get_id(), '_belmains_preview_nonce' => wp_create_nonce( 'belmains_preview_' . $product->get_id() ) ), home_url( '/' ) ),
            'live_url' => home_url( '/#fiche-produit' ),
        );
    }

    public static function products() {
        $items = array();
        foreach ( wc_get_products( array( 'type' => 'simple', 'status' => array( 'publish', 'draft', 'pending', 'private' ), 'limit' => -1, 'orderby' => 'name', 'order' => 'ASC' ) ) as $product ) {
            if ( $product->is_virtual() || $product->is_downloadable() || ! current_user_can( 'edit_post', $product->get_id() ) ) { continue; }
            $items[] = array( 'id' => $product->get_id(), 'name' => $product->get_name(), 'status' => $product->get_status(),
                'active' => $product->get_id() === Belmains_Commerce::product_id(), 'has_draft' => is_array( $product->get_meta( self::DRAFT, true ) ),
                'image' => wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) ?: '', 'stock_quantity' => $product->get_stock_quantity() );
        }
        return array( 'items' => $items, 'active_id' => Belmains_Commerce::product_id() );
    }

    public static function get_product( $request ) {
        $product = self::eligible( $request['id'] );
        return is_wp_error( $product ) ? $product : self::payload( $product );
    }

    private static function invalid( $field, $message ) { return new WP_Error( 'belmains_validation', $message, array( 'status' => 400, 'field' => $field ) ); }

    private static function validate_field( $value, $field, $path ) {
        $type = $field['type'];
        if ( 'checkbox' === $type ) {
            return is_bool( $value ) ? $value : self::invalid( $path, 'Choisissez oui ou non pour « ' . $field['label'] . ' ».' );
        }
        if ( 'repeater' === $type ) {
            $maximum = min( 30, (int) ( $field['max_items'] ?? 30 ) );
            if ( ! is_array( $value ) || array_values( $value ) !== $value || count( $value ) > $maximum ) { return self::invalid( $path, 'Cette liste peut contenir au maximum ' . $maximum . ' éléments.' ); }
            $result = array();
            foreach ( $value as $index => $row ) {
                if ( ! is_array( $row ) ) { return self::invalid( $path, 'Un élément de cette liste est invalide.' ); }
                $clean = array();
                foreach ( $field['fields'] as $child ) {
                    $clean[ $child['key'] ] = self::validate_field( $row[ $child['key'] ] ?? $child['default'] ?? '', $child, $path . '.' . $index . '.' . $child['key'] );
                    if ( is_wp_error( $clean[ $child['key'] ] ) ) { return $clean[ $child['key'] ]; }
                }
                $result[] = $clean;
            }
            return $result;
        }
        if ( in_array( $type, array( 'image', 'video' ), true ) ) {
            if ( ! is_array( $value ) || ! is_scalar( $value['id'] ?? 0 ) || ! is_string( $value['url'] ?? '' ) || ! is_string( $value['alt'] ?? '' ) ) { return self::invalid( $path, 'Sélectionnez un média valide.' ); }
            $id = absint( $value['id'] ?? 0 );
            $url = trim( $value['url'] ?? '' );
            if ( $id ) {
                $mime = get_post_mime_type( $id );
                if ( 'attachment' !== get_post_type( $id ) || ! $mime || ! str_starts_with( $mime, $type . '/' ) || 'image/svg+xml' === $mime ) { return self::invalid( $path, 'Le type de ce média ne convient pas à ce champ.' ); }
                $url = wp_get_attachment_url( $id );
            }
            if ( $url && ( strlen( $url ) > 2048 || ! preg_match( '#^https?://#i', $url ) || ! wp_parse_url( $url, PHP_URL_HOST ) ) ) { return self::invalid( $path, 'Utilisez une adresse de média HTTP ou HTTPS valide.' ); }
            return array( 'id' => $id, 'url' => esc_url_raw( $url, array( 'https', 'http' ) ), 'alt' => sanitize_text_field( $value['alt'] ?? '' ) );
        }
        if ( ! is_scalar( $value ) || is_bool( $value ) ) { return self::invalid( $path, 'Le champ « ' . $field['label'] . ' » est invalide.' ); }
        $value = (string) $value;
        if ( strlen( $value ) > 20000 ) { return self::invalid( $path, 'Le texte est trop long.' ); }
        if ( 'color' === $type ) { return sanitize_hex_color( $value ) ?: self::invalid( $path, 'Choisissez une couleur valide.' ); }
        if ( 'number' === $type || in_array( $path, array( 'regular_price', 'sale_price', 'duo_price', 'duo_compare_price' ), true ) ) {
            $value = str_replace( ',', '.', trim( $value ) );
            if ( '' === $value && in_array( $path, array( 'sale_price', 'duo_price', 'duo_compare_price' ), true ) ) { return ''; }
            if ( ! preg_match( '/^\d+(?:\.\d{1,2})?$/', $value ) || (float) $value > 999999 ) { return self::invalid( $path, 'Indiquez un montant positif, avec deux décimales au maximum.' ); }
            if ( ( isset( $field['min'] ) && (float) $value < (float) $field['min'] ) || ( isset( $field['max'] ) && (float) $value > (float) $field['max'] ) ) { return self::invalid( $path, 'La valeur est en dehors des limites proposées.' ); }
            return wc_format_decimal( $value, 2 );
        }
        if ( 'select' === $type ) {
            $options = $field['options'] ?? array();
            $keys = array_values( $options ) === $options ? array_map( static function( $option ) { return is_array( $option ) ? $option['value'] : $option; }, $options ) : array_keys( $options );
            $keys = array_map( 'strval', $keys );
            if ( ! in_array( $value, $keys, true ) ) { return self::invalid( $path, 'Choisissez une option proposée.' ); }
        }
        if ( 'rich' === $type ) { return wp_kses( $value, array( 'em' => array(), 'strong' => array(), 'br' => array() ) ); }
        return 'textarea' === $type ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
    }

    public static function validate_values( $values ) {
        if ( ! is_array( $values ) || ! self::schema() ) { return self::invalid( 'values', 'Le contenu de la fiche est invalide.' ); }
        $result = array();
        foreach ( self::schema() as $section ) {
            foreach ( $section['fields'] as $field ) {
                if ( ! array_key_exists( $field['key'], $values ) ) { return self::invalid( $field['key'], 'Le champ « ' . $field['label'] . ' » est manquant. Actualisez l’éditeur.' ); }
                $result[ $field['key'] ] = self::validate_field( $values[ $field['key'] ], $field, $field['key'] );
                if ( is_wp_error( $result[ $field['key'] ] ) ) { return $result[ $field['key'] ]; }
            }
        }
        if ( '' === trim( $result['product_name'] ) ) { return self::invalid( 'product_name', 'Donnez un nom au produit.' ); }
        if ( (float) $result['regular_price'] <= 0 ) { return self::invalid( 'regular_price', 'Le prix normal doit être supérieur à zéro.' ); }
        if ( '' !== $result['sale_price'] && ( (float) $result['sale_price'] <= 0 || (float) $result['sale_price'] >= (float) $result['regular_price'] ) ) { return self::invalid( 'sale_price', 'Le prix promotionnel doit être positif et inférieur au prix normal. Laissez-le vide pour supprimer la promotion.' ); }
        if ( $result['duo_enabled'] && (float) $result['duo_price'] <= 0 ) { return self::invalid( 'duo_price', 'Indiquez le prix de l’offre pour deux produits.' ); }
        if ( '' !== $result['duo_compare_price'] && (float) $result['duo_compare_price'] < (float) $result['duo_price'] ) { return self::invalid( 'duo_compare_price', 'Le prix barré ne peut pas être inférieur au prix de l’offre.' ); }
        return $result;
    }

    /** Atomic database lock: stale object caches cannot replace a live writer. */
    private static function lock() {
        global $wpdb;
        $token = wp_generate_uuid4() . '|' . time();
        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LOCK ) );
        if ( $existing && (int) substr( $existing, strrpos( $existing, '|' ) + 1 ) < time() - 60 ) {
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", self::LOCK, $existing ) );
        }
        $inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s,%s,'off')", self::LOCK, $token ) );
        return 1 === $inserted ? $token : false;
    }

    private static function unlock( $token ) {
        global $wpdb;
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", self::LOCK, $token ) );
        wp_cache_delete( self::LOCK, 'options' );
    }

    private static function mutate( $request, $callback ) {
        $token = self::lock();
        if ( ! $token ) { return new WP_Error( 'belmains_busy', 'Une sauvegarde est en cours. Réessayez dans quelques secondes.', array( 'status' => 409 ) ); }
        $product = null;
        try {
            clean_post_cache( absint( $request['id'] ) );
            $product = self::eligible( $request['id'] );
            if ( is_wp_error( $product ) ) { return $product; }
            if ( ! is_string( $request['revision'] ) || ! hash_equals( self::revision( $product ), $request['revision'] ) ) {
                return new WP_Error( 'belmains_conflict', 'Cette fiche a été modifiée ailleurs. Rechargez-la avant de continuer pour ne pas écraser ces changements.', array( 'status' => 409 ) );
            }
            wc_transaction_query( 'start' );
            $result = $callback( $product );
            wc_transaction_query( is_wp_error( $result ) ? 'rollback' : 'commit' );
            return $result;
        } catch ( Throwable $error ) {
            wc_transaction_query( 'rollback' );
            if ( $product instanceof WC_Product ) { clean_post_cache( $product->get_id() ); }
            wp_cache_delete( 'belmains_product_id', 'options' );
            return new WP_Error( 'belmains_save', 'La sauvegarde n’a pas abouti. Rechargez la fiche et réessayez.', array( 'status' => 500 ) );
        } finally { self::unlock( $token ); }
    }

    private static function bump( $product ) { $product->update_meta_data( self::REVISION, (int) $product->get_meta( self::REVISION, true ) + 1 ); }

    public static function save( $request ) {
        $action = $request['action'];
        if ( ! in_array( $action, array( 'draft', 'publish' ), true ) ) { return self::invalid( 'action', 'Choisissez enregistrer ou publier.' ); }
        if ( 'publish' === $action && ! current_user_can( 'publish_products' ) ) { return new WP_Error( 'belmains_forbidden', 'Vous ne pouvez pas publier de produit.', array( 'status' => 403 ) ); }
        if ( isset( $request['activate'] ) && ! is_bool( $request['activate'] ) ) { return self::invalid( 'activate', 'Confirmez le choix du produit à afficher.' ); }
        $values = self::validate_values( $request['values'] );
        if ( is_wp_error( $values ) ) { return $values; }
        return self::mutate( $request, static function( $product ) use ( $request, $action, $values ) {
            if ( 'draft' === $action ) { $product->update_meta_data( self::DRAFT, $values ); }
            else {
                $product->set_name( $values['product_name'] );
                $product->set_regular_price( $values['regular_price'] );
                $product->set_sale_price( $values['sale_price'] );
                // This editor offers immediate promotions; cancel native schedules explicitly.
                $product->set_date_on_sale_from( null );
                $product->set_date_on_sale_to( null );
                $product->set_price( '' === $values['sale_price'] ? $values['regular_price'] : $values['sale_price'] );
                $product->set_short_description( $values['product_description'] ?? '' );
                $image_ids = array_values( array_filter( array_map( static function( $row ) { return absint( $row['image']['id'] ?? 0 ); }, $values['gallery'] ?? array() ) ) );
                if ( $image_ids ) {
                    $product->set_image_id( array_shift( $image_ids ) );
                    $product->set_gallery_image_ids( $image_ids );
                } elseif ( ! empty( $values['product_video']['url'] ) && ! empty( $values['video_poster']['id'] ) ) {
                    $product->set_image_id( absint( $values['video_poster']['id'] ) );
                    $product->set_gallery_image_ids( array() );
                } elseif ( empty( $values['gallery'] ) ) {
                    // An explicit removal must also clear the cart/order product image.
                    // Nonempty bundled galleries have no attachment IDs: preserve their
                    // existing Woo image until a library image replaces it.
                    $product->set_image_id( 0 );
                    $product->set_gallery_image_ids( array() );
                }
                $attributes = $product->get_attributes();
                if ( ! empty( $values['color'] ) ) {
                    $attribute = new WC_Product_Attribute();
                    $attribute->set_name( 'Couleur' );
                    $attribute->set_options( array( $values['color'] ) );
                    $attribute->set_visible( true );
                    $attributes['couleur'] = $attribute;
                } else { unset( $attributes['couleur'] ); }
                $product->set_attributes( $attributes );
                $product->update_meta_data( '_belmains_managed', 'yes' );
                $product->update_meta_data( '_belmains_duo_enabled', $values['duo_enabled'] ? 'yes' : 'no' );
                $product->update_meta_data( '_belmains_duo_price', $values['duo_price'] );
                $product->update_meta_data( '_belmains_duo_compare_price', $values['duo_compare_price'] );
                $product->update_meta_data( self::CONTENT, $values );
                $product->delete_meta_data( self::DRAFT );
                $product->set_status( 'publish' );
                if ( true === $request['activate'] && $product->get_id() !== Belmains_Commerce::product_id() ) {
                    $previous = Belmains_Commerce::product();
                    if ( $previous ) {
                        if ( ! is_array( $previous->get_meta( self::CONTENT, true ) ) ) {
                            // Freeze the storefront's legacy defaults before it stops being
                            // featured. A saved draft must never become public implicitly.
                            $previous->update_meta_data( self::CONTENT, self::published_values( $previous ) );
                        }
                        $old_offer = Belmains_Commerce::offer_for_product( $previous );
                        $previous->update_meta_data( '_belmains_managed', 'yes' );
                        $previous->update_meta_data( '_belmains_duo_enabled', $old_offer['duo_enabled'] ? 'yes' : 'no' );
                        $previous->update_meta_data( '_belmains_duo_price', wc_format_decimal( $old_offer['duo_cents'] / 100, 2 ) );
                        $previous->update_meta_data( '_belmains_duo_compare_price', wc_format_decimal( $old_offer['duo_regular_cents'] / 100, 2 ) );
                        $previous->save_meta_data();
                    }
                    update_option( 'belmains_product_id', $product->get_id(), false );
                }
            }
            self::bump( $product );
            $product->save();
            return self::payload( wc_get_product( $product->get_id() ) );
        } );
    }

    public static function discard( $request ) {
        return self::mutate( $request, static function( $product ) {
            $product->delete_meta_data( self::DRAFT );
            self::bump( $product );
            $product->save_meta_data();
            return self::payload( wc_get_product( $product->get_id() ) );
        } );
    }

    public static function duplicate( $request ) {
        return self::mutate( $request, static function( $source ) {
            $values = self::values( $source );
            $values['product_name'] = $values['product_name'] . ' — copie';
            $product = new WC_Product_Simple();
            $product->set_name( $values['product_name'] );
            $product->set_status( 'draft' );
            $product->set_catalog_visibility( 'hidden' );
            $product->set_regular_price( $values['regular_price'] );
            $product->set_sale_price( $values['sale_price'] );
            $product->set_manage_stock( true );
            $product->set_stock_quantity( 0 );
            $product->set_stock_status( 'outofstock' );
            $product->set_backorders( 'no' );
            $product->set_image_id( $source->get_image_id() );
            $product->update_meta_data( '_belmains_managed', 'yes' );
            $product->update_meta_data( '_belmains_duo_enabled', $values['duo_enabled'] ? 'yes' : 'no' );
            $product->update_meta_data( '_belmains_duo_price', $values['duo_price'] );
            $product->update_meta_data( '_belmains_duo_compare_price', $values['duo_compare_price'] );
            $product->update_meta_data( self::DRAFT, $values );
            self::bump( $product );
            $product->save();
            return self::payload( wc_get_product( $product->get_id() ) );
        } );
    }

    public static function is_preview() {
        return isset( $_GET['belmains_preview'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- validated below.
    }

    public static function preview_authorized() {
        if ( ! self::is_preview() ) { return false; }
        $id = isset( $_GET['belmains_preview'] ) && is_scalar( $_GET['belmains_preview'] ) ? absint( $_GET['belmains_preview'] ) : 0;
        $nonce = isset( $_GET['_belmains_preview_nonce'] ) && is_string( $_GET['_belmains_preview_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_belmains_preview_nonce'] ) ) : '';
        return $id && is_user_logged_in() && current_user_can( 'manage_woocommerce' ) && current_user_can( 'edit_products' )
            && current_user_can( 'edit_post', $id ) && wp_verify_nonce( $nonce, 'belmains_preview_' . $id ) && ! is_wp_error( self::eligible( $id ) );
    }

    public static function protect_preview() {
        if ( ! self::is_preview() ) { return; }
        if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
        nocache_headers();
        header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
        if ( ! self::preview_authorized() ) { wp_die( 'Cet aperçu est réservé aux personnes autorisées à modifier la boutique.', 'Aperçu privé', array( 'response' => 403 ) ); }
    }

    public static function preview_robots( $robots ) {
        if ( self::is_preview() ) { $robots['noindex'] = true; $robots['nofollow'] = true; }
        return $robots;
    }

    public static function view() {
        $preview = self::preview_authorized();
        if ( self::is_preview() && ! $preview ) { return array( 'product' => null, 'values' => self::schema_defaults(), 'preview' => false ); }
        $product = $preview ? self::eligible( absint( $_GET['belmains_preview'] ) ) : Belmains_Commerce::product();
        return array( 'product' => $product ?: null, 'values' => $product ? self::values( $product, $preview ) : self::schema_defaults(), 'preview' => (bool) $preview );
    }
}
