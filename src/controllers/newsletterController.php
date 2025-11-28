<?php

require_once __DIR__ . '/../models/Newsletter.php';
require_once __DIR__ . '/../utils/ApiError.php';
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../../vendor/autoload.php';

function subscribe($req, $res) {
    $data = json_decode($req['body'], true);
    $email = $data['email'] ?? null;

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new ApiError(400, 'Valid email is required');
    }

    $newsletterModel = new Newsletter();
    $existing = $newsletterModel->findByEmail($email);

    if ($existing) {
        throw new ApiError(409, 'Email already subscribed');
    }

    $newsletterData = [
        'email' => strtolower(trim($email)),
        'created_at' => date('Y-m-d H:i:s'),
    ];

    $id = $newsletterModel->createSubscription($newsletterData);
    $subscription = $newsletterModel->getById($id);

    // Try to send the welcome email, but do not block subscription if it fails
    try {
        sendNewsletterWelcomeEmail($subscription['email']);
    } catch (\Throwable $mailError) {
        error_log("Newsletter welcome email failed: " . $mailError->getMessage());
    }

    http_response_code(201);
    header('Content-Type: application/json');
    echo json_encode(['data' => $subscription, 'message' => 'Subscribed successfully']);
}

function sendNewsletterWelcomeEmail($recipientEmail) {
    $emailConfig = Env::get('email');

    if (empty($emailConfig['host']) || empty($emailConfig['user']) || empty($emailConfig['pass'])) {
        throw new Exception('Email service is not configured.');
    }

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    // Server settings
    $mail->isSMTP();
    $mail->Host = $emailConfig['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $emailConfig['user'];
    $mail->Password = $emailConfig['pass'];
    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $emailConfig['port'] ?? 587;
    $mail->CharSet = 'UTF-8';

    $fromAddress = $emailConfig['from'] ?? 'hello@citymuscattours.com';
    $fromName = 'City Muscat Tours';

    // Recipients
    $mail->setFrom($fromAddress, $fromName);
    $mail->addAddress($recipientEmail);

    $mail->isHTML(true);
    $mail->Subject = 'Welcome to City Muscat Tours – Your Weekly Travel Inspiration';

    $currentYear = date('Y');

    $mail->Body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f5f7fb; margin: 0; padding: 0; color: #1f2937; }
            .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 18px; overflow: hidden; box-shadow: 0 20px 45px rgba(15,23,42,0.12); }
            .header { background: linear-gradient(135deg, #0f172a, #1d4ed8); padding: 40px 30px; color: #fff; text-align: center; }
            .header h1 { margin: 0; font-size: 26px; }
            .content { padding: 32px; line-height: 1.7; }
            .badge { display: inline-block; padding: 8px 18px; border-radius: 999px; background: #eef2ff; color: #4338ca; font-weight: 600; font-size: 13px; }
            .highlight { background: #fef3c7; padding: 14px 18px; border-radius: 14px; margin: 18px 0; color: #92400e; }
            .cta { display: inline-block; background: #1d4ed8; color: #fff; padding: 14px 24px; border-radius: 999px; text-decoration: none; font-weight: 600; margin-top: 24px; }
            .footer { padding: 20px 32px 32px; font-size: 13px; color: #94a3b8; text-align: center; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Welcome to City Muscat Tours</h1>
                <p style='margin-top:12px; font-size:15px;'>Discover the wonder of travel, every single week.</p>
            </div>
            <div class='content'>
                <span class='badge'>You're on the list!</span>
                <p>Thank you for signing up to our insider dispatch. Every week, you'll receive curated inspiration from Muscat's most magical destinations, private experiences, and luxury transfers designed for modern travelers.</p>
                <div class='highlight'>
                    <strong>What to expect:</strong><br/>
                    • Flash deals on premium tours & concierge services<br/>
                    • First access to new car rentals & bespoke transfers<br/>
                    • Seasonal inspiration straight from our Muscat experts
                </div>
                <p>We're thrilled to be part of your journey. If you're ready to start exploring now, our travel designers are just a tap away.</p>
                <a class='cta' href='https://citymuscattours.com' target='_blank'>Start exploring</a>
                <p style='margin-top:32px;'>Warm regards,<br/><strong>The City Muscat Tours Concierge Team</strong></p>
            </div>
            <div class='footer'>
                &copy; {$currentYear} City Muscat Tours. Al Khuwair, Muscat, Oman<br/>
                +968 9949 8697 · hello@citymuscattours.com
            </div>
        </div>
    </body>
    </html>
    ";

    $mail->AltBody = "Thank you for subscribing to City Muscat Tours. Expect weekly inspiration, flash offers, and concierge-only perks. Let's create unforgettable journeys together!";

    $mail->send();
}

