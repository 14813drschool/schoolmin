<?php

namespace Kotchasan\Http\Traits;

/**
 * Request Security Trait
 * Handles CSRF, sanitization, and security checks
 *
 * @package Kotchasan\Http\Traits
 */
trait RequestSecurityTrait
{
    /**
     * Sanitize a single value or array recursively
     *
     * @param mixed $value
     * @return mixed
     */
    protected function sanitizeValue($value)
    {
        if (is_array($value)) {
            return array_map([$this, 'sanitizeValue'], $value);
        }

        if (!is_string($value)) {
            return $value;
        }

        // Remove null bytes
        $value = str_replace("\0", '', $value);

        // Remove <script>...</script> and their content
        $value = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $value);

        // Remove all HTML tags
        $value = strip_tags($value);

        // Decode HTML entities to plain text
        $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');

        // Collapse multiple spaces into one
        $value = preg_replace('/\s+/u', ' ', $value);

        // Trim whitespace
        return trim($value);
    }

    /**
     * Generate CSRF token
     *
     * @return string
     */
    public function generateCsrfToken(string $sessionKey = 'csrf_token'): string
    {
        $token = bin2hex(random_bytes(32));

        $_SESSION[$sessionKey] = $token;
        $_SESSION[$sessionKey.'_meta'] = [
            'times' => 0,
            'expired' => time() + (defined('TOKEN_AGE') ? TOKEN_AGE : 3600),
            'created' => time()
        ];

        return $token;
    }

    /**
     * Validate CSRF token
     *
     * @param string|null $token Token to validate (if null, gets from request)
     * @param string $sessionKey Session key to check token against
     * @return bool
     */
    public function validateCsrfToken(?string $token = null, string $sessionKey = 'csrf_token'): bool
    {
        if ($token === null) {
            // request() may return an InputItem (fluent wrapper). Cast to string to get the actual value.
            $token = (string) $this->request('token', '');
        }

        if (empty($token) || !isset($_SESSION[$sessionKey])) {
            return false;
        }

        // Check token match
        if (!hash_equals($_SESSION[$sessionKey], $token)) {
            return false;
        }

        // Check token metadata
        $metaKey = $sessionKey.'_meta';
        if (!isset($_SESSION[$metaKey])) {
            return false;
        }

        $meta = $_SESSION[$metaKey];

        // Check expiration
        if ($meta['expired'] < time()) {
            $this->clearCsrfToken($sessionKey);
            return false;
        }

        // Check usage limit
        $limit = defined('TOKEN_LIMIT') ? TOKEN_LIMIT : 10;
        if ($meta['times'] >= $limit) {
            return false;
        }

        // Check referer
        if (!$this->isValidReferer()) {
            return false;
        }

        // Increment usage counter
        $_SESSION[$metaKey]['times']++;

        return true;
    }

    /**
     * Clear CSRF token
     *
     * @param string $sessionKey Session key to clear
     * @return void
     */
    public function clearCsrfToken(string $sessionKey = 'csrf_token'): void
    {
        unset($_SESSION[$sessionKey], $_SESSION[$sessionKey.'_meta']);
    }

    /**
     * Check if request has valid referer
     *
     * @return bool
     */
    public function isValidReferer(): bool
    {
        $referer = $this->server('HTTP_REFERER');

        if (empty($referer)) {
            return false;
        }

        $refererHost = parse_url($referer, PHP_URL_HOST);
        $currentHost = $this->server('HTTP_HOST', $this->server('SERVER_NAME'));

        return $refererHost === $currentHost;
    }

    /**
     * Check if request is safe (CSRF protected)
     *
     * @return bool
     */
    public function isSafe(): bool
    {
        return $this->validateCsrfToken();
    }

    /**
     * Get sanitized input data
     *
     * @param array|null $data Data to sanitize (if null, uses all input)
     * @return array
     */
    public function sanitize(?array $data = null): array
    {
        if ($data === null) {
            $data = $this->all(false);
        }

        $sanitized = [];
        foreach ($data as $key => $value) {
            $sanitized[$key] = $this->sanitizeValue($value);
        }

        return $sanitized;
    }

    /**
     * Validate input against rules
     *
     * @param array $rules Validation rules
     * @return array Validation errors
     */
    public function validate(array $rules): array
    {
        $errors = [];
        $data = $this->all();

        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            $fieldRules = is_string($rule) ? explode('|', $rule) : $rule;

            foreach ($fieldRules as $fieldRule) {
                $error = $this->validateField($field, $value, $fieldRule);
                if ($error) {
                    $errors[$field][] = $error;
                }
            }
        }

        return $errors;
    }

    /**
     * Validate single field
     *
     * @param string $field Field name
     * @param mixed $value Field value
     * @param string $rule Validation rule
     * @return string|null Error message or null if valid
     */
    protected function validateField(string $field, $value, string $rule): ?string
    {
        [$ruleName, $parameter] = array_pad(explode(':', $rule, 2), 2, null);

        // Convert null to empty string for consistent validation
        $stringValue = (string) $value;

        switch ($ruleName) {
            case 'required':
                return empty($value) ? "{$field} is required" : null;

            case 'email':
                return empty($value) ? null : (!filter_var($value, FILTER_VALIDATE_EMAIL) ? "{$field} must be a valid email" : null);

            case 'min':
                return empty($value) ? null : (strlen($stringValue) < (int) $parameter ? "{$field} must be at least {$parameter} characters" : null);

            case 'max':
                return empty($value) ? null : (strlen($stringValue) > (int) $parameter ? "{$field} must not exceed {$parameter} characters" : null);

            case 'numeric':
                return empty($value) ? null : (!is_numeric($value) ? "{$field} must be numeric" : null);

            case 'integer':
                return empty($value) ? null : (!filter_var($value, FILTER_VALIDATE_INT) ? "{$field} must be an integer" : null);

            case 'url':
                return empty($value) ? null : (!filter_var($value, FILTER_VALIDATE_URL) ? "{$field} must be a valid URL" : null);

            default:
                return null;
        }
    }
}
