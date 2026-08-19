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
use RoxAppointmentBooking\Supports\Color;
use RoxAppointmentBooking\Supports\IdList;
use RoxAppointmentBooking\Modules\Category\Data\CategoryModel;

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
     * Registers the widget controls, mirroring the Gutenberg block: a "Layout"
     * section (visibility switchers + the panel frame) and an "Availability"
     * section restricting which locations / categories the visitor is offered.
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

        $this->add_control(
            'show_background',
            [
                'label'        => esc_html__('Enable background', 'rox-appointment-booking'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
                'description'  => esc_html__('Draws the grey frame (background, padding and shadow) around the panel.', 'rox-appointment-booking'),
            ]
        );

        $this->add_control(
            'background_color',
            [
                'label'       => esc_html__('Background color', 'rox-appointment-booking'),
                'type'        => Controls_Manager::COLOR,
                'default'     => '',
                'description' => esc_html__('Leave empty to keep the default grey.', 'rox-appointment-booking'),
                // Only meaningful while the frame is drawn.
                'condition'   => ['show_background' => 'yes'],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'availability',
            ['label' => esc_html__('Availability', 'rox-appointment-booking')]
        );

        // The location control is only offered when the visitor would actually
        // see a location step to restrict: Pro active, the location module on,
        // and more than one location saved (with exactly one the panel
        // auto-selects it and skips the step).
        if (rox_appointment_booking_location_choice_available()) {
            $this->add_control(
                'location_ids',
                [
                    'label'       => esc_html__('Locations', 'rox-appointment-booking'),
                    'type'        => Controls_Manager::SELECT2,
                    'multiple'    => true,
                    'options'     => $this->getLocationOptions(),
                    'default'     => [],
                    'label_block' => true,
                    'description' => esc_html__('Leave empty to offer every location. Pick one or more to limit the location step to just those.', 'rox-appointment-booking'),
                ]
            );
        }

        $this->add_control(
            'category_ids',
            [
                'label'       => esc_html__('Categories', 'rox-appointment-booking'),
                'type'        => Controls_Manager::SELECT2,
                'multiple'    => true,
                'options'     => $this->getCategoryOptions(),
                'default'     => [],
                'label_block' => true,
                'description' => esc_html__('Leave empty to offer every category. Pick one or more to limit the category step to just those.', 'rox-appointment-booking'),
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Builds the location SELECT2 options (`id => name`). Only ever called
     * behind rox_appointment_booking_location_choice_available(), which already
     * guarantees Pro is active and therefore that the Pro location model exists.
     *
     * @return array<int, string>
     */
    protected function getLocationOptions(): array
    {
        $model = '\RoxAppointmentBookingPro\Modules\Location\Data\LocationModel';

        if (!class_exists($model)) {
            return [];
        }

        $options = [];

        foreach ($model::query()->where('status', 'active')->get() as $location) {
            $id = (int) $location->getID();
            $name = trim((string) $location->getName());

            $options[$id] = $name !== '' ? $name : sprintf(
                /* translators: %d: location id */
                esc_html__('Location #%d', 'rox-appointment-booking'),
                $id
            );
        }

        return $options;
    }

    /**
     * Builds the category SELECT2 options (`id => title`).
     *
     * @return array<int, string>
     */
    protected function getCategoryOptions(): array
    {
        $options = [];

        foreach (CategoryModel::query()->get() as $category) {
            $id = (int) $category->getID();
            $title = trim((string) $category->title);

            $options[$id] = $title !== '' ? $title : sprintf(
                /* translators: %d: category id */
                esc_html__('Category #%d', 'rox-appointment-booking'),
                $id
            );
        }

        return $options;
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

        // Frame switch defaults to on, unlike the two hide toggles above.
        $show_background  = (($settings['show_background'] ?? 'yes') === 'yes') ? 'true' : 'false';
        $background_color = Color::sanitize((string) ($settings['background_color'] ?? ''));

        // Optional "only offer these" picks. Elementor hands SELECT2 values
        // back as strings; an empty list means no restriction.
        $location_ids = IdList::toAttr($settings['location_ids'] ?? []);
        $category_ids = IdList::toAttr($settings['category_ids'] ?? []);

        printf(
            '<div class="rox-appointment-booking-frontend-root" data-type="booking-form" data-hide-navigation="%1$s" data-hide-info="%2$s" data-show-background="%3$s" data-background-color="%4$s" data-locations="%5$s" data-categories="%6$s"></div>',
            esc_attr($hide_navigation),
            esc_attr($hide_info),
            esc_attr($show_background),
            esc_attr($background_color),
            esc_attr($location_ids),
            esc_attr($category_ids)
        );
    }
}
