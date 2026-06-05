<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>FitSync — QR Scanner</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
    <style>
        :root {
            --red: #cc1a1a;
            --red-soft: rgba(204,26,26,.1);
            --red-glow: rgba(204,26,26,.22);
        }
        [data-theme="dark"] {
            --bg: #0d0d0d;
            --surface: #111;
            --surface2: #171717;
            --border: rgba(255,255,255,.07);
            --border2: rgba(255,255,255,.12);
            --text: #f0f0f0;
            --text-2: rgba(255,255,255,.5);
            --text-3: rgba(255,255,255,.25);
            --input-bg: rgba(255,255,255,.05);
            --row-hover: rgba(255,255,255,.02);
        }
        [data-theme="light"] {
            --bg: #f4f4f5;
            --surface: #fff;
            --surface2: #f9f9f9;
            --border: rgba(0,0,0,.07);
            --border2: rgba(0,0,0,.13);
            --text: #111;
            --text-2: rgba(0,0,0,.48);
            --text-3: rgba(0,0,0,.25);
            --input-bg: rgba(0,0,0,.04);
            --row-hover: rgba(0,0,0,.02);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; }
        body {
            font-family: 'Outfit', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            font-size: 14px;
            line-height: 1.5;
        }
        button { font-family: inherit; cursor: pointer; }

        /* ─── FULL-SCREEN GRID ───────────────────── */
        .scanner-layout {
            display: grid;
            grid-template-columns: 1fr 360px;
            gap: 1.25rem;
            padding: 1.5rem;
            min-height: 100vh;
            align-items: start;
        }

        /* ─── CARD ───────────────────────────────── */
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            overflow: hidden;
        }
        .card-head {
            display: flex; align-items: center;
            justify-content: space-between;
            padding: .9rem 1.1rem;
            border-bottom: 1px solid var(--border);
        }
        .card-title { font-size: .9rem; font-weight: 700; }
        .card-sub   { font-size: .7rem; color: var(--text-3); }
        .card-body  { padding: 1.25rem; }

        /* ─── CAMERA ─────────────────────────────── */
        .scanner-wrap {
            position: relative;
            width: 100%;
            aspect-ratio: 4/3;
            background: #000;
            border-radius: 10px;
            overflow: hidden;
        }
        #qr-video { width:100%; height:100%; object-fit:cover; display:block; }
        #qr-canvas { display:none; }

        .scan-overlay {
            position: absolute; inset: 0;
            display: flex; align-items: center; justify-content: center;
            pointer-events: none;
        }
        .scan-frame { width: 190px; height: 190px; position: relative; }
        .scan-frame::before, .scan-frame::after,
        .scan-frame .cb, .scan-frame .cbr {
            content:''; position:absolute; width:28px; height:28px;
            border-color:var(--red); border-style:solid;
        }
        .scan-frame::before  { top:0; left:0;   border-width:3px 0 0 3px; border-radius:4px 0 0 0; }
        .scan-frame::after   { top:0; right:0;  border-width:3px 3px 0 0; border-radius:0 4px 0 0; }
        .scan-frame .cb      { bottom:0; left:0;  border-width:0 0 3px 3px; border-radius:0 0 0 4px; }
        .scan-frame .cbr     { bottom:0; right:0; border-width:0 3px 3px 0; border-radius:0 0 4px 0; }
        .scan-line {
            position:absolute; left:4px; right:4px; height:2px;
            background:linear-gradient(90deg,transparent,var(--red),transparent);
            border-radius:99px; box-shadow:0 0 8px var(--red);
            animation:scanMove 2s ease-in-out infinite;
        }
        @keyframes scanMove {
            0%   { top:8px; opacity:1; }
            45%  { top:calc(100% - 10px); opacity:1; }
            50%  { top:calc(100% - 10px); opacity:0; }
            55%  { top:8px; opacity:0; }
            60%  { top:8px; opacity:1; }
            100% { top:8px; opacity:1; }
        }

        .scanner-idle {
            position:absolute; inset:0;
            display:flex; flex-direction:column;
            align-items:center; justify-content:center; gap:.75rem;
            background:var(--surface2);
        }
        .scanner-idle i { font-size:2.8rem; color:var(--text-3); }
        .scanner-idle p { font-size:.8rem; color:var(--text-2); }

        .scan-flash {
            position:absolute; inset:0;
            background:rgba(76,175,135,.18);
            border:2px solid #4caf87; border-radius:10px;
            display:none;
            animation:flashIn .35s ease forwards;
        }
        @keyframes flashIn { 0%{opacity:0} 30%{opacity:1} 100%{opacity:1} }

        /* ─── CONTROLS ───────────────────────────── */
        .controls-row { display:flex; gap:.6rem; margin-top:.9rem; flex-wrap:wrap; }
        .btn {
            display:inline-flex; align-items:center; gap:.4rem;
            padding:.4rem .9rem; border-radius:99px;
            font-size:.78rem; font-weight:600; font-family:inherit;
            border:1px solid var(--border2); background:transparent;
            color:var(--text-2); cursor:pointer; transition:all .15s;
        }
        .btn:hover { background:var(--red-soft); border-color:rgba(204,26,26,.3); color:var(--text); }
        .btn.primary { background:var(--red); border-color:var(--red); color:#fff; }
        .btn.primary:hover { background:#a01212; border-color:#a01212; }
        .btn:disabled { opacity:.4; pointer-events:none; }

        /* manual entry */
        .manual-row { display:flex; gap:.6rem; margin-top:.75rem; }
        .manual-row input {
            flex:1; background:var(--input-bg); border:1px solid var(--border);
            color:var(--text); border-radius:9px; padding:.42rem .85rem;
            font-size:.82rem; font-family:inherit; outline:none; transition:border-color .2s;
        }
        .manual-row input:focus { border-color:rgba(204,26,26,.45); }
        .manual-row input::placeholder { color:var(--text-3); }

        /* ─── LOG TABLE ──────────────────────────── */
        .tbl-wrap { border-radius:10px; overflow:hidden; border:1px solid var(--border); margin-top:1.1rem; }
        table { width:100%; border-collapse:collapse; }
        thead th {
            background:rgba(255,255,255,.03); border-bottom:1px solid var(--border);
            color:var(--text-3); font-size:.6rem; font-weight:700;
            text-transform:uppercase; letter-spacing:.7px;
            padding:.65rem 1rem; white-space:nowrap; text-align:left;
        }
        tbody td { padding:.7rem 1rem; border-bottom:1px solid var(--border); font-size:.82rem; vertical-align:middle; }
        tbody tr:last-child td { border-bottom:none; }
        tbody tr:hover td { background:var(--row-hover); }
        .empty-log { text-align:center; color:var(--text-3); font-size:.78rem; padding:2rem 1rem; }

        /* ─── MEMBER PANEL ───────────────────────── */
        .right-col { display:flex; flex-direction:column; gap:1.1rem; }

        .mem-header { display:flex; align-items:center; gap:.85rem; margin-bottom:1.1rem; }
        .mem-av {
            width:50px; height:50px; border-radius:13px;
            background:linear-gradient(135deg,var(--red),#7a0f0f);
            display:flex; align-items:center; justify-content:center;
            font-size:1.15rem; font-weight:800; color:#fff; flex-shrink:0;
        }
        .mem-name { font-size:.98rem; font-weight:700; line-height:1.25; }
        .mem-id   { font-size:.7rem; color:var(--text-3); margin-top:.1rem; }

        .detail-list { display:flex; flex-direction:column; gap:.55rem; }
        .detail-row {
            display:flex; align-items:center; justify-content:space-between;
            padding:.52rem .7rem; background:var(--surface2);
            border-radius:9px; border:1px solid var(--border);
        }
        .detail-label {
            font-size:.68rem; font-weight:700; color:var(--text-3);
            text-transform:uppercase; letter-spacing:.5px;
            display:flex; align-items:center; gap:.35rem;
        }
        .detail-label i { font-size:.82rem; }
        .detail-val { font-size:.83rem; font-weight:600; }

        .badge {
            display:inline-flex; align-items:center;
            padding:.18rem .52rem; border-radius:99px;
            font-size:.62rem; font-weight:700;
            text-transform:uppercase; letter-spacing:.4px;
        }
        .badge.active    { background:rgba(76,175,135,.12); color:#4caf87; }
        .badge.expired   { background:rgba(150,150,150,.12); color:#888; }
        .badge.frozen    { background:rgba(100,160,255,.12); color:#6ea4f0; }
        .badge.pending   { background:rgba(255,193,7,.12); color:#d6a100; }
        .badge.cancelled { background:rgba(220,53,69,.12); color:#e05656; }

        .expiry-warn {
            display:flex; align-items:center; gap:.55rem;
            background:rgba(204,26,26,.08); border:1px solid rgba(204,26,26,.2);
            border-radius:9px; padding:.6rem .8rem;
            font-size:.76rem; color:rgba(255,120,120,.85); margin-bottom:.85rem;
        }
        .expiry-warn i { font-size:.9rem; color:var(--red); flex-shrink:0; }

        .member-empty {
            display:flex; flex-direction:column;
            align-items:center; justify-content:center;
            gap:.7rem; padding:2.5rem 1.5rem; text-align:center;
        }
        .icon-ring {
            width:58px; height:58px; border-radius:50%;
            background:var(--red-soft);
            display:flex; align-items:center; justify-content:center;
            font-size:1.5rem; color:var(--red);
        }
        .member-empty h3 { font-size:.9rem; font-weight:700; }
        .member-empty p  { font-size:.76rem; color:var(--text-2); }

        .checkin-row { display:flex; gap:.6rem; margin-top:1.1rem; }
        .btn-checkin {
            flex:1; padding:.6rem 1rem; border-radius:10px;
            font-size:.84rem; font-weight:700; border:none;
            background:var(--red); color:#fff; cursor:pointer;
            display:flex; align-items:center; justify-content:center; gap:.5rem;
            transition:background .15s;
        }
        .btn-checkin:hover { background:#a01212; }
        .btn-checkin:disabled { opacity:.4; pointer-events:none; }
        .btn-clear {
            padding:.6rem .9rem; border-radius:10px; font-size:.82rem;
            font-weight:600; font-family:inherit;
            border:1px solid var(--border2); background:transparent;
            color:var(--text-2); cursor:pointer; transition:all .15s;
        }
        .btn-clear:hover { background:var(--input-bg); color:var(--text); }

        /* stats mini grid */
        .stats-grid { display:grid; grid-template-columns:1fr 1fr; gap:.65rem; }
        .stat-mini {
            background:var(--surface2); border:1px solid var(--border);
            border-radius:10px; padding:.85rem 1rem;
        }
        .stat-mini-lbl { font-size:.62rem; font-weight:700; color:var(--text-3); text-transform:uppercase; letter-spacing:.5px; margin-bottom:.3rem; }
        .stat-mini-val { font-size:1.9rem; font-weight:800; line-height:1; }

        /* dot status */
        .dot { width:7px; height:7px; border-radius:50%; display:inline-block; margin-right:.35rem; }
        .dot.online  { background:#4caf87; box-shadow:0 0 5px rgba(76,175,135,.5); }
        .dot.offline { background:#555; }

        /* ─── TOAST ──────────────────────────────── */
        #toast-container {
            position:fixed; bottom:1.5rem; right:1.5rem;
            display:flex; flex-direction:column; gap:.5rem; z-index:9999;
        }
        .toast {
            background:var(--surface); border:1px solid var(--border2);
            border-radius:12px; padding:.8rem 1.1rem;
            display:flex; align-items:flex-start; gap:.65rem;
            min-width:280px; max-width:360px;
            box-shadow:0 8px 24px rgba(0,0,0,.35);
            animation:toastIn .2s ease; transition:opacity .3s, transform .3s;
        }
        .toast.out { opacity:0; transform:translateX(20px); }
        @keyframes toastIn { from { opacity:0; transform:translateX(20px); } }
        .toast-icon { font-size:1.1rem; flex-shrink:0; margin-top:.05rem; }
        .toast.toast-success .toast-icon { color:#4caf87; }
        .toast.toast-error   .toast-icon { color:#e05656; }
        .toast.toast-info    .toast-icon { color:#6ea4f0; }
        .toast-body { flex:1; }
        .toast-title { font-size:.83rem; font-weight:700; margin-bottom:.15rem; }
        .toast-msg   { font-size:.77rem; color:var(--text-2); }
        .toast-close { background:none; border:none; color:var(--text-3); font-size:1rem; cursor:pointer; padding:0; transition:color .15s; }
        .toast-close:hover { color:var(--text); }

        /* ─── RESPONSIVE ─────────────────────────── */
        @media (max-width: 860px) {
            .scanner-layout { grid-template-columns:1fr; }
        }
    </style>
</head>
<body>

<div class="scanner-layout">

    <!-- ══ LEFT: QR Scanner ══════════════════════ -->
    <div>
        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title"><i class="ti ti-scan" style="color:var(--red);margin-right:.35rem"></i>QR Scanner</div>
                    <div class="card-sub" id="scanner-status-label">Camera inactive — press Start to begin</div>
                </div>
                <div style="display:flex;align-items:center;gap:.4rem">
                    <span class="dot offline" id="status-dot"></span>
                    <span style="font-size:.72rem;color:var(--text-3)" id="status-text">Offline</span>
                </div>
            </div>
            <div class="card-body">

                <!-- Camera -->
                <div class="scanner-wrap" id="scanner-wrap">
                    <video id="qr-video" autoplay playsinline muted></video>
                    <canvas id="qr-canvas"></canvas>

                    <div class="scanner-idle" id="scanner-idle">
                        <i class="ti ti-camera-off"></i>
                        <p>Camera is off. Press <strong>Start Camera</strong> below.</p>
                    </div>

                    <div class="scan-overlay" id="scan-overlay" style="display:none">
                        <div class="scan-frame">
                            <div class="cb"></div><div class="cbr"></div>
                            <div class="scan-line"></div>
                        </div>
                    </div>

                    <div class="scan-flash" id="scan-flash"></div>
                </div>

                <!-- Controls -->
                <div class="controls-row">
                    <button class="btn primary" id="btn-start" onclick="startCamera()"><i class="ti ti-player-play"></i> Start Camera</button>
                    <button class="btn" id="btn-stop"  onclick="stopCamera()"  disabled><i class="ti ti-player-stop"></i> Stop</button>
                    <button class="btn" id="btn-flip"  onclick="flipCamera()"  disabled><i class="ti ti-camera-rotate"></i> Flip</button>
                </div>

                <!-- Manual -->
                <div style="margin-top:1.1rem;padding-top:1.1rem;border-top:1px solid var(--border)">
                    <div style="font-size:.68rem;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:.6px;margin-bottom:.5rem">
                        <i class="ti ti-keyboard"></i> Manual ID Entry
                    </div>
                    <div class="manual-row">
                        <input type="text" id="manual-input" placeholder="Enter Member ID (e.g. MBR-00001)" onkeydown="if(event.key==='Enter')manualLookup()" />
                        <button class="btn primary" onclick="manualLookup()"><i class="ti ti-search"></i> Lookup</button>
                    </div>
                </div>

                <!-- Log -->
                <div style="margin-top:1.3rem">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">
                        <span style="font-size:.82rem;font-weight:700">Recent Scans</span>
                        <button class="btn" style="padding:.22rem .6rem;font-size:.68rem" onclick="clearLog()"><i class="ti ti-trash"></i> Clear</button>
                    </div>
                    <div class="tbl-wrap">
                        <table>
                            <thead><tr><th>Member ID</th><th>Name</th><th>Status</th><th>Time</th></tr></thead>
                            <tbody id="scan-log-body">
                                <tr><td colspan="4" class="empty-log"><i class="ti ti-history" style="display:block;font-size:1.4rem;margin-bottom:.4rem"></i>No scans yet</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- ══ RIGHT: Member Info ═════════════════════ -->
    <div class="right-col">

        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title"><i class="ti ti-id-badge-2" style="color:var(--red);margin-right:.35rem"></i>Member Info</div>
                    <div class="card-sub">Scanned member details</div>
                </div>
            </div>
            <div class="card-body">

                <!-- Empty state -->
                <div class="member-empty" id="member-empty">
                    <div class="icon-ring"><i class="ti ti-user-search"></i></div>
                    <h3>Awaiting Scan</h3>
                    <p>Scan a QR code or enter a Member ID to view details.</p>
                </div>

                <!-- Member data -->
                <div id="member-data" style="display:none">
                    <div class="mem-header">
                        <div class="mem-av" id="mem-initials">--</div>
                        <div>
                            <div class="mem-name" id="mem-name">—</div>
                            <div class="mem-id" id="mem-id">ID: —</div>
                        </div>
                        <span class="badge" id="mem-status-badge" style="margin-left:auto">—</span>
                    </div>

                    <div class="expiry-warn" id="expiry-warn" style="display:none">
                        <i class="ti ti-alert-triangle"></i>
                        <span id="expiry-warn-text"></span>
                    </div>

                    <div class="detail-list">
                        <div class="detail-row">
                            <span class="detail-label"><i class="ti ti-id-badge"></i> Membership</span>
                            <span class="detail-val" id="mem-plan">—</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label"><i class="ti ti-activity"></i> Status</span>
                            <span class="detail-val" id="mem-status-text">—</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label"><i class="ti ti-calendar-event"></i> Expiry Date</span>
                            <span class="detail-val" id="mem-expiry">—</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label"><i class="ti ti-clock"></i> Last Visit</span>
                            <span class="detail-val" id="mem-last-visit">—</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label"><i class="ti ti-building"></i> Branch</span>
                            <span class="detail-val" id="mem-branch">—</span>
                        </div>
                    </div>

                    <div class="checkin-row">
                        <button class="btn-checkin" id="btn-checkin" onclick="doCheckIn()">
                            <i class="ti ti-check"></i> Confirm Check-In
                        </button>
                        <button class="btn-clear" onclick="clearMember()" title="Clear"><i class="ti ti-x"></i></button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Today's stats -->
        <div class="card">
            <div class="card-head">
                <div class="card-title"><i class="ti ti-chart-bar" style="color:var(--red);margin-right:.35rem"></i>Today's Check-Ins</div>
                <div class="card-sub" id="today-date">—</div>
            </div>
            <div class="card-body">
                <div class="stats-grid">
                    <div class="stat-mini">
                        <div class="stat-mini-lbl">Total</div>
                        <div class="stat-mini-val" id="stat-total">0</div>
                    </div>
                    <div class="stat-mini">
                        <div class="stat-mini-lbl">Active</div>
                        <div class="stat-mini-val" style="color:#4caf87" id="stat-active">0</div>
                    </div>
                    <div class="stat-mini">
                        <div class="stat-mini-lbl">Expired</div>
                        <div class="stat-mini-val" style="color:#e05656" id="stat-expired">0</div>
                    </div>
                    <div class="stat-mini">
                        <div class="stat-mini-lbl">Denied</div>
                        <div class="stat-mini-val" style="color:#d6a100" id="stat-denied">0</div>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /right-col -->
</div><!-- /scanner-layout -->

<div id="toast-container"></div>

<script>
/* ── MOCK DB ─────────────────────────────────── */
const mockMembers = {
    'MBR-00001': { id:'MBR-00001', fname:'Maria',  lname:'Santos',   plan:'Annual',   status:'active',  expiry:'2026-01-10', lastVisit:'Jun 4, 2026',  branch:'Makati'  },
    'MBR-00002': { id:'MBR-00002', fname:'Carlos', lname:'Tan',      plan:'6 Months', status:'active',  expiry:'2026-08-20', lastVisit:'Jun 3, 2026',  branch:'BGC'     },
    'MBR-00003': { id:'MBR-00003', fname:'Jose',   lname:'Reyes',    plan:'Monthly',  status:'expired', expiry:'2026-05-01', lastVisit:'Apr 30, 2026', branch:'Alabang' },
    'MBR-00004': { id:'MBR-00004', fname:'Nina',   lname:'Bautista', plan:'3 Months', status:'frozen',  expiry:'2026-09-15', lastVisit:'May 12, 2026', branch:'Makati'  },
    'MBR-00005': { id:'MBR-00005', fname:'Liza',   lname:'Gomez',    plan:'Annual',   status:'active',  expiry:'2026-06-20', lastVisit:'Jun 4, 2026',  branch:'QC'      },
};

let stats = { total:0, active:0, expired:0, denied:0 };
let scanLog = [];
let currentMember = null;
let stream = null;
let scanning = false;
let facingMode = 'environment';
let rafId = null;

/* ── CAMERA ──────────────────────────────────── */
async function startCamera() {
    try {
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode, width:{ideal:1280}, height:{ideal:960} } });
        const v = document.getElementById('qr-video');
        v.srcObject = stream;
        await v.play();
        document.getElementById('scanner-idle').style.display = 'none';
        document.getElementById('scan-overlay').style.display = 'flex';
        document.getElementById('btn-start').disabled = true;
        document.getElementById('btn-stop').disabled  = false;
        document.getElementById('btn-flip').disabled  = false;
        document.getElementById('status-dot').className = 'dot online';
        document.getElementById('status-text').textContent = 'Live';
        document.getElementById('scanner-status-label').textContent = 'Scanning for QR codes…';
        scanning = true;
        requestAnimationFrame(scanFrame);
        toast('info', 'Camera started', 'Scanning for QR codes…');
    } catch(e) {
        toast('error', 'Camera error', e.message || 'Could not access camera.');
    }
}

function stopCamera() {
    scanning = false;
    if (rafId) cancelAnimationFrame(rafId);
    if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
    const v = document.getElementById('qr-video');
    v.srcObject = null;
    document.getElementById('scanner-idle').style.display = 'flex';
    document.getElementById('scan-overlay').style.display = 'none';
    document.getElementById('btn-start').disabled = false;
    document.getElementById('btn-stop').disabled  = true;
    document.getElementById('btn-flip').disabled  = true;
    document.getElementById('status-dot').className = 'dot offline';
    document.getElementById('status-text').textContent = 'Offline';
    document.getElementById('scanner-status-label').textContent = 'Camera inactive — press Start to begin';
}

async function flipCamera() {
    facingMode = facingMode === 'environment' ? 'user' : 'environment';
    stopCamera(); await startCamera();
}

function scanFrame() {
    if (!scanning) return;
    const v = document.getElementById('qr-video');
    const c = document.getElementById('qr-canvas');
    if (v.readyState === v.HAVE_ENOUGH_DATA) {
        c.width = v.videoWidth; c.height = v.videoHeight;
        const ctx = c.getContext('2d');
        ctx.drawImage(v, 0, 0, c.width, c.height);
        const img = ctx.getImageData(0, 0, c.width, c.height);
        const code = jsQR(img.data, img.width, img.height, { inversionAttempts:'dontInvert' });
        if (code) {
            handleScan(code.data);
            scanning = false;
            setTimeout(() => { scanning = true; requestAnimationFrame(scanFrame); }, 2500);
            return;
        }
    }
    rafId = requestAnimationFrame(scanFrame);
}

/* ── LOOKUP ──────────────────────────────────── */
function handleScan(raw) { lookupMember(raw.trim().toUpperCase()); }

function manualLookup() {
    const val = document.getElementById('manual-input').value.trim().toUpperCase();
    if (!val) { toast('error', 'Empty input', 'Please enter a Member ID.'); return; }
    document.getElementById('manual-input').value = '';
    lookupMember(val);
}

function lookupMember(id) {
    const flash = document.getElementById('scan-flash');
    flash.style.display = 'block';
    setTimeout(() => flash.style.display = 'none', 700);

    const m = mockMembers[id] || null;
    if (!m) {
        toast('error', 'Not found', `No member with ID "${id}".`);
        addToLog(id, '—', 'not found');
        stats.denied++; stats.total++;
        updateStats(); return;
    }
    currentMember = m;
    renderMember(m);
    const msg = { active:'success', expired:'error', frozen:'info' }[m.status] || 'info';
    const label = { active:'Member found', expired:'Membership expired', frozen:'Membership frozen' }[m.status];
    toast(msg, label, `${m.fname} ${m.lname} — ${m.plan}`);
}

function renderMember(m) {
    document.getElementById('member-empty').style.display = 'none';
    document.getElementById('member-data').style.display = 'block';
    document.getElementById('mem-initials').textContent = (m.fname[0]+m.lname[0]).toUpperCase();
    document.getElementById('mem-name').textContent = `${m.fname} ${m.lname}`;
    document.getElementById('mem-id').textContent = `ID: ${m.id}`;
    document.getElementById('mem-plan').textContent = m.plan;
    document.getElementById('mem-last-visit').textContent = m.lastVisit;
    document.getElementById('mem-branch').textContent = m.branch;
    document.getElementById('mem-expiry').textContent = fmtDate(m.expiry);
    document.getElementById('mem-status-text').textContent = cap(m.status);
    const badge = document.getElementById('mem-status-badge');
    badge.textContent = cap(m.status); badge.className = `badge ${m.status}`;

    const daysLeft = Math.ceil((new Date(m.expiry) - new Date()) / 86400000);
    const warn = document.getElementById('expiry-warn');
    if (m.status === 'active' && daysLeft <= 30 && daysLeft >= 0) {
        warn.style.display = 'flex';
        document.getElementById('expiry-warn-text').textContent = `Membership expires in ${daysLeft} day${daysLeft===1?'':'s'}.`;
    } else if (m.status === 'expired') {
        warn.style.display = 'flex';
        document.getElementById('expiry-warn-text').textContent = `Membership expired on ${fmtDate(m.expiry)}.`;
    } else { warn.style.display = 'none'; }

    document.getElementById('btn-checkin').disabled = m.status !== 'active';
}

function clearMember() {
    currentMember = null;
    document.getElementById('member-empty').style.display = 'flex';
    document.getElementById('member-data').style.display = 'none';
}

function doCheckIn() {
    if (!currentMember) return;
    const m = currentMember;
    addToLog(m.id, `${m.fname} ${m.lname}`, m.status);
    stats.total++;
    if (m.status === 'active') stats.active++;
    else if (m.status === 'expired') stats.expired++;
    else stats.denied++;
    updateStats();
    toast('success', 'Checked in!', `${m.fname} ${m.lname} successfully checked in.`);
    clearMember();
}

/* ── LOG ─────────────────────────────────────── */
function addToLog(id, name, status) {
    const time = new Date().toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' });
    scanLog.unshift({ id, name, status, time });
    renderLog();
}
function renderLog() {
    const body = document.getElementById('scan-log-body');
    if (!scanLog.length) {
        body.innerHTML = '<tr><td colspan="4" class="empty-log"><i class="ti ti-history" style="display:block;font-size:1.4rem;margin-bottom:.4rem"></i>No scans yet</td></tr>';
        return;
    }
    const map = { active:'active', expired:'expired', frozen:'frozen', 'not found':'cancelled' };
    body.innerHTML = scanLog.slice(0,10).map(r => `
        <tr>
            <td style="font-family:monospace;font-size:.77rem">${r.id}</td>
            <td style="font-weight:600">${r.name}</td>
            <td><span class="badge ${map[r.status]||''}">${r.status}</span></td>
            <td style="color:var(--text-3)">${r.time}</td>
        </tr>`).join('');
}
function clearLog() { scanLog = []; renderLog(); }

/* ── STATS ───────────────────────────────────── */
function updateStats() {
    document.getElementById('stat-total').textContent   = stats.total;
    document.getElementById('stat-active').textContent  = stats.active;
    document.getElementById('stat-expired').textContent = stats.expired;
    document.getElementById('stat-denied').textContent  = stats.denied;
}

/* ── UTILS ───────────────────────────────────── */
function fmtDate(d) {
    if (!d) return '—';
    return new Date(d+'T00:00:00').toLocaleDateString('en-PH', { month:'short', day:'numeric', year:'numeric' });
}
function cap(s) { return s ? s.charAt(0).toUpperCase()+s.slice(1) : s; }

function toast(type, title, msg) {
    const icons = { success:'ti-circle-check', error:'ti-circle-x', info:'ti-info-circle' };
    const el = document.createElement('div');
    el.className = `toast toast-${type}`;
    el.innerHTML = `
        <i class="ti ${icons[type]||icons.info} toast-icon"></i>
        <div class="toast-body">
            <div class="toast-title">${title}</div>
            ${msg ? `<div class="toast-msg">${msg}</div>` : ''}
        </div>
        <button class="toast-close" onclick="this.closest('.toast').remove()"><i class="ti ti-x"></i></button>`;
    document.getElementById('toast-container').appendChild(el);
    setTimeout(() => { el.classList.add('out'); setTimeout(() => el.remove(), 300); }, 3800);
}

/* ── BOOT ────────────────────────────────────── */
(() => {
    const t = localStorage.getItem('fs-theme');
    if (t) document.documentElement.setAttribute('data-theme', t);
    document.getElementById('today-date').textContent = new Date().toLocaleDateString('en-PH', { weekday:'short', month:'long', day:'numeric' });
})();
</script>
</body>
</html>