<?php
/**
 * MediaHub - Beautiful Hebrew Media Sharing Site
 * Inspired by JDN.co.il design
 * File-based storage (JSON) - No database required
 */

// Configuration
define('DATA_DIR', __DIR__ . '/data/');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', 'uploads/');
define('MAX_IMAGE_SIZE', 10 * 1024 * 1024);
define('MAX_VIDEO_SIZE', 100 * 1024 * 1024);
define('ITEMS_PER_PAGE', 20);

// Ensure data files exist
if (!file_exists(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
if (!file_exists(DATA_DIR . 'posts.json')) file_put_contents(DATA_DIR . 'posts.json', '[]');
if (!file_exists(DATA_DIR . 'chat.json')) file_put_contents(DATA_DIR . 'chat.json', '[]');
if (!file_exists(UPLOAD_DIR . 'images/')) mkdir(UPLOAD_DIR . 'images/', 0755, true);
if (!file_exists(UPLOAD_DIR . 'videos/')) mkdir(UPLOAD_DIR . 'videos/', 0755, true);
if (!file_exists(UPLOAD_DIR . 'thumbs/')) mkdir(UPLOAD_DIR . 'thumbs/', 0755, true);

// Helper functions
function loadJson($file) {
    $path = DATA_DIR . $file;
    if (!file_exists($path)) return [];
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function saveJson($file, $data) {
    file_put_contents(DATA_DIR . $file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function timeAgo($timestamp) {
    $diff = time() - $timestamp;
    if ($diff < 60) return "הרגע";
    if ($diff < 3600) return "לפני " . floor($diff / 60) . " דקות";
    if ($diff < 86400) return "לפני " . floor($diff / 3600) . " שעות";
    if ($diff < 604800) return "לפני " . floor($diff / 86400) . " ימים";
    return date('d/m/Y', $timestamp);
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
    if ($origW <= $maxWidth) { copy($source, $dest); imagedestroy($img); return true; }
    $ratio = $maxWidth / $origW;
    $newW = $maxWidth;
    $newH = (int)($origH * $ratio);
    $thumb = imagecreatetruecolor($newW, $newH);
    imagealphablending($thumb, false);
    imagesavealpha($thumb, true);
    imagecopyresampled($thumb, $img, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
    imagejpeg($thumb, $dest, 85);
    imagedestroy($img);
    imagedestroy($thumb);
    return true;
}

// Handle API requests
$action = $_GET['action'] ?? '';
if ($action) {
    header('Content-Type: application/json; charset=utf-8');

    // GET posts
    if ($action === 'get_posts') {
        $posts = loadJson('posts.json');
        usort($posts, fn($a, $b) => ($b['created_at'] ?? 0) - ($a['created_at'] ?? 0));
        $type = $_GET['type'] ?? '';
        if ($type) $posts = array_values(array_filter($posts, fn($p) => ($p['type'] ?? '') === $type));
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = (int)($_GET['limit'] ?? ITEMS_PER_PAGE);
        $offset = ($page - 1) * $limit;
        $total = count($posts);
        $items = array_slice($posts, $offset, $limit);
        foreach ($items as &$item) {
            $item['time_ago'] = timeAgo($item['created_at'] ?? 0);
        }
        echo json_encode(['posts' => $items, 'total' => $total, 'page' => $page, 'pages' => max(1, ceil($total / $limit))], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // GET recent 10 images for homepage
    if ($action === 'get_recent') {
        $posts = loadJson('posts.json');
        usort($posts, fn($a, $b) => ($b['created_at'] ?? 0) - ($a['created_at'] ?? 0));
        $images = array_values(array_filter($posts, fn($p) => ($p['type'] ?? '') === 'image'));
        $recent = array_slice($images, 0, 10);
        foreach ($recent as &$item) {
            $item['time_ago'] = timeAgo($item['created_at'] ?? 0);
        }
        echo json_encode(['posts' => $recent], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Upload image/video
    if ($action === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_FILES['media']) || $_FILES['media']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['error' => 'שגיאה בהעלאת הקובץ'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $file = $_FILES['media'];
        $mime = mime_content_type($file['tmp_name']);
        $allowedImages = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $allowedVideos = ['video/mp4', 'video/webm', 'video/ogg'];
        $isImage = in_array($mime, $allowedImages);
        $isVideo = in_array($mime, $allowedVideos);
        if (!$isImage && !$isVideo) {
            echo json_encode(['error' => 'סוג קובץ לא נתמך'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($isImage && $file['size'] > MAX_IMAGE_SIZE) {
            echo json_encode(['error' => 'תמונה גדולה מדי (מקסימום 10MB)'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($isVideo && $file['size'] > MAX_VIDEO_SIZE) {
            echo json_encode(['error' => 'סרטון גדול מדי (מקסימום 100MB)'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = uniqid('media_', true) . '.' . $ext;
        $subfolder = $isImage ? 'images/' : 'videos/';
        $targetPath = UPLOAD_DIR . $subfolder . $filename;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            echo json_encode(['error' => 'שגיאה בשמירת הקובץ'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $mediaUrl = UPLOAD_URL . $subfolder . $filename;
        $thumbUrl = $mediaUrl;

        // Generate thumbnail for images
        if ($isImage && function_exists('imagecreatefromjpeg')) {
            $thumbDir = UPLOAD_DIR . 'thumbs/';
            $thumbFile = $thumbDir . $filename;
            if (createThumb($targetPath, $thumbFile, 400)) {
                $thumbUrl = UPLOAD_URL . 'thumbs/' . $filename;
            }
        }

        $title = trim($_POST['title'] ?? '');
        $nickname = trim($_POST['nickname'] ?? 'אנונימי');
        if (!$title) $title = $isImage ? 'תמונה חדשה' : 'סרטון חדש';

        $post = [
            'id' => uniqid('p_'),
            'title' => $title,
            'type' => $isImage ? 'image' : 'video',
            'media_url' => $mediaUrl,
            'thumb_url' => $thumbUrl,
            'nickname' => $nickname,
            'created_at' => time(),
        ];

        $posts = loadJson('posts.json');
        $posts[] = $post;
        saveJson('posts.json', $posts);
        $post['time_ago'] = timeAgo($post['created_at']);
        echo json_encode(['success' => true, 'post' => $post], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Chat messages
    if ($action === 'get_chat') {
        $chat = loadJson('chat.json');
        usort($chat, fn($a, $b) => ($a['created_at'] ?? 0) - ($b['created_at'] ?? 0));
        $last = array_slice($chat, -50);
        foreach ($last as &$msg) {
            $msg['time_ago'] = timeAgo($msg['created_at'] ?? 0);
        }
        echo json_encode(['messages' => $last], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'send_chat' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $nickname = trim($_POST['nickname'] ?? 'אנונימי');
        $message = trim($_POST['message'] ?? '');
        $hasImage = isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK;

        if (!$message && !$hasImage) {
            echo json_encode(['error' => 'הודעה ריקה'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $imageUrl = null;
        $thumbUrl = null;
        if ($hasImage) {
            $file = $_FILES['image'];
            $mime = mime_content_type($file['tmp_name']);
            if (in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp']) && $file['size'] <= MAX_IMAGE_SIZE) {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $filename = uniqid('chat_', true) . '.' . $ext;
                $targetPath = UPLOAD_DIR . 'images/' . $filename;
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $imageUrl = UPLOAD_URL . 'images/' . $filename;
                    $thumbUrl = $imageUrl;
                    if (function_exists('imagecreatefromjpeg')) {
                        $thumbFile = UPLOAD_DIR . 'thumbs/' . $filename;
                        if (createThumb($targetPath, $thumbFile, 400)) {
                            $thumbUrl = UPLOAD_URL . 'thumbs/' . $filename;
                        }
                    }
                    // Auto-add as image post
                    $post = [
                        'id' => uniqid('p_'),
                        'title' => $message ?: 'תמונה מהצ\'אט',
                        'type' => 'image',
                        'media_url' => $imageUrl,
                        'thumb_url' => $thumbUrl,
                        'nickname' => $nickname,
                        'created_at' => time(),
                    ];
                    $posts = loadJson('posts.json');
                    $posts[] = $post;
                    saveJson('posts.json', $posts);
                }
            }
        }

        $chatMsg = [
            'id' => uniqid('c_'),
            'nickname' => $nickname,
            'message' => $message,
            'image_url' => $imageUrl,
            'created_at' => time(),
        ];
        $chat = loadJson('chat.json');
        $chat[] = $chatMsg;
        // Keep last 500 messages
        if (count($chat) > 500) $chat = array_slice($chat, -500);
        saveJson('chat.json', $chat);
        $chatMsg['time_ago'] = timeAgo($chatMsg['created_at']);
        echo json_encode(['success' => true, 'message' => $chatMsg], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['error' => 'Unknown action'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Determine current page
$page = $_GET['page'] ?? 'home';
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MediaHub - שיתוף מדיה</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Heebo:wght@300;400;500;600;700;800;900&family=Rubik:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
/* ========================================
   RESET & BASE
   ======================================== */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --primary: #1a5276;
    --primary-light: #2980b9;
    --primary-dark: #0e2f44;
    --accent: #e74c3c;
    --accent-hover: #c0392b;
    --bg: #eef1f5;
    --card-bg: #ffffff;
    --text: #2c3e50;
    --text-light: #7f8c8d;
    --text-muted: #95a5a6;
    --border: #dfe6e9;
    --shadow: 0 2px 15px rgba(0,0,0,0.08);
    --shadow-hover: 0 8px 30px rgba(0,0,0,0.15);
    --radius: 12px;
    --radius-sm: 8px;
    --chat-width: 340px;
    --header-height: 64px;
    --gradient-blue: linear-gradient(135deg, #1a5276 0%, #2980b9 50%, #3498db 100%);
    --gradient-overlay: linear-gradient(180deg, transparent 0%, transparent 40%, rgba(0,0,0,0.7) 100%);
    --chat-mine: #dcf8c6;
    --chat-other: #ffffff;
    --font-main: 'Heebo', 'Rubik', sans-serif;
}

html { font-size: 15px; }

body {
    font-family: var(--font-main);
    background: var(--bg);
    color: var(--text);
    line-height: 1.6;
    overflow-x: hidden;
    min-height: 100vh;
}

/* ========================================
   SCROLLBAR
   ======================================== */
::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: #bdc3c7; border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: #95a5a6; }

/* ========================================
   HEADER / NAVIGATION
   ======================================== */
.header {
    background: var(--gradient-blue);
    height: var(--header-height);
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    box-shadow: 0 2px 20px rgba(26,82,118,0.3);
}

.header-inner {
    max-width: 1400px;
    margin: 0 auto;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 24px;
}

.logo {
    font-size: 1.6rem;
    font-weight: 800;
    color: #fff;
    text-decoration: none;
    letter-spacing: -0.5px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.logo-icon {
    width: 36px;
    height: 36px;
    background: rgba(255,255,255,0.2);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    backdrop-filter: blur(10px);
}

.nav-menu {
    display: flex;
    align-items: center;
    gap: 4px;
    list-style: none;
}

.nav-menu a {
    color: rgba(255,255,255,0.85);
    text-decoration: none;
    padding: 8px 20px;
    border-radius: 8px;
    font-weight: 500;
    font-size: 0.95rem;
    transition: all 0.25s ease;
    position: relative;
}

.nav-menu a:hover,
.nav-menu a.active {
    color: #fff;
    background: rgba(255,255,255,0.15);
}

.nav-menu a.active::after {
    content: '';
    position: absolute;
    bottom: -2px;
    right: 20%;
    left: 20%;
    height: 3px;
    background: #fff;
    border-radius: 3px;
}

.header-search {
    position: relative;
    width: 220px;
}

.header-search input {
    width: 100%;
    padding: 8px 16px 8px 36px;
    background: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 20px;
    color: #fff;
    font-family: var(--font-main);
    font-size: 0.85rem;
    outline: none;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.header-search input::placeholder { color: rgba(255,255,255,0.6); }
.header-search input:focus {
    background: rgba(255,255,255,0.25);
    border-color: rgba(255,255,255,0.4);
    width: 260px;
}

.header-search svg {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    width: 16px;
    height: 16px;
    stroke: rgba(255,255,255,0.6);
    fill: none;
    pointer-events: none;
}

/* ========================================
   LAYOUT
   ======================================== */
.main-layout {
    display: flex;
    margin-top: var(--header-height);
    min-height: calc(100vh - var(--header-height));
}

.content-area {
    flex: 1;
    padding: 28px;
    margin-left: var(--chat-width);
    max-width: calc(100% - var(--chat-width));
}

/* ========================================
   SECTION HEADERS
   ======================================== */
.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    padding-bottom: 12px;
    border-bottom: 3px solid var(--primary);
}

.section-title {
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--primary-dark);
    position: relative;
    padding-right: 16px;
}

.section-title::before {
    content: '';
    position: absolute;
    right: 0;
    top: 4px;
    bottom: 4px;
    width: 4px;
    background: var(--accent);
    border-radius: 2px;
}

.section-link {
    color: var(--primary-light);
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 500;
    transition: color 0.2s;
}

.section-link:hover { color: var(--primary); }

/* ========================================
   IMAGE GRID - MASONRY STYLE
   ======================================== */
.image-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.image-card {
    background: var(--card-bg);
    border-radius: var(--radius);
    overflow: hidden;
    box-shadow: var(--shadow);
    transition: all 0.35s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    cursor: pointer;
    position: relative;
}

.image-card:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-hover);
}

.image-card:nth-child(3n+1) .card-image { height: 260px; }
.image-card:nth-child(3n+2) .card-image { height: 220px; }
.image-card:nth-child(3n+3) .card-image { height: 300px; }

.card-image {
    width: 100%;
    height: 240px;
    object-fit: cover;
    display: block;
    transition: transform 0.5s ease;
}

.image-card:hover .card-image {
    transform: scale(1.05);
}

.card-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: var(--gradient-overlay);
    padding: 40px 16px 14px;
    transition: padding 0.3s ease;
}

.image-card:hover .card-overlay {
    padding-bottom: 18px;
}

.card-title {
    color: #fff;
    font-size: 1rem;
    font-weight: 600;
    line-height: 1.3;
    text-shadow: 0 1px 3px rgba(0,0,0,0.3);
}

.card-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 6px;
}

.card-author {
    color: rgba(255,255,255,0.85);
    font-size: 0.78rem;
    font-weight: 400;
}

.card-time {
    color: rgba(255,255,255,0.7);
    font-size: 0.72rem;
}

/* Featured card (first item on homepage) */
.image-card.featured {
    grid-column: span 2;
    grid-row: span 2;
}

.image-card.featured .card-image { height: 100%; min-height: 400px; }
.image-card.featured .card-title { font-size: 1.35rem; }

/* ========================================
   UPLOAD AREA
   ======================================== */
.upload-zone {
    background: var(--card-bg);
    border: 2px dashed var(--border);
    border-radius: var(--radius);
    padding: 32px;
    text-align: center;
    margin-bottom: 28px;
    transition: all 0.3s ease;
    cursor: pointer;
}

.upload-zone:hover,
.upload-zone.dragover {
    border-color: var(--primary-light);
    background: rgba(41,128,185,0.04);
}

.upload-zone svg {
    width: 48px;
    height: 48px;
    stroke: var(--primary-light);
    fill: none;
    margin-bottom: 12px;
    stroke-width: 1.5;
}

.upload-zone h3 {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 6px;
}

.upload-zone p {
    color: var(--text-light);
    font-size: 0.85rem;
}

.upload-zone input[type="file"] { display: none; }

/* ========================================
   CHAT SIDEBAR
   ======================================== */
.chat-sidebar {
    width: var(--chat-width);
    position: fixed;
    top: var(--header-height);
    left: 0;
    bottom: 0;
    background: #e5ddd5;
    background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23c7beb3' fill-opacity='0.15'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    display: flex;
    flex-direction: column;
    z-index: 900;
    border-right: 1px solid var(--border);
}

.chat-header {
    background: var(--gradient-blue);
    padding: 14px 18px;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}

.chat-header h3 {
    font-size: 1rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.chat-header .online-dot {
    width: 8px;
    height: 8px;
    background: #2ecc71;
    border-radius: 50%;
    display: inline-block;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 16px 12px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.chat-msg {
    max-width: 85%;
    padding: 8px 12px;
    border-radius: 10px;
    font-size: 0.85rem;
    line-height: 1.5;
    position: relative;
    word-wrap: break-word;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

.chat-msg.other {
    align-self: flex-start;
    background: var(--chat-other);
    border-top-right-radius: 3px;
}

.chat-msg.mine {
    align-self: flex-end;
    background: var(--chat-mine);
    border-top-left-radius: 3px;
}

.chat-nickname {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--primary-light);
    margin-bottom: 2px;
}

.chat-msg .chat-image {
    max-width: 100%;
    border-radius: 8px;
    margin-top: 6px;
    cursor: pointer;
}

.chat-msg .chat-time {
    font-size: 0.65rem;
    color: var(--text-muted);
    text-align: left;
    margin-top: 4px;
}

.chat-input-area {
    padding: 10px 12px;
    background: #f0f0f0;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

.chat-input-area input[type="text"] {
    flex: 1;
    padding: 10px 14px;
    border: 1px solid var(--border);
    border-radius: 20px;
    font-family: var(--font-main);
    font-size: 0.85rem;
    outline: none;
    background: #fff;
    transition: border-color 0.2s;
}

.chat-input-area input[type="text"]:focus {
    border-color: var(--primary-light);
}

.chat-btn {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    flex-shrink: 0;
}

.chat-btn svg {
    width: 18px;
    height: 18px;
    fill: none;
    stroke-width: 2;
}

.chat-btn-send {
    background: var(--primary-light);
}

.chat-btn-send svg { stroke: #fff; }
.chat-btn-send:hover { background: var(--primary); }

.chat-btn-image {
    background: transparent;
}

.chat-btn-image svg { stroke: var(--text-light); }
.chat-btn-image:hover svg { stroke: var(--primary-light); }

.chat-input-area input[type="file"] { display: none; }

/* ========================================
   NICKNAME MODAL
   ======================================== */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    z-index: 2000;
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(4px);
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.modal-overlay.show {
    opacity: 1;
    visibility: visible;
}

.modal-box {
    background: var(--card-bg);
    border-radius: var(--radius);
    padding: 36px;
    width: 360px;
    max-width: 90vw;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    transform: translateY(20px);
    transition: transform 0.3s ease;
    text-align: center;
}

.modal-overlay.show .modal-box {
    transform: translateY(0);
}

.modal-box h2 {
    font-size: 1.3rem;
    margin-bottom: 8px;
    color: var(--primary-dark);
}

.modal-box p {
    color: var(--text-light);
    font-size: 0.9rem;
    margin-bottom: 20px;
}

.modal-box input {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid var(--border);
    border-radius: var(--radius-sm);
    font-family: var(--font-main);
    font-size: 1rem;
    text-align: center;
    outline: none;
    transition: border-color 0.2s;
    margin-bottom: 16px;
}

.modal-box input:focus { border-color: var(--primary-light); }

.modal-box button {
    width: 100%;
    padding: 12px;
    background: var(--gradient-blue);
    color: #fff;
    border: none;
    border-radius: var(--radius-sm);
    font-family: var(--font-main);
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: opacity 0.2s;
}

.modal-box button:hover { opacity: 0.9; }

/* ========================================
   LIGHTBOX
   ======================================== */
.lightbox {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.92);
    z-index: 3000;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    cursor: pointer;
}

.lightbox.show { opacity: 1; visibility: visible; }

.lightbox img {
    max-width: 90vw;
    max-height: 90vh;
    border-radius: 8px;
    box-shadow: 0 0 60px rgba(0,0,0,0.5);
    cursor: default;
}

.lightbox-close {
    position: absolute;
    top: 20px;
    right: 20px;
    width: 40px;
    height: 40px;
    background: rgba(255,255,255,0.15);
    border: none;
    border-radius: 50%;
    color: #fff;
    font-size: 1.4rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s;
}

.lightbox-close:hover { background: rgba(255,255,255,0.3); }

/* ========================================
   PAGINATION
   ======================================== */
.pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    margin-top: 32px;
    padding: 20px 0;
}

.pagination button {
    padding: 8px 16px;
    border: 1px solid var(--border);
    background: var(--card-bg);
    border-radius: var(--radius-sm);
    font-family: var(--font-main);
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s;
    color: var(--text);
}

.pagination button:hover { border-color: var(--primary-light); color: var(--primary-light); }
.pagination button.active {
    background: var(--primary-light);
    color: #fff;
    border-color: var(--primary-light);
}

.pagination button:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

/* ========================================
   EMPTY STATE
   ======================================== */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-light);
}

.empty-state svg {
    width: 80px;
    height: 80px;
    stroke: var(--border);
    fill: none;
    margin-bottom: 16px;
    stroke-width: 1;
}

.empty-state h3 {
    font-size: 1.2rem;
    margin-bottom: 8px;
    color: var(--text);
}

.empty-state p { font-size: 0.9rem; }

/* ========================================
   LOADING
   ======================================== */
.loading {
    text-align: center;
    padding: 40px;
    grid-column: 1 / -1;
}

.spinner {
    width: 40px;
    height: 40px;
    border: 3px solid var(--border);
    border-top-color: var(--primary-light);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin: 0 auto 12px;
}

@keyframes spin { to { transform: rotate(360deg); } }

/* ========================================
   VIDEO CARD
   ======================================== */
.video-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    background: rgba(0,0,0,0.7);
    color: #fff;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.72rem;
    font-weight: 600;
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    gap: 4px;
    z-index: 2;
}

.play-icon {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 54px;
    height: 54px;
    background: rgba(255,255,255,0.9);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0.8;
    transition: all 0.3s ease;
    z-index: 2;
}

.image-card:hover .play-icon { opacity: 1; transform: translate(-50%, -50%) scale(1.1); }
.play-icon svg { width: 24px; height: 24px; fill: var(--primary); stroke: none; margin-right: -2px; }

/* ========================================
   CHAT TOGGLE (MOBILE)
   ======================================== */
.chat-toggle {
    display: none;
    position: fixed;
    bottom: 20px;
    left: 20px;
    width: 56px;
    height: 56px;
    background: var(--gradient-blue);
    border: none;
    border-radius: 50%;
    color: #fff;
    font-size: 1.4rem;
    cursor: pointer;
    z-index: 1100;
    box-shadow: 0 4px 20px rgba(26,82,118,0.4);
    transition: transform 0.2s;
}

.chat-toggle:hover { transform: scale(1.1); }

/* ========================================
   UPLOAD MODAL
   ======================================== */
.upload-modal .modal-box { width: 440px; text-align: right; }
.upload-modal .modal-box h2 { text-align: center; }

.upload-preview {
    width: 100%;
    max-height: 200px;
    object-fit: contain;
    border-radius: var(--radius-sm);
    margin-bottom: 12px;
    display: none;
}

.upload-progress {
    height: 4px;
    background: var(--border);
    border-radius: 2px;
    overflow: hidden;
    margin-bottom: 12px;
    display: none;
}

.upload-progress-bar {
    height: 100%;
    background: var(--gradient-blue);
    width: 0%;
    transition: width 0.3s ease;
}

.upload-form-group {
    margin-bottom: 14px;
}

.upload-form-group label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 6px;
}

.upload-form-group input[type="text"] {
    width: 100%;
    padding: 10px 14px;
    border: 2px solid var(--border);
    border-radius: var(--radius-sm);
    font-family: var(--font-main);
    font-size: 0.9rem;
    outline: none;
    transition: border-color 0.2s;
}

.upload-form-group input:focus { border-color: var(--primary-light); }

/* ========================================
   RESPONSIVE
   ======================================== */
@media (max-width: 1024px) {
    .chat-sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
    }
    .chat-sidebar.open { transform: translateX(0); }
    .content-area { margin-left: 0; max-width: 100%; }
    .chat-toggle { display: flex; align-items: center; justify-content: center; }
    .header-search { width: 160px; }
    .header-search input:focus { width: 180px; }
    .image-card.featured { grid-column: span 1; grid-row: span 1; }
    .image-card.featured .card-image { height: 260px; min-height: auto; }
}

@media (max-width: 640px) {
    .content-area { padding: 16px; }
    .image-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px; }
    .card-image { height: 180px !important; }
    .header-search { display: none; }
    .nav-menu a { padding: 8px 12px; font-size: 0.85rem; }
    .logo { font-size: 1.2rem; }
    .section-title { font-size: 1.1rem; }
}

/* ========================================
   ANIMATIONS
   ======================================== */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.image-card { animation: fadeInUp 0.5s ease backwards; }
.image-card:nth-child(1) { animation-delay: 0.05s; }
.image-card:nth-child(2) { animation-delay: 0.1s; }
.image-card:nth-child(3) { animation-delay: 0.15s; }
.image-card:nth-child(4) { animation-delay: 0.2s; }
.image-card:nth-child(5) { animation-delay: 0.25s; }
.image-card:nth-child(6) { animation-delay: 0.3s; }
.image-card:nth-child(7) { animation-delay: 0.35s; }
.image-card:nth-child(8) { animation-delay: 0.4s; }
.image-card:nth-child(9) { animation-delay: 0.45s; }
.image-card:nth-child(10) { animation-delay: 0.5s; }

.chat-msg { animation: fadeInUp 0.3s ease; }
</style>
</head>
<body>

<!-- HEADER -->
<header class="header">
    <div class="header-inner">
        <a href="index.php" class="logo">
            <span class="logo-icon">M</span>
            MediaHub
        </a>

        <nav>
            <ul class="nav-menu">
                <li><a href="index.php?page=home" class="<?= $page === 'home' ? 'active' : '' ?>">דף הבית</a></li>
                <li><a href="index.php?page=images" class="<?= $page === 'images' ? 'active' : '' ?>">תמונות</a></li>
                <li><a href="index.php?page=videos" class="<?= $page === 'videos' ? 'active' : '' ?>">סרטונים</a></li>
            </ul>
        </nav>

        <div class="header-search">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="searchInput" placeholder="חיפוש..." autocomplete="off">
        </div>
    </div>
</header>

<!-- MAIN LAYOUT -->
<div class="main-layout">

    <!-- CONTENT -->
    <main class="content-area">

        <?php if ($page === 'home'): ?>
        <!-- HOMEPAGE -->
        <div class="upload-zone" id="uploadZone" onclick="document.getElementById('mainUploadFile').click()">
            <svg viewBox="0 0 24 24" stroke-width="1.5"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            <h3>העלאת תמונה או סרטון</h3>
            <p>גררו קובץ לכאן או לחצו לבחירה</p>
            <input type="file" id="mainUploadFile" accept="image/*,video/*">
        </div>

        <div class="section-header">
            <h2 class="section-title">תמונות אחרונות</h2>
            <a href="index.php?page=images" class="section-link">הצג הכל &larr;</a>
        </div>
        <div class="image-grid" id="recentGrid">
            <div class="loading"><div class="spinner"></div><p>טוען...</p></div>
        </div>

        <?php elseif ($page === 'images'): ?>
        <!-- IMAGES PAGE -->
        <div class="section-header">
            <h2 class="section-title">כל התמונות</h2>
        </div>
        <div class="image-grid" id="imagesGrid">
            <div class="loading"><div class="spinner"></div><p>טוען...</p></div>
        </div>
        <div class="pagination" id="imagesPagination"></div>

        <?php elseif ($page === 'videos'): ?>
        <!-- VIDEOS PAGE -->
        <div class="section-header">
            <h2 class="section-title">כל הסרטונים</h2>
        </div>
        <div class="image-grid" id="videosGrid">
            <div class="loading"><div class="spinner"></div><p>טוען...</p></div>
        </div>
        <div class="pagination" id="videosPagination"></div>
        <?php endif; ?>

    </main>

    <!-- CHAT SIDEBAR -->
    <aside class="chat-sidebar" id="chatSidebar">
        <div class="chat-header">
            <h3><span class="online-dot"></span> צ'אט חי</h3>
        </div>
        <div class="chat-messages" id="chatMessages">
        </div>
        <div class="chat-input-area">
            <button class="chat-btn chat-btn-send" id="chatSendBtn" title="שלח">
                <svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            </button>
            <input type="text" id="chatInput" placeholder="הקלד הודעה..." autocomplete="off">
            <label class="chat-btn chat-btn-image" title="שלח תמונה">
                <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                <input type="file" id="chatImageInput" accept="image/*" style="display:none">
            </label>
        </div>
    </aside>
</div>

<!-- MOBILE CHAT TOGGLE -->
<button class="chat-toggle" id="chatToggle" title="צ'אט">
    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>
</button>

<!-- NICKNAME MODAL -->
<div class="modal-overlay" id="nicknameModal">
    <div class="modal-box">
        <h2>!ברוכים הבאים</h2>
        <p>בחרו כינוי כדי להתחיל</p>
        <input type="text" id="nicknameInput" placeholder="הכינוי שלכם..." maxlength="20" autofocus>
        <button id="nicknameSubmit">כניסה</button>
    </div>
</div>

<!-- UPLOAD MODAL -->
<div class="modal-overlay upload-modal" id="uploadModal">
    <div class="modal-box">
        <h2>העלאת קובץ</h2>
        <img class="upload-preview" id="uploadPreview" alt="">
        <div class="upload-progress" id="uploadProgress"><div class="upload-progress-bar" id="uploadProgressBar"></div></div>
        <div class="upload-form-group">
            <label>כותרת</label>
            <input type="text" id="uploadTitle" placeholder="כותרת לתמונה/סרטון...">
        </div>
        <button id="uploadSubmit" style="width:100%;padding:12px;background:var(--gradient-blue);color:#fff;border:none;border-radius:var(--radius-sm);font-family:var(--font-main);font-size:1rem;font-weight:600;cursor:pointer;margin-top:8px;">העלאה</button>
        <button id="uploadCancel" style="width:100%;padding:10px;background:transparent;color:var(--text-light);border:1px solid var(--border);border-radius:var(--radius-sm);font-family:var(--font-main);font-size:0.9rem;cursor:pointer;margin-top:8px;">ביטול</button>
    </div>
</div>

<!-- LIGHTBOX -->
<div class="lightbox" id="lightbox" onclick="closeLightbox()">
    <button class="lightbox-close" onclick="closeLightbox()">&times;</button>
    <img id="lightboxImg" src="" alt="">
</div>

<script>
// ========================================
// APP STATE
// ========================================
let nickname = localStorage.getItem('mediahub_nickname') || '';
let currentPage = '<?= e($page) ?>';
let imagesPage = 1;
let videosPage = 1;
let chatPollTimer = null;
let pendingUploadFile = null;

// ========================================
// NICKNAME
// ========================================
function checkNickname() {
    if (!nickname) {
        document.getElementById('nicknameModal').classList.add('show');
        setTimeout(() => document.getElementById('nicknameInput').focus(), 300);
    }
}

document.getElementById('nicknameSubmit').addEventListener('click', function() {
    const val = document.getElementById('nicknameInput').value.trim();
    if (val) {
        nickname = val;
        localStorage.setItem('mediahub_nickname', nickname);
        document.getElementById('nicknameModal').classList.remove('show');
    }
});

document.getElementById('nicknameInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') document.getElementById('nicknameSubmit').click();
});

// ========================================
// API HELPERS
// ========================================
async function api(action, params = {}) {
    const url = new URL(window.location.href.split('?')[0]);
    url.searchParams.set('action', action);
    for (const [k, v] of Object.entries(params)) url.searchParams.set(k, v);
    const res = await fetch(url.toString());
    return res.json();
}

async function apiPost(action, formData) {
    const url = new URL(window.location.href.split('?')[0]);
    url.searchParams.set('action', action);
    const res = await fetch(url.toString(), { method: 'POST', body: formData });
    return res.json();
}

// ========================================
// RENDER CARDS
// ========================================
function renderCard(post, index, featured) {
    const isVideo = post.type === 'video';
    const thumb = post.thumb_url || post.media_url || '';
    const title = post.title || (isVideo ? 'סרטון' : 'תמונה');
    const cls = featured && index === 0 ? 'image-card featured' : 'image-card';

    let inner = '';
    if (isVideo) {
        inner += '<div class="video-badge"><svg width="10" height="10" viewBox="0 0 24 24" fill="white" stroke="none"><polygon points="5 3 19 12 5 21 5 3"/></svg> סרטון</div>';
        inner += '<div class="play-icon"><svg viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg></div>';
    }

    const clickAction = isVideo
        ? "playVideo('" + escAttr(post.media_url) + "')"
        : "openLightbox('" + escAttr(post.media_url) + "')";

    return '<div class="' + cls + '" onclick="' + clickAction + '" style="animation-delay:' + (index * 0.05) + 's">' +
        '<img class="card-image" src="' + escAttr(thumb) + '" alt="' + escAttr(title) + '" loading="lazy" onerror="this.style.background=\'#ddd\'">' +
        inner +
        '<div class="card-overlay">' +
            '<div class="card-title">' + escHtml(title) + '</div>' +
            '<div class="card-meta">' +
                '<span class="card-author">' + escHtml(post.nickname || 'אנונימי') + '</span>' +
                '<span class="card-time">' + escHtml(post.time_ago || '') + '</span>' +
            '</div>' +
        '</div>' +
    '</div>';
}

function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str || '';
    return d.innerHTML;
}

function escAttr(str) {
    return (str || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

function renderEmpty(msg) {
    return '<div class="empty-state" style="grid-column:1/-1">' +
        '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>' +
        '<h3>' + msg + '</h3>' +
        '<p>העלו תוכן חדש כדי להתחיל</p>' +
    '</div>';
}

function renderPagination(container, currentP, totalPages, onPageChange) {
    container.innerHTML = '';
    if (totalPages <= 1) return;

    const prev = document.createElement('button');
    prev.textContent = 'הקודם';
    prev.disabled = currentP <= 1;
    prev.onclick = function() { onPageChange(currentP - 1); };
    container.appendChild(prev);

    for (let i = 1; i <= totalPages; i++) {
        if (totalPages > 7 && i > 3 && i < totalPages - 2 && Math.abs(i - currentP) > 1) {
            if (i === 4 || i === totalPages - 3) {
                const dots = document.createElement('button');
                dots.textContent = '...';
                dots.disabled = true;
                container.appendChild(dots);
            }
            continue;
        }
        const btn = document.createElement('button');
        btn.textContent = i;
        if (i === currentP) btn.classList.add('active');
        (function(page) { btn.onclick = function() { onPageChange(page); }; })(i);
        container.appendChild(btn);
    }

    const next = document.createElement('button');
    next.textContent = 'הבא';
    next.disabled = currentP >= totalPages;
    next.onclick = function() { onPageChange(currentP + 1); };
    container.appendChild(next);
}

// ========================================
// LOAD PAGES
// ========================================
async function loadHomepage() {
    const grid = document.getElementById('recentGrid');
    if (!grid) return;
    try {
        const data = await api('get_recent');
        if (!data.posts || data.posts.length === 0) {
            grid.innerHTML = renderEmpty('עדיין אין תמונות');
            return;
        }
        grid.innerHTML = data.posts.map(function(p, i) { return renderCard(p, i, true); }).join('');
    } catch(err) {
        grid.innerHTML = renderEmpty('שגיאה בטעינה');
    }
}

async function loadImages(page) {
    const grid = document.getElementById('imagesGrid');
    if (!grid) return;
    grid.innerHTML = '<div class="loading"><div class="spinner"></div><p>טוען...</p></div>';
    try {
        const data = await api('get_posts', { type: 'image', page: page });
        if (!data.posts || data.posts.length === 0) {
            grid.innerHTML = renderEmpty('עדיין אין תמונות');
            return;
        }
        grid.innerHTML = data.posts.map(function(p, i) { return renderCard(p, i, false); }).join('');
        const pag = document.getElementById('imagesPagination');
        if (pag) renderPagination(pag, data.page, data.pages, function(p) { imagesPage = p; loadImages(p); });
    } catch(err) {
        grid.innerHTML = renderEmpty('שגיאה בטעינה');
    }
}

async function loadVideos(page) {
    const grid = document.getElementById('videosGrid');
    if (!grid) return;
    grid.innerHTML = '<div class="loading"><div class="spinner"></div><p>טוען...</p></div>';
    try {
        const data = await api('get_posts', { type: 'video', page: page });
        if (!data.posts || data.posts.length === 0) {
            grid.innerHTML = renderEmpty('עדיין אין סרטונים');
            return;
        }
        grid.innerHTML = data.posts.map(function(p, i) { return renderCard(p, i, false); }).join('');
        const pag = document.getElementById('videosPagination');
        if (pag) renderPagination(pag, data.page, data.pages, function(p) { videosPage = p; loadVideos(p); });
    } catch(err) {
        grid.innerHTML = renderEmpty('שגיאה בטעינה');
    }
}

// ========================================
// LIGHTBOX
// ========================================
function openLightbox(url) {
    document.getElementById('lightboxImg').src = url;
    document.getElementById('lightbox').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeLightbox() {
    document.getElementById('lightbox').classList.remove('show');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeLightbox();
});

function playVideo(url) {
    window.open(url, '_blank');
}

// ========================================
// UPLOAD
// ========================================
var uploadZone = document.getElementById('uploadZone');
var mainUploadFile = document.getElementById('mainUploadFile');

if (uploadZone) {
    uploadZone.addEventListener('dragover', function(e) { e.preventDefault(); uploadZone.classList.add('dragover'); });
    uploadZone.addEventListener('dragleave', function() { uploadZone.classList.remove('dragover'); });
    uploadZone.addEventListener('drop', function(e) {
        e.preventDefault();
        uploadZone.classList.remove('dragover');
        if (e.dataTransfer.files.length) showUploadModal(e.dataTransfer.files[0]);
    });
}

if (mainUploadFile) {
    mainUploadFile.addEventListener('change', function() {
        if (this.files.length) showUploadModal(this.files[0]);
        this.value = '';
    });
}

function showUploadModal(file) {
    if (!nickname) { checkNickname(); return; }
    pendingUploadFile = file;
    var preview = document.getElementById('uploadPreview');
    if (file.type.startsWith('image/')) {
        preview.src = URL.createObjectURL(file);
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }
    document.getElementById('uploadTitle').value = '';
    document.getElementById('uploadProgress').style.display = 'none';
    document.getElementById('uploadModal').classList.add('show');
}

document.getElementById('uploadCancel').addEventListener('click', function() {
    document.getElementById('uploadModal').classList.remove('show');
    pendingUploadFile = null;
});

document.getElementById('uploadSubmit').addEventListener('click', function() {
    if (!pendingUploadFile) return;
    var title = document.getElementById('uploadTitle').value.trim();
    var progress = document.getElementById('uploadProgress');
    var progressBar = document.getElementById('uploadProgressBar');
    progress.style.display = 'block';

    var fd = new FormData();
    fd.append('media', pendingUploadFile);
    fd.append('title', title);
    fd.append('nickname', nickname);

    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'index.php?action=upload');
    xhr.upload.onprogress = function(e) {
        if (e.lengthComputable) {
            progressBar.style.width = Math.round((e.loaded / e.total) * 100) + '%';
        }
    };
    xhr.onload = function() {
        document.getElementById('uploadModal').classList.remove('show');
        pendingUploadFile = null;
        if (currentPage === 'home') loadHomepage();
        else if (currentPage === 'images') loadImages(imagesPage);
        else if (currentPage === 'videos') loadVideos(videosPage);
    };
    xhr.onerror = function() {
        alert('שגיאה בהעלאה');
        progress.style.display = 'none';
    };
    xhr.send(fd);
});

// ========================================
// CHAT
// ========================================
async function loadChat() {
    try {
        var data = await api('get_chat');
        var container = document.getElementById('chatMessages');
        if (!data.messages || data.messages.length === 0) {
            container.innerHTML = '<div style="text-align:center;color:#999;padding:20px;font-size:0.85rem;">הצ\'אט ריק - היו הראשונים לכתוב!</div>';
            return;
        }
        var wasAtBottom = container.scrollTop >= container.scrollHeight - container.clientHeight - 50;
        var prevCount = container.children.length;

        container.innerHTML = data.messages.map(function(msg) {
            var isMine = msg.nickname === nickname;
            var html = '<div class="chat-msg ' + (isMine ? 'mine' : 'other') + '">';
            if (!isMine) html += '<div class="chat-nickname">' + escHtml(msg.nickname) + '</div>';
            if (msg.image_url) html += '<img class="chat-image" src="' + escAttr(msg.image_url) + '" onclick="event.stopPropagation();openLightbox(\'' + escAttr(msg.image_url) + '\')" alt="">';
            if (msg.message) html += '<div>' + escHtml(msg.message) + '</div>';
            html += '<div class="chat-time">' + escHtml(msg.time_ago || '') + '</div>';
            html += '</div>';
            return html;
        }).join('');

        if (wasAtBottom || container.children.length !== prevCount) {
            container.scrollTop = container.scrollHeight;
        }
    } catch(err) {}
}

document.getElementById('chatSendBtn').addEventListener('click', sendChat);
document.getElementById('chatInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') sendChat();
});

async function sendChat() {
    if (!nickname) { checkNickname(); return; }
    var input = document.getElementById('chatInput');
    var msg = input.value.trim();
    if (!msg) return;
    input.value = '';

    var fd = new FormData();
    fd.append('nickname', nickname);
    fd.append('message', msg);

    try {
        await apiPost('send_chat', fd);
        loadChat();
    } catch(err) {}
}

// Chat image upload
document.getElementById('chatImageInput').addEventListener('change', async function() {
    if (!this.files.length) return;
    if (!nickname) { checkNickname(); this.value = ''; return; }
    var file = this.files[0];
    this.value = '';

    var fd = new FormData();
    fd.append('nickname', nickname);
    fd.append('message', '');
    fd.append('image', file);

    try {
        await apiPost('send_chat', fd);
        loadChat();
        if (currentPage === 'home') setTimeout(loadHomepage, 500);
    } catch(err) {}
});

// Start chat polling
function startChatPolling() {
    loadChat();
    chatPollTimer = setInterval(loadChat, 4000);
}

// ========================================
// SEARCH
// ========================================
document.getElementById('searchInput').addEventListener('input', function() {
    var q = this.value.trim().toLowerCase();
    var cards = document.querySelectorAll('.image-card');
    cards.forEach(function(card) {
        var title = card.querySelector('.card-title');
        if (!q || (title && title.textContent.toLowerCase().indexOf(q) !== -1)) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
});

// ========================================
// CHAT TOGGLE (MOBILE)
// ========================================
document.getElementById('chatToggle').addEventListener('click', function() {
    document.getElementById('chatSidebar').classList.toggle('open');
});

document.addEventListener('click', function(e) {
    if (window.innerWidth <= 1024) {
        var sidebar = document.getElementById('chatSidebar');
        var toggle = document.getElementById('chatToggle');
        if (!sidebar.contains(e.target) && !toggle.contains(e.target) && sidebar.classList.contains('open')) {
            sidebar.classList.remove('open');
        }
    }
});

// ========================================
// INIT
// ========================================
checkNickname();
startChatPolling();

if (currentPage === 'home') loadHomepage();
else if (currentPage === 'images') loadImages(1);
else if (currentPage === 'videos') loadVideos(1);
</script>
</body>
</html>
