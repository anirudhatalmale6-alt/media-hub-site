<?php
/**
 * MediaHub - Posts API
 *
 * GET  ?id=X           - Get single post (increments views)
 * GET  ?sort=recent    - Get posts sorted by newest
 * GET  ?sort=views     - Get posts sorted by most viewed
 *      &type=image|video - Filter by media type
 *      &tag=X          - Filter by tag ID
 *      &q=search       - Search by title
 *      &page=0         - Pagination page number
 *      &limit=8        - Items per page
 * POST                 - Create new post with file upload
 */

require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

// ===== GET REQUESTS =====
if ($method === 'GET') {

    $db = getDB();

    // --- Single post by ID ---
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];

        // Increment views
        $db->prepare("UPDATE posts SET views = views + 1 WHERE id = ?")->execute([$id]);

        $stmt = $db->prepare("
            SELECT p.*, u.username, u.display_name, u.avatar
            FROM posts p
            JOIN users u ON p.user_id = u.id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        $post = $stmt->fetch();

        if (!$post) {
            jsonResponse(['error' => 'פוסט לא נמצא'], 404);
        }

        // Get tags
        $tagStmt = $db->prepare("
            SELECT t.id, t.name, t.slug
            FROM tags t
            JOIN post_tags pt ON t.id = pt.tag_id
            WHERE pt.post_id = ?
        ");
        $tagStmt->execute([$id]);
        $post['tags'] = $tagStmt->fetchAll();
        $post['time_ago'] = timeAgo($post['created_at']);

        jsonResponse(['post' => $post]);
    }

    // --- List posts ---
    $sort = $_GET['sort'] ?? 'recent';
    $type = $_GET['type'] ?? null;
    $tagId = $_GET['tag'] ?? null;
    $search = $_GET['q'] ?? null;
    $page = max(0, (int)($_GET['page'] ?? 0));
    $limit = min(50, max(1, (int)($_GET['limit'] ?? ITEMS_PER_PAGE)));
    $offset = $page * $limit;

    $where = [];
    $params = [];

    // Type filter
    if ($type && in_array($type, ['image', 'video'])) {
        $where[] = "p.type = ?";
        $params[] = $type;
    }

    // Tag filter
    if ($tagId && is_numeric($tagId)) {
        $where[] = "p.id IN (SELECT post_id FROM post_tags WHERE tag_id = ?)";
        $params[] = (int)$tagId;
    }

    // Search filter
    if ($search) {
        $where[] = "(p.title LIKE ? OR p.body LIKE ?)";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

    // Sort
    $orderBy = $sort === 'views' ? 'p.views DESC, p.created_at DESC' : 'p.created_at DESC';

    $sql = "
        SELECT p.*, u.username, u.display_name, u.avatar
        FROM posts p
        JOIN users u ON p.user_id = u.id
        $whereClause
        ORDER BY $orderBy
        LIMIT $limit OFFSET $offset
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $posts = $stmt->fetchAll();

    // Get tags for each post
    if (!empty($posts)) {
        $postIds = array_column($posts, 'id');
        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $tagStmt = $db->prepare("
            SELECT pt.post_id, t.id, t.name, t.slug
            FROM tags t
            JOIN post_tags pt ON t.id = pt.tag_id
            WHERE pt.post_id IN ($placeholders)
        ");
        $tagStmt->execute($postIds);
        $allTags = $tagStmt->fetchAll();

        $tagsByPost = [];
        foreach ($allTags as $tag) {
            $tagsByPost[$tag['post_id']][] = ['id' => $tag['id'], 'name' => $tag['name'], 'slug' => $tag['slug']];
        }

        foreach ($posts as &$post) {
            $post['tags'] = $tagsByPost[$post['id']] ?? [];
            $post['time_ago'] = timeAgo($post['created_at']);
        }
        unset($post);
    }

    // Get total count for pagination
    $countSql = "SELECT COUNT(*) FROM posts p $whereClause";
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    jsonResponse([
        'posts' => $posts,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'has_more' => ($offset + $limit) < $total,
    ]);
}

// ===== POST REQUEST - CREATE NEW POST =====
if ($method === 'POST') {

    if (!isLoggedIn()) {
        jsonResponse(['error' => 'יש להתחבר תחילה'], 401);
    }

    // CSRF check
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrf($token)) {
        jsonResponse(['error' => 'CSRF token invalid'], 403);
    }

    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $tags = $_POST['tags'] ?? [];

    if (empty($title)) {
        jsonResponse(['error' => 'יש להזין כותרת'], 400);
    }

    // Handle file upload
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['error' => 'יש לבחור קובץ להעלאה'], 400);
    }

    $file = $_FILES['file'];
    $result = handleUpload($file);

    if (!$result) {
        jsonResponse(['error' => 'סוג הקובץ אינו נתמך או שהקובץ גדול מדי'], 400);
    }

    $user = currentUser();
    $db = getDB();

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("
            INSERT INTO posts (user_id, title, body, type, media_url, thumbnail_url, views, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
        ");
        $stmt->execute([
            $user['id'],
            $title,
            $body,
            $result['type'],
            $result['path'],
            $result['thumb'],
        ]);

        $postId = (int)$db->lastInsertId();

        // Attach tags
        if (!empty($tags) && is_array($tags)) {
            $tagStmt = $db->prepare("INSERT IGNORE INTO post_tags (post_id, tag_id) VALUES (?, ?)");
            foreach ($tags as $tagId) {
                $tagId = (int)$tagId;
                if ($tagId > 0) {
                    $tagStmt->execute([$postId, $tagId]);
                }
            }
        }

        $db->commit();

        jsonResponse([
            'success' => true,
            'post_id' => $postId,
            'message' => 'התוכן הועלה בהצלחה',
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['error' => 'שגיאה בשמירת התוכן'], 500);
    }
}

// Unsupported method
jsonResponse(['error' => 'Method not allowed'], 405);
