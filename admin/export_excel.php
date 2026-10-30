<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('owner');
[$from, $to] = dateFilters();
$rows = db(
    '
        SELECT
            o.order_number,
            o.created_at,
            COALESCE(u.name, o.guest_name) customer_name,
            c.name cashier_name,
            o.status,
            o.subtotal,
            o.discount_amount,
            o.total_amount
        FROM orders o
            LEFT JOIN users u ON u.id = o.user_id
            LEFT JOIN users c ON c.id = o.cashier_id
        WHERE
            o.created_at >= ?
            AND o.created_at < DATE_ADD(?, INTERVAL 1 DAY)
        ORDER BY o.id
    ',
    [$from, $to]
)->fetchAll(PDO::FETCH_NUM);
$headers = [
    'Order Number',
    'Order Date',
    'Customer / Guest Name',
    'Cashier',
    'Order Status',
    'Subtotal',
    'Discount',
    'Final Total'
];
$autoload = __DIR__ . '/../vendor/autoload.php';
$csv = input('format', $_GET) === 'csv';

if (!is_file($autoload) && !$csv) {
    http_response_code(503);
    pageStart('Ekspor Excel belum tersedia');
    echo '<div class="notice error">Dependensi PhpSpreadsheet belum terpasang. Jalankan composer install untuk XLSX.</div><a class="button" href="' .
        e(
            url(
                'admin/export_excel.php?' .
                    http_build_query(['from' => $from, 'to' => $to, 'format' => 'csv'])
            )
        ) .
        '">Unduh CSV sementara</a>';
    pageEnd();
    exit();
}
if ($csv) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="kopipiro-' . $from . '-' . $to . '.csv"');
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, $headers, ',', '"', '');
    foreach ($rows as $row) {
        foreach ($row as &$cellValue) {
            $cellValue = safeSpreadsheetText((string) $cellValue);
        }
        unset($cellValue);
        fputcsv($output, $row, ',', '"', '');
    }
    fclose($output);
    exit();
}

// XLSX menggunakan tipe sel eksplisit agar teks dari pengguna tidak menjadi formula.
require $autoload;
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Sales');
foreach ($headers as $i => $heading) {
    $sheet->setCellValueExplicit(
        [$i + 1, 1],
        $heading,
        DataType::TYPE_STRING
    );
}
foreach ($rows as $rowIndex => $row) {
    foreach ($row as $columnIndex => $value) {
        // Lima kolom pertama selalu teks, termasuk nama yang mirip formula Excel.
        $isMoneyColumn = $columnIndex >= 5;
        $cellValue = $isMoneyColumn ? (float) $value : (string) $value;
        $cellType = $isMoneyColumn ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING;

        $sheet->setCellValueExplicit(
            [$columnIndex + 1, $rowIndex + 2],
            $cellValue,
            $cellType
        );
    }
}
$sheet->getStyle('A1:H1')->getFont()->setBold(true);
$sheet->freezePane('A2');
$sheet->setAutoFilter('A1:H' . max(1, count($rows) + 1));
if ($rows) {
    $sheet
        ->getStyle('F2:H' . (count($rows) + 1))
        ->getNumberFormat()
        ->setFormatCode('"Rp" #,##0.00');
}
foreach (range('A', 'H') as $columnIndex) {
    $sheet->getColumnDimension($columnIndex)->setAutoSize(true);
}
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="kopipiro-' . $from . '-' . $to . '.xlsx"');
(new Xlsx($spreadsheet))->save('php://output');
$spreadsheet->disconnectWorksheets();
exit();
