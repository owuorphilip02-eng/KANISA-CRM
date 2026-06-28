<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

$app->get('/balance', function (Request $request, Response $response) {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    $pageArgs = [
        'sRootPath'     => SystemURLs::getRootPath(),
        'sPageTitle'    => gettext('Balance Analytics'),
        'sPageSubtitle' => gettext('Income vs expenses trends and fund balances'),
        'aBreadcrumbs'  => PageHeader::breadcrumbs([
            ['Finance', 'finance/'],
            ['Balance Analytics'],
        ]),
        'sPageHeaderButtons' => PageHeader::buttons([]),
    ];

    return $renderer->render($response, 'balance.php', $pageArgs);
});

$app->get('/balance/data', function (Request $request, Response $response) {
    try {
        $con  = \Propel\Runtime\Propel::getConnection();
        $params = $request->getQueryParams();
$year = isset($params['year']) ? intval($params['year']) : intval(date('Y'));

        // Monthly income
        $stmt = $con->prepare("
            SELECT MONTH(plg_date) as month, SUM(plg_amount) as total
            FROM pledge_plg
            WHERE plg_PledgeOrPayment = 'Payment' AND YEAR(plg_date) = ?
            GROUP BY MONTH(plg_date) ORDER BY month
        ");
        $stmt->execute([$year]);
        $monthlyIncome = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $monthlyIncome[(int)$row['month']] = floatval($row['total']);
        }

        // Monthly expenses
        $stmt = $con->prepare("
            SELECT MONTH(approved_date) as month, SUM(amount) as total
            FROM fin_requisitions
            WHERE status = 'approved' AND YEAR(approved_date) = ?
            GROUP BY MONTH(approved_date) ORDER BY month
        ");
        $stmt->execute([$year]);
        $monthlyExpense = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $monthlyExpense[(int)$row['month']] = floatval($row['total']);
        }

        // Running balance
        $running = 0;
        $runningBalance = [];
        $monthlyBalance = [];
        for ($m = 1; $m <= 12; $m++) {
            $inc = $monthlyIncome[$m]  ?? 0;
            $exp = $monthlyExpense[$m] ?? 0;
            $net = $inc - $exp;
            $running += $net;
            $monthlyBalance[$m]  = $net;
            $runningBalance[$m]  = $running;
        }

        // Fund income
        $stmt = $con->prepare("
            SELECT f.fun_Name as fund, COALESCE(SUM(p.plg_amount), 0) as income
            FROM donationfund_fun f
            LEFT JOIN pledge_plg p ON p.plg_fundID = f.fun_ID
                AND p.plg_PledgeOrPayment = 'Payment'
                AND YEAR(p.plg_date) = ?
            WHERE f.fun_Active = 'true'
            GROUP BY f.fun_Name ORDER BY income DESC
        ");
        $stmt->execute([$year]);
        $fundIncome = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fund expense
        $stmt = $con->prepare("
            SELECT COALESCE(f.fun_Name, 'Unassigned') as fund,
                   COALESCE(SUM(r.amount), 0) as expense
            FROM fin_requisitions r
            LEFT JOIN donationfund_fun f ON r.fund_id = f.fun_ID
            WHERE r.status = 'approved' AND YEAR(r.approved_date) = ?
            GROUP BY f.fun_Name
        ");
        $stmt->execute([$year]);
        $fundExpense = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $fundExpense[$row['fund']] = floatval($row['expense']);
        }

        $fundBalance = [];
        foreach ($fundIncome as $f) {
            $exp = $fundExpense[$f['fund']] ?? 0;
            $fundBalance[] = [
                'fund'    => $f['fund'],
                'income'  => floatval($f['income']),
                'expense' => $exp,
                'balance' => floatval($f['income']) - $exp,
            ];
        }

        // YTD totals
        $stmt = $con->prepare("SELECT COALESCE(SUM(plg_amount),0) FROM pledge_plg WHERE plg_PledgeOrPayment = 'Payment' AND YEAR(plg_date) = ?");
        $stmt->execute([$year]);
        $ytdIncome = floatval($stmt->fetchColumn());

        $stmt = $con->prepare("SELECT COALESCE(SUM(amount),0) FROM fin_requisitions WHERE status = 'approved' AND YEAR(approved_date) = ?");
        $stmt->execute([$year]);
        $ytdExpense = floatval($stmt->fetchColumn());

        // Status breakdown
        $stmt = $con->prepare("SELECT status, COUNT(*) as count, COALESCE(SUM(amount),0) as total FROM fin_requisitions WHERE YEAR(requested_date) = ? GROUP BY status");
        $stmt->execute([$year]);
        $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [
            'monthlyIncome'  => $monthlyIncome,
            'monthlyExpense' => $monthlyExpense,
            'monthlyBalance' => $monthlyBalance,
            'runningBalance' => $runningBalance,
            'fundBalance'    => $fundBalance,
            'ytdIncome'      => $ytdIncome,
            'ytdExpense'     => $ytdExpense,
            'ytdBalance'     => $ytdIncome - $ytdExpense,
            'statuses'       => $statuses,
        ];

        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');

    } catch (\Throwable $e) {
        $response->getBody()->write(json_encode([
            'error' => $e->getMessage(),
            'file'  => $e->getFile(),
            'line'  => $e->getLine(),
        ]));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    }
});