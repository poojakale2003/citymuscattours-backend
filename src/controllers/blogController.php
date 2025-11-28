<?php

require_once __DIR__ . '/../models/Blog.php';
require_once __DIR__ . '/../utils/ApiError.php';

// Helper function to parse multipart/form-data for PUT requests
function parseMultipartFormData($rawBody, $boundary) {
    $data = [];
    if (empty($boundary) || empty($rawBody)) {
        return $data;
    }
    
    // Split by boundary
    $parts = explode('--' . $boundary, $rawBody);
    
    foreach ($parts as $part) {
        $part = trim($part);
        if (empty($part) || $part === '--' || $part === '-') {
            continue;
        }
        
        // Extract field name from Content-Disposition header
        if (preg_match('/Content-Disposition:\s*form-data;\s*name="([^"]+)"/i', $part, $nameMatch)) {
            $fieldName = $nameMatch[1];
            
            // Skip file fields (we only want form fields, files are in $_FILES)
            if (preg_match('/filename="/i', $part)) {
                continue;
            }
            
            // Extract value - content comes after the headers
            // Headers end with \r\n\r\n or \n\n
            if (preg_match('/\r?\n\r?\n(.*)$/s', $part, $valueMatch)) {
                $value = trim($valueMatch[1]);
                // Remove trailing boundary markers
                $value = rtrim($value, "\r\n-");
                $data[$fieldName] = $value;
            }
        }
    }
    
    return $data;
}

function listBlogs($req, $res) {
    $query = $req['query'] ?? [];
    
    $page = (int)($query['page'] ?? 1);
    $limit = min((int)($query['limit'] ?? 10), 50);
    $category = $query['category'] ?? null;
    $search = $query['search'] ?? null;
    
    // For admin access, allow viewing unpublished blogs
    $published = isset($query['published']) ? ($query['published'] === 'true' || $query['published'] === true) : null;
    
    $blogModel = new Blog();
    
    $filters = [];
    if ($category) $filters['category'] = $category;
    if ($search) $filters['search'] = $search;
    if ($published !== null) $filters['published'] = $published;
    
    $blogs = $blogModel->getAll($filters, $page, $limit);
    $total = $blogModel->getTotalCount($filters);
    
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode([
        'data' => $blogs,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

function getBlog($req, $res) {
    $id = $req['params']['id'] ?? null;
    
    if (!$id) {
        throw new ApiError(400, 'Blog ID is required');
    }
    
    $blogModel = new Blog();
    $blog = $blogModel->getById($id);
    
    if (!$blog) {
        throw new ApiError(404, 'Blog not found');
    }
    
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['data' => $blog]);
}

function getBlogBySlug($req, $res) {
    $slug = $req['params']['slug'] ?? null;
    
    if (!$slug) {
        throw new ApiError(400, 'Blog slug is required');
    }
    
    $blogModel = new Blog();
    $blog = $blogModel->getBySlug($slug);
    
    if (!$blog) {
        throw new ApiError(404, 'Blog not found');
    }
    
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['data' => $blog]);
}

function createBlog($req, $res) {
    // Check if this is a multipart/form-data request (file upload)
    $isMultipart = !empty($_FILES);
    
    if ($isMultipart) {
        // Handle FormData request
        // Use bodyData from router if available, otherwise use $_POST
        $data = $req['bodyData'] ?? $_POST;
        
        // Convert string boolean to actual boolean for is_published
        if (isset($data['is_published'])) {
            $data['is_published'] = ($data['is_published'] === 'true' || $data['is_published'] === true || $data['is_published'] === '1' || $data['is_published'] === 1);
        }
        
        // Handle image upload
        if (!empty($_FILES['featureImage']['name'])) {
            $uploadDir = __DIR__ . '/../../public/uploads/blogs';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $file = $_FILES['featureImage'];
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid('blog_', true) . ($extension ? ".{$extension}" : '');
            $targetPath = $uploadDir . '/' . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $data['image'] = '/uploads/blogs/' . $filename;
            }
        }
        // If image URL is provided in FormData, it's already in $data['image'], no need to reassign
    } else {
        // Handle JSON request
        $data = json_decode($req['body'], true);
    }
    
    if (empty($data['title'])) {
        throw new ApiError(400, 'Title is required');
    }
    
    if (empty($data['content'])) {
        throw new ApiError(400, 'Content is required');
    }
    
    $blogModel = new Blog();
    $id = $blogModel->createBlog($data);
    $newBlog = $blogModel->getById($id);
    
    http_response_code(201);
    header('Content-Type: application/json');
    echo json_encode(['data' => $newBlog]);
}

function updateBlog($req, $res) {
    $id = $req['params']['id'] ?? null;
    
    if (!$id) {
        throw new ApiError(400, 'Blog ID is required');
    }
    
    $blogModel = new Blog();
    $existingBlog = $blogModel->getById($id);
    
    if (!$existingBlog) {
        throw new ApiError(404, 'Blog not found');
    }
    
    // Check if this is a multipart/form-data request (file upload)
    $isMultipart = !empty($_FILES);
    
    if ($isMultipart) {
        // Handle FormData request
        $data = [];
        
        // Try $_POST first (works for POST requests)
        if (!empty($_POST)) {
            $data = $_POST;
        }
        
        // Also try bodyData from router
        if (!empty($req['bodyData'])) {
            $data = array_merge($data, $req['bodyData']);
        }
        
        // For PUT requests, $_POST might be empty, so try parsing manually
        if (empty($data) && !empty($req['body'])) {
            $contentType = $req['headers']['content-type'] ?? $_SERVER['CONTENT_TYPE'] ?? '';
            if (preg_match('/boundary=([^;]+)/i', $contentType, $matches)) {
                $boundary = trim($matches[1], " \t\n\r\0\x0B\"'");
                $parsed = parseMultipartFormData($req['body'], $boundary);
                if (!empty($parsed)) {
                    $data = array_merge($data, $parsed);
                }
            }
        }
        
        // Final fallback: if data is still empty, log error for debugging
        if (empty($data)) {
            error_log("updateBlog - Error: FormData detected but no form fields found.");
            error_log("POST data: " . json_encode($_POST));
            error_log("bodyData: " . json_encode($req['bodyData'] ?? []));
            error_log("Content-Type: " . ($req['headers']['content-type'] ?? 'not set'));
            error_log("Body length: " . strlen($req['body'] ?? ''));
            throw new ApiError(400, 'Form data not received. Please ensure all fields are filled.');
        }
        
        // Convert string boolean to actual boolean for is_published
        if (isset($data['is_published'])) {
            $data['is_published'] = ($data['is_published'] === 'true' || $data['is_published'] === true || $data['is_published'] === '1' || $data['is_published'] === 1);
        }
        
        // Handle image upload
        if (!empty($_FILES['featureImage']['name'])) {
            $uploadDir = __DIR__ . '/../../public/uploads/blogs';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $file = $_FILES['featureImage'];
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid('blog_', true) . ($extension ? ".{$extension}" : '');
            $targetPath = $uploadDir . '/' . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $data['image'] = '/uploads/blogs/' . $filename;
            }
        } elseif (!empty($data['keepExistingImage']) && $data['keepExistingImage'] === 'true') {
            // Keep existing image from database
            $data['image'] = $existingBlog['image'] ?? null;
        } else {
            // If no image field is provided and no keepExistingImage flag, keep the existing image
            $data['image'] = $existingBlog['image'] ?? null;
        }
    } else {
        // Handle JSON request
        $data = json_decode($req['body'], true);
        
        // If image is not provided in JSON, keep existing image
        if (!isset($data['image'])) {
            $data['image'] = $existingBlog['image'] ?? null;
        }
    }
    
    // Validate required fields
    if (empty($data['title'])) {
        throw new ApiError(400, 'Title is required');
    }
    
    if (empty($data['content'])) {
        throw new ApiError(400, 'Content is required');
    }
    
    $blogModel->updateBlog($id, $data);
    $updatedBlog = $blogModel->getById($id);
    
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['data' => $updatedBlog]);
}

function deleteBlog($req, $res) {
    $id = $req['params']['id'] ?? null;
    
    if (!$id) {
        throw new ApiError(400, 'Blog ID is required');
    }
    
    $blogModel = new Blog();
    $existingBlog = $blogModel->getById($id);
    
    if (!$existingBlog) {
        throw new ApiError(404, 'Blog not found');
    }
    
    $blogModel->deleteBlog($id);
    
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Blog deleted successfully']);
}

