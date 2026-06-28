<?php
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

$app->get('/', function (Request $request, Response $response) {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');
    $pageArgs = [
        'sRootPath'     => SystemURLs::getRootPath(),
        'sPageTitle'    => gettext('Inventory'),
        'sPageSubtitle' => gettext('Manage church assets and stock'),
        'aBreadcrumbs'  => PageHeader::breadcrumbs([
            [gettext('Inventory')],
        ]),
    ];
    return $renderer->render($response, 'dashboard.php', $pageArgs);
});

$app->post('/', function (Request $request, Response $response) {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');
    $pageArgs = [
        'sRootPath'     => SystemURLs::getRootPath(),
        'sPageTitle'    => gettext('Inventory'),
        'sPageSubtitle' => gettext('Manage church assets and stock'),
        'aBreadcrumbs'  => PageHeader::breadcrumbs([
            [gettext('Inventory')],
        ]),
    ];
    return $renderer->render($response, 'dashboard.php', $pageArgs);
});
