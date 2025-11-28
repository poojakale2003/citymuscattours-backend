<?php

require_once __DIR__ . '/router.php';
require_once __DIR__ . '/../middleware/authMiddleware.php';
require_once __DIR__ . '/../controllers/authController.php';
require_once __DIR__ . '/../controllers/userController.php';
require_once __DIR__ . '/../controllers/packageController.php';
require_once __DIR__ . '/../controllers/categoryController.php';
require_once __DIR__ . '/../controllers/bookingController.php';
require_once __DIR__ . '/../controllers/reviewController.php';
require_once __DIR__ . '/../controllers/wishlistController.php';
require_once __DIR__ . '/../controllers/newsletterController.php';
require_once __DIR__ . '/../controllers/contactLeadController.php';
require_once __DIR__ . '/../controllers/variantController.php';
require_once __DIR__ . '/../controllers/blogController.php';
require_once __DIR__ . '/../controllers/testimonialController.php';
require_once __DIR__ . '/middleware.php';

function wrapMiddleware($middlewareName) {
    return function($req, $res, $next) use ($middlewareName) {
        return applyMiddleware($middlewareName, $req, $res, $next);
    };
}

$router = new Router();

// Auth routes
$router->post('/api/auth/register', 'authController::register');
$router->post('/api/auth/login', 'authController::login');
$router->post('/api/auth/refresh', 'authController::refreshToken');
$router->post('/api/auth/logout', 'authController::logout', [wrapMiddleware('authenticate')]);

// User routes
$router->get('/api/users/profile', 'userController::getProfile', [wrapMiddleware('authenticate')]);
$router->put('/api/users/profile', 'userController::updateProfile', [wrapMiddleware('authenticate')]);
$router->get('/api/users/me', 'userController::getProfile', [wrapMiddleware('authenticate')]); // Alias for frontend compatibility
$router->put('/api/users/me', 'userController::updateProfile', [wrapMiddleware('authenticate')]); // Alias for frontend compatibility

// Category routes
$router->get('/api/categories', 'categoryController::getCategories');
$router->get('/api/categories/{slug}', 'categoryController::getCategory');
$router->get('/categories', 'categoryController::getCategories'); // Alias for frontend compatibility

// Package routes
$router->get('/api/packages', 'packageController::listPackages');
$router->get('/api/packages/{id}', 'packageController::getPackage');
$router->get('/api/packages/slug/{slug}', 'packageController::getPackageBySlug');
$router->post('/api/packages', 'packageController::createPackage', [wrapMiddleware('authenticate'), wrapMiddleware('authorize:admin')]);
$router->put('/api/packages/{id}', 'packageController::updatePackage', [wrapMiddleware('authenticate'), wrapMiddleware('authorize:admin')]);
$router->delete('/api/packages/{id}', 'packageController::deletePackage', [wrapMiddleware('authenticate'), wrapMiddleware('authorize:admin')]);
$router->post('/api/packages/{id}/archive', 'packageController::archivePackage', [wrapMiddleware('authenticate'), wrapMiddleware('authorize:admin')]);
$router->post('/api/packages/{id}/unarchive', 'packageController::unarchivePackage', [wrapMiddleware('authenticate'), wrapMiddleware('authorize:admin')]);

// Package Variant routes (for admin to manage booking options)
$router->get('/api/packages/{packageId}/variants', 'variantController::getPackageVariants', [wrapMiddleware('authenticate'), wrapMiddleware('authorize:admin')]);
$router->post('/api/packages/{packageId}/variants', 'variantController::createVariant', [wrapMiddleware('authenticate'), wrapMiddleware('authorize:admin')]);
$router->put('/api/variants/{id}', 'variantController::updateVariant', [wrapMiddleware('authenticate'), wrapMiddleware('authorize:admin')]);
$router->delete('/api/variants/{id}', 'variantController::deleteVariant', [wrapMiddleware('authenticate'), wrapMiddleware('authorize:admin')]);

// Booking routes
$router->post('/api/bookings/check', 'bookingController::checkBooking');
$router->post('/api/bookings/options', 'bookingController::getAvailableOptions');
$router->post('/api/bookings', 'bookingController::createBooking', [wrapMiddleware('authenticate')]);
$router->post('/api/bookings/dummy', 'bookingController::createDummyBooking'); // Public endpoint for dummy bookings
$router->post('/api/bookings/dummy-payment', 'bookingController::createDummyPayment'); // Public endpoint for dummy payments
$router->post('/api/bookings/send-confirmation-email', 'bookingController::sendConfirmationEmail');
$router->get('/api/bookings', 'bookingController::getBookings', [wrapMiddleware('authenticate')]);
$router->get('/api/bookings/{id}', 'bookingController::getBooking', [wrapMiddleware('authenticate')]);

// Review routes
$router->post('/api/reviews', 'reviewController::createReview', [wrapMiddleware('authenticate')]);
$router->get('/api/reviews', 'reviewController::getReviews');

// Wishlist routes
$router->post('/api/wishlist', 'wishlistController::addToWishlist', [wrapMiddleware('authenticate')]);
$router->get('/api/wishlist', 'wishlistController::getWishlist', [wrapMiddleware('authenticate')]);
$router->delete('/api/wishlist/{id}', 'wishlistController::removeFromWishlist', [wrapMiddleware('authenticate')]);

// Newsletter routes
$router->post('/api/newsletter', 'newsletterController::subscribe');
$router->post('/api/newsletter/subscribe', 'newsletterController::subscribe'); // Alias for frontend compatibility

// Contact/Lead routes
$router->post('/api/leads', 'contactLeadController::createLead');

// Blog routes
$router->get('/api/blogs', 'blogController::listBlogs');
$router->get('/api/blogs/{id}', 'blogController::getBlog');
$router->get('/api/blogs/slug/{slug}', 'blogController::getBlogBySlug');
$router->post('/api/blogs', 'blogController::createBlog', [wrapMiddleware('authenticate'), wrapMiddleware('authorize:admin')]);
$router->put('/api/blogs/{id}', 'blogController::updateBlog', [wrapMiddleware('authenticate'), wrapMiddleware('authorize:admin')]);
$router->delete('/api/blogs/{id}', 'blogController::deleteBlog', [wrapMiddleware('authenticate'), wrapMiddleware('authorize:admin')]);

// Testimonial routes
$router->get('/api/testimonials', 'testimonialController::listTestimonials');
$router->get('/api/testimonials/{id}', 'testimonialController::getTestimonial');
$router->post('/api/testimonials', 'testimonialController::createTestimonial', [wrapMiddleware('authenticate'), wrapMiddleware('authorize:admin')]);
$router->put('/api/testimonials/{id}', 'testimonialController::updateTestimonial', [wrapMiddleware('authenticate'), wrapMiddleware('authorize:admin')]);
$router->delete('/api/testimonials/{id}', 'testimonialController::deleteTestimonial', [wrapMiddleware('authenticate'), wrapMiddleware('authorize:admin')]);

return $router;

