<?php
session_start();
require_once 'config.php';

// Get statistics
try {
    $pdo = getConnection();
    $stats = [
        'total_received' => $pdo->query("SELECT COUNT(*) FROM received_beneficiaries")->fetchColumn(),
        'total_checks' => $pdo->query("SELECT COUNT(*) FROM check_history")->fetchColumn(),
        'last_check' => $pdo->query("SELECT check_date FROM check_history ORDER BY check_date DESC LIMIT 1")->fetchColumn()
    ];
} catch (Exception $e) {
    $stats = ['total_received' => 0, 'total_checks' => 0, 'last_check' => 'Never'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Duplicate Checker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="dashboard-shell">
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark navbar-glass px-4 shadow-sm">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <i class="bi bi-people-check fs-3 me-2"></i>
                Duplicate Checker
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topNav" aria-controls="topNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="topNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item me-3">
                        <a class="nav-link text-white" href="#statsSection">Overview</a>
                    </li>
                    <li class="nav-item me-3">
                        <a class="nav-link text-white" href="#uploadSection">Upload</a>
                    </li>
                    <li class="nav-item me-3">
                        <a class="nav-link text-white" href="#resultsSection">Results</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="#historySection">History</a>
                    </li>
                </ul>
            </div>
        </nav>

        <div class="dashboard-grid container-xl px-4 py-4">
            <aside class="sidebar bg-white shadow-sm rounded-4 p-4">
                <div class="sidebar-brand d-flex align-items-center mb-4">
                    <div class="sidebar-icon bg-primary text-white rounded-3 p-3 me-3">
                        <i class="bi bi-speedometer2 fs-4"></i>
                    </div>
                    <div>
                        <h5 class="mb-0">Dashboard</h5>
                        <small class="text-muted">Live performance</small>
                    </div>
                </div>
                <nav class="nav flex-column sidebar-nav">
                    <a class="nav-link active rounded-3 px-3 py-2 mb-2" href="#statsSection"><i class="bi bi-bar-chart-line-fill me-2"></i> Overview</a>
                    <a class="nav-link rounded-3 px-3 py-2 mb-2" href="#uploadSection"><i class="bi bi-cloud-upload-fill me-2"></i> Upload Files</a>
                    <a class="nav-link rounded-3 px-3 py-2 mb-2" href="#resultsSection"><i class="bi bi-table me-2"></i> Results</a>
                    <a class="nav-link rounded-3 px-3 py-2" href="#historySection"><i class="bi bi-clock-history me-2"></i> History</a>
                    <a class="nav-link rounded-3 px-3 py-2" href="#supportSection"><i class="bi bi-life-preserver me-2"></i> Support</a>
                </nav>
                <div class="sidebar-footer mt-5 p-3 rounded-4 bg-gradient-primary text-white shadow-sm">
                    <h6>Need help?</h6>
                    <p class="mb-0 small">Check the guide and export tools right from the panel.</p>
                </div>
            </aside>

            <main class="content-shell">
                <!-- Hero -->
                <section class="row mb-4" id="heroSection">
                    <div class="col-12">
                        <div class="card hero-card bg-white text-dark position-relative overflow-hidden">
                            <div class="card-body text-center py-5">
                                <h1 class="card-title display-6 fw-bold mb-3">
                                    <i class="bi bi-people-check me-2"></i> Beneficiary Duplicate Checker
                                </h1>
                                <p class="lead mb-0">Easily import beneficiary lists, run duplicate checks, and review clean results in one modern dashboard.</p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Statistics Cards -->
                <section class="row mb-4" id="statsSection">
                    <div class="col-md-4">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="bi bi-database-check stats-icon text-primary"></i>
                                <h3><?php echo number_format($stats['total_received']); ?></h3>
                                <p class="text-muted">Total Received Beneficiaries</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="bi bi-check2-circle stats-icon text-success"></i>
                                <h3><?php echo number_format($stats['total_checks']); ?></h3>
                                <p class="text-muted">Total Checks Performed</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="bi bi-clock-history stats-icon text-info"></i>
                                <h3><?php echo $stats['last_check'] ? date('M d, Y', strtotime($stats['last_check'])) : 'Never'; ?></h3>
                                <p class="text-muted">Last Check Date</p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Upload + Check Section -->
                <section class="row" id="uploadSection">
                    <div class="col-lg-5">
                        <div class="card section-card mb-4 mb-lg-0">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="bi bi-upload"></i> Step 1: Import Master List</h5>
                            </div>
                            <div class="card-body">
                                <form id="masterListForm" enctype="multipart/form-data">
                                    <div class="mb-4">
                                        <label for="masterFile" class="form-label">Upload Received Beneficiaries (Excel)</label>
                                        <input type="file" class="form-control" id="masterFile" name="masterFile" accept=".xlsx,.xls" required>
                                        <div class="form-text">File must contain columns: Name, Barangay, Birthday</div>
                                    </div>
                                    <div class="mb-4">
                                        <label for="batchName" class="form-label">Batch Name (optional)</label>
                                        <input type="text" class="form-control" id="batchName" name="batchName" placeholder="e.g., January 2024 Payout">
                                    </div>
                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="bi bi-cloud-upload"></i> Import Master List
                                    </button>
                                </form>
                                <div id="masterUploadStatus" class="mt-3"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <div class="card section-card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="bi bi-search"></i> Step 2: Check New List for Duplicates</h5>
                            </div>
                            <div class="card-body">
                                <form id="checkDuplicatesForm" enctype="multipart/form-data">
                                    <div class="mb-4">
                                        <label for="newFile" class="form-label">Upload New Beneficiary List (Excel)</label>
                                        <input type="file" class="form-control" id="newFile" name="newFile" accept=".xlsx,.xls" required>
                                        <div class="form-text">File must contain columns: Name, Barangay, Birthday</div>
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label">Matching Criteria</label>
                                        <div class="row gy-3">
                                            <div class="col-sm-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="matchName" checked>
                                                    <label class="form-check-label" for="matchName">Match Name</label>
                                                </div>
                                            </div>
                                            <div class="col-sm-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="matchBarangay" checked>
                                                    <label class="form-check-label" for="matchBarangay">Match Barangay</label>
                                                </div>
                                            </div>
                                            <div class="col-sm-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="matchBirthday" checked>
                                                    <label class="form-check-label" for="matchBirthday">Match Birthday</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-4 form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="fuzzyMatch">
                                        <label class="form-check-label" for="fuzzyMatch">Enable Fuzzy Name Matching (catches similar names)</label>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-search"></i> Check for Duplicates
                                    </button>
                                </form>
                                <div id="checkStatus" class="mt-3"></div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Results Section -->
                <section class="row mt-4" id="resultsSection">
                    <div class="col-12">
                        <div class="card section-card">
                            <div class="card-header bg-info text-white">
                                <div class="d-flex justify-content-between align-items-center flex-column flex-md-row gap-3">
                                    <h5 class="mb-0"><i class="bi bi-table"></i> Duplicate Check Results</h5>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-success btn-sm" onclick="exportDuplicates()">
                                            <i class="bi bi-download"></i> Export Duplicates
                                        </button>
                                        <button class="btn btn-warning btn-sm" onclick="exportCleanList()">
                                            <i class="bi bi-download"></i> Export Clean List
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="resultSummary" class="mb-3"></div>
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
                                        <tbody id="resultsBody"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- History Section -->
                <section class="row mt-4" id="historySection">
                    <div class="col-12">
                        <div class="card section-card">
                            <div class="card-header bg-secondary text-white">
                                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Check History</h5>
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
                                                    "SELECT * FROM check_history ORDER BY check_date DESC LIMIT 10"
                                                )->fetchAll();

                                                foreach ($history as $record) {
                                                    echo "<tr>";
                                                    echo "<td>" . date('M d, Y H:i', strtotime($record['check_date'])) . "</td>";
                                                    echo "<td>" . htmlspecialchars($record['batch_name'] ?? 'N/A') . "</td>";
                                                    echo "<td>" . $record['total_checked'] . "</td>";
                                                    echo "<td>" . $record['duplicates_found'] . "</td>";
                                                    echo "<td>" . $record['clean_records'] . "</td>";
                                                    echo "<td><button class='btn btn-info btn-sm' onclick='viewCheckDetails(" . $record['id'] . ")'>View</button></td>";
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
                </section>

                <!-- Support Section -->
                <section class="row mt-4" id="supportSection">
                    <div class="col-12">
                        <div class="card section-card">
                            <div class="card-header bg-light">
                                <h5 class="mb-0"><i class="bi bi-life-preserver"></i> Support &amp; Resources</h5>
                            </div>
                            <div class="card-body">
                                <p class="mb-4">Need help with imports, exports, or duplicates? Use the buttons below to access the most common actions.</p>
                                <div class="d-flex flex-column flex-sm-row gap-2">
                                    <a href="#uploadSection" class="btn btn-outline-primary flex-fill"><i class="bi bi-upload"></i> Upload Files</a>
                                    <a href="#resultsSection" class="btn btn-outline-success flex-fill"><i class="bi bi-table"></i> View Results</a>
                                    <a href="#historySection" class="btn btn-outline-secondary flex-fill"><i class="bi bi-clock-history"></i> View History</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <!-- View Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Check Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailsContent">
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>