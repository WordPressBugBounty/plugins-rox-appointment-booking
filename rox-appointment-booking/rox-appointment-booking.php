<?php

defined('ABSPATH') || exit;

/**
 * Plugin Name: Rox Appointment Booking
 * Description: Appointment booking scheduling solution.
 * Plugin URI: https://wordpress.org/plugins/rox-appointment-booking/
 * Author: roxnor
 * Version: 1.0.7
 * Author URI: http://roxnor.com
 * Requires PHP: 8.0
 * Text Domain: rox-appointment-booking
 * Domain Path: /languages
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.txt
 *
 */

/**
 * Main plugin class.
 * 
 * @package RoxAppointmentBooking
 * @since 1.0.0
 */
final class RoxAppointmentBooking
{
	/**
	 * Absolute path to the plugin root directory.
	 *
	 * @var string
	 */
	protected static $pluginDir;

	/**
	 * Tracks whether the plugin has already been initiated.
	 *
	 * @var bool
	 */
	protected static $initiated;

	/**
	 * Constructor.
	 * 
	 * Initializes the plugin by setting up the plugin directory and registering activation/deactivation hooks. 
	 * 
	 * @return void
	 */
	public function __construct()
	{
		// initiate only once
		if (self::$initiated === true) {
			return;
		}
		self::$initiated = true;
		self::manageConfig();
		self::$pluginDir = plugin_dir_path(__FILE__);

		add_action('plugins_loaded', [$this, 'initiate'], 10);
		register_activation_hook(ROX_APPOINTMENT_BOOKING_FILE, [$this, 'activatePlugin']);
		register_deactivation_hook(ROX_APPOINTMENT_BOOKING_FILE, [$this, 'deactivatePlugin']);
	}

	/**
	 * Defines all plugin constants.
	 *
	 * @return void
	 */
	public static function manageConfig(): void
	{
		global $wpdb;

		if (!defined('ROX_APPOINTMENT_BOOKING_VERSION')) {
			define('ROX_APPOINTMENT_BOOKING_VERSION', '1.0.7');
		}
		if (!defined('ROX_APPOINTMENT_BOOKING_PREFIX')) {
			define('ROX_APPOINTMENT_BOOKING_PREFIX', 'rox_appointment');
		}
		if (!defined('ROX_APPOINTMENT_BOOKING_DB_PREFIX')) {
			define('ROX_APPOINTMENT_BOOKING_DB_PREFIX', $wpdb->prefix);
		}
		if (!defined('ROX_APPOINTMENT_BOOKING_FILE')) {
			define('ROX_APPOINTMENT_BOOKING_FILE', __FILE__);
		}
		if (!defined('ROX_APPOINTMENT_BOOKING_NAME')) {
			define('ROX_APPOINTMENT_BOOKING_NAME', 'Booking Engine');
		}
		if (!defined('ROX_APPOINTMENT_BOOKING_TEXT_DOMAIN')) {
			define('ROX_APPOINTMENT_BOOKING_TEXT_DOMAIN', 'rox-appointment-booking');
		}
		if (!defined('ROX_APPOINTMENT_BOOKING_PATH')) {
			define('ROX_APPOINTMENT_BOOKING_PATH', trailingslashit(plugin_dir_path(__FILE__)));
		}
		if (!defined('ROX_APPOINTMENT_BOOKING_URL')) {
			define('ROX_APPOINTMENT_BOOKING_URL', trailingslashit(plugin_dir_url(__FILE__)));
		}
		if (!defined('ROX_APPOINTMENT_BOOKING_PUBLIC_PATH')) {
			define('ROX_APPOINTMENT_BOOKING_PUBLIC_PATH', trailingslashit(plugin_dir_path(__FILE__)) . 'public/');
		}
		if (!defined('ROX_APPOINTMENT_BOOKING_PUBLIC_URL')) {
			define('ROX_APPOINTMENT_BOOKING_PUBLIC_URL', trailingslashit(plugin_dir_url(__FILE__)) . 'public/');
		}
		if (!defined('ROX_APPOINTMENT_BOOKING_RESOURCES_PATH')) {
			define('ROX_APPOINTMENT_BOOKING_RESOURCES_PATH', trailingslashit(plugin_dir_path(__FILE__)) . 'src/resources/');
		}
		if (!defined('ROX_APPOINTMENT_BOOKING_RESOURCES_URL')) {
			define('ROX_APPOINTMENT_BOOKING_RESOURCES_URL', trailingslashit(plugin_dir_url(__FILE__)) . 'src/resources/');
		}
	}

	/**
	 * Loads function files from the plugin functions directory.
	 *
	 * @return void
	 */
	public static function loadFunctions()
	{
		// include all files from functions directory: src/functions/*.php
		// could not use autoloader's classmap due to prefixing vendors.
		foreach (glob(self::$pluginDir . 'src/functions/*.php') as $file) {
			include_once $file;
		}
	}

	/**
	 * Bootstraps the plugin once WordPress plugins are loaded.
	 *
	 * @return void
	 */
	public function initiate()
	{
		// dependency check
		include_once self::$pluginDir . 'dependency_check.php';
		$dependency_errors = rox_appointment_booking_check_dependency();
		if (!empty($dependency_errors)) {
			add_action('admin_notices', function () use ($dependency_errors) {
				?>
				<div class="notice notice-error">
					<h4><?php esc_html_e('RoxAppointmentBooking plugin is not activated due to the following dependency errors:', 'rox-appointment-booking'); ?></h4>
					<ul>
						<?php foreach ($dependency_errors as $error) { ?>
							<li><?php echo esc_html($error); ?></li>
						<?php } ?>
					</ul>
				</div>
				<?php
			});
			return;
		}

		// autoload composer dependencies
		require_once self::$pluginDir . 'vendor-prefixed/scoper-autoload.php';

		self::loadFunctions();

		new \RoxAppointmentBooking\Boot();
	}

	/**
	 * Runs activation handlers for the plugin.
	 *
	 * @return void
	 */
	public function activatePlugin()
	{
		// include all files from activation directory by name asc: self::$pluginDir . 'src/plugin-lifecycle/activate-plugin/*-worker.php'
		foreach (glob(self::$pluginDir . 'src/plugin-lifecycle/activate-plugin/*-worker.php') as $file) {
			include_once $file;
		}
	}

	/**
	 * Runs deactivation handlers for the plugin.
	 *
	 * @return void
	 */
	public function deactivatePlugin() {}
}

new  RoxAppointmentBooking();
