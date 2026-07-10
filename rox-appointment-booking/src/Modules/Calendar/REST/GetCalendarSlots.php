<?php

namespace RoxAppointmentBooking\Modules\Calendar\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Supports\Security;
use RoxAppointmentBooking\Modules\Calendar\Services\CalendarService;
use RoxAppointmentBooking\Modules\Agent\Services\AgentService;
use RoxAppointmentBooking\Modules\Service\Services\ServiceService;
use RoxAppointmentBooking\Modules\Settings\Services\SettingsService;

/**
 * Class GetCalendarSlots
 *
 * @package RoxAppointmentBooking\Modules\Calendar\REST
 * @description Provides available time slots for a specific date via REST API.
 *              Uses merged schedule: agent > service > global settings.
 */
class GetCalendarSlots extends AbstractREST
{
    /**
     * Whether the endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;
    /**
     * REST route for calendar slots.
     *
     * @var string
     */
    public static string $route = '/calendar/slots';
    /**
     * Usable route template for docs.
     *
     * @var string
     */
    public static string $usableRoute = '/calendar/slots';

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
        $calendarService = new CalendarService();
        $agentService = new AgentService();
        $serviceService = new ServiceService();
        $settingsService = new SettingsService();
        
        $date = $request->get_param('date');
        $serviceId = $request->get_param('service_id');
        $agentId = $request->get_param('agent_id');
        $locationId = $request->get_param('location_id');

        // Validate date parameter
        if (empty($date)) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 400,
                message: esc_html__('Date is required', 'rox-appointment-booking'),
                headers: ['status' => 400]
            );
        }

        // Validate date format
        $dateObj = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 400,
                message: esc_html__('Invalid date format. Use Y-m-d format.', 'rox-appointment-booking'),
                headers: ['status' => 400]
            );
        }

        // Get the day of week for the selected date
        $dayOfWeek = strtolower($dateObj->format('l')); // e.g., 'monday', 'tuesday'

        // Check if date is a holiday or day off
        $holidayCheck = $calendarService->checkGlobalHoliday($date);
        
        // Get merged weekly schedule (agent > service > global)
        $weeklySchedule = $this->getMergedWeeklySchedule(
            $settingsService,
            $serviceService,
            $agentService,
            $serviceId ? (int) $serviceId : null,
            $agentId ? (int) $agentId : null
        );

        $daySchedule = null;

        // Check for agent special days
        if ($agentId) {
            try {
                $specialDaysData = $agentService->getSpecialDays((int) $agentId);
                $specialDays = $specialDaysData['special_days'] ?? [];
                
                // Handle both associative object format and array format
                foreach ($specialDays as $specialDay) {
                    if (($specialDay['date'] ?? '') === $date) {
                        $schedule = $specialDay['schedule'] ?? [];
                        $is_day_off = empty($schedule) || count($schedule) < 2;
                        
                        // If it's a special day, use its data
                        if (!$is_day_off) {
                            $daySchedule = [
                                'day_name' => $dayOfWeek,
                                'start_time' => $schedule[0],
                                'end_time' => $schedule[1],
                                'breaks' => $specialDay['breaks'] ?? [],
                                'day_off' => false
                            ];
                        } else {
                            $daySchedule = [
                                'day_name' => $dayOfWeek,
                                'day_off' => true
                            ];
                        }
                        break;
                    }
                }
            } catch (\Exception $e) {
                // Ignore
            }
        }

        // Find the schedule for the selected day if no special day override exists
        if (!$daySchedule) {
            foreach ($weeklySchedule as $schedule) {
                if (strtolower($schedule['day_name'] ?? '') === $dayOfWeek) {
                    $daySchedule = $schedule;
                    break;
                }
            }
        }

        // Check if day is off
        $isDayOff = $holidayCheck['is_holiday'] || $holidayCheck['is_day_off'];
        if ($daySchedule && !empty($daySchedule['day_off'])) {
            $isDayOff = true;
        }

        if ($isDayOff) {
            return rox_appointment_booking_rest_response(
                data: [
                    'date' => $date,
                    'day_name' => $dayOfWeek,
                    'day_off' => true,
                    'is_holiday' => $holidayCheck['is_holiday'],
                    'slots' => [],
                    'available_count' => 0,
                    'total_count' => 0,
                ],
                message: $holidayCheck['is_holiday'] 
                    ? esc_html__('This date is a holiday', 'rox-appointment-booking')
                    : esc_html__('Business is closed on this day', 'rox-appointment-booking')
            );
        }

        // Get timeslots from the day schedule
        $timeslots = $daySchedule['timeslots'] ?? [];

        // If no timeslots defined, generate from start/end time
        if (empty($timeslots) && $daySchedule) {
            $startTime = $daySchedule['start_time'] ?? '09:00:00';
            $endTime = $daySchedule['end_time'] ?? '18:00:00';
            
            // Get breaks array (supports multiple break times)
            $breaks = $daySchedule['breaks'] ?? [];
            
            // Also support legacy break_start/break_end format
            if (empty($breaks) && !empty($daySchedule['break_start']) && !empty($daySchedule['break_end'])) {
                $breaks = [
                    ['start' => $daySchedule['break_start'], 'end' => $daySchedule['break_end']]
                ];
            }
            
            $timeslots = $this->generateTimeslots($startTime, $endTime, 30, $breaks);
        }

        // Get booked slots to mark as unavailable
        $bookedSlots = $this->getBookedSlots($date, $agentId, $locationId);

        // Build slots array with availability status
        $slots = [];
        foreach ($timeslots as $time) {
            // Normalize time format to HH:MM
            $timeNormalized = substr($time, 0, 5);
            
            // Check if slot is booked
            $isBooked = $this->isSlotBooked($timeNormalized, $bookedSlots, 30);
            
            $slots[] = [
                'time' => $time,
                'time_formatted' => $this->formatTime12Hour($time),
                'available' => !$isBooked,
            ];
        }

        return rox_appointment_booking_rest_response(
            data: [
                'date' => $date,
                'day_name' => $dayOfWeek,
                'day_off' => false,
                'slots' => $slots,
                'available_count' => count(array_filter($slots, fn($slot) => $slot['available'])),
                'total_count' => count($slots),
            ],
            message: esc_html__('Available slots retrieved successfully', 'rox-appointment-booking')
        );
    }

    /**
     * Get merged weekly schedule (agent > service > global).
     *
     * @param SettingsService $settingsService
     * @param ServiceService $serviceService
     * @param AgentService $agentService
     * @param int|null $serviceId
     * @param int|null $agentId
     * @return array
     */
    private function getMergedWeeklySchedule(
        SettingsService $settingsService,
        ServiceService $serviceService,
        AgentService $agentService,
        ?int $serviceId,
        ?int $agentId
    ): array {
        // Start with global schedule
        $globalSchedule = $settingsService->getWeeklySchedule() ?? [];
        
        // If we have a service, merge its schedule
        $serviceSchedule = [];
        if ($serviceId) {
            try {
                $serviceData = $serviceService->getWeeklySchedule($serviceId);
                if (!empty($serviceData['is_enabled']) && !empty($serviceData['weekly_schedule'])) {
                    $serviceSchedule = $serviceData['weekly_schedule'];
                }
            } catch (\Exception $e) {
                // Service schedule not found, continue with global
            }
        }
        
        // If we have an agent, merge its schedule
        $agentSchedule = [];
        if ($agentId) {
            try {
                $agentData = $agentService->getWeeklySchedule($agentId);
                if (!empty($agentData['is_enabled']) && !empty($agentData['weekly_schedule'])) {
                    $agentSchedule = $agentData['weekly_schedule'];
                }
            } catch (\Exception $e) {
                // Agent schedule not found, continue with service/global
            }
        }
        
        // Merge schedules: agent > service > global
        $mergedSchedule = [];
        $daysOfWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        
        foreach ($daysOfWeek as $dayName) {
            // Find schedule for this day from each source
            $globalDay = $this->findDaySchedule($globalSchedule, $dayName);
            $serviceDay = $this->findDaySchedule($serviceSchedule, $dayName);
            $agentDay = $this->findDaySchedule($agentSchedule, $dayName);
            
            // Priority: agent > service > global
            if ($agentDay) {
                $mergedSchedule[] = $agentDay;
            } elseif ($serviceDay) {
                $mergedSchedule[] = $serviceDay;
            } elseif ($globalDay) {
                $mergedSchedule[] = $globalDay;
            } else {
                // Default schedule for the day
                $mergedSchedule[] = [
                    'day_name' => ucfirst($dayName),
                    'day_off' => in_array($dayName, ['saturday', 'sunday']),
                    'start_time' => '09:00:00',
                    'end_time' => '18:00:00',
                    'timeslots' => [],
                ];
            }
        }
        
        return $mergedSchedule;
    }

    /**
     * Find day schedule from a weekly schedule array.
     *
     * @param array $weeklySchedule
     * @param string $dayName
     * @return array|null
     */
    private function findDaySchedule(array $weeklySchedule, string $dayName): ?array
    {
        foreach ($weeklySchedule as $schedule) {
            if (strtolower($schedule['day_name'] ?? '') === strtolower($dayName)) {
                return $schedule;
            }
        }
        return null;
    }

    /**
     * Generate timeslots from start/end time, excluding break times
     * 
     * @param string $startTime Start time (HH:MM:SS or HH:MM)
     * @param string $endTime End time (HH:MM:SS or HH:MM)
     * @param int $duration Slot duration in minutes
     * @param array $breaks Array of break times. Supports multiple formats:
     *                      - [["13:00:00", "14:00:00"]] (array with start/end as indices 0/1)
     *                      - [['start' => '13:00', 'end' => '14:00']]
     *                      - [['break_start' => '13:00', 'break_end' => '14:00']]
     * @return array Generated timeslots
     */
    private function generateTimeslots(
        string $startTime,
        string $endTime,
        int $duration = 30,
        array $breaks = []
    ): array {
        $timeslots = [];
        
        $startTimestamp = strtotime($startTime);
        $endTimestamp = strtotime($endTime);
        
        // Parse all break times into timestamps
        $breakPeriods = [];
        foreach ($breaks as $break) {
            $breakStart = null;
            $breakEnd = null;
            
            // Handle array format: ["13:00:00", "14:00:00"] (index 0 = start, index 1 = end)
            if (isset($break[0]) && isset($break[1])) {
                $breakStart = $break[0];
                $breakEnd = $break[1];
            }
            // Handle associative array format: ['start' => '13:00', 'end' => '14:00']
            elseif (isset($break['start']) && isset($break['end'])) {
                $breakStart = $break['start'];
                $breakEnd = $break['end'];
            }
            // Handle legacy format: ['break_start' => '13:00', 'break_end' => '14:00']
            elseif (isset($break['break_start']) && isset($break['break_end'])) {
                $breakStart = $break['break_start'];
                $breakEnd = $break['break_end'];
            }
            
            if ($breakStart && $breakEnd) {
                $breakPeriods[] = [
                    'start' => strtotime($breakStart),
                    'end' => strtotime($breakEnd),
                ];
            }
        }
        
        $current = $startTimestamp;
        while ($current < $endTimestamp) {
            // Check if current time falls within any break period
            $isInBreak = false;
            $skipToTime = null;
            
            foreach ($breakPeriods as $break) {
                if ($current >= $break['start'] && $current < $break['end']) {
                    $isInBreak = true;
                    $skipToTime = $break['end'];
                    break;
                }
            }
            
            // Skip break time
            if ($isInBreak && $skipToTime) {
                $current = $skipToTime;
                continue;
            }
            
            $timeslots[] = gmdate('H:i:s', $current);
            $current += $duration * 60;
        }
        
        return $timeslots;
    }

    /**
     * Get booked slots for a date.
     *
     * @param string $date
     * @param int|null $agentId
     * @param int|null $locationId
     * @return array
     */
    private function getBookedSlots(string $date, ?int $agentId, ?int $locationId): array
    {
        $slots = [];

        $query = \RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel::query()->where('date', $date);
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
     * Check if a time slot is booked.
     *
     * @param string $slotTime
     * @param array $bookedSlots
     * @param int $duration
     * @return bool
     */
    private function isSlotBooked(string $slotTime, array $bookedSlots, int $duration = 30): bool
    {
        $slotStart = strtotime($slotTime);
        $slotEnd = $slotStart + ($duration * 60);

        foreach ($bookedSlots as $booked) {
            $bookedStart = strtotime($booked['start']);
            $bookedEnd = strtotime($booked['end']);

            if ($slotStart < $bookedEnd && $slotEnd > $bookedStart) {
                return true;
            }
        }

        return false;
    }

    /**
     * Format time to 12-hour format.
     *
     * @param string $time
     * @return string
     */
    private function formatTime12Hour(string $time): string
    {
        return gmdate('h:i A', strtotime($time));
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

        if (!is_user_logged_in() || !Security::canAccessPanel()) {
            return false;
        }

        return true;
    }
}
