<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/goal_functions.php';
require_once 'includes/team_functions.php';
require_once 'includes/department_functions.php';

// Check authentication
checkAuth();

// Debug logging function
function debugLog($message) {
    error_log("CPD DEBUG: " . $message);
}

function updateUserGoalProgress($pdo, $user_id) {
    // Get all active/overdue goals that apply to this user
    $stmt = $pdo->prepare("
        SELECT DISTINCT g.id
        FROM cpd_goals g
        WHERE g.status IN ('active', 'overdue')
        AND (
            g.target_user_id = ?
            OR g.target_team_id IN (SELECT team_id FROM user_teams WHERE user_id = ?)
            OR g.target_department_id IN (
                SELECT d.id FROM departments d
                JOIN teams t ON d.id = t.department_id
                JOIN user_teams ut ON t.id = ut.team_id
                WHERE ut.user_id = ?
            )
        )
    ");
    $stmt->execute([$user_id, $user_id, $user_id]);
    $goal_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $updated_count = 0;
    foreach ($goal_ids as $goal_id) {
        if (updateGoalProgress($pdo, $goal_id)) {
            $updated_count++;
        }
    }
    
    return $updated_count;
}

// Handle bulk delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    debugLog("POST request received: " . print_r($_POST, true));
    
    // Handle bulk delete
    if (isset($_POST['delete_entries'])) {
        if (isset($_POST['selected_entries']) && is_array($_POST['selected_entries'])) {
            $deleted_count = 0;
            $error_count = 0;
            
            foreach ($_POST['selected_entries'] as $entry_id) {
                // Validate entry ID
                $entry_id = intval($entry_id);
                if ($entry_id > 0) {
                    try {
                        if (deleteCPDEntry($pdo, $entry_id, $_SESSION['user_id'])) {
                            $deleted_count++;
                        } else {
                            $error_count++;
                        }
                    } catch (Exception $e) {
                        error_log("Delete error for entry $entry_id: " . $e->getMessage());
                        $error_count++;
                    }
                }
            }
            
            if ($deleted_count > 0) {
                echo "<div class='alert alert-success'>Successfully deleted $deleted_count CPD entry(ies).</div>";
				// Update goal progress
				$updated_goals = updateUserGoalProgress($pdo, $_SESSION['user_id']);
				if ($updated_goals > 0) {
					debugLog("Updated progress for $updated_goals goal(s) after deletion");
				}
            }
            
            if ($error_count > 0) {
                echo "<div class='alert alert-error'>Failed to delete $error_count entry(ies). Please try again.</div>";
            }
        } else {
            echo "<div class='alert alert-error'>No entries selected for deletion.</div>";
        }
    }
    
    // Handle single entry update - FIXED (removed updated_at reference)
    if (isset($_POST['update_entry'])) {
        debugLog("Update entry form submitted");
        debugLog("POST data: " . print_r($_POST, true));
        debugLog("FILES data: " . print_r($_FILES, true));
        
        $entry_id = intval($_POST['entry_id'] ?? 0);
        $title = trim(htmlspecialchars($_POST['edit_title'] ?? ''));
        $description = trim(htmlspecialchars($_POST['edit_description'] ?? ''));
        $date_completed = $_POST['edit_date_completed'] ?? '';
        $hours = floatval($_POST['edit_hours'] ?? 0);
        $category = htmlspecialchars($_POST['edit_category'] ?? '');
        
        debugLog("Parsed data: entry_id=$entry_id, title=$title, hours=$hours, date=$date_completed, category=$category");
        
        // Validate input
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
                // Check if this entry belongs to the user
                $stmt = $pdo->prepare("SELECT id, supporting_docs FROM cpd_entries WHERE id = ? AND user_id = ?");
                $stmt->execute([$entry_id, $_SESSION['user_id']]);
                $entry = $stmt->fetch();
                
                if ($entry) {
                    debugLog("Entry found, belongs to user. Updating...");
                    
                    // File upload handling for edit
                    $file_name = null;
                    $keep_existing_file = isset($_POST['keep_existing_file']) && $_POST['keep_existing_file'] == '1';
                    
                    if (!$keep_existing_file && isset($_FILES['edit_supporting_doc']) && $_FILES['edit_supporting_doc']['error'] === UPLOAD_ERR_OK) {
                        debugLog("New file uploaded, deleting old file");
                        
                        // Delete old file if exists
                        if ($entry['supporting_docs']) {
                            $old_filename = $entry['supporting_docs'];
                            if (preg_match('/^user' . $_SESSION['user_id'] . '_/', $old_filename)) {
                                $filepath = 'uploads/' . $old_filename;
                                if (file_exists($filepath)) {
                                    unlink($filepath);
                                }
                            }
                        }
                        
                        // Upload new file
                        $file_name = handleFileUpload($_FILES['edit_supporting_doc'], $_SESSION['user_id']);
                        debugLog("New file uploaded: " . ($file_name ? $file_name : 'failed'));
                    } elseif ($keep_existing_file) {
                        // Keep existing file
                        $file_name = $entry['supporting_docs'] ?? null;
                        debugLog("Keeping existing file: " . ($file_name ? $file_name : 'none'));
                    }
                    
                    // Update the entry - FIXED: removed updated_at column
                    if ($file_name !== null) {
                        $sql = "UPDATE cpd_entries SET title = ?, description = ?, date_completed = ?, hours = ?, category = ?, supporting_docs = ? WHERE id = ? AND user_id = ?";
                        $params = [$title, $description, $date_completed, $hours, $category, $file_name, $entry_id, $_SESSION['user_id']];
                        debugLog("SQL with file: $sql");
                        debugLog("Params: " . print_r($params, true));
                        
                        $stmt = $pdo->prepare($sql);
                        $result = $stmt->execute($params);
                    } else {
                        $sql = "UPDATE cpd_entries SET title = ?, description = ?, date_completed = ?, hours = ?, category = ? WHERE id = ? AND user_id = ?";
                        $params = [$title, $description, $date_completed, $hours, $category, $entry_id, $_SESSION['user_id']];
                        debugLog("SQL without file: $sql");
                        debugLog("Params: " . print_r($params, true));
                        
                        $stmt = $pdo->prepare($sql);
                        $result = $stmt->execute($params);
                    }
                    
                    debugLog("Update query executed. Rows affected: " . $stmt->rowCount());
                    
                    if ($stmt->rowCount() > 0) {
                        echo "<div class='alert alert-success'>CPD entry updated successfully!</div>";
                        debugLog("Update successful for entry ID: $entry_id");
						// Update goal progress
						$updated_goals = updateUserGoalProgress($pdo, $_SESSION['user_id']);
						if ($updated_goals > 0) {
							debugLog("Updated progress for $updated_goals goal(s)");
						}
                    } else {
                        echo "<div class='alert alert-error'>No changes were made to the entry. This could mean the data was identical.</div>";
                        debugLog("No rows affected by update");
                    }
                } else {
                    echo "<div class='alert alert-error'>Entry not found or access denied.</div>";
                    debugLog("Entry not found or doesn't belong to user");
                }
            } catch (PDOException $e) {
                error_log("Update error: " . $e->getMessage());
                debugLog("Database error: " . $e->getMessage());
                debugLog("Error code: " . $e->getCode());
                debugLog("Error info: " . print_r($pdo->errorInfo(), true));
                
                // Show user-friendly error
                echo "<div class='alert alert-error'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        } else {
            // Display validation errors
            foreach ($validation_errors as $error) {
                echo "<div class='alert alert-error'>" . htmlspecialchars($error) . "</div>";
            }
            debugLog("Validation errors: " . implode(', ', $validation_errors));
        }
    }
    
    // Handle new CPD entry with validation
    if (isset($_POST['add_entry'])) {
        // Sanitize input
        $title = trim(htmlspecialchars($_POST['title']));
        $description = trim(htmlspecialchars($_POST['description']));
        $date_completed = $_POST['date_completed'];
        $hours = floatval($_POST['hours']);
        $category = htmlspecialchars($_POST['category']);
        
        // Validate input
        $validation_errors = validateCPDEntry($_POST);
        
        if (empty($validation_errors)) {
            // File upload handling with user_id
            $file_name = null;
            if (isset($_FILES['supporting_doc']) && $_FILES['supporting_doc']['error'] === UPLOAD_ERR_OK) {
                $file_name = handleFileUpload($_FILES['supporting_doc'], $_SESSION['user_id']);
                if (!$file_name) {
                    echo "<div class='alert alert-error'>Error uploading file. Please try again.</div>";
                }
            }
            
            try {
                // Use prepared statement
                $stmt = $pdo->prepare("INSERT INTO cpd_entries (user_id, title, description, date_completed, hours, category, supporting_docs) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_SESSION['user_id'], 
                    $title, 
                    $description, 
                    $date_completed, 
                    $hours, 
                    $category, 
                    $file_name
                ]);
                
                echo "<div class='alert alert-success'>CPD entry added successfully!</div>";
				// Update goal progress
				$updated_goals = updateUserGoalProgress($pdo, $_SESSION['user_id']);
				if ($updated_goals > 0) {
					debugLog("Updated progress for $updated_goals goal(s)");
				}
            } catch (PDOException $e) {
                error_log("CPD entry error: " . $e->getMessage());
                echo "<div class='alert alert-error'>Error adding CPD entry: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        } else {
            // Display validation errors
            foreach ($validation_errors as $error) {
                echo "<div class='alert alert-error'>" . htmlspecialchars($error) . "</div>";
            }
        }
    }
}

// Get user's CPD entries with prepared statement
try {
    $stmt = $pdo->prepare("SELECT * FROM cpd_entries WHERE user_id = ? ORDER BY date_completed DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $entries = $stmt->fetchAll();
    debugLog("Loaded " . count($entries) . " entries for user");
} catch (PDOException $e) {
    error_log("Fetch entries error: " . $e->getMessage());
    $entries = [];
}

// Get total hours
$total_hours = getTotalCPDHours($pdo, $_SESSION['user_id']);
debugLog("Total hours: $total_hours");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CPD Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            position: relative;
        }

        .close {
            position: absolute;
            right: 1rem;
            top: 1rem;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }

        .close:hover {
            color: #000;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 1.5rem;
        }

        .file-actions {
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        
        /* Debug info - only show if needed */
        .debug-info {
            background: #f8f9fa;
            border: 1px solid #ddd;
            padding: 10px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 12px;
            display: none;
        }
        
        /* Import section styling */
        .import-section {
            background: #fff;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            border-left: 4px solid #17a2b8;
        }
        
        .import-section h2 {
            color: #17a2b8;
            margin-top: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Debug toggle button -->
        <button onclick="document.getElementById('debugInfo').style.display='block'" style="float:right; font-size:12px; padding:5px;">Debug</button>
        <div id="debugInfo" class="debug-info">
            PHP Session ID: <?php echo session_id(); ?><br>
            User ID: <?php echo $_SESSION['user_id']; ?><br>
            Username: <?php echo $_SESSION['username']; ?><br>
            Total Entries: <?php echo count($entries); ?><br>
            Server Time: <?php echo date('Y-m-d H:i:s'); ?>
        </div>
        
        <div class="header">
            <div class="header-content">
                <div class="logo">
                    <a href="dashboard.php">CPD Tracker</a>
                </div>
                <nav class="user-menu">
					<span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
					<a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
					<a href="user_goals.php" class="btn btn-secondary">Goals</a>
					<?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
						<a href="admin_dashboard.php" class="btn btn-secondary">Admin Dashboard</a>
					<?php endif; ?>
					<?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'partner'): ?>
						<a href="partner_dashboard.php" class="btn btn-secondary">Partner Dashboard</a>
					<?php endif; ?>
					<?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'manager'): ?>
						<a href="manager_dashboard.php" class="btn btn-secondary">Manager Dashboard</a>
					<?php endif; ?>
					
					<a href="logout.php" class="btn btn-secondary">Logout</a>
				</nav>
            </div>
        </div>
		
		<div class="user-teams-section">
			<h2>My Teams</h2>
			<?php
			
			$user_teams = getUserTeams($pdo, $_SESSION['user_id']);
			if (count($user_teams) > 0): ?>
				<div class="teams-list">
					<?php foreach ($user_teams as $team): ?>
						<div class="team-badge">
							<span class="team-name"><?php echo htmlspecialchars($team['name']); ?></span>
							<?php if (!empty($team['description'])): ?>
								<span class="team-description"> - <?php echo htmlspecialchars($team['description']); ?></span>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php else: ?>
				<p>You are not assigned to any teams yet.</p>
			<?php endif; ?>
		</div>

        <div class="cpd-form">
            <h2>Add New CPD Entry</h2>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Title:</label>
                    <input type="text" name="title" required maxlength="255">
                </div>
                <div class="form-group">
                    <label>Description:</label>
                    <textarea name="description" rows="3" maxlength="2000"></textarea>
                </div>
                <div class="form-group">
                    <label>Date Completed:</label>
                    <input type="date" name="date_completed" required>
                </div>
                <div class="form-group">
                    <label>Hours:</label>
                    <input type="number" name="hours" step="0.5" min="0.5" max="100" required>
                </div>
                <div class="form-group">
                    <label>Category:</label>
                    <select name="category">
                        <option value="Training">Training</option>
                        <option value="Conference">Conference</option>
                        <option value="Reading">Reading</option>
                        <option value="Online Course">Online Course</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Supporting Documentation:</label>
                    <div class="file-upload">
                        <input type="file" name="supporting_doc" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        <small>Max 10MB - PDF, JPEG, PNG, or Word docs</small>
                    </div>
                </div>
                <button type="submit" name="add_entry" class="btn btn-block">Add CPD Entry</button>
            </form>
        </div>
		
		<h2>Your CPD Entries</h2>
        <?php if (count($entries) > 0): ?>
            <form id="deleteForm" method="POST" action="" enctype="multipart/form-data">
                <div id="bulkActions" class="bulk-actions" style="display: none; margin-bottom: 1rem;">
                    <button type="button" id="selectAllBtn" class="btn btn-secondary">Select All</button>
                    <button type="button" id="deselectAllBtn" class="btn btn-secondary">Deselect All</button>
                    <button type="button" id="editSelectedBtn" class="btn btn-primary" style="display: none;">
                        ✏️ Edit Selected
                    </button>
                    <button type="submit" name="delete_entries" id="deleteSelectedBtn" class="btn btn-danger">
                        🗑️ Delete Selected
                    </button>
                    <span id="selectedCount" style="margin-left: 10px; font-weight: bold;"></span>
                </div>
                
                <table class="entries-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;">
                                <input type="checkbox" id="selectAll">
                            </th>
                            <th>Date</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Hours</th>
                            <th>Documentation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entries as $entry): ?>
                        <tr data-entry-id="<?php echo $entry['id']; ?>" data-description="<?php echo htmlspecialchars($entry['description']); ?>">
                            <td>
                                <input type="checkbox" name="selected_entries[]" value="<?php echo $entry['id']; ?>" class="entry-checkbox">
                            </td>
                            <td><?php echo htmlspecialchars($entry['date_completed']); ?></td>
                            <td><?php echo htmlspecialchars($entry['title']); ?></td>
                            <td><?php echo htmlspecialchars($entry['category']); ?></td>
                            <td><?php echo htmlspecialchars($entry['hours']); ?> hours</td>
                            <td>
                                <?php if ($entry['supporting_docs']): ?>
                                    <?php 
                                    $safe_filename = htmlspecialchars($entry['supporting_docs']);
                                    ?>
                                    <a href="download.php?file=<?php echo urlencode($entry['supporting_docs']); ?>" target="_blank">View Document</a>
                                <?php else: ?>
                                    No document
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="total-hours-row">
                            <td colspan="5" style="text-align: right; font-weight: bold;">Total Hours:</td>
                            <td style="font-weight: bold;"><?php echo htmlspecialchars($total_hours); ?> hours</td>
                        </tr>
                    </tbody>
                </table>
            </form>
        <?php else: ?>
            <p>No CPD entries yet. Add your first entry above!</p>
        <?php endif; ?>
		<br>
		
		<style>
			.user-teams-section {
				background: #fff;
				padding: 1.5rem;
				border-radius: 8px;
				box-shadow: 0 2px 10px rgba(0,0,0,0.1);
				margin-bottom: 2rem;
				border-left: 4px solid #6c757d;
			}
			
			.user-teams-section h2 {
				color: #6c757d;
				margin-top: 0;
			}
			
			.teams-list {
				display: flex;
				flex-wrap: wrap;
				gap: 0.5rem;
			}
			
			.team-badge {
				background: #e9f7fe;
				border: 1px solid #b3d7ff;
				border-radius: 20px;
				padding: 0.5rem 1rem;
				font-size: 0.9rem;
			}
			
			.team-name {
				font-weight: bold;
				color: #007cba;
			}
			
			.team-description {
				color: #666;
			}
		</style>
		
		<?php include 'includes/goals_widget.php'; ?>
		
		<!-- Import Section -->
        <div class="import-section">
            <h2>Import from Calendar</h2>
            <p>Import CPD entries from calendar (.ics) files exported from Google Calendar, Outlook, etc.</p>
            <a href="import_ics.php" class="btn" style="background: #17a2b8;">
                📅 Import .ics File
            </a>
        </div>
		
		<!-- CSV Import Section -->
		<div class="import-section" style="border-left: 4px solid #28a745; margin-top: 2rem;">
			<h2>Bulk Import from CSV</h2>
			<p>Import multiple CPD entries at once using a CSV file. Download the template, fill in your data, and upload.</p>
			<a href="import_csv.php" class="btn" style="background: #28a745;">
				📊 Import CSV File
			</a>
		</div>

        <div class="export-section">
            <h2>Export CPD Records</h2>
            <form method="GET" action="export_csv.php" id="exportForm">
                <div class="form-group">
                    <label>Start Date:</label>
                    <input type="date" name="start_date" id="exportStartDate">
                </div>
                <div class="form-group">
                    <label>End Date:</label>
                    <input type="date" name="end_date" id="exportEndDate">
                </div>
                <div class="form-group">
                    <label>Category:</label>
                    <select name="category" id="exportCategory">
                        <option value="all">All Categories</option>
                        <option value="Training">Training</option>
                        <option value="Conference">Conference</option>
                        <option value="Reading">Reading</option>
                        <option value="Online Course">Online Course</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-block" style="background: #28a745;">
                    📥 Export CSV
                </button>
            </form>
        </div>
    </div>
    
    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Edit CPD Entry</h2>
            <form id="editForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" id="edit_entry_id" name="entry_id">
                <input type="hidden" id="edit_keep_existing_file" name="keep_existing_file" value="0">
                
                <div class="form-group">
                    <label>Title:</label>
                    <input type="text" id="edit_title" name="edit_title" required maxlength="255">
                </div>
                <div class="form-group">
                    <label>Description:</label>
                    <textarea id="edit_description" name="edit_description" rows="3" maxlength="2000"></textarea>
                </div>
                <div class="form-group">
                    <label>Date Completed:</label>
                    <input type="date" id="edit_date_completed" name="edit_date_completed" required>
                </div>
                <div class="form-group">
                    <label>Hours:</label>
                    <input type="number" id="edit_hours" name="edit_hours" step="0.5" min="0.5" max="100" required>
                </div>
                <div class="form-group">
                    <label>Category:</label>
                    <select id="edit_category" name="edit_category">
                        <option value="Training">Training</option>
                        <option value="Conference">Conference</option>
                        <option value="Reading">Reading</option>
                        <option value="Online Course">Online Course</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Supporting Documentation:</label>
                    <div id="currentFileInfo" style="margin-bottom: 10px; padding: 10px; background: #f8f9fa; border-radius: 4px; display: none;">
                        <strong>Current file:</strong> <span id="currentFileName"></span>
                        <br>
                        <label>
                            <input type="checkbox" id="keepFileCheckbox" checked> Keep existing file
                        </label>
                    </div>
                    <div class="file-upload">
                        <input type="file" id="edit_supporting_doc" name="edit_supporting_doc" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.ics">
                        <small>Max 10MB - PDF, JPEG, PNG, Word docs, or .ics calendar files</small>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="submit" name="update_entry" class="btn">Save Changes</button>
                    <button type="button" class="btn btn-secondary" id="cancelEdit">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="js/script.js"></script>
</body>
</html>