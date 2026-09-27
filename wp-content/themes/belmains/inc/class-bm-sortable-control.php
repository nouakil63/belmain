<?php
defined( 'ABSPATH' ) || exit;

/**
 * Contrôle Customizer : liste triable avec cases à cocher (ordre + visibilité des sections).
 * Valeur : "id:1,id:0,…".
 */
class BM_Sortable_Control extends WP_Customize_Control {

	public $type = 'bm_sortable';

	public function render_content(): void {
		$choices = $this->choices;
		$state   = array();
		foreach ( explode( ',', (string) $this->value() ) as $pair ) {
			$p = explode( ':', trim( $pair ) );
			if ( isset( $choices[ $p[0] ] ) ) {
				$state[ $p[0] ] = '0' !== ( $p[1] ?? '1' );
			}
		}
		foreach ( $choices as $id => $label ) {
			if ( ! array_key_exists( $id, $state ) ) {
				$state[ $id ] = true;
			}
		}
		?>
		<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
		<?php if ( $this->description ) : ?><span class="description customize-control-description"><?php echo esc_html( $this->description ); ?></span><?php endif; ?>
		<ul class="bm-sortable">
			<?php foreach ( $state as $id => $on ) : ?>
				<li data-id="<?php echo esc_attr( $id ); ?>">
					<span class="dashicons dashicons-menu bm-handle" aria-hidden="true"></span>
					<label><input type="checkbox" <?php checked( $on ); ?>> <?php echo esc_html( $choices[ $id ] ); ?></label>
				</li>
			<?php endforeach; ?>
		</ul>
		<input type="hidden" class="bm-sortable-value" <?php $this->link(); ?> value="<?php echo esc_attr( $this->value() ); ?>">
		<?php
	}
}
