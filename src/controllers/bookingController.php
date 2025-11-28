<?php

require_once __DIR__ . '/../models/Booking.php';
require_once __DIR__ . '/../models/Package.php';
require_once __DIR__ . '/../models/PackageVariant.php';
require_once __DIR__ . '/../utils/ApiError.php';
require_once __DIR__ . '/../utils/PriceHelper.php';

function createBooking($req, $res) {
    $data = json_decode($req['body'], true);
    $userId = $req['user']['sub'] ?? null;

    if (!$userId) {
        throw new ApiError(401, 'Authentication required');
    }

    $packageId = $data['packageId'] ?? null;
    $date = $data['date'] ?? null;
    $adults = (int)($data['adults'] ?? 1);
    $children = (int)($data['children'] ?? 0);
    $totalAmount = $data['totalAmount'] ?? null;

    if (!$packageId || !$date || !$totalAmount) {
        throw new ApiError(400, 'Package ID, date, and total amount are required');
    }

    $packageModel = new Package();
    $package = $packageModel->getById($packageId);
    
    if (!$package) {
        throw new ApiError(404, 'Package not found');
    }

    $bookingModel = new Booking();
    $bookingData = [
        'user_id' => $userId,
        'package_id' => $packageId,
        'date' => $date,
        'adults' => $adults,
        'children' => $children,
        'travelers' => $adults + $children,
        'total_amount' => $totalAmount,
        'currency' => $data['currency'] ?? 'INR',
        'status' => 'Pending',
        'payment_status' => 'pending',
        'contact_email' => $data['contactEmail'] ?? null,
        'contact_phone' => $data['contactPhone'] ?? null,
        'pickup_location' => $data['pickupLocation'] ?? null,
        'notes' => $data['notes'] ?? null,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    $bookingId = $bookingModel->createBooking($bookingData);
    $booking = $bookingModel->findWithPackage($bookingId);

    http_response_code(201);
    header('Content-Type: application/json');
    echo json_encode(['data' => $booking]);
}

function createDummyBooking($req, $res) {
    // Allow both admin and public access for dummy bookings
    $userRole = $req['user']['role'] ?? null;
    $userId = $req['user']['sub'] ?? null;
    
    $data = json_decode($req['body'], true);
    
    // Get package ID - required for public bookings
    $packageId = $data['packageId'] ?? null;
    if (!$packageId) {
        // For admin, allow random package selection
        if ($userRole === 'admin') {
            $packageModel = new Package();
            $allPackages = $packageModel->getAllPackages(10);
            if (empty($allPackages)) {
                throw new ApiError(400, 'No packages available. Please create a package first.');
            }
            $randomPackage = $allPackages[array_rand($allPackages)];
            $packageId = $randomPackage['id'];
        } else {
            throw new ApiError(400, 'Package ID is required');
        }
    }
    
    $packageModel = new Package();
    $package = $packageModel->getById($packageId);
    
    if (!$package) {
        throw new ApiError(404, 'Package not found');
    }
    
    // Use provided data or generate dummy data
    $contactEmail = $data['contactEmail'] ?? null;
    $contactPhone = $data['contactPhone'] ?? null;
    $bookingDate = $data['date'] ?? null;
    
    if (!$contactEmail || !$contactPhone || !$bookingDate) {
        // Generate dummy data if not provided (admin mode)
        $dummyNames = ['John Smith', 'Sarah Johnson', 'Michael Brown', 'Emily Davis', 'David Wilson', 'Lisa Anderson', 'Robert Taylor', 'Jennifer Martinez'];
        $dummyEmails = ['john.smith@example.com', 'sarah.j@example.com', 'michael.b@example.com', 'emily.d@example.com', 'david.w@example.com', 'lisa.a@example.com', 'robert.t@example.com', 'jennifer.m@example.com'];
        $dummyPhones = ['+1-555-0101', '+1-555-0102', '+1-555-0103', '+1-555-0104', '+1-555-0105', '+1-555-0106', '+1-555-0107', '+1-555-0108'];
        
        $randomIndex = array_rand($dummyNames);
        $travelerName = $dummyNames[$randomIndex];
        $contactEmail = $contactEmail ?? $dummyEmails[$randomIndex];
        $contactPhone = $contactPhone ?? $dummyPhones[$randomIndex];
        
        // Generate a future date (1-30 days from now) if not provided
        if (!$bookingDate) {
            $daysAhead = rand(1, 30);
            $bookingDate = date('Y-m-d', strtotime("+{$daysAhead} days"));
        }
    }
    
    // Get package price
    $packagePrice = PriceHelper::getEffectivePrice($package);
    $adults = $data['adults'] ?? rand(1, 4);
    $children = $data['children'] ?? rand(0, 2);
    $totalAmount = $data['totalAmount'] ?? ($packagePrice * ($adults + ($children * 0.5))); // Children at 50% price
    
    // Always start as Pending with pending payment
    $status = 'Pending';
    $paymentStatus = 'pending';
    
    $bookingModel = new Booking();
    $bookingData = [
        'user_id' => $userId ?? 1, // Use logged-in user ID or default
        'package_id' => $packageId,
        'date' => $bookingDate,
        'adults' => $adults,
        'children' => $children,
        'travelers' => $adults + $children,
        'total_amount' => $totalAmount,
        'currency' => $data['currency'] ?? 'INR',
        'status' => $status,
        'payment_status' => $paymentStatus,
        'contact_email' => $contactEmail,
        'contact_phone' => $contactPhone,
        'pickup_location' => $data['pickupLocation'] ?? 'Hotel pickup',
        'notes' => $data['notes'] ?? "Dummy booking",
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    
    $bookingId = $bookingModel->createBooking($bookingData);
    $booking = $bookingModel->findWithPackage($bookingId);
    
    http_response_code(201);
    header('Content-Type: application/json');
    echo json_encode(['data' => $booking, 'message' => 'Dummy booking created successfully']);
}

function createDummyPayment($req, $res) {
    // Allow both admin and public access for dummy payments
    $userRole = $req['user']['role'] ?? null;
    
    $data = json_decode($req['body'], true);
    $bookingId = $data['bookingId'] ?? null;
    
    if (!$bookingId) {
        throw new ApiError(400, 'Booking ID is required');
    }
    
    $bookingModel = new Booking();
    $booking = $bookingModel->findWithPackage($bookingId);
    
    if (!$booking) {
        throw new ApiError(404, 'Booking not found');
    }
    
    // Generate dummy payment data
    $paymentMethods = ['credit_card', 'debit_card', 'bank_transfer', 'upi'];
    $paymentMethod = $paymentMethods[array_rand($paymentMethods)];
    
    // Generate dummy transaction ID
    $transactionId = 'TXN' . strtoupper(uniqid());
    $paymentIntentId = 'pi_' . strtolower(uniqid());
    
    // Update booking with payment
    $updateData = [
        'payment_status' => 'paid',
        'status' => $data['status'] ?? 'Confirmed', // Can be Confirmed or Completed
        'payment_intent_id' => $paymentIntentId,
        'transaction_id' => $transactionId,
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    
    $bookingModel->updateBooking($bookingId, $updateData);
    $updatedBooking = $bookingModel->findWithPackage($bookingId);
    
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode([
        'data' => $updatedBooking,
        'payment' => [
            'transaction_id' => $transactionId,
            'payment_intent_id' => $paymentIntentId,
            'payment_method' => $paymentMethod,
            'amount' => $booking['total_amount'],
            'currency' => $booking['currency'] ?? 'INR',
            'status' => 'paid',
        ],
        'message' => 'Dummy payment processed successfully. Booking is now confirmed.'
    ]);
}

function getBookings($req, $res) {
    $userId = $req['user']['sub'] ?? null;
    $userRole = $req['user']['role'] ?? null;
    $query = $req['query'] ?? [];
    $page = (int)($query['page'] ?? 1);
    $limit = (int)($query['limit'] ?? 10);

    if (!$userId) {
        throw new ApiError(401, 'Authentication required');
    }

    $bookingModel = new Booking();
    
    // If user is admin, return all bookings, otherwise return only user's bookings
    if ($userRole === 'admin') {
        // For admin dashboard, allow fetching all bookings without limit
        $fetchAll = isset($query['all']) && $query['all'] === 'true';
        if ($fetchAll) {
            $bookings = $bookingModel->getAllWithoutLimit();
        } else {
            $bookings = $bookingModel->getAll($page, $limit);
        }
    } else {
    $bookings = $bookingModel->findByUser($userId, $page, $limit);
    }

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['data' => $bookings]);
}

function getBooking($req, $res) {
    $id = $req['params']['id'] ?? null;
    $userId = $req['user']['sub'] ?? null;

    if (!$id) {
        throw new ApiError(400, 'Booking ID is required');
    }

    if (!$userId) {
        throw new ApiError(401, 'Authentication required');
    }

    $bookingModel = new Booking();
    $booking = $bookingModel->findWithPackage($id);

    if (!$booking) {
        throw new ApiError(404, 'Booking not found');
    }

    if ($booking['user_id'] != $userId && $req['user']['role'] !== 'admin') {
        throw new ApiError(403, 'Insufficient permissions');
    }

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['data' => $booking]);
}

function checkBooking($req, $res) {
    $data = json_decode($req['body'], true);
    
    $packageId = $data['packageId'] ?? null;
    $date = $data['date'] ?? null;
    $adults = (int)($data['adults'] ?? 1);
    $children = (int)($data['children'] ?? 0);
    $travelers = $adults + $children;

    if (!$packageId || !$date) {
        throw new ApiError(400, 'Package ID and date are required');
    }

    if ($travelers <= 0) {
        throw new ApiError(400, 'At least one traveler is required');
    }

    // Validate date format
    $dateObj = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
        throw new ApiError(400, 'Invalid date format. Expected YYYY-MM-DD');
    }

    // Check if date is in the past
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    $dateObj->setTime(0, 0, 0);
    if ($dateObj < $today) {
        throw new ApiError(400, 'Cannot book for a past date');
    }

    $packageModel = new Package();
    $package = $packageModel->getById($packageId);
    
    if (!$package) {
        throw new ApiError(404, 'Package not found');
    }

    // Check if package is archived
    if (isset($package['is_archived']) && $package['is_archived']) {
        throw new ApiError(400, 'This package is no longer available');
    }

    // Check package date range if set
    if (!empty($package['start_date']) && !empty($package['end_date'])) {
        $startDate = DateTime::createFromFormat('Y-m-d', $package['start_date']);
        $endDate = DateTime::createFromFormat('Y-m-d', $package['end_date']);
        if ($startDate && $endDate) {
            $startDate->setTime(0, 0, 0);
            $endDate->setTime(0, 0, 0);
            if ($dateObj < $startDate || $dateObj > $endDate) {
                throw new ApiError(400, 'Selected date is outside the package availability period');
            }
        }
    }

    // Check capacity
    $bookingModel = new Booking();
    $bookedTravelers = $bookingModel->getBookedTravelersForDate($packageId, $date);
    $totalCapacity = (int)($package['total_people_allotted'] ?? 0);
    
    $available = true;
    $availableSpots = 0;
    $message = 'Available';

    if ($totalCapacity > 0) {
        $availableSpots = $totalCapacity - $bookedTravelers;
        if ($travelers > $availableSpots) {
            $available = false;
            $message = "Only {$availableSpots} spot(s) available for this date";
        }
    } else {
        // No capacity limit set, assume unlimited
        $availableSpots = -1; // -1 indicates unlimited
        $message = 'Available (unlimited capacity)';
    }

    // Calculate pricing
    $basePrice = PriceHelper::getEffectivePrice($package);
    $totalAmount = round($basePrice * $travelers, 2);
    $currency = 'INR';

    $response = [
        'available' => $available,
        'message' => $message,
        'package' => [
            'id' => $package['id'],
            'name' => $package['name'],
            'price' => PriceHelper::formatForJson($package['price'] ?? null),
            'offerPrice' => !empty($package['offer_price']) ? PriceHelper::formatForJson($package['offer_price']) : null,
        ],
        'date' => $date,
        'travelers' => [
            'adults' => $adults,
            'children' => $children,
            'total' => $travelers,
        ],
        'capacity' => [
            'total' => $totalCapacity > 0 ? $totalCapacity : null,
            'booked' => $bookedTravelers,
            'available' => $availableSpots,
            'unlimited' => $totalCapacity <= 0,
        ],
        'pricing' => [
            'basePrice' => PriceHelper::formatForJson($basePrice),
            'totalAmount' => PriceHelper::formatForJson($totalAmount),
            'currency' => $currency,
        ],
    ];

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['data' => $response]);
}

function getAvailableOptions($req, $res) {
    $data = json_decode($req['body'], true);
    
    $packageId = $data['packageId'] ?? null;
    $date = $data['date'] ?? null;
    $adults = (int)($data['adults'] ?? 1);
    $children = (int)($data['children'] ?? 0);
    $travelers = $adults + $children;

    if (!$packageId) {
        throw new ApiError(400, 'Package ID is required');
    }

    if ($date && $travelers <= 0) {
        throw new ApiError(400, 'At least one traveler is required when checking availability');
    }

    $packageModel = new Package();
    $package = $packageModel->getById($packageId);
    
    if (!$package) {
        throw new ApiError(404, 'Package not found');
    }

    // Check if package is archived
    if (isset($package['is_archived']) && $package['is_archived']) {
        throw new ApiError(400, 'This package is no longer available');
    }

    // Get package variants/options
    $variantModel = new PackageVariant();
    $variants = $variantModel->findByPackage($packageId, true);

    // If no variants exist, create a single simple booking option
    // This is for packages that don't need multiple booking options
    if (empty($variants)) {
        // Single booking option - simple and straightforward
        $variants = [
            [
                'variant_id' => 'default',
                'label' => $package['name'] ?? 'Package Booking',
                'subtitle' => 'Standard booking option',
                'language' => 'English',
                'start_time' => 'Flexible',
                'meeting_point' => $package['address1'] ?? $package['destination'] ?? 'Hotel collection available',
                'perks' => json_encode(['Free cancellation', 'Flexible payment', 'Instant confirmation']),
                'price_modifier' => 0.00,
                'rating' => $package['rating'] ?? 4.8,
                'reviews' => 0,
                'cancellation_policy' => 'Free cancellation up to 24 hours',
                'pickup_included' => true,
            ],
        ];
    }

    $basePrice = PriceHelper::getEffectivePrice($package);
    $currency = 'INR';

    // Process variants and check availability if date is provided
    $options = [];
    $availabilityData = null;

    if ($date) {
        // Validate date format
        $dateObj = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
            throw new ApiError(400, 'Invalid date format. Expected YYYY-MM-DD');
        }

        // Check if date is in the past
        $today = new DateTime();
        $today->setTime(0, 0, 0);
        $dateObj->setTime(0, 0, 0);
        if ($dateObj < $today) {
            throw new ApiError(400, 'Cannot book for a past date');
        }

        // Check package date range if set
        if (!empty($package['start_date']) && !empty($package['end_date'])) {
            $startDate = DateTime::createFromFormat('Y-m-d', $package['start_date']);
            $endDate = DateTime::createFromFormat('Y-m-d', $package['end_date']);
            if ($startDate && $endDate) {
                $startDate->setTime(0, 0, 0);
                $endDate->setTime(0, 0, 0);
                if ($dateObj < $startDate || $dateObj > $endDate) {
                    throw new ApiError(400, 'Selected date is outside the package availability period');
                }
            }
        }

        // Check capacity
        $bookingModel = new Booking();
        $bookedTravelers = $bookingModel->getBookedTravelersForDate($packageId, $date);
        $totalCapacity = (int)($package['total_people_allotted'] ?? 0);
        
        $available = true;
        $availableSpots = 0;
        $message = 'Available';

        if ($totalCapacity > 0) {
            $availableSpots = $totalCapacity - $bookedTravelers;
            if ($travelers > $availableSpots) {
                $available = false;
                $message = "Only {$availableSpots} spot(s) available for this date";
            }
        } else {
            $availableSpots = -1; // -1 indicates unlimited
            $message = 'Available (unlimited capacity)';
        }

        $availabilityData = [
            'available' => $available,
            'message' => $message,
            'capacity' => [
                'total' => $totalCapacity > 0 ? $totalCapacity : null,
                'booked' => $bookedTravelers,
                'available' => $availableSpots,
                'unlimited' => $totalCapacity <= 0,
            ],
        ];
    }

    // Build options array
    foreach ($variants as $variant) {
        $perks = [];
        if (!empty($variant['perks'])) {
            $decodedPerks = json_decode($variant['perks'], true);
            $perks = is_array($decodedPerks) ? $decodedPerks : [];
        }

        // Calculate price
        $price = $basePrice;
        if (!empty($variant['base_price_override'])) {
            $price = PriceHelper::toFloat($variant['base_price_override']);
        } elseif (!empty($variant['price_modifier'])) {
            $modifier = PriceHelper::toFloat($variant['price_modifier']);
            $price = $basePrice + ($basePrice * $modifier / 100);
            // Ensure minimum price
            if ($modifier < 0) {
                $price = max(50, $price);
            }
        }
        
        // Ensure price is properly rounded
        $price = PriceHelper::formatForJson($price);

        $option = [
            'id' => $variant['variant_id'],
            'variantId' => $variant['variant_id'],
            'label' => $variant['label'],
            'subtitle' => $variant['subtitle'] ?? '',
            'language' => $variant['language'] ?? 'English',
            'startTime' => $variant['start_time'] ?? '10:00 AM',
            'meetingPoint' => $variant['meeting_point'] ?? ($package['address1'] ?? $package['destination'] ?? ''),
            'perks' => $perks,
            'price' => $price,
            'priceLabel' => number_format($price, 2, '.', '') . ' ' . $currency,
            'rating' => (float)($variant['rating'] ?? $package['rating'] ?? 4.8),
            'reviews' => (int)($variant['reviews'] ?? 0),
            'cancellation' => $variant['cancellation_policy'] ?? 'Free cancellation up to 24 hours',
            'pickup' => (bool)($variant['pickup_included'] ?? true),
        ];

        // If date is provided, calculate total for travelers
        if ($date && $travelers > 0) {
            $option['totalAmount'] = PriceHelper::formatForJson($price * $travelers);
            $option['totalAmountLabel'] = number_format($option['totalAmount'], 2, '.', '') . ' ' . $currency;
        }

        $options[] = $option;
    }

    $response = [
        'package' => [
            'id' => $package['id'],
            'name' => $package['name'],
            'price' => PriceHelper::formatForJson($package['price'] ?? null),
            'offerPrice' => !empty($package['offer_price']) ? PriceHelper::formatForJson($package['offer_price']) : null,
        ],
        'options' => $options,
    ];

    if ($availabilityData) {
        $response['availability'] = $availabilityData;
        $response['date'] = $date;
        $response['travelers'] = [
            'adults' => $adults,
            'children' => $children,
            'total' => $travelers,
        ];
        $response['pricing'] = [
            'basePrice' => PriceHelper::formatForJson($basePrice),
            'currency' => $currency,
        ];
    }

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['data' => $response]);
}

function sendConfirmationEmail($req, $res) {
    require_once __DIR__ . '/../config/env.php';
    require_once __DIR__ . '/../../vendor/autoload.php';
    
    $data = json_decode($req['body'], true);
    
    // Validate required fields
    if (!isset($data['bookingId']) || !isset($data['recipientEmail'])) {
        throw new ApiError(400, 'Missing required fields: bookingId and recipientEmail are required');
    }
    
    $bookingId = $data['bookingId'];
    $recipientEmail = filter_var($data['recipientEmail'], FILTER_SANITIZE_EMAIL);
    $recipientName = $data['recipientName'] ?? 'Customer';
    $bookingDetails = $data['bookingDetails'] ?? [];
    
    // Validate email
    if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        throw new ApiError(400, 'Invalid email address');
    }
    
    // Get email configuration from env
    $emailConfig = Env::get('email');
    
    // Check if email is configured
    if (empty($emailConfig['host']) || empty($emailConfig['user']) || empty($emailConfig['pass'])) {
        throw new ApiError(500, 'Email service is not configured. Please configure email settings in .env file');
    }
    
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = $emailConfig['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $emailConfig['user'];
        $mail->Password = $emailConfig['pass'];
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; // Use STARTTLS by default
        $mail->Port = $emailConfig['port'] ?? 587;
        $mail->CharSet = 'UTF-8';
        
        // Enable verbose debug output (optional, for debugging)
        // $mail->SMTPDebug = 2;
        
        // Recipients
        $fromAddress = $emailConfig['from'] ?? 'Travelalshaheed2016@gmail.com';
        $fromName = 'Travel Al Shaheed';
        $mail->setFrom($fromAddress, $fromName);
        $mail->addAddress($recipientEmail, $recipientName);
        
        // Content
        $mail->isHTML(true);
        $bookingRef = $bookingDetails['bookingReference'] ?? ('#' . $bookingId);
        $mail->Subject = 'Booking Confirmation - ' . $bookingRef;
        
        // Generate email body
        $emailBody = generateBookingConfirmationEmail($bookingDetails, $recipientName);
        $mail->Body = $emailBody;
        $mail->AltBody = strip_tags($emailBody);
        
        $mail->send();
        
        // Log email sent (optional - can be extended to log to database)
        error_log("Confirmation email sent successfully to {$recipientEmail} for booking {$bookingId}");
        
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Confirmation email sent successfully'
        ]);
        
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        throw new ApiError(500, 'Failed to send email: ' . $mail->ErrorInfo);
    }
}

function generateBookingConfirmationEmail($details, $name) {
    $packageName = $details['packageName'] ?? 'Package';
    $date = $details['date'] ?? 'TBD';
    $formattedDate = $date !== 'TBD' ? date('F j, Y', strtotime($date)) : 'TBD';
    $adults = $details['adults'] ?? 0;
    $children = $details['children'] ?? 0;
    $totalAmount = $details['totalAmount'] ?? 0;
    $currency = $details['currency'] ?? 'INR';
    $bookingRef = $details['bookingReference'] ?? 'N/A';
    
    // Format amount in OMR (same value from database, no conversion)
    // Use OMR format with 3 decimal places
    $formattedAmount = 'OMR ' . number_format((float)$totalAmount, 3, '.', ',');
    
    $adultsText = $adults . ' Adult' . ($adults > 1 ? 's' : '');
    $childrenText = '';
    if ($children > 0) {
        $childrenText = ', ' . $children . ' Child' . ($children > 1 ? 'ren' : '');
    }
    $travelersText = $adultsText . $childrenText;
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #1e40af; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f9fafb; padding: 30px; border-radius: 0 0 8px 8px; }
            .booking-details { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
            .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e5e7eb; }
            .detail-row:last-child { border-bottom: none; }
            .label { font-weight: bold; color: #6b7280; }
            .value { color: #111827; }
            .footer { text-align: center; margin-top: 30px; color: #6b7280; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Booking Confirmation</h1>
            </div>
            <div class='content'>
                <p>Dear {$name},</p>
                <p>Thank you for your booking! We're excited to have you join us.</p>
                
                <div class='booking-details'>
                    <h2 style='margin-top: 0;'>Booking Details</h2>
                    <div class='detail-row'>
                        <span class='label'>Booking Reference:</span>
                        <span class='value'>{$bookingRef}</span>
                    </div>
                    <div class='detail-row'>
                        <span class='label'>Package:</span>
                        <span class='value'>{$packageName}</span>
                    </div>
                    <div class='detail-row'>
                        <span class='label'>Date:</span>
                        <span class='value'>{$formattedDate}</span>
                    </div>
                    <div class='detail-row'>
                        <span class='label'>Travelers:</span>
                        <span class='value'>{$travelersText}</span>
                    </div>
                    <div class='detail-row'>
                        <span class='label'>Total Amount:</span>
                        <span class='value'><strong>{$formattedAmount}</strong></span>
                    </div>
                </div>
                
                <p>We look forward to serving you. If you have any questions, please don't hesitate to contact us.</p>
                
                <p>Best regards,<br>Travel Al Shaheed Team</p>
            </div>
            <div class='footer'>
                <p>This is an automated confirmation email. Please do not reply to this email.</p>
                <p>Contact: +968 9949 8697 | Travelalshaheed2016@gmail.com</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

