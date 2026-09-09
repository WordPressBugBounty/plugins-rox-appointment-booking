<?php

/**
 * Class LoginFormWidget
 *
 * @package RoxAppointmentBooking
 * @subpackage Modules\Elementor\Widgets
 * @since 1.0.0
 *
 * Elementor widget that renders the same standalone login form as the
 * `[rox_appointment_login]` shortcode and the "Rox Appointment Login Form"
 * Gutenberg block. It depends on the shared login-form view bundle registered by
 * the module Provider and renders the same
 * `rox-appointment-booking-login-form-root` mount node, with the config built by
 * `LoginForm\Services\LoginFormConfig` — the single home for that logic.
 *
 * Because Elementor loads the declared frontend scripts inside its editor
 * preview iframe, the real form renders live in the editor — no separate editor
 * bundle is needed.
 */

namespace RoxAppointmentBooking\Modules\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use RoxAppointmentBooking\Modules\Elementor\Provider;
use RoxAppointmentBooking\Modules\LoginForm\Services\LoginFormConfig;
use RoxAppointmentBooking\Modules\LoginForm\Services\LoginFormShortcode;

if (! defined('ABSPATH')) exit; // Exit if accessed directly

class LoginFormWidget extends Widget_Base
{
    /**
     * Per-request counter so every widget on a page gets a unique instance id.
     *
     * @var int
     */
    protected static int $instance_count = 0;

    /**
     * Widget machine name. Must match the handle the login-form Elementor
     * handler hooks (`frontend/element_ready/<name>.default`).
     *
     * @return string
     */
    public function get_name(): string
    {
        return 'rox-appointment-login-form';
    }

    /**
     * Widget title shown in the Elementor panel.
     *
     * @return string
     */
    public function get_title(): string
    {
        return esc_html__('Rox Appointment Login Form', 'rox-appointment-booking');
    }

    /**
     * Widget icon (Elementor icon font).
     *
     * @return string
     */
    public function get_icon(): string
    {
        return 'eicon-lock-user';
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
        return ['login', 'customer', 'agent', 'account', 'rox'];
    }

    /**
     * Frontend scripts this widget depends on.
     *
     * @return string[]
     */
    public function get_script_depends(): array
    {
        return [LoginFormShortcode::VIEW_HANDLE, Provider::LOGIN_FORM_HANDLER_HANDLE];
    }

    /**
     * Frontend styles this widget depends on. The view style is registered with
     * the shared vendors style as a dependency, so depending on the view handle
     * pulls both in.
     *
     * @return string[]
     */
    public function get_style_depends(): array
    {
        return [LoginFormShortcode::VIEW_STYLE_HANDLE];
    }

    /**
     * CSS selector every style control writes its `--rlf-*` custom property to.
     * Elementor generates the rule, so no PHP var building is needed here (the
     * Gutenberg block has to build the same set by hand because blocks have no
     * equivalent mechanism).
     */
    protected const ROOT_SELECTOR = '{{WRAPPER}} .rox-appointment-booking-login-form-root';

    /**
     * Registers the widget controls: one Content section plus the Style sections
     * mirroring the Gutenberg block's Inspector panels one-for-one.
     *
     * @return void
     */
    protected function register_controls(): void
    {
        $this->registerContentSection();
        $this->registerContainerSection();
        $this->registerFieldsSection();
        $this->registerButtonSection();
        $this->registerLinksSection();
        $this->registerMessagesSection();
    }

    /**
     * Content section: behaviour, not styling.
     *
     * @return void
     */
    protected function registerContentSection(): void
    {
        $this->start_controls_section(
            'section_content',
            ['label' => esc_html__('Content', 'rox-appointment-booking')]
        );

        $this->add_control(
            'redirect_url',
            [
                'label'       => esc_html__('Redirect after login (URL)', 'rox-appointment-booking'),
                'type'        => Controls_Manager::TEXT,
                'default'     => '',
                'description' => esc_html__('Leave empty to send booking agents and customers to their dashboard. Everyone else stays on this page.', 'rox-appointment-booking'),
            ]
        );

        $this->add_control(
            'login_label',
            [
                'label'   => esc_html__('Login button label', 'rox-appointment-booking'),
                'type'    => Controls_Manager::TEXT,
                'default' => '',
                'placeholder' => esc_html__('Login', 'rox-appointment-booking'),
            ]
        );

        $this->add_control(
            'show_google',
            [
                'label'        => esc_html__('Show "Sign in with Google"', 'rox-appointment-booking'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
                'description'  => esc_html__('Only has an effect when Google login is enabled in the Pro integration settings.', 'rox-appointment-booking'),
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Container style section.
     *
     * @return void
     */
    protected function registerContainerSection(): void
    {
        $this->start_controls_section(
            'section_container',
            [
                'label' => esc_html__('Container', 'rox-appointment-booking'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'form_width',
            [
                'label'      => esc_html__('Width', 'rox-appointment-booking'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range'      => [
                    'px' => ['min' => 200, 'max' => 900],
                    '%'  => ['min' => 10, 'max' => 100],
                ],
                'selectors'  => [
                    self::ROOT_SELECTOR => '--rlf-width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        // Alignment is flex justification on the widget wrapper, so it never
        // collides with the container's own Margin control below.
        $this->add_responsive_control(
            'form_align',
            [
                'label'     => esc_html__('Alignment', 'rox-appointment-booking'),
                'type'      => Controls_Manager::CHOOSE,
                'options'   => [
                    'flex-start' => [
                        'title' => esc_html__('Left', 'rox-appointment-booking'),
                        'icon'  => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => esc_html__('Center', 'rox-appointment-booking'),
                        'icon'  => 'eicon-text-align-center',
                    ],
                    'flex-end' => [
                        'title' => esc_html__('Right', 'rox-appointment-booking'),
                        'icon'  => 'eicon-text-align-right',
                    ],
                ],
                'default'   => 'flex-start',
                'selectors' => [
                    '{{WRAPPER}}' => 'display: flex; justify-content: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'form_margin',
            [
                'label'      => esc_html__('Margin', 'rox-appointment-booking'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors'  => [
                    self::ROOT_SELECTOR => '--rlf-margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'form_padding',
            [
                'label'      => esc_html__('Padding', 'rox-appointment-booking'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors'  => [
                    self::ROOT_SELECTOR => '--rlf-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'form_bg',
            [
                'label'     => esc_html__('Background', 'rox-appointment-booking'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    self::ROOT_SELECTOR => '--rlf-bg: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'border_width',
            [
                'label'      => esc_html__('Border width', 'rox-appointment-booking'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 20]],
                'selectors'  => [
                    self::ROOT_SELECTOR => '--rlf-border-width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'border_style',
            [
                'label'   => esc_html__('Border style', 'rox-appointment-booking'),
                'type'    => Controls_Manager::SELECT,
                'default' => 'solid',
                'options' => [
                    'solid'  => esc_html__('Solid', 'rox-appointment-booking'),
                    'dashed' => esc_html__('Dashed', 'rox-appointment-booking'),
                    'dotted' => esc_html__('Dotted', 'rox-appointment-booking'),
                    'none'   => esc_html__('None', 'rox-appointment-booking'),
                ],
                'selectors' => [
                    self::ROOT_SELECTOR => '--rlf-border-style: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'border_color',
            [
                'label'     => esc_html__('Border color', 'rox-appointment-booking'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    self::ROOT_SELECTOR => '--rlf-border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'border_radius',
            [
                'label'      => esc_html__('Border radius', 'rox-appointment-booking'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range'      => ['px' => ['min' => 0, 'max' => 60]],
                'selectors'  => [
                    self::ROOT_SELECTOR => '--rlf-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Fields style section.
     *
     * @return void
     */
    protected function registerFieldsSection(): void
    {
        $this->start_controls_section(
            'section_fields',
            [
                'label' => esc_html__('Fields', 'rox-appointment-booking'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        // Labels are literal calls so WordPress's translation scanner can find
        // them; only the pairing with the CSS var is data.
        $this->addColorControls([
            'label_color'        => [esc_html__('Label', 'rox-appointment-booking'), '--rlf-label'],
            'input_color'        => [esc_html__('Input text', 'rox-appointment-booking'), '--rlf-input-color'],
            'input_bg'           => [esc_html__('Input background', 'rox-appointment-booking'), '--rlf-input-bg'],
            'input_border'       => [esc_html__('Input border', 'rox-appointment-booking'), '--rlf-input-border'],
            'input_focus_border' => [esc_html__('Input focus border', 'rox-appointment-booking'), '--rlf-input-focus-border'],
        ]);

        $this->add_control(
            'input_radius',
            [
                'label'      => esc_html__('Input border radius', 'rox-appointment-booking'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range'      => ['px' => ['min' => 0, 'max' => 40]],
                'selectors'  => [
                    self::ROOT_SELECTOR => '--rlf-input-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Login button style section.
     *
     * @return void
     */
    protected function registerButtonSection(): void
    {
        $this->start_controls_section(
            'section_button',
            [
                'label' => esc_html__('Login button', 'rox-appointment-booking'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->addColorControls([
            'btn_bg'          => [esc_html__('Background', 'rox-appointment-booking'), '--rlf-btn-bg'],
            'btn_color'       => [esc_html__('Text', 'rox-appointment-booking'), '--rlf-btn-color'],
            'btn_hover_bg'    => [esc_html__('Hover background', 'rox-appointment-booking'), '--rlf-btn-hover-bg'],
            'btn_hover_color' => [esc_html__('Hover text', 'rox-appointment-booking'), '--rlf-btn-hover-color'],
        ]);

        $this->add_control(
            'btn_radius',
            [
                'label'      => esc_html__('Border radius', 'rox-appointment-booking'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range'      => ['px' => ['min' => 0, 'max' => 40]],
                'selectors'  => [
                    self::ROOT_SELECTOR => '--rlf-btn-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'btn_margin',
            [
                'label'      => esc_html__('Margin', 'rox-appointment-booking'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors'  => [
                    self::ROOT_SELECTOR => '--rlf-btn-margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'btn_padding',
            [
                'label'      => esc_html__('Padding', 'rox-appointment-booking'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors'  => [
                    self::ROOT_SELECTOR => '--rlf-btn-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Links style section.
     *
     * @return void
     */
    protected function registerLinksSection(): void
    {
        $this->start_controls_section(
            'section_links',
            [
                'label' => esc_html__('Links', 'rox-appointment-booking'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'link_color',
            [
                'label'     => esc_html__('Forgot password link', 'rox-appointment-booking'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    self::ROOT_SELECTOR => '--rlf-link: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'link_hover_color',
            [
                'label'     => esc_html__('Hover', 'rox-appointment-booking'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    self::ROOT_SELECTOR => '--rlf-link-hover: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Message-box style section (login errors, reset success/failure).
     *
     * @return void
     */
    protected function registerMessagesSection(): void
    {
        $this->start_controls_section(
            'section_messages',
            [
                'label' => esc_html__('Messages', 'rox-appointment-booking'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->addColorControls([
            'error_color'   => [esc_html__('Error text', 'rox-appointment-booking'), '--rlf-error-color'],
            'error_bg'      => [esc_html__('Error background', 'rox-appointment-booking'), '--rlf-error-bg'],
            'success_color' => [esc_html__('Success text', 'rox-appointment-booking'), '--rlf-success-color'],
            'success_bg'    => [esc_html__('Success background', 'rox-appointment-booking'), '--rlf-success-bg'],
        ]);

        $this->end_controls_section();
    }

    /**
     * Whether this render is happening inside the Elementor editor — either the
     * editor itself or its preview iframe.
     *
     * @return bool
     */
    protected function isElementorEditor(): bool
    {
        if (!class_exists('\Elementor\Plugin')) {
            return false;
        }

        $elementor = \Elementor\Plugin::$instance;

        return (isset($elementor->editor) && $elementor->editor->is_edit_mode())
            || (isset($elementor->preview) && $elementor->preview->is_preview_mode());
    }

    /**
     * Adds a run of colour controls that each write one `--rlf-*` custom property
     * on the form root.
     *
     * @param array<string, array{0: string, 1: string}> $controls
     *        Control name => [translated label, CSS custom property].
     * @return void
     */
    protected function addColorControls(array $controls): void
    {
        foreach ($controls as $name => $meta) {
            $this->add_control(
                $name,
                [
                    'label'     => $meta[0],
                    'type'      => Controls_Manager::COLOR,
                    'selectors' => [
                        self::ROOT_SELECTOR => $meta[1] . ': {{VALUE}};',
                    ],
                ]
            );
        }
    }

    /**
     * Server-renders the same root node the shortcode and the block use, which
     * the shared view bundle mounts the login form on.
     *
     * Style controls need no work here: they are written as `--rlf-*` custom
     * properties by Elementor's own generated CSS (see the `selectors` above).
     *
     * @return void
     */
    protected function render(): void
    {
        self::$instance_count++;

        $settings = $this->get_settings_for_display();

        $config = LoginFormConfig::build(
            (string) ($settings['redirect_url'] ?? ''),
            (string) ($settings['login_label'] ?? ''),
            ($settings['show_google'] ?? 'yes') === 'yes'
        );

        // Whoever is building the page is signed in, so the real form would be
        // replaced by the "logged in as …" banner and none of the style controls
        // would be previewable. Force the logged-out view inside the editor; the
        // published page keeps the real behaviour.
        if ($this->isElementorEditor()) {
            $config['isLoggedIn'] = false;
            $config['userEmail']  = '';
            $config['logoutUrl']  = '';
        }

        printf(
            '<div class="rox-appointment-booking-login-form-root" data-instance="%1$s" data-config="%2$s"></div>',
            esc_attr((string) self::$instance_count),
            esc_attr(wp_json_encode($config))
        );
    }
}
