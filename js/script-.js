$(document).ready(function() {
    // Import Master List
    $('#masterListForm').on('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        formData.append('action', 'import_master');
        
        $('#masterUploadStatus').html('<div class="alert alert-info">Importing master list...</div>');
        
        $.ajax({
            url: 'process.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('#masterUploadStatus').html(
                        '<div class="alert alert-success">' +
                        '<i class="bi bi-check-circle"></i> ' + response.message +
                        '<br>Imported: ' + response.imported + ' records' +
                        '</div>'
                    );
                    // Reload to update statistics
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    $('#masterUploadStatus').html(
                        '<div class="alert alert-danger">' +
                        '<i class="bi bi-exclamation-triangle"></i> ' + response.message +
                        '</div>'
                    );
                }
            },
            error: function() {
                $('#masterUploadStatus').html(
                    '<div class="alert alert-danger">Error uploading file. Please try again.</div>'
                );
            }
        });
    });
    
    // Check Duplicates
    $('#checkDuplicatesForm').on('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        formData.append('action', 'check_duplicates');
        formData.append('match_name', $('#matchName').is(':checked'));
        formData.append('match_barangay', $('#matchBarangay').is(':checked'));
        formData.append('match_birthday', $('#matchBirthday').is(':checked'));
        formData.append('fuzzy_match', $('#fuzzyMatch').is(':checked'));
        
        $('#checkStatus').html('<div class="alert alert-info"><div class="spinner-border spinner-border-sm"></div> Checking for duplicates...</div>');
        
        $.ajax({
            url: 'process.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    displayResults(response.data);
                    $('#checkStatus').html(
                        '<div class="alert alert-success">' +
                        '<i class="bi bi-check-circle"></i> Check completed successfully!' +
                        '</div>'
                    );
                } else {
                    $('#checkStatus').html(
                        '<div class="alert alert-danger">' + response.message + '</div>'
                    );
                }
            },
            error: function() {
                $('#checkStatus').html(
                    '<div class="alert alert-danger">Error processing file. Please try again.</div>'
                );
            }
        });
    });
});

function displayResults(data) {
    $('#resultsSection').show();
    
    // Display summary
    var summaryHtml = '<div class="row">' +
        '<div class="col-md-4"><div class="alert alert-info">Total Checked: <strong>' + data.summary.total_checked + '</strong></div></div>' +
        '<div class="col-md-4"><div class="alert alert-danger">Duplicates Found: <strong>' + data.summary.duplicates + '</strong></div></div>' +
        '<div class="col-md-4"><div class="alert alert-success">Clean Records: <strong>' + data.summary.clean + '</strong></div></div>' +
        '</div>';
    $('#resultSummary').html(summaryHtml);
    
    // Display results table
    var tbody = $('#resultsBody');
    tbody.empty();
    
    if (data.duplicates.length > 0) {
        $.each(data.duplicates, function(index, record) {
            var row = '<tr class="duplicate">' +
                '<td>' + (index + 1) + '</td>' +
                '<td>' + record.name + '</td>' +
                '<td>' + record.barangay + '</td>' +
                '<td>' + record.birthday + '</td>' +
                '<td><span class="badge-duplicate status-badge">DUPLICATE</span></td>' +
                '<td>' + record.match_type + '</td>' +
                '</tr>';
            tbody.append(row);
        });
    } else {
        tbody.append('<tr><td colspan="6" class="text-center text-success">No duplicates found!</td></tr>');
    }
    
    // Scroll to results
    $('html, body').animate({
        scrollTop: $('#resultsSection').offset().top - 100
    }, 500);
}

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
                var html = '<div class="table-responsive"><table class="table table-bordered">' +
                    '<thead><tr><th>Name</th><th>Barangay</th><th>Birthday</th><th>Match Type</th></tr></thead><tbody>';
                
                $.each(response.data, function(index, record) {
                    html += '<tr>' +
                        '<td>' + record.name + '</td>' +
                        '<td>' + record.barangay + '</td>' +
                        '<td>' + record.birthday + '</td>' +
                        '<td>' + record.match_type + '</td>' +
                        '</tr>';
                });
                
                html += '</tbody></table></div>';
                $('#detailsContent').html(html);
                $('#detailsModal').modal('show');
            }
        }
    });
}

function exportDuplicates() {
    window.location.href = 'export.php?type=duplicates&check_id=' + currentCheckId;
}

function exportCleanList() {
    window.location.href = 'export.php?type=clean&check_id=' + currentCheckId;
}