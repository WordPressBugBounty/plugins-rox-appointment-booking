<?php

/**
 * Class LoginFormShortcode
 *
 * @package RoxAppointmentBooking
 * @subpackage Modules\LoginForm\Services
 * @since 1.0.0
 *
 * Registers the self-contained `[rox_appointment_login]` shortcode. The
 * shortcode owns the standalone login form's frontend bundle (`view.js` /
 * `view.css`) and mounts a bespoke React app on a dedicated root class
 * `rox-appointment-booking-login-form-root`. It never reuses the booking
 * panel's mount class, so the two never collide.
 *
 * The booking-panel shortcode (`[rox_appointment_booking]`), `FrontendApp` and
 * `BookingService` are NOT touched.
 */

namespace RoxAppointmentBooking\Modules\LoginForm\Services;

use RoxAppointmentBooking\Supports\Assets;

if (! defined('ABSPATH')) exit; // Exit if accessed directly

class LoginFormShortcode
{
    /**
     * Whether the service should be loadable.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * Shortcode tag.
     */
    protected const SHORTCODE = 'rox_appointment_login';

    /**
     * Frontend (view) script handle.
     */
    public const VIEW_HANDLE = 'rox-appointment-booking-login-form-view';

    /**
     * Frontend (view) style handle.
     */
    public const VIEW_STYLE_HANDLE = 'rox-appointment-booking-login-form-view-style';

    /**
     * Per-request counter so every form on a page gets a unique instance id.
     *
     * @var int
     */
    protected static int $instance_count = 0;

    /**
     * Outcome of the last registerAssets() run, so renderShortcode() can register
     * lazily when the hook has not fired. One of: `not-run`, `no-asset-file`, `ok`.
     *
     * @var string
     */
    protected static string $register_state = 'not-run';

    /**
     * Constructor.
     */
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'registerAssets'], 100);
        add_shortcode(self::SHORTCODE, [$this, 'renderShortcode']);
    }

    /**
     * Registers the login-form view bundle (runtime -> vendors -> view).
     *
     * Registration alone emits nothing, so it runs unconditionally; the bundle
     * is only enqueued when the shortcode actually renders. This keeps the form
     * working where the shortcode is not in `$post->post_content` (page
     * builders, templates, widgets).
     *
     * @return void
     */
    public function registerAssets(): void
    {
        $build_url  = ROX_APPOINTMENT_BOOKING_PUBLIC_URL . 'build/';
        $build_path = ROX_APPOINTMENT_BOOKING_PATH . 'public/build/';

        $view_asset_file = $build_path . 'blocks/login-form/view.asset.php';
        if (!file_exists($view_asset_file)) {
            self::$register_state = 'no-asset-file';
            return;
        }

        $view_asset = require $view_asset_file;

        $shared = Assets::enqueueSharedChunks(
            $build_url,
            $build_path,
            $view_asset['dependencies'] ?? [],
            $view_asset['version'] ?? ROX_APPOINTMENT_BOOKING_VERSION
        );

        wp_register_script(
            self::VIEW_HANDLE,
            $build_url . 'blocks/login-form/view.js',
            [$shared['script']],
            $view_asset['version'] ?? ROX_APPOINTMENT_BOOKING_VERSION,
            true
        );

        wp_set_script_translations(
            self::VIEW_HANDLE,
            'rox-appointment-booking',
            ROX_APPOINTMENT_BOOKING_PATH . 'languages'
        );

        wp_register_style(
            self::VIEW_STYLE_HANDLE,
            $build_url . 'blocks/login-form/view.css',
            array_filter([$shared['style']]),
            $view_asset['version'] ?? ROX_APPOINTMENT_BOOKING_VERSION
        );

        self::$register_state = 'ok';
    }

    /**
     * Renders the login-form shortcode: enqueues the bundle and outputs the root
     * div the view bundle mounts on.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function renderShortcode($atts = []): string
    {
        $atts = shortcode_atts([
            'redirect' => '',
        ], $atts, self::SHORTCODE);

        // `wp_enqueue_scripts` has normally already run by the time a shortcode
        // renders, but not on every render path (REST/AJAX preview, a builder
        // rendering content early). Register lazily in that case so the bundle
        // is never silently missing.
        if (self::$register_state === 'not-run') {
            $this->registerAssets();
        }

        // Enqueued at render time (during the_content, after wp_enqueue_scripts)
        // so the bundle loads only on pages that actually show the form. The
        // footer-loaded script then mounts on the div printed below.
        if (wp_script_is(self::VIEW_HANDLE, 'registered')) {
            wp_enqueue_script(self::VIEW_HANDLE);
            wp_enqueue_style(self::VIEW_STYLE_HANDLE);
        }

        self::$instance_count++;

        $config = LoginFormConfig::build($atts['redirect']);

        return sprintf(
            '<div class="rox-appointment-booking-login-form-root" data-instance="%1$s" data-config="%2$s"></div>',
            esc_attr((string) self::$instance_count),
            esc_attr(wp_json_encode($config))
        );
    }
}
