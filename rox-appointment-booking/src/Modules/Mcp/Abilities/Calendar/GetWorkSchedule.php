<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Calendar;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Modules\Mcp\Abilities\AbstractAbility;

/**
 * Class GetWorkSchedule
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Calendar
 * @description Returns weekly working hours for the business, an agent or a service.
 */
class GetWorkSchedule extends AbstractAbility
{
    protected string $name = 'get-work-schedule';

    protected string $capability = 'calendar.view';

    public function label(): string
    {
        return __('Get Work Schedule', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return "Returns weekly working hours. With agent_id: that agent's own schedule, special days and holidays. With service_id: the service's own schedule (empty means it follows the business hours). With neither: the business-wide hours. Times are in the site timezone.";
    }

    protected function properties(): array
    {
        return [
            'agent_id'   => ['type' => 'integer', 'minimum' => 1, 'description' => 'Agent whose schedule to return.'],
            'service_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Service whose schedule to return.'],
        ];
    }

    protected function run(array $input)
    {
        $agentId   = absint($input['agent_id'] ?? 0);
        $serviceId = absint($input['service_id'] ?? 0);

        if ($agentId) {
            $result = $this->dispatch('GET', 'agent/' . $agentId);
            if (is_wp_error($result)) {
                return $result;
            }
            $agent = (array) $result['data'];

            return [
                'scope'           => 'agent',
                'agent_id'        => $agentId,
                'weekly_schedule' => $agent['weekly_schedule'] ?? [],
                'special_days'    => $agent['special_days'] ?? [],
                'holiday'         => $agent['holiday'] ?? [],
            ];
        }

        if ($serviceId) {
            $result = $this->dispatch('GET', 'service/' . $serviceId);
            if (is_wp_error($result)) {
                return $result;
            }

            return [
                'scope'           => 'service',
                'service_id'      => $serviceId,
                'weekly_schedule' => $result['data']['weekly_schedule'] ?? [],
            ];
        }

        $result = $this->dispatch('GET', 'menueapi/working-hours/get');

        return is_wp_error($result) ? $result : array_merge(['scope' => 'business'], (array) $result['data']);
    }
}
