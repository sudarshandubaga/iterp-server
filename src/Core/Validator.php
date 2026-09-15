<?php

declare(strict_types=1);

namespace Iterp\Core;

/**
 * Minimal validation engine.
 *
 * Supported rules (pipe-separated, colon for args):
 *  - required            - nullable
 *  - string|numeric|integer|boolean|array
 *  - email               - url
 *  - min:8 / max:255 / between:4,64
 *  - confirmed            (expects field_confirmation)
 *  - same:other
 *  - in:a,b,c
 *  - unique:table,column
 *  - exists:table,column
 */
class Validator
{
    private array $errors = [];

    public static function make(array $data, array $rules): self
    {
        $instance = new self();

        foreach ($rules as $field => $ruleSet) {
            $ruleSet = is_string($ruleSet) ? explode('|', $ruleSet) : $ruleSet;
            $value   = $data[$field] ?? null;

            [$failed, $message] = $instance->checkField($field, $value, $ruleSet, $data);
            if ($failed) {
                $instance->errors[$field][] = str_replace(':field', ucfirst($field), $message);
            }
        }

        return $instance;
    }

    public function fails(): bool
    {
        return count($this->errors) > 0;
    }

    public function errors(): array
    {
        return $this->errors;
    }
private function checkField(string $field, $value, array $rules, array $data): array
    {
        $nullable = in_array('nullable', $rules, true);

        foreach ($rules as $rule) {
            if ($rule === 'nullable') {
                $nullable = true;
                continue;
            }

            if ($value === null || $value === '') {
                if ($nullable) {
                    return [false, ''];
                }
                if ($rule === 'required') {
                    return [true, ':field is required.'];
                }
                continue; // value absent; skip non-required checks
            }

            $parts = explode(':', $rule, 2);
            $name  = $parts[0];
            $param = $parts[1] ?? null;

            switch ($name) {
                case 'required':
                    break;

                case 'email':
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        return [true, ':field must be a valid email address.'];
                    }
                    break;

                case 'url':
                    if (filter_var($value, FILTER_VALIDATE_URL) === false) {
                        return [true, ':field must be a valid URL.'];
                    }
                    break;

                case 'string':
                    if (!is_string($value)) {
                        return [true, ':field must be a string.'];
                    }
                    break;

                case 'numeric':
                    if (!is_numeric($value)) {
                        return [true, ':field must be a number.'];
                    }
                    break;

                case 'integer':
                    if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                        return [true, ':field must be an integer.'];
                    }
                    break;

                case 'boolean':
                    if (!in_array($value, [true, false, 0, 1, '0', '1'], true)) {
                        return [true, ':field must be a boolean.'];
                    }
                    break;

                case 'array':
                    if (!is_array($value)) {
                        return [true, ':field must be an array.'];
                    }
                    break;

                case 'min':
                    if ((int) $this->measure($value) < (int) $param) {
                        return [true, ":field must be at least {$param}."];
                    }
                    break;

                case 'max':
                    if ((int) $this->measure($value) > (int) $param) {
                        return [true, ":field may not be greater than {$param}."];
                    }
                    break;

                case 'between':
                    [$lo, $hi] = array_map('intval', explode(',', (string) $param));
                    $len = (int) $this->measure($value);
                    if ($len < $lo || $len > $hi) {
                        return [true, ":field must be between {$lo} and {$hi}."];
                    }
                    break;

                case 'confirmed':
                    if (!array_key_exists($field . '_confirmation', $data)
                        || $data[$field . '_confirmation'] !== $value) {
                        return [true, ':field confirmation does not match.'];
                    }
                    break;

                case 'same':
                    if (($data[$param] ?? null) !== $value) {
                        return [true, ":field must match {$param}."];
                    }
                    break;

                case 'in':
                    $allowed = explode(',', (string) $param);
                    if (!in_array($value, $allowed, true)) {
                        return [true, ':field must be one of: ' . implode(', ', $allowed) . '.'];
                    }
                    break;

                case 'unique':
                    if (!self::isUnique($param, $field, $value)) {
                        return [true, ':field already exists.'];
                    }
                    break;

                case 'exists':
                    if (!self::recordExists($param, $field, $value)) {
                        return [true, ':field does not exist.'];
                    }
                    break;
            }
        }

        return [false, ''];
    }

    private function measure($value): int
    {
        return is_string($value)
            ? mb_strlen($value)
            : (is_numeric($value) ? (int) $value : 0);
    }

    /**
     * @param string $table eg. "users,email" or match against given column.
     */
    private static function isUnique(string $tableSpec, string $field, $value): bool
    {
        [$tableName, $column] = array_pad(explode(',', $tableSpec), 2, null);
        $column = $column ?: $field;

        $stmt = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM `{$tableName}` WHERE `{$column}` = :value"
        );
        $stmt->execute(['value' => $value]);
        return (int) $stmt->fetchColumn() === 0;
    }

    private static function recordExists(string $tableSpec, string $field, $value): bool
    {
        [$tableName, $column] = array_pad(explode(',', $tableSpec), 2, null);
        $column = $column ?: $field;

        $stmt = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM `{$tableName}` WHERE `{$column}` = :value"
        );
        $stmt->execute(['value' => $value]);
        return (int) $stmt->fetchColumn() > 0;
    }
}