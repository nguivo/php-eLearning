<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Validator;

/**
 * UpdateCourseValidator
 *
 * Validates the "edit course" form. Almost identical to CreateCourseValidator
 * but all fields are OPTIONAL (nullable) because the instructor may submit
 * only the fields they changed — a partial update (PATCH/PUT semantics).
 *
 * Fields that are absent or empty are simply skipped; fields that ARE
 * present are still validated against their rules.
 *
 * This is the key difference from CreateCourseValidator:
 *   Create → every field is required
 *   Update → every field is optional, but if provided it must be valid
 *
 * Usage in Instructor\CourseController:
 *
 *   public function update(Request $request, Response $response): void
 *   {
 *       $id        = (int) $request->getRouteParam('id');
 *       $validator = new UpdateCourseValidator();
 *
 *       if (!$validator->validate($this->all())) {
 *           $this->flash('errors', $validator->errors());
 *           return $this->redirectBack();
 *       }
 *
 *       // validated() only contains the fields that were actually submitted
 *       // and passed — nothing more
 *       $this->courseService->update($id, $validator->validated());
 *
 *       $this->flash('success', 'Course updated.');
 *       return $this->redirect('/instructor/courses');
 *   }
 */
class UpdateCourseValidator extends Validator
{
    protected function rules(): array
    {
        return [
            // nullable means: if not provided, skip all checks and include as-is
            'title'       => 'nullable|string|min:5|max:255',
            'description' => 'nullable|string|min:20',
            'price'       => 'nullable|numeric|min_val:0',
            'category_id' => 'nullable|integer|min_val:1',
            'level'       => 'nullable|in:beginner,intermediate,advanced',
        ];
    }

    protected function messages(): array
    {
        return [
            'title.min'       => 'The title must be at least 5 characters.',
            'price.numeric'   => 'Price must be a number (e.g. 29.99).',
            'price.min_val'   => 'Price cannot be negative.',
            'level.in'        => 'Level must be beginner, intermediate, or advanced.',
        ];
    }
}