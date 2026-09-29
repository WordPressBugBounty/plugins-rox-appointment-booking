<?php

namespace RoxAppointmentBooking\Modules\Service\Services;

use RoxAppointmentBooking\Modules\Service\Data\ServiceModel;
use RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel;
use RoxAppointmentBooking\Modules\RelationshipModel\Data\ServiceAgentRelationModel;
use RoxAppointmentBooking\Modules\RelationshipModel\Data\ServiceLocationRelationModel;
use RoxAppointmentBooking\Modules\RelationshipModel\Data\ServiceCategoryRelationModel;
use RoxAppointmentBooking\Modules\RelationshipModel\Data\ServiceExtraserviceRelationModel;
use RoxAppointmentBooking\Supports\QueryBuilder\RoxAppointmentBookingCollection;

/**
 * Class ServiceService
 *
 * @package RoxAppointmentBooking\Modules\Service\Services
 * @description Handles service-related business logic.
 */
class ServiceService
{
    /**
     * Whether this class should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * Get services with filters and pagination
     *
     * @param array $filters
     * @param int $page
     * @param int $per_page
     * @return array
     */
    public function getServices(array $filters = [], int $page = 1, int $per_page = 10): array
    {
        $query = ServiceModel::query();

        // Apply search filter
        if (!empty($filters['search'])) {
            $query->where(function($q) use ($filters) {
                $q->where('title', 'LIKE', "%{$filters['search']}%")
                  ->orWhere('description', 'LIKE', "%{$filters['search']}%");
            });
        }

        // Apply status filter
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Apply visibility filter
        if (!empty($filters['only_visible'])) {
            $query->visible();
        }

        // Apply price range filter
        if (!empty($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }
        if (!empty($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }

        // Apply capacity filter
        if (!empty($filters['min_capacity'])) {
            $query->where('max_capacity', '>=', $filters['min_capacity']);
        }
        if (!empty($filters['max_capacity'])) {
            $query->where('max_capacity', '<=', $filters['max_capacity']);
        }

        // Apply deposit filter
        if (isset($filters['has_deposit'])) {
            $query->where('deposit', $filters['has_deposit'] ? 1 : 0);
        }

        $total = $query->count();
        $services = $query->offset(($page - 1) * $per_page)
                          ->limit($per_page)
                          ->orderBy('created_at', 'DESC')
                          ->get();

        return [
            'items' => $services,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page)
        ];
    }

    /**
     * Save service (create or update)
     *
     * @param array $data
     * @param int|null $id
     * @return ServiceModel
     * @throws \Exception
     */
    public function saveService(array $data, ?int $id = null): ServiceModel
    {
        // Validate required fields
        $required = ['title', 'duration', 'price'];
        foreach ($required as $field) {
            if (empty($data[$field]) && $data[$field] !== '0' && $data[$field] !== 0) {
                // translators: %s = field name
                throw new \Exception(sprintf(esc_html__('%s is required', 'rox-appointment-booking'), esc_html(ucfirst(str_replace('_', ' ', $field)))));
            }
        }

        // Validate price
        if (!is_numeric($data['price']) || $data['price'] < 0) {
            throw new \Exception(esc_html__('Price must be a valid positive number', 'rox-appointment-booking'));
        }

        // Validate max_capacity if provided
        if (!empty($data['max_capacity']) && (!is_numeric($data['max_capacity']) || $data['max_capacity'] <= 0)) {
            throw new \Exception(esc_html__('Max capacity must be a valid positive number', 'rox-appointment-booking'));
        }

        // Validate deposit amount if deposit is enabled
        if (!empty($data['deposit']) && !empty($data['deposit_amount'])) {
            if (!is_numeric($data['deposit_amount']) || $data['deposit_amount'] < 0) {
                throw new \Exception(esc_html__('Deposit amount must be a valid positive number', 'rox-appointment-booking'));
            }
        }

        // Validate status
        if (!empty($data['status']) && !in_array($data['status'], ['active', 'inactive'])) {
            throw new \Exception(esc_html__('Status must be either active or inactive', 'rox-appointment-booking'));
        }

        // Check for duplicate title
        $existing = ServiceModel::query()
            ->where('title', $data['title'])
            ->when($id, function($q) use ($id) {
                $q->where('id', '!=', $id);
            })
            ->first();

        if ($existing) {
            throw new \Exception(esc_html__('A service with this title already exists', 'rox-appointment-booking'));
        }

        $service = $id ? ServiceModel::find($id) : new ServiceModel();
        if ($id && !$service) {
            throw new \Exception(esc_html__('Service not found', 'rox-appointment-booking'));
        }

        // Set default values
        if (empty($data['status'])) {
            $data['status'] = 'active';
        }

        // Convert boolean fields
        $boolean_fields = [
            'deposit', 'hide_price_booking_panel', 'hide_duration_booking_panel',
            'only_visible_to_agent', 'set_service_specific_payment_methods',
            'active_minimum_extra_service', 'active_maximum_extra_service'
        ];

        foreach ($boolean_fields as $field) {
            if (isset($data[$field])) {
                // Handle checkbox arrays (when multiple checkboxes are selected)
                if (is_array($data[$field])) {
                    // If checkbox is checked, it will contain the value, otherwise empty array
                    $data[$field] = !empty($data[$field]) && in_array('1', $data[$field]);
                } else {
                    // Handle single checkbox values
                    $data[$field] = filter_var($data[$field], FILTER_VALIDATE_BOOLEAN);
                }
            } else {
                // Set default to false for boolean fields that are not present
                $data[$field] = false;
            }
        }

        // Set created_by for new records or updated_by for updates
        $current_user_id = get_current_user_id();
        if (!$id && $current_user_id) {
            $data['created_by'] = $current_user_id;
        } elseif ($id && $current_user_id) {
            $data['updated_by'] = $current_user_id;
        }

        $service->fill($data);
        $service->save();

        return $service;
    }

    /**
     * Delete service
     *
     * @param int $id
     * @return bool
     * @throws \Exception
     */
    public function deleteService(int $id): bool
    {
        $service = ServiceModel::find($id);
        if (!$service) {
            throw new \Exception(esc_html__('Service not found', 'rox-appointment-booking'));
        }

        // Check if service is being used
        if ($this->isServiceInUse($id)) {
            throw new \Exception(esc_html__('Cannot delete service that is currently in use', 'rox-appointment-booking'));
        }

        return $service->delete();
    }

    /**
     * Get single service
     *
     * @param int $id
     * @return ServiceModel|null
     */
    public function getService(int $id): ?ServiceModel
    {
        return ServiceModel::find($id);
    }

    /**
     * Get active services
     *
     * @return \RoxAppointmentBooking\Supports\QueryBuilder\RoxAppointmentBookingCollection|mixed
     */
    public function getActiveServices()
    {
        return ServiceModel::active()->get();
    }

    /**
     * Get visible services (not only_visible_to_agent)
     *
     * @return \RoxAppointmentBooking\Supports\QueryBuilder\RoxAppointmentBookingCollection|mixed
     */
    public function getVisibleServices()
    {
        return ServiceModel::active()->visible()->get();
    }

    /**
     * Check if service is in use
     *
     * @param int $service_id
     * @return bool
     */
    public function isServiceInUse(int $service_id): bool
    {
        // Check in appointments
        if (AppointmentModel::where('service_id', $service_id)->exists()) {
            return true;
        }

        // Check in agent relations
        if (ServiceAgentRelationModel::where('service_id', $service_id)->exists()) {
            return true;
        }

        // Check in location relations
        if (ServiceLocationRelationModel::where('service_id', $service_id)->exists()) {
            return true;
        }

        // Check in category relations
        if (ServiceCategoryRelationModel::where('service_id', $service_id)->exists()) {
            return true;
        }

        // Check in extra service relations
        return ServiceExtraserviceRelationModel::where('service_id', $service_id)->exists();
    }

    /**
     * Get services by agent ID
     *
     * @param int $agent_id
     * @return \RoxAppointmentBooking\Supports\QueryBuilder\RoxAppointmentBookingCollection|mixed
     */
    public function getServicesByAgent(int $agent_id)
    {
        $service_ids = ServiceAgentRelationModel::where('agent_id', $agent_id)
            ->pluck('service_id')
            ->toArray();

        if (empty($service_ids)) {
            return new RoxAppointmentBookingCollection();
        }

        return ServiceModel::query()
            ->whereIn('id', $service_ids)
            ->active()
            ->get();
    }

    /**
     * Get services by location ID
     *
     * @param int $location_id
     * @return \RoxAppointmentBooking\Supports\QueryBuilder\RoxAppointmentBookingCollection|mixed
     */
    public function getServicesByLocation(int $location_id)
    {
        $service_ids = ServiceLocationRelationModel::where('location_id', $location_id)
            ->pluck('service_id')
            ->toArray();

        if (empty($service_ids)) {
            return new RoxAppointmentBookingCollection();
        }

        return ServiceModel::query()
            ->whereIn('id', $service_ids)
            ->active()
            ->get();
    }

    /**
     * Get services by category ID
     *
     * @param int $category_id
     * @return \RoxAppointmentBooking\Supports\QueryBuilder\RoxAppointmentBookingCollection|mixed
     */
    public function getServicesByCategory(int $category_id)
    {
        $service_ids = ServiceCategoryRelationModel::where('category_id', $category_id)
            ->pluck('service_id')
            ->toArray();

        if (empty($service_ids)) {
            return new RoxAppointmentBookingCollection();
        }

        return ServiceModel::query()
            ->whereIn('id', $service_ids)
            ->active()
            ->get();
    }

    /**
     * Bulk update service status
     *
     * @param array $ids
     * @param string $status
     * @return bool
     * @throws \Exception
     */
    public function bulkUpdateStatus(array $ids, string $status): bool
    {
        if (!in_array($status, ['active', 'inactive'])) {
            throw new \Exception(esc_html__('Invalid status provided', 'rox-appointment-booking'));
        }

        if (empty($ids)) {
            throw new \Exception(esc_html__('No services selected', 'rox-appointment-booking'));
        }

        $current_user_id = get_current_user_id();
        $update_data = ['status' => $status, 'updated_at' => current_time('mysql')];
        
        if ($current_user_id) {
            $update_data['updated_by'] = $current_user_id;
        }

        return ServiceModel::query()
            ->whereIn('id', $ids)
            ->update($update_data);
    }

    /**
     * Calculate total price for multiple services
     *
     * @param array $service_ids
     * @return float
     */
    public function calculateTotalPrice(array $service_ids): float
    {
        if (empty($service_ids)) {
            return 0.0;
        }

        return ServiceModel::query()
            ->whereIn('id', $service_ids)
            ->where('status', 'active')
            ->sum('price');
    }

    /**
     * Get duration options for service form
     *
     * @return array
     */
    public static function getDurationOptions(): array
    {
        // Complete predefined duration options array
        $durations = [
            // Minutes (5m to 55m)
            5 => '5m', 10 => '10m', 15 => '15m', 20 => '20m', 25 => '25m', 30 => '30m',
            35 => '35m', 40 => '40m', 45 => '45m', 50 => '50m', 55 => '55m',
            
            // Hours with minutes (1h to 23h 55m)
            60 => '1h', 65 => '1h 5m', 70 => '1h 10m', 75 => '1h 15m', 80 => '1h 20m', 85 => '1h 25m', 90 => '1h 30m',
            95 => '1h 35m', 100 => '1h 40m', 105 => '1h 45m', 110 => '1h 50m', 115 => '1h 55m',
            120 => '2h', 125 => '2h 5m', 130 => '2h 10m', 135 => '2h 15m', 140 => '2h 20m', 145 => '2h 25m', 150 => '2h 30m',
            155 => '2h 35m', 160 => '2h 40m', 165 => '2h 45m', 170 => '2h 50m', 175 => '2h 55m',
            180 => '3h', 185 => '3h 5m', 190 => '3h 10m', 195 => '3h 15m', 200 => '3h 20m', 205 => '3h 25m', 210 => '3h 30m',
            215 => '3h 35m', 220 => '3h 40m', 225 => '3h 45m', 230 => '3h 50m', 235 => '3h 55m',
            240 => '4h', 245 => '4h 5m', 250 => '4h 10m', 255 => '4h 15m', 260 => '4h 20m', 265 => '4h 25m', 270 => '4h 30m',
            275 => '4h 35m', 280 => '4h 40m', 285 => '4h 45m', 290 => '4h 50m', 295 => '4h 55m',
            300 => '5h', 305 => '5h 5m', 310 => '5h 10m', 315 => '5h 15m', 320 => '5h 20m', 325 => '5h 25m', 330 => '5h 30m',
            335 => '5h 35m', 340 => '5h 40m', 345 => '5h 45m', 350 => '5h 50m', 355 => '5h 55m',
            360 => '6h', 365 => '6h 5m', 370 => '6h 10m', 375 => '6h 15m', 380 => '6h 20m', 385 => '6h 25m', 390 => '6h 30m',
            395 => '6h 35m', 400 => '6h 40m', 405 => '6h 45m', 410 => '6h 50m', 415 => '6h 55m',
            420 => '7h', 425 => '7h 5m', 430 => '7h 10m', 435 => '7h 15m', 440 => '7h 20m', 445 => '7h 25m', 450 => '7h 30m',
            455 => '7h 35m', 460 => '7h 40m', 465 => '7h 45m', 470 => '7h 50m', 475 => '7h 55m',
            480 => '8h', 485 => '8h 5m', 490 => '8h 10m', 495 => '8h 15m', 500 => '8h 20m', 505 => '8h 25m', 510 => '8h 30m',
            515 => '8h 35m', 520 => '8h 40m', 525 => '8h 45m', 530 => '8h 50m', 535 => '8h 55m',
            540 => '9h', 545 => '9h 5m', 550 => '9h 10m', 555 => '9h 15m', 560 => '9h 20m', 565 => '9h 25m', 570 => '9h 30m',
            575 => '9h 35m', 580 => '9h 40m', 585 => '9h 45m', 590 => '9h 50m', 595 => '9h 55m',
            600 => '10h', 605 => '10h 5m', 610 => '10h 10m', 615 => '10h 15m', 620 => '10h 20m', 625 => '10h 25m', 630 => '10h 30m',
            635 => '10h 35m', 640 => '10h 40m', 645 => '10h 45m', 650 => '10h 50m', 655 => '10h 55m',
            660 => '11h', 665 => '11h 5m', 670 => '11h 10m', 675 => '11h 15m', 680 => '11h 20m', 685 => '11h 25m', 690 => '11h 30m',
            695 => '11h 35m', 700 => '11h 40m', 705 => '11h 45m', 710 => '11h 50m', 715 => '11h 55m',
            720 => '12h', 725 => '12h 5m', 730 => '12h 10m', 735 => '12h 15m', 740 => '12h 20m', 745 => '12h 25m', 750 => '12h 30m',
            755 => '12h 35m', 760 => '12h 40m', 765 => '12h 45m', 770 => '12h 50m', 775 => '12h 55m',
            780 => '13h', 785 => '13h 5m', 790 => '13h 10m', 795 => '13h 15m', 800 => '13h 20m', 805 => '13h 25m', 810 => '13h 30m',
            815 => '13h 35m', 820 => '13h 40m', 825 => '13h 45m', 830 => '13h 50m', 835 => '13h 55m',
            840 => '14h', 845 => '14h 5m', 850 => '14h 10m', 855 => '14h 15m', 860 => '14h 20m', 865 => '14h 25m', 870 => '14h 30m',
            875 => '14h 35m', 880 => '14h 40m', 885 => '14h 45m', 890 => '14h 50m', 895 => '14h 55m',
            900 => '15h', 905 => '15h 5m', 910 => '15h 10m', 915 => '15h 15m', 920 => '15h 20m', 925 => '15h 25m', 930 => '15h 30m',
            935 => '15h 35m', 940 => '15h 40m', 945 => '15h 45m', 950 => '15h 50m', 955 => '15h 55m',
            960 => '16h', 965 => '16h 5m', 970 => '16h 10m', 975 => '16h 15m', 980 => '16h 20m', 985 => '16h 25m', 990 => '16h 30m',
            995 => '16h 35m', 1000 => '16h 40m', 1005 => '16h 45m', 1010 => '16h 50m', 1015 => '16h 55m',
            1020 => '17h', 1025 => '17h 5m', 1030 => '17h 10m', 1035 => '17h 15m', 1040 => '17h 20m', 1045 => '17h 25m', 1050 => '17h 30m',
            1055 => '17h 35m', 1060 => '17h 40m', 1065 => '17h 45m', 1070 => '17h 50m', 1075 => '17h 55m',
            1080 => '18h', 1085 => '18h 5m', 1090 => '18h 10m', 1095 => '18h 15m', 1100 => '18h 20m', 1105 => '18h 25m', 1110 => '18h 30m',
            1115 => '18h 35m', 1120 => '18h 40m', 1125 => '18h 45m', 1130 => '18h 50m', 1135 => '18h 55m',
            1140 => '19h', 1145 => '19h 5m', 1150 => '19h 10m', 1155 => '19h 15m', 1160 => '19h 20m', 1165 => '19h 25m', 1170 => '19h 30m',
            1175 => '19h 35m', 1180 => '19h 40m', 1185 => '19h 45m', 1190 => '19h 50m', 1195 => '19h 55m',
            1200 => '20h', 1205 => '20h 5m', 1210 => '20h 10m', 1215 => '20h 15m', 1220 => '20h 20m', 1225 => '20h 25m', 1230 => '20h 30m',
            1235 => '20h 35m', 1240 => '20h 40m', 1245 => '20h 45m', 1250 => '20h 50m', 1255 => '20h 55m',
            1260 => '21h', 1265 => '21h 5m', 1270 => '21h 10m', 1275 => '21h 15m', 1280 => '21h 20m', 1285 => '21h 25m', 1290 => '21h 30m',
            1295 => '21h 35m', 1300 => '21h 40m', 1305 => '21h 45m', 1310 => '21h 50m', 1315 => '21h 55m',
            1320 => '22h', 1325 => '22h 5m', 1330 => '22h 10m', 1335 => '22h 15m', 1340 => '22h 20m', 1345 => '22h 25m', 1350 => '22h 30m',
            1355 => '22h 35m', 1360 => '22h 40m', 1365 => '22h 45m', 1370 => '22h 50m', 1375 => '22h 55m',
            1380 => '23h', 1385 => '23h 5m', 1390 => '23h 10m', 1395 => '23h 15m', 1400 => '23h 20m', 1405 => '23h 25m', 1410 => '23h 30m',
            1415 => '23h 35m', 1420 => '23h 40m', 1425 => '23h 45m', 1430 => '23h 50m', 1435 => '23h 55m',
            
            // Days and weeks
            1440 => '1d', 2880 => '2d', 4320 => '3d', 5760 => '4d', 7200 => '5d', 8640 => '6d',
            10080 => '1w', 11520 => '8d', 12960 => '9d', 14400 => '10d', 15840 => '11d', 17280 => '12d', 18720 => '13d',
            20160 => '2w', 21600 => '15d', 23040 => '16d', 24480 => '17d', 25920 => '18d', 27360 => '19d', 28800 => '20d',
            30240 => '3w', 31680 => '22d', 33120 => '23d', 34560 => '24d', 36000 => '25d', 37440 => '26d', 38880 => '27d',
            40320 => '4w', 41760 => '29d', 43200 => '30d', 44640 => '31d'
        ];

        $options = [];
        foreach ($durations as $value => $label) {
            $options[] = [
                'value' => $value,
                'label' => $label
            ];
        }

        return apply_filters('rox_appointment_booking_duration_options', $options);
    }

    /**
     * Normalize relationship IDs from request payloads.
     *
     * @param mixed $data
     * @return array
     */
    public static function normalizeIds(mixed $data): array
    {
        if (is_numeric($data)) {
            return [(int)$data];
        }

        if (is_string($data)) {
            return !empty($data) && is_numeric($data) ? [(int)$data] : [];
        }

        if (!is_array($data)) {
            return [];
        }

        $ids = [];

        foreach ($data as $item) {
            if (is_numeric($item)) {
                $ids[] = (int)$item;
                continue;
            }

            if (is_array($item)) {
                if (isset($item['id']) && is_numeric($item['id'])) {
                    $ids[] = (int)$item['id'];
                } elseif (isset($item['value']) && is_numeric($item['value'])) {
                    $ids[] = (int)$item['value'];
                }
                continue;
            }

            if (is_object($item)) {
                if (isset($item->id) && is_numeric($item->id)) {
                    $ids[] = (int)$item->id;
                } elseif (isset($item->value) && is_numeric($item->value)) {
                    $ids[] = (int)$item->value;
                }
            }
        }

        $ids = array_values(array_unique(array_filter($ids, function($id) {
            return $id > 0;
        })));

        return $ids;
    }

    /**
     * Get service title by ID
     *
     * @param int $id
     * @return string|null
     */
    public static function getServiceTitleById(int $id): ?string
    {
        $service = ServiceModel::find($id);
        return $service ? $service->title : null;
    }

    /**
     * Get weekly schedule by service ID
     *
     * @param int $service_id Service ID
     * @return array Weekly schedule data with service_id, is_enabled, and weekly_schedule
     */
    public function getWeeklySchedule(int $service_id): array
    {
        $service = ServiceModel::find($service_id);
        if (!$service) {
            throw new \Exception(esc_html__('Service not found', 'rox-appointment-booking'));
        }

        $schedule_data = is_string($service->weekly_schedule) 
            ? json_decode($service->weekly_schedule, true) 
            : $service->weekly_schedule;

        return [
            'service_id' => $service_id,
            'is_enabled' => $schedule_data['enabled'] ?? false,
            'weekly_schedule' => $schedule_data['weekly_schedule'] ?? []
        ];
    }

    /**
     * Get service duration by ID
     *
     * @param int $service_id Service ID
     * @return int|bool Duration in minutes or false if not set
     */
    public function getServiceDuration(int $service_id): int | bool
    {
        $service = ServiceModel::find($service_id);
        if (!$service) {
            throw new \Exception(esc_html__('Service not found', 'rox-appointment-booking'));
        }

        return $service->duration ?? false;
    }

    /**
     * The soonest a slot may be booked: minutes that must sit between now and
     * the slot's start.
     *
     * @param ServiceModel|null $service Service to read the window from.
     * @return int Minutes, 0 when there is no minimum.
     */
    public static function minimumAdvanceMinutes(?ServiceModel $service): int
    {
        return self::advanceWindow($service, 'minimum');
    }

    /**
     * The furthest ahead a slot may be booked: minutes between now and the last
     * slot still on offer.
     *
     * @param ServiceModel|null $service Service to read the window from.
     * @return int Minutes, 0 when there is no maximum.
     */
    public static function maximumAdvanceMinutes(?ServiceModel $service): int
    {
        return self::advanceWindow($service, 'maximum');
    }

    /**
     * One end of the booking window, resolved across its two tiers.
     *
     * The site-wide default in General settings is free. A service can carry
     * its own window and override that, but only while Pro is active — same
     * idiom as allow_without_agent: without Pro the stored per-service value is
     * ignored rather than wiped, so it resumes if Pro comes back, and the site
     * falls back to the global setting in the meantime rather than to nothing.
     *
     * @param ServiceModel|null $service Service to read the override from.
     * @param string $bound 'minimum' or 'maximum'.
     * @return int Minutes, 0 when neither tier sets this bound.
     */
    private static function advanceWindow(?ServiceModel $service, string $bound): int
    {
        $enable = $bound . '_advance_enable';
        $days = $bound . '_advance_days';
        $hours = $bound . '_advance_hours';

        if (
            $service
            && defined('ROX_APPOINTMENT_BOOKING_PRO_VERSION')
            && !empty($service->{$enable})
        ) {
            return self::advanceMinutes($service->{$days}, $service->{$hours});
        }

        $settings = rox_appointment_booking_general_settings();

        if (empty($settings[$enable])) {
            return 0;
        }

        return self::advanceMinutes($settings[$days] ?? 0, $settings[$hours] ?? 0);
    }

    /**
     * Days + hours as a single minute count, both halves floored at 0.
     *
     * @param mixed $days
     * @param mixed $hours
     * @return int
     */
    private static function advanceMinutes($days, $hours): int
    {
        return max(0, (int) $days) * 1440 + max(0, (int) $hours) * 60;
    }

    /**
     * Human-readable form of a booking-window bound, for error messages.
     *
     * @param int $minutes Minutes in the window.
     * @return string e.g. "5 days 4 hours".
     */
    public static function formatAdvanceWindow(int $minutes): string
    {
        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);

        $parts = [];
        if ($days > 0) {
            // translators: %d = number of days
            $parts[] = sprintf(_n('%d day', '%d days', $days, 'rox-appointment-booking'), $days);
        }
        if ($hours > 0) {
            // translators: %d = number of hours
            $parts[] = sprintf(_n('%d hour', '%d hours', $hours, 'rox-appointment-booking'), $hours);
        }

        return implode(' ', $parts);
    }

    /**
     * Whether this service uses buffer time at all (the service's Settings tab
     * → "Add buffer time").
     *
     * Buffer time is a Pro feature: the Pro plugin owns the columns behind it
     * and every rule about them, and answers serviceBufferSettings(). Nothing
     * answering means there is no buffer to switch on, so this is false.
     *
     * @param ServiceModel|null $service Service being booked.
     * @return bool
     */
    public static function bufferEnabled(?ServiceModel $service): bool
    {
        return !empty(self::serviceBufferSettings($service)['buffer_enable']);
    }

    /**
     * Whether a time on offer reads to the end of the wrap-up (the service's
     * Settings tab → "Include buffer in displayed slot time"). It changes only
     * the lists people choose from — the booking panels and the admin slot
     * picker — where running the times together means a customer sees no gap
     * between them. It does not change which times are offered (those always
     * sit one whole booking apart), and nothing that records a booking uses it:
     * the confirmation e-mail, the customer's list and the admin views all show
     * the appointment itself. False without the plugin that owns the setting,
     * because there is no buffer to read into a label then.
     *
     * @param ServiceModel|null $service Service being booked.
     * @return bool
     */
    public static function bufferInTimeSlots(?ServiceModel $service): bool
    {
        return !empty(self::serviceBufferSettings($service)['buffer_in_time_slots']);
    }

    /**
     * A service's buffer settings, as the plugin that owns them reports them.
     *
     * The free plugin stores none of this and reads no column for it: it asks,
     * and an unanswered filter means the site has no buffer time — not that it
     * is switched off. That is what keeps the paid feature out of the free
     * plugin (WordPress.org guideline 5) while every free code path above
     * carries on working on a schedule without buffers.
     *
     * @param ServiceModel|null $service Service being booked.
     * @return array buffer_enable, buffer_before, buffer_after, buffer_in_time_slots — empty when nothing answers.
     */
    public static function serviceBufferSettings(?ServiceModel $service): array
    {
        if (!$service) {
            return [];
        }

        $settings = apply_filters('rox_appointment_booking_service_buffer_settings', [], $service);

        return is_array($settings) ? $settings : [];
    }

    /**
     * Whether the booking panels label a time as a range ("8:05 - 8:10")
     * instead of just its start (Settings → General → Time Slot → "Show a time
     * range"). Site-wide, so a customer meets one style of time wherever
     * they look; off by default. Display only — it changes no booking and no
     * time on offer, and it is a free setting: without buffer time a range
     * simply runs from the start of the appointment to its end.
     *
     * @return bool
     */
    public static function showSlotTimeRange(): bool
    {
        return filter_var(
            rox_appointment_booking_general_settings('show_slot_time_range', false),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    /**
     * How long the label of a time on offer runs, in minutes.
     *
     * The appointment itself — extras included — unless something extends it,
     * which is what "Include buffer in displayed slot time" does. Only the lists
     * people choose from use this; nothing that records a booking does.
     *
     * @param int $appointment_minutes The appointment's own length, extras included.
     * @param ServiceModel|null $service Service being booked.
     * @param int $buffer_after Wrap-up minutes of the service, as the grid counted them.
     * @return int
     */
    public static function slotLabelMinutes(int $appointment_minutes, ?ServiceModel $service, int $buffer_after = 0): int
    {
        return max(
            0,
            (int) apply_filters(
                'rox_appointment_booking_slot_label_minutes',
                $appointment_minutes,
                $service,
                $buffer_after
            )
        );
    }

    /**
     * Preparation time: minutes the agent's schedule stays blocked before an
     * appointment of this service starts.
     *
     * @param ServiceModel|null $service Service to read the buffer from.
     * @return int Minutes, 0 when there is no buffer.
     */
    public static function bufferBeforeMinutes(?ServiceModel $service): int
    {
        return max(0, (int) (self::serviceBufferSettings($service)['buffer_before'] ?? 0));
    }

    /**
     * Wrap-up time: minutes the agent's schedule stays blocked after an
     * appointment of this service ends.
     *
     * @param ServiceModel|null $service Service to read the buffer from.
     * @return int Minutes, 0 when there is no buffer.
     */
    public static function bufferAfterMinutes(?ServiceModel $service): int
    {
        return max(0, (int) (self::serviceBufferSettings($service)['buffer_after'] ?? 0));
    }

    /**
     * Preparation time an existing booking holds, as snapshotted on its row
     * when it was written — a later change to the service's buffer does not
     * move bookings that already exist.
     *
     * @param AppointmentModel|null $appointment Booking to read the buffer from.
     * @return int Minutes, 0 when there is no buffer.
     */
    public static function appointmentBufferBeforeMinutes(?AppointmentModel $appointment): int
    {
        return self::appointmentBufferMinutes($appointment, 'before');
    }

    /**
     * Wrap-up time an existing booking holds, as snapshotted on its row.
     *
     * @param AppointmentModel|null $appointment Booking to read the buffer from.
     * @return int Minutes, 0 when there is no buffer.
     */
    public static function appointmentBufferAfterMinutes(?AppointmentModel $appointment): int
    {
        return self::appointmentBufferMinutes($appointment, 'after');
    }

    /**
     * The booking-row columns that carry buffer time: the service's buffer as
     * it stands now — snapshotted, so a later change to the service never moves
     * this booking — and the stretch of schedule it blocks.
     *
     * Empty when nothing answers, and callers spread it into the row they are
     * building, so a site without the Pro plugin writes no buffer column at all
     * (it does not have them).
     *
     * @param ServiceModel|null $service Service being booked.
     * @param string|null $start_time Booking start (a full datetime).
     * @param string|null $end_time Booking end (a full datetime, on the next day when the booking runs past midnight).
     * @return array buffer_before, buffer_after, block_start_time, block_end_time — or empty.
     */
    public static function bufferColumns(?ServiceModel $service, ?string $start_time, ?string $end_time): array
    {
        return self::blockColumns($service, $start_time, $end_time);
    }

    /**
     * The block range of a booking being moved to a new time. It keeps the
     * buffer snapshotted when it was booked — read as stored, so a move made
     * while the feature is unavailable neither wipes nor loses it — and only
     * the range is restamped around the new interval.
     *
     * @param AppointmentModel $booking Booking being moved.
     * @param string|null $start_time New start (a full datetime).
     * @param string|null $end_time New end (a full datetime).
     * @return array block_start_time, block_end_time — or empty.
     */
    public static function movedBlockColumns(AppointmentModel $booking, ?string $start_time, ?string $end_time): array
    {
        return self::blockColumns($booking, $start_time, $end_time);
    }

    /**
     * The buffer columns for a row being written, asked of whoever owns them.
     *
     * @param ServiceModel|AppointmentModel|null $record Service being booked, or the booking being moved.
     * @param string|null $start_time Interval start (a full datetime).
     * @param string|null $end_time Interval end (a full datetime).
     * @return array Columns to write, empty when nothing answers.
     */
    private static function blockColumns(?object $record, ?string $start_time, ?string $end_time): array
    {
        if (!$record) {
            return [];
        }

        $columns = apply_filters(
            'rox_appointment_booking_booking_block_columns',
            [],
            $record,
            $start_time,
            $end_time
        );

        return is_array($columns) ? $columns : [];
    }

    /**
     * Query constraint matching bookings whose schedule overlaps a new
     * booking's.
     *
     * On its own this is the plain test the plugin has always used: same date,
     * start before the new end, end after the new start. Buffer time widens it
     * on both sides, which is why the block columns are passed along — whoever
     * owns them can return a constraint that compares those instead.
     *
     * @param string $date Booking date (Y-m-d).
     * @param string $start_time Booking start, as the raw test compares it.
     * @param string $end_time Booking end, as the raw test compares it.
     * @param array $buffer Columns from bufferColumns().
     * @return \Closure For AppointmentModel::query()->where().
     */
    public static function scheduleOverlap(string $date, string $start_time, string $end_time, array $buffer): \Closure
    {
        $raw = function ($query) use ($date, $start_time, $end_time) {
            $query->where('date', $date)
                  ->where('start_time', '<', $end_time)
                  ->where('end_time', '>', $start_time);
        };

        $overlap = apply_filters(
            'rox_appointment_booking_schedule_overlap',
            $raw,
            $date,
            $start_time,
            $end_time,
            $buffer
        );

        return $overlap instanceof \Closure ? $overlap : $raw;
    }

    /**
     * Whether an overlapping booking only reaches the new one through a buffer —
     * the two appointments themselves do not overlap — so the error can say
     * preparation / wrap-up time is the reason.
     *
     * False on its own: without buffer time an overlap is always a real one.
     *
     * @param AppointmentModel $booking Booking found by scheduleOverlap().
     * @param string $start_time New booking start (a full datetime).
     * @param string $end_time New booking end (a full datetime).
     * @return bool
     */
    public static function overlapsOnlyThroughBuffer(AppointmentModel $booking, string $start_time, string $end_time): bool
    {
        return (bool) apply_filters(
            'rox_appointment_booking_overlap_is_buffer_only',
            false,
            $booking,
            $start_time,
            $end_time
        );
    }

    /**
     * One side of the buffer an existing booking holds, asked of whoever owns
     * the snapshot on its row.
     *
     * @param AppointmentModel|null $appointment Booking to read the buffer from.
     * @param string $side 'before' or 'after'.
     * @return int Minutes, floored at 0.
     */
    private static function appointmentBufferMinutes(?AppointmentModel $appointment, string $side): int
    {
        if (!$appointment) {
            return 0;
        }

        return max(
            0,
            (int) apply_filters(
                'rox_appointment_booking_appointment_buffer_minutes',
                0,
                $appointment,
                $side
            )
        );
    }
}
