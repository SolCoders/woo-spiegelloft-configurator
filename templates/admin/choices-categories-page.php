<?php
/**
 * Category-first customization choices admin page.
 *
 * @package WooSpiegelloftConfigurator
 *
 * @var array<string, array<string,mixed>> $all_groups     Group definitions.
 * @var array<string, array<int,mixed>>    $group_options  Options per group.
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
						<span>
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
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</div>
