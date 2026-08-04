<?php
/**
 * Admin dashboard wizard template.
 *
 * @package WooSpiegelloftConfigurator
 *
 * @var int  $choices_count       Published choices count.
 * @var int  $templates_count     Published templates count.
 * @var int  $products_count      Products with configurator enabled.
 * @var bool $step_choices_done   Step 1 complete.
 * @var bool $step_templates_done Step 2 complete.
 * @var bool $step_products_done  Step 3 complete.
 */

defined( 'ABSPATH' ) || exit;

$steps = array(
	array(
		'number'  => 1,
		'title'   => __( 'Review customization choices', 'woo-spiegelloft-configurator' ),
		'desc'    => __( 'These are the options customers can pick, including light color, sockets, make-up mirror, and more. We pre-loaded choices from your Shopify catalog.', 'woo-spiegelloft-configurator' ),
		'status'  => $step_choices_done ? 'complete' : 'pending',
		'count'   => $choices_count,
		'action'  => admin_url( 'edit.php?post_type=wcs_extra_option' ),
		'button'  => __( 'Manage choices', 'woo-spiegelloft-configurator' ),
	),
	array(
		'number'  => 2,
		'title'   => __( 'Create a mirror template', 'woo-spiegelloft-configurator' ),
		'desc'    => __( 'Templates define which choices appear on a product, size limits, and any restrictions between options.', 'woo-spiegelloft-configurator' ),
		'status'  => $step_templates_done ? 'complete' : 'pending',
		'count'   => $templates_count,
		'action'  => admin_url( 'post-new.php?post_type=wcs_template' ),
		'button'  => __( 'Create template', 'woo-spiegelloft-configurator' ),
	),
	array(
		'number'  => 3,
		'title'   => __( 'Enable on a product', 'woo-spiegelloft-configurator' ),
		'desc'    => __( 'Open any WooCommerce product, go to the Mirror Customizer tab, turn it on, and assign your template.', 'woo-spiegelloft-configurator' ),
		'status'  => $step_products_done ? 'complete' : 'pending',
		'count'   => $products_count,
		'action'  => admin_url( 'edit.php?post_type=product' ),
		'button'  => __( 'View products', 'woo-spiegelloft-configurator' ),
	),
);

$completed_steps = count(
	array_filter(
		$steps,
		static function ( $step ) {
			return 'complete' === $step['status'];
		}
	)
);
$progress_percent = (int) round( ( $completed_steps / count( $steps ) ) * 100 );
?>
<div class="wrap wcs-admin-wrap wcs-wizard">
	<section class="wcs-wizard-hero">
		<div>
			<span class="wcs-wizard-eyebrow"><?php esc_html_e( 'Setup guide', 'woo-spiegelloft-configurator' ); ?></span>
			<h1><?php esc_html_e( 'Getting started with Mirror Customizer', 'woo-spiegelloft-configurator' ); ?></h1>
			<p class="wcs-wizard-intro">
				<?php esc_html_e( 'Follow these three steps to launch the configurator on your store. Each step builds on the previous one.', 'woo-spiegelloft-configurator' ); ?>
			</p>
		</div>
		<div class="wcs-wizard-progress-card" aria-label="<?php esc_attr_e( 'Setup progress', 'woo-spiegelloft-configurator' ); ?>">
			<span class="wcs-wizard-progress-value">
				<?php
				printf(
					/* translators: 1: completed steps, 2: total steps */
					esc_html__( '%1$d of %2$d', 'woo-spiegelloft-configurator' ),
					(int) $completed_steps,
					count( $steps )
				);
				?>
			</span>
			<span class="wcs-wizard-progress-label"><?php esc_html_e( 'steps complete', 'woo-spiegelloft-configurator' ); ?></span>
			<span class="wcs-wizard-progress-track">
				<span style="width: <?php echo esc_attr( (string) $progress_percent ); ?>%;"></span>
			</span>
		</div>
	</section>

	<ol class="wcs-wizard-steps">
		<?php foreach ( $steps as $step ) : ?>
			<li class="wcs-wizard-step wcs-wizard-step--<?php echo esc_attr( (string) $step['status'] ); ?>">
				<div class="wcs-wizard-step-header">
					<span class="wcs-wizard-step-badge" aria-hidden="true">
						<?php echo esc_html( (string) $step['number'] ); ?>
					</span>
					<div>
						<h2><?php echo esc_html( (string) $step['title'] ); ?></h2>
						<span class="wcs-wizard-status wcs-wizard-status--<?php echo esc_attr( (string) $step['status'] ); ?>">
							<?php
							echo 'complete' === $step['status']
								? esc_html__( 'Done', 'woo-spiegelloft-configurator' )
								: esc_html__( 'To do', 'woo-spiegelloft-configurator' );
							?>
						</span>
					</div>
				</div>
				<p><?php echo esc_html( (string) $step['desc'] ); ?></p>
				<div class="wcs-wizard-step-footer">
					<p class="wcs-wizard-meta">
						<span><?php esc_html_e( 'Current count', 'woo-spiegelloft-configurator' ); ?></span>
						<strong><?php echo esc_html( (string) (int) $step['count'] ); ?></strong>
					</p>
					<a class="button button-primary" href="<?php echo esc_url( (string) $step['action'] ); ?>">
						<?php echo esc_html( (string) $step['button'] ); ?>
					</a>
				</div>
			</li>
		<?php endforeach; ?>
	</ol>
</div>
