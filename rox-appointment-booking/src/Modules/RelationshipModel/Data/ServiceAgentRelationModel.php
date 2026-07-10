<?php

namespace RoxAppointmentBooking\Modules\RelationshipModel\Data;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Supports\Abstracts\AbstractModel;
use RoxAppointmentBooking\Supports\QueryBuilder\RoxAppointmentBookingCollection;

/**
 * Class ServiceAgentRelationModel
 *
 * @package RoxAppointmentBooking\Modules\RelationshipModel\Data
 * @description Represents the relationship between services and agents in the system.
 */
class ServiceAgentRelationModel extends AbstractModel
{
    /**
     * RelationshipModel table name.
     *
     * @var string
     */
    protected $table = ROX_APPOINTMENT_BOOKING_DB_PREFIX . ROX_APPOINTMENT_BOOKING_PREFIX . '_agent_service';

    /**
     * Mass-assignable attributes.
     *
     * @var array
     */
    protected $fillable = [
        'agent_id',
        'service_id',
        'created_at',
        'updated_at',
    ];

    /**
     * Attribute cast rules.
     *
     * @var array
     */
    protected $casts = [
        'agent_id' => 'integer',
        'service_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get agent details for this relation
     *
     * @return \RoxAppointmentBooking\Modules\Agent\Data\AgentModel|null
     */
    public function getAgent()
    {
        return \RoxAppointmentBooking\Modules\Agent\Data\AgentModel::find($this->agent_id);
    }

    /**
     * Get service details for this relation
     *
     * @return \RoxAppointmentBooking\Modules\Service\Data\ServiceModel|null
     */
    public function getService()
    {
        return \RoxAppointmentBooking\Modules\Service\Data\ServiceModel::find($this->service_id);
    }

    /**
     * Scope to get relations by agent ID
     *
     * @param $query
     * @param int $agent_id
     * @return mixed
     */
    public function scopeByAgent($query, int $agent_id)
    {
        return $query->where('agent_id', $agent_id);
    }

    /**
     * Scope to get relations by service ID
     *
     * @param $query
     * @param int $service_id
     * @return mixed
     */
    public function scopeByService($query, int $service_id)
    {
        return $query->where('service_id', $service_id);
    }

    /**
     * Check if relation already exists
     *
     * @param int $agent_id
     * @param int $service_id
     * @return bool
     */
    public static function relationExists(int $agent_id, int $service_id): bool
    {
        return self::query()
            ->where('agent_id', $agent_id)
            ->where('service_id', $service_id)
            ->exists();
    }

    /**
     * Get all services for an agent
     *
     * @param int $agent_id
     * @return \RoxAppointmentBooking\Supports\QueryBuilder\RoxAppointmentBookingCollection|mixed
     */
    public static function getServicesByAgent(int $agent_id)
    {
        $relations = self::query()->byAgent($agent_id)->get();
        
        $services = new RoxAppointmentBookingCollection();
        foreach ($relations as $relation) {
            $service = $relation->getService();
            if ($service) {
                $services->push($service);
            }
        }
        
        return $services;
    }

    /**
     * Get all agents for a service
     *
     * @param int $service_id
     * @return \RoxAppointmentBooking\Supports\QueryBuilder\RoxAppointmentBookingCollection|mixed
     */
    public static function getAgentsByService(int $service_id)
    {
        $relations = self::query()->byService($service_id)->get();
        
        $agents = new RoxAppointmentBookingCollection();
        foreach ($relations as $relation) {
            $agent = $relation->getAgent();
            if ($agent) {
                $agents->push($agent);
            }
        }
        
        return $agents;
    }

    /**
     * Create relation between agent and service
     *
     * @param int $agent_id
     * @param int $service_id
     * @return self|null
     * @throws \Exception
     */
    public static function createRelation(int $agent_id, int $service_id): ?self
    {
        // Check if agent exists
        $agent = \RoxAppointmentBooking\Modules\Agent\Data\AgentModel::find($agent_id);
        if (!$agent) {
            throw new \Exception(esc_html__('Agent not found', 'rox-appointment-booking'));
        }

        // Check if service exists
        $service = \RoxAppointmentBooking\Modules\Service\Data\ServiceModel::find($service_id);
        if (!$service) {
            throw new \Exception(esc_html__('Service not found', 'rox-appointment-booking'));
        }

        // Check if relation already exists
        if (self::relationExists($agent_id, $service_id)) {
            throw new \Exception(esc_html__('Relation between agent and service already exists', 'rox-appointment-booking'));
        }

        $relation = new self();
        $relation->agent_id = $agent_id;
        $relation->service_id = $service_id;
        $relation->save();

        return $relation;
    }

    /**
     * Remove relation between agent and service
     *
     * @param int $agent_id
     * @param int $service_id
     * @return bool
     */
    public static function removeRelation(int $agent_id, int $service_id): bool
    {
        return self::query()
            ->where('agent_id', $agent_id)
            ->where('service_id', $service_id)
            ->delete();
    }

    /**
     * Remove all relations for an agent
     *
     * @param int $agent_id
     * @return bool
     */
    public static function removeAllForAgent(int $agent_id): bool
    {
        return self::query()->byAgent($agent_id)->delete();
    }

    /**
     * Remove all relations for a service
     *
     * @param int $service_id
     * @return bool
     */
    public static function removeAllForService(int $service_id): bool
    {
        return self::query()->byService($service_id)->delete();
    }

    /**
     * Get relation with agent and service data
     *
     * @return array
     */
    public function toDetailedArray(): array
    {
        $agent = $this->getAgent();
        $service = $this->getService();

        return [
            'id' => $this->getID(),
            'agent_id' => $this->agent_id,
            'service_id' => $this->service_id,
            'agent' => $agent ? [
                'id' => $agent->getID(),
                'first_name' => $agent->first_name,
                'last_name' => $agent->last_name,
                'full_name' => $agent->getFullName(),
                'email' => $agent->email,
                'phone' => $agent->phone,
                'title' => $agent->title,
            ] : null,
            'service' => $service ? [
                'id' => $service->getID(),
                'title' => $service->title,
                'description' => $service->description,
                'duration' => $service->duration,
                'price' => $service->price,
                'formatted_price' => $service->getFormattedPrice(),
                'status' => $service->status,
                'is_active' => $service->isActive(),
            ] : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
