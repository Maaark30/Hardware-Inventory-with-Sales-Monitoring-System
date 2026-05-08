<!-- <?php
   include 'project.php';
   session_start();
   
   // ===================== AUTH & USER INFO =====================
   if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
       header("Location: login.php");
       exit();
   }
   
   $username = $_SESSION['username'];
   $role = $_SESSION['role'] ?? 'Admin';
   
   // ===================== 1. FETCH SUMMARY DATA (KPIs) =====================
   
   // Total Products
   $total_products_res = $conn->query("SELECT COUNT(*) AS total FROM products");
   $total_products = $total_products_res->fetch_assoc()['total'];
   
   // Total Categories
   $total_categories_res = $conn->query("SELECT COUNT(*) AS total FROM categories");
   $total_categories = $total_categories_res->fetch_assoc()['total'];
   
   // Total Subcategories (Variants)
   $total_subcategories_res = $conn->query("SELECT COUNT(*) AS total FROM subcategories");
   $total_subcategories = $total_subcategories_res->fetch_assoc()['total'];
   
   // Total Sales (Revenue) - ***FIXED COLUMN NAME***
   $total_sales_res = $conn->query("SELECT SUM(total_price) AS total FROM sales");
   $total_sales = $total_sales_res->fetch_assoc()['total'] ?? 0;
   
   // Low Stock Count (Stock <= 20)
   $low_stock_res = $conn->query("SELECT COUNT(*) AS total FROM products WHERE stock <= 20");
   $low_stock_count = $low_stock_res->fetch_assoc()['total'];
   
   
   // ===================== 2. FETCH DATA FOR CHARTS =====================
   
   // --- Data for Top 5 Selling Products (for Bar/Pie Chart) ---
   $top_products_query = "
       SELECT p.product_name, SUM(s.quantity) AS total_quantity
       FROM sales s
       JOIN products p ON s.product_id = p.product_id
       GROUP BY p.product_id
       ORDER BY total_quantity DESC
       LIMIT 5
   ";
   $top_products_result = $conn->query($top_products_query);
   $top_products_data = [];
   while ($row = $top_products_result->fetch_assoc()) {
       $top_products_data[] = $row;
   }
   
   // --- Data for Monthly Sales (for Line Chart) ---
   $monthly_sales_query = "
       SELECT 
           DATE_FORMAT(sg.created_at, '%Y-%m') AS month,
           SUM(s.total_price) AS monthly_revenue  /* ***FIXED COLUMN NAME*** */
       FROM sale_groups sg
       JOIN sales s ON sg.sale_group_id = s.sale_group_id
       WHERE sg.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
       GROUP BY month
       ORDER BY month ASC
   ";
   $monthly_sales_result = $conn->query($monthly_sales_query);
   $monthly_sales_data = [];
   while ($row = $monthly_sales_result->fetch_assoc()) {
       $monthly_sales_data[] = $row;
   }
   
   
    $today_sales_res = $conn->query("
        SELECT SUM(s.total_price) AS total 
        FROM sales s
        JOIN sale_groups sg ON s.sale_group_id = sg.sale_group_id
        WHERE DATE(sg.created_at) = CURDATE()
    ");
    $today_sales = $today_sales_res->fetch_assoc()['total'] ?? 0;

    // Total Sales This Month
    $month_sales_res = $conn->query("
        SELECT SUM(s.total_price) AS total 
        FROM sales s
        JOIN sale_groups sg ON s.sale_group_id = sg.sale_group_id
        WHERE MONTH(sg.created_at) = MONTH(CURDATE()) 
        AND YEAR(sg.created_at) = YEAR(CURDATE())
    ");
    $month_sales = $month_sales_res->fetch_assoc()['total'] ?? 0;

    // Total Stock Quantity (Sum of all item quantities)
    $total_stock_res = $conn->query("SELECT SUM(stock) AS total FROM products");
    $total_stock_quantity = $total_stock_res->fetch_assoc()['total'] ?? 0;

   ?>
<!DOCTYPE html>
<html lang="en">
   <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Admin Dashboard</title>
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
      <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
      <link rel="stylesheet" href="css/admin1.css">
      <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
   </head>
   <body>
      <div class="d-flex">
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
               <li class="nav-item mb-2"><a class="nav-link " href="products.php"><i class="bi bi-box-seam me-2"></i> Product Management</a></li>
               <li class="nav-item mb-2"><a class="nav-link" href="categories.php"><i class="bi bi-tags me-2"></i> Categories</a></li>
               <li class="nav-item mb-2"><a class="nav-link" href="sales.php"><i class="bi bi-cart-check me-2"></i> Sales</a></li>
              <li class="nav-item mb-2"><a class="nav-link" href="p_os.php"><i class="bi bi-receipt me-2"></i> Invoice</a></li>
               <li class="nav-item mb-2"><a class="nav-link" href="admin_purchased_history.php"><i class="bi bi-clock-history me-2"></i> Purchase History</a></li>
            <li class="nav-item mb-2"><a class="nav-link active" href="stock_in_batches.php"><i class="bi bi-truck me-2"></i> Stock-In Batches</a></li>
               <li class="nav-item mb-2"><a class="nav-link" href="reports.php"><i class="bi bi-bar-chart-line me-2"></i> Reports</a></li>
               <li class="nav-item mb-2"><a class="nav-link" href="supplier.php"><i class="bi bi-truck me-2"></i> Suppliers</a></li>
               <li class="sidebar-title">Users</li>
               <li class="nav-item mb-2"><a class="nav-link" href="manageUser.php"><i class="bi bi-people me-2"></i> Manage Users</a></li>
               <li class="sidebar-title">Settings</li>
               <li class="nav-item mb-2"><a class="nav-link" href="settings.php"><i class="bi bi-gear me-2"></i> Settings</a></li>
               <li class="nav-item"><a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
            </ul>
         </div>
      </div>
      <div class="content flex-grow-1">
         <div class="container-fluid">
            <button class="btn btn-outline-light d-lg-none me-2" id="sidebarToggle">
            <i class="bi bi-list"></i>
            </button>
         </div>
         </nav>
         <div class="container-fluid">
            <h4 class="mb-4 fw-bold text-primary">Dashboard Overview</h4>
            <!-- KPI Cards -->
            <div class="row g-4 mb-5">

                <div class="col-lg-3 col-md-6">
                    <div class="card kpi-card border-0 shadow-lg p-3 text-white" style="background: linear-gradient(135deg, #1458ec, #1a73e8);">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="fw-semibold">Sales This Month</h6>
                                <h2 class="fw-bold mt-1 mb-0">₱<?php echo number_format($month_sales, 2); ?></h2>
                            </div>
                            <div class="kpi-icon">
                                <i class="bi bi-calendar-month"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="card kpi-card border-0 shadow-lg p-3 text-white" style="background: linear-gradient(135deg, #198754, #20c997);">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="fw-semibold">Sales Today</h6>
                                <h2 class="fw-bold mt-1 mb-0">₱<?php echo number_format($today_sales, 2); ?></h2>
                            </div>
                            <div class="kpi-icon">
                                <i class="bi bi-calendar-day"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="card kpi-card border-0 shadow-lg p-3 text-white" style="background: linear-gradient(135deg, #0dcaf0, #5bc0de);">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="fw-semibold">Total Products in Stock</h6>
                                <h2 class="fw-bold mt-1 mb-0">
                                    <?php echo number_format($total_stock_quantity); ?> <small class="fs-6 fw-normal">Units</small>
                                </h2>
                            </div>
                            <div class="kpi-icon">
                                <i class="bi bi-boxes"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="card kpi-card border-0 shadow-lg p-3 text-white <?php echo ($low_stock_count > 0) ? 'bg-danger' : 'bg-secondary'; ?>">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="fw-semibold">Low Stock Items</h6>
                                <h2 class="fw-bold mt-1 mb-0"><?php echo number_format($low_stock_count); ?></h2>
                                <?php if ($low_stock_count > 0): ?>
                                <small>⚠ Action Required!</small>
                                <?php endif; ?>
                            </div>
                            <div class="kpi-icon">
                                <i class="bi bi-exclamation-triangle"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
            <!-- Charts Section -->
            <h4 class="mb-4 fw-bold text-secondary">Sales & Inventory Analytics</h4>
            <div class="row g-4">
               <!-- Monthly Sales -->
               <div class="col-lg-7">
                  <div class="card shadow-sm border-0 p-4 chart-card">
                     <h6 class="fw-bold mb-3"><i class="bi bi-graph-up me-2 text-primary"></i> Monthly Revenue (Last 6 Months)</h6>
                     <div style="height:350px;">
                        <canvas id="monthlySalesChart"></canvas>
                     </div>
                  </div>
               </div>
               <!-- Top Products -->
               <div class="col-lg-5">
                  <div class="card shadow-sm border-0 p-4 chart-card">
                     <h6 class="fw-bold mb-3"><i class="bi bi-trophy me-2 text-warning"></i> Top 5 Selling Products</h6>
                     <div style="height:350px;">
                        <canvas id="topProductsChart"></canvas>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </div>
      </div>
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
      <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
      <script>
         // =======================================================
         // 1. Sidebar Toggle
         // =======================================================
         document.getElementById('sidebarToggle').addEventListener('click', function () {
             document.getElementById('sidebar').classList.toggle('show');
         });
         
         // =======================================================
         // 2. Chart Data Initialization (PHP to JS)
         // =======================================================
         
         // Data for Monthly Sales
         const monthlyData = <?php echo json_encode($monthly_sales_data); ?>;
         const monthlyLabels = monthlyData.map(item => {
             // Format YYYY-MM to Month Name (e.g., "2025-10" to "Oct 25")
             const [year, month] = item.month.split('-');
             const date = new Date(year, month - 1, 1);
             return date.toLocaleString('default', { month: 'short', year: '2-digit' });
         });
         const monthlyValues = monthlyData.map(item => item.monthly_revenue);
         
         // Data for Top Products
         const topProductsData = <?php echo json_encode($top_products_data); ?>;
         const productLabels = topProductsData.map(item => item.product_name);
         const productValues = topProductsData.map(item => item.total_quantity);
         
         
         // =======================================================
         // 3. Chart Drawing (Chart.js)
         // =======================================================
         
         // --- Monthly Sales Chart (Line) ---
         const ctxMonthly = document.getElementById('monthlySalesChart').getContext('2d');
         new Chart(ctxMonthly, {
         type: 'line',
         data: {
             labels: monthlyLabels,
             datasets: [{
                 label: 'Monthly Revenue (₱)',
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
             plugins: {
                 legend: { display: false }
             },
             scales: {
                 y: {
                     beginAtZero: true,
                     grid: { color: 'rgba(0,0,0,0.05)' }
                 },
                 x: { grid: { display: false } }
             }
         }
         });
         
         // --- Top Products Chart (Horizontal Bar with Modern Styling) ---
         const ctxProducts = document.getElementById('topProductsChart').getContext('2d');
         new Chart(ctxProducts, {
         type: 'bar',
         data: {
             labels: productLabels,
             datasets: [{
                 label: 'Units Sold',
                 data: productValues,
                 backgroundColor: [
                     'rgba(20, 88, 236, 0.85)',   // Primary blue
                     'rgba(25, 135, 84, 0.85)',   // Green
                     'rgba(255, 193, 7, 0.85)',   // Yellow
                     'rgba(220, 53, 69, 0.85)',   // Red
                     'rgba(108, 117, 125, 0.85)'  // Gray
                 ],
                 borderRadius: 10,
                 borderSkipped: false, // For rounded bar ends
                 borderWidth: 0,
                 barThickness: 25
             }]
         },
         options: {
             responsive: true,
             maintainAspectRatio: false,
             indexAxis: 'y', // Horizontal layout
             plugins: {
                 legend: {
                     display: false
                 },
                 title: {
                     display: false
                 },
                 tooltip: {
                     backgroundColor: 'rgba(0,0,0,0.75)',
                     titleFont: { size: 14 },
                     bodyFont: { size: 13 },
                     padding: 10
                 },
                 datalabels: {
                     anchor: 'end',
                     align: 'right',
                     color: '#333',
                     font: { weight: 'bold', size: 12 },
                     formatter: (value) => value
                 }
             },
             scales: {
                 x: {
                     beginAtZero: true,
                     grid: { color: 'rgba(0,0,0,0.05)' },
                     ticks: { color: '#555' }
                 },
                 y: {
                     grid: { display: false },
                     ticks: {
                         color: '#333',
                         font: { weight: 'bold' }
                     }
                 }
             }
         },
         plugins: [ChartDataLabels] // enables labels at the end of bars
         });
         
      </script>
   </body>
</html> -->