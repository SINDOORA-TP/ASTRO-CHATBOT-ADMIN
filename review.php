<?php
/**
 * review.php — Astrologer Admin: Booking Details, Payment Verification, Report Editing & PDF Export
 */
require_once __DIR__ . '/auth.php';
require_once file_exists(__DIR__ . '/config.php') ? __DIR__ . '/config.php' : __DIR__ . '/../api/config.php';

$pdo = getDbConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: dashboard.php');
    exit;
}

$msg = '';
$msgType = 'info';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE bookings SET status = 'approved', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$id]);
        $stmt2 = $pdo->prepare("UPDATE payments SET status = 'approved', reviewed_at = CURRENT_TIMESTAMP WHERE booking_id = ?");
        $stmt2->execute([$id]);
        $msg = 'Booking and payment approved successfully!';
        $msgType = 'success';
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("UPDATE bookings SET status = 'rejected', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$id]);
        $stmt2 = $pdo->prepare("UPDATE payments SET status = 'rejected', reviewed_at = CURRENT_TIMESTAMP WHERE booking_id = ?");
        $stmt2->execute([$id]);
        // Release slot lock so slot can be booked again if rejected
        $stmt3 = $pdo->prepare("DELETE FROM slot_locks WHERE booking_id = ?");
        $stmt3->execute([$id]);
        $msg = 'Booking rejected and slot released.';
        $msgType = 'warning';
    } elseif ($action === 'save_report') {
        $reportText = trim($_POST['report_text'] ?? '');
        $stmt = $pdo->prepare("UPDATE bookings SET report_text = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$reportText, $id]);
        $msg = 'Astrological report saved successfully!';
        $msgType = 'success';
    }
}

// Fetch booking with payment details
$stmt = $pdo->prepare("
    SELECT b.*, p.screenshot_path, p.amount, p.upi_ref, p.status AS payment_status, p.uploaded_at
    FROM bookings b
    LEFT JOIN payments p ON b.id = p.booking_id
    WHERE b.id = ?
");
$stmt->execute([$id]);
$booking = $stmt->fetch();

if (!$booking) {
    header('Location: dashboard.php');
    exit;
}

$chartData = !empty($booking['chart_json']) ? json_decode($booking['chart_json'], true) : null;

// Handle PDF Export Action: render print view directly
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    ?>
    <!DOCTYPE html>
    <html lang="ta">
    <head>
        <meta charset="UTF-8">
        <title>ஜாதக அறிக்கை - #<?= htmlspecialchars((string)$booking['id']) ?> - <?= htmlspecialchars($booking['name']) ?></title>
        <style>
            body { font-family: 'Noto Sans Tamil', 'Mukta Malar', 'Segoe UI', Arial, sans-serif; padding: 30px; color: #111; line-height: 1.6; }
            .header { text-align: center; border-bottom: 2px solid #6b21a8; padding-bottom: 15px; margin-bottom: 25px; }
            .header h1 { color: #581c87; margin: 0; font-size: 24px; }
            .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 25px; }
            .box { border: 1px solid #ddd; padding: 12px; border-radius: 6px; background: #fafafa; }
            .box strong { color: #581c87; }
            table { width: 100%; border-collapse: collapse; margin-top: 15px; }
            th, td { border: 1px solid #ccc; padding: 8px 10px; font-size: 13px; text-align: left; }
            th { background: #f3e8ff; color: #4c1d95; }
            .report-content { background: #fff; border: 1px solid #6b21a8; padding: 20px; border-radius: 6px; margin-top: 20px; white-space: pre-wrap; font-size: 14px; }
            .disclaimer { margin-top: 30px; padding: 12px; border-left: 4px solid #f59e0b; background: #fffbeb; font-size: 12px; color: #78350f; }
            @media print {
                .no-print { display: none; }
                body { padding: 0; }
            }
        </style>
    </head>
    <body>
        <div class="no-print" style="margin-bottom: 20px; text-align: right;">
            <button onclick="window.print()" style="padding: 10px 20px; background: #6b21a8; color: #fff; border: none; border-radius: 5px; cursor: pointer; font-size: 14px;">🖨️ PDF ஆக சேமி / Print PDF</button>
            <button onclick="window.close()" style="padding: 10px 15px; margin-left: 10px; background: #666; color: #fff; border: none; border-radius: 5px; cursor: pointer;">மூடுக</button>
        </div>

        <div class="header">
            <h1>⭐ ஜோதிட வழிகாட்டல் அறிக்கை (Astrology Guidance Report) ⭐</h1>
            <p>பதிவு எண்: #<?= htmlspecialchars((string)$booking['id']) ?> | தேதி: <?= htmlspecialchars($booking['slot_date']) ?></p>
        </div>

        <div class="meta-grid">
            <div class="box">
                <strong>வாடிக்கையாளர் விவரங்கள்:</strong><br>
                பெயர்: <?= htmlspecialchars($booking['name']) ?><br>
                தொலைபேசி: <?= htmlspecialchars($booking['phone']) ?><br>
                மின்னஞ்சல்: <?= htmlspecialchars($booking['email'] ?: 'இல்லை') ?>
            </div>
            <div class="box">
                <strong>ஜனன விவரங்கள்:</strong><br>
                பிறந்த தேதி: <?= htmlspecialchars($booking['dob']) ?><br>
                பிறந்த நேரம்: <?= htmlspecialchars($booking['tob']) ?><br>
                பிறந்த இடம்: <?= htmlspecialchars($booking['pob']) ?>
            </div>
        </div>

        <?php if ($chartData): ?>
        <div class="box" style="margin-bottom: 20px;">
            <strong>முக்கிய ஜோதிட அமைப்புகள்:</strong>
            <table>
                <tr>
                    <th>லக்னம் (Lagna)</th>
                    <td><?= htmlspecialchars($chartData['lagna']['sign'] ?? '-') ?> (<?= htmlspecialchars($chartData['lagna']['degree'] ?? '') ?>)</td>
                    <th>ராசி (Rasi)</th>
                    <td><?= htmlspecialchars($chartData['rasi']['sign'] ?? '-') ?></td>
                </tr>
                <tr>
                    <th>நட்சத்திரம் (Star)</th>
                    <td><?= htmlspecialchars($chartData['lagna']['nakshatra'] ?? $chartData['rasi']['nakshatra'] ?? '-') ?></td>
                    <th>நடப்பு தசை (Current Dasha)</th>
                    <td><?= htmlspecialchars($chartData['dasha']['current']['name'] ?? '-') ?> (<?= htmlspecialchars($chartData['dasha']['current']['end'] ?? '') ?> வரை)</td>
                </tr>
            </table>
        </div>
        <?php endif; ?>

        <h3>ஜோதிடரின் விரிவான ஆலோசனை அறிக்கை:</h3>
        <div class="report-content">
<?= htmlspecialchars($booking['report_text'] ?: "ஜோதிடரின் அறிக்கை இன்னும் பதிவு செய்யப்படவில்லை.") ?>
        </div>

        <div class="disclaimer">
            <strong>பொறுப்புத்துறப்பு (Disclaimer):</strong> இவ்வறிக்கை ஜோதிட கணிதங்கள் மற்றும் வேத சாஸ்திர அமைப்புகளின் அடிப்படையிலான வழிகாட்டுதல் மட்டுமே (Guidance Only). எந்தவொரு மருத்துவ அல்லது சட்ட முடிவுகளுக்கும் இது மாற்றாகாது.
        </div>

        <script>
            window.addEventListener('load', () => {
                setTimeout(() => window.print(), 500);
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}

$csrf_token = csrf_token();
?>
<!DOCTYPE html>
<html lang="ta">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking #<?= htmlspecialchars((string)$booking['id']) ?> - Astrologer Workspace</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+Tamil:wght@400;500;600;700&display=swap">
    <style>
        :root {
            --primary: #6b21a8;
            --primary-light: #7e22ce;
            --dark-bg: #1a0533;
            --card-bg: #ffffff;
            --text-dark: #1e1b4b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --success: #16a34a;
            --warning: #d97706;
            --danger: #dc2626;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', 'Noto Sans Tamil', -apple-system, sans-serif;
            background-color: #f8fafc;
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .navbar {
            background-color: var(--dark-bg);
            color: #fff;
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .navbar a { color: #f8fafc; text-decoration: none; font-size: 0.9rem; font-weight: 500; margin-left: 1rem; }
        .navbar a:hover { color: #facc15; }
        .container {
            max-width: 1200px;
            margin: 1.5rem auto;
            padding: 0 1rem;
            width: 100%;
        }
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
        }
        .alert-success { background: #dcfce7; color: #15803d; border-left: 4px solid var(--success); }
        .alert-warning { background: #fef3c7; color: #b45309; border-left: 4px solid var(--warning); }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        @media (max-width: 768px) {
            .grid-2 { grid-template-columns: 1fr; }
        }
        .card {
            background: #fff;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .card h2 {
            font-size: 1.15rem;
            color: var(--primary);
            margin-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .badge {
            display: inline-block;
            padding: 0.25rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-pending { background: #fef3c7; color: #b45309; }
        .badge-approved { background: #dcfce7; color: #15803d; }
        .badge-rejected { background: #fee2e2; color: #b91c1c; }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px dashed #f1f5f9;
            font-size: 0.9rem;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: var(--text-muted); font-weight: 500; }
        .info-val { font-weight: 600; text-align: right; }
        .slip-img {
            max-width: 100%;
            height: auto;
            max-height: 350px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            display: block;
            margin: 1rem auto;
            object-fit: contain;
            background: #f1f5f9;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.6rem 1.2rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-success { background: var(--success); color: #fff; }
        .btn-success:hover { background: #15803d; }
        .btn-danger { background: var(--danger); color: #fff; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-light); }
        .btn-secondary { background: #64748b; color: #fff; }
        .btn-group {
            display: flex;
            gap: 0.75rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        textarea.report-area {
            width: 100%;
            min-height: 220px;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-family: inherit;
            font-size: 0.95rem;
            line-height: 1.5;
            resize: vertical;
        }
        .planets-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            margin-top: 0.5rem;
        }
        .planets-table th, .planets-table td {
            border: 1px solid var(--border-color);
            padding: 0.4rem 0.6rem;
            text-align: left;
        }
        .planets-table th { background: #f8fafc; color: var(--text-muted); }
    </style>
</head>
<body>

    <nav class="navbar">
        <div style="font-weight: 700; font-size: 1.1rem;">
            ⭐ Astrologer Admin Workspace
        </div>
        <div>
            <a href="dashboard.php">← Bookings Dashboard</a>
            <a href="review.php?id=<?= $id ?>&export=pdf" target="_blank" class="btn btn-primary" style="padding: 0.3rem 0.8rem; font-size: 0.8rem;">📄 Export PDF</a>
            <a href="logout.php" style="color: #f87171;">Logout</a>
        </div>
    </nav>

    <div class="container">
        <?php if ($msg): ?>
            <div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <div class="grid-2">
            <!-- Left Column: Birth & Booking Details -->
            <div class="card">
                <h2>
                    <span>📋 Booking & Birth Details</span>
                    <span class="badge badge-<?= htmlspecialchars($booking['status']) ?>"><?= htmlspecialchars($booking['status']) ?></span>
                </h2>

                <div class="info-row">
                    <span class="info-label">Customer Name:</span>
                    <span class="info-val"><?= htmlspecialchars($booking['name']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone:</span>
                    <span class="info-val"><?= htmlspecialchars($booking['phone']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-val"><?= htmlspecialchars($booking['email'] ?: 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date of Birth:</span>
                    <span class="info-val"><?= htmlspecialchars($booking['dob']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Time of Birth:</span>
                    <span class="info-val"><?= htmlspecialchars($booking['tob']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Place of Birth:</span>
                    <span class="info-val"><?= htmlspecialchars($booking['pob']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Consultation Slot:</span>
                    <span class="info-val" style="color: var(--primary);">
                        📅 <?= htmlspecialchars($booking['slot_date']) ?> at ⏰ <?= htmlspecialchars(substr($booking['slot_time'], 0, 5)) ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Customer Query:</span>
                    <span class="info-val"><?= htmlspecialchars($booking['question'] ?: 'General consultation') ?></span>
                </div>

                <?php if ($chartData): ?>
                <h3 style="margin-top: 1.2rem; font-size: 0.95rem; color: var(--primary);">🪐 Computed Chart Overview</h3>
                <div class="info-row">
                    <span class="info-label">Lagna (Ascendant):</span>
                    <span class="info-val"><?= htmlspecialchars($chartData['lagna']['sign'] ?? '-') ?> (<?= htmlspecialchars($chartData['lagna']['degree'] ?? '') ?>)</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Rasi (Moon Sign):</span>
                    <span class="info-val"><?= htmlspecialchars($chartData['rasi']['sign'] ?? '-') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Current Dasha:</span>
                    <span class="info-val"><?= htmlspecialchars($chartData['dasha']['current']['name'] ?? '-') ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Column: Payment Verification -->
            <div class="card">
                <h2>
                    <span>💳 Payment Slip & Approval</span>
                    <span class="badge badge-<?= htmlspecialchars($booking['payment_status'] ?? 'pending') ?>">
                        <?= htmlspecialchars($booking['payment_status'] ?? 'pending') ?>
                    </span>
                </h2>

                <div class="info-row">
                    <span class="info-label">Amount:</span>
                    <span class="info-val">₹<?= htmlspecialchars(number_format((float)($booking['amount'] ?? 500), 2)) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">UPI Reference:</span>
                    <span class="info-val"><?= htmlspecialchars($booking['upi_ref'] ?: 'Not provided') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Uploaded At:</span>
                    <span class="info-val"><?= htmlspecialchars($booking['uploaded_at'] ?? 'Pending upload') ?></span>
                </div>

                <?php if (!empty($booking['screenshot_path'])): 
                    $slipSrc = file_exists(__DIR__ . '/uploads/payments/' . $booking['screenshot_path']) 
                        ? 'uploads/payments/' . htmlspecialchars($booking['screenshot_path'])
                        : '../uploads/payments/' . htmlspecialchars($booking['screenshot_path']);
                ?>
                    <p style="margin-top: 0.75rem; font-size: 0.85rem; color: var(--text-muted);">Payment Screenshot:</p>
                    <a href="<?= $slipSrc ?>" target="_blank">
                        <img class="slip-img" src="<?= $slipSrc ?>" alt="Payment Slip">
                    </a>
                <?php else: ?>
                    <div style="padding: 2rem; text-align: center; color: var(--text-muted); background: #f8fafc; border-radius: 6px; margin: 1rem 0;">
                        📷 No screenshot uploaded yet
                    </div>
                <?php endif; ?>

                <div class="btn-group">
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="action" value="approve">
                        <button type="submit" class="btn btn-success" onclick="return confirm('Approve this payment and confirm the booking?')">
                            ✓ Approve Payment
                        </button>
                    </form>

                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="action" value="reject">
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Reject this booking and release the time slot?')">
                            ✕ Reject Booking
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Full Width: Astrological Report Editor -->
        <div class="card" style="margin-bottom: 2rem;">
            <h2>
                <span>✍️ Astrologer Report Editor</span>
                <a href="review.php?id=<?= $id ?>&export=pdf" target="_blank" class="btn btn-primary" style="padding: 0.3rem 0.8rem; font-size: 0.8rem;">
                    🖨️ Open Print / PDF View
                </a>
            </h2>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="save_report">
                
                <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.5rem;">
                    ஜாதகருக்கு அனுப்ப வேண்டிய ஜோதிட அறிக்கையை இங்கே தட்டச்சு செய்து சேமிக்கவும். பின்னர் PDF ஆக பதிவிறக்கம் செய்து வாடிக்கையாளருக்கு அனுப்பலாம்:
                </p>

                <textarea name="report_text" class="report-area" placeholder="உங்கள் ஜோதிட விளக்கம், தசா-புக்தி பலன்கள், பரிகாரங்கள் மற்றும் நல்வழிகாட்டுதலை இங்கே எழுதவும்..."><?= htmlspecialchars($booking['report_text'] ?? '') ?></textarea>

                <div class="btn-group" style="margin-top: 1rem;">
                    <button type="submit" class="btn btn-primary">💾 Save Report</button>
                </div>
            </form>
        </div>

    </div>

</body>
</html>

