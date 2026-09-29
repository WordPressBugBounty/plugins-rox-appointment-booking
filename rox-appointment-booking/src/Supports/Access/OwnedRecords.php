<?php

namespace RoxAppointmentBooking\Supports\Access;

if (!defined('ABSPATH')) exit;

use RoxAppointmentBooking\Modules\Appointment\Services\AppointmentService;
use RoxAppointmentBooking\Modules\RelationshipModel\Data\ServiceAgentRelationModel;

/**
 * Resolves which services the current user may reach when
 * {@see Permissions::scopeFor()} says "own": those assigned to their agent row.
 *
 * @package RoxAppointmentBooking
 * @subpackage Supports\Access
 * @since 1.2.8
 */
class OwnedRecords
{
    /**
     * Per-request cache of resolved ids, keyed by user.
     *
     * @var array<int, int[]|null>
     */
    private static array $cache = [];

    /**
     * Ids of the services the current user may reach, or null when they read every service.
     *
     * @return int[]|null
     */
    public static function serviceIds(): ?array
    {
        if (Permissions::scopeFor('service') === Permissions::SCOPE_ALL) {
            return null;
        }

        $userId = get_current_user_id();
        if (array_key_exists($userId, self::$cache)) {
            return self::$cache[$userId];
        }

        $agentId = AppointmentService::getCurrentAgentId();
        $ids = $agentId
            ? ServiceAgentRelationModel::where('agent_id', $agentId)->pluck('service_id')->toArray()
            : [];

        return self::$cache[$userId] = array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * Whether the current user may reach one service.
     *
     * @param int $id
     * @return bool
     */
    public static function ownsService(int $id): bool
    {
        $ids = self::serviceIds();

        return $ids === null || in_array($id, $ids, true);
    }

    /**
     * Limit a service query to the services the current user may reach.
     *
     * @param mixed $query Query builder.
     * @return mixed
     */
    public static function restrictServices($query)
    {
        $ids = self::serviceIds();

        if ($ids !== null) {
            // An empty IN () is invalid SQL, so match nothing instead.
            $query->whereIn('id', $ids ?: [0]);
        }

        return $query;
    }
}
