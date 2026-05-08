<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$current_role = $_SESSION['role'] ?? 'staff';
$dashboard_link = ($current_role === 'admin') ? 'admin_dashboard.php' : 'staff_dashboard.php';
?>
<div class="sidebar flex-column p-0" id="sidebar">
    <div class="sidebar-logo text-center">
        <img src="images/logo.png" alt="Inventory Logo">
        <h5 class="mt-2 text-white">Inventory System</h5>
    </div>
    <hr class="text-white">
    <ul class="nav flex-column">
        <li class="sidebar-title">Main</li>
        <li class="nav-item mb-2">
            <a class="nav-link <?= ($currentPage == 'admin_dashboard.php' || $currentPage == 'staff_dashboard.php') ? 'active' : '' ?>" href="<?= $dashboard_link ?>">
                <i class="bi bi-speedometer2 me-2"></i> Dashboard
            </a>
        </li>
        
        <li class="sidebar-title">Management</li>
        <li class="nav-item mb-2">
            <a class="nav-link <?= ($currentPage == 'products.php') ? 'active' : '' ?>" href="products.php">
                <i class="bi bi-box-seam me-2"></i> Product Management
            </a>
        </li>
        <li class="nav-item mb-2">
            <a class="nav-link <?= ($currentPage == 'categories.php') ? 'active' : '' ?>" href="categories.php">
                <i class="bi bi-tags me-2"></i> Categories
            </a>
        </li>
        <li class="nav-item mb-2">
            <a class="nav-link <?= ($currentPage == 'sales.php') ? 'active' : '' ?>" href="sales.php">
                <i class="bi bi-cart-check me-2"></i> Sales History
            </a>
        </li>
        <li class="nav-item mb-2">
            <a class="nav-link <?= ($currentPage == 'p_os.php') ? 'active' : '' ?>" href="p_os.php">
                <i class="bi bi-receipt me-2"></i> Invoice Generator
            </a>
        </li>
        <li class="nav-item mb-2">
            <a class="nav-link <?= ($currentPage == 'returns.php') ? 'active' : '' ?>" href="returns.php">
                <i class="bi bi-arrow-return-left me-2"></i> Returns
            </a>
        </li>
        <li class="nav-item mb-2">
            <a class="nav-link <?= ($currentPage == 'stock_in_batches.php') ? 'active' : '' ?>" href="stock_in_batches.php">
                <i class="bi bi-box-arrow-down me-2"></i> Stock-In Records
            </a>
        </li>
        <li class="nav-item mb-2">
            <a class="nav-link <?= ($currentPage == 'stock_out_history.php') ? 'active' : '' ?>" href="stock_out_history.php">
                <i class="bi bi-box-arrow-up me-2"></i> Stock-Out Records
            </a>
        </li>
        <li class="nav-item mb-2">
            <a class="nav-link <?= ($currentPage == 'product_history.php') ? 'active' : '' ?>" href="product_history.php">
                <i class="bi bi-clock-history me-2"></i> Product History
            </a>
        </li>
        <li class="nav-item mb-2">
            <a class="nav-link <?= ($currentPage == 'admin_seasonal_report.php') ? 'active' : '' ?>" href="admin_seasonal_report.php">
                <i class="bi bi-calendar-range me-2"></i> Seasonal Analysis
            </a>
        </li>
        <li class="nav-item mb-2">
            <a class="nav-link <?= ($currentPage == 'reports.php') ? 'active' : '' ?>" href="reports.php">
                <i class="bi bi-bar-chart-line me-2"></i> Reports
            </a>
        </li>
        <li class="nav-item mb-2">
            <a class="nav-link <?= ($currentPage == 'supplier.php') ? 'active' : '' ?>" href="supplier.php">
                <i class="bi bi-truck me-2"></i> Suppliers
            </a>
        </li>
        
        <?php if ($current_role === 'admin'): ?>
            <li class="sidebar-title">Users</li>
            <li class="nav-item mb-2">
                <a class="nav-link <?= ($currentPage == 'manageUser.php') ? 'active' : '' ?>" href="manageUser.php">
                    <i class="bi bi-people me-2"></i> Manage Users
                </a>
            </li>
        <?php endif; ?>
        
        <li class="sidebar-title">Settings</li>
        <li class="nav-item mb-2">
            <a class="nav-link <?= ($currentPage == 'settings.php') ? 'active' : '' ?>" href="settings.php">
                <i class="bi bi-gear me-2"></i> Settings
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-danger" href="logout.php">
                <i class="bi bi-box-arrow-right me-2"></i> Logout
            </a>
        </li>
    </ul>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
    }

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
        const isClickInsideSidebar = sidebar.contains(event.target);
        const isClickInsideToggle = sidebarToggle?.contains(event.target);
        
        if (!isClickInsideSidebar && !isClickInsideToggle && sidebar.classList.contains('show')) {
            sidebar.classList.remove('show');
        }
    });
});
</script>
