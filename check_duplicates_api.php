<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

try {
    $pdo = getConnection();
    
    switch ($action) {
        case 'get_received_beneficiaries':
            getReceivedBeneficiaries($pdo);
            break;
            
        case 'get_beneficiary_details':
            getBeneficiaryDetails($pdo);
            break;
            
        case 'scan_duplicates':
            scanDuplicates($pdo);
            break;
            
        case 'accept_as_clean':
            acceptAsClean($pdo);
            break;
            
        case 'accept_selected_as_clean':
            acceptSelectedAsClean($pdo);
            break;
            
        case 'accept_all_as_clean':
            acceptAllAsClean($pdo);
            break;
            
        case 'delete_duplicate':
            deleteDuplicate($pdo);
            break;
            
        case 'delete_duplicates':
            deleteDuplicates($pdo);
            break;
            
        case 'delete_all_duplicates':
            deleteAllDuplicates($pdo);
            break;
            
        case 'get_clean_records':
            getCleanRecords($pdo);
            break;
            
        case 'delete_history':
            deleteHistory($pdo);
            break;
            
        case 'get_batch_list':
            getBatchList($pdo);
            break;
            
        case 'scan_specific_batch':
            scanSpecificBatch($pdo);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

/**
 * Get all received beneficiaries
 */
function getReceivedBeneficiaries($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, name, barangay, birthday, batch_reference FROM received_beneficiaries ORDER BY name ASC");
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $records,
            'total' => count($records)
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching beneficiaries: ' . $e->getMessage()]);
    }
}

/**
 * Get beneficiary details by ID
 */
function getBeneficiaryDetails($pdo) {
    $id = $_POST['id'] ?? 0;
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Beneficiary ID required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id, name, barangay, birthday, batch_reference FROM received_beneficiaries WHERE id = ?");
        $stmt->execute([$id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($record) {
            echo json_encode(['success' => true, 'data' => $record]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Beneficiary not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching beneficiary: ' . $e->getMessage()]);
    }
}

/**
 * Scan for duplicates in received beneficiaries
 */
function scanDuplicates($pdo) {
    try {
        // Get all beneficiaries
        $stmt = $pdo->query("SELECT id, name, barangay, birthday, batch_reference FROM received_beneficiaries");
        $beneficiaries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($beneficiaries)) {
            echo json_encode([
                'success' => false,
                'message' => 'No beneficiaries found to check. Please import a list first.'
            ]);
            return;
        }
        
        $duplicates = [];
        $cleanRecords = [];
        $processedIds = [];
        
        // First, mark all as potential duplicates and find groups
        foreach ($beneficiaries as $i => $beneficiary) {
            if (in_array($beneficiary['id'], $processedIds)) continue;
            
            $group = [$beneficiary];
            $processedIds[] = $beneficiary['id'];
            
            // Compare with other beneficiaries
            foreach ($beneficiaries as $j => $other) {
                if ($i === $j || in_array($other['id'], $processedIds)) continue;
                
                $similarity = calculateSimilarity($beneficiary, $other);
                
                if ($similarity >= 85) { // 85% similarity threshold
                    $group[] = $other;
                    $processedIds[] = $other['id'];
                }
            }
            
            // If group has more than 1, it's a duplicate group
            if (count($group) > 1) {
                // Sort group by name for consistency
                usort($group, function($a, $b) {
                    return strcmp($a['name'], $b['name']);
                });
                
                // Add all records in group as duplicates
                foreach ($group as $record) {
                    $record['match_level'] = 95; // Default match level
                    $record['match_details'] = count($group) > 1 ? 'Original record (has high-confidence duplicates)' : 'Potential duplicate';
                    $duplicates[] = $record;
                }
            } else {
                // Single record - consider it clean
                $cleanRecords[] = $beneficiary;
            }
        }
        
        // Save check history
        $stmt = $pdo->prepare("INSERT INTO check_history (batch_name, total_checked, duplicates_found, clean_records, check_date) VALUES (?, ?, ?, ?, NOW())");
        $batchName = $_POST['batch_name'] ?? 'Auto Check ' . date('Y-m-d H:i:s');
        $totalChecked = count($beneficiaries);
        $duplicatesFound = count($duplicates);
        $cleanCount = count($cleanRecords);
        $stmt->execute([$batchName, $totalChecked, $duplicatesFound, $cleanCount]);
        
        echo json_encode([
            'success' => true,
            'total_checked' => $totalChecked,
            'duplicates_found' => $duplicatesFound,
            'clean_records' => $cleanCount,
            'duplicates' => $duplicates,
            'clean_records_data' => $cleanRecords,
            'batch_reference' => $batchName,
            'message' => 'Duplicate scan completed successfully'
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error scanning duplicates: ' . $e->getMessage()]);
    }
}

/**
 * Calculate similarity between two beneficiaries
 */
function calculateSimilarity($a, $b) {
    $score = 0;
    $total = 0;
    
    // Compare name (40% weight)
    if (!empty($a['name']) && !empty($b['name'])) {
        similar_text(strtolower($a['name']), strtolower($b['name']), $percent);
        $score += ($percent / 100) * 40;
        $total += 40;
    }
    
    // Compare barangay (30% weight)
    if (!empty($a['barangay']) && !empty($b['barangay'])) {
        similar_text(strtolower($a['barangay']), strtolower($b['barangay']), $percent);
        $score += ($percent / 100) * 30;
        $total += 30;
    }
    
    // Compare birthday (30% weight)
    if (!empty($a['birthday']) && !empty($b['birthday'])) {
        if ($a['birthday'] === $b['birthday']) {
            $score += 30;
        }
        $total += 30;
    }
    
    // If no comparisons were made, return 0
    if ($total === 0) return 0;
    
    // Return percentage
    return round(($score / $total) * 100);
}

/**
 * Accept a record as clean
 */
function acceptAsClean($pdo) {
    $id = $_POST['id'] ?? 0;
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Record ID required']);
        return;
    }
    
    try {
        // Update the record status using is_accepted_clean column
        $stmt = $pdo->prepare("UPDATE received_beneficiaries SET is_accepted_clean = 1, accepted_date = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Record accepted as clean']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Accept selected records as clean
 */
function acceptSelectedAsClean($pdo) {
    $ids = $_POST['ids'] ?? [];
    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'No records selected']);
        return;
    }
    
    try {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("UPDATE received_beneficiaries SET is_accepted_clean = 1, accepted_date = NOW() WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        
        echo json_encode(['success' => true, 'message' => count($ids) . ' records accepted as clean']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Accept all records as clean for a batch
 */
function acceptAllAsClean($pdo) {
    $batchReference = $_POST['batch_reference'] ?? null;
    
    try {
        if ($batchReference) {
            $stmt = $pdo->prepare("UPDATE received_beneficiaries SET is_accepted_clean = 1, accepted_date = NOW() WHERE batch_reference = ?");
            $stmt->execute([$batchReference]);
        } else {
            $stmt = $pdo->exec("UPDATE received_beneficiaries SET is_accepted_clean = 1, accepted_date = NOW()");
        }
        
        echo json_encode(['success' => true, 'message' => 'All records accepted as clean']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Delete a single duplicate record
 */
function deleteDuplicate($pdo) {
    $id = $_POST['id'] ?? 0;
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Record ID required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM received_beneficiaries WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Record deleted successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Delete multiple duplicate records
 */
function deleteDuplicates($pdo) {
    $ids = $_POST['ids'] ?? [];
    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'No records selected']);
        return;
    }
    
    try {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("DELETE FROM received_beneficiaries WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        
        echo json_encode(['success' => true, 'message' => count($ids) . ' records deleted']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Delete all duplicates
 */
function deleteAllDuplicates($pdo) {
    $batchReference = $_POST['batch_reference'] ?? null;
    
    try {
        if ($batchReference) {
            $stmt = $pdo->prepare("DELETE FROM received_beneficiaries WHERE batch_reference = ? AND is_accepted_clean = 0");
            $stmt->execute([$batchReference]);
        } else {
            $stmt = $pdo->exec("DELETE FROM received_beneficiaries WHERE is_accepted_clean = 0");
        }
        
        echo json_encode(['success' => true, 'message' => 'All duplicate records deleted']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Get clean records
 */
function getCleanRecords($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, name, barangay, birthday, batch_reference FROM received_beneficiaries WHERE is_accepted_clean = 1 ORDER BY name ASC");
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'records' => $records]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Delete history record
 */
function deleteHistory($pdo) {
    $id = $_POST['id'] ?? 0;
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'History ID required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM check_history WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'History record deleted']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Get list of batches
 */
function getBatchList($pdo) {
    try {
        $stmt = $pdo->query("SELECT DISTINCT batch_reference FROM received_beneficiaries WHERE batch_reference IS NOT NULL ORDER BY batch_reference DESC");
        $batches = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo json_encode(['success' => true, 'batches' => $batches]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Scan specific batch
 */
function scanSpecificBatch($pdo) {
    $batchReference = $_POST['batch_reference'] ?? '';
    if (!$batchReference) {
        echo json_encode(['success' => false, 'message' => 'Batch reference required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id, name, barangay, birthday, batch_reference FROM received_beneficiaries WHERE batch_reference = ?");
        $stmt->execute([$batchReference]);
        $beneficiaries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($beneficiaries)) {
            echo json_encode(['success' => false, 'message' => 'No beneficiaries found for this batch']);
            return;
        }
        
        // Scan duplicates within this batch
        $duplicates = [];
        $cleanRecords = [];
        $processedIds = [];
        
        foreach ($beneficiaries as $i => $beneficiary) {
            if (in_array($beneficiary['id'], $processedIds)) continue;
            
            $group = [$beneficiary];
            $processedIds[] = $beneficiary['id'];
            
            foreach ($beneficiaries as $j => $other) {
                if ($i === $j || in_array($other['id'], $processedIds)) continue;
                
                $similarity = calculateSimilarity($beneficiary, $other);
                
                if ($similarity >= 85) {
                    $group[] = $other;
                    $processedIds[] = $other['id'];
                }
            }
            
            if (count($group) > 1) {
                usort($group, function($a, $b) {
                    return strcmp($a['name'], $b['name']);
                });
                
                foreach ($group as $record) {
                    $record['match_level'] = 95;
                    $record['match_details'] = count($group) > 1 ? 'Original record (has high-confidence duplicates)' : 'Potential duplicate';
                    $duplicates[] = $record;
                }
            } else {
                $cleanRecords[] = $beneficiary;
            }
        }
        
        echo json_encode([
            'success' => true,
            'total_checked' => count($beneficiaries),
            'duplicates_found' => count($duplicates),
            'clean_records' => count($cleanRecords),
            'duplicates' => $duplicates,
            'clean_records_data' => $cleanRecords,
            'batch_reference' => $batchReference
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}
?>