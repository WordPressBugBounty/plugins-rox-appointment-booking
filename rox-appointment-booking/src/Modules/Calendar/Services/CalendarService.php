<?php

namespace RoxAppointmentBooking\Modules\Calendar\Services;

use RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel;
use RoxAppointmentBooking\Modules\Customer\Services\CustomerService;
use RoxAppointmentBooking\Modules\Agent\Services\AgentService;
use RoxAppointmentBooking\Modules\Service\Services\ServiceService;
use RoxAppointmentBooking\Modules\Settings\Services\SettingsService;
use RoxAppointmentBooking\Modules\Appointment\Services\AppointmentService;
use RoxAppointmentBooking\Supports\Security;

/**
 * Class CalendarService
 *
 * @package RoxAppointmentBooking\Modules\Calendar\Services
 * @description Handles calendar-related business logic. Displays appointments from the booking table.
 */
class CalendarService
{
    /**
     * Whether the service should be loadable.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * Get all calendar data (appointments, services, locations, users) with optional filtering
     *
     * @param array $filters Filter parameters
     * @return array Array containing events, services, locations, and users
     */
    public function getEvents(array $filters = []): array
    {
        $events = $this->getAppointmentsAsEvents($filters);
        $services = $this->getServicesList($filters);
        $locations = $this->getLocationsList($filters);
        $users = $this->getUsersList($filters);

        return [
            'events' => $events,
            'services' => $services,
            'locations' => $locations,
            'users' => $users,
        ];
    }

    /**
     * Get appointments as calendar events
     *
     * @param array $filters
     * @return array
     */
    private function getAppointmentsAsEvents(array $filters): array
    {
        try {
            $query = AppointmentModel::query();

            if (!Security::canManageBookings()) {
                if (AppointmentService::isCustomerUser()) {
                    $currentCustomerId = AppointmentService::getCurrentCustomerId();
                    if ($currentCustomerId) {
                        $query->where('customer_id', $currentCustomerId);
                    } else {
                        $query->where('id', 0);
                    }
                } elseif (AppointmentService::isAgentUser()) {
                    $currentAgentId = AppointmentService::getCurrentAgentId();
                    if ($currentAgentId) {
                        $query->where('agent_id', $currentAgentId);
                    } else {
                        $query->where('id', 0);
                    }
                }
            }

            // Apply date range filter
            if (!empty($filters['start'])) {
                $query->where('date', '>=', $filters['start']);
            }

            if (!empty($filters['end'])) {
                $query->where('date', '<=', $filters['end']);
            }

            // Apply status filter
            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            // Apply agent filter
            if (!empty($filters['agent_id'])) {
                $query->where('agent_id', $filters['agent_id']);
            }

            // Apply location filter
            if (!empty($filters['location_id'])) {
                $query->where('location_id', $filters['location_id']);
            }

            // Apply service filter
            if (!empty($filters['service_id'])) {
                $query->where('service_id', $filters['service_id']);
            }

            $appointments = $query->orderBy('date', 'ASC')
                                  ->orderBy('start_time', 'ASC')
                                  ->get();

            $customerService = new CustomerService();
            $agentService = new AgentService();
            $serviceService = new ServiceService();

            $events = [];
            foreach ($appointments as $appointment) {
                $appointmentData = $appointment->toArray();
                
                // Get customer name
                $customerName = '';
                if (!empty($appointmentData['customer_id'])) {
                    $customer = $customerService->getCustomer($appointmentData['customer_id']);
                    if ($customer) {
                        $customerName = $customer->full_name;
                    }
                }

                // Get service name
                $serviceName = '';
                $serviceType = '';
                if (!empty($appointmentData['service_id'])) {
                    $service = $serviceService->getService($appointmentData['service_id']);
                    if ($service) {
                        $serviceName = $service->title ?? '';
                        $serviceType = $service->service_type ?? $serviceName;
                    }
                }

                // Get agent name and avatar
                $agentName = '';
                $agentAvatar = '';
                if (!empty($appointmentData['agent_id'])) {
                    $agent = $agentService->getAgent($appointmentData['agent_id']);
                    if ($agent) {
                        $agentName = $agent->full_name ?? '';
                        // Get avatar URL from thumbnail_id
                        if (!empty($agent->thumbnail_id)) {
                            $agentAvatar = wp_get_attachment_url($agent->thumbnail_id);
                        }
                    }
                }

                // Get location name
                $locationName = '';
                if (!empty($appointmentData['location_id']) && class_exists('\RoxAppointmentBookingPro\Modules\Location\Services\LocationService')) {
                    $locationService = new \RoxAppointmentBookingPro\Modules\Location\Services\LocationService();
                    $location = $locationService->getLocationById($appointmentData['location_id']);
                    if ($location) {
                        $locationName = $location['name'] ?? '';
                    }
                }

                // Title shows Service Name - Customer Name (or just Service Name if no customer)
                $title = $serviceName ?: esc_html__('Appointment', 'rox-appointment-booking');
                if ($serviceName && $customerName) {
                    $title = $serviceName;
                }

                // Handle start time - check if it's already a full datetime
                $startTime = $appointmentData['start_time'] ?? '00:00:00';
                if (strpos($startTime, ' ') !== false) {
                    $startDateTime = str_replace(' ', 'T', $startTime);
                } else {
                    $startDateTime = $appointmentData['date'] . 'T' . $startTime;
                }

                // Handle end time
                $endTime = $appointmentData['end_time'] ?? '23:59:59';
                if (strpos($endTime, ' ') !== false) {
                    $endDateTime = str_replace(' ', 'T', $endTime);
                } else {
                    $endDateTime = $appointmentData['date'] . 'T' . $endTime;
                }

                // Format time for display (e.g., "10:00 AM")
                $displayTime = gmdate('g:i A', strtotime($startTime));

                // Get service-based colors
                $colors = $this->getServiceTypeColors($serviceType);

                $event = [
                    'id' => (string) $appointmentData['id'],
                    'title' => $title,
                    'start' => $appointmentData['date'],
                    'end' => null,
                    'allDay' => true,
                    'backgroundColor' => $colors['backgroundColor'],
                    'borderColor' => $colors['borderColor'],
                    'textColor' => $colors['textColor'],
                    'extendedProps' => [
                        'type' => $serviceType,
                        'time' => $displayTime,
                        'appointment_id' => $appointmentData['id'],
                        'service_id' => $appointmentData['service_id'] ?? null,
                        'service_name' => $serviceName,
                        'customer_id' => $appointmentData['customer_id'] ?? null,
                        'customer_name' => $customerName,
                        'agent_id' => $appointmentData['agent_id'] ?? null,
                        'agent_name' => $agentName,
                        'agent_avatar' => $agentAvatar,
                        'location_id' => $appointmentData['location_id'] ?? null,
                        'location_name' => $locationName,
                        'status' => $appointmentData['status'] ?? 'pending',
                        'payment_status' => $appointmentData['payment_status'] ?? 'pending',
                        'client' => [
                            'id' => $appointmentData['customer_id'] ?? null,
                            'name' => $customerName,
                            'avatar' => '', // Customer avatar can be added if available
                        ],
                        'location' => !empty($appointmentData['location_id']) ? [
                            'id' => $appointmentData['location_id'],
                            'name' => $locationName,
                        ] : null,
                    ],
                ];

                $events[] = $event;
            }

            return $events;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get color based on appointment status
     *
     * @param string $status
     * @return string
     */
    private function getStatusColor(string $status): string
    {
        $colors = [
            'pending' => '#faad14',
            'confirmed' => '#52c41a',
            'approved' => '#52c41a',
            'completed' => '#1890ff',
            'cancelled' => '#ff4d4f',
            'rejected' => '#ff4d4f',
            'no-show' => '#8c8c8c',
            'active' => '#52c41a',
        ];

        return $colors[$status] ?? '#1890ff';
    }

    /**
     * Get available time slots for a specific date
     *
     * @param string $date Date in Y-m-d format
     * @param int|null $serviceId Service ID
     * @param int|null $agentId Agent ID
     * @param int|null $locationId Location ID
     * @return array Available time slots
     */
    public function getAvailableSlots(string $date, ?int $serviceId = null, ?int $agentId = null, ?int $locationId = null): array
    {
        $bookedSlots = $this->getBookedSlots($date, $agentId, $locationId);

        $startTime = '09:00';
        $endTime = '18:00';
        $slotDuration = 30;

        $slots = [];
        $currentTime = strtotime($date . ' ' . $startTime);
        $endTimestamp = strtotime($date . ' ' . $endTime);

        while ($currentTime < $endTimestamp) {
            $slotStart = gmdate('H:i', $currentTime);
            $slotEnd = gmdate('H:i', $currentTime + ($slotDuration * 60));
            
            $isBooked = $this->isSlotBooked($slotStart, $slotEnd, $bookedSlots);
            
            $slots[] = [
                'start' => $slotStart,
                'end' => $slotEnd,
                'available' => !$isBooked,
            ];

            $currentTime += $slotDuration * 60;
        }

        return $slots;
    }

    /**
     * Get booked time slots for a date
     *
     * @param string $date
     * @param int|null $agentId
     * @param int|null $locationId
     * @return array
     */
    private function getBookedSlots(string $date, ?int $agentId = null, ?int $locationId = null): array
    {
        $slots = [];

        $query = AppointmentModel::query()->where('date', $date);
        if ($agentId) {
            $query->where('agent_id', $agentId);
        }
        if ($locationId) {
            $query->where('location_id', $locationId);
        }
        $appointments = $query->get();

        foreach ($appointments as $appointment) {
            if (!empty($appointment->start_time) && !empty($appointment->end_time)) {
                $slots[] = [
                    'start' => $appointment->start_time,
                    'end' => $appointment->end_time,
                ];
            }
        }

        return $slots;
    }

    /**
     * Check if a time slot is booked
     *
     * @param string $slotStart
     * @param string $slotEnd
     * @param array $bookedSlots
     * @return bool
     */
    private function isSlotBooked(string $slotStart, string $slotEnd, array $bookedSlots): bool
    {
        $slotStartTime = strtotime($slotStart);
        $slotEndTime = strtotime($slotEnd);

        foreach ($bookedSlots as $booked) {
            $bookedStart = strtotime($booked['start']);
            $bookedEnd = strtotime($booked['end']);

            if ($slotStartTime < $bookedEnd && $slotEndTime > $bookedStart) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get calendar view configuration
     *
     * @return array
     */
    public static function getCalendarConfig(): array
    {
        return [
            'initialView' => 'dayGridMonth',
            'headerToolbar' => [
                'left' => 'prev,next today',
                'center' => 'title',
                'right' => 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
            ],
            'editable' => true,
            'selectable' => true,
            'selectMirror' => true,
            'dayMaxEvents' => true,
            'weekends' => true,
            'nowIndicator' => true,
            'slotMinTime' => '06:00:00',
            'slotMaxTime' => '22:00:00',
            'slotDuration' => '00:30:00',
            'businessHours' => [
                'daysOfWeek' => [1, 2, 3, 4, 5],
                'startTime' => '09:00',
                'endTime' => '18:00',
            ],
        ];
    }

    /**
     * Get status options
     *
     * @return array
     */
    public static function getStatusOptions(): array
    {
        return [
            ['value' => 'active', 'label' => esc_html__('Active', 'rox-appointment-booking')],
            ['value' => 'cancelled', 'label' => esc_html__('Cancelled', 'rox-appointment-booking')],
            ['value' => 'completed', 'label' => esc_html__('Completed', 'rox-appointment-booking')],
        ];
    }

    /**
     * Get color options for events
     *
     * @return array
     */
    public static function getColorOptions(): array
    {
        return [
            ['value' => '#1890ff', 'label' => esc_html__('Blue', 'rox-appointment-booking')],
            ['value' => '#52c41a', 'label' => esc_html__('Green', 'rox-appointment-booking')],
            ['value' => '#faad14', 'label' => esc_html__('Yellow', 'rox-appointment-booking')],
            ['value' => '#ff4d4f', 'label' => esc_html__('Red', 'rox-appointment-booking')],
            ['value' => '#722ed1', 'label' => esc_html__('Purple', 'rox-appointment-booking')],
            ['value' => '#eb2f96', 'label' => esc_html__('Pink', 'rox-appointment-booking')],
            ['value' => '#13c2c2', 'label' => esc_html__('Cyan', 'rox-appointment-booking')],
            ['value' => '#fa8c16', 'label' => esc_html__('Orange', 'rox-appointment-booking')],
            ['value' => '#8c8c8c', 'label' => esc_html__('Gray', 'rox-appointment-booking')],
        ];
    }

    /**
     * Check if a date is a global holiday or a day off
     *
     * @param string $date Date in Y-m-d format
     * @return array Holiday check result with is_holiday, is_day_off, description
     */
    public function checkGlobalHoliday(string $date): array
    {
        $settingsService = new SettingsService();
        
        // Check if date is a global holiday
        // Holidays can be stored as:
        // - Array of date strings: ["2025-12-31", "2025-01-01"]
        // - Array of objects: [{"date": "2025-12-31", "description": "New Year's Eve"}]
        $holidays = $settingsService->getHolidays();
        $isHoliday = false;
        $holidayDescription = '';
        
        foreach ($holidays as $holiday) {
            // Handle both formats: string date or object with date property
            $holidayDate = is_array($holiday) ? ($holiday['date'] ?? null) : $holiday;
            
            if ($holidayDate === $date) {
                $isHoliday = true;
                $holidayDescription = is_array($holiday) ? ($holiday['description'] ?? '') : '';
                break;
            }
        }
        
        // Check if day is a weekly day off (e.g., Saturday, Sunday)
        $weeklySchedule = $settingsService->getWeeklySchedule();
        $dayOfWeek = strtolower(gmdate('l', strtotime($date))); // e.g., 'monday', 'tuesday'
        $isDayOff = false;
        
        foreach ($weeklySchedule as $day) {
            $dayName = strtolower($day['day_name'] ?? '');
            if ($dayName === $dayOfWeek) {
                $isDayOff = isset($day['day_off']) && $day['day_off'] === true;
                break;
            }
        }
        
        return [
            'is_holiday' => $isHoliday,
            'is_day_off' => $isDayOff,
            'description' => $holidayDescription,
        ];
    }

    /**
     * Get all holidays and day offs within a date range
     *
     * @param string $startDate Start date in Y-m-d format
     * @param string $endDate End date in Y-m-d format
     * @return array Array containing holidays, day_offs, and disabled_dates
     */
    public function getHolidaysInRange(string $startDate, string $endDate): array
    {
        $settingsService = new SettingsService();
        
        // Get global holidays
        // Holidays can be stored as:
        // - Array of date strings: ["2025-12-31", "2025-01-01"]
        // - Array of objects: [{"date": "2025-12-31", "description": "New Year's Eve"}]
        $allHolidays = $settingsService->getHolidays();
        $weeklySchedule = $settingsService->getWeeklySchedule();
        
        // Find day off days in weekly schedule
        $dayOffDays = [];
        foreach ($weeklySchedule as $day) {
            if (isset($day['day_off']) && $day['day_off'] === true && isset($day['day_name'])) {
                $dayOffDays[] = strtolower($day['day_name']);
            }
        }
        
        $holidays = [];
        $dayOffs = [];
        $disabledDates = [];
        
        $currentDate = new \DateTime($startDate);
        $endDateObj = new \DateTime($endDate);
        
        while ($currentDate <= $endDateObj) {
            $dateStr = $currentDate->format('Y-m-d');
            $dayOfWeek = strtolower($currentDate->format('l'));
            
            // Check if it's a global holiday
            foreach ($allHolidays as $holiday) {
                // Handle both formats: string date or object with date property
                $holidayDate = is_array($holiday) ? ($holiday['date'] ?? null) : $holiday;
                $holidayDescription = is_array($holiday) ? ($holiday['description'] ?? '') : '';
                
                if ($holidayDate === $dateStr) {
                    $holidays[] = [
                        'date' => $dateStr,
                        'description' => $holidayDescription,
                    ];
                    $disabledDates[] = $dateStr;
                    break;
                }
            }
            
            // Check if it's a weekly day off
            if (in_array($dayOfWeek, $dayOffDays) && !in_array($dateStr, $disabledDates)) {
                $dayOffs[] = $dateStr;
                $disabledDates[] = $dateStr;
            }
            
            $currentDate->modify('+1 day');
        }
        
        return [
            'holidays' => $holidays,
            'day_offs' => $dayOffs,
            'disabled_dates' => array_unique($disabledDates),
        ];
    }

    /**
     * Check if a specific time slot is available for booking
     *
     * @param string $date Date in Y-m-d format
     * @param string $slot Time slot in HH:MM format
     * @param int $serviceId Service ID
     * @param int $agentId Agent ID
     * @param int|null $locationId Location ID
     * @return array Availability check result
     */
    public function checkSlotAvailability(
        string $date,
        string $slot,
        int $serviceId,
        int $agentId,
        ?int $locationId = null
    ): array {
        $settingsService = new SettingsService();
        $agentService = new AgentService();
        $serviceService = new ServiceService();
        
        $reasons = [];
        $available = true;
        
        // 1. Check if date is a global holiday
        $globalHolidayCheck = $this->checkGlobalHoliday($date);
        if ($globalHolidayCheck['is_holiday']) {
            $available = false;
            $reasons[] = [
                'type' => 'global_holiday',
                'message' => esc_html__('This date is a global holiday', 'rox-appointment-booking'),
                'description' => $globalHolidayCheck['description'],
            ];
        }
        
        // 2. Check if date is a global day off (e.g., weekend)
        if ($globalHolidayCheck['is_day_off']) {
            $available = false;
            $reasons[] = [
                'type' => 'global_day_off',
                'message' => esc_html__('Business is closed on this day', 'rox-appointment-booking'),
            ];
        }
        
        // 3. Check agent's holidays
        try {
            $agentHolidays = $agentService->getHolidays($agentId);
            if (!empty($agentHolidays['holidays'])) {
                foreach ($agentHolidays['holidays'] as $holiday) {
                    $holidayDate = $holiday['date'] ?? null;
                    if ($holidayDate === $date) {
                        $available = false;
                        $reasons[] = [
                            'type' => 'agent_holiday',
                            'message' => esc_html__('Agent is on holiday on this date', 'rox-appointment-booking'),
                            'description' => $holiday['description'] ?? '',
                        ];
                        break;
                    }
                }
            }
        } catch (\Exception $e) {
            $available = false;
            $reasons[] = [
                'type' => 'agent_not_found',
                'message' => esc_html__('Agent not found', 'rox-appointment-booking'),
            ];
        }
        
        // 4. Check agent's weekly schedule
        $agentScheduleInfo = null;
        try {
            $agentSchedule = $agentService->getWeeklySchedule($agentId);
            $agentScheduleInfo = $agentSchedule;
            
            // Check if agent has custom schedule enabled
            if (!empty($agentSchedule['is_enabled']) && !empty($agentSchedule['weekly_schedule'])) {
                $dayOfWeek = strtolower(gmdate('l', strtotime($date)));
                $daySchedule = null;
                
                foreach ($agentSchedule['weekly_schedule'] as $schedule) {
                    if (strtolower($schedule['day_name'] ?? '') === $dayOfWeek) {
                        $daySchedule = $schedule;
                        break;
                    }
                }
                
                if ($daySchedule) {
                    // Check if agent has day off on this day
                    if (!empty($daySchedule['day_off'])) {
                        $available = false;
                        $reasons[] = [
                            'type' => 'agent_day_off',
                            'message' => esc_html__('Agent does not work on this day', 'rox-appointment-booking'),
                        ];
                    } else {
                        // Check if slot is within agent's working hours
                        $slotTime = strtotime($slot);
                        $workStart = strtotime($daySchedule['start_time'] ?? '00:00');
                        $workEnd = strtotime($daySchedule['end_time'] ?? '23:59');
                        
                        if ($slotTime < $workStart || $slotTime >= $workEnd) {
                            $available = false;
                            $reasons[] = [
                                'type' => 'outside_working_hours',
                                'message' => sprintf(
                                    // translators: %1$s = start time, %2$s = end time
                                    esc_html__('Slot is outside agent\'s working hours (%1$s - %2$s)', 'rox-appointment-booking'),
                                    esc_html($daySchedule['start_time'] ?? '00:00'),
                                    esc_html($daySchedule['end_time'] ?? '23:59')
                                ),
                            ];
                        }
                        
                        // Check break times
                        if (!empty($daySchedule['break_start']) && !empty($daySchedule['break_end'])) {
                            $breakStart = strtotime($daySchedule['break_start']);
                            $breakEnd = strtotime($daySchedule['break_end']);
                            
                            if ($slotTime >= $breakStart && $slotTime < $breakEnd) {
                                $available = false;
                                $reasons[] = [
                                    'type' => 'break_time',
                                    'message' => sprintf(
                                        // translators: %1$s = break start time, %2$s = break end time
                                        esc_html__('Slot falls within agent\'s break time (%1$s - %2$s)', 'rox-appointment-booking'),
                                        esc_html($daySchedule['break_start']),
                                        esc_html($daySchedule['break_end'])
                                    ),
                                ];
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Agent schedule check failed, but don't block booking
        }
        
        // 5. Get service info and check duration
        $serviceInfo = null;
        try {
            $service = $serviceService->getService($serviceId);
            if ($service) {
                $serviceInfo = [
                    'id' => $service->id,
                    'title' => $service->title,
                    'duration' => $service->duration,
                    'price' => $service->price,
                ];
            } else {
                $available = false;
                $reasons[] = [
                    'type' => 'service_not_found',
                    'message' => esc_html__('Service not found', 'rox-appointment-booking'),
                ];
            }
        } catch (\Exception $e) {
            $available = false;
            $reasons[] = [
                'type' => 'service_error',
                'message' => esc_html__('Error fetching service information', 'rox-appointment-booking'),
            ];
        }
        
        // 6. Check if slot is already booked
        if ($available) {
            $bookedSlots = $this->getBookedSlots($date, $agentId, $locationId);
            
            // Normalize slot to HH:MM:SS format for comparison
            $slotNormalized = strlen($slot) === 5 ? $slot . ':00' : $slot;
            
            // Calculate end time based on service duration
            $slotEndTime = $slot;
            if ($serviceInfo && !empty($serviceInfo['duration'])) {
                $slotEndTimestamp = strtotime($slot) + ($serviceInfo['duration'] * 60);
                $slotEndTime = gmdate('H:i', $slotEndTimestamp);
            }
            
            $isBooked = $this->isSlotBooked($slot, $slotEndTime, $bookedSlots);
            
            if ($isBooked) {
                $available = false;
                $reasons[] = [
                    'type' => 'slot_booked',
                    'message' => esc_html__('This time slot is already booked', 'rox-appointment-booking'),
                ];
            }
        }
        
        return [
            'available' => $available,
            'date' => $date,
            'slot' => $slot,
            'service_id' => $serviceId,
            'agent_id' => $agentId,
            'location_id' => $locationId,
            'reasons' => $reasons,
            'service_info' => $serviceInfo,
            'agent_schedule' => $agentScheduleInfo,
        ];
    }

    /**
     * Get service-based colors for calendar events
     *
     * @param string $serviceType Service type
     * @return array Colors array with backgroundColor, borderColor, textColor
     */
    private function getServiceTypeColors(string $serviceType): array
    {
        // Default color mapping - can be customized per service
        $colorMap = [
            'Physiotherapy' => [
                'backgroundColor' => '#e8f5e9',
                'borderColor' => '#4caf50',
                'textColor' => '#1b5e20',
            ],
            'Dermatology' => [
                'backgroundColor' => '#e3f2fd',
                'borderColor' => '#2196f3',
                'textColor' => '#0d47a1',
            ],
            'Nutrition-Advice' => [
                'backgroundColor' => '#ffebee',
                'borderColor' => '#ef5350',
                'textColor' => '#b71c1c',
            ],
            'Nutrition Advice' => [
                'backgroundColor' => '#ffebee',
                'borderColor' => '#ef5350',
                'textColor' => '#b71c1c',
            ],
        ];

        // Return color or default blue
        return $colorMap[$serviceType] ?? [
            'backgroundColor' => '#e3f2fd',
            'borderColor' => '#2196f3',
            'textColor' => '#0d47a1',
        ];
    }

    /**
     * Get services list for calendar
     *
     * @param array $filters Optional filters
     * @return array Services array
     */
    private function getServicesList(array $filters = []): array
    {
        $serviceService = new ServiceService();
        $services = $serviceService->getVisibleServices();

        $servicesList = [];
        foreach ($services as $service) {
            $serviceType = $service->service_type ?? $service->title;
            $colors = $this->getServiceTypeColors($serviceType);

            $servicesList[] = [
                'id' => $service->id,
                'name' => $service->title,
                'type' => $serviceType,
                'color' => $colors['borderColor'],
                'backgroundColor' => $colors['backgroundColor'],
                'textColor' => $colors['textColor'],
            ];
        }

        return $servicesList;
    }

    /**
     * Get locations list for calendar
     *
     * @param array $filters Optional filters
     * @return array Locations array
     */
    private function getLocationsList(array $filters = []): array
    {
        if (!class_exists(\RoxAppointmentBookingPro\Modules\Location\Data\LocationModel::class)) {
            return [];
        }

        $locationModel = \RoxAppointmentBookingPro\Modules\Location\Data\LocationModel::query();
        
        // Apply filter if location_id is provided
        if (!empty($filters['location_id'])) {
            if (is_array($filters['location_id'])) {
                $locationModel->whereIn('id', $filters['location_id']);
            } else {
                $locationModel->where('id', $filters['location_id']);
            }
        }

        $locations = $locationModel->get();

        $locationsList = [];
        foreach ($locations as $location) {
            $locationsList[] = [
                'id' => $location->id,
                'name' => $location->name ?? $location->title ?? 'Location ' . $location->id,
            ];
        }

        return $locationsList;
    }

    /**
     * Get users/agents list for calendar
     *
     * @param array $filters Optional filters
     * @return array Users array
     */
    private function getUsersList(array $filters = []): array
    {
        $agentModel = \RoxAppointmentBooking\Modules\Agent\Data\AgentModel::query();

        if (!Security::canManageBookings()) {
            if (AppointmentService::isCustomerUser()) {
                $currentCustomerId = AppointmentService::getCurrentCustomerId();
                if (!$currentCustomerId) {
                    return [];
                }

                $agentIdsQuery = AppointmentModel::query()
                    ->where('customer_id', $currentCustomerId);

                if (!empty($filters['start'])) {
                    $agentIdsQuery->where('date', '>=', $filters['start']);
                }

                if (!empty($filters['end'])) {
                    $agentIdsQuery->where('date', '<=', $filters['end']);
                }

                $agentIds = [];
                $appointments = $agentIdsQuery->get();
                foreach ($appointments as $appointment) {
                    if (!empty($appointment->agent_id)) {
                        $agentIds[] = (int) $appointment->agent_id;
                    }
                }

                $agentIds = array_values(array_unique($agentIds));
                if (empty($agentIds)) {
                    return [];
                }

                $agentModel->whereIn('id', $agentIds);
            } elseif (AppointmentService::isAgentUser()) {
                $currentAgentId = AppointmentService::getCurrentAgentId();
                if (!$currentAgentId) {
                    return [];
                }
                $agentModel->where('id', $currentAgentId);
            }
        }
        
        // Apply filter if agent_id is provided
        if (!empty($filters['agent_id'])) {
            if (is_array($filters['agent_id'])) {
                $agentModel->whereIn('id', $filters['agent_id']);
            } else {
                $agentModel->where('id', $filters['agent_id']);
            }
        }

        $agents = $agentModel->get();

        $usersList = [];
        foreach ($agents as $agent) {
            $avatar = '';
            if (!empty($agent->thumbnail_id)) {
                $avatar = wp_get_attachment_url($agent->thumbnail_id);
            }

            $usersList[] = [
                'id' => $agent->id,
                'name' => $agent->full_name ?? 'Agent ' . $agent->id,
                'avatar' => $avatar,
            ];
        }

        return $usersList;
    }
}
