<?php

namespace RoxAppointmentBooking\Modules\Multilingual\Services;

defined('ABSPATH') || exit;

/**
 * Class TranslatableRegistry
 *
 * @package RoxAppointmentBooking\Modules\Multilingual\Services
 * @description The whitelist of what may be translated. Entity and field names
 *              are resolved from here and never from request input, so a caller
 *              cannot register arbitrary WPML string contexts.
 *
 *              Only free-plugin entities live here. Pro registers Location,
 *              ExtraService, CustomField and Coupon through the
 *              `rox_appointment_booking_translatable_fields` filter, so each
 *              plugin owns the entities it ships.
 */
class TranslatableRegistry
{
    /**
     * String Translation domain for everything the free plugin owns.
     *
     * One domain per plugin, matching the convention used across the product
     * line (ShopEngine ships `shopengine` / `shopengine-pro`). The entity is
     * carried in each string's *name* (`service_12_title`), not in the domain,
     * so nothing is lost by grouping them — and a translator gets one place to
     * look instead of six.
     *
     * Part of every string's identity: changing it after release orphans every
     * existing translation. Treat as frozen.
     */
    public const CONTEXT_FREE = 'rox-appointment-booking';

    /**
     * String Translation domain for everything the Pro plugin owns.
     *
     * Declared here rather than in Pro so both halves of the convention are
     * visible in one place; Pro references it when registering its entities.
     */
    public const CONTEXT_PRO = 'rox-appointment-booking-pro';

    /**
     * Field holding plain text — sanitised with sanitize_text_field() on output.
     */
    public const KIND_TEXT = 'text';

    /**
     * Field holding post-grade HTML — sanitised with wp_kses_post() on output.
     */
    public const KIND_HTML = 'html';

    /**
     * Cached, filtered entity map.
     *
     * @var array<string, array>|null
     */
    private static ?array $map = null;

    /**
     * Entities shipped by the free plugin.
     *
     * All three share CONTEXT_FREE — one domain per plugin, not one per entity.
     * They stay distinguishable because each string is named
     * `{entity}_{id}_{field}`, so `service_12_title` and `category_12_title`
     * cannot collide.
     *
     * @return array<string, array>
     */
    private static function defaults(): array
    {
        return [
            'service' => [
                'context' => self::CONTEXT_FREE,
                'model' => \RoxAppointmentBooking\Modules\Service\Data\ServiceModel::class,
                'fields' => [
                    'title' => self::KIND_TEXT,
                    'description' => self::KIND_HTML,
                ],
            ],
            'category' => [
                'context' => self::CONTEXT_FREE,
                'model' => \RoxAppointmentBooking\Modules\Category\Data\CategoryModel::class,
                'fields' => [
                    'title' => self::KIND_TEXT,
                    'description' => self::KIND_HTML,
                ],
            ],
            'agent' => [
                'context' => self::CONTEXT_FREE,
                'model' => \RoxAppointmentBooking\Modules\Agent\Data\AgentModel::class,
                'fields' => [
                    // Job title, not the agent's name — names are not translated.
                    'title' => self::KIND_TEXT,
                    'bio' => self::KIND_HTML,
                    // `certifications` is deliberately absent: despite the name
                    // it is `INT DEFAULT NULL` (a count rendered next to
                    // experience_years / happy_customers), not free text.
                ],
            ],
        ];
    }

    /**
     * The full entity map, including anything Pro or a third party added.
     *
     * @return array<string, array>
     */
    public static function all(): array
    {
        if (self::$map === null) {
            self::$map = self::normalise(
                apply_filters('rox_appointment_booking_translatable_fields', self::defaults())
            );
        }

        return self::$map;
    }

    /**
     * Discard anything malformed a filter callback may have added.
     *
     * A bad entry from a third party must not become a string context, so this
     * drops rather than repairs.
     *
     * @param mixed $map Raw filtered map.
     * @return array<string, array>
     */
    private static function normalise($map): array
    {
        if (!is_array($map)) {
            return [];
        }

        $clean = [];

        foreach ($map as $entity => $definition) {
            $entity = sanitize_key((string) $entity);

            if ($entity === '' || !is_array($definition)) {
                continue;
            }

            $context = isset($definition['context']) ? trim((string) $definition['context']) : '';
            $fields = isset($definition['fields']) && is_array($definition['fields']) ? $definition['fields'] : [];

            if ($context === '' || empty($fields)) {
                continue;
            }

            $cleanFields = [];

            foreach ($fields as $field => $kind) {
                $field = sanitize_key((string) $field);

                if ($field === '') {
                    continue;
                }

                $cleanFields[$field] = ($kind === self::KIND_HTML) ? self::KIND_HTML : self::KIND_TEXT;
            }

            if (empty($cleanFields)) {
                continue;
            }

            // Optional: the model the backfill walks to find this entity's
            // records. Dropped when it names a class that does not exist, so a
            // Pro entity declared while Pro is half-loaded cannot fatal a scan.
            $model = isset($definition['model']) ? (string) $definition['model'] : '';

            $clean[$entity] = [
                'context' => $context,
                'model' => ($model !== '' && class_exists($model)) ? $model : '',
                'fields' => $cleanFields,
            ];
        }

        return $clean;
    }

    /**
     * Whether an entity/field pair may be translated.
     *
     * @param string $entity Entity key, e.g. `service`.
     * @param string $field Column name, e.g. `title`.
     * @return bool
     */
    public static function has(string $entity, string $field): bool
    {
        $map = self::all();

        return isset($map[$entity]['fields'][$field]);
    }

    /**
     * The translator-facing context for an entity.
     *
     * @param string $entity Entity key.
     * @return string Empty string when the entity is not registered.
     */
    public static function context(string $entity): string
    {
        $map = self::all();

        return $map[$entity]['context'] ?? '';
    }

    /**
     * The model class backing an entity, for the backfill scan.
     *
     * @param string $entity Entity key.
     * @return string Empty string when the entity declared no usable model.
     */
    public static function model(string $entity): string
    {
        $map = self::all();

        return $map[$entity]['model'] ?? '';
    }

    /**
     * The translatable fields of an entity, as `field => kind`.
     *
     * @param string $entity Entity key.
     * @return array<string, string>
     */
    public static function fields(string $entity): array
    {
        $map = self::all();

        return $map[$entity]['fields'] ?? [];
    }

    /**
     * How a field's value must be sanitised on output.
     *
     * Defaults to the stricter of the two when the field is unknown.
     *
     * @param string $entity Entity key.
     * @param string $field Column name.
     * @return string One of the KIND_* constants.
     */
    public static function kind(string $entity, string $field): string
    {
        $map = self::all();

        return $map[$entity]['fields'][$field] ?? self::KIND_TEXT;
    }

    /**
     * Forget the cached map.
     *
     * Only needed when a filter is added after the map was first built, which
     * in practice means tests.
     *
     * @return void
     */
    public static function flush(): void
    {
        self::$map = null;
    }
}
