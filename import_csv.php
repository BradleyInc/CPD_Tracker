<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';

// Check authentication
checkAuth();

$pageTitle = 'Bulk Import CPD Entries (CSV)';
include 'includes/header.php';

$import_results = [
    'success' => 0,
    'failed' => 0,
    'errors' => [],
    'skipped' => 0
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['import_csv']) && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['csv_file'];
        
        // Validate file type
        $allowed_types = ['text/csv', 'application/csv', 'text/plain', 'application/vnd.ms-excel'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($file_ext !== 'csv' || !in_array($file['type'], $allowed_types)) {
            $import_results['errors'][] = 'Please upload a valid CSV file';
        } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB max
            $import_results['errors'][] = 'File size must be less than 5MB';
        } else {
            // Process CSV file
            $handle = fopen($file['tmp_name'], 'r');
            
            if ($handle !== false) {
                $headers = fgetcsv($handle);
                
                // Validate headers
                $expected_headers = ['title', 'description', 'date_completed', 'hours', 'category'];
                $header_lower = array_map('strtolower', $headers);
                
                if (count(array_intersect($expected_headers, $header_lower)) < count($expected_headers)) {
                    $import_results['errors'][] = 'CSV file has invalid headers. Please use the template.';
                } else {
                    // Map headers to indices
                    $header_map = array_flip($header_lower);
                    
                    $row_number = 1; // Start from 1 because we already read headers
                    
                    while (($data = fgetcsv($handle)) !== false) {
                        $row_number++;
                        
                        // Skip empty rows
                        if (count(array_filter($data)) === 0) {
                            continue;
                        }
                        
                        // Extract data using header map
                        $title = isset($header_map['title']) ? trim($data[$header_map['title']]) : '';
                        $description = isset($header_map['description']) ? trim($data[$header_map['description']]) : '';
                        $date_completed = isset($header_map['date_completed']) ? trim($data[$header_map['date_completed']]) : '';
                        $hours = isset($header_map['hours']) ? trim($data[$header_map['hours']]) : '';
                        $category = isset($header_map['category']) ? trim($data[$header_map['category']]) : '';
                        
                        // Validate row data
                        $validation_data = [
                            'title' => $title,
                            'description' => $description,
                            'date_completed' => $date_completed,
                            'hours' => $hours,
                            'category' => $category
                        ];
                        
                        $validation_errors = validateCPDEntry($validation_data);
                        
                        if (empty($validation_errors)) {
                            try {
                                // Check for duplicates (same title and date)
                                $stmt = $pdo->prepare("SELECT id FROM cpd_entries WHERE user_id = ? AND title = ? AND date_completed = ?");
                                $stmt->execute([
                                    $_SESSION['user_id'],
                                    $title,
                                    $date_completed
                                ]);
                                
                                if (!$stmt->fetch()) {
                                    // Insert CPD entry
                                    $stmt = $pdo->prepare("INSERT INTO cpd_entries (user_id, title, description, date_completed, hours, category, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                                    
                                    if ($stmt->execute([
                                        $_SESSION['user_id'],
                                        $title,
                                        $description,
                                        $date_completed,
                                        $hours,
                                        $category
                                    ])) {
                                        $import_results['success']++;
                                    } else {
                                        $import_results['failed']++;
                                        $import_results['errors'][] = "Row $row_number: Failed to insert into database";
                                    }
                                } else {
                                    $import_results['skipped']++;
                                }
                            } catch (PDOException $e) {
                                $import_results['failed']++;
                                $import_results['errors'][] = "Row $row_number: " . $e->getMessage();
                            }
                        } else {
                            $import_results['failed']++;
                            foreach ($validation_errors as $error) {
                                $import_results['errors'][] = "Row $row_number: $error";
                            }
                        }
                    }
                    
                    fclose($handle);
                }
            } else {
                $import_results['errors'][] = 'Could not read CSV file';
            }
        }
    } else {
        $import_results['errors'][] = 'Please select a CSV file to upload';
    }
}

// Function to generate template CSV
function generateCSVTemplate() {
    $template_data = [
        ['title', 'description', 'date_completed', 'hours', 'category'],
        ['Advanced JavaScript Workshop', '2-day workshop on modern JavaScript features', '2024-01-15', '16', 'Training'],
        ['Annual Tech Conference', 'Attended annual technology conference with various speakers', '2024-02-20', '8', 'Conference'],
        ['React Best Practices Book', 'Read book on React patterns and best practices', '2024-03-10', '10', 'Reading'],
        ['Data Science Online Course', 'Completed Coursera data science specialization', '2024-03-25', '30', 'Online Course'],
        ['Team Leadership Seminar', 'Internal seminar on team management skills', '2024-04-05', '4', 'Other']
    ];
    
    $filename = 'cpd_import_template.csv';
    $filepath = 'uploads/' . $filename;
    
    // Create uploads directory if it doesn't exist
    if (!is_dir('uploads/')) {
        mkdir('uploads/', 0755, true);
    }
    
    $handle = fopen($filepath, 'w');
    if ($handle !== false) {
        foreach ($template_data as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
        
        return $filename;
    }
    
    return false;
}

// Generate template if requested
if (isset($_GET['download_template'])) {
    $template_file = generateCSVTemplate();
    if ($template_file) {
        header('Location: download.php?file=' . urlencode($template_file));
        exit();
    }
}
?>

<div class="container">
    <h1>Bulk Import CPD Entries (CSV)</h1>
    
    <div class="import-instructions" style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
        <h3>How to Import CPD Entries from CSV:</h3>
        <ol>
            <li><a href="download_template.php" style="font-weight: bold;">Download the CSV template</a> to ensure proper formatting</li>
            <li>Fill in your CPD entries using the template format</li>
            <li>Save the file as a CSV (Comma Separated Values)</li>
            <li>Upload the CSV file below</li>
            <li>Review the import results</li>
        </ol>
        
        <div style="background: white; padding: 1rem; border-radius: 4px; margin-top: 1rem;">
            <h4>CSV Format Requirements:</h4>
            <ul style="margin-bottom: 0;">
                <li><strong>Columns must be in this exact order:</strong> title, description, date_completed, hours, category</li>
                <li><strong>Date format:</strong> YYYY-MM-DD (e.g., 2024-01-15)</li>
                <li><strong>Hours:</strong> Numeric values (e.g., 1.5, 8, 10.5)</li>
                <li><strong>Categories:</strong> Training, Conference, Reading, Online Course, Other</li>
                <li><strong>Maximum file size:</strong> 5MB</li>
            </ul>
        </div>
    </div>
    
    <?php if ($import_results['success'] > 0 || $import_results['failed'] > 0 || $import_results['skipped'] > 0): ?>
        <div class="import-results" style="margin-bottom: 2rem;">
            <h3>Import Results</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                <div style="background: #d4edda; padding: 1rem; border-radius: 4px; text-align: center;">
                    <div style="font-size: 2rem; font-weight: bold; color: #155724;"><?php echo $import_results['success']; ?></div>
                    <div>Successfully Imported</div>
                </div>
                <div style="background: #f8d7da; padding: 1rem; border-radius: 4px; text-align: center;">
                    <div style="font-size: 2rem; font-weight: bold; color: #721c24;"><?php echo $import_results['failed']; ?></div>
                    <div>Failed</div>
                </div>
                <div style="background: #fff3cd; padding: 1rem; border-radius: 4px; text-align: center;">
                    <div style="font-size: 2rem; font-weight: bold; color: #856404;"><?php echo $import_results['skipped']; ?></div>
                    <div>Skipped (Duplicates)</div>
                </div>
            </div>
            
            <?php if (!empty($import_results['errors'])): ?>
                <div class="alert alert-error" style="max-height: 200px; overflow-y: auto;">
                    <h4>Errors:</h4>
                    <ul style="margin-bottom: 0;">
                        <?php foreach (array_slice($import_results['errors'], 0, 10) as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                        <?php if (count($import_results['errors']) > 10): ?>
                            <li>... and <?php echo count($import_results['errors']) - 10; ?> more errors</li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <div class="import-form" style="background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 2rem;">
        <h2>Upload CSV File</h2>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Select CSV file:</label>
                <div class="file-upload">
                    <input type="file" name="csv_file" accept=".csv" required>
                    <small>Maximum file size: 5MB. <a href="download_template.php">Download template</a></small>
                </div>
            </div>
            <div class="form-group">
                <div class="form-check" style="margin-top: 1rem;">
                    <label>
                        <input type="checkbox" name="skip_duplicates" value="1" checked>
                        Skip duplicate entries (same title and date)
                    </label>
                </div>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 1.5rem;">
                <button type="submit" name="import_csv" class="btn btn-primary">Import CSV</button>
                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
    
    <div style="background: #e9f7fe; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
        <h3>CSV File Example:</h3>
        <pre style="background: white; padding: 1rem; border-radius: 4px; overflow-x: auto;">
title,description,date_completed,hours,category
"Advanced JavaScript Workshop","2-day workshop on modern JavaScript features",2024-01-15,16,Training
"Annual Tech Conference","Attended annual technology conference with various speakers",2024-02-20,8,Conference
"React Best Practices Book","Read book on React patterns and best practices",2024-03-10,10,Reading
"Data Science Online Course","Completed Coursera data science specialization",2024-03-25,30,Online Course
"Team Leadership Seminar","Internal seminar on team management skills",2024-04-05,4,Other</pre>
    </div>
    
    <div style="text-align: center; margin-top: 2rem;">
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>
</div>

<?php 
include 'includes/footer.php';
?>
[file content end]