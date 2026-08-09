<?php

defined('ABSPATH') || exit;

/**
 * Plugin Name: Rox Appointment Booking
 * Description: Appointment booking scheduling solution.
 * Plugin URI: https://wordpress.org/plugins/rox-appointment-booking/
 * Author: roxnor
 * Version: 1.2.1
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
			define('ROX_APPOINTMENT_BOOKING_VERSION', '1.2.1');
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

		self::maybeUpgrade();

		new \RoxAppointmentBooking\Boot();
	}

	/**
	 * Runs activation handlers for the plugin.
	 *
	 * @return void
	 */
	public function activatePlugin()
	{
		self::runActivationWorkers();
		update_option('rox_appointment_booking_db_version', ROX_APPOINTMENT_BOOKING_VERSION);

		// Only a real activation sends the admin to onboarding — maybeUpgrade()
		// replays the same workers on every version bump and must not.
		set_transient('rox_appointment_booking_activation_redirect', 1, 60);
	}

	/**
	 * Includes every DB schema worker in numeric filename order: self::$pluginDir . 'src/plugin-lifecycle/activate-plugin/*-worker.php'.
	 * Each worker guards itself with a SHOW TABLES/SHOW COLUMNS check before
	 * creating or altering anything, so replaying the full set here is safe
	 * even for sites that already have every table/column — only the workers
	 * whose change is actually missing do anything.
	 *
	 * @return void
	 */
	protected static function runActivationWorkers(): void
	{
		$files = glob(self::$pluginDir . 'src/plugin-lifecycle/activate-plugin/*-worker.php') ?: [];

		// glob() sorts alphabetically, which orders 17- before 2- and so runs
		// the ALTER workers before the CREATE TABLE they depend on — on a fresh
		// install those ALTERs hit a missing table, skip, and their columns are
		// never added. Sort naturally so the numeric prefix means what it looks
		// like it means.
		usort($files, static fn($a, $b) => strnatcmp(basename($a), basename($b)));

		foreach ($files as $file) {
			include_once $file;
		}
	}

	/**
	 * Picks up schema changes (new tables/columns) for sites that update the
	 * plugin through the normal WordPress update flow instead of a manual
	 * deactivate+reactivate. WordPress only fires register_activation_hook on
	 * an explicit activation — a plain "Update" in wp-admin (or an
	 * auto-update) never triggers it, so an already-active site would
	 * otherwise never get new DB columns added by a later plugin version.
	 *
	 * Runs on every `plugins_loaded`, but is a no-op past the first request
	 * after an update: the stored option is bumped to the current version as
	 * soon as the workers run once.
	 *
	 * @return void
	 */
	public static function maybeUpgrade(): void
	{
		$installedVersion = get_option('rox_appointment_booking_db_version', '');
		if ($installedVersion === ROX_APPOINTMENT_BOOKING_VERSION) {
			return;
		}

		self::runActivationWorkers();
		update_option('rox_appointment_booking_db_version', ROX_APPOINTMENT_BOOKING_VERSION);
	}

	/**
	 * Runs deactivation handlers for the plugin.
	 *
	 * @return void
	 */
	public function deactivatePlugin() {}
}

new  RoxAppointmentBooking();
