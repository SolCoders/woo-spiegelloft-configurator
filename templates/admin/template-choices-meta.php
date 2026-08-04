<?php
/**
 * Template customer choices meta box.
 *
 * @package WooSpiegelloftConfigurator
 *
 * @var string[]                             $enabled_groups Enabled group slugs.
 * @var array<string, int[]>                 $option_map     Group => option post IDs.
 * @var array<string, array<string,mixed>>   $all_groups     Group definitions.
 * @var array<string, array<int,mixed>>      $group_options  Options per group.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wcs-template-choices">
	<p class="description"><?php esc_html_e( 'Turn categories on or off, then pick which choices customers see in each category.', 'woo-spiegelloft-configurator' ); ?></p>

	<div class="wcs-template-accordion">
		<?php foreach ( $all_groups as $slug => $group ) : ?>
			<?php
			$is_enabled   = in_array( $slug, $enabled_groups, true );
			$options      = $group_options[ $slug ] ?? array();
			$selected_ids = (array) ( $option_map[ $slug ] ?? array() );
			$is_static    = 'static' === ( $group['type'] ?? 'selectable' );
			?>
			<div class="wcs-accordion-panel wcs-template-group-panel <?php echo $is_enabled ? 'is-enabled' : ''; ?>">
				<div class="wcs-template-group-header">
					<label>
						<input type="checkbox" class="wcs-group-toggle" name="wcs_enabled_groups[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $is_enabled ); ?>>
						<strong><?php echo esc_html( (string) ( $group['label'] ?? $slug ) ); ?></strong>
					</label>
					<button type="button" class="wcs-accordion-toggle" aria-expanded="false">
						<span class="wcs-accordion-icon" aria-hidden="true"></span>
					</button>
				</div>
				<div class="wcs-accordion-body">
					<?php if ( $is_static ) : ?>
						<p class="description"><?php esc_html_e( 'Static edge info — configure label in Basic settings.', 'woo-spiegelloft-configurator' ); ?></p>
					<?php elseif ( empty( $options ) ) : ?>
						<p class="description"><?php esc_html_e( 'No choices in this category yet.', 'woo-spiegelloft-configurator' ); ?></p>
					<?php else : ?>
						<div class="wcs-option-checklist">
							<?php foreach ( $options as $option ) : ?>
								<?php $option_id = (int) ( $option['id'] ?? 0 ); ?>
								<label class="wcs-checkbox-row">
									<input type="checkbox" name="wcs_extra_option_map[<?php echo esc_attr( $slug ); ?>][]" value="<?php echo esc_attr( (string) $option_id ); ?>" <?php checked( in_array( $option_id, $selected_ids, true ) || empty( $selected_ids ) ); ?>>
									<?php echo esc_html( (string) ( $option['title'] ?? '' ) ); ?>
									<?php if ( isset( $option['meta']['_wcs_price'] ) ) : ?>
										<em>(<?php echo wp_kses_post( wc_price( (float) $option['meta']['_wcs_price'] ) ); ?>)</em>
									<?php endif; ?>
								</label>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</div>
