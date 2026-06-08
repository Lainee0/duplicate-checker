<?php
session_start();
require_once 'config.php';
require_once 'vendor/autoload.php'; // For PhpSpreadsheet

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

use PhpOffice\PhpSpreadsheet\IOFactory;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'import_master':
            handleMasterImport();
            break;
        case 'check_duplicates':
            handleDuplicateCheck();
            break;
        case 'get_check_details':
            handleGetDetails();
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function handleMasterImport() {
    if (!isset($_FILES['masterFile'])) {
        throw new Exception('No file uploaded');
    }
    
    $file = $_FILES['masterFile'];
    $batchName = $_POST['batchName'] ?? 'Unnamed Batch';
    
    // Validate file
    $allowedTypes = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 
                     'application/vnd.ms-excel'];
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Invalid file type. Please upload an Excel file.');
    }
    
    // Read Excel file
    $spreadsheet = IOFactory::load($file['tmp_name']);
    $worksheet = $spreadsheet->getActiveSheet();
    $data = $worksheet->toArray();
    
    if (count($data) < 2) {
        throw new Exception('File is empty or has no data rows');
    }
    
    // Get headers from first row
    $headers = array_map('strtolower', array_map('trim', $data[0]));
    
    // Find column indexes
    $nameCol = array_search('name', $headers);
    $barangayCol = array_search('barangay', $headers);
    $birthdayCol = array_search('birthday', $headers);
    
    if ($nameCol === false || $barangayCol === false || $birthdayCol === false) {
        throw new Exception('Missing required columns. File must contain: Name, Barangay, Birthday');
    }
    
    // Process data
    $pdo = getConnection();
    $pdo->beginTransaction();
    
    try {
        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO received_beneficiaries (name, barangay, birthday, batch_reference) 
             VALUES (?, ?, ?, ?)"
        );
        
        $imported = 0;
        $skipped = 0;
        
        for ($i = 1; $i < count($data); $i++) {
            $row = $data[$i];
            
            // Skip empty rows
            if (empty($row[$nameCol]) && empty($row[$barangayCol]) && empty($row[$birthdayCol])) {
                continue;
            }
            
            $name = strtoupper(trim($row[$nameCol]));
            $barangay = strtoupper(trim($row[$barangayCol]));
            $birthday = formatDate($row[$birthdayCol]);
            
            if ($stmt->execute([$name, $barangay, $birthday, $batchName])) {
                if ($stmt->rowCount() > 0) {
                    $imported++;
                } else {
                    $skipped++;
                }
            }
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Successfully imported $imported records" . ($skipped > 0 ? " ($skipped duplicates skipped)" : ""),
            'imported' => $imported,
            'skipped' => $skipped
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function handleDuplicateCheck() {
    if (!isset($_FILES['newFile'])) {
        throw new Exception('No file uploaded');
    }
    
    $file = $_FILES['newFile'];
    $matchName = $_POST['match_name'] === 'true';
    $matchBarangay = $_POST['match_barangay'] === 'true';
    $matchBirthday = $_POST['match_birthday'] === 'true';
    $fuzzyMatch = $_POST['fuzzy_match'] === 'true';
    
    // Read Excel file
    $spreadsheet = IOFactory::load($file['tmp_name']);
    $worksheet = $spreadsheet->getActiveSheet();
    $data = $worksheet->toArray();
    
    if (count($data) < 2) {
        throw new Exception('File is empty or has no data rows');
    }
    
    // Get headers
    $headers = array_map('strtolower', array_map('trim', $data[0]));
    
    // Find column indexes
    $nameCol = array_search('name', $headers);
    $barangayCol = array_search('barangay', $headers);
    $birthdayCol = array_search('birthday', $headers);
    
    if ($nameCol === false || $barangayCol === false || $birthdayCol === false) {
        throw new Exception('Missing required columns. File must contain: Name, Barangay, Birthday');
    }
    
    // Process new beneficiaries
    $newBeneficiaries = [];
    for ($i = 1; $i < count($data); $i++) {
        $row = $data[$i];
        
        if (empty($row[$nameCol]) && empty($row[$barangayCol]) && empty($row[$birthdayCol])) {
            continue;
        }
        
        $newBeneficiaries[] = [
            'name' => strtoupper(trim($row[$nameCol])),
            'barangay' => strtoupper(trim($row[$barangayCol])),
            'birthday' => formatDate($row[$birthdayCol])
        ];
    }
    
    // Find duplicates
    $pdo = getConnection();
    $duplicates = [];
    $batchName = date('Y-m-d H:i:s') . ' Check';
    
    // Build query based on matching criteria
    $conditions = [];
    $params = [];
    
    foreach ($newBeneficiaries as $beneficiary) {
        $query = "SELECT * FROM received_beneficiaries WHERE 1=1";
        $queryParams = [];
        $matchTypes = [];
        
        if ($matchName) {
            if ($fuzzyMatch) {
                // Fuzzy matching using SOUNDEX or similar
                $query .= " AND SOUNDEX(name) = SOUNDEX(?)";
                $queryParams[] = $beneficiary['name'];
                $matchTypes[] = "Fuzzy Name";
            } else {
                $query .= " AND name = ?";
                $queryParams[] = $beneficiary['name'];
                $matchTypes[] = "Name";
            }
        }
        
        if ($matchBarangay) {
            $query .= " AND barangay = ?";
            $queryParams[] = $beneficiary['barangay'];
            $matchTypes[] = "Barangay";
        }
        
        if ($matchBirthday) {
            $query .= " AND birthday = ?";
            $queryParams[] = $beneficiary['birthday'];
            $matchTypes[] = "Birthday";
        }
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($queryParams);
        $results = $stmt->fetchAll();
        
        if (count($results) > 0) {
            $duplicates[] = [
                'name' => $beneficiary['name'],
                'barangay' => $beneficiary['barangay'],
                'birthday' => $beneficiary['birthday'],
                'match_type' => implode(' + ', $matchTypes)
            ];
        }
    }
    
    // Save check history
    $pdo->beginTransaction();
    try {
        // Insert check history
        $stmt = $pdo->prepare(
            "INSERT INTO check_history (batch_name, total_checked, duplicates_found, clean_records) 
             VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([
            $batchName,
            count($newBeneficiaries),
            count($duplicates),
            count($newBeneficiaries) - count($duplicates)
        ]);
        
        $checkId = $pdo->lastInsertId();
        
        // Store duplicate records
        if (count($duplicates) > 0) {
            $stmt = $pdo->prepare(
                "INSERT INTO duplicate_records (check_id, name, barangay, birthday, match_type) 
                 VALUES (?, ?, ?, ?, ?)"
            );
            
            foreach ($duplicates as $duplicate) {
                $stmt->execute([
                    $checkId,
                    $duplicate['name'],
                    $duplicate['barangay'],
                    $duplicate['birthday'],
                    $duplicate['match_type']
                ]);
            }
        }
        
        $pdo->commit();
        
        // Store check ID in session for export
        $_SESSION['last_check_id'] = $checkId;
        
        echo json_encode([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_checked' => count($newBeneficiaries),
                    'duplicates' => count($duplicates),
                    'clean' => count($newBeneficiaries) - count($duplicates)
                ],
                'duplicates' => $duplicates,
                'check_id' => $checkId
            ]
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function handleGetDetails() {
    $checkId = $_POST['check_id'] ?? 0;
    
    $pdo = getConnection();
    $stmt = $pdo->prepare(
        "SELECT * FROM duplicate_records WHERE check_id = ?"
    );
    $stmt->execute([$checkId]);
    $records = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $records
    ]);
}

function formatDate($date) {
    // Handle various date formats
    if (empty($date)) {
        return null;
    }
    
    // If it's a DateTime object from PhpSpreadsheet
    if ($date instanceof \DateTime) {
        return $date->format('Y-m-d');
    }
    
    // Try to parse string date
    $timestamp = strtotime($date);
    if ($timestamp) {
        return date('Y-m-d', $timestamp);
    }
    
    return null;
}

?>