<?php

/**
 * Class CustomerPanelShortcode
 *
 * @package RoxAppointmentBooking
 * @subpackage Modules\CustomerPanel\Services
 * @since 1.0.0
 *
 * Registers the `[rox_appointment_dashboard]` shortcode — the frontend home of
 * the Customer Panel. One page serves both states:
 *
 *   logged-in customer  → the Customer Panel bundle mounts (same bundle the
 *                         wp-admin page uses, same mount node id)
 *   logged out          → the standalone login form, set to return to this page
 *   logged-in agent     → redirected to their own dashboard in wp-admin
 *   administrator       → a short notice + a link back to wp-admin
 *
 * Because logging out lands back on this page, the visitor simply sees the login
 * form again — no separate login page has to exist for the logout redirect to
 * have somewhere to go.
 */

namespace RoxAppointmentBooking\Modules\CustomerPanel\Services;

use RoxAppointmentBooking\Supports\Assets;

if (! defined('ABSPATH')) exit; // Exit if accessed directly

class CustomerPanelShortcode
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
    protected const SHORTCODE = 'rox_appointment_dashboard';

    /**
     * Frontend script/style handle. Shares the name the wp-admin surface uses —
     * the two never render in the same request, so there is nothing to collide.
     */
    public const VIEW_HANDLE = 'rox-appointment-booking-customer-panel';

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
        add_action('template_redirect', [$this, 'redirectAgentsToAdmin']);
        add_filter('template_include', [$this, 'fullScreenTemplate']);
        add_shortcode(self::SHORTCODE, [$this, 'renderShortcode']);
    }

    /**
     * The panel is a standalone app, not a piece of page content — squeezed into
     * a theme's content column it loses the full-bleed canvas it was designed on
     * (the wp-admin surface got the same treatment, by hiding the admin chrome
     * with CSS). So for a customer this page is rendered from the plugin's own
     * bare template instead of the theme's: no site header, no footer, no page
     * title, the whole viewport.
     *
     * Everyone else — a logged-out visitor meeting the login form, an
     * administrator previewing the page — keeps the theme's template, where the
     * form belongs inside the site's own design.
     *
     * @param string $template Template the theme resolved.
     * @return string
     */
    public function fullScreenTemplate($template)
    {
        if (!is_user_logged_in() || !rox_appointment_booking_is_customer() || !$this->isDashboardPage()) {
            return $template;
        }

        // `template_include` is resolved before the template runs `wp_head()`, so
        // there is still time to enqueue normally and have the bundle land in
        // <head> rather than being flushed late into the footer.
        add_action('wp_enqueue_scripts', [$this, 'enqueueFullScreenAssets'], 100);

        return ROX_APPOINTMENT_BOOKING_PATH . 'src/Modules/CustomerPanel/views/full-screen.php';
    }

    /**
     * One page, two destinations: a customer signing in here stays and gets the
     * panel, an agent is handed straight to their own dashboard in wp-admin.
     *
     * Runs on `template_redirect` rather than inside the shortcode because a
     * shortcode renders during `the_content`, long after the headers went out.
     *
     * Administrators are deliberately left alone — they have to be able to open,
     * preview and edit this page.
     *
     * @return void
     */
    public function redirectAgentsToAdmin(): void
    {
        if (!is_user_logged_in() || !$this->isDashboardPage()) {
            return;
        }

        if (!in_array('rox_appointment_booking_agent', rox_appointment_booking_get_current_user_role(), true)) {
            return;
        }

        wp_safe_redirect(admin_url('admin.php?page=rox-appointment-booking-dashboard'));
        exit;
    }

    /**
     * Whether the request is for a page carrying this shortcode — the page the
     * plugin created, or any other one the site owner has put it on.
     *
     * @return bool
     */
    protected function isDashboardPage(): bool
    {
        if (!is_singular()) {
            return false;
        }

        $post = get_post();
        if (!$post) {
            return false;
        }

        return (int) $post->ID === rox_appointment_booking_dashboard_page_id()
            || has_shortcode($post->post_content, self::SHORTCODE);
    }

    /**
     * Registers the Customer Panel bundle (runtime -> vendors -> app).
     *
     * Registration alone emits nothing, so it runs unconditionally; the bundle is
     * only enqueued when the shortcode actually renders for a customer. This keeps
     * the panel working where the shortcode is not in `$post->post_content`
     * (page builders, templates, widgets).
     *
     * @return void
     */
    public function registerAssets(): void
    {
        $build_url  = ROX_APPOINTMENT_BOOKING_PUBLIC_URL . 'build/';
        $build_path = ROX_APPOINTMENT_BOOKING_PUBLIC_PATH . 'build/';

        $asset_file = $build_path . 'customer-panel/app.asset.php';
        if (!file_exists($asset_file)) {
            self::$register_state = 'no-asset-file';
            return;
        }

        $asset = require $asset_file;

        // runtime -> vendors -> entry load order (shared chunks are idempotent).
        $shared = Assets::enqueueSharedChunks(
            $build_url,
            $build_path,
            $asset['dependencies'] ?? [],
            $asset['version'] ?? ROX_APPOINTMENT_BOOKING_VERSION
        );

        wp_register_style(
            self::VIEW_HANDLE,
            $build_url . 'customer-panel/app.css',
            array_filter([$shared['style']]),
            $asset['version'] ?? ROX_APPOINTMENT_BOOKING_VERSION
        );

        wp_register_script(
            self::VIEW_HANDLE,
            $build_url . 'customer-panel/app.js',
            [$shared['script']],
            $asset['version'] ?? ROX_APPOINTMENT_BOOKING_VERSION,
            true
        );

        wp_set_script_translations(
            self::VIEW_HANDLE,
            'rox-appointment-booking',
            ROX_APPOINTMENT_BOOKING_PATH . 'languages'
        );

        self::$register_state = 'ok';
    }

    /**
     * Renders the dashboard shortcode: the panel, the login form, or a notice,
     * depending on who is looking.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function renderShortcode($atts = []): string
    {
        // Everything this shortcode prints is per-visitor: the panel config and
        // the login form both carry a fresh `wp_rest` nonce. wp-admin is never
        // cached, but a frontend page is — and a cached page would hand every
        // visitor someone else's stale nonce, failing every REST call with a 403.
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }

        // `is_user_logged_in()` has to come first: rox_appointment_booking_is_customer()
        // answers "which UI does this user get", and a logged-out visitor has no
        // roles at all, so it reports true for them too. In wp-admin that never
        // mattered — you cannot reach a wp-admin page logged out.
        if (!is_user_logged_in()) {
            return $this->renderLoginForm();
        }

        if (!rox_appointment_booking_is_customer()) {
            return $this->renderNonCustomerNotice() . $this->renderLoginForm();
        }

        return $this->renderPanel();
    }

    /**
     * Prints the mount node the React app looks for (the same id the wp-admin
     * view prints), once its bundle is on the page.
     *
     * @return string
     */
    protected function renderPanel(): string
    {
        if (!$this->enqueuePanelAssets()) {
            return '';
        }

        return '<div id="rox-appointment-booking-customer-panel-root" class="rox-appointment-booking-customer-panel-root"></div>';
    }

    /**
     * Puts the panel bundle on the page and hands it its config. Safe to call
     * more than once — the full-screen template enqueues on `wp_enqueue_scripts`
     * and the shortcode asks again as it renders.
     *
     * @return bool Whether the bundle is on the page.
     */
    public function enqueuePanelAssets(): bool
    {
        // `wp_enqueue_scripts` has normally already run by the time a shortcode
        // renders, but not on every render path (REST/AJAX preview, a builder
        // rendering content early). Register lazily in that case so the bundle
        // is never silently missing.
        if (self::$register_state === 'not-run') {
            $this->registerAssets();
        }

        if (!wp_script_is(self::VIEW_HANDLE, 'registered')) {
            return false;
        }

        if (wp_script_is(self::VIEW_HANDLE, 'enqueued')) {
            return true;
        }

        wp_enqueue_style(self::VIEW_HANDLE);
        wp_enqueue_script(self::VIEW_HANDLE);

        // Attached here rather than in registerAssets() so the config — nonce,
        // logout url, current user — is only emitted on a page that actually
        // mounts the panel.
        wp_add_inline_script(
            self::VIEW_HANDLE,
            CustomerPanelConfig::inlineScript(),
            'before'
        );

        return true;
    }

    /**
     * The bundle plus the handful of rules that give it the whole viewport. The
     * plugin's template prints no theme markup, so this only has to undo the
     * browser's own body margin and paint the panel's background behind it.
     *
     * @return void
     */
    public function enqueueFullScreenAssets(): void
    {
        if (!$this->enqueuePanelAssets()) {
            return;
        }

        wp_add_inline_style(
            self::VIEW_HANDLE,
            'html, body.rox-appointment-booking-dashboard-page { margin: 0; padding: 0; background: #f4f5f7; }
             .rox-appointment-booking-customer-panel-root, .rox-appointment-booking-customer-panel-root > .rox-cp { min-height: 100vh; }'
        );
    }

    /**
     * The standalone login form, told to come back to this page once the visitor
     * signs in. Rendered through the shortcode rather than by mounting the bundle
     * here, so the LoginForm module keeps owning its own assets and config.
     *
     * @return string
     */
    protected function renderLoginForm(): string
    {
        return do_shortcode(
            sprintf('[rox_appointment_login redirect="%s"]', esc_url_raw($this->currentPageUrl()))
        );
    }

    /**
     * Shown to an administrator who opens the page: the panel is a customer UI,
     * and their own tools live in wp-admin. Agents never see this — they are
     * redirected on `template_redirect` before the page renders.
     *
     * @return string
     */
    protected function renderNonCustomerNotice(): string
    {
        return sprintf(
            '<p class="rox-appointment-booking-dashboard-notice">%1$s <a href="%2$s">%3$s</a></p>',
            esc_html__('This dashboard is for customer accounts. Your booking tools are in the WordPress admin.', 'rox-appointment-booking'),
            esc_url(admin_url('admin.php?page=rox-appointment-booking-dashboard')),
            esc_html__('Go to the admin dashboard', 'rox-appointment-booking')
        );
    }

    /**
     * Permalink of the page the shortcode is rendering on — where the login form
     * should return to. Falls back to the requested URL for render paths with no
     * post in scope (a builder template, a widget area).
     *
     * @return string
     */
    protected function currentPageUrl(): string
    {
        $permalink = get_permalink();

        return $permalink ? $permalink : home_url(add_query_arg([]));
    }
}
