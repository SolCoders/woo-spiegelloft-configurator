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
	<p class="description"><?php esc_html_e( 'Enable the option groups shown on this template, then choose the values available to shoppers.', 'woo-spiegelloft-configurator' ); ?></p>

	<div class="wcs-template-accordion">
		<?php foreach ( $all_groups as $slug => $group ) : ?>
			<?php
			$is_static = 'static' === ( $group['type'] ?? 'selectable' );
			if ( $is_static ) {
				continue;
			}
			$is_enabled   = in_array( $slug, $enabled_groups, true );
			$options      = $group_options[ $slug ] ?? array();
			$selected_ids = (array) ( $option_map[ $slug ] ?? array() );
			$add_url      = add_query_arg(
				array(
					'post_type'       => 'wcs_extra_option',
					'wcs_extra_group' => $slug,
				),
				admin_url( 'post-new.php' )
			);
			?>
			<div class="wcs-accordion-panel wcs-template-group-panel <?php echo $is_enabled ? 'is-enabled' : ''; ?>">
				<div class="wcs-template-group-header">
					<label class="wcs-template-group-toggle">
						<input type="checkbox" class="wcs-group-toggle" name="wcs_enabled_groups[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $is_enabled ); ?>>
						<span>
							<strong><?php echo esc_html( (string) ( $group['label'] ?? $slug ) ); ?></strong>
							<small>
								<?php
								printf(
									/* translators: %d: choice count */
									esc_html__( '%d choices', 'woo-spiegelloft-configurator' ),
									count( $options )
								);
								?>
							</small>
						</span>
					</label>
					<button type="button" class="wcs-accordion-toggle" aria-expanded="false">
						<span class="wcs-accordion-icon" aria-hidden="true"></span>
					</button>
				</div>
				<div class="wcs-accordion-body">
					<?php if ( empty( $options ) ) : ?>
						<div class="wcs-template-group-actions">
							<a class="button button-secondary" href="<?php echo esc_url( $add_url ); ?>">
								<?php esc_html_e( 'Add choice', 'woo-spiegelloft-configurator' ); ?>
							</a>
						</div>
						<p class="description"><?php esc_html_e( 'No choices in this category yet.', 'woo-spiegelloft-configurator' ); ?></p>
					<?php else : ?>
						<div class="wcs-template-group-actions">
							<a class="button button-secondary" href="<?php echo esc_url( $add_url ); ?>">
								<?php esc_html_e( 'Add choice', 'woo-spiegelloft-configurator' ); ?>
							</a>
						</div>
						<div class="wcs-option-table-wrap">
							<table class="widefat striped wcs-option-table">
								<thead>
									<tr>
										<th scope="col" class="wcs-option-table-sort"><?php esc_html_e( 'Sort', 'woo-spiegelloft-configurator' ); ?></th>
										<th scope="col" class="wcs-option-table-check"><?php esc_html_e( 'Show', 'woo-spiegelloft-configurator' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Choice', 'woo-spiegelloft-configurator' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Value', 'woo-spiegelloft-configurator' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Price', 'woo-spiegelloft-configurator' ); ?></th>
										<th scope="col" class="wcs-option-table-action"><?php esc_html_e( 'Action', 'woo-spiegelloft-configurator' ); ?></th>
									</tr>
								</thead>
								<tbody>
							<?php foreach ( $options as $option ) : ?>
								<?php
								$option_id    = (int) ( $option['id'] ?? 0 );
								$option_title = (string) ( $option['title'] ?? '' );
								$option_slug  = (string) ( $option['slug'] ?? '' );
								$option_image = (string) ( $option['meta']['_wcs_image'] ?? '' );
								$option_price = (float) ( $option['meta']['_wcs_price'] ?? 0 );
								?>
									<tr>
										<td class="wcs-option-table-sort">
											<span class="dashicons dashicons-menu wcs-choice-sort-handle" title="<?php esc_attr_e( 'Drag to reorder', 'woo-spiegelloft-configurator' ); ?>"></span>
										</td>
										<td class="wcs-option-table-check">
											<input type="checkbox" name="wcs_extra_option_map[<?php echo esc_attr( $slug ); ?>][]" value="<?php echo esc_attr( (string) $option_id ); ?>" <?php checked( in_array( $option_id, $selected_ids, true ) || empty( $selected_ids ) ); ?>>
										</td>
										<td>
											<div class="wcs-option-choice">
												<span class="wcs-option-thumb">
													<?php if ( $option_image ) : ?>
														<img src="<?php echo esc_url( $option_image ); ?>" alt="">
													<?php else : ?>
														<span aria-hidden="true"></span>
													<?php endif; ?>
												</span>
												<strong><?php echo esc_html( $option_title ); ?></strong>
											</div>
										</td>
										<td><code><?php echo esc_html( $option_slug ); ?></code></td>
										<td><?php echo wp_kses_post( wc_price( $option_price ) ); ?></td>
										<td class="wcs-option-table-action">
											<a class="button button-small" href="<?php echo esc_url( get_edit_post_link( $option_id, '' ) ); ?>">
												<?php esc_html_e( 'Edit', 'woo-spiegelloft-configurator' ); ?>
											</a>
											<a class="button button-small wcs-delete-choice" href="<?php echo esc_url( get_delete_post_link( $option_id, '', true ) ); ?>">
												<?php esc_html_e( 'Delete', 'woo-spiegelloft-configurator' ); ?>
											</a>
										</td>
									</tr>
							<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</div>
