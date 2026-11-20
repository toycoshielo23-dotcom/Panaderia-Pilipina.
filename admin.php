<?php
require __DIR__ . '/includes/bootstrap.php';
require_role('admin');

sync_monthly_bills();
$rooms = load_json('rooms');
$tenants = load_json('tenants');
$bills = load_json('bills');
$tenantAccountIds = [];
foreach (load_json('users') as $user) {
    $accountTenantId = (string)($user['tenant_id'] ?? '');
    if (($user['role'] ?? '') === 'tenant' && $accountTenantId !== '') {
        $tenantAccountIds[$accountTenantId] = true;
    }
}
$announcements = load_json('announcements');
$stats = summary_stats();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add-room') {
        $roomNumber = trim((string)($_POST['number'] ?? ''));
        $ratePerBed = (float)($_POST['rate_per_bed'] ?? 0);
        $beds = filter_var($_POST['beds'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($roomNumber !== '' && $ratePerBed >= 0 && $beds !== false) {
            try {
                $imagePath = store_room_image($_FILES['room_image'] ?? []);
            } catch (RuntimeException $error) {
                $_SESSION['admin_error'] = $error->getMessage();
                redirect('admin.php?view=rooms');
            }
            $rooms[] = [
                'id' => 'R-' . strtoupper(bin2hex(random_bytes(3))),
                'number' => $roomNumber,
                'type' => 'Room',
                'beds' => $beds,
                'rate_per_bed' => $ratePerBed,
                'rate' => $ratePerBed * $beds,
                'status' => trim((string)($_POST['status'] ?? 'Available')),
                'description' => trim((string)($_POST['description'] ?? 'New room added.')),
                'image' => $imagePath,
            ];
            save_json('rooms', $rooms);
        } else {
            $_SESSION['admin_error'] = 'Suriin ang room number, bilang ng kama, at presyo kada kama.';
        }
    }

    if ($action === 'update-room') {
        $roomId = trim((string)($_POST['room_id'] ?? ''));
        $roomNumber = trim((string)($_POST['number'] ?? ''));
        $ratePerBedInput = trim((string)($_POST['rate_per_bed'] ?? ''));
        $ratePerBed = is_numeric($ratePerBedInput) ? (float)$ratePerBedInput : -1;
        $beds = filter_var($_POST['beds'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $status = trim((string)($_POST['status'] ?? ''));
        $allowedStatuses = ['Available', 'Occupied', 'Maintenance'];
        $roomIndex = null;

        foreach ($rooms as $index => $room) {
            if (($room['id'] ?? null) === $roomId) {
                $roomIndex = $index;
                break;
            }
        }
        $hasRoomTenants = false;
        foreach ($tenants as $tenant) {
            $tenantId = (string)($tenant['id'] ?? '');
            if (($tenant['room_id'] ?? '') === $roomId && isset($tenantAccountIds[$tenantId])) {
                $hasRoomTenants = true;
                break;
            }
        }

        if ($roomIndex === null) {
            $_SESSION['admin_error'] = 'Hindi mahanap ang room na ie-edit.';
        } elseif ($roomNumber === '' || $ratePerBed < 0 || $beds === false || !in_array($status, $allowedStatuses, true)) {
            $_SESSION['admin_error'] = 'Suriin ang room number, bilang ng kama, presyo kada kama, at status.';
        } elseif ($status === 'Available' && $hasRoomTenants) {
            $_SESSION['admin_error'] = 'May tenant pang naka-assign sa room na ito kaya hindi ito puwedeng gawing available.';
        } else {
            try {
                $imagePath = store_room_image($_FILES['room_image'] ?? []);
            } catch (RuntimeException $error) {
                $_SESSION['admin_error'] = $error->getMessage();
                redirect('admin.php?view=rooms');
            }

            $rooms[$roomIndex]['number'] = $roomNumber;
            $rooms[$roomIndex]['beds'] = $beds;
            $rooms[$roomIndex]['rate_per_bed'] = $ratePerBed;
            $rooms[$roomIndex]['rate'] = $ratePerBed * $beds;
            $rooms[$roomIndex]['status'] = $status;
            $rooms[$roomIndex]['description'] = trim((string)($_POST['description'] ?? ''));
            if ($imagePath !== '') {
                $rooms[$roomIndex]['image'] = $imagePath;
            }
            save_json('rooms', $rooms);

            $roomTenantIds = [];
            foreach ($tenants as $tenant) {
                $tenantId = (string)($tenant['id'] ?? '');
                if (($tenant['room_id'] ?? '') === $roomId && isset($tenantAccountIds[$tenantId])) {
                    $roomTenantIds[$tenantId] = true;
                }
            }
            $billsChanged = false;
            foreach ($bills as &$bill) {
                $tenantId = (string)($bill['tenant_id'] ?? '');
                if (isset($roomTenantIds[$tenantId]) && ($bill['status'] ?? '') === 'Unpaid') {
                    $bill['amount'] = $ratePerBed;
                    $billsChanged = true;
                }
            }
            unset($bill);
            if ($billsChanged) {
                save_json('bills', $bills);
            }
        }
    }

    if ($action === 'delete-tenant-account') {
        $tenantId = trim((string)($_POST['tenant_id'] ?? ''));
        $tenantRoomId = '';
        foreach ($tenants as $tenant) {
            if (($tenant['id'] ?? '') === $tenantId) {
                $tenantRoomId = (string)($tenant['room_id'] ?? '');
                break;
            }
        }
        $users = load_json('users');
        $remainingUsers = [];
        $accountDeleted = false;

        foreach ($users as $user) {
            if (is_array($user)
                && ($user['role'] ?? '') === 'tenant'
                && ($user['tenant_id'] ?? '') === $tenantId) {
                $accountDeleted = true;
                continue;
            }
            $remainingUsers[] = $user;
        }

        if ($tenantId === '' || !$accountDeleted) {
            $_SESSION['admin_error'] = 'Walang tenant login account na ma-delete.';
        } else {
            save_json('users', $remainingUsers);
            $remainingBills = array_values(array_filter(
                $bills,
                static fn (array $bill): bool => (string)($bill['tenant_id'] ?? '') !== $tenantId
            ));
            save_json('bills', $remainingBills);

            $hasRemainingTenants = false;
            foreach ($tenants as $tenant) {
                $otherTenantId = (string)($tenant['id'] ?? '');
                if ($otherTenantId !== $tenantId
                    && ($tenant['room_id'] ?? '') === $tenantRoomId
                    && isset($tenantAccountIds[$otherTenantId])) {
                    $hasRemainingTenants = true;
                    break;
                }
            }
            if ($tenantRoomId !== '' && !$hasRemainingTenants) {
                foreach ($rooms as &$room) {
                    if (($room['id'] ?? '') === $tenantRoomId && ($room['status'] ?? '') === 'Occupied') {
                        $room['status'] = 'Available';
                        save_json('rooms', $rooms);
                        break;
                    }
                }
                unset($room);
            }
        }
    }

    if ($action === 'add-tenant') {
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $contact = trim((string)($_POST['contact'] ?? ''));
        $emergency = trim((string)($_POST['emergency_contact'] ?? ''));
        $roomId = trim((string)($_POST['room_id'] ?? ''));
        $moveIn = trim((string)($_POST['move_in_date'] ?? date('Y-m-d')));
        $room = null;
        foreach ($rooms as $roomOption) {
            if (($roomOption['id'] ?? '') === $roomId) {
                $room = $roomOption;
                break;
            }
        }

        if ($fullName !== ''
            && preg_match('/^[0-9]{11}$/', $contact) === 1
            && preg_match('/^[0-9]{11}$/', $emergency) === 1
            && $room !== null
            && ($room['status'] ?? 'Available') !== 'Maintenance') {
            $tenantId = 'T-' . date('ymdHis');
            $tenant = [
                'id' => $tenantId,
                'full_name' => $fullName,
                'contact' => $contact,
                'emergency_contact' => $emergency,
                'move_in_date' => $moveIn,
                'room_id' => $roomId,
                'status' => 'Active',
            ];
            $tenants[] = $tenant;
            save_json('tenants', $tenants);

            $username = strtolower(str_replace(' ', '', $fullName));
            $users = load_json('users');
            $users[] = [
                'id' => 'U-' . date('ymdHis'),
                'username' => $username,
                'password_hash' => password_hash('tenant123', PASSWORD_DEFAULT),
                'role' => 'tenant',
                'name' => $fullName,
                'tenant_id' => $tenantId,
            ];
            save_json('users', $users);

            if ($room) {
                foreach ($rooms as &$item) {
                    if (($item['id'] ?? null) === $roomId) {
                        $item['status'] = 'Occupied';
                        break;
                    }
                }
                save_json('rooms', $rooms);
            }

            $bills[] = [
                'id' => 'B-' . date('ymdHis'),
                'tenant_id' => $tenantId,
                'month' => date('Y-m'),
                'amount' => $room ? room_rate_per_bed($room) : 0,
                'status' => 'Unpaid',
                'due_date' => date('Y-m-15'),
                'paid_date' => null,
                'receipt_number' => null,
            ];
            save_json('bills', $bills);
        } else {
            $_SESSION['admin_error'] = $fullName === ''
                ? 'Ilagay ang buong pangalan ng tenant.'
                : (preg_match('/^[0-9]{11}$/', $contact) !== 1 || preg_match('/^[0-9]{11}$/', $emergency) !== 1
                    ? 'Dapat eksaktong 11 digit ang Contact Number at Emergency Contact.'
                    : ($room === null ? 'Pumili ng valid na room.' : 'Hindi puwedeng magdagdag ng tenant sa room na maintenance.'));
        }
    }

    if ($action === 'add-announcement') {
        $title = trim((string)($_POST['title'] ?? ''));
        $message = trim((string)($_POST['message'] ?? ''));
        if ($title !== '' && $message !== '') {
            array_unshift($announcements, [
                'id' => 'A-' . date('ymdHis') . '-' . strtoupper(bin2hex(random_bytes(3))),
                'title' => $title,
                'message' => $message,
                'posted_at' => date('Y-m-d H:i:s'),
            ]);
            save_json('announcements', $announcements);
        }
    }

    if ($action === 'delete-announcement') {
        $announcementId = trim((string)($_POST['announcement_id'] ?? ''));
        $remainingAnnouncements = array_values(array_filter(
            $announcements,
            static fn ($announcement) => !is_array($announcement)
                || (string)($announcement['id'] ?? '') !== $announcementId
        ));
        if ($announcementId === '' || count($remainingAnnouncements) === count($announcements)) {
            $_SESSION['admin_error'] = 'Hindi makita ang announcement na gusto mong i-delete.';
        } else {
            $announcements = $remainingAnnouncements;
            save_json('announcements', $announcements);
        }
    }

    if ($action === 'mark-bill-paid') {
        $billId = (string)($_POST['bill_id'] ?? '');
        foreach ($bills as &$bill) {
            if (($bill['id'] ?? null) === $billId) {
                $bill['status'] = 'Paid';
                $bill['paid_date'] = date('Y-m-d');
                $bill['receipt_number'] = 'RCP-' . date('YmdHis');
                break;
            }
        }
        save_json('bills', $bills);
    }

    $viewAfterAction = match ($action) {
        'add-room', 'update-room' => 'rooms',
        'add-tenant' => 'tenants',
        'delete-tenant-account' => 'tenants',
        'add-announcement', 'delete-announcement' => 'announcements',
        'mark-bill-paid' => 'billing',
        default => 'dashboard',
    };
    redirect('admin.php?view=' . $viewAfterAction);
}

$rooms = load_json('rooms');
$tenants = load_json('tenants');
$bills = load_json('bills');
$announcements = load_json('announcements');
$stats = summary_stats();
$incomeHistory = monthly_income_history();
$maxMonthlyIncome = max($incomeHistory ?: [0]);
$adminError = $_SESSION['admin_error'] ?? null;
unset($_SESSION['admin_error']);

$allowedViews = ['dashboard', 'rooms', 'tenants', 'billing', 'announcements'];
$view = strtolower(trim((string)($_GET['view'] ?? 'dashboard')));
if (!in_array($view, $allowedViews, true)) {
    $view = 'dashboard';
}

$roomsById = [];
$tenantsByRoom = [];
$tenantsById = [];
foreach ($rooms as $room) {
    $roomId = (string)($room['id'] ?? '');
    $roomsById[$roomId] = $room;
    $tenantsByRoom[$roomId] = [];
}
$unassignedTenants = [];
foreach ($tenants as $tenant) {
    $tenantId = (string)($tenant['id'] ?? '');
    if ($tenantId === '' || !isset($tenantAccountIds[$tenantId])) {
        continue;
    }
    $tenantsById[$tenantId] = $tenant;
    $roomId = (string)($tenant['room_id'] ?? '');
    if ($roomId !== '' && isset($roomsById[$roomId])) {
        $tenantsByRoom[$roomId][] = $tenant;
    } else {
        $unassignedTenants[] = $tenant;
    }
}
$billsByRoom = [];
$unassignedBills = [];
foreach ($bills as $bill) {
    $tenantId = (string)($bill['tenant_id'] ?? '');
    $tenant = $tenantsById[$tenantId] ?? null;
    $roomId = (string)($tenant['room_id'] ?? '');
    if ($roomId !== '' && isset($roomsById[$roomId])) {
        $billsByRoom[$roomId][] = $bill;
    } else {
        $unassignedBills[] = $bill;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ucfirst($view) ?> | Golden Haven Admin</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="dashboard-page admin-dashboard">
    <aside class="admin-sidebar">
        <a class="admin-brand" href="admin.php">
            <strong>Golden Haven</strong>
            <span>Admin Panel</span>
        </a>

        <nav class="admin-nav" aria-label="Admin navigation">
            <p class="admin-nav-label">Overview</p>
            <a class="<?= $view === 'dashboard' ? 'active' : '' ?>" href="admin.php?view=dashboard">
                <span class="nav-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="8" height="8" rx="1"></rect>
                        <rect x="13" y="3" width="8" height="5" rx="1"></rect>
                        <rect x="13" y="10" width="8" height="11" rx="1"></rect>
                        <rect x="3" y="13" width="8" height="8" rx="1"></rect>
                    </svg>
                </span>
                Dashboard
            </a>

            <a class="<?= $view === 'rooms' ? 'active' : '' ?>" href="admin.php?view=rooms">
                <span class="nav-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m3 10 9-7 9 7"></path>
                        <path d="M5 9v12h14V9"></path>
                        <path d="M9 21v-7h6v7"></path>
                    </svg>
                </span>
                Room Management
            </a>
            <a class="<?= $view === 'tenants' ? 'active' : '' ?>" href="admin.php?view=tenants">
                <span class="nav-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="8" r="3.5"></circle>
                        <path d="M5 21c.5-4 3-6 7-6s6.5 2 7 6"></path>
                    </svg>
                </span>
                Tenants
            </a>
            <a class="<?= $view === 'billing' ? 'active' : '' ?>" href="admin.php?view=billing">
                <span class="nav-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"></path>
                        <path d="M9 8h6M9 12h6M9 16h3"></path>
                    </svg>
                </span>
                Billing
            </a>
            <a class="<?= $view === 'announcements' ? 'active' : '' ?>" href="admin.php?view=announcements">
                <span class="nav-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path>
                        <path d="M10 21h4"></path>
                    </svg>
                </span>
                Announcements
            </a>
        </nav>

        <div class="sidebar-actions">
            <a class="btn sidebar-home" href="index.php">View Website</a>
            <a class="sidebar-logout" href="logout.php">Logout</a>
        </div>
    </aside>

    <main class="admin-main">
        <header class="admin-topbar">
            <div>
                <p class="admin-breadcrumb">Admin / <strong><?= htmlspecialchars(ucfirst($view)) ?></strong></p>
                <h1>Good day, Administrator</h1>
            </div>
            <a class="admin-profile" href="logout.php" title="Logout">
                <span>AD</span>
            </a>
        </header>

        <div class="dashboard-content">
        <?php if ($view === 'dashboard'): ?>
        <section class="admin-welcome">
            <div>
                <p class="eyebrow">Your boarding house space</p>
                <h2>Manage rooms, tenants, and payments.</h2>
                <p>View today's occupancy and collection status in one place.</p>
            </div>
            <a class="btn btn-primary" href="#room-management">Manage rooms</a>
        </section>

        <section class="summary-grid">
            <div class="summary-card">
                <p>Total Rooms</p>
                <h3><?= htmlspecialchars((string) $stats['total_rooms']) ?></h3>
            </div>
            <div class="summary-card">
                <p>Occupied Rooms</p>
                <h3><?= htmlspecialchars((string) $stats['occupied_rooms']) ?></h3>
            </div>
            <div class="summary-card">
                <p>Available Rooms</p>
                <h3><?= htmlspecialchars((string) $stats['available_rooms']) ?></h3>
            </div>
            <div class="summary-card">
                <p>Rooms in Maintenance</p>
                <h3><?= htmlspecialchars((string) $stats['maintenance_rooms']) ?></h3>
            </div>
            <div class="summary-card">
                <p>Tenants</p>
                <h3><?= htmlspecialchars((string) $stats['tenants']) ?></h3>
            </div>
            <div class="summary-card">
                <p>Occupancy Rate</p>
                <h3><?= htmlspecialchars((string) $stats['occupancy']) ?>%</h3>
            </div>
            <div class="summary-card">
                <p>Collected This Month</p>
                <h3><?= format_money((float)$stats['monthly_income']) ?></h3>
            </div>
            <div class="summary-card">
                <p>Unpaid Balance</p>
                <h3><?= format_money((float)$stats['unpaid_balance']) ?></h3>
            </div>
            <div class="summary-card">
                <p>Overdue Bills</p>
                <h3><?= htmlspecialchars((string)$stats['overdue_bills']) ?></h3>
            </div>
        </section>

        <?php if ($adminError): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($adminError) ?></div>
        <?php endif; ?>

        <section class="panel full-width analytics-panel">
            <div class="analytics-heading">
                <div>
                    <p class="eyebrow">Collections</p>
                    <h2>Income in the last six months</h2>
                </div>
                <strong>All-time collected: <?= format_money((float)$stats['total_income']) ?></strong>
            </div>
            <div class="income-chart" aria-label="Monthly paid rent income">
                <?php foreach ($incomeHistory as $month => $income): ?>
                    <?php $barHeight = $maxMonthlyIncome > 0 ? max(4, (int)round(($income / $maxMonthlyIncome) * 100)) : 4; ?>
                    <div class="income-month">
                        <div class="income-bar-track">
                            <span class="income-bar" style="height: <?= $barHeight ?>%" title="<?= htmlspecialchars(format_money((float)$income)) ?>"></span>
                        </div>
                        <strong><?= format_money((float)$income) ?></strong>
                        <small><?= htmlspecialchars(date('M Y', strtotime($month . '-01'))) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($view === 'rooms'): ?>
        <section class="panel full-width management-page-panel">
            <?php if ($adminError): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($adminError) ?></div>
            <?php endif; ?>
            <div class="management-section-header">
                <div>
                    <h2>Room Management</h2>
                </div>
                <details class="add-item-control">
                    <summary><span aria-hidden="true">+</span> Add Room</summary>
                    <div class="add-item-popover">
                        <form method="post" enctype="multipart/form-data" class="stack-form">
                            <input type="hidden" name="action" value="add-room">
                            <input type="text" name="number" placeholder="Room number" required>
                            <label class="field-label">Number of beds
                                <input type="number" name="beds" min="1" value="1" required>
                            </label>
                            <label class="field-label">Price per bed / month
                                <input type="number" name="rate_per_bed" min="0" step="0.01" placeholder="e.g. 1500" required>
                            </label>
                            <select name="status">
                                <option value="Available">Available</option>
                                <option value="Occupied">Occupied</option>
                                <option value="Maintenance">Maintenance</option>
                            </select>
                            <textarea name="description" placeholder="Room description"></textarea>
                            <label class="field-label">Room photo
                                <input type="file" name="room_image" accept="image/jpeg,image/png,image/webp">
                            </label>
                            <button type="submit" class="btn btn-primary">Save Room</button>
                        </form>
                    </div>
                </details>
            </div>
        </section>
        <section class="panel full-width" id="room-management">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Room</th>
                            <th>Beds</th>
                            <th>Photo</th>
                            <th>Price / Bed / Month</th>
                            <th>Total Room Price / Month</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rooms as $roomIndex => $room): ?>
                            <tr>
                                <td>Room <?= $roomIndex + 1 ?></td>
                                <td><?= htmlspecialchars((string)($room['beds'] ?? 0)) ?> bed<?= (int)($room['beds'] ?? 0) === 1 ? '' : 's' ?></td>
                                <td>
                                    <?php if (!empty($room['image'])): ?>
                                        <img class="room-thumbnail" src="<?= htmlspecialchars((string)$room['image']) ?>" alt="Room <?= htmlspecialchars((string)($room['number'] ?? '')) ?>">
                                    <?php else: ?>
                                        <span class="muted">No photo</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= format_money(room_rate_per_bed($room)) ?></td>
                                <td><?= format_money(room_monthly_total($room)) ?></td>
                                <td><span class="badge badge-<?= htmlspecialchars(get_room_status_badge((string)($room['status'] ?? 'Available'))) ?>"><?= htmlspecialchars((string)($room['status'] ?? 'Available')) ?></span></td>
                                <td>
                                    <details class="room-edit-details">
                                        <summary>Edit Room</summary>
                                        <form method="post" enctype="multipart/form-data" class="stack-form room-edit-form">
                                            <input type="hidden" name="action" value="update-room">
                                            <input type="hidden" name="room_id" value="<?= htmlspecialchars((string)($room['id'] ?? '')) ?>">
                                            <label class="field-label">Room number
                                                <input type="text" name="number" value="<?= htmlspecialchars((string)($room['number'] ?? '')) ?>" required>
                                            </label>
                                            <label class="field-label">Number of beds
                                                <input type="number" name="beds" min="1" value="<?= htmlspecialchars((string)($room['beds'] ?? 1)) ?>" required>
                                            </label>
                                            <label class="field-label">Price per bed / month
                                                <input type="number" name="rate_per_bed" min="0" step="0.01" value="<?= htmlspecialchars((string)room_rate_per_bed($room)) ?>" required>
                                            </label>
                                            <label class="field-label">Status
                                                <select name="status">
                                                    <option value="Available" <?= (($room['status'] ?? 'Available') === 'Available') ? 'selected' : '' ?>>Available</option>
                                                    <option value="Occupied" <?= (($room['status'] ?? 'Available') === 'Occupied') ? 'selected' : '' ?>>Occupied</option>
                                                    <option value="Maintenance" <?= (($room['status'] ?? 'Available') === 'Maintenance') ? 'selected' : '' ?>>Maintenance</option>
                                                </select>
                                            </label>
                                            <label class="field-label">Description
                                                <textarea name="description"><?= htmlspecialchars((string)($room['description'] ?? '')) ?></textarea>
                                            </label>
                                            <?php if (!empty($room['image'])): ?>
                                                <img class="room-thumbnail" src="<?= htmlspecialchars((string)$room['image']) ?>" alt="Current photo of room <?= htmlspecialchars((string)($room['number'] ?? '')) ?>">
                                            <?php endif; ?>
                                            <label class="field-label">Replace room photo (optional)
                                                <input type="file" name="room_image" accept="image/jpeg,image/png,image/webp">
                                            </label>
                                            <button type="submit" class="btn btn-primary">Save Changes</button>
                                        </form>
                                    </details>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php endif; ?>

        <?php if ($view === 'tenants'): ?>
        <section class="panel full-width management-page-panel">
            <?php if ($adminError): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($adminError) ?></div>
            <?php endif; ?>
            <div class="management-section-header">
                <div>
                    <h2>Tenant Records</h2>
                </div>
                <details class="add-item-control">
                    <summary><span aria-hidden="true">+</span> Add Tenant</summary>
                    <div class="add-item-popover">
                        <form method="post" class="stack-form">
                            <input type="hidden" name="action" value="add-tenant">
                            <input type="text" name="full_name" placeholder="Full name" required>
                            <input type="tel" name="contact" placeholder="Contact Number (11 digits)" inputmode="numeric" pattern="[0-9]{11}" minlength="11" maxlength="11" title="Ilagay ang eksaktong 11 digit na contact number." autocomplete="tel" required>
                            <input type="tel" name="emergency_contact" placeholder="Emergency Contact (11 digits)" inputmode="numeric" pattern="[0-9]{11}" minlength="11" maxlength="11" title="Ilagay ang eksaktong 11 digit na emergency contact number." autocomplete="tel" required>
                            <input type="date" name="move_in_date" value="<?= date('Y-m-d') ?>" required>
                            <select name="room_id" required>
                                <?php foreach ($rooms as $roomIndex => $room): ?>
                                    <option value="<?= htmlspecialchars((string)($room['id'] ?? '')) ?>" <?= (($room['status'] ?? 'Available') === 'Maintenance') ? 'disabled' : '' ?>>Room <?= $roomIndex + 1 ?> (<?= htmlspecialchars((string)($room['status'] ?? 'Available')) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary">Register Tenant</button>
                        </form>
                    </div>
                </details>
            </div>
        </section>
        <section class="panel full-width" id="tenant-records">
            <h2>Tenant Records by Room</h2>
            <p class="muted">Bawat room ay nakahiwalay sa ibaba, kasama ang bilang ng tenants at kumpletong tenant records nito.</p>
            <div class="filter-row">
                <label class="field-label">Search tenant
                    <input type="search" id="tenant-search" placeholder="Name, contact, or room number">
                </label>
                <label class="field-label">Status
                    <select id="tenant-status-filter">
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </label>
            </div>
            <div class="room-tenant-list">
                <?php foreach ($rooms as $roomIndex => $room): ?>
                    <?php
                    $roomId = (string)($room['id'] ?? '');
                    $roomTenants = $tenantsByRoom[$roomId] ?? [];
                    ?>
                    <article class="room-tenant-card">
                        <div class="room-tenant-heading">
                            <div>
                                <h3>Room <?= $roomIndex + 1 ?></h3>
                                <p>Total room price: <?= format_money(room_monthly_total($room)) ?> / buwan</p>
                            </div>
                            <div class="room-tenant-count">
                                <strong><?= count($roomTenants) ?> tenant<?= count($roomTenants) === 1 ? '' : 's' ?></strong>
                                <span><?= htmlspecialchars((string)($room['beds'] ?? 0)) ?> bed<?= (int)($room['beds'] ?? 0) === 1 ? '' : 's' ?></span>
                            </div>
                        </div>
                        <?php if ($roomTenants): ?>
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Tenant ID</th>
                                            <th>Full Name</th>
                                            <th>Contact Number</th>
                                            <th>Emergency Contact</th>
                                            <th>Move-in Date</th>
                                            <th>Status</th>
                                            <th>Account</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($roomTenants as $tenant): ?>
                                            <?php
                                            $searchText = strtolower(implode(' ', [
                                                (string)($tenant['id'] ?? ''),
                                                (string)($tenant['full_name'] ?? ''),
                                                (string)($tenant['contact'] ?? ''),
                                                (string)($tenant['emergency_contact'] ?? ''),
                                                (string)($room['number'] ?? ''),
                                            ]));
                                            ?>
                                            <tr class="tenant-row" data-search="<?= htmlspecialchars($searchText) ?>" data-status="<?= htmlspecialchars(strtolower((string)($tenant['status'] ?? 'active'))) ?>">
                                                <td><?= htmlspecialchars((string)($tenant['id'] ?? '')) ?></td>
                                                <td><?= htmlspecialchars((string)($tenant['full_name'] ?? '')) ?></td>
                                                <td><?= htmlspecialchars((string)($tenant['contact'] ?? '')) ?></td>
                                                <td><?= htmlspecialchars((string)($tenant['emergency_contact'] ?? '')) ?></td>
                                                <td><?= format_date((string)($tenant['move_in_date'] ?? '')) ?></td>
                                                <td><?= htmlspecialchars((string)($tenant['status'] ?? 'Active')) ?></td>
                                                <td>
                                                    <form method="post" class="inline-form delete-account-form" data-confirm="Tatanggalin ang login account at bills. Mananatili ang profile pero mawawala ito sa tenant list.">
                                                        <input type="hidden" name="action" value="delete-tenant-account">
                                                        <input type="hidden" name="tenant_id" value="<?= htmlspecialchars((string)($tenant['id'] ?? '')) ?>">
                                                        <button type="submit" class="btn btn-small btn-danger">Delete Account</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="tenant-empty-state" hidden><td colspan="7">Walang tenant na tumutugma sa filter para sa room na ito.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="empty-room-tenants">Wala pang tenant na naka-assign sa room na ito.</p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>

                <?php if ($unassignedTenants): ?>
                    <article class="room-tenant-card unassigned-tenant-card">
                        <div class="room-tenant-heading">
                            <div>
                                <h3>Unassigned tenants</h3>
                                <p>Mga record na walang valid na room assignment.</p>
                            </div>
                            <div class="room-tenant-count">
                                <strong><?= count($unassignedTenants) ?> tenant<?= count($unassignedTenants) === 1 ? '' : 's' ?></strong>
                            </div>
                        </div>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Tenant ID</th>
                                        <th>Full Name</th>
                                        <th>Contact Number</th>
                                        <th>Emergency Contact</th>
                                        <th>Move-in Date</th>
                                        <th>Status</th>
                                        <th>Account</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($unassignedTenants as $tenant): ?>
                                        <?php
                                        $searchText = strtolower(implode(' ', [
                                            (string)($tenant['id'] ?? ''),
                                            (string)($tenant['full_name'] ?? ''),
                                            (string)($tenant['contact'] ?? ''),
                                            (string)($tenant['emergency_contact'] ?? ''),
                                        ]));
                                        ?>
                                        <tr class="tenant-row" data-search="<?= htmlspecialchars($searchText) ?>" data-status="<?= htmlspecialchars(strtolower((string)($tenant['status'] ?? 'active'))) ?>">
                                            <td><?= htmlspecialchars((string)($tenant['id'] ?? '')) ?></td>
                                            <td><?= htmlspecialchars((string)($tenant['full_name'] ?? '')) ?></td>
                                            <td><?= htmlspecialchars((string)($tenant['contact'] ?? '')) ?></td>
                                            <td><?= htmlspecialchars((string)($tenant['emergency_contact'] ?? '')) ?></td>
                                            <td><?= format_date((string)($tenant['move_in_date'] ?? '')) ?></td>
                                            <td><?= htmlspecialchars((string)($tenant['status'] ?? 'Active')) ?></td>
                                            <td>
                                                <form method="post" class="inline-form delete-account-form" data-confirm="Tatanggalin ang login account at bills. Mananatili ang profile pero mawawala ito sa tenant list.">
                                                    <input type="hidden" name="action" value="delete-tenant-account">
                                                    <input type="hidden" name="tenant_id" value="<?= htmlspecialchars((string)($tenant['id'] ?? '')) ?>">
                                                    <button type="submit" class="btn btn-small btn-danger">Delete Account</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="tenant-empty-state" hidden><td colspan="7">Walang tenant na tumutugma sa filter.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </article>
                <?php endif; ?>
            </div>
        </section>

        <?php endif; ?>

        <?php if ($view === 'billing'): ?>
        <section class="panel page-heading-panel">
            <h2>Billing and Payments</h2>
        </section>
        <section class="panel full-width" id="billing">
            <h2>Billing and Payment Tracker</h2>
            <div class="room-tenant-list billing-room-list">
                <?php foreach ($rooms as $roomIndex => $room): ?>
                    <?php
                    $roomId = (string)($room['id'] ?? '');
                    $roomBills = $billsByRoom[$roomId] ?? [];
                    ?>
                    <article class="room-tenant-card">
                        <div class="room-tenant-heading">
                            <div>
                                <h3>Room <?= $roomIndex + 1 ?></h3>
                                <p><?= count($roomBills) ?> billing record<?= count($roomBills) === 1 ? '' : 's' ?></p>
                            </div>
                        </div>
                        <?php if ($roomBills): ?>
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Tenant</th>
                                            <th>Month</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Due Date</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($roomBills as $bill): ?>
                                            <?php $tenant = $tenantsById[(string)($bill['tenant_id'] ?? '')] ?? null; ?>
                                            <tr>
                                                <td><?= htmlspecialchars((string)($tenant['full_name'] ?? 'Unknown')) ?></td>
                                                <td><?= htmlspecialchars((string)($bill['month'] ?? '')) ?></td>
                                                <td><?= format_money((float)($bill['amount'] ?? 0)) ?></td>
                                                <td><span class="badge badge-<?= htmlspecialchars(get_bill_badge((string)($bill['status'] ?? 'Unpaid'))) ?>"><?= htmlspecialchars((string)($bill['status'] ?? 'Unpaid')) ?></span></td>
                                                <td><?= format_date((string)($bill['due_date'] ?? '')) ?></td>
                                                <td>
                                                    <?php if (($bill['status'] ?? 'Unpaid') !== 'Paid'): ?>
                                                        <form method="post" class="inline-form">
                                                            <input type="hidden" name="action" value="mark-bill-paid">
                                                            <input type="hidden" name="bill_id" value="<?= htmlspecialchars((string)($bill['id'] ?? '')) ?>">
                                                            <button type="submit" class="btn btn-small">Mark Paid</button>
                                                        </form>
                                                    <?php else: ?>
                                                        <a href="receipt.php?id=<?= urlencode((string)($bill['id'] ?? '')) ?>" class="btn btn-small">View Receipt</a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="empty-room-tenants">Wala pang billing record para sa room na ito.</p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>

                <?php if ($unassignedBills): ?>
                    <article class="room-tenant-card unassigned-tenant-card">
                        <div class="room-tenant-heading">
                            <div>
                                <h3>Unassigned Billing Records</h3>
                                <p>Mga bill na walang valid na room assignment.</p>
                            </div>
                            <div class="room-tenant-count">
                                <strong><?= count($unassignedBills) ?> record<?= count($unassignedBills) === 1 ? '' : 's' ?></strong>
                            </div>
                        </div>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Tenant</th>
                                        <th>Month</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Due Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($unassignedBills as $bill): ?>
                                        <?php $tenant = $tenantsById[(string)($bill['tenant_id'] ?? '')] ?? null; ?>
                                        <tr>
                                            <td><?= htmlspecialchars((string)($tenant['full_name'] ?? 'Unknown')) ?></td>
                                            <td><?= htmlspecialchars((string)($bill['month'] ?? '')) ?></td>
                                            <td><?= format_money((float)($bill['amount'] ?? 0)) ?></td>
                                            <td><span class="badge badge-<?= htmlspecialchars(get_bill_badge((string)($bill['status'] ?? 'Unpaid'))) ?>"><?= htmlspecialchars((string)($bill['status'] ?? 'Unpaid')) ?></span></td>
                                            <td><?= format_date((string)($bill['due_date'] ?? '')) ?></td>
                                            <td>
                                                <?php if (($bill['status'] ?? 'Unpaid') !== 'Paid'): ?>
                                                    <form method="post" class="inline-form">
                                                        <input type="hidden" name="action" value="mark-bill-paid">
                                                        <input type="hidden" name="bill_id" value="<?= htmlspecialchars((string)($bill['id'] ?? '')) ?>">
                                                        <button type="submit" class="btn btn-small">Mark Paid</button>
                                                    </form>
                                                <?php else: ?>
                                                    <a href="receipt.php?id=<?= urlencode((string)($bill['id'] ?? '')) ?>" class="btn btn-small">View Receipt</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </article>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($view === 'announcements'): ?>
        <section class="panel full-width management-page-panel">
            <?php if ($adminError): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($adminError) ?></div>
            <?php endif; ?>
            <div class="management-section-header">
                <div>
                    <h2>Announcements</h2>
                </div>
                <details class="add-item-control">
                    <summary><span aria-hidden="true">+</span> Post Announcement</summary>
                    <div class="add-item-popover">
                        <form method="post" class="stack-form">
                            <input type="hidden" name="action" value="add-announcement">
                            <input type="text" name="title" placeholder="Announcement title" required>
                            <textarea name="message" placeholder="Announcement details" rows="4" required></textarea>
                            <button type="submit" class="btn btn-primary">Publish</button>
                        </form>
                    </div>
                </details>
            </div>
        </section>
        <section class="panel full-width" id="announcements">
            <h2>Announcements</h2>
            <div class="announcement-list">
                <?php foreach ($announcements as $announcement): ?>
                    <article class="announcement-item">
                        <h3><?= htmlspecialchars((string)($announcement['title'] ?? '')) ?></h3>
                        <p><?= htmlspecialchars((string)($announcement['message'] ?? '')) ?></p>
                        <small><?= format_date((string)($announcement['posted_at'] ?? '')) ?></small>
                        <form method="post" class="inline-form" data-confirm="Sigurado ka bang gusto mong i-delete ang announcement na ito? Mawawala rin ito sa tenant portal at public homepage.">
                            <input type="hidden" name="action" value="delete-announcement">
                            <input type="hidden" name="announcement_id" value="<?= htmlspecialchars((string)($announcement['id'] ?? '')) ?>">
                            <button type="submit" class="btn btn-small btn-danger">Delete</button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
        </div>
    </main>
    <script src="assets/js/app.js"></script>
</body>
</html>
