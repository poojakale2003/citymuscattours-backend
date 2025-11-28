<?php

require_once __DIR__ . '/../models/Package.php';
require_once __DIR__ . '/../utils/ApiError.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/PriceHelper.php';

// Helper function to parse multipart/form-data for PUT requests
if (!function_exists('parseMultipartFormDataForPackage')) {
    function parseMultipartFormDataForPackage($rawBody, $boundary, &$extractedFiles = null) {
    $data = [];
    $files = [];
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
            
            // Check if this is a file field
            if (preg_match('/filename="([^"]*)"/i', $part, $filenameMatch)) {
                $filename = $filenameMatch[1];
                
                // Extract Content-Type if present
                $contentType = 'application/octet-stream';
                if (preg_match('/Content-Type:\s*([^\r\n]+)/i', $part, $contentTypeMatch)) {
                    $contentType = trim($contentTypeMatch[1]);
                }
                
                // Extract file content - content comes after headers
                if (preg_match('/\r?\n\r?\n(.*)$/s', $part, $contentMatch)) {
                    $fileContent = $contentMatch[1];
                    // Remove trailing boundary markers
                    $fileContent = rtrim($fileContent, "\r\n-");
                    
                    // Save to temporary file
                    $tmpFile = tempnam(sys_get_temp_dir(), 'upload_');
                    file_put_contents($tmpFile, $fileContent);
                    
                    // Handle array notation (e.g., "galleryImages[]")
                    if (preg_match('/^(.+)\[\]$/', $fieldName, $arrayMatch)) {
                        $arrayName = $arrayMatch[1];
                        if (!isset($files[$arrayName])) {
                            $files[$arrayName] = [
                                'name' => [],
                                'type' => [],
                                'tmp_name' => [],
                                'error' => [],
                                'size' => []
                            ];
                        }
                        $files[$arrayName]['name'][] = $filename;
                        $files[$arrayName]['type'][] = $contentType;
                        $files[$arrayName]['tmp_name'][] = $tmpFile;
                        $files[$arrayName]['error'][] = UPLOAD_ERR_OK;
                        $files[$arrayName]['size'][] = strlen($fileContent);
                    } else {
                        $files[$fieldName] = [
                            'name' => $filename,
                            'type' => $contentType,
                            'tmp_name' => $tmpFile,
                            'error' => UPLOAD_ERR_OK,
                            'size' => strlen($fileContent)
                        ];
                    }
                }
                continue;
            }
            
            // Regular form field
            // Extract value - content comes after the headers
            // Headers end with \r\n\r\n or \n\n
            if (preg_match('/\r?\n\r?\n(.*)$/s', $part, $valueMatch)) {
                $value = trim($valueMatch[1]);
                // Remove trailing boundary markers
                $value = rtrim($value, "\r\n-");
                
                // Handle array notation (e.g., "galleryImages[]")
                if (preg_match('/^(.+)\[\]$/', $fieldName, $arrayMatch)) {
                    $arrayName = $arrayMatch[1];
                    if (!isset($data[$arrayName])) {
                        $data[$arrayName] = [];
                    }
                    $data[$arrayName][] = $value;
                } else {
                    $data[$fieldName] = $value;
                }
            }
        }
    }
    
    // Populate extractedFiles array if provided
    if ($extractedFiles !== null) {
        $extractedFiles = $files;
    }
    
    return $data;
    }
}

if (!function_exists('saveUploadedFileOrTemp')) {
    function saveUploadedFileOrTemp($tmpPath, $targetPath) {
        // If PHP recognizes it as a genuine uploaded file, use move_uploaded_file
        if (is_uploaded_file($tmpPath)) {
            return move_uploaded_file($tmpPath, $targetPath);
        }

        // Otherwise fallback to rename/copy for manually extracted temp files
        if (!file_exists($tmpPath)) {
            error_log("saveUploadedFileOrTemp - Temp file does not exist: " . $tmpPath);
            return false;
        }

        if (@rename($tmpPath, $targetPath)) {
            return true;
        }

        // Final fallback - copy then unlink
        if (@copy($tmpPath, $targetPath)) {
            @unlink($tmpPath);
            return true;
        }

        error_log("saveUploadedFileOrTemp - Failed to move temp file to target: " . $targetPath);
        return false;
    }
}

function listPackages($req, $res) {
    $query = $req['query'] ?? [];
    
    // Debug: Log the raw request URI and query
    error_log("listPackages - Raw URI: " . ($req['uri'] ?? 'not set'));
    error_log("listPackages - Raw query array: " . json_encode($query));
    
    $page = (int)($query['page'] ?? 1);
    $limit = min((int)($query['limit'] ?? 10), 50);
    $destination = $query['destination'] ?? null;
    $category = $query['category'] ?? null;
    $search = $query['search'] ?? null;
    $minPrice = isset($query['minPrice']) ? (float)$query['minPrice'] : null;
    $maxPrice = isset($query['maxPrice']) ? (float)$query['maxPrice'] : null;
    $featured = isset($query['featured']) ? $query['featured'] === 'true' : null;
    
    // Handle archived parameter - check for both string 'true' and boolean true
    $archived = false;
    if (isset($query['archived'])) {
        $archivedValue = $query['archived'];
        error_log("listPackages - archived value from query: " . var_export($archivedValue, true) . " (type: " . gettype($archivedValue) . ")");
        $archived = ($archivedValue === 'true' || $archivedValue === true || $archivedValue === '1' || $archivedValue === 1);
    } else {
        error_log("listPackages - archived parameter NOT SET in query");
    }
    
    error_log("listPackages - Query params: " . json_encode($query));
    error_log("listPackages - archived parameter: " . var_export($query['archived'] ?? 'not set', true) . ", parsed as: " . ($archived ? 'true' : 'false'));
    error_log("listPackages - Will filter for is_archived = " . ($archived ? '1' : '0'));

    $packageModel = new Package();
    
    $filters = [];
    if ($destination) $filters['destination'] = $destination;
    if ($category) $filters['category'] = $category;
    if ($featured !== null) $filters['isFeatured'] = $featured;
    if ($minPrice !== null) $filters['minPrice'] = $minPrice;
    if ($maxPrice !== null) $filters['maxPrice'] = $maxPrice;
    // Always set isArchived filter (even if false) to ensure proper filtering
    $filters['isArchived'] = $archived;
    error_log("listPackages - Setting filter isArchived = " . var_export($archived, true) . " (type: " . gettype($archived) . ")");

    $packages = $packageModel->search($filters, $page, $limit);
    
    // Log the results for debugging
    error_log("listPackages - Found " . count($packages) . " packages with is_archived = " . ($archived ? '1' : '0'));
    if (!empty($packages)) {
        error_log("listPackages - First package: id=" . $packages[0]['id'] . ", name=" . ($packages[0]['name'] ?? 'N/A') . ", is_archived=" . ($packages[0]['is_archived'] ?? 'N/A'));
    }
    
    // Get total count with same filters
    $db = getDB();
    $countSql = "SELECT COUNT(*) as total FROM packages WHERE is_archived = ?";
    $countParams = [$archived ? 1 : 0];
    
    // Add other filters to count query
    if ($destination) {
        $countSql .= " AND destination LIKE ?";
        $countParams[] = "%{$destination}%";
    }
    if ($category) {
        $countSql .= " AND category = ?";
        $countParams[] = $category;
    }
    if ($featured !== null) {
        $countSql .= " AND is_featured = ?";
        $countParams[] = $featured ? 1 : 0;
    }
    
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($countParams);
    $total = $countStmt->fetch()['total'];

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode([
        'data' => $packages,
        'meta' => [
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => (int)ceil($total / $limit) ?: 1,
        ],
    ]);
}

function getPackage($req, $res) {
    $id = $req['params']['id'] ?? null;
    
    if (!$id) {
        throw new ApiError(400, 'Package ID is required');
    }

    $packageModel = new Package();
    $package = null;

    // If the identifier is a pure integer, fetch by primary key. Otherwise treat it as a slug.
    if (is_numeric($id) && (string)(int)$id === (string)$id) {
        $package = $packageModel->getById((int)$id);
    } else {
        $package = $packageModel->findBySlug($id);
    }

    if (!$package) {
        throw new ApiError(404, 'Package not found');
    }

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['data' => $package]);
}

function getPackageBySlug($req, $res) {
    $slug = $req['params']['slug'] ?? null;

    if (!$slug) {
        throw new ApiError(400, 'Package slug is required');
    }

    $packageModel = new Package();
    $package = $packageModel->findBySlug($slug);

    if (!$package) {
        throw new ApiError(404, 'Package not found');
    }

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['data' => $package]);
}

function createPackage($req, $res) {
    // Get parsed body data from router, or parse it here
    $data = $req['bodyData'] ?? [];
    
    // If bodyData is empty, try parsing body directly
    if (empty($data)) {
        // Try JSON first
        $jsonData = json_decode($req['body'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
            $data = $jsonData;
        } else {
            // Try parsing as form data
            parse_str($req['body'], $data);
            // Also check $_POST for form data (FormData populates $_POST automatically)
            if (!empty($_POST)) {
                // Merge $_POST into $data, with $_POST taking precedence
                $data = array_merge($data, $_POST);
            }
        }
    } else {
        // If bodyData exists but $_POST also has data (FormData), merge them
        // $_POST takes precedence for FormData fields
        if (!empty($_POST)) {
            $data = array_merge($data, $_POST);
        }
    }
    
    // Normalize field names - accept both camelCase and snake_case
    if (empty($data)) {
        error_log("Package creation failed: Empty request body.");
        error_log("Raw body (first 500 chars): " . substr($req['body'] ?? '', 0, 500));
        error_log("Content-Type: " . ($req['headers']['content-type'] ?? 'not set'));
        error_log("POST data: " . json_encode($_POST));
        throw new ApiError(400, 'Request body is required');
    }
    
    // Log received data for debugging
    error_log("Package creation - Received data keys: " . implode(', ', array_keys($data)));
    error_log("Package creation - POST data: " . json_encode($_POST));
    if (isset($data['highlights'])) {
        error_log("Package creation - Highlights value received (type: " . gettype($data['highlights']) . "): " . (is_string($data['highlights']) ? $data['highlights'] : json_encode($data['highlights'])));
        error_log("Package creation - Highlights length: " . (is_string($data['highlights']) ? strlen($data['highlights']) : 'N/A'));
    } else {
        error_log("Package creation - Highlights NOT found in data");
        // Check $_POST directly
        if (isset($_POST['highlights'])) {
            error_log("Package creation - Highlights found in \$_POST: " . $_POST['highlights']);
            $data['highlights'] = $_POST['highlights'];
        }
    }
    
    // Accept both 'title' and 'name' fields - normalize to 'name' for database
    $packageName = null;
    if (isset($data['title']) && !empty(trim($data['title']))) {
        $packageName = trim($data['title']);
        $data['name'] = $packageName;
    } elseif (isset($data['name']) && !empty(trim($data['name']))) {
        $packageName = trim($data['name']);
    }
    
    // Also check for camelCase variations
    if (!$packageName && isset($data['packageTitle']) && !empty(trim($data['packageTitle']))) {
        $packageName = trim($data['packageTitle']);
        $data['name'] = $packageName;
    }
    
    // Get destination - check multiple possible field names
    $destination = $data['destination'] ?? $data['packageDestination'] ?? null;
    
    // Get price - check multiple possible field names and handle string conversion
    $price = null;
    if (isset($data['price'])) {
        $price = PriceHelper::toFloat($data['price']);
        if ($price <= 0) $price = null;
    } elseif (isset($data['packagePrice'])) {
        $price = PriceHelper::toFloat($data['packagePrice']);
        if ($price <= 0) $price = null;
    }
    
    // Validate required fields with better error messages
    $missingFields = [];
    if (!$packageName) {
        $missingFields[] = 'title (or name)';
    }
    if (!$destination) {
        $missingFields[] = 'destination';
    }
    if ($price === null || $price === '') {
        $missingFields[] = 'price';
    }
    
    if (!empty($missingFields)) {
        error_log("Package creation failed: Missing fields - " . implode(', ', $missingFields));
        error_log("Received data keys: " . implode(', ', array_keys($data)));
        error_log("Sample data: " . json_encode(array_slice($data, 0, 5)));
        throw new ApiError(400, 'Missing required fields: ' . implode(', ', $missingFields));
    }
    
    // Normalize destination and price in data array
    $data['destination'] = trim($destination);
    $data['price'] = $price;

    $packageModel = new Package();
    $data['created_at'] = date('Y-m-d H:i:s');
    $data['updated_at'] = date('Y-m-d H:i:s');
    
    // Generate slug from the package name
    $data['slug'] = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $packageName)));
    
    // Map frontend-only fields to database columns
    $fieldMapping = [
        'title' => 'name',
        'tourName' => 'name',
        'pricing' => 'price',
        'offerPrice' => 'offer_price',
        'startDate' => 'start_date',
        'endDate' => 'end_date',
        'durationDays' => 'duration_days',
        'durationNights' => 'duration_nights',
        'maxParticipants' => 'total_people_allotted',
        'totalPeople' => 'total_people_allotted',
        'minAge' => 'min_age',
        'zipCode' => 'zip_code',
        'address1' => 'address1',
        'location' => 'city',
        'faqs' => 'faq',
        'included' => 'includes',
        'excluded' => 'excludes',
    ];
    
    foreach ($fieldMapping as $source => $target) {
        if (isset($data[$source]) && !isset($data[$target])) {
            $data[$target] = $data[$source];
        }
    }
    
    if (isset($data['maxParticipants'])) {
        $data['total_people_allotted'] = (int)$data['maxParticipants'];
    }
    if (isset($data['total_people_allotted'])) {
        $data['total_people_allotted'] = (int)$data['total_people_allotted'];
    }
    if (isset($data['duration_days'])) {
        $data['duration_days'] = (int)$data['duration_days'];
    }
    if (isset($data['duration_nights'])) {
        $data['duration_nights'] = (int)$data['duration_nights'];
    }
    if (isset($data['min_age'])) {
        $data['min_age'] = (int)$data['min_age'];
    }
    if (isset($data['price'])) {
        $data['price'] = PriceHelper::formatForJson($data['price']);
    }
    if (isset($data['offer_price'])) {
        $data['offer_price'] = PriceHelper::formatForJson($data['offer_price']);
    }
    if (isset($data['rating'])) {
        $data['rating'] = (float)$data['rating'];
    }
    if (isset($data['is_featured'])) {
        $data['is_featured'] = filter_var($data['is_featured'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }
    
    if (isset($data['location']) && empty($data['city'])) {
        $data['city'] = $data['location'];
    }
    if (isset($data['isFeatured'])) {
        $data['is_featured'] = filter_var($data['isFeatured'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }
    
    // Normalize structured fields to JSON
    // Always ensure these fields are set (even if empty array) to avoid NULL in database
    $jsonFields = ['highlights', 'activities', 'includes', 'excludes', 'itinerary', 'faq', 'images'];
    foreach ($jsonFields as $jsonField) {
        // Initialize as empty array if not set
        if (!isset($data[$jsonField])) {
            $data[$jsonField] = json_encode([]);
            continue;
        }
        
        $value = $data[$jsonField];
        
        // Log the raw value for debugging
        error_log("Processing {$jsonField}: " . (is_string($value) ? substr($value, 0, 100) : gettype($value)));
        
        // If it's already an array, use it directly
        if (is_array($value)) {
            // Filter out empty strings from arrays (especially for highlights)
            $filtered = array_filter($value, function($item) {
                return is_string($item) ? trim($item) !== '' : !empty($item);
            });
            $filtered = array_values($filtered); // Re-index array
            // Always store as JSON, even if empty
            $data[$jsonField] = json_encode($filtered);
            error_log("{$jsonField} (array) - filtered count: " . count($filtered) . ", result: " . $data[$jsonField]);
        } 
        // If it's a string, try to decode it as JSON
        elseif (is_string($value)) {
            // Trim the value first
            $value = trim($value);
            
            // If it's completely empty or null, set to empty array
            if ($value === '' || $value === 'null' || strtolower($value) === 'null') {
                error_log("{$jsonField} - Empty or null value, setting to empty array");
                $data[$jsonField] = json_encode([]);
                continue;
            }
            
            // If it's already an empty array string, keep it
            if ($value === '[]') {
                $data[$jsonField] = json_encode([]);
                error_log("{$jsonField} - Already empty array, keeping as is");
                continue;
            }
            
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                error_log("{$jsonField} - Successfully decoded JSON. Original array: " . json_encode($decoded));
                // Filter out empty strings from decoded arrays
                $filtered = array_filter($decoded, function($item) {
                    if (is_string($item)) {
                        $trimmed = trim($item);
                        $keep = $trimmed !== '';
                        if (!$keep) {
                            error_log("Filtering out empty string item: '" . $item . "'");
                        }
                        return $keep;
                    }
                    return !empty($item);
                });
                $filtered = array_values($filtered); // Re-index array
                error_log("{$jsonField} - After filtering. Original count: " . count($decoded) . ", Filtered count: " . count($filtered));
                if (count($decoded) > 0 && count($filtered) === 0) {
                    error_log("WARNING: {$jsonField} - All items were filtered out! Original: " . json_encode($decoded));
                }
                // Always store as JSON, even if empty after filtering
                $data[$jsonField] = json_encode($filtered);
                error_log("{$jsonField} (JSON string) - original count: " . count($decoded) . ", filtered count: " . count($filtered) . ", result: " . $data[$jsonField]);
            } else {
                // If it's not valid JSON, log the error
                error_log("{$jsonField} - JSON decode failed: " . json_last_error_msg() . ", value: " . substr($value, 0, 100));
                // If it's a non-empty string that's not JSON, treat it as a single item array
                if ($value !== '') {
                    $data[$jsonField] = json_encode([$value]);
                    error_log("{$jsonField} - Treated as single item array: " . $data[$jsonField]);
                } else {
                    // Set to empty array instead of unsetting
                    $data[$jsonField] = json_encode([]);
                    error_log("{$jsonField} - Invalid/empty value, setting to empty array");
                }
            }
        } else {
            // For any other type, set to empty array
            $data[$jsonField] = json_encode([]);
            error_log("{$jsonField} - Unexpected type: " . gettype($value) . ", setting to empty array");
        }
    }
    
    // Only keep columns that actually exist in the packages table
    $allowedFields = [
        'name',
        'category',
        'start_date',
        'end_date',
        'destination',
        'duration_days',
        'duration_nights',
        'total_people_allotted',
        'price',
        'offer_price',
        'min_age',
        'country',
        'city',
        'state',
        'zip_code',
        'address',
        'address1',
        'highlights',
        'activities',
        'includes',
        'excludes',
        'itinerary',
        'faq',
        'feature_image',
        'images',
        'description',
        'duration',
        'rating',
        'slug',
        'is_featured',
        'created_at',
        'updated_at'
    ];
    
    // Handle file uploads (feature image + gallery)
    $uploadDir = __DIR__ . '/../../public/uploads/packages';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Feature image
    if (!empty($_FILES['featureImage']['name'])) {
        $featureFile = $_FILES['featureImage'];
        $extension = pathinfo($featureFile['name'], PATHINFO_EXTENSION);
        $filename = uniqid('feature_', true) . ($extension ? ".{$extension}" : '');
        $targetPath = $uploadDir . '/' . $filename;
        
        if (saveUploadedFileOrTemp($featureFile['tmp_name'], $targetPath)) {
            // Store relative path
            $data['feature_image'] = '/uploads/packages/' . $filename;
        }
    } elseif (!empty($data['featureImageName']) && !isset($data['feature_image'])) {
        $data['feature_image'] = $data['featureImageName'];
    }
    
    // Gallery images
    if (!empty($_FILES['galleryImages']['name'])) {
        $galleryPaths = [];
        $galleryFiles = $_FILES['galleryImages'];
        
        if (is_array($galleryFiles['name'])) {
            $fileCount = count($galleryFiles['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                if (empty($galleryFiles['name'][$i])) continue;
                
                $extension = pathinfo($galleryFiles['name'][$i], PATHINFO_EXTENSION);
                $filename = uniqid('gallery_', true) . ($extension ? ".{$extension}" : '');
                $targetPath = $uploadDir . '/' . $filename;
                
                if (saveUploadedFileOrTemp($galleryFiles['tmp_name'][$i], $targetPath)) {
                    $galleryPaths[] = '/uploads/packages/' . $filename;
                }
            }
        } else {
            // Single file input fallback
            $extension = pathinfo($galleryFiles['name'], PATHINFO_EXTENSION);
            $filename = uniqid('gallery_', true) . ($extension ? ".{$extension}" : '');
            $targetPath = $uploadDir . '/' . $filename;
            
            if (move_uploaded_file($galleryFiles['tmp_name'], $targetPath)) {
                $galleryPaths[] = '/uploads/packages/' . $filename;
            }
        }
        
        if (!empty($galleryPaths)) {
            $data['images'] = json_encode($galleryPaths);
        }
    }
    
    $packageData = [];
    foreach ($allowedFields as $field) {
        if (array_key_exists($field, $data)) {
            $packageData[$field] = $data[$field];
        }
    }
    
    $id = $packageModel->createPackage($packageData);
    $package = $packageModel->getById($id);

    http_response_code(201);
    header('Content-Type: application/json');
    echo json_encode(['data' => $package]);
}

function updatePackage($req, $res) {
    error_log("updatePackage - FUNCTION CALLED");
    error_log("updatePackage - Request method: " . ($req['method'] ?? 'unknown'));
    error_log("updatePackage - Request URI: " . ($req['uri'] ?? 'unknown'));
    
    $id = $req['params']['id'] ?? null;
    error_log("updatePackage - Package ID: " . ($id ?? 'null'));
    
    if (!$id) {
        throw new ApiError(400, 'Package ID is required');
    }

    $packageModel = new Package();
    $package = $packageModel->getById($id);

    if (!$package) {
        error_log("updatePackage - Package not found for ID: " . $id);
        throw new ApiError(404, 'Package not found');
    }
    
    error_log("updatePackage - Package found: " . $package['name']);

    // Check if this is a multipart/form-data request (file upload)
    // For PUT requests, $_FILES might be empty even with multipart data
    $contentType = $req['headers']['content-type'] ?? $_SERVER['CONTENT_TYPE'] ?? '';
    $isMultipart = !empty($_FILES) || strpos($contentType, 'multipart/form-data') !== false;
    
    // Log file detection
    error_log("updatePackage - Content-Type: " . $contentType);
    error_log("updatePackage - _FILES keys: " . json_encode(array_keys($_FILES)));
    error_log("updatePackage - isMultipart: " . ($isMultipart ? 'true' : 'false'));
    
    // Get parsed body data from router, or parse it here
    $data = $req['bodyData'] ?? [];
    
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
        // Also try reading php://input directly if req['body'] is empty
        $rawBody = $req['body'] ?? '';
        if (empty($rawBody)) {
            $rawBody = file_get_contents('php://input');
        }
        
        if (empty($data) && !empty($rawBody)) {
            $contentType = $req['headers']['content-type'] ?? $_SERVER['CONTENT_TYPE'] ?? '';
            error_log("updatePackage - Attempting to parse multipart data. Content-Type: " . $contentType);
            error_log("updatePackage - Body length: " . strlen($rawBody));
            
            if (preg_match('/boundary=([^;]+)/i', $contentType, $matches)) {
                $boundary = trim($matches[1], " \t\n\r\0\x0B\"'");
                error_log("updatePackage - Extracted boundary: " . $boundary);
                
                // Parse multipart data and extract files
                $extractedFiles = [];
                $parsed = parseMultipartFormDataForPackage($rawBody, $boundary, $extractedFiles);
                error_log("updatePackage - Parsed " . count($parsed) . " fields: " . implode(', ', array_keys($parsed)));
                error_log("updatePackage - Extracted " . count($extractedFiles) . " files: " . implode(', ', array_keys($extractedFiles)));
                
                // Merge extracted files into $_FILES if $_FILES is empty (PUT request)
                if (empty($_FILES) && !empty($extractedFiles)) {
                    foreach ($extractedFiles as $key => $file) {
                        $_FILES[$key] = $file;
                    }
                    error_log("updatePackage - Populated _FILES with extracted files");
                }
                
                if (!empty($parsed)) {
                    $data = array_merge($data, $parsed);
                    error_log("updatePackage - Data after merge: " . count($data) . " fields");
                } else {
                    error_log("updatePackage - Parsing returned empty array");
                }
            } else {
                error_log("updatePackage - No boundary found in Content-Type");
            }
        }
        
        // If data is still empty but we have files, that's okay - we might only be updating images
        // But we should have at least some data (like title/name) for a valid update
        if (empty($data)) {
            error_log("updatePackage - Warning: FormData detected but no form fields found.");
            error_log("updatePackage - POST data: " . json_encode($_POST));
            error_log("updatePackage - bodyData: " . json_encode($req['bodyData'] ?? []));
            error_log("updatePackage - Content-Type: " . ($req['headers']['content-type'] ?? 'not set'));
            error_log("updatePackage - Files: " . json_encode(array_keys($_FILES)));
            // Don't throw error yet - maybe we're only updating files, check later
        }
    } elseif (empty($data)) {
        // Try JSON first
        $jsonData = json_decode($req['body'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
            $data = $jsonData;
        } else {
            // Try parsing as form data
            parse_str($req['body'], $data);
            // Also check $_POST for form data
            if (!empty($_POST)) {
                // Merge $_POST into $data, with $_POST taking precedence
                $data = array_merge($data, $_POST);
            }
        }
    } else {
        // If bodyData exists but $_POST also has data (FormData), merge them
        // $_POST takes precedence for FormData fields
        if (!empty($_POST)) {
            $data = array_merge($data, $_POST);
        }
    }

    // If we have files but no form data, that's okay - we might be doing an image-only update
    // But we should still have some basic data. If completely empty and no files, that's an error.
    if (empty($data) && !$isMultipart) {
        throw new ApiError(400, 'Request body is required');
    }
    
    // If we have files but no form data, log a warning but continue
    // The update will only affect the files, which is valid
    if (empty($data) && $isMultipart) {
        error_log("updatePackage - Warning: Files detected but no form data parsed. This might be an image-only update.");
        $data = []; // Set to empty array, we'll only update files
    }

    // Accept both 'title' and 'name' fields - normalize to 'name' for database
    if (isset($data['title']) && !empty(trim($data['title']))) {
        $data['name'] = trim($data['title']);
        unset($data['title']); // Remove title to avoid database error
        // Regenerate slug if title/name is being updated
        if (isset($data['name'])) {
            $data['slug'] = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $data['name'])));
        }
    } elseif (isset($data['name']) && !empty(trim($data['name']))) {
        // Regenerate slug if name is being updated
        $data['slug'] = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $data['name'])));
    }

    // Map frontend-only fields to database columns (same as createPackage)
    $fieldMapping = [
        'title' => 'name',
        'tourName' => 'name',
        'pricing' => 'price',
        'offerPrice' => 'offer_price',
        'startDate' => 'start_date',
        'endDate' => 'end_date',
        'durationDays' => 'duration_days',
        'durationNights' => 'duration_nights',
        'maxParticipants' => 'total_people_allotted',
        'totalPeople' => 'total_people_allotted',
        'minAge' => 'min_age',
        'zipCode' => 'zip_code',
        'address1' => 'address1',
        'location' => 'city',
        'faqs' => 'faq',
        'included' => 'includes',
        'excluded' => 'excludes',
    ];
    
    foreach ($fieldMapping as $source => $target) {
        if (isset($data[$source]) && !isset($data[$target])) {
            $data[$target] = $data[$source];
            unset($data[$source]); // Remove source field
        }
    }

    // Normalize numeric fields
    if (isset($data['total_people_allotted'])) {
        $data['total_people_allotted'] = (int)$data['total_people_allotted'];
    }
    if (isset($data['duration_days'])) {
        $data['duration_days'] = (int)$data['duration_days'];
    }
    if (isset($data['duration_nights'])) {
        $data['duration_nights'] = (int)$data['duration_nights'];
    }
    if (isset($data['min_age'])) {
        $data['min_age'] = (int)$data['min_age'];
    }
    if (isset($data['price'])) {
        $data['price'] = PriceHelper::formatForJson($data['price']);
    }
    if (isset($data['offer_price'])) {
        $data['offer_price'] = PriceHelper::formatForJson($data['offer_price']);
    }

    // Normalize structured fields to JSON (same as createPackage)
    $jsonFields = ['highlights', 'activities', 'includes', 'excludes', 'itinerary', 'faq', 'images'];
    foreach ($jsonFields as $jsonField) {
        if (!isset($data[$jsonField])) {
            continue; // Don't set empty arrays on update if field is not provided
        }
        
        $value = $data[$jsonField];
        
        // If it's already an array, use it directly
        if (is_array($value)) {
            // Filter out empty strings from arrays
            $filtered = array_filter($value, function($item) {
                return is_string($item) ? trim($item) !== '' : !empty($item);
            });
            $filtered = array_values($filtered); // Re-index array
            $data[$jsonField] = json_encode($filtered);
        } 
        // If it's a string, try to decode it as JSON
        elseif (is_string($value)) {
            $value = trim($value);
            
            if ($value === '' || $value === 'null' || strtolower($value) === 'null') {
                $data[$jsonField] = json_encode([]);
                continue;
            }
            
            if ($value === '[]') {
                $data[$jsonField] = json_encode([]);
                continue;
            }
            
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                // Filter out empty strings from decoded arrays
                $filtered = array_filter($decoded, function($item) {
                    if (is_string($item)) {
                        $trimmed = trim($item);
                        return $trimmed !== '';
                    }
                    return !empty($item);
                });
                $filtered = array_values($filtered); // Re-index array
                $data[$jsonField] = json_encode($filtered);
            } else {
                // If it's not valid JSON, treat it as a single item array
                if ($value !== '') {
                    $data[$jsonField] = json_encode([$value]);
                } else {
                    $data[$jsonField] = json_encode([]);
                }
            }
        }
    }

    // Only keep columns that actually exist in the packages table
    $allowedFields = [
        'name',
        'category',
        'start_date',
        'end_date',
        'destination',
        'duration_days',
        'duration_nights',
        'total_people_allotted',
        'price',
        'offer_price',
        'min_age',
        'country',
        'city',
        'state',
        'zip_code',
        'address',
        'address1',
        'highlights',
        'activities',
        'includes',
        'excludes',
        'itinerary',
        'faq',
        'feature_image',
        'images',
        'description',
        'duration',
        'rating',
        'slug',
        'is_featured',
        'updated_at'
    ];

    // Handle file uploads (feature image + gallery)
    $uploadDir = __DIR__ . '/../../public/uploads/packages';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Feature image - replace if new one is uploaded
    // Check if featureImage exists in $_FILES
    $hasFeatureImage = isset($_FILES['featureImage']) && 
                       !empty($_FILES['featureImage']['name']) && 
                       !empty($_FILES['featureImage']['tmp_name']);
    
    error_log("updatePackage - hasFeatureImage: " . ($hasFeatureImage ? 'true' : 'false'));
    if ($hasFeatureImage) {
        error_log("updatePackage - Feature image name: " . $_FILES['featureImage']['name']);
        error_log("updatePackage - Feature image tmp_name: " . $_FILES['featureImage']['tmp_name']);
    }
    
    if ($hasFeatureImage) {
        $featureFile = $_FILES['featureImage'];
        $extension = pathinfo($featureFile['name'], PATHINFO_EXTENSION);
        $filename = uniqid('feature_', true) . ($extension ? ".{$extension}" : '');
        $targetPath = $uploadDir . '/' . $filename;
        
        // Use helper function that handles both uploaded files and manually extracted files
        if (saveUploadedFileOrTemp($featureFile['tmp_name'], $targetPath)) {
            // Delete old feature image if it exists
            if (!empty($package['feature_image'])) {
                $oldImagePath = __DIR__ . '/../../public' . $package['feature_image'];
                if (file_exists($oldImagePath) && is_file($oldImagePath)) {
                    @unlink($oldImagePath);
                    error_log("updatePackage - Deleted old feature image: " . $oldImagePath);
                }
            }
            // Store relative path
            $data['feature_image'] = '/uploads/packages/' . $filename;
            error_log("updatePackage - New feature image saved: " . $data['feature_image']);
        } else {
            error_log("updatePackage - Failed to move feature image to: " . $targetPath);
        }
    } elseif (!empty($data['featureImageName']) && !isset($data['feature_image'])) {
        // Keep existing feature image if no new one is uploaded
        $data['feature_image'] = $data['featureImageName'];
    }
    
    // Gallery images - replace if new ones are uploaded
    // Check if galleryImages exists in $_FILES and has content
    $hasGalleryImages = false;
    if (isset($_FILES['galleryImages'])) {
        $galleryFiles = $_FILES['galleryImages'];
        if (is_array($galleryFiles['name'])) {
            // Check if any file in the array has a name
            foreach ($galleryFiles['name'] as $name) {
                if (!empty($name)) {
                    $hasGalleryImages = true;
                    break;
                }
            }
        } elseif (!empty($galleryFiles['name'])) {
            $hasGalleryImages = true;
        }
    }
    
    if ($hasGalleryImages) {
        $galleryPaths = [];
        $galleryFiles = $_FILES['galleryImages'];
        
        if (is_array($galleryFiles['name'])) {
            $fileCount = count($galleryFiles['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                if (empty($galleryFiles['name'][$i]) || empty($galleryFiles['tmp_name'][$i])) continue;
                
                $extension = pathinfo($galleryFiles['name'][$i], PATHINFO_EXTENSION);
                $filename = uniqid('gallery_', true) . ($extension ? ".{$extension}" : '');
                $targetPath = $uploadDir . '/' . $filename;
                
                // Use helper function that handles both uploaded files and manually extracted files
                if (saveUploadedFileOrTemp($galleryFiles['tmp_name'][$i], $targetPath)) {
                    $galleryPaths[] = '/uploads/packages/' . $filename;
                    error_log("updatePackage - Uploaded gallery image: " . $filename);
                } else {
                    error_log("updatePackage - Failed to move gallery image: " . $galleryFiles['name'][$i]);
                }
            }
        } else {
            // Single file input fallback
            if (!empty($galleryFiles['tmp_name'])) {
                $extension = pathinfo($galleryFiles['name'], PATHINFO_EXTENSION);
                $filename = uniqid('gallery_', true) . ($extension ? ".{$extension}" : '');
                $targetPath = $uploadDir . '/' . $filename;
                
                if (saveUploadedFileOrTemp($galleryFiles['tmp_name'], $targetPath)) {
                    $galleryPaths[] = '/uploads/packages/' . $filename;
                    error_log("updatePackage - Uploaded gallery image: " . $filename);
                } else {
                    error_log("updatePackage - Failed to move gallery image: " . $galleryFiles['name']);
                }
            }
        }
        
        if (!empty($galleryPaths)) {
            // Delete old gallery images if they exist
            if (!empty($package['images'])) {
                $existingImages = json_decode($package['images'], true) ?? [];
                foreach ($existingImages as $oldImagePath) {
                    $fullOldPath = __DIR__ . '/../../public' . $oldImagePath;
                    if (file_exists($fullOldPath) && is_file($fullOldPath)) {
                        @unlink($fullOldPath);
                        error_log("updatePackage - Deleted old gallery image: " . $fullOldPath);
                    }
                }
            }
            // Replace with new images (don't merge)
            $data['images'] = json_encode($galleryPaths);
            error_log("updatePackage - New gallery images saved: " . count($galleryPaths) . " images");
        } else {
            error_log("updatePackage - Warning: Gallery images detected but none were successfully uploaded");
        }
    } else {
        // If no new gallery images are uploaded, preserve existing ones
        // Don't set $data['images'] so existing images remain unchanged
        error_log("updatePackage - No new gallery images uploaded, preserving existing images");
    }
    
    // Filter to only allowed fields
    $packageData = [];
    foreach ($allowedFields as $field) {
        if (array_key_exists($field, $data)) {
            $packageData[$field] = $data[$field];
        }
    }

    // Always set updated_at
    $packageData['updated_at'] = date('Y-m-d H:i:s');
    
    // Log what we're about to update
    error_log("updatePackage - About to update with " . count($packageData) . " fields: " . implode(', ', array_keys($packageData)));
    error_log("updatePackage - Files to process: " . json_encode(array_keys($_FILES)));
    error_log("updatePackage - Data keys before filtering: " . implode(', ', array_keys($data)));
    if (isset($data['feature_image'])) {
        error_log("updatePackage - feature_image in data: " . $data['feature_image']);
    } else {
        error_log("updatePackage - feature_image NOT in data");
    }
    if (isset($data['images'])) {
        error_log("updatePackage - images in data: " . (is_string($data['images']) ? substr($data['images'], 0, 100) : json_encode($data['images'])));
    } else {
        error_log("updatePackage - images NOT in data");
    }
    if (isset($packageData['feature_image'])) {
        error_log("updatePackage - feature_image in packageData: " . $packageData['feature_image']);
    } else {
        error_log("updatePackage - feature_image NOT in packageData");
    }
    if (isset($packageData['images'])) {
        error_log("updatePackage - images in packageData: " . (is_string($packageData['images']) ? substr($packageData['images'], 0, 100) : json_encode($packageData['images'])));
    } else {
        error_log("updatePackage - images NOT in packageData");
    }
    error_log("updatePackage - Data keys before filtering: " . implode(', ', array_keys($data)));
    if (isset($data['feature_image'])) {
        error_log("updatePackage - feature_image in data: " . $data['feature_image']);
    }
    if (isset($data['images'])) {
        error_log("updatePackage - images in data: " . (is_string($data['images']) ? substr($data['images'], 0, 100) : json_encode($data['images'])));
    }
    
    // The update method requires at least one field (we have updated_at, so this should always work)
    // But if we have files, we should have image paths in $data by now
    if (!empty($packageData)) {
        error_log("updatePackage - Calling updatePackage with " . count($packageData) . " fields");
        error_log("updatePackage - packageData contents: " . json_encode($packageData));
        try {
            $result = $packageModel->updatePackage($id, $packageData);
            error_log("updatePackage - Database update result: " . ($result ? 'success' : 'failed'));
        } catch (Exception $e) {
            error_log("updatePackage - Database update exception: " . $e->getMessage());
            throw $e;
        }
    } else {
        error_log("updatePackage - Error: No data to update (not even updated_at)");
        throw new ApiError(400, 'No data provided for update');
    }
    $updatedPackage = $packageModel->getById($id);
    error_log("updatePackage - Updated package retrieved. Feature image: " . ($updatedPackage['feature_image'] ?? 'null') . ", Images: " . ($updatedPackage['images'] ?? 'null'));

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['data' => $updatedPackage]);
}

function deletePackage($req, $res) {
    $id = $req['params']['id'] ?? null;
    
    if (!$id) {
        throw new ApiError(400, 'Package ID is required');
    }

    $packageModel = new Package();
    $package = $packageModel->getById($id);

    if (!$package) {
        throw new ApiError(404, 'Package not found');
    }

    $packageModel->deletePackage($id);

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Package deleted successfully']);
}

function archivePackage($req, $res) {
    $id = $req['params']['id'] ?? null;
    
    if (!$id) {
        throw new ApiError(400, 'Package ID is required');
    }

    $packageModel = new Package();
    $package = $packageModel->getById($id);

    if (!$package) {
        throw new ApiError(404, 'Package not found');
    }

    // Update the package to set is_archived = 1
    $result = $packageModel->updatePackage($id, ['is_archived' => 1]);
    
    // Verify the update
    $updatedPackage = $packageModel->getById($id);
    error_log("archivePackage - Package ID: $id, is_archived after update: " . ($updatedPackage['is_archived'] ?? 'not set'));

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Package archived successfully']);
}

function unarchivePackage($req, $res) {
    $id = $req['params']['id'] ?? null;
    
    if (!$id) {
        throw new ApiError(400, 'Package ID is required');
    }

    $packageModel = new Package();
    $package = $packageModel->getById($id);

    if (!$package) {
        throw new ApiError(404, 'Package not found');
    }

    $packageModel->updatePackage($id, ['is_archived' => 0]);

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Package unarchived successfully']);
}

