<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit;
}

// Get statistics
try {
    $pdo = getConnection();
    $stats = [
        'total_received' => $pdo->query("SELECT COUNT(*) FROM received_beneficiaries")->fetchColumn(),
        'total_checks' => $pdo->query("SELECT COUNT(*) FROM check_history")->fetchColumn(),
        'duplicates_found' => $pdo->query("SELECT COALESCE(SUM(duplicates_found), 0) FROM check_history")->fetchColumn(),
        'last_check' => $pdo->query("SELECT check_date FROM check_history ORDER BY check_date DESC LIMIT 1")->fetchColumn()
    ];
} catch (Exception $e) {
    $stats = ['total_received' => 0, 'total_checks' => 0, 'duplicates_found' => 0, 'last_check' => 'Never'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DupliChecker</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo-container">
                    <!-- <i class="bi bi-shield-check logo-icon"></i> -->
                    <div class="logo-text text-uppercase">
                        <h2>Dupli_Checker</h2>
                        <!-- <span>Duplicate Checker</span> -->
                    </div>
                </div>
                <!-- <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="bi bi-list"></i>
                </button> -->
            </div>
            
            <nav class="sidebar-nav">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a href="#" class="nav-link active" data-page="dashboard">
                            <i class="bi bi-grid-1x2-fill"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" id="openImportModal" data-bs-toggle="modal" data-bs-target="#importModal">
                            <i class="bi bi-upload"></i>
                            <span>Import List</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-page="check-duplicates">
                            <i class="bi bi-search"></i>
                            <span>Check Duplicates</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-page="results">
                            <i class="bi bi-table"></i>
                            <span>Results</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-page="history">
                            <i class="bi bi-clock-history"></i>
                            <span>History</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-page="reports">
                            <i class="bi bi-file-earmark-bar-graph"></i>
                            <span>Reports</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-page="settings">
                            <i class="bi bi-gear"></i>
                            <span>Settings</span>
                        </a>
                    </li>
                </ul>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="avatar">
                        <i class="bi bi-person-circle"></i>
                    </div>
                    <div class="user-details">
                        <span class="user-name"><?php echo htmlspecialchars(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')); ?></span>
                        <span class="user-role"><?php echo ucfirst(htmlspecialchars($_SESSION['role'] ?? 'User')); ?></span>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Navigation Bar -->
            <header class="navbar-top">
                <div class="container-fluid">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <button class="btn btn-link sidebar-collapse" id="sidebarCollapse">
                                <i class="bi bi-list"></i>
                            </button>
                            <h5 class="page-title mb-0" id="pageTitle">Dashboard</h5>
                        </div>
                        
                        <div class="d-flex align-items-center gap-3">
                            <!-- Search -->
                            <div class="search-box">
                                <i class="bi bi-search"></i>
                                <input type="text" class="form-control" placeholder="Search beneficiaries...">
                            </div>
                            
                            <!-- Notifications -->
                            <div class="dropdown">
                                <button class="btn btn-link notification-btn" data-bs-toggle="dropdown">
                                    <i class="bi bi-bell"></i>
                                    <span class="badge bg-danger">3</span>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end notification-menu">
                                    <div class="notification-header">
                                        <h6>Notifications</h6>
                                    </div>
                                    <div class="notification-item">
                                        <i class="bi bi-exclamation-circle text-warning"></i>
                                        <div>
                                            <p>15 duplicates found in latest check</p>
                                            <small>5 minutes ago</small>
                                        </div>
                                    </div>
                                    <div class="notification-item">
                                        <i class="bi bi-check-circle text-success"></i>
                                        <div>
                                            <p>Master list updated successfully</p>
                                            <small>1 hour ago</small>
                                        </div>
                                    </div>
                                    <div class="notification-item">
                                        <i class="bi bi-info-circle text-info"></i>
                                        <div>
                                            <p>New batch imported: January 2024</p>
                                            <small>2 hours ago</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- User Menu -->
                            <div class="dropdown">
                                <button class="btn btn-link user-menu-btn" data-bs-toggle="dropdown">
                                    <div class="avatar-sm">
                                        <i class="bi bi-person-circle"></i>
                                    </div>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <div class="dropdown-header">Logged in as: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></div>
                                    <a class="dropdown-item" href="#"><i class="bi bi-person"></i> Profile</a>
                                    <a class="dropdown-item" href="#"><i class="bi bi-gear"></i> Settings</a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <div class="page-content">
                <!-- Dashboard Page -->
                <div class="page active" id="dashboard-page">
                    <!-- Stats Cards -->
                    <div class="row g-4 mb-4">
                        <div class="col-xl-3 col-md-6">
                            <div class="stats-card gradient-1">
                                <div class="stats-icon">
                                    <i class="bi bi-people-fill"></i>
                                </div>
                                <div class="stats-info">
                                    <h3><?php echo number_format($stats['total_received']); ?></h3>
                                    <p>Total Beneficiaries</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <div class="stats-card gradient-2">
                                <div class="stats-icon">
                                    <i class="bi bi-check2-all"></i>
                                </div>
                                <div class="stats-info">
                                    <h3><?php echo number_format($stats['total_checks']); ?></h3>
                                    <p>Total Checks</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <div class="stats-card gradient-3">
                                <div class="stats-icon">
                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                </div>
                                <div class="stats-info">
                                    <h3><?php echo number_format($stats['duplicates_found']); ?></h3>
                                    <p>Duplicates Found</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <div class="stats-card gradient-2">
                                <div class="stats-icon">
                                    <i class="bi bi-check2-all"></i>
                                </div>
                                <div class="stats-info">
                                    <h3><?php echo number_format($stats['total_checks']); ?></h3>
                                    <p>Total Checks</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Row -->
                    <div class="row g-4 mb-4">
                        <!-- <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Duplicate Detection Trend</h5>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-secondary active">Week</button>
                                        <button class="btn btn-outline-secondary">Month</button>
                                        <button class="btn btn-outline-secondary">Year</button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <canvas id="trendChart" height="300"></canvas>
                                </div>
                            </div>
                        </div> -->
                        <div class="col-lg-6" style="width: 66%;">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Quick Actions</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-6">
                                            <button class="btn w-100 p-4 quick-action-btn" data-bs-toggle="modal" data-bs-target="#importModal" style="background: #F0EFFF;">
                                                <i class="bi bi-upload display-6 mb-2"></i>
                                                <h6>Import List</h6>
                                                <small>Upload received beneficiaries</small>
                                            </button>
                                        </div>
                                        <div class="col-6">
                                            <button class="btn w-100 p-4 quick-action-btn" onclick="switchPage('check-duplicates')" style="background: #F0EFFF;">
                                                <i class="bi bi-search display-6 mb-2"></i>
                                                <h6>Check Duplicates</h6>
                                                <small>Upload new list to verify</small>
                                            </button>
                                        </div>
                                        <div class="col-6">
                                            <button class="btn w-100 p-4 quick-action-btn" onclick="switchPage('results')" style="background: #F0EFFF;">
                                                <i class="bi bi-file-earmark-text display-6 mb-2"></i>
                                                <h6>View Results</h6>
                                                <small>Check previous results</small>
                                            </button>
                                        </div>
                                        <div class="col-6">
                                            <button class="btn w-100 p-4 quick-action-btn" onclick="switchPage('reports')" style="background: #F0EFFF;">
                                                <i class="bi bi-file-earmark-bar-graph display-6 mb-2"></i>
                                                <h6>Generate Report</h6>
                                                <small>Export detailed reports</small>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Duplicates by Barangay</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="barangayChart" height="300"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="row g-4">
                        <!-- <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Quick Actions</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-6">
                                            <button class="btn btn-primary w-100 p-4 quick-action-btn" data-bs-toggle="modal" data-bs-target="#importModal">
                                                <i class="bi bi-upload display-6 mb-2"></i>
                                                <h6>Import List</h6>
                                                <small>Upload received beneficiaries</small>
                                            </button>
                                        </div>
                                        <div class="col-6">
                                            <button class="btn btn-success w-100 p-4 quick-action-btn" onclick="switchPage('check-duplicates')">
                                                <i class="bi bi-search display-6 mb-2"></i>
                                                <h6>Check Duplicates</h6>
                                                <small>Upload new list to verify</small>
                                            </button>
                                        </div>
                                        <div class="col-6">
                                            <button class="btn btn-info w-100 p-4 quick-action-btn" onclick="switchPage('results')">
                                                <i class="bi bi-file-earmark-text display-6 mb-2"></i>
                                                <h6>View Results</h6>
                                                <small>Check previous results</small>
                                            </button>
                                        </div>
                                        <div class="col-6">
                                            <button class="btn btn-warning w-100 p-4 quick-action-btn" onclick="switchPage('reports')">
                                                <i class="bi bi-file-earmark-bar-graph display-6 mb-2"></i>
                                                <h6>Generate Report</h6>
                                                <small>Export detailed reports</small>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div> -->
                        <div class="col-lg-6" style="width: 100%;">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Recent Checks</h5>
                                    <button class="btn btn-sm btn-primary" style="background: #2D3250; border-color: #2D3250;">View All</button>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Batch Name</th>
                                                    <th>Date</th>
                                                    <th>Duplicates</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                try {
                                                    $pdo = getConnection();
                                                    $recent = $pdo->query(
                                                        "SELECT * FROM check_history ORDER BY check_date DESC LIMIT 5"
                                                    )->fetchAll();
                                                    
                                                    if (empty($recent)) {
                                                        echo "<tr><td colspan='4' class='text-center text-muted py-4'>No results to display</td></tr>";
                                                    } else {
                                                        foreach ($recent as $record) {
                                                            $status = $record['duplicates_found'] > 0 ? 'Flagged' : 'Clean';
                                                            $badgeClass = $record['duplicates_found'] > 0 ? 'bg-danger' : 'bg-success';
                                                            
                                                            echo "<tr>";
                                                            echo "<td>" . htmlspecialchars($record['batch_name'] ?? 'N/A') . "</td>";
                                                            echo "<td>" . date('M d, Y', strtotime($record['check_date'])) . "</td>";
                                                            echo "<td><span class='badge $badgeClass'>" . $record['duplicates_found'] . "</span></td>";
                                                            echo "<td><span class='badge $badgeClass'>$status</span></td>";
                                                            echo "</tr>";
                                                        }
                                                    }
                                                } catch (Exception $e) {
                                                    echo "<tr><td colspan='4' class='text-center text-muted py-4'>No data available</td></tr>";
                                                }
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Import List Page (now opens modal) -->
                <div class="page" id="import-master-page">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0"><i class="bi bi-upload"></i> Import List</h5>
                                </div>
                                <div class="card-body text-center">
                                    <p class="mb-3">Import master lists using the modal interface.</p>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#importModal">
                                        <i class="bi bi-cloud-upload"></i> Open Import Modal
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Import Modal -->
                <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
                  <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title" id="importModalLabel"><i class="bi bi-upload"></i> Import Master List</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body">
                        <form id="masterListForm" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label">Select Excel File</label>
                                <div class="file-upload-wrapper">
                                    <div class="file-upload-area" id="masterDropZone">
                                        <i class="bi bi-cloud-upload display-4 text-primary mb-3"></i>
                                        <h5>Drag & Drop your file here</h5>
                                        <p class="text-muted">or click to browse</p>
                                        <input type="file" class="file-input" id="masterFile" name="masterFile" accept=".xlsx,.xls" required>
                                    </div>
                                    <div class="file-info mt-2" id="masterFileInfo" style="display: none;">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi bi-file-earmark-excel text-success"></i>
                                            <span id="masterFileName"></span>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearFile('master')">
                                                <i class="bi bi-x"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="batchName" class="form-label">Batch Name (Optional)</label>
                                <input type="text" class="form-control" id="batchName" name="batchName" placeholder="e.g., January 2024 Payout">
                            </div>
                        </form>
                        <div id="masterUploadStatus" class="mt-3"></div>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" id="importModalSubmit">
                            <i class="bi bi-cloud-upload"></i> Import List
                        </button>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Check Duplicates Page -->
                <div class="page" id="check-duplicates-page">
                    <div class="row g-4">
                        <div class="col-lg-7">
                            <div class="card">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0"><i class="bi bi-search"></i> Check New Beneficiary List</h5>
                                </div>
                                <div class="card-body">
                                    <form id="checkDuplicatesForm" enctype="multipart/form-data">
                                        <div class="mb-3">
                                            <label class="form-label">Upload New Beneficiary List</label>
                                            <div class="file-upload-wrapper">
                                                <div class="file-upload-area" id="newDropZone">
                                                    <i class="bi bi-file-earmark-arrow-up display-4 text-success mb-3"></i>
                                                    <h5>Drop your new list here</h5>
                                                    <p class="text-muted">Excel files only (.xlsx, .xls)</p>
                                                    <input type="file" class="file-input" id="newFile" name="newFile" accept=".xlsx,.xls" required>
                                                </div>
                                                <div class="file-info mt-2" id="newFileInfo" style="display: none;">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <i class="bi bi-file-earmark-excel text-success"></i>
                                                        <span id="newFileName"></span>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearFile('new')">
                                                            <i class="bi bi-x"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="card bg-light mb-3">
                                            <div class="card-body">
                                                <h6 class="mb-3">Matching Criteria</h6>
                                                <div class="row">
                                                    <div class="col-md-4">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" id="matchName" checked>
                                                            <label class="form-check-label" for="matchName">
                                                                <strong>Match Name</strong>
                                                            </label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" id="matchBarangay" checked>
                                                            <label class="form-check-label" for="matchBarangay">
                                                                <strong>Match Barangay</strong>
                                                            </label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" id="matchBirthday" checked>
                                                            <label class="form-check-label" for="matchBirthday">
                                                                <strong>Match Birthday</strong>
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="fuzzyMatch">
                                                <label class="form-check-label" for="fuzzyMatch">
                                                    <strong>Enable Fuzzy Name Matching</strong>
                                                    <br><small class="text-muted">Detects similar names with minor spelling differences (e.g., "Jon" vs "John")</small>
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-success w-100 btn-lg">
                                            <i class="bi bi-search"></i> Check for Duplicates
                                        </button>
                                    </form>
                                    <div id="checkStatus" class="mt-3"></div>
                                </div>
                            </div>
                        </div>
                        <!-- <div class="col-lg-5">
                            <div class="card">
                                <div class="card-header bg-dark text-white">
                                    <h5 class="mb-0"><i class="bi bi-lightbulb"></i> Tips</h5>
                                </div>
                                <div class="card-body">
                                    <div class="tip-item">
                                        <i class="bi bi-check-circle-fill text-success"></i>
                                        <p>Select all three criteria (Name, Barangay, Birthday) for the most accurate matching</p>
                                    </div>
                                    <div class="tip-item">
                                        <i class="bi bi-check-circle-fill text-success"></i>
                                        <p>Use fuzzy matching when names might have slight variations</p>
                                    </div>
                                    <div class="tip-item">
                                        <i class="bi bi-check-circle-fill text-success"></i>
                                        <p>Ensure date format is consistent in your Excel files</p>
                                    </div>
                                    <div class="tip-item">
                                        <i class="bi bi-exclamation-circle-fill text-warning"></i>
                                        <p>Large files may take longer to process. Be patient.</p>
                                    </div>
                                </div>
                            </div>
                        </div> -->
                    </div>
                </div>

                <!-- Results Page -->
                <div class="page" id="results-page">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-table"></i> Duplicate Check Results</h5>
                                <div class="btn-group">
                                    <button class="btn btn-light btn-sm" onclick="exportDuplicates()">
                                        <i class="bi bi-download"></i> Export Duplicates
                                    </button>
                                    <button class="btn btn-light btn-sm" onclick="exportCleanList()">
                                        <i class="bi bi-download"></i> Export Clean List
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="resultSummary" class="mb-4"></div>
                            <div class="table-responsive">
                                <table class="table table-hover" id="resultsTable">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>#</th>
                                            <th>Name</th>
                                            <th>Barangay</th>
                                            <th>Birthday</th>
                                            <th>Status</th>
                                            <th>Match Type</th>
                                        </tr>
                                    </thead>
                                    <tbody id="resultsBody">
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-5">
                                                <i class="bi bi-inbox display-1 d-block mb-3"></i>
                                                <h5>No results to display</h5>
                                                <p>Run a duplicate check first to see results here</p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- History Page -->
                <div class="page" id="history-page">
                    <div class="card">
                        <div class="card-header bg-secondary text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Check History</h5>
                                <input type="text" class="form-control form-control-sm w-auto" placeholder="Search history...">
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Batch Name</th>
                                            <th>Total Checked</th>
                                            <th>Duplicates Found</th>
                                            <th>Clean Records</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        try {
                                            $pdo = getConnection();
                                            $history = $pdo->query(
                                                "SELECT * FROM check_history ORDER BY check_date DESC"
                                            )->fetchAll();
                                            
                                            foreach ($history as $record) {
                                                echo "<tr>";
                                                echo "<td>" . date('M d, Y H:i', strtotime($record['check_date'])) . "</td>";
                                                echo "<td>" . htmlspecialchars($record['batch_name'] ?? 'N/A') . "</td>";
                                                echo "<td>" . $record['total_checked'] . "</td>";
                                                echo "<td><span class='badge bg-danger'>" . $record['duplicates_found'] . "</span></td>";
                                                echo "<td><span class='badge bg-success'>" . $record['clean_records'] . "</span></td>";
                                                echo "<td>
                                                    <button class='btn btn-info btn-sm' onclick='viewCheckDetails(" . $record['id'] . ")'>
                                                        <i class='bi bi-eye'></i> View
                                                    </button>
                                                    <button class='btn btn-danger btn-sm'>
                                                        <i class='bi bi-trash'></i>
                                                    </button>
                                                </td>";
                                                echo "</tr>";
                                            }
                                        } catch (Exception $e) {
                                            echo "<tr><td colspan='6' class='text-center'>No history available</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reports Page -->
                <div class="page" id="reports-page">
                    <div class="row g-4">
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-body text-center p-4">
                                    <i class="bi bi-file-earmark-pdf display-1 text-danger mb-3"></i>
                                    <h5>Duplicate Report</h5>
                                    <p>Complete list of all duplicates found</p>
                                    <button class="btn btn-danger">
                                        <i class="bi bi-download"></i> Generate PDF
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-body text-center p-4">
                                    <i class="bi bi-file-earmark-excel display-1 text-success mb-3"></i>
                                    <h5>Excel Export</h5>
                                    <p>Export all data to Excel format</p>
                                    <button class="btn btn-success">
                                        <i class="bi bi-download"></i> Export Excel
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-body text-center p-4">
                                    <i class="bi bi-graph-up display-1 text-primary mb-3"></i>
                                    <h5>Analytics Report</h5>
                                    <p>Detailed analytics and trends</p>
                                    <button class="btn btn-primary">
                                        <i class="bi bi-download"></i> Download Report
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Settings Page -->
                <div class="page" id="settings-page">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">General Settings</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Default Matching Criteria</label>
                                        <select class="form-select">
                                            <option>Strict (Name + Barangay + Birthday)</option>
                                            <option>Moderate (Name + Barangay)</option>
                                            <option>Loose (Name only)</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Fuzzy Matching Threshold</label>
                                        <input type="range" class="form-range" min="70" max="100" value="85">
                                        <div class="d-flex justify-content-between">
                                            <small>70% Similar</small>
                                            <small>85% (Default)</small>
                                            <small>100% Exact</small>
                                        </div>
                                    </div>
                                    <button class="btn btn-primary">Save Settings</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title">Check Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailsContent">
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>