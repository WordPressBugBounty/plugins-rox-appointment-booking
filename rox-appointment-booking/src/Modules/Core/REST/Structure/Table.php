<?php

namespace RoxAppointmentBooking\Modules\Core\REST\Structure;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;

/**
 * Class Table
 *
 * @package RoxAppointmentBooking\Modules\Core\REST\Structure
 * @description Provides the structure for appointments table via REST API.
 */
class Table extends AbstractREST
{
    /**
     * Whether the endpoint should be loadable.
     *
     * @var bool
     */
    public static $loadable = true;
    /**
     * REST route for generic table structure.
     *
     * @var string
     */
    public static string $route = 'structure/table';

    /**
     * Get the methods allowed for this route.
     *
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return 'GET';
    }

    /**
     * Handle the REST API request.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return rox_appointment_booking_rest_response(
            data: $this->getTableStructure(),
            message: array(
                'success' => array(
                    esc_html__('Data Structure Retrieved Successfully', 'rox-appointment-booking')
                )
            )
        );
    }

    /**
     * Check if the user has permission to access the endpoint.
     *
     * @param WP_REST_Request $request
     * @return bool
     */
    public function permissionCheck(WP_REST_Request $request): bool
    {
        if (!wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')) {
            return false;
        }

        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            return false;
        }

        return true;
    }

    /**
     * Returns the appointments table structure as a PHP array.
     *
     * @return array
     */
    private function getTableStructure(): array
    {
        return apply_filters('rox_appointment_booking_table_structure', [
            "searchable" => true,
            "fields" => [
                "actionItems" => [
                    [
                        "key" => "1",
                        "type" => "default",
                        "label" => esc_html__('Table Settings', 'rox-appointment-booking'),
                        "icon" => "filter",
                        "route" => "/table/settings"
                    ],
                    [
                        "key" => "2",
                        "type" => "default",
                        "label" => esc_html__('Export', 'rox-appointment-booking'),
                        "icon" => "export",
                        "route" => "/table/customers"
                    ],
                    [
                        "key" => "3",
                        "type" => "primary",
                        "label" => esc_html__('Add Service', 'rox-appointment-booking'),
                        "icon" => "add",
                        "route" => "/table/add"
                    ]
                ],
                "filters" => [
                    "searchId" => [
                        "type" => "input",
                        "placeholder" => esc_html__('Search ID', 'rox-appointment-booking')
                    ],
                    "dateRange" => [
                        "type" => "dateRange",
                        "placeholder" => [
                            esc_html__('Start Date', 'rox-appointment-booking'),
                            esc_html__('End Date', 'rox-appointment-booking'),
                        ]
                    ],
                    "service" => [
                        "type" => "select",
                        "placeholder" => esc_html__('Service', 'rox-appointment-booking'),
                        "options" => ["Physio", "Ortho", "Derma"]
                    ],
                    "employee" => [
                        "type" => "select",
                        "placeholder" => esc_html__('Employee', 'rox-appointment-booking'),
                        "options" => ["Miles Tone", "Jackson", "Joss"]
                    ],
                    "customers" => [
                        "type" => "select",
                        "placeholder" => esc_html__('Customers', 'rox-appointment-booking'),
                        "options" => ["John Doe", "Jacob", "Wilson"]
                    ],
                    "status" => [
                        "type" => "select",
                        "placeholder" => esc_html__('All Status', 'rox-appointment-booking'),
                        "options" => ["Pending", "Approved", "Emergency"]
                    ]
                ],
                "columns" => [
                    [
                        "title" => esc_html__('ID', 'rox-appointment-booking'),
                        "dataIndex" => "id",
                        "key" => "id",
                        "align" => "center",
                        "sorter" => true
                    ],
                    [
                        "title" => esc_html__('Start Date', 'rox-appointment-booking'),
                        "dataIndex" => "startDate",
                        "key" => "startDate",
                        "align" => "center",
                        "sorter" => true
                    ],
                    [
                        "title" => esc_html__('Service', 'rox-appointment-booking'),
                        "dataIndex" => "service",
                        "key" => "service",
                        "render" => "link",
                        "sorter" => true
                    ],
                    [
                        "title" => esc_html__('Customer', 'rox-appointment-booking'),
                        "dataIndex" => "customer",
                        "key" => "customer",
                        "render" => "detailedAvatar",
                        "sorter" => true
                    ],
                    [
                        "title" => esc_html__('Employee', 'rox-appointment-booking'),
                        "dataIndex" => "employee",
                        "key" => "employee",
                        "render" => "nameAvatar",
                        "sorter" => true
                    ],
                    [
                        "title" => esc_html__('Duration', 'rox-appointment-booking'),
                        "dataIndex" => "duration",
                        "key" => "duration",
                        "sorter" => true
                    ],
                    [
                        "title" => esc_html__('Status', 'rox-appointment-booking'),
                        "dataIndex" => "status",
                        "key" => "status",
                        "render" => "status",
                        "sorter" => true
                    ],
                    [
                        "title" => esc_html__('Created At', 'rox-appointment-booking'),
                        "dataIndex" => "createdAt",
                        "key" => "createdAt",
                        "sorter" => true
                    ]
                ],
                "tableProps" => [
                    "bordered" => false,
                    "scroll" => ["x" => "max-content"],
                    "paginationCount" => 10,
                    "rowSelection" => []
                ]
            ],
            "title" => esc_html__('Appointments', 'rox-appointment-booking')
        ]);
    }
}