<?php
/** Run only with wp eval-file against the disposable local validation tables. */
global $wpdb;
if ( 'bcrm_validation_' !== $wpdb->prefix || 'local' !== wp_get_environment_type() ) { throw new RuntimeException( 'Builder tests require isolated local validation tables.' ); }
if ( ! class_exists( 'Belmains_Builder' ) ) { throw new RuntimeException( 'Activate Belmains Commerce.' ); }
add_filter( 'pre_wp_mail', '__return_false' );

final class Belmains_Builder_Integration {
    private static $checks = 0;
    private static $ids = array();
    private static $users = array();
    private static $original_active;
    private static $original_user;
    private static $currency;
    private static $admin;
    private static $old_get;
    private static $attachments = array();

    private static function check( $condition, $name ) {
        if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $name ); }
        ++self::$checks;
        echo 'PASS ' . $name . "\n";
    }

    private static function request( $method, $path, $body = array(), $nonce = true ) {
        $request = new WP_REST_Request( $method, '/belmains-builder/v1/products' . $path );
        $request->set_header( 'Content-Type', 'application/json' );
        if ( $nonce ) { $request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) ); }
        $request->set_body( wp_json_encode( $body ) );
        return rest_do_request( $request );
    }

    private static function edit( $payload, $values, $action = 'draft', $activate = false ) {
        return self::request( 'POST', '/' . $payload['id'], array( 'revision' => $payload['revision'], 'values' => $values, 'action' => $action, 'activate' => $activate ) );
    }

    private static function product( $name, $price, $stock = 20 ) {
        $product = new WC_Product_Simple();
        $product->set_name( $name );
        $product->set_status( 'publish' );
        $product->set_regular_price( '109.99' );
        $product->set_sale_price( $price );
        $product->set_manage_stock( true );
        $product->set_stock_quantity( $stock );
        $product->set_sku( 'BUILDER-' . wp_generate_uuid4() );
        $product->save();
        self::$ids[] = $product->get_id();
        return $product;
    }

    public static function run() {
        self::$original_active = get_option( 'belmains_product_id', false );
        self::$currency = get_option( 'woocommerce_currency' );
        self::$original_user = get_current_user_id();
        self::$old_get = $_GET;
        try {
            update_option( 'woocommerce_currency', 'EUR' );
            self::$admin = wp_insert_user( array( 'user_login' => 'builder-' . wp_generate_uuid4(), 'user_pass' => wp_generate_password( 32 ), 'role' => 'administrator', 'user_email' => 'builder-' . wp_generate_uuid4() . '@example.invalid' ) );
            if ( is_wp_error( self::$admin ) ) { throw new RuntimeException( 'Cannot create fixture user.' ); }
            self::$users[] = self::$admin;
            wp_set_current_user( self::$admin );
            $product = self::product( 'Builder fixture', '89.99' );
            $id = $product->get_id();
            update_option( 'belmains_product_id', $id );
            if ( ! did_action( 'rest_api_init' ) ) { do_action( 'rest_api_init' ); }
            $first = self::request( 'GET', '/' . $id )->get_data();
            self::check( 10 === count( $first['schema'] ), 'ten client-oriented sections available' );
            self::check( $first['active'] && ! $first['has_draft'], 'legacy product opens without creating draft' );
            self::check( '89.99' === $first['values']['sale_price'] && '149.99' === $first['values']['duo_price'], 'legacy canonical Woo price and duo defaults preserved' );
            self::check( 20 === $first['stock_quantity'], 'stock displayed separately' );
            $list = self::request( 'GET', '' )->get_data();
            self::check( $id === $list['active_id'] && in_array( $id, array_column( $list['items'], 'id' ), true ), 'product switcher includes selected product' );

            wp_set_current_user( 0 );
            self::check( 403 === self::request( 'GET', '/' . $id )->get_status(), 'anonymous editor request denied' );
            wp_set_current_user( self::$admin );
            self::check( 403 === self::request( 'GET', '/' . $id, array(), false )->get_status(), 'REST nonce required even for administrator' );
            $subscriber = wp_insert_user( array( 'user_login' => 'buyer-' . wp_generate_uuid4(), 'user_pass' => wp_generate_password( 32 ), 'role' => 'subscriber', 'user_email' => 'buyer-' . wp_generate_uuid4() . '@example.invalid' ) );
            self::$users[] = $subscriber;
            wp_set_current_user( $subscriber );
            self::check( 403 === self::request( 'POST', '/' . $id, array() )->get_status(), 'subscriber cannot write product' );
            wp_set_current_user( self::$admin );

            $invalid = $first['values'];
            $invalid['sale_price'] = 'javascript:120';
            self::check( 400 === self::edit( $first, $invalid )->get_status(), 'malformed price rejected before write' );
            $invalid['sale_price'] = '120';
            self::check( 400 === self::edit( $first, $invalid )->get_status(), 'sale price higher than regular price rejected' );
            $invalid = $first['values'];
            $invalid['hero_image']['url'] = 'javascript:alert(1)';
            self::check( 400 === self::edit( $first, $invalid )->get_status(), 'unsafe media protocol rejected' );
            $invalid = $first['values'];
            $invalid['brand_color'] = 'url(javascript:test)';
            self::check( 400 === self::edit( $first, $invalid )->get_status(), 'unsafe CSS color rejected' );
            $invalid = $first['values'];
            $invalid['hero_enabled'] = 'false';
            self::check( 400 === self::edit( $first, $invalid )->get_status(), 'ambiguous checkbox value rejected' );
            $invalid = $first['values'];
            $invalid['gallery'] = array_fill( 0, 13, $invalid['gallery'][0] );
            self::check( 400 === self::edit( $first, $invalid )->get_status(), 'schema repeater limit enforced' );
            self::check( ! wc_get_product( $id )->meta_exists( '_belmains_builder_draft' ), 'validation failures leave product untouched' );
            $video = wp_insert_attachment( array( 'post_title' => 'Builder video fixture', 'post_status' => 'inherit', 'post_mime_type' => 'video/mp4', 'guid' => home_url( '/builder-test.mp4' ) ) );
            self::$attachments[] = $video;
            $invalid = $first['values'];
            $invalid['hero_image'] = array( 'id' => $video, 'url' => home_url( '/builder-test.mp4' ), 'alt' => '' );
            self::check( 400 === self::edit( $first, $invalid )->get_status(), 'video attachment cannot be placed in image field' );

            $lock_method = new ReflectionMethod( 'Belmains_Builder', 'lock' );
            $lock_method->setAccessible( true );
            $unlock_method = new ReflectionMethod( 'Belmains_Builder', 'unlock' );
            $unlock_method->setAccessible( true );
            $token = $lock_method->invoke( null );
            try {
                wp_cache_delete( 'belmains_builder_write_lock', 'options' );
                self::check( 409 === self::edit( $first, $first['values'] )->get_status(), 'atomic writer lock prevents overlapping save despite cache miss' );
                $unlock_method->invoke( null, 'not-the-owner' );
                self::check( false === $lock_method->invoke( null ), 'another owner cannot release save lock' );
            } finally { $unlock_method->invoke( null, $token ); }

            $values = $first['values'];
            $values['product_name'] = 'Le nouveau produit';
            $values['sale_price'] = '79,99';
            $values['duo_price'] = '129,99';
            $values['hero_title'] = 'Titre <em>soigné</em><img src=x onerror=alert(1)><script>alert(1)</script>';
            $response = self::edit( $first, $values );
            self::check( 200 === $response->get_status(), 'complete draft saves' );
            $draft = $response->get_data();
            self::check( $draft['has_draft'] && $draft['revision'] !== $first['revision'], 'draft revision advances' );
            self::check( '79.99' === $draft['values']['sale_price'], 'French decimal converted to canonical money' );
            self::check( ! str_contains( $draft['values']['hero_title'], '<script' ) && ! str_contains( $draft['values']['hero_title'], '<img' ), 'rich text sanitizes executable markup' );
            self::check( '89.99' === wc_get_product( $id )->get_price() && 20 === wc_get_product( $id )->get_stock_quantity(), 'saving draft preserves public price and stock' );
            self::check( 'Builder fixture' === Belmains_Builder::view()['values']['product_name'], 'public view ignores saved draft' );
            self::check( 409 === self::edit( $first, $values )->get_status(), 'stale draft revision cannot overwrite newer content' );

            parse_str( wp_parse_url( $draft['preview_url'], PHP_URL_QUERY ), $_GET );
            self::check( Belmains_Builder::preview_authorized(), 'owner protected preview authorized' );
            self::check( Belmains_Builder::view()['preview'] && 'Le nouveau produit' === Belmains_Builder::view()['values']['product_name'], 'protected preview renders saved draft' );
            self::check( false === Belmains_Commerce::frontend_config()['available'], 'preview cannot enable buying draft' );
            self::check( ! empty( Belmains_Builder::preview_robots( array() )['noindex'] ), 'preview excluded from indexing' );
            wp_set_current_user( 0 );
            self::check( ! Belmains_Builder::preview_authorized() && ! Belmains_Builder::view()['product'], 'shared preview URL does not expose draft to visitor' );
            wp_set_current_user( self::$admin );
            $_GET['_belmains_preview_nonce'] = 'invalid';
            self::check( ! Belmains_Builder::preview_authorized(), 'invalid preview nonce denied' );
            $_GET = array();

            $fresh = wc_get_product( $id );
            $fresh->set_stock_quantity( 17 );
            $fresh->save();
            $published = self::edit( $draft, $draft['values'], 'publish' );
            self::check( 200 === $published->get_status(), 'publication succeeds after stock-only update' );
            $published = $published->get_data();
            $fresh = wc_get_product( $id );
            self::check( ! $published['has_draft'] && 'Le nouveau produit' === $fresh->get_name(), 'published content replaces draft and Woo title' );
            self::check( 17 === $fresh->get_stock_quantity(), 'publication never overwrites updated stock' );
            self::check( '79.99' === $fresh->get_price() && '129.99' === $fresh->get_meta( '_belmains_duo_price' ), 'single and duo pricing published canonically' );
            self::check( 'Gris' === $fresh->get_attribute( 'Couleur' ) && $fresh->get_short_description(), 'native Woo color and summary synchronized' );
            self::check( 12999 === Belmains_Commerce::price_cents( 2 ), 'cart totals use newly published offer' );

            $fresh->set_sale_price( '69.99' );
            $fresh->set_price( '69.99' );
            $fresh->save();
            self::check( 6999 === Belmains_Commerce::offer_for_product( wc_get_product( $id ) )['single_cents'], 'native Woo price change immediately used by commerce' );
            self::check( 409 === self::edit( $published, $published['values'] )->get_status(), 'native Woo price edit invalidates stale editor revision' );
            $published = self::request( 'GET', '/' . $id )->get_data();
            $copy_response = self::request( 'POST', '/' . $id . '/duplicate', array( 'revision' => $published['revision'] ) );
            self::check( 200 === $copy_response->get_status(), 'duplicate creates new product' );
            $copy = $copy_response->get_data();
            self::$ids[] = $copy['id'];
            $copy_product = wc_get_product( $copy['id'] );
            self::check( 'draft' === $copy['status'] && $copy['has_draft'] && ! $copy['active'], 'duplicate stays draft without switching storefront' );
            self::check( 0 === $copy_product->get_stock_quantity() && '' === $copy_product->get_sku() && 'no' === $copy_product->get_backorders(), 'duplicate never inherits stock SKU or backorders' );
            self::check( 17 === wc_get_product( $id )->get_stock_quantity() && $id === Belmains_Commerce::product_id(), 'duplicate leaves source and active product unchanged' );

            $copy_values = $copy['values'];
            $copy_values['product_name'] = 'Deuxième produit';
            $copy_values['regular_price'] = '59.99';
            $copy_values['sale_price'] = '';
            $copy_values['duo_enabled'] = false;
            $copy_values['color'] = 'Bleu';
            $copy_response = self::edit( $copy, $copy_values, 'publish' );
            if ( 200 !== $copy_response->get_status() ) { echo wp_json_encode( $copy_response->get_data() ) . "\n"; }
            $copy_pub = $copy_response->get_data();
            self::check( 'publish' === $copy_pub['status'] && $id === Belmains_Commerce::product_id(), 'publish alone never switches featured product' );
            $copy_pub = self::edit( $copy_pub, $copy_values, 'publish', true )->get_data();
            self::check( $copy['id'] === Belmains_Commerce::product_id() && $copy_pub['active'], 'explicit activation switches featured product' );
            self::check( 12999 === Belmains_Commerce::price_cents( 2, wc_get_product( $id ) ), 'previous product keeps its independent duo offer' );
            self::check( 11998 === Belmains_Commerce::price_cents( 2 ), 'disabled duo applies normal quantity pricing' );
            self::check( 0 === wc_get_product( $copy['id'] )->get_stock_quantity(), 'activation preserves new product zero stock' );

            $legacy = self::product( 'Untouched legacy', '89.99' );
            update_option( 'belmains_product_id', $legacy->get_id() );
            $legacy_public = Belmains_Builder::view()['values'];
            $legacy_editor = self::request( 'GET', '/' . $legacy->get_id() )->get_data();
            $legacy_draft = $legacy_editor['values'];
            $legacy_draft['hero_title'] = 'Ce titre reste privé';
            $legacy_draft['gallery'] = array();
            self::edit( $legacy_editor, $legacy_draft );
            $copy_pub = self::request( 'GET', '/' . $copy['id'] )->get_data();
            self::edit( $copy_pub, $copy_values, 'publish', true );
            self::check( 'yes' === wc_get_product( $legacy->get_id() )->get_meta( '_belmains_managed' ), 'switch marks previously untouched legacy product as managed' );
            self::check( 14999 === Belmains_Commerce::price_cents( 2, wc_get_product( $legacy->get_id() ) ), 'legacy duo price pinned before active switch' );
            $legacy_frozen = wc_get_product( $legacy->get_id() )->get_meta( '_belmains_builder_published' );
            self::check( $legacy_public === $legacy_frozen, 'switch freezes all original legacy marketing without using private draft' );
            self::check( 'Ce titre reste privé' === wc_get_product( $legacy->get_id() )->get_meta( '_belmains_builder_draft' )['hero_title'], 'switch preserves unpublished draft separately' );
            $legacy_editor = self::request( 'GET', '/' . $legacy->get_id() )->get_data();
            $legacy_restored = self::request( 'POST', '/' . $legacy->get_id() . '/discard', array( 'revision' => $legacy_editor['revision'] ) )->get_data();
            self::check( $legacy_public['hero_title'] === $legacy_restored['values']['hero_title'] && $legacy_public['gallery'] === $legacy_restored['values']['gallery'] && $legacy_public['product_video'] === $legacy_restored['values']['product_video'], 'reopening old legacy product restores hero gallery and video' );
            $legacy_activated = self::edit( $legacy_restored, $legacy_restored['values'], 'publish', true );
            self::check( 200 === $legacy_activated->get_status() && $legacy_public['gallery'] === Belmains_Builder::view()['values']['gallery'] && $legacy_public['product_video'] === Belmains_Builder::view()['values']['product_video'], 'switching back to original product restores its marketing media' );

            WC()->initialize_session();
            WC()->initialize_cart();
            WC()->cart->empty_cart( false );
            $key = WC()->cart->add_to_cart( $id, 2 );
            self::check( (bool) $key, 'previous managed product remains valid in existing cart' );
            WC()->cart->calculate_totals();
            self::check( 12999 === (int) round( WC()->cart->get_cart()[ $key ]['line_subtotal'] * 100 ), 'old product cart uses old offer after active switch' );
            Belmains_Commerce::apply_offer( WC()->cart );
            WC()->cart->calculate_totals();
            self::check( 12999 === (int) round( WC()->cart->get_cart()[ $key ]['line_subtotal'] * 100 ), 'recalculation remains idempotent' );

            $copy_pub = self::request( 'GET', '/' . $copy['id'] )->get_data();
            $changed = $copy_pub['values'];
            $changed['product_name'] = 'Brouillon à jeter';
            $saved = self::edit( $copy_pub, $changed )->get_data();
            self::check( 409 === self::request( 'POST', '/' . $copy['id'] . '/discard', array( 'revision' => $copy_pub['revision'] ) )->get_status(), 'stale discard cannot erase new draft' );
            $discarded = self::request( 'POST', '/' . $copy['id'] . '/discard', array( 'revision' => $saved['revision'] ) )->get_data();
            self::check( ! $discarded['has_draft'] && 'Deuxième produit' === $discarded['values']['product_name'], 'discard restores published values' );
            $native = self::product( 'Produit Woo natif', '49.99' );
            $native->set_short_description( 'Description native utile' );
            $image = wp_insert_attachment( array( 'post_title' => 'Photo native', 'post_status' => 'inherit', 'post_mime_type' => 'image/jpeg', 'guid' => home_url( '/builder-fixture.jpg' ) ) );
            self::$attachments[] = $image;
            $native->set_image_id( $image );
            $native->save();
            $native_payload = self::request( 'GET', '/' . $native->get_id() )->get_data();
            self::check( 'Description native utile' === $native_payload['values']['product_description'], 'existing native Woo description imported' );
            self::check( 1 === count( $native_payload['values']['gallery'] ) && $image === $native_payload['values']['gallery'][0]['image']['id'], 'existing native Woo image imported' );
            self::check( '' === $native_payload['values']['product_video']['url'] && array() === $native_payload['values']['reviews'] && ! $native_payload['values']['duo_enabled'], 'native product does not inherit glove media claims or duo' );
            $native_values = $native_payload['values'];
            $native_values['gallery'] = array( array( 'image' => array( 'id' => $image, 'url' => '', 'alt' => 'Photo produit' ), 'label' => 'Produit' ) );
            $native_save = self::edit( $native_payload, $native_values, 'publish' );
            if ( 200 !== $native_save->get_status() ) { echo wp_json_encode( $native_save->get_data() ) . "\n"; }
            self::check( 200 === $native_save->get_status() && (int) $image === (int) wc_get_product( $native->get_id() )->get_image_id(), 'media and empty optional sections publish for native product' );
            $native_payload = $native_save->get_data();
            $bundled = $native_payload['values'];
            $bundled['gallery'] = Belmains_Builder::schema_defaults()['gallery'];
            $bundled_saved = self::edit( $native_payload, $bundled, 'publish' );
            self::check( 200 === $bundled_saved->get_status() && (int) $image === (int) wc_get_product( $native->get_id() )->get_image_id(), 'nonempty bundled gallery preserves existing native image' );
            $empty = $bundled_saved->get_data()['values'];
            $empty['gallery'] = array();
            $empty['product_video'] = array( 'id' => 0, 'url' => '', 'alt' => '' );
            $removed = self::edit( $bundled_saved->get_data(), $empty, 'publish' );
            self::check( 200 === $removed->get_status() && 0 === (int) wc_get_product( $native->get_id() )->get_image_id() && array() === wc_get_product( $native->get_id() )->get_gallery_image_ids(), 'removing last gallery image clears native image and gallery' );
            $with_video = $removed->get_data()['values'];
            $with_video['product_video'] = array( 'id' => $video, 'url' => '', 'alt' => 'Vidéo' );
            $with_video['video_poster'] = array( 'id' => $image, 'url' => '', 'alt' => 'Couverture' );
            $video_saved = self::edit( $removed->get_data(), $with_video, 'publish' );
            self::check( 200 === $video_saved->get_status() && (int) $image === (int) wc_get_product( $native->get_id() )->get_image_id(), 'video-only gallery uses valid attachment poster for native image' );
            echo 'SUCCESS ' . self::$checks . " builder integration checks\n";
        } finally {
            $_GET = self::$old_get;
            if ( WC()->cart ) { WC()->cart->empty_cart( false ); }
            if ( false === self::$original_active ) { delete_option( 'belmains_product_id' ); } else { update_option( 'belmains_product_id', self::$original_active ); }
            update_option( 'woocommerce_currency', self::$currency );
            foreach ( self::$ids as $id ) { $product = wc_get_product( $id ); if ( $product ) { $product->delete( true ); } }
            foreach ( self::$attachments as $id ) { wp_delete_attachment( $id, true ); }
            require_once ABSPATH . 'wp-admin/includes/user.php';
            foreach ( self::$users as $id ) { wp_delete_user( $id ); }
            wp_set_current_user( self::$original_user );
        }
    }
}
Belmains_Builder_Integration::run();
