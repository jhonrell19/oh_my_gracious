<?php

declare(strict_types=1);

require_once __DIR__ . '/authorization.php';
require_once __DIR__ . '/helpers.php';

requireRegularAdmin();

http_response_code(403);
redirectTo(
    'admin-dashboard.php?error=' .
    urlencode('Front desk booking creation is disabled. Customers must create their own appointments.')
);
