<?php
session_start();
require_once 'config.php';
require_once 'vendor/autoload.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in'])) {
    http_response_code(401);
    die('Unauthorized');
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$type = $_GET['type'] ?? '';
$checkId = $_GET['check_id'] ?? $_SESSION['last_check_id'] ?? 0;

if (!$checkId) {
    die('No check data available');
}

$pdo = getConnection();

switch ($type) {
    case 'duplicates':
        exportDuplicates($pdo, $checkId);
        break;
    case 'clean':
        exportCleanList($pdo, $checkId);
        break;
    default:
        die('Invalid export type');
}

function exportDuplicates($pdo, $checkId) {
    $stmt = $pdo->prepare(
        "SELECT name, barangay, birthday, match_type 
         FROM duplicate_records 
         WHERE check_id = ?"
    );
    $stmt->execute([$checkId]);
    $records = $stmt->fetchAll();
    
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Headers
    $sheet->setCellValue('A1', 'Name');
    $sheet->setCellValue('B1', 'Barangay');
    $sheet->setCellValue('C1', 'Birthday');
    $sheet->setCellValue('D1', 'Match Type');
    $sheet->setCellValue('E1', 'Status');
    
    // Style headers
    $headerStyle = [
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'FF0000']
        ],
        'font' => ['color' => ['rgb' => 'FFFFFF']]
    ];
    $sheet->getStyle('A1:E1')->applyFromArray($headerStyle);
    
    // Data
    $row = 2;
    foreach ($records as $record) {
        $sheet->setCellValue('A' . $row, $record['name']);
        $sheet->setCellValue('B' . $row, $record['barangay']);
        $sheet->setCellValue('C' . $row, $record['birthday']);
        $sheet->setCellValue('D' . $row, $record['match_type']);
        $sheet->setCellValue('E' . $row, 'DUPLICATE');
        $row++;
    }
    
    // Auto-size columns
    foreach (range('A', 'E') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="duplicate_beneficiaries_' . date('Y-m-d') . '.xlsx"');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
}

function exportCleanList($pdo, $checkId) {
    // Get clean records (this is simplified - you might want to store clean records separately)
    $stmt = $pdo->prepare(
        "SELECT name, barangay, birthday 
         FROM received_beneficiaries 
         WHERE id NOT IN (
             SELECT id FROM duplicate_records WHERE check_id = ?
         )"
    );
    $stmt->execute([$checkId]);
    $records = $stmt->fetchAll();
    
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Headers
    $sheet->setCellValue('A1', 'Name');
    $sheet->setCellValue('B1', 'Barangay');
    $sheet->setCellValue('C1', 'Birthday');
    
    // Style headers
    $headerStyle = [
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => '00FF00']
        ]
    ];
    $sheet->getStyle('A1:C1')->applyFromArray($headerStyle);
    
    // Data
    $row = 2;
    foreach ($records as $record) {
        $sheet->setCellValue('A' . $row, $record['name']);
        $sheet->setCellValue('B' . $row, $record['barangay']);
        $sheet->setCellValue('C' . $row, $record['birthday']);
        $row++;
    }
    
    // Auto-size columns
    foreach (range('A', 'C') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="clean_beneficiaries_' . date('Y-m-d') . '.xlsx"');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
}
?>