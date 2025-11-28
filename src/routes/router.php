<?php

class Router {
    private $routes = [];

    public function get($path, $handler, $middleware = []) {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post($path, $handler, $middleware = []) {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function put($path, $handler, $middleware = []) {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function delete($path, $handler, $middleware = []) {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    private function addRoute($method, $path, $handler, $middleware = []) {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function handle($method, $uri) {
        $parsedUri = parse_url($uri);
        $path = $parsedUri['path'];
        $query = [];
        if (isset($parsedUri['query'])) {
            parse_str($parsedUri['query'], $query);
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $pattern = $this->convertToRegex($route['path']);
            if (preg_match($pattern, $path, $matches)) {
                $params = [];
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[$key] = $value;
                    }
                }

                // Get headers - handle both getallheaders() and $_SERVER fallback
                $headers = [];
                if (function_exists('getallheaders')) {
                    $headers = getallheaders();
                    // Normalize header keys to lowercase for consistency
                    if ($headers) {
                        $normalizedHeaders = [];
                        foreach ($headers as $key => $value) {
                            $normalizedHeaders[strtolower($key)] = $value;
                        }
                        $headers = $normalizedHeaders;
                    }
                } else {
                    // Fallback for environments where getallheaders() doesn't work
                    foreach ($_SERVER as $key => $value) {
                        if (strpos($key, 'HTTP_') === 0) {
                            $headerKey = str_replace('_', '-', substr($key, 5));
                            $headers[strtolower($headerKey)] = $value;
                        }
                    }
                }
                
                // Also check for Authorization header from $_SERVER (important for some servers)
                if (!isset($headers['authorization'])) {
                    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
                        $headers['authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
                    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                        $headers['authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
                    }
                }
                
                // Also check for specific headers
                if (isset($_SERVER['CONTENT_TYPE'])) {
                    $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
                }
                if (isset($_SERVER['CONTENT_LENGTH'])) {
                    $headers['content-length'] = $_SERVER['CONTENT_LENGTH'];
                }

                // Get request body - handle both JSON and FormData
                $rawBody = file_get_contents('php://input');
                $bodyData = [];
                
                // Check Content-Type header
                $contentType = $headers['content-type'] ?? '';
                
                // If it's JSON, decode it
                if (strpos($contentType, 'application/json') !== false) {
                    $jsonData = json_decode($rawBody, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $bodyData = $jsonData ?? [];
                    }
                }
                // If it's form data, use $_POST (PHP auto-populates it for POST requests)
                // For PUT requests with multipart/form-data, $_POST might be empty
                elseif (strpos($contentType, 'multipart/form-data') !== false || strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
                    // For POST requests, $_POST is automatically populated
                    // For PUT requests, we need to parse manually or rely on $_POST if available
                    if (!empty($_POST)) {
                        $bodyData = $_POST;
                    } else {
                        // For PUT requests, try to parse form-urlencoded data from raw body
                        // Note: multipart/form-data parsing is complex, so we rely on $_POST being populated
                        // If $_POST is empty for PUT, we'll need to handle it in the controller
                        $bodyData = [];
                    }
                }
                // Try to parse as JSON anyway (in case Content-Type is missing)
                elseif (!empty($rawBody)) {
                    $jsonData = json_decode($rawBody, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                        $bodyData = $jsonData;
                    } else {
                        // Try parsing as form data
                        parse_str($rawBody, $bodyData);
                    }
                }
                
                // Also merge $_POST if available (for FormData)
                if (!empty($_POST) && empty($bodyData)) {
                    $bodyData = $_POST;
                }

                $req = [
                    'method' => $method,
                    'uri' => $uri,
                    'path' => $path,
                    'params' => $params,
                    'query' => $query,
                    'body' => $rawBody, // Keep raw body for JSON parsing
                    'bodyData' => $bodyData, // Parsed body data
                    'headers' => $headers,
                ];

                $res = [];

                try {
                    // Apply middleware
                    $handler = function($req, $res) use ($route) {
                        return $this->callHandler($route['handler'], $req, $res);
                    };

                    if (empty($route['middleware'])) {
                        $handler($req, $res);
                    } else {
                        $next = $handler;
                        foreach (array_reverse($route['middleware']) as $middleware) {
                            $currentNext = $next;
                            $next = function($req, $res) use ($middleware, $currentNext) {
                                return $middleware($req, $res, $currentNext);
                            };
                        }
                        $next($req, $res);
                    }
                    return true;
                } catch (Exception $e) {
                    throw $e;
                }
            }
        }

        return false;
    }

    private function convertToRegex($path) {
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    private function callHandler($handler, $req, $res) {
        if (is_string($handler) && strpos($handler, '::') !== false) {
            list($class, $method) = explode('::', $handler);
            require_once __DIR__ . "/../controllers/{$class}.php";
            return $method($req, $res);
        } elseif (is_callable($handler)) {
            return $handler($req, $res);
        }
        throw new Exception("Invalid handler");
    }
}

