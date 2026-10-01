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
/* ================= MODAL POPUP GENERIK (FINO) ================= */
(function () {
    function openModal(m) { if (m) { m.classList.add('open'); document.body.style.overflow = 'hidden'; } }
    function closeModal(m) { if (m) { m.classList.remove('open'); document.body.style.overflow = ''; } }
    window.snOpenModal = openModal;
    window.snCloseModal = closeModal;
    document.addEventListener('click', function (e) {
        var opener = e.target.closest('[data-snmodal-open]');
        if (opener) { openModal(document.getElementById(opener.getAttribute('data-snmodal-open'))); return; }
        var closer = e.target.closest('[data-snmodal-close]');
        if (closer) { closeModal(closer.closest('.sn-modal')); return; }
        var overlay = e.target.classList && e.target.classList.contains('sn-modal') ? e.target : null;
        if (overlay) closeModal(overlay);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') document.querySelectorAll('.sn-modal.open').forEach(closeModal);
    });
})();

/* ================= SIDEBAR DRAWER (HP, ala WMS) ================= */
(function () {
    var btn = document.getElementById('snMenuBtn');
    var scrim = document.getElementById('snScrim');
    if (!btn) return;
    function setOpen(v) { document.body.classList.toggle('sn-nav-open', v); }
    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        setOpen(!document.body.classList.contains('sn-nav-open'));
    });
    if (scrim) scrim.addEventListener('click', function () { setOpen(false); });
    document.querySelectorAll('.nav-link').forEach(function (a) {
        a.addEventListener('click', function () { setOpen(false); });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') setOpen(false);
    });
})();

/* ================= AUTOCOMPLETE PENCARIAN SANTRI ================= */
(function () {
    var profilBase = <?= json_encode(url('modules/santri/profil.php')) ?>;
    var cariUrl = <?= json_encode(url('modules/santri/cari.php')) ?>;

    function esc(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    /** Pasang sugest live pada satu kotak pencarian (min 2 huruf). */
    function snAutocomplete(wrapId, inputId, boxId) {
        var wrap = document.getElementById(wrapId);
        var input = document.getElementById(inputId);
        var box = document.getElementById(boxId);
        if (!wrap || !input || !box) return;
        var timer = null;

        function closeBox() { box.classList.remove('open'); box.innerHTML = ''; }
        function render(items) {
            if (!items.length) {
                box.innerHTML = '<div class="sr-empty">Santri tidak ditemukan. Tekan Enter untuk pencarian penuh.</div>';
            } else {
                box.innerHTML = items.map(function (it) {
                    return '<a href="' + profilBase + '?id=' + it.id + '" data-no-transition>' +
                        '<div class="sr-nama">' + esc(it.nama) + '</div>' +
                        '<div class="sr-nis">NIS: ' + esc(it.nis) + '</div></a>';
                }).join('');
            }
            box.classList.add('open');
        }
        input.addEventListener('input', function () {
            var q = input.value.trim();
            clearTimeout(timer);
            if (q.length < 2) { closeBox(); return; }
            timer = setTimeout(function () {
                fetch(cariUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.ok ? r.json() : []; })
                    .then(render)
                    .catch(function () { closeBox(); });
            }, 250);
        });
        input.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeBox(); });
        document.addEventListener('click', function (e) { if (!wrap.contains(e.target)) closeBox(); });
    }

    snAutocomplete('snGlobalSearch', 'snGlobalSearchInput', 'snGlobalSearchResults'); // header
})();

/* ============ FILTER LIVE DAFTAR SANTRI (ketik -> tabel menyesuaikan) ============ */
(function () {
    var input = document.getElementById('snPageSearchInput');
    var tbody = document.getElementById('snSantriBody');
    if (!input || !tbody) return;
    var timer = null;
    var emptyRow = null;

    function hasQueryQ() { return new URLSearchParams(location.search).has('q'); }

    /** Teks yg dicari: kolom NIS (0), Nama (1), Alamat (4). */
    function rowText(tr) {
        var c = tr.cells;
        if (!c || c.length < 5) return '';
        return (c[0].textContent + ' ' + c[1].textContent + ' ' + c[4].textContent).toLowerCase();
    }
    function toggleEmpty(show) {
        if (show && !emptyRow) {
            emptyRow = document.createElement('tr');
            emptyRow.innerHTML = '<td colspan="8" class="empty">Tidak ada santri yang cocok dengan pencarian.</td>';
            tbody.appendChild(emptyRow);
        }
        if (emptyRow) emptyRow.style.display = show ? '' : 'none';
    }
    function applyFilter() {
        var q = input.value.trim().toLowerCase();
        if (q === '') {
            if (hasQueryQ()) { location.href = location.pathname; return; } // balik ke daftar penuh
            tbody.querySelectorAll('tr').forEach(function (tr) {
                if (tr !== emptyRow) tr.style.display = '';
            });
            toggleEmpty(false);
            return;
        }
        var visible = 0;
        tbody.querySelectorAll('tr').forEach(function (tr) {
            if (tr === emptyRow) return;
            var hit = rowText(tr).indexOf(q) !== -1;
            tr.style.display = hit ? '' : 'none';
            if (hit) visible++;
        });
        toggleEmpty(visible === 0);
    }
    input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(applyFilter, 150);
    });
})();
</script>
</body>
</html>
