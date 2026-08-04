<?php
/**
 * Nested choice meta box template.
 *
 * @package WooSpiegelloftConfigurator
 *
 * @var string                             $group  Selected group slug.
 * @var array<string, array<string,mixed>> $groups All groups.
 * @var array<string, mixed>               $nested          Nested field values.
 * @var WCS_Extra_Field_Type_Registry      $field_registry  Field type registry.
 */

defined( 'ABSPATH' ) || exit;

$group_def       = $group ? ( $groups[ $group ] ?? null ) : null;
$optional_fields = is_array( $group_def ) ? (array) ( $group_def['optional_fields'] ?? array() ) : array();
?>
<div class="wcs-choice-nested" data-group="<?php echo esc_attr( $group ); ?>">
	<?php if ( empty( $optional_fields ) ) : ?>
		<p class="wcs-nested-empty description">
			<?php esc_html_e( 'This category has no follow-up choices. Select a category with nested options (e.g. light color, sockets, make-up mirror).', 'woo-spiegelloft-configurator' ); ?>
		</p>
	<?php else : ?>
		<div class="wcs-nested-accordion">
			<?php foreach ( $optional_fields as $field_key => $field_def ) : ?>
				<?php
				if ( ! is_array( $field_def ) ) {
					continue;
				}
				$field_def['id']   = (string) $field_key;
				$field_label       = (string) ( $field_def['label'] ?? $field_key );
				$field_value       = $nested[ $field_key ] ?? ( 'repeater' === ( $field_def['type'] ?? '' ) ? array() : '' );
				$preset_key        = (string) ( $field_def['preset_key'] ?? '' );
				$field_name        = 'wcs_nested[' . $field_key . ']';
				$is_repeater       = 'repeater' === ( $field_def['type'] ?? '' );
				?>
				<div class="wcs-accordion-panel wcs-nested-section" data-field-key="<?php echo esc_attr( (string) $field_key ); ?>" data-field-type="<?php echo esc_attr( (string) ( $field_def['type'] ?? 'text' ) ); ?>" <?php echo $preset_key ? 'data-preset-key="' . esc_attr( $preset_key ) . '"' : ''; ?>>
					<button type="button" class="wcs-accordion-toggle" aria-expanded="false">
						<span class="wcs-accordion-title"><?php echo esc_html( $field_label ); ?></span>
						<span class="wcs-accordion-icon" aria-hidden="true"></span>
					</button>
					<div class="wcs-accordion-body">
						<?php if ( $is_repeater ) : ?>
							<div class="wcs-repeater-toolbar">
								<?php if ( $preset_key ) : ?>
									<button type="button" class="button wcs-use-preset" data-preset="<?php echo esc_attr( $preset_key ); ?>">
										<?php esc_html_e( 'Use preset', 'woo-spiegelloft-configurator' ); ?>
									</button>
								<?php endif; ?>
								<button type="button" class="button wcs-add-repeater-row"><?php esc_html_e( 'Add row', 'woo-spiegelloft-configurator' ); ?></button>
								<?php if ( in_array( 'id', array_column( (array) ( $field_def['fields'] ?? array() ), 'id' ), true ) ) : ?>
									<button type="button" class="button wcs-add-no-thanks"><?php esc_html_e( 'Add "No thanks" option', 'woo-spiegelloft-configurator' ); ?></button>
								<?php endif; ?>
							</div>
							<div class="wcs-repeater" data-name="<?php echo esc_attr( $field_name ); ?>">
								<?php
								$rows = is_array( $field_value ) ? $field_value : array();
								if ( empty( $rows ) ) {
									$rows = array( array() );
								}
								foreach ( $rows as $index => $row ) :
									$row = is_array( $row ) ? $row : array();
									?>
									<div class="wcs-repeater-row">
										<span class="wcs-repeater-handle dashicons dashicons-menu" title="<?php esc_attr_e( 'Drag to reorder', 'woo-spiegelloft-configurator' ); ?>"></span>
										<div class="wcs-repeater-fields">
											<?php
											foreach ( (array) ( $field_def['fields'] ?? array() ) as $subfield ) {
												if ( ! is_array( $subfield ) || empty( $subfield['id'] ) ) {
													continue;
												}
												$sub_name = $field_name . '[' . $index . '][' . $subfield['id'] . ']';
												$handler  = $field_registry->get( (string) ( $subfield['type'] ?? 'text' ) );
												if ( $handler ) {
													echo '<div class="wcs-repeater-subfield wcs-subfield-' . esc_attr( (string) $subfield['id'] ) . '">';
													$handler->render_admin_field( $sub_name, $row[ $subfield['id'] ] ?? '', $subfield );
													echo '</div>';
												}
											}
											?>
										</div>
										<button type="button" class="button-link wcs-remove-repeater-row"><?php esc_html_e( 'Remove', 'woo-spiegelloft-configurator' ); ?></button>
									</div>
								<?php endforeach; ?>
							</div>
						<?php else : ?>
							<?php
							$handler = $field_registry->get( (string) ( $field_def['type'] ?? 'text' ) );
							if ( $handler ) {
								$handler->render_admin_field( $field_name, $field_value, $field_def );
							}
							?>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
