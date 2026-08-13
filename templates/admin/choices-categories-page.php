<?php
/**
 * Category-first customization choices admin page.
 *
 * @package WooSpiegelloftConfigurator
 *
 * @var array<string, array<string,mixed>> $all_groups     Group definitions.
 * @var array<string, array<int,mixed>>    $group_options  Options per group.
 * @var array<string, array<string,mixed>> $group_position_settings Position settings per group.
 * @var int                                $total_options  Total option count.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap wcs-admin-wrap wcs-choices-page">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Customization choices', 'woo-spiegelloft-configurator' ); ?></h1>
	<a class="page-title-action" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wcs_extra_option' ) ); ?>">
		<?php esc_html_e( 'Add customization choice', 'woo-spiegelloft-configurator' ); ?>
	</a>
	<hr class="wp-header-end">

	<p class="wcs-choices-summary">
		<?php
		printf(
			/* translators: 1: category count, 2: choice count */
			esc_html__( '%1$d categories, %2$d choices', 'woo-spiegelloft-configurator' ),
			count( $all_groups ),
			(int) $total_options
		);
		?>
	</p>

	<div class="wcs-template-accordion wcs-choices-category-list">
		<?php foreach ( $all_groups as $slug => $group ) : ?>
			<?php
			$options   = $group_options[ $slug ] ?? array();
			$is_static = 'static' === ( $group['type'] ?? 'selectable' );
			$add_url   = add_query_arg(
				array(
					'post_type'       => 'wcs_extra_option',
					'wcs_extra_group' => $slug,
				),
				admin_url( 'post-new.php' )
			);
			?>
			<div class="wcs-accordion-panel wcs-template-group-panel">
				<div class="wcs-template-group-header">
					<div class="wcs-choice-category-heading">
						<strong><?php echo esc_html( (string) ( $group['label'] ?? $slug ) ); ?></strong>
						<code><?php echo esc_html( (string) $slug ); ?></code>
					</div>
					<div class="wcs-choice-category-meta">
						<span class="wcs-choice-count" data-count="<?php echo esc_attr( (string) count( $options ) ); ?>">
							<?php
							printf(
								/* translators: %d: choice count */
								esc_html__( '%d choices', 'woo-spiegelloft-configurator' ),
								count( $options )
							);
							?>
						</span>
						<button type="button" class="wcs-accordion-toggle" aria-expanded="false">
							<span class="wcs-accordion-icon" aria-hidden="true"></span>
						</button>
					</div>
				</div>
				<div class="wcs-accordion-body">
					<?php if ( $is_static ) : ?>
						<p class="description"><?php esc_html_e( 'Static edge information uses the label configured in Basic settings.', 'woo-spiegelloft-configurator' ); ?></p>
					<?php else : ?>
						<div class="wcs-template-group-actions">
							<a class="button button-secondary" href="<?php echo esc_url( $add_url ); ?>">
								<?php esc_html_e( 'Add choice', 'woo-spiegelloft-configurator' ); ?>
							</a>
						</div>
						<?php
						$position_settings = (array) ( $group_position_settings[ $slug ] ?? array() );
						$position_enabled  = ! empty( $position_settings['enabled'] );
						$position_label    = (string) ( $position_settings['label'] ?? '' );
						$position_show_when = (string) ( $position_settings['show_when'] ?? '' );
						$position_rows     = ! empty( $position_settings['options'] ) && is_array( $position_settings['options'] )
							? (array) $position_settings['options']
							: array( array( 'label' => '', 'value' => '' ) );
						?>
						<div
							class="wcs-inline-positions wcs-group-positions <?php echo $position_enabled ? 'is-enabled' : ''; ?>"
							data-group="<?php echo esc_attr( $slug ); ?>"
							data-nonce="<?php echo esc_attr( wp_create_nonce( 'wcs_group_positions_' . $slug ) ); ?>"
						>
							<div class="wcs-inline-position-head">
								<label class="wcs-inline-position-toggle">
									<input type="checkbox" class="wcs-inline-position-enabled" <?php checked( $position_enabled ); ?>>
									<span>
										<strong><?php esc_html_e( 'Position dropdown', 'woo-spiegelloft-configurator' ); ?></strong>
										<small><?php esc_html_e( 'Show a position selector for this category when the condition matches.', 'woo-spiegelloft-configurator' ); ?></small>
									</span>
								</label>
							</div>
							<div class="wcs-inline-position-fields">
								<input type="text" class="wcs-inline-position-label" value="<?php echo esc_attr( $position_label ); ?>" placeholder="<?php esc_attr_e( 'Position label', 'woo-spiegelloft-configurator' ); ?>">
								<select class="wcs-inline-position-condition">
									<option value=""><?php esc_html_e( 'Show for any selected choice', 'woo-spiegelloft-configurator' ); ?></option>
									<?php foreach ( $options as $condition_option ) : ?>
										<?php $condition_slug = (string) ( $condition_option['slug'] ?? '' ); ?>
										<option value="<?php echo esc_attr( $condition_slug ); ?>" <?php selected( $position_show_when, $condition_slug ); ?>>
											<?php echo esc_html( (string) ( $condition_option['title'] ?? $condition_slug ) ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<div class="wcs-inline-position-options">
									<?php foreach ( $position_rows as $position_row ) : ?>
										<div class="wcs-inline-position-option">
											<input type="text" class="wcs-inline-position-option-label" value="<?php echo esc_attr( (string) ( $position_row['label'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'top center', 'woo-spiegelloft-configurator' ); ?>">
											<input type="text" class="wcs-inline-position-option-value" value="<?php echo esc_attr( (string) ( $position_row['value'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'top-center', 'woo-spiegelloft-configurator' ); ?>">
											<button type="button" class="button wcs-inline-position-add">+</button>
											<button type="button" class="button wcs-inline-position-remove">-</button>
										</div>
									<?php endforeach; ?>
								</div>
								<button type="button" class="button button-small wcs-inline-position-save"><?php esc_html_e( 'Save positions', 'woo-spiegelloft-configurator' ); ?></button>
								<span class="wcs-inline-position-status" aria-live="polite"></span>
							</div>
						</div>
						<?php if ( empty( $options ) ) : ?>
							<p class="description"><?php esc_html_e( 'No choices in this category yet.', 'woo-spiegelloft-configurator' ); ?></p>
						<?php else : ?>
							<div class="wcs-option-table-wrap">
								<table class="widefat striped wcs-option-table">
									<thead>
										<tr>
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
													<a
														class="button button-small wcs-delete-choice"
														href="<?php echo esc_url( get_delete_post_link( $option_id, '', true ) ); ?>"
														data-choice-id="<?php echo esc_attr( (string) $option_id ); ?>"
														data-nonce="<?php echo esc_attr( wp_create_nonce( 'wcs_delete_choice_' . $option_id ) ); ?>"
													>
														<?php esc_html_e( 'Delete', 'woo-spiegelloft-configurator' ); ?>
													</a>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
							<p class="description wcs-empty-options-message" hidden><?php esc_html_e( 'No choices in this category yet.', 'woo-spiegelloft-configurator' ); ?></p>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</div>
