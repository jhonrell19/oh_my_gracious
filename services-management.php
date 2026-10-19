<?php
require_once __DIR__ . '/backend/authorization.php';
require_once __DIR__ . '/backend/db.php';
require_once __DIR__ . '/backend/csrf.php';
requireSuperAdmin();

$message = '';
$type = 'success';

function normalizePricingType(string $value): string
{
    return in_array($value, ['fixed', 'per_nail', 'range'], true) ? $value : 'fixed';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save') {
        $id = (int)($_POST['service_id'] ?? 0);
        $name = trim((string)($_POST['service_name'] ?? ''));
        $category = trim((string)($_POST['category'] ?? 'General'));
        $desc = trim((string)($_POST['description'] ?? ''));
        $price = (float)($_POST['price'] ?? 0);
        $pricingType = normalizePricingType((string)($_POST['pricing_type'] ?? 'fixed'));
        $priceMaxRaw = trim((string)($_POST['price_max'] ?? ''));
        $priceMax = null;
        $duration = max(0, (int)($_POST['duration_minutes'] ?? 0));
        $status = in_array($_POST['status'] ?? 'active', ['active', 'inactive'], true)
            ? (string)$_POST['status']
            : 'active';

        if ($pricingType === 'range') {
            $priceMax = $priceMaxRaw === '' ? null : (float)$priceMaxRaw;
        }

        if ($name === '' || $category === '' || $price < 0) {
            $message = 'Enter a valid category, service name, and price.';
            $type = 'error';
        } elseif ($pricingType === 'range' && ($priceMax === null || $priceMax < $price)) {
            $message = 'Range-priced services need a maximum price equal to or higher than the starting price.';
            $type = 'error';
        } else {
            try {
                if ($id > 0) {
                    $stmt = $conn->prepare(
                        'UPDATE services
                         SET service_name=?,category=?,description=?,price=?,price_max=?,pricing_type=?,duration_minutes=?,status=?
                         WHERE id=?'
                    );
                    $stmt->bind_param(
                        'sssddsisi',
                        $name,
                        $category,
                        $desc,
                        $price,
                        $priceMax,
                        $pricingType,
                        $duration,
                        $status,
                        $id
                    );
                    $stmt->execute();
                    $stmt->close();
                    audit($conn, 'service_updated', 'service', $id);
                } else {
                    $availability = 'available';
                    $stmt = $conn->prepare(
                        'INSERT INTO services(
                            service_name,category,description,price,price_max,pricing_type,
                            duration_minutes,status,booking_availability,display_order
                         ) VALUES (?,?,?,?,?,?,?,?,?,999)'
                    );
                    $stmt->bind_param(
                        'sssddsiss',
                        $name,
                        $category,
                        $desc,
                        $price,
                        $priceMax,
                        $pricingType,
                        $duration,
                        $status,
                        $availability
                    );
                    $stmt->execute();
                    $newId = (int)$conn->insert_id;
                    $stmt->close();
                    audit($conn, 'service_created', 'service', $newId);
                }

                $message = 'Service saved.';
            } catch (Throwable $e) {
                error_log('Service save: ' . $e->getMessage());
                $message = 'Service could not be saved. The service name may already exist.';
                $type = 'error';
            }
        }
    }
}

$services = $conn->query(
    'SELECT * FROM services ORDER BY category,display_order,service_name'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Services & Pricing | Oh My Gracious</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<header class="navbar">
    <div class="logo">Owner Console</div>
    <nav>
        <a href="super-admin-dashboard.php">Dashboard</a>
        <a href="revenue-reports.php">Revenue</a>
        <a class="active" href="services-management.php">Services</a>
        <a href="technician-management.php">Technicians</a>
        <a href="admin-management.php">Staff Admins</a>
        <a href="system-settings.php">Settings</a>
        <a href="audit-logs.php">Activity</a>
        <a href="payment-settings.php">Payments</a><a href="staff-security.php">Security</a><a href="logout.php" class="logout-btn">Logout</a>
    </nav>
</header>

<main class="page-wrap">
    <div class="page-heading">
        <div>
            <p class="eyebrow">SERVICE CATALOG</p>
            <h1>Services & Pricing</h1>
            <p>These prices are fully owner-managed. Use Fixed for normal services, Per Nail for nail-art charges, and Range when the final price depends on the client's condition or service requirement.</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert <?=$type?>"><?=h($message)?></div>
    <?php endif; ?>

    <section class="panel">
        <h2>Add Service</h2>
        <form method="POST" class="form-grid four">
            <?=csrfInput()?>
            <input type="hidden" name="action" value="save">

            <div class="field">
                <label>Category</label>
                <input name="category" value="General" required>
            </div>

            <div class="field">
                <label>Name</label>
                <input name="service_name" required>
            </div>

            <div class="field">
                <label>Pricing Type</label>
                <select name="pricing_type" class="pricing-type-select">
                    <option value="fixed">Fixed</option>
                    <option value="per_nail">Per Nail</option>
                    <option value="range">Price Range</option>
                </select>
            </div>

            <div class="field">
                <label>Price / Starting Price (PHP)</label>
                <input name="price" type="number" min="0" step="0.01" required>
            </div>

            <div class="field">
                <label>Maximum Price (PHP)</label>
                <input name="price_max" type="number" min="0" step="0.01" placeholder="Only for ranges">
            </div>

            <div class="field">
                <label>Estimated Minutes</label>
                <input name="duration_minutes" type="number" min="0" step="15" value="0">
                <small>Optional only. 0 = varies.</small>
            </div>

            <div class="field">
                <label>Catalog Status</label>
                <select name="status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <div class="field">
                <label>Description</label>
                <input name="description">
            </div>

            <div class="span-4">
                <button class="btn primary">Add Service</button>
            </div>
        </form>
    </section>

    <section class="panel">
        <div class="section-heading">
            <div>
                <h2>Current Services</h2>
                <p>Changing a price here updates future bookings only. Existing booking items keep the price that was recorded when the appointment was created.</p>
            </div>
        </div>

        <div class="table-scroll">
            <table>
                <thead>
                <tr>
                    <th>Category</th>
                    <th>Service</th>
                    <th>Description</th>
                    <th>Pricing</th>
                    <th>Price</th>
                    <th>Max</th>
                    <th>Est. Time</th>
                    <th>Catalog</th>
                    <th>Booking Availability</th>
                    <th>Update</th>
                </tr>
                </thead>
                <tbody>
                <?php while ($s = $services->fetch_assoc()): ?>
                    <?php $formId = 'service-form-' . (int)$s['id']; ?>
                    <tr>
                        <td><input form="<?=$formId?>" name="category" value="<?=h($s['category'])?>"></td>
                        <td><input form="<?=$formId?>" name="service_name" value="<?=h($s['service_name'])?>"></td>
                        <td><input form="<?=$formId?>" name="description" value="<?=h((string)$s['description'])?>"></td>
                        <td>
                            <select form="<?=$formId?>" name="pricing_type">
                                <option value="fixed" <?=$s['pricing_type'] === 'fixed' ? 'selected' : ''?>>Fixed</option>
                                <option value="per_nail" <?=$s['pricing_type'] === 'per_nail' ? 'selected' : ''?>>Per Nail</option>
                                <option value="range" <?=$s['pricing_type'] === 'range' ? 'selected' : ''?>>Range</option>
                            </select>
                        </td>
                        <td><input form="<?=$formId?>" name="price" type="number" min="0" step="0.01" value="<?=h($s['price'])?>"></td>
                        <td><input form="<?=$formId?>" name="price_max" type="number" min="0" step="0.01" value="<?=h((string)($s['price_max'] ?? ''))?>" placeholder="—"></td>
                        <td><input form="<?=$formId?>" name="duration_minutes" type="number" min="0" step="15" value="<?= (int)$s['duration_minutes']?>"></td>
                        <td>
                            <select form="<?=$formId?>" name="status">
                                <option value="active" <?=$s['status'] === 'active' ? 'selected' : ''?>>Active</option>
                                <option value="inactive" <?=$s['status'] === 'inactive' ? 'selected' : ''?>>Inactive</option>
                            </select>
                        </td>
                        <td>
                            <span class="status <?=$s['booking_availability'] === 'available' ? 'approved' : 'neutral'?>">
                                <?=h(ucfirst($s['booking_availability']))?>
                            </span>
                            <?php if ($s['unavailable_reason']): ?>
                                <br><small><?=h($s['unavailable_reason'])?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form id="<?=$formId?>" method="POST">
                                <?=csrfInput()?>
                                <input type="hidden" name="action" value="save">
                                <input type="hidden" name="service_id" value="<?= (int)$s['id']?>">
                                <button class="btn secondary tiny">Save</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
