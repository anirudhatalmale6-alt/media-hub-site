<?php
/**
 * MediaHub - Hebrew Media Sharing Site
 * JDN.co.il inspired editorial design
 * File-based JSON storage - No database
 */

define('DATA_DIR', __DIR__ . '/data/');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', 'uploads/');
define('MAX_IMAGE_SIZE', 10 * 1024 * 1024);
define('MAX_VIDEO_SIZE', 100 * 1024 * 1024);
define('ITEMS_PER_PAGE', 20);

if (!file_exists(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
if (!file_exists(DATA_DIR . 'posts.json')) file_put_contents(DATA_DIR . 'posts.json', '[]');
if (!file_exists(DATA_DIR . 'chat.json')) file_put_contents(DATA_DIR . 'chat.json', '[]');
if (!file_exists(UPLOAD_DIR . 'images/')) mkdir(UPLOAD_DIR . 'images/', 0755, true);
if (!file_exists(UPLOAD_DIR . 'videos/')) mkdir(UPLOAD_DIR . 'videos/', 0755, true);
if (!file_exists(UPLOAD_DIR . 'thumbs/')) mkdir(UPLOAD_DIR . 'thumbs/', 0755, true);

function loadJson($file) {
    $path = DATA_DIR . $file;
    if (!file_exists($path)) return [];
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function saveJson($file, $data) {
    file_put_contents(DATA_DIR . $file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

function e($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }

function timeAgo($ts) {
    $d = time() - $ts;
    if ($d < 60) return "הרגע";
    if ($d < 3600) return "לפני " . floor($d / 60) . " דקות";
    if ($d < 86400) return "לפני " . floor($d / 3600) . " שעות";
    if ($d < 604800) return "לפני " . floor($d / 86400) . " ימים";
    return date('d/m/Y', $ts);
}

function createThumb($source, $dest, $maxW) {
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
    $ow = imagesx($img); $oh = imagesy($img);
    if ($ow <= $maxW) { copy($source, $dest); imagedestroy($img); return true; }
    $r = $maxW / $ow; $nw = $maxW; $nh = (int)($oh * $r);
    $t = imagecreatetruecolor($nw, $nh);
    imagealphablending($t, false); imagesavealpha($t, true);
    imagecopyresampled($t, $img, 0, 0, 0, 0, $nw, $nh, $ow, $oh);
    imagejpeg($t, $dest, 85);
    imagedestroy($img); imagedestroy($t);
    return true;
}

// API
$action = $_GET['action'] ?? '';
if ($action) {
    header('Content-Type: application/json; charset=utf-8');

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
        foreach ($items as &$item) $item['time_ago'] = timeAgo($item['created_at'] ?? 0);
        echo json_encode(['posts' => $items, 'total' => $total, 'page' => $page, 'pages' => max(1, ceil($total / $limit))], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'get_recent') {
        $posts = loadJson('posts.json');
        usort($posts, fn($a, $b) => ($b['created_at'] ?? 0) - ($a['created_at'] ?? 0));
        $images = array_values(array_filter($posts, fn($p) => ($p['type'] ?? '') === 'image'));
        $recent = array_slice($images, 0, 10);
        foreach ($recent as &$item) $item['time_ago'] = timeAgo($item['created_at'] ?? 0);
        echo json_encode(['posts' => $recent], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_FILES['media']) || $_FILES['media']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['error' => 'שגיאה בהעלאת הקובץ'], JSON_UNESCAPED_UNICODE); exit;
        }
        $file = $_FILES['media'];
        $mime = mime_content_type($file['tmp_name']);
        $allowedImg = ['image/jpeg','image/png','image/gif','image/webp'];
        $allowedVid = ['video/mp4','video/webm','video/ogg'];
        $isImg = in_array($mime, $allowedImg); $isVid = in_array($mime, $allowedVid);
        if (!$isImg && !$isVid) { echo json_encode(['error' => 'סוג קובץ לא נתמך'], JSON_UNESCAPED_UNICODE); exit; }
        if ($isImg && $file['size'] > MAX_IMAGE_SIZE) { echo json_encode(['error' => 'תמונה גדולה מדי'], JSON_UNESCAPED_UNICODE); exit; }
        if ($isVid && $file['size'] > MAX_VIDEO_SIZE) { echo json_encode(['error' => 'סרטון גדול מדי'], JSON_UNESCAPED_UNICODE); exit; }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $fn = uniqid('media_', true) . '.' . $ext;
        $sub = $isImg ? 'images/' : 'videos/';
        $tp = UPLOAD_DIR . $sub . $fn;
        if (!move_uploaded_file($file['tmp_name'], $tp)) { echo json_encode(['error' => 'שגיאה בשמירה'], JSON_UNESCAPED_UNICODE); exit; }
        $mu = UPLOAD_URL . $sub . $fn; $tu = $mu;
        if ($isImg && function_exists('imagecreatefromjpeg')) {
            $tf = UPLOAD_DIR . 'thumbs/' . $fn;
            if (createThumb($tp, $tf, 400)) $tu = UPLOAD_URL . 'thumbs/' . $fn;
        }
        $title = trim($_POST['title'] ?? '');
        $nick = trim($_POST['nickname'] ?? 'אנונימי');
        if (!$title) $title = $isImg ? 'תמונה חדשה' : 'סרטון חדש';
        $post = ['id'=>uniqid('p_'),'title'=>$title,'type'=>$isImg?'image':'video','media_url'=>$mu,'thumb_url'=>$tu,'nickname'=>$nick,'created_at'=>time()];
        $posts = loadJson('posts.json'); $posts[] = $post; saveJson('posts.json', $posts);
        $post['time_ago'] = timeAgo($post['created_at']);
        echo json_encode(['success'=>true,'post'=>$post], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($action === 'get_chat') {
        $chat = loadJson('chat.json');
        usort($chat, fn($a, $b) => ($a['created_at'] ?? 0) - ($b['created_at'] ?? 0));
        $last = array_slice($chat, -50);
        foreach ($last as &$m) $m['time_ago'] = timeAgo($m['created_at'] ?? 0);
        echo json_encode(['messages' => $last], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($action === 'send_chat' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $nick = trim($_POST['nickname'] ?? 'אנונימי');
        $msg = trim($_POST['message'] ?? '');
        $hasImg = isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK;
        if (!$msg && !$hasImg) { echo json_encode(['error' => 'הודעה ריקה'], JSON_UNESCAPED_UNICODE); exit; }
        $imgUrl = null;
        if ($hasImg) {
            $file = $_FILES['image'];
            $mime = mime_content_type($file['tmp_name']);
            if (in_array($mime, ['image/jpeg','image/png','image/gif','image/webp']) && $file['size'] <= MAX_IMAGE_SIZE) {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $fn = uniqid('chat_', true) . '.' . $ext;
                $tp = UPLOAD_DIR . 'images/' . $fn;
                if (move_uploaded_file($file['tmp_name'], $tp)) {
                    $imgUrl = UPLOAD_URL . 'images/' . $fn;
                    $tu = $imgUrl;
                    if (function_exists('imagecreatefromjpeg')) {
                        $tf = UPLOAD_DIR . 'thumbs/' . $fn;
                        if (createThumb($tp, $tf, 400)) $tu = UPLOAD_URL . 'thumbs/' . $fn;
                    }
                    $post = ['id'=>uniqid('p_'),'title'=>$msg?:'תמונה מהצ\'אט','type'=>'image','media_url'=>$imgUrl,'thumb_url'=>$tu,'nickname'=>$nick,'created_at'=>time()];
                    $posts = loadJson('posts.json'); $posts[] = $post; saveJson('posts.json', $posts);
                }
            }
        }
        $cm = ['id'=>uniqid('c_'),'nickname'=>$nick,'message'=>$msg,'image_url'=>$imgUrl,'created_at'=>time()];
        $chat = loadJson('chat.json'); $chat[] = $cm;
        if (count($chat) > 500) $chat = array_slice($chat, -500);
        saveJson('chat.json', $chat);
        $cm['time_ago'] = timeAgo($cm['created_at']);
        echo json_encode(['success'=>true,'message'=>$cm], JSON_UNESCAPED_UNICODE); exit;
    }

    echo json_encode(['error'=>'Unknown action'], JSON_UNESCAPED_UNICODE); exit;
}

$page = $_GET['page'] ?? 'home';
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>מדיה שיתוף - MediaHub</title>
<link href="https://fonts.googleapis.com/css2?family=Heebo:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}

:root{
  --white:#ffffff;
  --off-white:#f8f8f8;
  --bg:#f2f2f2;
  --card:#ffffff;
  --text:#1a1a1a;
  --text-secondary:#666;
  --text-muted:#999;
  --border:#e5e5e5;
  --border-light:#eee;
  --red:#d32f2f;
  --red-dark:#b71c1c;
  --red-light:#fce4ec;
  --blue:#1565c0;
  --blue-light:#e3f2fd;
  --accent:#d32f2f;
  --shadow-sm:0 1px 3px rgba(0,0,0,0.06);
  --shadow:0 2px 8px rgba(0,0,0,0.08);
  --shadow-lg:0 4px 20px rgba(0,0,0,0.12);
  --radius:6px;
  --header-h:56px;
  --chat-w:320px;
  --font:'Heebo',sans-serif;
}

html{font-size:15px}
body{font-family:var(--font);background:var(--bg);color:var(--text);line-height:1.6;min-height:100vh}

/* Scrollbar */
::-webkit-scrollbar{width:5px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:#ccc;border-radius:3px}

/* ─── HEADER ─── */
.header{
  background:var(--white);
  height:var(--header-h);
  position:fixed;
  top:0;left:0;right:0;
  z-index:1000;
  border-bottom:1px solid var(--border);
  box-shadow:var(--shadow-sm);
}
.header-inner{
  max-width:1400px;margin:0 auto;height:100%;
  display:flex;align-items:center;justify-content:space-between;
  padding:0 20px;
}
.logo{
  font-size:1.5rem;font-weight:900;color:var(--red);
  text-decoration:none;letter-spacing:-1px;
  display:flex;align-items:center;gap:6px;
}
.logo span{
  background:var(--red);color:var(--white);
  width:32px;height:32px;border-radius:4px;
  display:flex;align-items:center;justify-content:center;
  font-size:1.1rem;font-weight:800;
}

/* Nav */
.nav{display:flex;align-items:center;gap:0;list-style:none}
.nav a{
  color:var(--text);text-decoration:none;
  padding:16px 18px;font-weight:500;font-size:0.93rem;
  border-bottom:3px solid transparent;
  transition:all .2s;display:block;
  line-height:calc(var(--header-h) - 3px - 32px);
}
.nav a:hover{color:var(--red);border-bottom-color:var(--red)}
.nav a.active{color:var(--red);border-bottom-color:var(--red);font-weight:700}

/* Search */
.header-search{position:relative;width:200px}
.header-search input{
  width:100%;padding:7px 14px 7px 32px;
  background:var(--off-white);border:1px solid var(--border);
  border-radius:20px;font-family:var(--font);
  font-size:0.82rem;outline:none;color:var(--text);
  transition:all .2s;
}
.header-search input:focus{border-color:var(--red);background:var(--white)}
.header-search input::placeholder{color:var(--text-muted)}
.header-search svg{
  position:absolute;left:10px;top:50%;transform:translateY(-50%);
  width:14px;height:14px;stroke:var(--text-muted);fill:none;
  stroke-width:2;pointer-events:none;
}

/* ─── LAYOUT ─── */
.layout{
  display:flex;
  margin-top:var(--header-h);
  min-height:calc(100vh - var(--header-h));
}
.content{
  flex:1;
  padding:24px 28px;
  margin-left:var(--chat-w);
  max-width:calc(100% - var(--chat-w));
}

/* ─── SECTION BAR ─── */
.section-bar{
  display:flex;align-items:center;justify-content:space-between;
  margin-bottom:20px;padding-bottom:10px;
  border-bottom:2px solid var(--red);
}
.section-bar h2{
  font-size:1.15rem;font-weight:800;color:var(--text);
  position:relative;padding-right:0;
}
.section-bar a{
  color:var(--red);text-decoration:none;
  font-size:0.8rem;font-weight:500;
  transition:opacity .2s;
}
.section-bar a:hover{opacity:.7}

/* ─── CARD GRID ─── */
.grid{
  display:grid;
  grid-template-columns:repeat(auto-fill, minmax(260px, 1fr));
  gap:18px;
}

/* ─── CARD ─── */
.card{
  background:var(--card);
  border-radius:var(--radius);
  overflow:hidden;
  border:1px solid var(--border-light);
  transition:box-shadow .25s, transform .25s;
  cursor:pointer;
}
.card:hover{
  box-shadow:var(--shadow-lg);
  transform:translateY(-3px);
}

.card-img{
  width:100%;height:200px;
  object-fit:cover;display:block;
  background:var(--bg);
  transition:opacity .3s;
}
.card:hover .card-img{opacity:.92}

.card-body{padding:12px 14px 14px}
.card-title{
  font-size:0.95rem;font-weight:700;
  color:var(--text);line-height:1.4;
  margin-bottom:8px;
  display:-webkit-box;-webkit-line-clamp:2;
  -webkit-box-orient:vertical;overflow:hidden;
}
.card-meta{
  display:flex;align-items:center;
  justify-content:space-between;
  font-size:0.73rem;color:var(--text-muted);
}
.card-author{color:var(--blue);font-weight:500}

/* Video badge */
.card-video{position:relative}
.card-video .vid-badge{
  position:absolute;top:10px;left:10px;
  background:rgba(0,0,0,.7);color:#fff;
  padding:3px 8px;border-radius:4px;
  font-size:0.68rem;font-weight:600;
  display:flex;align-items:center;gap:3px;
}
.card-video .play-btn{
  position:absolute;top:50%;left:50%;
  transform:translate(-50%,-50%);
  width:48px;height:48px;background:rgba(255,255,255,.9);
  border-radius:50%;display:flex;align-items:center;justify-content:center;
  opacity:.85;transition:all .2s;
}
.card:hover .play-btn{opacity:1;transform:translate(-50%,-50%) scale(1.08)}
.play-btn svg{width:20px;height:20px;fill:var(--red);margin-right:-2px}

/* Featured hero card */
.grid-hero{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:18px;
  margin-bottom:18px;
}
.hero-card{
  background:var(--card);border-radius:var(--radius);
  overflow:hidden;border:1px solid var(--border-light);
  cursor:pointer;transition:box-shadow .25s;
  position:relative;
}
.hero-card:hover{box-shadow:var(--shadow-lg)}
.hero-card:first-child{grid-row:span 2}
.hero-card:first-child .hero-img{height:100%;min-height:380px}
.hero-card:first-child .hero-title{font-size:1.25rem}
.hero-img{width:100%;height:185px;object-fit:cover;display:block;background:var(--bg)}
.hero-overlay{
  position:absolute;bottom:0;left:0;right:0;
  background:linear-gradient(transparent,rgba(0,0,0,.65));
  padding:30px 16px 14px;
}
.hero-title{
  color:#fff;font-size:0.95rem;font-weight:700;
  line-height:1.35;text-shadow:0 1px 2px rgba(0,0,0,.3);
}
.hero-meta{
  display:flex;gap:10px;margin-top:5px;
  font-size:0.7rem;color:rgba(255,255,255,.8);
}

/* ─── UPLOAD ZONE ─── */
.upload-zone{
  background:var(--white);border:2px dashed var(--border);
  border-radius:var(--radius);padding:24px;
  text-align:center;margin-bottom:22px;
  cursor:pointer;transition:all .2s;
}
.upload-zone:hover,.upload-zone.over{
  border-color:var(--red);background:var(--red-light);
}
.upload-zone svg{width:36px;height:36px;stroke:var(--red);fill:none;stroke-width:1.5;margin-bottom:8px}
.upload-zone h3{font-size:0.95rem;font-weight:700;color:var(--text);margin-bottom:4px}
.upload-zone p{color:var(--text-muted);font-size:0.8rem}
.upload-zone input{display:none}

/* ─── CHAT ─── */
.chat{
  width:var(--chat-w);position:fixed;
  top:var(--header-h);left:0;bottom:0;
  background:var(--white);
  display:flex;flex-direction:column;
  z-index:900;border-right:1px solid var(--border);
}
.chat-head{
  padding:12px 16px;
  background:var(--white);
  border-bottom:1px solid var(--border);
  font-size:0.9rem;font-weight:700;
  display:flex;align-items:center;gap:8px;
  flex-shrink:0;
}
.chat-head .dot{
  width:7px;height:7px;background:#4caf50;
  border-radius:50%;animation:blink 2s infinite;
}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.4}}

.chat-msgs{
  flex:1;overflow-y:auto;padding:12px 10px;
  display:flex;flex-direction:column;gap:5px;
  background:var(--off-white);
}
.msg{
  max-width:82%;padding:7px 11px;
  border-radius:8px;font-size:0.82rem;
  line-height:1.45;word-wrap:break-word;
  box-shadow:0 1px 1px rgba(0,0,0,.06);
}
.msg.other{align-self:flex-start;background:var(--white);border:1px solid var(--border-light);border-top-right-radius:2px}
.msg.mine{align-self:flex-end;background:#dcf8c6;border-top-left-radius:2px}
.msg-nick{font-size:0.68rem;font-weight:700;color:var(--blue);margin-bottom:1px}
.msg img{max-width:100%;border-radius:6px;margin-top:4px;cursor:pointer}
.msg-time{font-size:0.6rem;color:var(--text-muted);text-align:left;margin-top:3px}

.chat-input{
  padding:8px 10px;background:var(--white);
  border-top:1px solid var(--border);
  display:flex;align-items:center;gap:7px;flex-shrink:0;
}
.chat-input input[type=text]{
  flex:1;padding:8px 12px;border:1px solid var(--border);
  border-radius:18px;font-family:var(--font);font-size:0.82rem;
  outline:none;background:var(--off-white);transition:border .2s;
}
.chat-input input:focus{border-color:var(--red)}
.cbtn{
  width:34px;height:34px;border-radius:50%;border:none;
  cursor:pointer;display:flex;align-items:center;justify-content:center;
  transition:all .15s;flex-shrink:0;background:transparent;
}
.cbtn svg{width:16px;height:16px;fill:none;stroke-width:2}
.cbtn-send{background:var(--red)}
.cbtn-send svg{stroke:#fff}
.cbtn-send:hover{background:var(--red-dark)}
.cbtn-img svg{stroke:var(--text-muted)}
.cbtn-img:hover svg{stroke:var(--red)}
.chat-input input[type=file]{display:none}

/* ─── MODALS ─── */
.overlay{
  position:fixed;inset:0;background:rgba(0,0,0,.45);
  z-index:2000;display:flex;align-items:center;justify-content:center;
  backdrop-filter:blur(2px);opacity:0;visibility:hidden;
  transition:all .25s;
}
.overlay.show{opacity:1;visibility:visible}
.modal{
  background:var(--white);border-radius:var(--radius);
  padding:28px 24px;width:340px;max-width:92vw;
  box-shadow:var(--shadow-lg);transform:translateY(15px);
  transition:transform .25s;text-align:center;
}
.overlay.show .modal{transform:translateY(0)}
.modal h2{font-size:1.15rem;margin-bottom:6px;font-weight:800}
.modal p{color:var(--text-secondary);font-size:0.85rem;margin-bottom:16px}
.modal input[type=text]{
  width:100%;padding:10px 14px;border:1px solid var(--border);
  border-radius:var(--radius);font-family:var(--font);
  font-size:0.95rem;text-align:center;outline:none;
  margin-bottom:12px;transition:border .2s;
}
.modal input:focus{border-color:var(--red)}
.modal .btn{
  width:100%;padding:10px;background:var(--red);
  color:#fff;border:none;border-radius:var(--radius);
  font-family:var(--font);font-size:0.95rem;font-weight:700;
  cursor:pointer;transition:background .2s;
}
.modal .btn:hover{background:var(--red-dark)}
.modal .btn-ghost{
  background:transparent;color:var(--text-muted);
  border:1px solid var(--border);margin-top:8px;font-weight:400;
}
.modal .btn-ghost:hover{background:var(--off-white)}

.upload-preview{
  width:100%;max-height:180px;object-fit:contain;
  border-radius:var(--radius);margin-bottom:10px;display:none;
}
.upload-bar{
  height:3px;background:var(--border);border-radius:2px;
  overflow:hidden;margin-bottom:10px;display:none;
}
.upload-bar-fill{height:100%;background:var(--red);width:0;transition:width .3s}

/* ─── LIGHTBOX ─── */
.lb{
  position:fixed;inset:0;background:rgba(0,0,0,.92);
  z-index:3000;display:flex;align-items:center;justify-content:center;
  opacity:0;visibility:hidden;transition:all .25s;cursor:pointer;
}
.lb.show{opacity:1;visibility:visible}
.lb img{max-width:90vw;max-height:90vh;border-radius:4px;cursor:default}
.lb-close{
  position:absolute;top:16px;right:16px;
  width:36px;height:36px;background:rgba(255,255,255,.15);
  border:none;border-radius:50%;color:#fff;font-size:1.2rem;
  cursor:pointer;display:flex;align-items:center;justify-content:center;
}
.lb-close:hover{background:rgba(255,255,255,.3)}

/* ─── PAGINATION ─── */
.pager{
  display:flex;align-items:center;justify-content:center;
  gap:5px;margin-top:28px;padding:16px 0;
}
.pager button{
  padding:6px 14px;border:1px solid var(--border);
  background:var(--white);border-radius:var(--radius);
  font-family:var(--font);font-size:0.82rem;cursor:pointer;
  transition:all .15s;color:var(--text);
}
.pager button:hover{border-color:var(--red);color:var(--red)}
.pager button.act{background:var(--red);color:#fff;border-color:var(--red)}
.pager button:disabled{opacity:.35;cursor:not-allowed}

/* ─── EMPTY ─── */
.empty{text-align:center;padding:50px 20px;color:var(--text-muted);grid-column:1/-1}
.empty svg{width:60px;height:60px;stroke:var(--border);fill:none;stroke-width:1;margin-bottom:12px}
.empty h3{font-size:1.05rem;margin-bottom:4px;color:var(--text-secondary)}
.empty p{font-size:0.85rem}

.loading{text-align:center;padding:40px;grid-column:1/-1}
.spin{
  width:32px;height:32px;border:3px solid var(--border);
  border-top-color:var(--red);border-radius:50%;
  animation:spin .7s linear infinite;margin:0 auto 8px;
}
@keyframes spin{to{transform:rotate(360deg)}}

/* ─── CHAT TOGGLE MOBILE ─── */
.chat-toggle{
  display:none;position:fixed;bottom:18px;left:18px;
  width:50px;height:50px;background:var(--red);
  border:none;border-radius:50%;color:#fff;
  cursor:pointer;z-index:1100;
  box-shadow:0 3px 12px rgba(211,47,47,.4);
  transition:transform .15s;
}
.chat-toggle:hover{transform:scale(1.08)}
.chat-toggle svg{width:22px;height:22px;fill:none;stroke:#fff;stroke-width:2}

/* ─── RESPONSIVE ─── */
@media(max-width:1024px){
  .chat{transform:translateX(-100%);transition:transform .25s}
  .chat.open{transform:translateX(0)}
  .content{margin-left:0;max-width:100%}
  .chat-toggle{display:flex;align-items:center;justify-content:center}
  .grid-hero{grid-template-columns:1fr}
  .hero-card:first-child{grid-row:span 1}
  .hero-card:first-child .hero-img{min-height:200px}
}
@media(max-width:640px){
  .content{padding:14px}
  .grid{grid-template-columns:1fr 1fr;gap:10px}
  .card-img{height:140px}
  .card-body{padding:8px 10px 10px}
  .card-title{font-size:0.85rem}
  .header-search{display:none}
  .nav a{padding:16px 12px;font-size:0.85rem}
  .logo{font-size:1.2rem}
  .grid-hero{grid-template-columns:1fr}
}

/* ─── ANIMATIONS ─── */
@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
.card,.hero-card{animation:fadeUp .4s ease backwards}
.card:nth-child(1),.hero-card:nth-child(1){animation-delay:.03s}
.card:nth-child(2),.hero-card:nth-child(2){animation-delay:.06s}
.card:nth-child(3),.hero-card:nth-child(3){animation-delay:.09s}
.card:nth-child(4){animation-delay:.12s}
.card:nth-child(5){animation-delay:.15s}
.card:nth-child(6){animation-delay:.18s}
.card:nth-child(7){animation-delay:.21s}
.card:nth-child(8){animation-delay:.24s}
.card:nth-child(9){animation-delay:.27s}
.card:nth-child(10){animation-delay:.3s}
</style>
</head>
<body>

<!-- HEADER -->
<header class="header">
  <div class="header-inner">
    <a href="index.php" class="logo"><span>M</span>MediaHub</a>
    <nav>
      <ul class="nav">
        <li><a href="index.php?page=home" class="<?=($page==='home'?'active':'')?>">דף הבית</a></li>
        <li><a href="index.php?page=images" class="<?=($page==='images'?'active':'')?>">תמונות</a></li>
        <li><a href="index.php?page=videos" class="<?=($page==='videos'?'active':'')?>">סרטונים</a></li>
      </ul>
    </nav>
    <div class="header-search">
      <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" id="searchInput" placeholder="חיפוש..." autocomplete="off">
    </div>
  </div>
</header>

<!-- LAYOUT -->
<div class="layout">
  <main class="content">

    <?php if ($page === 'home'): ?>
    <div class="upload-zone" id="upZone" onclick="document.getElementById('upFile').click()">
      <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
      <h3>העלאת תמונה או סרטון</h3>
      <p>גררו קובץ לכאן או לחצו לבחירה</p>
      <input type="file" id="upFile" accept="image/*,video/*">
    </div>
    <div class="section-bar">
      <h2>תמונות אחרונות</h2>
      <a href="index.php?page=images">כל התמונות &larr;</a>
    </div>
    <div id="heroArea"></div>
    <div class="grid" id="recentGrid">
      <div class="loading"><div class="spin"></div><p>טוען...</p></div>
    </div>

    <?php elseif ($page === 'images'): ?>
    <div class="section-bar"><h2>כל התמונות</h2></div>
    <div class="grid" id="imagesGrid">
      <div class="loading"><div class="spin"></div><p>טוען...</p></div>
    </div>
    <div class="pager" id="imgPager"></div>

    <?php elseif ($page === 'videos'): ?>
    <div class="section-bar"><h2>כל הסרטונים</h2></div>
    <div class="grid" id="videosGrid">
      <div class="loading"><div class="spin"></div><p>טוען...</p></div>
    </div>
    <div class="pager" id="vidPager"></div>
    <?php endif; ?>

  </main>

  <!-- CHAT -->
  <aside class="chat" id="chatBox">
    <div class="chat-head"><span class="dot"></span> צ'אט חי</div>
    <div class="chat-msgs" id="chatMsgs"></div>
    <div class="chat-input">
      <button class="cbtn cbtn-send" id="chatSend" title="שלח">
        <svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
      </button>
      <input type="text" id="chatTxt" placeholder="הקלד הודעה..." autocomplete="off">
      <label class="cbtn cbtn-img" title="שלח תמונה">
        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
        <input type="file" id="chatImgIn" accept="image/*">
      </label>
    </div>
  </aside>
</div>

<!-- MOBILE CHAT TOGGLE -->
<button class="chat-toggle" id="chatToggle">
  <svg viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>
</button>

<!-- NICKNAME MODAL -->
<div class="overlay" id="nickOverlay">
  <div class="modal">
    <h2>ברוכים הבאים!</h2>
    <p>בחרו כינוי כדי להתחיל</p>
    <input type="text" id="nickIn" placeholder="הכינוי שלכם..." maxlength="20">
    <button class="btn" id="nickBtn">כניסה</button>
  </div>
</div>

<!-- UPLOAD MODAL -->
<div class="overlay" id="upOverlay">
  <div class="modal" style="text-align:right">
    <h2 style="text-align:center">העלאת קובץ</h2>
    <img class="upload-preview" id="upPrev" alt="">
    <div class="upload-bar" id="upBar"><div class="upload-bar-fill" id="upBarFill"></div></div>
    <input type="text" id="upTitle" placeholder="כותרת..." style="text-align:right;margin-bottom:10px">
    <button class="btn" id="upSubmit">העלאה</button>
    <button class="btn btn-ghost" id="upCancel">ביטול</button>
  </div>
</div>

<!-- LIGHTBOX -->
<div class="lb" id="lb" onclick="closeLB()">
  <button class="lb-close" onclick="closeLB()">&times;</button>
  <img id="lbImg" src="" alt="">
</div>

<script>
var nick = localStorage.getItem('mh_nick') || '';
var curPage = '<?=e($page)?>';
var imgP = 1, vidP = 1, upFile = null;

// Nickname
function checkNick() {
  if (!nick) { document.getElementById('nickOverlay').classList.add('show'); setTimeout(function(){document.getElementById('nickIn').focus()},200); }
}
document.getElementById('nickBtn').onclick = function() {
  var v = document.getElementById('nickIn').value.trim();
  if (v) { nick = v; localStorage.setItem('mh_nick', nick); document.getElementById('nickOverlay').classList.remove('show'); }
};
document.getElementById('nickIn').onkeydown = function(e) { if (e.key==='Enter') document.getElementById('nickBtn').click(); };

// API
function api(act, p) {
  p = p || {};
  var u = new URL(location.href.split('?')[0]);
  u.searchParams.set('action', act);
  for (var k in p) u.searchParams.set(k, p[k]);
  return fetch(u.toString()).then(function(r){return r.json()});
}
function apiPost(act, fd) {
  var u = new URL(location.href.split('?')[0]);
  u.searchParams.set('action', act);
  return fetch(u.toString(), {method:'POST',body:fd}).then(function(r){return r.json()});
}

// Helpers
function esc(s) { var d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }
function escA(s) { return (s||'').replace(/'/g,"\\'").replace(/"/g,'&quot;'); }

// Cards
function mkCard(p, i) {
  var isV = p.type==='video';
  var th = p.thumb_url||p.media_url||'';
  var t = p.title||(isV?'סרטון':'תמונה');
  var click = isV ? "window.open('"+escA(p.media_url)+"','_blank')" : "openLB('"+escA(p.media_url)+"')";
  var vid = '';
  if (isV) {
    vid = '<div class="vid-badge"><svg width="8" height="8" viewBox="0 0 24 24" fill="white" stroke="none"><polygon points="5 3 19 12 5 21"/></svg> סרטון</div>';
    vid += '<div class="play-btn"><svg viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg></div>';
  }
  return '<div class="card'+(isV?' card-video':'')+'" onclick="'+click+'" style="animation-delay:'+(i*.03)+'s">' +
    '<div style="position:relative;overflow:hidden"><img class="card-img" src="'+escA(th)+'" alt="'+escA(t)+'" loading="lazy" onerror="this.style.background=\'#eee\'">'+vid+'</div>' +
    '<div class="card-body"><div class="card-title">'+esc(t)+'</div>' +
    '<div class="card-meta"><span class="card-author">'+esc(p.nickname||'אנונימי')+'</span><span>'+esc(p.time_ago||'')+'</span></div></div></div>';
}

function mkHero(p, i) {
  var th = p.thumb_url||p.media_url||'';
  var t = p.title||'תמונה';
  return '<div class="hero-card" onclick="openLB(\''+escA(p.media_url)+'\')" style="animation-delay:'+(i*.04)+'s">' +
    '<img class="hero-img" src="'+escA(th)+'" alt="'+escA(t)+'" loading="lazy">' +
    '<div class="hero-overlay"><div class="hero-title">'+esc(t)+'</div>' +
    '<div class="hero-meta"><span>'+esc(p.nickname||'אנונימי')+'</span><span>'+esc(p.time_ago||'')+'</span></div></div></div>';
}

function mkEmpty(m) {
  return '<div class="empty"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg><h3>'+m+'</h3><p>העלו תוכן חדש כדי להתחיל</p></div>';
}

function mkPager(el, cur, tot, fn) {
  el.innerHTML='';
  if (tot<=1) return;
  var prev=document.createElement('button'); prev.textContent='הקודם'; prev.disabled=cur<=1; prev.onclick=function(){fn(cur-1)}; el.appendChild(prev);
  for(var i=1;i<=tot;i++){
    if(tot>7&&i>3&&i<tot-2&&Math.abs(i-cur)>1){if(i===4||i===tot-3){var d=document.createElement('button');d.textContent='...';d.disabled=true;el.appendChild(d);}continue;}
    var b=document.createElement('button');b.textContent=i;if(i===cur)b.classList.add('act');
    (function(pg){b.onclick=function(){fn(pg)}})(i);el.appendChild(b);
  }
  var next=document.createElement('button');next.textContent='הבא';next.disabled=cur>=tot;next.onclick=function(){fn(cur+1)};el.appendChild(next);
}

// Load pages
function loadHome() {
  var hero = document.getElementById('heroArea');
  var grid = document.getElementById('recentGrid');
  if (!grid) return;
  api('get_recent').then(function(d) {
    if (!d.posts||!d.posts.length) { hero.innerHTML=''; grid.innerHTML=mkEmpty('עדיין אין תמונות'); return; }
    // First 3 as hero cards
    if (d.posts.length >= 3) {
      hero.innerHTML = '<div class="grid-hero">' + d.posts.slice(0,3).map(function(p,i){return mkHero(p,i)}).join('') + '</div>';
      grid.innerHTML = d.posts.slice(3).map(function(p,i){return mkCard(p,i)}).join('');
    } else {
      hero.innerHTML = '';
      grid.innerHTML = d.posts.map(function(p,i){return mkCard(p,i)}).join('');
    }
  }).catch(function(){grid.innerHTML=mkEmpty('שגיאה בטעינה')});
}

function loadImages(pg) {
  var g=document.getElementById('imagesGrid'); if(!g)return;
  g.innerHTML='<div class="loading"><div class="spin"></div><p>טוען...</p></div>';
  api('get_posts',{type:'image',page:pg}).then(function(d){
    if(!d.posts||!d.posts.length){g.innerHTML=mkEmpty('עדיין אין תמונות');return;}
    g.innerHTML=d.posts.map(function(p,i){return mkCard(p,i)}).join('');
    var pg2=document.getElementById('imgPager');
    if(pg2)mkPager(pg2,d.page,d.pages,function(p){imgP=p;loadImages(p)});
  }).catch(function(){g.innerHTML=mkEmpty('שגיאה')});
}

function loadVideos(pg) {
  var g=document.getElementById('videosGrid'); if(!g)return;
  g.innerHTML='<div class="loading"><div class="spin"></div><p>טוען...</p></div>';
  api('get_posts',{type:'video',page:pg}).then(function(d){
    if(!d.posts||!d.posts.length){g.innerHTML=mkEmpty('עדיין אין סרטונים');return;}
    g.innerHTML=d.posts.map(function(p,i){return mkCard(p,i)}).join('');
    var pg2=document.getElementById('vidPager');
    if(pg2)mkPager(pg2,d.page,d.pages,function(p){vidP=p;loadVideos(p)});
  }).catch(function(){g.innerHTML=mkEmpty('שגיאה')});
}

// Lightbox
function openLB(u){document.getElementById('lbImg').src=u;document.getElementById('lb').classList.add('show');document.body.style.overflow='hidden'}
function closeLB(){document.getElementById('lb').classList.remove('show');document.body.style.overflow=''}
document.onkeydown=function(e){if(e.key==='Escape')closeLB()};

// Upload
var uz=document.getElementById('upZone'), uf=document.getElementById('upFile');
if(uz){
  uz.ondragover=function(e){e.preventDefault();uz.classList.add('over')};
  uz.ondragleave=function(){uz.classList.remove('over')};
  uz.ondrop=function(e){e.preventDefault();uz.classList.remove('over');if(e.dataTransfer.files.length)showUp(e.dataTransfer.files[0])};
}
if(uf)uf.onchange=function(){if(this.files.length)showUp(this.files[0]);this.value=''};

function showUp(f){
  if(!nick){checkNick();return;}
  upFile=f;
  var pv=document.getElementById('upPrev');
  if(f.type.startsWith('image/')){pv.src=URL.createObjectURL(f);pv.style.display='block'}else{pv.style.display='none'}
  document.getElementById('upTitle').value='';
  document.getElementById('upBar').style.display='none';
  document.getElementById('upOverlay').classList.add('show');
}
document.getElementById('upCancel').onclick=function(){document.getElementById('upOverlay').classList.remove('show');upFile=null};
document.getElementById('upSubmit').onclick=function(){
  if(!upFile)return;
  var bar=document.getElementById('upBar'),fill=document.getElementById('upBarFill');
  bar.style.display='block';
  var fd=new FormData();fd.append('media',upFile);fd.append('title',document.getElementById('upTitle').value.trim());fd.append('nickname',nick);
  var xhr=new XMLHttpRequest();
  xhr.open('POST','index.php?action=upload');
  xhr.upload.onprogress=function(e){if(e.lengthComputable)fill.style.width=Math.round(e.loaded/e.total*100)+'%'};
  xhr.onload=function(){document.getElementById('upOverlay').classList.remove('show');upFile=null;
    if(curPage==='home')loadHome();else if(curPage==='images')loadImages(imgP);else if(curPage==='videos')loadVideos(vidP)};
  xhr.onerror=function(){alert('שגיאה');bar.style.display='none'};
  xhr.send(fd);
};

// Chat
function loadChat(){
  api('get_chat').then(function(d){
    var c=document.getElementById('chatMsgs');
    if(!d.messages||!d.messages.length){c.innerHTML='<div style="text-align:center;color:#999;padding:20px;font-size:0.82rem">הצ\'אט ריק - היו הראשונים!</div>';return;}
    var atBot=c.scrollTop>=c.scrollHeight-c.clientHeight-40;
    var prev=c.children.length;
    c.innerHTML=d.messages.map(function(m){
      var mine=m.nickname===nick;
      var h='<div class="msg '+(mine?'mine':'other')+'">';
      if(!mine)h+='<div class="msg-nick">'+esc(m.nickname)+'</div>';
      if(m.image_url)h+='<img src="'+escA(m.image_url)+'" onclick="event.stopPropagation();openLB(\''+escA(m.image_url)+'\')" alt="">';
      if(m.message)h+='<div>'+esc(m.message)+'</div>';
      h+='<div class="msg-time">'+esc(m.time_ago||'')+'</div></div>';
      return h;
    }).join('');
    if(atBot||c.children.length!==prev)c.scrollTop=c.scrollHeight;
  }).catch(function(){});
}
document.getElementById('chatSend').onclick=sendMsg;
document.getElementById('chatTxt').onkeydown=function(e){if(e.key==='Enter')sendMsg()};
function sendMsg(){
  if(!nick){checkNick();return;}
  var inp=document.getElementById('chatTxt'),m=inp.value.trim();if(!m)return;inp.value='';
  var fd=new FormData();fd.append('nickname',nick);fd.append('message',m);
  apiPost('send_chat',fd).then(function(){loadChat()}).catch(function(){});
}
document.getElementById('chatImgIn').onchange=function(){
  if(!this.files.length)return;if(!nick){checkNick();this.value='';return;}
  var fd=new FormData();fd.append('nickname',nick);fd.append('message','');fd.append('image',this.files[0]);this.value='';
  apiPost('send_chat',fd).then(function(){loadChat();if(curPage==='home')setTimeout(loadHome,400)}).catch(function(){});
};

loadChat();
setInterval(loadChat,4000);

// Search
document.getElementById('searchInput').oninput=function(){
  var q=this.value.trim().toLowerCase();
  document.querySelectorAll('.card,.hero-card').forEach(function(c){
    var t=c.querySelector('.card-title,.hero-title');
    c.style.display=(!q||(t&&t.textContent.toLowerCase().indexOf(q)!==-1))?'':'none';
  });
};

// Chat toggle
document.getElementById('chatToggle').onclick=function(){document.getElementById('chatBox').classList.toggle('open')};
document.addEventListener('click',function(e){
  if(window.innerWidth<=1024){
    var sb=document.getElementById('chatBox'),tg=document.getElementById('chatToggle');
    if(!sb.contains(e.target)&&!tg.contains(e.target)&&sb.classList.contains('open'))sb.classList.remove('open');
  }
});

// Init
checkNick();
if(curPage==='home')loadHome();
else if(curPage==='images')loadImages(1);
else if(curPage==='videos')loadVideos(1);
</script>
</body>
</html>
