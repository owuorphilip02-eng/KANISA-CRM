<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

$app->map(['GET', 'POST'], '/requisitions', function (Request $request, Response $response) {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');
    $pageArgs = [
        'sRootPath'     => SystemURLs::getRootPath(),
        'sPageTitle'    => gettext('Requisitions'),
        'sPageSubtitle' => gettext('Manage withdrawal requests and approvals'),
        'aBreadcrumbs'  => PageHeader::breadcrumbs([
            [gettext('Finance'), '/finance/'],
            [gettext('Requisitions')],
        ]),
        'sPageHeaderButtons' => PageHeader::buttons([]),
    ];
    return $renderer->render($response, 'requisitions.php', $pageArgs);
});