<?php
/**
 * Reporter panel bootstrap.
 * Reporters can only submit news (goes to pending review).
 */
require_once __DIR__ . '/../../app/bootstrap.php';

Auth::start();

// Reporters are authenticated via the shared login page (/admin/).
if (!Auth::id()) {
    header('Location: /admin/index.php');
    exit;
}
if (!in_array(Auth::role(), ['admin', 'editor', 'reporter'], true)) {
    http_response_code(403);
    die('Access denied.');
}

$currentUser = Auth::user();
