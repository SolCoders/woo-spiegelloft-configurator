<?php
/**
 * Template customer choices meta box.
 *
 * @package WooSpiegelloftConfigurator
 *
 * @var string                              $flat_group_slug Internal group slug used for saving choices.
 * @var array<string,mixed>                 $flat_group      Internal group definition.
 * @var array<int, array<string,mixed>>     $flat_options    All published choices.
 * @var int[]                               $flat_selected_ids Selected choice IDs.
 * @var int                                 $flat_step       Internal step used for saving choices.
 */

defined( 'ABSPATH' ) || exit;

$add_url = add_query_arg(
	array(
		'post_type' => 'wcs_extra_option',
	),
	admin_url( 'post-new.php' )
);
$choice_step_map = (array) ( $data['choice_step_map'] ?? array() );
$per_page        = 8;
?>
<div class="wcs-template-choices">
	<p class="description"><?php esc_html_e( 'Choose the customization choices available to shoppers. Categories are no longer used for this template list.', 'woo-spiegelloft-configurator' ); ?></p>

	<?php if ( '' === $flat_group_slug ) : ?>
		<p class="description"><?php esc_html_e( 'No selectable option group is available for saving choices.', 'woo-spiegelloft-configurator' ); ?></p>
	<?php else : ?>
		<input type="hidden" name="wcs_enabled_groups[]" value="<?php echo esc_attr( $flat_group_slug ); ?>">
		<input type="hidden" name="wcs_group_order[]" value="<?php echo esc_attr( $flat_group_slug ); ?>">
		<input type="hidden" name="wcs_group_steps[<?php echo esc_attr( $flat_group_slug ); ?>]" value="<?php echo esc_attr( (string) $flat_step ); ?>">

		<div class="wcs-template-flat-panel">
			<div class="wcs-template-flat-header">
				<div>
					<strong><?php esc_html_e( 'Customization choices', 'woo-spiegelloft-configurator' ); ?></strong>
					<span class="wcs-choice-count">
						<?php
						printf(
							/* translators: %d: choice count */
							esc_html__( '%d choices', 'woo-spiegelloft-configurator' ),
							count( $flat_options )
						);
						?>
					</span>
				</div>
				<div class="wcs-template-flat-actions">
					<label class="screen-reader-text" for="wcs-choice-search"><?php esc_html_e( 'Search choices', 'woo-spiegelloft-configurator' ); ?></label>
					<input type="search" id="wcs-choice-search" class="wcs-choice-search" placeholder="<?php esc_attr_e( 'Search choices...', 'woo-spiegelloft-configurator' ); ?>">
					<a class="button button-secondary" href="<?php echo esc_url( $add_url ); ?>">
						<?php esc_html_e( 'Add choice', 'woo-spiegelloft-configurator' ); ?>
					</a>
				</div>
			</div>

			<?php if ( empty( $flat_options ) ) : ?>
				<p class="description wcs-template-flat-empty"><?php esc_html_e( 'No customization choices have been created yet.', 'woo-spiegelloft-configurator' ); ?></p>
			<?php else : ?>
				<div class="wcs-option-table-wrap">
					<table class="widefat striped wcs-option-table" data-per-page="<?php echo esc_attr( (string) $per_page ); ?>">
						<thead>
							<tr>
								<th scope="col" class="wcs-option-table-sort"><?php esc_html_e( 'Sort', 'woo-spiegelloft-configurator' ); ?></th>
								<th scope="col" class="wcs-option-table-check"><?php esc_html_e( 'Show', 'woo-spiegelloft-configurator' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Option Name', 'woo-spiegelloft-configurator' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Value', 'woo-spiegelloft-configurator' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Step', 'woo-spiegelloft-configurator' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $flat_options as $option ) : ?>
								<?php
								$option_id    = (int) ( $option['id'] ?? 0 );
								$option_title = (string) ( $option['title'] ?? '' );
								$option_slug  = (string) ( $option['slug'] ?? '' );
								$option_image = (string) ( $option['meta']['_wcs_image'] ?? '' );
								$option_step  = max( 1, absint( $choice_step_map[ $option_id ] ?? $flat_step ) );
								?>
								<tr data-choice-search="<?php echo esc_attr( strtolower( $option_title . ' ' . $option_slug ) ); ?>">
									<td class="wcs-option-table-sort">
										<span class="dashicons dashicons-menu wcs-choice-sort-handle" title="<?php esc_attr_e( 'Drag to reorder', 'woo-spiegelloft-configurator' ); ?>"></span>
									</td>
									<td class="wcs-option-table-check">
										<input type="checkbox" name="wcs_extra_option_map[<?php echo esc_attr( $flat_group_slug ); ?>][]" value="<?php echo esc_attr( (string) $option_id ); ?>" <?php checked( in_array( $option_id, $flat_selected_ids, true ) || empty( $flat_selected_ids ) ); ?>>
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
									<td>
										<select name="wcs_choice_steps[<?php echo esc_attr( (string) $option_id ); ?>]" class="wcs-choice-step-select">
											<?php for ( $step = 1; $step <= 6; $step++ ) : ?>
												<option value="<?php echo esc_attr( (string) $step ); ?>" <?php selected( $option_step, $step ); ?>>
													<?php
													printf(
														/* translators: %d: step number */
														esc_html__( 'Step %d', 'woo-spiegelloft-configurator' ),
														$step
													);
													?>
												</option>
											<?php endfor; ?>
										</select>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<div class="wcs-choice-pagination" aria-live="polite">
					<button type="button" class="button wcs-choice-page-prev"><?php esc_html_e( 'Previous', 'woo-spiegelloft-configurator' ); ?></button>
					<span class="wcs-choice-page-status"></span>
					<button type="button" class="button wcs-choice-page-next"><?php esc_html_e( 'Next', 'woo-spiegelloft-configurator' ); ?></button>
				</div>
				<p class="description wcs-template-flat-empty" hidden><?php esc_html_e( 'No customization choices have been created yet.', 'woo-spiegelloft-configurator' ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
