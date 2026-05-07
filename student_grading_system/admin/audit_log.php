<?php
// admin/audit_log.php
$pageTitle = 'Audit Log';
require_once '../includes/header.php';
require_once '../includes/functions.php';

$conn = db();

// Get filter parameters
$action_filter = isset($_GET['action']) ? sanitizeInput($_GET['action']) : '';
$user_filter = isset($_GET['user']) ? sanitizeInput($_GET['user']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query
$where = "WHERE 1=1";
$params = [];
$types = "";

if ($action_filter) {
    $where .= " AND a.action LIKE ?";
    $params[] = "%$action_filter%";
    $types .= "s";
}

if ($user_filter) {
    $where .= " AND (u.full_name LIKE ? OR u.username LIKE ?)";
    $params[] = "%$user_filter%";
    $params[] = "%$user_filter%";
    $types .= "ss";
}

if ($date_from) {
    $where .= " AND DATE(a.created_at) >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if ($date_to) {
    $where .= " AND DATE(a.created_at) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

// Get audit logs
$sql = "SELECT a.*, u.full_name, u.username, u.role 
        FROM audit_log a
        LEFT JOIN users u ON a.username = u.username
        $where
        ORDER BY a.created_at DESC
        LIMIT 500";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$logs = $stmt->get_result();

// Get unique actions for filter
$actions = $conn->query("SELECT DISTINCT action FROM audit_log ORDER BY action");
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><i class="fas fa-history me-2"></i> Audit Log</h1>
        <div>
            <button class="btn btn-success" onclick="exportToCSV()">
                <i class="fas fa-file-excel me-2"></i> Export
            </button>
            <button class="btn btn-secondary" onclick="location.reload()">
                <i class="fas fa-sync-alt me-2"></i> Refresh
            </button>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Action</label>
                    <select name="action" class="form-select">
                        <option value="">All Actions</option>
                        <?php while ($action = $actions->fetch_assoc()): ?>
                            <option value="<?php echo $action['action']; ?>" <?php echo $action_filter == $action['action'] ? 'selected' : ''; ?>>
                                <?php echo $action['action']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">User</label>
                    <input type="text" name="user" class="form-control" placeholder="Search by user..." value="<?php echo htmlspecialchars($user_filter); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100 me-2">
                        <i class="fas fa-search me-1"></i> Filter
                    </button>
                    <a href="audit_log.php" class="btn btn-secondary w-100">
                        <i class="fas fa-undo me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Audit Log Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="auditTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Role</th>
                            <th>Action</th>
                            <th>Table</th>
                            <th>Record ID</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($logs->num_rows > 0): ?>
                            <?php while ($log = $logs->fetch_assoc()): 
                                $badgeClass = match($log['action']) {
                                    'LOGIN' => 'success',
                                    'LOGOUT' => 'info',
                                    'INSERT', 'ADD_STUDENT', 'ADD_TEACHER' => 'primary',
                                    'UPDATE', 'EDIT' => 'warning',
                                    'DELETE' => 'danger',
                                    default => 'secondary'
                                };
                            ?>
                                <tr>
                                    <td><small><?php echo formatDateTime($log['created_at']); ?></small></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($log['full_name'] ?? $log['username'] ?? 'System'); ?></strong>
                                    </td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($log['role'] ?? 'system'); ?></span></td>
                                    <td>
                                        <span class="badge bg-<?php echo $badgeClass; ?>">
                                            <?php echo htmlspecialchars($log['action']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['table_name'] ?? '-'); ?></td>
                                    <td><?php echo $log['record_id'] ?? '-'; ?></td>
                                    <td><code><small><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></small></code></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <i class="fas fa-history fa-3x text-muted mb-3 d-block"></i>
                                    No audit logs found
                                 </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- System Info -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <i class="fas fa-info-circle me-2"></i> System Information
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <small class="text-muted">PHP Version</small>
                            <p class="mb-0"><?php echo phpversion(); ?></p>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">MySQL Version</small>
                            <p class="mb-0"><?php echo $conn->server_info; ?></p>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">Server Time</small>
                            <p class="mb-0"><?php echo date('Y-m-d H:i:s'); ?></p>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">Total Logs</small>
                            <p class="mb-0"><?php echo number_format($logs->num_rows); ?> records</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#auditTable').DataTable({
        pageLength: 50,
        responsive: true,
        order: [[0, 'desc']],
        language: {
            search: "Search:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries"
        }
    });
});

function exportToCSV() {
    const table = document.getElementById('auditTable');
    const rows = table.querySelectorAll('tr');
    const csv = [];
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const rowData = Array.from(cols).map(col => col.innerText);
        csv.push(rowData.join(','));
    });
    
    const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'audit_log_export.csv';
    link.click();
    URL.revokeObjectURL(link.href);
}
</script>

<?php require_once '../includes/footer.php'; ?>