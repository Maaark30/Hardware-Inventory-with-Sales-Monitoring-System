<!-- <?php
session_start();
include 'project.php';

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['id'];

// Fetch user info
$stmt = $conn->prepare("SELECT username, role, full_name, password FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die("User not found.");
}

// --- PASSWORD VERIFICATION LOGIC for Profile Access ---

if (!isset($_SESSION['profile_verified'])) {
    $_SESSION['profile_verified'] = false;
}

// Handle Password verification submission from the modal
if (isset($_POST['verify_password'])) {
    $submitted_password = $_POST['verification_password'];

    if (password_verify($submitted_password, $user['password'])) {
        $_SESSION['profile_verified'] = true;
        header("Location: my_profile.php");
        exit();
    } else {
        $password_error = "Incorrect Password. Please try again.";
    }
}


// Update profile
if (isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $username = trim($_POST['username']);

    $update = $conn->prepare("UPDATE users SET full_name=?, username=? WHERE id=?");
    $update->bind_param("ssi", $full_name, $username, $user_id);

    if ($update->execute()) {
        $_SESSION['success'] = "Profile updated successfully!";

        // Re-fetch user data
        $stmt = $conn->prepare("SELECT username, role, full_name, password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        header("Location: my_profile.php");
        exit();
    } else {
        $error = "Error updating profile. Please try again.";
    }
}

// Change password
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    $temp_pass_error = null;

    if (!password_verify($current_password, $user['password'])) {
        $temp_pass_error = "Current password is incorrect.";
    } elseif ($new_password !== $confirm_password) {
        $temp_pass_error = "New passwords do not match.";
    } else {
        $hashed_new = password_hash($new_password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param("si", $hashed_new, $user_id);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Password changed successfully!";
        } else {
            $temp_pass_error = "Error updating password.";
        }
    }

    if ($temp_pass_error) {
        $_SESSION['password_error'] = $temp_pass_error;
    }

    header("Location: my_profile.php");
    exit();
}

// Check for and display Password error if set
$password_change_error = isset($_SESSION['password_error']) ? $_SESSION['password_error'] : null;
unset($_SESSION['password_error']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/user.css">
    <style>
        body { background-color: #f4f6f9; }
        .profile-card {
            max-width: 650px;
            margin: 60px auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }
        .profile-card h3 { font-weight: bold; color: #1458ec; }
        .form-control[readonly] { background-color: #f8f9fa; }
        .btn-edit { background-color: #198754; color: white; }
        .btn-edit:hover { background-color: #157347; }
        .modal-verify-title { color: #1458ec; font-weight: bold; }
        .btn-primary-pin { background-color: #0d6efd; border-color: #0d6efd; color: white; }
        .btn-primary-pin:hover { background-color: #0b5ed7; border-color: #0a58ca; }
    </style>
</head>
<body>

<?php if (!$_SESSION['profile_verified']): ?>
<div class="modal fade" id="passwordVerificationModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-labelledby="passwordVerificationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title modal-verify-title" id="passwordVerificationModalLabel">Verify Identity</h5>
                </div>
                <div class="modal-body text-center">
                    <p>Please confirm your password to continue.</p>
                    <?php if (isset($password_error)): ?>
                        <div class="alert alert-danger mb-3"><?php echo $password_error; ?></div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <input type="password" name="verification_password" class="form-control form-control-lg text-center"
                               placeholder="Enter Password" required autofocus>
                    </div>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="submit" name="verify_password" class="btn btn-primary w-100">Verify Access</button>
                    <a href="<?php echo ($user['role'] == 'admin') ? 'admin_dashboard.php' : 'staff_dashboard.php'; ?>" class="btn btn-outline-secondary w-100 mt-2">Go Back</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('passwordVerificationModal');
    if (modalElement) {
        const passwordModal = new bootstrap.Modal(modalElement);
        passwordModal.show();
    }
});
</script>
<?php endif; ?>

<div class="d-flex <?php echo (!$_SESSION['profile_verified']) ? 'd-none' : ''; ?>">
    <div class="sidebar flex-column p-3" id="sidebar">
        <div class="sidebar-logo text-center">
            <img src="images/logo.png" alt="Staff Logo">
            <h5 class="mt-2">Staff Panel</h5>
        </div>
        <hr class="text-white">
        <ul class="nav flex-column">
            <li class="sidebar-title">Main</li>
            <li class="nav-item mb-2"><a class="nav-link " href="staff_dashboard.php"><i class="fa fa-home me-2"></i> Dashboard</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="staff_products.php"><i class="fa-solid fa-box me-2"></i> Products</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="purchased_history.php"><i class="fa fa-history me-2"></i> Purchased History</a></li>
            <li class="nav-item mb-2"><a class="nav-link " href="staff_returns.php"><i class="fa fa-undo me-2"></i> Returns & Refunds</a></li> 
            <li class="nav-item mb-2"><a class="nav-link" href="staff_supplier.php"><i class="fa fa-truck me-2"></i> Suppliers</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="staff_stock_history.php"><i class="bi bi-clock-history me-2"></i> Stock History</a></li>
            <li class="nav-item mb-2"><a class="nav-link " href="inventory_value_report.php"><i class="fa fa-hand-holding-dollar me-2"></i> Inventory Value</a></li>
            <li class="nav-item mb-2"><a class="nav-link " href="seasonal_demand_report.php"><i class="fa fa-calendar-alt me-2"></i> Seasonal Demand</a></li>
            <li class="sidebar-title">Account</li>
            <li class="nav-item mb-2"><a class="nav-link active" href="my_profile.php"><i class="fa fa-user me-2"></i> My Profile</a></li>
            <li class="sidebar-title">Others</li>
            <li class="nav-item"><a class="nav-link text-danger" href="logout.php"><i class="fa fa-sign-out-alt me-2"></i> Logout</a></li>
        </ul>
    </div>

    <div class="container">
        <div class="profile-card">
            <h3 class="text-center mb-4"><i class="fa fa-user-circle me-2"></i>My Profile</h3>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php elseif (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php elseif ($password_change_error): ?>
                <div class="alert alert-danger"><?php echo $password_change_error; ?></div>
            <?php endif; ?>

            <form method="POST" id="profileForm">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" readonly>
                </div>
            </form>

            <div class="d-flex justify-content-center gap-3 mt-4">
                <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                    <i class="fa fa-key me-2"></i>Change Password
                </button>
                <button type="button" id="editBtn" class="btn btn-edit">
                    <i class="fa fa-pen me-2"></i>Edit
                </button>
                <button type="button" id="saveBtn" class="btn btn-success d-none">
                    <i class="fa fa-save me-2"></i>Save
                </button>
                <button type="button" id="cancelBtn" class="btn btn-danger d-none">
                    <i class="fa fa-xmark me-2"></i>Cancel
                </button>
            </div>
        </div>
    </div>

    <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header bg-warning text-white">
                        <h5 class="modal-title"><i class="fa fa-key me-2"></i>Change Password</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <?php if ($password_change_error): ?>
                            <div class="alert alert-danger"><?php echo $password_change_error; ?></div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="change_password" class="btn btn-warning">Update Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
<?php if ($_SESSION['profile_verified']): ?>

<?php if ($password_change_error): ?>
document.addEventListener('DOMContentLoaded', function () {
    const passModalElement = document.getElementById('changePasswordModal');
    if (passModalElement) {
        const passModal = new bootstrap.Modal(passModalElement);
        passModal.show();
    }
});
<?php endif; ?>


const profileForm = document.getElementById('profileForm');
const editBtn = document.getElementById('editBtn');
const saveBtn = document.getElementById('saveBtn');
const cancelBtn = document.getElementById('cancelBtn');
const inputs = profileForm.querySelectorAll('input');

const originalValues = {};
inputs.forEach(input => {
    if (input.name !== 'update_profile_trigger' && input.type !== 'password') {
        originalValues[input.name] = input.value;
    }
});

editBtn.addEventListener('click', () => {
    inputs.forEach(input => {
        if (input.name !== 'role' && input.name !== 'update_profile_trigger' && input.type !== 'password') {
            input.removeAttribute('readonly');
        }
    });
    editBtn.classList.add('d-none');
    saveBtn.classList.remove('d-none');
    cancelBtn.classList.remove('d-none');
});

saveBtn.addEventListener('click', () => {
    const isConfirmed = confirm('Are you sure you want to save your profile changes?');

    if (isConfirmed) {
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'update_profile';
        hiddenInput.value = '1';
        profileForm.appendChild(hiddenInput);

        profileForm.submit();
    }
});

cancelBtn.addEventListener('click', () => {
    inputs.forEach(input => {
        if (originalValues[input.name] !== undefined) {
            input.value = originalValues[input.name];
        }

        if (input.name !== 'role' && input.name !== 'pin_code' && input.name !== 'update_profile_trigger' && input.type !== 'password') {
            input.setAttribute('readonly', 'readonly');
        }
    });

    editBtn.classList.remove('d-none');
    saveBtn.classList.add('d-none');
    cancelBtn.classList.add('d-none');
});

<?php endif; ?>
</script>
</body>
</html> -->