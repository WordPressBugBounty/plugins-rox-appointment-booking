<?php

namespace RoxAppointmentBooking\Modules\Multilingual\Services\Drivers;

defined('ABSPATH') || exit;

/**
 * Interface DriverInterface
 *
 * @package RoxAppointmentBooking\Modules\Multilingual\Services\Drivers
 * @description The seam between the plugin and whichever multilingual plugin is
 *              active. Every call site goes through MultilingualService, which
 *              holds exactly one driver, so no other file in the plugin ever
 *              checks for WPML directly. Adding Polylang later means adding a
 *              driver here, not touching call sites.
 */
interface DriverInterface
{
    /**
     * Whether a real multilingual plugin is backing this driver.
     *
     * @return bool
     */
    public function isActive(): bool;

    /**
     * Short machine name of the plugin behind this driver.
     *
     * Used for the `rox-multilingual-<id>` admin body class, so CSS can target
     * one specific plugin's interference — the button/tag/checkbox guard in
     * `app.scss` exists only to counter WPML's stylesheet and must not apply
     * under any other driver.
     *
     * @return string e.g. `wpml`, `polylang`, or `none`.
     */
    public function id(): string;

    /**
     * Register a source string so it becomes available for translation.
     *
     * Must be idempotent — it runs on every save of the owning record.
     *
     * @param string $context Translator-facing group name.
     * @param string $name Unique string name within the context.
     * @param string $value Source value in the default language.
     * @return void
     */
    public function registerString(string $context, string $name, string $value): void;

    /**
     * Translate a previously registered string.
     *
     * Falls back to $value when no translation exists — never returns empty.
     *
     * @param string $value Source value, used as the fallback.
     * @param string $context Translator-facing group name.
     * @param string $name Unique string name within the context.
     * @param string|null $lang Target language code, or null for the current one.
     * @return string
     */
    public function translateString(string $value, string $context, string $name, ?string $lang = null): string;

    /**
     * Drop a source string, e.g. when its record is deleted.
     *
     * @param string $context Translator-facing group name.
     * @param string $name Unique string name within the context.
     * @return void
     */
    public function unregisterString(string $context, string $name): void;

    /**
     * The id of a post's translation in the active language.
     *
     * Used for real WordPress posts and pages the plugin links to — the
     * customer dashboard page above all — so a link handed to a German visitor
     * points at the German page rather than the source one.
     *
     * @param int $postId Source post id.
     * @param string $postType Post type, e.g. `page`.
     * @return int The translated id, or $postId when there is no translation.
     */
    public function translatedPostId(int $postId, string $postType): int;

    /**
     * The language code active for this request.
     *
     * @return string
     */
    public function currentLanguage(): string;

    /**
     * The site's default (source) language code.
     *
     * @return string
     */
    public function defaultLanguage(): string;

    /**
     * All language codes configured on the site.
     *
     * @return string[]
     */
    public function activeLanguages(): array;

    /**
     * Switch the active language for the remainder of the request.
     *
     * Callers must restore the previous language in a `finally` block — a
     * leaked switch corrupts everything rendered later in the same process,
     * e-mails included.
     *
     * @param string|null $lang Language code, or null to restore the default.
     * @return void
     */
    public function switchLanguage(?string $lang): void;
}
