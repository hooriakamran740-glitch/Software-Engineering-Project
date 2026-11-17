<?php
session_start();

// ==========================================
// 1. DATABASE CONNECTION
// ==========================================
$host = "localhost";
$dbname = "buitems_complaints";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ==========================================
// 2. REAL AUTHENTICATION CHECK
// ==========================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['user_id'];

$stmtUser = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ? AND role = 'student'");
$stmtUser->execute([$student_id]);
$currentUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$currentUser) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$student_name = $currentUser['full_name'];

$name_parts = explode(' ', trim($student_name));
$initials = strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));

// ==========================================
// 3. UPLOADS DIRECTORY & MESSAGES
// ==========================================
$upload_dir = __DIR__ . '/uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$success_msg = "";
$error_msg = "";

// ==========================================
// 4. HANDLE LIKE / UNLIKE & FEEDBACK SUBMISSION
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['feedback_action'])) {
    $complaint_id = (int)($_POST['complaint_id'] ?? 0);
    $action = $_POST['feedback_action'] ?? '';
    $comments = trim($_POST['feedback_comments'] ?? '');

    if ($complaint_id > 0 && in_array($action, ['Like', 'Unlike'])) {
        try {
            $new_status = ($action === 'Like') ? 'Closed' : 'Reopened';

            // Save into feedback table
            $fbStmt = $pdo->prepare("INSERT INTO feedback (complaint_id, student_id, action, comments) VALUES (?, ?, ?, ?)");
            $fbStmt->execute([$complaint_id, $student_id, $action, $comments]);

            // Update complaint status
            $updStmt = $pdo->prepare("UPDATE complaints SET status = ? WHERE id = ? AND student_id = ?");
            $updStmt->execute([$new_status, $complaint_id, $student_id]);

            if ($action === 'Like') {
                $success_msg = "You liked the resolution! Ticket has been marked as <strong>Closed</strong>.";
            } else {
                $success_msg = "Ticket marked as <strong>Reopened</strong> and sent back to staff.";
            }
        } catch (PDOException $e) {
            $error_msg = "Failed to save feedback: " . $e->getMessage();
        }
    }
}

// ==========================================
// 5. CHECK DAILY COMPLAINT LIMIT (MAX 2/DAY)
// ==========================================
$stmtToday = $pdo->prepare("SELECT COUNT(*) FROM complaints WHERE student_id = ? AND DATE(created_at) = CURDATE()");
$stmtToday->execute([$student_id]);
$today_count = (int)$stmtToday->fetchColumn();

// ==========================================
// 6. HANDLE COMPLAINT FORM SUBMISSION
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_complaint'])) {
    $campus = trim($_POST['campus'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $incident_time = trim($_POST['incident_time'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($today_count >= 2) {
        $error_msg = "Daily limit reached: You can only submit up to 2 complaints per day.";
    } elseif (empty($campus) || empty($title) || empty($department) || empty($location) || empty($incident_time) || empty($description)) {
        $error_msg = "Please fill in all required complaint fields.";
    } else {
        $uploaded_photos = [null, null, null];
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
        $max_size = 2 * 1024 * 1024;
        $valid_photo_count = 0;

        for ($i = 1; $i <= 3; $i++) {
            $input_name = "photo" . $i;
            if (isset($_FILES[$input_name]) && $_FILES[$input_name]['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES[$input_name]['tmp_name'];
                $file_size = $_FILES[$input_name]['size'];
                $file_type = mime_content_type($file_tmp);

                if (!in_array($file_type, $allowed_types)) {
                    $error_msg = "Photo $i must be a JPG, PNG, or WebP image.";
                    break;
                }
                if ($file_size > $max_size) {
                    $error_msg = "Photo $i exceeds the 2 MB size limit.";
                    break;
                }

                $ext = pathinfo($_FILES[$input_name]['name'], PATHINFO_EXTENSION);
                $new_filename = 'CMP_' . time() . '_' . $student_id . '_' . $i . '.' . $ext;
                if (move_uploaded_file($file_tmp, $upload_dir . $new_filename)) {
                    $uploaded_photos[$i - 1] = 'uploads/' . $new_filename;
                    $valid_photo_count++;
                }
            }
        }

        if (empty($error_msg) && $valid_photo_count < 1) {
            $error_msg = "Please attach at least 1 photo as evidence.";
        }

        if (empty($error_msg)) {
            $year = date('Y');
            $stmtLast = $pdo->query("SELECT MAX(id) FROM complaints");
            $next_num = 1001 + (int)$stmtLast->fetchColumn();
            $ticket_code = "CMP-" . $year . "-" . $next_num;

            $priority = ($department === 'IT' || $department === 'Information Technology (IT)') ? 'High' : 'Medium';

            $insertStmt = $pdo->prepare("
                INSERT INTO complaints 
                (ticket_code, student_id, campus, title, department, location, incident_time, description, photo1, photo2, photo3, priority, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'In Progress')
            ");

            $insertStmt->execute([
                $ticket_code,
                $student_id,
                $campus,
                $title,
                $department,
                $location,
                $incident_time,
                $description,
                $uploaded_photos[0],
                $uploaded_photos[1],
                $uploaded_photos[2],
                $priority
            ]);

            $success_msg = "Complaint submitted successfully! Your ticket ID is <strong>$ticket_code</strong>.";
            $today_count++;
        }
    }
}

// ==========================================
// 7. FETCH METRIC COUNTS
// ==========================================
$stmtStats = $pdo->prepare("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status IN ('In Progress', 'Assigned', 'Reopened') THEN 1 ELSE 0 END) AS in_progress,
        SUM(CASE WHEN status IN ('Resolved', 'Closed') THEN 1 ELSE 0 END) AS resolved,
        SUM(CASE WHEN status = 'Escalated' THEN 1 ELSE 0 END) AS escalated
    FROM complaints
    WHERE student_id = ?
");
$stmtStats->execute([$student_id]);
$stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

$total_complaints = str_pad((int)($stats['total'] ?? 0), 2, '0', STR_PAD_LEFT);
$in_progress_count = str_pad((int)($stats['in_progress'] ?? 0), 2, '0', STR_PAD_LEFT);
$resolved_count = str_pad((int)($stats['resolved'] ?? 0), 2, '0', STR_PAD_LEFT);
$escalated_count = str_pad((int)($stats['escalated'] ?? 0), 2, '0', STR_PAD_LEFT);

// ==========================================
// 8. FETCH COMPLAINT HISTORY
// ==========================================
$stmtList = $pdo->prepare("SELECT * FROM complaints WHERE student_id = ? ORDER BY created_at DESC");
$stmtList->execute([$student_id]);
$complaints = $stmtList->fetchAll(PDO::FETCH_ASSOC);

$current_date_banner = strtoupper(date('l, d F Y'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BUITEMS | Campus Care - Student Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-bg: #08285b;
            --sidebar-active: #1a3b70;
            --page-bg: #f4f7fb;
            --card-bg: #ffffff;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --primary-blue: #1d63dc;
            --primary-blue-hover: #154eb3;
            --amber-accent: #f59e0b;
            --green-accent: #10b981;
            --indigo-accent: #6366f1;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: var(--page-bg);
            color: var(--text-dark);
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 260px;
            background-color: var(--sidebar-bg);
            color: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 24px 16px;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
        }

        .brand-box {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 8px 28px 8px;
        }

        .brand-logo {
            width: 48px;
            height: 48px;
            background: #ffffff;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 4px;
            flex-shrink: 0;
        }

        .brand-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .brand-text h2 {
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .brand-text p {
            font-size: 10px;
            color: #cbd5e1;
            line-height: 1.3;
            margin-top: 2px;
        }

        .nav-label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 1px;
            color: #94a3b8;
            padding: 0 12px;
            margin-bottom: 12px;
        }

        .nav-menu {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .nav-item a {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            color: #cbd5e1;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            border-radius: 10px;
            transition: all 0.2s;
            position: relative;
        }

        .nav-item a:hover {
            background-color: rgba(255, 255, 255, 0.08);
            color: #ffffff;
        }

        .nav-item.active a {
            background-color: var(--sidebar-active);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        .nav-item.active a::before {
            content: '';
            position: absolute;
            left: -16px;
            top: 8px;
            bottom: 8px;
            width: 4px;
            background-color: #facc15;
            border-radius: 0 4px 4px 0;
        }

        .sidebar-footer {
            padding: 16px 12px 0 12px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 11px;
            color: #94a3b8;
            line-height: 1.6;
        }

        .sidebar-footer strong {
            color: #e2e8f0;
            display: block;
            margin-top: 10px;
        }

        .main-wrapper {
            margin-left: 260px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .topbar {
            background: #ffffff;
            height: 68px;
            padding: 0 32px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 90;
        }

        .breadcrumb {
            font-size: 14px;
            color: var(--text-muted);
        }

        .breadcrumb span {
            color: var(--text-dark);
            font-weight: 600;
        }

        .user-controls {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .role-badge {
            background: #fef3c7;
            color: #b45309;
            font-size: 11px;
            font-weight: 700;
            padding: 5px 10px;
            border-radius: 6px;
            letter-spacing: 0.4px;
        }

        .avatar-circle {
            width: 34px;
            height: 34px;
            background: #e2e8f0;
            color: #1e293b;
            font-weight: 700;
            font-size: 13px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-dark);
        }

        .btn-logout {
            padding: 7px 14px;
            border: 1px solid var(--border-color);
            background: #ffffff;
            color: var(--text-dark);
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            transition: 0.2s;
        }

        .btn-logout:hover {
            background: #f8fafc;
        }

        .content-container {
            padding: 32px;
            max-width: 1320px;
            width: 100%;
            margin: 0 auto;
        }

        .date-label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 1px;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .hero-title {
            font-size: 30px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 6px;
        }

        .hero-subtitle {
            font-size: 15px;
            color: var(--text-muted);
            margin-bottom: 24px;
        }

        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 20px 24px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
        }

        .stat-card.total::before { background: transparent; }
        .stat-card.progress::before { background: var(--amber-accent); }
        .stat-card.resolved::before { background: var(--green-accent); }
        .stat-card.escalated::before { background: var(--indigo-accent); }

        .stat-title {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 12px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .workspace-grid {
            display: grid;
            grid-template-columns: 440px 1fr;
            gap: 24px;
            align-items: start;
        }

        .panel-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 18px;
            padding-bottom: 16px;
            border-bottom: 1px solid #f1f5f9;
        }

        .panel-header h3 {
            font-size: 19px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .panel-header p {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .limit-banner {
            background: #eff6ff;
            border: 1px solid #dbeafe;
            color: #1e40af;
            font-size: 13px;
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 6px;
        }

        .form-helper {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 6px;
            line-height: 1.4;
        }

        .form-control {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 14px;
            color: var(--text-dark);
            background: #ffffff;
            outline: none;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            border-color: var(--primary-blue);
        }

        .form-row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        textarea.form-control {
            min-height: 95px;
            resize: vertical;
        }

        .photo-section-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 4px;
        }

        .photo-section-title span {
            font-weight: 400;
            color: var(--text-muted);
        }

        .photo-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin: 12px 0 8px 0;
        }

        .photo-slot {
            border: 1px dashed #cbd5e1;
            border-radius: 10px;
            padding: 10px 8px;
            text-align: center;
            background: #f8fafc;
        }

        .photo-slot-label {
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 8px;
            text-align: left;
        }

        .photo-preview-box {
            width: 100%;
            height: 76px;
            background: #e2e8f0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            color: #64748b;
            margin-bottom: 8px;
            overflow: hidden;
            padding: 4px;
        }

        .photo-preview-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-file-input {
            width: 100%;
            font-size: 11px;
            margin-bottom: 6px;
        }

        .btn-remove-photo {
            width: 100%;
            padding: 5px;
            font-size: 11px;
            background: #e2e8f0;
            border: none;
            border-radius: 6px;
            color: #475569;
            cursor: pointer;
        }

        .btn-remove-photo:hover {
            background: #cbd5e1;
        }

        .photo-counter {
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 18px;
        }

        .btn-submit {
            width: 100%;
            padding: 13px;
            background: var(--primary-blue);
            color: #ffffff;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            margin-bottom: 10px;
        }

        .btn-submit:hover {
            background: var(--primary-blue-hover);
        }

        .btn-clear {
            width: 100%;
            padding: 11px;
            background: #ffffff;
            color: var(--text-dark);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
        }

        .btn-clear:hover {
            background: #f8fafc;
        }

        .btn-toggle-history {
            padding: 7px 12px;
            border: 1px solid var(--border-color);
            background: #ffffff;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-dark);
            cursor: pointer;
        }

        .filter-bar {
            display: grid;
            grid-template-columns: 1fr 180px;
            gap: 12px;
            margin-bottom: 16px;
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
        }

        .history-table th {
            background: #f8fafc;
            text-align: left;
            padding: 12px 14px;
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border-color);
        }

        .history-table td {
            padding: 16px 14px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .ticket-code {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 3px;
        }

        .ticket-title {
            font-size: 14px;
            color: #1e293b;
            margin-bottom: 3px;
        }

        .ticket-meta {
            font-size: 12px;
            color: var(--text-muted);
            line-height: 1.4;
        }

        .staff-note-text {
            font-size: 12px;
            color: #08285b;
            font-weight: 500;
            margin-top: 4px;
        }

        .cat-name {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-dark);
        }

        .cat-priority {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .status-pill {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-progress { background: #fef3c7; color: #b45309; }
        .status-resolved { background: #dcfce7; color: #15803d; }
        .status-closed { background: #e2e8f0; color: #334155; }
        .status-escalated { background: #e0e7ff; color: #4338ca; }

        /* Like / Unlike & Feedback Action Buttons */
        .action-stack {
            display: flex;
            flex-direction: column;
            gap: 6px;
            width: 115px;
            margin-left: auto;
        }

        .btn-like-pill {
            width: 100%;
            padding: 7px 10px;
            background: #0b3b8c;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: 0.2s;
        }
        .btn-like-pill:hover { background: #08285b; }

        .btn-unlike-pill {
            width: 100%;
            padding: 7px 10px;
            background: #facc15;
            color: #0f172a;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: 0.2s;
        }
        .btn-unlike-pill:hover { background: #eab308; }

        .btn-feedback-pill {
            width: 100%;
            padding: 6px 10px;
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-dark);
            cursor: pointer;
            text-align: center;
        }
        .btn-feedback-pill:hover { background: #e2e8f0; }

        .table-footer {
            margin-top: 14px;
            font-size: 12px;
            color: var(--text-muted);
        }

        .modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            z-index: 999;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-box {
            background: #ffffff;
            border-radius: 16px;
            max-width: 560px;
            width: 100%;
            padding: 24px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15);
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 14px;
            margin-bottom: 16px;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 22px;
            cursor: pointer;
            color: var(--text-muted);
        }

        .modal-detail-row {
            margin-bottom: 12px;
            font-size: 14px;
        }

        .modal-detail-row strong {
            display: block;
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 2px;
        }

        .modal-photos {
            display: flex;
            gap: 10px;
            margin-top: 12px;
            flex-wrap: wrap;
        }

        .modal-photos img {
            width: 140px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .feedback-modal-box {
            margin-top: 16px;
            padding: 14px;
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 12px;
        }

        .feedback-btn-row {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        @media (max-width: 1080px) {
            .workspace-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-wrapper { margin-left: 0; }
        }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div>
            <div class="brand-box">
                <div class="brand-logo">
                    <img src="images/buitems-logo.png" alt="BUITEMS Logo" onerror="this.style.display='none'">
                </div>
                <div class="brand-text">
                    <h2>BUITEMS</h2>
                    <p>CAMPUS CARE PORTAL<br>Quetta · Balochistan</p>
                </div>
            </div>

            <div class="nav-label">STUDENT WORKSPACE</div>
            <ul class="nav-menu">
                <li class="nav-item active"><a href="#overview">Overview</a></li>
                <li class="nav-item"><a href="#complaints-section">My complaints</a></li>
                <li class="nav-item"><a href="#complaints-section">Activity &amp; updates</a></li>
            </ul>
        </div>

        <div class="sidebar-footer">
            Made for a better campus.
            <strong>Campus support</strong>
            Report · Track · Resolve
        </div>
    </aside>

    <div class="main-wrapper">
        <header class="topbar">
            <div class="breadcrumb">
                Campus Care / <span>Overview</span>
            </div>

            <div class="user-controls">
                <span class="role-badge">STUDENT PORTAL</span>
                <div class="avatar-circle"><?php echo htmlspecialchars($initials); ?></div>
                <span class="user-name"><?php echo htmlspecialchars($student_name); ?></span>
                <a href="logout.php" class="btn-logout">Logout</a>
            </div>
        </header>

        <main class="content-container" id="overview">
            <div class="date-label"><?php echo $current_date_banner; ?></div>
            <h1 class="hero-title">Let’s make campus better.</h1>
            <p class="hero-subtitle">Report an issue and stay in the loop, every step of the way.</p>

            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success"><?php echo $success_msg; ?></div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error_msg); ?></div>
            <?php endif; ?>

            <section class="stats-grid">
                <div class="stat-card total">
                    <div class="stat-title">Total complaints</div>
                    <div class="stat-value"><?php echo $total_complaints; ?></div>
                </div>
                <div class="stat-card progress">
                    <div class="stat-title">In progress</div>
                    <div class="stat-value"><?php echo $in_progress_count; ?></div>
                </div>
                <div class="stat-card resolved">
                    <div class="stat-title">Resolved / closed</div>
                    <div class="stat-value"><?php echo $resolved_count; ?></div>
                </div>
                <div class="stat-card escalated">
                    <div class="stat-title">Escalated</div>
                    <div class="stat-value"><?php echo $escalated_count; ?></div>
                </div>
            </section>

            <section class="workspace-grid" id="complaints-section">
                <div class="panel-card">
                    <div class="panel-header">
                        <div>
                            <h3>Submit a complaint</h3>
                            <p>A few details help us get it to the right person.</p>
                        </div>
                    </div>

                    <div class="limit-banner">
                        <?php echo $today_count; ?> of 2 complaints submitted today. Each ticket goes to one expert.
                    </div>

                    <form method="POST" action="" enctype="multipart/form-data" id="complaintForm">
                        <div class="form-group">
                            <label for="campus">BUITEMS campus / location</label>
                            <select name="campus" id="campus" class="form-control" required>
                                <option value="">Select your campus</option>
                                <option value="Takatu Campus (Airport Road)">Takatu Campus (Airport Road)</option>
                                <option value="City Campus (Jinnah Town)">City Campus (Jinnah Town)</option>
                                <option value="Chiltan Campus">Chiltan Campus</option>
                            </select>
                            <div class="form-helper">Choose where the issue occurred; then select the relevant department or service.</div>
                        </div>

                        <div class="form-group">
                            <label for="title">Complaint title</label>
                            <input type="text" name="title" id="title" class="form-control" placeholder="e.g. Broken chair in lecture room" required>
                        </div>

                        <div class="form-row-2">
                            <div class="form-group">
                                <label for="department">Department / category</label>
                                <select name="department" id="department" class="form-control" required>
                                    <option value="Computer Engineering">Computer Engineering</option>
                                    <option value="Software Engineering">Software Engineering</option>
                                    <option value="Information Technology (IT)">IT</option>
                                    <option value="Facilities & Maintenance">Facilities</option>
                                    <option value="Electrical & Power">Electrical</option>
                                    <option value="Hostel & Transport">Hostel &amp; Transport</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="location">Location</label>
                                <input type="text" name="location" id="location" class="form-control" placeholder="Block, room or hostel" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="incident_time">When did it happen?</label>
                            <input type="datetime-local" name="incident_time" id="incident_time" class="form-control" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="description">What happened?</label>
                            <textarea name="description" id="description" class="form-control" placeholder="Describe the issue and how it affects you..." required></textarea>
                        </div>

                        <div class="photo-section-title">Photo evidence <span>· attach 1–3 photos</span></div>
                        <div class="form-helper">Add one photo in each slot. JPG, PNG or WebP · up to 2 MB each.</div>

                        <div class="photo-grid">
                            <?php for ($p = 1; $p <= 3; $p++): ?>
                            <div class="photo-slot">
                                <div class="photo-slot-label">Photo <?php echo $p; ?></div>
                                <div class="photo-preview-box" id="previewBox<?php echo $p; ?>">No photo selected</div>
                                <input type="file" name="photo<?php echo $p; ?>" id="photo<?php echo $p; ?>" class="photo-file-input" accept="image/jpeg,image/png,image/webp" onchange="previewPhoto(<?php echo $p; ?>)">
                                <button type="button" class="btn-remove-photo" onclick="removePhoto(<?php echo $p; ?>)">Remove photo <?php echo $p; ?></button>
                            </div>
                            <?php endfor; ?>
                        </div>

                        <div class="photo-counter" id="photoCounterText">0 of 3 photos added · at least 1 required</div>

                        <button type="submit" name="submit_complaint" class="btn-submit">Submit complaint &rarr;</button>
                        <button type="button" class="btn-clear" onclick="resetComplaintForm()">Clear form</button>
                    </form>
                </div>

                <div class="panel-card">
                    <div class="panel-header">
                        <div>
                            <h3>My complaint history</h3>
                            <p>Rate resolved tickets with Like / Unlike or leave feedback.</p>
                        </div>
                        <button type="button" class="btn-toggle-history" id="toggleHistoryBtn" onclick="toggleHistory()">
                            Hide complaint history &#9652; (<?php echo count($complaints); ?>)
                        </button>
                    </div>

                    <div id="historyBody">
                        <div class="filter-bar">
                            <input type="text" id="searchInput" class="form-control" placeholder="Search ticket or location..." onkeyup="filterTickets()">
                            <select id="statusFilter" class="form-control" onchange="filterTickets()">
                                <option value="all">All statuses</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Assigned">Assigned</option>
                                <option value="Resolved">Resolved</option>
                                <option value="Closed">Closed</option>
                                <option value="Reopened">Reopened</option>
                                <option value="Escalated">Escalated</option>
                            </select>
                        </div>

                        <table class="history-table" id="ticketsTable">
                            <thead>
                                <tr>
                                    <th>TICKET / ISSUE</th>
                                    <th>CATEGORY</th>
                                    <th>STATUS</th>
                                    <th style="text-align: right;">ACTION / FEEDBACK</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($complaints) > 0): ?>
                                    <?php foreach ($complaints as $row): ?>
                                        <?php
                                            $badge_class = 'status-progress';
                                            if ($row['status'] === 'Resolved') $badge_class = 'status-resolved';
                                            elseif ($row['status'] === 'Closed') $badge_class = 'status-closed';
                                            elseif ($row['status'] === 'Escalated') $badge_class = 'status-escalated';
                                        ?>
                                        <tr class="ticket-row" data-status="<?php echo htmlspecialchars($row['status']); ?>">
                                            <td>
                                                <div class="ticket-code"><?php echo htmlspecialchars($row['ticket_code']); ?></div>
                                                <div class="ticket-title"><?php echo htmlspecialchars($row['title']); ?></div>
                                                <div class="ticket-meta">
                                                    <?php echo htmlspecialchars($row['campus']); ?><br>
                                                    <?php echo htmlspecialchars($row['location']); ?>
                                                </div>
                                                <?php if (!empty($row['admin_notes'])): ?>
                                                    <div class="staff-note-text">
                                                        Staff: <?php echo htmlspecialchars($row['admin_notes']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="cat-name"><?php echo htmlspecialchars($row['department']); ?></div>
                                                <div class="cat-priority"><?php echo htmlspecialchars($row['priority']); ?> priority</div>
                                            </td>
                                            <td>
                                                <span class="status-pill <?php echo $badge_class; ?>">
                                                    <?php echo htmlspecialchars($row['status']); ?>
                                                </span>
                                            </td>
                                            <td style="text-align: right;">
                                                <div class="action-stack">
                                                    <?php if ($row['status'] === 'Resolved'): ?>
                                                        <form method="POST" action="" style="display:flex; flex-direction:column; gap:5px;">
                                                            <input type="hidden" name="complaint_id" value="<?php echo $row['id']; ?>">
                                                            <button type="submit" name="feedback_action" value="Like" class="btn-like-pill">&#128077; Like</button>
                                                            <button type="submit" name="feedback_action" value="Unlike" class="btn-unlike-pill">&#128078; Unlike</button>
                                                        </form>
                                                    <?php endif; ?>

                                                    <button type="button" class="btn-feedback-pill" onclick='openTicketModal(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8"); ?>)'>
                                                        &#128172; Feedback
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" style="text-align:center; color:#64748b; padding:28px;">
                                            No complaints submitted yet. Use the form on the left to submit your first ticket.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>

                        <div class="table-footer">
                            <?php echo count($complaints); ?> tickets · Updated <?php echo date('h:i A'); ?>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- TICKET DETAILS & FEEDBACK MODAL -->
    <div class="modal-backdrop" id="ticketModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3 id="modalTicketCode">Ticket Details</h3>
                <button type="button" class="modal-close" onclick="closeTicketModal()">&times;</button>
            </div>
            <div class="modal-detail-row">
                <strong>Title</strong>
                <span id="modalTitle"></span>
            </div>
            <div class="modal-detail-row">
                <strong>Campus &amp; Location</strong>
                <span id="modalLocation"></span>
            </div>
            <div class="modal-detail-row">
                <strong>Department &amp; Priority</strong>
                <span id="modalDept"></span>
            </div>
            <div class="modal-detail-row">
                <strong>Status</strong>
                <span id="modalStatus"></span>
            </div>
            <div class="modal-detail-row">
                <strong>Incident Date &amp; Time</strong>
                <span id="modalDate"></span>
            </div>
            <div class="modal-detail-row">
                <strong>Description</strong>
                <p id="modalDesc" style="margin-top:4px; line-height:1.5; color:#334155;"></p>
            </div>
            <div class="modal-detail-row" id="modalStaffNoteRow" style="display:none;">
                <strong>Staff / Admin Update</strong>
                <p id="modalStaffNote" style="margin-top:4px; line-height:1.5; color:#08285b; background:#eff6ff; padding:8px 12px; border-radius:8px;"></p>
            </div>
            <div class="modal-detail-row">
                <strong>Attached Evidence</strong>
                <div class="modal-photos" id="modalPhotos"></div>
            </div>

            <!-- FEEDBACK FORM WITH COMMENTS -->
            <div class="feedback-modal-box">
                <strong style="font-size:13px; color:var(--text-dark); display:block; margin-bottom:4px;">Submit Feedback &amp; Comments</strong>
                <p style="font-size:12px; color:var(--text-muted); margin-bottom:10px;">Select Like to close the ticket, or Unlike to reopen it with your comments.</p>
                <form method="POST" action="">
                    <input type="hidden" name="complaint_id" id="modalFeedbackComplaintId">
                    <textarea name="feedback_comments" class="form-control" placeholder="Write your feedback or comments here..." style="min-height: 70px; font-size: 13px;" required></textarea>
                    <div class="feedback-btn-row">
                        <button type="submit" name="feedback_action" value="Like" class="btn-like-pill" style="padding:10px;">&#128077; Like &amp; Close</button>
                        <button type="submit" name="feedback_action" value="Unlike" class="btn-unlike-pill" style="padding:10px;">&#128078; Unlike &amp; Reopen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function previewPhoto(slot) {
            const input = document.getElementById('photo' + slot);
            const box = document.getElementById('previewBox' + slot);
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    box.innerHTML = '<img src="' + e.target.result + '" alt="Photo ' + slot + '">';
                };
                reader.readAsDataURL(input.files[0]);
            } else {
                box.textContent = 'No photo selected';
            }
            updatePhotoCount();
        }

        function removePhoto(slot) {
            const input = document.getElementById('photo' + slot);
            const box = document.getElementById('previewBox' + slot);
            input.value = '';
            box.textContent = 'No photo selected';
            updatePhotoCount();
        }

        function updatePhotoCount() {
            let count = 0;
            for (let i = 1; i <= 3; i++) {
                if (document.getElementById('photo' + i).files.length > 0) {
                    count++;
                }
            }
            document.getElementById('photoCounterText').textContent = count + ' of 3 photos added · at least 1 required';
        }

        function resetComplaintForm() {
            document.getElementById('complaintForm').reset();
            for (let i = 1; i <= 3; i++) {
                removePhoto(i);
            }
        }

        function toggleHistory() {
            const body = document.getElementById('historyBody');
            const btn = document.getElementById('toggleHistoryBtn');
            const total = <?php echo count($complaints); ?>;
            if (body.style.display === 'none') {
                body.style.display = 'block';
                btn.innerHTML = 'Hide complaint history &#9652; (' + total + ')';
            } else {
                body.style.display = 'none';
                btn.innerHTML = 'Show complaint history &#9662; (' + total + ')';
            }
        }

        function filterTickets() {
            const query = document.getElementById('searchInput').value.toLowerCase();
            const status = document.getElementById('statusFilter').value;
            const rows = document.querySelectorAll('.ticket-row');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const rowStatus = row.getAttribute('data-status');
                const matchesSearch = text.includes(query);
                const matchesStatus = (status === 'all' || rowStatus === status);
                row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
            });
        }

        function openTicketModal(ticket) {
            document.getElementById('modalTicketCode').textContent = ticket.ticket_code;
            document.getElementById('modalFeedbackComplaintId').value = ticket.id;
            document.getElementById('modalTitle').textContent = ticket.title;
            document.getElementById('modalLocation').textContent = ticket.campus + ' — ' + ticket.location;
            document.getElementById('modalDept').textContent = ticket.department + ' (' + ticket.priority + ' priority)';
            document.getElementById('modalStatus').textContent = ticket.status;
            document.getElementById('modalDate').textContent = ticket.incident_time;
            document.getElementById('modalDesc').textContent = ticket.description;

            const noteRow = document.getElementById('modalStaffNoteRow');
            if (ticket.admin_notes) {
                document.getElementById('modalStaffNote').textContent = ticket.admin_notes;
                noteRow.style.display = 'block';
            } else {
                noteRow.style.display = 'none';
            }

            const photosDiv = document.getElementById('modalPhotos');
            photosDiv.innerHTML = '';
            [ticket.photo1, ticket.photo2, ticket.photo3].forEach(path => {
                if (path) {
                    const img = document.createElement('img');
                    img.src = path;
                    photosDiv.appendChild(img);
                }
            });

            document.getElementById('ticketModal').style.display = 'flex';
        }

        function closeTicketModal() {
            document.getElementById('ticketModal').style.display = 'none';
        }
    </script>
</body>
</html>