<?php
/**
 * MediaHub - Hebrew Media Sharing Site
 * Design: JDN.co.il style (dark blue header, cyan ticker, hero images)
 * Storage: JSON files, no database
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
umask(0);

define('DATA_DIR', __DIR__ . '/data/');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', 'uploads/');
define('MAX_IMAGE_SIZE', 10 * 1024 * 1024);
define('MAX_VIDEO_SIZE', 100 * 1024 * 1024);
define('PER_PAGE', 20);

// Create directories with full permissions
foreach ([DATA_DIR, UPLOAD_DIR.'images/', UPLOAD_DIR.'videos/', UPLOAD_DIR.'thumbs/'] as $d) {
    if (!is_dir($d)) { @mkdir($d, 0777, true); @chmod($d, 0777); }
    elseif (!is_writable($d)) { @chmod($d, 0777); }
}
foreach (['posts.json','chat.json'] as $f) {
    if (!file_exists(DATA_DIR.$f)) { @file_put_contents(DATA_DIR.$f, '[]'); @chmod(DATA_DIR.$f, 0666); }
}

function loadJson($f) {
    $p = DATA_DIR . $f;
    if (!file_exists($p)) return [];
    $d = @json_decode(@file_get_contents($p), true);
    return is_array($d) ? $d : [];
}
function saveJson($f, $d) {
    $p = DATA_DIR.$f;
    @file_put_contents($p, json_encode($d, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX);
    @chmod($p, 0666);
}
function e($s) { return htmlspecialchars($s??'', ENT_QUOTES, 'UTF-8'); }
function timeAgo($t) {
    $d = time()-$t;
    if ($d<60) return "הרגע";
    if ($d<3600) return "לפני ".floor($d/60)." דקות";
    if ($d<86400) return "לפני ".floor($d/3600)." שעות";
    if ($d<604800) return "לפני ".floor($d/86400)." ימים";
    return date('d/m/Y',$t);
}
function makeThumb($src,$dst,$mw) {
    $i=@getimagesize($src); if(!$i) return false;
    switch($i['mime']){
        case 'image/jpeg':$im=@imagecreatefromjpeg($src);break;
        case 'image/png':$im=@imagecreatefrompng($src);break;
        case 'image/gif':$im=@imagecreatefromgif($src);break;
        case 'image/webp':$im=@imagecreatefromwebp($src);break;
        default:return false;
    }
    if(!$im)return false;
    $ow=imagesx($im);$oh=imagesy($im);
    if($ow<=$mw){@copy($src,$dst);imagedestroy($im);return true;}
    $r=$mw/$ow;$nw=$mw;$nh=(int)($oh*$r);
    $th=imagecreatetruecolor($nw,$nh);
    imagealphablending($th,false);imagesavealpha($th,true);
    imagecopyresampled($th,$im,0,0,0,0,$nw,$nh,$ow,$oh);
    imagejpeg($th,$dst,85);
    imagedestroy($im);imagedestroy($th);
    return true;
}

// ─── API ───
$action = $_GET['action'] ?? '';
if ($action) {
    header('Content-Type: application/json; charset=utf-8');

    if ($action==='get_posts') {
        $posts=loadJson('posts.json');
        usort($posts, fn($a,$b)=>($b['created_at']??0)-($a['created_at']??0));
        $type=$_GET['type']??'';
        if($type) $posts=array_values(array_filter($posts, fn($p)=>($p['type']??'')===$type));
        $pg=max(1,(int)($_GET['page']??1));
        $lim=(int)($_GET['limit']??PER_PAGE);
        $off=($pg-1)*$lim;
        $tot=count($posts);
        $items=array_slice($posts,$off,$lim);
        foreach($items as &$it) $it['time_ago']=timeAgo($it['created_at']??0);
        echo json_encode(['posts'=>$items,'total'=>$tot,'page'=>$pg,'pages'=>max(1,ceil($tot/$lim))],JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action==='get_recent') {
        $posts=loadJson('posts.json');
        usort($posts, fn($a,$b)=>($b['created_at']??0)-($a['created_at']??0));
        $imgs=array_values(array_filter($posts, fn($p)=>($p['type']??'')==='image'));
        $rec=array_slice($imgs,0,10);
        foreach($rec as &$it) $it['time_ago']=timeAgo($it['created_at']??0);
        echo json_encode(['posts'=>$rec],JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action==='upload' && $_SERVER['REQUEST_METHOD']==='POST') {
        if(!isset($_FILES['media'])||$_FILES['media']['error']!==UPLOAD_ERR_OK){
            echo json_encode(['error'=>'שגיאה בהעלאה: '.($_FILES['media']['error']??'no file')],JSON_UNESCAPED_UNICODE); exit;
        }
        $file=$_FILES['media'];
        $mime=mime_content_type($file['tmp_name']);
        $aI=['image/jpeg','image/png','image/gif','image/webp'];
        $aV=['video/mp4','video/webm','video/ogg'];
        $isI=in_array($mime,$aI);$isV=in_array($mime,$aV);
        if(!$isI&&!$isV){echo json_encode(['error'=>'סוג קובץ לא נתמך'],JSON_UNESCAPED_UNICODE);exit;}
        if($isI&&$file['size']>MAX_IMAGE_SIZE){echo json_encode(['error'=>'תמונה גדולה מדי (10MB)'],JSON_UNESCAPED_UNICODE);exit;}
        if($isV&&$file['size']>MAX_VIDEO_SIZE){echo json_encode(['error'=>'סרטון גדול מדי (100MB)'],JSON_UNESCAPED_UNICODE);exit;}
        $ext=strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
        if(!$ext) $ext=$isI?'jpg':'mp4';
        $fn=uniqid('m_',true).'.'.$ext;
        $sub=$isI?'images/':'videos/';
        $tp=UPLOAD_DIR.$sub.$fn;
        if(!@move_uploaded_file($file['tmp_name'],$tp)){
            $dir=UPLOAD_DIR.$sub;
            $err='שגיאה בשמירת הקובץ.';
            if(!is_dir($dir)) $err.=' תיקייה לא קיימת: '.$dir;
            elseif(!is_writable($dir)) $err.=' אין הרשאת כתיבה לתיקייה: '.$dir.' | בצעו: chmod -R 777 uploads/';
            echo json_encode(['error'=>$err],JSON_UNESCAPED_UNICODE);exit;
        }
        @chmod($tp, 0666);
        $mu=UPLOAD_URL.$sub.$fn;$tu=$mu;
        if($isI){
            $tf=UPLOAD_DIR.'thumbs/'.$fn;
            if(makeThumb($tp,$tf,400)){$tu=UPLOAD_URL.'thumbs/'.$fn;@chmod($tf,0666);}
        }
        $title=trim($_POST['title']??'');
        $nick=trim($_POST['nickname']??'אנונימי');
        if(!$title) $title=$isI?'תמונה חדשה':'סרטון חדש';
        $post=['id'=>uniqid('p_'),'title'=>$title,'type'=>$isI?'image':'video','media_url'=>$mu,'thumb_url'=>$tu,'nickname'=>$nick,'created_at'=>time()];
        $posts=loadJson('posts.json');$posts[]=$post;saveJson('posts.json',$posts);
        $post['time_ago']=timeAgo($post['created_at']);
        echo json_encode(['success'=>true,'post'=>$post],JSON_UNESCAPED_UNICODE);exit;
    }

    if ($action==='get_chat') {
        $chat=loadJson('chat.json');
        usort($chat, fn($a,$b)=>($a['created_at']??0)-($b['created_at']??0));
        $last=array_slice($chat,-50);
        foreach($last as &$m) $m['time_ago']=timeAgo($m['created_at']??0);
        echo json_encode(['messages'=>$last],JSON_UNESCAPED_UNICODE);exit;
    }

    if ($action==='send_chat' && $_SERVER['REQUEST_METHOD']==='POST') {
        $nick=trim($_POST['nickname']??'אנונימי');
        $msg=trim($_POST['message']??'');
        $hasI=isset($_FILES['image'])&&$_FILES['image']['error']===UPLOAD_ERR_OK;
        if(!$msg&&!$hasI){echo json_encode(['error'=>'הודעה ריקה'],JSON_UNESCAPED_UNICODE);exit;}
        $imgUrl=null;
        if($hasI){
            $f=$_FILES['image'];$mime=mime_content_type($f['tmp_name']);
            if(in_array($mime,['image/jpeg','image/png','image/gif','image/webp'])&&$f['size']<=MAX_IMAGE_SIZE){
                $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
                if(!$ext)$ext='jpg';
                $fn=uniqid('c_',true).'.'.$ext;
                $tp=UPLOAD_DIR.'images/'.$fn;
                if(@move_uploaded_file($f['tmp_name'],$tp)){
                    @chmod($tp,0666);
                    $imgUrl=UPLOAD_URL.'images/'.$fn;$tu=$imgUrl;
                    $tf=UPLOAD_DIR.'thumbs/'.$fn;
                    if(makeThumb($tp,$tf,400)){$tu=UPLOAD_URL.'thumbs/'.$fn;@chmod($tf,0666);}
                    $post=['id'=>uniqid('p_'),'title'=>$msg?:'תמונה מהצ\'אט','type'=>'image','media_url'=>$imgUrl,'thumb_url'=>$tu,'nickname'=>$nick,'created_at'=>time()];
                    $posts=loadJson('posts.json');$posts[]=$post;saveJson('posts.json',$posts);
                }
            }
        }
        $cm=['id'=>uniqid('c_'),'nickname'=>$nick,'message'=>$msg,'image_url'=>$imgUrl,'created_at'=>time()];
        $chat=loadJson('chat.json');$chat[]=$cm;
        if(count($chat)>500)$chat=array_slice($chat,-500);
        saveJson('chat.json',$chat);
        $cm['time_ago']=timeAgo($cm['created_at']);
        echo json_encode(['success'=>true,'message'=>$cm],JSON_UNESCAPED_UNICODE);exit;
    }

    // Debug endpoint
    if ($action==='debug') {
        $dirs = [DATA_DIR, UPLOAD_DIR.'images/', UPLOAD_DIR.'videos/', UPLOAD_DIR.'thumbs/'];
        $dirInfo = [];
        foreach ($dirs as $d) {
            $dirInfo[$d] = [
                'exists' => is_dir($d),
                'writable' => is_writable($d),
                'perms' => is_dir($d) ? decoct(fileperms($d) & 0777) : 'N/A',
                'owner' => is_dir($d) ? posix_getpwuid(fileowner($d))['name'] ?? fileowner($d) : 'N/A',
            ];
        }
        $info = [
            'directories' => $dirInfo,
            'json_files' => [
                'posts.json' => ['exists'=>file_exists(DATA_DIR.'posts.json'), 'writable'=>is_writable(DATA_DIR.'posts.json'), 'count'=>count(loadJson('posts.json'))],
                'chat.json' => ['exists'=>file_exists(DATA_DIR.'chat.json'), 'writable'=>is_writable(DATA_DIR.'chat.json'), 'count'=>count(loadJson('chat.json'))],
            ],
            'php_version' => PHP_VERSION,
            'max_upload_size' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'gd_loaded' => extension_loaded('gd'),
            'current_user' => get_current_user(),
            'php_user' => posix_getpwuid(posix_geteuid())['name'] ?? posix_geteuid(),
            'umask' => sprintf('%04o', umask()),
        ];
        echo json_encode($info, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT); exit;
    }

    echo json_encode(['error'=>'Unknown action'],JSON_UNESCAPED_UNICODE);exit;
}

$page = $_GET['page'] ?? 'home';
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NO - מדיה ותמונות</title>
<link href="https://fonts.googleapis.com/css2?family=Heebo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}

:root{
  --navy:#0a1628;
  --navy-light:#132240;
  --cyan:#00bcd4;
  --cyan-light:#4dd0e1;
  --cyan-dark:#0097a7;
  --cyan-bg:rgba(0,188,212,.08);
  --white:#fff;
  --off:#f5f6f8;
  --bg:#ebeef3;
  --card:#fff;
  --text:#1a1a2e;
  --text2:#555;
  --text3:#999;
  --border:#e0e3e8;
  --red:#e53935;
  --shadow:0 2px 8px rgba(0,0,0,.08);
  --shadow2:0 6px 24px rgba(0,0,0,.12);
  --r:8px;
  --hh:58px;
  --ticker:0px;
  --chatw:310px;
  --font:'Heebo',sans-serif;
}

html{font-size:15px}
body{font-family:var(--font);background:var(--bg);color:var(--text);line-height:1.55;min-height:100vh}
::-webkit-scrollbar{width:5px}
::-webkit-scrollbar-thumb{background:#b0b8c4;border-radius:3px}

/* ═══ HEADER ═══ */
.hdr{
  background:var(--navy);height:var(--hh);
  position:fixed;top:0;left:0;right:0;z-index:1000;
}
.hdr-in{
  max-width:1400px;margin:0 auto;height:100%;
  display:flex;align-items:center;justify-content:space-between;
  padding:0 20px;
}
.logo{
  text-decoration:none;display:flex;align-items:center;
}
.logo img{height:45px;border-radius:4px;}
.nav{display:flex;list-style:none;gap:0;align-items:center}
.nav a{
  color:rgba(255,255,255,.75);text-decoration:none;
  padding:0 16px;font-size:.88rem;font-weight:500;
  height:var(--hh);display:flex;align-items:center;
  border-bottom:3px solid transparent;
  transition:all .2s;
}
.nav a:hover{color:#fff;background:rgba(255,255,255,.06)}
.nav a.on{color:var(--cyan);border-bottom-color:var(--cyan);font-weight:700}

.hsrch{position:relative;width:180px}
.hsrch input{
  width:100%;padding:6px 12px 6px 30px;
  background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);
  border-radius:16px;font-family:var(--font);font-size:.8rem;
  color:#fff;outline:none;transition:all .2s;
}
.hsrch input::placeholder{color:rgba(255,255,255,.4)}
.hsrch input:focus{background:rgba(255,255,255,.18);border-color:var(--cyan)}
.hsrch svg{position:absolute;left:9px;top:50%;transform:translateY(-50%);width:13px;height:13px;stroke:rgba(255,255,255,.4);fill:none;stroke-width:2;pointer-events:none}

/* ticker removed */

/* ═══ LAYOUT ═══ */
.wrap{
  display:flex;
  margin-top:calc(var(--hh) + var(--ticker));
  min-height:calc(100vh - var(--hh) - var(--ticker));
}
.main{
  flex:1;padding:22px 24px;
  margin-left:var(--chatw);max-width:calc(100% - var(--chatw));
}

/* ═══ HERO ═══ */
.hero{
  display:grid;grid-template-columns:1.4fr 1fr;
  gap:12px;margin-bottom:22px;border-radius:var(--r);overflow:hidden;
}
.hero-card{
  position:relative;overflow:hidden;border-radius:var(--r);
  cursor:pointer;min-height:180px;background:var(--navy);
}
.hero-card:first-child{grid-row:span 2;min-height:380px}
.hero-img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .4s}
.hero-card:hover .hero-img{transform:scale(1.03)}
.hero-ov{
  position:absolute;bottom:0;left:0;right:0;
  background:linear-gradient(transparent 0%,rgba(10,22,40,.85) 100%);
  padding:50px 18px 16px;
}
.hero-title{color:#fff;font-size:1rem;font-weight:700;line-height:1.4}
.hero-card:first-child .hero-title{font-size:1.4rem}
.hero-meta{display:flex;gap:10px;margin-top:5px;font-size:.7rem;color:rgba(255,255,255,.7)}
.hero-meta .tag{background:var(--cyan);color:var(--navy);padding:1px 8px;border-radius:3px;font-weight:600;font-size:.65rem}

/* ═══ SECTION ═══ */
.sec{
  display:flex;align-items:center;justify-content:space-between;
  margin-bottom:16px;
}
.sec h2{
  font-size:1.05rem;font-weight:800;color:var(--navy);
  padding-right:12px;border-right:4px solid var(--cyan);
}
.sec a{color:var(--cyan-dark);text-decoration:none;font-size:.78rem;font-weight:500}
.sec a:hover{text-decoration:underline}

/* ═══ GRID ═══ */
.grid{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(240px,1fr));
  gap:14px;
}

/* ═══ CARD ═══ */
.card{
  background:var(--card);border-radius:var(--r);overflow:hidden;
  border:1px solid var(--border);cursor:pointer;
  transition:box-shadow .25s,transform .25s;
}
.card:hover{box-shadow:var(--shadow2);transform:translateY(-3px)}
.card-wrap{position:relative;overflow:hidden}
.card-img{width:100%;height:180px;object-fit:cover;display:block;background:var(--off);transition:transform .4s}
.card:hover .card-img{transform:scale(1.04)}
.card-body{padding:10px 13px 13px}
.card-title{
  font-size:.9rem;font-weight:700;color:var(--text);line-height:1.4;
  margin-bottom:6px;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;
}
.card-meta{display:flex;justify-content:space-between;font-size:.7rem;color:var(--text3)}
.card-author{color:var(--cyan-dark);font-weight:600}

/* Video */
.card-wrap .vbadge{
  position:absolute;top:8px;left:8px;background:rgba(0,0,0,.7);
  color:#fff;padding:2px 7px;border-radius:4px;font-size:.65rem;font-weight:600;
  display:flex;align-items:center;gap:3px;z-index:2;
}
.card-wrap .playbtn{
  position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);
  width:44px;height:44px;background:rgba(255,255,255,.9);border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  opacity:.85;transition:all .2s;z-index:2;
}
.card:hover .playbtn{opacity:1;transform:translate(-50%,-50%) scale(1.1)}
.playbtn svg{width:18px;height:18px;fill:var(--navy);margin-right:-2px}

/* ═══ UPLOAD ═══ */
.upzone{
  background:var(--white);border:2px dashed var(--border);
  border-radius:var(--r);padding:20px;text-align:center;
  margin-bottom:20px;cursor:pointer;transition:all .2s;
}
.upzone:hover,.upzone.ov{border-color:var(--cyan);background:var(--cyan-bg)}
.upzone svg{width:32px;height:32px;stroke:var(--cyan);fill:none;stroke-width:1.5;margin-bottom:6px}
.upzone h3{font-size:.9rem;font-weight:700;margin-bottom:3px}
.upzone p{font-size:.78rem;color:var(--text3)}
.upzone input{display:none}

/* ═══ CHAT ═══ */
.chat{
  width:var(--chatw);position:fixed;
  top:calc(var(--hh)+var(--ticker));left:0;bottom:0;
  background:var(--white);display:flex;flex-direction:column;
  z-index:900;border-right:1px solid var(--border);
}
.chat-hd{
  padding:10px 14px;background:var(--navy);
  color:#fff;font-size:.88rem;font-weight:700;
  display:flex;align-items:center;gap:7px;flex-shrink:0;
}
.chat-hd .dot{width:6px;height:6px;background:#4caf50;border-radius:50%;animation:blinky 2s infinite}
@keyframes blinky{0%,100%{opacity:1}50%{opacity:.3}}

.chat-msgs{
  flex:1;overflow-y:auto;padding:10px 8px;
  display:flex;flex-direction:column;gap:4px;
  background:#f0f2f5;
  overscroll-behavior:contain;
}
.cmsg{
  max-width:82%;padding:6px 10px;border-radius:8px;
  font-size:.8rem;line-height:1.4;word-wrap:break-word;
  box-shadow:0 1px 1px rgba(0,0,0,.05);
}
.cmsg.ot{align-self:flex-start;background:var(--white);border:1px solid var(--border);border-top-right-radius:2px}
.cmsg.me{align-self:flex-end;background:#d9fdd3;border-top-left-radius:2px}
.cmsg-nick{font-size:.65rem;font-weight:700;color:var(--cyan-dark);margin-bottom:1px}
.cmsg img{max-width:100%;border-radius:6px;margin-top:3px;cursor:pointer}
.cmsg-t{font-size:.58rem;color:var(--text3);text-align:left;margin-top:2px}

.chat-bar{
  padding:8px;background:var(--white);border-top:1px solid var(--border);
  display:flex;align-items:center;gap:6px;flex-shrink:0;
}
.chat-bar input[type=text]{
  flex:1;padding:7px 12px;border:1px solid var(--border);
  border-radius:18px;font-family:var(--font);font-size:.8rem;
  outline:none;background:var(--off);transition:border .2s;
}
.chat-bar input:focus{border-color:var(--cyan)}
.cb{
  width:32px;height:32px;border-radius:50%;border:none;
  cursor:pointer;display:flex;align-items:center;justify-content:center;
  transition:all .15s;flex-shrink:0;background:transparent;
}
.cb svg{width:15px;height:15px;fill:none;stroke-width:2}
.cb-send{background:var(--cyan)}
.cb-send svg{stroke:#fff}
.cb-send:hover{background:var(--cyan-dark)}
.cb-img svg{stroke:var(--text3)}
.cb-img:hover svg{stroke:var(--cyan)}
.chat-bar input[type=file]{display:none}

/* ═══ MODALS ═══ */
.ov{
  position:fixed;inset:0;background:rgba(10,22,40,.55);
  z-index:2000;display:flex;align-items:center;justify-content:center;
  backdrop-filter:blur(3px);opacity:0;visibility:hidden;transition:all .25s;
}
.ov.show{opacity:1;visibility:visible}
.mdl{
  background:var(--white);border-radius:var(--r);
  padding:26px 22px;width:340px;max-width:92vw;
  box-shadow:var(--shadow2);transform:translateY(12px);
  transition:transform .25s;text-align:center;
}
.ov.show .mdl{transform:translateY(0)}
.mdl h2{font-size:1.1rem;font-weight:800;margin-bottom:4px;color:var(--navy)}
.mdl p{color:var(--text2);font-size:.83rem;margin-bottom:14px}
.mdl input[type=text]{
  width:100%;padding:9px 13px;border:1px solid var(--border);
  border-radius:6px;font-family:var(--font);font-size:.92rem;
  text-align:center;outline:none;margin-bottom:10px;transition:border .2s;
}
.mdl input:focus{border-color:var(--cyan)}
.mbtn{
  width:100%;padding:9px;border:none;border-radius:6px;
  font-family:var(--font);font-size:.92rem;font-weight:700;
  cursor:pointer;transition:all .2s;
}
.mbtn-pri{background:var(--navy);color:#fff}
.mbtn-pri:hover{background:var(--navy-light)}
.mbtn-sec{background:transparent;color:var(--text3);border:1px solid var(--border);margin-top:7px}
.mbtn-sec:hover{background:var(--off)}

.up-prev{width:100%;max-height:170px;object-fit:contain;border-radius:6px;margin-bottom:8px;display:none}
.up-bar{height:3px;background:var(--border);border-radius:2px;overflow:hidden;margin-bottom:8px;display:none}
.up-fill{height:100%;background:var(--cyan);width:0;transition:width .3s}

/* ═══ LIGHTBOX ═══ */
.lb{position:fixed;inset:0;background:rgba(0,0,0,.92);z-index:3000;display:flex;align-items:center;justify-content:center;opacity:0;visibility:hidden;transition:all .25s;cursor:pointer}
.lb.show{opacity:1;visibility:visible}
.lb img,.lb video{max-width:90vw;max-height:90vh;border-radius:6px;cursor:default}
.lb-x{position:absolute;top:14px;right:14px;width:36px;height:36px;background:rgba(255,255,255,.15);border:none;border-radius:50%;color:#fff;font-size:1.2rem;cursor:pointer;display:flex;align-items:center;justify-content:center}
.lb-x:hover{background:rgba(255,255,255,.3)}

/* ═══ PAGER ═══ */
.pgr{display:flex;align-items:center;justify-content:center;gap:4px;margin-top:24px;padding:14px 0}
.pgr button{
  padding:5px 13px;border:1px solid var(--border);background:var(--white);
  border-radius:6px;font-family:var(--font);font-size:.8rem;cursor:pointer;
  transition:all .15s;color:var(--text);
}
.pgr button:hover{border-color:var(--cyan);color:var(--cyan-dark)}
.pgr button.on{background:var(--navy);color:#fff;border-color:var(--navy)}
.pgr button:disabled{opacity:.3;cursor:not-allowed}

/* ═══ EMPTY ═══ */
.empty{text-align:center;padding:44px 20px;color:var(--text3);grid-column:1/-1}
.empty svg{width:56px;height:56px;stroke:var(--border);fill:none;stroke-width:1;margin-bottom:10px}
.empty h3{font-size:1rem;margin-bottom:3px;color:var(--text2)}
.empty p{font-size:.82rem}
.ld{text-align:center;padding:36px;grid-column:1/-1}
.sp{width:30px;height:30px;border:3px solid var(--border);border-top-color:var(--cyan);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto 6px}
@keyframes spin{to{transform:rotate(360deg)}}

/* ═══ CHAT TOGGLE ═══ */
.chtgl{
  display:none;position:fixed;bottom:16px;left:16px;
  width:48px;height:48px;background:var(--navy);
  border:none;border-radius:50%;color:#fff;cursor:pointer;z-index:1100;
  box-shadow:0 3px 14px rgba(10,22,40,.4);transition:transform .15s;
}
.chtgl:hover{transform:scale(1.08)}
.chtgl svg{width:20px;height:20px;fill:none;stroke:#fff;stroke-width:2}

/* ═══ RESPONSIVE ═══ */
@media(max-width:1024px){
  .chat{transform:translateX(-100%);transition:transform .25s}
  .chat.open{transform:translateX(0)}
  .main{margin-left:0;max-width:100%}
  .chtgl{display:flex;align-items:center;justify-content:center}
  .hero{grid-template-columns:1fr}
  .hero-card:first-child{grid-row:span 1;min-height:220px}
}
@media(max-width:640px){
  .main{padding:12px}
  .grid{grid-template-columns:1fr 1fr;gap:10px}
  .card-img{height:130px}
  .card-body{padding:8px 10px 10px}
  .card-title{font-size:.82rem}
  .hsrch{display:none}
  .nav a{padding:0 10px;font-size:.82rem}
  .logo img{height:36px}
  .hero{grid-template-columns:1fr;gap:8px}
  .hero-card:first-child{min-height:200px}
  .wrap{margin-top:var(--hh)}
  .chat{top:var(--hh)}
}

/* ═══ ANIM ═══ */
@keyframes fu{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
.card,.hero-card{animation:fu .35s ease backwards}
.card:nth-child(1),.hero-card:nth-child(1){animation-delay:.02s}
.card:nth-child(2),.hero-card:nth-child(2){animation-delay:.05s}
.card:nth-child(3),.hero-card:nth-child(3){animation-delay:.08s}
.card:nth-child(4){animation-delay:.11s}
.card:nth-child(5){animation-delay:.14s}
.card:nth-child(6){animation-delay:.17s}
.card:nth-child(7){animation-delay:.2s}
.card:nth-child(8){animation-delay:.23s}
</style>
</head>
<body>

<!-- HEADER -->
<header class="hdr">
  <div class="hdr-in">
    <a href="index.php" class="logo"><img src="logo.jpg" alt="NO"></a>
    <nav>
      <ul class="nav">
        <li><a href="index.php?page=home" class="<?=($page==='home'?'on':'')?>">דף הבית</a></li>
        <li><a href="index.php?page=images" class="<?=($page==='images'?'on':'')?>">תמונות</a></li>
        <li><a href="index.php?page=videos" class="<?=($page==='videos'?'on':'')?>">סרטונים</a></li>
        <li><a href="index.php?page=images">גלריות</a></li>
      </ul>
    </nav>
    <div class="hsrch">
      <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" id="sInput" placeholder="חיפוש..." autocomplete="off">
    </div>
  </div>
</header>


<div class="wrap">
  <main class="main">

    <?php if($page==='home'): ?>
    <div class="upzone" id="uz" onclick="document.getElementById('uf').click()">
      <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
      <h3>העלאת תמונה או סרטון</h3>
      <p>גררו קובץ לכאן או לחצו לבחירה</p>
      <input type="file" id="uf" accept="image/*,video/*">
    </div>
    <div id="heroBox"></div>
    <div class="sec">
      <h2>תמונות אחרונות</h2>
      <a href="index.php?page=images">כל התמונות &larr;</a>
    </div>
    <div class="grid" id="homeGrid"><div class="ld"><div class="sp"></div><p>טוען...</p></div></div>

    <?php elseif($page==='images'): ?>
    <div class="sec"><h2>כל התמונות</h2></div>
    <div class="grid" id="imgGrid"><div class="ld"><div class="sp"></div><p>טוען...</p></div></div>
    <div class="pgr" id="imgPgr"></div>

    <?php elseif($page==='videos'): ?>
    <div class="sec"><h2>כל הסרטונים</h2></div>
    <div class="grid" id="vidGrid"><div class="ld"><div class="sp"></div><p>טוען...</p></div></div>
    <div class="pgr" id="vidPgr"></div>
    <?php endif; ?>

  </main>

  <!-- CHAT -->
  <aside class="chat" id="chatBox">
    <div class="chat-hd"><span class="dot"></span> צ'אט חי</div>
    <div class="chat-msgs" id="chatMsgs"></div>
    <div class="chat-bar">
      <button class="cb cb-send" id="cSend"><svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg></button>
      <input type="text" id="cTxt" placeholder="הקלד הודעה..." autocomplete="off">
      <label class="cb cb-img"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg><input type="file" id="cImg" accept="image/*"></label>
    </div>
  </aside>
</div>

<button class="chtgl" id="chtgl"><svg viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg></button>

<!-- NICKNAME -->
<div class="ov" id="nickOv">
  <div class="mdl">
    <h2>ברוכים הבאים!</h2>
    <p>בחרו כינוי להתחלה</p>
    <input type="text" id="nickIn" placeholder="הכינוי שלכם..." maxlength="20">
    <button class="mbtn mbtn-pri" id="nickOk">כניסה</button>
  </div>
</div>

<!-- UPLOAD MODAL -->
<div class="ov" id="upOv">
  <div class="mdl" style="text-align:right">
    <h2 style="text-align:center">העלאת קובץ</h2>
    <img class="up-prev" id="upPv" alt="">
    <div class="up-bar" id="upBar"><div class="up-fill" id="upFill"></div></div>
    <input type="text" id="upTit" placeholder="כותרת..." style="text-align:right">
    <button class="mbtn mbtn-pri" id="upGo">העלאה</button>
    <button class="mbtn mbtn-sec" id="upNo">ביטול</button>
  </div>
</div>

<!-- LIGHTBOX -->
<div class="lb" id="lb" onclick="xLB()"><button class="lb-x" onclick="xLB()">&times;</button><img id="lbI" src="" alt="" onclick="event.stopPropagation()"><video id="lbV" src="" controls onclick="event.stopPropagation()" style="display:none"></video></div>

<script>
var nick=localStorage.getItem('mh_nick')||'',cp='<?=e($page)?>',ip=1,vp=1,upF=null;

function chkNick(){if(!nick){document.getElementById('nickOv').classList.add('show');setTimeout(function(){document.getElementById('nickIn').focus()},200)}}
document.getElementById('nickOk').onclick=function(){var v=document.getElementById('nickIn').value.trim();if(v){nick=v;localStorage.setItem('mh_nick',nick);document.getElementById('nickOv').classList.remove('show')}};
document.getElementById('nickIn').onkeydown=function(e){if(e.key==='Enter')document.getElementById('nickOk').click()};

function api(a,p){p=p||{};var u=new URL(location.href.split('?')[0]);u.searchParams.set('action',a);for(var k in p)u.searchParams.set(k,p[k]);return fetch(u).then(function(r){return r.json()})}
function apiP(a,fd){var u=new URL(location.href.split('?')[0]);u.searchParams.set('action',a);return fetch(u,{method:'POST',body:fd}).then(function(r){return r.json()})}
function esc(s){var d=document.createElement('div');d.textContent=s||'';return d.innerHTML}
function escA(s){return(s||'').replace(/'/g,"\\'").replace(/"/g,'&quot;')}

function mkCard(p,i){
  var isV=p.type==='video',th=p.thumb_url||p.media_url||'',t=p.title||(isV?'סרטון':'תמונה');
  var cl=isV?"oLB('"+escA(p.media_url)+"',true)":"oLB('"+escA(p.media_url)+"')";
  var vd='';
  if(isV){vd='<div class="vbadge"><svg width="8" height="8" viewBox="0 0 24 24" fill="white" stroke="none"><polygon points="5 3 19 12 5 21"/></svg> סרטון</div><div class="playbtn"><svg viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg></div>'}
  return '<div class="card" onclick="'+cl+'" style="animation-delay:'+(i*.03)+'s"><div class="card-wrap"><img class="card-img" src="'+escA(th)+'" alt="'+escA(t)+'" loading="lazy" onerror="this.style.background=\'#e8eaed\'">'+vd+'</div><div class="card-body"><div class="card-title">'+esc(t)+'</div><div class="card-meta"><span class="card-author">'+esc(p.nickname||'אנונימי')+'</span><span>'+esc(p.time_ago||'')+'</span></div></div></div>'
}

function mkHero(p,i){
  var th=p.thumb_url||p.media_url||'',t=p.title||'תמונה';
  return '<div class="hero-card" onclick="oLB(\''+escA(p.media_url)+'\')" style="animation-delay:'+(i*.04)+'s"><img class="hero-img" src="'+escA(th)+'" alt="'+escA(t)+'" loading="lazy"><div class="hero-ov"><div class="hero-meta"><span class="tag">חדש</span><span>'+esc(p.nickname||'אנונימי')+'</span><span>'+esc(p.time_ago||'')+'</span></div><div class="hero-title">'+esc(t)+'</div></div></div>'
}

function mkE(m){return '<div class="empty"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg><h3>'+m+'</h3><p>העלו תוכן חדש כדי להתחיל</p></div>'}

function mkPgr(el,cur,tot,fn){
  el.innerHTML='';if(tot<=1)return;
  var pv=document.createElement('button');pv.textContent='הקודם';pv.disabled=cur<=1;pv.onclick=function(){fn(cur-1)};el.appendChild(pv);
  for(var i=1;i<=tot;i++){
    if(tot>7&&i>3&&i<tot-2&&Math.abs(i-cur)>1){if(i===4||i===tot-3){var d=document.createElement('button');d.textContent='...';d.disabled=true;el.appendChild(d)}continue}
    var b=document.createElement('button');b.textContent=i;if(i===cur)b.classList.add('on');
    (function(pg){b.onclick=function(){fn(pg)}})(i);el.appendChild(b);
  }
  var nx=document.createElement('button');nx.textContent='הבא';nx.disabled=cur>=tot;nx.onclick=function(){fn(cur+1)};el.appendChild(nx);
}

function loadHome(){
  var hb=document.getElementById('heroBox'),gr=document.getElementById('homeGrid');if(!gr)return;
  api('get_recent').then(function(d){
    if(!d.posts||!d.posts.length){hb.innerHTML='';gr.innerHTML=mkE('עדיין אין תמונות');return}
    if(d.posts.length>=3){
      hb.innerHTML='<div class="hero">'+d.posts.slice(0,3).map(function(p,i){return mkHero(p,i)}).join('')+'</div>';
      gr.innerHTML=d.posts.slice(3).map(function(p,i){return mkCard(p,i)}).join('');
    } else {hb.innerHTML='';gr.innerHTML=d.posts.map(function(p,i){return mkCard(p,i)}).join('')}
  }).catch(function(e){gr.innerHTML=mkE('שגיאה: '+e.message)})
}

function loadImg(pg){
  var g=document.getElementById('imgGrid');if(!g)return;
  g.innerHTML='<div class="ld"><div class="sp"></div><p>טוען...</p></div>';
  api('get_posts',{type:'image',page:pg}).then(function(d){
    if(!d.posts||!d.posts.length){g.innerHTML=mkE('עדיין אין תמונות');return}
    g.innerHTML=d.posts.map(function(p,i){return mkCard(p,i)}).join('');
    var pe=document.getElementById('imgPgr');if(pe)mkPgr(pe,d.page,d.pages,function(p){ip=p;loadImg(p)})
  }).catch(function(){g.innerHTML=mkE('שגיאה')})
}

function loadVid(pg){
  var g=document.getElementById('vidGrid');if(!g)return;
  g.innerHTML='<div class="ld"><div class="sp"></div><p>טוען...</p></div>';
  api('get_posts',{type:'video',page:pg}).then(function(d){
    if(!d.posts||!d.posts.length){g.innerHTML=mkE('עדיין אין סרטונים');return}
    g.innerHTML=d.posts.map(function(p,i){return mkCard(p,i)}).join('');
    var pe=document.getElementById('vidPgr');if(pe)mkPgr(pe,d.page,d.pages,function(p){vp=p;loadVid(p)})
  }).catch(function(){g.innerHTML=mkE('שגיאה')})
}

// Lightbox
function oLB(u,isVideo){
  var img=document.getElementById('lbI'),vid=document.getElementById('lbV');
  if(isVideo){img.style.display='none';vid.style.display='block';vid.src=u;vid.play();}
  else{vid.style.display='none';vid.pause();vid.src='';img.style.display='block';img.src=u;}
  document.getElementById('lb').classList.add('show');document.body.style.overflow='hidden';
}
function xLB(){var vid=document.getElementById('lbV');vid.pause();vid.src='';document.getElementById('lb').classList.remove('show');document.body.style.overflow='';}
document.onkeydown=function(e){if(e.key==='Escape')xLB()};

// Upload
var uz=document.getElementById('uz'),uf=document.getElementById('uf');
if(uz){
  uz.ondragover=function(e){e.preventDefault();uz.classList.add('ov')};
  uz.ondragleave=function(){uz.classList.remove('ov')};
  uz.ondrop=function(e){e.preventDefault();uz.classList.remove('ov');if(e.dataTransfer.files.length)showUp(e.dataTransfer.files[0])};
}
if(uf)uf.onchange=function(){if(this.files.length)showUp(this.files[0]);this.value=''};

function showUp(f){
  if(!nick){chkNick();return}
  upF=f;var pv=document.getElementById('upPv');
  if(f.type.startsWith('image/')){pv.src=URL.createObjectURL(f);pv.style.display='block'}else{pv.style.display='none'}
  document.getElementById('upTit').value='';document.getElementById('upBar').style.display='none';
  document.getElementById('upOv').classList.add('show');
}
document.getElementById('upNo').onclick=function(){document.getElementById('upOv').classList.remove('show');upF=null};
document.getElementById('upGo').onclick=function(){
  if(!upF)return;
  var bar=document.getElementById('upBar'),fill=document.getElementById('upFill');bar.style.display='block';
  var fd=new FormData();fd.append('media',upF);fd.append('title',document.getElementById('upTit').value.trim());fd.append('nickname',nick);
  var xhr=new XMLHttpRequest();xhr.open('POST','index.php?action=upload');
  xhr.upload.onprogress=function(e){if(e.lengthComputable)fill.style.width=Math.round(e.loaded/e.total*100)+'%'};
  xhr.onload=function(){
    document.getElementById('upOv').classList.remove('show');upF=null;
    try{var r=JSON.parse(xhr.responseText);if(r.error)alert(r.error)}catch(e){}
    if(cp==='home')loadHome();else if(cp==='images')loadImg(ip);else if(cp==='videos')loadVid(vp);
  };
  xhr.onerror=function(){alert('שגיאה בהעלאה');bar.style.display='none'};
  xhr.send(fd);
};

// Chat
function loadChat(){
  api('get_chat').then(function(d){
    var c=document.getElementById('chatMsgs');
    if(!d.messages||!d.messages.length){c.innerHTML='<div style="text-align:center;color:#999;padding:18px;font-size:.8rem">הצ\'אט ריק - היו הראשונים!</div>';return}
    var ab=c.scrollTop>=c.scrollHeight-c.clientHeight-40,pv=c.children.length;
    c.innerHTML=d.messages.map(function(m){
      var mine=m.nickname===nick;
      var h='<div class="cmsg '+(mine?'me':'ot')+'">';
      if(!mine)h+='<div class="cmsg-nick">'+esc(m.nickname)+'</div>';
      if(m.image_url)h+='<img src="'+escA(m.image_url)+'" onclick="event.stopPropagation();oLB(\''+escA(m.image_url)+'\')" alt="">';
      if(m.message)h+='<div>'+esc(m.message)+'</div>';
      h+='<div class="cmsg-t">'+esc(m.time_ago||'')+'</div></div>';return h;
    }).join('');
    if(ab||c.children.length!==pv)c.scrollTop=c.scrollHeight;
  }).catch(function(){})
}
document.getElementById('cSend').onclick=sendMsg;
document.getElementById('cTxt').onkeydown=function(e){if(e.key==='Enter')sendMsg()};
function sendMsg(){
  if(!nick){chkNick();return}
  var inp=document.getElementById('cTxt'),m=inp.value.trim();if(!m)return;inp.value='';
  var fd=new FormData();fd.append('nickname',nick);fd.append('message',m);
  apiP('send_chat',fd).then(function(){loadChat()}).catch(function(){});
}
document.getElementById('cImg').onchange=function(){
  if(!this.files.length)return;if(!nick){chkNick();this.value='';return}
  var fd=new FormData();fd.append('nickname',nick);fd.append('message','');fd.append('image',this.files[0]);this.value='';
  apiP('send_chat',fd).then(function(){loadChat();if(cp==='home')setTimeout(loadHome,400)}).catch(function(){});
};
loadChat();setInterval(loadChat,4000);

// Fix chat scroll - prevent page scroll when scrolling inside chat
(function(){
  var chat=document.getElementById('chatBox');
  if(!chat)return;
  chat.addEventListener('wheel',function(e){
    var msgs=document.getElementById('chatMsgs');
    var atTop=msgs.scrollTop<=0;
    var atBot=msgs.scrollTop>=msgs.scrollHeight-msgs.clientHeight-1;
    if((e.deltaY<0&&atTop)||(e.deltaY>0&&atBot)){e.preventDefault();}
    e.stopPropagation();
  },{passive:false});
  chat.addEventListener('touchmove',function(e){e.stopPropagation();},{passive:true});
})();

// Search
document.getElementById('sInput').oninput=function(){
  var q=this.value.trim().toLowerCase();
  document.querySelectorAll('.card,.hero-card').forEach(function(c){
    var t=c.querySelector('.card-title,.hero-title');
    c.style.display=(!q||(t&&t.textContent.toLowerCase().indexOf(q)!==-1))?'':'none';
  });
};

// Chat toggle
document.getElementById('chtgl').onclick=function(){document.getElementById('chatBox').classList.toggle('open')};
document.addEventListener('click',function(e){
  if(window.innerWidth<=1024){
    var sb=document.getElementById('chatBox'),tg=document.getElementById('chtgl');
    if(!sb.contains(e.target)&&!tg.contains(e.target)&&sb.classList.contains('open'))sb.classList.remove('open');
  }
});

chkNick();
if(cp==='home')loadHome();else if(cp==='images')loadImg(1);else if(cp==='videos')loadVid(1);
</script>
</body>
</html>
