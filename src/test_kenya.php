<?php
require 'vendor/autoload.php';
try {
    $h = Yasumi\Yasumi::create('Kenya', 2026);
    echo count($h->getHolidays()) . ' holidays found';
    foreach ($h->getHolidays() as $holiday) {
        echo "\n" . $holiday->format('Y-m-d') . ' - ' . $holiday->getName();
    }
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage();
}