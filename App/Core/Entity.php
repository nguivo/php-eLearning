<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Entity — Base class for all domain entities.
 *
 * Then in a view:
 *   <?= htmlspecialchars($course->title) ?>
 *   <?php if ($course->isPublished()): ?> ... <?php endif; ?>
 */
abstract class Entity
{
    /** Most tables have these columns. Declare them here to avoid repetition. */
    public ?string $createdAt = null;
    public ?string $updatedAt = null;


    // -------------------------------------------------------------------------
    // Mass Assignment
    // -------------------------------------------------------------------------

    /**
     * Populate entity properties from an associative array.
     *
     * @param array<string, mixed> $data
     * @return static Returns $this for chaining: (new Course())->fill([...])
     */
    public function fill(array $data): static
    {
        foreach ($data as $key => $value) {
            // Accept both snake_case ('instructor_id') and camelCase ('instructorId')
            $camel = lcfirst(str_replace('_', '', ucwords($key, '_')));

            if (property_exists($this, $camel)) {
                $this->$camel = $value;
            } elseif (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

        return $this;
    }


    // -------------------------------------------------------------------------
    // Serialisation
    // -------------------------------------------------------------------------

    /**
     * Convert the entity to a plain associative array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }


    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Check whether the entity has been persisted to the database.
     * An entity with an ID has been saved; without one it is new/transient.
     *
     * Usage:
     *   if ($course->isPersisted()) { ... } // do an update, not an insert
     */
    public function isPersisted(): bool
    {
        return isset($this->id) && $this->id > 0;
    }
}