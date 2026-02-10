<?php
/**
 * MediaHub - Admin Tag Management Page
 * Add and delete tags (admin only)
 */

require_once __DIR__ . '/../includes/config.php';

// Check admin access
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../index.php');
    exit;
}

$user = currentUser();
$csrf = csrfToken();
$db = getDB();

// Handle POST actions
$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrf($token)) {
        $errorMsg = 'CSRF token invalid';
    } else {
        $action = $_POST['action'] ?? '';

        // ADD TAG
        if ($action === 'add') {
            $name = trim($_POST['name'] ?? '');
            $slug = trim($_POST['slug'] ?? '');

            if (empty($name)) {
                $errorMsg = 'יש להזין שם לתגית';
            } else {
                // Auto-generate slug if empty
                if (empty($slug)) {
                    $slug = preg_replace('/[^a-zA-Z0-9\-]/', '', str_replace(' ', '-', strtolower($name)));
                    if (empty($slug)) {
                        $slug = 'tag-' . time();
                    }
                }

                try {
                    $stmt = $db->prepare("INSERT INTO tags (name, slug) VALUES (?, ?)");
                    $stmt->execute([$name, $slug]);
                    $successMsg = 'התגית "' . e($name) . '" נוספה בהצלחה';
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        $errorMsg = 'תגית עם שם או slug זהה כבר קיימת';
                    } else {
                        $errorMsg = 'שגיאה בהוספת התגית';
                    }
                }
            }
        }

        // DELETE TAG
        if ($action === 'delete') {
            $tagId = (int)($_POST['tag_id'] ?? 0);
            if ($tagId > 0) {
                try {
                    $stmt = $db->prepare("DELETE FROM tags WHERE id = ?");
                    $stmt->execute([$tagId]);
                    if ($stmt->rowCount() > 0) {
                        $successMsg = 'התגית נמחקה בהצלחה';
                    } else {
                        $errorMsg = 'התגית לא נמצאה';
                    }
                } catch (PDOException $e) {
                    $errorMsg = 'שגיאה במחיקת התגית';
                }
            }
        }
    }
}

// Fetch all tags with counts
$tags = $db->query("
    SELECT t.*,
        (SELECT COUNT(*) FROM post_tags pt WHERE pt.tag_id = t.id) AS post_count
    FROM tags t
    ORDER BY t.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ניהול תגיות - <?= SITE_NAME ?></title>
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --primary: #1a56db;
            --primary-dark: #1040a0;
            --primary-light: #3b82f6;
            --bg: #f0f4f8;
            --bg-white: #ffffff;
            --text: #1e293b;
            --text-secondary: #64748b;
            --text-light: #94a3b8;
            --border: #e2e8f0;
            --shadow: 0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.08), 0 2px 4px -2px rgba(0,0,0,0.06);
            --radius: 10px;
            --font: 'Segoe UI', Tahoma, Arial, sans-serif;
        }

        body {
            font-family: var(--font);
            background: var(--bg);
            color: var(--text);
            direction: rtl;
            min-height: 100vh;
        }

        /* Header */
        .admin-header {
            background: linear-gradient(135deg, #1a237e 0%, #1565c0 40%, #0288d1 100%);
            padding: 18px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 12px rgba(0,0,0,0.15);
        }

        .admin-header h1 {
            color: #fff;
            font-size: 20px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .admin-header a {
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 8px;
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.25);
            transition: 0.2s;
        }

        .admin-header a:hover {
            background: rgba(255,255,255,0.25);
            color: #fff;
        }

        /* Container */
        .container {
            max-width: 800px;
            margin: 30px auto;
            padding: 0 20px;
        }

        /* Card */
        .card {
            background: var(--bg-white);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 24px;
            overflow: hidden;
            border: 1px solid #f1f5f9;
        }

        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            font-size: 17px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-body {
            padding: 20px;
        }

        /* Alert */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
            font-weight: 600;
        }

        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* Form */
        .form-row {
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }

        .form-group {
            flex: 1;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 6px;
        }

        .form-input {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 14px;
            font-family: var(--font);
            transition: 0.2s;
            direction: rtl;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: 0.2s;
            font-family: var(--font);
            white-space: nowrap;
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-danger {
            background: #ef4444;
            color: #fff;
            padding: 6px 14px;
            font-size: 13px;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        /* Table */
        .tags-table {
            width: 100%;
            border-collapse: collapse;
        }

        .tags-table th {
            background: #f8fafc;
            text-align: right;
            padding: 12px 14px;
            font-size: 13px;
            font-weight: 700;
            color: var(--text-secondary);
            border-bottom: 2px solid var(--border);
        }

        .tags-table td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
            vertical-align: middle;
        }

        .tags-table tr:last-child td {
            border-bottom: none;
        }

        .tags-table tr:hover td {
            background: #f8fafc;
        }

        .tag-name-cell {
            font-weight: 700;
        }

        .tag-slug-cell {
            color: var(--text-light);
            font-family: monospace;
            font-size: 13px;
            direction: ltr;
            text-align: right;
        }

        .tag-count-cell {
            text-align: center;
        }

        .tag-count-badge {
            background: #eff6ff;
            color: var(--primary);
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 700;
        }

        .tag-actions-cell {
            text-align: left;
            width: 100px;
        }

        .empty-row td {
            text-align: center;
            color: var(--text-light);
            padding: 30px;
        }

        /* Confirm dialog */
        .confirm-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.4);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .confirm-overlay.active { display: flex; }

        .confirm-box {
            background: var(--bg-white);
            border-radius: var(--radius);
            padding: 24px;
            max-width: 400px;
            width: 100%;
            box-shadow: var(--shadow-md);
            text-align: center;
        }

        .confirm-box p {
            margin-bottom: 18px;
            font-size: 15px;
            font-weight: 600;
        }

        .confirm-box .btns {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .btn-cancel {
            background: var(--bg);
            color: var(--text);
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            border: 1px solid var(--border);
            cursor: pointer;
            font-family: var(--font);
        }

        @media (max-width: 600px) {
            .form-row { flex-direction: column; }
            .container { padding: 0 12px; }
            .admin-header { padding: 14px 16px; }
        }
    </style>
</head>
<body>

<div class="admin-header">
    <h1>&#9881; ניהול תגיות</h1>
    <a href="../index.php">&rarr; חזרה לאתר</a>
</div>

<div class="container">

    <?php if ($successMsg): ?>
    <div class="alert alert-success">&#10003; <?= e($successMsg) ?></div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
    <div class="alert alert-error">&#10007; <?= e($errorMsg) ?></div>
    <?php endif; ?>

    <!-- Add Tag Form -->
    <div class="card">
        <div class="card-header">&#10010; הוספת תגית חדשה</div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="add">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">שם התגית (עברית)</label>
                        <input type="text" name="name" class="form-input" placeholder="לדוגמה: חדשות" required maxlength="100">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Slug (אנגלית, אופציונלי)</label>
                        <input type="text" name="slug" class="form-input" placeholder="news" maxlength="100" dir="ltr" style="text-align:left;">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary">&#10010; הוסף</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Tags List -->
    <div class="card">
        <div class="card-header">&#127991; רשימת תגיות (<?= count($tags) ?>)</div>
        <div class="card-body" style="padding:0;">
            <table class="tags-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>שם</th>
                        <th>Slug</th>
                        <th style="text-align:center;">פוסטים</th>
                        <th style="text-align:left;">פעולות</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tags)): ?>
                    <tr class="empty-row">
                        <td colspan="5">אין תגיות. הוסף תגית חדשה למעלה.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($tags as $i => $tag): ?>
                    <tr>
                        <td style="color:var(--text-light);"><?= $tag['id'] ?></td>
                        <td class="tag-name-cell"><?= e($tag['name']) ?></td>
                        <td class="tag-slug-cell"><?= e($tag['slug']) ?></td>
                        <td class="tag-count-cell">
                            <span class="tag-count-badge"><?= (int)$tag['post_count'] ?></span>
                        </td>
                        <td class="tag-actions-cell">
                            <button class="btn btn-danger" onclick="confirmDelete(<?= $tag['id'] ?>, '<?= e($tag['name']) ?>')">&#128465; מחק</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Delete Confirmation -->
<div class="confirm-overlay" id="confirmOverlay">
    <div class="confirm-box">
        <p>&#9888; האם למחוק את התגית "<span id="confirmTagName"></span>"?</p>
        <p style="font-size:12px; color:var(--text-light); margin-top:-10px; margin-bottom:16px;">פעולה זו תסיר את התגית מכל הפוסטים המשויכים</p>
        <form method="POST" action="" id="deleteForm">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="tag_id" id="deleteTagId" value="">
            <div class="btns">
                <button type="submit" class="btn btn-danger">&#128465; מחק</button>
                <button type="button" class="btn-cancel" onclick="closeConfirm()">ביטול</button>
            </div>
        </form>
    </div>
</div>

<script>
function confirmDelete(id, name) {
    document.getElementById('deleteTagId').value = id;
    document.getElementById('confirmTagName').textContent = name;
    document.getElementById('confirmOverlay').classList.add('active');
}

function closeConfirm() {
    document.getElementById('confirmOverlay').classList.remove('active');
}

document.getElementById('confirmOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeConfirm();
});
</script>

</body>
</html>
