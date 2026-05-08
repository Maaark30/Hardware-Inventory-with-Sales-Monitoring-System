<?php
session_start();
include 'project.php';

if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['id'];

$stmt = $conn->prepare("SELECT username, role, full_name, password FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    die("User not found.");
}

/* ============================================================
   PASSWORD VERIFICATION (gate to access settings)
   ============================================================ */
if (!isset($_SESSION['profile_verified_settings'])) {
    $_SESSION['profile_verified_settings'] = false;
}

$password_error = null;
if (isset($_POST['verify_password'])) {
    if (password_verify($_POST['verification_password'], $user['password'])) {
        $_SESSION['profile_verified_settings'] = true;
        header("Location: settings.php");
        exit();
    } else {
        $password_error = "Incorrect password. Please try again.";
    }
}

/* ============================================================
   UPDATE PROFILE
   ============================================================ */
if (isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $username  = trim($_POST['username'] ?? '');

    $update = $conn->prepare("UPDATE users SET full_name = ?, username = ? WHERE id = ?");
    $update->bind_param("ssi", $full_name, $username, $user_id);

    if ($update->execute()) {
        $_SESSION['success_settings'] = "Profile updated successfully!";
        // Re-fetch updated user
        $stmt = $conn->prepare("SELECT username, role, full_name, password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } else {
        $_SESSION['error_settings'] = "Error updating profile. Please try again.";
    }
    $update->close();
    header("Location: settings.php");
    exit();
}

/* ============================================================
   CHANGE PASSWORD
   ============================================================ */
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $temp_pass_error  = null;

    if (!password_verify($current_password, $user['password'])) {
        $temp_pass_error = "Current password is incorrect.";
    } elseif ($new_password !== $confirm_password) {
        $temp_pass_error = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $temp_pass_error = "New password must be at least 6 characters.";
    } else {
        $hashed_new = password_hash($new_password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_new, $user_id);
        if ($stmt->execute()) {
            $_SESSION['success_settings'] = "Password changed successfully!";
        } else {
            $temp_pass_error = "Error updating password.";
        }
        $stmt->close();
    }

    if ($temp_pass_error) {
        $_SESSION['password_error_settings'] = $temp_pass_error;
    }
    header("Location: settings.php");
    exit();
}

/* ============================================================
   RETRIEVE SESSION MESSAGES
   ============================================================ */
$success_msg       = $_SESSION['success_settings'] ?? null;
$error_msg         = $_SESSION['error_settings'] ?? null;
$password_change_error = $_SESSION['password_error_settings'] ?? null;

unset($_SESSION['success_settings'], $_SESSION['error_settings'], $_SESSION['password_error_settings']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
        <link rel="icon" type="image/x-icon" href="favicon.ico">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/admin1.css">
    <link rel="stylesheet" href="css/alert.css">
    <style>
        .settings-card {
            background: #fff;
            border-radius: 18px;
            border: 1px solid rgba(148,163,184,0.15);
            box-shadow: 0 8px 32px rgba(15,23,42,0.08);
            padding: 2rem;
        }
        .settings-avatar {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0d6efd, #1e66ff);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: #fff;
            font-weight: 700;
            flex-shrink: 0;
        }
        .section-label {
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #94a3b8;
            margin-bottom: 1rem;
        }
        .form-control[readonly] {
            background: #f8faff;
            border-color: rgba(148,163,184,0.2);
            color: #64748b;
        }
        .form-control:not([readonly]) {
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13,110,253,0.08);
        }
        .role-badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 600;
            background: #e0f2fe;
            color: #0369a1;
            letter-spacing: 0.04em;
        }
        .divider {
            border-top: 1px solid rgba(148,163,184,0.15);
            margin: 1.5rem 0;
        }
        .btn-edit-profile {
            background: linear-gradient(90deg, #0d6efd, #1e66ff);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 8px 20px;
            font-size: 0.9rem;
            transition: opacity 0.2s;
        }
        .btn-edit-profile:hover { opacity: 0.88; color: #fff; }

        /* Verify gate overlay */
        .verify-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15,23,42,0.55);
            backdrop-filter: blur(4px);
            z-index: 1050;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .verify-box {
            background: #fff;
            border-radius: 18px;
            padding: 2.5rem 2rem;
            width: 100%;
            max-width: 360px;
            box-shadow: 0 24px 60px rgba(15,23,42,0.18);
            text-align: center;
        }
        .verify-box .lock-icon {
            font-size: 2.5rem;
            color: #0d6efd;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>

<?php if (!$_SESSION['profile_verified_settings']): ?>
<!-- VERIFICATION GATE -->
<div class="verify-overlay">
    <div class="verify-box">
        <div class="lock-icon"><i class="bi bi-shield-lock-fill"></i></div>
        <h5 class="fw-bold mb-1">Verify Identity</h5>
        <p class="text-muted mb-3" style="font-size:0.9rem;">Enter your password to access settings.</p>

        <?php if ($password_error): ?>
            <div class="alert alert-danger py-2 text-start" style="font-size:0.875rem;">
                <i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($password_error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3 text-start">
                <label class="form-label fw-semibold" style="font-size:0.85rem;">Password</label>
                <input type="password" name="verification_password" class="form-control" placeholder="Enter your password" required autofocus>
            </div>
            <button type="submit" name="verify_password" class="btn btn-primary w-100 mb-2">
                <i class="bi bi-unlock me-1"></i> Verify Access
            </button>
            <a href="admin_dashboard.php" class="btn btn-outline-secondary w-100">
                <i class="bi bi-arrow-left me-1"></i> Go Back
            </a>
        </form>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php exit(); endif; ?>

<!-- MAIN SETTINGS PAGE (only shown after verification) -->
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
            <li class="nav-item mb-2"><a class="nav-link" href="manageUser.php"><i class="bi bi-people me-2"></i> Manage Users</a></li>
            <li class="sidebar-title">Settings</li>
            <li class="nav-item mb-2"><a class="nav-link active" href="settings.php"><i class="bi bi-gear me-2"></i> Settings</a></li>
            <li class="nav-item"><a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
        </ul>
    </div>

    <!-- CONTENT -->
    <div class="content flex-grow-1">
        <div class="container-fluid py-4">

            <div class="mb-4">
                <h4 class="fw-bold text-primary mb-0"><i class="bi bi-gear me-2"></i>Profile Settings</h4>
                <p class="text-muted mb-0" style="font-size:0.9rem;">Manage your account information and password.</p>
            </div>

            <!-- Toast Alert Container -->
            <div class="alert-toast-container" id="alertToastContainer">
                <?php if ($success_msg): ?>
                    <div class="alert alert-success">
                        <span class="alert-icon"></span>
                        <span><?= htmlspecialchars($success_msg) ?></span>
                        <button class="alert-close" onclick="this.parentElement.style.display='none';">&times;</button>
                    </div>
                <?php endif; ?>
                <?php if ($error_msg): ?>
                    <div class="alert alert-error">
                        <span class="alert-icon"></span>
                        <span><?= htmlspecialchars($error_msg) ?></span>
                        <button class="alert-close" onclick="this.parentElement.style.display='none';">&times;</button>
                    </div>
                <?php endif; ?>
            </div>
            <script>
                window.addEventListener('DOMContentLoaded', function () {
                    document.querySelectorAll('.alert-toast-container .alert').forEach(function (alert) {
                        setTimeout(function () { alert.style.display = 'none'; }, 3000);
                    });
                });
            </script>

            <div class="row g-4">

                <!-- PROFILE CARD -->
                <div class="col-lg-8">
                    <div class="settings-card h-100">
                        <!-- Header -->
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="settings-avatar">
                                <?= strtoupper(substr($user['full_name'] ?: $user['username'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="fw-bold fs-5"><?= htmlspecialchars($user['full_name'] ?: 'N/A') ?></div>
                                <div class="text-muted" style="font-size:0.9rem;">@<?= htmlspecialchars($user['username']) ?></div>
                                <span class="role-badge mt-1"><?= ucfirst($user['role']) ?></span>
                            </div>
                        </div>

                        <div class="divider"></div>

                        <div class="section-label">Account Information</div>

                        <form method="POST" id="profileForm">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" style="font-size:0.85rem;">Full Name</label>
                                    <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" style="font-size:0.85rem;">Username</label>
                                    <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" style="font-size:0.85rem;">Role</label>
                                    <input type="text" class="form-control" value="<?= ucfirst($user['role']) ?>" readonly>
                                </div>
                            </div>

                            <div class="d-flex gap-2 mt-4 flex-wrap">
                                <button type="button" id="editBtn" class="btn btn-edit-profile">
                                    <i class="bi bi-pencil me-1"></i> Edit Profile
                                </button>
                                <button type="button" id="saveBtn" class="btn btn-success d-none" data-bs-toggle="modal" data-bs-target="#updateConfirmModal">
                                    <i class="bi bi-check-circle me-1"></i> Save Changes
                                </button>
                                <button type="button" id="cancelBtn" class="btn btn-outline-secondary d-none">
                                    <i class="bi bi-x-circle me-1"></i> Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ACTIONS CARD -->
                <div class="col-lg-4">
                    <div class="settings-card h-100">
                        <div class="section-label">Security</div>

                        <p class="text-muted mb-4" style="font-size:0.85rem;">
                            Keep your account secure by updating your password regularly.
                        </p>

                        <button class="btn btn-warning w-100 mb-3" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                            <i class="bi bi-key me-2"></i> Change Password
                        </button>

                        <div class="divider"></div>

                        <div class="section-label">Danger Zone</div>
                        <p class="text-muted" style="font-size:0.85rem;">
                            Signing out will end your current session.
                        </p>
                        <a href="logout.php" class="btn btn-outline-danger w-100">
                            <i class="bi bi-box-arrow-right me-2"></i> Sign Out
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- CHANGE PASSWORD MODAL -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                    <i class="bi bi-key-fill text-warning"></i> Change Password
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?php if ($password_change_error): ?>
                        <div class="alert alert-danger py-2" style="font-size:0.875rem;">
                            <i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($password_change_error) ?>
                        </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" style="font-size:0.85rem;">Current Password</label>
                        <input type="password" name="current_password" class="form-control" placeholder="Enter current password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" style="font-size:0.85rem;">New Password</label>
                        <input type="password" name="new_password" class="form-control" placeholder="At least 6 characters" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" style="font-size:0.85rem;">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="Repeat new password" required>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="change_password" class="btn btn-warning">
                        <i class="bi bi-check-circle me-1"></i> Update Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- CONFIRM PROFILE UPDATE MODAL -->
<div class="modal fade" id="updateConfirmModal" tabindex="-1" aria-labelledby="updateConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                    <i class="bi bi-pencil-square text-primary"></i> Confirm Update
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to save these profile changes?
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="confirmSaveBtn" class="btn btn-primary">
                    <i class="bi bi-check-circle me-1"></i> Save
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Sidebar toggle
const sidebarToggle = document.getElementById('sidebarToggle');
if (sidebarToggle) {
    sidebarToggle.addEventListener('click', function () {
        document.getElementById('sidebar')?.classList.toggle('show');
    });
}

// Auto-show change password modal if there was an error
<?php if ($password_change_error): ?>
document.addEventListener('DOMContentLoaded', function () {
    new bootstrap.Modal(document.getElementById('changePasswordModal')).show();
});
<?php endif; ?>

// Profile editing
const profileForm = document.getElementById('profileForm');
const editBtn     = document.getElementById('editBtn');
const saveBtn     = document.getElementById('saveBtn');
const cancelBtn   = document.getElementById('cancelBtn');
const confirmSaveBtn = document.getElementById('confirmSaveBtn');

const editableFields = ['full_name', 'username'];
const originalValues = {};

editableFields.forEach(name => {
    const input = profileForm.querySelector(`[name="${name}"]`);
    if (input) originalValues[name] = input.value;
});

editBtn.addEventListener('click', function () {
    editableFields.forEach(name => {
        const input = profileForm.querySelector(`[name="${name}"]`);
        if (input) input.removeAttribute('readonly');
    });
    editBtn.classList.add('d-none');
    saveBtn.classList.remove('d-none');
    cancelBtn.classList.remove('d-none');
});

cancelBtn.addEventListener('click', function () {
    editableFields.forEach(name => {
        const input = profileForm.querySelector(`[name="${name}"]`);
        if (input) {
            input.value = originalValues[name];
            input.setAttribute('readonly', 'readonly');
        }
    });
    editBtn.classList.remove('d-none');
    saveBtn.classList.add('d-none');
    cancelBtn.classList.add('d-none');
});

confirmSaveBtn.addEventListener('click', function () {
    let hidden = profileForm.querySelector('input[name="update_profile"]');
    if (!hidden) {
        hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'update_profile';
        hidden.value = '1';
        profileForm.appendChild(hidden);
    }
    profileForm.submit();
});
</script>
</body>
</html>
</html>