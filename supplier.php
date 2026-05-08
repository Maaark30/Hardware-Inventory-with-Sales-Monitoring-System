<?php
include 'project.php';
session_start();

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// --- ADD SUPPLIER ---
if (isset($_POST['add_supplier'])) {
    $supplier_name  = trim($_POST['supplier_name']);
    $contact_person = trim($_POST['contact_person']);
    $contact_no     = trim($_POST['contact_no']);
    $email          = trim($_POST['email']);
    $address        = trim($_POST['address']);
    $notes          = trim($_POST['notes']);

    $check_stmt = $conn->prepare("SELECT COUNT(*) FROM suppliers WHERE supplier_name=? AND contact_person=? AND contact_no=? AND email=? AND address=? AND notes=?");
    $check_stmt->bind_param("ssssss", $supplier_name, $contact_person, $contact_no, $email, $address, $notes);
    $check_stmt->execute();
    $check_stmt->bind_result($count);
    $check_stmt->fetch();
    $check_stmt->close();

    if ($count > 0) {
        $_SESSION['warning'] = "Supplier with the same details already exists.";
    } elseif (!empty($supplier_name)) {
        $stmt = $conn->prepare("INSERT INTO suppliers (supplier_name, contact_person, contact_no, email, address, notes) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param("ssssss", $supplier_name, $contact_person, $contact_no, $email, $address, $notes);
        $ok = $stmt->execute();
        $_SESSION[$ok ? 'success' : 'error'] = $ok ? "Supplier added successfully." : "Failed to add supplier.";
        $stmt->close();
    } else {
        $_SESSION['error'] = "Supplier name cannot be empty.";
    }
    header("Location: supplier.php"); exit();
}

// --- UPDATE SUPPLIER ---
if (isset($_POST['update_supplier'])) {
    $supplier_id    = intval($_POST['edit_supplier_id']);
    $supplier_name  = trim($_POST['edit_supplier_name']);
    $contact_person = trim($_POST['edit_contact_person']);
    $contact_no     = trim($_POST['edit_contact_no']);
    $email          = trim($_POST['edit_email']);
    $address        = trim($_POST['edit_address']);
    $notes          = trim($_POST['edit_notes']);

    if ($supplier_id > 0 && !empty($supplier_name)) {
        $stmt = $conn->prepare("UPDATE suppliers SET supplier_name=?,contact_person=?,contact_no=?,email=?,address=?,notes=? WHERE supplier_id=?");
        $stmt->bind_param("ssssssi", $supplier_name, $contact_person, $contact_no, $email, $address, $notes, $supplier_id);
        $ok = $stmt->execute();
        $_SESSION[$ok ? 'success' : 'error'] = $ok ? "Supplier updated successfully." : "Failed to update.";
        $stmt->close();
    } else {
        $_SESSION['error'] = "Invalid supplier data.";
    }
    header("Location: supplier.php"); exit();
}

// --- DELETE SUPPLIER ---
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);

    // Block delete if supplier has stock history or batch records
    $used_stmt = $conn->prepare(
        "SELECT COUNT(*) FROM (
            SELECT supplier_id FROM stock_history WHERE supplier_id=?
            UNION ALL
            SELECT supplier_id FROM stock_in_batches WHERE supplier_id=?
         ) AS combined"
    );
    $used_stmt->bind_param("ii", $id, $id);
    $used_stmt->execute();
    $used_stmt->bind_result($used_count);
    $used_stmt->fetch();
    $used_stmt->close();

    if ($used_count > 0) {
        $_SESSION['error'] = "Cannot delete this supplier — they have existing stock records linked to them.";
    } else {
        $stmt = $conn->prepare("DELETE FROM suppliers WHERE supplier_id=?");
        $stmt->bind_param("i", $id);
        $ok = $stmt->execute();
        $_SESSION[$ok ? 'success' : 'error'] = $ok ? "Supplier deleted." : "Failed to delete.";
        $stmt->close();
    }
    header("Location: supplier.php"); exit();
}

$result          = $conn->query("SELECT * FROM suppliers ORDER BY created_at DESC");
$lowest_cost_sql = "
    SELECT
        p.product_name,
        p.brand,
        p.variation,
        s.supplier_name,
        t.min_price,
        COALESCE(c.category_name, 'Uncategorized') AS category_name
    FROM (
        SELECT product_id, MIN(supplier_price) AS min_price
        FROM stock_history
        WHERE supplier_price > 0
        GROUP BY product_id
    ) t
    JOIN stock_history sh ON t.product_id = sh.product_id AND t.min_price = sh.supplier_price
    JOIN products p ON sh.product_id = p.product_id
    JOIN suppliers s ON sh.supplier_id = s.supplier_id
    LEFT JOIN categories c ON p.category_id = c.category_id
    GROUP BY p.product_id
    ORDER BY category_name ASC, p.product_name ASC
";
$lowest_cost_result = $conn->query($lowest_cost_sql);

// Group best-price rows by category for the modal
$price_by_category = [];
if ($lowest_cost_result && $lowest_cost_result->num_rows > 0) {
    while ($lc = $lowest_cost_result->fetch_assoc()) {
        $price_by_category[$lc['category_name']][] = $lc;
    }
}

// Pre-compute supplier IDs that have stock records (to block delete in JS)
$linked_ids_result = $conn->query(
    "SELECT DISTINCT supplier_id FROM stock_history
     UNION
     SELECT DISTINCT supplier_id FROM stock_in_batches"
);
$linked_supplier_ids = [];
if ($linked_ids_result) {
    while ($lid = $linked_ids_result->fetch_assoc()) {
        $linked_supplier_ids[] = (int)$lid['supplier_id'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
        <link rel="icon" type="image/x-icon" href="favicon.ico">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Suppliers — Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

<link rel="stylesheet" href="css/admin1.css">

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

/* ── Toast Alerts ─────────────────────────────────── */
.toast-stack {
  position: fixed;
  top: 20px;
  right: 20px;
  z-index: 9999;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.toast-pill {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 18px;
  border-radius: 40px;
  font-size: 13.5px;
  font-weight: 500;
  box-shadow: 0 4px 20px rgba(0,0,0,0.1);
  animation: slideIn 0.35s ease;
  cursor: pointer;
}

.toast-pill.success { background: #16a34a; color: #fff; }
.toast-pill.warning { background: #d97706; color: #fff; }
.toast-pill.error   { background: #dc2626; color: #fff; }
.toast-pill i       { font-size: 15px; }

@keyframes slideIn {
  from { opacity: 0; transform: translateX(30px); }
  to   { opacity: 1; transform: translateX(0); }
}

/* ── Page header ──────────────────────────────────── */
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 28px;
  flex-wrap: wrap;
  gap: 12px;
}

.page-header-left h4 {
  font-size: 22px;
  font-weight: 800;
  color: #1a1d23;
  margin: 0;
  letter-spacing: -0.3px;
}

.page-header-left p {
  font-size: 13px;
  color: #6b7280;
  margin: 2px 0 0;
}

.page-header-icon {
  width: 42px;
  height: 42px;
  border-radius: 14px;
  background: rgba(37,99,235,0.12);
  color: #2563eb;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  margin-right: 0.85rem;
  flex-shrink: 0;
}

.header-actions {
  display: flex;
  gap: 10px;
}

/* ── Stat cards ───────────────────────────────────── */
.stat-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 14px;
  margin-bottom: 24px;
}

.stat-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  padding: 18px 20px;
  display: flex;
  align-items: center;
  gap: 14px;
}

.stat-icon {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  flex-shrink: 0;
}

.stat-icon.blue   { background: #eff6ff; color: #2563eb; }
.stat-icon.green  { background: #f0fdf4; color: #16a34a; }
.stat-icon.amber  { background: #fffbeb; color: #d97706; }

.stat-label { font-size: 12px; color: #6b7280; margin-bottom: 2px; }
.stat-value { font-size: 22px; font-weight: 600; color: #111827; line-height: 1; }

/* ── Toolbar ──────────────────────────────────────── */
.toolbar {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}

.search-wrap {
  position: relative;
  flex: 1;
  min-width: 200px;
  max-width: 340px;
}

.search-wrap i {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #9ca3af;
  font-size: 14px;
}

.search-wrap input {
  width: 100%;
  padding: 8px 12px 8px 36px;
  border: 1px solid #e5e7eb;
  border-radius: 9px;
  font-size: 13.5px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  background: #fff;
  color: #111827;
  outline: none;
  transition: border 0.2s;
}

.search-wrap input:focus { border-color: #2563eb; }

/* ── Table card ───────────────────────────────────── */
.table-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  overflow: hidden;
}

.table-card table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13.5px;
}

.table-card thead tr {
  background: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
}

.table-card thead th {
  padding: 13px 16px;
  font-size: 11.5px;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #6b7280;
  white-space: nowrap;
}

.table-card tbody tr {
  border-bottom: 1px solid #f3f4f6;
  transition: background 0.15s;
}

.table-card tbody tr:last-child { border-bottom: none; }
.table-card tbody tr:hover { background: #f9fafb; }

.table-card tbody td {
  padding: 13px 16px;
  color: #374151;
  vertical-align: middle;
}

.supplier-name-cell {
  font-weight: 600;
  color: #111827;
  display: flex;
  align-items: center;
  gap: 10px;
}

.supplier-avatar {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: #eff6ff;
  color: #2563eb;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 700;
  flex-shrink: 0;
}

.truncate-cell {
  max-width: 160px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.date-chip {
  font-family: var(--mono);
  font-size: 12px;
  color: #6b7280;
}

/* ── Action buttons ───────────────────────────────── */
.action-wrap { display: flex; gap: 6px; }

.btn-icon {
  width: 32px;
  height: 32px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  border: 1px solid transparent;
  background: #fff;
  cursor: pointer;
  transition: background 0.15s, border-color 0.15s, color 0.15s;
  color: #6b7280;
}

.btn-icon.edit {
  background: #eff6ff;
  border-color: #bfdbfe;
  color: #2563eb;
}

.btn-icon.del {
  background: #fef2f2;
  border-color: #fecaca;
  color: #dc2626;
}

.btn-icon:hover {
  filter: brightness(0.96);
}

/* ── Empty state ──────────────────────────────────── */
.empty-state {
  text-align: center;
  padding: 60px 20px;
  color: #9ca3af;
}

.empty-state i { font-size: 40px; margin-bottom: 12px; display: block; }
.empty-state p { font-size: 14px; margin: 0; }

/* ── Buttons (header) ─────────────────────────────── */
.btn-primary-custom {
  background: #2563eb;
  color: #fff;
  border: none;
  border-radius: 9px;
  padding: 9px 18px;
  font-size: 13.5px;
  font-weight: 500;
  font-family: 'Plus Jakarta Sans', sans-serif;
  display: flex;
  align-items: center;
  gap: 7px;
  cursor: pointer;
  transition: background 0.2s, transform 0.15s;
  white-space: nowrap;
}

.btn-primary-custom:hover { background: #1d4ed8; transform: translateY(-1px); }

.btn-secondary-custom {
  background: #fff;
  color: #374151;
  border: 1px solid #e5e7eb;
  border-radius: 9px;
  padding: 9px 18px;
  font-size: 13.5px;
  font-weight: 500;
  font-family: 'Plus Jakarta Sans', sans-serif;
  display: flex;
  align-items: center;
  gap: 7px;
  cursor: pointer;
  transition: background 0.2s, border-color 0.2s;
  white-space: nowrap;
}

.btn-secondary-custom:hover { background: #f9fafb; border-color: #d1d5db; }

/* ── Modals ───────────────────────────────────────── */
.modal-content {
  border: none;
  border-radius: 16px;
  overflow: hidden;
  font-family: 'Plus Jakarta Sans', sans-serif;
}

.modal-header {
  padding: 20px 24px 16px;
  border-bottom: 1px solid #f3f4f6;
}

.modal-header .modal-title {
  font-size: 16px;
  font-weight: 600;
  color: #111827;
}

.modal-header .btn-close { opacity: 0.4; }
.modal-header .btn-close:hover { opacity: 0.7; }

.modal-body { padding: 20px 24px; }
.modal-footer { padding: 16px 24px 20px; border-top: 1px solid #f3f4f6; gap: 8px; }

.modal-icon-header {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  margin-right: 12px;
  flex-shrink: 0;
}

.modal-icon-header.blue  { background: #eff6ff; color: #2563eb; }
.modal-icon-header.green { background: #f0fdf4; color: #16a34a; }
.modal-icon-header.red   { background: #fef2f2; color: #dc2626; }
.modal-icon-header.teal  { background: #f0fdfa; color: #0d9488; }

.modal-header-inner {
  display: flex;
  align-items: center;
}

/* Form elements inside modals */
.form-label-sm {
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #6b7280;
  margin-bottom: 6px;
  display: block;
}

.form-input {
  width: 100%;
  padding: 9px 13px;
  border: 1px solid #e5e7eb;
  border-radius: 9px;
  font-size: 14px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  color: #111827;
  outline: none;
  transition: border-color 0.2s, box-shadow 0.2s;
  background: #fff;
}

.form-input:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
}

textarea.form-input { resize: vertical; min-height: 80px; }

.btn-modal-primary {
  background: #2563eb;
  color: #fff;
  border: none;
  border-radius: 9px;
  padding: 9px 22px;
  font-size: 13.5px;
  font-weight: 500;
  font-family: 'Plus Jakarta Sans', sans-serif;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 7px;
  transition: background 0.2s;
}

.btn-modal-primary:hover { background: #1d4ed8; }
.btn-modal-primary.green { background: #16a34a; }
.btn-modal-primary.green:hover { background: #15803d; }
.btn-modal-primary.red   { background: #dc2626; }
.btn-modal-primary.red:hover   { background: #b91c1c; }

.btn-modal-cancel {
  background: #fff;
  color: #374151;
  border: 1px solid #e5e7eb;
  border-radius: 9px;
  padding: 9px 22px;
  font-size: 13.5px;
  font-weight: 500;
  font-family: 'Plus Jakarta Sans', sans-serif;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-modal-cancel:hover { background: #f9fafb; }

/* Best Price Modal table */
.price-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
.price-table thead tr { background: #f9fafb; border-bottom: 1px solid #e5e7eb; }
.price-table thead th { padding: 11px 16px; font-size: 11px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: #6b7280; }
.price-table tbody tr { border-bottom: 1px solid #f3f4f6; }
.price-table tbody tr:hover { background: #f9fafb; }
.price-table tbody td { padding: 11px 16px; vertical-align: middle; }

.badge-supplier {
  background: #f0fdf4;
  color: #15803d;
  border: 1px solid #bbf7d0;
  border-radius: 20px;
  padding: 3px 10px;
  font-size: 12px;
  font-weight: 500;
}

.price-chip {
  font-family: var(--mono);
  font-size: 13px;
  font-weight: 500;
  color: #2563eb;
}

/* Delete warning box */
.delete-warning-box {
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 10px;
  padding: 14px 16px;
  display: flex;
  gap: 12px;
  align-items: flex-start;
  margin-bottom: 14px;
}

.delete-warning-box i { color: #dc2626; font-size: 18px; flex-shrink: 0; margin-top: 1px; }
.delete-warning-box p { margin: 0; font-size: 13.5px; color: #7f1d1d; line-height: 1.5; }

.delete-supplier-name {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 14px;
  font-weight: 600;
  color: #111827;
}

/* ── Contact number hint ──────────────────────────── */
.input-hint {
  font-size: 11.5px;
  color: #9ca3af;
  margin-top: 4px;
}
</style>
</head>
<body>

<!-- ── Toast Alerts ── -->
<div class="toast-stack" id="toastStack">
  <?php if (!empty($_SESSION['success'])): ?>
    <div class="toast-pill success" onclick="this.remove()">
      <i class="bi bi-check-circle-fill"></i>
      <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
    </div>
  <?php endif; ?>
  <?php if (!empty($_SESSION['warning'])): ?>
    <div class="toast-pill warning" onclick="this.remove()">
      <i class="bi bi-exclamation-triangle-fill"></i>
      <?= htmlspecialchars($_SESSION['warning']); unset($_SESSION['warning']); ?>
    </div>
  <?php endif; ?>
  <?php if (!empty($_SESSION['error'])): ?>
    <div class="toast-pill error" onclick="this.remove()">
      <i class="bi bi-x-circle-fill"></i>
      <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
    </div>
  <?php endif; ?>
</div>

<div class="d-flex">

  <!-- ── Sidebar ── -->
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
      <li class="nav-item mb-2"><a class="nav-link active" href="supplier.php"><i class="bi bi-truck me-2"></i> Suppliers</a></li>
      <li class="sidebar-title">Users</li>
      <li class="nav-item mb-2"><a class="nav-link" href="manageUser.php"><i class="bi bi-people me-2"></i> Manage Users</a></li>
      <li class="sidebar-title">Settings</li>
      <li class="nav-item mb-2"><a class="nav-link" href="settings.php"><i class="bi bi-gear me-2"></i> Settings</a></li>
      <li class="nav-item"><a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
    </ul>
  </div>

  <!-- ── Main Content ── -->
  <div class="content flex-grow-1 p-4">

    <!-- Page Header -->
    <div class="page-header">
      <div style="display:flex; align-items:center;">
        <div class="page-header-icon">
          <i class="bi bi-truck"></i>
        </div>
        <div class="page-header-left">
          <h4>Supplier Management</h4>
          <p>Track and manage your supply chain partners</p>
        </div>
      </div>
      <div class="header-actions">
        <button class="btn-secondary-custom" data-bs-toggle="modal" data-bs-target="#lowestCostModal">
          <i class="bi bi-tags-fill"></i> Best Price Guide
        </button>
        <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
          <i class="bi bi-plus-lg"></i> Add Supplier
        </button>
      </div>
    </div>

    <!-- Stat Cards -->
    <?php
      $total_suppliers = $result->num_rows;
      $result->data_seek(0);
    ?>
    <div class="stat-row">
      <div class="stat-card">
        <div class="stat-icon blue"><i class="bi bi-truck"></i></div>
        <div>
          <div class="stat-label">Total Suppliers</div>
          <div class="stat-value"><?= $total_suppliers ?></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon green"><i class="bi bi-tags-fill"></i></div>
        <div>
          <div class="stat-label">Price Records</div>
          <div class="stat-value"><?= $lowest_cost_result ? $lowest_cost_result->num_rows : 0 ?></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon amber"><i class="bi bi-calendar-check"></i></div>
        <div>
          <div class="stat-label">Last Updated</div>
          <div class="stat-value" style="font-size:14px;"><?= date('M d, Y') ?></div>
        </div>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
      <div class="search-wrap">
        <i class="bi bi-search"></i>
        <input type="text" id="searchInput" placeholder="Search suppliers…">
      </div>
    </div>

    <!-- Table Card -->
    <div class="table-card">
      <div style="overflow-x: auto;">
        <table id="supplierTable">
          <thead>
            <tr>
              <th style="text-align:left;">Supplier</th>
              <th>Contact Person</th>
              <th>Contact No.</th>
              <th>Email</th>
              <th>Address</th>
              <th>Notes</th>
              <th>Date Added</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($result->num_rows > 0):
              $result->data_seek(0);
              while ($row = $result->fetch_assoc()):
                $initials = strtoupper(substr($row['supplier_name'], 0, 2));
            ?>
            <tr
              data-id="<?= $row['supplier_id'] ?>"
              data-name="<?= htmlspecialchars($row['supplier_name']) ?>"
              data-contact-person="<?= htmlspecialchars($row['contact_person']) ?>"
              data-contact-no="<?= htmlspecialchars($row['contact_no']) ?>"
              data-email="<?= htmlspecialchars($row['email']) ?>"
              data-address="<?= htmlspecialchars($row['address']) ?>"
              data-notes="<?= htmlspecialchars($row['notes']) ?>"
            >
              <td>
                <div class="supplier-name-cell">
                  <div class="supplier-avatar"><?= $initials ?></div>
                  <?= htmlspecialchars($row['supplier_name']) ?>
                </div>
              </td>
              <td><?= htmlspecialchars($row['contact_person']) ?: '—' ?></td>
              <td><?= htmlspecialchars($row['contact_no']) ?: '—' ?></td>
              <td class="truncate-cell"><?= htmlspecialchars($row['email']) ?: '—' ?></td>
              <td class="truncate-cell"><?= htmlspecialchars($row['address']) ?: '—' ?></td>
              <td class="truncate-cell"><?= htmlspecialchars($row['notes']) ?: '—' ?></td>
              <td><span class="date-chip"><?= date('M d, Y', strtotime($row['created_at'])) ?></span></td>
              <td>
                <div class="action-wrap">
                  <button class="btn-icon edit edit-btn"
                    data-bs-toggle="modal"
                    data-bs-target="#editSupplierModal"
                    title="Edit">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <button class="btn-icon del delete-btn"
                    data-supplier-id="<?= $row['supplier_id'] ?>"
                    data-supplier-name="<?= htmlspecialchars($row['supplier_name']) ?>"
                    title="Delete">
                    <i class="bi bi-trash"></i>
                  </button>
                </div>
              </td>
            </tr>
            <?php endwhile; else: ?>
            <tr>
              <td colspan="8">
                <div class="empty-state">
                  <i class="bi bi-truck"></i>
                  <p>No suppliers found. Add your first supplier to get started.</p>
                </div>
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div><!-- /content -->
</div><!-- /d-flex -->


<!-- ── Add Supplier Modal ── -->
<div class="modal fade" id="addSupplierModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header">
          <div class="modal-header-inner">
            <div class="modal-icon-header green"><i class="bi bi-plus-circle-fill"></i></div>
            <h5 class="modal-title">Add New Supplier</h5>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label-sm">Supplier Name <span style="color:#dc2626;">*</span></label>
              <input type="text" name="supplier_name" class="form-input" placeholder="e.g. ABC Supplies Co." required>
            </div>
            <div class="col-md-6">
              <label class="form-label-sm">Contact Person</label>
              <input type="text" name="contact_person" class="form-input" placeholder="e.g. Juan dela Cruz">
            </div>
            <div class="col-md-6">
              <label class="form-label-sm">Contact No.</label>
              <input
                type="tel"
                name="contact_no"
                class="form-input"
                placeholder="e.g. 09171234567"
                inputmode="numeric"
                pattern="[0-9]*"
                maxlength="15"
                oninput="this.value=this.value.replace(/[^0-9]/g,'')">
              <div class="input-hint"><i class="bi bi-info-circle me-1"></i>Numbers only</div>
            </div>
            <div class="col-md-6">
              <label class="form-label-sm">Email</label>
              <input type="email" name="email" class="form-input" placeholder="e.g. supplier@email.com">
            </div>
            <div class="col-12">
              <label class="form-label-sm">Address</label>
              <input type="text" name="address" class="form-input" placeholder="Street, City, Province">
            </div>
            <div class="col-12">
              <label class="form-label-sm">Notes</label>
              <textarea name="notes" class="form-input" placeholder="Any additional notes…"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="add_supplier" class="btn-modal-primary green">
            <i class="bi bi-check-lg"></i> Save Supplier
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── Edit Supplier Modal ── -->
<div class="modal fade" id="editSupplierModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header">
          <div class="modal-header-inner">
            <div class="modal-icon-header blue"><i class="bi bi-pencil-fill"></i></div>
            <h5 class="modal-title">Edit Supplier</h5>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="edit_supplier_id" id="edit_supplier_id">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label-sm">Supplier Name <span style="color:#dc2626;">*</span></label>
              <input type="text" name="edit_supplier_name" id="edit_supplier_name" class="form-input" required>
            </div>
            <div class="col-md-6">
              <label class="form-label-sm">Contact Person</label>
              <input type="text" name="edit_contact_person" id="edit_contact_person" class="form-input">
            </div>
            <div class="col-md-6">
              <label class="form-label-sm">Contact No.</label>
              <input
                type="tel"
                name="edit_contact_no"
                id="edit_contact_no"
                class="form-input"
                inputmode="numeric"
                pattern="[0-9]*"
                maxlength="15"
                oninput="this.value=this.value.replace(/[^0-9]/g,'')">
              <div class="input-hint"><i class="bi bi-info-circle me-1"></i>Numbers only</div>
            </div>
            <div class="col-md-6">
              <label class="form-label-sm">Email</label>
              <input type="email" name="edit_email" id="edit_email" class="form-input">
            </div>
            <div class="col-12">
              <label class="form-label-sm">Address</label>
              <input type="text" name="edit_address" id="edit_address" class="form-input">
            </div>
            <div class="col-12">
              <label class="form-label-sm">Notes</label>
              <textarea name="edit_notes" id="edit_notes" class="form-input"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="update_supplier" class="btn-modal-primary">
            <i class="bi bi-save"></i> Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── Best Price Modal ── -->
<div class="modal fade" id="lowestCostModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-header-inner">
          <div class="modal-icon-header teal"><i class="bi bi-tags-fill"></i></div>
          <div>
            <h5 class="modal-title" style="margin:0;">Best Price Guide</h5>
            <div style="font-size:12px;color:#6b7280;margin-top:2px;">Lowest recorded price per product, grouped by category</div>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <?php if (!empty($price_by_category)): ?>
      <!-- Search bar -->
      <div style="padding:14px 20px;border-bottom:1px solid #f3f4f6;background:#fafafa;">
        <div style="position:relative;">
          <i class="bi bi-search" style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:13px;"></i>
          <input type="text" id="priceGuideSearch" placeholder="Search product or supplier…"
            style="width:100%;padding:8px 12px 8px 34px;border:1px solid #e5e7eb;border-radius:9px;font-size:13.5px;font-family:'Plus Jakarta Sans',sans-serif;outline:none;background:#fff;color:#111827;">
        </div>
      </div>

      <!-- Category accordion -->
      <div class="modal-body" style="padding:16px 20px;" id="priceGuideBody">
        <div class="accordion" id="priceAccordion">
          <?php $catIndex = 0; foreach ($price_by_category as $catName => $items): ?>
          <div class="accordion-item price-cat-block"
               style="border:1px solid #e5e7eb;border-radius:12px;margin-bottom:10px;overflow:hidden;">

            <h2 class="accordion-header">
              <button class="accordion-button <?= $catIndex > 0 ? 'collapsed' : '' ?>"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#pricecat<?= $catIndex ?>"
                aria-expanded="<?= $catIndex === 0 ? 'true' : 'false' ?>"
                style="background:#f9fafb;font-size:13.5px;font-weight:600;color:#111827;padding:12px 16px;box-shadow:none;gap:10px;">
                <span style="display:flex;align-items:center;gap:10px;flex:1;">
                  <span style="width:30px;height:30px;border-radius:8px;background:#f0fdfa;color:#0d9488;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;">
                    <i class="bi bi-folder2"></i>
                  </span>
                  <?= htmlspecialchars($catName) ?>
                </span>
                <span style="background:#e0f2fe;color:#0369a1;border-radius:20px;padding:2px 10px;font-size:11.5px;font-weight:600;white-space:nowrap;margin-right:6px;">
                  <?= count($items) ?> product<?= count($items) !== 1 ? 's' : '' ?>
                </span>
              </button>
            </h2>

            <div id="pricecat<?= $catIndex ?>" class="accordion-collapse collapse <?= $catIndex === 0 ? 'show' : '' ?>">
              <div class="accordion-body" style="padding:0;">
                <table class="price-table">
                  <thead>
                    <tr style="background:#f9fafb;">
                      <th style="text-align:left;padding:10px 16px;">Product</th>
                      <th style="text-align:left;padding:10px 16px;">Brand</th>
                      <th style="text-align:left;padding:10px 16px;">Variation</th>
                      <th style="text-align:center;padding:10px 16px;">Cheapest Supplier</th>
                      <th style="text-align:right;padding:10px 16px;">Lowest Price</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($items as $lc): ?>
                    <tr class="price-row">
                      <td style="padding:11px 16px;font-weight:500;color:#111827;">
                        <span class="price-product-name"><?= htmlspecialchars($lc['product_name']) ?></span>
                      </td>
                      <td style="padding:11px 16px;color:#4b5563;">
                        <span class="price-brand-name"><?= htmlspecialchars($lc['brand'] ?: '—') ?></span>
                      </td>
                      <td style="padding:11px 16px;color:#4b5563;">
                        <span class="price-variation-name"><?= htmlspecialchars($lc['variation'] ?: '—') ?></span>
                      </td>
                      <td style="text-align:center;padding:11px 16px;">
                        <span class="badge-supplier price-supplier-name"><?= htmlspecialchars($lc['supplier_name']) ?></span>
                      </td>
                      <td style="text-align:right;padding:11px 16px;">
                        <span class="price-chip">₱<?= number_format($lc['min_price'], 2) ?></span>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <?php $catIndex++; endforeach; ?>
        </div>

        <!-- No results state (shown by JS search) -->
        <div id="priceGuideEmpty" style="display:none;text-align:center;padding:40px 20px;color:#9ca3af;">
          <i class="bi bi-search" style="font-size:32px;display:block;margin-bottom:10px;"></i>
          <p style="margin:0;font-size:14px;">No products match your search.</p>
        </div>
      </div>
      <?php else: ?>
      <div class="modal-body" style="text-align:center;padding:60px 20px;color:#9ca3af;">
        <i class="bi bi-tags" style="font-size:36px;display:block;margin-bottom:12px;"></i>
        <p style="margin:0;font-size:14px;">No stock history data available yet.</p>
      </div>
      <?php endif; ?>

      <div class="modal-footer">
        <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- ── Confirm Delete Modal ── -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-header-inner">
          <div class="modal-icon-header red"><i class="bi bi-trash-fill"></i></div>
          <h5 class="modal-title">Delete Supplier</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="delete-warning-box">
          <i class="bi bi-exclamation-triangle-fill"></i>
          <p>This action is permanent and cannot be undone. The supplier and all associated data will be removed.</p>
        </div>
        <label class="form-label-sm" style="margin-bottom:6px;">Supplier to be deleted</label>
        <div class="delete-supplier-name" id="deleteSupplierName">—</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="confirmDeleteButton" class="btn-modal-primary red">
          <i class="bi bi-trash"></i> Delete
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ── Cannot Delete Modal ── -->
<div class="modal fade" id="cannotDeleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:460px;">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-header-inner">
          <div class="modal-icon-header" style="background:#fff7ed;color:#c2410c;">
            <i class="bi bi-shield-exclamation"></i>
          </div>
          <h5 class="modal-title">Cannot Delete Supplier</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:12px;padding:16px 18px;display:flex;gap:14px;align-items:flex-start;margin-bottom:16px;">
          <i class="bi bi-box-seam" style="color:#c2410c;font-size:22px;flex-shrink:0;margin-top:2px;"></i>
          <div>
            <div style="font-weight:600;color:#7c2d12;font-size:14px;margin-bottom:4px;">Supplier has linked stock records</div>
            <p style="margin:0;font-size:13.5px;color:#92400e;line-height:1.6;">
              <strong id="cannotDeleteName">This supplier</strong> has supplied items that are recorded in the system.
              Deleting them would break existing stock and purchase history.
            </p>
          </div>
        </div>
        <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:13px 16px;font-size:13px;color:#374151;line-height:1.7;">
          <div style="font-weight:600;color:#111827;margin-bottom:6px;"><i class="bi bi-info-circle me-1"></i>What you can do instead:</div>
          <ul style="margin:0;padding-left:18px;">
            <li>Edit the supplier's details if information has changed.</li>
            <li>Only suppliers with <strong>no stock records</strong> can be deleted.</li>
          </ul>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-dismiss toasts
document.querySelectorAll('.toast-pill').forEach(function(el) {
  setTimeout(function() {
    el.style.opacity = '0';
    el.style.transition = 'opacity 0.4s';
    setTimeout(function(){ el.remove(); }, 400);
  }, 4000);
});

// Search filter
document.getElementById('searchInput').addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('#supplierTable tbody tr').forEach(function(row) {
    row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
});

// Edit modal population
document.getElementById('editSupplierModal').addEventListener('show.bs.modal', function(e) {
  const row = e.relatedTarget.closest('tr');
  this.querySelector('#edit_supplier_id').value     = row.dataset.id;
  this.querySelector('#edit_supplier_name').value   = row.dataset.name;
  this.querySelector('#edit_contact_person').value  = row.dataset.contactPerson;
  this.querySelector('#edit_contact_no').value      = row.dataset.contactNo;
  this.querySelector('#edit_email').value           = row.dataset.email;
  this.querySelector('#edit_address').value         = row.dataset.address;
  this.querySelector('#edit_notes').value           = row.dataset.notes;
});

// Supplier IDs that have linked stock records (cannot be deleted)
const linkedSupplierIds = new Set(<?= json_encode($linked_supplier_ids) ?>);

// Manually manage both delete modals
const confirmDeleteModal  = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
const cannotDeleteModal   = new bootstrap.Modal(document.getElementById('cannotDeleteModal'));
let deleteSupplierId = null;

document.querySelectorAll('.delete-btn').forEach(function(btn) {
  btn.addEventListener('click', function() {
    const supplierId   = parseInt(btn.getAttribute('data-supplier-id'));
    const supplierName = btn.getAttribute('data-supplier-name') || '—';

    if (linkedSupplierIds.has(supplierId)) {
      document.getElementById('cannotDeleteName').textContent = supplierName;
      cannotDeleteModal.show();
    } else {
      deleteSupplierId = supplierId;
      document.getElementById('deleteSupplierName').textContent = supplierName;
      confirmDeleteModal.show();
    }
  });
});

document.getElementById('confirmDeleteButton').addEventListener('click', function() {
  if (deleteSupplierId) window.location.href = '?delete_id=' + encodeURIComponent(deleteSupplierId);
});

// Price Guide search
const priceGuideSearchInput = document.getElementById('priceGuideSearch');
if (priceGuideSearchInput) {
  priceGuideSearchInput.addEventListener('input', function() {
    const q = this.value.trim().toLowerCase();
    let totalVisible = 0;

    document.querySelectorAll('.price-cat-block').forEach(function(block) {
      let blockVisible = 0;
      block.querySelectorAll('.price-row').forEach(function(row) {
        const product   = (row.querySelector('.price-product-name')?.textContent || '').toLowerCase();
        const brand     = (row.querySelector('.price-brand-name')?.textContent || '').toLowerCase();
        const variation = (row.querySelector('.price-variation-name')?.textContent || '').toLowerCase();
        const supplier  = (row.querySelector('.price-supplier-name')?.textContent || '').toLowerCase();

        const match = !q ||
                      product.includes(q) ||
                      brand.includes(q) ||
                      variation.includes(q) ||
                      supplier.includes(q);

        row.style.display = match ? '' : 'none';
        if (match) blockVisible++;
      });

      block.style.display = blockVisible > 0 ? '' : 'none';
      if (blockVisible > 0 && q) {
        const collapse = block.querySelector('.accordion-collapse');
        if (collapse && !collapse.classList.contains('show')) {
          new bootstrap.Collapse(collapse, { toggle: false }).show();
        }
      }
      totalVisible += blockVisible;
    });

    document.getElementById('priceGuideEmpty').style.display = totalVisible === 0 ? '' : 'none';
  });

  // Clear search when modal closes
  document.getElementById('lowestCostModal').addEventListener('hidden.bs.modal', function() {
    priceGuideSearchInput.value = '';
    priceGuideSearchInput.dispatchEvent(new Event('input'));
  });
}
</script>
</body>
</html>