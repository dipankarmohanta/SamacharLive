<?php
/**
 * Admin/Reporter AJAX image upload for the TinyMCE editor.
 * Any logged-in user (admin, editor, reporter) may upload.
 * CSRF (X-CSRF-Token header) protected. Returns JSON for TinyMCE.
 */
require_once __DIR__ . '/../app/bootstrap.php';

Auth::start();

header('Content-Type: application/json');

if (!Auth::user()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// CSRF check via custom header (TinyMCE fetch cannot submit the normal form field)
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if ($token === '' || !hash_equals(Security::csrfToken(), $token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
    http_response_code(400);
    echo json_encode(['error' => 'No file received']);
    exit;
}

$uploaded = Security::uploadImage($_FILES['file'], 'news', 2048);
if ($uploaded) {
    echo json_encode(['location' => '/' . ltrim($uploaded, '/')]);
} else {
    http_response_code(422);
    echo json_encode(['error' => 'Upload failed. Use JPG, PNG, GIF or WebP under 2MB.']);
}
exit;
