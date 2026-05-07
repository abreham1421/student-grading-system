/* assets/js/main.js — Gada Grading System v3.1
   Sidebar, DataTables, form helpers, score colours.           */
'use strict';

(function () {

    /* ── Sidebar toggle ──────────────────────────── */
    const toggle  = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const content = document.getElementById('mainContent');
    const footer  = document.getElementById('mainFooter');

    function sidebarWidth() {
        return getComputedStyle(document.documentElement)
               .getPropertyValue('--sidebar-w').trim() || '260px';
    }
    function isMobile() { return window.innerWidth < 992; }

    function openSidebar() {
        if (!sidebar) return;
        sidebar.classList.add('open');
        if (overlay) overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    function closeSidebar() {
        if (!sidebar) return;
        sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('show');
        document.body.style.overflow = '';
    }

    if (toggle) {
        toggle.addEventListener('click', function () {
            if (isMobile()) {
                sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
            } else {
                const collapsed = sidebar.classList.toggle('collapsed');
                const ml = collapsed ? '0px' : sidebarWidth();
                if (content) content.style.marginLeft = ml;
                if (footer)  footer.style.marginLeft  = ml;
            }
        });
    }
    if (overlay) overlay.addEventListener('click', closeSidebar);

    document.querySelectorAll('.sidebar .nav-link').forEach(function (link) {
        link.addEventListener('click', function () { if (isMobile()) closeSidebar(); });
    });

    window.addEventListener('resize', function () {
        if (!isMobile()) {
            closeSidebar();
            if (!sidebar.classList.contains('collapsed')) {
                if (content) content.style.marginLeft = sidebarWidth();
                if (footer)  footer.style.marginLeft  = sidebarWidth();
            }
        } else {
            if (content) content.style.marginLeft = '0';
            if (footer)  footer.style.marginLeft  = '0';
        }
    });

    /* ── Score colour coding ─────────────────────── */
    function getScoreColor(val) {
        if (val >= 80) return '#28a745';
        if (val >= 65) return '#ffc107';
        if (val >= 50) return '#17a2b8';
        return '#dc3545';
    }

    function applyScoreColors() {
        document.querySelectorAll('input.grade-input').forEach(function (inp) {
            inp.addEventListener('input', function () {
                const v = parseFloat(this.value);
                this.style.borderLeft = isNaN(v) || this.value === ''
                    ? '' : '4px solid ' + getScoreColor(v);
            });
        });
    }

    /* ── DataTables ──────────────────────────────── */
    function initDataTables() {
        if (typeof $ === 'undefined' || !$.fn.DataTable) return;
        $('.datatable').each(function () {
            if (!$.fn.DataTable.isDataTable(this)) {
                $(this).DataTable({
                    responsive: true,
                    pageLength: 20,
                    language: {
                        search:        '<i class="fas fa-search"></i> _INPUT_',
                        searchPlaceholder: 'Search...',
                        lengthMenu:    'Show _MENU_ entries',
                        paginate: {
                            previous: '<i class="fas fa-chevron-left"></i>',
                            next:     '<i class="fas fa-chevron-right"></i>'
                        }
                    },
                    dom: '<"row mb-3"<"col-sm-6"l><"col-sm-6"f>>rtip'
                });
            }
        });
    }

    /* ── Auto-dismiss alerts ─────────────────────── */
    function autoDismissAlerts() {
        if (typeof $ !== 'undefined') {
            $('.alert:not(.alert-permanent)').delay(5500).fadeOut('slow');
        }
    }

    /* ── Confirm dialogs ─────────────────────────── */
    function bindConfirmDialogs() {
        document.addEventListener('click', function (e) {
            const el = e.target.closest('[data-confirm]');
            if (el && !confirm(el.dataset.confirm || 'Are you sure?')) {
                e.preventDefault();
            }
        });
    }

    /* ── Init ────────────────────────────────────── */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        applyScoreColors();
        bindConfirmDialogs();
        if (typeof $ !== 'undefined') {
            $(document).ready(function () {
                initDataTables();
                autoDismissAlerts();
            });
        }
    }

})();
