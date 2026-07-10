<?php

/**
 * Class RoleManager
 * 
 * @package RoxAppointmentBooking
 * @subpackage Modules\Core\Services
 * @since 1.0.0
 *
 * Manages custom roles for the Booking Engine plugin.
 * Creates and handles rox_appointment_booking_customer and rox_appointment_booking_agent roles.
 */

namespace RoxAppointmentBooking\Modules\UserManagement\Services;

if (! defined('ABSPATH')) exit; // Exit if accessed directly


/**
 * Class RoleManager
 *
 * @package RoxAppointmentBooking\Modules\UserManagement
 * @description Handles RoleManager functionality.
 */
class RoleManager
{
    /**
     * Whether this class should be loaded.
     *
     * @var bool
     */
	public static $loadable = true;

	/**
	 * Role definitions
	 */
	const ROLES = [
		'rox_appointment_booking_customer' => [
			'name'         => 'Booking Engine Customer',
			'capabilities' => [
				'read'                        => true,
				'rox_appointment_booking_customer_capability' => true,
			],
		],
		'rox_appointment_booking_agent'    => [
			'name'         => 'Booking Engine Agent',
			'capabilities' => [
				'read'                     => true,
				'rox_appointment_booking_agent_capability' => true,
			],
		],
	];

	/**
	 * Constructor.
	 * 
	 * Initializes the class by registering roles on plugin activation.
	 */
	public function __construct()
	{
		add_action('init', [$this, 'registerRoles']);
	}

	/**
	 * Register all custom roles.
	 *
	 * @return void
	 */
	public function registerRoles(): void
	{
		foreach (self::ROLES as $role_slug => $role_data) {
			$this->addRole($role_slug, $role_data['name'], $role_data['capabilities']);
		}
	}

	/**
	 * Add a custom role.
	 *
	 * @param string $role_slug Role slug/identifier
	 * @param string $role_name Display name for the role
	 * @param array  $capabilities Array of capabilities
	 * @return void
	 */
	private function addRole(string $role_slug, string $role_name, array $capabilities): void
	{
		if (!get_role($role_slug)) {
			add_role($role_slug, $role_name, $capabilities);
		} else {
			$role = get_role($role_slug);
			$role->remove_cap('manage_options');
			foreach ($capabilities as $cap => $grant) {
				$grant ? $role->add_cap($cap) : $role->remove_cap($cap);
			}
		}
	}

	/**
	 * Remove all custom roles.
	 *
	 * @return void
	 */
	public function removeRoles(): void
	{
		foreach (array_keys(self::ROLES) as $role_slug) {
			remove_role($role_slug);
		}
	}

	/**
	 * Check if a user has a specific booking engine role.
	 *
	 * @param int    $user_id User ID
	 * @param string $role_slug Role to check
	 * @return bool
	 */
	public function userHasRole(int $user_id, string $role_slug): bool
	{
		$user = get_userdata($user_id);
		return $user && in_array($role_slug, (array) $user->roles, true);
	}

	/**
	 * Check if a user has the customer role.
	 *
	 * @param int $user_id User ID
	 * @return bool
	 */
	public function isCustomer(int $user_id): bool
	{
		return $this->userHasRole($user_id, 'rox_appointment_booking_customer');
	}

	/**
	 * Check if a user has the agent role.
	 *
	 * @param int $user_id User ID
	 * @return bool
	 */
	public function isAgent(int $user_id): bool
	{
		return $this->userHasRole($user_id, 'rox_appointment_booking_agent');
	}

	/**
	 * Assign a role to a user.
	 *
	 * @param int    $user_id User ID
	 * @param string $role_slug Role to assign
	 * @return bool
	 */
	public function assignRole(int $user_id, string $role_slug): bool
	{
		if (!array_key_exists($role_slug, self::ROLES)) {
			return false;
		}

		$user = get_userdata($user_id);
		if (!$user) {
			return false;
		}

		$user->add_role($role_slug);
		return true;
	}

	/**
	 * Remove a role from a user.
	 *
	 * @param int    $user_id User ID
	 * @param string $role_slug Role to remove
	 * @return bool
	 */
	public function removeRoleFromUser(int $user_id, string $role_slug): bool
	{
		$user = get_userdata($user_id);
		if (!$user) {
			return false;
		}

		$user->remove_role($role_slug);
		return true;
	}
}
