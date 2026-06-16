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
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-page="received-beneficiaries">
                            <i class="bi bi-people-fill"></i>
                            <span>Received Beneficiaries</span>
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
                            <!-- User Menu -->
                            <div class="dropdown">
                                <button class="btn btn-link user-menu-btn" data-bs-toggle="dropdown">
                                    <div class="avatar-sm">
                                        <i class="bi bi-person-circle"></i>
                                    </div>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <div class="dropdown-header">Logged in as: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></div>
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

                <!-- Received Beneficiaries Page -->
                <div class="page" id="received-beneficiaries-page">
                    <div class="row g-4">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="bi bi-people-fill"></i> Received Beneficiaries</h5>
                                    <div>
                                        <button class="btn btn-primary btn-sm me-2" onclick="checkDuplicatesFromReceived()">
                                            <i class="bi bi-search"></i> Check Duplicates
                                        </button>
                                        <button class="btn btn-success btn-sm me-2" onclick="exportReceivedBeneficiaries()">
                                            <i class="bi bi-download"></i> Export CSV
                                        </button>
                                        <button class="btn btn-secondary btn-sm" onclick="refreshReceivedList()">
                                            <i class="bi bi-arrow-repeat"></i> Refresh
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div id="receivedLoader" class="text-center py-5" style="display: none;">
                                        <div class="spinner-border text-primary mb-3" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <h5>Loading beneficiaries...</h5>
                                    </div>
                                    <div class="mb-3">
                                        <div class="input-group">
                                            <span class="input-group-text bg-light">
                                                <i class="bi bi-search"></i>
                                            </span>
                                            <input type="text" class="form-control" id="beneficiarySearch" placeholder="Search by name, barangay, or batch reference...">
                                            <button class="btn btn-outline-secondary" type="button" id="clearSearch" onclick="clearBeneficiarySearch()">
                                                <i class="bi bi-x-circle"></i> Clear
                                            </button>
                                        </div>
                                        <small class="text-muted d-block mt-1" id="searchResultsInfo"></small>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-hover" id="receivedTable">
                                            <thead class="table-dark">
                                                <tr>
                                                    <th>#</th>
                                                    <th class="sortable" data-sort="full_name">Full Name <i class="bi bi-arrow-down-up sort-icon"></i></th>
                                                    <th class="sortable" data-sort="barangay">Barangay <i class="bi bi-arrow-down-up sort-icon"></i></th>
                                                    <th class="sortable" data-sort="birthday">Birthday <i class="bi bi-arrow-down-up sort-icon"></i></th>
                                                    <th class="sortable" data-sort="batch_reference">Batch Reference <i class="bi bi-arrow-down-up sort-icon"></i></th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="receivedBody">
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted py-5">
                                                        <i class="bi bi-inbox display-1 d-block mb-3"></i>
                                                        <h5>No beneficiaries found</h5>
                                                        <p>Import a list first to see beneficiaries here</p>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="mt-3 d-flex justify-content-between align-items-center">
                                        <div id="receivedCount" class="text-muted">Total: 0</div>
                                        <nav>
                                            <ul class="pagination pagination-sm mb-0" id="receivedPagination">
                                            </ul>
                                        </nav>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Check Duplicates Results Page (hidden initially, shown when checking duplicates) -->
                <div class="page" id="check-duplicates-page">
                    <div class="row g-4">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="bi bi-search"></i> Duplicate Check Results</h5>
                                    <div>
                                        <button class="btn btn-secondary btn-sm me-2" onclick="switchPage('received-beneficiaries')">
                                            <i class="bi bi-arrow-left"></i> Back to List
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
                                        <p class="text-muted">Please wait while we analyze the beneficiaries list</p>
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
    let currentPage = 1;
    let pageSize = 20;
    let allReceivedBeneficiaries = [];

    // Function to switch pages
    function switchPage(pageId) {
        $('.page').removeClass('active');
        $('#' + pageId + '-page').addClass('active');
        $('#pageTitle').text($('.nav-link[data-page="' + pageId + '"] span').text() || pageId);
        $('.nav-link').removeClass('active');
        $('.nav-link[data-page="' + pageId + '"]').addClass('active');
        
        if (pageId === 'received-beneficiaries') {
            loadReceivedBeneficiaries();
        }
    }

    // Load received beneficiaries
    function loadReceivedBeneficiaries() {
        $('#receivedLoader').show();
        $('#receivedBody').html('<tr><td colspan="6" class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>');
        
        $.ajax({
            url: 'check_duplicates_api.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'get_received_beneficiaries'
            },
            success: function(response) {
                $('#receivedLoader').hide();
                if (response.success && response.data) {
                    allReceivedBeneficiaries = response.data;
                    renderReceivedTable(response.data);
                } else {
                    $('#receivedBody').html(`
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="bi bi-inbox display-1 d-block mb-3"></i>
                                <h5>No beneficiaries found</h5>
                                <p>Import a list first to see beneficiaries here</p>
                            </td>
                        </tr>
                    `);
                    $('#receivedCount').text('Total: 0');
                }
            },
            error: function() {
                $('#receivedLoader').hide();
                $('#receivedBody').html(`
                    <tr>
                        <td colspan="6" class="text-center text-danger py-5">
                            <i class="bi bi-exclamation-triangle-fill display-1 d-block mb-3"></i>
                            <h5>Error loading beneficiaries</h5>
                            <p>Please try again later</p>
                        </td>
                    </tr>
                `);
            }
        });
    }

    // Render received beneficiaries table - Display all beneficiaries
    function renderReceivedTable(data) {
        if (!data || data.length === 0) {
            $('#receivedBody').html(`
                <tr>
                    <td colspan="6" class="text-center text-muted py-5">
                        <i class="bi bi-inbox display-1 d-block mb-3"></i>
                        <h5>No beneficiaries found</h5>
                        <p>Import a list first to see beneficiaries here</p>
                    </td>
                </tr>
            `);
            $('#receivedCount').text('Total: 0');
            $('#receivedPagination').html('');
            return;
        }

        $('#receivedCount').text('Total: ' + data.length);

        // Display all beneficiaries without pagination
        let html = '';
        data.forEach(function(item, index) {
            html += `
                <tr>
                    <td>${index + 1}</td>
                    <td><strong>${escapeHtml(item.full_name || item.name || 'N/A')}</strong></td>
                    <td>${escapeHtml(item.barangay || 'N/A')}</td>
                    <td>${escapeHtml(item.birthday || 'N/A')}</td>
                    <td><span class="badge bg-secondary">${escapeHtml(item.batch_reference || 'N/A')}</span></td>
                    <td>
                        <button class="btn btn-sm btn-info" onclick="viewBeneficiaryDetails(${item.id})">
                            <i class="bi bi-eye"></i>
                        </button>
                    </td>
                </tr>
            `;
        });

        $('#receivedBody').html(html);

        // Hide pagination controls - all records are displayed on one page
        $('#receivedPagination').html('');
    }

    // Change page
    function changePage(page) {
        const totalPages = Math.ceil(allReceivedBeneficiaries.length / pageSize);
        if (page < 1 || page > totalPages) return;
        currentPage = page;
        renderReceivedTable(allReceivedBeneficiaries);
    }

    // Refresh received list
    function refreshReceivedList() {
        currentPage = 1;
        loadReceivedBeneficiaries();
    }

    // View beneficiary details
    function viewBeneficiaryDetails(id) {
        $.ajax({
            url: 'check_duplicates_api.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'get_beneficiary_details',
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
                                        <td>${escapeHtml(details.full_name || details.name)}</td>
                                    </tr>
                                    <tr>
                                        <th>Barangay:</th>
                                        <td>${escapeHtml(details.barangay || 'N/A')}</td>
                                    </tr>
                                    <tr>
                                        <th>Birthday:</th>
                                        <td>${escapeHtml(details.birthday || 'N/A')}</td>
                                    </tr>
                                    <tr>
                                        <th>Batch Reference:</th>
                                        <td><code>${escapeHtml(details.batch_reference || 'N/A')}</code></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    `;
                    $('#detailsContent').html(modalContent);
                    new bootstrap.Modal(document.getElementById('detailsModal')).show();
                } else {
                    Swal.fire('Error', 'Could not load beneficiary details', 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Could not load beneficiary details', 'error');
            }
        });
    }

    // Export received beneficiaries to CSV
    function exportReceivedBeneficiaries() {
        if (!allReceivedBeneficiaries || allReceivedBeneficiaries.length === 0) {
            Swal.fire('Info', 'No beneficiaries to export', 'info');
            return;
        }

        var csv = "Batch Reference,Full Name,Barangay,Birthday\n";
        allReceivedBeneficiaries.forEach(function(record) {
            csv += `"${escapeHtml(record.batch_reference || '')}","${escapeHtml(record.full_name || record.name || '')}","${escapeHtml(record.barangay || '')}","${escapeHtml(record.birthday || '')}"\n`;
        });

        downloadCSV(csv, 'received_beneficiaries_' + new Date().toISOString().slice(0,19) + '.csv');
        Swal.fire('Success', `Exported ${allReceivedBeneficiaries.length} beneficiaries`, 'success');
    }

    // Search beneficiaries
    function filterBeneficiaries(searchTerm) {
        const searchLower = searchTerm.toLowerCase().trim();
        
        if (!searchLower) {
            // If search is empty, display all beneficiaries
            renderReceivedTable(allReceivedBeneficiaries);
            $('#searchResultsInfo').text('');
            return;
        }

        // Filter beneficiaries based on search term
        const filtered = allReceivedBeneficiaries.filter(function(item) {
            const fullName = (item.full_name || item.name || '').toLowerCase();
            const barangay = (item.barangay || '').toLowerCase();
            const batchRef = (item.batch_reference || '').toLowerCase();
            const birthday = (item.birthday || '').toLowerCase();
            
            return fullName.includes(searchLower) || 
                   barangay.includes(searchLower) || 
                   batchRef.includes(searchLower) ||
                   birthday.includes(searchLower);
        });

        // Display filtered results
        renderReceivedTable(filtered);
        $('#searchResultsInfo').text(`Found ${filtered.length} of ${allReceivedBeneficiaries.length} beneficiaries`);
    }

    // Clear beneficiary search
    function clearBeneficiarySearch() {
        $('#beneficiarySearch').val('');
        renderReceivedTable(allReceivedBeneficiaries);
        $('#searchResultsInfo').text('');
    }

    // Function to check duplicates from received beneficiaries
    function checkDuplicatesFromReceived() {
        // Switch to the check duplicates page
        switchPage('check-duplicates');
        
        // Show loader and fetch duplicates
        $('#duplicateCheckLoader').show();
        $('#duplicateCheckResults').html('');
        
        $.ajax({
            url: 'check_duplicates_api.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'scan_duplicates',
                fuzzy_match: true
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

    // Function to display duplicate results
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
        
        // Table view of duplicates
        if (data.duplicates && data.duplicates.length > 0) {
            html += `
                <div class="row mb-3">
                    <div class="col-md-8">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input id="duplicateSearchInput" type="text" class="form-control" placeholder="Search duplicates by name, barangay, birthday or batch reference">
                            <button class="btn btn-primary" type="button" onclick="filterDuplicateResults()">Search</button>
                            <button class="btn btn-outline-secondary" type="button" onclick="resetDuplicateSearch()">Reset</button>
                        </div>
                    </div>
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
            
            var sortedDuplicates = [...data.duplicates];
            sortedDuplicates.sort(function(a, b) {
                return a.full_name.localeCompare(b.full_name);
            });
            
            sortedDuplicates.forEach(function(dup, index) {
                var rowClass = dup.match_details === 'Original record (has high-confidence duplicates)' ? 'table-warning' : '';
                
                html += `
                    <tr id="duplicate-row-${dup.id}" class="${rowClass}">
                        <td><input type="checkbox" class="duplicate-checkbox" value="${dup.id}"></td>
                        <td>${index + 1}${dup.match_details === 'Original record (has high-confidence duplicates)' ? ' <span class="badge bg-warning">Original</span>' : ''}</td>
                        <td><strong>${escapeHtml(dup.full_name)}</strong></td>
                        <td>${escapeHtml(dup.barangay)}</td>
                        <td>${escapeHtml(dup.birthday)}</td>
                        <td><span class="badge bg-secondary">${escapeHtml(dup.batch_reference)}</span></td>
                        <td>
                            <button class="btn btn-sm btn-success mb-1" onclick="acceptAsClean(${dup.id})" title="Accept as clean record">
                                <i class="bi bi-check-circle"></i>
                            </button>
                            <button class="btn btn-sm btn-warning mb-1" onclick="viewDuplicateDetails(${dup.id})">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteDuplicate(${dup.id})">
                                <i class="bi bi-trash"></i>
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
        currentDuplicatesData = data;
        
        if (data.duplicates) {
            localStorage.setItem('lastDuplicateResults', JSON.stringify(data));
        }
        if (data.clean_records_data) {
            localStorage.setItem('cleanRecords', JSON.stringify(data.clean_records_data));
        }
    }

    // Filter duplicate results
    function filterDuplicateResults() {
        var query = $('#duplicateSearchInput').val().trim().toLowerCase();
        if (!query) {
            resetDuplicateSearch();
            return;
        }

        $('#duplicatesTable tbody tr').each(function() {
            var rowText = $(this).text().toLowerCase();
            $(this).toggle(rowText.indexOf(query) !== -1);
        });
    }

    function resetDuplicateSearch() {
        $('#duplicateSearchInput').val('');
        $('#duplicatesTable tbody tr').show();
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

    function selectAllDuplicates() {
        $('.duplicate-checkbox').prop('checked', true);
        $('#selectAllDuplicates').prop('checked', true);
    }

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
                            selectedIds.forEach(function(id) {
                                $(`#duplicate-row-${id}`).remove();
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
                        csv += `"${escapeHtml(record.batch_reference || '')}","${escapeHtml(record.full_name || record.name)}","${escapeHtml(record.barangay || '')}","${escapeHtml(record.birthday || '')}"\n`;
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

    // Load received beneficiaries when page loads
    $(document).ready(function() {
        // If the received beneficiaries page is the default, load it
        if ($('#received-beneficiaries-page').hasClass('active')) {
            loadReceivedBeneficiaries();
        }
        
        // Handle nav clicks for received beneficiaries
        $('.nav-link[data-page="received-beneficiaries"]').click(function(e) {
            e.preventDefault();
            switchPage('received-beneficiaries');
        });
    });

    // Override switchPage to handle received beneficiaries
    var originalSwitchPage = window.switchPage;
    window.switchPage = function(pageId) {
        $('.page').removeClass('active');
        $('#' + pageId + '-page').addClass('active');
        $('#pageTitle').text($('.nav-link[data-page="' + pageId + '"] span').text() || pageId);
        $('.nav-link').removeClass('active');
        $('.nav-link[data-page="' + pageId + '"]').addClass('active');
        
        if (pageId === 'received-beneficiaries') {
            loadReceivedBeneficiaries();
        }
        
        if (pageId !== 'check-duplicates') {
            $('#duplicateCheckLoader').hide();
            $('#duplicateCheckResults').html('');
            $('#deleteAllBtn').hide();
        }
    };

    // Search functionality for beneficiaries
    $(document).on('keyup', '#beneficiarySearch', function() {
        const searchTerm = $(this).val();
        filterBeneficiaries(searchTerm);
    });
    </script>
</body>
</html>