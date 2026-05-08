<?php
session_start();
include 'project.php';

// ── If no admin account exists yet, force registration first ──
$adminCheck = $conn->query("SELECT COUNT(*) AS cnt FROM users WHERE role = 'admin'");
$adminCount = (int)($adminCheck->fetch_assoc()['cnt'] ?? 0);
if ($adminCount === 0) {
    header("Location: register.php");
    exit();
}

if (isset($_SESSION['id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: staff_dashboard.php");
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — K&J B Hardware</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    html, body {
      height: 100%;
      font-family: 'Arial', sans-serif;
      background:
        linear-gradient(135deg, rgba(10,20,50,0.72) 0%, rgba(20,50,110,0.65) 100%),
        url('images/image1.jpg') no-repeat center center fixed;
      background-size: cover;
    }

    .login-wrapper {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 64px;
      padding: 2rem;
      flex-wrap: wrap;
    }

    /* ── Brand panel ── */
    .brand {
      text-align: center;
      color: white;
      animation: fadeUp 0.7s ease both;
    }

    .brand-logo {
      width: 90px;
      height: 90px;
      border-radius: 50%;
      border: 2px solid rgba(255,255,255,0.25);
      object-fit: cover;
      margin-bottom: 18px;
      background: rgba(255,255,255,0.1);
    }

    .brand h1 {
      font-size: 28px;
      font-weight: 700;
      line-height: 1.25;
      letter-spacing: -0.3px;
    }

    .brand p {
      margin-top: 8px;
      font-size: 14px;
      color: rgba(255,255,255,0.5);
      letter-spacing: 0.04em;
    }

    /* ── Glass card ── */
    .login-card {
      background: rgba(255,255,255,0.08);
      border: 0.5px solid rgba(255,255,255,0.18);
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
      border-radius: 16px;
      padding: 2.25rem 2rem;
      width: 320px;
      color: white;
      animation: fadeScale 0.6s ease both;
    }

    .card-eyebrow {
      font-size: 11px;
      font-weight: 600;
      letter-spacing: 0.09em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.45);
      margin-bottom: 6px;
    }

    .card-title {
      font-size: 22px;
      font-weight: 700;
      color: white;
      margin-bottom: 1.75rem;
    }

    /* ── Field groups ── */
    .field-group {
      margin-bottom: 14px;
    }

    .field-label {
      display: block;
      font-size: 12px;
      color: rgba(255,255,255,0.55);
      margin-bottom: 6px;
    }

    .field-wrap {
      display: flex;
      align-items: center;
      gap: 10px;
      background: rgba(255,255,255,0.07);
      border: 0.5px solid rgba(255,255,255,0.18);
      border-radius: 9px;
      padding: 0 13px;
      transition: border-color 0.2s, background 0.2s;
    }

    .field-wrap:focus-within {
      border-color: rgba(80,140,255,0.7);
      background: rgba(255,255,255,0.11);
    }

    .field-wrap svg {
      flex-shrink: 0;
      opacity: 0.4;
    }

    .field-wrap input {
      flex: 1;
      background: none;
      border: none;
      outline: none;
      color: white;
      font-size: 14px;
      padding: 11px 0;
      font-family: inherit;
    }

    .field-wrap input::placeholder {
      color: rgba(255,255,255,0.3);
    }

    .toggle-pw {
      background: none;
      border: none;
      padding: 0;
      cursor: pointer;
      opacity: 0.4;
      transition: opacity 0.2s;
      display: flex;
      align-items: center;
      color: white;
    }

    .toggle-pw:hover { opacity: 0.75; }

    /* Hide browser-native password reveal button */
    input[type="password"]::-ms-reveal,
    input[type="password"]::-ms-clear,
    input[type="password"]::-webkit-credentials-auto-fill-button,
    input[type="password"]::-webkit-contacts-auto-fill-button {
      display: none !important;
      visibility: hidden;
    }

    /* ── Submit button ── */
    .btn-login {
      width: 100%;
      background: #1a58ec;
      border: none;
      color: white;
      font-size: 14px;
      font-weight: 600;
      padding: 11px;
      border-radius: 9px;
      cursor: pointer;
      margin-top: 8px;
      letter-spacing: 0.02em;
      transition: background 0.2s, transform 0.15s;
      font-family: inherit;
    }

    .btn-login:hover {
      background: #1248cc;
      transform: translateY(-1px);
    }

    .btn-login:active {
      transform: translateY(0);
      background: #0e3da8;
    }

    /* ── Divider ── */
    .divider {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-top: 20px;
    }

    .divider-line {
      flex: 1;
      height: 0.5px;
      background: rgba(255,255,255,0.13);
    }

    .divider-text {
      font-size: 11px;
      color: rgba(255,255,255,0.3);
      letter-spacing: 0.05em;
    }

    /* ── Success modal (registration) ── */
    #loginSuccessModal .modal-content {
      background: rgba(12, 18, 42, 0.92);
      backdrop-filter: blur(22px);
      -webkit-backdrop-filter: blur(22px);
      border: 0.5px solid rgba(255,255,255,0.12);
      border-radius: 20px;
      overflow: hidden;
      color: white;
    }
    #loginSuccessModal .modal-header {
      background: linear-gradient(135deg, rgba(16,185,129,0.5) 0%, rgba(5,150,105,0.4) 100%);
      border-bottom: 0.5px solid rgba(255,255,255,0.08);
      padding: 18px 22px 14px;
      align-items: center;
    }
    #loginSuccessModal .modal-title {
      font-size: 15px;
      font-weight: 700;
      color: #fff;
      display: flex;
      align-items: center;
      gap: 9px;
    }
    #loginSuccessModal .btn-close {
      filter: invert(1) grayscale(1);
      opacity: 0.5;
    }
    #loginSuccessModal .btn-close:hover { opacity: 1; }
    #loginSuccessModal .modal-body {
      padding: 26px 24px 12px;
      text-align: center;
    }
    #loginSuccessModal .suc-icon {
      width: 58px; height: 58px;
      border-radius: 50%;
      background: rgba(16,185,129,0.15);
      border: 1.5px solid rgba(16,185,129,0.35);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 16px;
    }
    #loginSuccessModal .suc-title {
      font-size: 16px;
      font-weight: 700;
      color: #fff;
      margin-bottom: 8px;
    }
    #loginSuccessModal .suc-msg {
      font-size: 13.5px;
      color: rgba(255,255,255,0.7);
      line-height: 1.6;
      margin: 0;
    }
    #loginSuccessModal .modal-footer {
      border-top: 0.5px solid rgba(255,255,255,0.07);
      padding: 14px 22px 20px;
      justify-content: center;
      background: transparent;
    }
    #loginSuccessModal .btn-ok {
      background: #059669;
      border: none;
      color: #fff;
      font-size: 13.5px;
      font-weight: 600;
      padding: 10px 36px;
      border-radius: 9px;
      cursor: pointer;
      letter-spacing: 0.02em;
      transition: background 0.2s, transform 0.15s;
      font-family: inherit;
    }
    #loginSuccessModal .btn-ok:hover { background: #047857; transform: translateY(-1px); }

    /* ── Error modal ── */
    #loginErrorModal .modal-content {
      background: rgba(12, 18, 42, 0.92);
      backdrop-filter: blur(22px);
      -webkit-backdrop-filter: blur(22px);
      border: 0.5px solid rgba(255,255,255,0.12);
      border-radius: 20px;
      overflow: hidden;
      color: white;
    }
    #loginErrorModal .modal-header {
      background: linear-gradient(135deg, rgba(220,38,38,0.55) 0%, rgba(153,27,27,0.45) 100%);
      border-bottom: 0.5px solid rgba(255,255,255,0.08);
      padding: 18px 22px 14px;
      align-items: center;
    }
    #loginErrorModal .modal-title {
      font-size: 15px;
      font-weight: 700;
      color: #fff;
      display: flex;
      align-items: center;
      gap: 9px;
    }
    #loginErrorModal .btn-close {
      filter: invert(1) grayscale(1);
      opacity: 0.5;
    }
    #loginErrorModal .btn-close:hover { opacity: 1; }
    #loginErrorModal .modal-body {
      padding: 26px 24px 12px;
      text-align: center;
    }
    #loginErrorModal .err-icon {
      width: 58px; height: 58px;
      border-radius: 50%;
      background: rgba(220,38,38,0.15);
      border: 1.5px solid rgba(220,38,38,0.3);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 16px;
    }
    #loginErrorModal .err-title {
      font-size: 16px;
      font-weight: 700;
      color: #fff;
      margin-bottom: 8px;
    }
    #loginErrorModal .err-msg {
      font-size: 13.5px;
      color: rgba(255,255,255,0.7);
      line-height: 1.6;
      margin: 0;
    }
    #loginErrorModal .err-tip {
      margin-top: 10px;
      font-size: 11.5px;
      color: rgba(255,255,255,0.35);
    }
    #loginErrorModal .modal-footer {
      border-top: 0.5px solid rgba(255,255,255,0.07);
      padding: 14px 22px 20px;
      justify-content: center;
      background: transparent;
    }
    #loginErrorModal .btn-try {
      background: #1a58ec;
      border: none;
      color: #fff;
      font-size: 13.5px;
      font-weight: 600;
      padding: 10px 36px;
      border-radius: 9px;
      cursor: pointer;
      letter-spacing: 0.02em;
      transition: background 0.2s, transform 0.15s;
      font-family: inherit;
    }
    #loginErrorModal .btn-try:hover {
      background: #1248cc;
      transform: translateY(-1px);
    }

    /* ── Animations ── */
    @keyframes fadeScale {
      from { opacity: 0; transform: scale(0.96); }
      to   { opacity: 1; transform: scale(1); }
    }

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(16px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ── Responsive ── */
    @media (max-width: 640px) {
      .login-wrapper { gap: 32px; }
      .brand h1 { font-size: 22px; }
      .login-card { width: 100%; max-width: 340px; }
    }
  </style>
</head>
<body>

<div class="login-wrapper">

  <!-- Brand -->
  <div class="brand">
    <img src="images/logo.png" alt="K&J Logo" class="brand-logo">
    <h1>K&amp;J B Hardware &amp;<br>Construction Supplies</h1>
    <p>Your trusted building partner</p>
  </div>

  <!-- Card -->
  <div class="login-card">

    <div class="card-eyebrow">Welcome back</div>
    <div class="card-title">Sign in to your account</div>



    <form action="query.php" method="POST" autocomplete="off">

      <!-- Username -->
      <div class="field-group">
        <label class="field-label" for="username">Username</label>
        <div class="field-wrap">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="8" r="4"/>
            <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
          </svg>
          <input type="text" id="username" name="username" placeholder="Enter username" required autocomplete="username" value="<?= htmlspecialchars($_GET['u'] ?? '') ?>">
        </div>
      </div>

      <!-- Password -->
      <div class="field-group" style="margin-bottom: 20px;">
        <label class="field-label" for="password">Password</label>
        <div class="field-wrap">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2"/>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
          <input type="password" id="password" name="password" placeholder="Enter password" required autocomplete="current-password">
          <button type="button" class="toggle-pw" onclick="togglePassword()" aria-label="Toggle password visibility">
            <svg id="eye-show" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
            <svg id="eye-hide" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
              <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
              <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
              <line x1="1" y1="1" x2="23" y2="23"/>
            </svg>
          </button>
        </div>
      </div>

      <button type="submit" name="login" class="btn-login">Sign in</button>

    </form>

  

  </div>
</div>

<!-- ── Registration Success Modal ── -->
<?php if (isset($_GET['registered'])): ?>
<div class="modal fade" id="loginSuccessModal" tabindex="-1" aria-labelledby="loginSuccessModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:390px;">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title" id="loginSuccessModalLabel">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#6ee7b7" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
          </svg>
          Account Created
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="suc-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#34d399" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
          </svg>
        </div>
        <div class="suc-title">Registration Successful!</div>
        <p class="suc-msg">Your admin account has been created.<br>You can now sign in with your credentials.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-ok" data-bs-dismiss="modal">Sign In Now</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ── Login Error Modal ── -->
<?php if (isset($_GET['error'])): ?>
<?php
  $errType  = $_GET['error'] ?? 'invalid';
  $errTitle = 'Login Failed';
  $errMsg   = 'Incorrect username or password.<br>Please double-check your credentials and try again.';
  if ($errType === 'notfound') {
      $errMsg = 'No account found with that username.<br>Please check the username and try again.';
  }
?>
<div class="modal fade" id="loginErrorModal" tabindex="-1" aria-labelledby="loginErrorModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered" style="max-width:390px;">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title" id="loginErrorModalLabel">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#fca5a5" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
          <?= htmlspecialchars($errTitle) ?>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="err-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#f87171" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
          </svg>
        </div>
        <div class="err-title"><?= htmlspecialchars($errTitle) ?></div>
        <p class="err-msg"><?= $errMsg ?></p>
        <p class="err-tip">Contact your system administrator if the problem persists.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-try" data-bs-dismiss="modal">Try Again</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  function togglePassword() {
    const input    = document.getElementById('password');
    const showIcon = document.getElementById('eye-show');
    const hideIcon = document.getElementById('eye-hide');
    if (input.type === 'password') {
      input.type = 'text';
      showIcon.style.display = 'none';
      hideIcon.style.display = 'block';
    } else {
      input.type = 'password';
      showIcon.style.display = 'block';
      hideIcon.style.display = 'none';
    }
  }

  <?php if (isset($_GET['error'])): ?>
  window.addEventListener('DOMContentLoaded', function () {
    new bootstrap.Modal(document.getElementById('loginErrorModal')).show();
  });
  <?php endif; ?>

  <?php if (isset($_GET['registered'])): ?>
  window.addEventListener('DOMContentLoaded', function () {
    new bootstrap.Modal(document.getElementById('loginSuccessModal')).show();
  });
  <?php endif; ?>
</script>

</body>
</html>