<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';

// Check authentication
checkAuth();

$pageTitle = 'Import Calendar (.ics) File';

// Use the header from includes directory
include 'includes/header.php';

// Function to parse .ics file and extract events
function parseICSFile($filepath) {
    $events = [];
    
    if (!file_exists($filepath)) {
        return ['error' => 'File not found'];
    }
    
    $content = file_get_contents($filepath);
    if (!$content) {
        return ['error' => 'Could not read file'];
    }
    
    // Split content into events
    $eventsRaw = explode('BEGIN:VEVENT', $content);
    
    // Remove the first element (it's usually calendar metadata)
    array_shift($eventsRaw);
    
    foreach ($eventsRaw as $eventRaw) {
        $event = [
            'summary' => '',
            'description' => '',
            'start' => '',
            'end' => '',
            'location' => '',
            'duration' => 0
        ];
        
        // Parse event properties
        $lines = explode("\n", $eventRaw);
        foreach ($lines as $line) {
            if (strpos($line, 'SUMMARY:') === 0) {
                $event['summary'] = trim(substr($line, 8));
            } elseif (strpos($line, 'DESCRIPTION:') === 0) {
                $event['description'] = trim(substr($line, 12));
            } elseif (strpos($line, 'DTSTART:') === 0) {
                $dtstart = trim(substr($line, 8));
                $event['start'] = parseICSTimestamp($dtstart);
            } elseif (strpos($line, 'DTEND:') === 0) {
                $dtend = trim(substr($line, 6));
                $event['end'] = parseICSTimestamp($dtend);
            } elseif (strpos($line, 'LOCATION:') === 0) {
                $event['location'] = trim(substr($line, 9));
            } elseif (strpos($line, 'DURATION:') === 0) {
                $duration = trim(substr($line, 9));
                $event['duration'] = parseICSDuration($duration);
            }
        }
        
        // Calculate duration if not provided
        if ($event['duration'] == 0 && $event['start'] && $event['end']) {
            $start = new DateTime($event['start']);
            $end = new DateTime($event['end']);
            $interval = $start->diff($end);
            $event['duration'] = ($interval->days * 24) + $interval->h + ($interval->i / 60);
        }
        
        // Only add events that have at least a summary and start date
        if (!empty($event['summary']) && !empty($event['start'])) {
            $events[] = $event;
        }
    }
    
    return ['events' => $events, 'count' => count($events)];
}

// Helper function to parse ICS timestamp
function parseICSTimestamp($timestamp) {
    // Handle different timestamp formats
    if (preg_match('/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})Z?$/', $timestamp, $matches)) {
        return $matches[1] . '-' . $matches[2] . '-' . $matches[3] . ' ' . $matches[4] . ':' . $matches[5] . ':' . $matches[6];
    } elseif (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $timestamp, $matches)) {
        return $matches[1] . '-' . $matches[2] . '-' . $matches[3];
    }
    return '';
}

// Helper function to parse ICS duration
function parseICSDuration($duration) {
    // Format: PT1H30M (1 hour 30 minutes) or P1DT2H (1 day 2 hours)
    $hours = 0;
    
    if (preg_match('/P(?:(\d+)D)?T(?:(\d+)H)?(?:(\d+)M)?/', $duration, $matches)) {
        $days = isset($matches[1]) ? (int)$matches[1] : 0;
        $hours = isset($matches[2]) ? (int)$matches[2] : 0;
        $minutes = isset($matches[3]) ? (int)$matches[3] : 0;
        
        return ($days * 24) + $hours + ($minutes / 60);
    }
    
    return 0;
}

// Handle file upload
$imported_events = [];
$import_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_ics'])) {
    if (isset($_FILES['ics_file']) && $_FILES['ics_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['ics_file'];
        
        // Validate file type
        $allowed_types = ['text/calendar', 'application/octet-stream', 'text/plain'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($file_ext !== 'ics' || !in_array($file['type'], $allowed_types)) {
            $import_errors[] = 'Please upload a valid .ics calendar file';
        } elseif ($file['size'] > 10 * 1024 * 1024) {
            $import_errors[] = 'File size must be less than 10MB';
        } else {
            // Parse the .ics file
            $result = parseICSFile($file['tmp_name']);
            
            if (isset($result['error'])) {
                $import_errors[] = $result['error'];
            } else {
                $imported_events = $result['events'];
                
                // Handle import confirmation
                if (isset($_POST['confirm_import']) && isset($_POST['selected_events'])) {
                    $imported_count = 0;
                    $skipped_count = 0;
                    
                    foreach ($_POST['selected_events'] as $index) {
                        if (isset($imported_events[$index])) {
                            $event = $imported_events[$index];
                            
                            // Check if event already exists (by title and date)
                            try {
                                $stmt = $pdo->prepare("SELECT id FROM cpd_entries WHERE user_id = ? AND title = ? AND date_completed = ?");
                                $stmt->execute([
                                    $_SESSION['user_id'],
                                    $event['summary'],
                                    date('Y-m-d', strtotime($event['start']))
                                ]);
                                
                                if (!$stmt->fetch()) {
                                    // Insert as CPD entry
                                    $stmt = $pdo->prepare("INSERT INTO cpd_entries (user_id, title, description, date_completed, hours, category, supporting_docs, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                                    
                                    $category = determineCategory($event['summary'], $event['description']);
                                    $hours = max(0.5, round($event['duration'], 1));
                                    
                                    $stmt->execute([
                                        $_SESSION['user_id'],
                                        $event['summary'],
                                        $event['description'] . (!empty($event['location']) ? "\n\nLocation: " . $event['location'] : ''),
                                        date('Y-m-d', strtotime($event['start'])),
                                        $hours,
                                        $category,
                                        'imported.ics' // Mark as imported from .ics
                                    ]);
                                    
                                    $imported_count++;
                                } else {
                                    $skipped_count++;
                                }
                            } catch (PDOException $e) {
                                error_log("Import error: " . $e->getMessage());
                                $import_errors[] = 'Database error importing event: ' . $event['summary'];
                            }
                        }
                    }
                    
                    if ($imported_count > 0) {
                        echo "<div class='alert alert-success'>Successfully imported $imported_count CPD entries. $skipped_count entries were skipped (already exist).</div>";
                    } else if ($skipped_count > 0) {
                        echo "<div class='alert alert-warning'>All $skipped_count entries were skipped (already exist).</div>";
                    }
                    $imported_events = []; // Clear after import
                }
            }
        }
    } else {
        $import_errors[] = 'Please select an .ics file to upload';
    }
}

// Helper function to determine category from event data
function determineCategory($title, $description) {
    $title_lower = strtolower($title);
    $desc_lower = strtolower($description);
    
    $keywords = [
        'Training' => ['training', 'workshop', 'seminar', 'course', 'bootcamp'],
        'Conference' => ['conference', 'summit', 'symposium', 'convention'],
        'Reading' => ['reading', 'article', 'book', 'journal', 'paper'],
        'Online Course' => ['online', 'webinar', 'e-learning', 'mooc', 'coursera', 'udemy', 'edx'],
        'Other' => []
    ];
    
    foreach ($keywords as $category => $terms) {
        foreach ($terms as $term) {
            if (strpos($title_lower, $term) !== false || strpos($desc_lower, $term) !== false) {
                return $category;
            }
        }
    }
    
    return 'Other';
}
?>

<div class="container">
    <h1>Import Calendar (.ics) File</h1>
    
    <div class="import-instructions" style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
        <h3>How to Import CPD from Calendar:</h3>
        <ol>
            <li>Export your calendar events from Google Calendar, Outlook, Apple Calendar, etc. as an .ics file</li>
            <li>Upload the .ics file below</li>
            <li>Review and select the events you want to import as CPD entries</li>
            <li>Events will be automatically categorized based on their titles/descriptions</li>
        </ol>
        <p><strong>Note:</strong> Each event will be imported as a separate CPD entry. Duration will be calculated from event times.</p>
    </div>
    
    <?php if (!empty($import_errors)): ?>
        <div class="alert alert-error">
            <?php foreach ($import_errors as $error): ?>
                <p><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <div class="import-form" style="background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 2rem;">
        <h2>Upload .ics File</h2>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Select .ics file:</label>
                <div class="file-upload">
                    <input type="file" name="ics_file" accept=".ics" required>
                    <small>Maximum file size: 10MB</small>
                </div>
            </div>
            <button type="submit" name="import_ics" class="btn btn-block">Upload and Parse</button>
        </form>
    </div>
    
    <?php if (!empty($imported_events)): ?>
        <div class="events-preview" style="background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <h2>Review Events for Import (<?php echo count($imported_events); ?> found)</h2>
            <p>Select the events you want to import as CPD entries:</p>
            
            <form method="POST">
                <input type="hidden" name="import_ics" value="1">
                
                <div style="margin-bottom: 1rem;">
                    <label>
                        <input type="checkbox" id="selectAllEvents" onclick="toggleAllEvents(this)">
                        Select All Events
                    </label>
                </div>
                
                <table class="entries-table" style="margin-bottom: 2rem;">
                    <thead>
                        <tr>
                            <th style="width: 40px;">Select</th>
                            <th>Date</th>
                            <th>Title</th>
                            <th>Duration</th>
                            <th>Category</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($imported_events as $index => $event): 
                            $category = determineCategory($event['summary'], $event['description']);
                            $hours = max(0.5, round($event['duration'], 1));
                        ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="selected_events[]" value="<?php echo $index; ?>" class="event-checkbox" checked>
                            </td>
                            <td><?php echo date('Y-m-d', strtotime($event['start'])); ?></td>
                            <td><?php echo htmlspecialchars($event['summary']); ?></td>
                            <td><?php echo $hours; ?> hours</td>
                            <td><?php echo htmlspecialchars($category); ?></td>
                            <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;">
                                <?php 
                                $desc = htmlspecialchars($event['description']);
                                echo strlen($desc) > 100 ? substr($desc, 0, 100) . '...' : $desc;
                                ?>
                                <?php if (!empty($event['location'])): ?>
                                    <br><small>Location: <?php echo htmlspecialchars($event['location']); ?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div style="display: flex; gap: 10px;">
                    <button type="submit" name="confirm_import" class="btn btn-primary">Import Selected</button>
                    <a href="import_ics.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
            
            <script>
            function toggleAllEvents(checkbox) {
                const eventCheckboxes = document.querySelectorAll('.event-checkbox');
                eventCheckboxes.forEach(cb => {
                    cb.checked = checkbox.checked;
                });
            }
            </script>
        </div>
    <?php endif; ?>
    
    <div style="margin-top: 2rem; text-align: center;">
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>
</div>

<?php 
// Use the footer from includes directory
include 'includes/footer.php'; 
?>