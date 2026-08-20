<?php
/**
 * Storefront product configurator.
 *
 * @package WooSpiegelloftConfigurator
 *
 * @var WC_Product           $product Product.
 * @var array<string, mixed> $config  Built configurator payload.
 * @var string[]             $images  Product image URLs.
 */

defined( 'ABSPATH' ) || exit;

$base_price = (float) $product->get_price();
$extras     = (array) ( $config['extras'] ?? array() );
$steps      = (array) ( $config['steps'] ?? array() );
$side_measurements = array_values( (array) ( $config['side_measurements'] ?? array() ) );
$sidewise_measurements = ! empty( $side_measurements ) || 'sidewise' === (string) ( $config['measurement_mode'] ?? 'standard' );
$step_one_groups = array();
foreach ( $steps as $step ) {
	if ( 1 === (int) ( $step['number'] ?? 0 ) ) {
		$step_one_groups = (array) ( $step['groups'] ?? array() );
		break;
	}
}
?>
<form class="cart wcs-configurator" method="post" enctype="multipart/form-data" data-base-price="<?php echo esc_attr( (string) $base_price ); ?>">
	<input type="hidden" name="wcs_selections" class="wcs-selections-input" value="">
	<input type="hidden" name="quantity" value="1">

	<div class="wcs-configurator__panel">
		<header class="wcs-configurator__header">
			<div>
				<h2><?php echo esc_html( $product->get_name() ); ?></h2>
				<p><?php esc_html_e( 'Delivery time: 5 - 8 working days', 'woo-spiegelloft-configurator' ); ?></p>
			</div>
			<strong class="wcs-configurator__price"><?php echo wp_kses_post( wc_price( $base_price ) ); ?></strong>
		</header>

		<p class="wcs-configurator__notice">
			<span><?php esc_html_e( 'Bitte beachten: Der Konfigurator dient nur zur visuellen Darstellung.', 'woo-spiegelloft-configurator' ); ?></span>
		</p>

		<div class="wcs-configurator__content">
			<section class="wcs-configurator__section">
			<h3><?php esc_html_e( 'Size selection', 'woo-spiegelloft-configurator' ); ?></h3>
			<button type="button" class="wcs-configurator__help">
				<span aria-hidden="true">?</span>
				<?php esc_html_e( 'How do I find the right measurements?', 'woo-spiegelloft-configurator' ); ?>
			</button>
			<?php if ( $sidewise_measurements ) : ?>
				<div class="wcs-size-sidewise">
					<?php foreach ( $side_measurements as $side ) : ?>
						<?php
						$side_label = (string) ( $side['label'] ?? '' );
						$side_key   = sanitize_title( (string) ( $side['key'] ?? $side_label ) );
						$side_min   = (int) ( $side['min'] ?? $side['min_width'] ?? $side['min_height'] ?? 400 );
						$side_max   = (int) ( $side['max'] ?? $side['max_width'] ?? $side['max_height'] ?? 2500 );
						if ( '' === $side_label || '' === $side_key ) {
							continue;
						}
						?>
					<div class="wcs-size-side-row">
						<span class="wcs-size-side-icon" aria-hidden="true"></span>
						<label>
							<span>
								<?php echo esc_html( $side_label ); ?>
								<small><?php echo esc_html( (string) $side_min ); ?> - <?php echo esc_html( (string) $side_max ); ?> mm</small>
							</span>
							<input type="number" class="wcs-dimension-input" data-key="<?php echo esc_attr( 'side_' . str_replace( '-', '_', $side_key ) ); ?>" data-label="<?php echo esc_attr( $side_label ); ?>" min="<?php echo esc_attr( (string) $side_min ); ?>" max="<?php echo esc_attr( (string) $side_max ); ?>" value="<?php echo esc_attr( (string) $side_min ); ?>">
							<em><?php esc_html_e( 'mm', 'woo-spiegelloft-configurator' ); ?></em>
						</label>
					</div>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<div class="wcs-size-box">
					<label>
						<span>
							<?php esc_html_e( 'Width', 'woo-spiegelloft-configurator' ); ?>
							<small><?php echo esc_html( (string) ( $config['min_width'] ?? 200 ) ); ?> - <?php echo esc_html( (string) ( $config['max_width'] ?? 2500 ) ); ?> mm</small>
						</span>
						<input type="number" class="wcs-dimension-input" data-key="width" min="<?php echo esc_attr( (string) ( $config['min_width'] ?? 200 ) ); ?>" max="<?php echo esc_attr( (string) ( $config['max_width'] ?? 2500 ) ); ?>" value="<?php echo esc_attr( (string) ( $config['min_width'] ?? 200 ) ); ?>">
						<em><?php esc_html_e( 'mm', 'woo-spiegelloft-configurator' ); ?></em>
					</label>
					<span class="wcs-size-box__times">x</span>
					<label>
						<span>
							<?php esc_html_e( 'Height', 'woo-spiegelloft-configurator' ); ?>
							<small><?php echo esc_html( (string) ( $config['min_height'] ?? 200 ) ); ?> - <?php echo esc_html( (string) ( $config['max_height'] ?? 2500 ) ); ?> mm</small>
						</span>
						<input type="number" class="wcs-dimension-input" data-key="height" min="<?php echo esc_attr( (string) ( $config['min_height'] ?? 200 ) ); ?>" max="<?php echo esc_attr( (string) ( $config['max_height'] ?? 2500 ) ); ?>" value="<?php echo esc_attr( (string) ( $config['min_height'] ?? 200 ) ); ?>">
						<em><?php esc_html_e( 'mm', 'woo-spiegelloft-configurator' ); ?></em>
					</label>
				</div>
			<?php endif; ?>
			<?php foreach ( $step_one_groups as $group_slug ) : ?>
				<?php
				$group_slug = (string) $group_slug;
				$group      = (array) ( $extras[ $group_slug ] ?? array() );
				if ( 'edge' === $group_slug || empty( $group['value'] ) || ! is_array( $group['value'] ) ) {
					continue;
				}
				$options = (array) $group['value'];
				$single_option = ! empty( $options ) ? (array) reset( $options ) : array();
				$hide_parent_select = ! empty( $single_option ) && ! empty( $single_option['customer_fields'] );
				$customer_fields = (array) ( $single_option['customer_fields'] ?? array() );
				$first_customer_field = ! empty( $customer_fields ) ? (array) reset( $customer_fields ) : array();
				$heading = $hide_parent_select
					? (string) ( $first_customer_field['label'] ?? $single_option['name'] ?? $single_option['value'] ?? $group['title'] ?? $group_slug )
					: (string) ( $group['title'] ?? $group_slug );
				?>
				<div class="wcs-step-option-group">
					<div class="wcs-option-heading">
						<h3><?php echo esc_html( $heading ); ?></h3>
						<span class="wcs-option-info" aria-hidden="true">i</span>
					</div>
					<label class="wcs-option-select <?php echo $hide_parent_select ? 'wcs-option-select--hidden-parent' : ''; ?>">
						<select class="wcs-choice-select" data-group="<?php echo esc_attr( $group_slug ); ?>">
							<?php if ( ! $hide_parent_select ) : ?>
								<option value=""><?php esc_html_e( 'Please select', 'woo-spiegelloft-configurator' ); ?></option>
							<?php endif; ?>
							<?php foreach ( $options as $option ) : ?>
								<option value="<?php echo esc_attr( (string) ( $option['value'] ?? '' ) ); ?>" data-price="<?php echo esc_attr( (string) ( $option['price'] ?? 0 ) ); ?>" data-image="<?php echo esc_attr( (string) ( $option['image'] ?? '' ) ); ?>" data-customer-fields="<?php echo esc_attr( wp_json_encode( (array) ( $option['customer_fields'] ?? array() ) ) ); ?>" <?php selected( $hide_parent_select ); ?>>
									<?php echo esc_html( (string) ( $option['name'] ?? $option['value'] ?? '' ) ); ?>
									<?php if ( ! empty( $option['price'] ) ) : ?>
										<?php echo esc_html( ' +' . wp_strip_all_tags( wc_price( (float) $option['price'] ) ) ); ?>
									<?php endif; ?>
								</option>
							<?php endforeach; ?>
						</select>
					</label>
					<div
						class="wcs-position-select"
						data-group="<?php echo esc_attr( $group_slug ); ?>"
						data-show-when="<?php echo esc_attr( (string) ( $group['position_config']['show_when'] ?? '' ) ); ?>"
						data-position-label="<?php echo esc_attr( (string) ( $group['position_config']['label'] ?? '' ) ); ?>"
						data-position-options="<?php echo esc_attr( wp_json_encode( (array) ( $group['position_config']['options'] ?? array() ) ) ); ?>"
						hidden
					></div>
					<div class="wcs-customer-field-target" data-skip-first-label="<?php echo esc_attr( $hide_parent_select ? '1' : '0' ); ?>" hidden></div>
				</div>
			<?php endforeach; ?>
			</section>

			<?php foreach ( $steps as $step ) : ?>
			<?php
			if ( 1 === (int) ( $step['number'] ?? 0 ) ) {
				continue;
			}
			$step_groups = (array) ( $step['groups'] ?? array() );
			if ( empty( $step_groups ) ) {
				continue;
			}
			?>
				<section class="wcs-configurator__section wcs-option-section">
				<?php foreach ( $step_groups as $group_slug ) : ?>
					<?php
					$group_slug = (string) $group_slug;
					$group      = (array) ( $extras[ $group_slug ] ?? array() );
					if ( 'edge' === $group_slug || empty( $group['value'] ) || ! is_array( $group['value'] ) ) {
						continue;
					}
					$options = (array) $group['value'];
					$single_option = ! empty( $options ) ? (array) reset( $options ) : array();
					$hide_parent_select = ! empty( $single_option ) && ! empty( $single_option['customer_fields'] );
					$customer_fields = (array) ( $single_option['customer_fields'] ?? array() );
					$first_customer_field = ! empty( $customer_fields ) ? (array) reset( $customer_fields ) : array();
					$heading = $hide_parent_select
						? (string) ( $first_customer_field['label'] ?? $single_option['name'] ?? $single_option['value'] ?? $group['title'] ?? $group_slug )
						: (string) ( $group['title'] ?? $group_slug );
					?>
					<div class="wcs-step-option-group">
						<div class="wcs-option-heading">
							<h3><?php echo esc_html( $heading ); ?></h3>
							<span class="wcs-option-info" aria-hidden="true">i</span>
						</div>
						<label class="wcs-option-select <?php echo $hide_parent_select ? 'wcs-option-select--hidden-parent' : ''; ?>">
							<select class="wcs-choice-select" data-group="<?php echo esc_attr( $group_slug ); ?>">
								<?php if ( ! $hide_parent_select ) : ?>
									<option value=""><?php esc_html_e( 'Please select', 'woo-spiegelloft-configurator' ); ?></option>
								<?php endif; ?>
								<?php foreach ( $options as $option ) : ?>
									<option value="<?php echo esc_attr( (string) ( $option['value'] ?? '' ) ); ?>" data-price="<?php echo esc_attr( (string) ( $option['price'] ?? 0 ) ); ?>" data-image="<?php echo esc_attr( (string) ( $option['image'] ?? '' ) ); ?>" data-customer-fields="<?php echo esc_attr( wp_json_encode( (array) ( $option['customer_fields'] ?? array() ) ) ); ?>" <?php selected( $hide_parent_select ); ?>>
										<?php echo esc_html( (string) ( $option['name'] ?? $option['value'] ?? '' ) ); ?>
										<?php if ( ! empty( $option['price'] ) ) : ?>
											<?php echo esc_html( ' +' . wp_strip_all_tags( wc_price( (float) $option['price'] ) ) ); ?>
										<?php endif; ?>
									</option>
								<?php endforeach; ?>
							</select>
						</label>
						<div
							class="wcs-position-select"
							data-group="<?php echo esc_attr( $group_slug ); ?>"
							data-show-when="<?php echo esc_attr( (string) ( $group['position_config']['show_when'] ?? '' ) ); ?>"
							data-position-label="<?php echo esc_attr( (string) ( $group['position_config']['label'] ?? '' ) ); ?>"
							data-position-options="<?php echo esc_attr( wp_json_encode( (array) ( $group['position_config']['options'] ?? array() ) ) ); ?>"
							hidden
						></div>
						<div class="wcs-customer-field-target" data-skip-first-label="<?php echo esc_attr( $hide_parent_select ? '1' : '0' ); ?>" hidden></div>
					</div>
				<?php endforeach; ?>
				</section>
			<?php endforeach; ?>
			<section class="wcs-configurator__section wcs-review-section">
				<div class="wcs-review" aria-live="polite"></div>
			</section>
		</div>

		<nav class="wcs-configurator__footer" aria-label="<?php esc_attr_e( 'Configurator navigation', 'woo-spiegelloft-configurator' ); ?>">
			<button type="button" class="button wcs-step-back"><?php esc_html_e( 'Back', 'woo-spiegelloft-configurator' ); ?></button>
			<button type="button" class="button alt wcs-step-next"><?php esc_html_e( 'Further', 'woo-spiegelloft-configurator' ); ?></button>
			<button type="submit" name="add-to-cart" value="<?php echo esc_attr( (string) $product->get_id() ); ?>" class="button alt wcs-add-to-cart"><?php esc_html_e( 'Add to cart', 'woo-spiegelloft-configurator' ); ?></button>
		</nav>
	</div>
</form>
