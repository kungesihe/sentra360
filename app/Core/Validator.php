<?php
declare(strict_types=1);

namespace App\Core;

/** Small server-side validator. Rules: required, email, max:N, min:N, mobile, slug, color, in:a|b. */
final class Validator
{
    public array $errors = [];
    public array $clean = [];

    public function __construct(private array $input)
    {
    }

    public static function make(array $input, array $rules): self
    {
        $v = new self($input);
        foreach ($rules as $field => $ruleStr) {
            $v->check($field, explode(',', $ruleStr));
        }
        return $v;
    }

    private function check(string $field, array $rules): void
    {
        $raw = $this->input[$field] ?? '';
        $value = is_string($raw) ? trim($raw) : '';
        $label = ucfirst(str_replace('_', ' ', $field));
        if ($value === '') {
            if (in_array('required', $rules, true)) {
                $this->errors[$field] = "$label is required.";
            }
            $this->clean[$field] = null;
            return;
        }
        foreach ($rules as $rule) {
            [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
            $err = match ($name) {
                'email'  => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : "$label must be a valid email address.",
                'max'    => mb_strlen($value) > (int) $arg ? "$label must be at most $arg characters." : null,
                'min'    => mb_strlen($value) < (int) $arg ? "$label must be at least $arg characters." : null,
                'mobile' => normalize_sa_mobile($value) ? null : "$label must be a valid South African mobile number.",
                'slug'   => preg_match('/^[a-z0-9][a-z0-9-]{1,38}[a-z0-9]$/', $value) ? null : "$label may only use lowercase letters, numbers and hyphens (3–40 characters).",
                'color'  => preg_match('/^#[0-9A-Fa-f]{6}$/', $value) ? null : "$label must be a colour like #0B3D91.",
                'in'     => in_array($value, explode('|', (string) $arg), true) ? null : "$label has an invalid value.",
                default  => null,
            };
            if ($err) {
                $this->errors[$field] = $err;
                break;
            }
        }
        if ($field === 'email' || in_array('email', $rules, true)) {
            $value = strtolower($value);
        }
        if (in_array('mobile', $rules, true)) {
            $value = normalize_sa_mobile($value) ?? $value;
        }
        $this->clean[$field] = $value;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field] = $message;
    }
}
