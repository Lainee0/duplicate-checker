<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$action = $input['action'] ?? '';

switch ($action) {
    case 'scan_duplicates':
        scanDuplicates($input);
        break;
    case 'get_duplicate_details':
        getDuplicateDetails($input);
        break;
    case 'get_batch_list':
        getBatchList();
        break;
    case 'scan_specific_batch':
        scanSpecificBatch($input);
        break;
    case 'delete_duplicate':
        deleteDuplicate($input);
        break;
    case 'delete_duplicates':
        deleteDuplicates($input);
        break;
    case 'delete_all_duplicates':
        deleteAllDuplicates($input);
        break;
    case 'get_clean_records':
        getCleanRecords($input);
        break;
    case 'delete_history':
        deleteHistory($input);
        break;
    case 'accept_as_clean':
        acceptAsClean($input);
        break;
    case 'accept_selected_as_clean':
        acceptSelectedAsClean($input);
        break;
    case 'accept_all_as_clean':
        acceptAllAsClean($input);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function scanDuplicates($params) {
    try {
        $pdo = getConnection();
        
        $batchReference = $params['batch_reference'] ?? null;
        
        // Build query for beneficiaries - only get records that are NOT marked as accepted
        $sql = "SELECT * FROM received_beneficiaries";
        $queryParams = [];
        
        // Add condition to exclude accepted duplicates if column exists
        try {
            $columns = $pdo->query("SHOW COLUMNS FROM received_beneficiaries")->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('is_accepted_clean', $columns)) {
                $sql .= " WHERE (is_accepted_clean IS NULL OR is_accepted_clean = 0)";
                if ($batchReference) {
                    $sql .= " AND batch_reference = ?";
                    $queryParams[] = $batchReference;
                }
            } else {
                if ($batchReference) {
                    $sql .= " WHERE batch_reference = ?";
                    $queryParams[] = $batchReference;
                }
            }
        } catch (Exception $e) {
            if ($batchReference) {
                $sql .= " WHERE batch_reference = ?";
                $queryParams[] = $batchReference;
            }
        }
        
        $sql .= " ORDER BY id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($queryParams);
        $beneficiaries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($beneficiaries)) {
            echo json_encode(['success' => false, 'message' => 'No beneficiaries found in the selected batch. Please import a list first.']);
            return;
        }
        
        // Get batch info
        $batchRef = $batchReference;
        if (!$batchRef && !empty($beneficiaries)) {
            $batchRef = $beneficiaries[0]['batch_reference'] ?? 'Unknown';
        }
        
        // Find duplicates only for matching normalized names
        $duplicates = [];
        $cleanRecords = [];
        $acceptedIds = [];
        $nameGroups = [];
        
        // First, get IDs of records already accepted as clean
        try {
            $columns = $pdo->query("SHOW COLUMNS FROM received_beneficiaries")->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('is_accepted_clean', $columns)) {
                $stmt = $pdo->prepare("SELECT id FROM received_beneficiaries WHERE is_accepted_clean = 1 AND batch_reference = ?");
                $stmt->execute([$batchRef]);
                $acceptedIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
        } catch (Exception $e) {
            $acceptedIds = [];
        }
        
        foreach ($beneficiaries as $beneficiary) {
            if (in_array($beneficiary['id'], $acceptedIds)) {
                continue;
            }

            $originalName = trim($beneficiary['full_name'] ?? $beneficiary['name'] ?? '');
            if (empty($originalName)) {
                continue;
            }

            $normalizedName = normalizeName($originalName);
            if (empty($normalizedName)) {
                continue;
            }

            $nameGroups[$normalizedName][] = $beneficiary;
        }

        foreach ($nameGroups as $normalizedName => $groupMembers) {
            if (count($groupMembers) > 1) {
                foreach ($groupMembers as $member) {
                    $duplicates[] = [
                        'id' => $member['id'],
                        'full_name' => trim($member['full_name'] ?? $member['name'] ?? ''),
                        'barangay' => $member['barangay'] ?? '',
                        'birthday' => $member['birthday'] ?? '',
                        'batch_reference' => $member['batch_reference'] ?? '',
                        'match_level' => 100,
                        'match_details' => 'Exact matching name',
                        'matched_with_id' => null
                    ];
                }
            } else {
                $member = $groupMembers[0];
                $cleanRecords[] = [
                    'id' => $member['id'],
                    'full_name' => trim($member['full_name'] ?? $member['name'] ?? ''),
                    'barangay' => $member['barangay'] ?? '',
                    'birthday' => $member['birthday'] ?? '',
                    'batch_reference' => $member['batch_reference'] ?? ''
                ];
            }
        }

        // Sort duplicates by name
        usort($duplicates, function($a, $b) {
            return strcmp($a['full_name'], $b['full_name']);
        });
        
        $finalCleanRecords = $cleanRecords;
        $totalChecked = count($beneficiaries);
        $duplicatesFound = count($duplicates);
        $cleanRecordsCount = count($finalCleanRecords);
        $duplicatePercentage = $totalChecked > 0 ? round(($duplicatesFound / $totalChecked) * 100, 2) : 0;
        
        // Store results in session
        $_SESSION['last_duplicate_check'] = [
            'duplicates' => $duplicates,
            'clean_records' => $finalCleanRecords,
            'total_checked' => $totalChecked,
            'duplicates_found' => $duplicatesFound,
            'clean_records_count' => $cleanRecordsCount,
            'duplicate_percentage' => $duplicatePercentage,
            'batch_reference' => $batchRef
        ];
        
        echo json_encode([
            'success' => true,
            'total_checked' => $totalChecked,
            'duplicates_found' => $duplicatesFound,
            'clean_records' => $cleanRecordsCount,
            'duplicate_percentage' => $duplicatePercentage,
            'duplicates' => $duplicates,
            'clean_records_data' => $finalCleanRecords,
            'batch_reference' => $batchRef
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// Function to accept a record as clean (remove from duplicate list)
function acceptAsClean($params) {
    try {
        $id = $params['id'] ?? 0;
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'No ID provided']);
            return;
        }
        
        $pdo = getConnection();
        
        // Check if is_accepted_clean column exists, if not create it
        try {
            $columns = $pdo->query("SHOW COLUMNS FROM received_beneficiaries")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('is_accepted_clean', $columns)) {
                $pdo->exec("ALTER TABLE received_beneficiaries ADD COLUMN is_accepted_clean TINYINT(1) DEFAULT 0");
            }
            if (!in_array('accepted_date', $columns)) {
                $pdo->exec("ALTER TABLE received_beneficiaries ADD COLUMN accepted_date DATETIME DEFAULT NULL");
            }
        } catch (Exception $e) {
            // Column might already exist
        }
        
        $stmt = $pdo->prepare("UPDATE received_beneficiaries SET is_accepted_clean = 1, accepted_date = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Record accepted as clean and removed from duplicate list']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Function to accept selected records as clean
function acceptSelectedAsClean($params) {
    try {
        $ids = $params['ids'] ?? [];
        if (empty($ids)) {
            echo json_encode(['success' => false, 'message' => 'No IDs provided']);
            return;
        }
        
        $pdo = getConnection();
        
        // Check if is_accepted_clean column exists
        try {
            $columns = $pdo->query("SHOW COLUMNS FROM received_beneficiaries")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('is_accepted_clean', $columns)) {
                $pdo->exec("ALTER TABLE received_beneficiaries ADD COLUMN is_accepted_clean TINYINT(1) DEFAULT 0");
            }
            if (!in_array('accepted_date', $columns)) {
                $pdo->exec("ALTER TABLE received_beneficiaries ADD COLUMN accepted_date DATETIME DEFAULT NULL");
            }
        } catch (Exception $e) {
            // Column might already exist
        }
        
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("UPDATE received_beneficiaries SET is_accepted_clean = 1, accepted_date = NOW() WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        
        echo json_encode(['success' => true, 'message' => count($ids) . ' record(s) accepted as clean']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Function to accept all duplicates as clean
function acceptAllAsClean($params) {
    try {
        $batchReference = $params['batch_reference'] ?? null;
        
        $pdo = getConnection();
        
        // Check if is_accepted_clean column exists
        try {
            $columns = $pdo->query("SHOW COLUMNS FROM received_beneficiaries")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('is_accepted_clean', $columns)) {
                $pdo->exec("ALTER TABLE received_beneficiaries ADD COLUMN is_accepted_clean TINYINT(1) DEFAULT 0");
            }
            if (!in_array('accepted_date', $columns)) {
                $pdo->exec("ALTER TABLE received_beneficiaries ADD COLUMN accepted_date DATETIME DEFAULT NULL");
            }
        } catch (Exception $e) {
            // Column might already exist
        }
        
        if ($batchReference) {
            $stmt = $pdo->prepare("UPDATE received_beneficiaries SET is_accepted_clean = 1, accepted_date = NOW() WHERE batch_reference = ?");
            $stmt->execute([$batchReference]);
        } else {
            $stmt = $pdo->query("SELECT DISTINCT batch_reference FROM received_beneficiaries ORDER BY id DESC LIMIT 1");
            $latest = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($latest) {
                $updateStmt = $pdo->prepare("UPDATE received_beneficiaries SET is_accepted_clean = 1, accepted_date = NOW() WHERE batch_reference = ?");
                $updateStmt->execute([$latest['batch_reference']]);
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'All records accepted as clean']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getFirstName($fullName) {
    $nameParts = explode(' ', trim($fullName));
    return $nameParts[0] ?? '';
}

function getLastName($fullName) {
    $nameParts = explode(' ', trim($fullName));
    if (count($nameParts) > 1) {
        return end($nameParts);
    }
    return '';
}

function normalizeName($name) {
    $name = strtolower($name);
    $name = preg_replace('/\b(jr|sr|ii|iii|iv|md|dr|prof|atty|hon)\b\.?\s*/i', '', $name);
    $name = preg_replace('/[^\p{L}\p{N}\s]/u', '', $name);
    
    $replacements = [
        'ñ' => 'n',
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
    ];
    
    foreach ($replacements as $search => $replace) {
        $name = str_replace($search, $replace, $name);
    }
    
    $name = preg_replace('/\s+/', ' ', $name);
    return trim($name);
}

function scanSpecificBatch($params) {
    scanDuplicates($params);
}

function getBatchList() {
    try {
        $pdo = getConnection();
        $columns = $pdo->query("SHOW COLUMNS FROM received_beneficiaries")->fetchAll(PDO::FETCH_COLUMN);
        
        if (in_array('batch_reference', $columns)) {
            $stmt = $pdo->query("SELECT DISTINCT batch_reference FROM received_beneficiaries WHERE batch_reference IS NOT NULL ORDER BY id DESC");
            $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $batches = [];
        }
        
        echo json_encode([
            'success' => true,
            'batches' => $batches
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage(), 'batches' => []]);
    }
}

function getDuplicateDetails($params) {
    try {
        $id = $params['id'] ?? 0;
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'No ID provided']);
            return;
        }
        
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT * FROM received_beneficiaries WHERE id = ?");
        $stmt->execute([$id]);
        $beneficiary = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($beneficiary) {
            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $beneficiary['id'],
                    'full_name' => $beneficiary['full_name'] ?? $beneficiary['name'] ?? '',
                    'barangay' => $beneficiary['barangay'] ?? '',
                    'birthday' => $beneficiary['birthday'] ?? '',
                    'batch_reference' => $beneficiary['batch_reference'] ?? 'N/A'
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Beneficiary not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function deleteDuplicate($params) {
    try {
        $id = $params['id'] ?? 0;
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'No ID provided']);
            return;
        }
        
        $pdo = getConnection();
        $stmt = $pdo->prepare("DELETE FROM received_beneficiaries WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Record deleted successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteDuplicates($params) {
    try {
        $ids = $params['ids'] ?? [];
        if (empty($ids)) {
            echo json_encode(['success' => false, 'message' => 'No IDs provided']);
            return;
        }
        
        $pdo = getConnection();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("DELETE FROM received_beneficiaries WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        
        echo json_encode(['success' => true, 'message' => count($ids) . ' record(s) deleted successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteAllDuplicates($params) {
    try {
        $batchReference = $params['batch_reference'] ?? null;
        
        $pdo = getConnection();
        
        if ($batchReference) {
            $stmt = $pdo->prepare("DELETE FROM received_beneficiaries WHERE batch_reference = ?");
            $stmt->execute([$batchReference]);
        } else {
            $stmt = $pdo->query("SELECT DISTINCT batch_reference FROM received_beneficiaries ORDER BY id DESC LIMIT 1");
            $latest = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($latest) {
                $deleteStmt = $pdo->prepare("DELETE FROM received_beneficiaries WHERE batch_reference = ?");
                $deleteStmt->execute([$latest['batch_reference']]);
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'All duplicates deleted successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getCleanRecords($params) {
    try {
        if (isset($_SESSION['last_duplicate_check']['clean_records']) && !empty($_SESSION['last_duplicate_check']['clean_records'])) {
            echo json_encode([
                'success' => true, 
                'records' => $_SESSION['last_duplicate_check']['clean_records']
            ]);
        } else {
            $pdo = getConnection();
            $stmt = $pdo->query("SELECT * FROM received_beneficiaries WHERE is_accepted_clean = 1 OR is_accepted_clean IS NULL ORDER BY id");
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $formattedRecords = [];
            foreach ($records as $record) {
                $formattedRecords[] = [
                    'id' => $record['id'],
                    'full_name' => $record['full_name'] ?? $record['name'] ?? '',
                    'barangay' => $record['barangay'] ?? '',
                    'birthday' => $record['birthday'] ?? '',
                    'batch_reference' => $record['batch_reference'] ?? ''
                ];
            }
            
            echo json_encode(['success' => true, 'records' => $formattedRecords]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteHistory($params) {
    try {
        $id = $params['id'] ?? 0;
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'No ID provided']);
            return;
        }
        
        $pdo = getConnection();
        $stmt = $pdo->prepare("DELETE FROM check_history WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'History record deleted successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>