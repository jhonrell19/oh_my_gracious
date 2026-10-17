<?php
require_once __DIR__ . '/backend/authorization.php';
require_once __DIR__ . '/backend/helpers.php';
requireRegularAdmin();

redirectTo(
    'admin-dashboard.php?error=' .
    urlencode('Front desk booking creation is disabled. Customers must create their own appointments.')
);
