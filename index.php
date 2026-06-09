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
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo-container">
                    <div class="logo-text text-uppercase">
                        <h2>Dupli_Checker</h2>
                    </div>
                </div>
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
                    <!-- <li class="nav-item">
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
                    </li> -->
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
                            <!-- <div class="search-box">
                                <i class="bi bi-search"></i>
                                <input type="text" class="form-control" placeholder="Search beneficiaries...">
                            </div> -->
                            
                            <!-- Notifications -->
                            <!-- <div class="dropdown">
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
                            </div> -->
                            
                            <!-- User Menu -->
                            <div class="dropdown">
                                <button class="btn btn-link user-menu-btn" data-bs-toggle="dropdown">
                                    <div class="avatar-sm">
                                        <i class="bi bi-person-circle"></i>
                                    </div>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <div class="dropdown-header">Logged in as: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></div>
                                    <!-- <a class="dropdown-item" href="#"><i class="bi bi-person"></i> Profile</a>
                                    <a class="dropdown-item" href="#"><i class="bi bi-gear"></i> Settings</a>
                                    <div class="dropdown-divider"></div> -->
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
                    </div>

                    <!-- Charts Row -->
                    <div class="row g-4 mb-4">
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
                                            <button class="btn w-100 p-4 quick-action-btn" onclick="openDuplicateCheckPage()" style="background: #F0EFFF;">
                                                <i class="bi bi-search display-6 mb-2"></i>
                                                <h6>Check Duplicates</h6>
                                                <small>Scan latest imported list</small>
                                            </button>
                                        </div>
                                        <!-- <div class="col-6">
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
                                        </div> -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Duplicates by Barangay</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="barangayChart" height="300"></canvas>
                                </div>
                            </div>
                        </div> -->
                    </div>

                    <!-- Recent Activity -->
                    <!-- <div class="row g-4">
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
                    </div> -->
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
                            <!-- <div class="mb-3">
                                <label class="form-label">Note</label>
                                <div class="alert alert-info py-2">
                                    The Excel file must include: <strong>Name, Barangay, Birthday, Assistance Receive</strong>.
                                    The Assistance Receive value will be stored in the record's <code>batch_reference</code> field.
                                </div>
                            </div> -->
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
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="bi bi-search"></i> Duplicate Check Results</h5>
                                    <div>
                                        <button class="btn btn-light btn-sm me-2" onclick="window.location.reload()">
                                            <i class="bi bi-arrow-repeat"></i> New Check
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="deleteAllDuplicates()" id="deleteAllBtn" style="display: none;">
                                            <i class="bi bi-trash"></i> Delete All Duplicates
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div id="duplicateCheckLoader" class="text-center py-5" style="display: none;">
                                        <div class="spinner-border text-primary mb-3" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <h5>Scanning for duplicates...</h5>
                                        <p class="text-muted">Please wait while we analyze the latest imported list</p>
                                    </div>
                                    <div id="duplicateCheckResults">
                                        <!-- Results will be loaded here dynamically -->
                                    </div>
                                </div>
                            </div>
                        </div>
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
                            <div class="mb-3">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="sortNameBtn" onclick="toggleSortResults('name')">
                                    <i class="bi bi-arrow-down-up me-1"></i>
                                    Sort by Name A → Z
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover" id="resultsTable">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>#</th>
                                            <th class="sortable" data-sort="name" onclick="toggleSortResults('name')">Name <i class="bi bi-arrow-down-up sort-icon"></i></th>
                                            <th class="sortable" data-sort="barangay" onclick="toggleSortResults('barangay')">Barangay <i class="bi bi-arrow-down-up sort-icon"></i></th>
                                            <th class="sortable" data-sort="birthday" onclick="toggleSortResults('birthday')">Birthday <i class="bi bi-arrow-down-up sort-icon"></i></th>
                                            <th class="sortable" data-sort="status" onclick="toggleSortResults('status')">Status <i class="bi bi-arrow-down-up sort-icon"></i></th>
                                            <th class="sortable" data-sort="match_type" onclick="toggleSortResults('match_type')">Match Type <i class="bi bi-arrow-down-up sort-icon"></i></th>
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
                                                    <button class='btn btn-danger btn-sm' onclick='deleteCheckHistory(" . $record['id'] . ")'>
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
                    <h5 class="modal-title">Beneficiary Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailsContent">
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this record?</p>
                    <p class="text-danger"><small>This action cannot be undone!</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
    
    <script>
    let currentDeleteId = null;
    let currentDuplicatesData = null;
    
    // Function to open the duplicate check page and load scanned duplicates
    function openDuplicateCheckPage() {
        // Switch to the check duplicates page
        switchPage('check-duplicates');
        
        // Show loader and fetch duplicates
        $('#duplicateCheckLoader').show();
        $('#duplicateCheckResults').html('');
        
        // Fuzzy matching is always enabled - no need for parameters
        $.ajax({
            url: 'check_duplicates_api.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'scan_duplicates',
                fuzzy_match: true  // Always enabled
            },
            success: function(response) {
                $('#duplicateCheckLoader').hide();
                
                if (response.success) {
                    displayDuplicateResults(response);
                } else {
                    $('#duplicateCheckResults').html(`
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle-fill"></i> 
                            ${response.message || 'Error scanning duplicates. Please make sure you have imported a list first.'}
                        </div>
                    `);
                }
            },
            error: function(xhr, status, error) {
                $('#duplicateCheckLoader').hide();
                $('#duplicateCheckResults').html(`
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill"></i> 
                        An error occurred while scanning for duplicates. Please try again.
                        <br><small class="text-muted">${error}</small>
                    </div>
                `);
            }
        });
    }
    
    // Function to display duplicate results with Accept buttons
    function displayDuplicateResults(data) {
        var html = '';
        
        // Summary section
        html += `
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <h3 class="text-primary">${data.total_checked || 0}</h3>
                            <p class="mb-0">Total Records Checked</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <h3 class="text-danger">${data.duplicates_found || 0}</h3>
                            <p class="mb-0">Potential Duplicates</p>
                            <small class="text-muted">Needs review</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <h3 class="text-success">${data.clean_records || 0}</h3>
                            <p class="mb-0">Clean Records</p>
                            <small class="text-muted">Already verified</small>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Single table view of all duplicates
        if (data.duplicates && data.duplicates.length > 0) {
            html += `
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle-fill"></i> 
                    Found <strong>${data.duplicates.length}</strong> potential duplicate(s). Review each record below.
                    <br><small>• Click <strong>Accept as Clean</strong> if this is NOT a duplicate</small>
                    <br><small>• Click <strong>Delete</strong> if this IS a duplicate</small>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-bordered" id="duplicatesTable">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAllDuplicates" onclick="toggleSelectAllDuplicates()"></th>
                                <th>#</th>
                                <th>Full Name</th>
                                <th>Barangay</th>
                                <th>Birthday</th>
                                <th>Batch Reference</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            // Sort duplicates by name
            var sortedDuplicates = [...data.duplicates];
            sortedDuplicates.sort(function(a, b) {
                return a.full_name.localeCompare(b.full_name);
            });
            
            sortedDuplicates.forEach(function(dup, index) {
                var matchBadge = '';
                if (dup.match_level >= 98) {
                    matchBadge = '<span class="badge bg-danger">' + dup.match_level + '% - Almost Identical</span>';
                } else if (dup.match_level >= 95) {
                    matchBadge = '<span class="badge bg-warning text-dark">' + dup.match_level + '% - Very High Match</span>';
                } else {
                    matchBadge = '<span class="badge bg-info">' + dup.match_level + '% - High Confidence</span>';
                }
                
                var rowClass = dup.match_details === 'Original record (has high-confidence duplicates)' ? 'table-warning' : '';
                
                html += `
                    <tr id="duplicate-row-${dup.id}" class="${rowClass}">
                        <td><input type="checkbox" class="duplicate-checkbox" value="${dup.id}"></td>
                        <td>${index + 1}${dup.match_details === 'Original record (has high-confidence duplicates)' ? ' <span class="badge bg-warning">Original</span>' : ''}</td>
                        <td><strong>${escapeHtml(dup.full_name)}</strong> <br><small class="text-muted">${escapeHtml(dup.match_details)}</small></td>
                        <td>${escapeHtml(dup.barangay)}</td>
                        <td>${escapeHtml(dup.birthday)}</td>
                        <td><span class="badge bg-secondary">${escapeHtml(dup.batch_reference)}</span></td>
                        <td>
                            <button class="btn btn-sm btn-success mb-1" onclick="acceptAsClean(${dup.id})" title="Accept as clean record">
                                <i class="bi bi-check-circle"></i> Accept as Clean
                            </button>
                            <button class="btn btn-sm btn-info mb-1" onclick="viewDuplicateDetails(${dup.id})">
                                <i class="bi bi-eye"></i> View
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteDuplicate(${dup.id})">
                                <i class="bi bi-trash"></i> Delete
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            html += `
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <div class="mb-2">
                            <button class="btn btn-warning me-2" onclick="selectAllDuplicates()">
                                <i class="bi bi-check-all"></i> Select All
                            </button>
                            <button class="btn btn-success me-2" onclick="acceptSelectedAsClean()">
                                <i class="bi bi-check-circle"></i> Accept Selected as Clean
                            </button>
                            <button class="btn btn-danger me-2" onclick="deleteSelectedDuplicates()">
                                <i class="bi bi-trash"></i> Delete Selected
                            </button>
                            <button class="btn btn-secondary" onclick="deselectAllDuplicates()">
                                <i class="bi bi-x-circle"></i> Deselect All
                            </button>
                        </div>
                        <div class="mb-2">
                            <button class="btn btn-info me-2" onclick="acceptAllAsClean()">
                                <i class="bi bi-check-all"></i> Accept All as Clean
                            </button>
                            <button class="btn btn-success me-2" onclick="exportCleanRecords()">
                                <i class="bi bi-download"></i> Export Clean Records
                            </button>
                            <button class="btn btn-primary" onclick="exportDuplicateResults()">
                                <i class="bi bi-download"></i> Export Duplicates
                            </button>
                        </div>
                    </div>
                </div>
            `;
        } else {
            html += `
                <div class="alert alert-success text-center">
                    <i class="bi bi-check-circle-fill display-4 d-block mb-3"></i>
                    <h4>No Pending Duplicates!</h4>
                    <p>All records have been reviewed and marked as clean.</p>
                    <button class="btn btn-success mt-3" onclick="exportCleanRecords()">
                        <i class="bi bi-download"></i> Export All Records
                    </button>
                </div>
            `;
        }
        
        $('#duplicateCheckResults').html(html);
        
        // Store results in localStorage
        if (data.duplicates) {
            localStorage.setItem('lastDuplicateResults', JSON.stringify(data));
        }
        if (data.clean_records_data) {
            localStorage.setItem('cleanRecords', JSON.stringify(data.clean_records_data));
        }
    }

    // Accept a single record as clean
    function acceptAsClean(id) {
        Swal.fire({
            title: 'Accept as Clean?',
            text: 'This record will be marked as clean and removed from the duplicate list.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, accept as clean!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'check_duplicates_api.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'accept_as_clean',
                        id: id
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Accepted!', response.message, 'success');
                            $(`#duplicate-row-${id}`).fadeOut(300, function() {
                                $(this).remove();
                                var remainingCount = $('.duplicate-checkbox').length;
                                $('.text-danger').closest('.col-md-3').find('h3').text(remainingCount);
                                if (remainingCount === 0) {
                                    setTimeout(function() {
                                        location.reload();
                                    }, 1500);
                                }
                            });
                        } else {
                            Swal.fire('Error!', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error!', 'Failed to accept record', 'error');
                    }
                });
            }
        });
    }

    // Accept selected records as clean
    function acceptSelectedAsClean() {
        var selectedIds = [];
        $('.duplicate-checkbox:checked').each(function() {
            selectedIds.push($(this).val());
        });
        
        if (selectedIds.length === 0) {
            Swal.fire('Warning', 'Please select at least one record to accept', 'warning');
            return;
        }
        
        Swal.fire({
            title: 'Accept Selected as Clean?',
            text: `You are about to accept ${selectedIds.length} record(s) as clean. They will be removed from the duplicate list.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, accept them!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'check_duplicates_api.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'accept_selected_as_clean',
                        ids: selectedIds
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Accepted!', response.message, 'success');
                            selectedIds.forEach(function(id) {
                                $(`#duplicate-row-${id}`).fadeOut(300, function() {
                                    $(this).remove();
                                });
                            });
                            var remainingCount = $('.duplicate-checkbox').length;
                            $('.text-danger').closest('.col-md-3').find('h3').text(remainingCount);
                            if (remainingCount === 0) {
                                setTimeout(function() {
                                    location.reload();
                                }, 1500);
                            }
                        } else {
                            Swal.fire('Error!', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error!', 'Failed to accept records', 'error');
                    }
                });
            }
        });
    }

    // Accept all duplicates as clean
    function acceptAllAsClean() {
        var totalRecords = $('.duplicate-checkbox').length;
        if (totalRecords === 0) {
            Swal.fire('Info', 'No records to accept', 'info');
            return;
        }
        
        Swal.fire({
            title: 'Accept All as Clean?',
            text: `You are about to accept all ${totalRecords} record(s) as clean. This will remove them from the duplicate list.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, accept all!'
        }).then((result) => {
            if (result.isConfirmed) {
                var data = localStorage.getItem('lastDuplicateResults');
                var batchRef = null;
                if (data) {
                    var results = JSON.parse(data);
                    batchRef = results.batch_reference;
                }
                
                $.ajax({
                    url: 'check_duplicates_api.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'accept_all_as_clean',
                        batch_reference: batchRef
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Accepted!', response.message, 'success');
                            location.reload();
                        } else {
                            Swal.fire('Error!', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error!', 'Failed to accept records', 'error');
                    }
                });
            }
        });
    }

    // Toggle select all duplicates
    function toggleSelectAllDuplicates() {
        var isChecked = $('#selectAllDuplicates').is(':checked');
        $('.duplicate-checkbox').prop('checked', isChecked);
    }

    // Select all duplicates
    function selectAllDuplicates() {
        $('.duplicate-checkbox').prop('checked', true);
        $('#selectAllDuplicates').prop('checked', true);
    }

    // Deselect all duplicates
    function deselectAllDuplicates() {
        $('.duplicate-checkbox').prop('checked', false);
        $('#selectAllDuplicates').prop('checked', false);
    }

    // Delete selected duplicates
    function deleteSelectedDuplicates() {
        var selectedIds = [];
        $('.duplicate-checkbox:checked').each(function() {
            selectedIds.push($(this).val());
        });
        
        if (selectedIds.length === 0) {
            Swal.fire('Warning', 'Please select at least one record to delete', 'warning');
            return;
        }
        
        Swal.fire({
            title: 'Are you sure?',
            text: `You are about to delete ${selectedIds.length} record(s). This action cannot be undone!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete them!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'check_duplicates_api.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'delete_duplicates',
                        ids: selectedIds
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Deleted!', response.message, 'success');
                            // Remove deleted rows from table
                            selectedIds.forEach(function(id) {
                                $(`#duplicate-row-${id}`).remove();
                            });
                            // Update count
                            var remainingCount = $('.duplicate-checkbox').length;
                            $('.text-danger').closest('.col-md-3').find('h3').text(remainingCount);
                            
                            if (remainingCount === 0) {
                                setTimeout(function() {
                                    Swal.fire('Success!', 'All duplicates have been cleared', 'success');
                                    location.reload();
                                }, 1500);
                            }
                        } else {
                            Swal.fire('Error!', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error!', 'Failed to delete records', 'error');
                    }
                });
            }
        });
    }

    // Delete single duplicate
    function deleteDuplicate(id) {
        Swal.fire({
            title: 'Delete Record?',
            text: 'This action cannot be undone!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'check_duplicates_api.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'delete_duplicate',
                        id: id
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Deleted!', response.message, 'success');
                            $(`#duplicate-row-${id}`).remove();
                            // Update count
                            var remainingCount = $('.duplicate-checkbox').length;
                            $('.text-danger').closest('.col-md-3').find('h3').text(remainingCount);
                        } else {
                            Swal.fire('Error!', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error!', 'Failed to delete record', 'error');
                    }
                });
            }
        });
    }

    // Toggle select all duplicates
    function toggleSelectAllDuplicates() {
        var isChecked = $('#selectAllDuplicates').is(':checked');
        $('.duplicate-checkbox').prop('checked', isChecked);
    }

    // Select all duplicates
    function selectAllDuplicates() {
        $('.duplicate-checkbox').prop('checked', true);
        $('#selectAllDuplicates').prop('checked', true);
    }

    // Deselect all duplicates
    function deselectAllDuplicates() {
        $('.duplicate-checkbox').prop('checked', false);
        $('#selectAllDuplicates').prop('checked', false);
    }

    // Delete selected duplicates
    function deleteSelectedDuplicates() {
        var selectedIds = [];
        $('.duplicate-checkbox:checked').each(function() {
            selectedIds.push($(this).val());
        });
        
        if (selectedIds.length === 0) {
            Swal.fire('Warning', 'Please select at least one record to delete', 'warning');
            return;
        }
        
        Swal.fire({
            title: 'Are you sure?',
            text: `You are about to delete ${selectedIds.length} record(s). This action cannot be undone!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete them!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'check_duplicates_api.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'delete_duplicates',
                        ids: selectedIds
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Deleted!', response.message, 'success');
                            // Remove deleted rows from table
                            selectedIds.forEach(function(id) {
                                $(`#duplicate-row-${id}`).remove();
                            });
                            // Update count
                            var remainingCount = $('.duplicate-checkbox').length;
                            $('.text-danger').closest('.col-md-3').find('h3').text(remainingCount);
                            
                            if (remainingCount === 0) {
                                setTimeout(function() {
                                    location.reload();
                                }, 1500);
                            }
                        } else {
                            Swal.fire('Error!', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error!', 'Failed to delete records', 'error');
                    }
                });
            }
        });
    }

    // Delete single duplicate
    function deleteDuplicate(id) {
        Swal.fire({
            title: 'Delete Record?',
            text: 'This action cannot be undone!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'check_duplicates_api.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'delete_duplicate',
                        id: id
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Deleted!', response.message, 'success');
                            $(`#duplicate-row-${id}`).remove();
                            // Update count
                            var remainingCount = $('.duplicate-checkbox').length;
                            $('.text-danger').closest('.col-md-3').find('h3').text(remainingCount);
                        } else {
                            Swal.fire('Error!', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error!', 'Failed to delete record', 'error');
                    }
                });
            }
        });
    }

    // Export clean records
    function exportCleanRecords() {
        var data = localStorage.getItem('lastDuplicateResults');
        if (data) {
            var results = JSON.parse(data);
            
            if (results.clean_records_data && results.clean_records_data.length > 0) {
                var csv = "Batch Reference,Full Name,Barangay,Birthday\n";
                results.clean_records_data.forEach(function(record) {
                    csv += `"${escapeHtml(record.batch_reference || '')}","${escapeHtml(record.full_name)}","${escapeHtml(record.barangay || '')}","${escapeHtml(record.birthday || '')}"\n`;
                });
                
                downloadCSV(csv, 'clean_records_' + new Date().toISOString().slice(0,19) + '.csv');
                Swal.fire('Success!', `${results.clean_records_data.length} clean records exported`, 'success');
            } else {
                Swal.fire('Info', 'No clean records found to export', 'info');
            }
        } else {
            fetchCleanRecordsFromServer();
        }
    }

    // Export duplicate results
    function exportDuplicateResults() {
        var data = localStorage.getItem('lastDuplicateResults');
        if (data) {
            var results = JSON.parse(data);
            var csv = "Batch Reference,Full Name,Barangay,Birthday,Match Level,Match Details\n";
            
            if (results.duplicates && results.duplicates.length > 0) {
                results.duplicates.forEach(function(dup) {
                    csv += `"${escapeHtml(dup.batch_reference || '')}","${escapeHtml(dup.full_name)}","${escapeHtml(dup.barangay || '')}","${escapeHtml(dup.birthday || '')}","${dup.match_level || 0}%","${escapeHtml(dup.match_details || '')}"\n`;
                });
            } else {
                csv += `"","No duplicates found","","","",""\n`;
            }
            
            downloadCSV(csv, 'duplicate_results_' + new Date().toISOString().slice(0,19) + '.csv');
            Swal.fire('Success!', 'Duplicates exported successfully', 'success');
        } else {
            Swal.fire('Info', 'No duplicate results to export', 'info');
        }
    }

    // Helper function to download CSV
    function downloadCSV(csv, filename) {
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        var url = URL.createObjectURL(blob);
        link.href = url;
        link.setAttribute('download', filename);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    // Fetch clean records from server
    function fetchCleanRecordsFromServer() {
        Swal.fire({
            title: 'Loading...',
            text: 'Fetching clean records from database',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        $.ajax({
            url: 'check_duplicates_api.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'get_clean_records'
            },
            success: function(response) {
                Swal.close();
                if (response.success && response.records && response.records.length > 0) {
                    var csv = "Batch Reference,Full Name,Barangay,Birthday\n";
                    response.records.forEach(function(record) {
                        csv += `"${escapeHtml(record.batch_reference || '')}","${escapeHtml(record.full_name)}","${escapeHtml(record.barangay || '')}","${escapeHtml(record.birthday || '')}"\n`;
                    });
                    downloadCSV(csv, 'clean_records_' + new Date().toISOString().slice(0,19) + '.csv');
                    Swal.fire('Success!', `${response.records.length} clean records exported`, 'success');
                } else {
                    Swal.fire('Info', 'No clean records found in the database', 'info');
                }
            },
            error: function() {
                Swal.close();
                Swal.fire('Error!', 'Failed to fetch clean records', 'error');
            }
        });
    }

    // Helper function to escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // View duplicate details
    function viewDuplicateDetails(id) {
        $.ajax({
            url: 'check_duplicates_api.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'get_duplicate_details',
                id: id
            },
            success: function(response) {
                if (response.success && response.data) {
                    var details = response.data;
                    var modalContent = `
                        <div class="row">
                            <div class="col-md-12">
                                <h6>Beneficiary Information</h6>
                                <table class="table table-bordered">
                                    <tr>
                                        <th style="width: 150px;">Full Name:</th>
                                        <td>${escapeHtml(details.full_name)}</td>
                                    </tr>
                                    <tr>
                                        <th>Barangay:</th>
                                        <td>${escapeHtml(details.barangay)}</td>
                                    </tr>
                                    <tr>
                                        <th>Birthday:</th>
                                        <td>${escapeHtml(details.birthday)}</td>
                                    </tr>
                                    <tr>
                                        <th>Batch Reference:</th>
                                        <td><code>${escapeHtml(details.batch_reference)}</code></td>
                                    </tr>
                                    <tr>
                                        <th>Status:</th>
                                        <td><span class="badge bg-danger">Potential Duplicate</span></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    `;
                    $('#detailsContent').html(modalContent);
                    new bootstrap.Modal(document.getElementById('detailsModal')).show();
                }
            },
            error: function() {
                Swal.fire('Error', 'Could not load beneficiary details', 'error');
            }
        });
    }

    // Update the openDuplicateCheckPage function to show/hide delete all button
    function openDuplicateCheckPage() {
        switchPage('check-duplicates');
        
        $('#duplicateCheckLoader').show();
        $('#duplicateCheckResults').html('');
        
        var matchName = localStorage.getItem('matchName') !== 'false';
        var matchBarangay = localStorage.getItem('matchBarangay') !== 'false';
        var matchBirthday = localStorage.getItem('matchBirthday') !== 'false';
        var fuzzyMatch = localStorage.getItem('fuzzyMatch') === 'true';
        
        $.ajax({
            url: 'check_duplicates_api.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'scan_duplicates',
                match_name: matchName,
                match_barangay: matchBarangay,
                match_birthday: matchBirthday,
                fuzzy_match: fuzzyMatch
            },
            success: function(response) {
                $('#duplicateCheckLoader').hide();
                
                if (response.success) {
                    displayDuplicateResults(response);
                } else {
                    $('#duplicateCheckResults').html(`
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle-fill"></i> 
                            ${response.message || 'Error scanning duplicates. Please make sure you have imported a list first.'}
                        </div>
                    `);
                }
            },
            error: function(xhr, status, error) {
                $('#duplicateCheckLoader').hide();
                $('#duplicateCheckResults').html(`
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill"></i> 
                        An error occurred while scanning for duplicates. Please try again.
                        <br><small class="text-muted">${error}</small>
                    </div>
                `);
            }
        });
    }

    // Additional helper functions for group management
    function toggleGroupCheckboxes(groupId, checked) {
        $(`.duplicate-checkbox-${groupId}`).prop('checked', checked);
    }

    function deleteSelectedFromGroup(groupId) {
        var selectedIds = [];
        $(`.duplicate-checkbox-${groupId}:checked`).each(function() {
            selectedIds.push($(this).val());
        });
        
        if (selectedIds.length === 0) {
            Swal.fire('Warning', 'Please select at least one record to delete', 'warning');
            return;
        }
        
        Swal.fire({
            title: 'Are you sure?',
            text: `You are about to delete ${selectedIds.length} record(s). This action cannot be undone!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Yes, delete them!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'check_duplicates_api.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'delete_duplicates',
                        ids: selectedIds
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Deleted!', response.message, 'success');
                            location.reload();
                        } else {
                            Swal.fire('Error!', response.message, 'error');
                        }
                    }
                });
            }
        });
    }

    function keepSelectedFromGroup(groupId) {
        // This will keep the selected records (remove them from the "to-delete" list)
        // Essentially uncheck them so they won't be deleted
        $(`.duplicate-checkbox-${groupId}:checked`).prop('checked', false);
        Swal.fire('Success', 'Selected records will be kept', 'success');
    }

    function keepRecord(recordId) {
        Swal.fire({
            title: 'Keep Record?',
            text: 'This record will be marked as clean and kept in the database.',
            icon: 'info',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            confirmButtonText: 'Yes, keep it!'
        }).then((result) => {
            if (result.isConfirmed) {
                // Remove from duplicates list (just refresh or update UI)
                Swal.fire('Success', 'Record will be kept', 'success');
            }
        });
    }

    function deleteGroupDuplicates(groupId) {
        Swal.fire({
            title: 'Delete Entire Group?',
            text: 'This will delete all records in this duplicate group. This action cannot be undone!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Yes, delete all!'
        }).then((result) => {
            if (result.isConfirmed) {
                // Get all IDs in this group
                var ids = [groupId];
                $(`.duplicate-checkbox-${groupId}`).each(function() {
                    ids.push($(this).val());
                });
                
                $.ajax({
                    url: 'check_duplicates_api.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'delete_duplicates',
                        ids: ids
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Deleted!', response.message, 'success');
                            location.reload();
                        } else {
                            Swal.fire('Error!', response.message, 'error');
                        }
                    }
                });
            }
        });
    }

    function selectAllDuplicates() {
        $('input[type="checkbox"][class*="duplicate-checkbox-"]').prop('checked', true);
    }

    function deleteAllSelectedDuplicates() {
        var selectedIds = [];
        $('input[type="checkbox"][class*="duplicate-checkbox-"]:checked').each(function() {
            selectedIds.push($(this).val());
        });
        
        if (selectedIds.length === 0) {
            Swal.fire('Warning', 'Please select at least one record to delete', 'warning');
            return;
        }
        
        Swal.fire({
            title: 'Are you sure?',
            text: `You are about to delete ${selectedIds.length} record(s). This action cannot be undone!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Yes, delete them!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'check_duplicates_api.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'delete_duplicates',
                        ids: selectedIds
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Deleted!', response.message, 'success');
                            location.reload();
                        } else {
                            Swal.fire('Error!', response.message, 'error');
                        }
                    }
                });
            }
        });
    }
    
    // Toggle select all checkboxes
    function toggleSelectAll() {
        var selectAll = $('#selectAll').is(':checked');
        $('.duplicate-checkbox').prop('checked', selectAll);
        $('#deleteSelectedBtn').toggle(selectAll);
    }
    
    // Show/hide delete selected button when checkboxes change
    $(document).on('change', '.duplicate-checkbox', function() {
        var anyChecked = $('.duplicate-checkbox:checked').length > 0;
        $('#deleteSelectedBtn').toggle(anyChecked);
    });
    
    // Delete single duplicate
    function deleteDuplicate(id) {
        currentDeleteId = id;
        $('#deleteConfirmModal').modal('show');
    }
    
    // Delete selected duplicates
    function deleteSelectedDuplicates() {
        var selectedIds = [];
        $('.duplicate-checkbox:checked').each(function() {
            selectedIds.push($(this).val());
        });
        
        if (selectedIds.length === 0) {
            Swal.fire('Warning', 'Please select at least one record to delete', 'warning');
            return;
        }
        
        Swal.fire({
            title: 'Are you sure?',
            text: `You are about to delete ${selectedIds.length} record(s). This action cannot be undone!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete them!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'check_duplicates_api.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'delete_duplicates',
                        ids: selectedIds
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Deleted!', response.message, 'success');
                            // Remove deleted rows from table
                            selectedIds.forEach(function(id) {
                                $(`#duplicate-row-${id}`).remove();
                            });
                            // Update counts
                            updateCountsAfterDeletion(selectedIds.length);
                        } else {
                            Swal.fire('Error!', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error!', 'Failed to delete records', 'error');
                    }
                });
            }
        });
    }
    
    // Update counts after deletion
    function updateCountsAfterDeletion(deletedCount) {
        var currentDuplicates = $('.duplicate-checkbox').length;
        var newDuplicatesCount = currentDuplicates - deletedCount;
        
        // Update display counts
        $('.text-danger').closest('.col-md-3').find('h3').text(newDuplicatesCount);
        
        if (newDuplicatesCount === 0) {
            location.reload();
        }
    }
    
    // Delete all duplicates
    function deleteAllDuplicates() {
        Swal.fire({
            title: 'Delete All Duplicates?',
            text: `You are about to delete all ${currentDuplicatesData?.duplicates?.length || 0} duplicate records. This action cannot be undone!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete all!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'check_duplicates_api.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'delete_all_duplicates',
                        batch_reference: currentDuplicatesData?.batch_reference
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Deleted!', response.message, 'success');
                            location.reload();
                        } else {
                            Swal.fire('Error!', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error!', 'Failed to delete duplicates', 'error');
                    }
                });
            }
        });
    }
    
    // Export clean records
    function exportCleanRecords() {
        var data = localStorage.getItem('lastDuplicateResults');
        if (data) {
            var results = JSON.parse(data);
            
            // Check if we have clean records data
            if (results.clean_records_data && results.clean_records_data.length > 0) {
                var csv = "Batch Reference,Full Name,Barangay,Birthday\n";
                results.clean_records_data.forEach(function(record) {
                    csv += `"${escapeHtml(record.batch_reference || '')}","${escapeHtml(record.full_name || record.name)}","${escapeHtml(record.barangay || '')}","${escapeHtml(record.birthday || '')}"\n`;
                });
                
                var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                var link = document.createElement('a');
                var url = URL.createObjectURL(blob);
                link.href = url;
                link.setAttribute('download', 'clean_records_' + new Date().toISOString().slice(0,19) + '.csv');
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            } else {
                // If no clean records in current results, fetch from server
                fetchCleanRecordsFromServer();
            }
        } else {
            // Fetch clean records from server
            fetchCleanRecordsFromServer();
        }
    }

    // Function to fetch clean records from server
    function fetchCleanRecordsFromServer() {
        Swal.fire({
            title: 'Loading...',
            text: 'Fetching clean records from database',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        $.ajax({
            url: 'check_duplicates_api.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'get_clean_records'
            },
            success: function(response) {
                Swal.close();
                if (response.success && response.records && response.records.length > 0) {
                    var csv = "Batch Reference,Full Name,Barangay,Birthday\n";
                    response.records.forEach(function(record) {
                        csv += `"${escapeHtml(record.batch_reference || '')}","${escapeHtml(record.full_name || record.name)}","${escapeHtml(record.barangay || '')}","${escapeHtml(record.birthday || '')}"\n`;
                    });
                    
                    var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                    var link = document.createElement('a');
                    var url = URL.createObjectURL(blob);
                    link.href = url;
                    link.setAttribute('download', 'clean_records_' + new Date().toISOString().slice(0,19) + '.csv');
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(url);
                    
                    Swal.fire('Success!', `${response.records.length} clean records exported`, 'success');
                } else {
                    Swal.fire('Info', 'No clean records found in the database', 'info');
                }
            },
            error: function() {
                Swal.close();
                Swal.fire('Error!', 'Failed to fetch clean records', 'error');
            }
        });
    }
    
    // Confirm delete button handler
    $('#confirmDeleteBtn').click(function() {
        if (currentDeleteId) {
            $.ajax({
                url: 'check_duplicates_api.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'delete_duplicate',
                    id: currentDeleteId
                },
                success: function(response) {
                    $('#deleteConfirmModal').modal('hide');
                    if (response.success) {
                        Swal.fire('Deleted!', response.message, 'success');
                        $(`#duplicate-row-${currentDeleteId}`).remove();
                        updateCountsAfterDeletion(1);
                        currentDeleteId = null;
                    } else {
                        Swal.fire('Error!', response.message, 'error');
                    }
                },
                error: function() {
                    $('#deleteConfirmModal').modal('hide');
                    Swal.fire('Error!', 'Failed to delete record', 'error');
                }
            });
        }
    });
    
    // Function to select a different batch
    function selectDifferentBatch() {
        $.ajax({
            url: 'check_duplicates_api.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'get_batch_list'
            },
            success: function(response) {
                if (response.success && response.batches.length > 0) {
                    var batchOptions = '<option value="">-- Select Batch --</option>';
                    response.batches.forEach(function(batch) {
                        batchOptions += `<option value="${escapeHtml(batch.batch_reference)}">${escapeHtml(batch.batch_reference)}</option>`;
                    });
                    
                    var modalHtml = `
                        <div class="modal fade" id="batchSelectModal" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Select Batch to Check</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <label class="form-label">Choose Batch Reference:</label>
                                        <select class="form-select" id="batchSelect">
                                            ${batchOptions}
                                        </select>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="button" class="btn btn-primary" onclick="checkSelectedBatch()">Check Duplicates</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    $('#batchSelectModal').remove();
                    $('body').append(modalHtml);
                    var modal = new bootstrap.Modal(document.getElementById('batchSelectModal'));
                    modal.show();
                } else {
                    alert('No batches found. Please import a list first.');
                }
            },
            error: function() {
                alert('Could not load batches');
            }
        });
    }
    
    // Function to check selected batch
    function checkSelectedBatch() {
        var batchReference = $('#batchSelect').val();
        if (!batchReference) {
            alert('Please select a batch');
            return;
        }
        
        $('#batchSelectModal').modal('hide');
        
        $('#duplicateCheckLoader').show();
        $('#duplicateCheckResults').html('');
        
        var matchName = localStorage.getItem('matchName') !== 'false';
        var matchBarangay = localStorage.getItem('matchBarangay') !== 'false';
        var matchBirthday = localStorage.getItem('matchBirthday') !== 'false';
        var fuzzyMatch = localStorage.getItem('fuzzyMatch') === 'true';
        
        $.ajax({
            url: 'check_duplicates_api.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'scan_specific_batch',
                batch_reference: batchReference,
                match_name: matchName ? '1' : '0',
                match_barangay: matchBarangay ? '1' : '0',
                match_birthday: matchBirthday ? '1' : '0',
                fuzzy_match: fuzzyMatch ? '1' : '0'
            },
            success: function(response) {
                $('#duplicateCheckLoader').hide();
                if (response.success) {
                    currentDuplicatesData = response;
                    displayDuplicateResults(response);
                    if (response.duplicates && response.duplicates.length > 0) {
                        $('#deleteAllBtn').show();
                    }
                } else {
                    $('#duplicateCheckResults').html(`
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle-fill"></i> 
                            ${response.message || 'Error scanning duplicates'}
                        </div>
                    `);
                }
            },
            error: function() {
                $('#duplicateCheckLoader').hide();
                $('#duplicateCheckResults').html(`
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill"></i> 
                        An error occurred while scanning for duplicates.
                    </div>
                `);
            }
        });
    }
    
    // Export function for CSV
    function exportDuplicateResults() {
        var data = localStorage.getItem('lastDuplicateResults');
        if (data) {
            var results = JSON.parse(data);
            var csv = "Batch Reference,Full Name,Barangay,Birthday,Match Type\n";
            
            if (results.duplicates && results.duplicates.length > 0) {
                results.duplicates.forEach(function(dup) {
                    csv += `"${dup.batch_reference || ''}","${dup.full_name || dup.name}","${dup.barangay || ''}","${dup.birthday || ''}","${dup.match_type || 'exact'}"\n`;
                });
            } else {
                csv += `"","No duplicates found","","",""\n`;
            }
            
            var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            var link = document.createElement('a');
            var url = URL.createObjectURL(blob);
            link.href = url;
            link.setAttribute('download', 'duplicate_results_' + new Date().toISOString().slice(0,19) + '.csv');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        } else {
            alert('No results to export');
        }
    }
    
    // Helper function to escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    // Function to view duplicate details
    function viewDuplicateDetails(id) {
        $.ajax({
            url: 'check_duplicates_api.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'get_duplicate_details',
                id: id
            },
            success: function(response) {
                if (response.success && response.data) {
                    var details = response.data;
                    var modalContent = `
                        <div class="row">
                            <div class="col-md-12">
                                <h6>Beneficiary Information</h6>
                                <table class="table table-bordered">
                                    <tr>
                                        <th style="width: 150px;">Full Name:</th>
                                        <td>${escapeHtml(details.full_name)}</td>
                                    </tr>
                                    <tr>
                                        <th>Barangay:</th>
                                        <td>${escapeHtml(details.barangay)}</td>
                                    </tr>
                                    <tr>
                                        <th>Birthday:</th>
                                        <td>${escapeHtml(details.birthday)}</td>
                                    </tr>
                                    <tr>
                                        <th>Batch Reference:</th>
                                        <td><code>${escapeHtml(details.batch_reference)}</code></td>
                                    </tr>
                                    <tr>
                                        <th>Status:</th>
                                        <td><span class="badge bg-danger">Duplicate Record</span></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    `;
                    $('#detailsContent').html(modalContent);
                    new bootstrap.Modal(document.getElementById('detailsModal')).show();
                } else {
                    alert('Could not load details: ' + (response.message || 'Unknown error'));
                }
            },
            error: function() {
                alert('Could not load beneficiary details');
            }
        });
    }
    
    // Delete check history
    function deleteCheckHistory(id) {
        Swal.fire({
            title: 'Delete History Record?',
            text: 'This action cannot be undone!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Yes, delete!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'check_duplicates_api.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'delete_history',
                        id: id
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Deleted!', response.message, 'success');
                            location.reload();
                        } else {
                            Swal.fire('Error!', response.message, 'error');
                        }
                    }
                });
            }
        });
    }
    
    // Override the existing switchPage function
    var originalSwitchPage = window.switchPage;
    window.switchPage = function(pageId) {
        if (typeof originalSwitchPage === 'function') {
            originalSwitchPage(pageId);
        } else {
            $('.page').removeClass('active');
            $('#' + pageId + '-page').addClass('active');
            $('#pageTitle').text($('.nav-link[data-page="' + pageId + '"] span').text() || pageId);
            $('.nav-link').removeClass('active');
            $('.nav-link[data-page="' + pageId + '"]').addClass('active');
        }
        
        if (pageId !== 'check-duplicates') {
            $('#duplicateCheckLoader').hide();
            $('#duplicateCheckResults').html('');
            $('#deleteAllBtn').hide();
        }
    };
    </script>
</body>
</html>