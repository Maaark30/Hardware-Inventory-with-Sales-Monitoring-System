<?php
include 'project.php';
session_start();

// ===================== AUTH CHECK =====================
if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}

// ===================== ADD STAFF HANDLER =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_staff') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $errors = [];
    if (empty($full_name)) $errors[] = 'Full name is required.';
    if (empty($username)) $errors[] = 'Username is required.';
    if (empty($password)) $errors[] = 'Password is required.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters long.';
    if ($password !== $confirm_password) $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check_stmt->bind_param("s", $username);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) { echo "error: Username already exists."; exit(); }
        $check_stmt->close();

        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $insert_stmt = $conn->prepare("INSERT INTO users (username, password, full_name, role, created_at) VALUES (?, ?, ?, 'staff', NOW())");
        $insert_stmt->bind_param("sss", $username, $hashed_password, $full_name);
        if ($insert_stmt->execute()) { echo "success: Staff account created successfully."; exit(); }
        else { echo "error: Failed to create staff account. " . $insert_stmt->error; exit(); }
        $insert_stmt->close();
    } else {
        echo "error: " . implode(" ", $errors); exit();
    }
}

// ===================== EDIT STAFF HANDLER =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_staff') {
    $staff_id = (int)($_POST['staff_id'] ?? 0);
    $full_name = trim($_POST['edit_full_name'] ?? '');
    $username = trim($_POST['edit_username'] ?? '');
    $password = $_POST['edit_password'] ?? '';

    $errors = [];
    if ($staff_id <= 0) $errors[] = 'Invalid staff ID.';
    if (empty($full_name)) $errors[] = 'Full name is required.';
    if (empty($username)) $errors[] = 'Username is required.';

    if (empty($errors)) {
        // Check if username exists on ANOTHER account
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check_stmt->bind_param("si", $username, $staff_id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) { echo "error: Username already exists."; exit(); }
        $check_stmt->close();

        if (!empty($password)) {
            if (strlen($password) < 6) { echo "error: Password must be at least 6 characters."; exit(); }
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $query = "UPDATE users SET full_name = ?, username = ?, password = ? WHERE id = ? AND role = 'staff'";
            $update_stmt = $conn->prepare($query);
            $update_stmt->bind_param("sssi", $full_name, $username, $hashed_password, $staff_id);
        } else {
            $query = "UPDATE users SET full_name = ?, username = ? WHERE id = ? AND role = 'staff'";
            $update_stmt = $conn->prepare($query);
            $update_stmt->bind_param("ssi", $full_name, $username, $staff_id);
        }

        if ($update_stmt->execute()) { echo "success: Staff account updated successfully."; exit(); }
        else { echo "error: Failed to update. " . $update_stmt->error; exit(); }
        $update_stmt->close();
    } else {
        echo "error: " . implode(" ", $errors); exit();
    }
}

// ===================== DELETE STAFF HANDLER =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_staff') {
    $staff_id = (int)($_POST['staff_id'] ?? 0);
    if ($staff_id <= 0) { echo "error: Invalid staff ID."; exit(); }

    $check_stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'staff'");
    $check_stmt->bind_param("i", $staff_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows === 0) { echo "error: Staff account not found."; exit(); }
    $check_stmt->close();

    $delete_stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'staff'");
    $delete_stmt->bind_param("i", $staff_id);
    echo $delete_stmt->execute() ? "success: Staff account deleted successfully." : "error: Failed to delete. " . $delete_stmt->error;
    $delete_stmt->close();
    exit();
}

// ===================== SEARCH & DATA =====================
$search = trim($_GET['q'] ?? '');

$total_staff = (int)($conn->query("SELECT COUNT(*) AS t FROM users WHERE role='staff'")->fetch_assoc()['t'] ?? 0);
$total_online = (int)($conn->query("SELECT COUNT(*) AS t FROM users WHERE role='staff' AND is_logged_in=1")->fetch_assoc()['t'] ?? 0);
$total_offline = $total_staff - $total_online;

$users = [];
$sql = "SELECT id, username, full_name, role, is_logged_in, last_login FROM users WHERE role = 'staff'";
$params = []; $types = '';

if ($search !== '') {
    $sql .= " AND (full_name LIKE ? OR username LIKE ?)";
    $search_term = '%' . $search . '%';
    $params = [$search_term, $search_term]; $types = 'ss';
}
$sql .= " ORDER BY is_logged_in DESC, full_name ASC";

$stmt = $conn->prepare($sql);
if (!$stmt) die("Query error: " . $conn->error);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) $users[] = $row;
$stmt->close();

function avatarColor(string $name): string {
    $colors = ['#3b5bdb','#1971c2','#0c8599','#2f9e44','#e67700','#c2255c','#9c36b5','#d63939'];
    return $colors[abs(crc32($name)) % count($colors)];
}
function initials(string $name): string {
    $parts = explode(' ', trim($name));
    return count($parts) >= 2 ? strtoupper(substr($parts[0],0,1).substr($parts[1],0,1)) : strtoupper(substr($name,0,2));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
        <link rel="icon" type="image/x-icon" href="favicon.ico">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="css/admin1.css">
    <link rel="stylesheet" href="css/alert.css">
    <style>
        :root {
            --bg:           #eef1f8;
            --surface:      #ffffff;
            --surface-2:    #f7f9fc;
            --border:       #e2e8f0;
            --border-light: #edf2f7;
            --ink:          #0f172a;
            --ink-2:        #334155;
            --muted:        #64748b;
            --faint:        #94a3b8;
            --blue:         #2563eb;
            --blue-dk:      #1d4ed8;
            --blue-lt:      #eff6ff;
            --blue-mid:     #dbeafe;
            --green:        #059669;
            --green-lt:     #ecfdf5;
            --amber:        #d97706;
            --amber-lt:     #fffbeb;
            --red:          #dc2626;
            --red-lt:       #fef2f2;
            --violet:       #7c3aed;
            --violet-lt:    #f5f3ff;
            --r:            12px;
            --r-sm:         8px;
            --r-lg:         18px;
            --sh-xs:        0 1px 3px rgba(0,0,0,.05);
            --sh-sm:        0 2px 8px rgba(0,0,0,.06);
            --sh:           0 4px 20px rgba(0,0,0,.08);
            --sh-lg:        0 8px 32px rgba(0,0,0,.1);
            --font:         'Plus Jakarta Sans', sans-serif;
            --mono:         'JetBrains Mono', monospace;
        }
        *,*::before,*::after{box-sizing:border-box;}
        body { font-family:var(--font); background:var(--bg); color:var(--ink); font-size:14px; }
        .content { background:var(--bg); min-height:100vh; }
        .main-wrap { padding:28px 28px 64px; }
        .dropdown-toggle::after { display:none; }

        /* KPI CARDS */
        .kpi-card {
            border-radius: 20px; border: none; padding: 1.5rem 1.75rem;
            position: relative; overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .kpi-card:hover { transform: translateY(-3px); box-shadow: 0 20px 48px rgba(0,0,0,0.14) !important; }
        .kpi-card::after {
            content:''; position:absolute; right:-20px; bottom:-20px;
            width:96px; height:96px; border-radius:50%; background:rgba(255,255,255,0.1);
        }
        .kpi-label { font-size:0.75rem; font-weight:600; letter-spacing:0.09em; text-transform:uppercase; opacity:0.85; margin-bottom:0.35rem; }
        .kpi-value { font-size:2.5rem; font-weight:700; letter-spacing:-0.04em; line-height:1; }
        .kpi-icon-wrap { position:absolute; right:1.5rem; top:50%; transform:translateY(-50%); font-size:2.4rem; opacity:0.2; }

        /* STAFF CARD */
        .staff-card { background:#fff; border-radius:20px; border:1px solid rgba(0,0,0,0.055); box-shadow:0 4px 24px rgba(15,23,42,0.06); overflow:hidden; }
        .staff-card-header { padding:1.4rem 1.6rem 1rem; border-bottom:1px solid rgba(0,0,0,0.055); }

        /* TABLE */
        .staff-table { margin:0; }
        .staff-table thead th {
            background:#f8faff; color:#64748b; font-size:0.71rem; font-weight:700;
            letter-spacing:0.09em; text-transform:uppercase; padding:0.8rem 1.3rem;
            border-bottom:1px solid rgba(0,0,0,0.055); border-top:none; white-space:nowrap;
        }
        .staff-table tbody tr { transition:background 0.12s; animation:rowFade 0.3s ease both; }
        .staff-table tbody tr:hover { background:#f5f8ff; }
        .staff-table tbody td { padding:0.95rem 1.3rem; border-bottom:1px solid rgba(0,0,0,0.04); vertical-align:middle; font-size:0.89rem; color:#1e293b; }
        .staff-table tbody tr:last-child td { border-bottom:none; }
        @keyframes rowFade { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }

        /* AVATAR */
        .staff-avatar { width:40px; height:40px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:0.76rem; font-weight:700; color:#fff; flex-shrink:0; font-family:'DM Mono',monospace; letter-spacing:0.03em; }
        .staff-name-cell { display:flex; align-items:center; gap:11px; }
        .staff-name { font-weight:600; color:#0f172a; font-size:0.91rem; }
        .staff-role-tag { font-size:0.71rem; color:#94a3b8; font-weight:500; margin-top:1px; }

        /* USERNAME */
        .username-tag { font-family:'DM Mono',monospace; font-size:0.8rem; color:#475569; background:#f1f5f9; padding:3px 10px; border-radius:7px; display:inline-block; }

        /* STATUS */
        .status-pill { display:inline-flex; align-items:center; gap:6px; font-size:0.77rem; font-weight:600; padding:4px 11px; border-radius:999px; }
        .status-online { background:#dcfce7; color:#16a34a; }
        .status-offline { background:#f1f5f9; color:#64748b; }
        .status-dot { width:7px; height:7px; border-radius:50%; flex-shrink:0; }
        .status-online .status-dot { background:#16a34a; animation:pulse-green 1.8s infinite; }
        .status-offline .status-dot { background:#94a3b8; }
        @keyframes pulse-green { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:0.5;transform:scale(1.4)} }

        /* LAST LOGIN */
        .last-login { font-size:0.83rem; color:#64748b; }

        /* EDIT/DEL BTNS */
        .btn-edit { width:34px; height:34px; border-radius:10px; border:1.5px solid #dbeafe; background:#eff6ff; color:#2563eb; display:inline-flex; align-items:center; justify-content:center; font-size:0.88rem; transition:all 0.15s; cursor:pointer; }
        .btn-edit:hover { background:#2563eb; border-color:#2563eb; color:#fff; transform:scale(1.08); }
        .btn-del { width:34px; height:34px; border-radius:10px; border:1.5px solid #fecaca; background:#fff5f5; color:#dc2626; display:inline-flex; align-items:center; justify-content:center; font-size:0.88rem; transition:all 0.15s; cursor:pointer; }
        .btn-del:hover { background:#dc2626; border-color:#dc2626; color:#fff; transform:scale(1.08); }

        /* PAGE HEADER */
        .page-eyebrow { font-size:0.71rem; font-weight:700; letter-spacing:0.11em; text-transform:uppercase; color:#3b5bdb; margin-bottom:0.2rem; }
        .page-heading { font-size:1.65rem; font-weight:700; color:#0f172a; letter-spacing:-0.025em; margin-bottom:0.15rem; }
        .page-subtext { font-size:0.87rem; color:#64748b; }

        /* SEARCH */
        .search-wrap .form-control { border-radius:12px 0 0 12px; border:1.5px solid #e2e8f0; font-size:0.87rem; padding:0.52rem 1rem; background:#f8faff; }
        .search-wrap .form-control:focus { border-color:#3b5bdb; box-shadow:0 0 0 3px rgba(59,91,219,0.09); background:#fff; }
        .search-wrap .btn-search { border-radius:0 12px 12px 0; background:#3b5bdb; border:none; color:#fff; padding:0.52rem 1rem; }
        .search-wrap .btn-search:hover { background:#2f4bbf; }
        .btn-add { background:linear-gradient(135deg,#1a7a3f,#27ae60); border:none; border-radius:12px; color:#fff; font-weight:600; font-size:0.87rem; padding:0.52rem 1.2rem; display:inline-flex; align-items:center; gap:7px; transition:opacity 0.18s,transform 0.18s; white-space:nowrap; }
        .btn-add:hover { opacity:0.88; transform:translateY(-1px); color:#fff; }

        .sidebar .nav-link.text-danger {
            transition: background 0.15s, color 0.15s;
        }
        .sidebar .nav-link.text-danger:hover {
            background: rgba(220, 38, 38, 0.12);
            color: #b91c1c;
        }

        /* TABLE FOOTER */
        .tbl-footer { padding:0.75rem 1.3rem; font-size:0.78rem; color:#94a3b8; border-top:1px solid rgba(0,0,0,0.05); background:#fafbff; }

        /* MODALS */
        .modal-content { border-radius:18px; border:none; box-shadow:0 24px 64px rgba(15,23,42,0.15); }
        .modal-header { padding:1.3rem 1.5rem 0.6rem; border-bottom:none; }
        .modal-body { padding:0.75rem 1.5rem 0.5rem; }
        .modal-footer { padding:0.6rem 1.5rem 1.3rem; border-top:none; }
        .modal-title { font-weight:700; font-size:0.98rem; color:#0f172a; }
        .icon-box { width:34px; height:34px; border-radius:9px; display:inline-flex; align-items:center; justify-content:center; flex-shrink:0; }
        .form-label { font-size:0.79rem; font-weight:600; color:#475569; margin-bottom:0.35rem; letter-spacing:0.02em; }
        .form-control { border-radius:10px; border:1.5px solid #e2e8f0; font-size:0.87rem; padding:0.52rem 0.9rem; }
        .form-control:focus { border-color:#3b5bdb; box-shadow:0 0 0 3px rgba(59,91,219,0.09); }
        .btn-cancel { border-radius:10px; border:1.5px solid #e2e8f0; background:#fff; color:#64748b; font-size:0.87rem; font-weight:600; padding:0.48rem 1.1rem; }
        .btn-cancel:hover { background:#f1f5f9; border-color:#cbd5e1; }
        .btn-confirm { border-radius:10px; border:none; font-size:0.87rem; font-weight:600; padding:0.48rem 1.3rem; }
    </style>
</head>
<body>

<div class="d-flex">
    <!-- Sidebar -->
    <div class="sidebar flex-column p-0" id="sidebar">
        <div class="sidebar-logo text-center">
            <img src="images/logo.png" alt="Inventory Logo">
            <h5 class="mt-2 text-white">Inventory System</h5>
        </div>
        <hr class="text-white">
        <ul class="nav flex-column">
            <li class="sidebar-title">Main</li>
            <li class="nav-item mb-2"><a class="nav-link" href="admin_dashboard.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a></li>
            <li class="sidebar-title">Management</li>
            <li class="nav-item mb-2"><a class="nav-link" href="products.php"><i class="bi bi-box-seam me-2"></i> Product Management</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="categories.php"><i class="bi bi-tags me-2"></i> Categories</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="sales.php"><i class="bi bi-cart-check me-2"></i> Sales</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="p_os.php"><i class="bi bi-receipt me-2"></i> Invoice</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="returns.php"><i class="bi bi-arrow-return-left me-2"></i> Returns</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="stock_in_batches.php"><i class="bi bi-box-arrow-down me-2"></i> Stock-In Records</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="stock_out_history.php"><i class="bi bi-box-arrow-up me-2"></i> Stock-Out Records</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="product_history.php"><i class="bi bi-clock-history me-2"></i> Product History</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="admin_seasonal_report.php"><i class="bi bi-calendar-range me-2"></i> Seasonal Analysis</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="reports.php"><i class="bi bi-bar-chart-line me-2"></i> Reports</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="supplier.php"><i class="bi bi-truck me-2"></i> Suppliers</a></li>
            <li class="sidebar-title">Users</li>
            <li class="nav-item mb-2"><a class="nav-link active" href="manageUser.php"><i class="bi bi-people me-2"></i> Manage Users</a></li>
            <li class="sidebar-title">Settings</li>
            <li class="nav-item mb-2"><a class="nav-link" href="settings.php"><i class="bi bi-gear me-2"></i> Settings</a></li>
            <li class="nav-item"><a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Content -->
    <div class="content flex-grow-1">
        <div class="container-fluid py-4">

            <!-- PAGE HEADING -->
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
                <div>
                    <div class="page-eyebrow">Administration</div>
                    <div class="page-heading">Staff Management</div>
                    <div class="page-subtext">Monitor staff accounts, login activity, and availability.</div>
                </div>
            </div>

            <!-- KPI CARDS -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="kpi-card shadow-sm text-white" style="background:linear-gradient(135deg,#1d4ed8,#60a5fa);">
                        <div class="kpi-label">Total Staff</div>
                        <div class="kpi-value"><?= number_format($total_staff) ?></div>
                        <div class="kpi-icon-wrap"><i class="bi bi-people-fill"></i></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="kpi-card shadow-sm text-white" style="background:linear-gradient(135deg,#15803d,#4ade80);">
                        <div class="kpi-label">Online Now</div>
                        <div class="kpi-value"><?= number_format($total_online) ?></div>
                        <div class="kpi-icon-wrap"><i class="bi bi-person-check-fill"></i></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="kpi-card shadow-sm text-white" style="background:linear-gradient(135deg,#334155,#94a3b8);">
                        <div class="kpi-label">Offline</div>
                        <div class="kpi-value"><?= number_format($total_offline) ?></div>
                        <div class="kpi-icon-wrap"><i class="bi bi-person-dash-fill"></i></div>
                    </div>
                </div>
            </div>

            <!-- STAFF TABLE CARD -->
            <div class="staff-card">
                <div class="staff-card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div>
                        <div style="font-weight:700;font-size:0.95rem;color:#0f172a;">
                            Staff Accounts
                            <span style="font-size:0.77rem;font-weight:500;color:#94a3b8;margin-left:8px;">
                                <?= count($users) ?> record<?= count($users) !== 1 ? 's' : '' ?>
                            </span>
                        </div>
                        <div style="font-size:0.79rem;color:#94a3b8;margin-top:2px;">All staff-role user accounts</div>
                    </div>

                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <form method="GET" action="manageUser.php">
                            <div class="input-group search-wrap">
                                <input type="text" name="q" class="form-control" placeholder="Search name or username…"
                                    value="<?= htmlspecialchars($search) ?>" style="min-width:230px;">
                                <button class="btn btn-search" type="submit"><i class="bi bi-search"></i></button>
                                <?php if ($search !== ''): ?>
                                    <a href="manageUser.php" class="btn btn-outline-secondary" style="border-radius:0 10px 10px 0;font-size:0.84rem;padding:0.52rem 0.9rem;">
                                        <i class="bi bi-x"></i> Reset
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                        <button type="button" class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                            <i class="bi bi-person-plus-fill"></i> Add Staff
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table staff-table">
                        <thead>
                            <tr>
                                <th style="width:44px;">#</th>
                                <th>Staff Member</th>
                                <th>Username</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th class="text-center" style="width:76px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($users)): ?>
                                <?php foreach ($users as $i => $user): ?>
                                    <?php
                                        $name  = $user['full_name'] ?: $user['username'];
                                        $color = avatarColor($name);
                                        $inits = initials($name);
                                    ?>
                                    <tr style="animation-delay:<?= $i * 0.04 ?>s;">
                                        <td style="color:#cbd5e1;font-size:0.79rem;font-weight:600;"><?= $i + 1 ?></td>
                                        <td>
                                            <div class="staff-name-cell">
                                                <div class="staff-avatar" style="background:<?= $color ?>;"><?= $inits ?></div>
                                                <div>
                                                    <div class="staff-name"><?= htmlspecialchars($name) ?></div>
                                                    <div class="staff-role-tag">Staff</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="username-tag">@<?= htmlspecialchars($user['username']) ?></span></td>
                                        <td>
                                            <?php if ((int)$user['is_logged_in'] === 1): ?>
                                                <span class="status-pill status-online"><span class="status-dot"></span> Online</span>
                                            <?php else: ?>
                                                <span class="status-pill status-offline"><span class="status-dot"></span> Offline</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="last-login">
                                                <?php if ($user['last_login']): ?>
                                                    <i class="bi bi-clock" style="font-size:0.74rem;margin-right:4px;"></i>
                                                    <?= date('M d, Y · h:i A', strtotime($user['last_login'])) ?>
                                                <?php else: ?>
                                                    <span style="color:#cbd5e1;">Never</span>
                                                <?php endif; ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex gap-2 justify-content-center">
                                                <button type="button" class="btn-edit edit-staff-btn"
                                                    data-id="<?= (int)$user['id'] ?>"
                                                    data-name="<?= htmlspecialchars($user['full_name'] ?: '') ?>"
                                                    data-username="<?= htmlspecialchars($user['username']) ?>"
                                                    title="Edit account">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <button type="button" class="btn-del delete-staff-btn"
                                                    data-id="<?= (int)$user['id'] ?>"
                                                    data-name="<?= htmlspecialchars($name) ?>"
                                                    title="Delete account">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align:center;padding:3.5rem 1rem;color:#94a3b8;">
                                        <i class="bi bi-people" style="font-size:2.2rem;display:block;margin-bottom:0.6rem;opacity:0.35;"></i>
                                        <div style="font-weight:600;margin-bottom:0.25rem;color:#64748b;">
                                            <?= $search !== '' ? 'No results found' : 'No staff accounts yet' ?>
                                        </div>
                                        <div style="font-size:0.82rem;">
                                            <?= $search !== '' ? 'Try a different search term.' : 'Click "Add Staff" to create one.' ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="tbl-footer">
                    Showing <strong><?= count($users) ?></strong> staff account<?= count($users) !== 1 ? 's' : '' ?>
                    &nbsp;·&nbsp; <span style="color:#16a34a;font-weight:600;"><?= $total_online ?> online</span>
                    &nbsp;·&nbsp; <?= $total_offline ?> offline
                    <?= $search !== '' ? ' &nbsp;·&nbsp; Filtered by: <strong>' . htmlspecialchars($search) . '</strong>' : '' ?>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- ADD STAFF MODAL -->
<div class="modal fade" id="addStaffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center gap-2">
                    <span class="icon-box" style="background:#dcfce7;">
                        <i class="bi bi-person-plus-fill text-success" style="font-size:0.9rem;"></i>
                    </span>
                    Add New Staff Account
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="addStaffForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="staffFullName" name="full_name" placeholder="e.g. Juan dela Cruz" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="staffUsername" name="username" placeholder="e.g. jdelacruz" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="staffPassword" name="password" placeholder="Min. 6 characters" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="staffConfirmPassword" name="confirm_password" placeholder="Repeat password" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-confirm" style="background:linear-gradient(135deg,#15803d,#22c55e);color:#fff;">
                        <i class="bi bi-check-circle me-1"></i> Create Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT STAFF MODAL -->
<div class="modal fade" id="editStaffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center gap-2">
                    <span class="icon-box" style="background:#dbeafe;">
                        <i class="bi bi-pencil-square text-primary" style="font-size:0.9rem;"></i>
                    </span>
                    Edit Staff Account
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editStaffForm">
                <input type="hidden" name="staff_id" id="editStaffId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editStaffFullName" name="edit_full_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editStaffUsername" name="edit_username" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">New Password (leave blank to keep current)</label>
                        <input type="password" class="form-control" id="editStaffPassword" name="edit_password" placeholder="Enter new password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-confirm" style="background:linear-gradient(135deg,#2563eb,#3b82f6);color:#fff;">
                        <i class="bi bi-check-circle me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- CONFIRM DELETE MODAL -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center gap-2">
                    <span class="icon-box" style="background:#fee2e2;">
                        <i class="bi bi-exclamation-triangle-fill text-danger" style="font-size:0.88rem;"></i>
                    </span>
                    Delete Account
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="confirmDeleteMessage" style="font-size:0.88rem;color:#475569;line-height:1.6;"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-confirm" id="confirmDeleteProceed" style="background:#dc2626;color:#fff;">
                    <i class="bi bi-trash3 me-1"></i> Delete
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MESSAGE MODAL -->
<div class="modal fade" id="messageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center gap-2">
                    <span id="messageModalIcon"></span>
                    <span id="messageModalTitle">Message</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="messageModalBody" style="font-size:0.88rem;color:#475569;"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-confirm" id="messageModalBtn" data-bs-dismiss="modal" style="background:#3b5bdb;color:#fff;">OK</button>
            </div>
        </div>
    </div>
</div>

<script>
const iconBoxHTML = (bg, cls) =>
    `<span class="icon-box" style="background:${bg};"><i class="bi ${cls}" style="font-size:0.9rem;"></i></span>`;

function showMessageModal(title, message, type = 'info', callback = null) {
    const configs = {
        success: { bg:'#dcfce7', cls:'bi-check-circle-fill text-success',  btn:'#22c55e' },
        error:   { bg:'#fee2e2', cls:'bi-exclamation-circle-fill text-danger', btn:'#dc2626' },
        warning: { bg:'#fef9c3', cls:'bi-exclamation-triangle-fill text-warning', btn:'#f59e0b' },
        info:    { bg:'#dbeafe', cls:'bi-info-circle-fill text-primary', btn:'#3b5bdb' },
    };
    const c = configs[type] || configs.info;
    document.getElementById('messageModalIcon').innerHTML = iconBoxHTML(c.bg, c.cls);
    document.getElementById('messageModalTitle').textContent = title;
    document.getElementById('messageModalBody').textContent = message;
    document.getElementById('messageModalBtn').style.background = c.btn;
    if (callback) document.getElementById('messageModal').addEventListener('hidden.bs.modal', callback, { once: true });
    new bootstrap.Modal(document.getElementById('messageModal')).show();
}

// ADD STAFF
document.getElementById('addStaffForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const fn = document.getElementById('staffFullName').value.trim();
    const un = document.getElementById('staffUsername').value.trim();
    const pw = document.getElementById('staffPassword').value;
    const cp = document.getElementById('staffConfirmPassword').value;

    if (!fn || !un || !pw || !cp) { showMessageModal('Validation Error', 'Please fill in all required fields.', 'warning'); return; }
    if (pw !== cp)  { showMessageModal('Validation Error', 'Passwords do not match.', 'warning'); return; }
    if (pw.length < 6) { showMessageModal('Validation Error', 'Password must be at least 6 characters.', 'warning'); return; }

    const fd = new FormData(this);
    fd.append('action', 'add_staff');
    fetch('manageUser.php', { method:'POST', body:fd })
        .then(r => r.text())
        .then(data => {
            if (data.includes('success')) {
                showMessageModal('Account Created', 'Staff account created successfully!', 'success', function () {
                    document.getElementById('addStaffForm').reset();
                    bootstrap.Modal.getInstance(document.getElementById('addStaffModal')).hide();
                    location.reload();
                });
            } else {
                showMessageModal('Error', data.replace('error: ', ''), 'error');
            }
        })
        .catch(err => showMessageModal('Error', err.message, 'error'));
});

// EDIT STAFF
const editModal = new bootstrap.Modal(document.getElementById('editStaffModal'));
document.querySelectorAll('.edit-staff-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        document.getElementById('editStaffId').value = this.getAttribute('data-id');
        document.getElementById('editStaffFullName').value = this.getAttribute('data-name');
        document.getElementById('editStaffUsername').value = this.getAttribute('data-username');
        document.getElementById('editStaffPassword').value = '';
        editModal.show();
    });
});

document.getElementById('editStaffForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('action', 'edit_staff');
    fetch('manageUser.php', { method:'POST', body:fd })
        .then(r => r.text())
        .then(data => {
            if (data.includes('success')) {
                showMessageModal('Updated', 'Staff account updated successfully!', 'success', () => location.reload());
            } else {
                showMessageModal('Error', data.replace('error: ', ''), 'error');
            }
        })
        .catch(err => showMessageModal('Error', err.message, 'error'));
});

// DELETE STAFF
let pendingDeleteId = null;
const delModal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));

document.querySelectorAll('.delete-staff-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        pendingDeleteId = this.getAttribute('data-id');
        document.getElementById('confirmDeleteMessage').textContent =
            'You are about to permanently delete the account of "' + this.getAttribute('data-name') + '". This cannot be undone.';
        delModal.show();
    });
});

document.getElementById('confirmDeleteProceed').addEventListener('click', function () {
    if (!pendingDeleteId) return;
    const fd = new FormData();
    fd.append('action', 'delete_staff');
    fd.append('staff_id', pendingDeleteId);
    fetch('manageUser.php', { method:'POST', body:fd })
        .then(r => r.text())
        .then(data => {
            delModal.hide();
            if (data.includes('success')) {
                showMessageModal('Deleted', 'Staff account has been removed.', 'success', () => location.reload());
            } else {
                showMessageModal('Error', data.replace('error: ', ''), 'error');
            }
        })
        .catch(err => { delModal.hide(); showMessageModal('Error', err.message, 'error'); });
});

// SIDEBAR
const st = document.getElementById('sidebarToggle');
const sb = document.getElementById('sidebar');
if (st && sb) st.addEventListener('click', () => sb.classList.toggle('show'));
</script>
</body>
</html>
</html>