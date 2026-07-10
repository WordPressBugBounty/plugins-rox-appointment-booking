<?php

namespace RoxAppointmentBooking\Modules\UserManagement\Util;

if (! defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Class UserInfo
 * 
 * Provides current user related data and utility methods.
 * 
 * @package RoxAppointmentBooking\Modules\UserManagement\Util
 * @since 1.0.0
 */
class UserInfo
{
    /**
     * @var \WP_User|null Current user object
     */
    private ?\WP_User $user = null;

    /**
     * Whether the class is loadable.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * Constructor.
     * 
     * Initializes the current user.
     */
    public function __construct()
    {
        $this->user = wp_get_current_user();
    }

    /**
     * Get the current user object.
     *
     * @return \WP_User|null
     */
    public function getUser(): ?\WP_User
    {
        return $this->user;
    }

    /**
     * Get the user's ID.
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->user->ID ?? 0;
    }

    /**
     * Get the user's full name.
     *
     * @return string
     */
    public function getFullName(): string
    {
        $firstName = $this->getFirstName();
        $lastName = $this->getLastName();

        $fullName = trim($firstName . ' ' . $lastName);

        return $fullName ?: $this->getDisplayName();
    }

    /**
     * Get the user's first name.
     *
     * @return string
     */
    public function getFirstName(): string
    {
        return $this->user->first_name ?? '';
    }

    /**
     * Get the user's last name.
     *
     * @return string
     */
    public function getLastName(): string
    {
        return $this->user->last_name ?? '';
    }

    /**
     * Get the user's display name.
     *
     * @return string
     */
    public function getDisplayName(): string
    {
        return $this->user->display_name ?? '';
    }

    /**
     * Get the user's username (login).
     *
     * @return string
     */
    public function getUsername(): string
    {
        return $this->user->user_login ?? '';
    }

    /**
     * Get the user's email.
     *
     * @return string
     */
    public function getEmail(): string
    {
        return $this->user->user_email ?? '';
    }

    /**
     * Get the user's roles.
     *
     * @return array
     */
    public function getRoles(): array
    {
        return $this->user->roles ?? [];
    }

    /**
     * Get the user's primary role.
     *
     * @return string
     */
    public function getRole(): string
    {
        $roles = $this->getRoles();
        return !empty($roles) ? $roles[0] : '';
    }

    /**
     * Get the user's role display name.
     *
     * @return string
     */
    public function getRoleDisplayName(): string
    {
        $role = $this->getRole();
        if (empty($role)) {
            return '';
        }

        $wp_roles = wp_roles();
        return $wp_roles->role_names[$role] ?? $role;
    }

    /**
     * Check if the user has a specific role.
     *
     * @param string $role Role to check.
     * @return bool
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles(), true);
    }

    /**
     * Check if the user has a specific capability.
     *
     * @param string $capability Capability to check.
     * @return bool
     */
    public function hasCapability(string $capability): bool
    {
        return user_can($this->user, $capability);
    }

    /**
     * Get the user's avatar URL.
     *
     * @param int $size Avatar size in pixels.
     * @return string
     */
    public function getAvatarUrl(int $size = 96): string
    {
        return get_avatar_url($this->getId(), ['size' => $size, 'default' => '404']) ?: '';
    }

    /**
     * Get the user's registration date.
     *
     * @param string $format Date format.
     * @return string
     */
    public function getRegistrationDate(string $format = 'Y-m-d H:i:s'): string
    {
        return $this->user->user_registered 
            ? gmdate($format, strtotime($this->user->user_registered)) 
            : '';
    }

    /**
     * Get the user's website URL.
     *
     * @return string
     */
    public function getWebsiteUrl(): string
    {
        return $this->user->user_url ?? '';
    }

    /**
     * Get the user's bio/description.
     *
     * @return string
     */
    public function getBio(): string
    {
        return get_user_meta($this->getId(), 'description', true) ?: '';
    }

    /**
     * Get user meta value.
     *
     * @param string $key Meta key.
     * @param bool $single Whether to return a single value.
     * @return mixed
     */
    public function getMeta(string $key, bool $single = true): mixed
    {
        return get_user_meta($this->getId(), $key, $single);
    }

    /**
     * Check if the user is logged in.
     *
     * @return bool
     */
    public function isLoggedIn(): bool
    {
        return is_user_logged_in();
    }

    /**
     * Check if the user is an administrator.
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->hasCapability('manage_options');
    }

    /**
     * Get the user's locale.
     *
     * @return string
     */
    public function getLocale(): string
    {
        return get_user_locale($this->getId());
    }

    /**
     * Get the user's gender.
     *
     * @return string
     */
    public function getGender(): string
    {
        return get_user_meta($this->getId(), 'gender', true) ?: '';
    }

    /**
     * Get the user's date of birth.
     *
     * @return string
     */
    public function getDateOfBirth(): string
    {
        return get_user_meta($this->getId(), 'date_of_birth', true) ?: '';
    }

    /**
     * Get the user's phone number.
     *
     * @return string
     */
    public function getPhone(): string
    {
        return get_user_meta($this->getId(), 'phone', true) ?: '';
    }

    /**
     * Get the user's internal notes.
     *
     * @return string
     */
    public function getInternalNotes(): string
    {
        return get_user_meta($this->getId(), 'internal_notes', true) ?: '';
    }

    /**
     * Get user profile data as an array.
     *
     * @return array
     */
    public function getUserData(): array
    {
        return [
            'first_name' => $this->getFirstName(),
            'last_name' => $this->getLastName(),
            'email' => $this->getEmail(),
            'gender' => $this->getGender(),
            'dob' => $this->getDateOfBirth(),
            'phone' => $this->getPhone(),
            'internal_notes' => $this->getInternalNotes(),
        ];
    }
}
