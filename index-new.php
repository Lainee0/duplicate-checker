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
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DupliChecker</title>
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .sidebar-bg {
            background-color: #2D3350;
        }
        .sidebar-link-active {
            background-color: rgba(255, 255, 255, 0.1);
            border-right: 4px solid #ffffff;
        }
        .page {
            display: none;
        }
        .page.active {
            display: block;
        }
    </style>
</head>
<body class="h-full overflow-hidden">
    <div class="flex h-full">
        <!-- BEGIN: Sidebar -->
        <aside class="sidebar-bg w-64 flex-shrink-0 flex flex-col text-white" id="sidebar">
            <!-- Logo Section -->
            <div class="p-6 border-b border-gray-700">
                <h1 class="text-lg font-bold tracking-wider">DUPLI_CHECKER</h1>
            </div>
            
            <!-- Navigation Links -->
            <nav class="flex-1 mt-6 px-2 space-y-1">
                <a class="sidebar-link-active flex items-center px-4 py-3 text-sm font-medium rounded-md transition-colors cursor-pointer" data-page="dashboard" onclick="switchPage('dashboard')">
                    <svg class="mr-3 h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M3 13h2v8H3zm4-8h2v16H7zm4-2h2v18h-2zm4-2h2v20h-2zm4 4h2v16h-2zm4 8h2v8h-2z"/>
                    </svg>
                    <span>Dashboard</span>
                </a>
                <a class="flex items-center px-4 py-3 text-sm font-medium text-gray-300 hover:bg-gray-700 hover:text-white rounded-md transition-colors cursor-pointer" id="openImportModal" onclick="openImportModal()">
                    <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    <span>Import List</span>
                </a>
                <a class="flex items-center px-4 py-3 text-sm font-medium text-gray-300 hover:bg-gray-700 hover:text-white rounded-md transition-colors cursor-pointer" data-page="received-beneficiaries" onclick="switchPage('received-beneficiaries')">
                    <svg class="mr-3 h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <span>Received Beneficiaries</span>
                </a>
                <a class="flex items-center px-4 py-3 text-sm font-medium text-gray-300 hover:bg-gray-700 hover:text-white rounded-md transition-colors cursor-pointer" data-page="check-duplicates" onclick="switchPage('check-duplicates'); checkDuplicatesFromReceived();">
                    <svg class="mr-3 h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2L1 21h22L12 2zm1 14h-2v-2h2v2zm0-4h-2V8h2v4z"/>
                    </svg>
                    <span>Check Duplicates</span>
                </a>
                <a class="flex items-center px-4 py-3 text-sm font-medium text-gray-300 hover:bg-gray-700 hover:text-white rounded-md transition-colors cursor-pointer" data-page="results" onclick="switchPage('results')">
                    <svg class="mr-3 h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
                    </svg>
                    <span>Results</span>
                </a>
                <a class="flex items-center px-4 py-3 text-sm font-medium text-gray-300 hover:bg-gray-700 hover:text-white rounded-md transition-colors cursor-pointer" data-page="history" onclick="switchPage('history')">
                    <svg class="mr-3 h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm3.5-9c0 .83-.67 1.5-1.5 1.5s-1.5-.67-1.5-1.5.67-1.5 1.5-1.5 1.5.67 1.5 1.5zm-7 0c0 .83-.67 1.5-1.5 1.5S5 11.83 5 11s.67-1.5 1.5-1.5 1.5.67 1.5 1.5z"/>
                    </svg>
                    <span>History</span>
                </a>
                <a class="flex items-center px-4 py-3 text-sm font-medium text-gray-300 hover:bg-gray-700 hover:text-white rounded-md transition-colors cursor-pointer" data-page="reports" onclick="switchPage('reports')">
                    <svg class="mr-3 h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2V17zm4 0h-2V7h2V17zm4 0h-2v-4h2V17z"/>
                    </svg>
                    <span>Reports</span>
                </a>
                <a class="flex items-center px-4 py-3 text-sm font-medium text-gray-300 hover:bg-gray-700 hover:text-white rounded-md transition-colors cursor-pointer" data-page="settings" onclick="switchPage('settings')">
                    <svg class="mr-3 h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l1.72-1.34c.15-.12.19-.34.1-.51l-1.63-2.83c-.12-.22-.37-.29-.59-.22l-2.03.81c-.42-.32-.93-.6-1.49-.82l-.31-2.15c-.05-.24-.24-.41-.49-.41h-3.26c-.25 0-.44.17-.49.41l-.31 2.15c-.56.22-1.07.51-1.49.82l-2.03-.81c-.22-.09-.47 0-.59.22L2.74 8.87c-.1.16-.06.39.1.51l1.72 1.34c-.05.3-.07.62-.07.94s.02.64.07.94l-1.72 1.34c-.15.12-.19.34-.1.51l1.63 2.83c.12.22.37.29.59.22l2.03-.81c.42.32.93.6 1.49.82l.31 2.15c.05.24.24.41.49.41h3.26c.25 0 .44-.17.49-.41l.31-2.15c.56-.22 1.07-.51 1.49-.82l2.03.81c.22.09.47 0 .59-.22l1.63-2.83c.1-.16.06-.39-.1-.51l-1.72-1.34zM12 15.5c-1.93 0-3.5-1.57-3.5-3.5s1.57-3.5 3.5-3.5 3.5 1.57 3.5 3.5-1.57 3.5-3.5 3.5z"/>
                    </svg>
                    <span>Settings</span>
                </a>
            </nav>
            
            <!-- User Profile Footer -->
            <div class="p-4 border-t border-gray-700 flex items-center">
                <div class="flex-shrink-0">
                    <div class="h-10 w-10 rounded-full bg-gray-500 flex items-center justify-center text-white">
                        <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                        </svg>
                    </div>
                </div>
                <div class="ml-3 overflow-hidden">
                    <p class="text-sm font-medium text-white truncate"><?php echo htmlspecialchars(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')); ?></p>
                    <p class="text-xs text-gray-400 truncate"><?php echo ucfirst(htmlspecialchars($_SESSION['role'] ?? 'User')); ?></p>
                </div>
            </div>
        </aside>
        <!-- END: Sidebar -->
        
        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- BEGIN: Header -->
            <header class="bg-white border-b border-gray-200 h-16 flex items-center justify-between px-6 z-10">
                <div class="flex items-center">
                    <button class="text-gray-500 hover:text-gray-700 lg:hidden mr-4" id="sidebarToggle">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                    <h2 class="text-lg font-semibold text-gray-800" id="pageTitle">Dashboard</h2>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="logout.php" class="text-gray-500 hover:text-gray-700 flex items-center">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </a>
                </div>
            </header>
            <!-- END: Header -->
            
            <!-- BEGIN: Main Content -->
            <main class="flex-1 overflow-y-auto p-8 bg-[#F9FAFC]">
                <!-- Dashboard Page -->
                <div class="page active" id="dashboard-page">
                    <!-- Stats Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <!-- Total Beneficiaries Card -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex items-center">
                            <div class="flex-shrink-0 bg-orange-100 p-4 rounded-xl text-orange-500 mr-5">
                                <svg class="h-8 w-8" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-3xl font-bold text-gray-800"><?php echo number_format($stats['total_received']); ?></p>
                                <p class="text-sm font-medium text-gray-500">Total Beneficiaries</p>
                            </div>
                        </div>
                        
                        <!-- Total Checks Card -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex items-center">
                            <div class="flex-shrink-0 bg-blue-100 p-4 rounded-xl text-blue-500 mr-5">
                                <svg class="h-8 w-8" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-3xl font-bold text-gray-800"><?php echo $stats['total_checks']; ?></p>
                                <p class="text-sm font-medium text-gray-500">Total Checks</p>
                            </div>
                        </div>
                        
                        <!-- Duplicates Found Card -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex items-center">
                            <div class="flex-shrink-0 bg-red-100 p-4 rounded-xl text-red-500 mr-5">
                                <svg class="h-8 w-8" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 2L1 21h22L12 2zm1 14h-2v-2h2v2zm0-4h-2V8h2v4z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-3xl font-bold text-gray-800"><?php echo number_format($stats['duplicates_found']); ?></p>
                                <p class="text-sm font-medium text-gray-500">Duplicates Found</p>
                            </div>
                        </div>
                        
                        <!-- Last Check Card -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex items-center">
                            <div class="flex-shrink-0 bg-green-100 p-4 rounded-xl text-green-500 mr-5">
                                <svg class="h-8 w-8" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm3.5-9c0 .83-.67 1.5-1.5 1.5s-1.5-.67-1.5-1.5.67-1.5 1.5-1.5 1.5.67 1.5 1.5z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-lg font-bold text-gray-800"><?php echo $stats['last_check'] ? date('M d, Y', strtotime($stats['last_check'])) : 'Never'; ?></p>
                                <p class="text-sm font-medium text-gray-500">Last Check</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
                        <h3 class="text-lg font-semibold text-gray-800 mb-6">Quick Actions</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <button onclick="openImportModal()" class="bg-blue-500 hover:bg-blue-600 text-white font-medium py-3 px-6 rounded-lg transition-colors flex items-center justify-center">
                                <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                </svg>
                                Import New List
                            </button>
                            <button onclick="switchPage('received-beneficiaries')" class="bg-purple-500 hover:bg-purple-600 text-white font-medium py-3 px-6 rounded-lg transition-colors flex items-center justify-center">
                                <svg class="h-5 w-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                                View Beneficiaries
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Received Beneficiaries Page -->
                <div class="page" id="received-beneficiaries-page">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800">Received Beneficiaries</h3>
                                <p class="text-sm text-gray-500 mt-1" id="receivedCount">Total: 0</p>
                            </div>
                            <div class="flex flex-wrap gap-2 mt-4 md:mt-0">
                                <input type="text" id="beneficiarySearch" placeholder="Search beneficiaries..." class="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <button onclick="clearBeneficiarySearch()" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-lg text-sm font-medium transition-colors">Clear</button>
                                <button onclick="refreshReceivedList()" class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition-colors">Refresh</button>
                                <button onclick="exportReceivedBeneficiaries()" class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition-colors">Export</button>
                            </div>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 border-b border-gray-200">
                                    <tr>
                                        <th class="px-6 py-3 text-left font-medium text-gray-700">#</th>
                                        <th class="px-6 py-3 text-left font-medium text-gray-700">Full Name</th>
                                        <th class="px-6 py-3 text-left font-medium text-gray-700">Barangay</th>
                                        <th class="px-6 py-3 text-left font-medium text-gray-700">Birthday</th>
                                        <th class="px-6 py-3 text-left font-medium text-gray-700">Batch Reference</th>
                                        <th class="px-6 py-3 text-center font-medium text-gray-700">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="receivedBody">
                                    <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-4" id="receivedPagination"></div>
                    </div>
                </div>
                
                <!-- Check Duplicates Results Page -->
                <div class="page" id="check-duplicates-page">
                    <div class="flex gap-4 mb-4">
                        <input type="text" id="duplicateSearchInput" placeholder="Search duplicates..." class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <button onclick="filterDuplicateResults()" class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition-colors">Search</button>
                        <button onclick="resetDuplicateSearch()" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-lg text-sm font-medium transition-colors">Reset</button>
                    </div>
                    <div id="duplicateCheckLoader" class="text-center py-8">
                        <div class="inline-block">
                            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
                            <p class="text-gray-600 mt-4">Scanning for duplicates...</p>
                        </div>
                    </div>
                    <div id="duplicateCheckResults"></div>
                </div>
                
                <!-- Results Page -->
                <div class="page" id="results-page">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-6">Duplicate Results</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 border-b border-gray-200">
                                    <tr>
                                        <th class="px-6 py-3 text-left font-medium text-gray-700">Name</th>
                                        <th class="px-6 py-3 text-left font-medium text-gray-700">Match Level</th>
                                        <th class="px-6 py-3 text-left font-medium text-gray-700">Details</th>
                                        <th class="px-6 py-3 text-center font-medium text-gray-700">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="resultsBody">
                                    <tr><td colspan="4" class="px-6 py-8 text-center text-gray-500">No results available</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- History Page -->
                <div class="page" id="history-page">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-6">Check History</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 border-b border-gray-200">
                                    <tr>
                                        <th class="px-6 py-3 text-left font-medium text-gray-700">Date</th>
                                        <th class="px-6 py-3 text-left font-medium text-gray-700">Batch Reference</th>
                                        <th class="px-6 py-3 text-left font-medium text-gray-700">Records Checked</th>
                                        <th class="px-6 py-3 text-left font-medium text-gray-700">Duplicates Found</th>
                                        <th class="px-6 py-3 text-center font-medium text-gray-700">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="historyBody">
                                    <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">Loading history...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Reports Page -->
                <div class="page" id="reports-page">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Duplicates by Batch</h3>
                            <canvas id="batchChart"></canvas>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Duplicate Rate</h3>
                            <canvas id="rateChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Settings Page -->
                <div class="page" id="settings-page">
                    <div class="max-w-2xl">
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-6">Settings</h3>
                            <div class="space-y-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Database Settings</label>
                                    <p class="text-sm text-gray-600">Manage your database configuration and connections.</p>
                                </div>
                                <div class="border-t border-gray-200 pt-6">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Export Settings</label>
                                    <p class="text-sm text-gray-600">Configure export format and options.</p>
                                </div>
                                <div class="border-t border-gray-200 pt-6">
                                    <a href="logout.php" class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg text-sm font-medium transition-colors inline-block">
                                        Logout
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
            <!-- END: Main Content -->
        </div>
    </div>
    
    <!-- Import Modal -->
    <div id="importModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-lg max-w-md w-full mx-4">
            <div class="bg-gray-800 text-white px-6 py-4 rounded-t-xl flex items-center justify-between">
                <h5 class="text-lg font-semibold">Import Beneficiaries</h5>
                <button onclick="closeImportModal()" class="text-gray-400 hover:text-white">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <form id="importForm" enctype="multipart/form-data" class="p-6">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select CSV File</label>
                    <input type="file" name="file" id="importFile" accept=".csv" class="block w-full text-sm text-gray-500 border border-gray-300 rounded-lg cursor-pointer focus:outline-none" required>
                    <p class="mt-1 text-xs text-gray-500">CSV file with columns: full_name, barangay, birthday, batch_reference</p>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Batch Reference</label>
                    <input type="text" name="batch_reference" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div class="flex gap-3">
                    <button type="button" onclick="closeImportModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="flex-1 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition-colors">
                        Import
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Details Modal -->
    <div id="detailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-lg max-w-2xl w-full mx-4 max-h-96 overflow-y-auto">
            <div class="bg-gray-800 text-white px-6 py-4 flex items-center justify-between sticky top-0">
                <h5 class="text-lg font-semibold">Beneficiary Details</h5>
                <button onclick="closeDetailsModal()" class="text-gray-400 hover:text-white">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="p-6" id="detailsContent"></div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteConfirmModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-lg max-w-md w-full mx-4">
            <div class="bg-red-600 text-white px-6 py-4 rounded-t-xl flex items-center justify-between">
                <h5 class="text-lg font-semibold">Confirm Delete</h5>
                <button onclick="closeDeleteModal()" class="text-gray-400 hover:text-white">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="p-6">
                <p>Are you sure you want to delete this record?</p>
                <p class="text-sm text-red-600 mt-2">This action cannot be undone!</p>
                <div class="flex gap-3 mt-6">
                    <button type="button" onclick="closeDeleteModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="button" onclick="confirmDelete()" class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium transition-colors">
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/script.js"></script>
    
    <script>
    // Modal control functions
    function openImportModal() {
        document.getElementById('importModal').classList.remove('hidden');
    }
    
    function closeImportModal() {
        document.getElementById('importModal').classList.add('hidden');
    }
    
    function closeDetailsModal() {
        document.getElementById('detailsModal').classList.add('hidden');
    }
    
    function closeDeleteModal() {
        document.getElementById('deleteConfirmModal').classList.add('hidden');
    }
    
    let currentDeleteId = null;
    let currentDuplicatesData = null;
    let currentPage = 1;
    let pageSize = 20;
    let allReceivedBeneficiaries = [];

    // Function to switch pages
    function switchPage(pageId) {
        document.querySelectorAll('.page').forEach(el => el.classList.remove('active'));
        document.getElementById(pageId + '-page').classList.add('active');
        document.getElementById('pageTitle').textContent = document.querySelector(`[data-page="${pageId}"] span`)?.textContent || pageId;
        
        document.querySelectorAll('[data-page]').forEach(el => {
            el.classList.remove('sidebar-link-active');
            if (el.getAttribute('data-page') === pageId) {
                el.classList.add('sidebar-link-active');
            }
        });
    }

    // Load received beneficiaries
    function loadReceivedBeneficiaries() {
        $.ajax({
            url: 'check_duplicates_api.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'get_received_beneficiaries'
            },
            success: function(response) {
                if (response.success && response.data) {
                    allReceivedBeneficiaries = response.data;
                    renderReceivedTable(response.data);
                } else {
                    document.getElementById('receivedBody').innerHTML = '<tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">No data found</td></tr>';
                    document.getElementById('receivedCount').textContent = 'Total: 0';
                }
            }
        });
    }

    // Render received beneficiaries table
    function renderReceivedTable(data) {
        if (!data || data.length === 0) {
            document.getElementById('receivedBody').innerHTML = '<tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">No beneficiaries found</td></tr>';
            document.getElementById('receivedCount').textContent = 'Total: 0';
            return;
        }

        document.getElementById('receivedCount').textContent = 'Total: ' + data.length;
        
        let html = '';
        data.forEach(function(item, index) {
            html += `
                <tr class="border-b border-gray-200 hover:bg-gray-50">
                    <td class="px-6 py-4">${index + 1}</td>
                    <td class="px-6 py-4"><strong>${escapeHtml(item.full_name || item.name || 'N/A')}</strong></td>
                    <td class="px-6 py-4">${escapeHtml(item.barangay || 'N/A')}</td>
                    <td class="px-6 py-4">${escapeHtml(item.birthday || 'N/A')}</td>
                    <td class="px-6 py-4"><span class="px-3 py-1 bg-gray-200 text-gray-800 text-xs rounded-full">${escapeHtml(item.batch_reference || 'N/A')}</span></td>
                    <td class="px-6 py-4 text-center">
                        <button onclick="viewBeneficiaryDetails(${item.id})" class="text-blue-500 hover:text-blue-700 text-sm font-medium">View</button>
                        <button onclick="deleteBeneficiary(${item.id})" class="text-red-500 hover:text-red-700 text-sm font-medium ml-2">Delete</button>
                    </td>
                </tr>
            `;
        });
        document.getElementById('receivedBody').innerHTML = html;
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
                    let details = response.data;
                    let modalContent = `
                        <dl class="grid grid-cols-2 gap-4">
                            <div><dt class="font-semibold text-gray-700">Name:</dt><dd class="text-gray-600">${escapeHtml(details.full_name || 'N/A')}</dd></div>
                            <div><dt class="font-semibold text-gray-700">Birthday:</dt><dd class="text-gray-600">${escapeHtml(details.birthday || 'N/A')}</dd></div>
                            <div><dt class="font-semibold text-gray-700">Barangay:</dt><dd class="text-gray-600">${escapeHtml(details.barangay || 'N/A')}</dd></div>
                            <div><dt class="font-semibold text-gray-700">Batch Reference:</dt><dd class="text-gray-600">${escapeHtml(details.batch_reference || 'N/A')}</dd></div>
                        </dl>
                    `;
                    document.getElementById('detailsContent').innerHTML = modalContent;
                    document.getElementById('detailsModal').classList.remove('hidden');
                }
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

    // Filter beneficiaries
    function filterBeneficiaries(searchTerm) {
        const searchLower = searchTerm.toLowerCase().trim();
        
        if (!searchLower) {
            renderReceivedTable(allReceivedBeneficiaries);
            return;
        }

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

        renderReceivedTable(filtered);
    }

    // Clear search
    function clearBeneficiarySearch() {
        document.getElementById('beneficiarySearch').value = '';
        renderReceivedTable(allReceivedBeneficiaries);
    }

    // Refresh list
    function refreshReceivedList() {
        currentPage = 1;
        loadReceivedBeneficiaries();
    }

    // Check duplicates
    function checkDuplicatesFromReceived() {
        switchPage('check-duplicates');
        document.getElementById('duplicateCheckLoader').style.display = 'block';
        document.getElementById('duplicateCheckResults').innerHTML = '';
        
        $.ajax({
            url: 'check_duplicates_api.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'scan_duplicates',
                fuzzy_match: true
            },
            success: function(response) {
                document.getElementById('duplicateCheckLoader').style.display = 'none';
                if (response.success) {
                    displayDuplicateResults(response);
                }
            }
        });
    }

    // Display duplicate results
    function displayDuplicateResults(data) {
        let html = '';
        
        html += `
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <p class="text-2xl font-bold text-blue-700">${data.duplicates ? data.duplicates.length : 0}</p>
                    <p class="text-sm text-blue-600">Duplicates Found</p>
                </div>
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <p class="text-2xl font-bold text-green-700">${data.clean_records_data ? data.clean_records_data.length : 0}</p>
                    <p class="text-sm text-green-600">Clean Records</p>
                </div>
                <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                    <p class="text-2xl font-bold text-purple-700">${data.total_records || 0}</p>
                    <p class="text-sm text-purple-600">Total Records</p>
                </div>
            </div>
        `;
        
        if (data.duplicates && data.duplicates.length > 0) {
            html += `
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <div class="flex justify-between items-center mb-4 flex-wrap gap-2">
                        <h3 class="text-lg font-semibold text-gray-800">Duplicate Records</h3>
                        <div class="flex gap-2 flex-wrap">
                            <button onclick="selectAllDuplicates()" class="px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white text-sm rounded">Select All</button>
                            <button onclick="deselectAllDuplicates()" class="px-3 py-1 bg-gray-500 hover:bg-gray-600 text-white text-sm rounded">Deselect</button>
                            <button onclick="acceptSelectedAsClean()" class="px-3 py-1 bg-green-500 hover:bg-green-600 text-white text-sm rounded">Accept Selected</button>
                            <button onclick="deleteSelectedDuplicates()" class="px-3 py-1 bg-red-500 hover:bg-red-600 text-white text-sm rounded">Delete Selected</button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-6 py-3 text-left"><input type="checkbox" id="selectAllDuplicates" onchange="toggleSelectAllDuplicates()"></th>
                                    <th class="px-6 py-3 text-left font-medium text-gray-700">Name</th>
                                    <th class="px-6 py-3 text-left font-medium text-gray-700">Match Level</th>
                                    <th class="px-6 py-3 text-left font-medium text-gray-700">Details</th>
                                    <th class="px-6 py-3 text-center font-medium text-gray-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
            `;
            
            data.duplicates.forEach(function(dup) {
                let rowClass = dup.match_details === 'Original record (has high-confidence duplicates)' ? 'bg-yellow-50' : '';
                
                html += `
                    <tr class="border-b border-gray-200 hover:bg-gray-50 ${rowClass}">
                        <td class="px-6 py-4"><input type="checkbox" class="duplicate-checkbox" value="${dup.id}"></td>
                        <td class="px-6 py-4"><strong>${escapeHtml(dup.full_name)}</strong></td>
                        <td class="px-6 py-4">${dup.match_level || 0}%</td>
                        <td class="px-6 py-4">${escapeHtml(dup.match_details || '')}</td>
                        <td class="px-6 py-4 text-center">
                            <button onclick="viewDuplicateDetails(${dup.id})" class="text-blue-500 hover:text-blue-700 text-sm mr-2">View</button>
                            <button onclick="acceptAsClean(${dup.id})" class="text-green-500 hover:text-green-700 text-sm mr-2">Accept</button>
                            <button onclick="deleteDuplicate(${dup.id})" class="text-red-500 hover:text-red-700 text-sm">Delete</button>
                        </td>
                    </tr>
                `;
            });
            
            html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        } else {
            html += `
                <div class="bg-green-50 border-2 border-green-200 rounded-xl p-12 text-center">
                    <svg class="h-16 w-16 text-green-500 mx-auto mb-4" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
                    </svg>
                    <h3 class="text-lg font-semibold text-green-800 mb-2">No Pending Duplicates!</h3>
                    <p class="text-green-700 mb-4">All records have been reviewed and marked as clean.</p>
                    <button onclick="exportCleanRecords()" class="px-6 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg font-medium">
                        Export Clean Records
                    </button>
                </div>
            `;
        }
        
        document.getElementById('duplicateCheckResults').innerHTML = html;
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
        const query = document.getElementById('duplicateSearchInput').value.trim().toLowerCase();
        if (!query) {
            resetDuplicateSearch();
            return;
        }
        // Implementation for filtering
    }

    function resetDuplicateSearch() {
        document.getElementById('duplicateSearchInput').value = '';
    }

    // Accept as clean
    function acceptAsClean(id) {
        Swal.fire({
            title: 'Accept as Clean?',
            text: 'This record will be marked as clean and removed from the duplicate list.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#22c55e',
            cancelButtonColor: '#ef4444',
            confirmButtonText: 'Yes, accept as clean!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'check_duplicates_api.php',
                    type: 'POST',
                    data: {
                        action: 'mark_as_clean',
                        id: id
                    },
                    success: function() {
                        Swal.fire('Success', 'Record marked as clean', 'success');
                        checkDuplicatesFromReceived();
                    }
                });
            }
        });
    }

    // Helper functions
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    function downloadCSV(csv, filename) {
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.href = url;
        link.setAttribute('download', filename);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    function selectAllDuplicates() {
        document.querySelectorAll('.duplicate-checkbox').forEach(el => el.checked = true);
    }

    function deselectAllDuplicates() {
        document.querySelectorAll('.duplicate-checkbox').forEach(el => el.checked = false);
    }

    function toggleSelectAllDuplicates() {
        const isChecked = document.getElementById('selectAllDuplicates').checked;
        document.querySelectorAll('.duplicate-checkbox').forEach(el => el.checked = isChecked);
    }

    function acceptSelectedAsClean() {
        const selectedIds = Array.from(document.querySelectorAll('.duplicate-checkbox:checked')).map(el => el.value);
        if (selectedIds.length === 0) {
            Swal.fire('Warning', 'Please select at least one record', 'warning');
            return;
        }
        Swal.fire({
            title: 'Accept Selected as Clean?',
            text: `You are about to accept ${selectedIds.length} record(s) as clean.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#22c55e',
            cancelButtonColor: '#ef4444',
            confirmButtonText: 'Yes, accept them!'
        }).then((result) => {
            if (result.isConfirmed) {
                checkDuplicatesFromReceived();
            }
        });
    }

    function deleteSelectedDuplicates() {
        const selectedIds = Array.from(document.querySelectorAll('.duplicate-checkbox:checked')).map(el => el.value);
        if (selectedIds.length === 0) {
            Swal.fire('Warning', 'Please select at least one record', 'warning');
            return;
        }
        Swal.fire({
            title: 'Delete Selected?',
            text: `You are about to delete ${selectedIds.length} record(s). This cannot be undone!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#3b82f6',
            confirmButtonText: 'Yes, delete them!'
        }).then((result) => {
            if (result.isConfirmed) {
                checkDuplicatesFromReceived();
            }
        });
    }

    function deleteDuplicate(id) {
        Swal.fire({
            title: 'Delete Record?',
            text: 'This action cannot be undone!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#3b82f6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                checkDuplicatesFromReceived();
            }
        });
    }

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
                    let details = response.data;
                    let modalContent = `
                        <dl class="grid grid-cols-2 gap-4">
                            <div><dt class="font-semibold text-gray-700">Name:</dt><dd class="text-gray-600">${escapeHtml(details.full_name)}</dd></div>
                            <div><dt class="font-semibold text-gray-700">Birthday:</dt><dd class="text-gray-600">${escapeHtml(details.birthday)}</dd></div>
                            <div><dt class="font-semibold text-gray-700">Barangay:</dt><dd class="text-gray-600">${escapeHtml(details.barangay)}</dd></div>
                            <div><dt class="font-semibold text-gray-700">Batch Reference:</dt><dd class="text-gray-600">${escapeHtml(details.batch_reference)}</dd></div>
                        </dl>
                    `;
                    document.getElementById('detailsContent').innerHTML = modalContent;
                    document.getElementById('detailsModal').classList.remove('hidden');
                }
            }
        });
    }

    function exportCleanRecords() {
        const data = localStorage.getItem('lastDuplicateResults');
        if (data) {
            const results = JSON.parse(data);
            if (results.clean_records_data && results.clean_records_data.length > 0) {
                let csv = "Batch Reference,Full Name,Barangay,Birthday\n";
                results.clean_records_data.forEach(function(record) {
                    csv += `"${escapeHtml(record.batch_reference || '')}","${escapeHtml(record.full_name)}","${escapeHtml(record.barangay || '')}","${escapeHtml(record.birthday || '')}"\n`;
                });
                downloadCSV(csv, 'clean_records_' + new Date().toISOString().slice(0,19) + '.csv');
                Swal.fire('Success!', `${results.clean_records_data.length} clean records exported`, 'success');
            }
        }
    }

    function deleteBeneficiary(id) {
        currentDeleteId = id;
        document.getElementById('deleteConfirmModal').classList.remove('hidden');
    }

    function confirmDelete() {
        if (currentDeleteId) {
            $.ajax({
                url: 'check_duplicates_api.php',
                type: 'POST',
                data: {
                    action: 'delete_beneficiary',
                    id: currentDeleteId
                },
                success: function() {
                    closeDeleteModal();
                    Swal.fire('Success', 'Record deleted successfully', 'success');
                    loadReceivedBeneficiaries();
                }
            });
        }
    }

    // Import form submission
    document.addEventListener('submit', function(e) {
        if (e.target.id === 'importForm') {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('action', 'import_beneficiaries');
            
            $.ajax({
                url: 'process.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Success', 'Beneficiaries imported successfully', 'success');
                        closeImportModal();
                        document.getElementById('importForm').reset();
                        loadReceivedBeneficiaries();
                    } else {
                        Swal.fire('Error', response.message || 'Import failed', 'error');
                    }
                }
            });
        }
    });

    // Search functionality
    document.addEventListener('keyup', function(e) {
        if (e.target.id === 'beneficiarySearch') {
            filterBeneficiaries(e.target.value);
        }
    });

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        loadReceivedBeneficiaries();
    });
    </script>
</body>
</html>