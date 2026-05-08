<?php
include 'project.php';
session_start();

date_default_timezone_set('Asia/Manila');
$conn->query("SET time_zone = '+08:00'");

if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'staff') {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];

/* ============================================================
   ✅ AJAX ENDPOINT FOR REACTIVE SLOW-MOVING ANALYTICS
   ============================================================ */
if (isset($_GET['ajax_slow_moving'])) {
    $timeframe = $_GET['timeframe'] ?? 'monthly';
    $interval = '1 MONTH';
    if ($timeframe === 'weekly') $interval = '7 DAY';
    elseif ($timeframe === 'yearly') $interval = '1 YEAR';

    $stmt = $conn->prepare("
        SELECT 
            p.product_name, p.brand, p.variation, p.unit,
            COALESCE(SUM(CASE WHEN sg.created_at >= DATE_SUB(NOW(), INTERVAL $interval) THEN s.quantity ELSE 0 END), 0) AS total_quantity
        FROM products p
        LEFT JOIN sales s ON p.product_id = s.product_id
        LEFT JOIN sale_groups sg ON s.sale_group_id = sg.sale_group_id
        GROUP BY p.product_id, p.product_name, p.brand, p.variation, p.unit
        ORDER BY total_quantity ASC, p.product_name ASC
        LIMIT 10
    ");
    $stmt->execute();
    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

/* ── COUNTS ── */
$total_products      = (int)(($conn->query("SELECT COUNT(*) AS t FROM products"))->fetch_assoc()['t'] ?? 0);
$total_stock_quantity= (int)(($conn->query("SELECT COALESCE(SUM(stock),0) AS t FROM products"))->fetch_assoc()['t'] ?? 0);
$total_sold_today    = (int)(($conn->query("SELECT COALESCE(SUM(s.quantity), 0) AS t FROM sales s JOIN sale_groups sg ON s.sale_group_id = sg.sale_group_id WHERE DATE(sg.created_at) = CURDATE()"))->fetch_assoc()['t'] ?? 0);

/* ── STOCK ALERTS (Inclusive) ── */
$out_of_stock_res = $conn->query("SELECT COUNT(*) AS total FROM products WHERE stock=0");
$out_of_stock_count = $out_of_stock_res ? (int)($out_of_stock_res->fetch_assoc()['total'] ?? 0) : 0;

$low_stock_res = $conn->query("SELECT COUNT(*) AS total FROM products WHERE stock > 0 AND reorder_level > 0 AND stock <= reorder_level");
$low_stock_count = $low_stock_res ? (int)($low_stock_res->fetch_assoc()['total'] ?? 0) : 0;

$total_alerts_count = $out_of_stock_count + $low_stock_count;

$low_stock_list_res = $conn->query("SELECT product_id,product_name,brand,variation,unit,stock,reorder_level FROM products WHERE stock > 0 AND reorder_level > 0 AND stock <= reorder_level ORDER BY stock ASC,product_name ASC");
$lowStockProducts = [];
if ($low_stock_list_res) while ($r = $low_stock_list_res->fetch_assoc()) $lowStockProducts[] = $r;

$out_of_stock_res = $conn->query("SELECT product_id,product_name,brand,variation,unit,stock,reorder_level FROM products WHERE stock=0 ORDER BY product_name ASC");
$outOfStockProducts = [];
if ($out_of_stock_res) while ($r = $out_of_stock_res->fetch_assoc()) $outOfStockProducts[] = $r;


/* ── EXPIRY ── */
$expiry_days = 30;
$expiry_count_res = $conn->query("SELECT COALESCE(SUM(CASE WHEN sh.expiry_date<CURDATE() THEN 1 ELSE 0 END),0) AS expired_count, COALESCE(SUM(CASE WHEN sh.expiry_date>=CURDATE() AND sh.expiry_date<=DATE_ADD(CURDATE(),INTERVAL {$expiry_days} DAY) THEN 1 ELSE 0 END),0) AS expiring_count FROM stock_history sh WHERE sh.expiry_date IS NOT NULL AND sh.quantity>0 AND (sh.expiry_date<CURDATE() OR (sh.expiry_date>=CURDATE() AND sh.expiry_date<=DATE_ADD(CURDATE(),INTERVAL {$expiry_days} DAY)))");
$expired_count = 0; $expiring_count = 0;
if ($expiry_count_res) { $er=$expiry_count_res->fetch_assoc(); $expired_count=(int)($er['expired_count']??0); $expiring_count=(int)($er['expiring_count']??0); }
$expiry_total = $expired_count + $expiring_count;

$expiry_list_res = $conn->query("SELECT p.product_id,p.product_name,p.brand,p.variation,p.unit,sh.id AS stock_history_id,sh.quantity AS batch_qty,sh.expiry_date FROM stock_history sh JOIN products p ON p.product_id=sh.product_id WHERE sh.expiry_date IS NOT NULL AND sh.quantity>0 AND (sh.expiry_date<CURDATE() OR (sh.expiry_date>=CURDATE() AND sh.expiry_date<=DATE_ADD(CURDATE(),INTERVAL {$expiry_days} DAY))) ORDER BY sh.expiry_date ASC,p.product_name ASC");
$expiryProducts = [];
if ($expiry_list_res) while ($r = $expiry_list_res->fetch_assoc()) $expiryProducts[] = $r;

/* ── CHARTS ── */
$top_products_result = $conn->query("SELECT p.product_name, p.brand, p.variation, SUM(s.quantity) AS total_quantity FROM sales s JOIN products p ON s.product_id=p.product_id GROUP BY p.product_id, p.product_name, p.brand, p.variation ORDER BY total_quantity DESC LIMIT 10");
$top_products_data = [];
if ($top_products_result) while ($r=$top_products_result->fetch_assoc()) $top_products_data[]=$r;

$monthly_units_result = $conn->query("SELECT DATE_FORMAT(sg.created_at,'%Y-%m') AS month,COALESCE(SUM(s.quantity),0) AS total_units FROM sale_groups sg JOIN sales s ON sg.sale_group_id=s.sale_group_id WHERE sg.created_at>=DATE_SUB(NOW(),INTERVAL 6 MONTH) GROUP BY month ORDER BY month ASC");
$monthly_units_data = [];
if ($monthly_units_result) while ($r=$monthly_units_result->fetch_assoc()) $monthly_units_data[]=['month'=>$r['month'],'total_units'=>(int)$r['total_units']];

/* ── SLOW MOVING ── */
$slow_count_res = $conn->query("
    SELECT COUNT(*) as total FROM (
        SELECT p.product_id
        FROM products p
        LEFT JOIN sales s ON p.product_id = s.product_id
        LEFT JOIN sale_groups sg ON s.sale_group_id = sg.sale_group_id
        GROUP BY p.product_id
        HAVING COALESCE(SUM(CASE WHEN sg.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH) THEN s.quantity ELSE 0 END), 0) = 0
    ) as slow_list
");
$slow_total_count = $slow_count_res ? (int)$slow_count_res->fetch_assoc()['total'] : 0;

$slow_interval = '1 MONTH';
$slow_products_stmt = $conn->prepare("
    SELECT 
        p.product_name, p.brand, p.variation, p.unit,
        COALESCE(SUM(CASE WHEN sg.created_at >= DATE_SUB(NOW(), INTERVAL $slow_interval) THEN s.quantity ELSE 0 END), 0) AS total_quantity
    FROM products p
    LEFT JOIN sales s ON p.product_id = s.product_id
    LEFT JOIN sale_groups sg ON s.sale_group_id = sg.sale_group_id
    GROUP BY p.product_id, p.product_name, p.brand, p.variation, p.unit
    ORDER BY total_quantity ASC, p.product_name ASC
    LIMIT 10
");
$slow_products_stmt->execute();
$slow_products_data = $slow_products_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$slow_products_stmt->close();
$slow_products_json = json_encode($slow_products_data);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Staff Dashboard — K&J B Hardware</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/admin1.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    :root {
            --bg:           #eef1f8;
            --surface:      #ffffff;
            --surface-2:    #f7f9fc;
            --border:       #e2e8f0;
            --ink:          #0f172a;
            --ink-2:        #334155;
            --muted:        #64748b;
            --faint:        #94a3b8;
            --blue:         #2563eb;
            --green:        #059669;
            --amber:        #d97706;
            --red:          #dc2626;
            --violet:       #7c3aed;
            --card-radius:  16px;
            --card-shadow:  0 2px 16px rgba(0,0,0,0.07);
            --font:         'Plus Jakarta Sans', sans-serif;
        }
        body { font-family:var(--font); background:var(--bg); color:var(--ink); font-size:14px; }
        .content { background:var(--bg); min-height:100vh; }
        .dropdown-toggle::after { display:none; }
        .loading-overlay { position: absolute; top:0; left:0; width:100%; height:100%; background: rgba(255,255,255,0.7); display: flex; align-items: center; justify-content: center; z-index: 10; border-radius: var(--card-radius); backdrop-filter: blur(2px); }
        
        .dash-header { background: linear-gradient(135deg, #0f2557 0%, #1a58ec 60%, #2563eb 100%); border-radius: 0 0 28px 28px; padding: 28px 32px 32px; margin-bottom: 32px; position: relative; overflow: hidden; }
        .dash-header h1 { font-size: 1.6rem; font-weight: 800; color: #fff; margin: 0; }
        .dash-header .sub { font-size: 13px; color: rgba(255,255,255,0.65); margin-top: 4px; }
        .dash-header .date-pill { background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.2); border-radius: 20px; padding: 6px 14px; font-size: 12.5px; color: rgba(255,255,255,0.9); font-weight: 500; }

        .section-label { font-size: 11px; font-weight: 700; letter-spacing: 0.09em; text-transform: uppercase; color: #94a3b8; margin-bottom: 14px; }

        .kpi-new { background: #fff; border-radius: var(--card-radius); box-shadow: var(--card-shadow); padding: 20px 22px; display: flex; align-items: center; gap: 16px; transition: transform 0.18s; border: 1px solid rgba(0,0,0,0.04); cursor: default; }
        .kpi-new:hover { transform: translateY(-3px); }
        .kpi-icon-sq { width: 52px; height: 52px; border-radius: 13px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0; }
        .kpi-icon-sq.blue   { background: rgba(26,88,236,0.12);  color: #1a58ec; }
        .kpi-icon-sq.green  { background: rgba(16,185,129,0.12); color: #10b981; }
        .kpi-icon-sq.cyan   { background: rgba(6,182,212,0.12);  color: #06b6d4; }
        .kpi-icon-sq.red    { background: rgba(239,68,68,0.12);   color: #ef4444; }
        .kpi-icon-sq.amber  { background: rgba(245,158,11,0.12);  color: #f59e0b; }
        .kpi-icon-sq.purple { background: rgba(139,92,246,0.12);  color: #8b5cf6; }
        .kpi-icon-sq.danger-active { background: rgba(239,68,68,0.18); color: #ef4444; }
        .kpi-icon-sq.warn-active   { background: rgba(245,158,11,0.18); color: #d97706; }
        .kpi-body { flex: 1; min-width: 0; }
        .kpi-label { font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; margin-bottom: 4px; }
        .kpi-value { font-size: 1.6rem; font-weight: 800; color: #0f172a; line-height: 1; }
        .kpi-sub { font-size: 11.5px; color: #94a3b8; margin-top: 4px; }
        .kpi-sub.warn { color: #ef4444; font-weight: 600; }
        .kpi-sub.ok { color: #10b981; font-weight: 600; }
        .kpi-clickable { cursor: pointer; }

        .chart-card-new { background: #fff; border-radius: var(--card-radius); box-shadow: var(--card-shadow); overflow: hidden; }
        .chart-card-new .cc-head { padding: 16px 20px 12px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 10px; }
        .chart-card-new .cc-icon { width: 34px; height: 34px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; }
        .chart-card-new .cc-head h6 { font-size: 14px; font-weight: 700; color: #0f172a; margin: 0; }
        .chart-card-new .cc-head small { font-size: 11.5px; color: #94a3b8; }
        .chart-card-new .cc-body { padding: 20px; }

        .modal-content { border: none; border-radius: 24px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); background: #fff; }
        .modal-header { padding: 24px 32px; border-bottom: 1px solid #f1f5f9; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff; }
        .modal-header .btn-close { filter: invert(1) grayscale(1); opacity: 0.8; }
        .modal-body { padding: 32px; }
        .nav-tabs { border-bottom: 2px solid #f1f5f9; gap: 8px; }
        .nav-tabs .nav-link { border: none; padding: 12px 20px; font-size: 14px; font-weight: 600; color: #64748b; transition: all 0.2s; position: relative; }
        .nav-tabs .nav-link.active { color: #2563eb; background: transparent; }
        .nav-tabs .nav-link.active::after { content: ''; position: absolute; bottom: -2px; left: 0; width: 100%; height: 2px; background: #2563eb; }

        .table-container { border-radius: 12px; border: 1px solid #f1f5f9; overflow: hidden; }
        .table thead th { background: #f8fafc; color: #475569; font-weight: 700; text-transform: uppercase; font-size: 11px; padding: 14px 16px; border-bottom: 1px solid #f1f5f9; }
        .table tbody td { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; font-size: 13.5px; }

        .status-pill { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .status-pill.danger { background: rgba(239,68,68,0.1); color: #ef4444; }
        .status-pill.warning { background: rgba(245,158,11,0.1); color: #d97706; }
        .status-pill.info { background: rgba(59,130,246,0.1); color: #3b82f6; }

        .toast-stack { position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 8px; min-width: 300px; }
        .toast-item { display: flex; align-items: flex-start; gap: 12px; padding: 14px 16px; border-radius: 12px; font-size: 0.82rem; font-weight: 500; box-shadow: 0 8px 32px rgba(0,0,0,0.1); background: #fff; border: 1px solid #f1f5f9; animation: toastIn 0.3s ease; }
        @keyframes toastIn { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }
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
      <li class="nav-item mb-2"><a class="nav-link active" href="staff_dashboard.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a></li>
      <li class="nav-item mb-2"><a class="nav-link " href="staff_add_sale.php"><i class="bi bi-cart-plus me-2"></i> Add Sale</a></li>

      <li class="sidebar-title">Operations</li>
      <li class="nav-item mb-2"><a class="nav-link" href="staff_products.php"><i class="bi bi-box-seam me-2"></i> Product Management</a></li>
      <li class="nav-item mb-2"><a class="nav-link" href="purchased_history.php"><i class="bi bi-cart-check me-2"></i> Sales</a></li>
      <li class="nav-item mb-2"><a class="nav-link" href="staff_returns.php"><i class="bi bi-arrow-return-left me-2"></i> Returns</a></li>
      <li class="nav-item mb-2"><a class="nav-link" href="staff_product_history.php"><i class="bi bi-clock-history me-2"></i> Product History</a></li>

      <li class="sidebar-title">Others</li>
      <li class="nav-item"><a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
    </ul>
  </div>

  <div class="content flex-grow-1">

    <div class="dash-header">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
          <h1><i class="bi bi-speedometer2 me-2"></i>Staff Dashboard</h1>
          <div class="sub">Welcome back, <strong><?= htmlspecialchars($username) ?></strong></div>
        </div>
        <div class="date-pill"><i class="bi bi-calendar3 me-2"></i><?= date('l, F j, Y') ?></div>
      </div>
    </div>

    <div class="container-fluid px-4">

      <!-- Inventory KPIs -->
      <div class="section-label">Inventory Overview</div>
      <div class="row g-3 mb-4">
        <div class="col-lg-4 col-md-6">
          <div class="kpi-new">
            <div class="kpi-icon-sq blue"><i class="bi bi-box-seam"></i></div>
            <div class="kpi-body">
              <div class="kpi-label">Total Products</div>
              <div class="kpi-value"><?= number_format($total_products) ?></div>
            </div>
          </div>
        </div>
        <div class="col-lg-4 col-md-6">
          <div class="kpi-new">
            <div class="kpi-icon-sq purple"><i class="bi bi-boxes"></i></div>
            <div class="kpi-body">
              <div class="kpi-label">Stock Units</div>
              <div class="kpi-value"><?= formatQty($total_stock_quantity) ?></div>
            </div>
          </div>
        </div>
        <div class="col-lg-4 col-md-6">
          <div class="kpi-new">
            <div class="kpi-icon-sq green"><i class="bi bi-cart-check"></i></div>
            <div class="kpi-body">
              <div class="kpi-label">Items Sold Today</div>
              <div class="kpi-value"><?= formatQty($total_sold_today) ?></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Alerts -->
      <div class="section-label">Stock &amp; Expiry Alerts</div>
      <div class="row g-3 mb-5">
        <div class="col-lg-4 col-md-6">
          <div class="kpi-new clickable kpi-clickable" role="button" data-bs-toggle="modal" data-bs-target="#stockAlertsModal">
            <div class="kpi-icon-sq <?= $total_alerts_count > 0 ? ($out_of_stock_count > 0 ? 'danger-active' : 'warn-active') : 'amber' ?>"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <div class="kpi-body">
              <div class="kpi-label">Stock Alerts</div>
              <div class="kpi-value"><?= number_format($total_alerts_count) ?></div>
              <div class="kpi-sub <?= $total_alerts_count > 0 ? 'warn' : 'ok' ?>"><?= $out_of_stock_count ?> Out | <?= $low_stock_count ?> Low</div>
            </div>
          </div>
        </div>
        <div class="col-lg-4 col-md-6">
          <div class="kpi-new clickable kpi-clickable" role="button" data-bs-toggle="modal" data-bs-target="#expiryAlertsModal">
            <div class="kpi-icon-sq <?= $expiry_total > 0 ? 'warn-active' : 'purple' ?>"><i class="bi bi-hourglass-split"></i></div>
            <div class="kpi-body">
              <div class="kpi-label">Expiring / Expired</div>
              <div class="kpi-value"><?= number_format($expiry_total) ?></div>
              <div class="kpi-sub">Expired: <?= $expired_count ?> | Near: <?= $expiring_count ?></div>
            </div>
          </div>
        </div>
        <div class="col-lg-4 col-md-6">
          <div class="kpi-new clickable kpi-clickable" onclick="document.getElementById('slowMovingSection').scrollIntoView({behavior:'smooth'})">
            <div class="kpi-icon-sq amber" style="background:rgba(217,119,6,0.1); color:#d97706;"><i class="bi bi-graph-down"></i></div>
            <div class="kpi-body">
              <div class="kpi-label">Slow-Moving</div>
              <div class="kpi-value"><?= number_format($slow_total_count) ?></div>
              <div class="kpi-sub">Last 30 days</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Charts & Slow Moving -->
      <div class="section-label">Sales &amp; Slow-Moving Analytics</div>
      <div class="row g-4 mb-4">
        <div class="col-lg-6">
          <div class="chart-card-new">
            <div class="cc-head"><div class="cc-icon" style="background:rgba(26,88,236,0.1);color:#1a58ec;"><i class="bi bi-graph-up"></i></div><h6>Monthly Units Sold</h6></div>
            <div class="cc-body"><div style="height:300px;"><canvas id="monthlyUnitsChart"></canvas></div></div>
          </div>
        </div>
        <div class="col-lg-6">
           <div class="chart-card-new position-relative" id="slowMovingSection">
            <div class="loading-overlay d-none" id="slowLoading"><div class="spinner-border text-primary"></div></div>
            <div class="cc-head d-flex justify-content-between align-items-center">
              <div class="d-flex align-items-center gap-2"><div class="cc-icon" style="background:rgba(100,116,139,0.1);color:#64748b;"><i class="bi bi-graph-down"></i></div><h6>Slow-Moving Products</h6></div>
              <div class="btn-group btn-group-sm">
                  <button type="button" class="btn btn-outline-secondary slow-btn" data-time="weekly">W</button>
                  <button type="button" class="btn btn-outline-secondary slow-btn active" data-time="monthly">M</button>
                  <button type="button" class="btn btn-outline-secondary slow-btn" data-time="yearly">Y</button>
              </div>
            </div>
            <div class="cc-body">
              <div class="table-container" style="max-height: 250px; overflow-y: auto;">
                <table class="table table-hover mb-0">
                  <thead class="sticky-top"><tr><th>Product</th><th class="text-end">Sold</th></tr></thead>
                  <tbody id="slowTableBody">
                    <?php foreach ($slow_products_data as $p): ?>
                      <tr>
                        <td>
                          <strong><?= htmlspecialchars($p['product_name']) ?></strong>
                          <br>
                            <?php
                            $meta = array_filter([$p['brand'] ?? '', $p['variation'] ?? '', $p['unit'] ?? '']);
                            echo htmlspecialchars(implode(' · ', $meta));
                            ?>
                        </td>
                        <td class="text-end"><span class="badge bg-light text-dark"><?= formatQty($p['total_quantity']) ?></span></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- Modals -->
<div class="modal fade" id="stockAlertsModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i>Stock Alerts</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
  <ul class="nav nav-tabs mb-4"><li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#outOfStock">Out of Stock</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#lowStock">Low Stock</button></li></ul>
  <div class="tab-content">
    <div class="tab-pane fade show active" id="outOfStock">
      <div class="table-container"><table class="table mb-0"><thead><tr><th>Product Details</th><th class="text-center">Stock</th></tr></thead><tbody>
        <?php foreach ($outOfStockProducts as $p): ?>
          <tr>
            <td>
              <strong><?= htmlspecialchars($p['product_name']) ?></strong>
              <br>
                <?php
                $meta = array_filter([$p['brand'] ?? '', $p['variation'] ?? '', $p['unit'] ?? '']);
                echo htmlspecialchars(implode(' · ', $meta));
                ?>
            </td>
            <td class="text-center"><span class="status-pill danger">0</span></td>
          </tr>
        <?php endforeach; ?>
      </tbody></table></div>
    </div>
    <div class="tab-pane fade" id="lowStock">
      <div class="table-container"><table class="table mb-0"><thead><tr><th>Product Details</th><th class="text-center">Stock</th></tr></thead><tbody>
        <?php foreach ($lowStockProducts as $p): ?>
          <tr>
            <td>
              <strong><?= htmlspecialchars($p['product_name']) ?></strong>
              <br>
                <?php
                $meta = array_filter([$p['brand'] ?? '', $p['variation'] ?? '', $p['unit'] ?? '']);
                echo htmlspecialchars(implode(' · ', $meta));
                ?>
            </td>
            <td class="text-center"><span class="status-pill warning"><?= formatQty($p['stock']) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody></table></div>
    </div>
  </div>
</div></div></div></div>

<div class="modal fade" id="expiryAlertsModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"><i class="bi bi-hourglass-split me-2"></i>Expiry Alerts</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
  <div class="table-container"><table class="table mb-0"><thead><tr><th>Product Details</th><th>Batch Qty</th><th>Expiry Date</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($expiryProducts as $e): $isExp = $e['expiry_date'] < date('Y-m-d'); ?>
      <tr>
        <td>
          <strong><?= htmlspecialchars($e['product_name']) ?></strong>
          <br>
            <?php
            $meta = array_filter([$e['brand'] ?? '', $e['variation'] ?? '', $e['unit'] ?? '']);
            echo htmlspecialchars(implode(' · ', $meta));
            ?>
        </td>
        <td><?= formatQty($e['batch_qty']) ?></td>
        <td><?= date('M d, Y', strtotime($e['expiry_date'])) ?></td>
        <td><span class="status-pill <?= $isExp ? 'danger' : 'warning' ?>"><?= $isExp ? 'Expired' : 'Near' ?></span></td>
      </tr>
    <?php endforeach; ?>
  </tbody></table></div>
</div></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const monthlyUnitsData = <?= json_encode($monthly_units_data) ?>;
  new Chart(document.getElementById('monthlyUnitsChart'), {
    type: 'line',
    data: {
      labels: monthlyUnitsData.map(d => {
        const [year, month] = d.month.split('-');
        const date = new Date(year, month - 1);
        return date.toLocaleString('default', { month: 'short', year: '2-digit' });
      }),
      datasets: [{ label: 'Units Sold', data: monthlyUnitsData.map(d => d.total_units), borderColor: '#1a58ec', backgroundColor: 'rgba(26,88,236,0.1)', fill: true, tension: 0.4 }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
  });

  const slowTableBody = document.getElementById('slowTableBody');
  const slowLoading = document.getElementById('slowLoading');
  document.querySelectorAll('.slow-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      document.querySelectorAll('.slow-btn').forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      const time = this.dataset.time;
      slowLoading.classList.remove('d-none');
      fetch(`staff_dashboard.php?ajax_slow_moving=1&timeframe=${time}`)
        .then(r => r.json())
        .then(data => {
          slowTableBody.innerHTML = data.length ? data.map(p => {
            const q = parseFloat(p.total_quantity);
            const qFormatted = (q % 1 === 0) ? parseInt(q) : parseFloat(q.toFixed(4));
            const meta = [p.brand, p.variation, p.unit].filter(x => x && x !== 'N/A' && x !== 'None').join(' · ');
            return `<tr><td><strong>${p.product_name}</strong><br><small class="text-muted">${meta}</small></td><td class="text-end"><span class="badge bg-light text-dark">${qFormatted.toLocaleString()}</span></td></tr>`;
          }).join('') : '<tr><td colspan="2" class="text-center py-4">No data</td></tr>';
        })
        .finally(() => slowLoading.classList.add('d-none'));
    });
  });
</script>
</body>
</html>