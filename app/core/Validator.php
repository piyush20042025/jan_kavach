<?php
namespace JanKavach\Core;

/**
 * Server-side validation.  Every write path runs through this, regardless
 * of any client-side checks.
 *
 *   $v = Validator::make($request->all(), [
 *       'name'     => 'required|string|max:150',
 *       'email'    => 'required|email|unique:users,email',
 *       'age'      => 'nullable|integer|between:0,120',
 *       'state_id' => 'required|exists:states,id',
 *   ], ['name' => 'Full Name']);
 *
 *   if ($v->fails()) { ... $v->errors() ... }
 */
class Validator
{
    private array $data;
    private array $rules;
    private array $labels;
    private array $errors = [];
    private array $validated = [];

    public function __construct(array $data, array $rules, array $labels = [])
    {
        $this->data   = $data;
        $this->rules  = $rules;
        $this->labels = $labels;
    }

    public static function make(array $data, array $rules, array $labels = []): self
    {
        $validator = new self($data, $rules, $labels);
        $validator->validate();
        return $validator;
    }

    public function validate(): bool
    {
        $this->errors = [];
        $this->validated = [];

        foreach ($this->rules as $field => $ruleString) {
            $rules = is_array($ruleString) ? $ruleString : explode('|', $ruleString);
            $value = $this->value($field);

            $nullable = in_array('nullable', $rules, true);
            $required = in_array('required', $rules, true);

            // Nothing supplied.
            if ($this->isBlank($value)) {
                if ($required) {
                    $this->addError($field, 'required', $this->label($field) . ' is required.');
                } elseif ($nullable || !$required) {
                    $this->validated[$field] = null;
                }
                continue;
            }

            foreach ($rules as $rule) {
                if (in_array($rule, ['required', 'nullable'], true)) {
                    continue;
                }
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
                if (!$this->applyRule($field, $value, $name, $param)) {
                    break;  // stop at the first failure for this field
                }
            }

            if (!isset($this->errors[$field])) {
                $this->validated[$field] = $value;
            }
        }
        return empty($this->errors);
    }

    private function applyRule(string $field, $value, string $rule, ?string $param): bool
    {
        $label = $this->label($field);

        switch ($rule) {
            case 'string':
                if (!is_string($value)) {
                    return $this->addError($field, $rule, "{$label} must be text.");
                }
                return true;

            case 'integer':
            case 'int':
                if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                    return $this->addError($field, $rule, "{$label} must be a whole number.");
                }
                $this->data[$field] = (int)$value;
                return true;

            case 'numeric':
                if (!is_numeric($value)) {
                    return $this->addError($field, $rule, "{$label} must be a number.");
                }
                return true;

            case 'decimal':
                if (!is_numeric($value)) {
                    return $this->addError($field, $rule, "{$label} must be a number.");
                }
                return true;

            case 'boolean':
                if (!in_array((string)$value, ['0', '1', 'true', 'false', 'yes', 'no', 'on'], true)) {
                    return $this->addError($field, $rule, "{$label} must be yes or no.");
                }
                return true;

            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return $this->addError($field, $rule, "{$label} must be a valid email address.");
                }
                return true;

            case 'url':
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    return $this->addError($field, $rule, "{$label} must be a valid URL.");
                }
                return true;

            case 'mobile':
                // Indian mobile: 10 digits starting 6-9, optional +91 / 0 prefix.
                $digits = preg_replace('/\D/', '', (string)$value);
                if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
                    $digits = substr($digits, 2);
                } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
                    $digits = substr($digits, 1);
                }
                if (!preg_match('/^[6-9]\d{9}$/', $digits)) {
                    return $this->addError($field, $rule, "{$label} must be a valid 10-digit Indian mobile number.");
                }
                $this->data[$field] = $digits;
                return true;

            case 'aadhaar':
                $digits = preg_replace('/\D/', '', (string)$value);
                if (!preg_match('/^\d{12}$/', $digits)) {
                    return $this->addError($field, $rule, "{$label} must be 12 digits.");
                }
                if (!self::verhoeffValid($digits)) {
                    return $this->addError($field, $rule, "{$label} failed the checksum test. Please re-enter it.");
                }
                $this->data[$field] = $digits;
                return true;

            case 'pincode':
                if (!preg_match('/^[1-9]\d{5}$/', (string)$value)) {
                    return $this->addError($field, $rule, "{$label} must be a valid 6-digit PIN code.");
                }
                return true;

            case 'alpha':
                if (!preg_match('/^[\p{L}\s]+$/u', (string)$value)) {
                    return $this->addError($field, $rule, "{$label} may contain letters and spaces only.");
                }
                return true;

            case 'alpha_num':
                if (!preg_match('/^[\p{L}\p{N}]+$/u', (string)$value)) {
                    return $this->addError($field, $rule, "{$label} may contain letters and numbers only.");
                }
                return true;

            case 'alpha_dash':
                if (!preg_match('/^[\p{L}\p{N}_\-\.]+$/u', (string)$value)) {
                    return $this->addError($field, $rule, "{$label} may contain letters, numbers, dashes, dots and underscores only.");
                }
                return true;

            case 'username':
                if (!preg_match('/^[a-zA-Z0-9._\-]{3,60}$/', (string)$value)) {
                    return $this->addError($field, $rule, "{$label} must be 3-60 characters using letters, numbers, dots, dashes or underscores.");
                }
                return true;

            case 'min':
                if (is_numeric($value) && !is_string($value)) {
                    if ((float)$value < (float)$param) {
                        return $this->addError($field, $rule, "{$label} must be at least {$param}.");
                    }
                } elseif (mb_strlen((string)$value) < (int)$param) {
                    return $this->addError($field, $rule, "{$label} must be at least {$param} characters.");
                }
                return true;

            case 'max':
                if (is_numeric($value) && !is_string($value)) {
                    if ((float)$value > (float)$param) {
                        return $this->addError($field, $rule, "{$label} may not be greater than {$param}.");
                    }
                } elseif (mb_strlen((string)$value) > (int)$param) {
                    return $this->addError($field, $rule, "{$label} may not exceed {$param} characters.");
                }
                return true;

            case 'between':
                [$lo, $hi] = array_pad(explode(',', (string)$param), 2, null);
                $numeric = (float)$value;
                if ($numeric < (float)$lo || $numeric > (float)$hi) {
                    return $this->addError($field, $rule, "{$label} must be between {$lo} and {$hi}.");
                }
                return true;

            case 'size':
                if (mb_strlen((string)$value) !== (int)$param) {
                    return $this->addError($field, $rule, "{$label} must be exactly {$param} characters.");
                }
                return true;

            case 'in':
                $allowed = explode(',', (string)$param);
                if (!in_array((string)$value, $allowed, true)) {
                    return $this->addError($field, $rule, "{$label} holds an unexpected value.");
                }
                return true;

            case 'not_in':
                $blocked = explode(',', (string)$param);
                if (in_array((string)$value, $blocked, true)) {
                    return $this->addError($field, $rule, "{$label} holds a value that is not allowed.");
                }
                return true;

            case 'date':
                if (!$this->isValidDate((string)$value)) {
                    return $this->addError($field, $rule, "{$label} must be a valid date.");
                }
                return true;

            case 'date_before_today':
                if (!$this->isValidDate((string)$value) || strtotime((string)$value) > strtotime('today 23:59:59')) {
                    return $this->addError($field, $rule, "{$label} cannot be a future date.");
                }
                return true;

            case 'after':
                // after:other_field  or  after:2020-01-01
                $other = $this->value($param);
                $compare = $other !== null ? (string)$other : (string)$param;
                if (strtotime((string)$value) < strtotime($compare)) {
                    $otherLabel = $this->value($param) !== null ? $this->label((string)$param) : $param;
                    return $this->addError($field, $rule, "{$label} must be on or after {$otherLabel}.");
                }
                return true;

            case 'confirmed':
                if ((string)$value !== (string)$this->value($field . '_confirmation')) {
                    return $this->addError($field, $rule, "{$label} confirmation does not match.");
                }
                return true;

            case 'password':
                return $this->checkPasswordStrength($field, (string)$value, $label);

            case 'unique':
                return $this->checkUnique($field, $value, (string)$param, $label);

            case 'exists':
                return $this->checkExists($field, $value, (string)$param, $label);

            case 'regex':
                if (!preg_match($param, (string)$value)) {
                    return $this->addError($field, $rule, "{$label} is not in the expected format.");
                }
                return true;

            case 'array':
                if (!is_array($value)) {
                    return $this->addError($field, $rule, "{$label} must be a list.");
                }
                return true;

            case 'json':
                json_decode((string)$value);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return $this->addError($field, $rule, "{$label} must be valid JSON.");
                }
                return true;

            case 'no_html':
                if ((string)$value !== strip_tags((string)$value)) {
                    return $this->addError($field, $rule, "{$label} may not contain HTML tags.");
                }
                return true;

            default:
                // Unknown rules are ignored rather than silently passing bad
                // data - log so the typo gets noticed in development.
                Logger::warning("Unknown validation rule [{$rule}] on field [{$field}].");
                return true;
        }
    }

    private function checkPasswordStrength(string $field, string $value, string $label): bool
    {
        $min = (int)Config::get('security.password_min_length', 8);
        if (strlen($value) < $min) {
            return $this->addError($field, 'password', "{$label} must be at least {$min} characters.");
        }
        if (!preg_match('/[A-Za-z]/', $value) || !preg_match('/\d/', $value)) {
            return $this->addError($field, 'password', "{$label} must contain both letters and numbers.");
        }
        $weak = ['password', 'password1', '12345678', 'admin123', 'qwerty123', 'jankavach'];
        if (in_array(strtolower($value), $weak, true)) {
            return $this->addError($field, 'password', "{$label} is too common. Please choose something less guessable.");
        }
        return true;
    }

    /**
     * unique:table,column[,ignoreId[,idColumn]]
     * Table and column names are whitelisted by pattern before use.
     */
    private function checkUnique(string $field, $value, string $param, string $label): bool
    {
        $parts    = explode(',', $param);
        $table    = $this->safeIdentifier($parts[0] ?? '');
        $column   = $this->safeIdentifier($parts[1] ?? $field);
        $ignoreId = $parts[2] ?? null;
        $idColumn = $this->safeIdentifier($parts[3] ?? 'id');

        $sql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = :value";
        $bindings = ['value' => $value];

        // Soft-deleted rows should not block a new record.
        if ($this->columnExists($table, 'deleted_at')) {
            $sql .= ' AND deleted_at IS NULL';
        }
        if ($ignoreId !== null && $ignoreId !== '' && $ignoreId !== '0') {
            $sql .= " AND `{$idColumn}` <> :ignore";
            $bindings['ignore'] = $ignoreId;
        }

        if ((int)Database::getInstance()->scalar($sql, $bindings) > 0) {
            return $this->addError($field, 'unique', "{$label} is already in use.");
        }
        return true;
    }

    /** exists:table,column */
    private function checkExists(string $field, $value, string $param, string $label): bool
    {
        $parts  = explode(',', $param);
        $table  = $this->safeIdentifier($parts[0] ?? '');
        $column = $this->safeIdentifier($parts[1] ?? 'id');

        $sql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = :value";
        if ($this->columnExists($table, 'deleted_at')) {
            $sql .= ' AND deleted_at IS NULL';
        }

        if ((int)Database::getInstance()->scalar($sql, ['value' => $value]) === 0) {
            return $this->addError($field, 'exists', "The selected {$label} is not valid.");
        }
        return true;
    }

    private function columnExists(string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        $count = (int)Database::getInstance()->scalar(
            'SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c',
            ['t' => $table, 'c' => $column]
        );
        return $cache[$key] = ($count > 0);
    }

    private function safeIdentifier(string $name): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            throw new \InvalidArgumentException("Unsafe identifier [{$name}] in a validation rule.");
        }
        return $name;
    }

    private function isValidDate(string $value): bool
    {
        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'Y-m-d H:i:s'] as $format) {
            $dt = \DateTime::createFromFormat($format, $value);
            if ($dt && $dt->format($format) === $value) {
                return true;
            }
        }
        return strtotime($value) !== false;
    }

    private function value(?string $field)
    {
        if ($field === null) {
            return null;
        }
        return $this->data[$field] ?? null;
    }

    private function isBlank($value): bool
    {
        return $value === null || $value === '' || (is_array($value) && count($value) === 0);
    }

    private function label(string $field): string
    {
        if (isset($this->labels[$field])) {
            return $this->labels[$field];
        }
        return ucwords(str_replace(['_id', '_'], ['', ' '], $field));
    }

    private function addError(string $field, string $rule, string $message): bool
    {
        $this->errors[$field][] = $message;
        return false;
    }

    // ------------------------------------------------------------------
    // Results
    // ------------------------------------------------------------------

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    /** Flatten to one message per field, for compact display. */
    public function firstErrors(): array
    {
        $out = [];
        foreach ($this->errors as $field => $messages) {
            $out[$field] = $messages[0];
        }
        return $out;
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $messages) {
            return $messages[0];
        }
        return null;
    }

    /** Only fields that had rules and passed them. */
    public function validated(): array
    {
        return $this->validated;
    }

    /**
     * Verhoeff checksum, the algorithm UIDAI uses for Aadhaar numbers.
     * Catches most transcription errors from the field.
     */
    public static function verhoeffValid(string $number): bool
    {
        static $d = [
            [0,1,2,3,4,5,6,7,8,9],
            [1,2,3,4,0,6,7,8,9,5],
            [2,3,4,0,1,7,8,9,5,6],
            [3,4,0,1,2,8,9,5,6,7],
            [4,0,1,2,3,9,5,6,7,8],
            [5,9,8,7,6,0,4,3,2,1],
            [6,5,9,8,7,1,0,4,3,2],
            [7,6,5,9,8,2,1,0,4,3],
            [8,7,6,5,9,3,2,1,0,4],
            [9,8,7,6,5,4,3,2,1,0],
        ];
        static $p = [
            [0,1,2,3,4,5,6,7,8,9],
            [1,5,7,6,2,8,3,0,9,4],
            [5,8,0,3,7,9,6,1,4,2],
            [8,9,1,6,0,4,3,5,2,7],
            [9,4,5,3,1,2,6,8,7,0],
            [4,2,8,6,5,7,3,9,0,1],
            [2,7,9,3,8,0,6,4,1,5],
            [7,0,4,6,9,1,3,2,5,8],
        ];
        static $inv = [0,4,3,2,1,5,6,7,8,9];

        $c = 0;
        $digits = array_reverse(array_map('intval', str_split($number)));
        foreach ($digits as $i => $digit) {
            $c = $d[$c][$p[$i % 8][$digit]];
        }
        return $c === 0;
    }
}
