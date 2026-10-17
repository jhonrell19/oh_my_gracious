<?php
require_once __DIR__ . '/backend/authorization.php';
require_once __DIR__ . '/backend/db.php';
require_once __DIR__ . '/backend/csrf.php';
requireCustomer();

$uid = (int)$_SESSION['user_id'];
$profileStmt = $conn->prepare('SELECT phone_number FROM users WHERE id=?');
$profileStmt->bind_param('i', $uid);
$profileStmt->execute();
$profile = $profileStmt->get_result()->fetch_assoc();
$profileStmt->close();
$needsProfile = empty($profile['phone_number']);

$serviceRows = $conn->query(
    "SELECT id,service_name,category,description,price,price_max,pricing_type,
            duration_minutes,booking_availability,unavailable_reason
     FROM services
     WHERE status='active'
     ORDER BY category,display_order,service_name"
)->fetch_all(MYSQLI_ASSOC);

$categories = [];
foreach ($serviceRows as $serviceRow) {
    $category = trim((string)$serviceRow['category']) ?: 'General';
    $categories[$category] = true;
}
$categories = array_keys($categories);

$designStmt = $conn->prepare(
    'SELECT id,design_name,nail_color,nail_shape,nail_finish
     FROM saved_designs
     WHERE user_id=?
     ORDER BY created_at DESC'
);
$designStmt->bind_param('i', $uid);
$designStmt->execute();
$designs = $designStmt->get_result();

$days = (int)getSetting($conn, 'booking_days_ahead', '90');
$prefDesign = (int)($_GET['design_id'] ?? 0);
$selectedDate = (string)($_GET['date'] ?? date('Y-m-d'));

function servicePriceText(array $service): string
{
    $price = (float)$service['price'];
    $type = (string)($service['pricing_type'] ?? 'fixed');
    $max = isset($service['price_max']) ? (float)$service['price_max'] : 0.0;

    if ($type === 'per_nail') {
        return '₱' . number_format($price, 2) . ' / nail';
    }

    if ($type === 'range' && $max > $price) {
        return '₱' . number_format($price, 2) . ' – ₱' . number_format($max, 2);
    }

    return '₱' . number_format($price, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Book Appointment | Oh My Gracious</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<header class="navbar">
    <div class="logo">Oh My Gracious</div>
    <nav>
        <a href="user-dashboard.php">Dashboard</a>
        <a class="active" href="booking.php">Book</a>
        <a href="booking-history.php">History</a>
        <a href="saved-designs.php">Saved Designs</a>
        <a href="account.php">My Account</a>
        <a href="logout.php" class="logout-btn">Logout</a>
    </nav>
</header>

<main class="page-wrap narrow">
    <div class="page-heading">
        <div>
            <p class="eyebrow">APPOINTMENTS</p>
            <h1>Book your salon visit</h1>
            <p>Select one or more available services, then choose a fixed booking time with remaining salon capacity.</p>
        </div>
    </div>

    <?php if (!empty($_GET['error'])): ?>
        <div class="alert error"><?=h((string)$_GET['error'])?></div>
    <?php endif; ?>

    <?php if ($needsProfile): ?>
        <div class="alert warning">
            <strong>Complete your contact profile first.</strong>
            Google-created accounts must add a contact number before booking.
            <a href="account.php?complete=1">Open My Account</a>.
        </div>
    <?php else: ?>
        <form id="bookingForm" action="backend/create-booking.php" method="POST" class="panel stack-lg">
            <?=csrfInput()?>

            <section>
                <div class="section-heading">
                    <div>
                        <h2>1. Choose services</h2>
                        <p>Prices below are managed by the salon owner. Per-nail services let you choose 1–10 nails.</p>
                    </div>
                </div>

                <div class="service-filter-bar">
                    <input id="serviceSearch" type="search" placeholder="Search services…" autocomplete="off">
                    <select id="categoryFilter">
                        <option value="">All categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?=h($category)?>"><?=h($category)?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="service-grid" id="serviceGrid">
                    <?php $lastCategory = null; ?>
                    <?php foreach ($serviceRows as $s): ?>
                        <?php
                        $category = trim((string)$s['category']) ?: 'General';
                        $available = $s['booking_availability'] === 'available';
                        $pricingType = (string)($s['pricing_type'] ?? 'fixed');
                        $searchText = strtolower($s['service_name'] . ' ' . $category . ' ' . ($s['description'] ?? ''));
                        ?>

                        <?php if ($lastCategory !== $category): $lastCategory = $category; ?>
                            <div class="service-category-label" data-category-label="<?=h($category)?>">
                                <?=h($category)?>
                            </div>
                        <?php endif; ?>

                        <div class="service-option <?=$available ? '' : 'unavailable'?>"
                             data-service-card
                             data-category="<?=h($category)?>"
                             data-search="<?=h($searchText)?>">
                            <label class="service-main">
                                <input
                                    class="service-check"
                                    type="checkbox"
                                    name="service_ids[]"
                                    value="<?= (int)$s['id']?>"
                                    data-price="<?=h((string)$s['price'])?>"
                                    data-duration="<?= (int)$s['duration_minutes']?>"
                                    data-pricing-type="<?=h($pricingType)?>"
                                    <?=$available ? '' : 'disabled'?>
                                >
                                <span>
                                    <strong><?=h($s['service_name'])?></strong>
                                    <small><?=h((string)$s['description'])?></small>
                                    <b><?=h(servicePriceText($s))?><?= (int)$s['duration_minutes'] > 0 ? ' · ~' . (int)$s['duration_minutes'] . ' min' : ''?></b>

                                    <?php if ($pricingType === 'range'): ?>
                                        <small class="range-price-note">Final price is confirmed by the salon based on the service required.</small>
                                    <?php endif; ?>

                                    <?php if (!$available): ?>
                                        <em>Unavailable<?= $s['unavailable_reason'] ? ' — ' . h($s['unavailable_reason']) : ''?></em>
                                    <?php endif; ?>
                                </span>
                            </label>

                            <?php if ($pricingType === 'per_nail' && $available): ?>
                                <div class="service-quantity">
                                    <label for="qty-<?= (int)$s['id']?>">Number of nails</label>
                                    <input
                                        id="qty-<?= (int)$s['id']?>"
                                        class="service-qty"
                                        type="number"
                                        name="service_quantities[<?= (int)$s['id']?>]"
                                        min="1"
                                        max="10"
                                        value="1"
                                        data-service-id="<?= (int)$s['id']?>"
                                        disabled
                                    >
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div id="noServiceMatches" class="empty-state compact-empty hidden">
                    No services match your search.
                </div>

                <div class="booking-summary">
                    <div>
                        <span>Estimated service time</span>
                        <strong id="durationText">0 min</strong>
                        <small>Informational only; actual service time may vary.</small>
                    </div>
                    <div>
                        <span id="totalLabel">Estimated total</span>
                        <strong id="totalText">₱0.00</strong>
                        <small id="rangeNotice" class="hidden">Range-priced services use the minimum shown price until the salon confirms the final amount.</small>
                    </div>
                </div>
            </section>

            <section>
                <h2>2. Date and booking time</h2>
                <div class="form-grid two">
                    <div class="field">
                        <label>Date</label>
                        <input id="bookingDate" name="booking_date" type="date"
                               min="<?=date('Y-m-d')?>"
                               max="<?=date('Y-m-d', strtotime('+' . $days . ' days'))?>"
                               value="<?=h($selectedDate)?>" required>
                    </div>
                    <div class="field">
                        <label>Available Time</label>
                        <select id="bookingTime" name="booking_time" required>
                            <option value="">Select services and date first</option>
                        </select>
                    </div>
                </div>
                <p class="field-help">Fixed appointment times use salon capacity instead of strict per-service durations. A time becomes unavailable when it is closed by staff or reaches its configured customer limit.</p>
            </section>

            <section>
                <h2>3. Design preference</h2>
                <div class="form-grid two">
                    <div class="field">
                        <label>Saved AR Design</label>
                        <select name="selected_design_id">
                            <option value="">No saved design</option>
                            <?php while ($d = $designs->fetch_assoc()): ?>
                                <option value="<?= (int)$d['id']?>" <?=$prefDesign === (int)$d['id'] ? 'selected' : ''?>>
                                    <?=h($d['design_name'] . ' — ' . $d['nail_shape'] . ' ' . $d['nail_finish'])?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Notes</label>
                        <textarea name="notes" rows="3" placeholder="Length, colors, special requests, etc."></textarea>
                    </div>
                </div>
            </section>

            <section>
                <h2>4. Payment</h2>
                <input type="hidden" name="payment_method" value="Cash">
                <div class="payment-option static-payment">
                    <span>
                        <strong>Pay at Salon</strong>
                        <small>Real online payment is the next integration phase. The current booking total is stored from owner-managed prices.</small>
                    </span>
                </div>
            </section>

            <button class="btn primary full" type="submit">Submit Booking Request</button>
        </form>
    <?php endif; ?>
</main>

<?php if (!$needsProfile): ?>
<script>
const checks = [...document.querySelectorAll('.service-check')];
const quantities = [...document.querySelectorAll('.service-qty')];
const dateEl = document.getElementById('bookingDate');
const timeEl = document.getElementById('bookingTime');
const totalEl = document.getElementById('totalText');
const totalLabel = document.getElementById('totalLabel');
const durationEl = document.getElementById('durationText');
const rangeNotice = document.getElementById('rangeNotice');
const searchEl = document.getElementById('serviceSearch');
const categoryEl = document.getElementById('categoryFilter');
const noMatches = document.getElementById('noServiceMatches');

function selectedIds() {
    return checks.filter(c => c.checked).map(c => c.value);
}

function quantityFor(check) {
    if (check.dataset.pricingType !== 'per_nail') return 1;
    const input = document.querySelector('.service-qty[data-service-id="' + check.value + '"]');
    if (!input) return 1;
    return Math.max(1, Math.min(10, Number(input.value) || 1));
}

function updateSummary() {
    let total = 0;
    let duration = 0;
    let hasRange = false;

    checks.forEach(check => {
        const qty = document.querySelector('.service-qty[data-service-id="' + check.value + '"]');
        if (qty) qty.disabled = !check.checked;

        if (check.checked) {
            total += Number(check.dataset.price) * quantityFor(check);
            duration += Number(check.dataset.duration);
            if (check.dataset.pricingType === 'range') hasRange = true;
        }
    });

    totalEl.textContent = (hasRange ? 'From ' : '') + '₱' + total.toFixed(2);
    totalLabel.textContent = hasRange ? 'Estimated starting total' : 'Estimated total';
    rangeNotice.classList.toggle('hidden', !hasRange);
    durationEl.textContent = duration > 0 ? '~' + duration + ' min' : 'Varies';
    loadTimes();
}

async function loadTimes() {
    const ids = selectedIds();
    timeEl.innerHTML = '<option value="">Loading availability…</option>';

    if (!ids.length || !dateEl.value) {
        timeEl.innerHTML = '<option value="">Select services and date first</option>';
        return;
    }

    try {
        const response = await fetch(
            'backend/available-times.php?date=' + encodeURIComponent(dateEl.value) +
            '&service_ids=' + ids.join(',')
        );
        const data = await response.json();
        timeEl.innerHTML = '';

        if (data.status !== 'success') {
            timeEl.innerHTML = '<option value="">' + (data.message || 'Availability unavailable') + '</option>';
            return;
        }

        timeEl.append(new Option('Select booking time', ''));
        data.times.forEach(slot => {
            const option = new Option(slot.label, slot.value);
            option.disabled = !slot.available;
            timeEl.append(option);
        });
    } catch (error) {
        timeEl.innerHTML = '<option value="">Availability unavailable</option>';
    }
}

function filterServices() {
    const query = searchEl.value.trim().toLowerCase();
    const category = categoryEl.value;
    const cards = [...document.querySelectorAll('[data-service-card]')];
    let visible = 0;

    cards.forEach(card => {
        const matchesText = !query || card.dataset.search.includes(query);
        const matchesCategory = !category || card.dataset.category === category;
        const show = matchesText && matchesCategory;
        card.classList.toggle('hidden', !show);
        if (show) visible++;
    });

    document.querySelectorAll('[data-category-label]').forEach(label => {
        const categoryName = label.dataset.categoryLabel;
        const anyVisible = cards.some(card =>
            !card.classList.contains('hidden') && card.dataset.category === categoryName
        );
        label.classList.toggle('hidden', !anyVisible);
    });

    noMatches.classList.toggle('hidden', visible !== 0);
}

checks.forEach(check => check.addEventListener('change', updateSummary));
quantities.forEach(input => input.addEventListener('input', updateSummary));
dateEl.addEventListener('change', loadTimes);
searchEl.addEventListener('input', filterServices);
categoryEl.addEventListener('change', filterServices);

document.getElementById('bookingForm').addEventListener('submit', event => {
    if (!selectedIds().length) {
        event.preventDefault();
        alert('Select at least one available service.');
    }
});

filterServices();
updateSummary();
</script>
<?php endif; ?>
</body>
</html>
