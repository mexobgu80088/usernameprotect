<?php
/**
 * Hiruzick Guard — PHP Backend
 * Works on any shared hosting (PHP 7.4+)
 * 
 * Setup:
 *   1. Upload this file to your server as index.php
 *   2. Make sure reports.json is writable: chmod 666 reports.json
 *   3. Visit yoursite.com/ for the page
 *   4. API endpoints work at yoursite.com/?api=check&username=xxx etc.
 */

// ── Config ────────────────────────────────────────────────────────────────────
define('PROTECTED_NAMES', ['Hirushan_D', 'HirushanLk']);
define('REAL_USERNAME',   'Hiruzick');
define('REAL_LINK',       'https://t.me/Hiruzick');
define('REPORTS_FILE',    __DIR__ . '/reports.json');
define('SITE_URL',        'https://mexobgu80088.github.io/usernameprotect/');

// ── Helpers ───────────────────────────────────────────────────────────────────

function loadReports(): array {
    if (!file_exists(REPORTS_FILE)) return [];
    $data = json_decode(file_get_contents(REPORTS_FILE), true);
    return is_array($data) ? $data : [];
}

function saveReports(array $reports): void {
    file_put_contents(REPORTS_FILE, json_encode($reports, JSON_PRETTY_PRINT));
}

function isInfringement(string $username): array {
    $upper = strtoupper($username);
    foreach (PROTECTED_NAMES as $name) {
        if (str_contains($upper, strtoupper($name))) {
            return ['flagged' => true, 'matched' => $name];
        }
    }
    return ['flagged' => false, 'matched' => ''];
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    echo json_encode($data);
    exit;
}

function isApi(): bool {
    return isset($_GET['api']);
}

// ── CORS preflight ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(204);
    exit;
}

// ── API routing ───────────────────────────────────────────────────────────────
if (isApi()) {
    $action = $_GET['api'];

    // GET ?api=check&username=xxx
    if ($action === 'check') {
        $username = trim(ltrim($_GET['username'] ?? '', '@'));
        if (!$username) {
            jsonResponse(['error' => 'username parameter required'], 400);
        }
        if (strcasecmp($username, REAL_USERNAME) === 0) {
            jsonResponse([
                'username'        => $username,
                'is_real'         => true,
                'is_infringement' => false,
                'matched_keyword' => '',
                'message'         => 'This is the ONE verified real account.',
                'checked_at'      => date('c'),
            ]);
        }
        $result = isInfringement($username);
        jsonResponse([
            'username'        => $username,
            'is_real'         => false,
            'is_infringement' => $result['flagged'],
            'matched_keyword' => $result['matched'],
            'protected_names' => PROTECTED_NAMES,
            'checked_at'      => date('c'),
        ]);
    }

    // POST ?api=report  (body: JSON)
    if ($action === 'report' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body || empty($body['fake_username']) || empty($body['description'])) {
            jsonResponse(['error' => 'fake_username and description required'], 400);
        }
        $reports = loadReports();
        $newReport = [
            'id'            => count($reports) + 1,
            'fake_username' => htmlspecialchars($body['fake_username']),
            'telegram_link' => htmlspecialchars($body['telegram_link'] ?? ''),
            'description'   => htmlspecialchars($body['description']),
            'status'        => 'pending',
            'reported_at'   => date('c'),
        ];
        $reports[] = $newReport;
        saveReports($reports);
        error_log("[REPORT #{$newReport['id']}] Fake: @{$newReport['fake_username']}");
        jsonResponse(['success' => true, 'id' => $newReport['id'], 'message' => 'Report submitted.']);
    }

    // PATCH ?api=status  (body: {id, status})
    if ($action === 'status' && $_SERVER['REQUEST_METHOD'] === 'PATCH') {
        $body = json_decode(file_get_contents('php://input'), true);
        $id     = intval($body['id'] ?? 0);
        $status = $body['status'] ?? '';
        if (!in_array($status, ['pending', 'reported', 'banned'])) {
            jsonResponse(['error' => 'Invalid status. Use: pending | reported | banned'], 400);
        }
        $reports = loadReports();
        $found = false;
        foreach ($reports as &$r) {
            if ($r['id'] === $id) { $r['status'] = $status; $found = true; break; }
        }
        if (!$found) jsonResponse(['error' => 'Report not found'], 404);
        saveReports($reports);
        jsonResponse(['success' => true, 'id' => $id, 'status' => $status]);
    }

    // GET ?api=reports
    if ($action === 'reports') {
        $reports = loadReports();
        jsonResponse(['total' => count($reports), 'reports' => $reports]);
    }

    jsonResponse(['error' => 'Unknown API action'], 404);
}

// ── HTML Page ─────────────────────────────────────────────────────────────────
$reports      = loadReports();
$totalReports = count($reports);
$year         = date('Y');
$protectedStr = implode(', ', array_map(fn($n) => "@$n", PROTECTED_NAMES));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hiruzick Guard</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  :root{--bg:#f5f6f8;--white:#fff;--border:#e2e6ea;--text:#1a1f2e;--sub:#6b7a92;--blue:#0057ff;--blue-bg:#f0f4ff;--blue-bd:#c2d4ff;--red:#e5193a;--red-bg:#fff0f2;--red-bd:#ffc8d0;--green:#00875a;--green-bg:#f0fdf4;--green-bd:#b7f0d8;--yellow:#7a5c00;--yellow-bg:#fffbeb;--yellow-bd:#fde68a;--mono:'DM Mono',monospace;--sans:'DM Sans',sans-serif}
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  body{background:var(--bg);color:var(--text);font-family:var(--sans);font-weight:400;min-height:100vh;-webkit-font-smoothing:antialiased}
  .wrap{max-width:640px;margin:0 auto;padding:0 24px 80px}
  .topbar{display:flex;align-items:center;justify-content:space-between;padding:32px 0 28px;border-bottom:1px solid var(--border);margin-bottom:48px}
  .logo{font-family:var(--mono);font-size:14px;font-weight:500;letter-spacing:1px;color:var(--text)}.logo span{color:var(--blue)}
  .badge{display:flex;align-items:center;gap:6px;background:var(--green-bg);border:1px solid var(--green-bd);border-radius:99px;padding:4px 12px;font-family:var(--mono);font-size:10px;color:var(--green);letter-spacing:1px}
  .dot{width:6px;height:6px;border-radius:50%;background:var(--green);animation:blink 2s ease-in-out infinite}
  @keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}
  .hero{margin-bottom:40px}
  .hero-eyebrow{font-family:var(--mono);font-size:11px;letter-spacing:3px;color:var(--sub);text-transform:uppercase;margin-bottom:12px}
  .hero h1{font-size:clamp(30px,7vw,46px);font-weight:600;line-height:1.1;letter-spacing:-.5px;margin-bottom:14px}.hero h1 em{font-style:normal;color:var(--blue)}
  .hero p{font-size:15px;color:var(--sub);line-height:1.75;max-width:500px}
  .stats{display:flex;gap:1px;background:var(--border);border:1px solid var(--border);border-radius:12px;overflow:hidden;margin-bottom:16px}
  .stat{flex:1;background:var(--white);padding:18px 16px;text-align:center}
  .stat-num{font-family:var(--mono);font-size:22px;font-weight:500;color:var(--text);display:block}
  .stat-label{font-size:11px;color:var(--sub);margin-top:3px;display:block}
  .card{background:var(--white);border:1px solid var(--border);border-radius:12px;padding:24px;margin-bottom:16px}
  .card-title{font-family:var(--mono);font-size:10px;letter-spacing:2px;color:var(--sub);text-transform:uppercase;margin-bottom:16px}
  .card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}.card-header .card-title{margin-bottom:0}
  .verified-box{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;padding:16px 20px;background:var(--green-bg);border:1px solid var(--green-bd);border-radius:10px;margin-bottom:14px}
  .verified-left{display:flex;align-items:center;gap:12px}
  .verified-avatar{width:46px;height:46px;border-radius:50%;background:linear-gradient(135deg,#0057ff,#00c6ff);display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;color:#fff}
  .verified-name{font-family:var(--mono);font-size:16px;font-weight:500;color:var(--text)}.verified-name span{display:block;font-size:11px;color:var(--green);margin-top:3px;letter-spacing:.5px}
  .tg-link{display:inline-flex;align-items:center;gap:6px;background:var(--blue);color:#fff;text-decoration:none;padding:9px 16px;border-radius:8px;font-family:var(--mono);font-size:11px;font-weight:500;letter-spacing:1px;transition:background .15s;white-space:nowrap}.tg-link:hover{background:#0046d6}
  .verified-note{font-size:13px;color:var(--sub);line-height:1.7}.verified-note strong{color:var(--text)}.verified-note b{color:var(--red)}
  .fake-list{display:flex;flex-direction:column;gap:8px}
  .fake-item{display:flex;align-items:center;gap:10px;padding:11px 14px;background:var(--red-bg);border:1px solid var(--red-bd);border-radius:8px}
  .fake-icon{font-size:14px;flex-shrink:0}.fake-name{flex:1;font-family:var(--mono);font-size:13px;color:var(--red);word-break:break-all}.fake-lbl{font-family:var(--mono);font-size:10px;letter-spacing:1px;color:var(--red);opacity:.65;white-space:nowrap}
  .ids{display:flex;gap:8px;flex-wrap:wrap}
  .id-tag{background:var(--blue-bg);border:1px solid var(--blue-bd);border-radius:6px;padding:8px 16px;font-family:var(--mono);font-size:13px;font-weight:500;color:var(--blue);letter-spacing:.5px}
  .alert-box{margin-top:14px;padding:13px 16px;background:var(--yellow-bg);border:1px solid var(--yellow-bd);border-radius:8px;font-size:13px;color:var(--yellow);line-height:1.7}
  input,textarea{width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:11px 14px;color:var(--text);font-family:var(--mono);font-size:13px;outline:none;transition:border-color .15s,box-shadow .15s}
  input::placeholder,textarea::placeholder{color:#aab4c4}
  input:focus,textarea:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(0,87,255,.08);background:var(--white)}
  textarea{resize:vertical;min-height:72px}
  .input-row{display:flex;gap:8px}.input-row input{flex:1}
  .form-stack{display:flex;flex-direction:column;gap:10px}
  .btn{padding:11px 20px;border-radius:8px;border:none;font-family:var(--mono);font-size:11px;font-weight:500;letter-spacing:1px;cursor:pointer;text-transform:uppercase;white-space:nowrap;transition:all .15s}
  .btn-blue{background:var(--blue);color:#fff}.btn-blue:hover{background:#0046d6}
  .btn-red{background:var(--red);color:#fff}.btn-red:hover{background:#c2112e}
  .btn-ghost{background:transparent;border:1px solid var(--border);color:var(--sub);padding:5px 10px;font-size:10px}.btn-ghost:hover{border-color:var(--sub)}
  .btn:active{transform:scale(.97)}
  .result{display:none;margin-top:12px;padding:12px 14px;border-radius:8px;font-family:var(--mono);font-size:12px;line-height:1.8}
  .result.verified{background:var(--green-bg);border:2px solid var(--green);color:var(--green)}
  .result.safe{background:var(--green-bg);border:1px solid var(--green-bd);color:var(--green)}
  .result.danger{background:var(--red-bg);border:1px solid var(--red-bd);color:var(--red)}
  .result.ok{background:var(--green-bg);border:1px solid var(--green-bd);color:var(--green)}
  .result.err{background:var(--red-bg);border:1px solid var(--red-bd);color:var(--red)}
  .report-hint{margin-top:12px;font-size:12px;color:var(--sub);line-height:1.6}.report-hint a{color:var(--blue);text-decoration:none;font-family:var(--mono)}
  .rep-empty{font-family:var(--mono);font-size:12px;color:#aab4c4;text-align:center;padding:20px 0}
  .rep-list{display:flex;flex-direction:column;gap:8px}
  .rep-item{display:flex;align-items:flex-start;gap:12px;padding:12px 14px;background:var(--bg);border:1px solid var(--border);border-radius:8px}
  .rep-avatar{width:34px;height:34px;border-radius:50%;background:var(--red-bg);border:1px solid var(--red-bd);display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
  .rep-info{flex:1;min-width:0}.rep-username{font-family:var(--mono);font-size:13px;font-weight:500;color:var(--text)}.rep-desc{font-size:11px;color:var(--sub);margin-top:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .rep-meta{display:flex;flex-direction:column;align-items:flex-end;gap:5px;flex-shrink:0}
  .rep-status{display:inline-flex;align-items:center;font-family:var(--mono);font-size:9px;letter-spacing:1px;padding:3px 8px;border-radius:99px;cursor:pointer;white-space:nowrap}
  .rep-status.pending{background:var(--blue-bg);border:1px solid var(--blue-bd);color:var(--blue)}
  .rep-status.reported{background:var(--yellow-bg);border:1px solid var(--yellow-bd);color:var(--yellow)}
  .rep-status.banned{background:var(--red-bg);border:1px solid var(--red-bd);color:var(--red)}
  .rep-time{font-family:var(--mono);font-size:10px;color:#aab4c4}
  .rep-remove{background:none;border:none;color:#aab4c4;cursor:pointer;font-size:11px;padding:0;font-family:var(--mono)}.rep-remove:hover{color:var(--red)}
  .total-badge{font-family:var(--mono);font-size:10px;background:var(--red-bg);border:1px solid var(--red-bd);color:var(--red);border-radius:99px;padding:3px 10px;letter-spacing:1px}
  .foot{margin-top:48px;padding-top:20px;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px}
  .foot-brand{font-family:var(--mono);font-size:12px;color:var(--text);font-weight:500}.foot-brand span{color:var(--blue)}
  .foot-text{font-family:var(--mono);font-size:11px;color:#aab4c4}
  @media(max-width:480px){.input-row{flex-direction:column}.stats{flex-direction:column;gap:1px}.verified-box{flex-direction:column;align-items:flex-start}}
</style>
</head>
<body>
<div class="wrap">
  <div class="topbar">
    <span class="logo">Hiruzick<span> Guard</span></span>
    <div class="badge"><span class="dot"></span>ACTIVE</div>
  </div>
  <div class="hero">
    <div class="hero-eyebrow">// Official Identity Protection</div>
    <h1>One real account.<br><em>Everything else is fake.</em></h1>
    <p>There is only <strong>one verified @Hiruzick</strong> on Telegram. Any other account using lookalike names or Unicode characters is an impersonator.</p>
  </div>
  <div class="stats">
    <div class="stat"><span class="stat-num">1</span><span class="stat-label">Real Account</span></div>
    <div class="stat"><span class="stat-num" id="rcnt"><?= $totalReports ?></span><span class="stat-label">Reports Filed</span></div>
    <div class="stat"><span class="stat-num">0</span><span class="stat-label">Fakes Tolerated</span></div>
  </div>
  <div class="card">
    <div class="card-title">&#10003; The Only Real Account</div>
    <div class="verified-box">
      <div class="verified-left">
        <div class="verified-avatar">&#9889;</div>
        <div class="verified-name">@Hiruzick<span>&#10003; VERIFIED &mdash; REAL IDENTITY</span></div>
      </div>
      <a class="tg-link" href="https://t.me/Hiruzick" target="_blank" rel="noopener">&#10148;&nbsp;Open on Telegram</a>
    </div>
    <p class="verified-note">This is the <strong>only legitimate Hiruzick account</strong>. Anyone else claiming to be Hiruzick is a <b>scam</b>. Block and report immediately.</p>
  </div>
  <div class="card">
    <div class="card-title">&#128683; Known Fake &amp; Banned Accounts</div>
    <div class="fake-list">
      <div class="fake-item"><span class="fake-icon">&#128683;</span><span class="fake-name">&#x29C;&#x26A;&#x280;&#x1D1C;&#x1D22;&#x26A;&#x1D04;&#x1D0B; &nbsp;&#xFF5C;&nbsp; &#x15E2; &nbsp;&#9834;</span><span class="fake-lbl">FAKE &mdash; BANNED</span></div>
      <div class="fake-item"><span class="fake-icon">&#128683;</span><span class="fake-name">Unicode / special character lookalikes of Hiruzick</span><span class="fake-lbl">FAKE &mdash; BANNED</span></div>
      <div class="fake-item"><span class="fake-icon">&#128683;</span><span class="fake-name">Hirushan_D &nbsp;/&nbsp; HirushanLk &nbsp;/&nbsp; any variation</span><span class="fake-lbl">FAKE &mdash; BANNED</span></div>
    </div>
  </div>
  <div class="card">
    <div class="card-title">Protected Keywords</div>
    <div class="ids">
      <?php foreach (PROTECTED_NAMES as $name): ?>
      <div class="id-tag"><?= htmlspecialchars($name) ?></div>
      <?php endforeach; ?>
    </div>
    <div class="alert-box">&#9888;&nbsp; <strong>Zero tolerance.</strong> Any username using these keywords or Unicode lookalikes will be reported to Telegram and permanently banned.</div>
  </div>
  <div class="card">
    <div class="card-title">Scan a Username</div>
    <div class="input-row">
      <input id="u" type="text" placeholder="@username or t.me/username" autocomplete="off" spellcheck="false" />
      <button class="btn btn-blue" onclick="check()">Scan</button>
    </div>
    <div id="check-result" class="result"></div>
  </div>
  <div class="card">
    <div class="card-title">Report a Fake Account</div>
    <div class="form-stack">
      <input id="r-user" type="text" placeholder="Fake account username" autocomplete="off" />
      <input id="r-link" type="text" placeholder="t.me/link (optional)" autocomplete="off" />
      <textarea id="r-desc" placeholder="Describe how this account impersonates @Hirushan_D or @HirushanLk..."></textarea>
      <div><button class="btn btn-red" onclick="submitReport()">Submit Report</button></div>
    </div>
    <div id="report-result" class="result"></div>
    <div id="copy-box" style="display:none;margin-top:14px;">
      <div style="font-family:var(--mono);font-size:10px;letter-spacing:2px;color:var(--sub);text-transform:uppercase;margin-bottom:8px;">&#128203; Copy &amp; Send to @SpamBot</div>
      <textarea id="copy-text" rows="5" style="font-size:12px;color:var(--sub);background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:10px 14px;width:100%;resize:none;font-family:var(--mono);outline:none;" readonly></textarea>
      <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap;">
        <button class="btn btn-blue" id="copy-btn" onclick="copyMsg()">Copy Message</button>
        <a class="tg-link" href="https://t.me/SpamBot" target="_blank" rel="noopener">&#10148;&nbsp;Open @SpamBot</a>
      </div>
    </div>
    <div class="report-hint">Submitting opens <a href="https://t.me/SpamBot" target="_blank">@SpamBot</a> automatically. Copy the message and send it to complete the report.</div>
  </div>
  <div class="card">
    <div class="card-header">
      <div class="card-title">&#128220; Reported Accounts</div>
      <div style="display:flex;align-items:center;gap:8px;">
        <span class="total-badge" id="rep-badge"><?= $totalReports ?> REPORTED</span>
        <button class="btn btn-ghost" onclick="clearAll()">Clear All</button>
      </div>
    </div>
    <div id="rep-empty" class="rep-empty" <?= $totalReports > 0 ? 'style="display:none"' : '' ?>>No reports yet. Submit a report above.</div>
    <div id="rep-list" class="rep-list">
      <?php foreach (array_reverse($reports) as $r): ?>
      <div class="rep-item" data-id="<?= $r['id'] ?>">
        <div class="rep-avatar">&#128683;</div>
        <div class="rep-info">
          <div class="rep-username">@<?= htmlspecialchars($r['fake_username']) ?></div>
          <div class="rep-desc"><?= htmlspecialchars($r['description']) ?></div>
          <?php if ($r['telegram_link']): ?><div class="rep-desc"><a href="<?= htmlspecialchars($r['telegram_link']) ?>" target="_blank" style="color:var(--blue);text-decoration:none;font-family:var(--mono);font-size:10px;"><?= htmlspecialchars($r['telegram_link']) ?></a></div><?php endif; ?>
        </div>
        <div class="rep-meta">
          <span class="rep-status <?= $r['status'] ?>" data-id="<?= $r['id'] ?>"><?= $r['status'] === 'pending' ? '⏳ PENDING' : ($r['status'] === 'reported' ? '⚡ REPORTED' : '🚫 BANNED') ?></span>
          <span class="rep-time"><?= date('M d, H:i', strtotime($r['reported_at'])) ?></span>
          <button class="rep-remove" data-id="<?= $r['id'] ?>">remove</button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="foot">
    <span class="foot-brand">Hiruzick<span> Guard</span></span>
    <span class="foot-text">&copy; <?= $year ?> &mdash; Impersonation violates Telegram's ToS</span>
  </div>
</div>
<script>
var KEYWORDS = ['hirushan_d','hirushanlk'];
var REAL = 'hiruzick';
var API_BASE = ''; // same file — uses ?api=xxx

function esc(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')}

function extractUsername(val){
  val=val.trim();
  var m=val.match(/(?:https?:\/\/)?(?:t\.me|telegram\.me)\/([A-Za-z0-9_]+)/i);
  if(m) return m[1];
  return val.replace(/^@/,'');
}

function check(){
  var raw=document.getElementById('u').value.trim();
  var res=document.getElementById('check-result');
  if(!raw) return;
  var u=extractUsername(raw);
  res.style.display='block';

  fetch('?api=check&username='+encodeURIComponent(u))
    .then(function(r){return r.json();})
    .then(function(d){
      if(d.is_real){
        res.className='result verified';
        res.innerHTML='\u2705 VERIFIED REAL ACCOUNT<br>@'+esc(d.username)+' is the one and only legitimate account.';
      } else if(d.is_infringement){
        res.className='result danger';
        res.innerHTML='\u26D4 FAKE DETECTED<br>@'+esc(d.username)+' matches protected keyword "'+esc(d.matched_keyword.toUpperCase())+'".<br>Report this account below.';
      } else {
        res.className='result safe';
        res.textContent='\u2713 Clear \u2014 @'+d.username+' \u2014 no match found.';
      }
    })
    .catch(function(){
      // Fallback: client-side check
      if(u.toLowerCase()===REAL){res.className='result verified';res.innerHTML='\u2705 VERIFIED REAL ACCOUNT';return;}
      var l=u.toLowerCase().replace(/[^a-z0-9_]/g,'');
      var match=null;
      for(var i=0;i<KEYWORDS.length;i++) if(l.indexOf(KEYWORDS[i])!==-1){match=KEYWORDS[i];break;}
      if(match){res.className='result danger';res.innerHTML='\u26D4 FAKE DETECTED \u2014 @'+esc(u)+' matches "'+match.toUpperCase()+'"';}
      else{res.className='result safe';res.textContent='\u2713 Clear \u2014 @'+u;}
    });
}

document.getElementById('u').addEventListener('keydown',function(e){if(e.key==='Enter')check();});

function submitReport(){
  var u=document.getElementById('r-user').value.trim();
  var l=document.getElementById('r-link').value.trim();
  var d=document.getElementById('r-desc').value.trim();
  var res=document.getElementById('report-result');
  if(!u||!d){res.style.display='block';res.className='result err';res.textContent='Please fill in the username and description.';return;}

  fetch('?api=report',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({fake_username:u.replace(/^@/,''),telegram_link:l,description:d})
  })
  .then(function(r){return r.json();})
  .then(function(data){
    if(data.success){
      res.style.display='block';res.className='result ok';
      res.innerHTML='\u2705 Report #'+data.id+' logged. Opening @SpamBot \u2014 copy the message and send it.';
      var msg='Fake account impersonating @Hirushan_D / @HirushanLk.\n\nFake username: @'+u.replace(/^@/,'')+'\n'+(l?'Profile: '+l+'\n':'')+'Details: '+d+'\n\nReported via Hiruzick Guard';
      document.getElementById('copy-text').value=msg;
      document.getElementById('copy-box').style.display='block';
      // Update counter
      var el=document.getElementById('rcnt'); el.textContent=parseInt(el.textContent||'0')+1;
      var badge=document.getElementById('rep-badge'); badge.textContent=el.textContent+' REPORTED';
      setTimeout(function(){window.open('https://t.me/SpamBot','_blank');},600);
      document.getElementById('r-user').value='';
      document.getElementById('r-link').value='';
      document.getElementById('r-desc').value='';
      // Reload to show new report in list
      setTimeout(function(){location.reload();},1500);
    }
  })
  .catch(function(){res.style.display='block';res.className='result err';res.textContent='Error. Please try again.';});
}

function copyMsg(){
  var t=document.getElementById('copy-text');t.select();t.setSelectionRange(0,99999);document.execCommand('copy');
  var btn=document.getElementById('copy-btn');btn.textContent='Copied!';
  setTimeout(function(){btn.textContent='Copy Message';},2000);
}

// Status cycle via PATCH API
document.querySelectorAll('.rep-status').forEach(function(el){
  el.addEventListener('click',function(){
    var id=parseInt(this.getAttribute('data-id'));
    var s=['pending','reported','banned'];
    var labels={'pending':'⏳ PENDING','reported':'⚡ REPORTED','banned':'🚫 BANNED'};
    var cur=s.indexOf(this.className.replace('rep-status ','').trim());
    var next=s[(cur+1)%s.length];
    var btn=this;
    fetch('?api=status',{method:'PATCH',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:id,status:next})})
      .then(function(r){return r.json();})
      .then(function(d){if(d.success){btn.className='rep-status '+next;btn.innerHTML=labels[next];}});
  });
});

// Remove buttons
document.querySelectorAll('.rep-remove').forEach(function(btn){
  btn.addEventListener('click',function(){
    var id=parseInt(this.getAttribute('data-id'));
    var item=this.closest('.rep-item');
    // PHP doesn't have a delete API — just hide it visually and reload
    item.style.opacity='0.3';
    setTimeout(function(){item.remove();},300);
  });
});

function clearAll(){
  if(!confirm('Clear all reports?')) return;
  document.getElementById('rep-list').innerHTML='';
  document.getElementById('rep-empty').style.display='block';
}
</script>
</body>
</html>
