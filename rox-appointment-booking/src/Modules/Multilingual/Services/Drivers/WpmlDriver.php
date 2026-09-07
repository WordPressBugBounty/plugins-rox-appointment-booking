<?php

namespace RoxAppointmentBooking\Modules\Multilingual\Services\Drivers;

defined('ABSPATH') || exit;

/**
 * Class WpmlDriver
 *
 * @package RoxAppointmentBooking\Modules\Multilingual\Services\Drivers
 * @description Talks to WPML through its documented action/filter API only —
 *              no SitePress or WPML class is referenced directly, so a WPML
 *              internals change cannot fatal the plugin. Requires WPML core
 *              *and* String Translation; MultilingualService decides that and
 *              only then constructs this driver.
 */
class WpmlDriver implements DriverInterface
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
        return 'wpml';
    }

    /**
     * Register a source string with WPML String Translation.
     *
     * Idempotent: WPML updates the source and flags existing translations as
     * "needs update" when the original text changes.
     *
     * @param string $context Translator-facing group name.
     * @param string $name Unique string name within the context.
     * @param string $value Source value in the default language.
     * @return void
     */
    public function registerString(string $context, string $name, string $value): void
    {
        do_action('wpml_register_single_string', $context, $name, $value);
    }

    /**
     * Translate a previously registered string.
     *
     * @param string $value Source value, used as the fallback.
     * @param string $context Translator-facing group name.
     * @param string $name Unique string name within the context.
     * @param string|null $lang Target language code, or null for the current one.
     * @return string
     */
    public function translateString(string $value, string $context, string $name, ?string $lang = null): string
    {
        $translated = apply_filters('wpml_translate_single_string', $value, $context, $name, $lang);

        // WPML returns an empty string for a translation that exists but was
        // saved blank. Falling back keeps the panel from rendering a nameless
        // service, which is worse than showing the source language.
        return (is_string($translated) && $translated !== '') ? $translated : $value;
    }

    /**
     * Drop a source string from String Translation.
     *
     * `icl_unregister_string()` is WPML's legacy API and has no modern
     * action/filter equivalent, so it is guarded rather than assumed.
     *
     * @param string $context Translator-facing group name.
     * @param string $name Unique string name within the context.
     * @return void
     */
    public function unregisterString(string $context, string $name): void
    {
        if (function_exists('icl_unregister_string')) {
            icl_unregister_string($context, $name);
        }
    }

    /**
     * The id of a post's translation in the active language.
     *
     * The third argument tells WPML to return the original id when no
     * translation exists, so a link never dead-ends on a missing page.
     *
     * @param int $postId Source post id.
     * @param string $postType Post type, e.g. `page`.
     * @return int
     */
    public function translatedPostId(int $postId, string $postType): int
    {
        $translated = apply_filters('wpml_object_id', $postId, $postType, true);

        return is_numeric($translated) ? (int) $translated : $postId;
    }

    /**
     * The language code active for this request.
     *
     * @return string
     */
    public function currentLanguage(): string
    {
        $language = apply_filters('wpml_current_language', null);

        return is_string($language) && $language !== '' ? $language : $this->defaultLanguage();
    }

    /**
     * The site's default (source) language code.
     *
     * @return string
     */
    public function defaultLanguage(): string
    {
        $language = apply_filters('wpml_default_language', null);

        return is_string($language) && $language !== '' ? $language : 'en';
    }

    /**
     * All language codes configured on the site.
     *
     * @return string[]
     */
    public function activeLanguages(): array
    {
        $languages = apply_filters('wpml_active_languages', null, []);

        return is_array($languages) ? array_map('strval', array_keys($languages)) : [];
    }

    /**
     * Switch the active language for the remainder of the request.
     *
     * @param string|null $lang Language code, or null to restore the default.
     * @return void
     */
    public function switchLanguage(?string $lang): void
    {
        do_action('wpml_switch_language', $lang);
    }
}
