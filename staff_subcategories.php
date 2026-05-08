<?php
include 'project.php';
session_start();

// ===================== PHP LOGIC =====================
// Restrict to staff only
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$role = $_SESSION['role']; // Include role for navbar display

// Get category_id from URL
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
if ($category_id <= 0) {
    header("Location: staff_categories.php");
    exit();
}

// Fetch category info
$cat_stmt = $conn->prepare("SELECT * FROM categories WHERE category_id=?");
$cat_stmt->bind_param("i", $category_id);
$cat_stmt->execute();
$cat_result = $cat_stmt->get_result();
$category = $cat_result->fetch_assoc();
$cat_stmt->close();

// Check if category exists before proceeding
if (!$category) {
    header("Location: staff_categories.php");
    exit();
}

// Fetch subcategories for this category
$sub_stmt = $conn->prepare("SELECT * FROM subcategories WHERE category_id=? ORDER BY created_at DESC");
$sub_stmt->bind_param("i", $category_id);
$sub_stmt->execute();
$sub_result = $sub_stmt->get_result();
$sub_stmt->close();

// Fetch products for this category
$prod_stmt = $conn->prepare("SELECT p.*, s.subcategory_name FROM products p LEFT JOIN subcategories s ON p.subcategory_id = s.subcategory_id WHERE p.category_id=? ORDER BY p.created_at DESC");
$prod_stmt->bind_param("i", $category_id);
$prod_stmt->execute();
$prod_result = $prod_stmt->get_result();
$prod_stmt->close();

// ===================== HTML VIEW =====================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subcategories & Products - <?= htmlspecialchars($category['category_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/user.css"> <style>
        /* Styles from original code */
        .subcat-card {
            border-radius: 12px;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .subcat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        .subcat-card img {
            height: 120px;
            width: 100%;
            object-fit: cover;
        }
        .subcat-card-body {
            padding: 0.8rem;
            text-align: center;
        }
        .table img {
            height: 50px;
            width: 50px;
            object-fit: cover;
            border-radius: 5px;
        }
    </style>
</head>
<body>

<div class="d-flex">
    <div class="sidebar flex-column p-3" id="sidebar">
        <div class="sidebar-logo text-center">
            <img src="images/logo.png" alt="Staff Logo">
            <h5 class="mt-2">Staff Panel</h5>
        </div>
        <hr class="text-white">
        <ul class="nav flex-column">
            <li class="sidebar-title">Main</li>
            <li class="nav-item mb-2"><a class="nav-link" href="staff_dashboard.php"><i class="fa fa-home me-2"></i> Dashboard</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="staff_products.php"><i class="fa fa-box-seam me-2"></i> Products</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="process_transaction.php"><i class="fa fa-dollar-sign me-2"></i> Process Transaction</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="purchased_history.php"><i class="fa fa-history me-2"></i> Purchased History</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="staff_supplier.php"><i class="fa fa-truck me-2"></i> Suppliers</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="staff_tasks.php"><i class="fa fa-clipboard-list me-2"></i> My Tasks</a></li>
            <!-- <li class="sidebar-title">Account</li> -->
            <!-- <li class="nav-item mb-2"><a class="nav-link" href="my_profile.php"><i class="fa fa-user me-2"></i> My Profile</a></li> -->
            <li class="sidebar-title">Others</li>
            <li class="nav-item"><a class="nav-link text-danger" href="logout.php"><i class="fa fa-sign-out-alt me-2"></i> Logout</a></li>
        </ul>
    </div>

    <div class="content flex-grow-1">
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark rounded mb-4">
            <div class="container-fluid">
                <button class="btn btn-outline-light d-lg-none me-2" id="sidebarToggle">
                    <i class="fa fa-bars"></i>
                </button>
                <a class="navbar-brand" href="#">Welcome, <?php echo htmlspecialchars($username); ?></a>
                <div class="ms-auto d-flex align-items-center">
                    <i class="fa fa-user-circle fa-2x me-2"></i>
                    <span class="text-white fw-semibold"><?php echo ucfirst($role); ?></span>
                    <button class="btn btn-outline-light ms-3" data-bs-toggle="modal" data-bs-target="#searchModal">
                        <i class="fa fa-search"></i> Search
                    </button>
                </div>
            </div>
        </nav>
        
        <div class="container-fluid">
            <h4 class="mb-4">Category: <?= htmlspecialchars($category['category_name']) ?></h4>

            <div class="row g-4 mb-4">
                <?php if ($sub_result && mysqli_num_rows($sub_result) > 0): ?>
                    <?php while ($sub = mysqli_fetch_assoc($sub_result)): ?>
                        <div class="col-6 col-sm-4 col-md-3 col-lg-2 text-center">
                            <div class="card subcat-card shadow-sm">
                                <?php if (!empty($sub['image_path']) && file_exists($sub['image_path'])): ?>
                                    <img src="<?= htmlspecialchars($sub['image_path']) ?>" alt="<?= htmlspecialchars($sub['subcategory_name']) ?>">
                                <?php else: ?>
                                    <img src="https://via.placeholder.com/300x120?text=No+Image" alt="No Image">
                                <?php endif; ?>
                                <div class="subcat-card-body">
                                    <h5><?= htmlspecialchars($sub['subcategory_name']) ?></h5>
                                    <p class="text-muted" style="font-size:0.85rem;"><?= date("M d, Y", strtotime($sub['created_at'])) ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="alert alert-warning text-center">No subcategories found for this category.</div>
                    </div>
                <?php endif; ?>
            </div>

            <h5 class="mb-3">Products in this Category</h5>
            <?php if ($prod_result && mysqli_num_rows($prod_result) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Image</th>
                                <th>Product Name</th>
                                <th>Subcategory</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Created At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($prod = mysqli_fetch_assoc($prod_result)): ?>
                                <tr>
                                    <td><img src="<?= htmlspecialchars($prod['image_path'] ?? 'https://via.placeholder.com/50x50?text=No+Img') ?>" alt="<?= htmlspecialchars($prod['product_name']) ?>"></td>
                                    <td><?= htmlspecialchars($prod['product_name']) ?></td>
                                    <td><?= htmlspecialchars($prod['subcategory_name']) ?></td>
                                    <td>₱<?= number_format($prod['price'], 2) ?></td>
                                    <td><span class="badge <?= ($prod['stock'] < 10) ? 'bg-danger' : 'bg-success' ?>"><?= intval($prod['stock']) ?></span></td>
                                    <td><?= date("M d, Y", strtotime($prod['created_at'])) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-warning text-center">No products found for this category.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="searchModal" tabindex="-1" aria-labelledby="searchModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="searchModalLabel">Search Products</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="text" id="modalSearchInput" class="form-control mb-3" placeholder="Search product or subcategory...">
        <div id="modalSearchResults" class="table-responsive">
          <table class="table table-hover align-middle">
              <thead class="table-dark">
                  <tr>
                      <th>Product Name</th>
                      <th>Subcategory</th>
                      <th>Price</th>
                      <th>Stock</th>
                  </tr>
              </thead>
              <tbody id="searchResultsBody">
                  <tr><td colspan="4" class="text-center text-muted">Start typing to search...</td></tr>
              </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Sidebar toggle for mobile (Necessary for user.css media query)
document.getElementById('sidebarToggle').addEventListener('click', function () {
    document.getElementById('sidebar').classList.toggle('show');
});

// Search functionality script (Kept as is)
const input = document.getElementById('modalSearchInput');
const tbody = document.getElementById('searchResultsBody');

input.addEventListener('input', function() {
    const query = input.value.trim();
    if (query.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Start typing to search...</td></tr>';
        return;
    }

    fetch('search_products.php?q=' + encodeURIComponent(query))
    .then(res => res.json())
    .then(data => {
        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No results found.</td></tr>';
            return;
        }
        tbody.innerHTML = '';
        data.forEach(p => {
            tbody.innerHTML += `<tr>
                <td>${p.product_name}</td>
                <td>${p.subcategory_name}</td>
                <td>₱${parseFloat(p.price).toFixed(2)}</td>
                <td>${p.stock}</td>
            </tr>`;
        });
    });
});
</script>

</body>
</html>