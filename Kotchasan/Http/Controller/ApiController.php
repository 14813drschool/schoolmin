<?php

namespace Kotchasan\Http\Controller;

use Kotchasan\Database;
use Kotchasan\Http\Request;
use Kotchasan\Http\Response;

/**
 * Class ApiController
 *
 * Base controller for API endpoints with core API functionalities.
 *
 * @package Kotchasan\Http\Controller
 */
abstract class ApiController extends \Kotchasan\KBase
{
    /**
     * Database instance.
     *
     * @var Database|null
     */
    protected ?Database $db = null;

    /**
     * HTTP request instance.
     *
     * @var Request|null
     */
    protected ?Request $request = null;

    /**
     * HTTP response instance.
     *
     * @var Response|null
     */
    protected ?Response $response = null;

    /**
     * API configuration.
     *
     * @var array
     */
    protected array $config = [];

    /**
     * Constructor.
     *
     * @param string|null $connection Database connection name
     * @param Request|null $request HTTP request instance
     * @param Response|null $response HTTP response instance
     * @param array $config API configuration
     */
    public function __construct(?string $connection = null, ?Request $request = null, ?Response $response = null, array $config = [])
    {
        $this->db = $connection ? Database::create($connection) : Database::create();
        $this->request = $request ?? Request::createFromGlobals();
        $this->response = $response ?? new Response();
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * Get default configuration.
     *
     * @return array
     */
    protected function getDefaultConfig(): array
    {
        return [
            'cors' => [
                'enabled' => true,
                'origin' => '*',
                'credentials' => 'true',
                'headers' => 'Content-Type, Authorization, X-Requested-With, X-Access-Token',
                'max_age' => '86400'
            ],
            'jwt' => [
                'secret' => '',
                'ttl' => 900,
                'algorithm' => 'HS256',
                'cookie' => true
            ],
            'rate_limits' => [
                'default' => ['requests' => 100, 'window' => 3600],
                'auth' => ['requests' => 10, 'window' => 300],
                'heavy' => ['requests' => 10, 'window' => 3600]
            ],
            'debug' => false,
            'api_tokens' => [],
            'api_secret' => '',
            'api_ips' => ['0.0.0.0']
        ];
    }

    /**
     * Create a successful JSON response.
     *
     * @param mixed $data Response data
     * @param int $status HTTP status code
     * @return Response
     */
    protected function success($data = null, int $status = 200): Response
    {
        return Response::makeJson([
            'success' => true,
            'data' => $data
        ], $status);
    }

    /**
     * Create an error JSON response.
     *
     * @param string $message Error message
     * @param int $status HTTP status code
     * @param array $errors Detailed errors
     * @return Response
     */
    protected function error(string $message, int $status = 400, array $errors = []): Response
    {
        $response = [
            'success' => false,
            'message' => $message
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return Response::makeJson($response, $status);
    }

    /**
     * Validate request data against rules.
     *
     * @param Request $request HTTP request
     * @param array $rules Validation rules
     * @return array [isValid, errors]
     */
    protected function validate(Request $request, array $rules): array
    {
        $errors = [];
        $parameters = $this->getRequestData($request);

        foreach ($rules as $field => $fieldRules) {
            $value = $parameters[$field] ?? null;

            // Split rules by |
            $rulesList = is_array($fieldRules) ? $fieldRules : explode('|', $fieldRules);

            foreach ($rulesList as $rule) {
                // Check if rule has parameters
                if (strpos($rule, ':') !== false) {
                    list($ruleName, $ruleParam) = explode(':', $rule, 2);
                } else {
                    $ruleName = $rule;
                    $ruleParam = null;
                }

                // Apply validation rule
                $result = $this->applyValidationRule($ruleName, $field, $value, $ruleParam, $parameters);

                if ($result !== true) {
                    $errors[$field][] = $result;
                    break; // Stop validation for this field after first error
                }
            }
        }

        return [empty($errors), $errors];
    }

    /**
     * Get request data based on HTTP method.
     *
     * @param Request $request HTTP request
     * @return array
     */
    protected function getRequestData(Request $request): array
    {
        $method = strtoupper($request->getMethod());

        switch ($method) {
            case 'GET':
                return $request->getQueryParams();
            case 'POST':
            case 'PUT':
            case 'PATCH':
            case 'DELETE':
                $body = $request->getParsedBody();
                return is_array($body) ? $body : [];
            default:
                return [];
        }
    }

    /**
     * Apply a validation rule to a field.
     *
     * @param string $rule Rule name
     * @param string $field Field name
     * @param mixed $value Field value
     * @param string|null $param Rule parameter
     * @param array $allValues All request values
     * @return true|string True if valid, error message otherwise
     */
    protected function applyValidationRule(string $rule, string $field, $value, ?string $param, array $allValues)
    {
        switch ($rule) {
            case 'required':
                return ($value !== null && $value !== '') ? true : "The $field field is required.";

            case 'email':
                return (filter_var($value, FILTER_VALIDATE_EMAIL) !== false) ? true : "The $field must be a valid email address.";

            case 'numeric':
                return (is_numeric($value)) ? true : "The $field must be numeric.";

            case 'integer':
                return (filter_var($value, FILTER_VALIDATE_INT) !== false) ? true : "The $field must be an integer.";

            case 'min':
                if (is_string($value)) {
                    return (mb_strlen($value) >= (int) $param) ? true : "The $field must be at least $param characters.";
                }
                return ($value >= (int) $param) ? true : "The $field must be at least $param.";

            case 'max':
                if (is_string($value)) {
                    return (mb_strlen($value) <= (int) $param) ? true : "The $field must not exceed $param characters.";
                }
                return ($value <= (int) $param) ? true : "The $field must not exceed $param.";

            case 'in':
                $allowedValues = explode(',', $param);
                return (in_array($value, $allowedValues)) ? true : "The $field must be one of: $param.";

            case 'date':
                return (strtotime($value) !== false) ? true : "The $field must be a valid date.";

            case 'json':
                if (!is_string($value)) {
                    return "The $field must be a valid JSON string.";
                }
                json_decode($value);
                return (json_last_error() === JSON_ERROR_NONE) ? true : "The $field must be a valid JSON string.";

            case 'same':
                return ($value === ($allValues[$param] ?? null)) ? true : "The $field must match the $param field.";

            case 'regex':
                return (preg_match($param, $value)) ? true : "The $field format is invalid.";

            case 'url':
                return (filter_var($value, FILTER_VALIDATE_URL) !== false) ? true : "The $field must be a valid URL.";

            case 'ip':
                return (filter_var($value, FILTER_VALIDATE_IP) !== false) ? true : "The $field must be a valid IP address.";

            case 'array':
                return (is_array($value)) ? true : "The $field must be an array.";

            case 'boolean':
                return (is_bool($value) || in_array($value, [0, 1, '0', '1', true, false, 'true', 'false'], true)) ? true : "The $field must be boolean.";

            default:
                return true; // Unknown rule, consider valid
        }
    }

    /**
     * Sanitize and filter input data.
     *
     * @param array $data Input data
     * @param array $filters Filters to apply
     * @return array Filtered data
     */
    protected function filter(array $data, array $filters): array
    {
        $filtered = [];

        foreach ($filters as $field => $filter) {
            if (!isset($data[$field])) {
                continue;
            }

            $value = $data[$field];

            switch ($filter) {
                case 'int':
                    $filtered[$field] = (int) $value;
                    break;

                case 'float':
                    $filtered[$field] = (float) $value;
                    break;

                case 'bool':
                    $filtered[$field] = (bool) $value;
                    break;

                case 'string':
                    $filtered[$field] = (string) $value;
                    break;

                case 'email':
                    $filtered[$field] = filter_var($value, FILTER_SANITIZE_EMAIL);
                    break;

                case 'url':
                    $filtered[$field] = filter_var($value, FILTER_SANITIZE_URL);
                    break;

                case 'strip_tags':
                    $filtered[$field] = strip_tags((string) $value);
                    break;

                case 'trim':
                    $filtered[$field] = trim((string) $value);
                    break;

                default:
                    // If filter is a callable, use it
                    if (is_callable($filter)) {
                        $filtered[$field] = $filter($value);
                    } else {
                        $filtered[$field] = $value;
                    }
            }
        }

        return $filtered;
    }

    /**
     * Validate API token.
     *
     * @param string $token Token to validate
     * @param array|null $config Configuration override
     * @return bool
     */
    public function validateToken(string $token, ?array $config = null): bool
    {
        if (empty($token)) {
            return false;
        }

        $cfg = $config ?? $this->config;

        // Check configured API tokens (if any)
        if (!empty($cfg['api_tokens'])) {
            $validTokens = is_array($cfg['api_tokens']) ? $cfg['api_tokens'] : explode(',', $cfg['api_tokens']);
            return in_array($token, $validTokens);
        }

        // Fallback: validate using API secret
        if (!empty($cfg['api_secret'])) {
            return hash_equals($cfg['api_secret'], $token);
        }

        // Allow empty configuration during development
        return true;
    }

    /**
     * Validate Bearer token from request.
     *
     * @param Request $request HTTP request
     * @param array|null $config Configuration override
     * @return bool
     */
    public function validateTokenBearer(Request $request, ?array $config = null): bool
    {
        $cfg = $config ?? $this->config;
        $authHeader = $request->getHeaderLine('Authorization');

        if (!empty($cfg['api_secret'])) {
            return preg_match('/^Bearer\s'.preg_quote($cfg['api_secret'], '/').'$/', $authHeader);
        }

        return false;
    }

    /**
     * Validate request signature.
     *
     * @param array $params Request parameters
     * @param array|null $config Configuration override
     * @return bool
     */
    public function validateSign(array $params, ?array $config = null): bool
    {
        if (count($params) <= 1 || !isset($params['sign'])) {
            return true; // Signature validation is optional by default
        }

        $cfg = $config ?? $this->config;

        if (empty($cfg['api_secret'])) {
            return true; // No secret configured, skip validation
        }

        $sign = $params['sign'];
        unset($params['sign']);

        $expectedSign = \Kotchasan\Password::generateSign($params, $cfg['api_secret']);
        return hash_equals($expectedSign, $sign);
    }

    /**
     * Validate HTTP method.
     *
     * @param Request $request HTTP request
     * @param string $expectedMethod Expected HTTP method
     * @return bool
     */
    public static function validateMethod(Request $request, string $expectedMethod): bool
    {
        return strtoupper($request->getMethod()) === strtoupper($expectedMethod);
    }

    /**
     * Check if IP is allowed.
     *
     * @param Request $request HTTP request
     * @param array|null $config Configuration override
     * @return bool
     */
    public function validateIp(Request $request, ?array $config = null): bool
    {
        $cfg = $config ?? $this->config;
        $allowedIps = $cfg['api_ips'] ?? ['0.0.0.0'];

        if (in_array('0.0.0.0', $allowedIps)) {
            return true; // Allow all IPs
        }

        $clientIp = $request->getClientIp();
        return in_array($clientIp, $allowedIps);
    }
}
