<!-- <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@500;600;700;800&family=Geist+Mono:wght@400;500&display=swap" rel="stylesheet"/>
</head>
<body> -->
<?php

/**
 * sidebar.php — MiGrid Navigation
 *
 * DESKTOP  ≥1024px : Fixed left sidebar, 280px wide.
 * MOBILE   <1024px : Fixed bottom tab bar, OPay-style bold icons + labels.
 *
 * USAGE:
 *   <?php require_once 'sidebar.php'; ?>
 *   <div class="vm-wrap"> ... your content ... </div>
 *
 * Active link auto-detected from $_SERVER['PHP_SELF'].
 */
// ── Start session if not already started ─────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) return;

$current = basename($_SERVER['PHP_SELF']);

/**
 * IMPORTANT: SVG icons below deliberately have NO stroke-width attribute.
 * stroke-width is controlled entirely by CSS so active/hover overrides work.
 * Fill, stroke-linecap and stroke-linejoin are set via CSS too.
 */
$nav = [
    [
        'href'  => 'dashboard.php',
        'label' => 'Home',
        'desc'  => 'Overview',
        'match' => ['dashboard.php', 'index.php'],
        'icon'  => '<svg viewBox="0 0 24 24"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 01-1 1H4a1 1 0 01-1-1V9.5z"/><path d="M9 21V12h6v9"/></svg>',
    ],
    [
        'href'  => 'energy.php',
        'label' => 'Energy',
        'desc'  => 'Live monitoring',
        'match' => ['energy.php'],
        'icon'  => '<svg viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>',
    ],
    [
        'href'  => 'control.php',
        'label' => 'Control',
        'desc'  => 'Device / node',
        'match' => ['control.php', 'devices.php'],
        'icon'  => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 010 14.14M4.93 4.93a10 10 0 000 14.14M15.54 8.46a5 5 0 010 7.07M8.46 8.46a5 5 0 000 7.07"/></svg>',
    ],
    [
        'href'  => 'analytics.php',
        'label' => 'Analytics',
        'desc'  => 'History & insights',
        'match' => ['analytics.php', 'billing.php', 'logs.php', 'reports.php'],
        'icon'  => '<svg viewBox="0 0 24 24"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>',
    ],
    [
        'href'  => 'profile.php',
        'label' => 'Profile',
        'desc'  => 'User / settings',
        'match' => ['profile.php', 'profile.php', 'settings.php'],
        'icon'  => '<svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>',
    ],
];

function vm_on(array $item, string $page): bool {
    return in_array($page, $item['match'], true);
}

$initials  = strtoupper(implode('', array_map(
    fn($w) => $w[0] ?? '',
    array_slice(explode(' ', trim($_SESSION['user_name'] ?? 'U')), 0, 2)
)));
$uname     = htmlspecialchars($_SESSION['user_name']  ?? 'User');
$node_name = htmlspecialchars($_SESSION['node_name']  ?? 'Unassigned');
$node_loc  = htmlspecialchars($_SESSION['node_loc']   ?? '—');
$urole     = strtoupper($_SESSION['user_role']        ?? 'USER');
?>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@500;600;700;800&family=Geist+Mono:wght@400;500&display=swap" rel="stylesheet"/>
<style>
/* ═══════════════════════════════════════════
   TOKENS
   ═══════════════════════════════════════════ */
:root {
  --vs-w:          260px;
  --vs-mob-h:       60px;
  --vs-bg:         #020c16;
  --vs-surface:    rgba(255,255,255,0.035);
  --vs-border:     rgba(16,185,129,0.10);
  --vs-border2:    rgba(255,255,255,0.06);
  --vs-accent:     #10b981;
  --vs-accent-dim: rgba(16,185,129,0.38);
  --vs-txt-hi:     #e8fdf3;
  --vs-txt-lo:     #4d6475;
  --vs-txt-mid:    #8ba8b5;
  --vs-danger:     #f87171;
  --vs-item-r:     14px;
  --vs-item-h:     60px;
}

.vs-sidebar *, .vs-bottom *, .vm-wrap { box-sizing: border-box; }
.vs-sidebar a, .vs-bottom a           { text-decoration: none;  }

/* All SVG icons — properties set here, NOT in the HTML attributes */
.vs-icon svg,
.vs-tab-pill svg {
  fill:            none;
  stroke:          currentColor;
  stroke-linecap:  round;
  stroke-linejoin: round;
  flex-shrink:     0;
}

/* ═══════════════════════════════════════════
   DESKTOP SIDEBAR
   ═══════════════════════════════════════════ */
.vs-sidebar {
  position: fixed;
  top: 0; left: 0; bottom: 0;
  width: var(--vs-w);
  background: var(--vs-bg);
  border-right: 1px solid var(--vs-border);
  box-shadow: inset -1px 0 0 rgba(16,185,129,0.04), 4px 0 48px rgba(0,0,0,0.5);
  display: flex;
  flex-direction: column;
  z-index: 100;
}

/* Brand */
.vs-brand {
  display: flex; align-items: center; gap: 14px;
  padding: 24px 20px 20px;
  border-bottom: 1px solid var(--vs-border);
  flex-shrink: 0;
}
.vs-logo {
  width: 44px; height: 44px;
  background: rgba(16,185,129,0.08);
  border: 1px solid rgba(16,185,129,0.22);
  border-radius: 13px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.vs-logo svg      { width: 24px; height: 24px; stroke: var(--vs-accent); stroke-width: 2; }
.vs-brand-name    { font-family:'Syne',sans-serif; font-size:1.1rem; font-weight:800; letter-spacing:0.06em; color:#fff; line-height:1; }
.vs-brand-tag     { font-family:'Geist Mono',monospace; font-size:8px; letter-spacing:0.30em; color:var(--vs-accent-dim); margin-top:4px; }

/* Node card */
.vs-node {
  margin: 16px 14px 4px;
  background: rgba(16,185,129,0.04);
  border: 1px solid rgba(16,185,129,0.12);
  border-radius: 13px;
  padding: 12px 15px 13px;
  flex-shrink: 0;
}
.vs-node-eye  { font-family:'Geist Mono',monospace; font-size:8px; letter-spacing:0.32em; color:var(--vs-accent-dim); margin-bottom:5px; }
.vs-node-name { font-family:'Syne',sans-serif; font-weight:700; font-size:0.85rem; color:var(--vs-accent); line-height:1.2; }
.vs-node-loc  { font-family:'Geist Mono',monospace; font-size:9.5px; color:var(--vs-txt-mid); margin-top:3px; }
.vs-node-live { display:inline-flex; align-items:center; gap:6px; margin-top:8px; }
.vs-pulse {
  width: 7px; height: 7px; border-radius: 50%;
  background: var(--vs-accent); flex-shrink: 0;
  animation: vs-ping 2.4s ease-in-out infinite;
}
@keyframes vs-ping {
  0%,100% { box-shadow: 0 0 0 0   rgba(16,185,129,0.6); }
  50%      { box-shadow: 0 0 0 5px rgba(16,185,129,0);   }
}
.vs-live-txt { font-family:'Geist Mono',monospace; font-size:9px; letter-spacing:0.2em; color:var(--vs-accent); }

/* Nav list */
.vs-nav {
  flex: 1;
  padding: 14px 12px;
  display: flex; flex-direction: column; gap: 4px;
  overflow-y: auto; scrollbar-width: none;
}
.vs-nav::-webkit-scrollbar { width: 0; }

.vs-item {
  display: flex; align-items: center; gap: 14px;
  height: var(--vs-item-h);
  padding: 0 14px;
  border-radius: var(--vs-item-r);
  border: 1px solid transparent;
  color: var(--vs-txt-lo);
  font-family: 'Syne', sans-serif;
  cursor: pointer; position: relative;
  transition: background 0.16s, color 0.16s, border-color 0.16s;
  overflow: hidden;
}
.vs-item::after {
  content: ''; position: absolute; inset: 0;
  background: linear-gradient(120deg, transparent 30%, rgba(16,185,129,0.04) 50%, transparent 70%);
  opacity: 0; transition: opacity 0.3s;
}
.vs-item:hover::after { opacity: 1; }
.vs-item:hover        { background: rgba(16,185,129,0.05); color: var(--vs-txt-hi); }
.vs-item.on           { background: rgba(16,185,129,0.10); border-color: rgba(16,185,129,0.20); color: var(--vs-accent); }
.vs-item.on::before {
  content: ''; position: absolute;
  left: 0; top: 20%; bottom: 20%;
  width: 3px; border-radius: 0 4px 4px 0;
  background: var(--vs-accent);
  box-shadow: 0 0 10px rgba(16,185,129,0.6);
}

/* Icon box — desktop */
.vs-icon {
  width: 42px; height: 42px; border-radius: 11px;
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.07);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  transition: background 0.16s, border-color 0.16s;
}
.vs-icon svg             { width: 24px; height: 24px; stroke-width: 1.8; }
.vs-item.on .vs-icon     { background: rgba(16,185,129,0.12); border-color: rgba(16,185,129,0.25); }
.vs-item.on .vs-icon svg { stroke-width: 2.3; }

.vs-txt   { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.vs-label { font-weight: 700; font-size: 0.93rem; letter-spacing: 0.015em; line-height: 1; white-space: nowrap; }
.vs-desc  { font-family:'Geist Mono',monospace; font-size:9.5px; letter-spacing:0.06em; color:var(--vs-txt-mid); line-height:1; white-space:nowrap; transition:color 0.16s; }
.vs-item.on .vs-desc { color: var(--vs-accent); opacity: 0.65; }

/* User footer */
.vs-foot { flex-shrink: 0; padding: 12px 14px 18px; border-top: 1px solid var(--vs-border); }
.vs-user {
  display: flex; align-items: center; gap: 11px;
  padding: 10px 12px; border-radius: 13px;
  background: var(--vs-surface);
  border: 1px solid var(--vs-border2);
  margin-bottom: 10px;
}
.vs-avatar {
  width: 36px; height: 36px; border-radius: 10px;
  background: rgba(16,185,129,0.12); border: 1px solid rgba(16,185,129,0.22);
  display: flex; align-items: center; justify-content: center;
  font-family: 'Syne', sans-serif; font-weight: 800; font-size: 12px;
  color: var(--vs-accent); letter-spacing: 0.05em; flex-shrink: 0;
}
.vs-uname { font-family:'Syne',sans-serif; font-size:0.82rem; font-weight:700; color:var(--vs-txt-hi); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:150px; }
.vs-urole { font-family:'Geist Mono',monospace; font-size:8.5px; letter-spacing:0.16em; color:var(--vs-accent-dim); margin-top:2px; }
.vs-logout {
  display: flex; align-items: center; gap: 8px;
  width: 100%; padding: 8px 12px; border-radius: 10px;
  background: transparent; border: 1px solid transparent;
  color: var(--vs-txt-lo);
  font-family: 'Geist Mono', monospace; font-size: 10px; letter-spacing: 0.12em;
  cursor: pointer; transition: all 0.15s;
}
.vs-logout svg         { width: 15px; height: 15px; stroke-width: 2; }
.vs-logout:hover       { color: var(--vs-danger); background: rgba(248,113,113,0.07); border-color: rgba(248,113,113,0.16); }

/* Page wrapper */
.vm-wrap { margin-left: var(--vs-w); min-height: 100vh; }

/* ═══════════════════════════════════════════
   MOBILE BOTTOM NAV
   ═══════════════════════════════════════════ */
.vs-bottom { display: none; }

@media (max-width: 768px) {

  .vs-sidebar { display: none; }

  .vm-wrap {
    margin-left: 0;
    padding-bottom: calc(var(--vs-mob-h) + env(safe-area-inset-bottom, 0px));
  }

  /* Bar */
  .vs-bottom {
    display: flex;
    position: fixed;
    bottom: 0; left: 0; right: 0;
    height: calc(var(--vs-mob-h) + env(safe-area-inset-bottom, 0px));
    padding-bottom: env(safe-area-inset-bottom, 0px);
    background: #020c16;
    border-top: 1.5px solid rgba(16,185,129,0.14);
    backdrop-filter: blur(30px);
    -webkit-backdrop-filter: blur(30px);
    z-index: 100;
    align-items: stretch;
    box-shadow: 0 -1px 0 rgba(16,185,129,0.06), 0 -12px 40px rgba(0,0,0,0.6);
  }

  /* Tab */
  .vs-tab {
    flex: 1;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    gap: 4px; padding: 8px 2px 6px;
    position: relative; color: #3e5a6e;
    cursor: pointer;
    -webkit-tap-highlight-color: transparent; user-select: none;
    transition: color 0.15s;
  }

  /* Pill container — 52px wide so 5 tabs fit on 320px screens */
  .vs-tab-pill {
    width: 52px; height: 32px;
    border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    transition: background 0.18s, transform 0.14s, box-shadow 0.18s;
  }

  /* Icon — 26px. stroke-width controlled here (no inline attr in SVG) */
  .vs-tab-pill svg {
    width: 26px; height: 26px;
    stroke-width: 1.8;  /* CSS wins because SVG has no inline stroke-width */
    transition: stroke-width 0.15s, filter 0.15s;
  }

  /* Label — Syne 700, 10.5px */
  .vs-tab-lbl {
    font-family: 'Syne', sans-serif;
    font-size: 10.5px; font-weight: 700;
    letter-spacing: 0.01em; line-height: 1;
    transition: color 0.15s;
  }

  /* Hover */
  .vs-tab:hover              { color: #6faaa0; }
  .vs-tab:hover .vs-tab-pill { background: rgba(16,185,129,0.07); }

  /* Active */
  .vs-tab.on { color: var(--vs-accent); }

  .vs-tab.on .vs-tab-pill {
    background: rgba(16,185,129,0.16);
    box-shadow: 0 0 14px rgba(16,185,129,0.18);
    transform: scale(1.05);
  }
  .vs-tab.on .vs-tab-pill svg {
    stroke-width: 2.4;  /* thicker on active — works because no inline attr */
    filter: drop-shadow(0 0 5px rgba(16,185,129,0.6));
  }
  .vs-tab.on .vs-tab-lbl {
    font-weight: 800;
    color: var(--vs-accent);
  }

  /* Top accent bar */
  .vs-tab.on::before {
    content: '';
    position: absolute; top: 0; left: 50%; transform: translateX(-50%);
    width: 28px; height: 3px; border-radius: 0 0 4px 4px;
    background: var(--vs-accent);
    box-shadow: 0 0 10px rgba(16,185,129,0.7);
  }

  /* Press feedback */
  .vs-tab:active .vs-tab-pill { transform: scale(0.92); }
}
</style>

<!-- ═══════════════════════════ DESKTOP SIDEBAR ════════════════════════════ -->
<aside class="vs-sidebar" role="navigation" aria-label="Main navigation">

  <div class="vs-brand">
    <div class="vs-logo">
      <svg viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
    </div>
    <div>
      <div class="vs-brand-name">MiGrid</div>
      <div class="vs-brand-tag">ENERGY NETWORK</div>
    </div>
  </div>

  <div class="vs-node">
    <div class="vs-node-eye">ASSIGNED NODE</div>
    <div class="vs-node-name"><?= $node_name ?></div>
    <div class="vs-node-loc"><?= $node_loc ?></div>
    <div class="vs-node-live">
      <span class="vs-pulse"></span>
      <span class="vs-live-txt">CONNECTED</span>
    </div>
  </div>

  <nav class="vs-nav">
    <?php foreach ($nav as $n):
      $active = vm_on($n, $current);
    ?>
    <a href="<?= $n['href'] ?>"
       class="vs-item<?= $active ? ' on' : '' ?>"
       <?= $active ? 'aria-current="page"' : '' ?>>
      <div class="vs-icon"><?= $n['icon'] ?></div>
      <div class="vs-txt">
        <span class="vs-label"><?= $n['label'] ?></span>
        <span class="vs-desc"><?= $n['desc'] ?></span>
      </div>
    </a>
    <?php endforeach; ?>
  </nav>

  <div class="vs-foot">
    <div class="vs-user">
      <div class="vs-avatar"><?= $initials ?></div>
      <div style="min-width:0">
        <div class="vs-uname"><?= $uname ?></div>
        <div class="vs-urole"><?= $urole ?></div>
      </div>
    </div>
    <a href="logout.php" class="vs-logout">
      <svg viewBox="0 0 24 24">
        <path d="M17 16l4-4m0 0l-4-4m4 4H7"/>
        <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
      </svg>
      SIGN OUT
    </a>
  </div>

</aside>

<!-- ════════════════════════════ MOBILE BOTTOM NAV ═══════════════════════════ -->
<nav class="vs-bottom" role="navigation" aria-label="Mobile navigation">
  <?php foreach ($nav as $n):
    $active = vm_on($n, $current);
  ?>
  <a href="<?= $n['href'] ?>"
     class="vs-tab<?= $active ? ' on' : '' ?>"
     aria-label="<?= $n['label'] ?> — <?= $n['desc'] ?>"
     <?= $active ? 'aria-current="page"' : '' ?>>
    <div class="vs-tab-pill"><?= $n['icon'] ?></div>
    <span class="vs-tab-lbl"><?= $n['label'] ?></span>
  </a>
  <?php endforeach; ?>
</nav>

<!-- </body>
</html> -->

<!-- </body>
</html> -->