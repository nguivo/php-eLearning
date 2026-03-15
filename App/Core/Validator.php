<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Validator — Base class for all form/input validators.
 */
abstract class Validator
{
    // -------------------------------------------------------------------------
    // Properties
    // -------------------------------------------------------------------------

    private array $data = [];
    private array $errors = [];
    private array $validated = [];


    abstract protected function rules(): array;


    protected function messages(): array
    {
        return [];
    }

    /**
     * Transform or normalise data BEFORE the rules are applied.
     *
     * Use this to cast types, generate derived fields, or trim values
     * that the rules will then check. Runs inside validate() automatically.
     *
     * @param array<string, mixed> $data Raw input
     * @return array<string, mixed> Transformed input
     */
    protected function prepare(array $data): array
    {
        return $data;
    }


    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Run validation against the given data.
     *
     * Returns true if ALL rules pass, false if ANY rule fails.
     *
     * @param array<string, mixed> $data Raw input (typically from $this->all())
     */
    public function validate(array $data): bool
    {
        // Reset state so this instance can be reused across calls
        $this->errors    = [];
        $this->validated = [];

        // Run any pre-processing (casting, slug generation, etc.)
        $this->data = $this->prepare($data);

        foreach ($this->rules() as $field => $ruleString) {
            $value    = $this->data[$field] ?? null;
            $rules    = explode('|', $ruleString);
            $nullable = in_array('nullable', $rules, true);

            // If nullable and the value is empty, skip all further checks
            // and include the field in validated data as-is (null/empty)
            if ($nullable && ($value === null || $value === '')) {
                $this->validated[$field] = $value;
                continue;
            }

            $fieldPassed = true;

            foreach ($rules as $rule) {
                if ($rule === 'nullable') {
                    continue;
                }

                $error = $this->applyRule($field, $value, $rule);

                if ($error !== null) {
                    $this->errors[$field][] = $error;
                    $fieldPassed = false;
                    // Stop checking further rules for this field once one fails.
                    // "required|min:3" should not report "min:3 failed" if the
                    // value is missing — only the first relevant error matters.
                    break;
                }
            }

            // Only include in validated data if every rule passed
            if ($fieldPassed) {
                $this->validated[$field] = $value;
            }
        }

        return empty($this->errors);
    }


    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Return the first error message for a specific field, or null.
     */
    public function error(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * Return true if a specific field has at least one error.
     *
     * Usage in a view:
     *   <input class="<?= $validator->hasError('title') ? 'is-invalid' : '' ?>">
     */
    public function hasError(string $field): bool
    {
        return !empty($this->errors[$field]);
    }


    /**
     * Return true if validation passed (no errors).
     */
    public function passes(): bool
    {
        return empty($this->errors);
    }


    /**
     * Return true if validation failed (has errors).
     */
    public function fails(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Return the clean, validated data — ONLY the fields declared in rules().
     *
     * This is what you pass to the Service. It is guaranteed to contain only
     * declared fields that passed their rules. No extra POST fields, no
     * un-validated data, no surprises.
     */
    public function validated(): array
    {
        return $this->validated;
    }


    // -------------------------------------------------------------------------
    // Rule Engine
    // -------------------------------------------------------------------------

    /**
     * Apply a single rule to a field value.
     * Returns a human-readable error string, or null if the rule passes.
     *
     * Checks for a custom message in messages() first, then falls back
     * to the default message generated here.
     */
    private function applyRule(string $field, mixed $value, string $rule): ?string
    {
        // Rules with parameters: 'min:8', 'max:255', 'in:draft,published'
        [$ruleName, $ruleParam] = array_pad(explode(':', $rule, 2), 2, null);

        $error = $this->checkRule($field, $value, $ruleName, $ruleParam);

        if ($error === null) {
            return null;
        }

        // Check for a custom message override: 'field.rule'
        $customKey = "{$field}.{$ruleName}";
        return $this->messages()[$customKey] ?? $error;
    }

    /**
     * Core rule-checking logic.
     * Returns a default error string, or null if the rule passes.
     */
    private function checkRule(string $field, mixed $value, string $rule, ?string $param): ?string
    {
        $label = ucfirst(str_replace('_', ' ', $field));

        return match ($rule) {

            // ── Presence ──────────────────────────────────────────────────────

            'required' => (
                $value === null || $value === '' ||
                (is_array($value) && empty($value))
            )
                ? "{$label} is required."
                : null,

            // ── Type checks ───────────────────────────────────────────────────

            'string' => !is_string($value)
                ? "{$label} must be a string."
                : null,

            'integer' => !(is_int($value) || ctype_digit((string) $value))
                ? "{$label} must be a whole number."
                : null,

            'numeric' => !is_numeric($value)
                ? "{$label} must be a number."
                : null,

            'boolean' => !in_array($value, [true, false, 0, 1, '0', '1'], true)
                ? "{$label} must be true or false."
                : null,

            // ── Format checks ─────────────────────────────────────────────────

            'email' => !filter_var($value, FILTER_VALIDATE_EMAIL)
                ? "{$label} must be a valid email address."
                : null,

            'url' => !filter_var($value, FILTER_VALIDATE_URL)
                ? "{$label} must be a valid URL."
                : null,

            'alpha' => !ctype_alpha((string) $value)
                ? "{$label} may only contain letters."
                : null,

            'alpha_num' => !ctype_alnum((string) $value)
                ? "{$label} may only contain letters and numbers."
                : null,

            'alpha_dash' => !preg_match('/^[a-zA-Z0-9_\-]+$/', (string) $value)
                ? "{$label} may only contain letters, numbers, hyphens, and underscores."
                : null,

            'date' => (strtotime((string) $value) === false)
                ? "{$label} must be a valid date."
                : null,

            // ── String length ─────────────────────────────────────────────────

            'min' => (is_string($value) && mb_strlen($value) < (int) $param)
                ? "{$label} must be at least {$param} characters."
                : null,

            'max' => (is_string($value) && mb_strlen($value) > (int) $param)
                ? "{$label} must not exceed {$param} characters."
                : null,

            // ── Numeric range ─────────────────────────────────────────────────

            'min_val' => (is_numeric($value) && (float) $value < (float) $param)
                ? "{$label} must be at least {$param}."
                : null,

            'max_val' => (is_numeric($value) && (float) $value > (float) $param)
                ? "{$label} must not exceed {$param}."
                : null,

            // ── Set membership ────────────────────────────────────────────────

            'in' => (!in_array($value, explode(',', (string) $param), true))
                ? "{$label} must be one of: " . str_replace(',', ', ', (string) $param) . "."
                : null,

            'not_in' => (in_array($value, explode(',', (string) $param), true))
                ? "{$label} must not be one of: " . str_replace(',', ', ', (string) $param) . "."
                : null,

            // ── Pattern ───────────────────────────────────────────────────────

            'regex' => (!preg_match((string) $param, (string) $value))
                ? "{$label} format is invalid."
                : null,

            // ── Cross-field ───────────────────────────────────────────────────

            // 'confirmed' checks that {field}_confirmation matches
            'confirmed' => ($value !== ($this->data["{$field}_confirmation"] ?? null))
                ? "{$label} confirmation does not match."
                : null,

            // 'same:other' checks that this field matches another
            'same' => ($value !== ($this->data[$param] ?? null))
                ? "{$label} must match " . ucfirst(str_replace('_', ' ', (string) $param)) . "."
                : null,

            // 'different:other' checks that this field does NOT match another
            'different' => ($value === ($this->data[$param] ?? null))
                ? "{$label} must be different from " . ucfirst(str_replace('_', ' ', (string) $param)) . "."
                : null,

            default => null, // Unknown rules pass silently — add custom rules via override
        };
    }
}