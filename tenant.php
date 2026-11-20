<?php
require __DIR__ . '/includes/bootstrap.php';
require_role('tenant');

sync_monthly_bills();
$user = current_user();
$tenant = tenant_by_user($user);
if (!$tenant) {
    redirect('login.php');
}

$tenantBills = array_values(array_filter(load_json('bills'), static fn ($bill) => ($bill['tenant_id'] ?? '') === $tenant['id']));
$announcements = load_json('announcements');
$room = get_room_by_id((string)($tenant['room_id'] ?? ''));
$roomOrdinal = null;
foreach (load_json('rooms') as $roomIndex => $roomItem) {
    if (($roomItem['id'] ?? null) === ($tenant['room_id'] ?? null)) {
        $roomOrdinal = $roomIndex + 1;
        break;
    }
}
$paidTotal = 0;
foreach ($tenantBills as $bill) {
    if (($bill['status'] ?? 'Unpaid') === 'Paid') {
        $paidTotal += (float)($bill['amount'] ?? 0);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Portal | Golden Haven</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="dashboard-page">
    <header class="dashboard-topbar">
        <div class="container nav-wrap">
            <div>
                <strong>Golden Haven</strong>
                <span>Tenant Portal</span>
            </div>
            <nav>
                <a href="index.php">Home</a>
                <a href="logout.php">Logout</a>
            </nav>
        </div>
    </header>

    <main class="container dashboard-main">
        <section class="tenant-hero panel">
            <h1>Welcome, <?= htmlspecialchars((string)($tenant['full_name'] ?? 'Boarder')) ?>!</h1>
            <div class="tenant-meta">
                <p><strong>Room:</strong> <?= $roomOrdinal !== null ? 'Room ' . $roomOrdinal : 'N/A' ?></p>
                <p><strong>Monthly Rate per Bed:</strong> <?= format_money($room ? room_rate_per_bed($room) : 0) ?></p>
                <p><strong>Paid Total:</strong> <?= format_money((float)$paidTotal) ?></p>
            </div>
        </section>

        <section class="panel full-width">
            <h2>Monthly Bills</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Tenant</th>
                            <th>Contact</th>
                            <th>Room</th>
                            <th>Monthly Billing History</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars((string)($tenant['full_name'] ?? '')) ?></strong>
                                <small class="tenant-id"><?= htmlspecialchars((string)($tenant['id'] ?? '')) ?></small>
                            </td>
                            <td><?= htmlspecialchars((string)($tenant['contact'] ?? '')) ?></td>
                            <td><?= $roomOrdinal !== null ? 'Room ' . $roomOrdinal : 'N/A' ?></td>
                            <td>
                                <?php if ($tenantBills): ?>
                                    <div class="bill-history-list">
                                        <?php foreach ($tenantBills as $bill): ?>
                                            <article class="bill-history-entry">
                                                <div class="bill-history-heading">
                                                    <strong><?= htmlspecialchars((string)($bill['month'] ?? '')) ?></strong>
                                                    <span class="badge badge-<?= htmlspecialchars(get_bill_badge((string)($bill['status'] ?? 'Unpaid'))) ?>"><?= htmlspecialchars((string)($bill['status'] ?? 'Unpaid')) ?></span>
                                                </div>
                                                <div class="bill-history-details">
                                                    <span>Amount: <?= format_money((float)($bill['amount'] ?? 0)) ?></span>
                                                    <span>Due: <?= format_date((string)($bill['due_date'] ?? '')) ?></span>
                                                    <?php if (($bill['status'] ?? 'Unpaid') === 'Paid'): ?>
                                                        <span>Paid: <?= format_date((string)($bill['paid_date'] ?? '')) ?></span>
                                                        <a href="receipt.php?id=<?= urlencode((string)($bill['id'] ?? '')) ?>" class="btn btn-small">Receipt</a>
                                                    <?php endif; ?>
                                                </div>
                                            </article>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="muted">No billing history yet.</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel full-width">
            <h2>Announcements and Notices</h2>
            <div class="announcement-list">
                <?php foreach ($announcements as $announcement): ?>
                    <article class="announcement-item">
                        <h3><?= htmlspecialchars((string)($announcement['title'] ?? '')) ?></h3>
                        <p><?= htmlspecialchars((string)($announcement['message'] ?? '')) ?></p>
                        <small><?= format_date((string)($announcement['posted_at'] ?? '')) ?></small>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
</body>
</html>
