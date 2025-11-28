<?php

require_once __DIR__ . '/../models/ContactLead.php';
require_once __DIR__ . '/../utils/ApiError.php';

function createLead($req, $res) {
    $data = json_decode($req['body'], true);
    $name = $data['name'] ?? null;
    $email = $data['email'] ?? null;
    $message = $data['message'] ?? null;

    if (!$name || !$email || !$message) {
        throw new ApiError(400, 'Name, email, and message are required');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new ApiError(400, 'Invalid email address');
    }

    $leadModel = new ContactLead();
    $leadData = [
        'name' => $name,
        'email' => strtolower(trim($email)),
        'message' => $message,
        'phone' => $data['phone'] ?? null,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    $id = $leadModel->createLead($leadData);
    $lead = $leadModel->getById($id);

    http_response_code(201);
    header('Content-Type: application/json');
    echo json_encode(['data' => $lead, 'message' => 'Lead created successfully']);
}

