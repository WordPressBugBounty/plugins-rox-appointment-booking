<?php

/**
 * Class BookingPanelWidget
 *
 * @package RoxAppointmentBooking
 * @subpackage Modules\Elementor\Widgets
 * @since 1.0.0
 *
 * Elementor widget that renders the same booking panel as the
 * `[rox_appointment_booking]` shortcode and the "Rox Appointment Booking Panel"
 * Gutenberg block. It depends on the shared frontend bundle
 * (`rox-appointment-booking-frontend`) registered by the module Provider and
 * renders the `rox-appointment-booking-frontend-root` mount node with the same
 * `data-*` attributes the React app reads.
 *
 * Because Elementor loads the declared frontend scripts inside its editor
 * preview iframe, the real panel renders live in the editor — no separate
 * editor bundle is needed.
 */

namespace RoxAppointmentBooking\Modules\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use RoxAppointmentBooking\Modules\Elementor\Provider;

if (! defined('ABSPATH')) exit; // Exit if accessed directly

class BookingPanelWidget extends Widget_Base
{
    /**
     * Shared frontend (view) script/style handle.
     */
    protected const VIEW_HANDLE = 'rox-appointment-booking-frontend';

    /**
     * Widget machine name.
     *
     * @return string
     */
    public function get_name(): string
    {
        return 'rox-appointment-booking-panel';
    }

    /**
     * Widget title shown in the Elementor panel.
     *
     * @return string
     */
    public function get_title(): string
    {
        return esc_html__('Rox Appointment Booking Panel', 'rox-appointment-booking');
    }

    /**
     * Widget icon (Elementor icon font).
     *
     * @return string
     */
    public function get_icon(): string
    {
        return 'eicon-calendar';
    }

    /**
     * Categories this widget belongs to.
     *
     * @return string[]
     */
    public function get_categories(): array
    {
        return [Provider::CATEGORY_SLUG];
    }

    /**
     * Search keywords for the Elementor panel.
     *
     * @return string[]
     */
    public function get_keywords(): array
    {
        return ['booking', 'appointment', 'panel', 'rox'];
    }

    /**
     * Frontend scripts this widget depends on.
     *
     * @return string[]
     */
    public function get_script_depends(): array
    {
        return [self::VIEW_HANDLE, Provider::HANDLER_HANDLE];
    }

    /**
     * Frontend styles this widget depends on. The frontend style is registered
     * with the shared vendors style as a dependency, so depending on the view
     * handle pulls both in.
     *
     * @return string[]
     */
    public function get_style_depends(): array
    {
        return [self::VIEW_HANDLE];
    }

    /**
     * Registers the widget controls: one "Layout" section with two switchers
     * mirroring the Gutenberg block.
     *
     * @return void
     */
    protected function register_controls(): void
    {
        $this->start_controls_section(
            'layout',
            ['label' => esc_html__('Layout', 'rox-appointment-booking')]
        );

        $this->add_control(
            'hide_navigation',
            [
                'label'        => esc_html__('Hide left navigation', 'rox-appointment-booking'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => '',
            ]
        );

        $this->add_control(
            'hide_info',
            [
                'label'        => esc_html__('Hide right info section', 'rox-appointment-booking'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => '',
                'description'  => esc_html__('The right info / booking summary appears from the Date & Time step onward, so it is not visible on the first step.', 'rox-appointment-booking'),
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Server-renders the same root node the shortcode uses, which the shared
     * frontend bundle mounts the booking panel on.
     *
     * @return void
     */
    protected function render(): void
    {
        $settings = $this->get_settings_for_display();

        $hide_navigation = (($settings['hide_navigation'] ?? '') === 'yes') ? 'true' : 'false';
        $hide_info       = (($settings['hide_info'] ?? '') === 'yes') ? 'true' : 'false';

        printf(
            '<div class="rox-appointment-booking-frontend-root" data-type="booking-form" data-hide-navigation="%1$s" data-hide-info="%2$s"></div>',
            esc_attr($hide_navigation),
            esc_attr($hide_info)
        );
    }
}
