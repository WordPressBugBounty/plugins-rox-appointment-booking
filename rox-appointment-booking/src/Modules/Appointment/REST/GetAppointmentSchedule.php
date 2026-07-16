<?php

namespace RoxAppointmentBooking\Modules\Appointment\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Agent\Services\AgentService;
use RoxAppointmentBooking\Modules\Service\Services\ServiceService;
use RoxAppointmentBooking\Modules\Settings\Services\SettingsService;
use RoxAppointmentBooking\Modules\RelationshipModel\Data\ServiceAgentRelationModel;
use RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel;

/**
 * Class GetAppointmentSchedule
 * 
 * @package RoxAppointmentBooking\Modules\Appointment\REST
 * @description Provides the data of the appointment via REST API.
 */
class GetAppointmentSchedule extends AbstractREST
{
    /**
     * Whether the endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;
    /**
     * REST route for appointment schedule data.
     *
     * @var string
     */
    public static string $route = '/appointment-schedule';

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
     * Merge multiple weekly schedules with priority: agent > service > global
     * Any "day_off" at any level marks the day as off in final schedule
     * Generates timeslots based on service duration, schedule, and breaks
     * 
     * @param array $agent_schedule Agent weekly schedule
     * @param array $service_schedule Service weekly schedule
     * @param array $global_schedule Global weekly schedule
     * @param int $service_duration Service duration in minutes
     * @return array Final merged weekly schedule with timeslots
     */
    public function mergeWeeklySchedules(
        array $agent_schedule,
        array $service_schedule,
        array $global_schedule,
        int $service_duration
    ): array {
        if ($service_duration <= 0) {
            return [];
        }

        $final_schedule = [];

        for ($day_index = 0; $day_index < 7; $day_index++) {
            $day_name = $this->getDayName($day_index);

            // Get schedules for this day from each level
            $agent_day = $this->getDayFromSchedule($agent_schedule, $day_index);
            $service_day = $this->getDayFromSchedule($service_schedule, $day_index);
            $global_day = $this->getDayFromSchedule($global_schedule, $day_index);

            // Check if day is off at any level
            $is_day_off = $this->isDayOff($agent_day) ||
                $this->isDayOff($service_day) ||
                $this->isDayOff($global_day);

            // Get intersection of available time windows
            $schedule_times = $is_day_off ? [] : $this->intersectTimeslots(
                $agent_day['schedule'] ?? [],
                $service_day['schedule'] ?? [],
                $global_day['schedule'] ?? []
            );

            $breaks = $is_day_off ? [] : $this->mergeBreaks(
                $agent_day['breaks'] ?? [],
                $service_day['breaks'] ?? [],
                $global_day['breaks'] ?? []
            );

            // Generate timeslots based on schedule, breaks, and service duration
            $timeslots = $is_day_off ? [] : $this->generateTimeslots(
                $schedule_times,
                $breaks,
                $service_duration
            );

            $final_schedule[$day_index] = [
                'day_name' => $day_name,
                'day_off' => (bool) $is_day_off,
                'timeslots' => $timeslots
            ];
        }

        return $final_schedule;
    }

    /**
     * Generate timeslots based on schedule, breaks, and service duration
     * 
     * @param array $schedule_times [start_time, end_time] in HH:MM:SS format
     * @param array $breaks [[start, end], ...] in HH:MM:SS format
     * @param int $service_duration Duration in minutes
     * @return array List of available timeslots in HH:MM:SS format
     */
    private function generateTimeslots(array $schedule_times, array $breaks, int $service_duration): array
    {
        if (empty($schedule_times) || count($schedule_times) < 2) {
            return [];
        }

        $start_time = $schedule_times[0];
        $end_time = $schedule_times[1];

        // Validate time format
        if (!$this->isValidTimeFormat($start_time) || !$this->isValidTimeFormat($end_time)) {
            return [];
        }

        // Convert times to DateTime for easier calculation
        $current = \DateTime::createFromFormat('H:i:s', $start_time);
        $end = \DateTime::createFromFormat('H:i:s', $end_time);
        $timeslots = [];

        if (!$current || !$end) {
            return [];
        }

        // Handle case where end time is earlier than start time
        if ($end <= $current) {
            return [];
        }

        // Generate timeslots
        while ($current < $end) {
            $slot_time = $current->format('H:i:s');

            // Check if this slot falls within any break time
            if (!$this->isTimeInBreak($slot_time, $breaks)) {
                $timeslots[] = $slot_time;
            }

            // Add service duration to current time
            $current->modify("+{$service_duration} minutes");
        }

        return $timeslots;
    }

    /**
     * Validate if time string is in HH:MM:SS format
     * 
     * @param string $time Time string to validate
     * @return bool True if valid format
     */
    private function isValidTimeFormat(string $time): bool
    {
        return (bool) preg_match('/^\d{2}:\d{2}:\d{2}$/', $time);
    }

    /**
     * Check if a given time falls within any break period
     * 
     * @param string $time Time in HH:MM:SS format
     * @param array $breaks [[start, end], ...] in HH:MM:SS format
     * @return bool True if time falls within a break
     */
    private function isTimeInBreak(string $time, array $breaks): bool
    {
        if (empty($breaks)) {
            return false;
        }

        $time_obj = \DateTime::createFromFormat('H:i:s', $time);
        if (!$time_obj) {
            return false;
        }

        foreach ($breaks as $break) {
            if (!is_array($break) || count($break) < 2) {
                continue;
            }

            $break_start_str = is_string($break[0]) ? $break[0] : '';
            $break_end_str = is_string($break[1]) ? $break[1] : '';

            // Handle both formats: already extracted times and ISO 8601
            if (strpos($break_start_str, 'T') !== false) {
                $break_start_str = $this->extractTimeFromString($break_start_str);
            }
            if (strpos($break_end_str, 'T') !== false) {
                $break_end_str = $this->extractTimeFromString($break_end_str);
            }

            $break_start = \DateTime::createFromFormat('H:i:s', $break_start_str);
            $break_end = \DateTime::createFromFormat('H:i:s', $break_end_str);

            if ($break_start && $break_end && $time_obj >= $break_start && $time_obj < $break_end) {
                return true;
            }
        }

        return false;
    }

    /**
     * Merge holidays from agent and global sources
     * Any date that appears in either source is marked as a holiday
     * 
     * @param array $agent_holidays Agent holidays array
     * @param array $global_holidays Global holidays array
     * @return array Merged and sorted unique holidays
     */
    public function mergeHolidays(array $agent_holidays, array $global_holidays): array
    {
        // Combine all holidays
        $all_holidays = array_merge(
            $agent_holidays['holidays'] ?? [],
            $global_holidays ?? []
        );

        if (empty($all_holidays)) {
            return [];
        }

        // Remove duplicates and re-index
        $merged_holidays = array_values(array_unique($all_holidays));

        // Sort chronologically
        sort($merged_holidays);

        return $merged_holidays;
    }

    /**
     * Get booked timeslots for a specific agent based on service duration
     * Returns an array of objects with date and overlapping timeslots properties
     * 
     * @param int $agent_id Agent ID
     * @param int $service_duration Duration of the combined service
     * @param array $weekly_schedule Valid slots for each day
     * @param array $special_days Valid slots for special dates
     * @return array Booked timeslots as array of objects
     */
    public function getBookedTimeslots(int $agent_id, int $service_duration, array $weekly_schedule = [], array $special_days = []): array
    {
        $booked_timeslots = [];

        // Query all appointments for this agent
        $appointments = AppointmentModel::query()
            ->where('agent_id', $agent_id)
            ->where('date', '>=', gmdate('Y-m-d'))
            ->get();

        if (!$appointments || $appointments->isEmpty()) {
            return [];
        }

        $grouped_appointments = [];
        foreach ($appointments as $appointment) {
            $status = strtolower(trim((string) ($appointment->status ?? '')));
            if (in_array($status, ['cancelled', 'canceled', 'rejected'], true)) {
                continue;
            }

            $date = $appointment->date;
            if (empty($date) || empty($appointment->start_time) || empty($appointment->end_time)) {
                continue;
            }
            if (!isset($grouped_appointments[$date])) {
                $grouped_appointments[$date] = [];
            }

            $appointment_start_time = $this->extractTimeFromStartTime((string) $appointment->start_time);
            $appointment_end_time = $this->extractTimeFromStartTime((string) $appointment->end_time);

            if (empty($appointment_start_time) || empty($appointment_end_time)) {
                continue;
            }
            
            $grouped_appointments[$date][] = [
                'start' => \DateTime::createFromFormat('Y-m-d H:i:s', "{$date} {$appointment_start_time}"),
                'end' => \DateTime::createFromFormat('Y-m-d H:i:s', "{$date} {$appointment_end_time}"),
            ];
        }

        // Map special days by date for quick lookup
        $special_days_map = [];
        foreach ($special_days as $sd) {
            $special_days_map[$sd['date']] = $sd['timeslots'] ?? [];
        }

        foreach ($grouped_appointments as $date => $day_appointments) {
            // Figure out base available timeslots for this date
            if (isset($special_days_map[$date])) {
                $day_slots = $special_days_map[$date];
            } else {
                $day_index = (int) date('w', strtotime($date));
                // PHP's 'w' (0=Sun, 6=Sat). Our array uses 0=Mon, 6=Sun. Adjust index:
                $adjusted_index = ($day_index === 0) ? 6 : $day_index - 1;
                $day_slots = $weekly_schedule[$adjusted_index]['timeslots'] ?? [];
            }

            if (empty($day_slots)) {
                continue;
            }

            $blocked = [];
            foreach ($day_slots as $slot_time_str) {
                // $slot_time_str e.g. "09:00:00"
                $slot_start = \DateTime::createFromFormat('Y-m-d H:i:s', "{$date} {$slot_time_str}");
                $slot_end = clone $slot_start;
                $slot_end->modify("+{$service_duration} minutes");

                // Check overlap with any appointment today
                foreach ($day_appointments as $appt) {
                    if (!$appt['start'] || !$appt['end']) continue;
                    // Condition for overlap: slot_start < appt_end AND slot_end > appt_start
                    if ($slot_start < $appt['end'] && $slot_end > $appt['start']) {
                        $blocked[] = $slot_time_str;
                        break;
                    }
                }
            }

            if (!empty($blocked)) {
                $booked_timeslots[] = [
                    'date' => $date,
                    'timeslots' => array_values(array_unique($blocked))
                ];
            }
        }

        // Sort by date
        usort($booked_timeslots, function($a, $b) {
            return strcmp($a['date'], $b['date']);
        });

        return $booked_timeslots;
    }

    /**
     * Booked timeslots for an agent-less service.
     *
     * A slot is "full" when the number of overlapping non-cancelled bookings of
     * THIS service (across all agents) reaches $max_capacity. Mirrors the overlap
     * math of getBookedTimeslots(), but counts concurrency against a capacity
     * limit instead of blocking on the first overlapping appointment.
     *
     * @param int $service_id Service ID
     * @param int $service_duration Duration of the combined service
     * @param int $max_capacity Max concurrent bookings per slot (min 1)
     * @param array $weekly_schedule Valid slots for each day
     * @param array $special_days Valid slots for special dates
     * @return array Booked timeslots as array of objects
     */
    public function getBookedTimeslotsByServiceCapacity(
        int $service_id,
        int $service_duration,
        int $max_capacity,
        array $weekly_schedule = [],
        array $special_days = []
    ): array {
        if ($max_capacity <= 0) {
            $max_capacity = 1;
        }

        $booked_timeslots = [];

        // Query all future appointments for this service (any agent)
        $appointments = AppointmentModel::query()
            ->where('service_id', $service_id)
            ->where('date', '>=', gmdate('Y-m-d'))
            ->get();

        if (!$appointments || $appointments->isEmpty()) {
            return [];
        }

        $grouped_appointments = [];
        foreach ($appointments as $appointment) {
            $status = strtolower(trim((string) ($appointment->status ?? '')));
            if (in_array($status, ['cancelled', 'canceled', 'rejected'], true)) {
                continue;
            }

            $date = $appointment->date;
            if (empty($date) || empty($appointment->start_time) || empty($appointment->end_time)) {
                continue;
            }
            if (!isset($grouped_appointments[$date])) {
                $grouped_appointments[$date] = [];
            }

            $appointment_start_time = $this->extractTimeFromStartTime((string) $appointment->start_time);
            $appointment_end_time = $this->extractTimeFromStartTime((string) $appointment->end_time);

            if (empty($appointment_start_time) || empty($appointment_end_time)) {
                continue;
            }

            $grouped_appointments[$date][] = [
                'start' => \DateTime::createFromFormat('Y-m-d H:i:s', "{$date} {$appointment_start_time}"),
                'end' => \DateTime::createFromFormat('Y-m-d H:i:s', "{$date} {$appointment_end_time}"),
            ];
        }

        // Map special days by date for quick lookup
        $special_days_map = [];
        foreach ($special_days as $sd) {
            $special_days_map[$sd['date']] = $sd['timeslots'] ?? [];
        }

        foreach ($grouped_appointments as $date => $day_appointments) {
            // Figure out base available timeslots for this date
            if (isset($special_days_map[$date])) {
                $day_slots = $special_days_map[$date];
            } else {
                $day_index = (int) date('w', strtotime($date));
                // PHP's 'w' (0=Sun, 6=Sat). Our array uses 0=Mon, 6=Sun. Adjust index:
                $adjusted_index = ($day_index === 0) ? 6 : $day_index - 1;
                $day_slots = $weekly_schedule[$adjusted_index]['timeslots'] ?? [];
            }

            if (empty($day_slots)) {
                continue;
            }

            $blocked = [];
            foreach ($day_slots as $slot_time_str) {
                $slot_start = \DateTime::createFromFormat('Y-m-d H:i:s', "{$date} {$slot_time_str}");
                $slot_end = clone $slot_start;
                $slot_end->modify("+{$service_duration} minutes");

                // Count concurrent bookings overlapping this slot
                $overlap_count = 0;
                foreach ($day_appointments as $appt) {
                    if (!$appt['start'] || !$appt['end']) continue;
                    // Condition for overlap: slot_start < appt_end AND slot_end > appt_start
                    if ($slot_start < $appt['end'] && $slot_end > $appt['start']) {
                        $overlap_count++;
                    }
                }

                // Slot is full only when concurrency reaches the capacity limit
                if ($overlap_count >= $max_capacity) {
                    $blocked[] = $slot_time_str;
                }
            }

            if (!empty($blocked)) {
                $booked_timeslots[] = [
                    'date' => $date,
                    'timeslots' => array_values(array_unique($blocked))
                ];
            }
        }

        // Sort by date
        usort($booked_timeslots, function($a, $b) {
            return strcmp($a['date'], $b['date']);
        });

        return $booked_timeslots;
    }

    /**
     * Extract time portion from start_time field
     * Handles formats: "HH:MM:SS", "YYYY-MM-DD HH:MM:SS", "HH:MM"
     * 
     * @param string $start_time The start time value
     * @return string Time in HH:MM:SS format or empty string if invalid
     */
    private function extractTimeFromStartTime(string $start_time): string
    {
        if (empty($start_time)) {
            return '';
        }

        // Already in HH:MM:SS format
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $start_time)) {
            return $start_time;
        }

        // HH:MM format - add seconds
        if (preg_match('/^\d{2}:\d{2}$/', $start_time)) {
            return $start_time . ':00';
        }

        // YYYY-MM-DD HH:MM:SS format - extract time
        if (preg_match('/\d{4}-\d{2}-\d{2}\s+(\d{2}:\d{2}:\d{2})/', $start_time, $matches)) {
            return $matches[1];
        }

        // YYYY-MM-DD HH:MM format - extract time and add seconds
        if (preg_match('/\d{4}-\d{2}-\d{2}\s+(\d{2}:\d{2})$/', $start_time, $matches)) {
            return $matches[1] . ':00';
        }

        return '';
    }

    /**
     * Get a specific day from a weekly schedule array
     * 
     * @param array|null $schedule Schedule array
     * @param int $day_index Day index (0-6)
     * @return array|null Day schedule or null if not found
     */
    private function getDayFromSchedule(?array $schedule, int $day_index): ?array
    {
        if (!is_array($schedule) || !isset($schedule[$day_index])) {
            return null;
        }
        return $schedule[$day_index];
    }

    /**
     * Check if a day is marked as off
     * 
     * @param array|null $day_data Day data array
     * @return bool True if day is off
     */
    private function isDayOff(?array $day_data): bool
    {
        return is_array($day_data) && !empty($day_data['day_off']);
    }

    /**
     * Get the day name from index (0=Monday, 6=Sunday)
     * 
     * @param int $index Day index
     * @return string Day name
     */
    private function getDayName(int $index): string
    {
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        return $days[$index] ?? '';
    }

    /**
     * Intersect timeslots - returns the overlapping time window between agent, service, and global
     * All times are in 24-hour format (HH:MM:SS or HH:MM)
     *
     * @param array $agent_slots Agent schedule times
     * @param array $service_slots Service schedule times
     * @param array $global_slots Global schedule times
     * @return array Overlapping schedule times in HH:MM:SS format [start, end] or [] if no overlap
     */
    private function intersectTimeslots(array $agent_slots, array $service_slots, array $global_slots): array
    {
        // Extract time windows for each (should be [start, end])
        $agent = $this->extractTimeWindow($agent_slots);
        $service = $this->extractTimeWindow($service_slots);
        $global = $this->extractTimeWindow($global_slots);

        // If any is missing, treat as unavailable
        if (empty($agent) || empty($service) || empty($global)) {
            return [];
        }

        // Find the intersection
        $start = $this->maxTime([$agent[0], $service[0], $global[0]]);
        $end = $this->minTime([$agent[1], $service[1], $global[1]]);

        if ($start && $end && $start < $end) {
            return [$start, $end];
        }
        return [];
    }

    /**
     * Extracts a [start, end] time window from a schedule array
     * Accepts both ISO 8601 and HH:MM:SS formats
     * @param array $slots
     * @return array [start, end] or []
     */
    private function extractTimeWindow(array $slots): array
    {
        if (count($slots) < 2) {
            return [];
        }
        $start = $this->extractTimeFromString($slots[0]);
        $end = $this->extractTimeFromString($slots[1]);
        if ($start && $end) {
            return [$start, $end];
        }
        return [];
    }

    /**
     * Returns the maximum (latest) time from an array of time strings (HH:MM:SS)
     * @param array $times
     * @return string|null
     */
    private function maxTime(array $times): ?string
    {
        $max = null;
        foreach ($times as $t) {
            if (!$this->isValidTimeFormat($t)) continue;
            if ($max === null || $t > $max) {
                $max = $t;
            }
        }
        return $max;
    }

    /**
     * Returns the minimum (earliest) time from an array of time strings (HH:MM:SS)
     * @param array $times
     * @return string|null
     */
    private function minTime(array $times): ?string
    {
        $min = null;
        foreach ($times as $t) {
            if (!$this->isValidTimeFormat($t)) continue;
            if ($min === null || $t < $min) {
                $min = $t;
            }
        }
        return $min;
    }

    /**
     * Extract time portion from datetime strings
     * Handles both ISO 8601 format "2025-10-08T19:00:00.000Z" and "HH:MM:SS" format
     * 
     * @param array $slots Array of datetime strings
     * @return array Array of HH:MM:SS time strings
     */
    private function extractTimeOnly(array $slots): array
    {
        if (empty($slots)) {
            return [];
        }

        $extracted_times = [];

        foreach ($slots as $slot) {
            if (!is_string($slot)) {
                continue;
            }

            // Check if it's already in HH:MM:SS format
            if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $slot)) {
                $extracted_times[] = $slot;
            }
            // Extract time from ISO 8601 format (HH:MM:SS)
            elseif (preg_match('/T(\d{2}:\d{2}:\d{2})/', $slot, $matches)) {
                $extracted_times[] = $matches[1];
            }
        }

        return $extracted_times;
    }

    /**
     * Merge breaks - combine all breaks with time-only format, removing duplicates
     * 
     * @param array $agent_breaks Agent breaks
     * @param array $service_breaks Service breaks
     * @param array $global_breaks Global breaks
     * @return array Merged breaks in HH:MM:SS format
     */
    private function mergeBreaks(array $agent_breaks, array $service_breaks, array $global_breaks): array
    {
        $merged_breaks = [];
        $all_breaks = array_merge($agent_breaks, $service_breaks, $global_breaks);

        if (empty($all_breaks)) {
            return [];
        }

        foreach ($all_breaks as $break) {
            if (!is_array($break) || count($break) < 2) {
                continue;
            }

            // Extract time only from break times
            $start_time = $this->extractTimeFromString($break[0]);
            $end_time = $this->extractTimeFromString($break[1]);

            if (empty($start_time) || empty($end_time)) {
                continue;
            }

            // Check for duplicates
            $break_key = $start_time . '|' . $end_time;
            if (!isset($merged_breaks[$break_key])) {
                $merged_breaks[$break_key] = [$start_time, $end_time];
            }
        }

        // Re-index the array
        $result = array_values($merged_breaks);
        return $result;
    }

    /**
     * Extract time from a datetime string
     * Converts ISO 8601 format to HH:MM:SS
     * Handles both formats: already extracted times and ISO 8601
     * 
     * @param string $datetime ISO 8601 datetime string or HH:MM:SS format
     * @return string Time in HH:MM:SS format or empty string if invalid
     */
    private function extractTimeFromString(string $datetime): string
    {
        if (empty($datetime)) {
            return '';
        }

        // Check if it's already in HH:MM:SS format
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $datetime)) {
            return $datetime;
        }

        // Extract time from ISO 8601 format
        if (preg_match('/T(\d{2}:\d{2}:\d{2})/', $datetime, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * Updated handler with merge logic for schedules and holidays
     * 
     * @param WP_REST_Request $request REST request object
     * @return WP_REST_Response|WP_Error Response or error
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $agent_id = $request->get_param('agent_id');
        $service_id = $request->get_param('service_id');
        $extra_services_param = $request->get_param('extra_services'); // Can be string or array

        // service_id is always required
        if (empty($service_id)) {
            return rox_appointment_booking_rest_response(
                data: ['error' => 'Missing required parameters: agent_id and service_id'],
                status: 'error',
                code: 400
            );
        }

        try {
            // Validate service exists (needed to know if it is agent-less)
            $service_service = new ServiceService();
            $service_exists = $service_service->getService($service_id);
            if (!$service_exists) {
                return rox_appointment_booking_rest_response(
                    data: ['error' => 'Service not found'],
                    status: 'error',
                    code: 404
                );
            }

            // Agent-less path only when no agent is passed AND the service allows it.
            // Agent-optional booking is a Pro feature: without Pro active every
            // service is treated as agent-required (DB flag is ignored, not wiped).
            $allow_without_agent = defined('ROX_APPOINTMENT_BOOKING_PRO_VERSION') && (bool) $service_exists->allow_without_agent;
            $is_agent_less = empty($agent_id) && $allow_without_agent;

            $agent_service = new AgentService();

            // When an agent is required (or one was passed), validate it as before.
            if (!$is_agent_less) {
                if (empty($agent_id)) {
                    return rox_appointment_booking_rest_response(
                        data: ['error' => 'Missing required parameters: agent_id and service_id'],
                        status: 'error',
                        code: 400
                    );
                }

                // Validate agent exists
                $agent_exists = $agent_service->getAgent($agent_id);
                if (!$agent_exists) {
                    return rox_appointment_booking_rest_response(
                        data: ['error' => 'Agent not found'],
                        status: 'error',
                        code: 404
                    );
                }

                // Validate agent provides this service
                if (!ServiceAgentRelationModel::relationExists((int)$agent_id, (int)$service_id)) {
                    return rox_appointment_booking_rest_response(
                        data: ['error' => 'The selected agent does not provide this service'],
                        status: 'error',
                        code: 400
                    );
                }
            }

            // Get service duration (minutes)
            $service_duration = $service_service->getServiceDuration($service_id);
            
            // Add extra services duration
            if (!empty($extra_services_param)) {
                $extra_services = is_array($extra_services_param) ? $extra_services_param : explode(',', $extra_services_param);
                foreach ($extra_services as $extra_id) {
                    $extra_id = trim($extra_id);
                    if (!empty($extra_id)) {
                        if (class_exists('\\RoxAppointmentBookingPro\\Modules\\ExtraService\\Data\\ExtraServiceModel')) {
                            $extra_service = \RoxAppointmentBookingPro\Modules\ExtraService\Data\ExtraServiceModel::find(intval($extra_id));
                            if ($extra_service && !empty($extra_service->duration)) {
                                $service_duration += (int) $extra_service->duration;
                            }
                        }
                    }
                }
            }
            
            if (!$service_duration || !is_numeric($service_duration) || (int)$service_duration <= 0) {
                return rox_appointment_booking_rest_response(
                    data: ['error' => 'Invalid service duration'],
                    status: 'error',
                    code: 400
                );
            }

            // Fetch all schedule and holiday data
            $global_weekly_schedule = (new SettingsService())->getWeeklySchedule();
            $service_weekly_schedule = $service_service->getWeeklySchedule($service_id);

            // Check if service schedule is enabled, otherwise use global schedule will be service schedule
            if(!isset($service_weekly_schedule['is_enabled']) || $service_weekly_schedule['is_enabled'] != 1) {
                $service_weekly_schedule['weekly_schedule'] = $global_weekly_schedule;
            }

            if ($is_agent_less) {
                // No agent: build the schedule from service + global only. Feeding the
                // global schedule in as the "agent" slot reduces the 3-way intersection
                // to service ∩ global. Holidays come from global only.
                $agent_weekly_schedule = ['weekly_schedule' => $global_weekly_schedule];
                $agent_holidays = [];
            } else {
                $agent_weekly_schedule = $agent_service->getWeeklySchedule($agent_id);

                // Check if agent schedule is enabled, otherwise use global schedule will be agent schedule
                if(!isset($agent_weekly_schedule['is_enabled']) || $agent_weekly_schedule['is_enabled'] != 1) {
                    $agent_weekly_schedule['weekly_schedule'] = $global_weekly_schedule;
                }

                $agent_holidays = $agent_service->getHolidays($agent_id);
            }

            $global_holidays = (new SettingsService())->getHolidays();

            // Validate schedule data
            if (!is_array($agent_weekly_schedule) || !is_array($service_weekly_schedule) || !is_array($global_weekly_schedule)) {
                return rox_appointment_booking_rest_response(
                    data: ['error' => 'Invalid schedule data'],
                    status: 'error',
                    code: 500
                );
            }

            // Merge weekly schedules with timeslot generation
            $final_weekly_schedule = $this->mergeWeeklySchedules(
                $agent_weekly_schedule['weekly_schedule'] ?? [],
                $service_weekly_schedule['weekly_schedule'] ?? [],
                $global_weekly_schedule ?? [],
                (int)$service_duration
            );

            // Merge holidays ($agent_holidays is [] for agent-less → global only)
            $final_holidays = $this->mergeHolidays($agent_holidays, $global_holidays);

            if ($is_agent_less) {
                // No agent → no agent special days; block slots by service capacity.
                $special_days = [];

                $max_capacity = (int) $service_exists->without_agent_capacity;
                if ($max_capacity <= 0) {
                    $max_capacity = 1;
                }

                $booked_timeslots = $this->getBookedTimeslotsByServiceCapacity(
                    (int)$service_id,
                    (int)$service_duration,
                    $max_capacity,
                    $final_weekly_schedule,
                    $special_days
                );
            } else {
                // Get and process special days
                $special_days = $this->getProcessedSpecialDays((int)$agent_id, (int)$service_duration);

                // Get booked timeslots for this agent with overlap logic
                $booked_timeslots = $this->getBookedTimeslots((int)$agent_id, (int)$service_duration, $final_weekly_schedule, $special_days);
            }

            $final_schedule_holidays = [
                'weekly_schedule' => $final_weekly_schedule,
                'holidays' => $final_holidays,
                'booked_timeslots' => $booked_timeslots,
                'special_days' => $special_days,
                'slot_duration' => (int)$service_duration
            ];

            return rox_appointment_booking_rest_response(
                data: $final_schedule_holidays,
            );

        } catch (\Exception $e) {
            return rox_appointment_booking_rest_response(
                data: ['error' => 'An error occurred while processing the request'],
                status: 'error',
                code: 500
            );
        }
    }

    /**
     * Get and process special days for an agent
     * 
     * @param int $agent_id Agent ID
     * @param int $service_duration Service duration
     * @return array Processed special days with pre-generated timeslots
     */
    private function getProcessedSpecialDays(int $agent_id, int $service_duration): array
    {
        $agent_service = new AgentService();
        try {
            $special_days_data = $agent_service->getSpecialDays($agent_id);
            $special_days = $special_days_data['special_days'] ?? [];

            $processed = [];
            foreach ($special_days as $id => $data) {
                $date = $data['date'] ?? '';
                if (empty($date)) continue;

                $schedule = $data['schedule'] ?? [];

                // If it's a working day, calculate timeslots. Otherwise it's a day off.
                $is_day_off = empty($schedule) || count($schedule) < 2;

                $timeslots = [];
                if (!$is_day_off) {
                    $timeslots = $this->generateTimeslots($schedule, $data['breaks'] ?? [], $service_duration);
                }

                $processed[$date] = [
                    'date' => $date,
                    'day_off' => $is_day_off,
                    'timeslots' => $timeslots
                ];
            }
            return array_values($processed);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Check if the user has permission to access the endpoint.
     * 
     * @param WP_REST_Request $request REST request object
     * @return bool Permission granted or not
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
}
