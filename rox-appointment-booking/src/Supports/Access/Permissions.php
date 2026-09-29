<?php

namespace RoxAppointmentBooking\Supports\Access;

if (!defined('ABSPATH')) exit;

use RoxAppointmentBooking\Supports\Security;

/**
 * Single answer to "may the current user do this?".
 *
 * Every gate in the plugin asks this class instead of calling current_user_can()
 * or Security directly. The answer is a hardcoded default — deliberately the
 * same rule that gate used before — passed through a filter, so an add-on can
 * grant more than the free plugin does.
 *
 * An add-on must only ever widen the answer. Narrowing it here would mean
 * deactivating that add-on silently opens up access, so the free defaults below
 * are the floor and nothing may lower them.
 *
 * Answers are for the current user only.
 *
 * @package RoxAppointmentBooking
 * @subpackage Supports\Access
 * @since 1.2.8
 */
class Permissions
{
    /**
     * Filters the yes/no answer for one capability.
     */
    public const FILTER_CAN = 'rox_appointment_booking_user_can';

    /**
     * Filters whether a resource is limited to the user's own rows.
     */
    public const FILTER_SCOPE = 'rox_appointment_booking_user_scope';

    /**
     * Filters the capability registry so add-ons can declare their own.
     */
    public const FILTER_CAPABILITIES = 'rox_appointment_booking_capabilities';

    /**
     * The user sees only rows belonging to them.
     */
    public const SCOPE_OWN = 'own';

    /**
     * The user sees every row.
     */
    public const SCOPE_ALL = 'all';

    /**
     * Known capabilities, as resource => actions.
     *
     * Add-ons append their own resources through the registry filter; a
     * capability that is not listed here is always denied.
     *
     * @return array<string, string[]>
     */
    public static function capabilities(): array
    {
        $capabilities = [
            // Reaching the panel at all, for endpoints that serve every page
            // rather than one resource.
            'panel'        => ['access'],
            'dashboard'    => ['view'],
            'appointment'  => ['view', 'create', 'edit', 'delete', 'reschedule', 'cancel', 'change_status'],
            'calendar'     => ['view'],
            'customer'     => ['view', 'create', 'edit', 'delete'],
            'order'        => ['view', 'edit', 'delete', 'refund'],
            'payment'      => ['view', 'create'],
            // Also governs service categories.
            'service'      => ['view', 'create', 'edit', 'delete'],
            'agent'        => ['view', 'create', 'edit', 'delete'],
            // Pro features, but declared here because the free bundle renders
            // their pages and menu items and has to know the keys.
            'location'     => ['view', 'create', 'edit', 'delete'],
            'coupon'       => ['view', 'create', 'edit', 'delete'],
            'custom_field' => ['view', 'edit'],
            'notification' => ['view', 'edit'],
            'profile'      => ['view', 'edit'],
            // Derived: true when any `settings_*` tab below grants the action.
            'settings'     => ['view', 'edit'],
            // One pair per Settings tab.
            'settings_general'       => ['view', 'edit'],
            'settings_work_schedule' => ['view', 'edit'],
            'settings_email'         => ['view', 'edit'],
            'settings_payments'      => ['view', 'edit'],
            'integration'  => ['view', 'edit'],
            // Managing what a role may do. Never offered inside a permission
            // set: a role able to widen itself would make every other key here
            // meaningless.
            'role'         => ['view', 'create', 'edit', 'delete'],
        ];

        return (array) apply_filters(self::FILTER_CAPABILITIES, $capabilities);
    }

    /**
     * Resources whose rows can belong to one user, so a reader may be limited to
     * their own. Everything else is all-or-nothing and has no scope.
     *
     * @return string[]
     */
    public static function scopedResources(): array
    {
        return ['appointment', 'calendar', 'service'];
    }

    /**
     * Whether the current user holds a capability, e.g. 'appointment.edit'.
     *
     * @param string $capability Capability key in "resource.action" form.
     * @return bool
     */
    public static function can(string $capability): bool
    {
        [$resource, $action] = self::split($capability);

        if (!self::isKnown($resource, $action)) {
            self::warn(sprintf('Unknown capability "%s".', $capability));
            return false;
        }

        if ($resource === 'settings') {
            return self::canAnySettingsTab($action);
        }

        $default = self::coreDefault($resource, $action);

        return (bool) apply_filters(self::FILTER_CAN, $default, $capability, get_current_user_id());
    }

    /**
     * Whether any Settings tab grants an action, e.g. 'view'.
     *
     * @param string $action
     * @return bool
     */
    protected static function canAnySettingsTab(string $action): bool
    {
        foreach (array_keys(self::capabilities()) as $resource) {
            if (strpos($resource, 'settings_') === 0 && self::can("$resource.$action")) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the current user reads all rows of a resource or only their own.
     *
     * @param string $resource Resource key, e.g. 'appointment'.
     * @return string One of SCOPE_OWN or SCOPE_ALL.
     */
    public static function scopeFor(string $resource): string
    {
        if (!isset(self::capabilities()[$resource])) {
            self::warn(sprintf('Unknown resource "%s".', $resource));
            return self::SCOPE_OWN;
        }

        if (!in_array($resource, self::scopedResources(), true)) {
            return self::SCOPE_ALL;
        }

        $default = self::coreScopeDefault();

        $scope = apply_filters(self::FILTER_SCOPE, $default, $resource, get_current_user_id());

        return $scope === self::SCOPE_ALL ? self::SCOPE_ALL : self::SCOPE_OWN;
    }

    /**
     * Every capability the current user holds, as flat "resource.action" keys.
     *
     * Injected into the admin app config so the menu and the route guard are
     * built from the same answers the REST endpoints enforce, instead of a
     * second hardcoded list that can drift.
     *
     * @return string[]
     */
    public static function grantedCapabilities(): array
    {
        $granted = [];

        foreach (self::capabilities() as $resource => $actions) {
            foreach ((array) $actions as $action) {
                if (self::can("$resource.$action")) {
                    $granted[] = "$resource.$action";
                }
            }
        }

        return $granted;
    }

    /**
     * The scope the current user reads each scoped resource at.
     *
     * Injected alongside the capabilities so the admin app can tell "my rows"
     * from "every row": a table that drops its Agent column because every row
     * belongs to the viewer has to stop doing that once the viewer reads them
     * all.
     *
     * @return array<string, string>
     */
    public static function grantedScopes(): array
    {
        $scopes = [];

        foreach (self::scopedResources() as $resource) {
            $scopes[$resource] = self::scopeFor($resource);
        }

        return $scopes;
    }

    /**
     * The free plugin's answer for a capability, before any filter.
     *
     * Each branch reproduces the gate that guarded this capability before the
     * facade existed, so routing a gate through can() cannot change behaviour.
     *
     * @param string $resource
     * @param string $action
     * @return bool
     */
    protected static function coreDefault(string $resource, string $action): bool
    {
        return match ($resource) {
            // Reading an appointment is open to the whole panel; scopeFor()
            // decides whose. Writing needs a manager.
            'appointment' => match ($action) {
                'view'       => Security::canAccessPanel(),
                'reschedule' => Security::canManageBookings() || rox_appointment_booking_agent_can_reschedule(),
                'cancel'     => Security::canManageBookings() || rox_appointment_booking_agent_can_cancel(),
                default      => Security::canManageBookings(),
            },
            // An agent works their own calendar, edits their own profile, and
            // reaches the shared panel endpoints.
            'calendar', 'profile', 'panel' => Security::canAccessPanel(),
            // Agents read the services assigned to them; only admins edit them.
            'service' => $action === 'view'
                ? Security::canAccessPanel()
                : current_user_can('manage_options'),
            default => current_user_can('manage_options'),
        };
    }

    /**
     * The free plugin's scope for a scoped resource, before any filter. Anyone
     * who cannot manage bookings sees only their own rows.
     *
     * @return string
     */
    protected static function coreScopeDefault(): string
    {
        return Security::canManageBookings() ? self::SCOPE_ALL : self::SCOPE_OWN;
    }

    /**
     * Split "resource.action" into its two halves.
     *
     * @param string $capability
     * @return array{0: string, 1: string}
     */
    protected static function split(string $capability): array
    {
        $parts = explode('.', $capability, 2);

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    /**
     * Whether the registry lists this resource/action pair.
     *
     * @param string $resource
     * @param string $action
     * @return bool
     */
    protected static function isKnown(string $resource, string $action): bool
    {
        $capabilities = self::capabilities();

        return isset($capabilities[$resource])
            && in_array($action, (array) $capabilities[$resource], true);
    }

    /**
     * Flag a mistyped capability during development. A typo would otherwise deny
     * silently, which is safe but very hard to trace.
     *
     * @param string $message
     * @return void
     */
    protected static function warn(string $message): void
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        _doing_it_wrong(__METHOD__, esc_html($message), ROX_APPOINTMENT_BOOKING_VERSION);
    }
}
