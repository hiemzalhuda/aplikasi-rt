        </div><!-- /.content -->
        <footer class="footer">
            <?= e(defined('APP_FULL') ? APP_FULL : 'Pondok Pesantren') ?> &mdash; Sistem Informasi Santri
        </footer>
    </main>
</div>
<script>
/* ================= JAM DIGITAL (WIB) ================= */
(function () {
    var el = document.getElementById('tbClockTime');
    if (!el) return;
    var tick = function () {
        var now = new Date();
        el.textContent = ('0' + now.getHours()).slice(-2) + ':' + ('0' + now.getMinutes()).slice(-2);
    };
    tick();
    setInterval(tick, 1000);
})();

/* ================= JADWAL SHOLAT (Aladhan, Tangerang — pola WMS) ================= */
(function () {
    var pName = document.getElementById('tbPrayerName');
    var pTime = document.getElementById('tbPrayerTime');
    var pWrap = document.getElementById('tbPrayer');
    if (!pName || !pTime || !pWrap) return;

    var order = ['Fajr', 'Sunrise', 'Dhuhr', 'Asr', 'Maghrib', 'Isha'];
    var labels = { Fajr: 'Subuh', Sunrise: 'Syuruq', Dhuhr: 'Dzuhur', Asr: 'Ashar', Maghrib: 'Maghrib', Isha: 'Isya' };
    var icons = { Fajr: 'fa-cloud-sun', Sunrise: 'fa-sun', Dhuhr: 'fa-sun', Asr: 'fa-cloud-sun', Maghrib: 'fa-moon', Isha: 'fa-star-and-crescent' };
    var lastSched = null;

    function toSec(hhmm) {
        var p = hhmm.split(':');
        return (+p[0]) * 3600 + (+p[1]) * 60;
    }
    function nextKey(sched) {
        var now = new Date();
        var cur = now.getHours() * 3600 + now.getMinutes() * 60 + now.getSeconds();
        for (var i = 0; i < order.length; i++) {
            var k = order[i];
            if (sched[k] && toSec(sched[k]) > cur) return k;
        }
        return 'Fajr'; // semua lewat -> Subuh esok hari
    }
    function updateWidget(sched) {
        var k = nextKey(sched);
        pName.textContent = labels[k];
        pTime.textContent = sched[k];
    }
    async function loadPrayer() {
        try {
            var r = await fetch('https://api.aladhan.com/v1/timingsByCity?city=Tangerang&country=Indonesia&method=20');
            var j = await r.json();
            var timings = j.data.timings || {};
            var sched = {};
            order.forEach(function (k) {
                if (timings[k]) sched[k] = String(timings[k]).split(' ')[0];
            });
            lastSched = sched;
            updateWidget(sched);
            setInterval(function () { updateWidget(sched); }, 30000);
        } catch (e) {
            pName.textContent = 'Jadwal';
            pTime.textContent = '--:--';
        }
    }

    // Popup jadwal lengkap (dibangun via JS seperti WMS)
    var modal = document.createElement('div');
    modal.className = 'prayer-modal';
    modal.id = 'prayerModal';
    modal.innerHTML =
        '<div class="prayer-box" role="dialog" aria-label="Jadwal Sholat">' +
        '<div class="prayer-head"><h3><i class="fas fa-mosque" style="color:var(--brand-active);margin-right:8px;"></i>Jadwal Sholat</h3>' +
        '<button class="prayer-close" id="prayerClose" aria-label="Tutup"><i class="fas fa-times"></i></button></div>' +
        '<div class="prayer-date" id="prayerDate"></div>' +
        '<div id="prayerRows"></div>' +
        '<div class="prayer-foot">Tangerang &amp; sekitarnya &middot; Kemenag</div>' +
        '</div>';
    document.body.appendChild(modal);
    var box = modal.querySelector('.prayer-box');

    function renderRows(sched) {
        var rowsEl = document.getElementById('prayerRows');
        if (!rowsEl || !sched) return;
        var nk = nextKey(sched);
        var html = '';
        order.forEach(function (k) {
            html += '<div class="prayer-row' + (k === nk ? ' next' : '') + '">' +
                '<span class="pn"><i class="fas ' + (icons[k] || 'fa-clock') + '"></i>' + labels[k] + '</span>' +
                '<span class="pt">' + (sched[k] || '--:--') + '</span></div>';
        });
        rowsEl.innerHTML = html;
    }
    function openModal() {
        if (!lastSched) return;
        var d = document.getElementById('prayerDate');
        if (d) d.textContent = new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
        renderRows(lastSched);
        // posisi dekat tombol trigger (pola WMS: getBoundingClientRect, clamp)
        var r = pWrap.getBoundingClientRect();
        var w = 300;
        var left = r.left + r.width / 2 - w / 2;
        left = Math.max(12, Math.min(left, window.innerWidth - w - 12));
        var top = r.bottom + window.scrollY + 10;
        box.style.left = left + 'px';
        box.style.top = top + 'px';
        modal.classList.add('open');
    }
    function closeModal() { modal.classList.remove('open'); }
    pWrap.addEventListener('click', function (e) { e.stopPropagation(); modal.classList.contains('open') ? closeModal() : openModal(); });
    modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
    modal.querySelector('#prayerClose').addEventListener('click', closeModal);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });

    loadPrayer();
})();

/* ================= DARK MODE TOGGLE (pola WMS: body.dark-mode + localStorage) ================= */
(function () {
    var body = document.body;
    var btn = document.getElementById('darkModeToggle');
    var icon = document.getElementById('darkIcon');
    function applyIcon(isDark) {
        if (!icon) return;
        icon.classList.toggle('fa-moon', !isDark);
        icon.classList.toggle('fa-sun', isDark);
    }
    applyIcon(body.classList.contains('dark-mode'));
    if (!btn) return;
    btn.addEventListener('click', function () {
        var toDark = !body.classList.contains('dark-mode');
        var applyNow = function () {
            body.classList.toggle('dark-mode', toDark);
            try { localStorage.setItem('santriDark', toDark ? '1' : '0'); } catch (e) {}
            applyIcon(toDark);
            document.dispatchEvent(new Event('santri:theme'));
        };
        // Ripple reveal dari tombol via View Transition API, fallback langsung
        if (!document.startViewTransition || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            applyNow();
            return;
        }
        var rect = btn.getBoundingClientRect();
        var x = rect.left + rect.width / 2;
        var y = rect.top + rect.height / 2;
        var endR = Math.hypot(Math.max(x, innerWidth - x), Math.max(y, innerHeight - y));
        var tr = document.startViewTransition(applyNow);
        tr.ready.then(function () {
            document.documentElement.animate(
                { clipPath: ['circle(0px at ' + x + 'px ' + y + 'px)', 'circle(' + endR + 'px at ' + x + 'px ' + y + 'px)'] },
                { duration: 450, easing: 'cubic-bezier(.22,1,.36,1)', pseudoElement: '::view-transition-new(root)' }
            );
        });
    });
})();

/* ================= TRANSISI PINDAH HALAMAN (ringan, <300ms) ================= */
(function () {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    var LEAVE_MS = 180;
    document.addEventListener('click', function (e) {
        var a = e.target.closest('a[href]');
        if (!a) return;
        var href = a.getAttribute('href');
        if (!href || href.charAt(0) === '#' || a.target === '_blank' || a.hasAttribute('download') || a.dataset.noTransition !== undefined) return;
        var url;
        try { url = new URL(href, location.href); } catch (err) { return; }
        if (url.origin !== location.origin) return; // eksternal: biarkan normal
        e.preventDefault();
        document.body.classList.add('page-leave');
        setTimeout(function () { location.href = url.href; }, LEAVE_MS);
    });
})();
</script>
</body>
</html>
