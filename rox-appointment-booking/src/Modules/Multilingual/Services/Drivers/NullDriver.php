<?php

namespace RoxAppointmentBooking\Modules\Multilingual\Services\Drivers;

defined('ABSPATH') || exit;

/**
 * Class NullDriver
 *
 * @package RoxAppointmentBooking\Modules\Multilingual\Services\Drivers
 * @description Used when no supported multilingual plugin is active. Every
 *              method is a passthrough, so call sites are identical whether or
 *              not WPML is installed and the single-language install pays no
 *              measurable cost.
 */
class NullDriver implements DriverInterface
{
    /**
     * Whether a real multilingual plugin is backing this driver.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return false;
    }

    /**
     * Short machine name of the plugin behind this driver.
     *
     * @return string
     */
    public function id(): string
    {
        return 'none';
    }

    /**
     * No-op — there is nowhere to register a string.
     *
     * @param string $context Translator-facing group name.
     * @param string $name Unique string name within the context.
     * @param string $value Source value in the default language.
     * @return void
     */
    public function registerString(string $context, string $name, string $value): void
    {
    }

    /**
     * Returns the source value untouched.
     *
     * @param string $value Source value.
     * @param string $context Translator-facing group name.
     * @param string $name Unique string name within the context.
     * @param string|null $lang Ignored.
     * @return string
     */
    public function translateString(string $value, string $context, string $name, ?string $lang = null): string
    {
        return $value;
    }

    /**
     * No-op — nothing was ever registered.
     *
     * @param string $context Translator-facing group name.
     * @param string $name Unique string name within the context.
     * @return void
     */
    public function unregisterString(string $context, string $name): void
    {
    }

    /**
     * Returns the post id untouched — there are no translations to point at.
     *
     * @param int $postId Source post id.
     * @param string $postType Ignored.
     * @return int
     */
    public function translatedPostId(int $postId, string $postType): int
    {
        return $postId;
    }

    /**
     * The site locale reduced to a language code, e.g. `en_US` becomes `en`.
     *
     * Keeps the return shape identical to WPML's two-letter codes so callers
     * never have to care which driver answered.
     *
     * @return string
     */
    public function currentLanguage(): string
    {
        return $this->languageFromLocale(determine_locale());
    }

    /**
     * Same as the current language — a single-language site has only one.
     *
     * @return string
     */
    public function defaultLanguage(): string
    {
        return $this->languageFromLocale(get_locale());
    }

    /**
     * The one language this site has.
     *
     * @return string[]
     */
    public function activeLanguages(): array
    {
        return [$this->defaultLanguage()];
    }

    /**
     * No-op — there is nothing to switch to.
     *
     * @param string|null $lang Ignored.
     * @return void
     */
    public function switchLanguage(?string $lang): void
    {
    }

    /**
     * Reduce a WordPress locale to its language subtag.
     *
     * @param string $locale WordPress locale, e.g. `de_DE` or `en`.
     * @return string
     */
    private function languageFromLocale(string $locale): string
    {
        $language = strtolower((string) strtok($locale, '_-'));

        return $language !== '' ? $language : 'en';
    }
}
