<?php
session_start();
include_once 'config.php';

include_once 'sidebar.php';

if (!isLoggedIn()) {
    redirect('login.php');
}
if (!isset($_SESSION['user_id'])) return;


$house_uid = htmlspecialchars($_SESSION['house_uid']  ?? 'User');
$uname     = htmlspecialchars($_SESSION['user_name']  ?? 'User');
$node_name = htmlspecialchars($_SESSION['node_name']  ?? 'Unassigned');
$node_loc  = htmlspecialchars($_SESSION['node_loc']   ?? '—');
$urole     = strtoupper($_SESSION['user_role']        ?? 'USER');

$energy_value = "0.00";
$energy_unit  = "kWh";
$timestamp    = date("F j, Y, g:i A");

// --- CHANGE THIS TO YOUR OWN GIF URL ---
$advert_gif = "img/renewable_energy_banner.png"; // example
// ---------------------------------------
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Energy Monitor | Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <style>
        /* === ROOT VARIABLES === */
        :root {
            --vs-bg:         #020c16;
            /* --vs-bg:         #1f5485; */
            --vs-accent:     #10b981;
            --vs-accent-dark: rgba(4,30,33,0.6);
            --vs-txt-mid:    #8ba8b5;
            --vs-border-col: rgba(16,185,129,0.3);
            --space-xs: 0.3rem;
            --space-sm: 0.5rem;
            --space-md: 0.4rem;
            --space-lg: 1.2rem;
            --space-xl: 0.7rem;
            --font-base: 0.75rem;
            --font-h1: 2.2rem;
            --font-h2: 1.25rem;
            --font-btn: 0.9rem;
            --icon-primary: 1.9rem;
            --icon-secondary: 1.8rem;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--vs-bg);
            font-size: var(--font-base);
            line-height: 1.4;
        }

        .main-content {
            margin-left: 260px;
            padding: var(--space-xl);
            transition: margin-left 0.3s ease;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: var(--space-lg);
            }
        }

        /* Session bar */
        .session-bar {
            background: var(--vs-accent-dark);
            border-radius: 1rem;
            padding: var(--space-md) var(--space-xl);
            margin-bottom: var(--space-xl);
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: var(--space-md);
            border: 1px solid var(--vs-border-col);
        }
        .user-name {
            font-weight: 700;
            font-size: 0.85rem;
            background: var(--vs-accent);
            padding: 0.15rem 0.7rem;
            border-radius: 40px;
            color: #0f172a;
        }
        .role-badge {
            background: var(--vs-accent);
            color: black;
            padding: 0.15rem 0.6rem;
            border-radius: 40px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .node-details {
            font-size: 0.75rem;
            gap: var(--space-md);
            color: #e8ecf1;
        }
        .node-details span {
            color: var(--vs-accent);
        }

        /* Energy card */
        .energy-card {
            background: linear-gradient(135deg, var(--vs-accent-dark), rgba(1, 11, 12, 0.6));
            border-radius: 1.2rem;
            padding: var(--space-xl);
            margin-bottom: var(--space-xl);
            position: relative;
            border: 1px solid var(--vs-border-col);
        }
        .card-top-right {
            position: absolute;
            top: var(--space-lg);
            right: var(--space-lg);
        }
        .energy-value {
            font-size: var(--font-h1);
            font-weight: 800;
            letter-spacing: -0.5px;
            color: var(--vs-accent);
        }
        .energy-unit {
            font-size: var(--font-h2);
            margin-left: 0.2rem;
            color: var(--vs-txt-mid);
        }
        .energy-label {
            font-size: 0.7rem;
            letter-spacing: 1.5px;
            margin-top: 0.2rem;
            color: var(--vs-txt-mid);
        }
        .timestamp {
            font-size: 0.65rem;
            background: rgba(255,255,255,0.1);
            padding: 0.2rem 0.7rem;
            border-radius: 30px;
            display: inline-block;
            margin-top: var(--space-md);
            color: var(--vs-accent);
        }

        /* Add unit button */
        .add-unit-btn {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border: none;
            color: white;
            font-weight: 600;
            font-size: var(--font-btn);
            padding: 0.4rem 1rem;
            border-radius: 60px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: transform 0.1s ease;
        }
        .add-unit-btn:active {
            transform: scale(0.97);
        }

        /* Notification card */
        .notification-card {
            background: linear-gradient(135deg, #2d1f0a 0%, #1f1506 100%);
            border-left: 4px solid #f59e0b;
            border-radius: 0.8rem;
            padding: 0.6rem 1rem;
            margin-bottom: var(--space-xl);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: var(--space-md);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }
        .notification-icon { font-size: 1rem; }
        .notification-text { font-size: 0.75rem; color: #fcd34d; }
        .notification-action {
            background: #f59e0b;
            color: #1a1a24;
            border: none;
            padding: 0.3rem 0.9rem;
            border-radius: 60px;
            font-weight: 700;
            font-size: 0.7rem;
            cursor: pointer;
        }

        /* GRID – now 6 columns to accommodate 6 buttons (original 3 + new 3) */
        /* But we keep responsive, but for desktop show 6 buttons in one row */
        .action-buttons-container {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 0.9rem;
            margin-bottom: var(--space-xl);
        }
        .secondary-buttons-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.9rem;
            margin-bottom: var(--space-xl);
        }

        /* Buttons base style */
        .action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.9rem 0.5rem;
            background: linear-gradient(135deg, var(--vs-accent-dark) 0%, rgba(255,255,255,0.075) 100%);
            color: #10b981;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.7rem;
            border-radius: 0.8rem;
            border: 1px solid var(--vs-border-col);
            cursor: pointer;
            text-align: center;
            transition: all 0.2s ease;
        }
        .action-btn:hover {
            background: rgba(16,185,129,0.2);
            transform: translateY(-2px);
            border-color: var(--vs-accent);
        }
        .secondary-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            padding: 0.7rem 0.4rem;
            background: linear-gradient(135deg, rgba(16,185,129,0.1) 0%, rgba(255,255,255,0.05) 100%);
            color: var(--vs-txt-mid);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.7rem;
            border-radius: 0.8rem;
            border: 1px solid rgba(16,185,129,0.2);
            cursor: pointer;
            text-align: center;
            transition: all 0.2s ease;
        }
        .secondary-btn:hover {
            border-color: var(--vs-accent);
            color: var(--vs-accent);
            transform: translateY(-1px);
        }

        /* Icons */
        .action-btn svg {
            width: var(--icon-primary);
            height: var(--icon-primary);
            stroke-width: 2.5;
        }
        .secondary-btn svg {
            width: var(--icon-secondary);
            height: var(--icon-secondary);
            stroke-width: 2;
        }
        svg {
            fill: none;
            stroke: currentColor;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .unit-list {
            margin-top: var(--space-md);
            font-size: 0.65rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.3rem;
        }

        /* Advert Card */
        .advert-card {
            background: linear-gradient(135deg, #0a1a1f 0%, #031016 100%);
            border-radius: 1rem;
            padding: var(--space-md);
            margin-bottom: var(--space-xl);
            border: 1px solid var(--vs-border-col);
            text-align: center;
            overflow: hidden;
        }
        .advert-card img {
            max-width: 100%;
            height: auto;
            border-radius: 0.6rem;
            display: block;
            margin: 0 auto;
        }
        .advert-label {
            font-size: 0.6rem;
            color: var(--vs-txt-mid);
            margin-top: var(--space-xs);
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        /* Modal Styles for add house / pair node forms */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(3px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            visibility: hidden;
            opacity: 0;
            transition: all 0.2s ease;
        }
        .modal-overlay.active {
            visibility: visible;
            opacity: 1;
        }
        .modal-container {
            background: #0f2125;
            border-radius: 1.5rem;
            width: 90%;
            max-width: 420px;
            padding: 1.5rem;
            border: 1px solid var(--vs-accent);
            box-shadow: 0 20px 35px rgba(0,0,0,0.4);
        }
        .modal-container h3 {
            color: #10b981;
            margin-bottom: 1rem;
            font-size: 1.3rem;
        }
        .modal-container input, .modal-container select {
            width: 100%;
            padding: 0.7rem;
            margin: 0.6rem 0;
            background: #1e2f33;
            border: 1px solid #2d4a4f;
            border-radius: 0.8rem;
            color: white;
            font-family: 'Inter', sans-serif;
        }
        .modal-container input:focus {
            outline: none;
            border-color: #10b981;
        }
        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 0.8rem;
            margin-top: 1.2rem;
        }
        .modal-buttons button {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            border: none;
            cursor: pointer;
            font-weight: 600;
        }
        .modal-submit {
            background: #10b981;
            color: #0f172a;
        }
        .modal-cancel {
            background: #3b4e54;
            color: #ddd;
        }
        .toast-message {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: #10b981;
            color: #0a1a1f;
            padding: 0.5rem 1.2rem;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.75rem;
            z-index: 1100;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            transition: opacity 0.2s;
            opacity: 0;
            pointer-events: none;
        }

        @media (max-width: 900px) {
            .action-buttons-container {
                grid-template-columns: repeat(3, 1fr);
                gap: 0.8rem;
            }
        }
        @media (max-width: 540px) {
            .action-buttons-container {
                grid-template-columns: repeat(3, 1fr);
            }
            .secondary-buttons-container {
                grid-template-columns: repeat(4, 1fr);
            }
        }
    </style>
</head>
<body>
<div class="main-content">
    <div class="session-bar">
        <div class="user-info">
            <div class="user-name">👤 <?php echo $uname; ?></div>
        </div>
        <div class="node-details">
            <div>📍 Node: <span><?php echo $node_name; ?></span></div>
            <div>📌 Location: <span><?php echo $node_loc; ?></span></div>
            <div>🏠 House UID: <span><?php echo $house_uid; ?></span></div>
        </div>
    </div>
        <!-- <?php
        echo "<pre>";
print_r($_SESSION);
echo "</pre>";
        ?> -->
    <div class="energy-card">
        <div class="card-top-right">
            <a href="add_units.php" class="add-unit-btn" id="openModalBtn">➕ Add Unit</a>
        </div>
        <div>
            <div class="energy-value">
                <span id="energyValue"><?php echo $energy_value; ?></span>
                <span class="energy-unit"><?php echo $energy_unit; ?></span>
            </div>
            <div class="energy-label">ENERGY REMAINING</div>
            <div class="timestamp" id="timestamp">
                🕒 Updated: <?php echo $timestamp; ?>
            </div>
        </div>
        <div class="unit-list" id="unitListDisplay"></div>
    </div>

    <div id="notificationCard" class="notification-card" style="display: none;">
        <div class="notification-content">
            <div class="notification-icon" id="notificationIcon">⚠️</div>
            <div class="notification-text" id="notificationText">Running low on units</div>
        </div>
        <button class="notification-action" id="notificationAddBtn">➕ Add Unit</button>
    </div>

    <!-- PRIMARY BUTTONS: original 3 + NEW 3 (Add House, Pair Node, Verify) = total 6 -->
    <div class="action-buttons-container">
        <a href="sell.php" class="action-btn" id="sellEnergyBtn">
            <svg viewBox="0 0 24 24"><path d="M12 2v20M17 7l-5-5-5 5M7 17l5 5 5-5M4 12h16"/></svg>
            <span>Sell Energy</span>
        </a>
        <a href="transfer.php" class="action-btn" id="transferUnitsBtn">
            <svg viewBox="0 0 24 24"><path d="M17 2l4 4-4 4M3 12h15.5M7 6h12M7 18h12M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h7"/></svg>
            <span>Transfer Units</span>
        </a>
        <a href="wallet.php" class="action-btn" id="fundWalletBtn">
            <svg viewBox="0 0 24 24"><rect x="2" y="6" width="20" height="12" rx="2" ry="2"/><path d="M22 10h-4a2 2 0 000 4h4"/><circle cx="18" cy="12" r="1"/><path d="M6 6h10"/></svg>
            <span>Wallet</span>
        </a>
        <!-- NEW BUTTONS: Add House -->
        <a href="create_house.php" class="action-btn" id="addHouseBtn">
            <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <span>Add House</span>
        </a>
        <!-- Pair Node -->
        <button class="action-btn" id="pairNodeBtn">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 4.6C18.3 3.5 16.8 3 15 3h-6C6.3 3 4 5.3 4 8v8c0 2.7 2.3 5 5 5h6c1.8 0 3.3-0.5 4.4-1.6"/><path d="M19 9c1.3 0 2.4 0.5 3.2 1.3L22 11"/><path d="M22 13c-0.8 0.8-1.9 1.3-3.2 1.3"/><line x1="2" y1="2" x2="22" y2="22"/></svg>
            <span>Pair Node</span>
        </button>
        <!-- Verify -->
        <button class="action-btn" id="verifyBtn">
            <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <span>Verify</span>
        </button>
    </div>

    <!-- Secondary buttons unchanged -->
    <div class="secondary-buttons-container">
        <a href="#" class="secondary-btn" id="convertBtn">
            <svg viewBox="0 0 24 24"><path d="M7 7l5-5 5 5M17 17l-5 5-5-5M12 2v20M4 12h16"/></svg>
            <span>Convert</span>
        </a>
        <a href="#" class="secondary-btn" id="airtimeBtn">
            <svg viewBox="0 0 24 24"><path d="M22 2L15 9M22 2l-7 7-4-4-9 9M16 3h5v5"/><circle cx="9" cy="15" r="2"/><circle cx="18" cy="8" r="2"/><path d="M4 20h16"/></svg>
            <span>Airtime</span>
        </a>
        <a href="#" class="secondary-btn" id="dataBtn">
            <svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/><path d="M7 9h2M15 9h2"/><circle cx="12" cy="12" r="1"/></svg>
            <span>Data</span>
        </a>
        <a href="#" class="secondary-btn" id="electricityBtn">
            <svg viewBox="0 0 24 24"><path d="M12 2v6M12 18v4M4.93 4.93l4.24 4.24M14.83 14.83l4.24 4.24M2 12h6M18 12h4M4.93 19.07l4.24-4.24M14.83 9.17l4.24-4.24"/><circle cx="12" cy="12" r="3"/></svg>
            <span>Electricity</span>
        </a>
    </div>

    <div class="advert-card">
        <img src="<?php echo $advert_gif; ?>" alt="Advertisement" loading="lazy">
        <div class="advert-label">Sponsored</div>
    </div>
</div>

<!-- Toast message container -->
<div id="toastMsg" class="toast-message">✅ Action completed</div>

<!-- MODAL (reusable for Add House, Pair Node, and Verify) -->
<div id="genericModal" class="modal-overlay">
    <div class="modal-container">
        <h3 id="modalTitle">Add House</h3>
        <input type="text" id="modalInput1" placeholder="House name / UID" autocomplete="off">
        <input type="text" id="modalInput2" placeholder="Additional info (optional)" autocomplete="off" style="display: none;">
        <div id="modalSelectContainer" style="display: none;">
            <select id="modalSelect"></select>
        </div>
        <div class="modal-buttons">
            <button class="modal-cancel" id="modalCancelBtn">Cancel</button>
            <button class="modal-submit" id="modalSubmitBtn">Confirm</button>
        </div>
    </div>
</div>

<script>

    async function getWeather() {
    const API_KEY = "43aead8c3cf9e9c781966398e1a41af0";
    const city = "Abuja"; // You can change this to your city or make it dynamic

    const url = `https://api.openweathermap.org/data/2.5/weather?q=${city}&appid=${API_KEY}&units=metric`;

    try {
        const res = await fetch(url);
        const data = await res.json();

        console.log("Weather:", data);

        // Example usage
        const temp = data.main.temp;
        const clouds = data.clouds.all;

        document.getElementById("weatherTemp").innerText = temp + " °C";
        document.getElementById("weatherClouds").innerText = clouds + " %";

    } catch (err) {
        console.error("Weather error:", err);
    }
}

// getWeather();

    // ---------------------- UTILITIES -------------------------
    const notificationCard = document.getElementById('notificationCard');
    const notificationIcon = document.getElementById('notificationIcon');
    const notificationText = document.getElementById('notificationText');
    function showNotification(icon, msg) {
        notificationIcon.textContent = icon || '⚠️';
        notificationText.textContent = msg || 'Notification';
        notificationCard.style.display = 'flex';
    }
    function hideNotification() { notificationCard.style.display = 'none'; }

    const unitList = document.getElementById('unitListDisplay');
    function addUnitToList(name, val) {
        const chip = document.createElement('div');
        chip.style.cssText = `display:inline-flex; align-items:center; background:rgba(16,185,129,0.15); padding:0.15rem 0.5rem; border-radius:40px; gap:0.2rem; font-size:0.65rem; color:#10b981; border:1px solid rgba(16,185,129,0.3);`;
        const text = document.createElement('span');
        text.textContent = `${escapeHtml(name)}: ${escapeHtml(val)}`;
        const remove = document.createElement('span');
        remove.textContent = '✕';
        remove.style.cssText = 'cursor:pointer; font-size:0.6rem; margin-left:0.15rem; opacity:0.7; color:#f87171;';
        remove.onclick = () => { chip.remove(); if (unitList.children.length === 0) showNotification('⚠️', 'Running low on units'); };
        chip.appendChild(text); chip.appendChild(remove);
        unitList.appendChild(chip);
        hideNotification();
    }
    function escapeHtml(str) { return str.replace(/[&<>]/g, m => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;' }[m])); }

    // Toast helper
    function showToast(message, isError = false) {
        const toast = document.getElementById('toastMsg');
        toast.textContent = message;
        toast.style.backgroundColor = isError ? '#dc2626' : '#10b981';
        toast.style.opacity = '1';
        setTimeout(() => {
            toast.style.opacity = '0';
        }, 2500);
    }

    // ---------- MODAL LOGIC (reusable) ----------
    const modalOverlay = document.getElementById('genericModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalInput1 = document.getElementById('modalInput1');
    const modalInput2 = document.getElementById('modalInput2');
    const modalSelectContainer = document.getElementById('modalSelectContainer');
    const modalSelect = document.getElementById('modalSelect');
    let currentModalCallback = null;

    function closeModal() {
        modalOverlay.classList.remove('active');
        modalInput1.value = '';
        modalInput2.value = '';
        modalInput2.style.display = 'none';
        modalSelectContainer.style.display = 'none';
        modalSelect.innerHTML = '';
        currentModalCallback = null;
    }

    function openModal(title, fields, callback) {
        modalTitle.textContent = title;
        // reset fields visibility
        modalInput1.placeholder = fields.input1Placeholder || "Enter value";
        modalInput1.style.display = fields.showInput1 ? 'block' : 'none';
        if (fields.showInput2) {
            modalInput2.style.display = 'block';
            modalInput2.placeholder = fields.input2Placeholder || "Additional info";
        } else {
            modalInput2.style.display = 'none';
        }
        if (fields.selectOptions && fields.selectOptions.length) {
            modalSelectContainer.style.display = 'block';
            modalSelect.innerHTML = '<option value="">-- select --</option>';
            fields.selectOptions.forEach(opt => {
                const option = document.createElement('option');
                option.value = opt.value;
                option.textContent = opt.label;
                modalSelect.appendChild(option);
            });
        } else {
            modalSelectContainer.style.display = 'none';
        }
        currentModalCallback = callback;
        modalOverlay.classList.add('active');
    }

    document.getElementById('modalSubmitBtn').onclick = () => {
        if (currentModalCallback) {
            const input1Val = modalInput1.style.display !== 'none' ? modalInput1.value : '';
            const input2Val = modalInput2.style.display !== 'none' ? modalInput2.value : '';
            const selectVal = modalSelectContainer.style.display !== 'none' ? modalSelect.value : '';
            currentModalCallback({ input1: input1Val, input2: input2Val, select: selectVal });
        }
        closeModal();
    };
    document.getElementById('modalCancelBtn').onclick = closeModal;
    modalOverlay.addEventListener('click', (e) => { if(e.target === modalOverlay) closeModal(); });

    // ========== NEW BUTTON HANDLERS ==========
    // 1. ADD HOUSE
    document.getElementById('addHouseBtn')?.addEventListener('click', () => {
        openModal('Add New House', {
            showInput1: true,
            input1Placeholder: 'House name / UID (e.g., H001)',
            showInput2: true,
            input2Placeholder: 'Address or location (optional)'
        }, (data) => {
            if (!data.input1.trim()) {
                showToast('House name/UID required', true);
                return;
            }
            // Simulate API call - you can replace with fetch to backend
            console.log('[Add House]', data);
            showToast(`🏠 House "${data.input1}" added successfully!`);
            // Here you could do AJAX POST to server endpoint like /api/add_house
            // For demo, just append to session-bar or something
            const sessionDiv = document.querySelector('.session-bar .node-details');
            if (sessionDiv && !sessionDiv.innerHTML.includes('New House')) {
                const houseSpan = document.createElement('div');
                houseSpan.innerHTML = `🏡 New: <span>${escapeHtml(data.input1)}</span>`;
                houseSpan.style.fontSize = '0.7rem';
                sessionDiv.appendChild(houseSpan);
            }
        });
    });

    // 2. PAIR NODE
    document.getElementById('pairNodeBtn')?.addEventListener('click', () => {
        openModal('Pair IoT Node', {
            showInput1: true,
            input1Placeholder: 'Node Serial / MAC Address',
            showInput2: true,
            input2Placeholder: 'Node location / description'
        }, (data) => {
            if (!data.input1.trim()) {
                showToast('Node identifier required', true);
                return;
            }
            console.log('[Pair Node]', data);
            showToast(`🔗 Node "${data.input1}" paired successfully!`);
            // Simulate pairing: update node details on UI
            const nodeSpan = document.querySelector('.node-details span:first-child');
            if (nodeSpan) {
                nodeSpan.innerHTML = escapeHtml(data.input1);
            }
            const locSpan = document.querySelectorAll('.node-details span')[1];
            if (locSpan && data.input2.trim()) {
                locSpan.innerHTML = escapeHtml(data.input2);
            } else if (locSpan && !data.input2.trim()) {
                locSpan.innerHTML = '📍 Paired';
            }
        });
    });

    // 3. VERIFY
    document.getElementById('verifyBtn')?.addEventListener('click', () => {
        openModal('Verification', {
            showInput1: true,
            input1Placeholder: 'Enter OTP / Verification Code',
            showInput2: false,
            selectOptions: [
                { value: 'house', label: 'Verify House Ownership' },
                { value: 'node', label: 'Verify Node Authenticity' },
                { value: 'user', label: 'Verify User Identity' }
            ]
        }, (data) => {
            const selectedType = data.select;
            const code = data.input1.trim();
            if (!code) {
                showToast('Verification code required', true);
                return;
            }
            console.log(`[Verify] type=${selectedType}, code=${code}`);
            // Simulate backend verification
            setTimeout(() => {
                if (code === '123456' || code.length > 3) {
                    showToast(`✅ Verification successful (${selectedType})`);
                } else {
                    showToast(`❌ Verification failed: Invalid code`, true);
                }
            }, 300);
        });
    });

    // Existing secondary buttons keep same behavior
    document.getElementById('sellEnergyBtn')?.addEventListener('click', e => { if(e.target.closest('a')) return; console.log('Sell Energy'); showToast('Redirect to sell page'); });
    document.getElementById('transferUnitsBtn')?.addEventListener('click', e => { console.log('Transfer Units'); showToast('Redirect to transfer'); });
    document.getElementById('fundWalletBtn')?.addEventListener('click', e => { console.log('Fund Wallet'); showToast('Opening wallet...'); });
    document.getElementById('convertBtn')?.addEventListener('click', e => showNotification('🔄', 'Converting energy units...'));
    document.getElementById('airtimeBtn')?.addEventListener('click', e => showNotification('📱', 'Purchase airtime'));
    document.getElementById('dataBtn')?.addEventListener('click', e => showNotification('📶', 'Buy data bundle'));
    document.getElementById('electricityBtn')?.addEventListener('click', e => showNotification('⚡', 'Pay electricity bill'));
</script>
<!-- <script src="js/ws.js"></script> -->
<script>
    // ================= CONFIG =================
    // const WS_URL = new WebSocket("ws://" + window.location.hostname + ":3000")
    const WS_URL = "ws://" + window.location.hostname + ":3000";
    // const ws = new WebSocket("ws://" + window.location.hostname + ":3000");
    let ws;
    let userId = <?php echo $_SESSION['user_id']; ?>; // from PHP session

    // ================= CONNECT =================
    function connectWS() {

        ws = new WebSocket(WS_URL);

        ws.onopen = () => {
            console.log("🟢 WS Connected");

            ws.send(JSON.stringify({
                type: "AUTH",
                user_id: userId
            }));
        };

        ws.onmessage = (event) => {
            const data = JSON.parse(event.data);

            console.log("📩 WS:", data);



                    const total = parseFloat(data.total_energy) || 0;
                    if (data.total_energy < 2) {
                        showNotification("⚠️", "Running low on units");
                    } else {
                        hideNotification();
                    }
                    document.getElementById("energyValue").innerText = total.toFixed(2);
                    document.querySelector(".timestamp").innerText = "🕒 Updated: " + new Date().toLocaleString(); 


            // ================= AUTH =================
            if (data.type === "AUTH_SUCCESS") {
                console.log("✅ Authenticated");
                return;
            }
            if (data.type === "AUTH_FAILURE") {
                console.error("❌ Authentication failed");
                ws.close();
                return;
            }
            // ================= ACTION RESPONSES =================
            switch (data.reason) {

                case "SELL_STARTED":
                    console.log("🟢 Selling started");
                    break;

                case "SELL_STOPPED":
                    console.log("🔴 Selling stopped");
                    showNotification("🔴", "Selling stopped");
                    break;

                case "BUY_STARTED":
                    console.log("🔵 Buying started");
                    break;

                case "BUY_STOPPED":
                    console.log("⚪ Buying stopped");
                    showNotification("⚪", "Buying stopped");
                    break;

                case "BUY_LIMIT_REACHED":
                    console.log("🛑 BUY LIMIT REACHED");
                    showNotification("🛑", data.message);
                    showAlert(data.message, "error");
                    break;
            }
        };

        ws.onclose = () => {
            console.log("🔴 Disconnected... reconnecting");
            setTimeout(connectWS, 3000);
        };

        ws.onerror = (err) => {
            console.error("❌ WS Error:", err);
        };
    }

    // ================= ACTIONS =================
    // 🔥 IMPORTANT: you must pass house_uid from backend (PHP)

    function startSell(house_uid, amount) {
        ws.send(JSON.stringify({
            type: "START_SELL",
            house_uid,
            amount_kwh: amount
        }));
    }

    function stopSell(house_uid) {
        ws.send(JSON.stringify({
            type: "STOP_SELL",
            house_uid
        }));
    }

    function startBuy(house_uid) {
        ws.send(JSON.stringify({
            type: "START_BUY",
            house_uid
        }));
    }

    function stopBuy(house_uid) {
        ws.send(JSON.stringify({
            type: "STOP_BUY",
            house_uid
        }));
    }

    function showAlert(message, type = "success") {
        const container = document.getElementById("alertContainer");
        const alertDiv = document.createElement("div");
        alertDiv.className = `alert alert-${type}`;
        alertDiv.innerText = message;
        container.appendChild(alertDiv);
        setTimeout(() => {
            alertDiv.style.opacity = "0";
            alertDiv.style.transform = "translateX(100%)";
            setTimeout(() => alertDiv.remove(), 300);
        }, 4000);
    }

    // ================= START =================
    connectWS();
</script>
</body>
</html>