<?php

/**
 * Abstract Taxonomy Class  Booking Engine
 *
 * This class provides base functionality for registering and managing
 * taxonomies in theBooking Engine plugin.
 *
 * @package RoxAppointmentBooking
 * @since 1.0.0
 */

namespace RoxAppointmentBooking\Supports\Abstracts;

if (! defined('ABSPATH')) exit; // Exit if accessed directly

abstract class AbstractTaxonomy
{
    /**
     * Indicates whether the taxonomy is loadable.
     *
     * @var bool
     */
    public $loadable = true;

    /**
     * Hooks taxonomy registration on init.
     *
     * @return void
     */
    public function __construct()
    {
        add_action('init', [$this, 'register']);
    }

    /**
     * Registers the taxonomy and attaches it to post types.
     *
     * @return void
     */
    public function register(): void
    {
        $args = [
            'public'             => true,
            'show_ui'           => true,
            'show_in_menu'      => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'          => ['slug' => $this->type()],
            'hierarchical'      => $this->isHierarchical(),
            'labels'           => $this->labels(),
            'capabilities'     => $this->capabilities(),
        ];

        register_taxonomy(
            $this->type(),
            $this->postTypes(),
            $args
        );

        // Register taxonomy for each post type
        foreach ($this->postTypes() as $postType) {
            register_taxonomy_for_object_type($this->type(), $postType);
        }
    }

	/**
	 * Builds the taxonomy label set.
	 *
	 * @return array
	 */
    public function labels(): array
    {
        $singular = $this->singularName();
        $plural = $this->pluralName();

        return [
            'name'                       => esc_html($plural),
            'singular_name'              => esc_html($singular),
            'menu_name'                  => esc_html($plural),
            // translators: %s = taxonomy plural name
            'all_items'                  => sprintf(esc_html__('All %s', 'rox-appointment-booking'), esc_html($plural)),
            // translators: %s = taxonomy singular name
            'edit_item'                  => sprintf(esc_html__('Edit %s', 'rox-appointment-booking'), esc_html($singular)),
            // translators: %s = taxonomy singular name
            'view_item'                  => sprintf(esc_html__('View %s', 'rox-appointment-booking'), esc_html($singular)),
            // translators: %s = taxonomy singular name
            'update_item'                => sprintf(esc_html__('Update %s', 'rox-appointment-booking'), esc_html($singular)),
            // translators: %s = taxonomy singular name
            'add_new_item'               => sprintf(esc_html__('Add New %s', 'rox-appointment-booking'), esc_html($singular)),
            // translators: %s = taxonomy singular name
            'new_item_name'              => sprintf(esc_html__('New %s Name', 'rox-appointment-booking'), esc_html($singular)),
            // translators: %s = taxonomy singular name
            'parent_item'                => sprintf(esc_html__('Parent %s', 'rox-appointment-booking'), esc_html($singular)),
            // translators: %s = taxonomy singular name
            'parent_item_colon'          => sprintf(esc_html__('Parent %s:', 'rox-appointment-booking'), esc_html($singular)),
            // translators: %s = taxonomy plural name
            'search_items'               => sprintf(esc_html__('Search %s', 'rox-appointment-booking'), esc_html($plural)),
            // translators: %s = taxonomy plural name
            'popular_items'              => sprintf(esc_html__('Popular %s', 'rox-appointment-booking'), esc_html($plural)),
            // translators: %s = lowercased taxonomy plural name
            'separate_items_with_commas' => sprintf(esc_html__('Separate %s with commas', 'rox-appointment-booking'), esc_html(strtolower($plural))),
            // translators: %s = lowercased taxonomy plural name
            'add_or_remove_items'        => sprintf(esc_html__('Add or remove %s', 'rox-appointment-booking'), esc_html(strtolower($plural))),
            // translators: %s = lowercased taxonomy plural name
            'choose_from_most_used'      => sprintf(esc_html__('Choose from the most used %s', 'rox-appointment-booking'), esc_html(strtolower($plural))),
            // translators: %s = lowercased taxonomy plural name
            'not_found'                  => sprintf(esc_html__('No %s found', 'rox-appointment-booking'), esc_html(strtolower($plural))),
            // translators: %s = taxonomy plural name
            'back_to_items'              => sprintf(esc_html__('← Back to %s', 'rox-appointment-booking'), esc_html($plural)),
        ];
    }

    /**
     * Get default capabilities for the taxonomy
     */
    protected function capabilities(): array
    {
        return [
            'manage_terms' => 'manage_categories',
            'edit_terms'   => 'manage_categories',
            'delete_terms' => 'manage_categories',
            'assign_terms' => 'edit_posts',
        ];
    }

    /**
     * Whether this taxonomy should be hierarchical (have parent/child relationships)
     */
    protected function isHierarchical(): bool
    {
        return true;
    }

    /**
     * Returns the taxonomy slug.
     *
     * @return string
     */
    abstract public function type(): string;
    /**
     * Returns the taxonomy singular label.
     *
     * @return string
     */
    abstract public function singularName(): string;
    /**
     * Returns the taxonomy plural label.
     *
     * @return string
     */
    abstract public function pluralName(): string;

    /**
     * Get the post types this taxonomy should be registered for
     *
     * @return array Array of post type slugs
     */
    abstract public function postTypes(): array;
}
