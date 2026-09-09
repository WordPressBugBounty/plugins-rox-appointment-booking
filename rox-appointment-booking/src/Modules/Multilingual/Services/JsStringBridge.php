<?php

namespace RoxAppointmentBooking\Modules\Multilingual\Services;

defined('ABSPATH') || exit;

/**
 * Class JsStringBridge
 *
 * @package RoxAppointmentBooking\Modules\Multilingual\Services
 * @description Lets WPML/Polylang translate the plugin's JS/React UI text —
 *              the half of the plugin `wp_set_script_translations()` serves
 *              from a `.json` language file — through the same String
 *              Translation screen already used for Service/Category/Agent
 *              content, instead of requiring a separate tool (Loco
 *              Translate, or a hand-built `.po`/`.json` pair) for it.
 *
 *              Mechanism: every UI string this plugin ships in its JS/JSX
 *              source is registered once as a driver string under its own
 *              context. WordPress core's `load_script_translations` filter
 *              is then used to hand back a translations blob built from
 *              those driver strings instead of one read from disk — the
 *              same shape `wp_set_script_translations()` expects, just
 *              generated on the fly from whatever the site owner has
 *              translated through WPML or Polylang.
 *
 *              Driver-agnostic like the rest of this module: it calls
 *              MultilingualService, never WPML/Polylang directly, so it
 *              works under either without a special case.
 */
class JsStringBridge
{
    /**
     * @var bool
     */
    public static $loadable = true;

    /**
     * Translator-facing group name for every string this class registers.
     *
     * @var string
     */
    const CONTEXT = 'rox-appointment-booking-ui';

    /**
     * Batch size per admin_init pass, mirroring StringBackfillService — a
     * plugin with ~1,200 UI strings must not register all of them in one
     * synchronous request the first time a driver becomes active.
     *
     * @var int
     */
    const BATCH_SIZE = 150;

    /**
     * Stores the offset of the next string to register, and the source
     * file's mtime it was built against (so a plugin update — new UI text —
     * is picked up rather than silently ignored).
     *
     * @var string
     */
    const PROGRESS_OPTION = 'rox_appointment_booking_ui_strings_progress';

    /**
     * Transient prefix for the built JSON, one entry per language.
     *
     * A transient rather than `wp_cache_*`: without a persistent object
     * cache — the default on most sites — `wp_cache_set()` lives only for
     * the current request, so every single page load would rebuild the blob
     * by asking the driver for all ~1,400 strings one at a time.
     *
     * @var string
     */
    const CACHE_PREFIX = 'rox_ab_ui_i18n_';

    /**
     * How long a built blob stays valid. Neither driver exposes a reliable
     * "a translation was saved" hook to invalidate on, so this is kept short
     * and the "Re-scan strings" button clears it outright.
     *
     * @var int
     */
    const CACHE_TTL = 300;

    /**
     * Tracks which languages have a cached blob, so {@see flushCache()} can
     * delete them without a driver call (it is also called from contexts
     * where no driver is active).
     *
     * @var string
     */
    const CACHE_INDEX_OPTION = 'rox_appointment_booking_ui_i18n_langs';

    /**
     * Provider constructor.
     *
     * @return void
     */
    public function __construct()
    {
        // Two hooks, because core only reaches the second one when a real
        // `.json` file exists on disk (l10n.php bails at `! is_readable( $file )`
        // long before applying it). Without a file — the whole point of this
        // bridge — only `pre_load_script_translations` ever runs.
        add_filter('pre_load_script_translations', [$this, 'preBridgeTranslations'], PHP_INT_MAX, 4);

        // Runs last, deliberately: WPML's own String Translation plugin (from
        // v3.5.4) hooks this same filter to inject its own JS translations,
        // and its output has a real bug — the required `""` header row lands
        // under `locale_data.messages` instead of the actual domain key, so
        // the browser's loader crashes with "Cannot set properties of
        // undefined (setting 'domain')" the moment it runs. Going last means
        // buildJson() always gets the final say and can repair that shape
        // rather than losing to it.
        add_filter('load_script_translations', [$this, 'bridgeTranslations'], PHP_INT_MAX, 4);
        add_action('admin_init', [$this, 'maybeRegisterBatch']);

        // No confirmed WPML/Polylang "a string translation was saved" hook
        // to flush on, so the cache is kept short instead (CACHE_TTL) and
        // the "Re-scan strings" button clears it outright — see
        // RescanStrings::handleRequest().
    }

    /**
     * Register the next batch of not-yet-registered UI strings.
     *
     * Runs on every wp-admin page load, but is a no-op past the first few
     * once the option catches up — cheap to leave attached.
     *
     * @return void
     */
    public function maybeRegisterBatch(): void
    {
        $service = rox_appointment_booking_multilingual();

        if (!$service->isActive()) {
            return;
        }

        $strings = self::sourceStrings();
        $sourceHash = self::sourceHash();
        $progress = get_option(self::PROGRESS_OPTION, []);
        $progress = is_array($progress) ? $progress : [];

        // The source list changed (plugin update added/removed UI text) —
        // start over so nothing new is silently left unregistered.
        if (($progress['hash'] ?? '') !== $sourceHash) {
            $progress = ['hash' => $sourceHash, 'offset' => 0];
        }

        $offset = (int) ($progress['offset'] ?? 0);

        if ($offset >= count($strings)) {
            return;
        }

        $batch = array_slice($strings, $offset, self::BATCH_SIZE);
        $driver = $service->driver();

        foreach ($batch as $string) {
            $string = (string) $string;
            $driver->registerString(self::CONTEXT, self::stringName($string), $string);
        }

        $progress['offset'] = $offset + count($batch);
        update_option(self::PROGRESS_OPTION, $progress, false);
    }

    /**
     * Force every UI string to be re-registered from scratch, e.g. from the
     * existing "Re-scan strings for translation" button.
     *
     * @return void
     */
    public static function resetProgress(): void
    {
        delete_option(self::PROGRESS_OPTION);
    }

    /**
     * Register as many UI strings as fit in one deliberate admin action (the
     * "Re-scan strings" button), then leave the rest to the background
     * batches.
     *
     * Time-bounded on purpose: each string is a driver call that writes to
     * the multilingual plugin's own tables, and ~1,400 of them in a single
     * request is enough to hit `max_execution_time` on modest hosting. The
     * progress option is written either way, so the next few admin page
     * loads pick up exactly where this stopped.
     *
     * @param int $budgetSeconds Wall-clock budget for this pass.
     * @return int Number of strings registered in this pass.
     */
    public static function registerAll(int $budgetSeconds = 10): int
    {
        $service = rox_appointment_booking_multilingual();

        if (!$service->isActive()) {
            return 0;
        }

        $strings = self::sourceStrings();
        $driver = $service->driver();
        $deadline = microtime(true) + max(1, $budgetSeconds);
        $done = 0;

        foreach ($strings as $string) {
            $driver->registerString(self::CONTEXT, self::stringName((string) $string), (string) $string);
            $done++;

            if (microtime(true) >= $deadline) {
                break;
            }
        }

        update_option(self::PROGRESS_OPTION, [
            'hash' => self::sourceHash(),
            'offset' => $done,
        ], false);

        return $done;
    }

    /**
     * Fingerprint of the current source list, used to notice that a plugin
     * update changed the UI text. Derived from the `.pot` file's mtime,
     * which {@see sourceStrings()} already caches against — hashing all
     * ~1,400 strings on every admin page load would not be free.
     *
     * @return string
     */
    private static function sourceHash(): string
    {
        $potFile = ROX_APPOINTMENT_BOOKING_PATH . 'languages/rox-appointment-booking.pot';

        return file_exists($potFile) ? (string) filemtime($potFile) : '';
    }

    /**
     * Supply translations before core looks for a `.json` file at all.
     *
     * `load_script_textdomain()` gives up — and never applies the
     * `load_script_translations` filter — as soon as it finds no readable
     * file on disk, which is exactly the situation this bridge exists for:
     * the translations live in WPML/Polylang, not in a file. This runs
     * first, and returning anything other than null short-circuits core's
     * file lookup entirely.
     *
     * Any real file that DOES exist (a Loco export, say) is still read and
     * merged here, so a site using both keeps both. When this bridge has
     * nothing to contribute it returns null and core carries on normally —
     * short-circuiting there would rob every other plugin's
     * `load_script_translations` filter of its turn.
     *
     * @param string|false|null $translations Null by default.
     * @param string|false $file Path core was about to look for.
     * @param string $handle Script handle.
     * @param string $domain Text domain.
     * @return string|false|null
     */
    public function preBridgeTranslations($translations, $file, $handle, $domain)
    {
        if ($translations !== null || $this->targetLanguageFor($handle, $domain) === null) {
            return $translations;
        }

        $base = (is_string($file) && is_readable($file)) ? file_get_contents($file) : false;
        $built = $this->bridgeTranslations($base, $file, $handle, $domain);

        return is_string($built) ? $built : null;
    }

    /**
     * Supply a translations JSON blob for one of our scripts, built from the
     * active driver's data, layered over whatever core or an earlier filter
     * already resolved.
     *
     * @param string|false $translations JSON already resolved (by core, or
     *   an earlier filter), or false if none was found on disk.
     * @param string|false $file The `.json` file core looked for. Unused —
     *   its content, when there is any, arrives as $translations.
     * @param string $handle Script handle.
     * @param string $domain Text domain.
     * @return string|false
     */
    public function bridgeTranslations($translations, $file, $handle, $domain)
    {
        $lang = $this->targetLanguageFor($handle, $domain);

        if ($lang === null) {
            return $translations;
        }

        $cacheKey = self::CACHE_PREFIX . md5($lang);
        $cached = get_transient($cacheKey);

        // Only a cached blob with nothing to layer over can be served as-is;
        // otherwise the file-based translations passed in would be dropped.
        if (is_string($cached) && !is_string($translations)) {
            return $cached;
        }

        $json = $this->buildJson(
            rox_appointment_booking_multilingual(),
            $lang,
            is_string($translations) ? $translations : false
        );

        if ($json === null) {
            return $translations;
        }

        if (!is_string($translations)) {
            set_transient($cacheKey, $json, self::CACHE_TTL);
            self::rememberCachedLanguage($lang);
        }

        return $json;
    }

    /**
     * The language to serve for one script, or null when this bridge should
     * keep its hands off the request entirely.
     *
     * @param string $handle Script handle.
     * @param string $domain Text domain.
     * @return string|null
     */
    private function targetLanguageFor($handle, $domain): ?string
    {
        if ($domain !== 'rox-appointment-booking' || strpos((string) $handle, 'rox-appointment-booking') !== 0) {
            return null;
        }

        $service = rox_appointment_booking_multilingual();

        if (!$service->isActive()) {
            return null;
        }

        return $this->targetLanguage($service);
    }

    /**
     * The language this bridge should serve, or null when there is nothing
     * to bridge (source language — the compiled bundle already is that
     * text).
     *
     * wp-admin has no "page language" the way the frontend does — WPML's own
     * current-language filter stays pinned to the site default there, since
     * nothing about viewing a translated post applies to a custom React
     * screen. What actually varies per admin request is the logged-in user's
     * own profile Language — the same signal `determine_locale()` (and so
     * Loco's `.json` files) already key off — so that is used here instead,
     * letting the SAME WPML-registered strings serve both surfaces.
     *
     * @param \RoxAppointmentBooking\Modules\Multilingual\Services\MultilingualService $service
     * @return string|null
     */
    private function targetLanguage($service): ?string
    {
        if (!is_admin()) {
            $lang = $service->currentLanguage();

            return $lang !== $service->defaultLanguage() ? $lang : null;
        }

        $locale = strtolower(determine_locale());
        $default = strtolower($service->defaultLanguage());

        // A WPML/Polylang language code can be either a bare subtag ("bn")
        // or a hyphenated regional one ("pt-br"), while a WordPress locale
        // is always "bn_BD" style — so both shapes are compared.
        $candidates = [str_replace('_', '-', $locale), (string) strtok($locale, '_-')];

        foreach ($service->driver()->activeLanguages() as $activeLang) {
            $code = strtolower($activeLang);

            if ($code !== $default && in_array($code, $candidates, true)) {
                return $activeLang;
            }
        }

        return null;
    }

    /**
     * Build one Jed-format translations JSON from the driver's data,
     * layering it over whatever a real `.json` file already supplied so a
     * site using both Loco and this bridge does not lose either source.
     *
     * @param \RoxAppointmentBooking\Modules\Multilingual\Services\MultilingualService $service
     * @param string $lang
     * @param string|false $base Existing file-based JSON, if any.
     * @return string|null Null when the result cannot be encoded, so the
     *   caller can fall back to what it already had rather than serve a
     *   broken blob (a translation holding invalid UTF-8 is enough to make
     *   wp_json_encode() fail).
     */
    private function buildJson($service, string $lang, $base): ?string
    {
        $decoded = is_string($base) ? json_decode($base, true) : null;

        if (!is_array($decoded)) {
            $decoded = [];
        }

        $decoded['domain'] = 'rox-appointment-booking';

        if (!isset($decoded['locale_data']['rox-appointment-booking']) || !is_array($decoded['locale_data']['rox-appointment-booking'])) {
            $decoded['locale_data']['rox-appointment-booking'] = [];
        }

        $messages = &$decoded['locale_data']['rox-appointment-booking'];

        // The one thing every base MUST have and that a buggy or unexpected
        // source cannot be trusted to supply correctly: WPML's own String
        // Translation (from v3.5.4) hooks this same filter and puts this
        // header under `locale_data.messages` instead of the real domain key
        // — the browser's loader then reads `undefined` here and throws.
        // Rebuilding it fresh every time, keeping any extra field (e.g.
        // `plural-forms`) from wherever it landed, is the only shape that is
        // never wrong.
        $misplacedHeader = (array) ($decoded['locale_data']['messages'][''] ?? []);
        $existingHeader = is_array($messages[''] ?? null) ? $messages[''] : [];
        $messages[''] = array_merge($misplacedHeader, $existingHeader, ['domain' => 'rox-appointment-booking', 'lang' => $lang]);

        $driver = $service->driver();

        foreach (self::sourceStrings() as $string) {
            $string = (string) $string;

            // A file-based translation for this exact string already won —
            // leave it alone rather than risk a worse driver-side entry.
            if (isset($messages[$string])) {
                continue;
            }

            $translated = $driver->translateString($string, self::CONTEXT, self::stringName($string), $lang);

            if ($translated !== $string && $translated !== '') {
                $messages[$string] = [$translated];
            }
        }

        unset($messages);

        $json = wp_json_encode($decoded);

        return is_string($json) ? $json : null;
    }

    /**
     * Note that a language now has a cached blob, so flushCache() can find
     * it later without needing an active driver to enumerate languages.
     *
     * @param string $lang
     * @return void
     */
    private static function rememberCachedLanguage(string $lang): void
    {
        $known = get_option(self::CACHE_INDEX_OPTION, []);
        $known = is_array($known) ? $known : [];

        if (!in_array($lang, $known, true)) {
            $known[] = $lang;
            update_option(self::CACHE_INDEX_OPTION, $known, false);
        }
    }

    /**
     * Drop every cached translations blob — called from the "Re-scan
     * strings" endpoint so a just-saved translation shows up immediately
     * rather than waiting out {@see CACHE_TTL}.
     *
     * @return void
     */
    public static function flushCache(): void
    {
        $known = get_option(self::CACHE_INDEX_OPTION, []);

        foreach (is_array($known) ? $known : [] as $lang) {
            if (is_string($lang)) {
                delete_transient(self::CACHE_PREFIX . md5($lang));
            }
        }

        delete_option(self::CACHE_INDEX_OPTION);
    }

    /**
     * A stable, driver-safe name for a source string. Keyed by content, not
     * by call site, since the same English label (e.g. "Category") can come
     * from several components and must resolve to one translation.
     *
     * @param string $string
     * @return string
     */
    public static function stringName(string $string): string
    {
        return 'ui_' . md5($string);
    }

    /**
     * Every UI string worth registering: the `msgid` entries in the
     * plugin's own `.pot` whose `#:` reference points at JS/JSX source —
     * the half of the `.pot` a PHP-only tool (WPML's Theme and Plugin
     * Localization scanner) never reaches. Parsed once per `.pot` change,
     * not per request.
     *
     * @return string[]
     */
    public static function sourceStrings(): array
    {
        static $cached = null;

        if ($cached !== null) {
            return $cached;
        }

        $potFile = ROX_APPOINTMENT_BOOKING_PATH . 'languages/rox-appointment-booking.pot';
        $potMTime = file_exists($potFile) ? (string) filemtime($potFile) : '';
        $stored = get_option('rox_appointment_booking_ui_strings_cache', []);

        if (($stored['mtime'] ?? '') === $potMTime && is_array($stored['strings'] ?? null)) {
            return $cached = $stored['strings'];
        }

        $strings = self::parsePotForJsStrings($potFile);

        update_option('rox_appointment_booking_ui_strings_cache', [
            'mtime' => $potMTime,
            'strings' => $strings,
        ], false);

        return $cached = $strings;
    }

    /**
     * Extract `msgid` values whose preceding `#:` reference lines name at
     * least one `.js` or `.jsx` file — a plain line scan, not a full PO
     * parser, since `.pot` entries are single-line `msgid "..."` here (this
     * plugin's strings are never multiline).
     *
     * @param string $potFile
     * @return string[]
     */
    private static function parsePotForJsStrings(string $potFile): array
    {
        if (!file_exists($potFile)) {
            return [];
        }

        $lines = file($potFile, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            return [];
        }

        $strings = [];
        $isJs = false;
        $hasAnyRef = false;

        foreach ($lines as $line) {
            if ($line === '') {
                $isJs = false;
                $hasAnyRef = false;
                continue;
            }

            if (strpos($line, '#:') === 0) {
                $hasAnyRef = true;
                if (preg_match('/\.jsx?(:\d+)?(\s|$)/', $line)) {
                    $isJs = true;
                }
                continue;
            }

            if ($hasAnyRef && $isJs && strpos($line, 'msgid "') === 0) {
                $value = self::unescapePoString(substr($line, 7, -1));

                // Header row, plugin-header metadata, and blanks are never
                // real UI text.
                if ($value !== '') {
                    $strings[$value] = true;
                }
            }
        }

        return array_keys($strings);
    }

    /**
     * Undo PO string escaping (`\"`, `\\`, `\n`) for one `msgid` payload.
     *
     * @param string $value
     * @return string
     */
    private static function unescapePoString(string $value): string
    {
        return str_replace(['\\"', '\\\\', '\\n', '\\t'], ['"', '\\', "\n", "\t"], $value);
    }
}
