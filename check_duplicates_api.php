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

// Function to parse full name in format "Lastname, Firstname Middlename"
function parseFullName($fullName) {
    $result = [
        'lastname' => '',
        'firstname' => '',
        'middlename' => '',
        'suffix' => '',
        'original' => $fullName
    ];
    
    if (empty($fullName)) return $result;
    
    // Check if name contains comma (Lastname, Firstname format)
    if (strpos($fullName, ',') !== false) {
        $parts = explode(',', $fullName, 2);
        $result['lastname'] = trim($parts[0]);
        $nameParts = trim($parts[1]);
        
        // Split firstname and middlename
        $nameSegments = preg_split('/\s+/', $nameParts);
        if (count($nameSegments) > 0) {
            $result['firstname'] = $nameSegments[0];
            if (count($nameSegments) > 1) {
                $result['middlename'] = implode(' ', array_slice($nameSegments, 1));
            }
        }
    } else {
        // No comma, assume "Firstname Lastname" format
        $nameSegments = preg_split('/\s+/', $fullName);
        if (count($nameSegments) >= 2) {
            $result['firstname'] = $nameSegments[0];
            $result['lastname'] = implode(' ', array_slice($nameSegments, 1));
        } else {
            $result['firstname'] = $fullName;
        }
    }
    
    // Remove common suffixes from lastname
    $suffixes = ['JR', 'SR', 'II', 'III', 'IV', 'MD', 'DR', 'RN'];
    foreach ($suffixes as $suffix) {
        if (preg_match('/\b' . $suffix . '\b$/i', $result['lastname'])) {
            $result['suffix'] = $suffix;
            $result['lastname'] = preg_replace('/\b' . $suffix . '\b$/i', '', $result['lastname']);
            $result['lastname'] = trim($result['lastname']);
            break;
        }
    }
    
    return $result;
}

// Function to check if two first names are spelling variations (typos, missing letters, etc.)
function areSpellingVariations($name1, $name2) {
    if (empty($name1) || empty($name2)) return false;
    
    $name1 = strtolower(trim($name1));
    $name2 = strtolower(trim($name2));
    
    // Exact match
    if ($name1 === $name2) {
        return true;
    }
    
    // Calculate similarity percentage
    similar_text($name1, $name2, $percent);
    
    // For short names (3-4 letters), require higher similarity
    $minLength = min(strlen($name1), strlen($name2));
    if ($minLength <= 4) {
        return $percent >= 85;
    }
    
    // For longer names, 80% similarity is enough for typos
    return $percent >= 80;
}

function scanDuplicates($params) {
    try {
        $pdo = getConnection();
        
        $batchReference = $params['batch_reference'] ?? null;
        
        // Build query for beneficiaries
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
        
        // Parse all names first
        $parsedBeneficiaries = [];
        foreach ($beneficiaries as $beneficiary) {
            $fullName = trim($beneficiary['full_name'] ?? $beneficiary['name'] ?? '');
            $parsed = parseFullName($fullName);
            $parsed['id'] = $beneficiary['id'];
            $parsed['original_data'] = $beneficiary;
            $parsedBeneficiaries[] = $parsed;
        }
        
        // Find duplicates based on lastname AND middlename matching exactly
        $duplicates = [];
        $cleanRecords = [];
        $processedIds = [];
        
        for ($i = 0; $i < count($parsedBeneficiaries); $i++) {
            $current = $parsedBeneficiaries[$i];
            
            if (in_array($current['id'], $processedIds)) {
                continue;
            }
            
            $matches = [];
            
            for ($j = $i + 1; $j < count($parsedBeneficiaries); $j++) {
                $compare = $parsedBeneficiaries[$j];
                
                // Check if last names match exactly (case-insensitive)
                $lastNameMatch = false;
                $middleNameMatch = false;
                
                if (!empty($current['lastname']) && !empty($compare['lastname'])) {
                    $lastNameMatch = (strtolower(trim($current['lastname'])) === strtolower(trim($compare['lastname'])));
                }
                
                // Check if middle names match exactly (case-insensitive)
                // Handle empty middlenames - if both are empty, consider as match
                $currentMiddle = strtolower(trim($current['middlename'] ?? ''));
                $compareMiddle = strtolower(trim($compare['middlename'] ?? ''));
                
                if (empty($currentMiddle) && empty($compareMiddle)) {
                    $middleNameMatch = true;
                } elseif (!empty($currentMiddle) && !empty($compareMiddle)) {
                    $middleNameMatch = ($currentMiddle === $compareMiddle);
                } else {
                    $middleNameMatch = false;
                }
                
                // Only proceed if BOTH lastname AND middlename match
                if ($lastNameMatch && $middleNameMatch) {
                    // Now check if first names are spelling variations
                    $isSpellingVariation = areSpellingVariations($current['firstname'], $compare['firstname']);
                    
                    if ($isSpellingVariation) {
                        // Calculate similarity percentage for display
                        similar_text(strtolower($current['firstname']), strtolower($compare['firstname']), $percent);
                        $matchLevel = round($percent);
                        
                        $matchDetails = "Same last name: {$current['lastname']}<br>";
                        $matchDetails .= "Same middle name: " . ($current['middlename'] ?: '(none)') . "<br>";
                        $matchDetails .= "First name: '{$current['firstname']}' vs '{$compare['firstname']}'<br>";
                        $matchDetails .= "Similarity: {$matchLevel}% - Possible typo/spelling variation";
                        
                        $matches[] = [
                            'id' => $compare['id'],
                            'full_name' => $compare['original_data']['full_name'] ?? $compare['original_data']['name'] ?? '',
                            'firstname' => $compare['firstname'],
                            'lastname' => $compare['lastname'],
                            'middlename' => $compare['middlename'],
                            'barangay' => $compare['original_data']['barangay'] ?? '',
                            'birthday' => $compare['original_data']['birthday'] ?? '',
                            'batch_reference' => $compare['original_data']['batch_reference'] ?? '',
                            'match_level' => $matchLevel,
                            'match_details' => $matchDetails
                        ];
                    }
                }
            }
            
            if (!empty($matches)) {
                // Add current record as primary
                $duplicates[] = [
                    'id' => $current['id'],
                    'full_name' => $current['original_data']['full_name'] ?? $current['original_data']['name'] ?? '',
                    'firstname' => $current['firstname'],
                    'lastname' => $current['lastname'],
                    'middlename' => $current['middlename'],
                    'barangay' => $current['original_data']['barangay'] ?? '',
                    'birthday' => $current['original_data']['birthday'] ?? '',
                    'batch_reference' => $current['original_data']['batch_reference'] ?? '',
                    'match_level' => 100,
                    'match_details' => "Original record (has potential duplicates with same last name and middle name)",
                    'is_primary' => true
                ];
                
                // Add matches
                foreach ($matches as $match) {
                    $duplicates[] = $match;
                    $processedIds[] = $match['id'];
                }
                
                $processedIds[] = $current['id'];
            } else {
                // No matches found, clean record
                $cleanRecords[] = [
                    'id' => $current['id'],
                    'full_name' => $current['original_data']['full_name'] ?? $current['original_data']['name'] ?? '',
                    'firstname' => $current['firstname'],
                    'lastname' => $current['lastname'],
                    'middlename' => $current['middlename'],
                    'barangay' => $current['original_data']['barangay'] ?? '',
                    'birthday' => $current['original_data']['birthday'] ?? '',
                    'batch_reference' => $current['original_data']['batch_reference'] ?? ''
                ];
            }
        }
        
        // Sort duplicates by last name then first name
        usort($duplicates, function($a, $b) {
            $lastNameComp = strcmp($a['lastname'], $b['lastname']);
            if ($lastNameComp === 0) {
                $middleNameComp = strcmp($a['middlename'], $b['middlename']);
                if ($middleNameComp === 0) {
                    return strcmp($a['firstname'], $b['firstname']);
                }
                return $middleNameComp;
            }
            return $lastNameComp;
        });
        
        $totalChecked = count($beneficiaries);
        $duplicatesFound = count($duplicates);
        $cleanRecordsCount = count($cleanRecords);
        $duplicatePercentage = $totalChecked > 0 ? round(($duplicatesFound / $totalChecked) * 100, 2) : 0;
        
        // Save to check history
        try {
            $columns = $pdo->query("SHOW COLUMNS FROM check_history")->fetchAll(PDO::FETCH_COLUMN);
            
            if (in_array('batch_reference', $columns)) {
                $stmt = $pdo->prepare("
                    INSERT INTO check_history (batch_reference, total_checked, duplicates_found, clean_records, check_date) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$batchRef, $totalChecked, $duplicatesFound, $cleanRecordsCount]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO check_history (total_checked, duplicates_found, clean_records, check_date) 
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$totalChecked, $duplicatesFound, $cleanRecordsCount]);
            }
        } catch (Exception $e) {
            error_log("Failed to save to check_history: " . $e->getMessage());
        }
        
        // Store results in session
        $_SESSION['last_duplicate_check'] = [
            'duplicates' => $duplicates,
            'clean_records' => $cleanRecords,
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
            'clean_records_data' => $cleanRecords,
            'batch_reference' => $batchRef
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
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
            $parsed = parseFullName($beneficiary['full_name'] ?? $beneficiary['name'] ?? '');
            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $beneficiary['id'],
                    'full_name' => $beneficiary['full_name'] ?? $beneficiary['name'] ?? '',
                    'lastname' => $parsed['lastname'],
                    'firstname' => $parsed['firstname'],
                    'middlename' => $parsed['middlename'],
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
            $stmt = $pdo->query("SELECT * FROM received_beneficiaries ORDER BY id");
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

function acceptAsClean($params) {
    try {
        $id = $params['id'] ?? 0;
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'No ID provided']);
            return;
        }
        
        $pdo = getConnection();
        
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

function acceptSelectedAsClean($params) {
    try {
        $ids = $params['ids'] ?? [];
        if (empty($ids)) {
            echo json_encode(['success' => false, 'message' => 'No IDs provided']);
            return;
        }
        
        $pdo = getConnection();
        
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

function acceptAllAsClean($params) {
    try {
        $batchReference = $params['batch_reference'] ?? null;
        
        $pdo = getConnection();
        
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
?>