<?php
/**
 * MediaHub - Tags API
 *
 * GET  - Get all tags with post counts
 */

require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

// ===== GET - List all tags =====
if ($method === 'GET') {

    $db = getDB();

    $stmt = $db->query("
        SELECT t.id, t.name, t.slug,
            (SELECT COUNT(*) FROM post_tags pt WHERE pt.tag_id = t.id) AS post_count
        FROM tags t
        ORDER BY t.name ASC
    ");

    $tags = $stmt->fetchAll();

    jsonResponse([
        'tags' => $tags,
        'count' => count($tags),
    ]);
}

// Unsupported method
jsonResponse(['error' => 'Method not allowed'], 405);
