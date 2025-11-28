<?php

require_once __DIR__ . '/../models/Review.php';
require_once __DIR__ . '/../models/Package.php';
require_once __DIR__ . '/../utils/ApiError.php';

function createReview($req, $res) {
    $data = json_decode($req['body'], true);
    $userId = $req['user']['sub'] ?? null;

    if (!$userId) {
        throw new ApiError(401, 'Authentication required');
    }

    $packageId = $data['packageId'] ?? null;
    $rating = $data['rating'] ?? null;
    $comment = $data['comment'] ?? null;

    if (!$packageId || !$rating) {
        throw new ApiError(400, 'Package ID and rating are required');
    }

    if ($rating < 1 || $rating > 5) {
        throw new ApiError(400, 'Rating must be between 1 and 5');
    }

    $packageModel = new Package();
    $package = $packageModel->findById($packageId);
    
    if (!$package) {
        throw new ApiError(404, 'Package not found');
    }

    $reviewModel = new Review();
    $existingReview = $reviewModel->findByUserAndPackage($userId, $packageId);
    
    if ($existingReview) {
        throw new ApiError(409, 'Review already exists for this package');
    }

    $reviewData = [
        'user_id' => $userId,
        'package_id' => $packageId,
        'rating' => $rating,
        'comment' => $comment,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    $reviewId = $reviewModel->create($reviewData);
    $review = $reviewModel->findById($reviewId);

    http_response_code(201);
    header('Content-Type: application/json');
    echo json_encode(['data' => $review]);
}

function getReviews($req, $res) {
    $packageId = $req['query']['packageId'] ?? null;
    $page = (int)($req['query']['page'] ?? 1);
    $limit = (int)($req['query']['limit'] ?? 10);

    if (!$packageId) {
        throw new ApiError(400, 'Package ID is required');
    }

    $reviewModel = new Review();
    $reviews = $reviewModel->findByPackage($packageId, $page, $limit);
    $ratingStats = $reviewModel->getAverageRating($packageId);

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode([
        'data' => $reviews,
        'rating' => $ratingStats,
    ]);
}

