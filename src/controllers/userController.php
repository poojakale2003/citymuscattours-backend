<?php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../utils/ApiError.php';

function getProfile($req, $res) {
    $userId = $req['user']['sub'] ?? null;

    if (!$userId) {
        throw new ApiError(401, 'Authentication required');
    }

    $userModel = new User();
    $user = $userModel->findById($userId);

    if (!$user) {
        throw new ApiError(404, 'User not found');
    }

    unset($user['password']);

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['data' => $user]);
}

function updateProfile($req, $res) {
    $userId = $req['user']['sub'] ?? null;
    $data = json_decode($req['body'], true);

    if (!$userId) {
        throw new ApiError(401, 'Authentication required');
    }

    $userModel = new User();
    $user = $userModel->findById($userId);

    if (!$user) {
        throw new ApiError(404, 'User not found');
    }

    $updateData = [];
    if (isset($data['name'])) $updateData['name'] = $data['name'];
    if (isset($data['phone'])) $updateData['phone'] = $data['phone'];
    $updateData['updated_at'] = date('Y-m-d H:i:s');

    $userModel->update($userId, $updateData);
    $updatedUser = $userModel->findById($userId);
    unset($updatedUser['password']);

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['data' => $updatedUser]);
}

