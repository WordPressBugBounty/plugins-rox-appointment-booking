<?php

namespace RoxAppointmentBooking\Modules\Multilingual\Services\Drivers;

defined('ABSPATH') || exit;

/**
 * Class PolylangDriver
 *
 * @package RoxAppointmentBooking\Modules\Multilingual\Services\Drivers
 * @description Talks to Polylang through its documented `pll_*` helper
 *              functions. Every call is guarded with `function_exists()` so a
 *              renamed or removed helper degrades to the untranslated source
 *              string rather than a fatal.
 *
 *              **Polylang is not WPML, and two differences change behaviour:**
 *
 *              1. **Translations are keyed by the source text, not by name.**
 *                 WPML looks a string up by context + name, so `service_3_title`
 *                 and `service_9_title` can hold different translations even
 *                 when both read "Consultation". Polylang's `pll__()` /
 *                 `pll_translate_string()` look up by the string itself, so two
 *                 records sharing a title share one translation. The
 *                 `{entity}_{id}_{field}` name is still passed to
 *                 `pll_register_string()` — it is what a translator sees in
 *                 Languages -> Strings translations — but it does not separate
 *                 the translations. Same title, same translation: a limitation
 *                 of Polylang, not something this driver can work around.
 *
 *              2. **Registration is per request, not stored.**
 *                 `pll_register_string()` adds to an in-memory list that builds
 *                 the Strings translations screen; it writes nothing durable.
 *                 A string therefore has to be re-registered on every admin
 *                 request to stay visible, which `MultilingualService` arranges
 *                 by running the backfill scan on `admin_init` under this
 *                 driver. Translation itself does not depend on registration —
 *                 `pll_translate_string()` reads the saved translations
 *                 directly — so the public booking panel works either way.
 *
 *              Consequently `unregisterString()` is a no-op: there is no stored
 *              record to remove, and a string simply stops being listed once
 *              nothing registers it.
 */
class PolylangDriver implements DriverInterface
{
    /**
     * Whether a real multilingual plugin is backing this driver.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return true;
    }

    /**
     * Short machine name of the plugin behind this driver.
     *
     * @return string
     */
    public function id(): string
    {
        return 'polylang';
    }

    /**
     * Register a source string so it appears in Languages -> Strings
     * translations.
     *
     * @param string $context Group name shown to the translator.
     * @param string $name Unique string name within the context.
     * @param string $value Source value in the default language.
     * @return void
     */
    public function registerString(string $context, string $name, string $value): void
    {
        if (!function_exists('pll_register_string')) {
            return;
        }

        // Polylang's signature is (name, string, context, multiline). The name
        // is the translator-facing label, the context the group heading — the
        // reverse emphasis of WPML's, hence the argument order here.
        pll_register_string($name, $value, $context, strpos($value, "\n") !== false);
    }

    /**
     * Translate a previously registered string.
     *
     * @param string $value Source value, used as the fallback.
     * @param string $context Unused by Polylang's lookup; kept for the contract.
     * @param string $name Unused by Polylang's lookup; kept for the contract.
     * @param string|null $lang Target language code, or null for the current one.
     * @return string
     */
    public function translateString(string $value, string $context, string $name, ?string $lang = null): string
    {
        if ($value === '') {
            return $value;
        }

        // Always resolve to an explicit language and always go through
        // `pll_translate_string()`.
        //
        // `pll__()` is `__( $string, 'pll_string' )`, and Polylang loads that
        // text domain once per request from whatever language was current at
        // the time. Switching the language afterwards — which is exactly what
        // `?lang=` on a REST call and recipient-language e-mails do — does not
        // reload it, so `pll__()` keeps answering in the original language.
        // `pll_translate_string()` reads the requested language's stored
        // translations directly and is not affected.
        if (!function_exists('pll_translate_string')) {
            return $value;
        }

        $translated = pll_translate_string($value, $lang ?? $this->currentLanguage());

        // Polylang returns the source unchanged when there is no translation,
        // but guard against an empty saved translation the same way the WPML
        // driver does — a nameless service is worse than an untranslated one.
        return (is_string($translated) && $translated !== '') ? $translated : $value;
    }

    /**
     * No-op: Polylang stores no registration to remove.
     *
     * @param string $context Group name.
     * @param string $name String name.
     * @return void
     */
    public function unregisterString(string $context, string $name): void
    {
    }

    /**
     * The id of a post's translation in the active language.
     *
     * @param int $postId Source post id.
     * @param string $postType Unused by Polylang; kept for the contract.
     * @return int The translated id, or $postId when there is no translation.
     */
    public function translatedPostId(int $postId, string $postType): int
    {
        if (!function_exists('pll_get_post')) {
            return $postId;
        }

        $translated = pll_get_post($postId, $this->currentLanguage());

        // pll_get_post() returns false when the post has no translation, and
        // null when the language is unknown.
        return is_numeric($translated) && (int) $translated > 0 ? (int) $translated : $postId;
    }

    /**
     * The language code active for this request.
     *
     * @return string
     */
    public function currentLanguage(): string
    {
        if (function_exists('pll_current_language')) {
            $language = pll_current_language('slug');

            if (is_string($language) && $language !== '') {
                return $language;
            }
        }

        // pll_current_language() returns false in contexts with no language
        // resolved — WP-Cron and REST among them, which is exactly where the
        // reminder e-mails run — so fall back rather than return an empty code.
        return $this->defaultLanguage();
    }

    /**
     * The site's default language code.
     *
     * @return string
     */
    public function defaultLanguage(): string
    {
        if (function_exists('pll_default_language')) {
            $language = pll_default_language('slug');

            if (is_string($language) && $language !== '') {
                return $language;
            }
        }

        return 'en';
    }

    /**
     * All language codes configured on the site.
     *
     * @return string[]
     */
    public function activeLanguages(): array
    {
        if (!function_exists('pll_languages_list')) {
            return [];
        }

        $languages = pll_languages_list(['fields' => 'slug']);

        return is_array($languages) ? array_map('strval', $languages) : [];
    }

    /**
     * Switch the active language for the remainder of the request.
     *
     * Polylang exposes no public setter for the current language, so this
     * reaches into `PLL()->curlang` — the assignment its own documentation and
     * support answers point people at. It is guarded on every step and simply
     * does nothing if the internals move, in which case
     * `translateString()`'s explicit `$lang` argument still carries the cases
     * that matter most (recipient-language e-mails, `?lang=` on REST).
     *
     * @param string|null $lang Language code, or null to restore the default.
     * @return void
     */
    public function switchLanguage(?string $lang): void
    {
        if (!function_exists('PLL')) {
            return;
        }

        $pll = PLL();

        if (!is_object($pll) || !isset($pll->model) || !is_object($pll->model)) {
            return;
        }

        $target = $lang ?? $this->defaultLanguage();

        // `is_callable()`, not `method_exists()`. Polylang 3.x proxies its model
        // methods through `__call()`, so `method_exists($pll->model,
        // 'get_language')` is false even though the call works — an earlier
        // guard used it and silently blocked every language switch, which took
        // `?lang=` and the default-language save guard down with it.
        if (!is_callable([$pll->model, 'get_language'])) {
            return;
        }

        $language = $pll->model->get_language($target);

        if ($language) {
            $pll->curlang = $language;
        }
    }
}
