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
// 2. ADMIN AUTHENTICATION CHECK
// ==========================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['full_name'] ?? 'Administrator';

$name_parts = explode(' ', trim($admin_name));
$initials = strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));

// Auto-escalate unresolved tickets older than 48 hours
$pdo->query("UPDATE complaints SET status = 'Escalated' WHERE status IN ('In Progress', 'Assigned', 'Reopened') AND created_at <= NOW() - INTERVAL 48 HOUR");

$success_msg = "";
$error_msg = "";

// ==========================================
// 3. HANDLE QUICK ASSIGN & MODAL UPDATES
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Quick Assign & Priority from Table Row
    if (isset($_POST['quick_assign'])) {
        $complaint_id = (int)($_POST['complaint_id'] ?? 0);
        $ticket_code = trim($_POST['ticket_code'] ?? '');
        $assigned_staff_id = !empty($_POST['assigned_staff_id']) ? (int)$_POST['assigned_staff_id'] : null;
        $priority = $_POST['priority'] ?? 'Medium';

        if ($complaint_id > 0) {
            try {
                if ($assigned_staff_id) {
                    $stmt = $pdo->prepare("UPDATE complaints SET assigned_staff_id = ?, priority = ?, status = CASE WHEN status = 'In Progress' THEN 'Assigned' ELSE status END WHERE id = ?");
                    $stmt->execute([$assigned_staff_id, $priority, $complaint_id]);
                    $success_msg = "Ticket <strong>" . htmlspecialchars($ticket_code) . "</strong> assigned to staff with <strong>" . htmlspecialchars($priority) . "</strong> priority.";
                } else {
                    $stmt = $pdo->prepare("UPDATE complaints SET assigned_staff_id = NULL, priority = ? WHERE id = ?");
                    $stmt->execute([$priority, $complaint_id]);
                    $success_msg = "Ticket <strong>" . htmlspecialchars($ticket_code) . "</strong> priority updated to <strong>" . htmlspecialchars($priority) . "</strong>.";
                }
            } catch (PDOException $e) {
                $error_msg = "Failed to assign ticket: " . $e->getMessage();
            }
        }
    }

    // Modal Full Update (Status, Staff, Priority & Student Update Note)
    if (isset($_POST['update_ticket'])) {
        $complaint_id = (int)($_POST['complaint_id'] ?? 0);
        $new_status = $_POST['status'] ?? '';
        $admin_notes = trim($_POST['admin_notes'] ?? '');
        $assigned_staff_id = !empty($_POST['assigned_staff_id']) ? (int)$_POST['assigned_staff_id'] : null;
        $priority = $_POST['priority'] ?? 'Medium';

        if ($complaint_id > 0 && !empty($new_status)) {
            try {
                $updateStmt = $pdo->prepare("UPDATE complaints SET status = ?, admin_notes = ?, assigned_staff_id = ?, priority = ? WHERE id = ?");
                $updateStmt->execute([$new_status, $admin_notes, $assigned_staff_id, $priority, $complaint_id]);
                $success_msg = "Ticket successfully updated to <strong>" . htmlspecialchars($new_status) . "</strong>.";
            } catch (PDOException $e) {
                $error_msg = "Failed to update ticket: " . $e->getMessage();
            }
        } else {
            $error_msg = "Invalid status or ticket selection.";
        }
    }
}

// ==========================================
// 4. FETCH ADMIN METRICS COUNTS
// ==========================================
$statsStmt = $pdo->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status IN ('In Progress', 'Assigned', 'Reopened') THEN 1 ELSE 0 END) AS in_progress,
        SUM(CASE WHEN status IN ('Resolved', 'Closed') THEN 1 ELSE 0 END) AS resolved,
        SUM(CASE WHEN status = 'Escalated' THEN 1 ELSE 0 END) AS escalated
    FROM complaints
");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

$total_num = (int)($stats['total'] ?? 0);
$in_progress_num = (int)($stats['in_progress'] ?? 0);
$resolved_num = (int)($stats['resolved'] ?? 0);
$escalated_num = (int)($stats['escalated'] ?? 0);

$total_complaints = str_pad($total_num, 2, '0', STR_PAD_LEFT);
$in_progress_count = str_pad($in_progress_num, 2, '0', STR_PAD_LEFT);
$resolved_count = str_pad($resolved_num, 2, '0', STR_PAD_LEFT);
$escalated_count = str_pad($escalated_num, 2, '0', STR_PAD_LEFT);

// ==========================================
// 5. FETCH ALL COMPLAINTS WITH STUDENT & STAFF INFO
// ==========================================
$complaintsStmt = $pdo->query("
    SELECT c.*, u.full_name AS student_name, u.email AS student_email, s.full_name AS staff_name
    FROM complaints c 
    JOIN users u ON c.student_id = u.id 
    LEFT JOIN users s ON c.assigned_staff_id = s.id
    ORDER BY c.created_at DESC
");
$all_complaints = $complaintsStmt->fetchAll(PDO::FETCH_ASSOC);

// FETCH ALL STAFF MEMBERS FOR DROPDOWN
$staffStmt = $pdo->query("SELECT id, full_name FROM users WHERE role = 'staff' ORDER BY full_name ASC");
$staff_list = $staffStmt->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// 6. ANALYTICS BREAKDOWN (DEPARTMENT & CAMPUS)
// ==========================================
$deptStatsStmt = $pdo->query("
    SELECT department, COUNT(*) AS count,
           SUM(CASE WHEN status IN ('Resolved', 'Closed') THEN 1 ELSE 0 END) AS resolved_count
    FROM complaints
    GROUP BY department
    ORDER BY count DESC
");
$dept_stats = $deptStatsStmt->fetchAll(PDO::FETCH_ASSOC);

$campusStatsStmt = $pdo->query("
    SELECT campus, COUNT(*) AS count
    FROM complaints
    GROUP BY campus
    ORDER BY count DESC
");
$campus_stats = $campusStatsStmt->fetchAll(PDO::FETCH_ASSOC);

$resolution_rate = $total_num > 0 ? round(($resolved_num / $total_num) * 100) : 0;
$current_date_banner = strtoupper(date('l, d F Y'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BUITEMS | Campus Care - Admin Dashboard</title>
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

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background-color: var(--page-bg); color: var(--text-dark); display: flex; min-height: 100vh; }

        .sidebar {
            width: 260px;
            background-color: var(--sidebar-bg);
            color: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 24px 16px;
            position: fixed;
            top: 0; bottom: 0; left: 0;
            z-index: 100;
        }

        .brand-box { display: flex; align-items: center; gap: 12px; padding: 0 8px 28px 8px; }
        .brand-logo { width: 48px; height: 48px; background: #ffffff; border-radius: 10px; display: flex; align-items: center; justify-content: center; padding: 4px; flex-shrink: 0; }
        .brand-logo img { width: 100%; height: 100%; object-fit: contain; }
        .brand-text h2 { font-size: 16px; font-weight: 700; letter-spacing: 0.5px; }
        .brand-text p { font-size: 10px; color: #cbd5e1; line-height: 1.3; margin-top: 2px; }

        .nav-label { font-size: 11px; font-weight: 600; letter-spacing: 1px; color: #94a3b8; padding: 0 12px; margin-bottom: 12px; }
        .nav-menu { list-style: none; display: flex; flex-direction: column; gap: 6px; }
        .nav-item a { display: flex; align-items: center; padding: 12px 16px; color: #cbd5e1; text-decoration: none; font-size: 14px; font-weight: 500; border-radius: 10px; transition: all 0.2s; cursor: pointer; }
        .nav-item a:hover, .nav-item.active a { background-color: var(--sidebar-active); color: #ffffff; }

        .sidebar-footer { padding: 16px 12px 0 12px; border-top: 1px solid rgba(255, 255, 255, 0.1); font-size: 11px; color: #94a3b8; line-height: 1.6; }
        .sidebar-footer strong { color: #e2e8f0; display: block; margin-top: 10px; }

        .main-wrapper { margin-left: 260px; flex: 1; display: flex; flex-direction: column; min-width: 0; }
        .topbar { background: #ffffff; height: 68px; padding: 0 32px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 90; }
        .breadcrumb { font-size: 14px; color: var(--text-muted); }
        .breadcrumb span { color: var(--text-dark); font-weight: 600; }

        .user-controls { display: flex; align-items: center; gap: 14px; }
        .role-badge { background: #dbeafe; color: #1e40af; font-size: 11px; font-weight: 700; padding: 5px 10px; border-radius: 6px; }
        .avatar-circle { width: 34px; height: 34px; background: #08285b; color: #ffffff; font-weight: 700; font-size: 13px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .user-name { font-size: 14px; font-weight: 600; color: var(--text-dark); }
        .btn-logout { padding: 7px 14px; border: 1px solid var(--border-color); background: #ffffff; color: var(--text-dark); border-radius: 8px; font-size: 13px; font-weight: 500; text-decoration: none; }

        .content-container { padding: 32px; max-width: 1420px; width: 100%; margin: 0 auto; }
        .hero-title { font-size: 28px; font-weight: 700; color: var(--text-dark); margin-bottom: 6px; }
        .hero-subtitle { font-size: 14px; color: var(--text-muted); margin-bottom: 24px; }

        .alert { padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        /* Complaint Flow Process Card */
        .flow-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .flow-card h3 { font-size: 16px; font-weight: 700; color: #08285b; margin-bottom: 14px; }
        .flow-steps {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            overflow-x: auto;
            padding-bottom: 4px;
        }
        .flow-step {
            flex: 1;
            min-width: 155px;
            background: #1d63dc;
            color: #ffffff;
            border-radius: 12px;
            padding: 14px 12px;
            text-align: center;
            font-size: 12px;
            line-height: 1.4;
            box-shadow: 0 2px 6px rgba(29, 99, 220, 0.18);
        }
        .flow-step strong { display: block; font-size: 13px; margin-bottom: 3px; }
        .flow-step.step-final { background: #10b981; box-shadow: 0 2px 6px rgba(16, 185, 129, 0.2); }
        .flow-arrow { color: #94a3b8; font-weight: 700; font-size: 16px; flex-shrink: 0; }

        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 28px; }
        .stat-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 14px; padding: 20px 24px; position: relative; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
        .stat-title { font-size: 13px; color: var(--text-muted); margin-bottom: 12px; }
        .stat-value { font-size: 32px; font-weight: 700; color: var(--text-dark); }

        .panel-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); margin-bottom: 24px; }
        .panel-header { margin-bottom: 18px; padding-bottom: 14px; border-bottom: 1px solid #f1f5f9; }
        .panel-header h3 { font-size: 18px; font-weight: 700; color: var(--text-dark); }
        .panel-header p { font-size: 13px; color: var(--text-muted); margin-top: 4px; }

        .filter-bar { display: grid; grid-template-columns: 1fr 200px; gap: 12px; margin-bottom: 16px; }
        .form-control { width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; outline: none; }

        .history-table { width: 100%; border-collapse: collapse; min-width: 1060px; }
        .history-table th { background: #f8fafc; text-align: left; padding: 12px 12px; font-size: 12px; font-weight: 700; color: var(--text-dark); border-bottom: 1px solid var(--border-color); }
        .history-table td { padding: 16px 12px; border-bottom: 1px solid #f1f5f9; vertical-align: top; font-size: 13px; }

        .ticket-code { font-size: 13px; font-weight: 700; color: var(--text-dark); line-height: 1.4; }
        .ticket-title { font-size: 14px; color: #0f172a; font-weight: 700; margin-bottom: 3px; line-height: 1.35; }
        .ticket-desc { font-size: 13px; color: #475569; margin-bottom: 4px; line-height: 1.4; }
        .ticket-meta { font-size: 12px; color: var(--text-muted); line-height: 1.4; }

        .status-pill { display: inline-block; padding: 5px 11px; border-radius: 999px; font-size: 11px; font-weight: 700; white-space: nowrap; }
        .status-assigned { background: #fef3c7; color: #b45309; }
        .status-progress { background: #ffedd5; color: #c2410c; }
        .status-resolved { background: #dcfce7; color: #15803d; }
        .status-closed { background: #f1f5f9; color: #475569; }
        .status-escalated { background: #e0e7ff; color: #4338ca; }

        .inline-select { width: 135px; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 12px; font-weight: 500; background: #ffffff; color: var(--text-dark); outline: none; }
        .inline-select:focus { border-color: var(--primary-blue); }

        .action-stack { display: flex; flex-direction: column; gap: 7px; width: 165px; }
        .btn-assign-staff { width: 100%; padding: 8px 10px; background: #08285b; color: #ffffff; border: none; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; line-height: 1.3; }
        .btn-assign-staff:hover { background: #1a3b70; }

        .btn-send-student { width: 100%; padding: 7px 10px; background: #ffffff; color: #08285b; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; line-height: 1.3; }
        .btn-send-student:hover { background: #f8fafc; border-color: #94a3b8; }

        .btn-view { width: 100%; padding: 6px 10px; background: #f8fafc; border: 1px solid var(--border-color); border-radius: 7px; font-size: 12px; font-weight: 600; cursor: pointer; color: var(--text-dark); }
        .btn-view:hover { background: #e2e8f0; }

        /* Analytics Bar Styles */
        .analytics-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        .progress-row { margin-bottom: 16px; }
        .progress-label { display: flex; justify-content: space-between; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
        .progress-track { width: 100%; height: 10px; background: #e2e8f0; border-radius: 999px; overflow: hidden; }
        .progress-fill { height: 100%; background: var(--primary-blue); border-radius: 999px; }

        .app-section { display: none; }
        .app-section.active-section { display: block; }

        /* Modal Styles */
        .modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55); z-index: 999; align-items: center; justify-content: center; padding: 20px; }
        .modal-box { background: #ffffff; border-radius: 16px; max-width: 600px; width: 100%; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.15); max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 14px; margin-bottom: 16px; }
        .modal-close { background: none; border: none; font-size: 22px; cursor: pointer; color: var(--text-muted); }
        .modal-row { margin-bottom: 14px; font-size: 13px; }
        .modal-row strong { display: block; font-size: 11px; color: var(--text-muted); margin-bottom: 3px; }
        .modal-photos { display: flex; gap: 10px; margin-top: 8px; flex-wrap: wrap; }
        .modal-photos img { width: 120px; height: 90px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border-color); }
        .btn-submit { padding: 10px 16px; background: var(--primary-blue); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 13px; }
        .btn-submit:hover { background: var(--primary-blue-hover); }
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

            <div class="nav-label">ADMIN WORKSPACE</div>
            <ul class="nav-menu">
                <li class="nav-item active" id="menu-overview"><a onclick="switchSection('overview')">Overview</a></li>
                <li class="nav-item" id="menu-complaints"><a onclick="switchSection('complaints')">All Complaints</a></li>
                <li class="nav-item" id="menu-reports"><a onclick="switchSection('reports')">Reports &amp; Analytics</a></li>
            </ul>
        </div>

        <div class="sidebar-footer">
            Admin Portal Management.
            <strong>Secure Access</strong>
            Monitor · Assign · Resolve
        </div>
    </aside>

    <div class="main-wrapper">
        <header class="topbar">
            <div class="breadcrumb" id="breadcrumbTitle">
                Campus Care / <span>Admin Overview</span>
            </div>

            <div class="user-controls">
                <span class="role-badge">ADMINISTRATOR</span>
                <div class="avatar-circle"><?php echo htmlspecialchars($initials); ?></div>
                <span class="user-name"><?php echo htmlspecialchars($admin_name); ?></span>
                <a href="logout.php" class="btn-logout">Logout</a>
            </div>
        </header>

        <main class="content-container">
            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success"><?php echo $success_msg; ?></div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error_msg); ?></div>
            <?php endif; ?>

            <!-- ================= SECTION 1: OVERVIEW ================= -->
            <div id="section-overview" class="app-section active-section">
                <div style="font-size: 11px; font-weight: 600; letter-spacing: 1px; color: var(--text-muted); margin-bottom: 6px;"><?php echo $current_date_banner; ?></div>
                <h1 class="hero-title">Admin Complaint Control Center</h1>
                <p class="hero-subtitle">Review, manage, and route incoming student complaints across all BUITEMS campuses.</p>

                <!-- COMPLAINT FLOW PROCESS -->
                <section class="flow-card">
                    <h3>Complaint Flow Process</h3>
                    <div class="flow-steps">
                        <div class="flow-step">
                            <strong>🎓 Student</strong>
                            Submits Complaint
                        </div>
                        <span class="flow-arrow">&rarr;</span>
                        <div class="flow-step">
                            <strong>🛡️ Admin</strong>
                            Reviews &amp; Assigns
                        </div>
                        <span class="flow-arrow">&rarr;</span>
                        <div class="flow-step">
                            <strong>📋 Select Expert</strong>
                            &amp; Staff Priority
                        </div>
                        <span class="flow-arrow">&rarr;</span>
                        <div class="flow-step">
                            <strong>🛠️ Staff</strong>
                            Investigates &amp; Resolves
                        </div>
                        <span class="flow-arrow">&rarr;</span>
                        <div class="flow-step step-final">
                            <strong>✅ Resolved</strong>
                            Feedback / Update
                        </div>
                    </div>
                </section>

                <!-- 4 STATISTICS CARDS -->
                <section class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-title">Total complaints</div>
                        <div class="stat-value"><?php echo $total_complaints; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-title">In progress / Open</div>
                        <div class="stat-value" style="color: var(--amber-accent);"><?php echo $in_progress_count; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-title">Resolved / Closed</div>
                        <div class="stat-value" style="color: var(--green-accent);"><?php echo $resolved_count; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-title">Escalated</div>
                        <div class="stat-value" style="color: var(--indigo-accent);"><?php echo $escalated_count; ?></div>
                    </div>
                </section>

                <!-- COMPLAINT TICKETS - ASSIGN & FORWARD TABLE -->
                <section class="panel-card">
                    <div class="panel-header">
                        <h3>Complaint Tickets — Assign &amp; Forward</h3>
                        <p>Manage incoming complaints and route each one to a single staff expert.</p>
                    </div>

                    <div class="filter-bar">
                        <input type="text" id="searchTicket" class="form-control" placeholder="Search by ticket ID, student name or problem..." onkeyup="filterTable('searchTicket', 'statusFilter', 'adminTable')">
                        <select id="statusFilter" class="form-control" onchange="filterTable('searchTicket', 'statusFilter', 'adminTable')">
                            <option value="">All statuses</option>
                            <option value="Assigned">Assigned</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Resolved">Resolved</option>
                            <option value="Closed">Closed</option>
                            <option value="Reopened">Reopened</option>
                            <option value="Escalated">Escalated</option>
                        </select>
                    </div>

                    <div style="overflow-x: auto;">
                        <table class="history-table" id="adminTable">
                            <thead>
                                <tr>
                                    <th style="width: 110px;">Ticket</th>
                                    <th style="width: 120px;">Student</th>
                                    <th style="width: 115px;">Category</th>
                                    <th>Problem</th>
                                    <th style="width: 105px;">Status</th>
                                    <th style="width: 145px;">Dropdown 1:<br>Staff</th>
                                    <th style="width: 145px;">Dropdown 2:<br>Priority</th>
                                    <th style="width: 175px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($all_complaints)): ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 30px;">
                                            No complaints found in the database.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($all_complaints as $c): ?>
                                        <?php 
                                            $status_class = 'status-progress';
                                            if ($c['status'] === 'Assigned' || $c['status'] === 'Reopened') $status_class = 'status-assigned';
                                            elseif ($c['status'] === 'Resolved') $status_class = 'status-resolved';
                                            elseif ($c['status'] === 'Closed') $status_class = 'status-closed';
                                            elseif ($c['status'] === 'Escalated') $status_class = 'status-escalated';

                                            $form_id = "assignForm_" . $c['id'];
                                        ?>
                                        <tr>
                                            <!-- 1. TICKET -->
                                            <td>
                                                <div class="ticket-code"><?php echo htmlspecialchars($c['ticket_code']); ?></div>
                                                <div class="ticket-meta"><?php echo date('d M Y', strtotime($c['created_at'])); ?></div>
                                            </td>

                                            <!-- 2. STUDENT -->
                                            <td>
                                                <div style="font-weight: 600;"><?php echo htmlspecialchars($c['student_name']); ?></div>
                                                <div class="ticket-meta"><?php echo htmlspecialchars($c['student_email']); ?></div>
                                            </td>

                                            <!-- 3. CATEGORY -->
                                            <td>
                                                <div style="font-weight: 500;"><?php echo htmlspecialchars($c['department']); ?></div>
                                            </td>

                                            <!-- 4. PROBLEM -->
                                            <td>
                                                <div class="ticket-title"><?php echo htmlspecialchars($c['title']); ?></div>
                                                <div class="ticket-desc"><?php echo htmlspecialchars($c['description']); ?></div>
                                                <div class="ticket-meta"><?php echo htmlspecialchars($c['campus']); ?> &bull; <?php echo htmlspecialchars($c['location']); ?></div>
                                            </td>

                                            <!-- 5. STATUS -->
                                            <td>
                                                <span class="status-pill <?php echo $status_class; ?>"><?php echo htmlspecialchars($c['status']); ?></span>
                                            </td>

                                            <!-- 6. DROPDOWN 1: STAFF -->
                                            <td>
                                                <select name="assigned_staff_id" form="<?php echo $form_id; ?>" class="inline-select">
                                                    <option value="">-- Unassigned --</option>
                                                    <?php foreach ($staff_list as $staff): ?>
                                                        <option value="<?php echo $staff['id']; ?>" <?php echo ($c['assigned_staff_id'] == $staff['id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($staff['full_name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>

                                            <!-- 7. DROPDOWN 2: PRIORITY -->
                                            <td>
                                                <select name="priority" form="<?php echo $form_id; ?>" class="inline-select">
                                                    <option value="High" <?php echo ($c['priority'] === 'High') ? 'selected' : ''; ?>>High</option>
                                                    <option value="Medium" <?php echo ($c['priority'] === 'Medium') ? 'selected' : ''; ?>>Medium</option>
                                                    <option value="Low" <?php echo ($c['priority'] === 'Low') ? 'selected' : ''; ?>>Low</option>
                                                </select>
                                            </td>

                                            <!-- 8. ACTION -->
                                            <td>
                                                <form id="<?php echo $form_id; ?>" method="POST" action="" style="display:none;">
                                                    <input type="hidden" name="complaint_id" value="<?php echo $c['id']; ?>">
                                                    <input type="hidden" name="ticket_code" value="<?php echo htmlspecialchars($c['ticket_code']); ?>">
                                                </form>

                                                <div class="action-stack">
                                                    <button type="submit" name="quick_assign" form="<?php echo $form_id; ?>" class="btn-assign-staff">
                                                        &#10148; Assign to Staff
                                                    </button>
                                                    <button type="button" class="btn-send-student" onclick='openModal(<?php echo htmlspecialchars(json_encode($c), ENT_QUOTES, "UTF-8"); ?>)'>
                                                        &#128172; Send Update to Student
                                                    </button>
                                                    <button type="button" class="btn-view" onclick='openModal(<?php echo htmlspecialchars(json_encode($c), ENT_QUOTES, "UTF-8"); ?>)'>
                                                        Manage / View
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <!-- ================= SECTION 2: ALL COMPLAINTS ================= -->
            <div id="section-complaints" class="app-section">
                <h1 class="hero-title">All Student Complaints</h1>
                <p class="hero-subtitle">Complete directory of every submitted complaint, assignment status, and priority level.</p>

                <section class="panel-card">
                    <div class="panel-header">
                        <h3>Complete Ticket Directory (<?php echo $total_num; ?>)</h3>
                    </div>

                    <div class="filter-bar">
                        <input type="text" id="searchAllTickets" class="form-control" placeholder="Filter by ticket ID, student, campus, or staff..." onkeyup="filterTable('searchAllTickets', 'statusAllFilter', 'allTicketsTable')">
                        <select id="statusAllFilter" class="form-control" onchange="filterTable('searchAllTickets', 'statusAllFilter', 'allTicketsTable')">
                            <option value="">All statuses</option>
                            <option value="Assigned">Assigned</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Resolved">Resolved</option>
                            <option value="Closed">Closed</option>
                            <option value="Reopened">Reopened</option>
                            <option value="Escalated">Escalated</option>
                        </select>
                    </div>

                    <div style="overflow-x: auto;">
                        <table class="history-table" id="allTicketsTable">
                            <thead>
                                <tr>
                                    <th>Ticket &amp; Title</th>
                                    <th>Student Info</th>
                                    <th>Department &amp; Campus</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($all_complaints)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 30px;">
                                            No complaints found in the database.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($all_complaints as $c): ?>
                                        <?php 
                                            $status_class = 'status-progress';
                                            if ($c['status'] === 'Assigned' || $c['status'] === 'Reopened') $status_class = 'status-assigned';
                                            elseif ($c['status'] === 'Resolved') $status_class = 'status-resolved';
                                            elseif ($c['status'] === 'Closed') $status_class = 'status-closed';
                                            elseif ($c['status'] === 'Escalated') $status_class = 'status-escalated';
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="ticket-code"><?php echo htmlspecialchars($c['ticket_code']); ?></div>
                                                <div class="ticket-title"><?php echo htmlspecialchars($c['title']); ?></div>
                                                <div class="ticket-meta"><?php echo date('d M Y, h:i A', strtotime($c['created_at'])); ?></div>
                                            </td>
                                            <td>
                                                <div style="font-weight: 600;"><?php echo htmlspecialchars($c['student_name']); ?></div>
                                                <div class="ticket-meta"><?php echo htmlspecialchars($c['student_email']); ?></div>
                                            </td>
                                            <td>
                                                <div style="font-weight: 500;"><?php echo htmlspecialchars($c['department']); ?></div>
                                                <div class="ticket-meta"><?php echo htmlspecialchars($c['campus']); ?> &bull; <?php echo htmlspecialchars($c['location']); ?></div>
                                                <div class="ticket-meta" style="color: var(--primary-blue); margin-top: 2px;">
                                                    Assigned: <?php echo !empty($c['staff_name']) ? htmlspecialchars($c['staff_name']) : 'Unassigned'; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span style="font-weight: 600; color: var(--text-dark);"><?php echo htmlspecialchars($c['priority']); ?></span>
                                            </td>
                                            <td>
                                                <span class="status-pill <?php echo $status_class; ?>"><?php echo htmlspecialchars($c['status']); ?></span>
                                            </td>
                                            <td>
                                                <button type="button" class="btn-view" onclick='openModal(<?php echo htmlspecialchars(json_encode($c), ENT_QUOTES, "UTF-8"); ?>)'>Manage / View</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <!-- ================= SECTION 3: REPORTS & ANALYTICS ================= -->
            <div id="section-reports" class="app-section">
                <h1 class="hero-title">Reports &amp; Analytics</h1>
                <p class="hero-subtitle">Real-time complaint resolution performance across BUITEMS campuses and departments.</p>

                <section class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-title">Resolution Rate</div>
                        <div class="stat-value" style="color: var(--green-accent);"><?php echo $resolution_rate; ?>%</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-title">Active / Pending</div>
                        <div class="stat-value" style="color: var(--amber-accent);"><?php echo $in_progress_count; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-title">Total Resolved</div>
                        <div class="stat-value"><?php echo $resolved_count; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-title">Escalated Cases</div>
                        <div class="stat-value" style="color: var(--indigo-accent);"><?php echo $escalated_count; ?></div>
                    </div>
                </section>

                <div class="analytics-grid">
                    <section class="panel-card">
                        <div class="panel-header">
                            <h3>Complaints by Department</h3>
                        </div>
                        <?php if (empty($dept_stats)): ?>
                            <p style="color: var(--text-muted); font-size: 13px;">No department data available yet.</p>
                        <?php else: ?>
                            <?php foreach ($dept_stats as $d): ?>
                                <?php $pct = $total_num > 0 ? round(($d['count'] / $total_num) * 100) : 0; ?>
                                <div class="progress-row">
                                    <div class="progress-label">
                                        <span><?php echo htmlspecialchars($d['department']); ?></span>
                                        <span><?php echo $d['count']; ?> ticket(s) (<?php echo $pct; ?>%)</span>
                                    </div>
                                    <div class="progress-track">
                                        <div class="progress-fill" style="width: <?php echo $pct; ?>%;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </section>

                    <section class="panel-card">
                        <div class="panel-header">
                            <h3>Complaints by Campus</h3>
                        </div>
                        <?php if (empty($campus_stats)): ?>
                            <p style="color: var(--text-muted); font-size: 13px;">No campus data available yet.</p>
                        <?php else: ?>
                            <?php foreach ($campus_stats as $cam): ?>
                                <?php $pct = $total_num > 0 ? round(($cam['count'] / $total_num) * 100) : 0; ?>
                                <div class="progress-row">
                                    <div class="progress-label">
                                        <span><?php echo htmlspecialchars($cam['campus']); ?></span>
                                        <span><?php echo $cam['count']; ?> ticket(s) (<?php echo $pct; ?>%)</span>
                                    </div>
                                    <div class="progress-track">
                                        <div class="progress-fill" style="width: <?php echo $pct; ?>%; background: var(--green-accent);"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </section>
                </div>
            </div>
        </main>
    </div>

    <!-- MANAGE / SEND UPDATE MODAL -->
    <div class="modal-backdrop" id="manageModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3 id="modalTitle">Manage Ticket</h3>
                <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="complaint_id" id="modalComplaintId">
                <div id="modalBody">
                    <!-- Populated via JavaScript -->
                </div>
                
                <div style="margin-top: 16px; border-top: 1px solid var(--border-color); padding-top: 16px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="modal-row">
                            <label for="modalStatus" style="display:block; font-size:12px; font-weight:600; color:var(--text-dark); margin-bottom:6px;">Update Status</label>
                            <select name="status" id="modalStatus" class="form-control" required>
                                <option value="Assigned">Assigned</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Resolved">Resolved</option>
                                <option value="Closed">Closed</option>
                                <option value="Escalated">Escalated</option>
                            </select>
                        </div>

                        <div class="modal-row">
                            <label for="modalPriority" style="display:block; font-size:12px; font-weight:600; color:var(--text-dark); margin-bottom:6px;">Priority</label>
                            <select name="priority" id="modalPriority" class="form-control" required>
                                <option value="High">High</option>
                                <option value="Medium">Medium</option>
                                <option value="Low">Low</option>
                            </select>
                        </div>
                    </div>

                    <div class="modal-row" style="margin-top: 12px;">
                        <label for="modalStaff" style="display:block; font-size:12px; font-weight:600; color:var(--text-dark); margin-bottom:6px;">Assign to Staff Member</label>
                        <select name="assigned_staff_id" id="modalStaff" class="form-control">
                            <option value="">-- Unassigned --</option>
                            <?php foreach ($staff_list as $staff): ?>
                                <option value="<?php echo $staff['id']; ?>"><?php echo htmlspecialchars($staff['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="modal-row" style="margin-top: 12px;">
                        <label for="modalNotes" style="display:block; font-size:12px; font-weight:600; color:var(--text-dark); margin-bottom:6px;">Send Update / Remarks to Student</label>
                        <textarea name="admin_notes" id="modalNotes" class="form-control" placeholder="Write an update or resolution remarks for the student..." style="min-height: 80px; resize: vertical;"></textarea>
                    </div>

                    <div style="margin-top: 16px; text-align: right;">
                        <button type="submit" name="update_ticket" class="btn-submit">Save Changes &amp; Notify Student</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        function switchSection(sectionId) {
            document.querySelectorAll('.app-section').forEach(sec => sec.classList.remove('active-section'));
            document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));

            document.getElementById('section-' + sectionId).classList.add('active-section');
            document.getElementById('menu-' + sectionId).classList.add('active');

            let title = "Admin Overview";
            if (sectionId === 'complaints') title = "All Complaints";
            if (sectionId === 'reports') title = "Reports & Analytics";
            document.getElementById('breadcrumbTitle').innerHTML = `Campus Care / <span>${title}</span>`;
        }

        function openModal(complaint) {
            document.getElementById('modalTitle').innerText = 'Ticket: ' + complaint.ticket_code;
            document.getElementById('modalComplaintId').value = complaint.id;
            document.getElementById('modalStatus').value = complaint.status;
            document.getElementById('modalPriority').value = complaint.priority || 'Medium';
            document.getElementById('modalStaff').value = complaint.assigned_staff_id || '';
            document.getElementById('modalNotes').value = complaint.admin_notes || '';

            let photosHtml = '';
            ['photo1', 'photo2', 'photo3'].forEach(pKey => {
                if (complaint[pKey]) {
                    photosHtml += '<img src="' + complaint[pKey] + '" alt="Evidence photo">';
                }
            });

            let html = `
                <div class="modal-row">
                    <strong>Student Name &amp; Email</strong>
                    ${escapeHtml(complaint.student_name)} (${escapeHtml(complaint.student_email)})
                </div>
                <div class="modal-row">
                    <strong>Title &amp; Description</strong>
                    <div style="font-weight:600; margin-top:2px;">${escapeHtml(complaint.title)}</div>
                    <p style="margin-top:4px; color:#334155; line-height:1.4;">${escapeHtml(complaint.description)}</p>
                </div>
                <div class="modal-row">
                    <strong>Campus &amp; Location</strong>
                    ${escapeHtml(complaint.campus)} &bull; ${escapeHtml(complaint.location)}
                </div>
                <div class="modal-row">
                    <strong>Department &amp; Priority</strong>
                    ${escapeHtml(complaint.department)} &bull; <span style="font-weight:600; color:var(--primary-blue);">${escapeHtml(complaint.priority)}</span>
                </div>
            `;

            if (photosHtml) {
                html += `
                    <div class="modal-row">
                        <strong>Evidence Photos</strong>
                        <div class="modal-photos">${photosHtml}</div>
                    </div>
                `;
            }

            document.getElementById('modalBody').innerHTML = html;
            document.getElementById('manageModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('manageModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('manageModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }

        function escapeHtml(text) {
            if (text === null || text === undefined) return '';
            return String(text)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        function filterTable(searchInputId, statusSelectId, tableId) {
            const query = document.getElementById(searchInputId).value.toLowerCase();
            const statusVal = document.getElementById(statusSelectId).value;
            const rows = document.querySelectorAll('#' + tableId + ' tbody tr');

            rows.forEach(row => {
                if (row.cells.length < 5) return;
                const text = row.innerText.toLowerCase();
                const statusPill = row.querySelector('.status-pill').innerText;

                const matchesQuery = text.includes(query);
                const matchesStatus = !statusVal || statusPill === statusVal;

                if (matchesQuery && matchesStatus) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
    </script>
</body> 
</html>