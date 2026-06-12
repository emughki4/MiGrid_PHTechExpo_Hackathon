<?php
/**
 * login.php
 * Handles user login – works even if user has no house or node yet.
 */

require_once 'config.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error    = '';
$email_val = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $email_val = htmlspecialchars($email);

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        // 1. Fetch user
        $stmt = $pdo->prepare('
            SELECT id, full_name, email, password_hash, phone, role
            FROM users
            WHERE email = ?
            LIMIT 1
        ');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = 'Invalid email or password.';
        } else {
            session_regenerate_id(true);

            // Basic user session
            $_SESSION['user_id']    = (int) $user['id'];
            $_SESSION['user_name']  = $user['full_name'];
            $_SESSION['phone']      = $user['phone'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role']  = $user['role'];

            // 2. Check for a house owned by this user
            $stmt = $pdo->prepare('
                SELECT id, name, location, mode, buy_limit_kwh, sell_eligibility
                FROM houses
                WHERE user_id = ?
                LIMIT 1
            ');
            $stmt->execute([$user['id']]);
            $house = $stmt->fetch();

            if ($house) {
                $_SESSION['house_id']   = $house['id'];
                $_SESSION['house_name'] = $house['name'];
                $_SESSION['house_loc']  = $house['location'];
                $_SESSION['house_mode'] = $house['mode'];
                $_SESSION['buy_limit_kwh'] = $house['buy_limit_kwh'];
                $_SESSION['house_sell_eligibility'] = (bool) $house['sell_eligibility'];

                // 3. Try to get the node linked to this house (1:1 via house_node_map)
                $stmt = $pdo->prepare('
                    SELECT n.id, n.node_uid, n.node_name, n.location, n.status
                    FROM house_node_map hnm
                    JOIN nodes n ON hnm.node_id = n.id
                    WHERE hnm.house_id = ?
                    LIMIT 1
                ');
                $stmt->execute([$house['id']]);
                $node = $stmt->fetch();

                if ($node) {
                    $_SESSION['node_id']   = $node['id'];
                    $_SESSION['node_uid']  = $node['node_uid'];
                    $_SESSION['node_name'] = $node['node_name'];
                    $_SESSION['node_loc']  = $node['location'];
                    $_SESSION['node_status'] = $node['status'];
                } else {
                    // House exists but no node paired yet
                    $_SESSION['node_id']   = null;
                    $_SESSION['node_name'] = null;
                    $_SESSION['node_loc']  = null;
                }
            } else {
                // No house at all
                $_SESSION['house_id']   = null;
                $_SESSION['house_name'] = null;
                $_SESSION['house_loc']  = null;
                $_SESSION['house_mode'] = null;
                $_SESSION['buy_limit_kwh'] = null;
                $_SESSION['house_sell_eligibility'] = false;
                $_SESSION['node_id']    = null;
                $_SESSION['node_name']  = null;
                $_SESSION['node_loc']   = null;
            }

            redirect('dashboard.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Sign In — MiGrid</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Geist+Mono:wght@300;400;500&display=swap" rel="stylesheet"/>
  <style>
    body { font-family: 'Syne', sans-serif; }
    .mono { font-family: 'Geist Mono', monospace; }
    .bg-mesh {
      background-color: #020c18;
      background-image:
        radial-gradient(ellipse 80% 60% at 90% 10%,  rgba(6,182,212,0.08) 0%, transparent 60%),
        radial-gradient(ellipse 60% 50% at 10% 90%,  rgba(16,185,129,0.07) 0%, transparent 55%);
    }
    .grid-overlay {
      background-image:
        linear-gradient(rgba(16,185,129,0.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(16,185,129,0.04) 1px, transparent 1px);
      background-size: 48px 48px;
    }
    .card-glass {
      background: rgba(2,16,30,0.85);
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
    .field-input::placeholder { color: rgba(156,220,190,0.3); }
    .pw-eye { color: rgba(16,185,129,0.4); transition: color 0.2s; cursor: pointer; }
    .pw-eye:hover { color: #10b981; }
    .btn-submit {
      background: linear-gradient(135deg, #059669 0%, #0d9488 100%);
      transition: opacity 0.2s, transform 0.15s;
    }
    .btn-submit:hover  { opacity: 0.9; transform: translateY(-1px); }
    .btn-submit:active { transform: translateY(0); }
    @keyframes fadeSlide { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:none; } }
    .anim-1 { animation: fadeSlide 0.5s 0.05s ease both; }
    .anim-2 { animation: fadeSlide 0.5s 0.12s ease both; }
    .anim-3 { animation: fadeSlide 0.5s 0.19s ease both; }
    .anim-4 { animation: fadeSlide 0.5s 0.26s ease both; }
    .anim-5 { animation: fadeSlide 0.5s 0.33s ease both; }
  </style>
</head>
<body class="bg-mesh grid-overlay min-h-screen flex items-center justify-center p-4">
  <div class="fixed inset-0 pointer-events-none overflow-hidden" aria-hidden="true">
    <div class="absolute top-1/3 -right-20 w-72 h-72 rounded-full border border-cyan-500/5"></div>
    <div class="absolute bottom-20 -left-16 w-56 h-56 rounded-full border border-emerald-500/5"></div>
    <div class="absolute bottom-10 right-10 mono text-cyan-500/8 text-xs leading-6 select-none text-right">
      NODE NET<br/>AUTH v1
    </div>
  </div>

  <div class="w-full max-w-sm relative z-10">
    <div class="text-center mb-8 anim-1">
      <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl border border-emerald-500/25 bg-emerald-950/40 mb-4">
        <svg class="w-7 h-7 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>
        </svg>
      </div>
      <h1 class="text-2xl font-extrabold text-white tracking-wide">MiGrid</h1>
      <p class="text-emerald-400/60 mono text-xs tracking-widest mt-1">DECENTRALIZED ENERGY NETWORK</p>
    </div>

    <div class="card-glass rounded-2xl p-8">
      <h2 class="text-lg font-semibold text-white mb-1 anim-2">Welcome back</h2>
      <p class="text-sm text-slate-400 mb-6 anim-2">Sign in to access your energy dashboard.</p>

      <?php if ($error): ?>
      <div class="rounded-xl border border-red-500/30 bg-red-950/30 p-3.5 mb-5 flex gap-2.5 items-center anim-2">
        <svg class="w-4 h-4 text-red-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
        </svg>
        <p class="text-sm text-red-300"><?= htmlspecialchars($error) ?></p>
      </div>
      <?php endif; ?>

      <form method="POST" action="login.php">
        <div class="mb-4 anim-3">
          <label class="block text-xs font-medium text-slate-300 mb-1.5 tracking-wide">Email Address</label>
          <input type="email" name="email" id="email" required autocomplete="email"
                 value="<?= $email_val ?>" placeholder="you@example.com"
                 class="field-input w-full rounded-xl px-4 py-2.5 text-sm"/>
        </div>
        <div class="mb-6 anim-4">
          <label class="block text-xs font-medium text-slate-300 mb-1.5 tracking-wide">Password</label>
          <div class="relative">
            <input type="password" name="password" id="password" required autocomplete="current-password"
                   placeholder="••••••••" class="field-input w-full rounded-xl px-4 py-2.5 text-sm pr-10"/>
            <button type="button" class="pw-eye absolute right-3 top-1/2 -translate-y-1/2" onclick="togglePw()" aria-label="Toggle password visibility">
              <svg class="w-4 h-4" id="eye-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
              </svg>
            </button>
          </div>
        </div>
        <div class="anim-4">
          <button type="submit" class="btn-submit w-full text-white font-semibold py-3 rounded-xl text-sm tracking-wide">
            Sign In to Network
          </button>
        </div>
      </form>
      <p class="text-center text-sm text-slate-500 mt-6 anim-5">
        No account yet?
        <a href="register.php" class="text-emerald-400 hover:text-emerald-300 font-medium transition-colors">Create one</a>
      </p>
    </div>
    <p class="mono text-center text-xs text-slate-600 mt-5 anim-5">
      MiGrid Energy Platform &nbsp;·&nbsp; Decentralized Power Grid
    </p>
  </div>

  <script>
  function togglePw() {
    const input = document.getElementById('password');
    const svg   = document.getElementById('eye-icon');
    const isText = input.type === 'text';
    input.type = isText ? 'password' : 'text';
    svg.innerHTML = isText
      ? `<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>`
      : `<path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>`;
  }
  </script>
</body>
</html>