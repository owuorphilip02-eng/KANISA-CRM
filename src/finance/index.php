<?php

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\Slim\MvcAppFactory;
use ChurchCRM\Slim\Middleware\Request\Auth\FinanceRoleAuthMiddleware;

$app = MvcAppFactory::create('/finance', [
    'dashboardUrl' => '/finance/',
    'dashboardText' => gettext('Back to Finance Dashboard'),
    'roleMiddleware' => FinanceRoleAuthMiddleware::class,
]);

// Register routes
require __DIR__ . '/routes/dashboard.php';
require __DIR__ . '/routes/reports.php';
require __DIR__ . '/routes/pledges.php';
require __DIR__ . '/routes/requisitions.php';
require __DIR__ . '/routes/deposits-breakdown.php';
require __DIR__ . '/routes/quickbooks.php';
require __DIR__ . '/routes/withdrawals.php';
require __DIR__ . '/routes/balance.php';

$app->run();
