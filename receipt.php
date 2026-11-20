<?php
require __DIR__ . '/includes/bootstrap.php';

$billId = (string)($_GET['id'] ?? '');
$bill = null;
foreach (load_json('bills') as $entry) {
    if (($entry['id'] ?? '') === $billId) {
        $bill = $entry;
        break;
    }
}

if ($bill === null) {
    redirect('login.php');
}

$user = current_user();
if ($user && ($user['role'] ?? '') === 'tenant' && ($user['tenant_id'] ?? '') !== ($bill['tenant_id'] ?? '')) {
    redirect('tenant.php');
}
if (!$user) {
    redirect('login.php');
}
if (($bill['status'] ?? '') !== 'Paid') {
    redirect(($user['role'] ?? '') === 'admin' ? 'admin.php' : 'tenant.php');
}

$tenant = get_tenant_by_id((string)($bill['tenant_id'] ?? ''));
$room = get_room_by_id((string)($tenant['room_id'] ?? ''));

if (($_GET['format'] ?? '') === 'pdf') {
    $pdfSafe = static function (string $text): string {
        $text = str_replace('₱', 'PHP ', $text);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($ascii === false) {
            $ascii = preg_replace('/[^\x20-\x7E]/', '', $text) ?? '';
        }
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $ascii);
    };

    $lines = [
        'Golden Haven Boarding House',
        'PAYMENT RECEIPT',
        '',
        'Receipt No: ' . (string)($bill['receipt_number'] ?? ('RCP-' . $bill['id'])),
        'Tenant: ' . (string)($tenant['full_name'] ?? 'Unknown'),
        'Room: ' . (string)($room['number'] ?? 'N/A'),
        'Rent month: ' . (string)($bill['month'] ?? ''),
        'Amount paid: ' . format_money((float)($bill['amount'] ?? 0)),
        'Paid on: ' . format_date((string)($bill['paid_date'] ?? '')),
        '',
        'Thank you for your payment.',
    ];
    $stream = '';
    $y = 760;
    foreach ($lines as $index => $line) {
        $fontSize = $index === 0 ? 18 : ($index === 1 ? 14 : 12);
        $stream .= "BT /F1 {$fontSize} Tf 50 {$y} Td (" . $pdfSafe($line) . ") Tj ET\n";
        $y -= $index === 0 ? 34 : 26;
    }

    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
        '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "endstream",
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
    }
    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    for ($index = 1; $index <= count($objects); $index++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$index]);
    }
    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="receipt-' . preg_replace('/[^A-Za-z0-9_-]/', '', (string)$bill['id']) . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt | Golden Haven</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="auth-page">
    <div class="auth-card" style="width:min(760px,100%);">
        <div class="auth-header">
            <p class="eyebrow">Payment Receipt</p>
            <h1>Golden Haven Boarding House</h1>
        </div>

        <div class="receipt-summary">
            <p><strong>Receipt No:</strong> <?= htmlspecialchars((string)($bill['receipt_number'] ?? 'N/A')) ?></p>
            <p><strong>Tenant:</strong> <?= htmlspecialchars((string)($tenant['full_name'] ?? 'Unknown')) ?></p>
            <p><strong>Room:</strong> <?= htmlspecialchars((string)($room['number'] ?? 'N/A')) ?></p>
            <p><strong>Month:</strong> <?= htmlspecialchars((string)($bill['month'] ?? '')) ?></p>
            <p><strong>Amount Paid:</strong> <?= format_money((float)($bill['amount'] ?? 0)) ?></p>
            <p><strong>Paid On:</strong> <?= format_date((string)($bill['paid_date'] ?? '')) ?></p>
        </div>

        <div class="demo-box">
            <p>Thank you for your payment. This receipt confirms your monthly rent payment for the boarding house.</p>
        </div>

        <div class="receipt-actions">
            <a class="btn btn-primary" href="receipt.php?id=<?= urlencode((string)$bill['id']) ?>&amp;format=pdf">Download PDF</a>
            <button type="button" class="btn btn-ghost print-button" onclick="window.print()">Print</button>
        </div>
        <p class="back-link"><a href="<?= (($user['role'] ?? '') === 'admin') ? 'admin.php' : 'tenant.php' ?>">Return to dashboard</a></p>
    </div>
</body>
</html>
