<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Dentfluence — Staff Check-In</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Cormorant+Garamond:wght@600;700&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Inter', sans-serif;
    background: linear-gradient(145deg, #1a0320 0%, #3a0050 50%, #1a0320 100%);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 24px 16px;
    color: #fff;
}
.brand {
    font-family: 'Cormorant Garamond', serif;
    font-size: 26px;
    font-weight: 700;
    color: #fff;
    letter-spacing: .02em;
    margin-bottom: 4px;
    text-align: center;
}
.brand span { color: #c77dff; }
.tagline { font-size: 12px; color: #c4a8d6; margin-bottom: 28px; text-align: center; }

.card {
    background: rgba(255,255,255,.05);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,.1);
    border-radius: 20px;
    padding: 24px;
    width: 100%;
    max-width: 420px;
    margin-bottom: 16px;
}
.card-title { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .08em; color: #c4a8d6; margin-bottom: 16px; }

/* QR Input area */
.qr-input-wrap { position: relative; }
.qr-input {
    width: 100%;
    background: rgba(255,255,255,.08);
    border: 1.5px solid rgba(255,255,255,.15);
    border-radius: 12px;
    padding: 14px 48px 14px 16px;
    color: #fff;
    font-size: 14px;
    font-family: 'Inter', sans-serif;
    outline: none;
    transition: border-color .15s;
}
.qr-input::placeholder { color: rgba(255,255,255,.35); }
.qr-input:focus { border-color: #c77dff; }
.qr-icon {
    position: absolute;
    right: 14px; top: 50%; transform: translateY(-50%);
    color: rgba(255,255,255,.4);
    pointer-events: none;
}

/* Staff grid */
.staff-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.staff-card {
    background: rgba(255,255,255,.06);
    border: 1.5px solid rgba(255,255,255,.1);
    border-radius: 12px;
    padding: 12px;
    cursor: pointer;
    transition: background .15s, border-color .15s, transform .1s;
    text-align: center;
    user-select: none;
}
.staff-card:hover { background: rgba(199,125,255,.12); border-color: rgba(199,125,255,.4); transform: scale(1.02); }
.staff-card.selected { background: rgba(199,125,255,.2); border-color: #c77dff; }
.staff-avatar {
    width: 42px; height: 42px; border-radius: 50%;
    background: linear-gradient(135deg, #6a0f70, #c77dff);
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; font-weight: 700; color: #fff;
    margin: 0 auto 8px;
}
.staff-name { font-size: 12.5px; font-weight: 600; color: #fff; margin-bottom: 2px; line-height: 1.2; }
.staff-role { font-size: 11px; color: rgba(255,255,255,.5); }

/* Entry/Exit buttons */
.action-btns { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 4px; }
.btn {
    padding: 14px;
    border: none;
    border-radius: 14px;
    font-size: 15px;
    font-weight: 700;
    font-family: 'Inter', sans-serif;
    cursor: pointer;
    transition: opacity .15s, transform .1s;
    letter-spacing: .02em;
}
.btn:active { transform: scale(.97); }
.btn-entry { background: linear-gradient(135deg, #059669, #10b981); color: #fff; }
.btn-exit  { background: linear-gradient(135deg, #b91c1c, #ef4444); color: #fff; }
.btn:disabled { opacity: .5; cursor: not-allowed; }

/* Feedback toast */
#toast {
    position: fixed;
    bottom: 28px; left: 50%; transform: translateX(-50%) translateY(80px);
    background: #1a1a2e;
    color: #fff;
    padding: 14px 24px;
    border-radius: 14px;
    font-size: 14px;
    font-weight: 500;
    text-align: center;
    max-width: 340px;
    width: calc(100% - 32px);
    transition: transform .35s cubic-bezier(.34,1.56,.64,1);
    z-index: 999;
    border: 1px solid rgba(255,255,255,.1);
    box-shadow: 0 8px 32px rgba(0,0,0,.4);
}
#toast.show { transform: translateX(-50%) translateY(0); }
#toast.success { border-color: #059669; background: #052e16; }
#toast.error   { border-color: #b91c1c; background: #2d0909; }

/* Selected staff display */
#selectedInfo {
    display: none;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    background: rgba(199,125,255,.1);
    border: 1.5px solid rgba(199,125,255,.3);
    border-radius: 12px;
    margin-bottom: 16px;
}
#selectedInfo .big-avatar {
    width: 48px; height: 48px; border-radius: 50%;
    background: linear-gradient(135deg, #6a0f70, #c77dff);
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; font-weight: 700; color: #fff;
    flex-shrink: 0;
}
#selectedInfo .info { flex: 1; }
#selectedInfo .sname { font-size: 15px; font-weight: 700; color: #fff; }
#selectedInfo .srole { font-size: 12px; color: rgba(255,255,255,.5); }
#clearBtn { font-size: 12px; color: rgba(255,255,255,.4); cursor: pointer; background: none; border: none; padding: 4px 8px; }
#clearBtn:hover { color: #fff; }

.time-display { text-align: center; font-size: 32px; font-weight: 700; color: #fff; letter-spacing: .04em; }
.date-display { text-align: center; font-size: 13px; color: rgba(255,255,255,.5); margin-bottom: 20px; }

/* Tab switcher */
.tab-row { display: flex; gap: 8px; margin-bottom: 16px; }
.tab-pill {
    flex: 1; padding: 8px; text-align: center;
    background: rgba(255,255,255,.06);
    border: 1.5px solid rgba(255,255,255,.1);
    border-radius: 10px;
    font-size: 12.5px; font-weight: 600; color: rgba(255,255,255,.5);
    cursor: pointer; transition: all .15s;
}
.tab-pill.active { background: rgba(199,125,255,.15); border-color: #c77dff; color: #fff; }
</style>
</head>
<body>

<div class="brand">Dent<span>fluence</span></div>
<p class="tagline">Staff Entry / Exit Log</p>

{{-- Clock --}}
<div class="card" style="padding:18px;max-width:420px;text-align:center;">
    <div class="time-display" id="clockTime">00:00:00</div>
    <div class="date-display" id="clockDate"></div>
</div>

{{-- Main action card --}}
<div class="card" style="max-width:420px;">

    {{-- Selected staff bar --}}
    <div id="selectedInfo">
        <div class="big-avatar" id="selAvatar">?</div>
        <div class="info">
            <div class="sname" id="selName">—</div>
            <div class="srole" id="selRole"></div>
        </div>
        <button class="clearBtn" id="clearBtn" onclick="clearSelection()">✕ Change</button>
    </div>

    {{-- Tab: QR vs Staff List --}}
    <div class="tab-row">
        <div class="tab-pill active" id="tabQr"    onclick="switchMode('qr')">QR Code</div>
        <div class="tab-pill"        id="tabStaff" onclick="switchMode('staff')">Staff List</div>
    </div>

    {{-- QR input panel --}}
    <div id="panelQr">
        <p style="font-size:12px;color:rgba(255,255,255,.5);margin-bottom:10px;">Scan your QR code or type the token manually:</p>
        <div class="qr-input-wrap">
            <input type="text" id="qrInput" class="qr-input" placeholder="Scan / paste QR token…" autocomplete="off" autofocus>
            <svg class="qr-icon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                <rect x="3" y="14" width="7" height="7"/><line x1="14" y1="14" x2="14" y2="21"/>
                <line x1="17" y1="14" x2="17" y2="14"/><line x1="20" y1="14" x2="20" y2="21"/>
                <line x1="17" y1="17" x2="20" y2="17"/>
            </svg>
        </div>
        <p style="font-size:11px;color:rgba(255,255,255,.3);margin-top:8px;text-align:center;">QR code auto-submits on scan · or press Enter</p>
    </div>

    {{-- Staff list panel --}}
    <div id="panelStaff" style="display:none;">
        <input type="text" id="staffSearch" class="qr-input" placeholder="Search staff…" oninput="filterStaff(this.value)" style="margin-bottom:12px;">
        <div class="staff-grid" id="staffGrid">
            @foreach($staffList as $member)
            <div class="staff-card" onclick="selectStaff('{{ $member->hrProfile?->qr_token }}','{{ addslashes($member->name) }}','{{ ucfirst(str_replace('_',' ',$member->role)) }}')"
                 id="sc_{{ $member->id }}" data-name="{{ strtolower($member->name) }}" data-token="{{ $member->hrProfile?->qr_token }}">
                <div class="staff-avatar">{{ strtoupper(substr($member->name,0,1)) }}</div>
                <div class="staff-name">{{ $member->name }}</div>
                <div class="staff-role">{{ ucfirst(str_replace('_',' ',$member->role)) }}</div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Entry / Exit buttons --}}
    <div class="action-btns" style="margin-top:20px;">
        <button class="btn btn-entry" id="btnEntry" onclick="doLog('entry')" disabled>
            ↓ Entry
        </button>
        <button class="btn btn-exit" id="btnExit" onclick="doLog('exit')" disabled>
            ↑ Exit
        </button>
    </div>
</div>

{{-- Toast notification --}}
<div id="toast">—</div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
let currentToken = null;

// ── Clock ──
function updateClock() {
    const now = new Date();
    document.getElementById('clockTime').textContent =
        now.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    document.getElementById('clockDate').textContent =
        now.toLocaleDateString('en-IN', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
}
updateClock();
setInterval(updateClock, 1000);

// ── Mode switch ──
function switchMode(mode) {
    document.getElementById('panelQr').style.display    = mode === 'qr'    ? '' : 'none';
    document.getElementById('panelStaff').style.display = mode === 'staff' ? '' : 'none';
    document.getElementById('tabQr').classList.toggle('active',    mode === 'qr');
    document.getElementById('tabStaff').classList.toggle('active', mode === 'staff');
    if (mode === 'qr') document.getElementById('qrInput').focus();
}

// ── QR input: auto-submit on scan (most QR scanners append Enter/Return) ──
const qrInput = document.getElementById('qrInput');
let qrDebounce;
qrInput.addEventListener('input', function() {
    clearTimeout(qrDebounce);
    const val = this.value.trim();
    if (val.length < 6) return; // skip short partial input
    // QR scanner typically fires all chars in < 100ms then sends Enter
    qrDebounce = setTimeout(() => {
        if (this.value.trim().length >= 6) {
            setToken(this.value.trim(), null, null);
        }
    }, 300);
});
qrInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        clearTimeout(qrDebounce);
        const val = this.value.trim();
        if (val) setToken(val, null, null);
        e.preventDefault();
    }
});

// ── Staff tap selection ──
function selectStaff(token, name, role) {
    if (!token) { showToast('This staff member has no QR token assigned.', 'error'); return; }
    document.querySelectorAll('.staff-card').forEach(c => c.classList.remove('selected'));
    const sel = document.querySelector(`[data-token="${token}"]`);
    if (sel) sel.classList.add('selected');
    setToken(token, name, role);
}

function setToken(token, name, role) {
    currentToken = token;
    const initials = name ? name.charAt(0).toUpperCase() : '?';
    document.getElementById('selAvatar').textContent = initials;
    document.getElementById('selName').textContent   = name || token;
    document.getElementById('selRole').textContent   = role || '';
    document.getElementById('selectedInfo').style.display = 'flex';
    document.getElementById('btnEntry').disabled = false;
    document.getElementById('btnExit').disabled  = false;
}

function clearSelection() {
    currentToken = null;
    document.getElementById('selectedInfo').style.display = 'none';
    document.getElementById('btnEntry').disabled = true;
    document.getElementById('btnExit').disabled  = true;
    document.querySelectorAll('.staff-card').forEach(c => c.classList.remove('selected'));
    document.getElementById('qrInput').value = '';
    document.getElementById('qrInput').focus();
}

// ── Filter staff list ──
function filterStaff(q) {
    const s = q.toLowerCase();
    document.querySelectorAll('.staff-card').forEach(card => {
        card.style.display = card.dataset.name.includes(s) ? '' : 'none';
    });
}

// ── Log entry / exit ──
async function doLog(type) {
    if (!currentToken) { showToast('Select a staff member first.', 'error'); return; }

    const btnEntry = document.getElementById('btnEntry');
    const btnExit  = document.getElementById('btnExit');
    btnEntry.disabled = btnExit.disabled = true;
    btnEntry.textContent = btnExit.textContent = '…';

    try {
        const res = await fetch('/hr/scan', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
            },
            body: JSON.stringify({ qr_token: currentToken, type }),
        });
        const data = await res.json();

        if (data.ok) {
            showToast(`✓ ${data.message}\n${data.time}`, 'success');
            // Briefly show success then reset
            setTimeout(() => clearSelection(), 2500);
        } else {
            showToast(data.message || 'Could not log. Try again.', 'error');
            btnEntry.disabled = false;
            btnExit.disabled  = false;
        }
    } catch (e) {
        showToast('Network error. Check connection.', 'error');
        btnEntry.disabled = false;
        btnExit.disabled  = false;
    }

    btnEntry.textContent = '↓ Entry';
    btnExit.textContent  = '↑ Exit';
}

// ── Toast ──
let toastTimer;
function showToast(msg, type = '') {
    const t = document.getElementById('toast');
    t.textContent  = msg;
    t.className    = 'show ' + type;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { t.className = type; }, 3500);
}
</script>
</body>
</html>
