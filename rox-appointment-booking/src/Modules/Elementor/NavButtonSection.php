<?php

/**
 * Class NavButtonSection
 *
 * @package RoxAppointmentBooking
 * @subpackage Modules\Elementor
 * @since 1.0.0
 *
 * Registers the Style-tab controls that restyle the booking panel's Back and
 * Next buttons, for any Elementor widget that renders the panel.
 *
 * A registrar rather than a base class or a trait: the widgets that need it live
 * in two different plugins, and Pro must be able to skip it with a class_exists()
 * check when it sits on a free plugin old enough not to ship this file. Every
 * method it calls is public on Elementor's Controls_Stack.
 */

namespace RoxAppointmentBooking\Modules\Elementor;

use Elementor\Controls_Manager;
use Elementor\Controls_Stack;
use RoxAppointmentBooking\Supports\NavButtons;

if (! defined('ABSPATH')) exit; // Exit if accessed directly

class NavButtonSection
{
    /**
     * Registers both button sections on a widget, in the order the panel shows
     * them.
     *
     * @param Controls_Stack $widget         Widget registering its controls.
     * @param string         $modal_selector Extra selector every rule is also
     *                                       written to, for a widget that can
     *                                       show the panel in a modal — that
     *                                       panel sits outside `{{WRAPPER}}`.
     *                                       Empty leaves the rules as they were.
     * @return void
     */
    public static function register(Controls_Stack $widget, string $modal_selector = ''): void
    {
        self::section(
            $widget,
            'nav_back',
            'back',
            esc_html__('Back button', 'rox-appointment-booking'),
            $modal_selector
        );

        self::section(
            $widget,
            'nav_next',
            'next',
            esc_html__('Next button', 'rox-appointment-booking'),
            $modal_selector
        );
    }

    /**
     * Builds a control's `selectors` map: the wrapper always, plus the modal
     * panel when the widget offers one.
     *
     * @param string $declaration    The CSS the control writes.
     * @param string $modal_selector Extra selector, or '' for none.
     * @return array<string, string>
     */
    protected static function selectors(string $declaration, string $modal_selector): array
    {
        $selectors = ['{{WRAPPER}}' => $declaration];

        if ($modal_selector !== '') {
            $selectors[$modal_selector] = $declaration;
        }

        return $selectors;
    }

    /**
     * Registers one Style-tab section for a navigation button.
     *
     * Everything is written as a `--rox-nav-*` custom property on the wrapper
     * rather than as a direct declaration: the buttons are rendered by React
     * deep inside the panel, and only the panel stylesheet can reach them. Its
     * fallbacks are the current design, so a control left untouched changes
     * nothing.
     *
     * Elementor's own group controls are avoided for the same reason
     * the button section avoids Group_Control_Border — they set the real
     * properties directly, which would bypass the variables the hover rule and
     * the row-height calculation read.
     *
     * @param Controls_Stack $widget         Widget registering its controls.
     * @param string         $id             Control-name prefix, unique per section.
     * @param string         $key            Variable segment (`back` / `next`).
     * @param string         $label          Section label.
     * @param string         $modal_selector Extra selector, or '' for none.
     * @return void
     */
    protected static function section(
        Controls_Stack $widget,
        string $id,
        string $key,
        string $label,
        string $modal_selector = ''
    ): void {
        $var = '--rox-nav-' . $key . '-';

        $widget->start_controls_section(
            $id . '_style',
            [
                'label' => $label,
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        // --- Colours ---------------------------------------------------------

        $widget->start_controls_tabs($id . '_colors');

        $widget->start_controls_tab(
            $id . '_colors_normal',
            ['label' => esc_html__('Normal', 'rox-appointment-booking')]
        );

        $widget->add_control(
            $id . '_text_color',
            [
                'label'     => esc_html__('Text color', 'rox-appointment-booking'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => self::selectors($var . 'fg: {{VALUE}};', $modal_selector),
            ]
        );

        $widget->add_control(
            $id . '_bg_color',
            [
                'label'     => esc_html__('Background color', 'rox-appointment-booking'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => self::selectors($var . 'bg: {{VALUE}};', $modal_selector),
            ]
        );

        $widget->add_control(
            $id . '_border_color',
            [
                'label'       => esc_html__('Border color', 'rox-appointment-booking'),
                'type'        => Controls_Manager::COLOR,
                'selectors'   => self::selectors($var . 'border: {{VALUE}};', $modal_selector),
                'description' => esc_html__('Set a border width below for this to show.', 'rox-appointment-booking'),
            ]
        );

        $widget->end_controls_tab();

        $widget->start_controls_tab(
            $id . '_colors_hover',
            ['label' => esc_html__('Hover', 'rox-appointment-booking')]
        );

        $widget->add_control(
            $id . '_text_color_hover',
            [
                'label'     => esc_html__('Text color', 'rox-appointment-booking'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => self::selectors($var . 'fg-hover: {{VALUE}};', $modal_selector),
            ]
        );

        $widget->add_control(
            $id . '_bg_color_hover',
            [
                'label'     => esc_html__('Background color', 'rox-appointment-booking'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => self::selectors($var . 'bg-hover: {{VALUE}};', $modal_selector),
            ]
        );

        $widget->add_control(
            $id . '_border_color_hover',
            [
                'label'     => esc_html__('Border color', 'rox-appointment-booking'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => self::selectors($var . 'border-hover: {{VALUE}};', $modal_selector),
            ]
        );

        $widget->end_controls_tab();

        $widget->end_controls_tabs();

        $widget->add_control(
            $id . '_colors_help',
            [
                'type'            => Controls_Manager::RAW_HTML,
                'raw'             => esc_html__('Leave a color empty to keep the panel default.', 'rox-appointment-booking'),
                'content_classes' => 'elementor-descriptor',
            ]
        );

        // --- Spacing ---------------------------------------------------------

        $widget->add_control(
            $id . '_spacing_heading',
            [
                'label'     => esc_html__('Spacing', 'rox-appointment-booking'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $widget->add_responsive_control(
            $id . '_padding',
            [
                'label'      => esc_html__('Padding', 'rox-appointment-booking'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', 'rem', '%'],
                'selectors'  => self::selectors(
                    $var . 'pt: {{TOP}}{{UNIT}}; ' . $var . 'pr: {{RIGHT}}{{UNIT}}; '
                        . $var . 'pb: {{BOTTOM}}{{UNIT}}; ' . $var . 'pl: {{LEFT}}{{UNIT}};',
                    $modal_selector
                ),
            ]
        );

        $widget->add_responsive_control(
            $id . '_margin',
            [
                'label'      => esc_html__('Margin', 'rox-appointment-booking'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', 'rem', '%'],
                // Allows pulling the button towards the row's edge.
                'allowed_dimensions' => 'all',
                'selectors'  => self::selectors(
                    $var . 'mt: {{TOP}}{{UNIT}}; ' . $var . 'mr: {{RIGHT}}{{UNIT}}; '
                        . $var . 'mb: {{BOTTOM}}{{UNIT}}; ' . $var . 'ml: {{LEFT}}{{UNIT}};',
                    $modal_selector
                ),
            ]
        );

        // --- Border ----------------------------------------------------------

        $widget->add_control(
            $id . '_border_heading',
            [
                'label'     => esc_html__('Border', 'rox-appointment-booking'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $widget->add_control(
            $id . '_border_style',
            [
                'label'     => esc_html__('Border style', 'rox-appointment-booking'),
                'type'      => Controls_Manager::SELECT,
                'default'   => '',
                'options'   => [
                    ''       => esc_html__('Default', 'rox-appointment-booking'),
                    'none'   => esc_html__('None', 'rox-appointment-booking'),
                    'solid'  => esc_html__('Solid', 'rox-appointment-booking'),
                    'dashed' => esc_html__('Dashed', 'rox-appointment-booking'),
                    'dotted' => esc_html__('Dotted', 'rox-appointment-booking'),
                    'double' => esc_html__('Double', 'rox-appointment-booking'),
                ],
                'selectors' => self::selectors($var . 'bs: {{VALUE}};', $modal_selector),
            ]
        );

        $widget->add_responsive_control(
            $id . '_border_width',
            [
                'label'      => esc_html__('Border width', 'rox-appointment-booking'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 20]],
                'selectors'  => self::selectors($var . 'bw: {{SIZE}}{{UNIT}};', $modal_selector),
                'condition'  => [$id . '_border_style!' => 'none'],
            ]
        );

        $widget->add_responsive_control(
            $id . '_border_radius',
            [
                'label'      => esc_html__('Corner radius', 'rox-appointment-booking'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 100]],
                'selectors'  => self::selectors($var . 'br: {{SIZE}}{{UNIT}};', $modal_selector),
            ]
        );

        // --- Text ------------------------------------------------------------
        // Size and weight only: the panel takes its font family from the Layout
        // tab's own picker, which applies to every one of its surfaces.

        $widget->add_control(
            $id . '_text_heading',
            [
                'label'     => esc_html__('Text', 'rox-appointment-booking'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $widget->add_responsive_control(
            $id . '_font_size',
            [
                'label'      => esc_html__('Font size', 'rox-appointment-booking'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 10, 'max' => 40]],
                'selectors'  => self::selectors($var . 'fs: {{SIZE}}{{UNIT}};', $modal_selector),
            ]
        );

        // Built by hand rather than with array_merge(), which would renumber
        // the weights the moment PHP casts "300" to an integer key.
        $weights = ['' => esc_html__('Default', 'rox-appointment-booking')];

        foreach (NavButtons::FONT_WEIGHTS as $weight) {
            $weights[$weight] = $weight;
        }

        $widget->add_control(
            $id . '_font_weight',
            [
                'label'     => esc_html__('Font weight', 'rox-appointment-booking'),
                'type'      => Controls_Manager::SELECT,
                'default'   => '',
                'options'   => $weights,
                'selectors' => self::selectors($var . 'fw: {{VALUE}};', $modal_selector),
            ]
        );

        $widget->end_controls_section();
    }
}
