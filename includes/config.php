<?php
/**
 * MediaHub - Database Configuration & Helpers
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'media_site');
define('DB_USER', 'root');
define('DB_PASS', '');

// Site configuration
define('SITE_NAME', 'MediaHub');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', 'uploads/');
define('MAX_IMAGE_SIZE', 10 * 1024 * 1024); // 10MB
define('MAX_VIDEO_SIZE', 100 * 1024 * 1024); // 100MB
define('ITEMS_PER_PAGE', 20);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_VIDEO_TYPES', ['video/mp4', 'video/webm', 'video/ogg']);

// Database connection
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    return $pdo;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper: current user
function currentUser() {
    return $_SESSION['user'] ?? null;
}

function isLoggedIn() {
    return isset($_SESSION['user']);
}

function isAdmin() {
    return isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin';
}

// CSRF protection
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf($token) {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

// JSON response helper
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Sanitize output
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// Time ago in Hebrew
function timeAgo($datetime) {
    $now = new DateTime();
    $past = new DateTime($datetime);
    $diff = $now->diff($past);

    if ($diff->y > 0) return $diff->y == 1 ? "לפני שנה" : "לפני " . $diff->y . " שנים";
    if ($diff->m > 0) return $diff->m == 1 ? "לפני חודש" : "לפני " . $diff->m . " חודשים";
    if ($diff->d > 6) {
        $weeks = floor($diff->d / 7);
        return $weeks == 1 ? "לפני שבוע" : "לפני " . $weeks . " שבועות";
    }
    if ($diff->d > 0) return $diff->d == 1 ? "לפני יום" : "לפני " . $diff->d . " ימים";
    if ($diff->h > 0) return $diff->h == 1 ? "לפני שעה" : "לפני " . $diff->h . " שעות";
    if ($diff->i > 0) return $diff->i == 1 ? "לפני דקה" : "לפני " . $diff->i . " דקות";
    return "הרגע";
}

// Handle file upload, returns path or null
function handleUpload($fileInput, $subfolder = '') {
    if (!isset($fileInput) || $fileInput['error'] !== UPLOAD_ERR_OK) return null;

    $mime = mime_content_type($fileInput['tmp_name']);
    $isImage = in_array($mime, ALLOWED_IMAGE_TYPES);
    $isVideo = in_array($mime, ALLOWED_VIDEO_TYPES);

    if (!$isImage && !$isVideo) return null;
    if ($isImage && $fileInput['size'] > MAX_IMAGE_SIZE) return null;
    if ($isVideo && $fileInput['size'] > MAX_VIDEO_SIZE) return null;

    $ext = strtolower(pathinfo($fileInput['name'], PATHINFO_EXTENSION));
    $filename = uniqid('media_', true) . '.' . $ext;

    $targetDir = UPLOAD_DIR . ($subfolder ? $subfolder . '/' : '');
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $targetPath = $targetDir . $filename;
    if (!move_uploaded_file($fileInput['tmp_name'], $targetPath)) return null;

    $relativePath = UPLOAD_URL . ($subfolder ? $subfolder . '/' : '') . $filename;

    // Generate thumbnail for images
    $thumbPath = null;
    if ($isImage && function_exists('imagecreatefromjpeg')) {
        $thumbDir = $targetDir . 'thumbs/';
        if (!is_dir($thumbDir)) mkdir($thumbDir, 0755, true);
        $thumbFile = $thumbDir . $filename;
        if (createThumb($targetPath, $thumbFile, 400)) {
            $thumbPath = UPLOAD_URL . ($subfolder ? $subfolder . '/' : '') . 'thumbs/' . $filename;
        }
    }

    return [
        'path' => $relativePath,
        'thumb' => $thumbPath ?? $relativePath,
        'type' => $isImage ? 'image' : 'video',
        'mime' => $mime,
    ];
}

function createThumb($source, $dest, $maxWidth) {
    $info = @getimagesize($source);
    if (!$info) return false;

    switch ($info['mime']) {
        case 'image/jpeg': $img = @imagecreatefromjpeg($source); break;
        case 'image/png': $img = @imagecreatefrompng($source); break;
        case 'image/gif': $img = @imagecreatefromgif($source); break;
        case 'image/webp': $img = @imagecreatefromwebp($source); break;
        default: return false;
    }
    if (!$img) return false;

    $origW = imagesx($img);
    $origH = imagesy($img);

    if ($origW <= $maxWidth) {
        copy($source, $dest);
        imagedestroy($img);
        return true;
    }

    $ratio = $maxWidth / $origW;
    $newW = $maxWidth;
    $newH = (int)($origH * $ratio);

    $thumb = imagecreatetruecolor($newW, $newH);
    // Preserve transparency for PNG
    imagealphablending($thumb, false);
    imagesavealpha($thumb, true);
    imagecopyresampled($thumb, $img, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
    imagejpeg($thumb, $dest, 85);

    imagedestroy($img);
    imagedestroy($thumb);
    return true;
}
