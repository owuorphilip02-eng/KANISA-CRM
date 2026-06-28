<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

$app->get('/deposits/breakdown', function (Request $request, Response $response) {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');
    $pageArgs = [
        'sRootPath'     => SystemURLs::getRootPath(),
        'sPageTitle'    => gettext('Deposits Breakdown'),
        'sPageSubtitle' => gettext('Full breakdown of all deposits by type'),
        'aBreadcrumbs'  => PageHeader::breadcrumbs([
            [gettext('Finance'), '/finance/'],
            [gettext('Deposits Breakdown')],
        ]),
        'sPageHeaderButtons' => PageHeader::buttons([]),
    ];
    return $renderer->render($response, 'deposits-breakdown.php', $pageArgs);
});