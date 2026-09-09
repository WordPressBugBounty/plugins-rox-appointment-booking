<?php

/**
 * Class BookingPanelWidget
 *
 * @package RoxAppointmentBooking
 * @subpackage Modules\Elementor\Widgets
 * @since 1.0.0
 *
 * Elementor widget that shows the same booking panel as the
 * `[rox_appointment_booking]` shortcode and the "Rox Appointment Booking Panel"
 * Gutenberg block, one of two ways:
 *
 * - `general` (the default): the panel is laid out in place. The widget depends
 *   on the shared frontend bundle (`rox-appointment-booking-frontend`) and
 *   renders the `rox-appointment-booking-frontend-root` mount node with the
 *   same `data-*` attributes the React app reads.
 * - `popup`: only a trigger button is rendered, through
 *   {@see BookingButtonMarkup} so it stays byte-identical to the Gutenberg
 *   block's. The modal and the panel inside it are built client-side on the
 *   first click by the view bundle.
 *
 * Because Elementor loads the declared frontend scripts inside its editor
 * preview iframe, the real panel renders live in the editor — no separate
 * editor bundle is needed.
 *
 * The button's presentation is left to Elementor's own controls (typography,
 * colours, border, padding, margin, alignment), which generate a stylesheet rule
 * per element — so the markup is asked for no inline style at all, since an
 * inline style would always win. Colours are written as the `--rox-btn-*` custom
 * properties the button stylesheet reads, which is what makes the hover controls
 * work.
 */

namespace RoxAppointmentBooking\Modules\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Box_Shadow;
use RoxAppointmentBooking\Modules\Elementor\NavButtonSection;
use RoxAppointmentBooking\Modules\Elementor\Provider;
use RoxAppointmentBooking\Supports\BookingPanel\BookingButtonAssets;
use RoxAppointmentBooking\Supports\BookingPanel\BookingButtonMarkup;
use RoxAppointmentBooking\Supports\BookingPanel\PanelContent;
use RoxAppointmentBooking\Supports\Color;
use RoxAppointmentBooking\Supports\FontFamily;
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
     * Shows a control only once one of the layout switches is on.
     *
     * Elementor's plain `condition` is an AND, and either switch on its own is
     * enough here, so this is the `conditions` form with an explicit `or`.
     *
     * @var array
     */
    protected const HIDDEN_COLUMN_CONDITION = [
        'relation' => 'or',
        'terms'    => [
            ['name' => 'hide_navigation', 'operator' => '===', 'value' => 'yes'],
            ['name' => 'hide_info', 'operator' => '===', 'value' => 'yes'],
        ],
    ];

    /**
     * The panel copy controls that hold plain text, as control name => the
     * override key the panel reads it under.
     *
     * Elementor stores a widget's settings flat, one control at a time, so the
     * two names are kept side by side here and nowhere else: the section below
     * registers the left column, {@see panelContent()} rebuilds the right one.
     *
     * @var array<string, string>
     */
    protected const PANEL_CONTENT_TEXT = [
        // Step 1's sidebar: its title and subtitle, and the help box under them.
        'panel_sidebar_title'          => 'sidebarTitle',
        'panel_sidebar_subtitle'       => 'sidebarSubtitle',
        'panel_help_title'             => 'helpTitle',
        'panel_help_button_text'       => 'helpButtonText',
        'panel_help_button_url'        => 'helpButtonUrl',
        'panel_help_note'              => 'helpNote',
        // The heading above each step's cards.
        'panel_location_heading'       => 'locationHeading',
        'panel_category_heading'       => 'categoryHeading',
        'panel_services_heading'       => 'servicesHeading',
        'panel_agents_heading'         => 'agentsHeading',
        'panel_datetime_heading'       => 'dateTimeHeading',
        'panel_information_heading'    => 'informationHeading',
        // The left step list, from the second step onward.
        'panel_step_location_label'    => 'stepLocationLabel',
        'panel_step_category_label'    => 'stepCategoryLabel',
        'panel_step_services_label'    => 'stepServicesLabel',
        'panel_step_agents_label'      => 'stepAgentsLabel',
        'panel_step_datetime_label'    => 'stepDateTimeLabel',
        'panel_step_information_label' => 'stepInformationLabel',
        'panel_step_payment_label'     => 'stepPaymentLabel',
        'panel_step_complete_label'    => 'stepCompleteLabel',
    ];

    /**
     * Widget machine name. Both Elementor handler scripts hook
     * `frontend/element_ready/<name>.default`, so this string is also part of
     * that contract.
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
        return ['booking', 'appointment', 'panel', 'button', 'popup', 'modal', 'rox'];
    }

    /**
     * Frontend scripts this widget depends on.
     *
     * Both modes are declared, not just the one in use: Elementor resolves a
     * widget's dependencies from the widget type, so a per-setting list would
     * leave a popup with no bundle behind it the moment Elementor asked before
     * the settings were in hand. The trigger bundle is small and registered with
     * the panel bundle as its dependency, so nothing is loaded twice.
     *
     * @return string[]
     */
    public function get_script_depends(): array
    {
        return [
            self::VIEW_HANDLE,
            Provider::HANDLER_HANDLE,
            BookingButtonAssets::VIEW_HANDLE,
            Provider::BUTTON_HANDLER_HANDLE,
        ];
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
        return [self::VIEW_HANDLE, BookingButtonAssets::VIEW_HANDLE];
    }

    /**
     * Registers the widget controls, mirroring the Gutenberg block: a display
     * mode, a "Panel content" section rewriting the panel's own wording, the
     * trigger button's own sections (popup only), a "Layout" section
     * (visibility switchers + the panel frame), an "Availability" section
     * restricting which locations / categories the visitor is offered, and a
     * Style-tab section per navigation button.
     *
     * @return void
     */
    protected function register_controls(): void
    {
        $this->registerDisplaySection();
        $this->registerPanelContentSection();
        $this->registerButtonContentSection();
        $this->registerPopupSection();
        $this->registerLayoutSection();
        $this->registerAvailabilitySection();
        $this->registerButtonStyleSection();
        $this->registerColorSection();

        // The panel the modal builds sits on <body>, outside {{WRAPPER}}, so
        // every rule is written to it as well.
        NavButtonSection::register($this, BookingButtonMarkup::PANEL_OWNER_SELECTOR);

        $this->registerDashboardButtonSection();
    }

    /**
     * The confirmation screen's "Go to Dashboard" button: whether it shows, and
     * its label / target when it does. Its own section rather than a row in
     * Layout — it belongs with the nav buttons, after the Back / Next sections.
     *
     * @return void
     */
    protected function registerDashboardButtonSection(): void
    {
        $this->start_controls_section(
            'dashboard_button',
            ['label' => esc_html__('Go to Dashboard button', 'rox-appointment-booking')]
        );

        $this->add_control(
            'show_dashboard_button',
            [
                'label'        => esc_html__('Show button', 'rox-appointment-booking'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
                'description'  => esc_html__('On the booking confirmation screen. Turn off for a public panel where visitors have no account.', 'rox-appointment-booking'),
            ]
        );

        $this->add_control(
            'dashboard_button_text',
            [
                'label'       => esc_html__('Button text', 'rox-appointment-booking'),
                'type'        => Controls_Manager::TEXT,
                'default'     => '',
                'placeholder' => esc_html__('Go to Dashboard', 'rox-appointment-booking'),
                'label_block' => true,
                'condition'   => ['show_dashboard_button' => 'yes'],
            ]
        );

        $this->add_control(
            'dashboard_button_url',
            [
                'label'       => esc_html__('Button link', 'rox-appointment-booking'),
                'type'        => Controls_Manager::TEXT,
                'default'     => '',
                'placeholder' => esc_html__('Customer dashboard page', 'rox-appointment-booking'),
                'label_block' => true,
                'description' => esc_html__("Leave empty to use the plugin's customer dashboard page.", 'rox-appointment-booking'),
                'condition'   => ['show_dashboard_button' => 'yes'],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Display: whether the panel sits on the page or behind a button.
     *
     * @return void
     */
    protected function registerDisplaySection(): void
    {
        $this->start_controls_section(
            'display',
            ['label' => esc_html__('Display', 'rox-appointment-booking')]
        );

        $this->add_control(
            'display_mode',
            [
                'label'       => esc_html__('Display mode', 'rox-appointment-booking'),
                'type'        => Controls_Manager::SELECT,
                'default'     => 'general',
                'options'     => [
                    'general' => esc_html__('General', 'rox-appointment-booking'),
                    'popup'   => esc_html__('Popup', 'rox-appointment-booking'),
                ],
                'description' => esc_html__('General lays the panel out on the page. Popup shows a button that opens it.', 'rox-appointment-booking'),
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Panel content: the panel's own wording, as far as an editor rewrites it.
     *
     * The booking panel's copy lives in its React components — the first step's
     * sidebar, the heading above each step's cards, and the step list down the
     * left from the second step onward. This is where an editor rewrites it,
     * and it applies to both modes: the popup carries the same copy to the
     * panel the modal builds.
     *
     * @return void
     */
    protected function registerPanelContentSection(): void
    {
        $this->start_controls_section(
            'panel_content',
            ['label' => esc_html__('Panel content', 'rox-appointment-booking')]
        );

        $this->add_control(
            'panel_sidebar_group',
            [
                'label' => esc_html__('First step sidebar', 'rox-appointment-booking'),
                'type'  => Controls_Manager::HEADING,
            ]
        );

        $this->add_control(
            'panel_sidebar_image',
            [
                'label'       => esc_html__('Illustration', 'rox-appointment-booking'),
                'type'        => Controls_Manager::MEDIA,
                'media_types' => ['image'],
                'default'     => ['url' => ''],
                'description' => esc_html__('Leave empty to keep the panel\'s own illustration.', 'rox-appointment-booking'),
            ]
        );

        // Sits with the picker because it sizes what the picker chose. Offered
        // even with no image of its own: the panel's default illustration is
        // sized by the same value, so it opens on that width rather than on the
        // slider's floor.
        $width = PanelContent::INT_RANGES['sidebarImageWidth'];

        $this->add_control(
            'panel_sidebar_image_width',
            [
                'label'       => esc_html__('Illustration width', 'rox-appointment-booking'),
                'type'        => Controls_Manager::SLIDER,
                'size_units'  => ['px'],
                'range'       => ['px' => ['min' => $width['min'], 'max' => $width['max']]],
                'default'     => ['unit' => 'px', 'size' => $width['default']],
                'description' => sprintf(
                    /* translators: %d: sidebar column width in pixels */
                    esc_html__('The sidebar column is %d px wide, so the image is scaled down to fit rather than past it.', 'rox-appointment-booking'),
                    $width['max']
                ),
            ]
        );

        $this->contentField(
            'panel_sidebar_title',
            esc_html__('Title', 'rox-appointment-booking'),
            __('Location Selection', 'rox-appointment-booking'),
            esc_html__('Shown whichever step comes first, so a panel that starts on Category or Services uses this too.', 'rox-appointment-booking')
        );

        $this->contentField(
            'panel_sidebar_subtitle',
            esc_html__('Subtitle', 'rox-appointment-booking'),
            __('Select the location where you\'d like to book your appointment.', 'rox-appointment-booking')
        );

        $this->contentField(
            'panel_help_title',
            esc_html__('Help box title', 'rox-appointment-booking'),
            __('Need Help?', 'rox-appointment-booking')
        );

        $this->contentField(
            'panel_help_button_text',
            esc_html__('Help button text', 'rox-appointment-booking'),
            __('Help', 'rox-appointment-booking')
        );

        // A plain field rather than Elementor's link picker: the panel renders
        // the help box as a bare anchor, so the picker's target and nofollow
        // options would be controls that do nothing.
        $this->contentField(
            'panel_help_button_url',
            esc_html__('Help button link', 'rox-appointment-booking'),
            '/help'
        );

        $this->contentField(
            'panel_help_note',
            esc_html__('Help box note', 'rox-appointment-booking'),
            __('If you have any questions', 'rox-appointment-booking')
        );

        $this->add_control(
            'panel_headings_group',
            [
                'label'     => esc_html__('Step headings', 'rox-appointment-booking'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        // The Location step is Pro's, and it only reaches the visitor with the
        // module on and more than one location to choose between — with one the
        // panel auto-selects it and drops the step. Anything less and this
        // heading is never rendered, so the field would rewrite nothing.
        if (rox_appointment_booking_location_choice_available()) {
            $this->contentField(
                'panel_location_heading',
                esc_html__('Location step', 'rox-appointment-booking'),
                __('Select Location', 'rox-appointment-booking')
            );
        }

        $this->contentField(
            'panel_category_heading',
            esc_html__('Category step', 'rox-appointment-booking'),
            __('Available Category', 'rox-appointment-booking')
        );

        $this->contentField(
            'panel_services_heading',
            esc_html__('Services step', 'rox-appointment-booking'),
            __('Services', 'rox-appointment-booking'),
            esc_html__('Follows the chosen category\'s name, as in "Haircut Services".', 'rox-appointment-booking')
        );

        $this->contentField(
            'panel_agents_heading',
            esc_html__('Agents step', 'rox-appointment-booking'),
            __('Select Agent', 'rox-appointment-booking')
        );

        $this->contentField(
            'panel_datetime_heading',
            esc_html__('Date & Time step', 'rox-appointment-booking'),
            __('Date & Time Selection', 'rox-appointment-booking')
        );

        $this->contentField(
            'panel_information_heading',
            esc_html__('Information step', 'rox-appointment-booking'),
            __('Customer Information', 'rox-appointment-booking')
        );

        $this->add_control(
            'panel_steps_group',
            [
                'label'     => esc_html__('Step list', 'rox-appointment-booking'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        // Same gate as the heading above: no Location step, no row in the list.
        if (rox_appointment_booking_location_choice_available()) {
            $this->contentField(
                'panel_step_location_label',
                esc_html__('Location', 'rox-appointment-booking'),
                __('Location', 'rox-appointment-booking')
            );
        }

        $this->contentField(
            'panel_step_category_label',
            esc_html__('Category', 'rox-appointment-booking'),
            __('Category', 'rox-appointment-booking')
        );

        $this->contentField(
            'panel_step_services_label',
            esc_html__('Services', 'rox-appointment-booking'),
            __('Services', 'rox-appointment-booking')
        );

        $this->contentField(
            'panel_step_agents_label',
            esc_html__('Agents', 'rox-appointment-booking'),
            __('Agents', 'rox-appointment-booking')
        );

        $this->contentField(
            'panel_step_datetime_label',
            esc_html__('Date & Time', 'rox-appointment-booking'),
            __('Date & Time', 'rox-appointment-booking')
        );

        $this->contentField(
            'panel_step_information_label',
            esc_html__('Information', 'rox-appointment-booking'),
            __('Information', 'rox-appointment-booking')
        );

        $this->contentField(
            'panel_step_payment_label',
            esc_html__('Payment', 'rox-appointment-booking'),
            __('Payment', 'rox-appointment-booking')
        );

        $this->contentField(
            'panel_step_complete_label',
            esc_html__('Complete', 'rox-appointment-booking'),
            __('Complete', 'rox-appointment-booking')
        );

        $this->end_controls_section();
    }

    /**
     * One copy override, as the field an editor rewrites it in.
     *
     * The field opens on the panel's own text as its value, not just as a
     * placeholder, so an editor edits the real wording instead of retyping it.
     * Clearing it drops the override and brings the default straight back.
     *
     * The default is the panel's text as it renders, not an escaped copy of it:
     * it is content on its way to the frontend, escaped where it is printed, so
     * an escaped default would ship "Date &amp; Time" as the visible label.
     *
     * @param string $name        Control name.
     * @param string $label       Field label.
     * @param string $default     The panel's own wording, unescaped.
     * @param string $description Optional note under the field.
     * @return void
     */
    protected function contentField(string $name, string $label, string $default, string $description = ''): void
    {
        $this->add_control(
            $name,
            [
                'label'       => $label,
                'type'        => Controls_Manager::TEXT,
                'default'     => $default,
                'placeholder' => $default,
                'label_block' => true,
                'description' => $description,
            ]
        );
    }

    /**
     * Button content: what the trigger says and how it sits in its column.
     *
     * @return void
     */
    protected function registerButtonContentSection(): void
    {
        $this->start_controls_section(
            'button_content',
            [
                'label'     => esc_html__('Button', 'rox-appointment-booking'),
                'condition' => ['display_mode' => 'popup'],
            ]
        );

        $this->add_control(
            'button_text',
            [
                'label'       => esc_html__('Text', 'rox-appointment-booking'),
                'type'        => Controls_Manager::TEXT,
                'default'     => esc_html__('Book Appointment', 'rox-appointment-booking'),
                'placeholder' => esc_html__('Book Appointment', 'rox-appointment-booking'),
                'label_block' => true,
            ]
        );

        $this->add_control(
            'button_icon',
            [
                'label'   => esc_html__('Icon', 'rox-appointment-booking'),
                'type'    => Controls_Manager::SELECT,
                'default' => 'calendar',
                'options' => $this->getIconOptions(),
            ]
        );

        $this->add_control(
            'button_size',
            [
                'label'       => esc_html__('Size', 'rox-appointment-booking'),
                'type'        => Controls_Manager::SELECT,
                'default'     => 'medium',
                'options'     => [
                    'small'  => esc_html__('Small', 'rox-appointment-booking'),
                    'medium' => esc_html__('Medium', 'rox-appointment-booking'),
                    'large'  => esc_html__('Large', 'rox-appointment-booking'),
                ],
                'description' => esc_html__('Sets the default padding and text size. The Style tab overrides both.', 'rox-appointment-booking'),
            ]
        );

        $this->add_control(
            'button_width',
            [
                'label'   => esc_html__('Width', 'rox-appointment-booking'),
                'type'    => Controls_Manager::SELECT,
                'default' => 'auto',
                'options' => [
                    'auto' => esc_html__('Fit to text', 'rox-appointment-booking'),
                    'full' => esc_html__('Full width', 'rox-appointment-booking'),
                ],
            ]
        );

        // The markup carries no inline text-align for Elementor, so this control
        // alone positions the button.
        $this->add_responsive_control(
            'button_align',
            [
                'label'     => esc_html__('Alignment', 'rox-appointment-booking'),
                'type'      => Controls_Manager::CHOOSE,
                'default'   => 'left',
                'selectors' => [
                    '{{WRAPPER}} .rox-booking-button-wrap' => 'text-align: {{VALUE}};',
                ],
                'options' => [
                    'left'   => [
                        'title' => esc_html__('Left', 'rox-appointment-booking'),
                        'icon'  => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => esc_html__('Center', 'rox-appointment-booking'),
                        'icon'  => 'eicon-text-align-center',
                    ],
                    'right'  => [
                        'title' => esc_html__('Right', 'rox-appointment-booking'),
                        'icon'  => 'eicon-text-align-right',
                    ],
                ],
                // A full-width button fills its column, so there is nothing left
                // to align it against.
                'condition' => ['button_width' => 'auto'],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Popup: how the modal that carries the panel behaves.
     *
     * @return void
     */
    protected function registerPopupSection(): void
    {
        $this->start_controls_section(
            'popup',
            [
                'label'     => esc_html__('Popup', 'rox-appointment-booking'),
                'condition' => ['display_mode' => 'popup'],
            ]
        );

        $this->add_control(
            'modal_width',
            [
                'label'       => esc_html__('Maximum width (px)', 'rox-appointment-booking'),
                'type'        => Controls_Manager::NUMBER,
                'default'     => 1100,
                'min'         => 600,
                'max'         => 2000,
                'step'        => 20,
                'description' => esc_html__('The booking panel is a wide, multi-column layout. On small screens the popup always goes full-screen regardless of this value.', 'rox-appointment-booking'),
            ]
        );

        $this->add_control(
            'reset_on_close',
            [
                'label'        => esc_html__('Reset booking on close', 'rox-appointment-booking'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => '',
                'description'  => esc_html__('Off: closing keeps the visitor\'s progress, so reopening returns them to the same step. On: closing starts a fresh booking.', 'rox-appointment-booking'),
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Layout: the panel regions the surface can hide, its font and its frame.
     * Applies to both modes.
     *
     * @return void
     */
    protected function registerLayoutSection(): void
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

        // How many service cards sit side by side on the Services step. The
        // panel keeps it one-per-row on narrow screens whatever this says.
        $this->add_control(
            'service_columns',
            [
                'label'       => esc_html__('Service columns', 'rox-appointment-booking'),
                'type'        => Controls_Manager::SLIDER,
                'range'       => ['px' => ['min' => 1, 'max' => 2, 'step' => 1]],
                'default'     => ['size' => 2],
                'description' => esc_html__('Number of service cards per row on the Services step. Always one per row on narrow screens.', 'rox-appointment-booking'),
            ]
        );

        // Opt-in, and left alone by default: the heading sits where it always
        // has unless an editor says otherwise. Not conditional on the layout
        // switches — a heading can be worth moving either way.
        $this->add_control(
            'heading_align',
            [
                'label'       => esc_html__('Heading alignment', 'rox-appointment-booking'),
                'type'        => Controls_Manager::SELECT,
                'default'     => '',
                'options'     => [
                    ''       => esc_html__('Default (left)', 'rox-appointment-booking'),
                    'left'   => esc_html__('Left', 'rox-appointment-booking'),
                    'center' => esc_html__('Center', 'rox-appointment-booking'),
                    'right'  => esc_html__('Right', 'rox-appointment-booking'),
                ],
                'description' => esc_html__('The step title above each list — Select Location, Available Category, and so on.', 'rox-appointment-booking'),
            ]
        );

        // The content control below moves the whole column, heading included;
        // this moves the heading alone, which is what sets it apart from the
        // cards under it. Margin rather than padding: the heading has no
        // surface of its own for padding to show on, and a negative value is
        // what pulls it back out of the column.
        $this->add_control(
            'heading_margin',
            [
                'label'      => esc_html__('Heading margin', 'rox-appointment-booking'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px'],
            ]
        );

        // Only once a column is gone: with both in place the panel is full and
        // there is nothing left over to nudge into. Either switch on its own is
        // enough, which is the `conditions` form with an explicit `or` — the
        // plain `condition` key is an AND.
        //
        // Render arguments rather than `selectors`: the popup's panel is built
        // client-side and is not inside {{WRAPPER}}.
        $this->add_control(
            'content_margin',
            [
                'label'       => esc_html__('Content margin', 'rox-appointment-booking'),
                'type'        => Controls_Manager::DIMENSIONS,
                'size_units'  => ['px'],
                'description' => esc_html__('Nudges the step heading and its cards, which sit centred in the room the hidden column freed up.', 'rox-appointment-booking'),
                'conditions'  => self::HIDDEN_COLUMN_CONDITION,
            ]
        );


        $this->add_control(
            'font_family',
            [
                'label'       => esc_html__('Font family', 'rox-appointment-booking'),
                'type'        => Controls_Manager::SELECT,
                'options'     => FontFamily::selectOptions(),
                'default'     => '',
                'label_block' => true,
                'description' => esc_html__('Applies to the whole panel. Pick "Theme font" to let it inherit the font of the page it sits on.', 'rox-appointment-booking'),
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
    }

    /**
     * Availability: which locations and categories the visitor is offered.
     * Applies to both modes.
     *
     * @return void
     */
    protected function registerAvailabilitySection(): void
    {
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
     * Button style: Elementor's native typography / colour / spacing controls
     * for the popup trigger.
     *
     * Colours are written as the `--rox-btn-*` custom properties the button
     * stylesheet reads, not as plain `color` / `background-color` declarations.
     * That is what lets the hover colours work — the stylesheet owns the
     * `:hover` rule — and it keeps Elementor's generated CSS from having to
     * out-specify the block's own rules for the same property.
     *
     * @return void
     */
    protected function registerButtonStyleSection(): void
    {
        $this->start_controls_section(
            'button_style_section',
            [
                'label'     => esc_html__('Button', 'rox-appointment-booking'),
                'tab'       => Controls_Manager::TAB_STYLE,
                'condition' => ['display_mode' => 'popup'],
            ]
        );

        $this->add_control(
            'button_style',
            [
                'label'   => esc_html__('Variant', 'rox-appointment-booking'),
                'type'    => Controls_Manager::SELECT,
                'default' => 'filled',
                'options' => [
                    'filled'  => esc_html__('Filled', 'rox-appointment-booking'),
                    'outline' => esc_html__('Outline', 'rox-appointment-booking'),
                    'link'    => esc_html__('Link', 'rox-appointment-booking'),
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'button_typography',
                'selector' => '{{WRAPPER}} .rox-booking-button',
            ]
        );

        // --- Colours ---------------------------------------------------------

        $this->start_controls_tabs('button_colors');

        $this->start_controls_tab(
            'button_colors_normal',
            ['label' => esc_html__('Normal', 'rox-appointment-booking')]
        );

        $this->add_control(
            'button_text_color',
            [
                'label'     => esc_html__('Text color', 'rox-appointment-booking'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rox-booking-button' => '--rox-btn-fg: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_bg_color',
            [
                'label'     => esc_html__('Background color', 'rox-appointment-booking'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rox-booking-button' => '--rox-btn-bg: {{VALUE}};',
                ],
                // Neither other variant paints a fill, so the control would do
                // nothing there.
                'condition' => ['button_style' => 'filled'],
            ]
        );

        $this->add_control(
            'button_border_color',
            [
                'label'     => esc_html__('Border color', 'rox-appointment-booking'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rox-booking-button' => '--rox-btn-border: {{VALUE}};',
                ],
                'condition' => ['button_style!' => 'link'],
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'button_colors_hover',
            ['label' => esc_html__('Hover', 'rox-appointment-booking')]
        );

        // Each hover colour also switches off the stylesheet's default
        // brightness shift, which would otherwise tint the chosen colour on top
        // of itself.
        $this->add_control(
            'button_text_color_hover',
            [
                'label'     => esc_html__('Text color', 'rox-appointment-booking'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rox-booking-button' => '--rox-btn-fg-hover: {{VALUE}}; --rox-btn-hover-filter: none;',
                ],
            ]
        );

        $this->add_control(
            'button_bg_color_hover',
            [
                'label'     => esc_html__('Background color', 'rox-appointment-booking'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rox-booking-button' => '--rox-btn-bg-hover: {{VALUE}}; --rox-btn-hover-filter: none;',
                ],
                'condition' => ['button_style' => 'filled'],
            ]
        );

        $this->add_control(
            'button_border_color_hover',
            [
                'label'     => esc_html__('Border color', 'rox-appointment-booking'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rox-booking-button' => '--rox-btn-border-hover: {{VALUE}}; --rox-btn-hover-filter: none;',
                ],
                'condition' => ['button_style!' => 'link'],
            ]
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_control(
            'button_colors_help',
            [
                'type'            => Controls_Manager::RAW_HTML,
                'raw'             => esc_html__('Leave the hover colors empty to keep the default: the button simply brightens on hover.', 'rox-appointment-booking'),
                'content_classes' => 'elementor-descriptor',
            ]
        );

        // --- Border ----------------------------------------------------------
        // Written out rather than using Group_Control_Border: that group sets
        // `border-color` directly, which would bypass the `--rox-btn-border`
        // variable the hover rule reads and leave hover borders broken.

        $this->add_control(
            'button_border_heading',
            [
                'label'     => esc_html__('Border', 'rox-appointment-booking'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
                'condition' => ['button_style!' => 'link'],
            ]
        );

        $this->add_control(
            'button_border_style',
            [
                'label'     => esc_html__('Border style', 'rox-appointment-booking'),
                'type'      => Controls_Manager::SELECT,
                'default'   => 'solid',
                'options'   => [
                    'none'   => esc_html__('None', 'rox-appointment-booking'),
                    'solid'  => esc_html__('Solid', 'rox-appointment-booking'),
                    'dashed' => esc_html__('Dashed', 'rox-appointment-booking'),
                    'dotted' => esc_html__('Dotted', 'rox-appointment-booking'),
                    'double' => esc_html__('Double', 'rox-appointment-booking'),
                ],
                'selectors' => [
                    '{{WRAPPER}} .rox-booking-button' => 'border-style: {{VALUE}};',
                ],
                'condition' => ['button_style!' => 'link'],
            ]
        );

        $this->add_responsive_control(
            'button_border_width',
            [
                'label'      => esc_html__('Border width', 'rox-appointment-booking'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px'],
                'selectors'  => [
                    '{{WRAPPER}} .rox-booking-button' => 'border-top-width: {{TOP}}{{UNIT}}; border-right-width: {{RIGHT}}{{UNIT}}; border-bottom-width: {{BOTTOM}}{{UNIT}}; border-left-width: {{LEFT}}{{UNIT}};',
                ],
                'condition'  => [
                    'button_style!'        => 'link',
                    'button_border_style!' => 'none',
                ],
            ]
        );

        // Not responsive: this is the odd case of a border that appears or
        // thickens on hover, and that reads the same on every screen.
        $this->add_control(
            'button_border_width_hover',
            [
                'label'      => esc_html__('Border width — hover', 'rox-appointment-booking'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px'],
                'selectors'  => [
                    '{{WRAPPER}} .rox-booking-button:hover' => 'border-top-width: {{TOP}}{{UNIT}}; border-right-width: {{RIGHT}}{{UNIT}}; border-bottom-width: {{BOTTOM}}{{UNIT}}; border-left-width: {{LEFT}}{{UNIT}};',
                ],
                'condition'  => [
                    'button_style!'        => 'link',
                    'button_border_style!' => 'none',
                ],
            ]
        );

        $this->add_responsive_control(
            'button_border_radius',
            [
                'label'      => esc_html__('Border radius', 'rox-appointment-booking'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors'  => [
                    '{{WRAPPER}} .rox-booking-button' => 'border-top-left-radius: {{TOP}}{{UNIT}}; border-top-right-radius: {{RIGHT}}{{UNIT}}; border-bottom-right-radius: {{BOTTOM}}{{UNIT}}; border-bottom-left-radius: {{LEFT}}{{UNIT}};',
                ],
                'condition'  => ['button_style!' => 'link'],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name'     => 'button_box_shadow',
                'selector' => '{{WRAPPER}} .rox-booking-button',
            ]
        );

        // The stylesheet resets the shadow on :hover so a theme cannot leave
        // one behind, which means a hover shadow has to be stated outright
        // rather than inherited from the resting one.
        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name'     => 'button_box_shadow_hover',
                'selector' => '{{WRAPPER}} .rox-booking-button:hover',
            ]
        );

        // --- Size ------------------------------------------------------------

        // On top of the Width choice in the Content tab rather than instead of
        // it: left unset that choice decides, set this wins — which is what
        // makes it useful for lining a button up with something beside it.
        $this->add_responsive_control(
            'button_width_size',
            [
                'label'      => esc_html__('Width', 'rox-appointment-booking'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range'      => [
                    'px' => ['min' => 60, 'max' => 800],
                    '%'  => ['min' => 10, 'max' => 100],
                ],
                'selectors'  => [
                    '{{WRAPPER}} .rox-booking-button' => 'width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        // --- Icon ------------------------------------------------------------

        $this->add_control(
            'button_icon_heading',
            [
                'label'     => esc_html__('Icon', 'rox-appointment-booking'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
                'condition' => ['button_icon!' => 'none'],
            ]
        );

        $this->add_responsive_control(
            'button_icon_size',
            [
                'label'      => esc_html__('Icon size', 'rox-appointment-booking'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 8, 'max' => 64]],
                'selectors'  => [
                    '{{WRAPPER}} .rox-booking-button__icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ],
                'condition'  => ['button_icon!' => 'none'],
            ]
        );

        $this->add_responsive_control(
            'button_icon_gap',
            [
                'label'      => esc_html__('Space between', 'rox-appointment-booking'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 60]],
                'selectors'  => [
                    '{{WRAPPER}} .rox-booking-button' => 'gap: {{SIZE}}{{UNIT}};',
                ],
                'condition'  => ['button_icon!' => 'none'],
            ]
        );

        // An icon's visual centre rarely lands on the text baseline, and a
        // pixel either way is what settles it.
        $this->add_responsive_control(
            'button_icon_offset_y',
            [
                'label'      => esc_html__('Move icon vertically', 'rox-appointment-booking'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => -20, 'max' => 20]],
                'selectors'  => [
                    '{{WRAPPER}} .rox-booking-button__icon' => 'transform: translateY({{SIZE}}{{UNIT}});',
                ],
                'condition'  => ['button_icon!' => 'none'],
            ]
        );

        // --- Spacing ---------------------------------------------------------

        $this->add_control(
            'button_spacing_heading',
            [
                'label'     => esc_html__('Spacing', 'rox-appointment-booking'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_responsive_control(
            'button_padding',
            [
                'label'      => esc_html__('Padding', 'rox-appointment-booking'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors'  => [
                    '{{WRAPPER}} .rox-booking-button' => 'padding-top: {{TOP}}{{UNIT}}; padding-right: {{RIGHT}}{{UNIT}}; padding-bottom: {{BOTTOM}}{{UNIT}}; padding-left: {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'button_margin',
            [
                'label'      => esc_html__('Margin', 'rox-appointment-booking'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                // Allows pulling the button up into whatever sits above it.
                'allowed_dimensions' => 'all',
                'selectors'  => [
                    '{{WRAPPER}} .rox-booking-button' => 'margin-top: {{TOP}}{{UNIT}}; margin-right: {{RIGHT}}{{UNIT}}; margin-bottom: {{BOTTOM}}{{UNIT}}; margin-left: {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'button_wrap_margin',
            [
                'label'      => esc_html__('Outer margin', 'rox-appointment-booking'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors'  => [
                    '{{WRAPPER}} .rox-booking-button-wrap' => 'margin-top: {{TOP}}{{UNIT}}; margin-right: {{RIGHT}}{{UNIT}}; margin-bottom: {{BOTTOM}}{{UNIT}}; margin-left: {{LEFT}}{{UNIT}};',
                ],
                'description' => esc_html__('Spacing around the whole block, outside the button itself.', 'rox-appointment-booking'),
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Registers the Style-tab section that recolours the whole panel.
     *
     * Written as a `--rox-accent` custom property on the wrapper rather than as
     * a direct declaration: the panel is rendered by React and only its own
     * stylesheet can reach the accent surfaces. That stylesheet derives every
     * lighter shade and translucent wash from this one variable, and falls back
     * to the shipped blue when it is unset, so an untouched control changes
     * nothing.
     *
     * A CSS variable is also the only form an Elementor Global Color survives:
     * globals are resolved when the CSS is generated, never in
     * get_settings_for_display(), so a `selectors` entry is what lets the panel
     * follow a site colour instead of a literal one — in popup mode too, which
     * is why the modal's panel is written to as a second selector.
     *
     * @return void
     */
    protected function registerColorSection(): void
    {
        $this->start_controls_section(
            'panel_colors',
            [
                'label' => esc_html__('Panel color', 'rox-appointment-booking'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'accent_color',
            [
                'label'       => esc_html__('Panel color', 'rox-appointment-booking'),
                'type'        => Controls_Manager::COLOR,
                'default'     => '',
                'selectors'   => [
                    '{{WRAPPER}}' => '--rox-accent: {{VALUE}};',
                    BookingButtonMarkup::PANEL_OWNER_SELECTOR => '--rox-accent: {{VALUE}};',
                ],
                'description' => esc_html__('Recolours every accent surface at once — the Next button, the active step markers, selected cards and time slots, links and focus rings. Pick a site colour with the globe icon to follow the theme, or leave it empty to keep the panel default.', 'rox-appointment-booking'),
            ]
        );

        $this->add_control(
            'accent_color_help',
            [
                'type'            => Controls_Manager::RAW_HTML,
                'raw'             => esc_html__('The Back and Next button sections below override this for those two buttons.', 'rox-appointment-booking'),
                'content_classes' => 'elementor-descriptor',
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Icon choices for the button, driven by the icon set rather than a list
     * kept here: an icon added to the JSON shows up without a second edit.
     *
     * The labels are spelled out so they can be translated — a name derived
     * from the JSON key could not be — and any key without one falls back to
     * a readable version of the key itself.
     *
     * @return array<string, string>
     */
    protected function getIconOptions(): array
    {
        $labels = [
            'none'         => esc_html__('None', 'rox-appointment-booking'),
            'calendar'     => esc_html__('Calendar', 'rox-appointment-booking'),
            'clock'        => esc_html__('Clock', 'rox-appointment-booking'),
            'user'         => esc_html__('User', 'rox-appointment-booking'),
            'users'        => esc_html__('Users', 'rox-appointment-booking'),
            'phone'        => esc_html__('Phone', 'rox-appointment-booking'),
            'mail'         => esc_html__('Mail', 'rox-appointment-booking'),
            'chat'         => esc_html__('Chat', 'rox-appointment-booking'),
            'check'        => esc_html__('Check', 'rox-appointment-booking'),
            'check-circle' => esc_html__('Check circle', 'rox-appointment-booking'),
            'star'         => esc_html__('Star', 'rox-appointment-booking'),
            'heart'        => esc_html__('Heart', 'rox-appointment-booking'),
            'location'     => esc_html__('Location', 'rox-appointment-booking'),
            'scissors'     => esc_html__('Scissors', 'rox-appointment-booking'),
            'ticket'       => esc_html__('Ticket', 'rox-appointment-booking'),
            'bell'         => esc_html__('Bell', 'rox-appointment-booking'),
            'arrow-right'  => esc_html__('Arrow', 'rox-appointment-booking'),
            'plus'         => esc_html__('Plus', 'rox-appointment-booking'),
            'sparkle'      => esc_html__('Sparkle', 'rox-appointment-booking'),
        ];

        $options = [];

        foreach (BookingButtonMarkup::iconKeys() as $key) {
            $options[$key] = $labels[$key] ?? ucfirst(str_replace('-', ' ', $key));
        }

        return $options;
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
     * Renders the widget for the mode it is set to.
     *
     * @return void
     */
    protected function render(): void
    {
        $settings = $this->get_settings_for_display();

        // Anything but an explicit "popup" is the inline panel, so a widget
        // saved before the mode existed keeps rendering exactly as it did.
        if (($settings['display_mode'] ?? '') === 'popup') {
            $this->renderPopup($settings);
            return;
        }

        $this->renderInline($settings);
    }

    /**
     * Server-renders the same root node the shortcode uses, which the shared
     * frontend bundle mounts the booking panel on.
     *
     * @param array $settings Resolved widget settings.
     * @return void
     */
    protected function renderInline(array $settings): void
    {
        $hide_navigation = (($settings['hide_navigation'] ?? '') === 'yes') ? 'true' : 'false';
        $hide_info       = (($settings['hide_info'] ?? '') === 'yes') ? 'true' : 'false';

        // Frame switch defaults to on, unlike the two hide toggles above.
        $show_background  = $this->showBackground($settings) ? 'true' : 'false';
        $background_color = Color::sanitize((string) ($settings['background_color'] ?? ''));

        // The panel is handed a resolved CSS stack, never the stored key, so an
        // unknown key falls back to the stylesheet's own font. The URL rides
        // along because the editor re-renders this markup over AJAX, where an
        // enqueue reaches nothing — the handler script loads it from here.
        $font_key    = (string) ($settings['font_family'] ?? '');
        $font_family = FontFamily::stack($font_key);
        $font_url    = FontFamily::googleUrl($font_key);
        FontFamily::enqueue($font_key);

        // Optional "only offer these" picks. Elementor hands SELECT2 values
        // back as strings; an empty list means no restriction.
        $location_ids = IdList::toAttr($settings['location_ids'] ?? []);
        $category_ids = IdList::toAttr($settings['category_ids'] ?? []);

        printf(
            // The instance id decides which store the panel gets, so two of
            // these widgets on one page keep their selections apart. Elementor's
            // element id is unique per widget and stable across renders.
            '<div class="rox-appointment-booking-frontend-root" data-instance="%7$s" data-type="booking-form" data-hide-navigation="%1$s" data-hide-info="%2$s" data-service-columns="%14$s" data-show-dashboard-button="%15$s" data-dashboard-button-text="%16$s" data-dashboard-button-url="%17$s" data-show-background="%3$s" data-background-color="%4$s" data-locations="%5$s" data-categories="%6$s" data-font-family="%8$s" data-font-url="%9$s" data-content-margin="%10$s" data-heading-align="%11$s" data-heading-margin="%12$s" data-panel-content="%13$s"></div>',
            esc_attr($hide_navigation),
            esc_attr($hide_info),
            esc_attr($show_background),
            esc_attr($background_color),
            esc_attr($location_ids),
            esc_attr($category_ids),
            esc_attr((string) $this->get_id()),
            esc_attr($font_family),
            esc_url($font_url),
            esc_attr(BookingButtonMarkup::contentSpacing($this->dimensions($settings, 'content_margin'), true)),
            esc_attr($this->headingAlign($settings)),
            esc_attr(BookingButtonMarkup::contentSpacing($this->dimensions($settings, 'heading_margin'), true)),
            // The panel's own copy, as far as the editor rewrote it. Empty when
            // every field was left alone, which keeps the panel's own wording.
            esc_attr(PanelContent::toAttr($this->panelContent($settings))),
            esc_attr((string) $this->serviceColumns($settings)),
            esc_attr(($settings['show_dashboard_button'] ?? 'yes') === 'yes' ? 'true' : 'false'),
            esc_attr((string) ($settings['dashboard_button_text'] ?? '')),
            esc_attr(esc_url_raw((string) ($settings['dashboard_button_url'] ?? '')))
        );
    }

    /**
     * Where a step's heading sits, validated against the whitelist.
     *
     * Empty is the default and the answer for anything unrecognised: the
     * value reaches the stylesheet as a class name, and Elementor validates
     * nothing on the way in.
     *
     * @param array $settings Resolved widget settings.
     * @return string
     */
    protected function headingAlign(array $settings): string
    {
        $value = (string) ($settings['heading_align'] ?? '');

        return in_array($value, BookingButtonMarkup::HEADING_ALIGNS, true) ? $value : '';
    }

    /**
     * Service cards per row on the Services step, clamped to the 1-2 the panel
     * supports. The SLIDER control stores `{size, unit}`; the markup builder
     * unwraps and clamps it.
     *
     * @param array $settings Resolved widget settings.
     * @return int
     */
    protected function serviceColumns(array $settings): int
    {
        return BookingButtonMarkup::serviceColumns($settings['service_columns'] ?? 2);
    }

    /**
     * Collects the copy controls back into the override map the panel reads.
     *
     * Elementor stores each control on its own, so the flat settings are folded
     * back into the shape {@see PanelContent} normalises — which is also what
     * drops a field left on the panel's own wording.
     *
     * @param array $settings Resolved widget settings.
     * @return array
     */
    protected function panelContent(array $settings): array
    {
        $content = [];

        foreach (self::PANEL_CONTENT_TEXT as $control => $key) {
            $content[$key] = $settings[$control] ?? '';
        }

        // MEDIA hands back an id / url pair; only the URL reaches the panel.
        $image = $settings['panel_sidebar_image'] ?? [];
        $content['sidebarImage'] = is_array($image) ? ($image['url'] ?? '') : '';

        // SLIDER keeps the number apart from its unit, and the panel only ever
        // reads pixels, so the size is all that travels.
        $size = $settings['panel_sidebar_image_width'] ?? [];
        $content['sidebarImageWidth'] = is_array($size) ? ($size['size'] ?? '') : $size;

        return $content;
    }

    /**
     * Reads an Elementor DIMENSIONS control as the four-sided map the markup
     * builder expects.
     *
     * Elementor keeps the unit apart from the numbers and leaves a side that
     * was never filled in as an empty string, so each side is stitched back
     * together here and the blank ones are simply left out.
     *
     * @param array  $settings Resolved widget settings.
     * @param string $key      Control name.
     * @return array
     */
    protected function dimensions(array $settings, string $key): array
    {
        $raw = $settings[$key] ?? [];

        if (!is_array($raw)) {
            return [];
        }

        $unit  = (string) ($raw['unit'] ?? 'px');
        $sides = [];

        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $value = $raw[$side] ?? '';

            if ($value === '' || $value === null) {
                continue;
            }

            $sides[$side] = $value . $unit;
        }

        return $sides;
    }

    /**
     * Server-renders the popup trigger. No panel root is emitted: the modal and
     * the panel inside it are built by the view bundle on the first click, so
     * only the settings that panel will need travel here.
     *
     * @param array $settings Resolved widget settings.
     * @return void
     */
    protected function renderPopup(array $settings): void
    {
        $width = ($settings['button_width'] ?? 'auto') === 'full' ? 'full' : 'auto';
        // The alignment itself is applied by the control's CSS, not by the
        // markup; this only decides the wrapper's fallback.
        $align = (string) ($settings['button_align'] ?? 'left');

        $font_key = (string) ($settings['font_family'] ?? '');
        FontFamily::enqueue($font_key);

        // Escaped inside BookingButtonMarkup::render(), which builds every
        // attribute through esc_attr() / esc_html().
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo BookingButtonMarkup::render([
            'text'                 => (string) ($settings['button_text'] ?? ''),
            'align'                => $align,
            'width'                => $width,
            'size'                 => (string) ($settings['button_size'] ?? 'medium'),
            'style'                => (string) ($settings['button_style'] ?? 'filled'),
            'icon'                 => (string) ($settings['button_icon'] ?? 'calendar'),
            'modalWidth'           => (int) ($settings['modal_width'] ?? 1100),
            'hideNavigation'       => ($settings['hide_navigation'] ?? '') === 'yes',
            'hideInfo'             => ($settings['hide_info'] ?? '') === 'yes',
            'serviceColumns'       => $this->serviceColumns($settings),
            'showDashboardButton'  => ($settings['show_dashboard_button'] ?? 'yes') === 'yes',
            'dashboardButtonText'  => (string) ($settings['dashboard_button_text'] ?? ''),
            'dashboardButtonUrl'   => (string) ($settings['dashboard_button_url'] ?? ''),
            'contentMargin'        => $this->dimensions($settings, 'content_margin'),
            'headingAlign'         => $this->headingAlign($settings),
            'headingMargin'        => $this->dimensions($settings, 'heading_margin'),
            'panelContent'         => $this->panelContent($settings),
            // Elementor hands SELECT2 values back as strings; IdList normalises.
            'locationIds'          => $settings['location_ids'] ?? [],
            'categoryIds'          => $settings['category_ids'] ?? [],
            'resetOnClose'         => ($settings['reset_on_close'] ?? '') === 'yes',
            // Panel settings that are data rather than style, so they travel on
            // the trigger for the modal to copy onto its mount node.
            'showPanelBackground'  => $this->showBackground($settings),
            'panelBackgroundColor' => (string) ($settings['background_color'] ?? ''),
            'fontFamily'           => FontFamily::stack($font_key),
            // The panel's own colours stay in Elementor's generated CSS, aimed
            // at the modal through this id — that is what keeps Global Colours
            // and per-breakpoint values working there.
            'panelOwner'           => (string) $this->get_id(),
            // Colours and radius come from the Style tab as a stylesheet rule;
            // an inline style would beat it.
            'inlineStyle'          => false,
        ]);
    }

    /**
     * Whether the panel draws its own grey frame. Defaults to on, so only an
     * explicit "off" switches it off.
     *
     * @param array $settings Resolved widget settings.
     * @return bool
     */
    protected function showBackground(array $settings): bool
    {
        return ($settings['show_background'] ?? 'yes') === 'yes';
    }
}
