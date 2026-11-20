<?php
session_start();
date_default_timezone_set('UTC');

function app_path(string $relative = ''): string
{
    return __DIR__ . '/../' . ltrim($relative, '/');
}

function storage_path(string $relative = ''): string
{
    $storageRoot = getenv('APP_STORAGE_PATH');
    if ($storageRoot === false || trim($storageRoot) === '') {
        return app_path($relative);
    }

    return rtrim($storageRoot, DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . ltrim($relative, '/\\');
}

function ensure_data_files(): void
{
    $base = storage_path('data');
    if (!is_dir($base)) {
        mkdir($base, 0777, true);
    }

    $defaults = [
        'rooms' => [
            [
                'id' => 'R-101',
                'number' => '101',
                'type' => 'Single Bed',
                'beds' => 1,
                'rate' => 4500,
                'status' => 'Available',
                'description' => 'Quiet room near the kitchen and shared bathroom.'
            ],
            [
                'id' => 'R-102',
                'number' => '102',
                'type' => 'Double Bed',
                'beds' => 2,
                'rate' => 7000,
                'status' => 'Occupied',
                'description' => 'Spacious room with a small study desk and cabinet.'
            ],
            [
                'id' => 'R-103',
                'number' => '103',
                'type' => 'Single Bed',
                'beds' => 1,
                'rate' => 5000,
                'status' => 'Maintenance',
                'description' => 'Under repair for a new ceiling fan and repainting.'
            ],
            [
                'id' => 'R-104',
                'number' => '104',
                'type' => 'Single Bed',
                'beds' => 1,
                'rate' => 4800,
                'status' => 'Available',
                'description' => 'Bright room with a cool breeze and windows facing the yard.'
            ],
            [
                'id' => 'R-201',
                'number' => '201',
                'type' => 'Double Bed',
                'beds' => 2,
                'rate' => 8200,
                'status' => 'Occupied',
                'description' => 'Top-floor room with a larger common area and good ventilation.'
            ],
        ],
        'tenants' => [
            [
                'id' => 'T-1001',
                'full_name' => 'Maria Santos',
                'contact' => '09123456789',
                'emergency_contact' => '09234567890',
                'move_in_date' => '2025-02-01',
                'room_id' => 'R-102',
                'status' => 'Active'
            ],
            [
                'id' => 'T-1002',
                'full_name' => 'Juan Dela Cruz',
                'contact' => '09345678901',
                'emergency_contact' => '09456789012',
                'move_in_date' => '2025-03-10',
                'room_id' => 'R-201',
                'status' => 'Active'
            ],
        ],
        'bills' => [
            [
                'id' => 'B-1001',
                'tenant_id' => 'T-1001',
                'month' => '2025-02',
                'amount' => 3500,
                'status' => 'Paid',
                'due_date' => '2025-02-15',
                'paid_date' => '2025-02-12',
                'receipt_number' => 'RCP-2025-02-001'
            ],
            [
                'id' => 'B-1002',
                'tenant_id' => 'T-1001',
                'month' => '2025-03',
                'amount' => 3500,
                'status' => 'Paid',
                'due_date' => '2025-03-15',
                'paid_date' => '2025-03-14',
                'receipt_number' => 'RCP-2025-03-001'
            ],
            [
                'id' => 'B-1003',
                'tenant_id' => 'T-1002',
                'month' => '2025-03',
                'amount' => 8200,
                'status' => 'Unpaid',
                'due_date' => '2025-03-15',
                'paid_date' => null,
                'receipt_number' => null
            ],
        ],
        'announcements' => [
            [
                'id' => 'A-1001',
                'title' => 'Water Interruption Notice',
                'message' => 'Water service may be interrupted tomorrow morning from 8:00 AM to 11:00 AM for maintenance work.',
                'posted_at' => '2025-03-12 08:45:00'
            ],
            [
                'id' => 'A-1002',
                'title' => 'House Rule Reminder',
                'message' => 'Visitors must register at the front desk before entering the boarding house after 8:00 PM.',
                'posted_at' => '2025-03-14 14:20:00'
            ],
        ],
        'users' => [
            [
                'id' => 'U-1001',
                'username' => 'admin',
                'password_hash' => '$2y$12$YE5q4MwGV7FX3L0l3DDxE.eT/Pmpc/shUnp5RWnVu3fJOvfkbeCTm',
                'role' => 'admin',
                'name' => 'Owner'
            ],
            [
                'id' => 'U-1002',
                'username' => 'maria',
                'password_hash' => '$2y$12$EwuWkQB2DV5xRdUoDGXyHOfOE/bQhUU7ZomwIocxh4sUIRL3kqScC',
                'role' => 'tenant',
                'name' => 'Maria Santos',
                'tenant_id' => 'T-1001'
            ],
        ],
    ];

    foreach ($defaults as $file => $data) {
        $path = $base . '/' . $file . '.json';
        if (!file_exists($path)) {
            file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }
}

function load_json(string $file): array
{
    ensure_data_files();
    $path = storage_path('data/' . $file . '.json');
    $content = @file_get_contents($path);
    if ($content === false || trim($content) === '') {
        return [];
    }

    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

function save_json(string $file, array $data): void
{
    $path = storage_path('data/' . $file . '.json');
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_role(string $role): void
{
    $user = current_user();
    if (!$user || $user['role'] !== $role) {
        redirect('login.php');
    }
}

function user_by_username(string $username): ?array
{
    foreach (load_json('users') as $user) {
        if (isset($user['username']) && strtolower($user['username']) === strtolower($username)) {
            return $user;
        }
    }

    return null;
}

function tenant_by_user(array $user): ?array
{
    if (empty($user['tenant_id'])) {
        return null;
    }

    foreach (load_json('tenants') as $tenant) {
        if (($tenant['id'] ?? null) === $user['tenant_id']) {
            return $tenant;
        }
    }

    return null;
}

function get_room_by_id(string $roomId): ?array
{
    foreach (load_json('rooms') as $room) {
        if (($room['id'] ?? null) === $roomId) {
            return $room;
        }
    }

    return null;
}

function room_rate_per_bed(array $room): float
{
    if (isset($room['rate_per_bed'])) {
        return (float)$room['rate_per_bed'];
    }

    $beds = max(1, (int)($room['beds'] ?? 1));
    return (float)($room['rate'] ?? 0) / $beds;
}

function sync_monthly_bills(): void
{
    $tenants = load_json('tenants');
    $rooms = load_json('rooms');
    $bills = load_json('bills');
    $users = load_json('users');
    $month = date('Y-m');

    $roomsById = [];
    foreach ($rooms as $room) {
        $roomsById[(string)($room['id'] ?? '')] = $room;
    }

    $tenantAccountIds = [];
    foreach ($users as $user) {
        $tenantId = (string)($user['tenant_id'] ?? '');
        if (($user['role'] ?? '') === 'tenant' && $tenantId !== '') {
            $tenantAccountIds[$tenantId] = true;
        }
    }

    $billsByTenant = [];
    $unassignedBills = [];
    foreach ($bills as $bill) {
        $tenantId = (string)($bill['tenant_id'] ?? '');
        if ($tenantId === '') {
            $unassignedBills[] = $bill;
            continue;
        }
        $billsByTenant[$tenantId][] = $bill;
    }

    $syncedBills = [];
    $tenantIds = [];
    foreach ($tenants as $tenant) {
        $tenantId = (string)($tenant['id'] ?? '');
        if ($tenantId === '') {
            continue;
        }
        if (!isset($tenantAccountIds[$tenantId])) {
            continue;
        }
        $tenantIds[$tenantId] = true;
        $tenantBills = $billsByTenant[$tenantId] ?? [];
        $currentBill = null;
        $refreshBill = false;

        foreach ($tenantBills as $bill) {
            if (($bill['month'] ?? '') === $month
                && ($currentBill === null || ($bill['status'] ?? '') === 'Paid')) {
                $currentBill = $bill;
            }
        }

        if ($currentBill === null && $tenantBills) {
            usort($tenantBills, static fn (array $left, array $right): int =>
                strcmp((string)($right['month'] ?? ''), (string)($left['month'] ?? ''))
            );
            $currentBill = $tenantBills[0];
            $currentBill['id'] = 'B-' . date('ymdHis') . strtoupper(bin2hex(random_bytes(3)));
            $currentBill['month'] = $month;
            $currentBill['status'] = 'Unpaid';
            $currentBill['due_date'] = $month . '-15';
            $currentBill['paid_date'] = null;
            $currentBill['receipt_number'] = null;
            $refreshBill = true;
        } elseif ($currentBill === null) {
            $currentBill = [
                'id' => 'B-' . date('ymdHis') . strtoupper(bin2hex(random_bytes(3))),
                'tenant_id' => $tenantId,
                'month' => $month,
                'amount' => 0,
                'status' => 'Unpaid',
                'due_date' => $month . '-15',
                'paid_date' => null,
                'receipt_number' => null,
            ];
            $refreshBill = true;
        }

        $currentBill['tenant_id'] = $tenantId;
        $roomId = (string)($tenant['room_id'] ?? '');
        if ($refreshBill && isset($roomsById[$roomId])) {
            $currentBill['amount'] = room_rate_per_bed($roomsById[$roomId]);
        }
        $syncedBills[] = $currentBill;
    }

    foreach ($billsByTenant as $tenantId => $tenantBills) {
        if (isset($tenantIds[$tenantId])) {
            continue;
        }
        if (!isset($tenantAccountIds[$tenantId])) {
            continue;
        }
        usort($tenantBills, static fn (array $left, array $right): int =>
            strcmp((string)($right['month'] ?? ''), (string)($left['month'] ?? ''))
        );
        if ($tenantBills) {
            $syncedBills[] = $tenantBills[0];
        }
    }

    $syncedBills = array_merge($syncedBills, $unassignedBills);
    if ($syncedBills !== $bills) {
        save_json('bills', $syncedBills);
    }
}

function room_monthly_total(array $room): float
{
    if (isset($room['rate_per_bed'])) {
        return room_rate_per_bed($room) * max(1, (int)($room['beds'] ?? 1));
    }

    return (float)($room['rate'] ?? 0);
}

function get_tenant_by_id(string $tenantId): ?array
{
    foreach (load_json('tenants') as $tenant) {
        if (($tenant['id'] ?? null) === $tenantId) {
            return $tenant;
        }
    }

    return null;
}

function summary_stats(): array
{
    $rooms = load_json('rooms');
    $tenants = load_json('tenants');
    $bills = load_json('bills');
    $users = load_json('users');

    $totalRooms = count($rooms);
    $occupiedRooms = 0;
    $availableRooms = 0;
    $maintenanceRooms = 0;
    $totalIncome = 0;
    $monthIncome = 0;
    $unpaidBalance = 0;
    $overdueBills = 0;
    $month = date('Y-m');
    $occupancy = 0;
    $tenantAccountIds = [];
    foreach ($users as $user) {
        $tenantId = (string)($user['tenant_id'] ?? '');
        if (($user['role'] ?? '') === 'tenant' && $tenantId !== '') {
            $tenantAccountIds[$tenantId] = true;
        }
    }
    $tenantIds = [];
    foreach ($tenants as $tenant) {
        $tenantId = (string)($tenant['id'] ?? '');
        if ($tenantId !== '' && isset($tenantAccountIds[$tenantId])) {
            $tenantIds[$tenantId] = true;
        }
    }

    foreach ($rooms as $room) {
        $status = strtolower($room['status'] ?? 'Available');
        if ($status === 'occupied') {
            $occupiedRooms++;
        } elseif ($status === 'available') {
            $availableRooms++;
        } elseif ($status === 'maintenance') {
            $maintenanceRooms++;
        }
    }

    foreach ($bills as $bill) {
        if (($bill['status'] ?? '') === 'Paid') {
            $amount = (float) ($bill['amount'] ?? 0);
            $totalIncome += $amount;
            if (substr((string)($bill['paid_date'] ?? ''), 0, 7) === $month) {
                $monthIncome += $amount;
            }
        } elseif (($bill['status'] ?? '') === 'Unpaid') {
            $unpaidBalance += (float) ($bill['amount'] ?? 0);
            if (!empty($bill['due_date']) && strtotime((string)$bill['due_date']) < strtotime(date('Y-m-d'))) {
                $overdueBills++;
            }
        }
    }

    if ($totalRooms > 0) {
        $occupancy = round(($occupiedRooms / $totalRooms) * 100, 1);
    }

    return [
        'total_rooms' => $totalRooms,
        'occupied_rooms' => $occupiedRooms,
        'available_rooms' => $availableRooms,
        'maintenance_rooms' => $maintenanceRooms,
        'tenants' => count($tenantIds),
        'monthly_income' => $monthIncome,
        'total_income' => $totalIncome,
        'unpaid_balance' => $unpaidBalance,
        'overdue_bills' => $overdueBills,
        'occupancy' => $occupancy,
    ];
}

function monthly_income_history(int $months = 6): array
{
    $history = [];
    for ($offset = $months - 1; $offset >= 0; $offset--) {
        $month = date('Y-m', strtotime("-$offset months"));
        $history[$month] = 0.0;
    }

    foreach (load_json('bills') as $bill) {
        if (($bill['status'] ?? '') !== 'Paid') {
            continue;
        }
        $paidMonth = substr((string)($bill['paid_date'] ?? ''), 0, 7);
        if (array_key_exists($paidMonth, $history)) {
            $history[$paidMonth] += (float)($bill['amount'] ?? 0);
        }
    }

    return $history;
}

function store_room_image(array $upload): string
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if (($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Hindi ma-upload ang larawan. Subukan muli.');
    }
    if (($upload['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('Hanggang 5 MB lang ang larawan.');
    }

    $tmpName = (string)($upload['tmp_name'] ?? '');
    if (!is_uploaded_file($tmpName)) {
        throw new RuntimeException('Hindi valid ang napiling upload.');
    }

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $fileInfo->file($tmpName);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($extensions[$mime]) || @getimagesize($tmpName) === false) {
        throw new RuntimeException('JPG, PNG, o WEBP na larawan lang ang puwede.');
    }

    $directory = storage_path('uploads/rooms');
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Hindi maihanda ang folder para sa room photos.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($tmpName, $directory . '/' . $filename)) {
        throw new RuntimeException('Hindi ma-save ang room photo.');
    }

    return 'uploads/rooms/' . $filename;
}

function format_money(float $amount): string
{
    return '₱' . number_format($amount, 2);
}

function format_date(?string $value): string
{
    if (empty($value)) {
        return '—';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    return date('M d, Y', $timestamp);
}

function get_room_status_badge(string $status): string
{
    $status = strtolower($status);
    $variants = [
        'available' => 'success',
        'occupied' => 'info',
        'maintenance' => 'warning',
    ];

    return $variants[$status] ?? 'secondary';
}

function get_bill_badge(string $status): string
{
    $status = strtolower($status);
    $variants = [
        'paid' => 'success',
        'unpaid' => 'warning',
    ];

    return $variants[$status] ?? 'secondary';
}

ensure_data_files();
