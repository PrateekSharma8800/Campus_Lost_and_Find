<?php
/**
 * Server-side input validation. The browser's HTML5 checks are only for
 * convenience; this is the check that counts.
 *
 * Usage:
 *   $v = Validator::make($data, [
 *       'email'    => ['required', 'email', 'max:190'],
 *       'phone'    => ['nullable', 'regex:/^[0-9+\-\s()]{7,20}$/'],
 *       'password' => ['required', 'password', 'same:password_confirmation'],
 *   ]);
 *   if ($v->fails()) { $errors = $v->errors(); }
 *
 * Rules: required, nullable, email, min:n, max:n (characters),
 *        max_bytes:n, same:field, password, regex:pattern
 * Only the first error per field is reported.
 */

declare(strict_types=1);

final class Validator
{
    private array $errors = [];

    private function __construct(private array $data, array $rules, private array $labels)
    {
        foreach ($rules as $field => $fieldRules) {
            $this->validateField($field, $fieldRules);
        }
    }

    public static function make(array $data, array $rules, array $labels = []): self
    {
        return new self($data, $rules, $labels);
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string, string> field => message */
    public function errors(): array
    {
        return $this->errors;
    }

    private function label(string $field): string
    {
        return $this->labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }

    private function validateField(string $field, array $rules): void
    {
        $raw = $this->data[$field] ?? null;

        // Arrays where a string is expected (e.g. email[]=x) are rejected outright.
        if (is_array($raw)) {
            $this->errors[$field] = $this->label($field) . ' is invalid.';
            return;
        }
        $value = $raw === null ? '' : (string) $raw;

        if (!mb_check_encoding($value, 'UTF-8')) {
            $this->errors[$field] = $this->label($field) . ' contains invalid characters.';
            return;
        }

        if ($value === '') {
            if (in_array('required', $rules, true)) {
                $this->errors[$field] = $this->label($field) . ' is required.';
            }
            return; // empty optional field: skip remaining rules
        }

        foreach ($rules as $rule) {
            [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
            $error = $this->check($name, $arg, $value, $field);
            if ($error !== null) {
                $this->errors[$field] = $error;
                return;
            }
        }
    }

    private function check(string $rule, ?string $arg, string $value, string $field): ?string
    {
        $label = $this->label($field);

        return match ($rule) {
            'required', 'nullable' => null,
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false
                ? null : "$label must be a valid email address.",
            'min' => mb_strlen($value) >= (int) $arg
                ? null : "$label must be at least $arg characters.",
            'max' => mb_strlen($value) <= (int) $arg
                ? null : "$label must not exceed $arg characters.",
            'max_bytes' => strlen($value) <= (int) $arg
                ? null : "$label is too long.",
            'same' => hash_equals((string) ($this->data[$arg] ?? ''), $value)
                ? null : "$label does not match.",
            'password' => (preg_match('/\pL/u', $value) && preg_match('/\pN/u', $value))
                ? null : "$label must contain at least one letter and one number.",
            'regex' => preg_match((string) $arg, $value) === 1
                ? null : "$label format is invalid.",
            // in:lost,found -> value must be one of the listed options
            'in' => in_array($value, explode(',', (string) $arg), true)
                ? null : "Please choose a valid $label.",
            'integer' => ctype_digit($value)
                ? null : "Please choose a valid $label.",
            'past_date' => self::isRecentPastDate($value)
                ? null : "$label must be a valid date within the last year (not in the future).",
            default => throw new InvalidArgumentException("Unknown validation rule: $rule"),
        };
    }

    /** A real YYYY-MM-DD date, not in the future and not older than one year. */
    private static function isRecentPastDate(string $value): bool
    {
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            return false;   // wrong format or impossible date like 2026-02-30
        }
        // Server time is UTC; allowing "tomorrow" covers users ahead of UTC (e.g. India).
        $latest   = gmdate('Y-m-d', strtotime('+1 day'));
        $earliest = gmdate('Y-m-d', strtotime('-1 year'));
        return $value >= $earliest && $value <= $latest;
    }
}
