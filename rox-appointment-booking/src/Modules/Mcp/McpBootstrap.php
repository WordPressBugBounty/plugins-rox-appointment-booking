<?php

namespace RoxAppointmentBooking\Modules\Mcp;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Modules\Mcp\Abilities\AbstractAbility;
use RoxAppointmentBooking\Modules\Mcp\Abilities\Agent;
use RoxAppointmentBooking\Modules\Mcp\Abilities\Appointment;
use RoxAppointmentBooking\Modules\Mcp\Abilities\Calendar;
use RoxAppointmentBooking\Modules\Mcp\Abilities\Customer;
use RoxAppointmentBooking\Modules\Mcp\Abilities\Dashboard;
use RoxAppointmentBooking\Modules\Mcp\Abilities\Order;
use RoxAppointmentBooking\Modules\Mcp\Abilities\Service;
use RoxAppointmentBooking\Modules\Mcp\Server\McpServer;
use RoxAppointmentBooking\Modules\Mcp\Services\McpSettings;

/**
 * Class McpBootstrap
 *
 * @package RoxAppointmentBooking\Modules\Mcp
 * @description Boots the built-in MCP server and mirrors the tools into the WordPress Abilities API when present.
 */
class McpBootstrap
{
    /**
     * Server id, also the suggested client config key.
     */
    public const SERVER_ID = 'rox-appointment-booking';

    /**
     * Ability instances, built once per request.
     *
     * @var AbstractAbility[]|null
     */
    private static ?array $abilities = null;

    /**
     * McpBootstrap constructor.
     *
     * @return void
     */
    public function __construct()
    {
        if (!McpSettings::isOn('mcp_abilities_enable')) {
            return;
        }

        new McpServer();

        // Optional: WordPress 6.9+ also lists them in its own Abilities API.
        if (function_exists('wp_register_ability')) {
            add_action('wp_abilities_api_categories_init', [$this, 'registerCategory']);
            add_action('wp_abilities_api_init', [$this, 'registerAbilities']);
        }
    }

    /**
     * Every ability, free and add-on, whatever the toggles say.
     *
     * @return AbstractAbility[]
     */
    public static function abilities(): array
    {
        if (self::$abilities === null) {
            $list = apply_filters('rox_appointment_booking_mcp_abilities', [
                new Appointment\ListAppointments(),
                new Appointment\GetAppointment(),
                new Appointment\GetAppointmentsForDate(),
                new Appointment\GetUpcomingAppointments(),
                new Appointment\GetAppointmentStatuses(),
                new Calendar\GetAvailableSlots(),
                new Calendar\CheckSlotAvailability(),
                new Calendar\GetWorkSchedule(),
                new Calendar\ListHolidays(),
                new Customer\ListCustomers(),
                new Customer\SearchCustomers(),
                new Customer\GetCustomer(),
                new Customer\GetCustomerByEmail(),
                new Customer\GetCustomerAppointments(),
                new Service\ListServices(),
                new Service\GetService(),
                new Service\ListServiceCategories(),
                new Service\GetServiceAgents(),
                new Agent\ListAgents(),
                new Agent\GetAgent(),
                new Agent\GetAgentServices(),
                new Agent\GetAgentAppointments(),
                new Order\ListOrders(),
                new Order\GetOrder(),
                new Order\GetOrderStats(),
                new Dashboard\GetDashboardStats(),
                new Dashboard\GetTopServices(),
                new Dashboard\GetRecentActivity(),
                new Appointment\CreateAppointment(),
                new Appointment\UpdateAppointment(),
                new Appointment\ChangeAppointmentStatus(),
                new Appointment\RescheduleAppointment(),
                new Appointment\CancelAppointment(),
                new Customer\CreateCustomer(),
                new Customer\UpdateCustomer(),
                new Service\CreateService(),
                new Service\UpdateService(),
                new Service\SetServiceStatus(),
                new Agent\CreateAgent(),
                new Agent\UpdateAgent(),
                new Order\ChangeOrderStatus(),
                new Customer\DeleteCustomer(),
                new Service\DeleteService(),
                new Agent\DeleteAgent(),
                new Order\DeleteOrder(),
                new Order\RefundOrder(),
            ]);

            self::$abilities = array_values(array_filter((array) $list, function ($ability) {
                return $ability instanceof AbstractAbility;
            }));
        }

        return self::$abilities;
    }

    /**
     * Register the ability category.
     *
     * @return void
     */
    public function registerCategory(): void
    {
        wp_register_ability_category(AbstractAbility::CATEGORY, [
            'label'       => __('Rox Appointment Booking', 'rox-appointment-booking'),
            'description' => __('Appointments, customers, services, agents and orders.', 'rox-appointment-booking'),
        ]);
    }

    /**
     * Register only the abilities the toggles allow, so gated ones stay invisible.
     *
     * @return void
     */
    public function registerAbilities(): void
    {
        foreach (self::abilities() as $ability) {
            if ($ability->isEnabled()) {
                wp_register_ability($ability->id(), $ability->definition());
            }
        }
    }
}
