<?php

namespace RoxAppointmentBooking\Modules\Category\Services;

use RoxAppointmentBooking\Modules\Category\Data\CategoryModel;
use RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel;
use RoxAppointmentBooking\Modules\RelationshipModel\Data\ServiceCategoryRelationModel;

/**
 * Category service helper.
 *
 * @package RoxAppointmentBooking
 * @since 1.0.0
 */
class CategoryService
{
    /**
     * Check if a category has any appointments
     *
     * @param int $categoryId
     * @return bool
     */
    public static function hasAppointments(int $categoryId): bool
    {
        // Assuming AppointmentModel has a method to query by category_id
        $appointments = AppointmentModel::where('category_id', $categoryId)->get();
        return !empty($appointments);
    }

    /**
     * Create a new category
     *
     * @param array $data
     * @return CategoryModel|null
     */
    public static function createCategory(array $data): ?CategoryModel
    {
        $category = new CategoryModel($data);
        return $category->save() ? $category : null;
    }

    /**
     * Update an existing category
     *
     * @param int $categoryId
     * @param array $data
     * @return bool
     */
    public static function updateCategory(int $categoryId, array $data): bool
    {
        $category = CategoryModel::find($categoryId);
        if (!$category) {
            return false;
        }
        foreach ($data as $key => $value) {
            $category->$key = $value;
        }
        return $category->save();
    }

    /**
     * Delete a category
     *
     * @param int $categoryId
     * @return bool
     */
    public static function deleteCategory(int $categoryId): bool
    {
        $category = CategoryModel::find($categoryId);
        if (!$category) {
            return false;
        }
        return $category->delete();
    }

    /**
     * Get all categories
     *
     * @return array
     */
    public static function getCategories(): array
    {
        return CategoryModel::all()->toArray();
    }

    public static function getServicesCountByCatId(int $categoryId): int
    {
        return ServiceCategoryRelationModel::where('category_id', $categoryId)->count();
    }
}