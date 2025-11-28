<?php

require_once __DIR__ . '/../utils/ApiError.php';

function notFound($req, $res) {
    $uri = $req['uri'] ?? 'unknown';
    $method = $req['method'] ?? 'unknown';
    throw new ApiError(404, "Route not found: {$method} {$uri}. Please check if the backend endpoint exists.");
}

