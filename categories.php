<?php
include 'project.php';
session_start();

/* ============================================================
   CATEGORIES PAGE
   CLEANED + ORGANIZED VERSION
   ============================================================ */

/* ============================================================
   1) AUTH
   ============================================================ */
if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$role = $_SESSION['role'] ?? 'Admin';

/* ============================================================
   2) CONFIG
   ============================================================ */
$limit = 10;
$uploadDir = 'uploads/subcategories/';
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$maxFileSize = 2 * 1024 * 1024; // 2MB

/* ============================================================
   3) HELPERS
   ============================================================ */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function safeRedirect(string $url): void
{
    header("Location: " . $url);
    exit();
}

function uploadSubcategoryImage(array $file, string $uploadDir, array $allowedExtensions, int $maxFileSize): array
{
    if (!isset($file) || !isset($file['error']) || $file['error'] !== 0) {
        return ['success' => false, 'path' => '', 'error' => 'No image uploaded.'];
    }

    if ($file['size'] > $maxFileSize) {
        return ['success' => false, 'path' => '', 'error' => 'Image is too large. Maximum size is 2MB.'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions, true)) {
        return ['success' => false, 'path' => '', 'error' => 'Invalid image type. Allowed: jpg, jpeg, png, gif, webp.'];
    }

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $filename = 'subcat_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $targetPath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => false, 'path' => '', 'error' => 'Failed to upload image.'];
    }

    return ['success' => true, 'path' => $targetPath, 'error' => ''];
}

function fetchOldSubcategoryImage(mysqli $conn, int $id): string
{
    $stmt = $conn->prepare("SELECT image_path FROM subcategories WHERE subcategory_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row['image_path'] ?? '';
}

/* ============================================================
   4) HANDLE ACTIONS
   ============================================================ */

/* ADD CATEGORY */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_category'])) {
    $category_name = trim($_POST['category_name'] ?? '');

    if ($category_name === '') {
        $_SESSION['error'] = "Category name is required.";
        safeRedirect("categories.php");
    }

    $check = $conn->prepare("SELECT COUNT(*) FROM categories WHERE category_name = ?");
    $check->bind_param("s", $category_name);
    $check->execute();
    $check->bind_result($exists);
    $check->fetch();
    $check->close();

    if ($exists > 0) {
        $_SESSION['error'] = "Category already exists.";
        safeRedirect("categories.php");
    }

    $stmt = $conn->prepare("INSERT INTO categories (category_name, created_at) VALUES (?, NOW())");
    $stmt->bind_param("s", $category_name);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Category added successfully.";
    } else {
        $_SESSION['error'] = "Failed to add category.";
    }
    $stmt->close();

    safeRedirect("categories.php");
}

/* EDIT CATEGORY */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_category'])) {
    $id = (int)($_POST['id'] ?? 0);
    $category_name = trim($_POST['category_name'] ?? '');

    if ($id <= 0 || $category_name === '') {
        $_SESSION['error'] = "Invalid category update request.";
        safeRedirect("categories.php");
    }

    $stmt = $conn->prepare("UPDATE categories SET category_name = ? WHERE category_id = ?");
    $stmt->bind_param("si", $category_name, $id);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Category updated successfully.";
    } else {
        $_SESSION['error'] = "Failed to update category.";
    }
    $stmt->close();

    safeRedirect("categories.php");
}

/* DELETE CATEGORY */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_category'])) {
    $id = (int)($_POST['delete_category_id'] ?? 0);

    if ($id <= 0) {
        $_SESSION['error'] = "Invalid category deletion request.";
        safeRedirect("categories.php");
    }

    // Prevent deleting a category that still has related products or subcategories.
    $productCheck = $conn->prepare("SELECT COUNT(*) AS count_products FROM products WHERE category_id = ?");
    $productCheck->bind_param("i", $id);
    $productCheck->execute();
    $productCount = (int)$productCheck->get_result()->fetch_assoc()['count_products'];
    $productCheck->close();

    $subcategoryCheck = $conn->prepare("SELECT COUNT(*) AS count_subcategories FROM subcategories WHERE category_id = ?");
    $subcategoryCheck->bind_param("i", $id);
    $subcategoryCheck->execute();
    $subcategoryCount = (int)$subcategoryCheck->get_result()->fetch_assoc()['count_subcategories'];
    $subcategoryCheck->close();

    if ($productCount > 0 || $subcategoryCount > 0) {
        $errorParts = [];
        if ($productCount > 0) {
            $errorParts[] = "there are {$productCount} product(s) assigned to this category";
        }
        if ($subcategoryCount > 0) {
            $errorParts[] = "there are {$subcategoryCount} subcategory(ies) under this category";
        }
        $_SESSION['error'] = "Cannot delete category because " . implode(' and ', $errorParts) . ". Please reassign or remove them first.";
        safeRedirect("categories.php");
    }

    $stmt = $conn->prepare("DELETE FROM categories WHERE category_id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Category deleted successfully.";
    } else {
        $_SESSION['error'] = "Failed to delete category.";
    }
    $stmt->close();

    safeRedirect("categories.php");
}

/* ADD SUBCATEGORY */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_subcategory'])) {
    $subcategory_name = trim($_POST['subcategory_name'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $imagePath = '';

    if ($subcategory_name === '' || $category_id <= 0) {
        $_SESSION['error'] = "Subcategory name and category are required.";
        safeRedirect("categories.php");
    }

    if (isset($_FILES['image_path']) && $_FILES['image_path']['error'] === 0) {
        $upload = uploadSubcategoryImage($_FILES['image_path'], $uploadDir, $allowedExtensions, $maxFileSize);

        if (!$upload['success']) {
            $_SESSION['error'] = $upload['error'];
            safeRedirect("categories.php");
        }

        $imagePath = $upload['path'];
    }

    $stmt = $conn->prepare("
        INSERT INTO subcategories (subcategory_name, category_id, image_path, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->bind_param("sis", $subcategory_name, $category_id, $imagePath);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Subcategory added successfully.";
    } else {
        $_SESSION['error'] = "Failed to add subcategory.";
    }
    $stmt->close();

    safeRedirect("categories.php");
}

/* EDIT SUBCATEGORY */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_subcategory'])) {
    $id = (int)($_POST['id'] ?? 0);
    $subcategory_name = trim($_POST['subcategory_name'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);

    if ($id <= 0 || $subcategory_name === '' || $category_id <= 0) {
        $_SESSION['error'] = "Invalid subcategory update request.";
        safeRedirect("categories.php");
    }

    $removeImage = isset($_POST['remove_image']) && $_POST['remove_image'] == '1';
    $updateImage = false;
    $imagePath = '';

    // If removing image
    if ($removeImage) {
        $oldImage = fetchOldSubcategoryImage($conn, $id);
        if ($oldImage !== '' && file_exists($oldImage)) {
            unlink($oldImage);
        }
        $imagePath = '';
        $updateImage = true;
    }

    // If uploading new image (this overrides removal if both are done, which is logical)
    if (isset($_FILES['image_path']) && $_FILES['image_path']['error'] === 0) {
        $upload = uploadSubcategoryImage($_FILES['image_path'], $uploadDir, $allowedExtensions, $maxFileSize);

        if (!$upload['success']) {
            $_SESSION['error'] = $upload['error'];
            safeRedirect("categories.php");
        }

        $imagePath = $upload['path'];
        $updateImage = true;

        // If we didn't already remove it above
        if (!$removeImage) {
            $oldImage = fetchOldSubcategoryImage($conn, $id);
            if ($oldImage !== '' && file_exists($oldImage)) {
                unlink($oldImage);
            }
        }
    }

    if ($updateImage) {
        $stmt = $conn->prepare("
            UPDATE subcategories
            SET subcategory_name = ?, category_id = ?, image_path = ?
            WHERE subcategory_id = ?
        ");
        $stmt->bind_param("sisi", $subcategory_name, $category_id, $imagePath, $id);
    } else {
        $stmt = $conn->prepare("
            UPDATE subcategories
            SET subcategory_name = ?, category_id = ?
            WHERE subcategory_id = ?
        ");
        $stmt->bind_param("sii", $subcategory_name, $category_id, $id);
    }

    if ($stmt->execute()) {
        $_SESSION['success'] = "Subcategory updated successfully.";
    } else {
        $_SESSION['error'] = "Failed to update subcategory.";
    }
    $stmt->close();

    safeRedirect("categories.php");
}

/* DELETE SUBCATEGORY */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_subcategory'])) {
    $id = (int)($_POST['delete_subcategory_id'] ?? 0);

    if ($id <= 0) {
        $_SESSION['error'] = "Invalid subcategory deletion request.";
        safeRedirect("categories.php");
    }

    // Prevent deleting a subcategory that still has related products.
    $productCheck = $conn->prepare("SELECT COUNT(*) AS count_products FROM products WHERE subcategory_id = ?");
    $productCheck->bind_param("i", $id);
    $productCheck->execute();
    $productCount = (int)$productCheck->get_result()->fetch_assoc()['count_products'];
    $productCheck->close();

    if ($productCount > 0) {
        $_SESSION['error'] = "Cannot delete subcategory because there are {$productCount} product(s) assigned to it. Please reassign or remove them first.";
        safeRedirect("categories.php");
    }

    $oldImage = fetchOldSubcategoryImage($conn, $id);
    if ($oldImage !== '' && file_exists($oldImage)) {
        unlink($oldImage);
    }

    $stmt = $conn->prepare("DELETE FROM subcategories WHERE subcategory_id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Subcategory deleted successfully.";
    } else {
        $_SESSION['error'] = "Failed to delete subcategory.";
    }
    $stmt->close();

    safeRedirect("categories.php");
}

/* ============================================================
   5) PAGINATION SETUP
   ============================================================ */
$cat_page = isset($_GET['cat_page']) ? max(1, (int)$_GET['cat_page']) : 1;
$sub_page = isset($_GET['sub_page']) ? max(1, (int)$_GET['sub_page']) : 1;

$cat_offset = ($cat_page - 1) * $limit;
$sub_offset = ($sub_page - 1) * $limit;

/* ============================================================
   6) COUNTS / SUMMARY
   ============================================================ */
$summary_query = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM categories) AS total_categories,
        (SELECT COUNT(*) FROM subcategories) AS total_subcategories,
        (SELECT COUNT(*) FROM subcategories WHERE image_path IS NOT NULL AND image_path <> '') AS with_image,
        (SELECT COUNT(*) FROM subcategories WHERE image_path IS NULL OR image_path = '') AS without_image
");
$summary = $summary_query->fetch_assoc();

$total_categories = (int)($summary['total_categories'] ?? 0);
$total_subcategories = (int)($summary['total_subcategories'] ?? 0);
$with_image = (int)($summary['with_image'] ?? 0);
$without_image = (int)($summary['without_image'] ?? 0);

/* ============================================================
   7) CATEGORY DATA
   ============================================================ */
$cat_count_result = $conn->query("SELECT COUNT(*) AS total FROM categories");
$cat_total_rows = (int)($cat_count_result->fetch_assoc()['total'] ?? 0);
$cat_total_pages = max(1, (int)ceil($cat_total_rows / $limit));

$categories_result = $conn->query("
    SELECT *
    FROM categories
    ORDER BY created_at DESC
    LIMIT $limit OFFSET $cat_offset
");

/* ============================================================
   8) SUBCATEGORY DATA
   ============================================================ */
$sub_count_result = $conn->query("SELECT COUNT(*) AS total FROM subcategories");
$sub_total_rows = (int)($sub_count_result->fetch_assoc()['total'] ?? 0);
$sub_total_pages = max(1, (int)ceil($sub_total_rows / $limit));

$subcategories_result = $conn->query("
    SELECT s.*, c.category_name
    FROM subcategories s
    JOIN categories c ON s.category_id = c.category_id
    ORDER BY s.created_at DESC
    LIMIT $limit OFFSET $sub_offset
");

/* ============================================================
   9) DATA FOR MODALS
   ============================================================ */
$categories_for_modals = $conn->query("SELECT * FROM categories ORDER BY created_at DESC");
$subcategories_for_modals = $conn->query("
    SELECT s.*, c.category_name, c.category_id
    FROM subcategories s
    JOIN categories c ON s.category_id = c.category_id
    ORDER BY s.created_at DESC
");
$categories_dropdown_result = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");

$categories = $categories_for_modals;
$subcategories = $subcategories_for_modals;
?>
<!DOCTYPE html>
<html lang="en">
<head>
        <link rel="icon" type="image/x-icon" href="favicon.ico">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories — Inventory System</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin1.css">
    <link rel="stylesheet" href="css/alert.css">

    <style>
        :root {
            --accent:       #1458ec;
            --accent-dark:  #0f3bbf;
            --accent-light: #e8f0fe;
            --success:      #12b76a;
            --info:         #0ea5e9;
            --warning:      #f59e0b;
            --card-radius:  16px;
            --card-shadow:  0 4px 24px rgba(20, 88, 236, 0.08);
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

       /* ── Page header ── */
        .page-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 28px;
            gap: 16px;
        }
        .page-header-left { display: flex; align-items: center; gap: 14px; }
        .page-header-icon {
            width: 46px; height: 46px;
            background: var(--blue);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            color: #fff;
            font-size: 20px;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(37,99,235,.3);
        }
        .page-header h4 {
            font-size: 1.3rem;
            font-weight: 700;
            margin: 0 0 3px;
            letter-spacing: -.3px;
            color: var(--ink);
        }
        .page-header p {
            margin: 0;
            font-size: .75rem;
            color: var(--muted);
        }

        /* ── Stat cards ──────────────────────────────────────── */
        .stat-card-new {
            border-radius: var(--card-radius);
            background: #fff;
            box-shadow: var(--card-shadow);
            padding: 22px 24px;
            display: flex;
            align-items: center;
            gap: 18px;
            transition: transform 0.22s ease, box-shadow 0.22s ease;
            border: 1px solid rgba(0,0,0,0.05);
        }
        .stat-card-new:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(20, 88, 236, 0.14);
        }
        .stat-icon {
            width: 52px; height: 52px;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        .stat-icon.blue   { background: #e8f0fe; color: var(--accent); }
        .stat-icon.green  { background: #d1fae5; color: var(--success); }
        .stat-icon.cyan   { background: #e0f2fe; color: var(--info); }
        .stat-icon.amber  { background: #fef3c7; color: var(--warning); }
        .stat-body .label { font-size: 0.78rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.6px; color: #7c8a99; margin-bottom: 4px; }
        .stat-body .value { font-size: 1.9rem; font-weight: 700; color: #1a2332; line-height: 1; }

        /* ── Section header ─────────────────────────────────── */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
        }
        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1a2332;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .section-title .title-icon {
            width: 34px; height: 34px;
            border-radius: 10px;
            background: var(--accent-light);
            color: var(--accent);
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem;
        }

        /* ── Table card ─────────────────────────────────────── */
        .table-card {
            background: #fff;
            border-radius: var(--card-radius);
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(0,0,0,0.05);
            overflow: hidden;
            margin-bottom: 28px;
        }
        .table-card .table { margin-bottom: 0; }
        .table-card .table thead th {
            background: linear-gradient(90deg, var(--accent) 0%, #2563eb 100%);
            color: #fff;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 0;
            padding: 14px 16px;
        }
        .table-card .table tbody td {
            padding: 13px 16px;
            vertical-align: middle;
            border-color: #f0f4f8;
            font-size: 0.9rem;
            color: #374151;
        }
        .table-card .table tbody tr:hover { background: #f8faff; }
        .table-card .table tbody tr:last-child td { border-bottom: 0; }

        /* ── Subcategory image ──────────────────────────────── */
        .sub-img {
            width: 44px; height: 44px;
            border-radius: 10px;
            object-fit: cover;
            border: 2px solid #e8f0fe;
        }
        .no-img-badge {
            width: 44px; height: 44px;
            border-radius: 10px;
            background: #f0f4f8;
            color: #9ca3af;
            font-size: 0.65rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            line-height: 1.2;
        }

        /* ── Category badge chip in table ───────────────────── */
        .cat-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: var(--accent-light);
            color: var(--accent);
            border-radius: 20px;
            padding: 3px 10px;
            font-size: 0.78rem;
            font-weight: 600;
        }

        /* ── Action buttons ─────────────────────────────────── */
        .btn-action {
            width: 34px; height: 34px;
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            font-size: 0.88rem;
            cursor: pointer;
            transition: filter 0.18s ease, transform 0.18s ease;
        }
        .btn-action:hover { filter: brightness(1.12); transform: scale(1.1); }
        .btn-edit   { background: #fef3c7; color: #d97706; }
        .btn-delete { background: #fee2e2; color: #dc2626; }

        /* ── Add button ─────────────────────────────────────── */
        .btn-add {
            background: linear-gradient(135deg, var(--accent), #2563eb);
            color: #fff;
            border: 0;
            border-radius: 10px;
            padding: 9px 18px;
            font-size: 0.88rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: box-shadow 0.2s ease, transform 0.2s ease;
            cursor: pointer;
        }
        .btn-add:hover {
            box-shadow: 0 6px 18px rgba(20,88,236,0.35);
            transform: translateY(-2px);
            color: #fff;
        }

        /* ── Pagination ─────────────────────────────────────── */
        .pagination { margin-top: 16px; padding: 0 16px 16px; }
        .page-link {
            border-radius: 8px !important;
            margin: 0 2px;
            font-size: 0.85rem;
            border-color: #e5e7eb;
            color: var(--accent);
        }
        .page-item.active .page-link {
            background: var(--accent);
            border-color: var(--accent);
        }

        /* ── Empty state ─────────────────────────────────────── */
        .empty-state {
            padding: 48px 0;
            text-align: center;
            color: #9ca3af;
        }
        .empty-state i { font-size: 2.5rem; margin-bottom: 10px; display: block; }
        .empty-state p { font-size: 0.88rem; margin: 0; }

        /* ── Date text ──────────────────────────────────────── */
        .date-text { font-size: 0.82rem; color: #6b7280; }

        /* ── object-fit helper ───────────────────────────────── */
        .object-fit-cover { object-fit: cover; }
    </style>
</head>
<body>
<div class="d-flex">

    <!-- ── Sidebar ─────────────────────────────────────────── -->
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
            <li class="nav-item mb-2"><a class="nav-link active" href="categories.php"><i class="bi bi-tags me-2"></i> Categories</a></li>
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
            <li class="nav-item mb-2"><a class="nav-link" href="manageUser.php"><i class="bi bi-people me-2"></i> Manage Users</a></li>

            <li class="sidebar-title">Settings</li>
            <li class="nav-item mb-2"><a class="nav-link" href="settings.php"><i class="bi bi-gear me-2"></i> Settings</a></li>
            <li class="nav-item"><a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
        </ul>
    </div>

    <!-- ── Main content ────────────────────────────────────── -->
    <div class="content flex-grow-1">
        <div class="container-fluid py-4">

            <!-- Toast alerts -->
            <div class="alert-toast-container" id="alertToastContainer">
                <?php if (!empty($_SESSION['success'])): ?>
                    <div class="alert alert-success">
                        <span class="alert-icon"></span>
                        <span><?= e($_SESSION['success']) ?></span>
                        <button class="alert-close" onclick="this.parentElement.style.display='none';">&times;</button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
                <?php if (!empty($_SESSION['error'])): ?>
                    <div class="alert alert-error">
                        <span class="alert-icon"></span>
                        <span><?= e($_SESSION['error']) ?></span>
                        <button class="alert-close" onclick="this.parentElement.style.display='none';">&times;</button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
            </div>
            <script>
                window.addEventListener('DOMContentLoaded', function () {
                    document.querySelectorAll('.alert-toast-container .alert').forEach(function (a) {
                        setTimeout(function () { a.style.display = 'none'; }, 3500);
                    });
                });
            </script>

            <!-- ── Page header ──────────────────────────────── -->
            <div class="page-header">
                <div class="page-header-left">
                    <div class="page-header-icon"><i class="bi bi-tags-fill"></i></div>
                    <div>
                        <h4>Category Management</h4>
                        <p>Organise your inventory by managing categories and subcategories.</p>
                    </div>
                </div>
            </div>

            <!-- ── Stat cards ────────────────────────────────── -->
            <div class="row g-3 mb-4">
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card-new">
                        <div class="stat-icon blue"><i class="bi bi-tags-fill"></i></div>
                        <div class="stat-body">
                            <div class="label">Total Categories</div>
                            <div class="value"><?= number_format($total_categories) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card-new">
                        <div class="stat-icon green"><i class="bi bi-diagram-3-fill"></i></div>
                        <div class="stat-body">
                            <div class="label">Subcategories</div>
                            <div class="value"><?= number_format($total_subcategories) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card-new">
                        <div class="stat-icon cyan"><i class="bi bi-image-fill"></i></div>
                        <div class="stat-body">
                            <div class="label">With Image</div>
                            <div class="value"><?= number_format($with_image) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card-new">
                        <div class="stat-icon amber"><i class="bi bi-image"></i></div>
                        <div class="stat-body">
                            <div class="label">Without Image</div>
                            <div class="value"><?= number_format($without_image) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Item Categories section ───────────────────── -->
            <div class="section-header">
                <div class="section-title">
                    <div class="title-icon"><i class="bi bi-tags"></i></div>
                    Item Categories
                </div>
                <button class="btn-add" data-bs-toggle="modal" data-bs-target="#addCategoryModal" id="addCategoryBtn">
                    <i class="bi bi-plus-lg"></i> Add Category
                </button>
            </div>

            <div class="table-card mb-4">
                <div class="table-responsive">
                    <table class="table mb-0" id="categoriesTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Category Name</th>
                                <th>Created At</th>
                                <th style="width:110px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($categories_result && $categories_result->num_rows > 0):
                                $catRowNum = $cat_offset + 1;
                                while ($row = $categories_result->fetch_assoc()): ?>
                                <tr>
                                    <td class="text-muted" style="font-size:.82rem;"><?= $catRowNum++ ?></td>
                                    <td>
                                        <span class="fw-600" style="font-weight:600;color:#1a2332;"><?= e($row['category_name']) ?></span>
                                    </td>
                                    <td class="date-text"><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <button class="btn-action btn-edit"
                                                title="Edit"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editCategoryModal<?= (int)$row['category_id'] ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form method="POST" class="d-inline delete-form">
                                                <input type="hidden" name="delete_category_id" value="<?= (int)$row['category_id'] ?>">
                                                <button type="button"
                                                    class="btn-action btn-delete confirm-delete"
                                                    title="Delete"
                                                    data-message="WARNING: Deleting this category may affect related subcategories and product links. Proceed?"
                                                    data-form-action="delete_category">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4">
                                        <div class="empty-state">
                                            <i class="bi bi-tags"></i>
                                            <p>No categories found. Add your first category!</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <nav aria-label="Category Pagination">
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?= ($cat_page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="categories.php?cat_page=<?= $cat_page - 1 ?>&sub_page=<?= $sub_page ?>">&laquo;</a>
                        </li>
                        <?php for ($i = 1; $i <= $cat_total_pages; $i++): ?>
                            <li class="page-item <?= ($cat_page === $i) ? 'active' : '' ?>">
                                <a class="page-link" href="categories.php?cat_page=<?= $i ?>&sub_page=<?= $sub_page ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= ($cat_page >= $cat_total_pages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="categories.php?cat_page=<?= $cat_page + 1 ?>&sub_page=<?= $sub_page ?>">&raquo;</a>
                        </li>
                    </ul>
                </nav>
            </div>

            <!-- ── Item Subcategories section ────────────────── -->
            <div class="section-header">
                <div class="section-title">
                    <div class="title-icon"><i class="bi bi-diagram-3"></i></div>
                    Item Subcategories
                </div>
                <button class="btn-add" data-bs-toggle="modal" data-bs-target="#addSubcategoryModal" id="addSubcategoryBtn">
                    <i class="bi bi-plus-lg"></i> Add Subcategory
                </button>
            </div>

            <div class="table-card">
                <div class="table-responsive">
                    <table class="table mb-0" id="subcategoriesTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Image</th>
                                <th>Subcategory Name</th>
                                <th>Parent Category</th>
                                <th>Created At</th>
                                <th style="width:110px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($subcategories_result && $subcategories_result->num_rows > 0):
                                $subRowNum = $sub_offset + 1;
                                while ($sub = $subcategories_result->fetch_assoc()): ?>
                                <tr>
                                    <td class="text-muted" style="font-size:.82rem;"><?= $subRowNum++ ?></td>
                                    <td>
                                        <?php if (!empty($sub['image_path'])): ?>
                                            <img src="<?= e($sub['image_path']) ?>" class="sub-img" alt="<?= e($sub['subcategory_name']) ?>">
                                        <?php else: ?>
                                            <div class="no-img-badge">No<br>Image</div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-weight:600;color:#1a2332;"><?= e($sub['subcategory_name']) ?></td>
                                    <td>
                                        <span class="cat-chip">
                                            <i class="bi bi-tag-fill" style="font-size:.7rem;"></i>
                                            <?= e($sub['category_name'] ?? 'None') ?>
                                        </span>
                                    </td>
                                    <td class="date-text"><?= date('M d, Y', strtotime($sub['created_at'])) ?></td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <button class="btn-action btn-edit"
                                                title="Edit"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editSubcategoryModal<?= (int)$sub['subcategory_id'] ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form method="POST" class="d-inline delete-form">
                                                <input type="hidden" name="delete_subcategory_id" value="<?= (int)$sub['subcategory_id'] ?>">
                                                <button type="button"
                                                    class="btn-action btn-delete confirm-delete"
                                                    title="Delete"
                                                    data-message="Are you sure you want to delete this subcategory? This action cannot be undone."
                                                    data-form-action="delete_subcategory">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6">
                                        <div class="empty-state">
                                            <i class="bi bi-diagram-3"></i>
                                            <p>No subcategories found. Add your first subcategory!</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <nav aria-label="Subcategory Pagination">
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?= ($sub_page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="categories.php?sub_page=<?= $sub_page - 1 ?>&cat_page=<?= $cat_page ?>">&laquo;</a>
                        </li>
                        <?php for ($i = 1; $i <= $sub_total_pages; $i++): ?>
                            <li class="page-item <?= ($sub_page === $i) ? 'active' : '' ?>">
                                <a class="page-link" href="categories.php?sub_page=<?= $i ?>&cat_page=<?= $cat_page ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= ($sub_page >= $sub_total_pages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="categories.php?sub_page=<?= $sub_page + 1 ?>&cat_page=<?= $cat_page ?>">&raquo;</a>
                        </li>
                    </ul>
                </nav>
            </div>

        </div>
    </div>
</div>

<?php include 'category_modals.php'; ?>

<!-- ============================================================
     CONFIRMATION MODAL
     ============================================================ -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
        <div class="modal-content border-0" style="border-radius:18px;overflow:hidden;">
            <div class="modal-header border-0" style="background:linear-gradient(135deg,#fee2e2,#fef2f2);padding:20px 24px 12px;">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:44px;height:44px;border-radius:12px;background:#fee2e2;display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-exclamation-triangle-fill" style="color:#dc2626;font-size:1.3rem;"></i>
                    </div>
                    <h5 class="modal-title mb-0 fw-700" id="confirmModalLabel" style="font-weight:700;color:#1a2332;">Confirm Delete</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="confirmModalMessage" style="padding:18px 24px;color:#374151;font-size:.92rem;">
                Are you sure you want to proceed?
            </div>
            <div class="modal-footer border-0" style="padding:12px 24px 20px;gap:10px;">
                <button type="button" class="btn btn-light fw-600" data-bs-dismiss="modal" style="border-radius:10px;padding:9px 20px;font-weight:600;">
                    <i class="bi bi-x-circle me-1"></i> Cancel
                </button>
                <button type="button" class="btn btn-danger fw-600" id="confirmModalProceed" style="border-radius:10px;padding:9px 20px;font-weight:600;">
                    <i class="bi bi-trash me-1"></i> Delete
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    // Sidebar toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function () {
            document.getElementById('sidebar')?.classList.toggle('show');
        });
    }

    // Confirmation modal logic
    let pendingForm = null;
    let pendingAction = null;
    const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));

    document.querySelectorAll('.confirm-delete').forEach(function (btn) {
        btn.addEventListener('click', function () {
            pendingForm = btn.closest('.delete-form');
            pendingAction = btn.getAttribute('data-form-action');
            const message = btn.getAttribute('data-message');
            document.getElementById('confirmModalMessage').textContent = message;
            confirmModal.show();
        });
    });

    document.getElementById('confirmModalProceed').addEventListener('click', function () {
        if (pendingForm && pendingAction) {
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = pendingAction;
            actionInput.value = '1';
            pendingForm.appendChild(actionInput);
            pendingForm.submit();
        }
    });
})();
</script>
</body>
</html>
</html>
