<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

$app->get('/withdrawals', function (Request $request, Response $response) {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    $pageArgs = [
        'sRootPath'     => SystemURLs::getRootPath(),
        'sPageTitle'    => gettext('Withdrawal Analytics'),
        'sPageSubtitle' => gettext('Breakdown and trends of all approved withdrawals'),
        'aBreadcrumbs'  => PageHeader::breadcrumbs([
            ['Finance', 'finance/'],
            ['Withdrawal Analytics'],
        ]),
        'sPageHeaderButtons' => PageHeader::buttons([]),
    ];

    return $renderer->render($response, 'withdrawals.php', $pageArgs);
});

$app->get('/withdrawals/data', function (Request $request, Response $response) {
    $con = \Propel\Runtime\Propel::getConnection();
    $params = $request->getQueryParams();
    $year   = $params['year'] ?? date('Y');

    // Monthly totals
    $stmt = $con->prepare("
        SELECT MONTH(approved_date) as month, SUM(amount) as total
        FROM fin_requisitions
        WHERE status = 'approved' AND YEAR(approved_date) = ?
        GROUP BY MONTH(approved_date)
        ORDER BY month
    ");
    $stmt->execute([$year]);
    $monthly = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $monthly[$row['month']] = floatval($row['total']);
    }

    // Fund breakdown
    $stmt = $con->prepare("
        SELECT COALESCE(f.fun_Name, 'Unassigned') as fund, SUM(r.amount) as total, COUNT(*) as count
        FROM fin_requisitions r
        LEFT JOIN donationfund_fun f ON r.fund_id = f.fun_ID
        WHERE r.status = 'approved' AND YEAR(r.approved_date) = ?
        GROUP BY f.fun_Name
        ORDER BY total DESC
    ");
    $stmt->execute([$year]);
    $funds = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Status breakdown (all years)
    $stmt = $con->prepare("
        SELECT status, COUNT(*) as count, COALESCE(SUM(amount),0) as total
        FROM fin_requisitions
        WHERE YEAR(requested_date) = ?
        GROUP BY status
    ");
    $stmt->execute([$year]);
    $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top 10 withdrawals
    $stmt = $con->prepare("
        SELECT r.id, r.description, r.amount, r.approved_date,
               COALESCE(f.fun_Name,'Unassigned') as fund,
               u.usr_UserName as requester
        FROM fin_requisitions r
        LEFT JOIN donationfund_fun f ON r.fund_id = f.fun_ID
        LEFT JOIN user_usr u ON r.requested_by = u.usr_per_ID
        WHERE r.status = 'approved' AND YEAR(r.approved_date) = ?
        ORDER BY r.amount DESC
        LIMIT 10
    ");
    $stmt->execute([$year]);
    $top = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // YTD total
    $stmt = $con->prepare("
        SELECT COALESCE(SUM(amount),0) as total, COUNT(*) as count
        FROM fin_requisitions
        WHERE status = 'approved' AND YEAR(approved_date) = ?
    ");
    $stmt->execute([$year]);
    $ytd = $stmt->fetch(PDO::FETCH_ASSOC);

    // Avg per month
    $stmt = $con->prepare("
        SELECT COALESCE(AVG(monthly_total),0) as avg
        FROM (
            SELECT SUM(amount) as monthly_total
            FROM fin_requisitions
            WHERE status = 'approved' AND YEAR(approved_date) = ?
            GROUP BY MONTH(approved_date)
        ) t
    ");
    $stmt->execute([$year]);
    $avg = $stmt->fetch(PDO::FETCH_ASSOC);

    $response->getBody()->write(json_encode([
        'monthly'  => $monthly,
        'funds'    => $funds,
        'statuses' => $statuses,
        'top'      => $top,
        'ytd'      => $ytd,
        'avg'      => $avg,
    ]));
    return $response->withHeader('Content-Type', 'application/json');
});