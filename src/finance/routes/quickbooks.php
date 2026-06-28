<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

$app->get('/quickbooks', function (Request $request, Response $response) {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    $pageArgs = [
        'sRootPath'     => SystemURLs::getRootPath(),
        'sPageTitle'    => gettext('QuickBooks Summary'),
        'sPageSubtitle' => gettext('Import and analyse accountant records'),
        'aBreadcrumbs'  => PageHeader::breadcrumbs([
            ['Finance', 'finance/'],
            ['QuickBooks Summary'],
        ]),
        'sPageHeaderButtons' => PageHeader::buttons([]),
    ];

    return $renderer->render($response, 'quickbooks.php', $pageArgs);
});

$app->post('/quickbooks/import', function (Request $request, Response $response) {
    $uploadedFiles = $request->getUploadedFiles();
    $file = $uploadedFiles['qbfile'] ?? null;

    if (!$file || $file->getError() !== UPLOAD_ERR_OK) {
        $response->getBody()->write(json_encode(['success' => false, 'error' => 'No file uploaded']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    $filename   = $file->getClientFilename();
    $ext        = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $tmpPath    = sys_get_temp_dir() . '/' . uniqid('qb_') . '.' . $ext;
    $file->moveTo($tmpPath);

    $pdo = Propel::getConnection();
    $imported = 0;
    $errors   = [];

    if ($ext === 'csv') {
        $handle = fopen($tmpPath, 'r');
        $headers = null;
        while (($row = fgetcsv($handle)) !== false) {
            if (!$headers) { $headers = array_map('trim', $row); continue; }
            $data = array_combine($headers, $row);

            $date     = $data['Date'] ?? $data['date'] ?? null;
            $type     = $data['Transaction Type'] ?? $data['Type'] ?? '';
            $account  = $data['Account'] ?? $data['account'] ?? '';
            $name     = $data['Name'] ?? $data['name'] ?? '';
            $memo     = $data['Memo'] ?? $data['memo'] ?? '';
            $amount   = floatval(str_replace([',', '$', 'KES'], '', $data['Amount'] ?? $data['amount'] ?? 0));
            $fund     = $data['Class'] ?? $data['Fund'] ?? '';
            $category = $amount >= 0 ? 'income' : 'expense';
            $amount   = abs($amount);

            if (!$date) continue;

            try {
                $stmt = $pdo->prepare("INSERT INTO quickbooks_imports 
                    (filename, file_type, transaction_date, transaction_type, account, name, memo, amount, fund, category)
                    VALUES (?, 'csv', ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$filename, date('Y-m-d', strtotime($date)), $type, $account, $name, $memo, $amount, $fund, $category]);
                $imported++;
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
        fclose($handle);

    } elseif ($ext === 'qbo') {
        $xml = simplexml_load_file($tmpPath);
        if ($xml) {
            foreach ($xml->STMTTRN as $trn) {
                $date     = (string)$trn->DTPOSTED;
                $date     = substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2);
                $amount   = floatval((string)$trn->TRNAMT);
                $category = $amount >= 0 ? 'income' : 'expense';
                $memo     = (string)$trn->MEMO;
                $name     = (string)$trn->NAME;
                $type     = (string)$trn->TRNTYPE;

                try {
                    $stmt = $pdo->prepare("INSERT INTO quickbooks_imports 
                        (filename, file_type, transaction_date, transaction_type, name, memo, amount, fund, category)
                        VALUES (?, 'qbo', ?, ?, ?, ?, ?, '', ?)");
                    $stmt->execute([$filename, $date, $type, $name, $memo, abs($amount), $category]);
                    $imported++;
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }
    }

    unlink($tmpPath);
    $response->getBody()->write(json_encode(['success' => true, 'imported' => $imported, 'errors' => $errors]));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/quickbooks/data', function (Request $request, Response $response) {
    $pdo    = Propel::getConnection();
    $params = $request->getQueryParams();
    $period = $params['period'] ?? 'yearly';
    $year   = $params['year'] ?? date('Y');
    $month  = $params['month'] ?? date('m');

    $where  = "WHERE YEAR(transaction_date) = ?";
    $binds  = [$year];

    if ($period === 'monthly') {
        $where .= " AND MONTH(transaction_date) = ?";
        $binds[] = $month;
    }

    // P&L totals
    $stmt = $pdo->prepare("SELECT category, SUM(amount) as total FROM quickbooks_imports $where GROUP BY category");
    $stmt->execute($binds);
    $pl = ['income' => 0, 'expense' => 0];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $pl[$row['category']] = floatval($row['total']);
    }

    // Fund breakdown
    $stmt = $pdo->prepare("SELECT fund, category, SUM(amount) as total FROM quickbooks_imports $where AND fund != '' GROUP BY fund, category");
    $stmt->execute($binds);
    $funds = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $funds[$row['fund']][$row['category']] = floatval($row['total']);
    }

    // Monthly totals
    $stmt = $pdo->prepare("SELECT MONTH(transaction_date) as month, category, SUM(amount) as total 
        FROM quickbooks_imports WHERE YEAR(transaction_date) = ? GROUP BY MONTH(transaction_date), category ORDER BY month");
    $stmt->execute([$year]);
    $monthly = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $monthly[$row['month']][$row['category']] = floatval($row['total']);
    }

    // Recent transactions
    $stmt = $pdo->prepare("SELECT * FROM quickbooks_imports $where ORDER BY transaction_date DESC LIMIT 50");
    $stmt->execute($binds);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [
        'pl'           => $pl,
        'funds'        => $funds,
        'monthly'      => $monthly,
        'transactions' => $transactions,
        'balance'      => $pl['income'] - $pl['expense'],
    ];

    $response->getBody()->write(json_encode($result));
    return $response->withHeader('Content-Type', 'application/json');
});