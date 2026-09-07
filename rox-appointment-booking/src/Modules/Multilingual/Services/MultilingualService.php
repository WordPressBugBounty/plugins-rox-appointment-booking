<?php

namespace RoxAppointmentBooking\Modules\Multilingual\Services;

use RoxAppointmentBooking\Modules\Multilingual\Services\Drivers\DriverInterface;
use RoxAppointmentBooking\Modules\Multilingual\Services\Drivers\NullDriver;
use RoxAppointmentBooking\Modules\Multilingual\Services\Drivers\PolylangDriver;
use RoxAppointmentBooking\Modules\Multilingual\Services\Drivers\WpmlDriver;

defined('ABSPATH') || exit;

/**
 * Class MultilingualService
 *
 * @package RoxAppointmentBooking\Modules\Multilingual\Services
 * @description The one place that knows a multilingual plugin exists. Holds a
 *              single driver, validates every entity/field against
 *              TranslatableRegistry, and re-sanitises translated values on the
 *              way out — a WPML translator is not necessarily an administrator,
 *              so a translation is untrusted input all over again.
 *
 *              Nothing calls this yet; save/read wiring lands in Steps 5 and 6
 *              of dev-resources/WPML_SUPPORT_PLAN.md.
 */
class MultilingualService
{
    /**
     * Whether this class should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * Shared instance, so the helper functions in src/functions/multilingual.php
     * reach the same driver the Provider booted.
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * The active driver.
     *
     * @var DriverInterface
     */
    private DriverInterface $driver;

    /**
     * Pick a driver and, when WPML is half-installed, say so.
     */
    public function __construct()
    {
        $this->driver = $this->resolveDriver();

        self::$instance = $this;

        // One subscription instead of edits in every Save*/Delete* endpoint —
        // the same seam the Appointment and Order modules already use.
        add_action('rox_appointment_booking_after_entity_saved', [$this, 'onEntitySaved'], 10, 2);
        add_action('rox_appointment_booking_after_entity_deleted', [$this, 'onEntityDeleted'], 10, 2);

        // Lets the admin UI show the "Re-scan strings" control only where it can
        // actually do something. Same idiom the Pro plugin uses to declare
        // `wooCommerceActive`.
        add_filter('rox_appointment_booking_app_config_structure', [$this, 'declareAppConfig']);

        // Marks wp-admin while a multilingual plugin is actually driving
        // translations. The admin stylesheet hangs its WPML compatibility rules
        // off this class so that, with no such plugin installed, not one of
        // them applies and the UI is byte-for-byte what it always was. On
        // <body> rather than the app root because antd portals modals, drawers,
        // dropdowns and tooltips to <body>, outside our root.
        add_filter('admin_body_class', [$this, 'addAdminBodyClass']);

        if ($this->wpmlNeedsStringTranslation()) {
            add_action('admin_notices', [$this, 'renderStringTranslationNotice']);
        }
    }

    /**
     * Flag wp-admin while a multilingual plugin is driving translations.
     *
     * @param string $classes Space-separated admin body classes.
     * @return string
     */
    public function addAdminBodyClass($classes)
    {
        if (!$this->isActive()) {
            return $classes;
        }

        // Named after the specific plugin, not just "active": the CSS guard
        // in app.scss counters WPML's stylesheet and must not apply under
        // Polylang, which ships no such sheet.
        return trim($classes . ' rox-multilingual-' . sanitize_html_class($this->driver->id()));
    }

    /**
     * Tell the admin app whether translations are live.
     *
     * @param array $config Incoming app config.
     * @return array
     */
    public function declareAppConfig($config)
    {
        if (!is_array($config)) {
            return $config;
        }

        $config['multilingualActive'] = $this->isActive();

        return $config;
    }

    /**
     * Register a saved record's translatable fields as source strings.
     *
     * @param string $entity Entity key, e.g. `service`.
     * @param object $model Saved model instance.
     * @return void
     */
    public function onEntitySaved(string $entity, $model): void
    {
        if (!$this->driver->isActive() || !is_object($model) || !method_exists($model, 'getID')) {
            return;
        }

        $values = [];

        foreach (TranslatableRegistry::fields($entity) as $field => $kind) {
            $values[$field] = $model->{$field} ?? null;
        }

        $this->registerRecord($entity, (int) $model->getID(), $values);
    }

    /**
     * Drop a deleted record's source strings.
     *
     * @param string $entity Entity key.
     * @param int $id Record ID.
     * @return void
     */
    public function onEntityDeleted(string $entity, $id): void
    {
        if (!$this->driver->isActive()) {
            return;
        }

        $this->unregisterStrings($entity, (int) $id);
    }

    /**
     * The shared instance, constructing one if the Provider has not run yet.
     *
     * @return self
     */
    public static function instance(): self
    {
        if (self::$instance === null) {
            new self();
        }

        return self::$instance;
    }

    /**
     * The active driver.
     *
     * @return DriverInterface
     */
    public function driver(): DriverInterface
    {
        return $this->driver;
    }

    /**
     * Whether a real multilingual plugin is driving translations.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->driver->isActive();
    }

    /**
     * Register one field of one record as a translatable source string.
     *
     * Silently ignores unknown entity/field pairs — the registry is the
     * whitelist, so a typo cannot invent a string context.
     *
     * @param string $entity Entity key, e.g. `service`.
     * @param int $id Record ID.
     * @param string $field Column name, e.g. `title`.
     * @param string $value Source value in the default language.
     * @return void
     */
    public function registerString(string $entity, int $id, string $field, string $value, string $suffix = ''): void
    {
        $name = $this->stringName($entity, $id, $field, $suffix);

        if ($name === '' || $value === '') {
            return;
        }

        $this->driver->registerString(TranslatableRegistry::context($entity), $name, $value);
    }

    /**
     * Register every translatable field of a record in one call.
     *
     * @param string $entity Entity key.
     * @param int $id Record ID.
     * @param array<string, mixed> $values Field values, keyed by column name.
     * @return void
     */
    public function registerRecord(string $entity, int $id, array $values): void
    {
        foreach (TranslatableRegistry::fields($entity) as $field => $kind) {
            if (!isset($values[$field]) || !is_scalar($values[$field])) {
                continue;
            }

            $this->registerString($entity, $id, $field, (string) $values[$field]);
        }
    }

    /**
     * Translate one field of one record.
     *
     * Always returns something renderable: an unknown field, an inactive
     * driver, or a missing translation all fall back to $value.
     *
     * @param string $entity Entity key.
     * @param int $id Record ID.
     * @param string $field Column name.
     * @param string $value Source value, used as the fallback.
     * @param string|null $lang Target language, or null for the current one.
     * @return string
     */
    public function translate(string $entity, int $id, string $field, string $value, ?string $lang = null, string $suffix = ''): string
    {
        $name = $this->stringName($entity, $id, $field, $suffix);

        if ($name === '' || $value === '') {
            return $value;
        }

        $translated = $this->driver->translateString(
            $value,
            TranslatableRegistry::context($entity),
            $name,
            $lang === null ? null : $this->sanitizeLanguage($lang)
        );

        // A translation is user input from someone who may hold nothing but
        // WPML's translator capability, so it is re-filtered here rather than
        // trusted because the source was clean.
        $translated = $this->sanitizeValue($translated, TranslatableRegistry::kind($entity, $field));

        /**
         * Filters a translated value just before it is returned.
         *
         * @param string $translated Sanitised translation.
         * @param string $value Source value.
         * @param string $entity Entity key.
         * @param int $id Record ID.
         * @param string $field Column name.
         */
        $translated = apply_filters(
            'rox_appointment_booking_translated_value',
            $translated,
            $value,
            $entity,
            $id,
            $field
        );

        return is_string($translated) && $translated !== '' ? $translated : $value;
    }

    /**
     * Drop every source string belonging to a record, e.g. after a delete.
     *
     * @param string $entity Entity key.
     * @param int $id Record ID.
     * @return void
     */
    public function unregisterStrings(string $entity, int $id): void
    {
        foreach (TranslatableRegistry::fields($entity) as $field => $kind) {
            $name = $this->stringName($entity, $id, $field);

            if ($name === '') {
                continue;
            }

            $this->driver->unregisterString(TranslatableRegistry::context($entity), $name);
        }
    }

    /**
     * Run a callback with a different language active, then restore.
     *
     * The restore is in `finally` on purpose: an exception that escaped with
     * the language still switched would silently corrupt everything rendered
     * later in the same PHP process, e-mails included.
     *
     * @param string|null $lang Language to switch to; invalid values fall back
     *                          to the default language.
     * @param callable $callback Work to run.
     * @return mixed Whatever $callback returns.
     */
    public function withLanguage(?string $lang, callable $callback)
    {
        if (!$this->driver->isActive()) {
            return $callback();
        }

        $previous = $this->currentLanguage();
        $target = $this->sanitizeLanguage($lang);

        if ($target === $previous) {
            return $callback();
        }

        $this->driver->switchLanguage($target);

        try {
            return $callback();
        } finally {
            $this->driver->switchLanguage($previous);
        }
    }

    /**
     * The id of a post's translation in the active language.
     *
     * @param int $postId Source post id.
     * @param string $postType Post type, e.g. `page`.
     * @return int
     */
    public function translatedPostId(int $postId, string $postType = 'page'): int
    {
        $postId = absint($postId);

        if ($postId === 0) {
            return 0;
        }

        return $this->driver->translatedPostId($postId, sanitize_key($postType));
    }

    /**
     * The language code active for this request.
     *
     * @return string
     */
    public function currentLanguage(): string
    {
        /**
         * Filters the detected current language.
         *
         * @param string $language Language code from the active driver.
         */
        $language = apply_filters('rox_appointment_booking_current_language', $this->driver->currentLanguage());

        return $this->sanitizeLanguage(is_string($language) ? $language : null);
    }

    /**
     * The site's default (source) language code.
     *
     * @return string
     */
    public function defaultLanguage(): string
    {
        return sanitize_key($this->driver->defaultLanguage());
    }

    /**
     * Whether the request is running in the site's default language.
     *
     * Step 6b uses this to refuse admin writes made while a translation is
     * active, which would otherwise overwrite the source string.
     *
     * @return bool
     */
    public function isDefaultLanguage(): bool
    {
        return !$this->driver->isActive() || $this->currentLanguage() === $this->defaultLanguage();
    }

    /**
     * Reduce an untrusted language code to one the site actually has.
     *
     * `lang` reaches us on unauthenticated public requests and is handed to
     * WPML, so it is whitelisted rather than merely escaped. Anything unknown
     * becomes the default language.
     *
     * @param string|null $lang Raw language code.
     * @return string
     */
    public function sanitizeLanguage(?string $lang): string
    {
        $lang = sanitize_key((string) $lang);

        if ($lang === '') {
            return $this->defaultLanguage();
        }

        $active = $this->driver->activeLanguages();

        if (empty($active)) {
            return $this->defaultLanguage();
        }

        return in_array($lang, array_map('sanitize_key', $active), true) ? $lang : $this->defaultLanguage();
    }

    /**
     * Warn that WPML core alone cannot store our strings.
     *
     * @return void
     */
    public function renderStringTranslationNotice(): void
    {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        printf(
            '<div class="notice notice-warning"><p>%s</p></div>',
            esc_html__(
                'Rox Appointment Booking: WPML String Translation is required to translate services, categories and agents. Translations are disabled until it is active.',
                'rox-appointment-booking'
            )
        );
    }

    /**
     * Build a string name, or an empty string when the field is not whitelisted.
     *
     * @param string $entity Entity key.
     * @param int $id Record ID.
     * @param string $field Column name.
     * @return string
     */
    private function stringName(string $entity, int $id, string $field, string $suffix = ''): string
    {
        $entity = sanitize_key($entity);
        $field = sanitize_key($field);
        $id = absint($id);

        if ($id === 0 || !TranslatableRegistry::has($entity, $field)) {
            return '';
        }

        $name = sprintf('%s_%d_%s', $entity, $id, $field);

        // Repeatable fields (a custom field's option labels) need one string per
        // entry. The suffix keys them by the entry's own stable value rather
        // than its position, so reordering options does not orphan translations.
        $suffix = sanitize_key($suffix);

        return $suffix === '' ? $name : $name . '_' . $suffix;
    }

    /**
     * Sanitise a value according to its registered field kind.
     *
     * A translation never gets to allow more HTML than the source field does.
     *
     * @param string $value Value to sanitise.
     * @param string $kind One of the TranslatableRegistry::KIND_* constants.
     * @return string
     */
    private function sanitizeValue(string $value, string $kind): string
    {
        return $kind === TranslatableRegistry::KIND_HTML
            ? wp_kses_post($value)
            : sanitize_text_field($value);
    }

    /**
     * Pick the driver for whatever is installed.
     *
     * WPML core without String Translation cannot store strings, so that
     * combination deliberately falls through to NullDriver.
     *
     * @return DriverInterface
     */
    private function resolveDriver(): DriverInterface
    {
        if (defined('ICL_SITEPRESS_VERSION') && defined('WPML_ST_VERSION')) {
            return new WpmlDriver();
        }

        // Polylang. Checked after WPML because the two cannot run together, and
        // detected by its helper rather than a version constant: the free
        // plugin, Polylang Pro and Polylang for WooCommerce all define
        // different constants but all ship `pll_register_string()`.
        if (function_exists('pll_register_string') && function_exists('pll_current_language')) {
            return new PolylangDriver();
        }

        return new NullDriver();
    }

    /**
     * Whether WPML core is active but String Translation is not.
     *
     * @return bool
     */
    private function wpmlNeedsStringTranslation(): bool
    {
        return defined('ICL_SITEPRESS_VERSION') && !defined('WPML_ST_VERSION');
    }
}
