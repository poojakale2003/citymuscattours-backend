<?php
/**
 * Debug Package Request
 * This script helps debug what data is being received when creating packages
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

$debug = [
    'method' => $_SERVER['REQUEST_METHOD'],
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
    'content_length' => $_SERVER['CONTENT_LENGTH'] ?? 0,
];

// Get raw body
$rawBody = file_get_contents('php://input');
$debug['raw_body_length'] = strlen($rawBody);
$debug['raw_body_preview'] = substr($rawBody, 0, 500);

// Try to parse as JSON
$jsonData = json_decode($rawBody, true);
if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
    $debug['body_type'] = 'JSON';
    $debug['body_data'] = $jsonData;
    $debug['body_keys'] = array_keys($jsonData);
} else {
    $debug['body_type'] = 'Not JSON';
    $debug['json_error'] = json_last_error_msg();
}

// Check $_POST
if (!empty($_POST)) {
    $debug['post_data'] = $_POST;
    $debug['post_keys'] = array_keys($_POST);
}

// Check for title/name
$hasTitle = isset($jsonData['title']) || isset($_POST['title']);
$hasName = isset($jsonData['name']) || isset($_POST['name']);
$hasDestination = isset($jsonData['destination']) || isset($_POST['destination']);
$hasPrice = isset($jsonData['price']) || isset($_POST['price']);

$debug['fields_found'] = [
    'title' => $hasTitle,
    'name' => $hasName,
    'destination' => $hasDestination,
    'price' => $hasPrice,
];

$debug['values'] = [
    'title' => $jsonData['title'] ?? $_POST['title'] ?? null,
    'name' => $jsonData['name'] ?? $_POST['name'] ?? null,
    'destination' => $jsonData['destination'] ?? $_POST['destination'] ?? null,
    'price' => $jsonData['price'] ?? $_POST['price'] ?? null,
];

// Check headers
$headers = [];
if (function_exists('getallheaders')) {
    $headers = getallheaders();
}
$debug['headers'] = $headers;

echo json_encode($debug, JSON_PRETTY_PRINT);

