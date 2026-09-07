<?php

namespace RoxAppointmentBooking\Modules\Multilingual\Services;

defined('ABSPATH') || exit;

/**
 * Class StringBackfillService
 *
 * @package RoxAppointmentBooking\Modules\Multilingual\Services
 * @description Registers the source strings of records that already existed
 *              before translations were wired up — the case for every site that
 *              installs WPML after the plugin, which is most of them.
 *
 *              Save-time registration (Step 5) only ever fires for records
 *              touched since; without this scan an established site would see an
 *              empty String Translation list and conclude the feature is broken.
 */
class StringBackfillService
{
    /**
     * Option remembering that the automatic one-time scan has run.
     */
    public const DONE_OPTION = 'rox_appointment_booking_wpml_backfilled';

    /**
     * Lock guarding against two scans running at once.
     */
    public const LOCK_TRANSIENT = 'rox_appointment_booking_wpml_backfill_lock';

    /**
     * How many records are loaded per query.
     *
     * The scan is O(records) and holds each batch in memory, so it is paged
     * rather than loaded whole — a mature site can have thousands of services.
     */
    private const BATCH_SIZE = 200;

    /**
     * Register every translatable field of every existing record.
     *
     * @return array{entities: array<string, int>, records: int, skipped: string[]}
     */
    public static function run(): array
    {
        $service = MultilingualService::instance();

        $result = ['entities' => [], 'records' => 0, 'skipped' => []];

        // Nothing to register into without a real driver, and the caller should
        // be told that rather than shown a success message.
        if (!$service->isActive()) {
            $result['skipped'][] = 'inactive_driver';

            return $result;
        }

        foreach (array_keys(TranslatableRegistry::all()) as $entity) {
            $model = TranslatableRegistry::model($entity);

            if ($model === '' || !method_exists($model, 'query')) {
                // An entity can legitimately declare no model (a third party may
                // register its strings by hand); report rather than fail.
                $result['skipped'][] = $entity;
                continue;
            }

            $count = self::runForEntity($service, $entity, $model);

            $result['entities'][$entity] = $count;
            $result['records'] += $count;
        }

        /**
         * Fires after a backfill scan completes, so an entity with no model of
         * its own can register its records too.
         *
         * @param array $result Scan tallies.
         */
        do_action('rox_appointment_booking_after_string_backfill', $result);

        return $result;
    }

    /**
     * Scan one entity's table in batches.
     *
     * @param MultilingualService $service Shared service.
     * @param string $entity Entity key.
     * @param string $model Model class name.
     * @return int Records processed.
     */
    private static function runForEntity(MultilingualService $service, string $entity, string $model): int
    {
        $fields = array_keys(TranslatableRegistry::fields($entity));

        if (empty($fields)) {
            return 0;
        }

        $processed = 0;
        $offset = 0;

        do {
            $records = $model::query()
                ->orderBy('id', 'ASC')
                ->offset($offset)
                ->limit(self::BATCH_SIZE)
                ->get();

            $batch = is_countable($records) ? count($records) : 0;

            foreach ($records as $record) {
                $values = [];

                foreach ($fields as $field) {
                    $values[$field] = $record->{$field} ?? null;
                }

                $service->registerRecord($entity, (int) $record->getID(), $values);

                // Repeatable fields and anything else an owning plugin knows
                // about — the same action the save path fires, so Pro's option
                // label handler is reused instead of duplicated here.
                do_action('rox_appointment_booking_after_entity_saved', $entity, $record, false);

                $processed++;
            }

            $offset += self::BATCH_SIZE;
        } while ($batch === self::BATCH_SIZE);

        return $processed;
    }

    /**
     * Take the scan lock.
     *
     * The scan walks every record of every entity, so a double-clicked button
     * must not start a second pass on top of the first.
     *
     * @return bool False when a scan is already running.
     */
    public static function acquireLock(): bool
    {
        if (get_transient(self::LOCK_TRANSIENT)) {
            return false;
        }

        // Expires on its own so a fatal mid-scan cannot wedge the button shut.
        set_transient(self::LOCK_TRANSIENT, time(), 5 * MINUTE_IN_SECONDS);

        return true;
    }

    /**
     * Release the scan lock.
     *
     * @return void
     */
    public static function releaseLock(): void
    {
        delete_transient(self::LOCK_TRANSIENT);
    }

    /**
     * Run the scan once automatically, the first time an admin loads wp-admin
     * with a working driver.
     *
     * Deliberately not an activation worker: those run during the free plugin's
     * `plugins_loaded`, which fires before the Pro plugin has registered its
     * entities through `rox_appointment_booking_translatable_fields`, so a
     * worker would silently skip every Pro entity.
     *
     * @return void
     */
    public static function maybeRunOnce(): void
    {
        $service = MultilingualService::instance();

        // Polylang keeps no durable record of a registered string:
        // `pll_register_string()` fills an in-memory list that builds the
        // Strings translations screen, and that list is empty again next
        // request. So under Polylang the scan runs on every admin request
        // rather than once — otherwise a translator opens
        // Languages -> Strings translations and finds nothing to translate.
        // Only the admin screens need it; translation itself reads the saved
        // translations directly and does not depend on registration.
        if ($service->isActive() && $service->driver()->id() === 'polylang') {
            self::run();

            return;
        }

        if (get_option(self::DONE_OPTION)) {
            return;
        }


        // Not marked done: the site may install WPML later, and this should run
        // on the first admin page load after that.
        if (!$service->isActive()) {
            return;
        }

        if (!self::acquireLock()) {
            return;
        }

        try {
            self::run();
            update_option(self::DONE_OPTION, time());
        } finally {
            self::releaseLock();
        }
    }
}
