<?php

namespace RoxAppointmentBooking\Modules\Email;

use RoxAppointmentBooking\Supports\Abstracts\AbstractLoader;
use RoxAppointmentBooking\Modules\Email\Services\ReminderCronService;

/**
 * Class Provider
 *
 * @package RoxAppointmentBooking\Modules\Email
 * @description Registers the e-mail notification module.
 */
class Provider extends AbstractLoader
{
    /**
     * Provider constructor.
     *
     * Loads module service and REST classes. In Services/ only
     * EmailEventDispatcher is $loadable; the rest are static helpers.
     *
     * @return void
     */
    public function __construct()
    {
        $this->classLoader([
            plugin_dir_path(__FILE__) . 'Services',
            plugin_dir_path(__FILE__) . 'REST',
        ]);
        $this->registerReminderCron();
    }

    /**
     * Register the hourly WP-Cron scan that sends appointment reminders.
     *
     * Scheduled unconditionally — the licence is checked when the scan runs, so
     * activating Pro does not depend on the schedule being rebuilt.
     *
     * @return void
     */
    private function registerReminderCron(): void
    {
        add_action(ReminderCronService::HOOK, [ReminderCronService::class, 'run']);

        if (!wp_next_scheduled(ReminderCronService::HOOK)) {
            wp_schedule_event(time(), 'hourly', ReminderCronService::HOOK);
        }
    }
}
