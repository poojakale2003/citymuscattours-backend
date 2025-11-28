<?php

require_once __DIR__ . '/../models/Testimonial.php';
require_once __DIR__ . '/../utils/ApiError.php';

function listTestimonials($req, $res) {
    $page = (int)($req['query']['page'] ?? 1);
    $limit = (int)($req['query']['limit'] ?? 100);
    $filters = [];

    // Only filter by active status if explicitly requested (for public API)
    if (isset($req['query']['active'])) {
        $filters['is_active'] = filter_var($req['query']['active'], FILTER_VALIDATE_BOOLEAN);
    }

    if (isset($req['query']['search'])) {
        $filters['search'] = $req['query']['search'];
    }

    $testimonialModel = new Testimonial();
    $testimonials = $testimonialModel->getAll($filters, $page, $limit);
    $total = $testimonialModel->getTotalCount($filters);
    $totalPages = ceil($total / $limit);

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode([
        'data' => $testimonials,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => $totalPages,
        ],
    ]);
}

function getTestimonial($req, $res) {
    $id = $req['params']['id'] ?? null;

    if (!$id) {
        throw new ApiError(400, 'Testimonial ID is required');
    }

    $testimonialModel = new Testimonial();
    $testimonial = $testimonialModel->getById($id);

    if (!$testimonial) {
        throw new ApiError(404, 'Testimonial not found');
    }

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['data' => $testimonial]);
}

function createTestimonial($req, $res) {
    // Check if this is a multipart/form-data request (file upload)
    $isMultipart = !empty($_FILES);
    
    if ($isMultipart) {
        // Handle FormData request
        $data = $req['bodyData'] ?? $_POST;
        
        // Handle avatar upload
        if (!empty($_FILES['avatar']['name'])) {
            $uploadDir = __DIR__ . '/../../public/uploads/testimonials';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $file = $_FILES['avatar'];
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid('avatar_', true) . ($extension ? ".{$extension}" : '');
            $targetPath = $uploadDir . '/' . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $data['avatar'] = '/uploads/testimonials/' . $filename;
            }
        }
    } else {
        // Handle JSON request
        $data = json_decode($req['body'], true);
    }

    if (!$data) {
        throw new ApiError(400, 'Invalid data');
    }

    // Validate required fields
    if (empty($data['name'])) {
        throw new ApiError(400, 'Name is required');
    }

    if (empty($data['location'])) {
        throw new ApiError(400, 'Location is required');
    }

    if (empty($data['quote'])) {
        throw new ApiError(400, 'Quote is required');
    }

    // Validate rating if provided
    if (isset($data['rating']) && ($data['rating'] < 1 || $data['rating'] > 5)) {
        throw new ApiError(400, 'Rating must be between 1 and 5');
    }

    $testimonialModel = new Testimonial();
    $testimonialId = $testimonialModel->createTestimonial($data);
    $testimonial = $testimonialModel->getById($testimonialId);

    http_response_code(201);
    header('Content-Type: application/json');
    echo json_encode(['data' => $testimonial]);
}

function updateTestimonial($req, $res) {
    $id = $req['params']['id'] ?? null;

    if (!$id) {
        throw new ApiError(400, 'Testimonial ID is required');
    }

    $testimonialModel = new Testimonial();
    $testimonial = $testimonialModel->getById($id);

    if (!$testimonial) {
        throw new ApiError(404, 'Testimonial not found');
    }

    // Check if this is a multipart/form-data request (file upload)
    $isMultipart = !empty($_FILES);
    
    if ($isMultipart) {
        // Handle FormData request
        $data = $req['bodyData'] ?? $_POST;
        
        // Handle avatar upload
        if (!empty($_FILES['avatar']['name'])) {
            $uploadDir = __DIR__ . '/../../public/uploads/testimonials';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $file = $_FILES['avatar'];
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid('avatar_', true) . ($extension ? ".{$extension}" : '');
            $targetPath = $uploadDir . '/' . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $data['avatar'] = '/uploads/testimonials/' . $filename;
            }
        } elseif (!isset($data['avatar'])) {
            // Keep existing avatar if not provided
            $data['avatar'] = $testimonial['avatar'] ?? null;
        }
    } else {
        // Handle JSON request
        $data = json_decode($req['body'], true);
        
        // If avatar is not provided in JSON, keep existing avatar
        if (!isset($data['avatar'])) {
            $data['avatar'] = $testimonial['avatar'] ?? null;
        }
    }

    if (!$data) {
        throw new ApiError(400, 'Invalid data');
    }

    // Validate rating if provided
    if (isset($data['rating']) && ($data['rating'] < 1 || $data['rating'] > 5)) {
        throw new ApiError(400, 'Rating must be between 1 and 5');
    }

    $testimonialModel->updateTestimonial($id, $data);
    $updatedTestimonial = $testimonialModel->getById($id);

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['data' => $updatedTestimonial]);
}

function deleteTestimonial($req, $res) {
    $id = $req['params']['id'] ?? null;

    if (!$id) {
        throw new ApiError(400, 'Testimonial ID is required');
    }

    $testimonialModel = new Testimonial();
    $testimonial = $testimonialModel->getById($id);

    if (!$testimonial) {
        throw new ApiError(404, 'Testimonial not found');
    }

    $testimonialModel->deleteTestimonial($id);

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Testimonial deleted successfully']);
}

