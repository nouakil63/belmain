<?php
defined( 'ABSPATH' ) || exit;

/**
 * Page « Réglages » du menu Belmains (option unique bm_crm_settings).
 */
class BM_Settings {

	public static function init(): void {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	public static function fields(): array {
		return array(
			'easyship' => array(
				'title'  => 'Easyship',
				'desc'   => 'Jeton API (Easyship → Connect → API). En sandbox, utilisez un jeton « sand_ ». L\'URL de webhook à déclarer chez Easyship est affichée ci-dessous.',
				'fields' => array(
					'easyship_token'       => array( 'label' => 'Jeton API', 'type' => 'password' ),
					'easyship_sandbox'     => array( 'label' => 'Mode sandbox', 'type' => 'checkbox' ),
					'easyship_webhook_key' => array( 'label' => 'Clé secrète webhook', 'type' => 'text', 'desc' => 'Copiez cette clé dans le champ « secret » du webhook Easyship.' ),
					'easyship_auto'        => array( 'label' => 'Créer l\'expédition Easyship automatiquement', 'type' => 'checkbox', 'desc' => 'Dès qu\'une commande passe en « En cours » (payée).' ),
					'easyship_buy_label'   => array( 'label' => 'Acheter l\'étiquette automatiquement', 'type' => 'checkbox', 'desc' => 'Sinon, l\'étiquette est achetée depuis le tableau de bord Easyship.' ),
				),
			),
			'origin' => array(
				'title'  => 'Adresse d\'expédition (origine)',
				'desc'   => 'Utilisée pour créer les expéditions via l\'API.',
				'fields' => array(
					'origin_company'  => array( 'label' => 'Société', 'type' => 'text' ),
					'origin_name'     => array( 'label' => 'Contact', 'type' => 'text' ),
					'origin_line1'    => array( 'label' => 'Adresse', 'type' => 'text' ),
					'origin_line2'    => array( 'label' => 'Complément', 'type' => 'text' ),
					'origin_postcode' => array( 'label' => 'Code postal', 'type' => 'text' ),
					'origin_city'     => array( 'label' => 'Ville', 'type' => 'text' ),
					'origin_country'  => array( 'label' => 'Pays (ISO-2)', 'type' => 'text' ),
					'origin_phone'    => array( 'label' => 'Téléphone', 'type' => 'text' ),
					'origin_email'    => array( 'label' => 'E-mail', 'type' => 'email' ),
				),
			),
			'parcel' => array(
				'title'  => 'Colis par défaut',
				'desc'   => 'Dimensions utilisées si le produit n\'a pas de poids/dimensions renseignés.',
				'fields' => array(
					'parcel_weight' => array( 'label' => 'Poids (kg)', 'type' => 'number', 'step' => '0.01' ),
					'parcel_length' => array( 'label' => 'Longueur (cm)', 'type' => 'number' ),
					'parcel_width'  => array( 'label' => 'Largeur (cm)', 'type' => 'number' ),
					'parcel_height' => array( 'label' => 'Hauteur (cm)', 'type' => 'number' ),
					'hs_code'       => array( 'label' => 'Code douanier (HS)', 'type' => 'text', 'desc' => 'Requis hors UE. Ex. 9019.10 pour les appareils de massage — à vérifier avec votre transitaire.' ),
				),
			),
			'ops' => array(
				'title'  => 'Stocks & retours',
				'fields' => array(
					'low_stock'     => array( 'label' => 'Seuil de stock bas', 'type' => 'number' ),
					'return_window' => array( 'label' => 'Délai de retour (jours)', 'type' => 'number' ),
					'notify_email'  => array( 'label' => 'E-mail de notification interne', 'type' => 'email' ),
					'return_address'=> array( 'label' => 'Adresse de retour (affichée au client)', 'type' => 'textarea' ),
				),
			),
		);
	}

	public static function register(): void {
		register_setting( 'bm_crm', 'bm_crm_settings', array(
			'type'              => 'array',
			'sanitize_callback' => array( __CLASS__, 'sanitize' ),
		) );
	}

	public static function sanitize( $input ): array {
		$out = get_option( 'bm_crm_settings', array() );
		if ( ! is_array( $input ) ) {
			return $out;
		}
		foreach ( self::fields() as $group ) {
			foreach ( $group['fields'] as $key => $f ) {
				$val = $input[ $key ] ?? '';
				switch ( $f['type'] ) {
					case 'checkbox':
						$out[ $key ] = empty( $val ) ? '0' : '1';
						break;
					case 'number':
						$out[ $key ] = is_numeric( $val ) ? (string) $val : '';
						break;
					case 'email':
						$out[ $key ] = sanitize_email( $val );
						break;
					case 'textarea':
						$out[ $key ] = sanitize_textarea_field( $val );
						break;
					default:
						$out[ $key ] = sanitize_text_field( $val );
				}
			}
		}
		return $out;
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Accès refusé.' );
		}
		$opts = get_option( 'bm_crm_settings', array() );
		?>
		<div class="wrap bm-wrap">
			<h1>Belmains — Réglages</h1>
			<?php settings_errors(); ?>
			<form method="post" action="options.php">
				<?php settings_fields( 'bm_crm' ); ?>
				<?php foreach ( self::fields() as $gid => $group ) : ?>
					<div class="bm-card">
						<h2><?php echo esc_html( $group['title'] ); ?></h2>
						<?php if ( ! empty( $group['desc'] ) ) : ?><p class="description"><?php echo esc_html( $group['desc'] ); ?></p><?php endif; ?>
						<?php if ( 'easyship' === $gid ) : ?>
							<p><strong>URL de webhook :</strong> <code><?php echo esc_html( rest_url( 'belmains/v1/easyship/webhook' ) ); ?></code></p>
							<p><strong>Statut :</strong> <?php echo BM_Easyship::is_configured() ? bm_badge( 'delivered', 'Jeton renseigné' ) : bm_badge( 'exception', 'Jeton manquant' ); ?></p>
						<?php endif; ?>
						<table class="form-table" role="presentation">
							<?php foreach ( $group['fields'] as $key => $f ) :
								$val = $opts[ $key ] ?? '';
								$id  = 'bm_' . $key; ?>
								<tr>
									<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $f['label'] ); ?></label></th>
									<td>
										<?php if ( 'checkbox' === $f['type'] ) : ?>
											<label><input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="bm_crm_settings[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( '1', $val ); ?>> <?php echo esc_html( $f['desc'] ?? '' ); ?></label>
										<?php elseif ( 'textarea' === $f['type'] ) : ?>
											<textarea id="<?php echo esc_attr( $id ); ?>" name="bm_crm_settings[<?php echo esc_attr( $key ); ?>]" rows="4" class="large-text"><?php echo esc_textarea( $val ); ?></textarea>
										<?php else : ?>
											<input type="<?php echo esc_attr( $f['type'] ); ?>" id="<?php echo esc_attr( $id ); ?>" name="bm_crm_settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $val ); ?>" class="regular-text" <?php echo isset( $f['step'] ) ? 'step="' . esc_attr( $f['step'] ) . '"' : ''; ?>>
											<?php if ( ! empty( $f['desc'] ) ) : ?><p class="description"><?php echo esc_html( $f['desc'] ); ?></p><?php endif; ?>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</table>
					</div>
				<?php endforeach; ?>
				<?php submit_button( 'Enregistrer les réglages' ); ?>
			</form>
		</div>
		<?php
	}
}
