<?php

/**
 * Full-screen template for the customer dashboard page.
 *
 * Swapped in for the theme's own template by
 * `CustomerPanel\Services\CustomerPanelShortcode::fullScreenTemplate()`, and only
 * for a logged-in customer. Deliberately bare: no site header, footer, sidebar or
 * page title — the panel is an application, and this gives it the whole viewport,
 * the same canvas it had on the wp-admin surface.
 *
 * `wp_head()` / `wp_footer()` still run, so plugins and the theme's own scripts
 * behave normally.
 *
 * @package RoxAppointmentBooking
 * @subpackage Modules\CustomerPanel\views
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>

<body <?php body_class('rox-appointment-booking-dashboard-page'); ?>>
	<?php wp_body_open(); ?>

	<?php
	// Rendered through the shortcode rather than printed here, so the panel has
	// exactly one definition of what it puts on the page.
	//
	// Not escaped on the way out, and it must not be: this is markup, not data.
	// The shortcode escapes every dynamic value it embeds (esc_attr on the mount
	// node's data-config, esc_url/esc_html in the notice), so what comes back is
	// already safe. esc_html() here would print the tags as text, and
	// wp_kses_post() would strip the data-config attribute the React apps mount on.
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode output is pre-escaped markup; see above.
	echo do_shortcode('[rox_appointment_dashboard]');
	?>

	<?php wp_footer(); ?>
</body>

</html>
