<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

$app->get('/membership-expiry', function (Request $request, Response $response) {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');
    $pageArgs = [
        'sRootPath'     => SystemURLs::getRootPath(),
        'sPageTitle'    => gettext('Membership Expiry'),
        'sPageSubtitle' => gettext('Members with expired or expiring memberships'),
        'aBreadcrumbs'  => PageHeader::breadcrumbs([
            [gettext('Members'), '/people/dashboard'],
            [gettext('Membership Expiry')],
        ]),
        'sPageHeaderButtons' => PageHeader::buttons([]),
    ];
    return $renderer->render($response, 'membership-expiry.php', $pageArgs);
});