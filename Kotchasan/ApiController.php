<?php

namespace Kotchasan;

use Kotchasan\Http\Request;

/**
 * API Controller base class for handling API requests and routing.
 *
 * @see https://www.kotchasan.com/
 */
class ApiController extends \Kotchasan\KBase
{
    /**
     * API Controller index action - Router for API endpoints.
     *
     * @param Request $request The HTTP request object.
     */
    public function index(Request $request)
    {
        $headers = ['Content-type' => 'application/json; charset=UTF-8'];

        if (empty(self::$cfg->api_token) || empty(self::$cfg->api_ips)) {
            // Token or IP authorization not set up
            $result = [
                'code' => 503,
                'message' => 'Unavailable API'
            ];
        } elseif (in_array('0.0.0.0', self::$cfg->api_ips) || in_array($request->getClientIp(), self::$cfg->api_ips)) {
            try {
                // Get values from the router - support both patterns:
                // Pattern 1: api.php/v1/auth/login -> V1\Auth\Controller::login
                // Pattern 2: api.php/module/method/action (legacy Model pattern)
                $module = $request->get('module')->filter('a-z0-9');
                $method = $request->get('method')->filter('a-z');
                $action = $request->get('action')->filter('a-z');

                // Try Controller pattern first (v1/auth/login -> V1\Auth\Controller::login)
                $controllerClass = ucfirst($module).'\\'.ucfirst($method).'\\Controller';

                if (class_exists($controllerClass) && method_exists($controllerClass, $action)) {
                    // Instantiate controller and call method
                    $controller = new $controllerClass();
                    $result = $controller->$action($request);

                    // If result is a Response object, handle it specially
                    if ($result instanceof \Kotchasan\Http\Response) {
                        // Add CORS headers if configured
                        if (!empty(self::$cfg->api_cors)) {
                            $result = $result->withHeader('Access-Control-Allow-Origin', self::$cfg->api_cors)
                                ->withHeader('Access-Control-Allow-Headers', 'origin, x-requested-with, content-type, authorization, x-api-token, x-access-token');
                        }
                        $result->send();
                        return;
                    }
                } else {
                    // Fallback to Model pattern (legacy support)
                    $modelClass = ucfirst($module).'\\'.ucfirst($method).'\\Model';
                    if (class_exists($modelClass) && method_exists($modelClass, $action)) {
                        $result = createClass($modelClass)->$action($request);
                    } else {
                        // Error: class or method not found
                        $result = [
                            'code' => 404,
                            'message' => 'Endpoint not found: '.$controllerClass.'::'.$action.' or '.$modelClass.'::'.$action
                        ];
                    }
                }

                // Add CORS headers for JSON responses
                if (!empty(self::$cfg->api_cors)) {
                    $headers['Access-Control-Allow-Origin'] = self::$cfg->api_cors;
                    $headers['Access-Control-Allow-Headers'] = 'origin, x-requested-with, content-type, authorization, x-api-token, x-access-token';
                    $headers['Access-Control-Allow-Methods'] = 'GET, POST, PUT, DELETE, OPTIONS';
                }

            } catch (ApiException $e) {
                // API Error
                $result = [
                    'code' => $e->getCode(),
                    'message' => $e->getMessage()
                ];
            } catch (\Exception $e) {
                // General Exception
                $result = [
                    'code' => 500,
                    'message' => 'Internal server error: '.$e->getMessage()
                ];
            }
        } else {
            // IP not allowed
            $result = [
                'code' => 403,
                'message' => 'Forbidden'
            ];
        }

        // Return JSON response based on $result
        $response = new \Kotchasan\Http\Response();
        $response->withHeaders($headers)
            ->withStatus(empty($result['code']) ? 200 : $result['code'])
            ->withContent(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
            ->send();
    }

    /**
     * Validate the API token.
     *
     * @param string $token The token to validate.
     *
     * @return bool True if the token is valid, otherwise throws an ApiException with an "Invalid token" error.
     */
    public static function validateToken($token)
    {
        if (self::$cfg->api_token === $token) {
            return true;
        }
        throw new ApiException('Invalid token', 401);
    }

    /**
     * Validate the Bearer token.
     *
     * @param Request $request The HTTP request object.
     *
     * @return bool True if the token is valid, otherwise throws an ApiException with an "Invalid token" error.
     */
    public static function validateTokenBearer(Request $request)
    {
        if (preg_match('/^Bearer\s'.preg_quote(self::$cfg->api_token, '/').'$/', $request->getHeaderLine('Authorization'))) {
            return true;
        }
        throw new ApiException('Invalid token', 401);
    }

    /**
     * Validate the sign.
     *
     * @param array $params The parameters to validate.
     *
     * @return bool True if the sign is valid, otherwise throws an ApiException with an "Invalid sign" error.
     */
    public static function validateSign($params)
    {
        if (count($params) > 1 && isset($params['sign'])) {
            $sign = $params['sign'];
            unset($params['sign']);
            if ($sign === \Kotchasan\Password::generateSign($params, self::$cfg->api_secret)) {
                return true;
            }
        }
        throw new ApiException('Invalid sign', 403);
    }

    /**
     * Validate the HTTP method.
     *
     * @param Request $request The HTTP request object.
     * @param string  $method  The expected HTTP method (e.g., POST, GET, PUT, DELETE, OPTIONS).
     *
     * @return bool True if the method is valid, otherwise throws an ApiException with a "Method not allowed" error.
     */
    public static function validateMethod(Request $request, $method)
    {
        if ($request->getMethod() === $method) {
            return true;
        }
        throw new ApiException('Method not allowed', 405);
    }

    /**
     * Validate IP address.
     *
     * @param Request $request The HTTP request object.
     *
     * @return bool True if the IP is allowed, otherwise throws an ApiException.
     */
    public static function validateIpAddress(Request $request)
    {
        $allowedIps = self::$cfg->api_ips ?? ['0.0.0.0'];

        if (in_array('0.0.0.0', $allowedIps)) {
            return true; // Allow all IPs
        }

        $clientIp = $request->getClientIp();
        if (in_array($clientIp, $allowedIps)) {
            return true;
        }

        throw new ApiException('IP not allowed', 403);
    }
}
