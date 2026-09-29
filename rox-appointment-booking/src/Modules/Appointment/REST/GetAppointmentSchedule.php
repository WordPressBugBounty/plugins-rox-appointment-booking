<?php

namespace RoxAppointmentBooking\Modules\Appointment\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Supports\Access\Permissions;
use RoxAppointmentBooking\Modules\RelationshipModel\Data\ServiceAgentRelationModel;
use RoxAppointmentBooking\Modules\Agent\Services\AgentService;
use RoxAppointmentBooking\Modules\Service\Services\ServiceService;
use RoxAppointmentBooking\Modules\Settings\Services\SettingsService;
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
     * @param int $slot_step Minutes from one offered time to the next
     * @param int $appointment_duration Booking's own length (service + extras); 0 falls back to $slot_step
     * @param int $buffer_before Preparation minutes before the booking
     * @param int $buffer_after Wrap-up minutes after the booking
     * @return array Final merged weekly schedule with timeslots
     */
    public function mergeWeeklySchedules(
        array $agent_schedule,
        array $service_schedule,
        array $global_schedule,
        int $slot_step,
        int $appointment_duration = 0,
        int $buffer_before = 0,
        int $buffer_after = 0
    ): array {
        if ($slot_step <= 0) {
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
                $slot_step,
                $appointment_duration,
                $buffer_before,
                $buffer_after
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
     * @param int $slot_step Minutes from one offered time to the next
     * @param int $appointment_duration Booking's own length (service + extras); 0 falls back to $slot_step
     * @param int $buffer_before Preparation minutes before the booking
     * @param int $buffer_after Wrap-up minutes after the booking
     * @return array List of available timeslots in HH:MM:SS format
     */
    private function generateTimeslots(
        array $schedule_times,
        array $breaks,
        int $slot_step,
        int $appointment_duration = 0,
        int $buffer_before = 0,
        int $buffer_after = 0
    ): array {
        if (empty($schedule_times) || count($schedule_times) < 2 || $slot_step <= 0) {
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

        // The booking's own length. Callers that do not separate the two pass
        // the step alone, which is what it is without buffers.
        $duration = $appointment_duration > 0 ? $appointment_duration : $slot_step;

        // Breaks cut the day into the stretches that can actually hold a
        // booking. Each stretch is filled on its own, so nothing on offer ever
        // runs into a break and the times start again right after one.
        $windows = $this->availableWindows($current, $end, $this->breakRanges($breaks));

        foreach ($windows as $window) {
            $slot = clone $window['start'];

            // BUFFER TIME (Pro): preparation is work too, so a stretch's first
            // booking starts that many minutes in — 08:05 on a day opening at
            // 08:00 with a 5-minute prep (owner decision, 2026-09-24) — rather
            // than reaching back before the day opens or into the break that
            // ends here. Every later time is one step on; with buffers in the
            // grid that step is a whole booking, so one booking's wrap-up and
            // the next one's preparation meet exactly.
            if ($buffer_before > 0) {
                $slot->modify("+{$buffer_before} minutes");
            }

            while ($slot < $window['end']) {
                // Everything the booking holds — preparation and wrap-up
                // included — belongs inside this stretch, so stop once it would
                // run past the end. Later times only start later, so none fit.
                $block_end = (clone $slot)->modify('+' . ($duration + $buffer_after) . ' minutes');
                if ($block_end > $window['end']) {
                    break;
                }

                $timeslots[] = $slot->format('H:i:s');

                $slot->modify("+{$slot_step} minutes");
            }
        }

        return $timeslots;
    }

    /**
     * Validate if time string is in HH:MM:SS format
     * 
     * @param string $time Time string to validate
     * @return bool True if valid format
     */
    private static function isValidTimeFormat(string $time): bool
    {
        return (bool) preg_match('/^\d{2}:\d{2}:\d{2}$/', $time);
    }

    /**
     * Parse break periods into comparable time ranges
     *
     * @param array $breaks [[start, end], ...] in HH:MM:SS or ISO 8601 format
     * @return array List of ['start' => DateTime, 'end' => DateTime]
     */
    private static function breakRanges(array $breaks): array
    {
        $ranges = [];

        foreach ($breaks as $break) {
            if (!is_array($break) || count($break) < 2) {
                continue;
            }

            $break_start_str = is_string($break[0]) ? $break[0] : '';
            $break_end_str = is_string($break[1]) ? $break[1] : '';

            // Handle both formats: already extracted times and ISO 8601
            if (strpos($break_start_str, 'T') !== false) {
                $break_start_str = self::extractTimeFromString($break_start_str);
            }
            if (strpos($break_end_str, 'T') !== false) {
                $break_end_str = self::extractTimeFromString($break_end_str);
            }

            $break_start = \DateTime::createFromFormat('H:i:s', $break_start_str);
            $break_end = \DateTime::createFromFormat('H:i:s', $break_end_str);

            if ($break_start && $break_end && $break_end > $break_start) {
                $ranges[] = ['start' => $break_start, 'end' => $break_end];
            }
        }

        return $ranges;
    }

    /**
     * Split a working window into the stretches the breaks leave free
     *
     * Overlapping or out-of-order breaks are handled by walking the day once,
     * so the stretches come back in order and never overlap each other.
     *
     * @param \DateTime $start Start of the working window
     * @param \DateTime $end End of the working window
     * @param array $break_ranges Ranges from breakRanges()
     * @return array List of ['start' => DateTime, 'end' => DateTime]
     */
    private static function availableWindows(\DateTime $start, \DateTime $end, array $break_ranges): array
    {
        usort($break_ranges, function ($a, $b) {
            return $a['start'] <=> $b['start'];
        });

        $windows = [];
        $cursor = clone $start;

        foreach ($break_ranges as $range) {
            if ($range['end'] <= $cursor) {
                // Break is behind us, or covers where we already are.
                continue;
            }

            if ($range['start'] >= $end) {
                // Breaks are in order, so this and the rest are after the day.
                break;
            }

            if ($range['start'] > $cursor) {
                $windows[] = ['start' => clone $cursor, 'end' => clone $range['start']];
            }

            $cursor = clone $range['end'];
        }

        if ($cursor < $end) {
            $windows[] = ['start' => $cursor, 'end' => clone $end];
        }

        return $windows;
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
     * Working stretches of one date, with the breaks already taken out
     *
     * The same sources the slot grid is built from (agent, service and global
     * schedules, plus the agent's special days), reduced to the one date the
     * write paths are about. They use it to refuse a booking that would run
     * outside the working day or into a break, which the slot grid never offers.
     *
     * @param int|null $agent_id Agent being booked, null for an agent-less booking
     * @param int|null $service_id Service being booked
     * @param string $date Date in YYYY-MM-DD format
     * @return array|null Stretches as ['start' => 'H:i:s', 'end' => 'H:i:s'], or null when no schedule could be read
     */
    public static function workingWindowsForDate(?int $agent_id, ?int $service_id, string $date): ?array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        $timestamp = strtotime($date);
        if (!$timestamp) {
            return null;
        }

        try {
            $settings_service = new SettingsService();
            $global_weekly_schedule = $settings_service->getWeeklySchedule();

            // Nothing to measure against: leave the decision to the callers.
            if (!is_array($global_weekly_schedule) || empty($global_weekly_schedule)) {
                return null;
            }

            $service_weekly_schedule = $service_id
                ? (new ServiceService())->getWeeklySchedule($service_id)
                : [];
            if (!is_array($service_weekly_schedule)) {
                $service_weekly_schedule = [];
            }
            // A service schedule that is switched off means "follow the global one".
            if (!isset($service_weekly_schedule['is_enabled']) || $service_weekly_schedule['is_enabled'] != 1) {
                $service_weekly_schedule['weekly_schedule'] = $global_weekly_schedule;
            }

            $agent_service = new AgentService();
            $special_day = null;

            if ($agent_id) {
                $agent_weekly_schedule = $agent_service->getWeeklySchedule($agent_id);
                if (!is_array($agent_weekly_schedule)) {
                    $agent_weekly_schedule = [];
                }
                if (!isset($agent_weekly_schedule['is_enabled']) || $agent_weekly_schedule['is_enabled'] != 1) {
                    $agent_weekly_schedule['weekly_schedule'] = $global_weekly_schedule;
                }

                // A special day replaces the weekday's hours and breaks outright.
                $special_days = $agent_service->getSpecialDays($agent_id)['special_days'] ?? [];
                foreach ($special_days as $data) {
                    if (is_array($data) && ($data['date'] ?? '') === $date) {
                        $special_day = $data;
                        break;
                    }
                }
            } else {
                // Agent-less booking: service ∩ global, the same reduction the
                // slot grid makes by feeding the global schedule in as the agent.
                $agent_weekly_schedule = ['weekly_schedule' => $global_weekly_schedule];
            }

            if (is_array($special_day)) {
                $schedule_times = $special_day['schedule'] ?? [];
                $breaks = $special_day['breaks'] ?? [];

                if (!is_array($schedule_times) || count($schedule_times) < 2) {
                    // Special day off: no stretch of it can hold a booking.
                    return [];
                }
            } else {
                // PHP's 'w' (0=Sun, 6=Sat). Our array uses 0=Mon, 6=Sun.
                $day_index = (int) gmdate('w', $timestamp);
                $day_index = ($day_index === 0) ? 6 : $day_index - 1;

                $agent_day = self::getDayFromSchedule($agent_weekly_schedule['weekly_schedule'] ?? [], $day_index);
                $service_day = self::getDayFromSchedule($service_weekly_schedule['weekly_schedule'] ?? [], $day_index);
                $global_day = self::getDayFromSchedule($global_weekly_schedule, $day_index);

                if (self::isDayOff($agent_day) || self::isDayOff($service_day) || self::isDayOff($global_day)) {
                    return [];
                }

                $schedule_times = self::intersectTimeslots(
                    $agent_day['schedule'] ?? [],
                    $service_day['schedule'] ?? [],
                    $global_day['schedule'] ?? []
                );

                $breaks = self::mergeBreaks(
                    $agent_day['breaks'] ?? [],
                    $service_day['breaks'] ?? [],
                    $global_day['breaks'] ?? []
                );
            }

            if (count($schedule_times) < 2) {
                return [];
            }

            if (!self::isValidTimeFormat($schedule_times[0]) || !self::isValidTimeFormat($schedule_times[1])) {
                return null;
            }

            $start = \DateTime::createFromFormat('H:i:s', $schedule_times[0]);
            $end = \DateTime::createFromFormat('H:i:s', $schedule_times[1]);

            if (!$start || !$end || $end <= $start) {
                return [];
            }

            $windows = [];
            foreach (self::availableWindows($start, $end, self::breakRanges($breaks)) as $window) {
                $windows[] = [
                    'start' => $window['start']->format('H:i:s'),
                    'end' => $window['end']->format('H:i:s'),
                ];
            }

            return $windows;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Whether a stretch of schedule fits inside one working stretch of the day
     *
     * Everything the booking holds is measured, preparation included: a
     * stretch's first time already starts after it (see generateTimeslots()),
     * so a booking whose prep would reach back before the day opens, or into
     * the break that ends there, is not one the panels ever offered.
     *
     * @param array $windows Stretches from workingWindowsForDate()
     * @param string $block_start Start of the booking's preparation as 'Y-m-d H:i:s'
     * @param string $block_end End of the booking's wrap-up as 'Y-m-d H:i:s'
     * @return bool True when one stretch holds the whole block
     */
    public static function blockFitsWindows(array $windows, string $block_start, string $block_end): bool
    {
        $start = strtotime($block_start);
        $end = strtotime($block_end);

        if (!$start || !$end || $end <= $start) {
            return true;
        }

        // A booking running past midnight cannot sit inside one day's hours.
        if (gmdate('Y-m-d', $start) !== gmdate('Y-m-d', $end)) {
            return false;
        }

        $block_from = gmdate('H:i:s', $start);
        $block_to = gmdate('H:i:s', $end);

        foreach ($windows as $window) {
            $window_from = is_array($window) ? ($window['start'] ?? '') : '';
            $window_to = is_array($window) ? ($window['end'] ?? '') : '';

            if ($window_from !== '' && $window_to !== '' && $block_from >= $window_from && $block_to <= $window_to) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get booked timeslots for a specific agent based on service duration
     * Returns an array of objects with date and overlapping timeslots properties
     * 
     * @param int $agent_id Agent ID
     * @param int $service_duration Duration of the combined service
     * @param array $weekly_schedule Valid slots for each day
     * @param array $special_days Valid slots for special dates
     * @param int $buffer_before Buffer minutes before a slot of the service being booked
     * @param int $buffer_after Buffer minutes after a slot of the service being booked
     * @return array Booked timeslots as array of objects
     */
    public function getBookedTimeslots(int $agent_id, int $service_duration, array $weekly_schedule = [], array $special_days = [], int $buffer_before = 0, int $buffer_after = 0): array
    {
        $booked_timeslots = [];
        $today = gmdate('Y-m-d');

        // Query all appointments for this agent. Yesterday is included only so a
        // buffer running past midnight still blocks today; blockedDates() drops
        // anything that lands before today.
        $appointments = AppointmentModel::query()
            ->where('agent_id', $agent_id)
            ->where('date', '>=', gmdate('Y-m-d', strtotime('-1 day')))
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

            $range = $this->blockedRange($appointment, $date, $appointment_start_time, $appointment_end_time);
            if (!$range) {
                continue;
            }
            foreach ($this->blockedDates($range, $today) as $block_date) {
                $grouped_appointments[$block_date][] = $range;
            }
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
            $buffer_blocked = [];
            foreach ($day_slots as $slot_time_str) {
                // $slot_time_str e.g. "09:00:00"
                $slot_start = \DateTime::createFromFormat('Y-m-d H:i:s', "{$date} {$slot_time_str}");
                $slot_end = clone $slot_start;
                $slot_end->modify("+{$service_duration} minutes");
                [$slot_block_start, $slot_block_end] = $this->padRange($slot_start, $slot_end, $buffer_before, $buffer_after);

                // Check overlap with any appointment today, both sides padded by their buffers
                $taken_by_booking = false;
                $taken_by_buffer = false;
                foreach ($day_appointments as $appt) {
                    if (!$appt['start'] || !$appt['end']) continue;
                    // Condition for overlap: slot_start < appt_end AND slot_end > appt_start
                    if ($slot_block_start < $appt['end'] && $slot_block_end > $appt['start']) {
                        // The two appointments themselves overlapping is a taken
                        // time; reaching each other only through a buffer is the
                        // gap around one, which the panels hide instead.
                        if ($slot_start < $appt['raw_end'] && $slot_end > $appt['raw_start']) {
                            $taken_by_booking = true;
                            break;
                        }
                        $taken_by_buffer = true;
                    }
                }

                if ($taken_by_booking) {
                    $blocked[] = $slot_time_str;
                } elseif ($taken_by_buffer) {
                    $buffer_blocked[] = $slot_time_str;
                }
            }

            if (!empty($blocked) || !empty($buffer_blocked)) {
                $booked_timeslots[] = [
                    'date' => $date,
                    'timeslots' => array_values(array_unique($blocked)),
                    // Closed only by the buffer around a booking: the panels
                    // drop these from the list rather than grey them out, so a
                    // customer is not shown times they can never take.
                    'buffer_timeslots' => array_values(array_unique($buffer_blocked))
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
     * @param int $buffer_before Buffer minutes before a slot of the service being booked
     * @param int $buffer_after Buffer minutes after a slot of the service being booked
     * @return array Booked timeslots as array of objects
     */
    public function getBookedTimeslotsByServiceCapacity(
        int $service_id,
        int $service_duration,
        int $max_capacity,
        array $weekly_schedule = [],
        array $special_days = [],
        int $buffer_before = 0,
        int $buffer_after = 0
    ): array {
        if ($max_capacity <= 0) {
            $max_capacity = 1;
        }

        $booked_timeslots = [];
        $today = gmdate('Y-m-d');

        // Query all future appointments for this service (any agent). Yesterday
        // is included only so a buffer running past midnight still counts today.
        $appointments = AppointmentModel::query()
            ->where('service_id', $service_id)
            ->where('date', '>=', gmdate('Y-m-d', strtotime('-1 day')))
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

            $range = $this->blockedRange($appointment, $date, $appointment_start_time, $appointment_end_time);
            if (!$range) {
                continue;
            }
            foreach ($this->blockedDates($range, $today) as $block_date) {
                $grouped_appointments[$block_date][] = $range;
            }
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
            $buffer_blocked = [];
            foreach ($day_slots as $slot_time_str) {
                $slot_start = \DateTime::createFromFormat('Y-m-d H:i:s', "{$date} {$slot_time_str}");
                $slot_end = clone $slot_start;
                $slot_end->modify("+{$service_duration} minutes");
                [$slot_block_start, $slot_block_end] = $this->padRange($slot_start, $slot_end, $buffer_before, $buffer_after);

                // Count concurrent bookings overlapping this slot, both sides
                // padded by their buffers — a booking still in its wrap-up time
                // keeps holding one of the parallel places.
                $overlap_count = 0;
                $booking_overlap_count = 0;
                foreach ($day_appointments as $appt) {
                    if (!$appt['start'] || !$appt['end']) continue;
                    // Condition for overlap: slot_start < appt_end AND slot_end > appt_start
                    if ($slot_block_start < $appt['end'] && $slot_block_end > $appt['start']) {
                        $overlap_count++;
                        if ($slot_start < $appt['raw_end'] && $slot_end > $appt['raw_start']) {
                            $booking_overlap_count++;
                        }
                    }
                }

                // Slot is full only when concurrency reaches the capacity limit
                if ($overlap_count >= $max_capacity) {
                    // Full of bookings is a taken time; full only once the
                    // buffers are counted is the gap around them, which the
                    // panels hide instead of greying out.
                    if ($booking_overlap_count >= $max_capacity) {
                        $blocked[] = $slot_time_str;
                    } else {
                        $buffer_blocked[] = $slot_time_str;
                    }
                }
            }

            if (!empty($blocked) || !empty($buffer_blocked)) {
                $booked_timeslots[] = [
                    'date' => $date,
                    'timeslots' => array_values(array_unique($blocked)),
                    // See getBookedTimeslots(): hidden rather than greyed.
                    'buffer_timeslots' => array_values(array_unique($buffer_blocked))
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
     * Booked timeslots for a group-capacity service.
     *
     * A slot is "full" when the SUM of total_attendees across overlapping
     * non-cancelled bookings of THIS service reaches $max_capacity, instead of
     * closing as soon as one booking exists — unlike getBookedTimeslots() (single
     * occupancy) and getBookedTimeslotsByServiceCapacity() (counts bookings, not
     * people; agent-less only). When $agent_id is given, capacity is scoped per
     * agent (each agent's own session has its own attendee pool); when null, the
     * capacity is shared across all bookings of the service (agent-less group).
     *
     * @param int $service_id Service ID
     * @param int|null $agent_id Agent ID, or null for an agent-less group service
     * @param int $service_duration Duration of the combined service
     * @param int $max_capacity Max attendees per slot (min 1)
     * @param array $weekly_schedule Valid slots for each day
     * @param array $special_days Valid slots for special dates
     * @param int $buffer_before Buffer minutes before a slot of the service being booked
     * @param int $buffer_after Buffer minutes after a slot of the service being booked
     * @return array Booked timeslots as array of objects
     */
    public function getBookedTimeslotsByGroupCapacity(
        int $service_id,
        ?int $agent_id,
        int $service_duration,
        int $max_capacity,
        array $weekly_schedule = [],
        array $special_days = [],
        int $buffer_before = 0,
        int $buffer_after = 0
    ): array {
        if ($max_capacity <= 0) {
            $max_capacity = 1;
        }

        $booked_timeslots = [];
        $today = gmdate('Y-m-d');
        // Yesterday is included only so a buffer running past midnight still
        // blocks today; blockedDates() drops anything that lands before today.
        $since = gmdate('Y-m-d', strtotime('-1 day'));

        $query = AppointmentModel::query()
            ->where('service_id', $service_id)
            ->where('date', '>=', $since);
        if ($agent_id !== null) {
            $query->where('agent_id', $agent_id);
        } else {
            $query->whereNull('agent_id');
        }
        $appointments = $query->get();

        // The attendee pool above only covers this service. The agent can still be
        // occupied by another service at the same time; those bookings share no
        // capacity with the group session, so they block the slot outright.
        $other_service_appointments = [];
        if ($agent_id !== null) {
            $other_service_appointments = AppointmentModel::query()
                ->where('agent_id', $agent_id)
                ->where('date', '>=', $since)
                ->where(function($query) use ($service_id) {
                    $query->where('service_id', '!=', $service_id)
                          ->whereNull('service_id', 'or');
                })
                ->get();
        }

        $grouped_appointments = [];
        foreach ([[$appointments, false], [$other_service_appointments, true]] as [$collection, $is_exclusive]) {
            if (!$collection || $collection->isEmpty()) {
                continue;
            }

            foreach ($collection as $appointment) {
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

                $attendees = (int) ($appointment->total_attendees ?? 1);
                if ($attendees < 1) {
                    $attendees = 1;
                }

                $range = $this->blockedRange($appointment, $date, $appointment_start_time, $appointment_end_time);
                if (!$range) {
                    continue;
                }
                $range['attendees'] = $attendees;
                $range['exclusive'] = $is_exclusive;
                foreach ($this->blockedDates($range, $today) as $block_date) {
                    $grouped_appointments[$block_date][] = $range;
                }
            }
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
            $buffer_blocked = [];
            foreach ($day_slots as $slot_time_str) {
                $slot_start = \DateTime::createFromFormat('Y-m-d H:i:s', "{$date} {$slot_time_str}");
                $slot_end = clone $slot_start;
                $slot_end->modify("+{$service_duration} minutes");
                [$slot_block_start, $slot_block_end] = $this->padRange($slot_start, $slot_end, $buffer_before, $buffer_after);

                // Sum attendees of bookings overlapping this slot
                $attendee_sum = 0;
                $agent_busy = false;
                // Whether what closed the slot is a booking's own time rather
                // than the buffer around it.
                $booking_busy = false;
                foreach ($day_appointments as $appt) {
                    if (!$appt['start'] || !$appt['end']) continue;
                    // Condition for overlap: slot_start < appt_end AND slot_end > appt_start,
                    // both sides padded by their buffers
                    if ($slot_block_start < $appt['end'] && $slot_block_end > $appt['start']) {
                        // The same group session (the bookings themselves overlap):
                        // attendees share its pool.
                        if (!$appt['exclusive'] && $slot_start < $appt['raw_end'] && $slot_end > $appt['raw_start']) {
                            $attendee_sum += $appt['attendees'];
                            continue;
                        }
                        // Another service on the agent's calendar, or a neighbouring
                        // session of this one reaching in through a buffer: no shared
                        // capacity, the slot is gone whatever the group has room for.
                        $agent_busy = true;
                        $booking_busy = $slot_start < $appt['raw_end'] && $slot_end > $appt['raw_start'];
                        break;
                    }
                }

                // Slot is full only when attendee count reaches the capacity limit
                if ($agent_busy || $attendee_sum >= $max_capacity) {
                    // A session of this service the customer could have joined,
                    // or another booking on the agent's own time, is a taken
                    // slot. A neighbouring booking reaching in only through a
                    // buffer is the gap around it, which the panels hide.
                    if ($attendee_sum > 0 || $booking_busy) {
                        $blocked[] = $slot_time_str;
                    } else {
                        $buffer_blocked[] = $slot_time_str;
                    }
                }
            }

            if (!empty($blocked) || !empty($buffer_blocked)) {
                $booked_timeslots[] = [
                    'date' => $date,
                    'timeslots' => array_values(array_unique($blocked)),
                    // See getBookedTimeslots(): hidden rather than greyed.
                    'buffer_timeslots' => array_values(array_unique($buffer_blocked))
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
     * The stretch of the agent's schedule an existing booking holds: its own
     * interval padded by the buffer snapshotted on its row.
     *
     * 'start'/'end' are the padded range used for overlap; 'raw_start'/'raw_end'
     * are the booking itself, which group capacity needs to tell the same
     * session apart from a neighbouring one.
     *
     * @param AppointmentModel $appointment Booking row
     * @param string $date Booking date (Y-m-d)
     * @param string $start_time Booking start (H:i:s)
     * @param string $end_time Booking end (H:i:s)
     * @return array|null Range, or null when the times do not parse
     */
    private function blockedRange(AppointmentModel $appointment, string $date, string $start_time, string $end_time): ?array
    {
        $raw_start = \DateTime::createFromFormat('Y-m-d H:i:s', "{$date} {$start_time}");
        $raw_end = \DateTime::createFromFormat('Y-m-d H:i:s', "{$date} {$end_time}");
        if (!$raw_start || !$raw_end) {
            return null;
        }

        [$start, $end] = $this->padRange(
            $raw_start,
            $raw_end,
            ServiceService::appointmentBufferBeforeMinutes($appointment),
            ServiceService::appointmentBufferAfterMinutes($appointment)
        );

        return [
            'start' => $start,
            'end' => $end,
            'raw_start' => $raw_start,
            'raw_end' => $raw_end,
        ];
    }

    /**
     * Every date, from $today on, that a blocked range touches — a buffer can
     * run past midnight into the day before or after the booking itself.
     *
     * @param array $range Range from blockedRange()
     * @param string $today Earliest date to return (Y-m-d)
     * @return array Dates (Y-m-d)
     */
    private function blockedDates(array $range, string $today): array
    {
        // The range is half-open, so one ending exactly at midnight does not
        // reach into the next day.
        $last = $range['end'] > $range['start']
            ? (clone $range['end'])->modify('-1 second')->format('Y-m-d')
            : $range['start']->format('Y-m-d');

        $dates = [];
        $cursor = (clone $range['start'])->setTime(0, 0);
        while ($cursor->format('Y-m-d') <= $last) {
            $date = $cursor->format('Y-m-d');
            if ($date >= $today) {
                $dates[] = $date;
            }
            $cursor->modify('+1 day');
        }

        return $dates;
    }

    /**
     * Copies of an interval widened by a buffer on each side.
     *
     * @param \DateTime $start Interval start
     * @param \DateTime $end Interval end
     * @param int $before Minutes to move the start earlier
     * @param int $after Minutes to move the end later
     * @return array [\DateTime $start, \DateTime $end]
     */
    private function padRange(\DateTime $start, \DateTime $end, int $before, int $after): array
    {
        $padded_start = clone $start;
        $padded_end = clone $end;

        if ($before > 0) {
            $padded_start->modify("-{$before} minutes");
        }
        if ($after > 0) {
            $padded_end->modify("+{$after} minutes");
        }

        return [$padded_start, $padded_end];
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
    private static function getDayFromSchedule(?array $schedule, int $day_index): ?array
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
    private static function isDayOff(?array $day_data): bool
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
    private static function intersectTimeslots(array $agent_slots, array $service_slots, array $global_slots): array
    {
        // Extract time windows for each (should be [start, end])
        $agent = self::extractTimeWindow($agent_slots);
        $service = self::extractTimeWindow($service_slots);
        $global = self::extractTimeWindow($global_slots);

        // If any is missing, treat as unavailable
        if (empty($agent) || empty($service) || empty($global)) {
            return [];
        }

        // Find the intersection
        $start = self::maxTime([$agent[0], $service[0], $global[0]]);
        $end = self::minTime([$agent[1], $service[1], $global[1]]);

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
    private static function extractTimeWindow(array $slots): array
    {
        if (count($slots) < 2) {
            return [];
        }
        $start = self::extractTimeFromString($slots[0]);
        $end = self::extractTimeFromString($slots[1]);
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
    private static function maxTime(array $times): ?string
    {
        $max = null;
        foreach ($times as $t) {
            if (!self::isValidTimeFormat($t)) continue;
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
    private static function minTime(array $times): ?string
    {
        $min = null;
        foreach ($times as $t) {
            if (!self::isValidTimeFormat($t)) continue;
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
    private static function extractTimeOnly(array $slots): array
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
    private static function mergeBreaks(array $agent_breaks, array $service_breaks, array $global_breaks): array
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
            $start_time = self::extractTimeFromString($break[0]);
            $end_time = self::extractTimeFromString($break[1]);

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
    private static function extractTimeFromString(string $datetime): string
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
     * Whether an agent must already provide the service to return its slots.
     *
     * False on this admin-only route: the admin booking form offers every agent, and
     * saving the booking assigns the service to the one picked, so slots have to load
     * before that relation exists. The public subclass keeps it on.
     *
     * @return bool
     */
    protected function enforcesAgentServiceRelation(): bool
    {
        return false;
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
                if (
                    $this->enforcesAgentServiceRelation()
                    && !ServiceAgentRelationModel::relationExists((int)$agent_id, (int)$service_id)
                ) {
                    return rox_appointment_booking_rest_response(
                        data: ['error' => 'The selected agent does not provide this service'],
                        status: 'error',
                        code: 400
                    );
                }
            }

            // Get service duration (minutes)
            $service_duration = $service_service->getServiceDuration($service_id);

            // Add extra services duration. Public input: whole positive ids only,
            // each counted once, and a sane cap on how many are looked up.
            if (!empty($extra_services_param)) {
                $extra_services = is_array($extra_services_param) ? $extra_services_param : explode(',', (string) $extra_services_param);
                $extra_services = array_slice(array_unique(array_filter(array_map('intval', array_filter($extra_services, 'is_scalar')))), 0, 20);
                foreach ($extra_services as $extra_id) {
                    if ($extra_id > 0) {
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
            // Buffer time (Pro) of the service being booked: a candidate slot needs
            // this much clear schedule around it on top of its own duration. Each
            // existing booking brings its own buffer, read off its row.
            $buffer_before = ServiceService::bufferBeforeMinutes($service_exists);
            $buffer_after = ServiceService::bufferAfterMinutes($service_exists);

            // The times always sit one whole booking apart: buffer before +
            // service + chosen extras + buffer after, so one booking's wrap-up
            // and the next one's preparation meet exactly and no minute of the
            // agent's day is stranded (owner decision, 2026-09-24). Without a
            // buffer this is the appointment itself, as always. Whether the
            // label carries the wrap-up is the service's own switch, further
            // down in `slot_range_minutes`. The appointment and its wrap-up
            // still have to fit inside the working day and clear of the breaks
            // (generateTimeslots()).
            $slot_step = (int) $service_duration + $buffer_before + $buffer_after;

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
                $slot_step,
                (int) $service_duration,
                $buffer_before,
                $buffer_after
            );

            // Merge holidays ($agent_holidays is [] for agent-less → global only)
            $final_holidays = $this->mergeHolidays($agent_holidays, $global_holidays);

            // Group-capacity service: a slot stays open (shared across unrelated
            // customers) until the sum of attendees reaches service.max_capacity,
            // instead of closing after the first booking. Takes priority over the
            // agent-less multi-booking model below when both happen to apply.
            $is_group = defined('ROX_APPOINTMENT_BOOKING_PRO_VERSION') && ($service_exists->capacity === 'group');

            if ($is_group) {
                $special_days = $is_agent_less ? [] : $this->getProcessedSpecialDays((int)$agent_id, $slot_step, (int)$service_duration, $buffer_before, $buffer_after);

                $max_capacity = (int) $service_exists->max_capacity;
                if ($max_capacity <= 0) {
                    $max_capacity = 1;
                }

                $booked_timeslots = $this->getBookedTimeslotsByGroupCapacity(
                    (int)$service_id,
                    $is_agent_less ? null : (int)$agent_id,
                    (int)$service_duration,
                    $max_capacity,
                    $final_weekly_schedule,
                    $special_days,
                    $buffer_before,
                    $buffer_after
                );
            } elseif ($is_agent_less) {
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
                    $special_days,
                    $buffer_before,
                    $buffer_after
                );
            } else {
                // Get and process special days
                $special_days = $this->getProcessedSpecialDays((int)$agent_id, $slot_step, (int)$service_duration, $buffer_before, $buffer_after);

                // Get booked timeslots for this agent with overlap logic
                $booked_timeslots = $this->getBookedTimeslots((int)$agent_id, (int)$service_duration, $final_weekly_schedule, $special_days, $buffer_before, $buffer_after);
            }

            $final_schedule_holidays = [
                'weekly_schedule' => $final_weekly_schedule,
                'holidays' => $final_holidays,
                'booked_timeslots' => $booked_timeslots,
                'special_days' => $special_days,
                // The appointment itself (service + extras) — what the panel shows
                // as the booking's length. Buffers are the agent's, never shown.
                'slot_duration' => (int)$service_duration,
                // The weekly schedule is a per-weekday template with no notion of
                // "now", so the panels apply this window on top of it to hide slots
                // that are too soon or too far out. Both ends are re-checked by the
                // write paths on submit. 0 means that end is unbounded.
                'minimum_advance_minutes' => ServiceService::minimumAdvanceMinutes($service_exists),
                'maximum_advance_minutes' => ServiceService::maximumAdvanceMinutes($service_exists),
                // Already applied to booked_timeslots above; exposed for display only.
                'buffer_before_minutes' => $buffer_before,
                'buffer_after_minutes' => $buffer_after,
                // Label each time as "8:00 - 8:15" instead of "8:00".
                'show_slot_time_range' => ServiceService::showSlotTimeRange(),
                // How long that label runs: the appointment, extras included,
                // unless something extends it — "Include buffer in displayed
                // slot time" reads the label to the end of the wrap-up instead.
                // The preparation before the slot is never shown.
                'slot_range_minutes' => ServiceService::slotLabelMinutes(
                    (int) $service_duration,
                    $service_exists,
                    $buffer_after
                )
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
     * @param int $slot_step Minutes from one offered time to the next
     * @param int $appointment_duration Booking's own length (service + extras); 0 falls back to $slot_step
     * @param int $buffer_before Preparation minutes before the booking
     * @param int $buffer_after Wrap-up minutes after the booking
     * @return array Processed special days with pre-generated timeslots
     */
    private function getProcessedSpecialDays(int $agent_id, int $slot_step, int $appointment_duration = 0, int $buffer_before = 0, int $buffer_after = 0): array
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
                    $timeslots = $this->generateTimeslots($schedule, $data['breaks'] ?? [], $slot_step, $appointment_duration, $buffer_before, $buffer_after);
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

        // Slots feed the booking form, which is reached to create as well as to
        // edit — either grant is enough to read them.
        if (!is_user_logged_in()) {
            return false;
        }

        if (!Permissions::can('appointment.create') && !Permissions::can('appointment.edit')) {
            return false;
        }

        return true;
    }
}
