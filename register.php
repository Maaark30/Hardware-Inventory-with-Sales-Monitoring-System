<?php
session_start();
include 'project.php';

// ── If an admin already exists, this page is no longer needed ──
$adminCheck = $conn->query("SELECT COUNT(*) AS cnt FROM users WHERE role = 'admin'");
$adminCount = (int)($adminCheck->fetch_assoc()['cnt'] ?? 0);
if ($adminCount > 0) {
    header("Location: login.php");
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
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Registration — K&J B Hardware</title>
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

    /* ── Brand ── */
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
    }

    .brand p {
      margin-top: 8px;
      font-size: 14px;
      color: rgba(255,255,255,0.5);
      letter-spacing: 0.04em;
    }

    /* ── Card ── */
    .login-card {
      background: rgba(255,255,255,0.08);
      border: 0.5px solid rgba(255,255,255,0.18);
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
      border-radius: 16px;
      padding: 2.25rem 2rem;
      width: 340px;
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

    /* ── Fields ── */
    .field-group { margin-bottom: 14px; }

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

    .field-wrap svg { flex-shrink: 0; opacity: 0.4; }

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

    .field-wrap input::placeholder { color: rgba(255,255,255,0.3); }

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

    /* ── Password feedback ── */
    .pw-feedback {
      margin-top: 8px;
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .pw-rule {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 12px;
      color: rgba(255,255,255,0.35);
      transition: color 0.2s;
    }

    .pw-rule.valid { color: #4ade80; }

    .pw-rule-dot {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: rgba(255,255,255,0.2);
      flex-shrink: 0;
      transition: background 0.2s;
    }

    .pw-rule.valid .pw-rule-dot { background: #4ade80; }

    /* ── Confirm match ── */
    .match-msg {
      font-size: 12px;
      margin-top: 6px;
    }
    .match-msg.ok  { color: #4ade80; }
    .match-msg.err { color: #f87171; }

    /* ── Submit ── */
    .btn-register {
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
    .btn-register:hover  { background: #1248cc; transform: translateY(-1px); }
    .btn-register:active { transform: translateY(0); background: #0e3da8; }

    /* ── Login link ── */
    .login-link {
      text-align: center;
      margin-top: 16px;
      font-size: 13px;
      color: rgba(255,255,255,0.45);
    }

    .login-link a {
      color: white;
      font-weight: 600;
      text-decoration: none;
      transition: color 0.2s;
    }
    .login-link a:hover { color: #ffcc00; }

    /* ── Error modal ── */
    #regErrorModal .modal-content {
      background: rgba(12, 18, 42, 0.92);
      backdrop-filter: blur(22px);
      -webkit-backdrop-filter: blur(22px);
      border: 0.5px solid rgba(255,255,255,0.12);
      border-radius: 20px;
      overflow: hidden;
      color: white;
    }
    #regErrorModal .modal-header {
      background: linear-gradient(135deg, rgba(220,38,38,0.55) 0%, rgba(153,27,27,0.45) 100%);
      border-bottom: 0.5px solid rgba(255,255,255,0.08);
      padding: 18px 22px 14px;
      align-items: center;
    }
    #regErrorModal .modal-title {
      font-size: 15px; font-weight: 700; color: #fff;
      display: flex; align-items: center; gap: 9px;
    }
    #regErrorModal .btn-close { filter: invert(1) grayscale(1); opacity: 0.5; }
    #regErrorModal .btn-close:hover { opacity: 1; }
    #regErrorModal .modal-body { padding: 26px 24px 12px; text-align: center; }
    #regErrorModal .err-icon {
      width: 58px; height: 58px; border-radius: 50%;
      background: rgba(220,38,38,0.15); border: 1.5px solid rgba(220,38,38,0.3);
      display: inline-flex; align-items: center; justify-content: center;
      margin-bottom: 16px;
    }
    #regErrorModal .err-title { font-size: 16px; font-weight: 700; color: #fff; margin-bottom: 8px; }
    #regErrorModal .err-msg { font-size: 13.5px; color: rgba(255,255,255,0.7); line-height: 1.6; margin: 0; }
    #regErrorModal .modal-footer {
      border-top: 0.5px solid rgba(255,255,255,0.07);
      padding: 14px 22px 20px; justify-content: center; background: transparent;
    }
    #regErrorModal .btn-try {
      background: #1a58ec; border: none; color: #fff;
      font-size: 13.5px; font-weight: 600; padding: 10px 36px;
      border-radius: 9px; cursor: pointer; letter-spacing: 0.02em;
      transition: background 0.2s, transform 0.15s; font-family: inherit;
    }
    #regErrorModal .btn-try:hover { background: #1248cc; transform: translateY(-1px); }

    /* ── Animations ── */
    @keyframes fadeScale {
      from { opacity: 0; transform: scale(0.96); }
      to   { opacity: 1; transform: scale(1); }
    }
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(16px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* Hide browser-native password reveal button */
    input[type="password"]::-ms-reveal,
    input[type="password"]::-ms-clear,
    input[type="password"]::-webkit-credentials-auto-fill-button,
    input[type="password"]::-webkit-contacts-auto-fill-button {
      display: none !important;
      visibility: hidden;
    }

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
    <div class="card-eyebrow">Admin access</div>
    <div class="card-title">Create an account</div>

    <form action="query.php" method="POST" onsubmit="return validateForm(event)" autocomplete="off">

      <!-- Hidden role field — always admin -->
      <input type="hidden" name="role" value="admin">

      <!-- Full name -->
      <div class="field-group">
        <label class="field-label" for="full_name">Full name</label>
        <div class="field-wrap">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="8" r="4"/>
            <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
          </svg>
          <input type="text" id="full_name" name="full_name" placeholder="Enter full name" required>
        </div>
      </div>

      <!-- Username -->
      <div class="field-group">
        <label class="field-label" for="username">Username</label>
        <div class="field-wrap">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
            <circle cx="12" cy="7" r="4"/>
          </svg>
          <input type="text" id="username" name="username" placeholder="Choose a username" required>
        </div>
      </div>

      <!-- Password -->
      <div class="field-group">
        <label class="field-label" for="password">Password</label>
        <div class="field-wrap">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2"/>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
          <input type="password" id="password" name="password" placeholder="Create a password" required>
          <button type="button" class="toggle-pw" onclick="togglePw('password','eye1-show','eye1-hide')" aria-label="Toggle password">
            <svg id="eye1-show" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
            <svg id="eye1-hide" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none">
              <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
              <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
              <line x1="1" y1="1" x2="23" y2="23"/>
            </svg>
          </button>
        </div>
        <!-- Strength rules -->
        <div class="pw-feedback" id="pw-feedback" style="display:none;">
          <div class="pw-rule" id="rule-length"><div class="pw-rule-dot"></div>At least 8 characters</div>
          <div class="pw-rule" id="rule-upper"><div class="pw-rule-dot"></div>One uppercase letter</div>
          <div class="pw-rule" id="rule-lower"><div class="pw-rule-dot"></div>One lowercase letter</div>
          <div class="pw-rule" id="rule-number"><div class="pw-rule-dot"></div>One number</div>
        </div>
      </div>

      <!-- Confirm password -->
      <div class="field-group" style="margin-bottom: 20px;">
        <label class="field-label" for="confirm_password">Confirm password</label>
        <div class="field-wrap">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2"/>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
          <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter password" required>
          <button type="button" class="toggle-pw" onclick="togglePw('confirm_password','eye2-show','eye2-hide')" aria-label="Toggle confirm password">
            <svg id="eye2-show" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
            <svg id="eye2-hide" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none">
              <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
              <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
              <line x1="1" y1="1" x2="23" y2="23"/>
            </svg>
          </button>
        </div>
        <div id="match-msg" class="match-msg"></div>
      </div>

      <button type="submit" name="register" class="btn-register">Create admin account</button>

    </form>

    <div class="login-link">
      Already have an account? <a href="login.php">Sign in</a>
    </div>

    <!-- Inline validation error -->
    <div id="form-error" style="
      display:none;
      margin-top: 14px;
      background: rgba(220,38,38,0.18);
      border: 0.5px solid rgba(220,38,38,0.35);
      border-radius: 9px;
      padding: 10px 13px;
      font-size: 12.5px;
      color: #fca5a5;
      text-align: center;
    "></div>
  </div>
</div>

<?php if (isset($_GET['error'])): ?>
<?php
  $regErrors = [
    'fields'   => 'All fields are required. Please fill in every field before submitting.',
    'username' => 'That username is already taken. Please choose a different username.',
  ];
  $regErrMsg = $regErrors[$_GET['error']] ?? 'An error occurred. Please try again.';
?>
<div class="modal fade" id="regErrorModal" tabindex="-1" aria-labelledby="regErrorModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered" style="max-width:390px;">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title" id="regErrorModalLabel">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#fca5a5" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
          Registration Error
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
        <div class="err-title">Registration Error</div>
        <p class="err-msg"><?= htmlspecialchars($regErrMsg) ?></p>
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
  // Toggle show/hide password
  function togglePw(inputId, showId, hideId) {
    const input = document.getElementById(inputId);
    const show  = document.getElementById(showId);
    const hide  = document.getElementById(hideId);
    if (input.type === 'password') {
      input.type = 'text';
      show.style.display = 'none';
      hide.style.display = 'block';
    } else {
      input.type = 'password';
      show.style.display = 'block';
      hide.style.display = 'none';
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    const pwInput      = document.getElementById('password');
    const confirmInput = document.getElementById('confirm_password');
    const feedback     = document.getElementById('pw-feedback');
    const matchMsg     = document.getElementById('match-msg');

    const rules = [
      { id: 'rule-length', test: v => v.length >= 8 },
      { id: 'rule-upper',  test: v => /[A-Z]/.test(v) },
      { id: 'rule-lower',  test: v => /[a-z]/.test(v) },
      { id: 'rule-number', test: v => /\d/.test(v) },
    ];

    function checkStrength() {
      const val = pwInput.value;
      feedback.style.display = val ? 'flex' : 'none';
      rules.forEach(r => {
        document.getElementById(r.id).classList.toggle('valid', r.test(val));
      });
      checkMatch();
    }

    function checkMatch() {
      const pw  = pwInput.value;
      const cfm = confirmInput.value;
      if (!cfm) { matchMsg.textContent = ''; matchMsg.className = 'match-msg'; return; }
      if (pw === cfm) {
        matchMsg.textContent = 'Passwords match';
        matchMsg.className = 'match-msg ok';
      } else {
        matchMsg.textContent = 'Passwords do not match';
        matchMsg.className = 'match-msg err';
      }
    }

    pwInput.addEventListener('input', checkStrength);
    confirmInput.addEventListener('input', checkMatch);

    <?php if (isset($_GET['error'])): ?>
    new bootstrap.Modal(document.getElementById('regErrorModal')).show();
    <?php endif; ?>
  });

  function validateForm(event) {
    const pw    = document.getElementById('password').value;
    const cfm   = document.getElementById('confirm_password').value;
    const errEl = document.getElementById('form-error');

    const showErr = (msg) => {
      errEl.textContent = msg;
      errEl.style.display = 'block';
    };

    if (pw !== cfm) {
      showErr('Passwords do not match. Please re-enter your password.');
      document.getElementById('confirm_password').focus();
      return false;
    }

    const strong = pw.length >= 8 && /[A-Z]/.test(pw) && /[a-z]/.test(pw) && /\d/.test(pw);
    if (!strong) {
      showErr('Password does not meet all strength requirements. Please follow the rules above.');
      document.getElementById('password').focus();
      return false;
    }

    errEl.style.display = 'none';
    return true;
  }
</script>

</body>
</html>