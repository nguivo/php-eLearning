<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Validator;

/**
 * CreateCourseValidator
 *
 * Validates the "create course" form submitted by instructors.
 * Used by Instructor\CourseController::store() before calling CourseService::create().
 *
 * Note the use of prepare() to auto-generate the slug from the title.
 * The slug is then available in validated() and passed to the service,
 * without the instructor needing to type it manually.
 *
 * Usage in Instructor\CourseController:
 *
 *   public function store(Request $request, Response $response): void
 *   {
 *       $validator = new CreateCourseValidator();
 *
 *       if (!$validator->validate($this->all())) {
 *           $this->flash('errors', $validator->errors());
 *           $this->flash('old',    $this->all());
 *           return $this->redirectBack();
 *       }
 *
 *       // validated() contains title, slug, description, price, category_id, level
 *       // slug was generated in prepare() — not submitted by the user
 *       $course = $this->courseService->create(
 *           $this->authId(),
 *           $validator->validated()
 *       );
 *
 *       $this->flash('success', 'Course created. You can now add lessons.');
 *       return $this->redirect('/instructor/courses/' . $course->id . '/edit');
 *   }
 */
class CreateCourseValidator extends Validator
{
    protected function rules(): array
    {
        return [
            'title'       => 'required|string|min:5|max:255',
            // slug is generated in prepare() from title — validated here
            'slug'        => 'required|string|alpha_dash|max:255',
            'description' => 'required|string|min:20',
            'price'       => 'required|numeric|min_val:0',
            'category_id' => 'required|integer|min_val:1',
            'level'       => 'required|in:beginner,intermediate,advanced',
        ];
    }

    protected function messages(): array
    {
        return [
            'title.required'       => 'Your course needs a title.',
            'title.min'            => 'The title must be at least 5 characters.',
            'description.required' => 'Please write a course description.',
            'description.min'      => 'The description should be at least 20 characters.',
            'price.required'       => 'Please set a price for your course.',
            'price.numeric'        => 'Price must be a number (e.g. 29.99).',
            'price.min_val'        => 'Price cannot be negative.',
            'category_id.required' => 'Please choose a category.',
            'level.required'       => 'Please choose a difficulty level.',
            'level.in'             => 'Level must be beginner, intermediate, or advanced.',
        ];
    }

    /**
     * Auto-generate a URL slug from the title before validation runs.
     *
     * This way the instructor only fills in the title on the form and the
     * slug is produced and validated automatically.
     *
     * "Introduction to PHP 8" → "introduction-to-php-8"
     */
    protected function prepare(array $data): array
    {
        if (!empty($data['title'])) {
            $data['slug'] = $this->slugify($data['title']);
        }
        return $data;
    }

    private function slugify(string $title): string
    {
        $slug = mb_strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9\s\-]/', '', $slug);  // remove special chars
        $slug = preg_replace('/[\s\-]+/', '-', $slug);        // spaces/hyphens → single dash
        return trim($slug, '-');
    }
}