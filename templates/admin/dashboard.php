<?php
/**
 * Admin dashboard template.
 *
 * @package WooSpiegelloftConfigurator
 *
 * @var object $templates_count Template post counts.
 * @var object $options_count   Extra option post counts.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap wcs-admin-wrap">
	<h1><?php esc_html_e( 'Mirror Configurator', 'woo-spiegelloft-configurator' ); ?></h1>

	<div class="wcs-dashboard-cards">
		<div class="wcs-card">
			<h2><?php esc_html_e( 'Templates', 'woo-spiegelloft-configurator' ); ?></h2>
			<p class="wcs-stat"><?php echo esc_html( (string) ( $templates_count->publish ?? 0 ) ); ?></p>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=wcs_template' ) ); ?>">
				<?php esc_html_e( 'Manage Templates', 'woo-spiegelloft-configurator' ); ?>
			</a>
		</div>

		<div class="wcs-card">
			<h2><?php esc_html_e( 'Extra Options', 'woo-spiegelloft-configurator' ); ?></h2>
			<p class="wcs-stat"><?php echo esc_html( (string) ( $options_count->publish ?? 0 ) ); ?></p>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wcs-extras' ) ); ?>">
				<?php esc_html_e( 'Manage Extras', 'woo-spiegelloft-configurator' ); ?>
			</a>
		</div>
	</div>

	<p><?php esc_html_e( 'Assign a configurator template to WooCommerce products via the Configurator product data tab.', 'woo-spiegelloft-configurator' ); ?></p>
</div>