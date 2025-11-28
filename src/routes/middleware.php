<?php

require_once __DIR__ . '/../middleware/authMiddleware.php';

function applyMiddleware($name, $req, $res, $next) {
    if ($name === 'authenticate') {
        return authenticate($req, $res, $next);
    } elseif (strpos($name, 'authorize:') === 0) {
        $role = substr($name, 10); // Remove 'authorize:' prefix
        $authorizeFn = authorize($role);
        return $authorizeFn($req, $res, $next);
    }
    return $next($req, $res);
}
