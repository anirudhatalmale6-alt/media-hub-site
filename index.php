<?php
require_once __DIR__ . '/includes/config.php';

$user = currentUser();
$csrf = csrfToken();
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> - שיתוף מדיה</title>
    <style>
        /* ===== CSS RESET & BASE ===== */
        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #1a56db;
            --primary-dark: #1040a0;
            --primary-light: #3b82f6;
            --primary-lighter: #93c5fd;
            --accent: #0ea5e9;
            --bg: #f0f4f8;
            --bg-white: #ffffff;
            --text: #1e293b;
            --text-secondary: #64748b;
            --text-light: #94a3b8;
            --border: #e2e8f0;
            --border-light: #f1f5f9;
            --shadow: 0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.08), 0 2px 4px -2px rgba(0,0,0,0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.08), 0 4px 6px -4px rgba(0,0,0,0.06);
            --radius: 10px;
            --radius-sm: 6px;
            --radius-lg: 16px;
            --header-height: 62px;
            --chat-width: 340px;
            --font: 'Segoe UI', Tahoma, Arial, sans-serif;
            --transition: 0.2s ease;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: var(--font);
            background: var(--bg);
            color: var(--text);
            direction: rtl;
            line-height: 1.6;
            min-height: 100vh;
        }

        a { color: var(--primary); text-decoration: none; }
        a:hover { text-decoration: underline; }

        /* ===== HEADER ===== */
        .header {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: linear-gradient(135deg, #1a237e 0%, #1565c0 40%, #0288d1 100%);
            height: var(--header-height);
            display: flex;
            align-items: center;
            padding: 0 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.15);
        }

        .header-inner {
            display: flex;
            align-items: center;
            width: 100%;
            max-width: 1440px;
            margin: 0 auto;
            gap: 20px;
        }

        .logo {
            font-size: 24px;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
            cursor: pointer;
        }

        .logo-icon {
            width: 34px;
            height: 34px;
            background: rgba(255,255,255,0.2);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .nav-menu {
            display: flex;
            align-items: center;
            gap: 4px;
            margin: 0 auto;
        }

        .nav-link {
            color: rgba(255,255,255,0.85);
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            transition: var(--transition);
            cursor: pointer;
            white-space: nowrap;
            border: none;
            background: none;
            font-family: var(--font);
        }

        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.15);
            color: #fff;
            text-decoration: none;
        }

        .nav-link.active {
            background: rgba(255,255,255,0.2);
        }

        .nav-divider {
            width: 1px;
            height: 24px;
            background: rgba(255,255,255,0.2);
            margin: 0 4px;
        }

        .header-search {
            position: relative;
            flex-shrink: 0;
        }

        .header-search input {
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            padding: 8px 14px 8px 36px;
            color: #fff;
            font-size: 14px;
            width: 200px;
            transition: var(--transition);
            font-family: var(--font);
            direction: rtl;
        }

        .header-search input::placeholder { color: rgba(255,255,255,0.5); }
        .header-search input:focus {
            outline: none;
            background: rgba(255,255,255,0.25);
            border-color: rgba(255,255,255,0.4);
            width: 260px;
        }

        .header-search .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255,255,255,0.5);
            font-size: 14px;
            pointer-events: none;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            font-family: var(--font);
            white-space: nowrap;
        }

        .btn-white {
            background: rgba(255,255,255,0.15);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.25);
        }

        .btn-white:hover {
            background: rgba(255,255,255,0.25);
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-accent {
            background: linear-gradient(135deg, #0ea5e9, #3b82f6);
            color: #fff;
        }

        .btn-accent:hover {
            background: linear-gradient(135deg, #0284c7, #2563eb);
        }

        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 1px solid var(--border);
        }

        .btn-outline:hover {
            background: var(--bg);
        }

        .btn-danger {
            background: #ef4444;
            color: #fff;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }

        .user-menu-btn {
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.25);
            color: #fff;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            font-family: var(--font);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .user-menu-btn:hover {
            background: rgba(255,255,255,0.25);
        }

        .user-avatar-small {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: rgba(255,255,255,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
        }

        /* ===== LAYOUT ===== */
        .main-layout {
            display: flex;
            max-width: 1440px;
            margin: 0 auto;
            padding: 20px;
            gap: 20px;
            min-height: calc(100vh - var(--header-height));
        }

        .content-area {
            flex: 1;
            min-width: 0;
        }

        .chat-sidebar {
            width: var(--chat-width);
            flex-shrink: 0;
            position: sticky;
            top: calc(var(--header-height) + 20px);
            height: calc(100vh - var(--header-height) - 40px);
        }

        /* ===== SECTION HEADERS ===== */
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .section-title {
            font-size: 20px;
            font-weight: 800;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-title .icon {
            color: var(--primary);
        }

        .section-more {
            font-size: 13px;
            color: var(--primary);
            font-weight: 600;
            cursor: pointer;
        }

        .section-more:hover {
            text-decoration: underline;
        }

        /* ===== FILTER TABS ===== */
        .filter-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 7px 18px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid var(--border);
            background: var(--bg-white);
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
            font-family: var(--font);
        }

        .filter-tab:hover {
            border-color: var(--primary-lighter);
            color: var(--primary);
        }

        .filter-tab.active {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }

        /* ===== CARD GRID ===== */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }

        .card {
            background: var(--bg-white);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
            cursor: pointer;
            border: 1px solid var(--border-light);
        }

        .card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .card-thumb {
            position: relative;
            width: 100%;
            padding-top: 62%;
            background: #e2e8f0;
            overflow: hidden;
        }

        .card-thumb img, .card-thumb video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .card-thumb .type-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.6);
            color: #fff;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            backdrop-filter: blur(4px);
        }

        .card-thumb .play-icon {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 50px;
            height: 50px;
            background: rgba(0,0,0,0.5);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 22px;
            backdrop-filter: blur(4px);
        }

        .card-body {
            padding: 12px 14px;
        }

        .card-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
            line-height: 1.4;
            margin-bottom: 8px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .card-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12px;
            color: var(--text-light);
        }

        .card-user {
            display: flex;
            align-items: center;
            gap: 4px;
            font-weight: 600;
            color: var(--text-secondary);
        }

        .card-stats {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-stat {
            display: flex;
            align-items: center;
            gap: 3px;
        }

        .card-tags {
            display: flex;
            gap: 4px;
            margin-top: 8px;
            flex-wrap: wrap;
        }

        .tag-badge {
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 10px;
            background: #eff6ff;
            color: var(--primary);
            font-weight: 600;
        }

        /* ===== CHAT SIDEBAR ===== */
        .chat-panel {
            background: var(--bg-white);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
            height: 100%;
            border: 1px solid var(--border-light);
            overflow: hidden;
        }

        .chat-header {
            padding: 14px 16px;
            background: linear-gradient(135deg, #1a237e, #1565c0);
            color: #fff;
            font-weight: 700;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .chat-header .dot {
            width: 8px;
            height: 8px;
            background: #4ade80;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 12px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .chat-messages::-webkit-scrollbar { width: 5px; }
        .chat-messages::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

        .chat-msg {
            display: flex;
            gap: 8px;
            align-items: flex-start;
        }

        .chat-msg-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-light), var(--accent));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .chat-msg-content {
            flex: 1;
            min-width: 0;
        }

        .chat-msg-header {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 2px;
        }

        .chat-msg-name {
            font-size: 13px;
            font-weight: 700;
            color: var(--text);
        }

        .chat-msg-time {
            font-size: 11px;
            color: var(--text-light);
        }

        .chat-msg-text {
            font-size: 13px;
            color: var(--text-secondary);
            line-height: 1.5;
            word-break: break-word;
        }

        .chat-msg-image {
            margin-top: 6px;
            border-radius: 8px;
            overflow: hidden;
            max-width: 200px;
        }

        .chat-msg-image img {
            width: 100%;
            display: block;
            border-radius: 8px;
        }

        .chat-input-area {
            padding: 12px;
            border-top: 1px solid var(--border);
            flex-shrink: 0;
            background: #fafbfd;
        }

        .chat-input-row {
            display: flex;
            gap: 6px;
            align-items: flex-end;
        }

        .chat-input-wrapper {
            flex: 1;
            position: relative;
        }

        .chat-input {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 9px 12px;
            font-size: 13px;
            font-family: var(--font);
            resize: none;
            direction: rtl;
            line-height: 1.4;
            transition: var(--transition);
            background: var(--bg-white);
        }

        .chat-input:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
        }

        .chat-attach-btn {
            width: 36px;
            height: 36px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--bg-white);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            transition: var(--transition);
            flex-shrink: 0;
        }

        .chat-attach-btn:hover {
            background: var(--bg);
            color: var(--primary);
        }

        .chat-send-btn {
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 8px;
            background: var(--primary);
            color: #fff;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            flex-shrink: 0;
        }

        .chat-send-btn:hover {
            background: var(--primary-dark);
        }

        .chat-preview {
            margin-top: 8px;
            display: none;
            position: relative;
        }

        .chat-preview img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 6px;
            border: 2px solid var(--border);
        }

        .chat-preview .remove-preview {
            position: absolute;
            top: -6px;
            right: -6px;
            width: 20px;
            height: 20px;
            background: #ef4444;
            color: #fff;
            border: none;
            border-radius: 50%;
            font-size: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .chat-login-prompt {
            padding: 20px;
            text-align: center;
            color: var(--text-secondary);
            font-size: 13px;
        }

        .chat-login-prompt a {
            font-weight: 700;
        }

        /* ===== FLOATING UPLOAD BUTTON ===== */
        .fab-upload {
            position: fixed;
            bottom: 30px;
            left: 30px;
            width: 58px;
            height: 58px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0ea5e9, #3b82f6);
            color: #fff;
            border: none;
            box-shadow: 0 4px 20px rgba(59,130,246,0.4);
            cursor: pointer;
            font-size: 26px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            z-index: 900;
        }

        .fab-upload:hover {
            transform: scale(1.08);
            box-shadow: 0 6px 25px rgba(59,130,246,0.5);
        }

        /* ===== MODAL ===== */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal {
            background: var(--bg-white);
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 540px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
            animation: modalIn 0.25s ease;
        }

        @keyframes modalIn {
            from { opacity: 0; transform: scale(0.95) translateY(10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .modal-header {
            padding: 18px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-title {
            font-size: 18px;
            font-weight: 800;
        }

        .modal-close {
            width: 32px;
            height: 32px;
            border: none;
            background: var(--bg);
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }

        .modal-close:hover {
            background: #fee2e2;
            color: #ef4444;
        }

        .modal-body {
            padding: 24px;
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border);
            display: flex;
            gap: 10px;
            justify-content: flex-start;
        }

        /* ===== FORM ELEMENTS ===== */
        .form-group {
            margin-bottom: 18px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 6px;
        }

        .form-input, .form-textarea, .form-select {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 14px;
            font-family: var(--font);
            transition: var(--transition);
            direction: rtl;
            background: var(--bg-white);
            color: var(--text);
        }

        .form-input:focus, .form-textarea:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 80px;
        }

        /* File drop zone */
        .file-drop-zone {
            border: 2px dashed var(--border);
            border-radius: var(--radius);
            padding: 30px 20px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            background: var(--border-light);
        }

        .file-drop-zone:hover, .file-drop-zone.dragover {
            border-color: var(--primary-light);
            background: #eff6ff;
        }

        .file-drop-zone .icon {
            font-size: 36px;
            color: var(--text-light);
            margin-bottom: 8px;
        }

        .file-drop-zone .text {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .file-drop-zone .subtext {
            font-size: 12px;
            color: var(--text-light);
            margin-top: 4px;
        }

        .file-preview {
            margin-top: 12px;
            display: none;
        }

        .file-preview img, .file-preview video {
            max-width: 100%;
            max-height: 200px;
            border-radius: 8px;
            border: 1px solid var(--border);
        }

        .file-preview .file-info {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 6px;
        }

        /* Tags checkboxes */
        .tags-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .tag-checkbox {
            display: none;
        }

        .tag-label {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid var(--border);
            background: var(--bg-white);
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
            user-select: none;
        }

        .tag-checkbox:checked + .tag-label {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }

        .tag-label:hover {
            border-color: var(--primary-lighter);
        }

        /* ===== LIGHTBOX / CONTENT VIEWER ===== */
        .lightbox-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.85);
            z-index: 3000;
            align-items: center;
            justify-content: center;
            padding: 20px;
            backdrop-filter: blur(6px);
        }

        .lightbox-overlay.active {
            display: flex;
        }

        .lightbox-container {
            max-width: 900px;
            width: 100%;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            background: var(--bg-white);
            border-radius: var(--radius-lg);
            overflow: hidden;
            animation: modalIn 0.3s ease;
        }

        .lightbox-media {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #000;
            min-height: 300px;
            max-height: 60vh;
            overflow: hidden;
        }

        .lightbox-media img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .lightbox-media video {
            max-width: 100%;
            max-height: 100%;
        }

        .lightbox-info {
            padding: 20px 24px;
            max-height: 30vh;
            overflow-y: auto;
        }

        .lightbox-title {
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .lightbox-body {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.7;
            margin-bottom: 12px;
        }

        .lightbox-meta {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 13px;
            color: var(--text-light);
        }

        .lightbox-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .lightbox-close {
            position: fixed;
            top: 20px;
            left: 20px;
            width: 40px;
            height: 40px;
            border: none;
            background: rgba(255,255,255,0.15);
            color: #fff;
            border-radius: 50%;
            font-size: 20px;
            cursor: pointer;
            z-index: 3001;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
            transition: var(--transition);
        }

        .lightbox-close:hover {
            background: rgba(255,255,255,0.3);
        }

        /* ===== LOADING / EMPTY STATES ===== */
        .loading-spinner {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }

        .spinner {
            width: 36px;
            height: 36px;
            border: 3px solid var(--border);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-light);
        }

        .empty-state .icon {
            font-size: 48px;
            margin-bottom: 12px;
        }

        .empty-state .title {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }

        /* ===== TOAST NOTIFICATIONS ===== */
        .toast-container {
            position: fixed;
            top: 80px;
            left: 20px;
            z-index: 5000;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .toast {
            background: var(--bg-white);
            border-radius: 10px;
            padding: 12px 18px;
            box-shadow: var(--shadow-lg);
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            animation: toastIn 0.3s ease;
            border-right: 4px solid var(--primary);
        }

        .toast.success { border-right-color: #22c55e; }
        .toast.error { border-right-color: #ef4444; }

        @keyframes toastIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1100px) {
            .chat-sidebar {
                display: none;
            }
            .header-search input { width: 160px; }
            .header-search input:focus { width: 200px; }
        }

        @media (max-width: 768px) {
            .header { padding: 0 12px; }
            .header-inner { gap: 10px; }
            .nav-menu { display: none; }
            .header-search { display: none; }
            .logo { font-size: 20px; }
            .main-layout { padding: 12px; }
            .cards-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 10px;
            }
            .card-body { padding: 10px; }
            .card-title { font-size: 13px; }
            .section-title { font-size: 17px; }
            .modal { max-width: 100%; border-radius: var(--radius); }
            .fab-upload { bottom: 20px; left: 20px; width: 50px; height: 50px; font-size: 22px; }
        }

        /* ===== SCROLLBAR ===== */
        ::-webkit-scrollbar { width: 7px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        /* ===== DROPDOWN ===== */
        .dropdown {
            position: relative;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            background: var(--bg-white);
            border: 1px solid var(--border);
            border-radius: 10px;
            box-shadow: var(--shadow-lg);
            min-width: 180px;
            z-index: 1100;
            overflow: hidden;
            animation: modalIn 0.15s ease;
        }

        .dropdown-menu.show { display: block; }

        .dropdown-item {
            padding: 10px 16px;
            font-size: 14px;
            color: var(--text);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            border: none;
            background: none;
            width: 100%;
            font-family: var(--font);
            text-align: right;
        }

        .dropdown-item:hover {
            background: var(--bg);
        }

        .dropdown-divider {
            height: 1px;
            background: var(--border);
        }

        /* Users link style */
        .users-link {
            color: rgba(255,255,255,0.85);
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
            transition: var(--transition);
            cursor: pointer;
            padding: 6px 10px;
            border-radius: 6px;
        }

        .users-link:hover {
            color: #fff;
            background: rgba(255,255,255,0.1);
            text-decoration: none;
        }
    </style>
</head>
<body>

<!-- ===== HEADER ===== -->
<header class="header">
    <div class="header-inner">
        <!-- Logo (right side in RTL) -->
        <div class="logo" onclick="window.location.reload()">
            <div class="logo-icon">&#9654;</div>
            <?= SITE_NAME ?>
        </div>

        <!-- Center Navigation -->
        <nav class="nav-menu">
            <button class="nav-link active" data-filter="all" onclick="setTypeFilter('all', this)">&#128196; הכל</button>
            <div class="nav-divider"></div>
            <button class="nav-link" data-filter="image" onclick="setTypeFilter('image', this)">&#128247; תמונות</button>
            <div class="nav-divider"></div>
            <button class="nav-link" data-filter="video" onclick="setTypeFilter('video', this)">&#127909; סרטונים</button>
        </nav>

        <!-- Search -->
        <div class="header-search">
            <input type="text" id="searchInput" placeholder="חיפוש מתקדם..." oninput="debounceSearch()">
            <span class="search-icon">&#128269;</span>
        </div>

        <!-- Actions -->
        <div class="header-actions">
            <a href="#" class="users-link" onclick="showToast('דף המשתמשים בקרוב'); return false;">&#128101; משתמשים</a>

            <?php if (isLoggedIn()): ?>
                <div class="dropdown">
                    <button class="user-menu-btn" onclick="toggleDropdown(this)">
                        <span class="user-avatar-small">&#128100;</span>
                        <?= e($user['display_name']) ?>
                        &#9662;
                    </button>
                    <div class="dropdown-menu">
                        <div class="dropdown-item" style="color:var(--text-light); font-size:12px; cursor:default;">
                            <?= e($user['username']) ?>
                        </div>
                        <div class="dropdown-divider"></div>
                        <?php if (isAdmin()): ?>
                        <a href="admin/tags.php" class="dropdown-item">&#9881; ניהול תגיות</a>
                        <div class="dropdown-divider"></div>
                        <?php endif; ?>
                        <button class="dropdown-item" onclick="doLogout()" style="color:#ef4444;">&#128682; התנתק</button>
                    </div>
                </div>
            <?php else: ?>
                <button class="btn btn-white" onclick="openModal('loginModal')">&#128274; התחברות</button>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- ===== MAIN LAYOUT ===== -->
<div class="main-layout">

    <!-- Chat Sidebar (LEFT in RTL) -->
    <aside class="chat-sidebar">
        <div class="chat-panel">
            <div class="chat-header">
                <span class="dot"></span>
                &#128172; צ'אט חי
                <span style="margin-right:auto; font-size:12px; opacity:0.7;" id="chatOnlineCount"></span>
            </div>

            <div class="chat-messages" id="chatMessages">
                <div class="loading-spinner"><div class="spinner"></div></div>
            </div>

            <?php if (isLoggedIn()): ?>
            <div class="chat-input-area">
                <div class="chat-preview" id="chatPreview">
                    <img id="chatPreviewImg" src="" alt="">
                    <button class="remove-preview" onclick="removeChatImage()">&times;</button>
                </div>
                <div class="chat-input-row">
                    <div class="chat-input-wrapper">
                        <textarea class="chat-input" id="chatInput" rows="1" placeholder="כתוב הודעה..." onkeydown="chatKeyDown(event)"></textarea>
                    </div>
                    <label class="chat-attach-btn" for="chatFileInput" title="צרף תמונה">
                        &#128206;
                        <input type="file" id="chatFileInput" accept="image/*" style="display:none" onchange="previewChatImage(this)">
                    </label>
                    <button class="chat-send-btn" onclick="sendChat()" title="שלח">&#10148;</button>
                </div>
                <div style="font-size:11px; color:var(--text-light); margin-top:6px;">
                    * שליחה עם תמונה תיצור גם פוסט חדש
                </div>
            </div>
            <?php else: ?>
            <div class="chat-login-prompt">
                &#128274; <a href="#" onclick="openModal('loginModal'); return false;">התחבר</a> כדי להשתתף בצ'אט
            </div>
            <?php endif; ?>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="content-area">

        <!-- Filter Tabs -->
        <div class="filter-tabs" id="tagFilters">
            <button class="filter-tab active" data-tag="all" onclick="setTagFilter('all', this)">הכל</button>
            <!-- Tags loaded dynamically -->
        </div>

        <!-- Recent Uploads Section -->
        <section class="content-section" id="recentSection">
            <div class="section-header">
                <h2 class="section-title"><span class="icon">&#128293;</span> העלאות אחרונות</h2>
                <span class="section-more" onclick="loadMore('recent')">הצג עוד &larr;</span>
            </div>
            <div class="cards-grid" id="recentGrid">
                <div class="loading-spinner" style="grid-column:1/-1"><div class="spinner"></div></div>
            </div>
        </section>

        <!-- Most Viewed Section -->
        <section class="content-section" id="popularSection">
            <div class="section-header">
                <h2 class="section-title"><span class="icon">&#128065;</span> הכי נצפים</h2>
                <span class="section-more" onclick="loadMore('popular')">הצג עוד &larr;</span>
            </div>
            <div class="cards-grid" id="popularGrid">
                <div class="loading-spinner" style="grid-column:1/-1"><div class="spinner"></div></div>
            </div>
        </section>

    </div>
</div>

<!-- ===== FLOATING UPLOAD BUTTON ===== -->
<button class="fab-upload" onclick="openUploadModal()" title="העלה תוכן חדש">&#43;</button>

<!-- ===== LOGIN MODAL ===== -->
<div class="modal-overlay" id="loginModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">&#128274; התחברות</h3>
            <button class="modal-close" onclick="closeModal('loginModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div id="loginError" style="display:none; color:#ef4444; font-size:13px; margin-bottom:12px; padding:10px; background:#fef2f2; border-radius:8px;"></div>
            <div class="form-group">
                <label class="form-label">שם משתמש</label>
                <input type="text" class="form-input" id="loginUsername" placeholder="הכנס שם משתמש">
            </div>
            <div class="form-group">
                <label class="form-label">סיסמה</label>
                <input type="password" class="form-input" id="loginPassword" placeholder="הכנס סיסמה" onkeydown="if(event.key==='Enter')doLogin()">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" onclick="doLogin()">&#128274; התחבר</button>
            <button class="btn btn-outline" onclick="closeModal('loginModal')">ביטול</button>
        </div>
        <div style="padding:0 24px 18px; font-size:12px; color:var(--text-light);">
            * ברירת מחדל: admin / admin123 או demo / user123
        </div>
    </div>
</div>

<!-- ===== UPLOAD MODAL ===== -->
<div class="modal-overlay" id="uploadModal">
    <div class="modal" style="max-width:580px;">
        <div class="modal-header">
            <h3 class="modal-title">&#128228; העלאת תוכן חדש</h3>
            <button class="modal-close" onclick="closeModal('uploadModal')">&times;</button>
        </div>
        <div class="modal-body">
            <?php if (!isLoggedIn()): ?>
            <div class="empty-state">
                <div class="icon">&#128274;</div>
                <div class="title">יש להתחבר תחילה</div>
                <p style="margin-top:10px"><button class="btn btn-primary" onclick="closeModal('uploadModal'); openModal('loginModal')">התחבר</button></p>
            </div>
            <?php else: ?>
            <div id="uploadError" style="display:none; color:#ef4444; font-size:13px; margin-bottom:12px; padding:10px; background:#fef2f2; border-radius:8px;"></div>

            <!-- File Drop Zone -->
            <div class="form-group">
                <label class="form-label">בחר קובץ</label>
                <div class="file-drop-zone" id="fileDropZone" onclick="document.getElementById('uploadFile').click()">
                    <div class="icon">&#128193;</div>
                    <div class="text">לחץ כאן או גרור קובץ</div>
                    <div class="subtext">תמונות: JPG, PNG, GIF, WebP | סרטונים: MP4, WebM</div>
                    <input type="file" id="uploadFile" accept="image/*,video/*" style="display:none" onchange="previewUploadFile(this)">
                </div>
                <div class="file-preview" id="uploadPreview">
                    <div id="uploadPreviewContent"></div>
                    <div class="file-info" id="uploadFileInfo"></div>
                </div>
            </div>

            <!-- Title -->
            <div class="form-group">
                <label class="form-label">כותרת</label>
                <input type="text" class="form-input" id="uploadTitle" placeholder="כותרת התוכן" maxlength="255">
            </div>

            <!-- Body -->
            <div class="form-group">
                <label class="form-label">תיאור (אופציונלי)</label>
                <textarea class="form-textarea" id="uploadBody" placeholder="תיאור או גוף הטקסט..." rows="3"></textarea>
            </div>

            <!-- Tags -->
            <div class="form-group">
                <label class="form-label">תגיות</label>
                <div class="tags-grid" id="uploadTagsGrid">
                    <!-- Loaded dynamically -->
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php if (isLoggedIn()): ?>
        <div class="modal-footer">
            <button class="btn btn-accent" onclick="submitUpload()" id="uploadSubmitBtn">&#128228; העלה</button>
            <button class="btn btn-outline" onclick="closeModal('uploadModal')">ביטול</button>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== LIGHTBOX / CONTENT VIEWER ===== -->
<div class="lightbox-overlay" id="lightbox" onclick="closeLightbox(event)">
    <button class="lightbox-close" onclick="closeLightbox()">&times;</button>
    <div class="lightbox-container" onclick="event.stopPropagation()">
        <div class="lightbox-media" id="lightboxMedia"></div>
        <div class="lightbox-info">
            <div class="lightbox-title" id="lightboxTitle"></div>
            <div class="lightbox-body" id="lightboxBody"></div>
            <div class="lightbox-meta" id="lightboxMeta"></div>
            <div class="card-tags" id="lightboxTags" style="margin-top:10px;"></div>
        </div>
    </div>
</div>

<!-- ===== TOAST CONTAINER ===== -->
<div class="toast-container" id="toastContainer"></div>


<!-- ===== JAVASCRIPT ===== -->
<script>
(function() {
    'use strict';

    // ===== CONFIGURATION =====
    const API = {
        posts: 'api/posts.php',
        chat: 'api/chat.php',
        auth: 'api/auth.php',
        tags: 'api/tags.php',
    };

    const CSRF = '<?= $csrf ?>';
    let currentTypeFilter = 'all';
    let currentTagFilter = 'all';
    let searchQuery = '';
    let searchTimeout = null;
    let chatInterval = null;
    let lastChatId = 0;
    let recentPage = 0;
    let popularPage = 0;

    // ===== INITIALIZATION =====
    document.addEventListener('DOMContentLoaded', function() {
        loadTags();
        loadPosts('recent');
        loadPosts('popular');
        loadChat();
        chatInterval = setInterval(loadChat, 5000); // Poll every 5s
        setupDragDrop();
    });

    // ===== TAG LOADING =====
    window.loadTags = async function() {
        try {
            const res = await fetch(API.tags);
            const data = await res.json();
            if (data.tags) {
                renderTagFilters(data.tags);
                renderUploadTags(data.tags);
            }
        } catch(e) { console.error('loadTags:', e); }
    };

    function renderTagFilters(tags) {
        const container = document.getElementById('tagFilters');
        // Keep the "all" button
        let html = '<button class="filter-tab active" data-tag="all" onclick="setTagFilter(\'all\', this)">הכל</button>';
        tags.forEach(tag => {
            html += '<button class="filter-tab" data-tag="' + tag.id + '" onclick="setTagFilter(\'' + tag.id + '\', this)">' + escHtml(tag.name) + '</button>';
        });
        container.innerHTML = html;
    }

    function renderUploadTags(tags) {
        const container = document.getElementById('uploadTagsGrid');
        if (!container) return;
        let html = '';
        tags.forEach(tag => {
            html += '<input type="checkbox" class="tag-checkbox" id="utag_' + tag.id + '" name="tags[]" value="' + tag.id + '">';
            html += '<label class="tag-label" for="utag_' + tag.id + '">' + escHtml(tag.name) + '</label>';
        });
        container.innerHTML = html;
    }

    // ===== POSTS LOADING =====
    window.loadPosts = async function(section, append) {
        const grid = document.getElementById(section === 'recent' ? 'recentGrid' : 'popularGrid');
        const page = section === 'recent' ? recentPage : popularPage;

        if (!append) {
            grid.innerHTML = '<div class="loading-spinner" style="grid-column:1/-1"><div class="spinner"></div></div>';
        }

        try {
            let url = API.posts + '?sort=' + (section === 'recent' ? 'recent' : 'views');
            url += '&page=' + page;
            url += '&limit=8';
            if (currentTypeFilter !== 'all') url += '&type=' + currentTypeFilter;
            if (currentTagFilter !== 'all') url += '&tag=' + currentTagFilter;
            if (searchQuery) url += '&q=' + encodeURIComponent(searchQuery);

            const res = await fetch(url);
            const data = await res.json();

            if (!append) grid.innerHTML = '';

            if (data.posts && data.posts.length > 0) {
                data.posts.forEach(post => {
                    grid.insertAdjacentHTML('beforeend', renderCard(post));
                });
            } else if (!append) {
                grid.innerHTML = '<div class="empty-state" style="grid-column:1/-1"><div class="icon">&#128194;</div><div class="title">אין תוכן להצגה</div><p>נסה לשנות את הסינון או העלה תוכן חדש</p></div>';
            }
        } catch(e) {
            console.error('loadPosts:', e);
            if (!append) grid.innerHTML = '<div class="empty-state" style="grid-column:1/-1"><div class="icon">&#9888;</div><div class="title">שגיאה בטעינה</div></div>';
        }
    };

    function renderCard(post) {
        const isVideo = post.type === 'video';
        const thumbUrl = post.thumbnail_url || post.media_url || '';
        const typeBadge = isVideo ? 'סרטון' : 'תמונה';
        const playIcon = isVideo ? '<div class="play-icon">&#9654;</div>' : '';

        let tagsHtml = '';
        if (post.tags && post.tags.length > 0) {
            tagsHtml = '<div class="card-tags">';
            post.tags.forEach(t => {
                tagsHtml += '<span class="tag-badge">' + escHtml(t.name) + '</span>';
            });
            tagsHtml += '</div>';
        }

        return '<div class="card" onclick="openPost(' + post.id + ')" data-id="' + post.id + '">' +
            '<div class="card-thumb">' +
                (thumbUrl ?
                    (isVideo ?
                        '<img src="' + escAttr(thumbUrl) + '" alt="">' :
                        '<img src="' + escAttr(thumbUrl) + '" alt="">'
                    ) :
                    '<div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:var(--text-light);font-size:40px;">&#128444;</div>'
                ) +
                playIcon +
                '<span class="type-badge">' + typeBadge + '</span>' +
            '</div>' +
            '<div class="card-body">' +
                '<div class="card-title">' + escHtml(post.title || 'ללא כותרת') + '</div>' +
                '<div class="card-meta">' +
                    '<span class="card-user">&#128100; ' + escHtml(post.display_name || post.username || 'אנונימי') + '</span>' +
                    '<span class="card-stats">' +
                        '<span class="card-stat">&#128065; ' + formatNum(post.views || 0) + '</span>' +
                        '<span class="card-stat">&#128337; ' + escHtml(post.time_ago || '') + '</span>' +
                    '</span>' +
                '</div>' +
                tagsHtml +
            '</div>' +
        '</div>';
    }

    // ===== OPEN SINGLE POST (LIGHTBOX) =====
    window.openPost = async function(postId) {
        const lightbox = document.getElementById('lightbox');
        const mediaEl = document.getElementById('lightboxMedia');
        const titleEl = document.getElementById('lightboxTitle');
        const bodyEl = document.getElementById('lightboxBody');
        const metaEl = document.getElementById('lightboxMeta');
        const tagsEl = document.getElementById('lightboxTags');

        mediaEl.innerHTML = '<div class="loading-spinner"><div class="spinner"></div></div>';
        titleEl.textContent = '';
        bodyEl.textContent = '';
        metaEl.innerHTML = '';
        tagsEl.innerHTML = '';
        lightbox.classList.add('active');
        document.body.style.overflow = 'hidden';

        try {
            const res = await fetch(API.posts + '?id=' + postId);
            const data = await res.json();
            if (!data.post) throw new Error('Not found');
            const post = data.post;

            // Media
            if (post.type === 'video') {
                mediaEl.innerHTML = '<video controls autoplay style="max-width:100%;max-height:100%"><source src="' + escAttr(post.media_url) + '"></video>';
            } else {
                mediaEl.innerHTML = '<img src="' + escAttr(post.media_url) + '" alt="' + escAttr(post.title) + '">';
            }

            titleEl.textContent = post.title || 'ללא כותרת';
            bodyEl.textContent = post.body || '';

            metaEl.innerHTML =
                '<span>&#128100; ' + escHtml(post.display_name || 'אנונימי') + '</span>' +
                '<span>&#128065; ' + formatNum(post.views) + ' צפיות</span>' +
                '<span>&#128337; ' + escHtml(post.time_ago) + '</span>';

            if (post.tags && post.tags.length) {
                tagsEl.innerHTML = post.tags.map(t => '<span class="tag-badge">' + escHtml(t.name) + '</span>').join('');
            }
        } catch(e) {
            mediaEl.innerHTML = '<div class="empty-state"><div class="icon">&#9888;</div><div class="title">שגיאה בטעינה</div></div>';
        }
    };

    window.closeLightbox = function(e) {
        if (e && e.target !== e.currentTarget && !e.target.classList.contains('lightbox-close')) return;
        document.getElementById('lightbox').classList.remove('active');
        document.body.style.overflow = '';
        // Stop any playing video
        const vid = document.querySelector('#lightboxMedia video');
        if (vid) vid.pause();
    };

    // ===== FILTERS =====
    window.setTypeFilter = function(type, btn) {
        currentTypeFilter = type;
        recentPage = 0;
        popularPage = 0;
        document.querySelectorAll('.nav-link').forEach(el => el.classList.remove('active'));
        btn.classList.add('active');
        loadPosts('recent');
        loadPosts('popular');
    };

    window.setTagFilter = function(tagId, btn) {
        currentTagFilter = tagId;
        recentPage = 0;
        popularPage = 0;
        document.querySelectorAll('.filter-tab').forEach(el => el.classList.remove('active'));
        btn.classList.add('active');
        loadPosts('recent');
        loadPosts('popular');
    };

    window.debounceSearch = function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            searchQuery = document.getElementById('searchInput').value.trim();
            recentPage = 0;
            popularPage = 0;
            loadPosts('recent');
            loadPosts('popular');
        }, 400);
    };

    window.loadMore = function(section) {
        if (section === 'recent') recentPage++;
        else popularPage++;
        loadPosts(section, true);
    };

    // ===== CHAT =====
    window.loadChat = async function() {
        try {
            const res = await fetch(API.chat + '?after=' + lastChatId);
            const data = await res.json();
            if (!data.messages) return;

            const container = document.getElementById('chatMessages');
            const isAtBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 50;

            if (lastChatId === 0) {
                container.innerHTML = '';
            }

            if (data.messages.length > 0) {
                data.messages.forEach(msg => {
                    container.insertAdjacentHTML('beforeend', renderChatMsg(msg));
                    lastChatId = Math.max(lastChatId, msg.id);
                });

                if (isAtBottom) {
                    container.scrollTop = container.scrollHeight;
                }
            } else if (lastChatId === 0) {
                container.innerHTML = '<div class="empty-state"><div class="icon">&#128172;</div><div class="title">אין הודעות עדיין</div><p>התחל שיחה!</p></div>';
            }
        } catch(e) { console.error('loadChat:', e); }
    };

    function renderChatMsg(msg) {
        const initial = (msg.display_name || '?').charAt(0);
        let imageHtml = '';
        if (msg.media_url) {
            imageHtml = '<div class="chat-msg-image"><img src="' + escAttr(msg.media_url) + '" alt="" onclick="event.stopPropagation(); if(this.dataset.postId) openPost(this.dataset.postId);" data-post-id="' + (msg.post_id || '') + '"></div>';
        }

        return '<div class="chat-msg">' +
            '<div class="chat-msg-avatar">' + escHtml(initial) + '</div>' +
            '<div class="chat-msg-content">' +
                '<div class="chat-msg-header">' +
                    '<span class="chat-msg-name">' + escHtml(msg.display_name || 'אנונימי') + '</span>' +
                    '<span class="chat-msg-time">' + escHtml(msg.time_ago || '') + '</span>' +
                '</div>' +
                '<div class="chat-msg-text">' + escHtml(msg.message) + '</div>' +
                imageHtml +
            '</div>' +
        '</div>';
    }

    window.chatKeyDown = function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendChat();
        }
    };

    window.sendChat = async function() {
        const input = document.getElementById('chatInput');
        const message = input.value.trim();
        const fileInput = document.getElementById('chatFileInput');
        const file = fileInput.files[0];

        if (!message && !file) return;

        const formData = new FormData();
        formData.append('message', message || '(תמונה)');
        formData.append('csrf_token', CSRF);
        if (file) {
            formData.append('image', file);
        }

        input.value = '';
        removeChatImage();

        try {
            const res = await fetch(API.chat, { method: 'POST', body: formData });
            const data = await res.json();
            if (data.error) {
                showToast(data.error, 'error');
                return;
            }
            // Force reload chat
            lastChatId = 0;
            await loadChat();
            // If a post was created, reload posts too
            if (data.post_id) {
                recentPage = 0;
                loadPosts('recent');
                showToast('ההודעה נשלחה ופוסט חדש נוצר!', 'success');
            }
        } catch(e) {
            showToast('שגיאה בשליחת ההודעה', 'error');
        }
    };

    window.previewChatImage = function(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('chatPreviewImg').src = e.target.result;
                document.getElementById('chatPreview').style.display = 'inline-block';
            };
            reader.readAsDataURL(input.files[0]);
        }
    };

    window.removeChatImage = function() {
        document.getElementById('chatPreview').style.display = 'none';
        document.getElementById('chatPreviewImg').src = '';
        document.getElementById('chatFileInput').value = '';
    };

    // ===== UPLOAD =====
    window.openUploadModal = function() {
        openModal('uploadModal');
    };

    window.previewUploadFile = function(input) {
        const preview = document.getElementById('uploadPreview');
        const content = document.getElementById('uploadPreviewContent');
        const info = document.getElementById('uploadFileInfo');

        if (input.files && input.files[0]) {
            const file = input.files[0];
            const isVideo = file.type.startsWith('video/');

            info.textContent = file.name + ' (' + formatBytes(file.size) + ')';

            if (isVideo) {
                const url = URL.createObjectURL(file);
                content.innerHTML = '<video src="' + url + '" controls style="max-width:100%;max-height:200px;border-radius:8px;"></video>';
            } else {
                const reader = new FileReader();
                reader.onload = function(e) {
                    content.innerHTML = '<img src="' + e.target.result + '" alt="preview">';
                };
                reader.readAsDataURL(file);
            }
            preview.style.display = 'block';
            document.getElementById('fileDropZone').style.display = 'none';
        }
    };

    window.submitUpload = async function() {
        const fileInput = document.getElementById('uploadFile');
        const title = document.getElementById('uploadTitle').value.trim();
        const body = document.getElementById('uploadBody').value.trim();
        const errorEl = document.getElementById('uploadError');
        const submitBtn = document.getElementById('uploadSubmitBtn');

        errorEl.style.display = 'none';

        if (!fileInput.files[0]) {
            errorEl.textContent = 'יש לבחור קובץ';
            errorEl.style.display = 'block';
            return;
        }
        if (!title) {
            errorEl.textContent = 'יש להזין כותרת';
            errorEl.style.display = 'block';
            return;
        }

        const formData = new FormData();
        formData.append('file', fileInput.files[0]);
        formData.append('title', title);
        formData.append('body', body);
        formData.append('csrf_token', CSRF);

        // Collect selected tags
        document.querySelectorAll('#uploadTagsGrid .tag-checkbox:checked').forEach(cb => {
            formData.append('tags[]', cb.value);
        });

        submitBtn.disabled = true;
        submitBtn.textContent = 'מעלה...';

        try {
            const res = await fetch(API.posts, { method: 'POST', body: formData });
            const data = await res.json();

            if (data.error) {
                errorEl.textContent = data.error;
                errorEl.style.display = 'block';
                return;
            }

            showToast('התוכן הועלה בהצלחה!', 'success');
            closeModal('uploadModal');
            resetUploadForm();
            recentPage = 0;
            loadPosts('recent');
        } catch(e) {
            errorEl.textContent = 'שגיאה בהעלאה';
            errorEl.style.display = 'block';
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = '\uD83D\uDCE4 העלה';
        }
    };

    function resetUploadForm() {
        const fileInput = document.getElementById('uploadFile');
        if (fileInput) fileInput.value = '';
        const titleInput = document.getElementById('uploadTitle');
        if (titleInput) titleInput.value = '';
        const bodyInput = document.getElementById('uploadBody');
        if (bodyInput) bodyInput.value = '';
        const preview = document.getElementById('uploadPreview');
        if (preview) preview.style.display = 'none';
        const dropZone = document.getElementById('fileDropZone');
        if (dropZone) dropZone.style.display = '';
        document.querySelectorAll('#uploadTagsGrid .tag-checkbox').forEach(cb => cb.checked = false);
    }

    // ===== DRAG & DROP =====
    function setupDragDrop() {
        const zone = document.getElementById('fileDropZone');
        if (!zone) return;

        zone.addEventListener('dragover', function(e) {
            e.preventDefault();
            zone.classList.add('dragover');
        });
        zone.addEventListener('dragleave', function() {
            zone.classList.remove('dragover');
        });
        zone.addEventListener('drop', function(e) {
            e.preventDefault();
            zone.classList.remove('dragover');
            const fileInput = document.getElementById('uploadFile');
            if (e.dataTransfer.files.length > 0) {
                fileInput.files = e.dataTransfer.files;
                previewUploadFile(fileInput);
            }
        });
    }

    // ===== AUTH =====
    window.doLogin = async function() {
        const username = document.getElementById('loginUsername').value.trim();
        const password = document.getElementById('loginPassword').value;
        const errorEl = document.getElementById('loginError');
        errorEl.style.display = 'none';

        if (!username || !password) {
            errorEl.textContent = 'יש למלא את כל השדות';
            errorEl.style.display = 'block';
            return;
        }

        try {
            const formData = new FormData();
            formData.append('action', 'login');
            formData.append('username', username);
            formData.append('password', password);
            formData.append('csrf_token', CSRF);

            const res = await fetch(API.auth, { method: 'POST', body: formData });
            const data = await res.json();

            if (data.error) {
                errorEl.textContent = data.error;
                errorEl.style.display = 'block';
                return;
            }

            showToast('התחברת בהצלחה!', 'success');
            setTimeout(() => window.location.reload(), 500);
        } catch(e) {
            errorEl.textContent = 'שגיאה בהתחברות';
            errorEl.style.display = 'block';
        }
    };

    window.doLogout = async function() {
        try {
            const formData = new FormData();
            formData.append('action', 'logout');
            formData.append('csrf_token', CSRF);
            await fetch(API.auth, { method: 'POST', body: formData });
            showToast('התנתקת בהצלחה', 'success');
            setTimeout(() => window.location.reload(), 500);
        } catch(e) {
            window.location.reload();
        }
    };

    // ===== MODALS =====
    window.openModal = function(id) {
        document.getElementById(id).classList.add('active');
        document.body.style.overflow = 'hidden';
    };

    window.closeModal = function(id) {
        document.getElementById(id).classList.remove('active');
        document.body.style.overflow = '';
    };

    // Close modals on overlay click
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-overlay')) {
            e.target.classList.remove('active');
            document.body.style.overflow = '';
        }
    });

    // ESC key to close
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
            document.getElementById('lightbox').classList.remove('active');
            document.body.style.overflow = '';
        }
    });

    // ===== DROPDOWN =====
    window.toggleDropdown = function(btn) {
        const menu = btn.nextElementSibling;
        const wasOpen = menu.classList.contains('show');
        // Close all
        document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show'));
        if (!wasOpen) menu.classList.add('show');
    };

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show'));
        }
    });

    // ===== TOAST =====
    window.showToast = function(message, type) {
        type = type || 'success';
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = 'toast ' + type;
        toast.textContent = message;
        container.appendChild(toast);
        setTimeout(function() {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(-20px)';
            toast.style.transition = '0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    };

    // ===== UTILITIES =====
    function escHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function escAttr(str) {
        if (!str) return '';
        return str.replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function formatNum(n) {
        n = parseInt(n) || 0;
        if (n >= 1000000) return (n / 1000000).toFixed(1) + 'M';
        if (n >= 1000) return (n / 1000).toFixed(1) + 'K';
        return n.toString();
    }

    function formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }

})();
</script>

</body>
</html>
