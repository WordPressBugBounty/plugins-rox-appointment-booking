<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Service;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Modules\Mcp\Abilities\AbstractWriteAbility;

/**
 * Class CreateService
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Service
 * @description Adds a bookable service.
 */
class CreateService extends AbstractWriteAbility
{
    protected string $name = 'create-service';

    protected string $capability = 'service.create';

    public function label(): string
    {
        return __('Create Service', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return 'Adds a bookable service. Title must be unique. Price is in the site currency, duration in minutes. Assign agents with agent_ids (see list-agents) and categories with category_ids (see list-service-categories); without a category it goes to "Uncategorized". New services are active unless status says otherwise. Returns the new service.';
    }

    protected function properties(): array
    {
        return $this->fields();
    }

    protected function required(): array
    {
        return ['title', 'price', 'duration'];
    }

    protected function run(array $input)
    {
        $result = $this->dispatch('POST', 'service', $this->payload($input));
        if (is_wp_error($result)) {
            return $result;
        }

        $id = (int) ($result['data']['id'] ?? 0);

        return $id ? $this->fresh($id) : (array) $result['data'];
    }

    /**
     * Editable service fields.
     *
     * @return array
     */
    protected function fields(): array
    {
        return [
            'title'        => ['type' => 'string', 'minLength' => 1, 'description' => 'Service name.'],
            'description'  => ['type' => 'string', 'description' => 'Description shown on the booking panel.'],
            'price'        => ['type' => 'number', 'minimum' => 0, 'description' => 'Price in the site currency.'],
            'duration'     => ['type' => 'integer', 'minimum' => 1, 'description' => 'Length in minutes.'],
            'status'       => ['type' => 'string', 'enum' => ['active', 'inactive'], 'description' => 'Whether it can be booked.'],
            'color'        => ['type' => 'string', 'description' => 'Calendar colour as a hex code, e.g. #3560fb.'],
            'category_ids' => $this->idsProperty('Categories; replaces the current ones.'),
            'agent_ids'    => $this->idsProperty('Agents who provide it; replaces the current ones.'),
        ];
    }

    /**
     * Sanitised service fields present in the input.
     *
     * @param array $input
     * @return array
     */
    protected function payload(array $input): array
    {
        $payload = $this->texts($input, ['title', 'status']);

        if (array_key_exists('description', $input)) {
            $payload['description'] = sanitize_textarea_field((string) $input['description']);
        }
        if (array_key_exists('price', $input)) {
            $payload['price'] = max(0, (float) $input['price']);
        }
        if (array_key_exists('duration', $input)) {
            $payload['duration'] = absint($input['duration']);
        }
        if (array_key_exists('color', $input)) {
            $payload['color'] = (string) sanitize_hex_color((string) $input['color']);
        }
        foreach (['category_ids', 'agent_ids'] as $key) {
            if (array_key_exists($key, $input)) {
                $payload[$key] = $this->ids($input[$key]);
            }
        }

        return $payload;
    }

    /**
     * The service as get-service returns it.
     *
     * @param int $id
     * @return array|\WP_Error
     */
    protected function fresh(int $id)
    {
        $result = $this->dispatch('GET', 'service/' . $id);

        return is_wp_error($result) ? $result : (array) $result['data'];
    }
}
