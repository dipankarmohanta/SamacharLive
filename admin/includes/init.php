<?php
/**
 * Admin panel bootstrap.
 * Requires login with admin/editor role. Sets up common context.
 */
require_once __DIR__ . '/../../app/bootstrap.php';

Auth::start();
Auth::requireRole(['admin', 'editor']);

$currentUser = Auth::user();

// Determine current page for sidebar highlighting
$pageName = basename($_SERVER['SCRIPT_NAME']);
