<?php
session_start();
require_once 'config.php';

// Include PhpSpreadsheet
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;
use PhpOffice\PhpSpreadsheet\Reader\Xls as XlsReader;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$action = isset($_POST['action']) ? $_POST['action'] : '';
$response = ['success' => false, 'message' => 'Invalid action'];

try {
    $pdo = getConnection();
    
    switch ($action) {
        case 'get_rice_beneficiaries':
            $stmt = $pdo->query("SELECT * FROM rice_beneficiaries ORDER BY created_at DESC");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response = ['success' => true, 'data' => $data];
            break;
            
        case 'get_rice_beneficiary_details':
            if (!isset($_POST['id'])) {
                $response = ['success' => false, 'message' => 'Missing ID'];
                break;
            }
            $stmt = $pdo->prepare("SELECT * FROM rice_beneficiaries WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            $response = ['success' => true, 'data' => $data];
            break;
            
        case 'import_rice_beneficiaries':
            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                $response = ['success' => false, 'message' => 'Please upload a valid file'];
                break;
            }
            
            $batchReference = isset($_POST['batch_reference']) ? trim($_POST['batch_reference']) : '';
            $distributionDate = isset($_POST['distribution_date']) && !empty($_POST['distribution_date']) ? $_POST['distribution_date'] : null;
            
            if (empty($batchReference)) {
                $response = ['success' => false, 'message' => 'Batch reference is required'];
                break;
            }
            
            $filePath = $_FILES['file']['tmp_name'];
            $fileType = $_FILES['file']['type'];
            $fileExtension = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
            
            try {
                // Create reader based on file extension
                $reader = null;
                if ($fileExtension === 'csv') {
                    $reader = new CsvReader();
                    $reader->setInputEncoding('UTF-8');
                    $reader->setDelimiter(',');
                    $reader->setEnclosure('"');
                    $reader->setSheetIndex(0);
                } elseif ($fileExtension === 'xls') {
                    $reader = new XlsReader();
                } elseif ($fileExtension === 'xlsx') {
                    $reader = new XlsxReader();
                } else {
                    $response = ['success' => false, 'message' => 'Unsupported file format. Please upload CSV, XLS, or XLSX'];
                    break;
                }
                
                $spreadsheet = $reader->load($filePath);
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray();
                
                if (empty($rows)) {
                    $response = ['success' => false, 'message' => 'File is empty'];
                    break;
                }
                
                // Get header row
                $header = array_shift($rows);
                
                // Clean header
                $header = array_map(function($col) {
                    return strtolower(trim($col));
                }, $header);
                
                // Map columns
                $columnMap = [];
                foreach ($header as $index => $column) {
                    if (strpos($column, 'first name') !== false || strpos($column, 'firstname') !== false) {
                        $columnMap['first_name'] = $index;
                    } elseif (strpos($column, 'middle name') !== false || strpos($column, 'middlename') !== false) {
                        $columnMap['middle_name'] = $index;
                    } elseif (strpos($column, 'lastname') !== false || strpos($column, 'last name') !== false) {
                        $columnMap['last_name'] = $index;
                    } elseif (strpos($column, 'suffix') !== false) {
                        $columnMap['suffix'] = $index;
                    } elseif (strpos($column, 'barangay') !== false) {
                        $columnMap['barangay'] = $index;
                    } elseif (strpos($column, 'sector') !== false) {
                        $columnMap['sector'] = $index;
                    } elseif (strpos($column, 'month') !== false) {
                        $columnMap['month'] = $index;
                    } elseif (strpos($column, 'day') !== false) {
                        $columnMap['day'] = $index;
                    } elseif (strpos($column, 'year') !== false) {
                        $columnMap['year'] = $index;
                    } elseif (strpos($column, 'male') !== false) {
                        $columnMap['male'] = $index;
                    } elseif (strpos($column, 'female') !== false) {
                        $columnMap['female'] = $index;
                    }
                }
                
                // Check if we have at least first name and last name
                if (!isset($columnMap['first_name']) && !isset($columnMap['last_name'])) {
                    $response = ['success' => false, 'message' => 'File must have first name and/or last name columns'];
                    break;
                }
                
                $stmt = $pdo->prepare("INSERT INTO rice_beneficiaries 
                    (full_name, barangay, rice_type, quantity, batch_reference, distribution_date, status, sector) 
                    VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)");
                $count = 0;
                $skippedCount = 0;
                $barangayStats = [];
                $sectorStats = [];
                
                foreach ($rows as $row) {
                    // Skip empty rows
                    if (count($row) < 2 || empty(trim(implode('', $row)))) {
                        $skippedCount++;
                        continue;
                    }
                    
                    // Build full name
                    $firstName = isset($columnMap['first_name']) && isset($row[$columnMap['first_name']]) ? trim($row[$columnMap['first_name']]) : '';
                    $middleName = isset($columnMap['middle_name']) && isset($row[$columnMap['middle_name']]) ? trim($row[$columnMap['middle_name']]) : '';
                    $lastName = isset($columnMap['last_name']) && isset($row[$columnMap['last_name']]) ? trim($row[$columnMap['last_name']]) : '';
                    $suffix = isset($columnMap['suffix']) && isset($row[$columnMap['suffix']]) ? trim($row[$columnMap['suffix']]) : '';
                    
                    // Skip if no name
                    if (empty($firstName) && empty($lastName)) {
                        $skippedCount++;
                        continue;
                    }
                    
                    // Build full name
                    $fullName = trim($firstName . ' ' . $middleName . ' ' . $lastName);
                    if (!empty($suffix)) {
                        $fullName .= ' ' . $suffix;
                    }
                    $fullName = preg_replace('/\s+/', ' ', $fullName); // Remove extra spaces
                    
                    // Get barangay
                    $barangay = isset($columnMap['barangay']) && isset($row[$columnMap['barangay']]) ? trim($row[$columnMap['barangay']]) : '';
                    
                    // Get sector
                    $sector = isset($columnMap['sector']) && isset($row[$columnMap['sector']]) ? trim($row[$columnMap['sector']]) : '';
                    
                    // Determine rice type based on sector or default
                    $riceType = 'Regular';
                    if (!empty($sector)) {
                        $sectorLower = strtolower($sector);
                        if (strpos($sectorLower, 'senior') !== false) {
                            $riceType = 'Senior';
                        } elseif (strpos($sectorLower, 'pwd') !== false) {
                            $riceType = 'PWD';
                        } elseif (strpos($sectorLower, 'solo') !== false || strpos($sectorLower, 'single') !== false) {
                            $riceType = 'Solo Parent';
                        } elseif (strpos($sectorLower, '4ps') !== false) {
                            $riceType = '4PS';
                        } elseif (strpos($sectorLower, 'farmer') !== false) {
                            $riceType = 'Farmer';
                        } elseif (strpos($sectorLower, 'fisher') !== false) {
                            $riceType = 'Fisherfolks';
                        } elseif (strpos($sectorLower, 'transport') !== false) {
                            $riceType = 'Transport Group';
                        } elseif (strpos($sectorLower, 'womens') !== false || strpos($sectorLower, 'women') !== false) {
                            $riceType = 'Womens';
                        }
                    }
                    
                    // Set quantity (default 1 for rice distribution)
                    $quantity = 10;
                    
                    $stmt->execute([
                        $fullName,
                        $barangay,
                        $riceType,
                        $quantity,
                        $batchReference,
                        $distributionDate,
                        $sector
                    ]);
                    $count++;
                    
                    // Track stats
                    if (!empty($barangay)) {
                        if (!isset($barangayStats[$barangay])) {
                            $barangayStats[$barangay] = 0;
                        }
                        $barangayStats[$barangay]++;
                    }
                    
                    if (!empty($sector)) {
                        $sectorKey = explode('/', $sector)[0];
                        $sectorKey = trim($sectorKey);
                        if (!isset($sectorStats[$sectorKey])) {
                            $sectorStats[$sectorKey] = 0;
                        }
                        $sectorStats[$sectorKey]++;
                    }
                }
                
                // Log distribution history
                if ($count > 0) {
                    $logStmt = $pdo->prepare("INSERT INTO rice_distribution_history 
                        (batch_reference, total_beneficiaries, total_rice_distributed, distribution_date, barangay_stats, sector_stats) 
                        VALUES (?, ?, ?, ?, ?, ?)");
                    $totalRice = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM rice_beneficiaries WHERE batch_reference = ?");
                    $totalRice->execute([$batchReference]);
                    $totalQuantity = $totalRice->fetchColumn();
                    
                    $logStmt->execute([
                        $batchReference, 
                        $count, 
                        $totalQuantity, 
                        $distributionDate,
                        json_encode($barangayStats),
                        json_encode($sectorStats)
                    ]);
                }
                
                $response = [
                    'success' => true, 
                    'message' => "Imported $count rice beneficiaries" . ($skippedCount > 0 ? " (Skipped $skippedCount empty rows)" : ""),
                    'count' => $count,
                    'skipped' => $skippedCount,
                    'barangay_stats' => $barangayStats,
                    'sector_stats' => $sectorStats
                ];
                
            } catch (Exception $e) {
                $response = ['success' => false, 'message' => 'Error reading file: ' . $e->getMessage()];
            }
            break;
            
        case 'update_rice_status':
            if (!isset($_POST['id']) || !isset($_POST['status'])) {
                $response = ['success' => false, 'message' => 'Missing required fields'];
                break;
            }
            
            $id = intval($_POST['id']);
            $status = $_POST['status'];
            $distributionDate = isset($_POST['distribution_date']) && !empty($_POST['distribution_date']) ? $_POST['distribution_date'] : null;
            
            $stmt = $pdo->prepare("UPDATE rice_beneficiaries SET status = ?, distribution_date = ? WHERE id = ?");
            $stmt->execute([$status, $distributionDate, $id]);
            
            $response = ['success' => true, 'message' => 'Status updated successfully'];
            break;
            
        case 'delete_rice_beneficiary':
            if (!isset($_POST['id'])) {
                $response = ['success' => false, 'message' => 'Missing ID'];
                break;
            }
            $stmt = $pdo->prepare("DELETE FROM rice_beneficiaries WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $response = ['success' => true, 'message' => 'Record deleted successfully'];
            break;
            
        case 'get_rice_stats':
            $stmt = $pdo->query("SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'distributed' THEN 1 ELSE 0 END) as distributed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                COALESCE(SUM(quantity), 0) as total_quantity
                FROM rice_beneficiaries");
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt2 = $pdo->query("SELECT 
                barangay,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'distributed' THEN 1 ELSE 0 END) as distributed
                FROM rice_beneficiaries 
                GROUP BY barangay
                ORDER BY total DESC");
            $barangayStats = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            
            $stmt3 = $pdo->query("SELECT 
                sector,
                COUNT(*) as total
                FROM rice_beneficiaries 
                WHERE sector IS NOT NULL AND sector != ''
                GROUP BY sector
                ORDER BY total DESC");
            $sectorStats = $stmt3->fetchAll(PDO::FETCH_ASSOC);
            
            $response = [
                'success' => true, 
                'stats' => $stats, 
                'barangay_stats' => $barangayStats,
                'sector_stats' => $sectorStats
            ];
            break;
        
        case 'compare_rice_list':
            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                $response = ['success' => false, 'message' => 'Please upload a valid file to compare'];
                break;
            }
            
            $filePath = $_FILES['file']['tmp_name'];
            $fileExtension = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
            
            try {
                // Parse the file to get names
                $importedNames = [];
                $fullRows = [];
                
                if ($fileExtension === 'csv') {
                    if (($handle = fopen($filePath, 'r')) !== false) {
                        $header = fgetcsv($handle);
                        // Map columns
                        $colMap = [];
                        foreach ($header as $index => $col) {
                            $col = strtolower(trim($col));
                            if (strpos($col, 'first name') !== false || strpos($col, 'firstname') !== false) {
                                $colMap['first_name'] = $index;
                            } elseif (strpos($col, 'middle name') !== false || strpos($col, 'middlename') !== false) {
                                $colMap['middle_name'] = $index;
                            } elseif (strpos($col, 'lastname') !== false || strpos($col, 'last name') !== false) {
                                $colMap['last_name'] = $index;
                            } elseif (strpos($col, 'suffix') !== false) {
                                $colMap['suffix'] = $index;
                            } elseif (strpos($col, 'barangay') !== false) {
                                $colMap['barangay'] = $index;
                            }
                        }
                        
                        while (($row = fgetcsv($handle)) !== false) {
                            if (empty(trim(implode('', $row)))) continue;
                            
                            $firstName = isset($colMap['first_name']) && isset($row[$colMap['first_name']]) ? trim($row[$colMap['first_name']]) : '';
                            $middleName = isset($colMap['middle_name']) && isset($row[$colMap['middle_name']]) ? trim($row[$colMap['middle_name']]) : '';
                            $lastName = isset($colMap['last_name']) && isset($row[$colMap['last_name']]) ? trim($row[$colMap['last_name']]) : '';
                            $suffix = isset($colMap['suffix']) && isset($row[$colMap['suffix']]) ? trim($row[$colMap['suffix']]) : '';
                            $barangay = isset($colMap['barangay']) && isset($row[$colMap['barangay']]) ? trim($row[$colMap['barangay']]) : '';
                            
                            if (empty($firstName) && empty($lastName)) continue;
                            
                            $fullName = trim($firstName . ' ' . $middleName . ' ' . $lastName);
                            if (!empty($suffix)) {
                                $fullName .= ' ' . $suffix;
                            }
                            $fullName = preg_replace('/\s+/', ' ', $fullName);
                            
                            $importedNames[] = [
                                'full_name' => $fullName,
                                'barangay' => $barangay,
                                'first_name' => $firstName,
                                'middle_name' => $middleName,
                                'last_name' => $lastName,
                                'suffix' => $suffix
                            ];
                        }
                        fclose($handle);
                    }
                } else {
                    // Use PhpSpreadsheet for Excel files
                    require_once 'vendor/autoload.php';

                    $reader = IOFactory::createReaderForFile($filePath);
                    $spreadsheet = $reader->load($filePath);
                    $worksheet = $spreadsheet->getActiveSheet();
                    $rows = $worksheet->toArray();
                    
                    if (empty($rows)) {
                        $response = ['success' => false, 'message' => 'File is empty'];
                        break;
                    }
                    
                    $header = array_shift($rows);
                    $header = array_map('strtolower', array_map('trim', $header));
                    
                    $colMap = [];
                    foreach ($header as $index => $col) {
                        if (strpos($col, 'first name') !== false || strpos($col, 'firstname') !== false) {
                            $colMap['first_name'] = $index;
                        } elseif (strpos($col, 'middle name') !== false || strpos($col, 'middlename') !== false) {
                            $colMap['middle_name'] = $index;
                        } elseif (strpos($col, 'lastname') !== false || strpos($col, 'last name') !== false) {
                            $colMap['last_name'] = $index;
                        } elseif (strpos($col, 'suffix') !== false) {
                            $colMap['suffix'] = $index;
                        } elseif (strpos($col, 'barangay') !== false) {
                            $colMap['barangay'] = $index;
                        }
                    }
                    
                    foreach ($rows as $row) {
                        if (empty(trim(implode('', $row)))) continue;
                        
                        $firstName = isset($colMap['first_name']) && isset($row[$colMap['first_name']]) ? trim($row[$colMap['first_name']]) : '';
                        $middleName = isset($colMap['middle_name']) && isset($row[$colMap['middle_name']]) ? trim($row[$colMap['middle_name']]) : '';
                        $lastName = isset($colMap['last_name']) && isset($row[$colMap['last_name']]) ? trim($row[$colMap['last_name']]) : '';
                        $suffix = isset($colMap['suffix']) && isset($row[$colMap['suffix']]) ? trim($row[$colMap['suffix']]) : '';
                        $barangay = isset($colMap['barangay']) && isset($row[$colMap['barangay']]) ? trim($row[$colMap['barangay']]) : '';
                        
                        if (empty($firstName) && empty($lastName)) continue;
                        
                        $fullName = trim($firstName . ' ' . $middleName . ' ' . $lastName);
                        if (!empty($suffix)) {
                            $fullName .= ' ' . $suffix;
                        }
                        $fullName = preg_replace('/\s+/', ' ', $fullName);
                        
                        $importedNames[] = [
                            'full_name' => $fullName,
                            'barangay' => $barangay,
                            'first_name' => $firstName,
                            'middle_name' => $middleName,
                            'last_name' => $lastName,
                            'suffix' => $suffix
                        ];
                    }
                }
                
                if (empty($importedNames)) {
                    $response = ['success' => false, 'message' => 'No valid names found in the file'];
                    break;
                }
                
                // Get existing rice beneficiaries from database
                $stmt = $pdo->query("SELECT id, full_name, barangay FROM rice_beneficiaries");
                $existingBeneficiaries = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $existingNames = array_column($existingBeneficiaries, 'full_name');
                $existingNamesLower = array_map('strtolower', $existingNames);
                
                // Compare names
                $matches = [];
                $nonMatches = [];
                $exactMatches = [];
                $partialMatches = [];
                $duplicates = [];
                
                foreach ($importedNames as $index => $imported) {
                    $importedName = trim($imported['full_name']);
                    $importedNameLower = strtolower($importedName);
                    
                    // Check for exact match
                    if (in_array($importedName, $existingNames)) {
                        $matches[] = $imported;
                        $exactMatches[] = $imported;
                        
                        // Find the existing record
                        foreach ($existingBeneficiaries as $existing) {
                            if ($existing['full_name'] === $importedName) {
                                $imported['existing_id'] = $existing['id'];
                                $imported['existing_barangay'] = $existing['barangay'];
                                break;
                            }
                        }
                        continue;
                    }
                    
                    // Check for case-insensitive match
                    $found = false;
                    foreach ($existingNames as $existing) {
                        if (strtolower($existing) === $importedNameLower) {
                            $matches[] = $imported;
                            $exactMatches[] = $imported;
                            $found = true;
                            break;
                        }
                    }
                    if ($found) continue;
                    
                    // Check for partial match (first+last name)
                    $nameParts = explode(' ', $importedName);
                    $firstNamePart = isset($nameParts[0]) ? $nameParts[0] : '';
                    $lastNamePart = isset($nameParts[count($nameParts) - 1]) ? $nameParts[count($nameParts) - 1] : '';
                    
                    if (!empty($firstNamePart) && !empty($lastNamePart)) {
                        foreach ($existingNames as $existing) {
                            $existingParts = explode(' ', $existing);
                            $existingFirstName = isset($existingParts[0]) ? $existingParts[0] : '';
                            $existingLastName = isset($existingParts[count($existingParts) - 1]) ? $existingParts[count($existingParts) - 1] : '';
                            
                            // Check if first and last names match
                            if (strtolower($firstNamePart) === strtolower($existingFirstName) && 
                                strtolower($lastNamePart) === strtolower($existingLastName)) {
                                $imported['matched_with'] = $existing;
                                $partialMatches[] = $imported;
                                $matches[] = $imported;
                                $found = true;
                                break;
                            }
                        }
                    }
                    
                    if (!$found) {
                        // Check for duplicate within the imported list
                        $isDuplicate = false;
                        foreach ($nonMatches as $existingNonMatch) {
                            if (strtolower($existingNonMatch['full_name']) === $importedNameLower) {
                                $isDuplicate = true;
                                break;
                            }
                        }
                        if ($isDuplicate) {
                            $duplicates[] = $imported;
                        } else {
                            $nonMatches[] = $imported;
                        }
                    }
                }
                
                // Generate comparison report
                $response = [
                    'success' => true,
                    'data' => [
                        'total_imported' => count($importedNames),
                        'exact_matches' => count($exactMatches),
                        'partial_matches' => count($partialMatches),
                        'new_records' => count($nonMatches),
                        'duplicates_in_file' => count($duplicates),
                        'exact_match_list' => $exactMatches,
                        'partial_match_list' => $partialMatches,
                        'new_records_list' => $nonMatches,
                        'duplicate_list' => $duplicates,
                        'all_imported' => $importedNames
                    ]
                ];
                
            } catch (Exception $e) {
                $response = ['success' => false, 'message' => 'Error comparing file: ' . $e->getMessage()];
            }
            break;

        case 'export_non_matches':
            if (!isset($_POST['data'])) {
                $response = ['success' => false, 'message' => 'No data to export'];
                break;
            }
            
            $data = json_decode($_POST['data'], true);
            if (!$data || !isset($data['new_records_list']) || empty($data['new_records_list'])) {
                $response = ['success' => false, 'message' => 'No new records to export'];
                break;
            }
            
            $newRecords = $data['new_records_list'];
            $totalRecords = count($newRecords);
            
            // Check if exceeds 1500 limit
            if ($totalRecords > 1500) {
                $response = [
                    'success' => false, 
                    'message' => "Export limit exceeded. Found $totalRecords new records, but maximum is 1500. Please filter or reduce your list.",
                    'total' => $totalRecords,
                    'limit' => 1500
                ];
                break;
            }
            
            // Generate CSV
            $filename = 'new_rice_beneficiaries_' . date('Y-m-d_His') . '.csv';
            
            // Set headers for download
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            $output = fopen('php://output', 'w');
            
            // Add UTF-8 BOM for Excel compatibility
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Write headers
            fputcsv($output, [
                'SN',
                'FIRST NAME',
                'MIDDLE NAME',
                'LASTNAME',
                'SUFFIX',
                'BARANGAY',
                'STATUS'
            ]);
            
            // Write data
            $sn = 1;
            foreach ($newRecords as $record) {
                // Extract first name, middle name, last name from full_name
                $nameParts = explode(' ', $record['full_name']);
                $firstName = isset($nameParts[0]) ? $nameParts[0] : '';
                $lastName = isset($nameParts[count($nameParts) - 1]) ? $nameParts[count($nameParts) - 1] : '';
                $middleName = '';
                if (count($nameParts) > 2) {
                    $middleName = implode(' ', array_slice($nameParts, 1, -1));
                }
                
                fputcsv($output, [
                    $sn++,
                    $firstName,
                    $middleName,
                    $lastName,
                    $record['suffix'] ?? '',
                    $record['barangay'] ?? '',
                    'New Record'
                ]);
            }
            
            fclose($output);
            exit; // Important: stop execution after sending file
            break;
            
        default:
            $response = ['success' => false, 'message' => 'Invalid action'];
    }
} catch (PDOException $e) {
    $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
} catch (Exception $e) {
    $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
}

echo json_encode($response);