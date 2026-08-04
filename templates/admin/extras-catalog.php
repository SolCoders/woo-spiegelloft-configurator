<?php
/**
 * Extras catalog admin template.
 *
 * @package WooSpiegelloftConfigurator
 *
 * @var array<string, array<string, mixed>> $groups  Group definitions.
 * @var array<string, array<int, array<string, mixed>>> $options Options by group.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap wcs-admin-wrap">
	<h1><?php esc_html_e( 'Extras Catalog', 'woo-spiegelloft-configurator' ); ?></h1>

	<?php foreach ( $groups as $slug => $group ) : ?>
		<div class="wcs-group-panel" data-group="<?php echo esc_attr( $slug ); ?>">
			<h2><?php echo esc_html( (string) ( $group['label'] ?? $slug ) ); ?></h2>
			<table class="widefat striped wcs-extras-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Title', 'woo-spiegelloft-configurator' ); ?></th>
						<th><?php esc_html_e( 'Slug', 'woo-spiegelloft-configurator' ); ?></th>
						<th><?php esc_html_e( 'Price', 'woo-spiegelloft-configurator' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'woo-spiegelloft-configurator' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( (array) ( $options[ $slug ] ?? array() ) as $option ) : ?>
						<tr data-option-id="<?php echo esc_attr( (string) ( $option['id'] ?? 0 ) ); ?>">
							<td><?php echo esc_html( (string) ( $option['title'] ?? '' ) ); ?></td>
							<td><code><?php echo esc_html( (string) ( $option['slug'] ?? '' ) ); ?></code></td>
							<td><?php echo wp_kses_post( wc_price( (float) ( $option['meta']['_wcs_price'] ?? 0 ) ) ); ?></td>
							<td>
								<a href="<?php echo esc_url( get_edit_post_link( (int) ( $option['id'] ?? 0 ) ) ); ?>" class="button button-small">
									<?php esc_html_e( 'Edit', 'woo-spiegelloft-configurator' ); ?>
								</a>
								<button type="button" class="button button-small wcs-delete-option" data-id="<?php echo esc_attr( (string) ( $option['id'] ?? 0 ) ); ?>">
									<?php esc_html_e( 'Delete', 'woo-spiegelloft-configurator' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p>
				<a class="button" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wcs_extra_option' ) ); ?>">
					<?php esc_html_e( 'Add Option', 'woo-spiegelloft-configurator' ); ?>
				</a>
			</p>
		</div>
	<?php endforeach; ?>
</div>