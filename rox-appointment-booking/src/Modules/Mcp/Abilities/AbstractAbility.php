<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities;

defined('ABSPATH') || exit;

use WP_Error;
use WP_REST_Request;
use RoxAppointmentBooking\Modules\Mcp\Services\McpSettings;
use RoxAppointmentBooking\Supports\Access\Permissions;

/**
 * Class AbstractAbility
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities
 * @description Base for one WordPress Ability exposed to AI clients over MCP.
 */
abstract class AbstractAbility
{
    /**
     * Ability category and id prefix.
     */
    public const CATEGORY = 'rox-appointment-booking';

    /**
     * Fields never handed to an AI client, at any depth.
     */
    protected const HIDDEN_FIELDS = ['internal_notes', 'password', 'user_pass', 'api_key', 'secret'];

    /**
     * Ability name without the category prefix, e.g. 'list-appointments'.
     *
     * @var string
     */
    protected string $name = '';

    /**
     * Capability checked through Permissions::can(), e.g. 'appointment.view'.
     *
     * @var string
     */
    protected string $capability = '';

    /**
     * Whether the ability only reads data.
     *
     * @var bool
     */
    protected bool $readOnly = true;

    /**
     * Whether the ability deletes data or moves money.
     *
     * @var bool
     */
    protected bool $destructive = false;

    /**
     * Whether repeating the same call has no further effect.
     *
     * @var bool
     */
    protected bool $idempotent = true;

    /**
     * Human-readable label.
     *
     * @return string
     */
    abstract public function label(): string;

    /**
     * Description written for the model: what it returns, when to use it, side effects.
     *
     * @return string
     */
    abstract public function description(): string;

    /**
     * JSON Schema of the input object's properties.
     *
     * @return array
     */
    abstract protected function properties(): array;

    /**
     * Run the ability for validated input.
     *
     * @param array $input
     * @return array|WP_Error
     */
    abstract protected function run(array $input);

    /**
     * Required input properties.
     *
     * @return string[]
     */
    protected function required(): array
    {
        return [];
    }

    /**
     * Full ability id, e.g. 'rox-appointment-booking/list-appointments'.
     *
     * @return string
     */
    public function id(): string
    {
        return self::CATEGORY . '/' . $this->name;
    }

    /**
     * Gating level: 'read', 'edit' or 'delete'.
     *
     * @return string
     */
    public function level(): string
    {
        if ($this->destructive) {
            return 'delete';
        }

        return $this->readOnly ? 'read' : 'edit';
    }

    /**
     * Whether the MCP toggles currently allow this ability's level.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        // The server toggle also gates the Abilities API mirror, so off means unreachable.
        if (!McpSettings::isOn('mcp_abilities_enable') || !McpSettings::isOn('mcp_server_enable')) {
            return false;
        }

        switch ($this->level()) {
            case 'delete':
                return McpSettings::isOn('mcp_abilities_delete_enable');
            case 'edit':
                return McpSettings::isOn('mcp_abilities_edit_enable');
            default:
                return true;
        }
    }

    /**
     * Permission callback: toggles first, then the plugin's own capability check.
     *
     * @return bool
     */
    public function permission(): bool
    {
        return is_user_logged_in() && $this->isEnabled() && Permissions::can($this->capability);
    }

    /**
     * Execute callback.
     *
     * @param mixed $input
     * @return array|WP_Error
     */
    public function execute($input = null)
    {
        return $this->run(is_array($input) ? $input : []);
    }

    /**
     * Input JSON Schema as PHP arrays, usable by rest_validate_value_from_schema().
     *
     * @return array
     */
    public function inputSchema(): array
    {
        $schema = [
            'type'                 => 'object',
            'properties'           => $this->properties(),
            'additionalProperties' => false,
        ];

        if ($this->required()) {
            $schema['required'] = $this->required();
        }

        return $schema;
    }

    /**
     * Behaviour hints; concrete booleans, since strict clients reject nulls.
     *
     * @return array{readonly: bool, destructive: bool, idempotent: bool}
     */
    public function annotations(): array
    {
        return [
            'readonly'    => $this->readOnly && !$this->destructive,
            'destructive' => $this->destructive,
            'idempotent'  => $this->idempotent,
        ];
    }

    /**
     * Arguments for wp_register_ability().
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'label'               => $this->label(),
            'description'         => $this->description(),
            'category'            => self::CATEGORY,
            'permission_callback' => [$this, 'permission'],
            'execute_callback'    => [$this, 'execute'],
            'input_schema'        => self::normalizeSchema(array_merge($this->inputSchema(), ['default' => []])),
            'output_schema'       => ['type' => 'object'],
            'meta'                => [
                'show_in_rest' => true,
                'annotations'  => array_merge($this->annotations(), ['openWorldHint' => false]),
            ],
        ];
    }

    /**
     * Empty `properties` must encode as `{}`; strict clients reject `[]` and drop every tool.
     *
     * @param array $schema
     * @return array
     */
    public static function normalizeSchema(array $schema): array
    {
        if (array_key_exists('properties', $schema)) {
            if (empty($schema['properties'])) {
                $schema['properties'] = new \stdClass();
            } elseif (is_array($schema['properties'])) {
                foreach ($schema['properties'] as $key => $property) {
                    if (is_array($property)) {
                        $schema['properties'][$key] = self::normalizeSchema($property);
                    }
                }
            }
        }

        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = self::normalizeSchema($schema['items']);
        }

        return $schema;
    }

    /**
     * Shared page/per_page input properties.
     *
     * @return array
     */
    protected function paginationProperties(): array
    {
        return [
            'page'     => ['type' => 'integer', 'minimum' => 1, 'default' => 1, 'description' => 'Page number, starting at 1.'],
            'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20, 'description' => 'Items per page (max 100).'],
        ];
    }

    /**
     * Single required id input property.
     *
     * @param string $description
     * @return array
     */
    protected function idProperty(string $description): array
    {
        return ['id' => ['type' => 'integer', 'minimum' => 1, 'description' => $description]];
    }

    /**
     * Page and per_page from input, clamped.
     *
     * @param array $input
     * @return array{page: int, per_page: int}
     */
    protected function pageParams(array $input): array
    {
        return [
            'page'     => max(1, absint($input['page'] ?? 1)),
            'per_page' => min(100, max(1, absint($input['per_page'] ?? 20))),
        ];
    }

    /**
     * Uniform paginated output from a dispatched list response.
     *
     * @param array $result  Output of dispatch().
     * @param array $paging  Output of pageParams().
     * @return array
     */
    protected function paged(array $result, array $paging): array
    {
        $items = array_values((array) $result['data']);
        $meta  = $result['options']['meta_data'] ?? $result['options'];
        $total = (int) ($meta['total'] ?? count($items));

        return [
            'items'       => $items,
            'total'       => $total,
            'page'        => $paging['page'],
            'per_page'    => $paging['per_page'],
            'total_pages' => (int) ($meta['total_pages'] ?? max(1, (int) ceil($total / $paging['per_page']))),
        ];
    }

    /**
     * A Y-m-d date from input, or '' when the value is not one.
     *
     * @param mixed $value
     * @return string
     */
    protected function date($value): string
    {
        $value = sanitize_text_field((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }

    /**
     * Call one of the plugin's own REST routes as the current user.
     *
     * Reusing the handlers keeps permission, scoping, field stripping and
     * notifications identical to the admin dashboard.
     *
     * @param string $method
     * @param string $route  Route below the plugin namespace, e.g. 'appointment/12'.
     * @param array  $params
     * @return array{data: mixed, options: array}|WP_Error
     */
    protected function dispatch(string $method, string $route, array $params = [])
    {
        $request = new WP_REST_Request($method, '/' . ROX_APPOINTMENT_BOOKING_TEXT_DOMAIN . '/v1/' . ltrim($route, '/'));
        $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));

        if ($method === 'GET') {
            $request->set_query_params($params);
        } else {
            // Several save handlers read get_json_params(), so send JSON like the dashboard does.
            $request->set_header('Content-Type', 'application/json');
            $request->set_body(wp_json_encode($params));
        }

        $response = rest_do_request($request);
        $body     = $response->get_data();
        $status   = $response->get_status();

        if ($status >= 400) {
            $message = is_array($body) ? ($body['message'] ?? '') : '';
            if (is_array($message)) {
                $parts = [];
                array_walk_recursive($message, function ($part) use (&$parts) {
                    $parts[] = (string) $part;
                });
                $message = implode(' ', $parts);
            }

            return new WP_Error(
                'rox_appointment_booking_mcp_' . $status,
                $message ?: __('The request could not be completed.', 'rox-appointment-booking'),
                ['status' => $status]
            );
        }

        return [
            'data'    => self::strip(is_array($body) ? ($body['data'] ?? null) : $body),
            'options' => is_array($body) && is_array($body['options'] ?? null) ? $body['options'] : [],
        ];
    }

    /**
     * Remove hidden fields at any depth.
     *
     * @param mixed $data
     * @return mixed
     */
    protected static function strip($data)
    {
        if (!is_array($data)) {
            return $data;
        }

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array($key, self::HIDDEN_FIELDS, true)) {
                unset($data[$key]);
                continue;
            }
            $data[$key] = self::strip($value);
        }

        return $data;
    }
}
