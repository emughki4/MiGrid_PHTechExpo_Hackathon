<?php
/**
 * register.php
 * Handles user account creation only.
 * Houses will be created later from the dashboard.
 */

require_once 'config.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$errors   = [];
$success  = false;
$formData = ['full_name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── 1. Sanitise inputs ────────────────────────────────────────────────
    $full_name    = trim($_POST['full_name']    ?? '');
    $email        = trim($_POST['email']        ?? '');
    $phone        = trim($_POST['phone']        ?? '');
    $password     = $_POST['password']          ?? '';
    $confirm_pass = $_POST['confirm_password']  ?? '';

    $formData = compact('full_name', 'email', 'phone');

    // ── 2. Field validation ───────────────────────────────────────────────
    if (empty($full_name)) {
        $errors[] = 'Full name is required.';
    } elseif (strlen($full_name) > 100) {
        $errors[] = 'Full name must be 100 characters or fewer.';
    }

    if (empty($email)) {
        $errors[] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (strlen($email) > 100) {
        $errors[] = 'Email must be 100 characters or fewer.';
    }

    if (!empty($phone) && !preg_match('/^\+?[0-9\s\-]{7,20}$/', $phone)) {
        $errors[] = 'Please enter a valid phone number.';
    }

    if (empty($password)) {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if ($password !== $confirm_pass) {
        $errors[] = 'Passwords do not match.';
    }

    // ── 3. Check duplicate email ─────────────────────────────────────────
    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with this email already exists.';
        }
    }

    // ── 4. Create user (no house) ────────────────────────────────────────
    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_BCRYPT);

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO users
                    (full_name, email, phone, password_hash, role)
                 VALUES
                    (:full_name, :email, :phone, :password_hash, "user")'
            );
            $stmt->execute([
                ':full_name'     => $full_name,
                ':email'         => $email,
                ':phone'         => $phone ?: null,
                ':password_hash' => $password_hash,
            ]);
            $success = true;
        } catch (PDOException $e) {
            $errors[] = 'Registration failed. Please try again. (' . $e->getMessage() . ')';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Create Account — VoltMesh</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Geist+Mono:wght@300;400;500&display=swap" rel="stylesheet"/>
  <style>
    body { font-family: 'Syne', sans-serif; }
    .mono { font-family: 'Geist Mono', monospace; }

    .bg-mesh {
      background-color: #020c18;
      background-image:
        radial-gradient(ellipse 80% 60% at 10% 20%, rgba(16,185,129,0.10) 0%, transparent 60%),
        radial-gradient(ellipse 60% 50% at 90% 80%, rgba(6,182,212,0.08) 0%, transparent 55%),
        radial-gradient(ellipse 40% 40% at 50% 50%, rgba(16,185,129,0.04) 0%, transparent 70%);
    }

    .grid-overlay {
      background-image:
        linear-gradient(rgba(16,185,129,0.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(16,185,129,0.04) 1px, transparent 1px);
      background-size: 48px 48px;
    }

    .card-glass {
      background: rgba(2, 16, 30, 0.85);
      backdrop-filter: blur(20px);
      border: 1px solid rgba(16,185,129,0.15);
    }

    .field-input {
      background: rgba(16,185,129,0.04);
      border: 1px solid rgba(16,185,129,0.15);
      color: #e2fdf4;
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    .field-input:focus {
      outline: none;
      border-color: rgba(16,185,129,0.5);
      box-shadow: 0 0 0 3px rgba(16,185,129,0.08);
    }
    .field-input::placeholder { color: rgba(156,220,190,0.35); }

    .pw-eye { color: rgba(16,185,129,0.4); transition: color 0.2s; cursor: pointer; }
    .pw-eye:hover { color: #10b981; }

    .btn-submit {
      background: linear-gradient(135deg, #059669 0%, #0d9488 100%);
      transition: opacity 0.2s, transform 0.15s;
    }
    .btn-submit:hover  { opacity: 0.92; transform: translateY(-1px); }
    .btn-submit:active { transform: translateY(0); opacity: 1; }

    .strength-seg { transition: background 0.3s; }

    @keyframes fadeSlide { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:none; } }
    .anim-1 { animation: fadeSlide 0.5s 0.05s ease both; }
    .anim-2 { animation: fadeSlide 0.5s 0.12s ease both; }
    .anim-3 { animation: fadeSlide 0.5s 0.19s ease both; }
    .anim-4 { animation: fadeSlide 0.5s 0.26s ease both; }
    .anim-5 { animation: fadeSlide 0.5s 0.33s ease both; }
    .anim-6 { animation: fadeSlide 0.5s 0.40s ease both; }
    .anim-7 { animation: fadeSlide 0.5s 0.47s ease both; }
    .anim-8 { animation: fadeSlide 0.5s 0.54s ease both; }
  </style>
</head>
<body class="bg-mesh grid-overlay min-h-screen flex items-center justify-center p-4">

  <div class="fixed inset-0 pointer-events-none overflow-hidden" aria-hidden="true">
    <div class="absolute top-1/4 -left-32 w-64 h-64 rounded-full border border-emerald-500/5"></div>
    <div class="absolute bottom-1/4 -right-24 w-96 h-96 rounded-full border border-cyan-500/5"></div>
    <div class="absolute top-10 right-10 mono text-emerald-500/10 text-xs leading-6 select-none">
      VOLTMESH<br/>v1.0.0<br/>NODE NET
    </div>
  </div>

  <div class="w-full max-w-md relative z-10">

    <div class="text-center mb-8 anim-1">
      <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl border border-emerald-500/25 bg-emerald-950/40 mb-4">
        <svg class="w-7 h-7 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>
        </svg>
      </div>
      <h1 class="text-2xl font-extrabold text-white tracking-wide">VoltMesh</h1>
      <p class="text-emerald-400/60 mono text-xs tracking-widest mt-1">DECENTRALIZED ENERGY NETWORK</p>
    </div>

    <div class="card-glass rounded-2xl p-8">

      <h2 class="text-lg font-semibold text-white mb-1 anim-2">Create your account</h2>
      <p class="text-sm text-slate-400 mb-6 anim-2">You can set up your house later from the dashboard.</p>

      <?php if ($success): ?>
      <div class="rounded-xl border border-emerald-500/30 bg-emerald-950/40 p-5 text-center">
        <div class="flex justify-center mb-3">
          <div class="w-12 h-12 rounded-full bg-emerald-500/15 border border-emerald-500/30 flex items-center justify-center">
            <svg class="w-6 h-6 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
          </div>
        </div>
        <h3 class="text-emerald-300 font-semibold text-base mb-1">Account created!</h3>
        <p class="text-slate-400 text-sm mb-4">You can now log in and start managing your energy.</p>
        <a href="login.php" class="inline-block w-full btn-submit text-white font-semibold py-2.5 rounded-xl text-sm text-center">
          Continue to Login →
        </a>
      </div>

      <?php else: ?>

      <?php if (!empty($errors)): ?>
      <div class="rounded-xl border border-red-500/30 bg-red-950/30 p-4 mb-5 anim-2">
        <div class="flex gap-2.5 items-start">
          <svg class="w-4 h-4 text-red-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
          </svg>
          <ul class="text-sm text-red-300 space-y-0.5">
            <?php foreach ($errors as $err): ?>
            <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
      <?php endif; ?>

      <form method="POST" action="register.php" id="registerForm" novalidate>

        <!-- Full Name -->
        <div class="mb-4 anim-3">
          <label class="block text-xs font-medium text-slate-300 mb-1.5 tracking-wide">Full Name</label>
          <input type="text" name="full_name" id="full_name" autocomplete="name" required
                 value="<?= htmlspecialchars($formData['full_name']) ?>"
                 placeholder="Ada Okafor"
                 class="field-input w-full rounded-xl px-4 py-2.5 text-sm"/>
        </div>

        <!-- Email -->
        <div class="mb-4 anim-3">
          <label class="block text-xs font-medium text-slate-300 mb-1.5 tracking-wide">Email Address</label>
          <input type="email" name="email" id="email" autocomplete="email" required
                 value="<?= htmlspecialchars($formData['email']) ?>"
                 placeholder="ada@example.com"
                 class="field-input w-full rounded-xl px-4 py-2.5 text-sm"/>
        </div>

        <!-- Phone -->
        <div class="mb-4 anim-4">
          <label class="block text-xs font-medium text-slate-300 mb-1.5 tracking-wide">
            Phone <span class="text-slate-500 font-normal">(optional)</span>
          </label>
          <input type="tel" name="phone" id="phone" autocomplete="tel"
                 value="<?= htmlspecialchars($formData['phone']) ?>"
                 placeholder="+234 800 000 0000"
                 class="field-input w-full rounded-xl px-4 py-2.5 text-sm"/>
        </div>

        <!-- Password -->
        <div class="mb-4 anim-5">
          <label class="block text-xs font-medium text-slate-300 mb-1.5 tracking-wide">Password</label>
          <div class="relative">
            <input type="password" name="password" id="password" autocomplete="new-password" required
                   placeholder="Min. 8 characters"
                   class="field-input w-full rounded-xl px-4 py-2.5 text-sm pr-10"
                   oninput="checkStrength(this.value)"/>
            <button type="button" class="pw-eye absolute right-3 top-1/2 -translate-y-1/2" onclick="togglePw('password', this)" aria-label="Toggle password visibility">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
              </svg>
            </button>
          </div>
          <div class="flex gap-1 mt-2" id="strength-bar">
            <div class="strength-seg h-1 flex-1 rounded-full bg-slate-700" id="s1"></div>
            <div class="strength-seg h-1 flex-1 rounded-full bg-slate-700" id="s2"></div>
            <div class="strength-seg h-1 flex-1 rounded-full bg-slate-700" id="s3"></div>
            <div class="strength-seg h-1 flex-1 rounded-full bg-slate-700" id="s4"></div>
          </div>
          <p class="text-xs text-slate-500 mt-1" id="strength-label"></p>
        </div>

        <!-- Confirm Password -->
        <div class="mb-5 anim-5">
          <label class="block text-xs font-medium text-slate-300 mb-1.5 tracking-wide">Confirm Password</label>
          <div class="relative">
            <input type="password" name="confirm_password" id="confirm_password" autocomplete="new-password" required
                   placeholder="Re-enter your password"
                   class="field-input w-full rounded-xl px-4 py-2.5 text-sm pr-10"/>
            <button type="button" class="pw-eye absolute right-3 top-1/2 -translate-y-1/2" onclick="togglePw('confirm_password', this)" aria-label="Toggle confirm password visibility">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
              </svg>
            </button>
          </div>
        </div>

        <div class="anim-6">
          <button type="submit" class="btn-submit w-full text-white font-semibold py-3 rounded-xl text-sm tracking-wide">
            Create Account
          </button>
        </div>

      </form>

      <?php endif; ?>

      <p class="text-center text-sm text-slate-500 mt-6 anim-7">
        Already have an account?
        <a href="login.php" class="text-emerald-400 hover:text-emerald-300 font-medium transition-colors">Sign in</a>
      </p>

    </div>

    <p class="mono text-center text-xs text-slate-600 mt-5 anim-8">
      VoltMesh Energy Platform &nbsp;·&nbsp; Decentralized Power Grid
    </p>

  </div>

  <script>
  function togglePw(fieldId, btn) {
    const input = document.getElementById(fieldId);
    const isText = input.type === 'text';
    input.type = isText ? 'password' : 'text';
    const svg = btn.querySelector('svg');
    if (isText) {
      svg.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>`;
    } else {
      svg.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>`;
    }
  }

  function checkStrength(val) {
    let score = 0;
    if (val.length >= 8)                         score++;
    if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++;
    if (/[0-9]/.test(val))                       score++;
    if (/[^A-Za-z0-9]/.test(val))               score++;
    const colors  = ['bg-red-500', 'bg-orange-400', 'bg-yellow-400', 'bg-emerald-400'];
    const labels  = ['', 'Weak', 'Fair', 'Good', 'Strong'];
    for (let i = 1; i <= 4; i++) {
      const seg = document.getElementById('s'+i);
      seg.className = 'strength-seg h-1 flex-1 rounded-full ' + (i <= score ? colors[score-1] : 'bg-slate-700');
    }
    const lbl = document.getElementById('strength-label');
    lbl.textContent = val.length ? labels[score] : '';
    lbl.className = 'text-xs mt-1 ' + (val.length ? `text-${colors[score-1].replace('bg-','')}` : 'text-slate-500');
  }

  document.getElementById('registerForm')?.addEventListener('submit', function(e) {
    const pw  = document.getElementById('password').value;
    const cpw = document.getElementById('confirm_password').value;
    if (pw !== cpw) {
      e.preventDefault();
      document.getElementById('confirm_password').style.borderColor = 'rgba(239,68,68,0.6)';
      document.getElementById('confirm_password').focus();
    }
  });
  document.getElementById('confirm_password')?.addEventListener('input', function() {
    this.style.borderColor = '';
  });
  </script>
</body>
</html>