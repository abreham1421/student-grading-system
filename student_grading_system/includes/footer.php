</div><!-- /.main-content -->

<footer class="footer" id="mainFooter" style="margin-left:var(--sidebar-w);background:#fff;padding:14px 24px;border-top:1px solid #e9ecef;transition:margin-left .3s ease;">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-12 col-md-6 text-center text-md-start text-muted small mb-1 mb-md-0">
                <i class="fas fa-graduation-cap me-1"></i>
                &copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>
                <span class="d-none d-sm-inline">&nbsp;|&nbsp;Jimma University &mdash; CBTP Phase III</span>
            </div>
            <div class="col-12 col-md-6 text-center text-md-end text-muted small">
                <i class="fas fa-code me-1"></i> Version 3.1
            </div>
        </div>
    </div>
</footer>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>

<script>
(function () {
    'use strict';

    /* ── Sidebar toggle ─────────────────────── */
    const toggle   = document.getElementById('sidebarToggle');
    const sidebar  = document.getElementById('sidebar');
    const overlay  = document.getElementById('sidebarOverlay');
    const content  = document.getElementById('mainContent');
    const footer   = document.getElementById('mainFooter');
    const SIDEBAR_W = getComputedStyle(document.documentElement)
                        .getPropertyValue('--sidebar-w').trim() || '260px';

    function isMobile() { return window.innerWidth < 992; }

    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
        document.body.style.overflow = '';
    }

    if (toggle) {
        toggle.addEventListener('click', () => {
            if (isMobile()) {
                sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
            } else {
                // Desktop: collapse/expand
                const collapsed = sidebar.classList.toggle('collapsed');
                const ml = collapsed ? '0px' : SIDEBAR_W;
                if (content) content.style.marginLeft = ml;
                if (footer)  footer.style.marginLeft  = ml;
            }
        });
    }
    if (overlay) overlay.addEventListener('click', closeSidebar);

    // Close sidebar on mobile when a nav link is clicked
    document.querySelectorAll('.sidebar .nav-link').forEach(link => {
        link.addEventListener('click', () => { if (isMobile()) closeSidebar(); });
    });

    // Adjust on resize
    window.addEventListener('resize', () => {
        if (!isMobile()) {
            closeSidebar();
            if (content) content.style.marginLeft = SIDEBAR_W;
            if (footer)  footer.style.marginLeft  = SIDEBAR_W;
        } else {
            if (content) content.style.marginLeft = '0';
            if (footer)  footer.style.marginLeft  = '0';
        }
    });

    /* ── DataTables ─────────────────────────── */
    $(document).ready(function () {
        // Auto-dismiss alerts
        $('.alert:not(.alert-permanent)').delay(5000).fadeOut('slow');

        // Initialize all datatable elements
        if ($.fn.DataTable) {
            $('.datatable').each(function () {
                if (!$.fn.DataTable.isDataTable(this)) {
                    $(this).DataTable({
                        responsive: true,
                        pageLength: 20,
                        language: {
                            search: '<i class="fas fa-search"></i> _INPUT_',
                            searchPlaceholder: 'Search...',
                            lengthMenu: 'Show _MENU_ entries',
                            paginate: {
                                previous: '<i class="fas fa-chevron-left"></i>',
                                next:     '<i class="fas fa-chevron-right"></i>'
                            }
                        },
                        dom: '<"row mb-3"<"col-sm-6"l><"col-sm-6"f>>rtip',
                    });
                }
            });
        }

        /* ── Confirm dialogs ─────────────────── */
        $(document).on('click', '[data-confirm]', function (e) {
            if (!confirm($(this).data('confirm') || 'Are you sure?')) {
                e.preventDefault();
            }
        });

        /* ── Score colour coding ─────────────── */
        function colorScore(val) {
            if (val >= 80) return '#28a745';
            if (val >= 65) return '#ffc107';
            if (val >= 50) return '#17a2b8';
            return '#dc3545';
        }
        $('input.grade-input').on('input', function () {
            const v = parseFloat($(this).val());
            $(this).css('border-left', isNaN(v) ? '' : '4px solid ' + colorScore(v));
        }).trigger('input');
    });
})();
</script>

<?php if (isset($extraScripts)) echo $extraScripts; ?>
</body>
</html>
