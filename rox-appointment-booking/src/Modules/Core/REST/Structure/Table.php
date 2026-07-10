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
                        "label" => "Table Settings",
                        "icon" => "filter",
                        "route" => "/table/settings"
                    ],
                    [
                        "key" => "2",
                        "type" => "default",
                        "label" => "Export",
                        "icon" => "export",
                        "route" => "/table/customers"
                    ],
                    [
                        "key" => "3",
                        "type" => "primary",
                        "label" => "Add Service",
                        "icon" => "add",
                        "route" => "/table/add"
                    ]
                ],
                "filters" => [
                    "searchId" => [
                        "type" => "input",
                        "placeholder" => "Search ID"
                    ],
                    "dateRange" => [
                        "type" => "dateRange",
                        "placeholder" => ["Start Date", "End Date"]
                    ],
                    "service" => [
                        "type" => "select",
                        "placeholder" => "Service",
                        "options" => ["Physio", "Ortho", "Derma"]
                    ],
                    "employee" => [
                        "type" => "select",
                        "placeholder" => "Employee",
                        "options" => ["Miles Tone", "Jackson", "Joss"]
                    ],
                    "customers" => [
                        "type" => "select",
                        "placeholder" => "Customers",
                        "options" => ["John Doe", "Jacob", "Wilson"]
                    ],
                    "status" => [
                        "type" => "select",
                        "placeholder" => "All Status",
                        "options" => ["Pending", "Approved", "Emergency"]
                    ]
                ],
                "columns" => [
                    [
                        "title" => "ID",
                        "dataIndex" => "id",
                        "key" => "id",
                        "align" => "center",
                        "sorter" => true
                    ],
                    [
                        "title" => "Start Date",
                        "dataIndex" => "startDate",
                        "key" => "startDate",
                        "align" => "center",
                        "sorter" => true
                    ],
                    [
                        "title" => "Service",
                        "dataIndex" => "service",
                        "key" => "service",
                        "render" => "link",
                        "sorter" => true
                    ],
                    [
                        "title" => "Customer",
                        "dataIndex" => "customer",
                        "key" => "customer",
                        "render" => "detailedAvatar",
                        "sorter" => true
                    ],
                    [
                        "title" => "Employee",
                        "dataIndex" => "employee",
                        "key" => "employee",
                        "render" => "nameAvatar",
                        "sorter" => true
                    ],
                    [
                        "title" => "Duration",
                        "dataIndex" => "duration",
                        "key" => "duration",
                        "sorter" => true
                    ],
                    [
                        "title" => "Status",
                        "dataIndex" => "status",
                        "key" => "status",
                        "render" => "status",
                        "sorter" => true
                    ],
                    [
                        "title" => "Created At",
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
            "title" => "Appointments"
        ]);
    }
}