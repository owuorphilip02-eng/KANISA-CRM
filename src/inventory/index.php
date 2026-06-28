<?php
require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\Slim\MvcAppFactory;

$app = MvcAppFactory::create('/inventory', [
    'dashboardUrl'  => '/inventory',
    'dashboardText' => gettext('Back to Inventory'),
]);

require __DIR__ . '/routes/dashboard.php';

$app->run();
