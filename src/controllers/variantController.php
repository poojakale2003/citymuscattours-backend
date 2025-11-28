<?php

require_once __DIR__ . '/../models/PackageVariant.php';
require_once __DIR__ . '/../models/Package.php';
require_once __DIR__ . '/../utils/ApiError.php';

function getPackageVariants($req, $res) {
    $packageId = $req['params']['packageId'] ?? null;
    
    if (!$packageId) {
        throw new ApiError(400, 'Package ID is required');
    }

    // Verify package exists
    $packageModel = new Package();
    $package = $packageModel->getById($packageId);
    
    if (!$package) {
        throw new ApiError(404, 'Package not found');
    }

    $variantModel = new PackageVariant();
    $variants = $variantModel->findByPackage($packageId, false); // Get all, not just active

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['data' => $variants]);
}

function createVariant($req, $res) {
    $packageId = $req['params']['packageId'] ?? null;
    $data = json_decode($req['body'], true);
    
    if (!$packageId) {
        throw new ApiError(400, 'Package ID is required');
    }

    // Verify package exists
    $packageModel = new Package();
    $package = $packageModel->getById($packageId);
    
    if (!$package) {
        throw new ApiError(404, 'Package not found');
    }

    // Validate required fields
    $variantId = $data['variant_id'] ?? $data['variantId'] ?? null;
    $label = $data['label'] ?? null;

    if (!$variantId || !$label) {
        throw new ApiError(400, 'Variant ID and label are required');
    }

    $variantModel = new PackageVariant();
    
    // Check if variant already exists
    $existing = $variantModel->findByPackageAndVariant($packageId, $variantId);
    if ($existing) {
        throw new ApiError(400, 'Variant with this ID already exists for this package');
    }

    // Prepare variant data
    $variantData = [
        'package_id' => $packageId,
        'variant_id' => $variantId,
        'label' => $label,
        'subtitle' => $data['subtitle'] ?? '',
        'description' => $data['description'] ?? null,
        'language' => $data['language'] ?? 'English',
        'start_time' => $data['start_time'] ?? $data['startTime'] ?? '10:00 AM',
        'meeting_point' => $data['meeting_point'] ?? $data['meetingPoint'] ?? '',
        'perks' => isset($data['perks']) ? json_encode($data['perks']) : json_encode([]),
        'price_modifier' => isset($data['price_modifier']) ? (float)$data['price_modifier'] : (isset($data['priceModifier']) ? (float)$data['priceModifier'] : 0.00),
        'base_price_override' => isset($data['base_price_override']) ? (float)$data['base_price_override'] : (isset($data['basePriceOverride']) ? (float)$data['basePriceOverride'] : null),
        'rating' => isset($data['rating']) ? (float)$data['rating'] : null,
        'reviews' => isset($data['reviews']) ? (int)$data['reviews'] : 0,
        'cancellation_policy' => $data['cancellation_policy'] ?? $data['cancellationPolicy'] ?? 'Free cancellation up to 24 hours',
        'pickup_included' => isset($data['pickup_included']) ? (bool)$data['pickup_included'] : (isset($data['pickupIncluded']) ? (bool)$data['pickupIncluded'] : true),
        'is_active' => isset($data['is_active']) ? (bool)$data['is_active'] : (isset($data['isActive']) ? (bool)$data['isActive'] : true),
        'display_order' => isset($data['display_order']) ? (int)$data['display_order'] : (isset($data['displayOrder']) ? (int)$data['displayOrder'] : 0),
    ];

    $id = $variantModel->createVariant($variantData);
    $variant = $variantModel->findByPackageAndVariant($packageId, $variantId);

    http_response_code(201);
    header('Content-Type: application/json');
    echo json_encode(['data' => $variant]);
}

function updateVariant($req, $res) {
    $id = $req['params']['id'] ?? null;
    $data = json_decode($req['body'], true);
    
    if (!$id) {
        throw new ApiError(400, 'Variant ID is required');
    }

    $variantModel = new PackageVariant();
    $variant = $variantModel->getById($id);

    if (!$variant) {
        throw new ApiError(404, 'Variant not found');
    }

    // Prepare update data
    $updateData = [];
    $allowedFields = [
        'label', 'subtitle', 'description', 'language', 'start_time', 'meeting_point',
        'perks', 'price_modifier', 'base_price_override', 'rating', 'reviews',
        'cancellation_policy', 'pickup_included', 'is_active', 'display_order'
    ];

    foreach ($allowedFields as $field) {
        if (array_key_exists($field, $data)) {
            if ($field === 'perks' && is_array($data[$field])) {
                $updateData[$field] = json_encode($data[$field]);
            } else {
                $updateData[$field] = $data[$field];
            }
        } elseif (array_key_exists(camelCase($field), $data)) {
            $camelField = camelCase($field);
            if ($field === 'perks' && is_array($data[$camelField])) {
                $updateData[$field] = json_encode($data[$camelField]);
            } else {
                $updateData[$field] = $data[$camelField];
            }
        }
    }

    if (empty($updateData)) {
        throw new ApiError(400, 'No valid fields to update');
    }

    $variantModel->updateVariant($id, $updateData);
    $updatedVariant = $variantModel->getById($id);

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['data' => $updatedVariant]);
}

function deleteVariant($req, $res) {
    $id = $req['params']['id'] ?? null;
    
    if (!$id) {
        throw new ApiError(400, 'Variant ID is required');
    }

    $variantModel = new PackageVariant();
    $variant = $variantModel->getById($id);

    if (!$variant) {
        throw new ApiError(404, 'Variant not found');
    }

    $variantModel->deleteVariant($id);

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Variant deleted successfully']);
}

function camelCase($str) {
    return lcfirst(str_replace('_', '', ucwords($str, '_')));
}


