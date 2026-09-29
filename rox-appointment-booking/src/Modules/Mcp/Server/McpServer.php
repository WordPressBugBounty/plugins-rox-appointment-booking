<?php

namespace RoxAppointmentBooking\Modules\Mcp\Server;

defined('ABSPATH') || exit;

use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use RoxAppointmentBooking\Modules\Mcp\Abilities\AbstractAbility;
use RoxAppointmentBooking\Modules\Mcp\McpBootstrap;
use RoxAppointmentBooking\Modules\Mcp\Services\McpSettings;
use RoxAppointmentBooking\Supports\Access\Permissions;

/**
 * Class McpServer
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Server
 * @description Built-in MCP server: JSON-RPC over the Streamable HTTP transport, stateless, no add-on plugin needed.
 */
class McpServer
{
    /**
     * Protocol revisions this server speaks, newest first.
     */
    public const PROTOCOL_VERSIONS = ['2025-06-18', '2025-03-26', '2024-11-05'];

    /**
     * JSON-RPC error codes.
     */
    private const PARSE_ERROR      = -32700;
    private const INVALID_REQUEST  = -32600;
    private const METHOD_NOT_FOUND = -32601;
    private const INVALID_PARAMS   = -32602;

    /**
     * Most messages one batch may carry.
     */
    private const MAX_BATCH = 20;

    /**
     * McpServer constructor.
     *
     * @return void
     */
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'registerRoute']);
    }

    /**
     * Register the endpoint only while the server toggle is on, so it 404s otherwise.
     *
     * @return void
     */
    public function registerRoute(): void
    {
        if (!McpSettings::isOn('mcp_server_enable')) {
            return;
        }

        register_rest_route(ROX_APPOINTMENT_BOOKING_TEXT_DOMAIN . '/v1', '/' . McpSettings::SERVER_ROUTE, [
            'methods'             => ['GET', 'POST', 'DELETE'],
            'callback'            => [$this, 'handle'],
            'permission_callback' => [$this, 'permission'],
        ]);
    }

    /**
     * Only dashboard users may connect; customers are turned away here.
     *
     * @return true|WP_Error
     */
    public function permission()
    {
        if (!is_user_logged_in()) {
            return new WP_Error('rest_not_logged_in', __('Sign in with an Application Password.', 'rox-appointment-booking'), ['status' => 401]);
        }

        if (!Permissions::can('panel.access')) {
            return new WP_Error('rest_forbidden', __('This user may not use the MCP server.', 'rox-appointment-booking'), ['status' => 403]);
        }

        return true;
    }

    /**
     * Handle one HTTP request carrying a JSON-RPC message or batch.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        // Stateless: no server-sent event stream and no session to end.
        if ($request->get_method() !== 'POST') {
            $response = new WP_REST_Response(null, 405);
            $response->header('Allow', 'POST');

            return $response;
        }

        $message = json_decode((string) $request->get_body(), true);
        if (!is_array($message)) {
            return new WP_REST_Response($this->error(null, self::PARSE_ERROR, 'Parse error'), 400);
        }

        // A batch is a JSON array; array_is_list() needs PHP 8.1.
        if ($message === [] || array_keys($message) === range(0, count($message) - 1)) {
            if ($message === [] || count($message) > self::MAX_BATCH) {
                return new WP_REST_Response($this->error(null, self::INVALID_REQUEST, 'Invalid Request'), 400);
            }

            $replies = array_values(array_filter(array_map([$this, 'reply'], $message)));

            return $replies ? new WP_REST_Response($replies, 200) : new WP_REST_Response(null, 202);
        }

        $reply = $this->reply($message);

        return $reply ? new WP_REST_Response($reply, 200) : new WP_REST_Response(null, 202);
    }

    /**
     * Answer one JSON-RPC message; notifications and responses get no reply.
     *
     * @param mixed $message
     * @return array|null
     */
    private function reply($message): ?array
    {
        if (!is_array($message) || ($message['jsonrpc'] ?? '') !== '2.0' || !isset($message['method']) || !is_string($message['method'])) {
            // A client response to us, which this server never asks for.
            if (is_array($message) && (isset($message['result']) || isset($message['error']))) {
                return null;
            }

            return $this->error($message['id'] ?? null, self::INVALID_REQUEST, 'Invalid Request');
        }

        if (!array_key_exists('id', $message)) {
            return null;
        }

        $id     = $message['id'];
        $params = is_array($message['params'] ?? null) ? $message['params'] : [];

        switch ($message['method']) {
            case 'initialize':
                return $this->result($id, $this->initialize($params));
            case 'ping':
                return $this->result($id, new \stdClass());
            case 'tools/list':
                return $this->result($id, ['tools' => $this->listTools()]);
            case 'tools/call':
                return $this->callTool($id, $params);
            case 'resources/list':
                return $this->result($id, ['resources' => []]);
            case 'resources/templates/list':
                return $this->result($id, ['resourceTemplates' => []]);
            case 'prompts/list':
                return $this->result($id, ['prompts' => []]);
            default:
                return $this->error($id, self::METHOD_NOT_FOUND, 'Method not found: ' . $message['method']);
        }
    }

    /**
     * Result of the initialize handshake.
     *
     * @param array $params
     * @return array
     */
    private function initialize(array $params): array
    {
        $requested = (string) ($params['protocolVersion'] ?? '');

        $branding = rox_appointment_booking_branding();
        $title    = !empty($branding['enabled']) && !empty($branding['companyName'])
            ? $branding['companyName']
            : 'Rox Appointment Booking';

        return [
            'protocolVersion' => in_array($requested, self::PROTOCOL_VERSIONS, true) ? $requested : self::PROTOCOL_VERSIONS[0],
            'capabilities'    => ['tools' => ['listChanged' => false]],
            'serverInfo'      => [
                'name'    => McpBootstrap::SERVER_ID,
                'title'   => $title,
                'version' => ROX_APPOINTMENT_BOOKING_VERSION,
            ],
            'instructions'    => 'Tools for an appointment booking business: appointments, availability, customers, services, agents and orders. Dates are Y-m-d and times H:i in the site timezone; money is in the site currency. Check get-available-slots before booking, and confirm with the user before any delete or refund.',
        ];
    }

    /**
     * Tools the toggles currently allow.
     *
     * @return array<string, AbstractAbility> Keyed by tool name.
     */
    private function tools(): array
    {
        $enabled = [];
        foreach (McpBootstrap::abilities() as $ability) {
            if ($ability->isEnabled()) {
                $enabled[$ability->id()] = $ability;
            }
        }

        $allowed = (array) apply_filters('rox_appointment_booking_mcp_tool_ids', array_keys($enabled));

        $tools = [];
        foreach ($allowed as $id) {
            if (isset($enabled[$id])) {
                $tools[self::toolName($enabled[$id])] = $enabled[$id];
            }
        }

        return $tools;
    }

    /**
     * Tool name on the wire: the ability name without the category prefix.
     *
     * @param AbstractAbility $ability
     * @return string
     */
    public static function toolName(AbstractAbility $ability): string
    {
        return substr($ability->id(), strlen(AbstractAbility::CATEGORY) + 1);
    }

    /**
     * Tool definitions for tools/list.
     *
     * @return array
     */
    private function listTools(): array
    {
        $list = [];
        foreach ($this->tools() as $name => $ability) {
            $hints = $ability->annotations();

            $list[] = [
                'name'        => $name,
                'title'       => $ability->label(),
                'description' => $ability->description(),
                'inputSchema' => AbstractAbility::normalizeSchema($ability->inputSchema()),
                'annotations' => [
                    'title'           => $ability->label(),
                    'readOnlyHint'    => $hints['readonly'],
                    'destructiveHint' => $hints['destructive'],
                    'idempotentHint'  => $hints['idempotent'],
                    'openWorldHint'   => false,
                ],
            ];
        }

        return $list;
    }

    /**
     * Run a tool; failures inside the tool come back as an isError result the model can read.
     *
     * @param mixed $id
     * @param array $params
     * @return array
     */
    private function callTool($id, array $params): array
    {
        $name  = (string) ($params['name'] ?? '');
        $tools = $this->tools();

        if (!isset($tools[$name])) {
            return $this->error($id, self::INVALID_PARAMS, 'Unknown tool: ' . $name);
        }

        $ability = $tools[$name];
        $args    = $params['arguments'] ?? [];
        if (!is_array($args)) {
            return $this->error($id, self::INVALID_PARAMS, 'arguments must be an object');
        }

        if (!$ability->permission()) {
            return $this->result($id, $this->toolError(__('Permission denied: your WordPress user is not allowed to do this.', 'rox-appointment-booking')));
        }

        $schema = $ability->inputSchema();
        $valid  = rest_validate_value_from_schema($args, $schema, 'arguments');
        if (is_wp_error($valid)) {
            return $this->result($id, $this->toolError($valid->get_error_message()));
        }
        $args = (array) rest_sanitize_value_from_schema($args, $schema, 'arguments');

        try {
            $output = $ability->execute($args);
        } catch (Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('Rox Appointment Booking MCP tool ' . $name . ' failed: ' . $e->getMessage());
            }

            return $this->result($id, $this->toolError(__('The tool failed unexpectedly.', 'rox-appointment-booking')));
        }

        if (is_wp_error($output)) {
            return $this->result($id, $this->toolError($output->get_error_message()));
        }

        return $this->result($id, [
            'content' => [['type' => 'text', 'text' => (string) wp_json_encode($output)]],
            'isError' => false,
        ]);
    }

    /**
     * Tool result reporting a failure.
     *
     * @param string $message
     * @return array
     */
    private function toolError(string $message): array
    {
        return [
            'content' => [['type' => 'text', 'text' => $message]],
            'isError' => true,
        ];
    }

    /**
     * JSON-RPC success envelope.
     *
     * @param mixed $id
     * @param mixed $result
     * @return array
     */
    private function result($id, $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    /**
     * JSON-RPC error envelope.
     *
     * @param mixed  $id
     * @param int    $code
     * @param string $message
     * @return array
     */
    private function error($id, int $code, string $message): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }
}
