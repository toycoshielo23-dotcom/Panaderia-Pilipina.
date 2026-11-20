<?php
require __DIR__ . '/includes/bootstrap.php';
$rooms = load_json('rooms');
$announcements = load_json('announcements');
$stats = summary_stats();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Affordable boarding house near the city with clean rooms, flexible rent terms, and secure management for tenants and owners.">
    <meta name="keywords" content="Affordable Boarding House near Town, boarding house rooms, room rental, boarder management">
    <title>Golden Haven Boarding House</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <header class="topbar">
        <div class="container nav-wrap">
            <div class="brand">
                <strong>Golden Haven</strong>
                <span>Boarding House</span>
            </div>
            <nav>
                <a href="#rooms">Rooms</a>
                <a href="#rules">House Rules</a>
                <a href="#contact">Contact</a>
                <a class="btn btn-primary" href="login.php">Login</a>
            </nav>
        </div>
    </header>

    <section class="hero">
        <div class="container hero-grid">
            <div>
                <p class="eyebrow">Clean living. Fair pricing. Trusted management.</p>
                <h1>Affordable boarding house rooms for students and workers</h1>
                <p class="lead">Comfortable, secure, and well-managed rooms with transparent rent billing and a friendly community environment.</p>
                <div class="cta-row">
                    <a class="btn btn-primary" href="#rooms">View Rooms</a>
                    <a class="btn btn-ghost" href="login.php">Owner Login</a>
                </div>
                <ul class="stats-list">
                    <li><strong><?= htmlspecialchars((string)$stats['total_rooms']) ?></strong><span>Rooms</span></li>
                    <li><strong><?= htmlspecialchars((string)$stats['available_rooms']) ?></strong><span>Available</span></li>
                    <li><strong><?= htmlspecialchars((string)$stats['occupancy']) ?>%</strong><span>Occupied</span></li>
                </ul>
            </div>
            <div class="hero-card">
                <img src="https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=900&q=80" alt="Comfortable boarding room interior" />
            </div>
        </div>
    </section>

    <main>
        <section id="rooms" class="section">
            <div class="container">
                <div class="section-heading">
                    <p class="eyebrow">Room availability</p>
                    <h2>Available rooms and rates</h2>
                </div>
                <div class="room-grid">
                    <?php foreach ($rooms as $roomIndex => $room): ?>
                        <article class="room-card">
                            <span class="badge badge-<?= htmlspecialchars(get_room_status_badge((string)($room['status'] ?? 'Available'))) ?>"><?= htmlspecialchars((string)($room['status'] ?? 'Available')) ?></span>
                            <?php if (!empty($room['image'])): ?>
                                <img class="room-photo" src="<?= htmlspecialchars((string)$room['image']) ?>" alt="Boarding room <?= htmlspecialchars((string)($room['number'] ?? '')) ?>">
                            <?php endif; ?>
                            <h3>Room <?= $roomIndex + 1 ?></h3>
                            <?php $bedCount = (int)($room['beds'] ?? 1); ?>
                            <p class="meta"><?= $bedCount ?> <?= $bedCount === 1 ? 'bed' : 'beds' ?></p>
                            <p><?= htmlspecialchars((string)($room['description'] ?? 'Well-kept room for everyday living.')) ?></p>
                            <div class="price-row">
                                <strong><?= format_money(room_rate_per_bed($room)) ?></strong>
                                <span>per bed / month</span>
                            </div>
                            <p class="meta">Total for <?= htmlspecialchars((string)($room['beds'] ?? 1)) ?> beds: <?= format_money(room_monthly_total($room)) ?> / month</p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section id="rules" class="section alt">
            <div class="container rules-grid">
                <div>
                    <p class="eyebrow">House policies</p>
                    <h2>Comfortable, secure, and respectful community living</h2>
                    <ul class="checks">
                        <li>Monthly rent is due on or before the 15th of each month.</li>
                        <li>Quiet hours are observed from 10:00 PM to 6:00 AM.</li>
                        <li>Visitors must register with the admin before entering the premises.</li>
                        <li>Utilities and maintenance concerns should be reported immediately.</li>
                    </ul>
                </div>
                <div class="notice-box">
                    <h3>Latest announcements</h3>
                    <?php foreach (array_slice($announcements, 0, 3) as $announcement): ?>
                        <div class="notice-item">
                            <strong><?= htmlspecialchars((string)($announcement['title'] ?? 'Announcement')) ?></strong>
                            <p><?= htmlspecialchars((string)($announcement['message'] ?? '')) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </main>

    <footer id="contact" class="footer">
        <div class="container footer-grid">
            <div>
                <h3>Golden Haven Boarding House</h3>
                <p>Clean rooms, affordable pricing, and a safe environment for students and workers.</p>
            </div>
            <div>
                <h4>Contact</h4>
                <p>Phone: 0912-345-6789</p>
                <p>Email: admin@goldenhavenboarding.com</p>
            </div>
            <div>
                <h4>Location</h4>
                <p>Near the town plaza and public transport</p>
            </div>
        </div>
    </footer>
</body>
</html>
