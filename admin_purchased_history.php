<!-- <?php
   include 'project.php';
   session_start();
   
   // =================== ACCESS CONTROL =================== //
   // Restrict access to ADMIN ONLY (as requested by the dashboard structure)
   if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
       header("Location: login.php");
       exit();
   }
   
   $user_role = $_SESSION['role'] ?? 'admin';
   $username = $_SESSION['username'] ?? 'Admin';
   
   // =================== FETCH SALE HISTORY =================== //
   // This query gets a comprehensive summary of each sale transaction.
   $sql = "
       SELECT 
           sg.sale_group_id,
           sg.created_at,
           sp.total_amount,
           sp.payment_type,
           COUNT(s.sale_id) AS total_items_count
       FROM 
           sale_groups sg
       JOIN 
           sale_payments sp ON sg.sale_group_id = sp.sale_group_id
       JOIN 
           sales s ON sg.sale_group_id = s.sale_group_id
       GROUP BY 
           sg.sale_group_id, sg.created_at, sp.total_amount, sp.payment_type
       ORDER BY 
           sg.created_at DESC
   ";
   
   $result = $conn->query($sql);
   
   // Check if the query was successful before fetching results
   if ($result === false) {
       die("SQL Error: " . $conn->error);
   }
   
   $history = $result->fetch_all(MYSQLI_ASSOC);
   
   ?>
<!DOCTYPE html>
<html lang="en">
   <head>
           <link rel="icon" type="image/x-icon" href="favicon.ico">
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Admin Sales History</title>
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
      <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
      <link rel="stylesheet" href="css/admin1.css">
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
               <li class="nav-item mb-2"><a class="nav-link" href="admin_dashboard.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a></li>
               <li class="sidebar-title">Management</li>
               <li class="nav-item mb-2"><a class="nav-link" href="products.php"><i class="bi bi-box-seam me-2"></i> Products</a></li>
               <li class="nav-item mb-2"><a class="nav-link" href="supplier.php"><i class="bi bi-truck me-2"></i> Suppliers</a></li>
               <li class="nav-item mb-2"><a class="nav-link" href="categories.php"><i class="bi bi-tags me-2"></i> Categories</a></li>
               <li class="nav-item mb-2"><a class="nav-link" href="sales.php"><i class="bi bi-cart-check me-2"></i> Sales</a></li>
               <li class="nav-item mb-2"><a class="nav-link active" href="admin_purchased_history.php"><i class="bi bi-clock-history me-2"></i> Sales History</a></li>
               <li class="nav-item mb-2"><a class="nav-link" href="reports.php"><i class="bi bi-bar-chart-line me-2"></i> Reports</a></li>
               <li class="sidebar-title">Users</li>
               <li class="nav-item mb-2"><a class="nav-link" href="manageUser.php"><i class="bi bi-people me-2"></i> Manage Users</a></li>
               <li class="sidebar-title">Settings</li>
               <li class="nav-item mb-2"><a class="nav-link" href="settings.php"><i class="bi bi-gear me-2"></i> Settings</a></li>
               <li class="nav-item"><a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
            </ul>
         </div>
         <div class="content flex-grow-1">
            <h4 class="mb-4 fw-bold text-primary"> Sales Transaction History</h4>
            <div class="card product-card p-4 mb-4 shadow-lg">
               <div class="card-body">
                  <?php if (empty($history)): ?>
                  <div class="alert alert-info text-center" role="alert">
                     <i class="bi bi-info-circle me-2"></i>No sale transactions have been recorded yet.
                  </div>
                  <?php else: ?>
                  <div class="table-responsive">
                     <table class="table table-striped table-hover align-middle">
                        <thead class="table-dark">
                           <tr>
                              <th>Reference no.</th>
                              <th>Date & Time</th>
                              <th>Items</th>
                              <th>Payment Type</th>
                              <th>Total Amount</th>
                              <th>Action</th>
                           </tr>
                        </thead>
                        <tbody>
                           <?php foreach ($history as $sale): ?>
                           <tr>
                              <td><strong><?php echo htmlspecialchars($sale['sale_group_id']); ?></strong></td>
                              <td><?php echo date('M d, Y h:i A', strtotime($sale['created_at'])); ?></td>
                              <td><span class="badge bg-secondary"><?php echo htmlspecialchars($sale['total_items_count']); ?></span></td>
                              <td><?php echo htmlspecialchars(ucfirst($sale['payment_type'])); ?></td>
                              <td class="fw-bold text-success">₱<?php echo number_format($sale['total_amount'], 2); ?></td>
                              <td>
                                 <a href="invoice_receipt.php?sale_group_id=<?php echo $sale['sale_group_id']; ?>" class="btn btn-sm btn-outline-primary">
                                 View Receipt <i class="bi bi-eye ms-1"></i>
                                 </a>
                              </td>
                           </tr>
                           <?php endforeach; ?>
                        </tbody>
                     </table>
                  </div>
                  <?php endif; ?>
               </div>
            </div>
         </div>
      </div>
      </div>
      <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
      <script>
         // There's no toggle button since the navbar is gone, but we'll keep the JS minimal.
      </script>
   </body>
</html> -->