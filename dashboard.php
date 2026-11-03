<?php
/**
 * dashboard.php — Main Admin Dashboard
 */

require_once __DIR__ . '/auth.php';
require_once file_exists(__DIR__ . '/config.php') ? __DIR__ . '/config.php' : __DIR__ . '/../api/config.php';

// ─── Database connection ─────────────────────────────────────────────────────
try {
    $pdo = getDbConnection();
} catch (Throwable $e) {
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}

// ─── Pagination & Filter ─────────────────────────────────────────────────────
$per_page     = 10;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$offset       = ($current_page - 1) * $per_page;

$allowed_statuses = ['pending', 'approved', 'rejected'];
$filter_status    = $_GET['status'] ?? 'all';
if (!in_array($filter_status, array_merge(['all'], $allowed_statuses), true)) {
    $filter_status = 'all';
}

// ─── Stats ───────────────────────────────────────────────────────────────────
$stats_stmt = $pdo->query("
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0)  AS pending,
        COALESCE(SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END), 0) AS approved,
        COALESCE(SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END), 0) AS rejected
    FROM bookings
");
$stats = $stats_stmt->fetch();

// ─── Bookings Query ──────────────────────────────────────────────────────────
if ($filter_status === 'all') {
    $count_stmt = $pdo->query("SELECT COUNT(*) FROM bookings");
    $total_rows = (int)$count_stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT id, name, dob, slot_date, slot_time, status, created_at
        FROM bookings
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit',  $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,   PDO::PARAM_INT);
    $stmt->execute();
} else {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE status = :s");
    $count_stmt->execute([':s' => $filter_status]);
    $total_rows = (int)$count_stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT id, name, dob, slot_date, slot_time, status, created_at
        FROM bookings
        WHERE status = :status
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':status', $filter_status);
    $stmt->bindValue(':limit',  $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,   PDO::PARAM_INT);
    $stmt->execute();
}

$bookings    = $stmt->fetchAll();
$total_pages = (int)ceil($total_rows / $per_page);

// ─── CSRF token ──────────────────────────────────────────────────────────────
$csrf_token = csrf_token();

// ─── Helper: status badge ────────────────────────────────────────────────────
function status_badge(string $status): string {
    $map = [
        'pending'  => ['🟠', 'badge-pending',  'Pending'],
        'approved' => ['🟢', 'badge-approved', 'Approved'],
        'rejected' => ['🔴', 'badge-rejected', 'Rejected'],
    ];
    [$icon, $cls, $label] = $map[$status] ?? ['⚪', 'badge-unknown', ucfirst($status)];
    return '<span class="badge ' . $cls . '">' . $icon . ' ' . htmlspecialchars($label) . '</span>';
}

// ─── Helper: pagination URL ──────────────────────────────────────────────────
function page_url(int $page, string $status): string {
    $params = ['page' => $page];
    if ($status !== 'all') $params['status'] = $status;
    return 'dashboard.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Astro Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Tamil:wght@400;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --purple-dark:   #1a0a2e;
            --purple-mid:    #2d1457;
            --purple-accent: #7c3aed;
            --purple-light:  #a78bfa;
            --purple-bg:     #f5f0ff;
            --gold:          #f59e0b;
            --white:         #ffffff;
            --gray-50:       #f9fafb;
            --gray-100:      #f3f4f6;
            --gray-200:      #e5e7eb;
            --gray-500:      #6b7280;
            --gray-700:      #374151;
            --green:         #16a34a;
            --orange:        #ea580c;
            --red:           #dc2626;
            --blue:          #2563eb;
            --sidebar-w:     240px;
        }

        body {
            font-family: 'Inter', 'Noto Sans Tamil', sans-serif;
            background: var(--gray-50);
            color: var(--gray-700);
            min-height: 100vh;
            display: flex;
        }

        /* ── Sidebar ── */
        .sidebar {
            width: var(--sidebar-w);
            background: linear-gradient(180deg, var(--purple-dark) 0%, var(--purple-mid) 100%);
            min-height: 100vh;
            position: fixed;
            left: 0; top: 0;
            display: flex;
            flex-direction: column;
            z-index: 100;
            transition: transform 0.3s ease;
        }

        .sidebar-brand {
            padding: 1.5rem 1.25rem;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .sidebar-brand .brand-icon { font-size: 1.75rem; }

        .sidebar-brand .brand-text { color: var(--white); font-weight: 700; font-size: 1rem; line-height: 1.2; }
        .sidebar-brand .brand-sub  { color: var(--purple-light); font-size: 0.72rem; }

        .sidebar-nav { flex: 1; padding: 1rem 0; }

        .nav-label {
            padding: 0.4rem 1.25rem;
            color: rgba(167,139,250,0.6);
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            margin-top: 0.5rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.7rem 1.25rem;
            color: rgba(255,255,255,0.75);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            border-left: 3px solid transparent;
            transition: all 0.15s;
        }

        .nav-link:hover, .nav-link.active {
            color: var(--white);
            background: rgba(124,58,237,0.2);
            border-left-color: var(--purple-light);
        }

        .nav-link .icon { font-size: 1.1rem; width: 1.25rem; text-align: center; }

        .sidebar-footer {
            padding: 1rem 1.25rem;
            border-top: 1px solid rgba(255,255,255,0.08);
        }

        .btn-logout {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            font-size: 0.875rem;
            padding: 0.5rem 0;
            transition: color 0.15s;
        }

        .btn-logout:hover { color: #fca5a5; }

        /* ── Main Content ── */
        .main {
            margin-left: var(--sidebar-w);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* ── Top Bar ── */
        .topbar {
            background: var(--white);
            border-bottom: 1px solid var(--gray-200);
            padding: 0.875rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .topbar-left { display: flex; align-items: center; gap: 1rem; }

        .hamburger {
            display: none;
            background: none;
            border: none;
            font-size: 1.4rem;
            cursor: pointer;
            color: var(--gray-700);
            padding: 0.25rem;
        }

        .topbar h2 { font-size: 1.1rem; font-weight: 600; color: var(--gray-700); }

        .topbar-right { color: var(--gray-500); font-size: 0.85rem; }

        /* ── Page Content ── */
        .content { padding: 1.5rem; flex: 1; }

        /* ── Stats Cards ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: var(--white);
            border-radius: 12px;
            padding: 1.25rem;
            border: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }

        .stat-icon {
            width: 48px; height: 48px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
        }

        .stat-icon.purple { background: #ede9fe; }
        .stat-icon.orange { background: #fff7ed; }
        .stat-icon.green  { background: #f0fdf4; }
        .stat-icon.red    { background: #fef2f2; }

        .stat-info .value { font-size: 1.75rem; font-weight: 700; color: var(--gray-700); line-height: 1; }
        .stat-info .label { font-size: 0.8rem; color: var(--gray-500); margin-top: 0.25rem; }

        /* ── Filter bar ── */
        .filter-bar {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-bottom: 1.25rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .filter-label { font-size: 0.85rem; font-weight: 600; color: var(--gray-500); }

        .filter-btn {
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.82rem;
            font-weight: 500;
            text-decoration: none;
            border: 1px solid var(--gray-200);
            color: var(--gray-700);
            background: var(--gray-50);
            transition: all 0.15s;
        }

        .filter-btn:hover { border-color: var(--purple-accent); color: var(--purple-accent); }

        .filter-btn.active {
            background: var(--purple-accent);
            color: var(--white);
            border-color: var(--purple-accent);
        }

        /* ── Bookings Table Card ── */
        .card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            overflow: hidden;
        }

        .card-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--gray-100);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-header h3 { font-size: 0.95rem; font-weight: 600; }
        .card-header .count { font-size: 0.8rem; color: var(--gray-500); }

        .table-wrapper { overflow-x: auto; -webkit-overflow-scrolling: touch; }

        table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }

        thead th {
            background: var(--gray-50);
            padding: 0.75rem 1rem;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: var(--gray-500);
            border-bottom: 1px solid var(--gray-200);
            white-space: nowrap;
        }

        tbody td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid var(--gray-100);
            vertical-align: middle;
            white-space: nowrap;
        }

        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover { background: var(--purple-bg); }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.25rem 0.65rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-pending  { background: #fff7ed; color: #c2410c; }
        .badge-approved { background: #f0fdf4; color: #15803d; }
        .badge-rejected { background: #fef2f2; color: #b91c1c; }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.35rem 0.85rem;
            border-radius: 7px;
            font-size: 0.8rem;
            font-weight: 500;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: opacity 0.15s, transform 0.1s;
        }

        .btn:active { transform: scale(0.97); }
        .btn-blue { background: var(--blue); color: var(--white); }
        .btn-blue:hover { opacity: 0.85; }

        /* ── Pagination ── */
        .pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            padding: 1rem;
            border-top: 1px solid var(--gray-100);
            flex-wrap: wrap;
        }

        .page-link {
            padding: 0.4rem 0.75rem;
            border-radius: 7px;
            font-size: 0.82rem;
            font-weight: 500;
            text-decoration: none;
            color: var(--gray-700);
            border: 1px solid var(--gray-200);
            transition: all 0.15s;
        }

        .page-link:hover { border-color: var(--purple-accent); color: var(--purple-accent); }
        .page-link.active { background: var(--purple-accent); color: var(--white); border-color: var(--purple-accent); }
        .page-link.disabled { opacity: 0.4; pointer-events: none; }

        /* ── Mobile: card rows ── */
        .mobile-cards { display: none; }

        .booking-card {
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            padding: 1rem;
            background: var(--white);
            margin-bottom: 0.75rem;
        }

        .booking-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.6rem;
        }

        .booking-card-name { font-weight: 600; font-size: 0.95rem; }
        .booking-card-id   { font-size: 0.75rem; color: var(--gray-500); }
        .booking-card-meta { font-size: 0.8rem; color: var(--gray-500); display: flex; flex-direction: column; gap: 0.2rem; margin-bottom: 0.75rem; }
        .booking-card-footer { display: flex; justify-content: space-between; align-items: center; }

        /* ── Mobile sidebar overlay ── */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 90;
        }

        @media (max-width: 900px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .sidebar-overlay.open { display: block; }
            .main { margin-left: 0; }
            .hamburger { display: block; }
            .desktop-table { display: none; }
            .mobile-cards { display: block; }
            .content { padding: 1rem; }
        }

        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .stat-info .value { font-size: 1.4rem; }
        }
    </style>
</head>
<body>

<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- ── Sidebar ──────────────────────────────────────────────────────── -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <span class="brand-icon">🔮</span>
        <div>
            <div class="brand-text">Astro Admin</div>
            <div class="brand-sub">Jothidam Panel</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-label">Main</div>
        <a href="dashboard.php" class="nav-link active">
            <span class="icon">📊</span> Dashboard
        </a>
        <a href="dashboard.php?status=pending" class="nav-link">
            <span class="icon">🕐</span> Pending
        </a>
        <a href="dashboard.php?status=approved" class="nav-link">
            <span class="icon">✅</span> Approved
        </a>
        <a href="dashboard.php?status=rejected" class="nav-link">
            <span class="icon">❌</span> Rejected
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="logout.php" class="btn-logout">
            <span>🚪</span> Sign Out
        </a>
    </div>
</aside>

<!-- ── Main ─────────────────────────────────────────────────────────── -->
<div class="main">
    <!-- Top Bar -->
    <header class="topbar">
        <div class="topbar-left">
            <button class="hamburger" onclick="toggleSidebar()" aria-label="Menu">☰</button>
            <h2>Dashboard</h2>
        </div>
        <div class="topbar-right">
            Welcome, <strong>admin</strong> &nbsp;|&nbsp;
            <a href="logout.php" style="color:var(--red); text-decoration:none; font-weight:500;">Logout</a>
        </div>
    </header>

    <!-- Content -->
    <div class="content">

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon purple">📋</div>
                <div class="stat-info">
                    <div class="value"><?= (int)($stats['total'] ?? 0) ?></div>
                    <div class="label">Total Bookings</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange">🕐</div>
                <div class="stat-info">
                    <div class="value"><?= (int)($stats['pending'] ?? 0) ?></div>
                    <div class="label">Pending Approvals</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">✅</div>
                <div class="stat-info">
                    <div class="value"><?= (int)($stats['approved'] ?? 0) ?></div>
                    <div class="label">Approved</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red">❌</div>
                <div class="stat-info">
                    <div class="value"><?= (int)($stats['rejected'] ?? 0) ?></div>
                    <div class="label">Rejected</div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <span class="filter-label">Filter:</span>
            <?php
            $filters = ['all' => 'All', 'pending' => '🟠 Pending', 'approved' => '🟢 Approved', 'rejected' => '🔴 Rejected'];
            foreach ($filters as $val => $label):
                $active = ($filter_status === $val) ? ' active' : '';
            ?>
            <a href="dashboard.php?status=<?= urlencode($val) ?>" class="filter-btn<?= $active ?>">
                <?= htmlspecialchars($label) ?>
            </a>
            <?php endforeach; ?>
            <span style="margin-left:auto; font-size:0.8rem; color:var(--gray-500);">
                <?= $total_rows ?> booking<?= $total_rows !== 1 ? 's' : '' ?> found
            </span>
        </div>

        <!-- Table Card (Desktop) -->
        <div class="card desktop-table">
            <div class="card-header">
                <h3>All Bookings</h3>
                <span class="count">Page <?= $current_page ?> of <?= max(1, $total_pages) ?></span>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Date of Birth</th>
                            <th>Slot Date</th>
                            <th>Slot Time</th>
                            <th>Booked On</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bookings)): ?>
                        <tr>
                            <td colspan="8" style="text-align:center; padding:2rem; color:var(--gray-500);">
                                No bookings found.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($bookings as $b): ?>
                        <tr>
                            <td><strong>#<?= (int)$b['id'] ?></strong></td>
                            <td><?= htmlspecialchars($b['name']) ?></td>
                            <td><?= htmlspecialchars($b['dob']) ?></td>
                            <td><?= htmlspecialchars($b['slot_date']) ?></td>
                            <td><?= htmlspecialchars($b['slot_time']) ?></td>
                            <td><?= htmlspecialchars(date('d M Y', strtotime($b['created_at']))) ?></td>
                            <td><?= status_badge($b['status']) ?></td>
                            <td>
                                <a href="review.php?id=<?= (int)$b['id'] ?>" class="btn btn-blue">
                                    👁️ View
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <a href="<?= htmlspecialchars(page_url($current_page - 1, $filter_status)) ?>"
                   class="page-link <?= $current_page <= 1 ? 'disabled' : '' ?>">← Prev</a>

                <?php for ($p = max(1, $current_page - 2); $p <= min($total_pages, $current_page + 2); $p++): ?>
                <a href="<?= htmlspecialchars(page_url($p, $filter_status)) ?>"
                   class="page-link <?= $p === $current_page ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>

                <a href="<?= htmlspecialchars(page_url($current_page + 1, $filter_status)) ?>"
                   class="page-link <?= $current_page >= $total_pages ? 'disabled' : '' ?>">Next →</a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Mobile Cards -->
        <div class="mobile-cards">
            <?php if (empty($bookings)): ?>
            <p style="text-align:center; color:var(--gray-500); padding:2rem;">No bookings found.</p>
            <?php else: ?>
            <?php foreach ($bookings as $b): ?>
            <div class="booking-card">
                <div class="booking-card-header">
                    <div>
                        <div class="booking-card-name"><?= htmlspecialchars($b['name']) ?></div>
                        <div class="booking-card-id">#<?= (int)$b['id'] ?></div>
                    </div>
                    <?= status_badge($b['status']) ?>
                </div>
                <div class="booking-card-meta">
                    <span>📅 DOB: <?= htmlspecialchars($b['dob']) ?></span>
                    <span>🗓️ Slot: <?= htmlspecialchars($b['slot_date']) ?> at <?= htmlspecialchars($b['slot_time']) ?></span>
                    <span>🕐 Booked: <?= htmlspecialchars(date('d M Y', strtotime($b['created_at']))) ?></span>
                </div>
                <div class="booking-card-footer">
                    <a href="review.php?id=<?= (int)$b['id'] ?>" class="btn btn-blue">👁️ View Details</a>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Mobile Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <a href="<?= htmlspecialchars(page_url($current_page - 1, $filter_status)) ?>"
                   class="page-link <?= $current_page <= 1 ? 'disabled' : '' ?>">← Prev</a>
                <span style="font-size:0.85rem; color:var(--gray-500);"><?= $current_page ?> / <?= $total_pages ?></span>
                <a href="<?= htmlspecialchars(page_url($current_page + 1, $filter_status)) ?>"
                   class="page-link <?= $current_page >= $total_pages ? 'disabled' : '' ?>">Next →</a>
            </div>
            <?php endif; ?>

            <?php endif; ?>
        </div>

    </div><!-- /.content -->
</div><!-- /.main -->

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('open');
}
</script>
</body>
</html>
