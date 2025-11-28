<?php

require_once __DIR__ . '/../models/Wishlist.php';
require_once __DIR__ . '/../models/Package.php';
require_once __DIR__ . '/../utils/ApiError.php';

function addToWishlist($req, $res) {
    $data = json_decode($req['body'], true);
    $userId = $req['user']['sub'] ?? null;
    $packageId = $data['packageId'] ?? null;

    if (!$packageId) {
        throw new ApiError(400, 'Package ID is required');
    }

    $packageModel = new Package();
    $package = $packageModel->findById($packageId);
    
    if (!$package) {
        throw new ApiError(404, 'Package not found');
    }

    $wishlistModel = new Wishlist();
    
    if ($userId) {
        $existing = $wishlistModel->findByUserAndPackage($userId, $packageId);
        if ($existing) {
            throw new ApiError(409, 'Package already in wishlist');
        }
        
        $wishlistData = [
            'user_id' => $userId,
            'package_id' => $packageId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    } else {
        $deviceId = $data['deviceId'] ?? null;
        if (!$deviceId) {
            throw new ApiError(400, 'Device ID is required for guest users');
        }
        
        $existing = $wishlistModel->findByDeviceAndPackage($deviceId, $packageId);
        if ($existing) {
            throw new ApiError(409, 'Package already in wishlist');
        }
        
        $wishlistData = [
            'device_id' => $deviceId,
            'package_id' => $packageId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    $wishlistId = $wishlistModel->create($wishlistData);
    $wishlist = $wishlistModel->findById($wishlistId);

    http_response_code(201);
    header('Content-Type: application/json');
    echo json_encode(['data' => $wishlist]);
}

function getWishlist($req, $res) {
    $userId = $req['user']['sub'] ?? null;

    if (!$userId) {
        throw new ApiError(401, 'Authentication required');
    }

    $wishlistModel = new Wishlist();
    $wishlist = $wishlistModel->findByUser($userId);

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['data' => $wishlist]);
}

function removeFromWishlist($req, $res) {
    $id = $req['params']['id'] ?? null;
    $userId = $req['user']['sub'] ?? null;

    if (!$id) {
        throw new ApiError(400, 'Wishlist item ID is required');
    }

    if (!$userId) {
        throw new ApiError(401, 'Authentication required');
    }

    $wishlistModel = new Wishlist();
    $wishlist = $wishlistModel->findById($id);

    if (!$wishlist || $wishlist['user_id'] != $userId) {
        throw new ApiError(404, 'Wishlist item not found');
    }

    $wishlistModel->delete($id);

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Removed from wishlist']);
}

