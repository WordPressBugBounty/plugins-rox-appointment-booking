<?php

namespace RoxAppointmentBooking\Modules\Settings\Services;

/**
 * Class SettingsService
 *
 * @package RoxAppointmentBooking\Modules\Settings\Services
 * @description Handles settings-related business logic for holidays and weekly schedules.
 */
class SettingsService
{
    /**
     * Whether this class should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * Get holidays from database
     * 
     * @return array Array of holiday objects with date and description
     */
    public function getHolidays(): array
    {
        $holidays = get_option('rox_appointment_booking_holidays', []);
        return is_array($holidays) ? $holidays : [];
    }

    /**
     * Get weekly schedule from database
     * 
     * @return array Array of weekly schedule data
     */
    public function getWeeklySchedule(): array
    {
        $weekly_schedule_json = get_option('rox_appointment_booking_weekly_schedule', '[]');
        $weekly_schedule = json_decode($weekly_schedule_json, true);
        return is_array($weekly_schedule) ? $weekly_schedule : [];
    }

    /**
     * Save holidays to database
     * 
     * @param array $holidays Array of holiday data
     * @return bool Success status
     */
    public function saveHolidays(array $holidays): bool
    {
        return update_option('rox_appointment_booking_holidays', $holidays);
    }

    /**
     * Save weekly schedule to database
     * 
     * @param array $weekly_schedule Array of weekly schedule data
     * @return bool Success status
     */
    public function saveWeeklySchedule(array $weekly_schedule): bool
    {
        $json_schedule = json_encode($weekly_schedule);
        return update_option('rox_appointment_booking_weekly_schedule', $json_schedule);
    }

    /**
     * Delete all holidays
     * 
     * @return bool Success status
     */
    public function deleteAllHolidays(): bool
    {
        return delete_option('rox_appointment_booking_holidays');
    }

    /**
     * Delete weekly schedule
     * 
     * @return bool Success status
     */
    public function deleteWeeklySchedule(): bool
    {
        return delete_option('rox_appointment_booking_weekly_schedule');
    }

    /**
     * Check if a specific date is a holiday
     * 
     * @param string $date Date in Y-m-d format
     * @return bool True if date is a holiday
     */
    public function isHoliday(string $date): bool
    {
        $holidays = $this->getHolidays();
        
        foreach ($holidays as $holiday) {
            // Handle both formats: string date or object with date property
            // Holidays can be stored as:
            // - Array of date strings: ["2025-12-31", "2025-01-01"]
            // - Array of objects: [{"date": "2025-12-31", "description": "..."}]
            $holidayDate = is_array($holiday) ? ($holiday['date'] ?? null) : $holiday;
            
            if ($holidayDate === $date) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get working days from weekly schedule
     * 
     * @return array Array of working day names
     */
    public function getWorkingDays(): array
    {
        $weekly_schedule = $this->getWeeklySchedule();
        $working_days = [];
        
        foreach ($weekly_schedule as $day) {
            if (isset($day['day_off']) && $day['day_off'] === false && isset($day['day_name'])) {
                $working_days[] = $day['day_name'];
            }
        }
        
        return $working_days;
    }
}
