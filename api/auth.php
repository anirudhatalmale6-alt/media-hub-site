<?php
/**
 * MediaHub - Authentication API
 *
 * POST action=login     - Login with username/password
 * POST action=logout    - Logout current session
 * GET                   - Get current user info
 */

require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

// ===== GET - Current User Info =====
if ($method === 'GET') {
    if (isLoggedIn()) {
        $user = currentUser();
        jsonResponse([
            'logged_in' => true,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'display_name' => $user['display_name'],
                'role' => $user['role'],
                'avatar' => $user['avatar'] ?? null,
            ],
        ]);
    } else {
        jsonResponse(['logged_in' => false]);
    }
}

// ===== POST - Login / Logout =====
if ($method === 'POST') {

    $action = $_POST['action'] ?? '';

    // --- LOGOUT ---
    if ($action === 'logout') {
        unset($_SESSION['user']);
        session_destroy();
        jsonResponse(['success' => true, 'message' => 'התנתקת בהצלחה']);
    }

    // --- LOGIN ---
    if ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            jsonResponse(['error' => 'יש למלא את כל השדות'], 400);
        }

        $db = getDB();

        // Find user
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user) {
            jsonResponse(['error' => 'שם משתמש או סיסמה שגויים'], 401);
        }

        // Verify password
        if (!password_verify($password, $user['password'])) {
            jsonResponse(['error' => 'שם משתמש או סיסמה שגויים'], 401);
        }

        // Set session
        $_SESSION['user'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'display_name' => $user['display_name'],
            'role' => $user['role'],
            'avatar' => $user['avatar'],
        ];

        // Regenerate session ID for security
        session_regenerate_id(true);

        jsonResponse([
            'success' => true,
            'message' => 'התחברת בהצלחה',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'display_name' => $user['display_name'],
                'role' => $user['role'],
            ],
        ]);
    }

    jsonResponse(['error' => 'פעולה לא חוקית'], 400);
}

// Unsupported method
jsonResponse(['error' => 'Method not allowed'], 405);
