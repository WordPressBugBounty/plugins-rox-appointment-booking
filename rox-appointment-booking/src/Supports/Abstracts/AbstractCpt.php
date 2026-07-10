<?php

/**
 * Abstract Custom Post Type Class  Booking Engine
 *
 * This class provides base functionality for registering and managing
 * custom post types in theBooking Engine plugin.
 *
 * @package RoxAppointmentBooking
 * @since 1.0.0
 */

namespace RoxAppointmentBooking\Supports\Abstracts;

if (! defined('ABSPATH')) exit; // Exit if accessed directly

abstract class AbstractCpt
{
    /**
     * Indicates whether the CPT is loadable.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * Hooks CPT registration on init.
     *
     * @return void
     */
    public function __construct()
    {
        add_action('init', [$this, 'register']);
    }

    /**
     * Registers the custom post type.
     *
     * @param array $args
     * @return void
     */
    public function register($args = []): void
    {
        $args = array_merge([
            'public'              => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_rest'       => true,
            'show_in_nav_menus'  => false,
            'capability_type'    => 'page',
            'hierarchical'       => false,
            'has_archive'        => false,
            'rewrite'            => ['slug' => $this->type()],
            'supports'           => $this->supports(),
            'labels'             => $this->labels(),
        ], $args);

        register_post_type($this->type(), $args);
    }

	/**
	 * Builds the CPT label set.
	 *
	 * @return array
	 */
    public function labels(): array
    {
        $singular = $this->singularName();
        $plural = $this->pluralName();

        return [
            'name'               => esc_html($plural),
            'singular_name'      => esc_html($singular),
            'menu_name'          => esc_html($plural),
            'add_new'           => esc_html__('Add New', 'rox-appointment-booking'),
            // translators: %s = CPT singular name
            'add_new_item'      => sprintf(esc_html__('Add New %s', 'rox-appointment-booking'), esc_html($singular)),
            // translators: %s = CPT singular name
            'edit_item'         => sprintf(esc_html__('Edit %s', 'rox-appointment-booking'), esc_html($singular)),
            // translators: %s = CPT singular name
            'new_item'          => sprintf(esc_html__('New %s', 'rox-appointment-booking'), esc_html($singular)),
            // translators: %s = CPT singular name
            'view_item'         => sprintf(esc_html__('View %s', 'rox-appointment-booking'), esc_html($singular)),
            // translators: %s = CPT plural name
            'search_items'      => sprintf(esc_html__('Search %s', 'rox-appointment-booking'), esc_html($plural)),
            // translators: %s = CPT plural name
            'not_found'         => sprintf(esc_html__('No %s found', 'rox-appointment-booking'), esc_html($plural)),
            // translators: %s = CPT plural name
            'not_found_in_trash' => sprintf(esc_html__('No %s found in Trash', 'rox-appointment-booking'), esc_html($plural)),
            // translators: %s = CPT singular name
            'parent_item_colon' => sprintf(esc_html__('Parent %s:', 'rox-appointment-booking'), esc_html($singular)),
            // translators: %s = CPT plural name
            'all_items'         => sprintf(esc_html__('All %s', 'rox-appointment-booking'), esc_html($plural)),
        ];
    }

    /**
     * Returns the CPT type slug.
     *
     * @return string
     */
    abstract public function type(): string;
    /**
     * Returns the CPT singular label.
     *
     * @return string
     */
    abstract public function singularName(): string;
    /**
     * Returns the CPT plural label.
     *
     * @return string
     */
    abstract public function pluralName(): string;
    /**
     * Returns the CPT supported features.
     *
     * @return array
     */
    abstract public function supports(): array;
}
