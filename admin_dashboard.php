<?php
include 'project.php';
session_start();

/* ============================================================
   ✅ TIMEZONE FIX (PHILIPPINES)
   ============================================================ */
date_default_timezone_set('Asia/Manila');
$conn->query("SET time_zone = '+08:00'");

/* ============================================================
   ✅ AUTH
   ============================================================ */
if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$role     = $_SESSION['role'] ?? 'Admin';

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

/* ============================================================
   ✅ STOCK OUT EXPIRED BATCH (from Expiry Modal)
   NOTE: Assumes stock_history primary key column name is `id`
         and stock_out_history has: (product_id, quantity, reason, created_at)
   ============================================================ */
if (isset($_POST['stockout_expired']) && ($_SESSION['role'] ?? '') === 'admin') {

    $product_id = (int)($_POST['product_id'] ?? 0);
    $stock_history_id = (int)($_POST['stock_history_id'] ?? 0);

    if ($product_id > 0 && $stock_history_id > 0) {

        // ✅ Get expired batch qty + supplier_price (unit cost)
        $stmt = $conn->prepare("
            SELECT
                sh.quantity,
                sh.expiry_date,
                COALESCE(p.supplier_price, 0) AS supplier_price
            FROM stock_history sh
            JOIN products p ON p.product_id = sh.product_id
            WHERE sh.id = ?
              AND sh.product_id = ?
              AND sh.quantity > 0
              AND sh.expiry_date IS NOT NULL
              AND sh.expiry_date < CURDATE()
            LIMIT 1
        ");
        $stmt->bind_param("ii", $stock_history_id, $product_id);
        $stmt->execute();
        $batch = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($batch) {
            $batchQty   = (float)$batch['quantity'];
            $unitCost   = (float)$batch['supplier_price']; // ✅ correct variable
            $totalCost  = $batchQty * $unitCost;

            $conn->begin_transaction();
            try {
                // 1) Deduct from products.stock
                $stmt1 = $conn->prepare("
                    UPDATE products
                    SET stock = GREATEST(stock - ?, 0)
                    WHERE product_id = ?
                ");
                $stmt1->bind_param("di", $batchQty, $product_id);
                $stmt1->execute();
                $stmt1->close();

                // 2) Mark batch qty to 0 so it won't appear again
                $stmt2 = $conn->prepare("
                    UPDATE stock_history
                    SET quantity = 0
                    WHERE id = ?
                ");
                $stmt2->bind_param("i", $stock_history_id);
                $stmt2->execute();
                $stmt2->close();

                // 3) Insert to stock_out with cost + user
                $reason = "Expired batch stock-out";
                $stocked_by = $_SESSION['username'] ?? 'admin';

                $stmt3 = $conn->prepare("
                    INSERT INTO stock_out (product_id, quantity, supplier_price, total_cost, reason, stocked_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt3->bind_param("idddss", $product_id, $batchQty, $unitCost, $totalCost, $reason, $stocked_by);
                $stmt3->execute();
                $stmt3->close();

                $conn->commit();

                $_SESSION['success'] =
                    "Expired batch stocked out. Qty: {$batchQty} | Unit Cost: ₱" . number_format($unitCost, 2) .
                    " | Total Cost: ₱" . number_format($totalCost, 2);

            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['error'] = "❌ Stock-out failed: " . $e->getMessage();
            }

        } else {
            $_SESSION['error'] = "❌ Invalid batch or not expired / already zero.";
        }

    } else {
        $_SESSION['error'] = "❌ Missing product/batch ID.";
    }

    header("Location: admin_dashboard.php");
    exit();
}

/* ============================================================
   1) BASIC COUNTS
   ============================================================ */
$total_products_res = $conn->query("SELECT COUNT(*) AS total FROM products");
$total_products = $total_products_res ? (int)($total_products_res->fetch_assoc()['total'] ?? 0) : 0;

$total_categories_res = $conn->query("SELECT COUNT(*) AS total FROM categories");
$total_categories = $total_categories_res ? (int)($total_categories_res->fetch_assoc()['total'] ?? 0) : 0;

$total_subcategories_res = $conn->query("SELECT COUNT(*) AS total FROM subcategories");
$total_subcategories = $total_subcategories_res ? (int)($total_subcategories_res->fetch_assoc()['total'] ?? 0) : 0;

/* ============================================================
   2) LOW STOCK (MATCH products.php LOGIC)
   Low = reorder_level > 0 AND stock <= reorder_level AND stock > 0
   ============================================================ */
$low_stock_res = $conn->query("
    SELECT COUNT(*) AS total
    FROM products
    WHERE reorder_level > 0 AND stock <= reorder_level AND stock > 0
");
$low_stock_count = $low_stock_res ? (int)($low_stock_res->fetch_assoc()['total'] ?? 0) : 0;

$low_stock_list_res = $conn->query("
    SELECT 
        p.product_id, p.product_name, p.brand, p.variation, p.unit, p.stock, p.reorder_level, p.supplier_price,
        (SELECT MIN(expiry_date) FROM stock_history WHERE product_id = p.product_id AND quantity > 0 AND expiry_date IS NOT NULL) AS nearest_expiry
    FROM products p
    WHERE p.reorder_level > 0 AND p.stock <= p.reorder_level AND p.stock > 0
    ORDER BY p.stock ASC, p.product_name ASC
");
$lowStockProducts = [];
if ($low_stock_list_res) {
    while ($row = $low_stock_list_res->fetch_assoc()) {
        $lowStockProducts[] = $row;
    }
}

/* ============================================================
   2B) OUT OF STOCK PRODUCTS
   ============================================================ */
$out_of_stock_res = $conn->query("
    SELECT product_id, product_name, brand, variation, unit, stock, reorder_level, category_id, supplier_price
    FROM products
    WHERE stock = 0
    ORDER BY product_name ASC
");
$outOfStockProducts = [];
if ($out_of_stock_res) {
    while ($row = $out_of_stock_res->fetch_assoc()) {
        $outOfStockProducts[] = $row;
    }
}

/* ============================================================
   2C) PRODUCTS BY CATEGORY WITH STOCK INFO
   ============================================================ */
$products_by_category_res = $conn->query("
    SELECT
        c.category_id,
        c.category_name,
        COUNT(p.product_id) AS total_products,
        SUM(p.stock) AS total_stock,
        SUM(CASE WHEN p.stock = 0 THEN 1 ELSE 0 END) AS out_of_stock_count,
        SUM(CASE WHEN p.reorder_level > 0 AND p.stock > 0 AND p.stock <= p.reorder_level THEN 1 ELSE 0 END) AS low_stock_count
    FROM categories c
    LEFT JOIN products p ON c.category_id = p.category_id
    GROUP BY c.category_id, c.category_name
    ORDER BY c.category_name ASC
");
$productsByCategory = [];
if ($products_by_category_res) {
    while ($row = $products_by_category_res->fetch_assoc()) {
        $productsByCategory[] = $row;
    }
}

/* ============================================================
   3) RETURNS COST (DEDUCT FROM REVENUE)
   - gross_return_val: original selling price sum
   - net_refund_val: proportional refund (after discount)
   ============================================================ */
$return_cost_today_res = $conn->query("
    SELECT 
        COALESCE(SUM(ri.quantity * (s.total_price / NULLIF(s.quantity,0))), 0) AS gross,
        COALESCE(SUM(
            (s.total_price / NULLIF((SELECT SUM(s2.total_price) FROM sales s2 WHERE s2.sale_group_id = r.original_sale_group_id), 0))
            * sp.total_amount 
            * (ri.quantity / NULLIF(s.quantity, 0))
        ), 0) AS net
    FROM returns r
    JOIN return_items ri ON r.return_id = ri.return_id
    JOIN sales s ON r.original_sale_group_id = s.sale_group_id AND ri.product_id = s.product_id
    JOIN sale_payments sp ON r.original_sale_group_id = sp.sale_group_id
    WHERE DATE(r.created_at) = CURDATE()
");
$ret_today = $return_cost_today_res ? $return_cost_today_res->fetch_assoc() : ['gross'=>0, 'net'=>0, 'total_returns'=>0];
$return_cost_today = (float)$ret_today['net'];
$gross_return_today = (float)$ret_today['gross'];

$return_cost_week_res = $conn->query("
    SELECT 
        COALESCE(SUM(ri.quantity * (s.total_price / NULLIF(s.quantity,0))), 0) AS gross,
        COALESCE(SUM(
            (s.total_price / NULLIF((SELECT SUM(s2.total_price) FROM sales s2 WHERE s2.sale_group_id = r.original_sale_group_id), 0))
            * sp.total_amount 
            * (ri.quantity / NULLIF(s.quantity, 0))
        ), 0) AS net
    FROM returns r
    JOIN return_items ri ON r.return_id = ri.return_id
    JOIN sales s ON r.original_sale_group_id = s.sale_group_id AND ri.product_id = s.product_id
    JOIN sale_payments sp ON r.original_sale_group_id = sp.sale_group_id
    WHERE YEARWEEK(r.created_at, 1) = YEARWEEK(CURDATE(), 1)
");
$ret_week = $return_cost_week_res ? $return_cost_week_res->fetch_assoc() : ['gross'=>0, 'net'=>0, 'total_returns'=>0];
$return_cost_week = (float)$ret_week['net'];

$return_cost_month_res = $conn->query("
    SELECT 
        COUNT(DISTINCT r.return_id) AS total_returns,
        COALESCE(SUM(ri.quantity * (s.total_price / NULLIF(s.quantity,0))), 0) AS gross,
        COALESCE(SUM(
            (s.total_price / NULLIF((SELECT SUM(s2.total_price) FROM sales s2 WHERE s2.sale_group_id = r.original_sale_group_id), 0))
            * sp.total_amount 
            * (ri.quantity / NULLIF(s.quantity, 0))
        ), 0) AS net
    FROM returns r
    JOIN return_items ri ON r.return_id = ri.return_id
    JOIN sales s ON r.original_sale_group_id = s.sale_group_id AND ri.product_id = s.product_id
    JOIN sale_payments sp ON r.original_sale_group_id = sp.sale_group_id
    WHERE MONTH(r.created_at) = MONTH(CURDATE()) AND YEAR(r.created_at) = YEAR(CURDATE())
");
$ret_month = $return_cost_month_res ? $return_cost_month_res->fetch_assoc() : ['gross'=>0, 'net'=>0, 'total_returns'=>0];
$return_cost_month = (float)$ret_month['net'];
$gross_return_month = (float)$ret_month['gross'];

$return_cost_total_res = $conn->query("
    SELECT 
        COALESCE(SUM(ri.quantity * (s.total_price / NULLIF(s.quantity,0))), 0) AS gross,
        COALESCE(SUM(
            (s.total_price / NULLIF((SELECT SUM(s2.total_price) FROM sales s2 WHERE s2.sale_group_id = r.original_sale_group_id), 0))
            * sp.total_amount 
            * (ri.quantity / NULLIF(s.quantity, 0))
        ), 0) AS net
    FROM returns r
    JOIN return_items ri ON r.return_id = ri.return_id
    JOIN sales s ON r.original_sale_group_id = s.sale_group_id AND ri.product_id = s.product_id
    JOIN sale_payments sp ON r.original_sale_group_id = sp.sale_group_id
");
$ret_total = $return_cost_total_res ? $return_cost_total_res->fetch_assoc() : ['gross'=>0, 'net'=>0, 'total_returns'=>0];
$return_cost_total = (float)$ret_total['net'];

/* ============================================================
   4) RETURNS KPI THIS MONTH (COUNT + COST)
   ============================================================ */
$monthly_returns_count = $ret_month['total_returns'] ?? 0;
$monthly_returns_cost  = (float)$ret_month['net'];
$monthly_returns_gross = (float)$ret_month['gross'];

/* ============================================================
   5) REVENUE KPIs (GROSS then NET = GROSS - RETURNS COST)
   ============================================================ */
$total_sales_res = $conn->query("SELECT COALESCE(SUM(sp.total_amount),0) AS total FROM sale_payments sp");
$total_sales_gross = $total_sales_res ? (float)($total_sales_res->fetch_assoc()['total'] ?? 0) : 0;

$today_sales_res = $conn->query("
     SELECT COALESCE(SUM(sp.total_amount),0) AS total
     FROM sale_payments sp
     JOIN sale_groups sg ON sp.sale_group_id = sg.sale_group_id
     WHERE DATE(sg.created_at) = CURDATE()
");
$today_sales_gross = $today_sales_res ? (float)($today_sales_res->fetch_assoc()['total'] ?? 0) : 0;

$week_sales_res = $conn->query("
     SELECT COALESCE(SUM(sp.total_amount),0) AS total
     FROM sale_payments sp
     JOIN sale_groups sg ON sp.sale_group_id = sg.sale_group_id
     WHERE YEARWEEK(sg.created_at, 1) = YEARWEEK(CURDATE(), 1)
");
$week_sales_gross = $week_sales_res ? (float)($week_sales_res->fetch_assoc()['total'] ?? 0) : 0;

$month_sales_res = $conn->query("
     SELECT COALESCE(SUM(sp.total_amount),0) AS total
     FROM sale_payments sp
     JOIN sale_groups sg ON sp.sale_group_id = sg.sale_group_id
     WHERE MONTH(sg.created_at) = MONTH(CURDATE())
       AND YEAR(sg.created_at) = YEAR(CURDATE())
");
$month_sales_gross = $month_sales_res ? (float)($month_sales_res->fetch_assoc()['total'] ?? 0) : 0;

$today_sales = max(0, $today_sales_gross - $return_cost_today);
$week_sales  = max(0, $week_sales_gross - $return_cost_week);
$month_sales = max(0, $month_sales_gross - $return_cost_month);
$total_sales = max(0, $total_sales_gross - $return_cost_total);

/* ============================================================
   6) SALES BREAKDOWN (display cost, revenue, and profit)
   ============================================================ */
$daily_roi_data = $conn->query("
    SELECT 
        COALESCE(SUM(s.quantity * p.supplier_price), 0) AS total_cost,
        COALESCE(SUM(s.quantity * p.selling_price), 0) AS total_revenue
    FROM sales s
    JOIN sale_groups sg ON s.sale_group_id = sg.sale_group_id
    JOIN products p ON s.product_id = p.product_id
    WHERE DATE(sg.created_at) = CURDATE()
");
$daily_data = $daily_roi_data ? $daily_roi_data->fetch_assoc() : ['total_cost' => 0, 'total_revenue' => 0];
$daily_cost = (float)$daily_data['total_cost'];
$daily_revenue = (float)$daily_data['total_revenue'];
$daily_profit = $daily_revenue - $daily_cost;

$weekly_roi_data = $conn->query("
    SELECT 
        COALESCE(SUM(s.quantity * p.supplier_price), 0) AS total_cost,
        COALESCE(SUM(s.quantity * p.selling_price), 0) AS total_revenue
    FROM sales s
    JOIN sale_groups sg ON s.sale_group_id = sg.sale_group_id
    JOIN products p ON s.product_id = p.product_id
    WHERE YEARWEEK(sg.created_at, 1) = YEARWEEK(CURDATE(), 1)
");
$weekly_data = $weekly_roi_data ? $weekly_roi_data->fetch_assoc() : ['total_cost' => 0, 'total_revenue' => 0];
$weekly_cost = (float)$weekly_data['total_cost'];
$weekly_revenue = (float)$weekly_data['total_revenue'];
$weekly_profit = $weekly_revenue - $weekly_cost;

$monthly_roi_data = $conn->query("
    SELECT 
        COALESCE(SUM(s.quantity * p.supplier_price), 0) AS total_cost,
        COALESCE(SUM(s.quantity * p.selling_price), 0) AS total_revenue
    FROM sales s
    JOIN sale_groups sg ON s.sale_group_id = sg.sale_group_id
    JOIN products p ON s.product_id = p.product_id
    WHERE MONTH(sg.created_at) = MONTH(CURDATE())
      AND YEAR(sg.created_at) = YEAR(CURDATE())
");
$monthly_data = $monthly_roi_data ? $monthly_roi_data->fetch_assoc() : ['total_cost' => 0, 'total_revenue' => 0];
$monthly_cost = (float)$monthly_data['total_cost'];
$monthly_revenue = (float)$monthly_data['total_revenue'];
$monthly_profit = $monthly_revenue - $monthly_cost;

/* ============================================================
   7) EXPIRY / EXPIRING (BATCH-BASED, ONLY WITHIN 30 DAYS)
   ✅ Shows ONLY batches that are expired or within 30 days
   ============================================================ */
$expiry_days = 30;

$expiry_count_res = $conn->query("
    SELECT
      COALESCE(SUM(CASE WHEN sh.expiry_date < CURDATE() THEN 1 ELSE 0 END), 0) AS expired_count,
      COALESCE(SUM(CASE WHEN sh.expiry_date >= CURDATE()
                        AND sh.expiry_date <= DATE_ADD(CURDATE(), INTERVAL {$expiry_days} DAY)
                       THEN 1 ELSE 0 END), 0) AS expiring_count
    FROM stock_history sh
    WHERE sh.expiry_date IS NOT NULL
      AND sh.quantity > 0
      AND (
            sh.expiry_date < CURDATE()
         OR (sh.expiry_date >= CURDATE()
             AND sh.expiry_date <= DATE_ADD(CURDATE(), INTERVAL {$expiry_days} DAY))
      )
");
$expired_count = 0;
$expiring_count = 0;
if ($expiry_count_res) {
    $er = $expiry_count_res->fetch_assoc();
    $expired_count = (int)($er['expired_count'] ?? 0);
    $expiring_count = (int)($er['expiring_count'] ?? 0);
}
$expiry_total = $expired_count + $expiring_count;

$expiry_list_res = $conn->query("
    SELECT
      p.product_id,
      p.product_name,
      p.brand,
      p.variation,
      p.unit,
      sh.id AS stock_history_id,
      sh.quantity AS batch_qty,
      sh.expiry_date
    FROM stock_history sh
    JOIN products p ON p.product_id = sh.product_id
    WHERE sh.expiry_date IS NOT NULL
      AND sh.quantity > 0
      AND (
            sh.expiry_date < CURDATE()
         OR (sh.expiry_date >= CURDATE()
             AND sh.expiry_date <= DATE_ADD(CURDATE(), INTERVAL {$expiry_days} DAY))
      )
    ORDER BY sh.expiry_date ASC, p.product_name ASC
");
$expiryProducts = [];
if ($expiry_list_res) {
    while ($row = $expiry_list_res->fetch_assoc()) {
        $expiryProducts[] = $row;
    }
}

/* ============================================================
   8) CHARTS
   ============================================================ */
// Top 10 Selling Products (Including Brand & Variation)
$top_products_result = $conn->query("
    SELECT p.product_name, p.brand, p.variation, p.unit, SUM(s.quantity) AS total_quantity
    FROM sales s
    JOIN products p ON s.product_id = p.product_id
    GROUP BY p.product_id, p.product_name, p.brand, p.variation, p.unit
    ORDER BY total_quantity DESC
    LIMIT 10
");
$top_products_data = [];
if ($top_products_result) {
    while ($row = $top_products_result->fetch_assoc()) $top_products_data[] = $row;
}

// Monthly Sales last 6 months (NET: subtract returns per month)
$monthly_sales_result = $conn->query("
    SELECT 
        DATE_FORMAT(sg.created_at, '%Y-%m') AS month,
        COALESCE(SUM(sp.total_amount),0) AS gross_revenue
    FROM sale_groups sg
    JOIN sale_payments sp ON sg.sale_group_id = sp.sale_group_id
    WHERE sg.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY month ASC
");
$monthly_sales_data = [];
if ($monthly_sales_result) {
    while ($row = $monthly_sales_result->fetch_assoc()) {
        $monthly_sales_data[] = [
            'month' => $row['month'],
            'monthly_revenue' => (float)$row['gross_revenue']
        ];
    }
}

$monthly_returns_cost_6m_res = $conn->query("
    SELECT 
        DATE_FORMAT(r.created_at, '%Y-%m') AS month,
        COALESCE(SUM(ri.quantity * (s.total_price / NULLIF(s.quantity,0))),0) AS return_cost
    FROM returns r
    JOIN return_items ri ON r.return_id = ri.return_id
    JOIN sales s 
        ON s.sale_group_id = r.original_sale_group_id
       AND s.product_id = ri.product_id
    WHERE r.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month
");
$returns_by_month = [];
if ($monthly_returns_cost_6m_res) {
    while ($r = $monthly_returns_cost_6m_res->fetch_assoc()) {
        $returns_by_month[$r['month']] = (float)$r['return_cost'];
    }
}

foreach ($monthly_sales_data as &$m) {
    $monthKey = $m['month'];
    $retCost = $returns_by_month[$monthKey] ?? 0;
    $m['monthly_revenue'] = max(0, (float)$m['monthly_revenue'] - $retCost);
}
unset($m);

// Top 5 Suppliers
$top_suppliers_result = $conn->query("
    SELECT 
        s.supplier_id,
        s.supplier_name,
        SUM(sh.quantity) AS total_supplied_quantity
    FROM stock_history sh
    JOIN suppliers s ON sh.supplier_id = s.supplier_id
    GROUP BY s.supplier_id, s.supplier_name
    ORDER BY total_supplied_quantity DESC
    LIMIT 5
");
$top_suppliers_data = [];
if ($top_suppliers_result) {
    while ($row = $top_suppliers_result->fetch_assoc()) $top_suppliers_data[] = $row;
}

// Fetch top 5 items per supplier
if (!empty($top_suppliers_data)) {
    $supplier_ids = array_column($top_suppliers_data, 'supplier_id');
    $placeholders = implode(',', array_fill(0, count($supplier_ids), '?'));
    $types_str = str_repeat('i', count($supplier_ids));

    $stmt = $conn->prepare("
        SELECT 
            sh.supplier_id,
            p.product_name,
            p.brand,
            p.variation,
            SUM(sh.quantity) AS total_qty
        FROM stock_history sh
        JOIN products p ON sh.product_id = p.product_id
        WHERE sh.supplier_id IN ($placeholders)
        GROUP BY sh.supplier_id, p.product_id, p.product_name, p.brand, p.variation
        ORDER BY sh.supplier_id, total_qty DESC
    ");
    $stmt->bind_param($types_str, ...$supplier_ids);
    $stmt->execute();
    $items_result = $stmt->get_result();

    $supplier_top_items_map = [];
    while ($row = $items_result->fetch_assoc()) {
        $sid = $row['supplier_id'];
        if (!isset($supplier_top_items_map[$sid])) $supplier_top_items_map[$sid] = [];
        if (count($supplier_top_items_map[$sid]) < 3) {
            $meta = array_filter([$row['brand'] ?? '', $row['variation'] ?? '']);
            $label = $row['product_name'];
            if (!empty($meta)) $label .= ' (' . implode(' · ', $meta) . ')';
            $supplier_top_items_map[$sid][] = $label . ' — ' . formatQty($row['total_qty']) . ' units';
        }
    }
    $stmt->close();

    foreach ($top_suppliers_data as &$sup) {
        $sup['top_items'] = $supplier_top_items_map[$sup['supplier_id']] ?? [];
    }
    unset($sup);
}

$top_suppliers_json = json_encode($top_suppliers_data);

// Count total products with 0 sales in the last 30 days (Slow-moving count)
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

// Initial Load for Slow-Moving Products (Monthly by default)
$slow_timeframe = 'monthly';
$slow_interval  = '1 MONTH';
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

/* ============================================================
   9) RECENT TRANSACTIONS (FOR DASHBOARD TABLES)
   ============================================================ */
// Recent Sales (Last 5)
$recent_sales_res = $conn->query("
    SELECT 
        sg.sale_group_id, sg.customer_name, sg.created_at, sp.total_amount, 
        COALESCE(u.full_name, sg.created_by) AS cashier_name,
        (SELECT SUM(quantity) FROM sales WHERE sale_group_id = sg.sale_group_id) AS total_qty_sold,
        (SELECT COALESCE(SUM(ri.quantity), 0) FROM returns r JOIN return_items ri ON r.return_id = ri.return_id WHERE r.original_sale_group_id = sg.sale_group_id) AS total_qty_returned
    FROM sale_groups sg
    JOIN sale_payments sp ON sg.sale_group_id = sp.sale_group_id
    LEFT JOIN users u ON sg.created_by = u.username
    ORDER BY sg.created_at DESC LIMIT 5
");
$recentSales = $recent_sales_res ? $recent_sales_res->fetch_all(MYSQLI_ASSOC) : [];

// Recent Returns (Last 5)
$recent_returns_res = $conn->query("
    SELECT 
        r.return_id, r.original_sale_group_id, r.created_at, r.return_reason,
        COALESCE(u.full_name, r.processed_by) AS processed_by,
        COALESCE(SUM(
            (s.total_price / NULLIF((SELECT SUM(s2.total_price) FROM sales s2 WHERE s2.sale_group_id = r.original_sale_group_id), 0))
            * sp.total_amount * (ri.quantity / NULLIF(s.quantity, 0))
        ), 0) AS refund_amount
    FROM returns r
    JOIN return_items ri ON r.return_id = ri.return_id
    JOIN sales s ON r.original_sale_group_id = s.sale_group_id AND ri.product_id = s.product_id
    JOIN sale_payments sp ON r.original_sale_group_id = sp.sale_group_id
    LEFT JOIN users u ON r.processed_by = u.username
    GROUP BY r.return_id ORDER BY r.created_at DESC LIMIT 5
");
$recentReturns = $recent_returns_res ? $recent_returns_res->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard — K&J B Hardware</title>
 <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin1.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
             --card-radius:  16px;
            --card-shadow:  0 2px 16px rgba(0,0,0,0.07);
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
        
        /* Loading Overlay for Reactive Sections */
        .position-relative { position: relative; }
        .loading-overlay {
          position: absolute; top:0; left:0; width:100%; height:100%;
          background: rgba(255,255,255,0.7);
          display: flex; align-items: center; justify-content: center;
          z-index: 10; border-radius: var(--card-radius);
          backdrop-filter: blur(2px);
        }

        *,*::before,*::after{box-sizing:border-box;}
        body { font-family:var(--font); background:var(--bg); color:var(--ink); font-size:14px; }
        .content { background:var(--bg); min-height:100vh; }
        .main-wrap { padding:28px 28px 64px; }
        .dropdown-toggle::after { display:none; }


    /* ── Dashboard header ── */
    .dash-header {
      background: linear-gradient(135deg, #0f2557 0%, #1a58ec 60%, #2563eb 100%);
      border-radius: 0 0 28px 28px;
      padding: 28px 32px 32px;
      margin-bottom: 32px;
      position: relative;
      overflow: hidden;
    }
    .dash-header::before {
      content: '';
      position: absolute;
      width: 320px; height: 320px;
      background: rgba(255,255,255,0.05);
      border-radius: 50%;
      top: -100px; right: -60px;
      pointer-events: none;
    }
    .dash-header::after {
      content: '';
      position: absolute;
      width: 180px; height: 180px;
      background: rgba(255,255,255,0.04);
      border-radius: 50%;
      bottom: -60px; left: 30%;
      pointer-events: none;
    }
    .dash-header h1 { font-size: 1.6rem; font-weight: 800; color: #fff; margin: 0; }
    .dash-header .sub { font-size: 13px; color: rgba(255,255,255,0.65); margin-top: 4px; }
    .dash-header .date-pill {
      background: rgba(255,255,255,0.15);
      border: 1px solid rgba(255,255,255,0.2);
      border-radius: 20px;
      padding: 6px 14px;
      font-size: 12.5px;
      color: rgba(255,255,255,0.9);
      font-weight: 500;
    }

    /* ── Section label ── */
    .section-label {
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.09em;
      text-transform: uppercase;
      color: #94a3b8;
      margin-bottom: 14px;
    }

    /* ── KPI Cards ── */
    .kpi-new {
      background: #fff;
      border-radius: var(--card-radius);
      box-shadow: var(--card-shadow);
      padding: 20px 22px;
      display: flex;
      align-items: center;
      gap: 16px;
      transition: transform 0.18s, box-shadow 0.18s;
      border: 1px solid rgba(0,0,0,0.04);
      cursor: default;
    }
    .kpi-new:hover { transform: translateY(-3px); box-shadow: 0 8px 28px rgba(0,0,0,0.1); }
    .kpi-new.clickable { cursor: pointer; }
    .kpi-icon-sq {
      width: 52px; height: 52px;
      border-radius: 13px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.4rem;
      flex-shrink: 0;
    }
    .kpi-icon-sq.blue   { background: rgba(26,88,236,0.12);  color: #1a58ec; }
    .kpi-icon-sq.green  { background: rgba(16,185,129,0.12); color: #10b981; }
    .kpi-icon-sq.cyan   { background: rgba(6,182,212,0.12);  color: #06b6d4; }
    .kpi-icon-sq.red    { background: rgba(239,68,68,0.12);   color: #ef4444; }
    .kpi-icon-sq.amber  { background: rgba(245,158,11,0.12);  color: #f59e0b; }
    .kpi-icon-sq.purple { background: rgba(139,92,246,0.12);  color: #8b5cf6; }
    .kpi-icon-sq.danger-active { background: rgba(239,68,68,0.18); color: #ef4444; }
    .kpi-icon-sq.warn-active   { background: rgba(245,158,11,0.18); color: #d97706; }
    .kpi-body { flex: 1; min-width: 0; }
    .kpi-label { font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
    .kpi-value { font-size: 1.6rem; font-weight: 800; color: #0f172a; line-height: 1; }
    .kpi-sub   { font-size: 11.5px; color: #94a3b8; margin-top: 4px; }
    .kpi-sub.warn  { color: #ef4444; font-weight: 600; }
    .kpi-sub.ok    { color: #10b981; font-weight: 600; }

    /* ── Profit Cards ── */
    .profit-card {
      background: #fff;
      border-radius: var(--card-radius);
      box-shadow: var(--card-shadow);
      padding: 20px 22px;
      border-left: 4px solid transparent;
      transition: transform 0.18s;
    }
    .profit-card:hover { transform: translateY(-2px); }
    .profit-card.monthly { border-left-color: #1a58ec; }
    .profit-card.weekly  { border-left-color: #10b981; }
    .profit-card.daily   { border-left-color: #06b6d4; }
    .profit-card .pc-label { font-size: 11.5px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; margin-bottom: 6px; }
    .profit-card.monthly .pc-label { color: #1a58ec; }
    .profit-card.weekly  .pc-label { color: #10b981; }
    .profit-card.daily   .pc-label { color: #06b6d4; }
    .profit-card .pc-value { font-size: 1.55rem; font-weight: 800; color: #0f172a; }
    .profit-card .pc-sub   { font-size: 11px; color: #94a3b8; margin-top: 4px; }

    /* ── Chart Cards ── */
    .chart-card-new {
      background: #fff;
      border-radius: var(--card-radius);
      box-shadow: var(--card-shadow);
      overflow: hidden;
    }
    .chart-card-new .cc-head {
      padding: 16px 20px 12px;
      border-bottom: 1px solid #f1f5f9;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .chart-card-new .cc-icon {
      width: 34px; height: 34px;
      border-radius: 9px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1rem;
      flex-shrink: 0;
    }
    .chart-card-new .cc-head h6 { font-size: 14px; font-weight: 700; color: #0f172a; margin: 0; }
    .chart-card-new .cc-head small { font-size: 11.5px; color: #94a3b8; }
    .chart-card-new .cc-body { padding: 20px; }

    /* ── Alert section headers ── */
    .section-header {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 20px;
    }
    .section-header .sh-icon {
      width: 38px; height: 38px;
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.1rem;
    }
    .section-header h4 { font-size: 1rem; font-weight: 700; color: #0f172a; margin: 0; }

    /* ── Modals ── */
    .modal-content {
      border: none;
      border-radius: 24px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.15);
      overflow: hidden;
      background: #fff;
    }
    .modal-header {
      padding: 24px 32px;
      border-bottom: 1px solid #f1f5f9;
      background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
      color: #fff;
    }
    .modal-header .modal-title { font-size: 1.25rem; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 12px; }
    .modal-header .btn-close { filter: invert(1) grayscale(1); opacity: 0.8; }
    .modal-body { padding: 32px; background: #fff; }
    .modal-footer { padding: 20px 32px; border-top: 1px solid #f1f5f9; background: #f8fafc; }

    /* ── Tabs ── */
    .nav-tabs { border-bottom: 2px solid #f1f5f9; gap: 8px; }
    .nav-tabs .nav-link {
      border: none;
      padding: 12px 20px;
      font-size: 14px;
      font-weight: 600;
      color: #64748b;
      border-radius: 10px 10px 0 0;
      transition: all 0.2s;
      position: relative;
    }
    .nav-tabs .nav-link:hover { color: var(--accent); background: #f8fafc; }
    .nav-tabs .nav-link.active {
      color: var(--accent);
      background: transparent;
    }
    .nav-tabs .nav-link.active::after {
      content: '';
      position: absolute;
      bottom: -2px; left: 0; width: 100%; height: 2px;
      background: var(--accent);
    }

    /* ── Tables ── */
    .table-container { border-radius: 12px; border: 1px solid #f1f5f9; overflow: hidden; }
    .table thead th {
      background: #f8fafc;
      color: #475569;
      font-weight: 700;
      text-transform: uppercase;
      font-size: 11px;
      letter-spacing: 0.05em;
      padding: 14px 16px;
      border-bottom: 1px solid #f1f5f9;
    }
    .table tbody td { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; font-size: 13.5px; color: #1e293b; }
    .table tbody tr:last-child td { border-bottom: none; }
    .table-hover tbody tr:hover { background-color: #f8fafc; }

    /* ── Status Badges ── */
    .status-pill {
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.02em;
      text-transform: uppercase;
    }
    .status-pill.danger  { background: rgba(239,68,68,0.1); color: #ef4444; }
    .status-pill.warning { background: rgba(245,158,11,0.1); color: #d97706; }
    .status-pill.success { background: rgba(16,185,129,0.1); color: #10b981; }
    .status-pill.info    { background: rgba(59,130,246,0.1); color: #3b82f6; }
    .status-pill.muted   { background: rgba(148,163,184,0.1); color: #64748b; }

    .kpi-clickable { cursor: pointer; }

    /* ── Toast notifications ── */
    .toast-stack {
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 9999;
      display: flex;
      flex-direction: column;
      gap: 8px;
      min-width: 300px;
      max-width: 420px;
    }
    .toast-item {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      padding: 14px 16px;
      border-radius: var(--r);
      font-size: 0.82rem;
      font-weight: 500;
      box-shadow: var(--sh-lg);
      border: 1px solid transparent;
      animation: toastIn 0.3s cubic-bezier(0.22, 1, 0.36, 1);
    }
    @keyframes toastIn {
      from { opacity: 0; transform: translateX(24px); }
      to { opacity: 1; transform: translateX(0); }
    }
    .toast-item.success {
      background: #fff;
      border-color: #bbf7d0;
    }
    .toast-item.success .toast-icon {
      color: #059669;
    }
    .toast-item.error {
      background: #fff;
      border-color: #fecaca;
    }
    .toast-item.error .toast-icon {
      color: #dc2626;
    }
    .toast-item.warning {
      background: #fff;
      border-color: #fde68a;
    }
    .toast-item.warning .toast-icon {
      color: #d97706;
    }
    .toast-icon {
      font-size: 1.1rem;
      flex-shrink: 0;
      margin-top: 1px;
    }
    .toast-text {
      flex: 1;
      color: #334155;
    }
    .toast-close {
      background: none;
      border: none;
      color: #94a3b8;
      cursor: pointer;
      font-size: 0.9rem;
      padding: 0;
      line-height: 1;
    }
    .toast-close:hover {
      color: #64748b;
    }

    /* ── Confirm stock-out modal ── */
    #confirmStockOutModal .modal-content { border-radius: 22px; overflow: hidden; box-shadow: 0 18px 40px rgba(15,23,42,0.16); }
    #confirmStockOutModal .modal-header { padding: 22px 24px; }
    #confirmStockOutModal .modal-body { padding: 24px; }
    #confirmStockOutModal .modal-footer { padding: 18px 24px 22px; }
    #confirmStockOutModal .modal-body p { color: #475569; }
    #confirmStockOutModal .modal-body .bg-light { background: #f8fafc; }

    @media print {
      .sidebar, .btn, .no-print, .alert, nav, form, input, select, .pagination { display: none !important; }
      .content { margin-left: 0 !important; padding: 0 !important; }
    }
  </style>
</head>

<body>
<div class="toast-stack">
  <?php if (!empty($_SESSION['success'])): ?>
    <div class="toast-item success" role="alert" aria-live="polite" aria-atomic="true">
      <div class="toast-icon">
        <i class="bi bi-check-circle-fill"></i>
      </div>
      <div class="toast-text">
        <?= htmlspecialchars($_SESSION['success']) ?>
      </div>
      <button type="button" class="toast-close" data-bs-dismiss="toast" aria-label="Close">&times;</button>
    </div>
    <?php unset($_SESSION['success']); ?>
  <?php endif; ?>
  <?php if (!empty($_SESSION['error'])): ?>
    <div class="toast-item error" role="alert" aria-live="assertive" aria-atomic="true">
      <div class="toast-icon">
        <i class="bi bi-exclamation-circle-fill"></i>
      </div>
      <div class="toast-text">
        <?= htmlspecialchars($_SESSION['error']) ?>
      </div>
      <button type="button" class="toast-close" data-bs-dismiss="toast" aria-label="Close">&times;</button>
    </div>
    <?php unset($_SESSION['error']); ?>
  <?php endif; ?>
</div>
<div class="d-flex">

  <!-- SIDEBAR -->
  <div class="sidebar flex-column p-0" id="sidebar">
    <div class="sidebar-logo text-center">
      <img src="images/logo.png" alt="Inventory Logo">
      <h5 class="mt-2 text-white">Inventory System</h5>
    </div>
    <hr class="text-white">
    <ul class="nav flex-column">
      <li class="sidebar-title">Main</li>
      <li class="nav-item mb-2"><a class="nav-link active" href="admin_dashboard.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a></li>

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
      <li class="nav-item mb-2"><a class="nav-link" href="manageUser.php"><i class="bi bi-people me-2"></i> Manage Users</a></li>

      <li class="sidebar-title">Settings</li>
      <li class="nav-item mb-2"><a class="nav-link" href="settings.php"><i class="bi bi-gear me-2"></i> Settings</a></li>
      <li class="nav-item"><a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
    </ul>
  </div>

  <!-- MAIN CONTENT -->
  <div class="content flex-grow-1">

    <!-- ── Dashboard Header ── -->
    <div class="dash-header">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
          <h1><i class="bi bi-speedometer2 me-2"></i>Dashboard</h1>
          <div class="sub">Welcome back, <strong><?= htmlspecialchars($username) ?></strong> — here's your overview</div>
        </div>
        <div class="date-pill">
          <i class="bi bi-calendar3 me-2"></i><?= date('l, F j, Y') ?>
        </div>
      </div>
    </div>

    <div class="container-fluid px-4">

      <!-- ── Revenue KPIs ── -->
      <div class="section-label">Revenue Overview</div>
      <!-- ── Revenue & Returns Overview ── -->
      <!-- ── Revenue & Returns Overview ── -->
      <div class="row g-3 mb-4">
        <!-- Row 1: Sales -->
        <div class="col-lg-4 col-md-6">
          <div class="kpi-new">
            <div class="kpi-icon-sq blue shadow-sm"><i class="bi bi-bar-chart-line-fill"></i></div>
            <div class="kpi-body">
              <div class="kpi-label">Monthly Revenue</div>
              <div class="kpi-value">₱<?= number_format($month_sales, 2) ?></div>
              <div class="kpi-sub">Net after returns</div>
            </div>
          </div>
        </div>

        <div class="col-lg-4 col-md-6">
          <div class="kpi-new">
            <div class="kpi-icon-sq cyan shadow-sm"><i class="bi bi-calendar2-range"></i></div>
            <div class="kpi-body">
              <div class="kpi-label">Weekly Sales</div>
              <div class="kpi-value text-primary">₱<?= number_format($week_sales, 2) ?></div>
              <div class="kpi-sub">This week's performance</div>
            </div>
          </div>
        </div>

        <div class="col-lg-4 col-md-6">
          <div class="kpi-new">
            <div class="kpi-icon-sq green shadow-sm"><i class="bi bi-calendar-check"></i></div>
            <div class="kpi-body">
              <div class="kpi-label">Today's Sales</div>
              <div class="kpi-value">₱<?= number_format($today_sales, 2) ?></div>
              <div class="kpi-sub">Today's performance</div>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-3 mb-4">
        <!-- Row 2: Returns & Products -->
        <div class="col-lg-4 col-md-6">
          <div class="kpi-new">
            <div class="kpi-icon-sq purple shadow-sm"><i class="bi bi-arrow-return-left"></i></div>
            <div class="kpi-body">
              <div class="kpi-label">Returns (This Month)</div>
              <div class="kpi-value"><?= number_format($monthly_returns_count) ?></div>
              <div class="kpi-sub">Transactions processed</div>
            </div>
          </div>
        </div>

        <div class="col-lg-4 col-md-6">
          <div class="kpi-new">
            <div class="kpi-icon-sq red shadow-sm"><i class="bi bi-wallet2"></i></div>
            <div class="kpi-body">
              <div class="kpi-label">Total Refunds (This Month)</div>
              <div class="kpi-value text-danger">₱<?= number_format($monthly_returns_cost, 2) ?></div>
              <div class="kpi-sub">Prorated net refund</div>
            </div>
          </div>
        </div>

        <div class="col-lg-4 col-md-6">
          <div class="kpi-new">
            <div class="kpi-icon-sq amber shadow-sm"><i class="bi bi-box-seam"></i></div>
            <div class="kpi-body">
              <div class="kpi-label">Total Products</div>
              <div class="kpi-value"><?= number_format($total_products) ?></div>
              <div class="kpi-sub">Active in inventory</div>
            </div>
          </div>
        </div>
      </div>

      <!-- ── Alert KPIs ── -->
      <div class="section-label">Stock & Expiry Alerts</div>
      <div class="row g-3 mb-4">

        <!-- Low Stock -->
        <div class="col-lg-4 col-md-6">
          <div class="kpi-new clickable kpi-clickable"
               role="button" data-bs-toggle="modal" data-bs-target="#stockAlertsModal"
               title="Click to view stock alerts">
            <div class="kpi-icon-sq <?= $low_stock_count > 0 ? 'danger-active' : 'amber' ?>">
              <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
            <div class="kpi-body">
              <div class="kpi-label">Low Stock Items</div>
              <div class="kpi-value"><?= number_format($low_stock_count) ?></div>
              <div class="kpi-sub <?= $low_stock_count > 0 ? 'warn' : 'ok' ?>">
                <?= $low_stock_count > 0 ? '⚠ Action required — click to review' : '✓ All stock levels healthy' ?>
              </div>
            </div>
          </div>
        </div>


        <!-- Expiry -->
        <div class="col-lg-4 col-md-6">
          <div class="kpi-new clickable kpi-clickable"
               role="button" data-bs-toggle="modal" data-bs-target="#expiryAlertsModal"
               title="Click to view expiring/expired products">
            <div class="kpi-icon-sq <?= $expiry_total > 0 ? 'warn-active' : 'purple' ?>">
              <i class="bi bi-hourglass-split"></i>
            </div>
            <div class="kpi-body">
              <div class="kpi-label">Expiring / Expired</div>
              <div class="kpi-value"><?= number_format($expiry_total) ?></div>
              <div class="kpi-sub <?= $expiry_total > 0 ? 'warn' : '' ?>">
                Expired: <?= number_format($expired_count) ?> &nbsp;|&nbsp; Within <?= $expiry_days ?>d: <?= number_format($expiring_count) ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Slow Moving -->
        <div class="col-lg-4 col-md-6">
          <div class="kpi-new clickable"
               onclick="document.getElementById('slowMovingSection').scrollIntoView({behavior: 'smooth'})"
               title="Click to view slow moving items">
            <div class="kpi-icon-sq amber-lt text-amber" style="background:rgba(217,119,6,0.1); color:#d97706;">
              <i class="bi bi-graph-down"></i>
            </div>
            <div class="kpi-body">
              <div class="kpi-label">Slow-Moving Products</div>
              <div class="kpi-value"><?= number_format($slow_total_count) ?></div>
              <div class="kpi-sub">0 sales in last 30 days</div>
            </div>
          </div>
        </div>
      </div>


      <!-- ── Charts ── -->
      <div class="section-label">Sales &amp; Inventory Analytics</div>
      <div class="row g-4 mb-4">

        <div class="col-lg-6">
          <div class="chart-card-new">
            <div class="cc-head">
              <div class="cc-icon" style="background:rgba(26,88,236,0.1);color:#1a58ec;"><i class="bi bi-graph-up"></i></div>
              <div>
                <h6>Monthly Revenue (Last 6 Months)</h6>
                <small>Net — returns deducted</small>
              </div>
            </div>
            <div class="cc-body"><div style="height:300px;"><canvas id="monthlySalesChart"></canvas></div></div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="chart-card-new">
            <div class="cc-head">
              <div class="cc-icon" style="background:rgba(6,182,212,0.1);color:#06b6d4;"><i class="bi bi-truck-flatbed"></i></div>
              <div>
                <h6>Top Suppliers &amp; Key Items</h6>
                <small>Total units supplied</small>
              </div>
            </div>
            <div class="cc-body"><div style="height:300px;"><canvas id="topSuppliersChart"></canvas></div></div>
          </div>
        </div>

      </div>

      <!-- ── Reactive Slow-Moving Inventory ── -->
      <div class="row g-4 mb-5">
        <div class="col-12">
          <div class="chart-card-new position-relative" id="slowMovingSection">
            <!-- Loading Overlay -->
            <div class="loading-overlay d-none" id="slowLoading">
               <div class="spinner-border text-primary" role="status"></div>
            </div>

            <div class="cc-head d-flex justify-content-between align-items-center flex-wrap gap-3">
              <div class="d-flex align-items-center gap-2">
                <div class="cc-icon" style="background:rgba(100,116,139,0.1);color:#64748b;"><i class="bi bi-graph-down"></i></div>
                <div>
                  <h6>Slow-Moving Products</h6>
                  <small>Least sold items in the period</small>
                </div>
              </div>
              <div class="btn-group btn-group-sm">
                  <button type="button" class="btn btn-outline-secondary slow-btn" data-time="weekly">Weekly</button>
                  <button type="button" class="btn btn-outline-secondary slow-btn active" data-time="monthly">Monthly</button>
                  <button type="button" class="btn btn-outline-secondary slow-btn" data-time="yearly">Yearly</button>
              </div>
            </div>
            
            <div class="cc-body">
                <!-- Expanded Table -->
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto; border: 1px solid #f1f5f9; border-radius: 12px;">
                    <table class="table table-hover mb-0" style="font-size: 14px;">
                        <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                        <tr>
                            <th class="ps-4 border-0">Product Details</th>
                            <th class="text-end pe-4 border-0">Units Sold</th>
                        </tr>
                        </thead>
                        <tbody id="slowTableBody">
                        <?php if (empty($slow_products_data)): ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">No data available</td></tr>
                        <?php else: ?>
                            <?php foreach ($slow_products_data as $p): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold"><?= htmlspecialchars($p['product_name']) ?></div>
                                    <div class="small text-muted">
                                        <?php
                                        $meta = array_filter([$p['brand'] ?? '', $p['variation'] ?? '', $p['unit'] ?? '']);
                                        echo htmlspecialchars(implode(' · ', $meta));
                                        ?>
                                    </div>
                                </td>
                                <td class="text-end pe-4">
                                    <?= formatQty($p['total_quantity']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Keep Top Selling Products -->
      <div class="row g-4 mb-5">
        <div class="col-12">
          <div class="chart-card-new">
            <div class="cc-head">
              <div class="cc-icon" style="background:rgba(245,158,11,0.1);color:#f59e0b;"><i class="bi bi-trophy-fill"></i></div>
              <div>
                <h6>Top 10 Selling Products</h6>
                <small>Ranked by units sold</small>
              </div>
            </div>
            <div class="cc-body">
                <!-- Top Products Table -->
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto; border: 1px solid #f1f5f9; border-radius: 12px;">
                    <table class="table table-hover mb-0" style="font-size: 14px;">
                        <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                        <tr>
                            <th class="ps-4 border-0">Product Details</th>
                            <th class="text-end pe-4 border-0">Units Sold</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($top_products_data)): ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted">No sales data available</td></tr>
                        <?php else: ?>
                            <?php foreach ($top_products_data as $p): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold"><?= htmlspecialchars($p['product_name']) ?></div>
                                    <div class="small text-muted">
                                        <?php
                                        $meta = array_filter([$p['brand'] ?? '', $p['variation'] ?? '', $p['unit'] ?? '']);
                                        echo htmlspecialchars(implode(' · ', $meta));
                                        ?>
                                    </div>
                                </td>
                                <td class="text-end pe-4">
                                    <?= formatQty($p['total_quantity']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
          </div>
        </div>
      </div>
      <!-- ── Recent Activity Log ── -->
      <div class="section-label">Recent Activity (Latest 5 Records)</div>
      <div class="row g-4 mb-5">
        <!-- Recent Sales -->
        <div class="col-xl-6">
          <div class="chart-card-new h-100">
            <div class="cc-head d-flex justify-content-between">
              <div class="d-flex align-items-center gap-2">
                <div class="cc-icon" style="background:rgba(26,88,236,0.1);color:#1a58ec;"><i class="bi bi-cart-check"></i></div>
                <div>
                  <h6>Latest Sales Transactions</h6>
                  <small>Quick view of recent checkout activity</small>
                </div>
              </div>
              <a href="sales.php" class="btn btn-sm btn-link text-decoration-none p-0">View All</a>
            </div>
            <div class="cc-body p-0">
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 13.5px;">
                  <thead class="table-light">
                    <tr>
                      <th class="ps-4 border-0">Receipt</th>
                      <th class="border-0">Customer</th>
                      <th class="border-0 text-center">Amount</th>
                      <th class="border-0 text-center">Cashier</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($recentSales)): ?>
                      <tr><td colspan="4" class="text-center py-4 text-muted">No recent sales found.</td></tr>
                    <?php else: ?>
                      <?php foreach ($recentSales as $sale): ?>
                        <tr>
                          <td class="ps-4 font-monospace fw-bold text-primary">
                            #<?= $sale['sale_group_id'] ?>
                            <?php 
                                $sold = (float)($sale['total_qty_sold'] ?? 0);
                                $ret  = (float)($sale['total_qty_returned'] ?? 0);
                                if ($ret > 0): 
                                    if ($ret >= $sold): ?>
                                        <span class="badge bg-danger text-white ms-1" style="font-size: 0.6rem; padding: 1px 4px;">Fully Returned</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle ms-1" style="font-size: 0.6rem; padding: 1px 4px;">Partial Return</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                          </td>
                          <td><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></td>
                          <td class="text-center fw-bold text-success">₱<?= number_format($sale['total_amount'], 2) ?></td>
                          <td class="text-center small text-muted"><?= htmlspecialchars($sale['cashier_name']) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent Returns -->
        <div class="col-xl-6">
          <div class="chart-card-new h-100">
            <div class="cc-head d-flex justify-content-between">
              <div class="d-flex align-items-center gap-2">
                <div class="cc-icon" style="background:rgba(139,92,246,0.1);color:#8b5cf6;"><i class="bi bi-arrow-return-left"></i></div>
                <div>
                  <h6>Latest Returns processed</h6>
                  <small>Recent items brought back by customers</small>
                </div>
              </div>
              <a href="returns.php" class="btn btn-sm btn-link text-decoration-none p-0">View History</a>
            </div>
            <div class="cc-body p-0">
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 13.5px;">
                  <thead class="table-light">
                    <tr>
                      <th class="ps-4 border-0">Return ID</th>
                      <th class="border-0">Refund</th>
                      <th class="border-0">Reason</th>
                      <th class="border-0 text-center">Processed By</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($recentReturns)): ?>
                      <tr><td colspan="4" class="text-center py-4 text-muted">No recent returns found.</td></tr>
                    <?php else: ?>
                      <?php foreach ($recentReturns as $ret): ?>
                        <tr>
                          <td class="ps-4 font-monospace fw-bold text-primary">R#<?= $ret['return_id'] ?></td>
                          <td class="fw-bold text-danger">₱<?= number_format($ret['refund_amount'], 2) ?></td>
                          <td><span class="text-muted d-inline-block text-truncate" style="max-width: 150px;" title="<?= htmlspecialchars($ret['return_reason']) ?>"><?= htmlspecialchars($ret['return_reason']) ?></span></td>
                          <td class="text-center small text-muted"><?= htmlspecialchars($ret['processed_by']) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /container-fluid -->
  </div><!-- /content -->
</div><!-- /d-flex -->

<!-- ✅ STOCK ALERTS MODAL -->
<div class="modal fade" id="stockAlertsModal" tabindex="-1" aria-labelledby="stockAlertsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title" id="stockAlertsModalLabel">
          <i class="bi bi-exclamation-triangle-fill text-warning"></i>
          Inventory Stock Alerts
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <!-- Dashboard-style Mini KPI for Modal -->
        <div class="row g-3 mb-4">
          <div class="col-md-4">
            <div class="d-flex align-items-center p-3 rounded-4 bg-light">
              <div class="kpi-icon-sq red me-3" style="width:40px; height:40px; font-size:1rem;"><i class="bi bi-box"></i></div>
              <div>
                <div class="text-muted small fw-bold text-uppercase">Out of Stock</div>
                <div class="h5 mb-0 fw-bold"><?= count($outOfStockProducts) ?> Items</div>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="d-flex align-items-center p-3 rounded-4 bg-light">
              <div class="kpi-icon-sq amber me-3" style="width:40px; height:40px; font-size:1rem;"><i class="bi bi-graph-down"></i></div>
              <div>
                <div class="text-muted small fw-bold text-uppercase">Low Stock</div>
                <div class="h5 mb-0 fw-bold"><?= count($lowStockProducts) ?> Items</div>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="d-flex align-items-center p-3 rounded-4 bg-light">
              <div class="kpi-icon-sq blue me-3" style="width:40px; height:40px; font-size:1rem;"><i class="bi bi-tags"></i></div>
              <div>
                <div class="text-muted small fw-bold text-uppercase">Total Categories</div>
                <div class="h5 mb-0 fw-bold"><?= count($productsByCategory) ?> Sections</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Nav Tabs -->
        <ul class="nav nav-tabs mb-4" role="tablist">
          <li class="nav-item">
            <button class="nav-link active" id="outOfStock-tab" data-bs-toggle="tab" data-bs-target="#outOfStock" type="button" role="tab" aria-selected="true">
              <i class="bi bi-x-circle me-1"></i> Out of Stock
            </button>
          </li>
          <li class="nav-item">
            <button class="nav-link" id="lowStock-tab" data-bs-toggle="tab" data-bs-target="#lowStock" type="button" role="tab" aria-selected="false">
              <i class="bi bi-exclamation-circle me-1"></i> Low Stock
            </button>
          </li>
          <li class="nav-item">
            <button class="nav-link" id="byCategory-tab" data-bs-toggle="tab" data-bs-target="#byCategory" type="button" role="tab" aria-selected="false">
              <i class="bi bi-grid-3x3-gap me-1"></i> By Category
            </button>
          </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content">
          <!-- OUT OF STOCK TAB -->
          <div class="tab-pane fade show active" id="outOfStock" role="tabpanel">
            <?php if (empty($outOfStockProducts)): ?>
              <div class="text-center py-5">
                <i class="bi bi-check2-circle text-success" style="font-size: 3rem;"></i>
                <h6 class="mt-3 fw-bold">All stock items are available.</h6>
                <p class="text-muted small">No products with zero stock found.</p>
              </div>
            <?php else: ?>
              <div class="table-container">
                <table class="table table-hover align-middle mb-0">
                  <thead>
                    <tr>
                      <th style="width:50%">Product Details</th>
                      <th class="text-center">Current Stock</th>
                      <th class="text-center">Reorder Level</th>
                      <th class="text-center">Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($outOfStockProducts as $p): ?>
                      <tr>
                        <td>
                          <div class="fw-bold"><?= htmlspecialchars($p['product_name']) ?></div>
                          <div class="small text-muted">
                            <?php
                            $meta = array_filter([$p['brand'] ?? '', $p['variation'] ?? '', $p['unit'] ?? '']);
                            echo htmlspecialchars(implode(' · ', $meta));
                            ?>
                          </div>
                        </td>

                        <td class="text-center"><span class="fw-bold text-danger">0</span></td>
                        <td class="text-center"><?= (int)($p['reorder_level'] ?? 0) ?></td>
                        <td class="text-center"><span class="status-pill danger">Out of Stock</span></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>

          <!-- LOW STOCK TAB -->
          <div class="tab-pane fade" id="lowStock" role="tabpanel">
            <?php if (empty($lowStockProducts)): ?>
              <div class="text-center py-5">
                <i class="bi bi-check2-circle text-success" style="font-size: 3rem;"></i>
                <h6 class="mt-3 fw-bold">Stock levels are healthy.</h6>
                <p class="text-muted small">No items are below reorder level.</p>
              </div>
            <?php else: ?>
              <div class="table-container">
                <table class="table table-hover align-middle mb-0">
                  <thead>
                    <tr>
                      <th style="width:40%">Product Details</th>
                      <th class="text-center">Expiry Date</th>
                      <th class="text-center">Current Stock</th>
                      <th class="text-center">Reorder Level</th>
                      <th class="text-center">Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($lowStockProducts as $p): ?>
                      <tr>
                        <td>
                          <div class="fw-bold"><?= htmlspecialchars($p['product_name']) ?></div>
                          <div class="small text-muted">
                            <?php
                            $meta = array_filter([$p['brand'] ?? '', $p['variation'] ?? '', $p['unit'] ?? '']);
                            echo htmlspecialchars(implode(' · ', $meta));
                            ?>
                          </div>
                        </td>

                        <td class="text-center">
                          <span class="<?= ($p['nearest_expiry'] && $p['nearest_expiry'] < date('Y-m-d')) ? 'text-danger fw-bold' : 'text-muted' ?>">
                            <?= $p['nearest_expiry'] ? date('M d, Y', strtotime($p['nearest_expiry'])) : 'No Expiry' ?>
                          </span>
                        </td>
                        <td class="text-center"><span class="fw-bold text-warning"><?= formatQty($p['stock']) ?></span></td>
                        <td class="text-center"><?= formatQty($p['reorder_level'] ?? 0) ?></td>
                        <td class="text-center"><span class="status-pill warning">Low Stock</span></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>

          <!-- BY CATEGORY TAB -->
          <div class="tab-pane fade" id="byCategory" role="tabpanel">
            <div class="table-container">
              <table class="table table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th>Category</th>
                    <th class="text-center">Total Products</th>
                    <th class="text-center">Stock Volume</th>
                    <th class="text-center">Out of Stock Count</th>
                    <th class="text-center">Low Stock</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($productsByCategory as $cat): ?>
                    <tr>
                      <td class="fw-bold"><?= htmlspecialchars($cat['category_name'] ?: 'Uncategorized') ?></td>
                      <td class="text-center"><?= (int)$cat['total_products'] ?></td>
                      <td class="text-center"><?= formatQty($cat['total_stock'] ?? 0) ?></td>
                      <td class="text-center">
                        <?php if ((int)$cat['out_of_stock_count'] > 0): ?>
                          <span class="status-pill danger"><?= (int)$cat['out_of_stock_count'] ?></span>
                        <?php else: ?>
                          <span class="text-muted">—</span>
                        <?php endif; ?>
                      </td>
                      <td class="text-center">
                        <?php if ((int)$cat['low_stock_count'] > 0): ?>
                          <span class="status-pill warning"><?= (int)$cat['low_stock_count'] ?></span>
                        <?php else: ?>
                          <span class="text-muted">—</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Close</button>
        <a href="products.php" class="btn btn-primary px-4 fw-bold rounded-pill">
          <i class="bi bi-box-arrow-right me-2"></i> Manage Inventory
        </a>
      </div>
    </div>
  </div>
</div>

<!-- ✅ EXPIRY ALERTS MODAL -->
<div class="modal fade" id="expiryAlertsModal" tabindex="-1" aria-labelledby="expiryAlertsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title" id="expiryAlertsModalLabel">
          <i class="bi bi-hourglass-split text-info"></i>
          Batch Product Expiry Tracking
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <div class="d-flex align-items-center justify-content-between mb-4">
          <div>
            <h6 class="fw-bold mb-1">Time-Sensitive Inventory</h6>
            <p class="text-muted small mb-0">Monitoring items expiring within the next <?= (int)$expiry_days ?> days.</p>
          </div>
          <div class="d-flex gap-2">
            <span class="status-pill danger">Expired</span>
            <span class="status-pill warning">Expiring Soon</span>
          </div>
        </div>

        <?php if (empty($expiryProducts)): ?>
          <div class="text-center py-5">
            <div class="kpi-icon-sq purple mx-auto mb-3" style="width:60px; height:60px; font-size:1.8rem;">
              <i class="bi bi-shield-check"></i>
            </div>
            <h6 class="fw-bold">No expiring products found.</h6>
            <p class="text-muted small">All batch products are within their shelf life.</p>
          </div>
        <?php else: ?>
          <div class="table-container">
            <table class="table table-hover align-middle mb-0">
              <thead>
                    <tr>
                      <th style="width:40%">Product Details</th>
                      <th class="text-center">Batch Qty</th>
                      <th class="text-center">Expiry Date</th>
                      <th class="text-center">Status</th>
                      <th class="text-center">Action</th>
                    </tr>
              </thead>
              <tbody>
                <?php foreach ($expiryProducts as $e): ?>
                  <?php
                    $exp = $e['expiry_date'];
                    $today = date('Y-m-d');
                    $isEx = ($exp < $today);
                  ?>
                  <tr>
                    <td>
                      <div class="fw-bold"><?= htmlspecialchars($e['product_name']) ?></div>
                      <div class="small text-muted">
                        <?php
                        $meta = array_filter([$e['brand'] ?? '', $e['variation'] ?? '', $e['unit'] ?? '']);
                        echo htmlspecialchars(implode(' · ', $meta));
                        ?>
                      </div>
                    </td>
                    <td class="text-center">
                      <span class="fw-bold fs-6"><?= formatQty($e['batch_qty']) ?></span>
                    </td>
                    <td class="text-center">
                      <div class="fw-semibold <?= $isEx ? 'text-danger' : '' ?>">
                        <?= htmlspecialchars(date('M d, Y', strtotime($exp))) ?>
                      </div>
                      <div class="text-muted" style="font-size:10px;">
                        <?= $isEx ? 'Expired' : 'Expires in ' . round((strtotime($exp) - strtotime($today)) / (60 * 60 * 24)) . ' days' ?>
                      </div>
                    </td>
                    <td class="text-center">
                      <span class="status-pill <?= $isEx ? 'danger' : 'warning' ?>">
                        <?= $isEx ? 'EXPIRED' : 'EXPIRING' ?>
                      </span>
                    </td>
                    <td class="text-center">
                      <?php if ($isEx): ?>
                        <form method="POST" class="d-inline expiry-stockout-form">
                          <input type="hidden" name="product_id" value="<?= (int)$e['product_id'] ?>">
                          <input type="hidden" name="stock_history_id" value="<?= (int)$e['stock_history_id'] ?>">
                          <input type="hidden" name="stockout_expired" value="1">
                          <button
                            type="button"
                            class="btn btn-sm btn-danger px-3 rounded-pill fw-bold stockout-action-btn"
                            data-product-name="<?= htmlspecialchars($e['product_name']) ?>"
                            data-batch-qty="<?= (int)$e['batch_qty'] ?>"
                            data-expiry-date="<?= htmlspecialchars(date('M d, Y', strtotime($exp))) ?>"
                          >
                            Stock Out
                          </button>
                        </form>
                      <?php else: ?>
                        <span class="text-muted small">—</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-success px-4 fw-bold rounded-pill" onclick="printExpiryReport()">
          <i class="bi bi-printer me-2"></i> Print Report
        </button>
      </div>

    </div>
  </div>
</div>

<!-- ✅ Confirm Expired Batch Stock Out Modal -->
<div class="modal fade" id="confirmStockOutModal" tabindex="-1" aria-labelledby="confirmStockOutModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-danger text-white border-0">
        <h5 class="modal-title" id="confirmStockOutModalLabel"><i class="bi bi-exclamation-octagon-fill me-2"></i> Confirm Stock Out</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-3">You are about to stock out an expired batch. This will record the item as removed from current inventory.</p>
        <div class="p-3 rounded-3 bg-light border">
          <p class="mb-2"><strong>Product</strong>: <span id="confirmProductName">-</span></p>
          <p class="mb-2"><strong>Batch Qty</strong>: <span id="confirmBatchQty">-</span></p>
          <p class="mb-0"><strong>Expiry Date</strong>: <span id="confirmExpiryDate">-</span></p>
        </div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light fw-bold rounded-pill" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="stockOutConfirmBtn" class="btn btn-danger fw-bold rounded-pill">Stock Out Now</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>

<script>
  document.getElementById('sidebarToggle')?.addEventListener('click', function () {
    document.getElementById('sidebar')?.classList.toggle('show');
  });

  const expiryStockOutModal = new bootstrap.Modal(document.getElementById('confirmStockOutModal'));
  let selectedExpiryForm = null;

  document.querySelectorAll('.stockout-action-btn').forEach(button => {
    button.addEventListener('click', function () {
      const form = this.closest('.expiry-stockout-form');
      if (!form) return;

      selectedExpiryForm = form;
      document.getElementById('confirmProductName').textContent = this.dataset.productName || '-';
      document.getElementById('confirmBatchQty').textContent = this.dataset.batchQty || '-';
      document.getElementById('confirmExpiryDate').textContent = this.dataset.expiryDate || '-';
      expiryStockOutModal.show();
    });
  });

  document.getElementById('stockOutConfirmBtn')?.addEventListener('click', function () {
    if (selectedExpiryForm) {
      selectedExpiryForm.querySelector('button[type="button"]').disabled = true;
      selectedExpiryForm.submit();
    }
  });

  document.querySelectorAll('.toast-item').forEach(toastEl => {
    const closeBtn = toastEl.querySelector('.toast-close');
    const dismissToast = () => toastEl.remove();
    
    closeBtn?.addEventListener('click', dismissToast);
    setTimeout(dismissToast, 4500);
  });

  // Print Expiry Report Function
  function printExpiryReport() {
    const expiryProducts = <?php echo json_encode($expiryProducts); ?>;
    const expiryDays = <?php echo (int)$expiry_days; ?>;

    let printContent = `
      <!DOCTYPE html>
      <html lang="en">
      <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Expiry Report - Inventory System</title>
        <style>
          body { font-family: Arial, sans-serif; margin: 20px; color: #111827; }
          .header { text-align: center; margin-bottom: 20px; }
          .header img { width: 80px; height: auto; }
          .header h2 { margin: 10px 0; color: #1458ec; }
          .header p { color: #6b7280; }
          .info { margin-bottom: 20px; }
          .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
          .table th, .table td { border: 1px solid #d1d5db; padding: 8px 12px; text-align: left; }
          .table th { background: #f3f4f6; color: #111827; font-weight: bold; }
          .table tbody tr:nth-child(even) { background: #f9fafb; }
          .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
          .badge-expired { background: #dc3545; color: white; }
          .badge-expiring { background: #ffc107; color: #212529; }
          .footer { margin-top: 30px; text-align: center; color: #6b7280; font-size: 14px; }
          @media print { body { margin: 10px; } }
        </style>
      </head>
      <body>
        <div class="header">
          <img src="images/logo.png" alt="Inventory Logo" onerror="this.onerror=null; this.src='images/logo.jpg';">
          <h2>Inventory System</h2>
          <p>Expiry Report - Generated on ${new Date().toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
          })}</p>
        </div>
        
        <div class="info">
          <p><strong>Total Items:</strong> ${expiryProducts.length}</p>
          <p><strong>Expiry Check Period:</strong> Within ${expiryDays} days or already expired</p>
        </div>
    `;

    if (expiryProducts.length === 0) {
      printContent += '<p>No expiring or expired items found.</p>';
    } else {
      printContent += `
        <table class="table">
          <thead>
            <tr>
              <th>Product</th>
              <th>Brand</th>
              <th>Variation</th>
              <th>Batch Qty</th>
              <th>Expiry Date</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
      `;

      expiryProducts.forEach(product => {
        const expDate = new Date(product.expiry_date);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        expDate.setHours(0, 0, 0, 0);
        
        const isExpired = expDate < today;
        const badgeClass = isExpired ? 'badge-expired' : 'badge-expiring';
        const status = isExpired ? 'EXPIRED' : 'EXPIRING';
        
        printContent += `
          <tr>
            <td>${product.product_name || ''}</td>
            <td>${product.brand || '-'}</td>
            <td>${product.variation || '-'}</td>
            <td>${product.batch_qty || 0}</td>
            <td>${new Date(product.expiry_date).toLocaleDateString('en-US', { 
              year: 'numeric', 
              month: 'short', 
              day: 'numeric' 
            })}</td>
            <td><span class="badge ${badgeClass}">${status}</span></td>
          </tr>
        `;
      });

      printContent += `
          </tbody>
        </table>
      `;
    }

    printContent += `
        <div class="footer">
          <p>Inventory System - Expiry Report</p>
        </div>
      </body>
      </html>
    `;

    const printWindow = window.open('', '_blank');
    printWindow.document.write(printContent);
    printWindow.document.close();
    printWindow.focus();
    
    // Wait for content to load then print
    setTimeout(() => {
      printWindow.print();
      printWindow.close();
    }, 250);
  }

  // Chart Data
  const monthlyData = <?php echo json_encode($monthly_sales_data); ?>;
  const monthlyLabels = monthlyData.map(item => {
    const [year, month] = item.month.split('-');
    const date = new Date(year, month - 1, 1);
    return date.toLocaleString('default', { month: 'short', year: '2-digit' });
  });
  const monthlyValues = monthlyData.map(item => item.monthly_revenue);

  const topSuppliersData = <?php echo $top_suppliers_json; ?>;
  const supplierLabels = topSuppliersData.map(item => {
    const q = parseFloat(item.total_supplied_quantity);
    const qFormatted = (q % 1 === 0) ? parseInt(q) : parseFloat(q.toFixed(4));
    return item.supplier_name + ' (' + qFormatted + ')';
  });
  const supplierValues = topSuppliersData.map(item => item.total_supplied_quantity);
  const supplierTopItemsList = topSuppliersData.map(item => item.top_items || []);

  // Monthly Sales (Line) - NET
  const ctxMonthly = document.getElementById('monthlySalesChart').getContext('2d');
  new Chart(ctxMonthly, {
    type: 'line',
    data: {
      labels: monthlyLabels,
      datasets: [{
        label: 'Monthly Revenue (NET ₱)',
        data: monthlyValues,
        borderColor: '#1458ec',
        backgroundColor: 'rgba(20, 88, 236, 0.2)',
        borderWidth: 3,
        tension: 0.4,
        fill: true,
        pointBackgroundColor: '#1458ec'
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
        x: { grid: { display: false } }
      }
    }
  });

  // Top Products Data (Used in Table rendering)
  const topProductsData = <?php echo json_encode($top_products_data); ?>;

  // Top Suppliers (Vertical Bar)
  const ctxSuppliers = document.getElementById('topSuppliersChart').getContext('2d');
  new Chart(ctxSuppliers, {
    type: 'bar',
    data: {
      labels: supplierLabels,
      datasets: [{
        label: 'Total Units Supplied',
        data: supplierValues,
        backgroundColor: [
          'rgba(13, 202, 240, 0.9)',
          'rgba(25, 135, 84, 0.9)',
          'rgba(20, 88, 236, 0.9)',
          'rgba(255, 193, 7, 0.9)',
          'rgba(108, 117, 125, 0.9)'
        ],
        borderRadius: 5,
        borderWidth: 0
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            title: function(context) {
              const index = context[0].dataIndex;
              return supplierLabels[index];
            },
            label: function(context) {
              const index = context.dataIndex;
              const items = supplierTopItemsList[index] || [];
              if (items.length === 0) return 'No items found';
              
              let labels = ['Top Items:'];
              items.forEach(item => labels.push('• ' + item));
              return labels;
            },
            afterLabel: function(context) {
              return 'Total Qty: ' + context.parsed.y.toLocaleString();
            }
          }
        },
        datalabels: {
          anchor: 'end',
          align: 'top',
          color: '#333',
          font: { weight: 'bold', size: 12 },
          formatter: (value) => {
            const v = parseFloat(value);
            return (v % 1 === 0) ? parseInt(v) : parseFloat(v.toFixed(4));
          }
        }
      },
      scales: {
        y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
        x: { grid: { display: false } }
      }
    },
    plugins: [ChartDataLabels]
  });

  /* ============================================================
     🚀 REACTIVE SLOW-MOVING ANALYTICS
     ============================================================ */
  function updateSlowUI(data) {
    // Update Table
    let html = '';
    if (data.length === 0) {
      html = '<tr><td colspan="5" class="text-center py-4 text-muted">No data for this period</td></tr>';
    } else {
      data.forEach(p => {
        const badgeColor = p.total_quantity == 0 ? 'bg-danger-subtle text-danger' : 'bg-secondary-subtle text-secondary';
            const q = parseFloat(p.total_quantity);
            const qFormatted = (q % 1 === 0) ? parseInt(q) : parseFloat(q.toFixed(4));
            const meta = [p.brand, p.variation, p.unit].filter(x => x && x !== 'N/A' && x !== 'None').join(' · ');
            html += `
          <tr>
            <td class="ps-4">
              <div class="fw-bold">${p.product_name}</div>
              <div class="small text-muted">
                ${meta}
              </div>
            </td>
            <td class="text-end pe-4">
               <span class="badge ${badgeColor}" style="font-size: 13px; padding: 6px 12px;">${qFormatted.toLocaleString()}</span>
            </td>
          </tr>
        `;
      });
    }
    slowTableBody.innerHTML = html;
  }

  const slowTableBody = document.getElementById('slowTableBody');
  const slowLoading = document.getElementById('slowLoading');
  // Initial Load (already handled by PHP in the new full-width table layout)

  // Click Handlers
  document.querySelectorAll('.slow-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      const time = this.getAttribute('data-time');
      
      document.querySelectorAll('.slow-btn').forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      
      slowLoading.classList.remove('d-none');

      fetch(`admin_dashboard.php?ajax_slow_moving=1&timeframe=${time}`)
        .then(res => res.json())
        .then(data => {
          updateSlowUI(data);
        })
        .catch(err => console.error('Slow Move Error:', err))
        .finally(() => {
          showLoading(slowLoading, false);
        });
    });
  });

  function showLoading(el, show) {
    if (show) el.classList.remove('d-none');
    else el.classList.add('d-none');
  }

</script>
</body>
</html>