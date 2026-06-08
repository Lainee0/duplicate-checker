$(document).ready(function() {
    // Sidebar Toggle
    $('#sidebarCollapse, #sidebarToggle').on('click', function() {
        $('#sidebar').toggleClass('collapsed');
        $('.main-content').toggleClass('expanded');
    });

    // Mobile Sidebar
    $('#sidebarCollapse').on('click', function() {
        if ($(window).width() <= 768) {
            $('#sidebar').toggleClass('show');
        }
    });

    // Page Navigation
    $('.nav-link[data-page]').on('click', function(e) {
        e.preventDefault();
        const page = $(this).data('page');
        switchPage(page);
    });

    // Initialize Charts
    initCharts();

    // File Upload Handling
    setupFileUpload('master');
    setupFileUpload('new');

    // Import Master List
    $('#masterListForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'import_master');
        
        showLoading('masterUploadStatus', 'Importing master list...');
        
        $.ajax({
            url: 'process.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showAlert('masterUploadStatus', 'success', 
                        `<i class="bi bi-check-circle"></i> ${response.message}<br>
                         <small>Imported: ${response.imported} records</small>`);
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showAlert('masterUploadStatus', 'danger', 
                        `<i class="bi bi-exclamation-triangle"></i> ${response.message}`);
                }
            },
            error: function() {
                showAlert('masterUploadStatus', 'danger', 
                    '<i class="bi bi-exclamation-triangle"></i> Error uploading file. Please try again.');
            }
        });
    });

    // Check Duplicates
    $('#checkDuplicatesForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'check_duplicates');
        formData.append('match_name', $('#matchName').is(':checked'));
        formData.append('match_barangay', $('#matchBarangay').is(':checked'));
        formData.append('match_birthday', $('#matchBirthday').is(':checked'));
        formData.append('fuzzy_match', $('#fuzzyMatch').is(':checked'));
        
        showLoading('checkStatus', 'Checking for duplicates...');
        
        $.ajax({
            url: 'process.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    displayResults(response.data);
                    showAlert('checkStatus', 'success', 
                        '<i class="bi bi-check-circle"></i> Check completed successfully!');
                    switchPage('results');
                } else {
                    showAlert('checkStatus', 'danger', response.message);
                }
            },
            error: function() {
                showAlert('checkStatus', 'danger', 
                    'Error processing file. Please try again.');
            }
        });
    });
});

// Page Switching
function switchPage(pageName) {
    // Update sidebar active state
    $('.nav-link[data-page]').removeClass('active');
    $(`.nav-link[data-page="${pageName}"]`).addClass('active');
    
    // Show selected page
    $('.page').removeClass('active');
    $(`#${pageName}-page`).addClass('active');
    
    // Update page title
    const titles = {
        'dashboard': 'Dashboard',
        'import-master': 'Import Master List',
        'check-duplicates': 'Check Duplicates',
        'results': 'Results',
        'history': 'History',
        'reports': 'Reports',
        'settings': 'Settings'
    };
    $('#pageTitle').text(titles[pageName] || 'Dashboard');
}

// Initialize Charts
function initCharts() {
    // Trend Chart
    const trendCtx = document.getElementById('trendChart');
    if (trendCtx) {
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Duplicates Found',
                    data: [5, 8, 3, 12, 7, 4, 15],
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }
    
    // Barangay Chart
    const barangayCtx = document.getElementById('barangayChart');
    if (barangayCtx) {
        new Chart(barangayCtx, {
            type: 'doughnut',
            data: {
                labels: ['San Jose', 'Santa Rosa', 'San Pedro', 'Others'],
                datasets: [{
                    data: [30, 25, 20, 25],
                    backgroundColor: ['#667eea', '#764ba2', '#f093fb', '#4facfe']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }
}

// File Upload Setup
function setupFileUpload(type) {
    const dropZone = $(`#${type}DropZone`);
    const fileInput = $(`#${type}File`);
    const fileInfo = $(`#${type}FileInfo`);
    const fileName = $(`#${type}FileName`);
    
    // Click to upload
    dropZone.on('click', function() {
        fileInput.click();
    });
    
    // File selected
    fileInput.on('change', function() {
        const file = this.files[0];
        if (file) {
            fileName.text(file.name);
            fileInfo.show();
            dropZone.find('h5').text('File Selected');
        }
    });
    
    // Drag and drop
    dropZone.on('dragover', function(e) {
        e.preventDefault();
        $(this).addClass('drag-over');
    });
    
    dropZone.on('dragleave', function(e) {
        e.preventDefault();
        $(this).removeClass('drag-over');
    });
    
    dropZone.on('drop', function(e) {
        e.preventDefault();
        $(this).removeClass('drag-over');
        const files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) {
            fileInput[0].files = files;
            fileInput.trigger('change');
        }
    });
}

// Clear File
function clearFile(type) {
    $(`#${type}File`).val('');
    $(`#${type}FileInfo`).hide();
    $(`#${type}DropZone`).find('h5').text('Drag & Drop your file here');
}

// Display Results
function displayResults(data) {
    const summary = `
        <div class="row g-3">
            <div class="col-md-4">
                <div class="alert alert-info text-center">
                    <h4>${data.summary.total_checked}</h4>
                    <small>Total Checked</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="alert alert-danger text-center">
                    <h4>${data.summary.duplicates}</h4>
                    <small>Duplicates Found</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="alert alert-success text-center">
                    <h4>${data.summary.clean}</h4>
                    <small>Clean Records</small>
                </div>
            </div>
        </div>
    `;
    $('#resultSummary').html(summary);
    
    const tbody = $('#resultsBody');
    tbody.empty();
    
    if (data.duplicates.length > 0) {
        data.duplicates.forEach((record, index) => {
            tbody.append(`
                <tr class="duplicate">
                    <td>${index + 1}</td>
                    <td><strong>${record.name}</strong></td>
                    <td>${record.barangay}</td>
                    <td>${record.birthday}</td>
                    <td><span class="badge bg-danger">DUPLICATE</span></td>
                    <td>${record.match_type}</td>
                </tr>
            `);
        });
    } else {
        tbody.append(`
            <tr>
                <td colspan="6" class="text-center text-success py-5">
                    <i class="bi bi-check-circle display-1 d-block mb-3"></i>
                    <h5>No duplicates found!</h5>
                    <p>All beneficiaries in the new list are unique</p>
                </td>
            </tr>
        `);
    }
}

// Export Functions
function exportDuplicates() {
    window.location.href = `export.php?type=duplicates&check_id=${currentCheckId}`;
}

function exportCleanList() {
    window.location.href = `export.php?type=clean&check_id=${currentCheckId}`;
}

// View Check Details
function viewCheckDetails(checkId) {
    $.ajax({
        url: 'process.php',
        type: 'POST',
        data: {
            action: 'get_check_details',
            check_id: checkId
        },
        success: function(response) {
            if (response.success) {
                let html = '<div class="table-responsive"><table class="table table-bordered">';
                html += '<thead class="table-dark"><tr><th>Name</th><th>Barangay</th><th>Birthday</th><th>Match Type</th></tr></thead><tbody>';
                
                response.data.forEach(record => {
                    html += `
                        <tr>
                            <td>${record.name}</td>
                            <td>${record.barangay}</td>
                            <td>${record.birthday}</td>
                            <td><span class="badge bg-info">${record.match_type}</span></td>
                        </tr>
                    `;
                });
                
                html += '</tbody></table></div>';
                $('#detailsContent').html(html);
                $('#detailsModal').modal('show');
            }
        }
    });
}

// Utility Functions
function showLoading(elementId, message) {
    $(`#${elementId}`).html(`
        <div class="alert alert-info">
            <div class="spinner-border spinner-border-sm me-2"></div>
            ${message}
        </div>
    `);
}

function showAlert(elementId, type, message) {
    $(`#${elementId}`).html(`
        <div class="alert alert-${type}">
            ${message}
        </div>
    `);
}