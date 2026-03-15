<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Service — Base class for all application services.
 */
abstract class Service
{
    // -------------------------------------------------------------------------
    // Internal Assertion Helper
    // -------------------------------------------------------------------------

    /**
     * Assert that a condition is true, throwing a LogicException if not.
     *
     * Use this for INTERNAL programming contracts between services —
     * not for validating user input (use App/Validators/ for that).
     *
     * This catches bugs (a service being called with a negative ID) rather
     * than user mistakes (a form field being empty).
     *
     * Usage:
     *   $this->assert($userId > 0,   'userId must be a positive integer');
     *   $this->assert($courseId > 0, 'courseId must be a positive integer');
     *   $this->assert(!empty($email), 'email must not be empty');
     *
     * @throws \LogicException if the condition is false
     */
    protected function assert(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \LogicException(
                static::class . ': ' . $message
            );
        }
    }

    /**
     * Lightweight validate() bridge — kept for convenience when a service
     * needs a quick format check on internal data (not user-facing form data).
     *
     * For full user-facing validation with per-field errors, create a class
     * in App/Validators/ that extends the Validator base class.
     *
     * Rules are the same pipe-separated format: 'required|integer|min_val:1'
     * Throws \InvalidArgumentException on failure (no field-level error map).
     *
     * @param array<string, mixed>  $data
     * @param array<string, string> $rules
     * @throws \InvalidArgumentException
     */
    protected function validate(array $data, array $rules): void
    {
        $errors = [];

        foreach ($rules as $field => $ruleString) {
            $value = $data[$field] ?? null;

            foreach (explode('|', $ruleString) as $rule) {
                [$ruleName, $ruleParam] = array_pad(explode(':', $rule, 2), 2, null);
                $label = ucfirst(str_replace('_', ' ', $field));

                $error = match ($ruleName) {
                    'required'  => ($value === null || $value === '') ? "{$label} is required." : null,
                    'integer'   => !(is_int($value) || ctype_digit((string) $value)) ? "{$label} must be an integer." : null,
                    'numeric'   => !is_numeric($value) ? "{$label} must be numeric." : null,
                    'string'    => !is_string($value) ? "{$label} must be a string." : null,
                    'email'     => !filter_var($value, FILTER_VALIDATE_EMAIL) ? "{$label} must be a valid email." : null,
                    'min_val'   => (is_numeric($value) && (float)$value < (float)$ruleParam) ? "{$label} must be at least {$ruleParam}." : null,
                    'max_val'   => (is_numeric($value) && (float)$value > (float)$ruleParam) ? "{$label} must not exceed {$ruleParam}." : null,
                    'min'       => (is_string($value) && mb_strlen($value) < (int)$ruleParam) ? "{$label} must be at least {$ruleParam} characters." : null,
                    'max'       => (is_string($value) && mb_strlen($value) > (int)$ruleParam) ? "{$label} must not exceed {$ruleParam} characters." : null,
                    default     => null,
                };

                if ($error !== null) {
                    $errors[] = $error;
                    break;
                }
            }
        }

        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode(' | ', $errors));
        }
    }


    // -------------------------------------------------------------------------
    // Logging
    // -------------------------------------------------------------------------

    /**
     * Write a message to the application log.
     * Log files are stored in BASE_PATH/logs/ and named by level (info.log, warning.log, error.log).
     * @param 'info'|'warning'|'error' $level
     * @param string                   $message
     */
    protected function log(string $level, string $message): void
    {
        $logFile   = BASE_PATH . '/logs/' . $level . '.log';
        $class     = static::class;
        $timestamp = date('Y-m-d H:i:s');
        $line      = "[{$timestamp}] [{$level}] [{$class}] {$message}" . PHP_EOL;

        error_log($line, 3, $logFile);
    }
}