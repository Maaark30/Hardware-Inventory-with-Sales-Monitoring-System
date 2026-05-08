<?php
include 'project.php';
session_start();

// =================== ACCESS CONTROL =================== //
if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

$user_role = $_SESSION['role'] ?? 'staff';
$username = $_SESSION['username'] ?? 'User';

// =================== PAGINATION SETUP =================== //
$limit = 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// =================== COUNT TOTAL SALE GROUPS =================== //
$count_sql = "
    SELECT COUNT(*) AS total
    FROM sale_groups sg
    JOIN sale_payments sp ON sg.sale_group_id = sp.sale_group_id
";
$count_result = $conn->query($count_sql);
$total_rows = ($count_result && $count_result->num_rows > 0) ? (int)$count_result->fetch_assoc()['total'] : 0;
$total_pages = ceil($total_rows / $limit);

if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
    $offset = ($page - 1) * $limit;
} elseif ($total_pages == 0) {
    $page = 1;
    $offset = 0;
}

// =================== FETCH SALE HISTORY =================== //
$sql = "
    SELECT 
        sg.sale_group_id,
        sg.created_at,
        sp.total_amount,
        COUNT(s.sale_id) AS total_items_count
    FROM 
        sale_groups sg
    JOIN 
        sale_payments sp ON sg.sale_group_id = sp.sale_group_id
    JOIN 
        sales s ON sg.sale_group_id = s.sale_group_id
    GROUP BY 
        sg.sale_group_id, sg.created_at, sp.total_amount
    ORDER BY 
        sg.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Error preparing sales history query: " . $conn->error);
}

$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();
$history = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

// =================== HELPER FUNCTION =================== //
function buildPaginationUrl($page)
{
    $params = $_GET;
    $params['page'] = $page;
    return 'purchased_history.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
        <link rel="icon" type="image/x-icon" href="favicon.ico">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Sales History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"> 
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
            --card-radius:  16px;
            --card-shadow:  0 2px 16px rgba(0,0,0,0.07);
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


        .product-header-card { background: transparent; border-radius: 12px; padding: 20px 24px; display: flex; justify-content: space-between; align-items: center; box-shadow: none; margin-bottom: 24px; border: none; }
        .ph-left { display: flex; align-items: center; gap: 16px; }
        .ph-icon { width: 52px; height: 52px; background: #3b82f6; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 24px; box-shadow: 0 4px 12px rgba(59,130,246,0.3); }
        .ph-title { margin: 0; font-size: 1.35rem; font-weight: 700; color: #1e293b; letter-spacing: -0.01em; }
        .ph-subtitle { margin: 4px 0 0 0; font-size: 13px; color: #64748b; }

        .main-table-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); overflow: hidden; border: 1px solid rgba(0,0,0,0.03); margin-bottom: 24px; }
        .mtc-header { display: flex; justify-content: space-between; align-items: center; padding: 18px 24px; background: #fff; border-bottom: 1px solid #f1f5f9; }
        .mtc-title { font-size: 15px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 10px; margin: 0; }
        .mtc-title i { color: #3b82f6; font-size: 18px; }
        .mtc-count { background: #f8fafc; color: #64748b; font-size: 12px; font-weight: 600; padding: 6px 14px; border-radius: 20px; border: 1px solid #e2e8f0; }

        .table-new { border-collapse: separate; border-spacing: 0; margin: 0; width: 100%; }
        .table-new thead th { background: #fff; color: #0f172a; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; padding: 16px 24px; border-bottom: 2px solid #f1f5f9; }
        .table-new tbody td { padding: 16px 24px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; font-size: 14px; color: #1e293b; }
        .table-new tbody tr:hover { background: #f8fafc; }
        .table-new tbody tr:last-child td { border-bottom: none; }

        .badge-items { background: #f1f5f9; color: #3b82f6; font-size: 12.5px; font-weight: 700; padding: 4px 12px; border-radius: 20px; }
        .btn-view { font-weight: 600; font-size: 13px; padding: 6px 14px; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px; }

        .pagination .page-link { border-radius: 8px; margin: 0 4px; font-weight: 600; font-size: 13px; color: #475569; border: 1px solid #e2e8f0; }
        .pagination .page-item.active .page-link { background-color: #3b82f6; border-color: #3b82f6; color: #fff; }
        .pagination .page-link:hover:not(.disabled) { background-color: #f1f5f9; }
    </style>
</head>
<body>

<div class="d-flex">
    <!-- SIDEBAR -->
    <div class="sidebar flex-column p-0" id="sidebar">
        <div class="sidebar-logo text-center">
            <img src="images/logo.png" alt="Inventory Logo">
            <h5 class="mt-2 text-white">Staff Panel</h5>
        </div>
        <hr class="text-white">
        <ul class="nav flex-column">
            <li class="sidebar-title">Main</li>
            <li class="nav-item mb-2"><a class="nav-link" href="staff_dashboard.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="staff_add_sale.php"><i class="bi bi-cart-plus me-2"></i> Add Sale</a></li>
            <li class="sidebar-title">Operations</li>
            <li class="nav-item mb-2"><a class="nav-link" href="staff_products.php"><i class="bi bi-box-seam me-2"></i> Product Management</a></li>
            <li class="nav-item mb-2"><a class="nav-link active" href="purchased_history.php"><i class="bi bi-cart-check me-2"></i> Sales</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="staff_returns.php"><i class="bi bi-arrow-return-left me-2"></i> Returns</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="staff_product_history.php"><i class="bi bi-clock-history me-2"></i> Product History</a></li>

            <!-- <li class="sidebar-title">Account</li> -->
            <!-- <li class="nav-item mb-2"><a class="nav-link" href="my_profile.php"><i class="bi bi-person-circle me-2"></i> My Profile</a></li> -->

            <li class="sidebar-title">Others</li>
            <li class="nav-item"><a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
        </ul>
    </div>

    <div class="content flex-grow-1">
        <div class="main-wrap">
            <div class="ph-left">
                    <div class="ph-icon">
                        <i class="bi bi-cart-check"></i>
                    </div>
                    <div>
                        <h2 class="ph-title">Sales History</h2>
                        <p class="ph-subtitle">View and print receipts for past transactions</p>
                    </div>
                </div>
                <br>

            <div class="main-table-card">
                <div class="mtc-header">
                    <h3 class="mtc-title"><i class="bi bi-calendar3"></i> Transaction Records</h3>
                    <div class="mtc-count"><?= $total_rows ?> transactions</div>
                </div>

                <?php if (empty($history)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-info-circle fs-2 text-primary opacity-50"></i>
                        <p class="mt-3">No sale transactions have been recorded yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table-new text-center">
                            <thead>
                                <tr>
                                    <th class="text-start">Reference No.</th>
                                    <th>Date & Time</th>
                                    <th>Items</th>
                                    <th class="text-end">Total Amount</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history as $sale): ?>
                                    <tr>
                                        <td class="text-start font-monospace fw-bold text-primary">#<?= htmlspecialchars($sale['sale_group_id']); ?></td>
                                        <td><?= date('M d, Y h:i A', strtotime($sale['created_at'])); ?></td>
                                        <td><span class="badge-items"><?= htmlspecialchars($sale['total_items_count']); ?> items</span></td>
                                        <td class="text-end fw-bold text-success">₱<?= number_format($sale['total_amount'], 2); ?></td>
                                        <td>
                                            <a href="receipt.php?sale_group_id=<?= urlencode($sale['sale_group_id']); ?>" class="btn btn-outline-primary btn-sm btn-view">
                                                <i class="bi bi-eye"></i> View Receipt
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if ($total_pages > 1): ?>
                        <div class="p-4 border-top border-light">
                            <nav aria-label="Sales pagination">
                                <ul class="pagination pagination-sm justify-content-center mb-1">
                                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= buildPaginationUrl($page - 1) ?>">&laquo;</a>
                                    </li>

                                    <?php
                                    $start_loop = max(1, $page - 2);
                                    $end_loop = min($total_pages, $page + 2);

                                    if ($start_loop > 1) {
                                        echo '<li class="page-item"><a class="page-link" href="' . buildPaginationUrl(1) . '">1</a></li>';
                                        if ($start_loop > 2) {
                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        }
                                    }

                                    for ($i = $start_loop; $i <= $end_loop; $i++): ?>
                                        <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                            <a class="page-link" href="<?= buildPaginationUrl($i) ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor;

                                    if ($end_loop < $total_pages) {
                                        if ($end_loop < $total_pages - 1) {
                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        }
                                        echo '<li class="page-item"><a class="page-link" href="' . buildPaginationUrl($total_pages) . '">' . $total_pages . '</a></li>';
                                    }
                                    ?>

                                    <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= buildPaginationUrl($page + 1) ?>">&raquo;</a>
                                    </li>
                                </ul>

                                <div class="text-center text-muted" style="font-size: 11px;">
                                    Showing transactions <?= min($offset + 1, $total_rows) ?> to <?= min($offset + $limit, $total_rows) ?> of <?= $total_rows ?> total
                                    (Page <?= $page ?> of <?= $total_pages ?>)
                                </div>
                            </nav>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>