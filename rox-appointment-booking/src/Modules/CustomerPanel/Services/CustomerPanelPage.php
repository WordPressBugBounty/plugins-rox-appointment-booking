<?php

/**
 * Class CustomerPanelPage
 *
 * @package RoxAppointmentBooking
 * @subpackage Modules\CustomerPanel\Services
 * @since 1.0.0
 *
 * Owns the frontend customer dashboard page so the site owner never has to build
 * one. Runs once, when `22-create_customer_dashboard_page-worker.php` has raised
 * the pending flag: it adopts an existing page that already carries the
 * `[rox_appointment_dashboard]` shortcode, or publishes a new one, and stores the
 * id in `rox_appointment_booking_dashboard_page_id`.
 */

namespace RoxAppointmentBooking\Modules\CustomerPanel\Services;

if (! defined('ABSPATH')) exit; // Exit if accessed directly

class CustomerPanelPage
{
    /**
     * Whether the service should be loadable.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * Option holding the resolved page id.
     */
    public const PAGE_OPTION = 'rox_appointment_booking_dashboard_page_id';

    /**
     * Option the activation worker raises to request a one-time run.
     */
    public const PENDING_OPTION = 'rox_appointment_booking_dashboard_page_pending';

    /**
     * Slug of the page this creates.
     */
    protected const PAGE_SLUG = 'rox-appointment-dashboard';

    /**
     * Shortcode the page is built around, and the marker an existing page is
     * recognised by.
     */
    protected const SHORTCODE_TAG = '[rox_appointment_dashboard';

    /**
     * Constructor.
     */
    public function __construct()
    {
        // Late on `init`: post types are registered, the text domain is loaded,
        // and rewrite rules exist, so the page can be inserted and permalinked.
        add_action('init', [$this, 'maybeCreatePage'], 20);
        add_filter('display_post_states', [$this, 'addPostState'], 10, 2);
    }

    /**
     * Labels the page in the wp-admin Pages list — the same "— Cart Page" badge
     * WooCommerce puts on its own pages — so a site owner can tell at a glance
     * which page belongs to the plugin and does not delete it by mistake.
     *
     * @param array    $post_states Existing states for this row.
     * @param \WP_Post $post        The page being listed.
     * @return array
     */
    public function addPostState($post_states, $post)
    {
        $page_id = rox_appointment_booking_dashboard_page_id();

        if ($page_id && $post instanceof \WP_Post && (int) $post->ID === $page_id) {
            $post_states['rox_appointment_booking_dashboard'] = esc_html__('Rox Appointment Dashboard', 'rox-appointment-booking');
        }

        return $post_states;
    }

    /**
     * Creates (or adopts) the dashboard page when the activation worker has asked
     * for it. A no-op on every other request — the pending option is autoloaded,
     * so the check costs no query.
     *
     * @return void
     */
    public function maybeCreatePage(): void
    {
        if (!get_option(self::PENDING_OPTION, 0)) {
            return;
        }

        // Cleared up front so this stays a one-shot: a site where the insert
        // fails must not retry it on every request forever. The site owner can
        // still point the plugin at a page of their own.
        delete_option(self::PENDING_OPTION);

        if (get_option(self::PAGE_OPTION, 0)) {
            return;
        }

        $page_id = $this->findExistingPage() ?: $this->createPage();

        if ($page_id) {
            update_option(self::PAGE_OPTION, $page_id);
        }
    }

    /**
     * Finds a published page that already renders the dashboard shortcode. Sites
     * that built their own dashboard page before this shipped keep it instead of
     * getting a duplicate.
     *
     * @return int Page id, or 0 when there is none.
     */
    protected function findExistingPage(): int
    {
        global $wpdb;

        // Matched without the closing bracket so `[rox_appointment_dashboard foo="1"]`
        // counts too. A one-time query behind a one-shot flag, so the LIKE scan
        // never runs on a normal request.
        $page_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                 WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE %s
                 ORDER BY ID ASC LIMIT 1",
                '%' . $wpdb->esc_like(self::SHORTCODE_TAG) . '%'
            )
        );

        return (int) $page_id;
    }

    /**
     * Publishes the dashboard page. The shortcode is wrapped in a shortcode block
     * so the block editor shows it as a proper block; the wrapper comments are
     * inert everywhere else.
     *
     * @return int Page id, or 0 if the insert failed.
     */
    protected function createPage(): int
    {
        $page_id = wp_insert_post([
            'post_title'     => esc_html__('Agent & Customer Dashboard', 'rox-appointment-booking'),
            'post_name'      => self::PAGE_SLUG,
            'post_content'   => '<!-- wp:shortcode -->[rox_appointment_dashboard]<!-- /wp:shortcode -->',
            'post_status'    => 'publish',
            'post_type'      => 'page',
            'comment_status' => 'closed',
            'ping_status'    => 'closed',
        ]);

        return is_wp_error($page_id) ? 0 : (int) $page_id;
    }
}
