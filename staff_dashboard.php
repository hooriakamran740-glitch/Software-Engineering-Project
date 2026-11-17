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
// 2. STAFF AUTHENTICATION CHECK
// ==========================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit();
}

$staff_id = $_SESSION['user_id'];
$staff_name = $_SESSION['full_name'] ?? 'Aisha Rahman';

$name_parts = explode(' ', trim($staff_name));
$initials = strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));

// Auto-escalate unresolved tickets older than 48 hours
$pdo->query("UPDATE complaints SET status = 'Escalated' WHERE status IN ('In Progress', 'Assigned', 'Reopened') AND created_at <= NOW() - INTERVAL 48 HOUR");

$success_msg = "";
$error_msg = "";

// ==========================================
// 3. HANDLE ALL STAFF ACTIONS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complaint_id'])) {
    $complaint_id = (int)$_POST['complaint_id'];
    $ticket_code = trim($_POST['ticket_code'] ?? '');

    try {
        // Start Work
        if (isset($_POST['start_work'])) {
            $stmt = $pdo->prepare("UPDATE complaints SET status = 'In Progress' WHERE id = ? AND assigned_staff_id = ?");
            $stmt->execute([$complaint_id, $staff_id]);
            $success_msg = "Work started on ticket <strong>" . htmlspecialchars($ticket_code) . "</strong> (Status: In Progress).";

        // Send Update to Student
        } elseif (isset($_POST['send_update'])) {
            $msg = trim($_POST['message_to_student'] ?? '');
            if (!empty($msg)) {
                $stmt = $pdo->prepare("UPDATE complaints SET admin_notes = ?, status = CASE WHEN status = 'Assigned' THEN 'In Progress' ELSE status END WHERE id = ? AND assigned_staff_id = ?");
                $stmt->execute([$msg, $complaint_id, $staff_id]);
                $success_msg = "Update sent to the student for <strong>" . htmlspecialchars($ticket_code) . "</strong>.";
            } else {
                $error_msg = "Please enter a message before sending an update to the student.";
            }

        // Mark Resolved & Notify Student
        } elseif (isset($_POST['mark_resolved'])) {
            $msg = trim($_POST['message_to_student'] ?? '');
            if (!empty($msg)) {
                $stmt = $pdo->prepare("UPDATE complaints SET status = 'Resolved', admin_notes = ? WHERE id = ? AND assigned_staff_id = ?");
                $stmt->execute([$msg, $complaint_id, $staff_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE complaints SET status = 'Resolved' WHERE id = ? AND assigned_staff_id = ?");
                $stmt->execute([$complaint_id, $staff_id]);
            }
            $success_msg = "Ticket <strong>" . htmlspecialchars($ticket_code) . "</strong> marked as Resolved &amp; student notified.";

        // Modal Update
        } elseif (isset($_POST['update_repair'])) {
            $new_status = $_POST['status'] ?? '';
            $repair_notes = trim($_POST['repair_notes'] ?? '');
            if (!empty($new_status)) {
                $updateStmt = $pdo->prepare("UPDATE complaints SET status = ?, admin_notes = ? WHERE id = ? AND assigned_staff_id = ?");
                $updateStmt->execute([$new_status, $repair_notes, $complaint_id, $staff_id]);
                $success_msg = "Ticket repair status successfully updated to <strong>" . htmlspecialchars($new_status) . "</strong>.";
            } else {
                $error_msg = "Invalid status selection.";
            }
        }
    } catch (PDOException $e) {
        $error_msg = "Failed to update ticket: " . $e->getMessage();
    }
}

// ==========================================
// 4. FETCH STAFF METRIC COUNTS (ONLY MINE)
// ==========================================
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status IN ('Assigned', 'Reopened') THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status IN ('In Progress', 'Escalated') THEN 1 ELSE 0 END) AS in_progress,
        SUM(CASE WHEN status IN ('Resolved', 'Closed') THEN 1 ELSE 0 END) AS resolved
    FROM complaints
    WHERE assigned_staff_id = ?
");
$statsStmt->execute([$staff_id]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

$total_complaints = str_pad((int)($stats['total'] ?? 0), 2, '0', STR_PAD_LEFT);
$pending_count = str_pad((int)($stats['pending'] ?? 0), 2, '0', STR_PAD_LEFT);
$in_progress_count = str_pad((int)($stats['in_progress'] ?? 0), 2, '0', STR_PAD_LEFT);
$resolved_count = str_pad((int)($stats['resolved'] ?? 0), 2, '0', STR_PAD_LEFT);

// ==========================================
// 5. FETCH ASSIGNED COMPLAINTS WITH STUDENT INFO
// ==========================================
$complaintsStmt = $pdo->prepare("
    SELECT c.*, u.full_name AS student_name, u.email AS student_email 
    FROM complaints c 
    JOIN users u ON c.student_id = u.id 
    WHERE c.assigned_staff_id = ? 
    ORDER BY c.created_at DESC
");
$complaintsStmt->execute([$staff_id]);
$assigned_complaints = $complaintsStmt->fetchAll(PDO::FETCH_ASSOC);

$current_date_banner = strtoupper(date('l, d F Y'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BUITEMS | Campus Care - Staff Dashboard</title>
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
        .role-badge { background: #eff6ff; color: #1e40af; font-size: 11px; font-weight: 700; padding: 5px 10px; border-radius: 6px; }
        .avatar-circle { width: 34px; height: 34px; background: #08285b; color: #ffffff; font-weight: 700; font-size: 13px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .user-name { font-size: 14px; font-weight: 600; color: var(--text-dark); }
        .btn-logout { padding: 7px 14px; border: 1px solid var(--border-color); background: #ffffff; color: var(--text-dark); border-radius: 8px; font-size: 13px; font-weight: 500; text-decoration: none; }

        .content-container { padding: 32px; max-width: 1420px; width: 100%; margin: 0 auto; }
        .hero-title { font-size: 30px; font-weight: 700; color: var(--text-dark); margin-bottom: 6px; }
        .hero-subtitle { font-size: 15px; color: var(--text-muted); margin-bottom: 24px; }

        .alert { padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 28px; }
        .stat-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 14px; padding: 20px 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
        .stat-title { font-size: 13px; color: var(--text-muted); margin-bottom: 12px; }
        .stat-value { font-size: 32px; font-weight: 700; color: var(--text-dark); }

        .panel-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; padding-bottom: 16px; border-bottom: 1px solid #f1f5f9; }
        .panel-header h3 { font-size: 19px; font-weight: 700; color: var(--text-dark); }

        .filter-bar { display: grid; grid-template-columns: 1fr 200px; gap: 12px; margin-bottom: 16px; }
        .form-control { width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; outline: none; }

        /* 8 Separate Columns Table */
        .history-table { width: 100%; border-collapse: collapse; min-width: 1050px; }
        .history-table th { background: #f8fafc; text-align: left; padding: 14px 12px; font-size: 12px; font-weight: 700; color: var(--text-dark); border-bottom: 1px solid var(--border-color); white-space: nowrap; }
        .history-table td { padding: 16px 12px; border-bottom: 1px solid #f1f5f9; vertical-align: top; font-size: 13px; }

        .ticket-code { font-size: 13px; font-weight: 700; color: var(--text-dark); line-height: 1.4; }
        .ticket-title { font-size: 14px; color: #0f172a; font-weight: 700; margin-bottom: 4px; line-height: 1.35; }
        .ticket-desc { font-size: 13px; color: #475569; margin-bottom: 6px; line-height: 1.4; }
        .ticket-meta { font-size: 12px; color: var(--text-muted); line-height: 1.4; }

        .status-pill { display: inline-block; padding: 5px 11px; border-radius: 999px; font-size: 11px; font-weight: 700; white-space: nowrap; }
        .status-assigned { background: #fef3c7; color: #b45309; }
        .status-progress { background: #ffedd5; color: #c2410c; }
        .status-resolved { background: #dcfce7; color: #15803d; }
        .status-escalated { background: #e0e7ff; color: #4338ca; }

        .priority-pill { display: inline-block; padding: 4px 11px; border-radius: 999px; font-size: 11px; font-weight: 700; white-space: nowrap; }
        .prio-high { background: #fee2e2; color: #b91c1c; }
        .prio-medium { background: #fef3c7; color: #b45309; }
        .prio-low { background: #e0f2fe; color: #0369a1; }

        /* Action Column Stack */
        .quick-action-box { display: flex; flex-direction: column; gap: 7px; width: 205px; }
        .quick-input { width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 7px; font-size: 12px; outline: none; }
        .quick-input:focus { border-color: var(--primary-blue); }

        .btn-start-work { width: 100%; padding: 7px 12px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 7px; font-size: 12px; font-weight: 600; cursor: pointer; color: var(--text-dark); }
        .btn-start-work:hover { background: #f1f5f9; }

        .btn-send-update { width: 100%; padding: 8px 12px; background: #ffffff; border: 1.5px solid #08285b; border-radius: 7px; font-size: 12px; font-weight: 700; cursor: pointer; color: #08285b; line-height: 1.3; }
        .btn-send-update:hover { background: #eff6ff; }

        .btn-mark-resolved { width: 100%; padding: 9px 12px; background: #08285b; border: none; border-radius: 7px; font-size: 12px; font-weight: 700; cursor: pointer; color: #ffffff; line-height: 1.3; }
        .btn-mark-resolved:hover { background: #1a3b70; }

        .btn-view-photo { display: inline-block; margin-top: 6px; font-size: 12px; color: var(--primary-blue); font-weight: 600; background: none; border: none; cursor: pointer; text-decoration: underline; padding: 0; }

        .table-privacy-footer { margin-top: 16px; padding-top: 12px; border-top: 1px solid #f1f5f9; font-size: 12px; color: var(--text-muted); }

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

            <div class="nav-label">STAFF WORKSPACE</div>
            <ul class="nav-menu">
                <li class="nav-item active" id="menu-assigned"><a onclick="switchSection('assigned')">My assigned tickets</a></li>
                <li class="nav-item" id="menu-activity"><a onclick="switchSection('activity')">Activity &amp; updates</a></li>
            </ul>
        </div>

        <div class="sidebar-footer">
            Your work makes a difference.
            <strong>Campus support</strong>
            Report · Track · Resolve
        </div>
    </aside>

    <div class="main-wrapper">
        <header class="topbar">
            <div class="breadcrumb" id="breadcrumbTitle">
                Campus Care / <span>My assigned tickets</span>
            </div>

            <div class="user-controls">
                <span class="role-badge">STAFF PORTAL</span>
                <div class="avatar-circle"><?php echo htmlspecialchars($initials); ?></div>
                <span class="user-name"><?php echo htmlspecialchars($staff_name); ?></span>
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

            <!-- ================= SECTION 1: ASSIGNED TICKETS ================= -->
            <div id="section-assigned" class="app-section active-section">
                <div style="font-size: 11px; font-weight: 600; letter-spacing: 1px; color: var(--text-muted); margin-bottom: 6px;"><?php echo $current_date_banner; ?></div>
                <h1 class="hero-title">My Assigned Tickets — Only Mine</h1>
                <p class="hero-subtitle">Tickets assigned specifically to you by Admin, not the whole department.</p>

                <!-- STATS GRID -->
                <section class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-title">Total assigned</div>
                        <div class="stat-value"><?php echo $total_complaints; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-title">My Pending</div>
                        <div class="stat-value" style="color: var(--amber-accent);"><?php echo $pending_count; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-title">My In Progress</div>
                        <div class="stat-value" style="color: var(--primary-blue);"><?php echo $in_progress_count; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-title">My Resolved</div>
                        <div class="stat-value" style="color: var(--green-accent);"><?php echo $resolved_count; ?></div>
                    </div>
                </section>

                <section class="panel-card">
                    <div class="panel-header">
                        <h3>Assigned Complaints</h3>
                    </div>

                    <div class="filter-bar">
                        <input type="text" id="searchTicket" class="form-control" placeholder="Search ticket, student, category or location..." onkeyup="filterTable()">
                        <select id="statusFilter" class="form-control" onchange="filterTable()">
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
                        <table class="history-table" id="staffTable">
                            <thead>
                                <tr>
                                    <th style="width: 115px;">Ticket</th>
                                    <th style="width: 130px;">Student</th>
                                    <th style="width: 120px;">Category</th>
                                    <th>Description</th>
                                    <th style="width: 95px;">Priority</th>
                                    <th style="width: 110px;">Assigned by Admin</th>
                                    <th style="width: 110px;">Status</th>
                                    <th style="width: 220px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($assigned_complaints)): ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 30px;">
                                            No complaints currently assigned to you.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($assigned_complaints as $c): ?>
                                        <?php 
                                            $status_class = 'status-progress';
                                            if ($c['status'] === 'Assigned' || $c['status'] === 'Reopened') $status_class = 'status-assigned';
                                            elseif ($c['status'] === 'Resolved' || $c['status'] === 'Closed') $status_class = 'status-resolved';
                                            elseif ($c['status'] === 'Escalated') $status_class = 'status-escalated';

                                            $prio = $c['priority'] ?? 'Medium';
                                            $prio_class = 'prio-medium';
                                            if ($prio === 'High') $prio_class = 'prio-high';
                                            if ($prio === 'Low') $prio_class = 'prio-low';
                                        ?>
                                        <tr>
                                            <!-- 1. TICKET -->
                                            <td>
                                                <div class="ticket-code"><?php echo htmlspecialchars($c['ticket_code']); ?></div>
                                            </td>

                                            <!-- 2. STUDENT -->
                                            <td>
                                                <div style="font-weight: 600; color: var(--text-dark);"><?php echo htmlspecialchars($c['student_name']); ?></div>
                                            </td>

                                            <!-- 3. CATEGORY -->
                                            <td>
                                                <div style="font-weight: 500;"><?php echo htmlspecialchars($c['department']); ?></div>
                                            </td>

                                            <!-- 4. DESCRIPTION -->
                                            <td>
                                                <div class="ticket-title"><?php echo htmlspecialchars($c['title']); ?></div>
                                                <div class="ticket-desc"><?php echo htmlspecialchars($c['description']); ?></div>
                                                <div class="ticket-meta"><?php echo htmlspecialchars($c['campus']); ?> &bull; <?php echo htmlspecialchars($c['location']); ?></div>
                                                <?php if (!empty($c['admin_notes'])): ?>
                                                    <div class="ticket-meta" style="color: var(--primary-blue); margin-top: 4px;">
                                                        <strong>Last Update:</strong> <?php echo htmlspecialchars($c['admin_notes']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <button type="button" class="btn-view-photo" onclick='openModal(<?php echo htmlspecialchars(json_encode($c), ENT_QUOTES, "UTF-8"); ?>)'>View Details / Photos</button>
                                            </td>

                                            <!-- 5. PRIORITY -->
                                            <td>
                                                <span class="priority-pill <?php echo $prio_class; ?>"><?php echo htmlspecialchars($prio); ?></span>
                                            </td>

                                            <!-- 6. ASSIGNED BY ADMIN -->
                                            <td>
                                                <div style="color: #334155; line-height: 1.4;">Admin<br>User</div>
                                            </td>

                                            <!-- 7. STATUS -->
                                            <td>
                                                <span class="status-pill <?php echo $status_class; ?>"><?php echo htmlspecialchars($c['status']); ?></span>
                                            </td>

                                            <!-- 8. ACTION (LAST COLUMN) -->
                                            <td>
                                                <form method="POST" action="" class="quick-action-box">
                                                    <input type="hidden" name="complaint_id" value="<?php echo $c['id']; ?>">
                                                    <input type="hidden" name="ticket_code" value="<?php echo htmlspecialchars($c['ticket_code']); ?>">

                                                    <?php if ($c['status'] === 'Assigned' || $c['status'] === 'Reopened'): ?>
                                                        <button type="submit" name="start_work" class="btn-start-work">Start work</button>
                                                    <?php endif; ?>

                                                    <input type="text" name="message_to_student" class="quick-input" placeholder="Message to student...">

                                                    <button type="submit" name="send_update" class="btn-send-update">Send Update to Student</button>

                                                    <button type="submit" name="mark_resolved" class="btn-mark-resolved">Mark Resolved &amp; Notify Student</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="table-privacy-footer">
                        Showing <?php echo count($assigned_complaints); ?> ticket(s) assigned to <strong><?php echo htmlspecialchars($staff_name); ?></strong> &middot; &#128274; Only tickets assigned specifically to you are shown. Department-wide tickets are hidden.
                    </div>
                </section>
            </div>

            <!-- ================= SECTION 2: ACTIVITY & UPDATES ================= -->
            <div id="section-activity" class="app-section">
                <div class="panel-card">
                    <div class="panel-header">
                        <h3>Activity &amp; updates</h3>
                    </div>
                    <div style="padding: 10px 0;">
                        <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 16px;">In-app updates. Email delivery is not configured.</p>
                        <?php if (empty($assigned_complaints)): ?>
                            <p style="color: var(--text-muted); text-align: center; padding: 20px;">No recent activities found.</p>
                        <?php else: ?>
                            <?php foreach ($assigned_complaints as $c): ?>
                                <div style="display: flex; gap: 14px; padding: 14px 0; border-bottom: 1px solid var(--border-color);">
                                    <div style="width: 8px; height: 8px; background: var(--primary-blue); border-radius: 50%; margin-top: 6px;"></div>
                                    <div>
                                        <div style="font-size: 14px; font-weight: 600;"><?php echo htmlspecialchars($c['ticket_code']); ?> · Complaint received and assigned to <?php echo htmlspecialchars($staff_name); ?>.</div>
                                        <?php if (!empty($c['admin_notes'])): ?>
                                            <div style="font-size: 13px; color: #334155; margin-top: 3px;">Latest note: <?php echo htmlspecialchars($c['admin_notes']); ?></div>
                                        <?php endif; ?>
                                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;"><?php echo date('m/d/Y, h:i:s A', strtotime($c['created_at'])); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- DETAILS & PHOTOS MODAL -->
    <div class="modal-backdrop" id="repairModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3 id="modalTitle">Ticket Details</h3>
                <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="complaint_id" id="modalComplaintId">
                <div id="modalBody"></div>

                <div style="margin-top: 16px; border-top: 1px solid var(--border-color); padding-top: 16px;">
                    <div class="modal-row">
                        <label for="modalStatus" style="display:block; font-size:12px; font-weight:600; color:var(--text-dark); margin-bottom:6px;">Update Repair Status</label>
                        <select name="status" id="modalStatus" class="form-control" required>
                            <option value="Assigned">Assigned</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Resolved">Resolved</option>
                            <option value="Escalated">Escalated</option>
                        </select>
                    </div>

                    <div class="modal-row" style="margin-top: 12px;">
                        <label for="modalNotes" style="display:block; font-size:12px; font-weight:600; color:var(--text-dark); margin-bottom:6px;">Record Repair Notes</label>
                        <textarea name="repair_notes" id="modalNotes" class="form-control" placeholder="Describe actions taken or repairs recorded..." style="min-height: 80px; resize: vertical;"></textarea>
                    </div>

                    <div style="margin-top: 16px; text-align: right;">
                        <button type="submit" name="update_repair" class="btn-submit">Save Repair Status</button>
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

            let title = "My assigned tickets";
            if(sectionId === 'activity') title = "Activity & updates";
            document.getElementById('breadcrumbTitle').innerHTML = `Campus Care / <span>${title}</span>`;
        }

        function openModal(complaint) {
            document.getElementById('modalTitle').innerText = 'Ticket: ' + complaint.ticket_code;
            document.getElementById('modalComplaintId').value = complaint.id;
            document.getElementById('modalStatus').value = complaint.status;
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
                    <strong>Issue Title &amp; Description</strong>
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
            document.getElementById('repairModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('repairModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('repairModal');
            if (event.target === modal) modal.style.display = 'none';
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

        function filterTable() {
            const query = document.getElementById('searchTicket').value.toLowerCase();
            const statusVal = document.getElementById('statusFilter').value;
            const rows = document.querySelectorAll('#staffTable tbody tr');

            rows.forEach(row => {
                if (row.cells.length < 8) return;
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