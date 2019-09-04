<?php
    ini_set('display_errors', 0);
    require_once 'composer/excelParser.alkondev.php';

    $xlsx = new PHPExcel();

    $xlsx->XLSXWriter( 'File', $_POST );

    exit;
?>