<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (current_user()) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/config/koneksi.php';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $row = db_one($koneksi, 'SELECT * FROM users WHERE username = ? AND aktif = 1 LIMIT 1', 's', array($username));
    if ($row && password_verify($password, $row['password_hash'])) {
        login_user($row);
        session_regenerate_id(true); // cegah session fixation (penting utk sesi via URL)
        redirect('index.php');
    }
    $error = 'Username atau password salah.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk &mdash; <?= e(APP_FULL) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="<?= asset_v('assets/style.css') ?>">
</head>
<body>
<script>
/* Ikuti preferensi dark mode yg tersimpan (sebelum render, hindari flash) */
try { if (localStorage.getItem('santriDark') === '1') document.body.classList.add('dark-mode'); } catch (e) {}
</script>
<div class="login-wrap">
    <div class="login-orb o1"></div>
    <div class="login-orb o2"></div>
    <div class="login-orb o3"></div>
    <div class="sn-login" id="snCard">
        <div class="sn-winbar">
            <span class="sn-dot sn-dot-r"></span><span class="sn-dot sn-dot-y"></span><span class="sn-dot sn-dot-g"></span>
            <span class="sn-sysid">SI-RT&nbsp;&middot;&nbsp;LOGIN</span>
            <button type="button" class="sn-dark-btn" id="snDarkToggle" aria-label="Toggle dark mode">
                <i class="fa-solid fa-moon"></i>
            </button>
        </div>
        <div class="sn-logo"><i class="fa-solid fa-house-user"></i></div>
        <h3><?= e(APP_FULL) ?></h3>
        <p class="sn-sub">Sistem Informasi Rukun Tetangga</p>
        <p class="sn-hint" id="snHint">Isi dua kolom di bawah. Saat lengkap, tombolnya berhenti lari.</p>
        <?php if ($error): ?>
            <div class="sn-error"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" action="" id="snForm" autocomplete="off">
            <div class="l-field">
                <label class="l-label" for="snUser">USERNAME</label>
                <div class="l-input-wrap">
                    <input type="text" class="sn-input" id="snUser" name="username" placeholder="cth: admin" required autofocus autocomplete="username">
                    <span class="l-input-group-text"><i class="fa-solid fa-user"></i></span>
                </div>
            </div>
            <div class="l-field">
                <label class="l-label" for="snPass">PASSWORD</label>
                <div class="l-input-wrap">
                    <input type="password" class="sn-input" id="snPass" name="password" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" required autocomplete="current-password">
                    <span class="l-input-group-text"><i class="fa-solid fa-lock"></i></span>
                </div>
            </div>
            <div class="sn-zone" id="snZone">
                <div class="sn-home"></div>
                <button type="submit" class="sn-btn" id="snBtn">
                    <span id="snBtnText"><i class="fa-solid fa-sign-in-alt me-2"></i>MASUK SISTEM</span>
                    <span id="snBtnLoad" style="display:none;"><i class="fa-solid fa-spinner fa-spin me-2"></i>MEMPROSES&hellip;</span>
                </button>
            </div>
        </form>
        <p class="sn-foot"><?= e(strtoupper(rt_label())) ?> &middot; SISTEM INFORMASI RT</p>
    </div>
</div>
<script>
(function () {
    var zone = document.getElementById('snZone');
    var btn = document.getElementById('snBtn');
    var card = document.getElementById('snCard');
    var hint = document.getElementById('snHint');
    var form = document.getElementById('snForm');
    var txt = document.getElementById('snBtnText');
    var load = document.getElementById('snBtnLoad');
    if (!zone || !btn || !form) return;

    var fine = window.matchMedia('(pointer: fine)').matches;
    var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var fields = form.querySelectorAll('input');
    var TIP_OFF = 'Isi dua kolom di bawah. Saat lengkap, tombolnya berhenti lari.';
    var TIP_OK = 'Semua kolom lengkap \u2014 tombolnya berhenti. Klik untuk masuk.';
    var TIP_BLOCK = 'Masih ada kolom kosong!';

    function valid() {
        for (var i = 0; i < fields.length; i++) { if (!fields[i].value.trim()) return false; }
        return true;
    }
    function updateHint(msg) {
        var ok = valid();
        card.classList.toggle('armed', ok);
        if (hint) {
            hint.textContent = msg || (ok ? TIP_OK : TIP_OFF);
            hint.style.color = msg ? '#E0524D' : '';
            if (msg) setTimeout(function () { hint.style.color = ''; }, 1600);
        }
    }
    for (var i = 0; i < fields.length; i++) fields[i].addEventListener('input', function () { updateHint(); });
    updateHint();

    /* Tombol kabur: menghindar dari kursor selama form belum lengkap */
    var tx = 0, ty = 0, cx = 0, cy = 0, raf = null;
    function maxOffsets() {
        var zr = zone.getBoundingClientRect(), br = btn.getBoundingClientRect();
        return {
            mx: Math.max(6, (zr.width - br.width) / 2 - 4),
            my: Math.max(4, (zr.height - br.height) / 2 - 3)
        };
    }
    function tick() {
        cx += (tx - cx) * 0.16; cy += (ty - cy) * 0.16;
        if (Math.abs(tx - cx) < 0.3 && Math.abs(ty - cy) < 0.3) { cx = tx; cy = ty; }
        btn.style.transform = 'translate(calc(-50% + ' + cx.toFixed(2) + 'px), calc(-50% + ' + cy.toFixed(2) + 'px))';
        raf = (Math.abs(tx - cx) > 0.3 || Math.abs(ty - cy) > 0.3) ? requestAnimationFrame(tick) : null;
    }
    function ensureRaf() { if (raf === null) raf = requestAnimationFrame(tick); }

    if (fine && !reduced) {
        zone.addEventListener('mousemove', function (e) {
            if (valid()) { if (tx !== 0 || ty !== 0) { tx = 0; ty = 0; ensureRaf(); } return; }
            var zr = zone.getBoundingClientRect();
            var mx = e.clientX - (zr.left + zr.width / 2);
            var my = e.clientY - (zr.top + zr.height / 2);
            var d = Math.sqrt(mx * mx + my * my) || 1;
            var R = 140;
            if (d < R) {
                var m = maxOffsets();
                var k = 1 - d / R; /* makin dekat kursor, makin jauh lari */
                tx = -(mx / d) * m.mx * k;
                ty = -(my / d) * m.my * k;
            } else { tx = 0; ty = 0; }
            ensureRaf();
        });
        zone.addEventListener('mouseleave', function () { tx = 0; ty = 0; ensureRaf(); });
    }

    /* Cegah submit saat form belum lengkap + animasi shake */
    form.addEventListener('submit', function (e) {
        if (!valid()) {
            e.preventDefault();
            updateHint(TIP_BLOCK);
            card.classList.remove('shake');
            void card.offsetWidth;
            card.classList.add('shake');
            return;
        }
        txt.style.display = 'none';
        load.style.display = 'inline';
        btn.classList.add('loading');
        btn.disabled = true;
    });

    /* Toggle dark mode halaman login */
    var darkBtn = document.getElementById('snDarkToggle');
    var darkIcon = darkBtn ? darkBtn.querySelector('i') : null;
    function paintLoginDark() {
        if (!darkIcon) return;
        var dark = document.body.classList.contains('dark-mode');
        darkIcon.className = dark ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
    }
    if (darkBtn) darkBtn.addEventListener('click', function () {
        var dark = document.body.classList.toggle('dark-mode');
        try { localStorage.setItem('santriDark', dark ? '1' : '0'); } catch (e) {}
        paintLoginDark();
    });
    paintLoginDark();
})();
</script>
</body>
</html>
