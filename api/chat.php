<?php
/**
 * MediaHub - Chat API
 *
 * GET  ?after=X    - Get chat messages newer than ID X (or all recent if 0)
 *      &limit=50   - Number of messages to fetch
 * POST             - Send a new chat message (with optional image)
 *                    If image is attached, a post is also created automatically
 */

require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

// ===== GET - FETCH MESSAGES =====
if ($method === 'GET') {

    $db = getDB();
    $afterId = max(0, (int)($_GET['after'] ?? 0));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));

    if ($afterId > 0) {
        // Get messages after a certain ID
        $stmt = $db->prepare("
            SELECT cm.*, u.username, u.display_name, u.avatar
            FROM chat_messages cm
            JOIN users u ON cm.user_id = u.id
            WHERE cm.id > ?
            ORDER BY cm.id ASC
            LIMIT ?
        ");
        $stmt->bindValue(1, $afterId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        // Get most recent messages
        $stmt = $db->prepare("
            SELECT cm.*, u.username, u.display_name, u.avatar
            FROM chat_messages cm
            JOIN users u ON cm.user_id = u.id
            ORDER BY cm.id DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
    }

    $messages = $stmt->fetchAll();

    // If we got the latest (no afterId), reverse so oldest first
    if ($afterId === 0) {
        $messages = array_reverse($messages);
    }

    // Add time_ago and clean up
    foreach ($messages as &$msg) {
        $msg['time_ago'] = timeAgo($msg['created_at']);
    }
    unset($msg);

    jsonResponse([
        'messages' => $messages,
        'count' => count($messages),
    ]);
}

// ===== POST - SEND MESSAGE =====
if ($method === 'POST') {

    if (!isLoggedIn()) {
        jsonResponse(['error' => 'יש להתחבר תחילה'], 401);
    }

    // CSRF check
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrf($token)) {
        jsonResponse(['error' => 'CSRF token invalid'], 403);
    }

    $message = trim($_POST['message'] ?? '');
    $hasImage = isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK;

    if (empty($message) && !$hasImage) {
        jsonResponse(['error' => 'יש להזין הודעה או לצרף תמונה'], 400);
    }

    if (empty($message)) {
        $message = '(תמונה)';
    }

    $user = currentUser();
    $db = getDB();
    $mediaUrl = null;
    $postId = null;

    try {
        $db->beginTransaction();

        // Handle image upload
        if ($hasImage) {
            $result = handleUpload($_FILES['image'], 'chat');
            if ($result) {
                $mediaUrl = $result['path'];

                // Also create a post/article when an image is sent via chat
                $postStmt = $db->prepare("
                    INSERT INTO posts (user_id, title, body, type, media_url, thumbnail_url, views, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
                ");
                $postTitle = mb_strlen($message) > 100 ? mb_substr($message, 0, 100) . '...' : $message;
                if ($postTitle === '(תמונה)') $postTitle = 'שיתוף מהצ\'אט';

                $postStmt->execute([
                    $user['id'],
                    $postTitle,
                    $message,
                    $result['type'],
                    $result['path'],
                    $result['thumb'],
                ]);
                $postId = (int)$db->lastInsertId();
            }
        }

        // Insert chat message
        $stmt = $db->prepare("
            INSERT INTO chat_messages (user_id, message, media_url, post_id, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $user['id'],
            $message,
            $mediaUrl,
            $postId,
        ]);

        $messageId = (int)$db->lastInsertId();

        $db->commit();

        jsonResponse([
            'success' => true,
            'message_id' => $messageId,
            'post_id' => $postId,
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['error' => 'שגיאה בשליחת ההודעה'], 500);
    }
}

// Unsupported method
jsonResponse(['error' => 'Method not allowed'], 405);
