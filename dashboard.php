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
    
    // Handle single entry update with multiple documents
	if (isset($_POST['update_entry'])) {
		debugLog("Update entry form submitted");
		
		$entry_id = intval($_POST['entry_id'] ?? 0);
		$title = trim(htmlspecialchars($_POST['edit_title'] ?? ''));
		$description = trim(htmlspecialchars($_POST['edit_description'] ?? ''));
		$date_completed = $_POST['edit_date_completed'] ?? '';
		$hours = floatval($_POST['edit_hours'] ?? 0);
		$category = htmlspecialchars($_POST['edit_category'] ?? '');
		$points = !empty($_POST['edit_points']) ? floatval($_POST['edit_points']) : null;
		
		// debugLog("Parsed data: entry_id=$entry_id, title=$title, hours=$hours, points=$points, date=$date_completed, category=$category");
		
		// Validate input
		$validation_data = [
			'title' => $title,
			'description' => $description,
			'date_completed' => $date_completed,
			'hours' => $hours,
			'points' => $points,
			'category' => $category
		];
		
		$validation_errors = validateCPDEntry($validation_data);
		
		if (empty($validation_errors)) {
			try {
				// Check if this entry belongs to the user
				$stmt = $pdo->prepare("SELECT id FROM cpd_entries WHERE id = ? AND user_id = ?");
				$stmt->execute([$entry_id, $_SESSION['user_id']]);
				$entry = $stmt->fetch();
				
				if ($entry) {
					// Update the entry
					$sql = "UPDATE cpd_entries SET title = ?, description = ?, date_completed = ?, hours = ?,  points = ?, category = ? WHERE id = ? AND user_id = ?";
					$params = [$title, $description, $date_completed, $hours, $points, $category, $entry_id, $_SESSION['user_id']];
					$stmt = $pdo->prepare($sql);
					$result = $stmt->execute($params);
					
					// Handle new file uploads
					$new_files_count = 0;
					if (isset($_FILES['edit_supporting_docs']) && !empty($_FILES['edit_supporting_docs']['name'][0])) {
						$uploaded_files = handleMultipleFileUploads($_FILES['edit_supporting_docs'], $_SESSION['user_id']);
						
						if (!empty($uploaded_files)) {
							saveCPDDocuments($pdo, $entry_id, $uploaded_files);
							$new_files_count = count($uploaded_files);
						}
					}
					
					if ($result || $new_files_count > 0) {
						$message = "CPD entry updated successfully!";
						if ($new_files_count > 0) {
							$message .= " Added $new_files_count new document(s).";
						}
						echo "<div class='alert alert-success'>$message</div>";
						
						// Update goal progress
						$updated_goals = updateUserGoalProgress($pdo, $_SESSION['user_id']);
						if ($updated_goals > 0) {
							debugLog("Updated progress for $updated_goals goal(s)");
						}
					} else {
						echo "<div class='alert alert-error'>No changes were made to the entry.</div>";
					}
				} else {
					echo "<div class='alert alert-error'>Entry not found or access denied.</div>";
				}
			} catch (PDOException $e) {
				error_log("Update error: " . $e->getMessage());
				echo "<div class='alert alert-error'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
			}
		} else {
			foreach ($validation_errors as $error) {
				echo "<div class='alert alert-error'>" . htmlspecialchars($error) . "</div>";
			}
		}
	}
    
    // Handle new CPD entry with multiple documents
	if (isset($_POST['add_entry'])) {
		// Sanitize input
		$title = trim(htmlspecialchars($_POST['title']));
		$description = trim(htmlspecialchars($_POST['description']));
		$date_completed = $_POST['date_completed'];
		$hours = floatval($_POST['hours']);
		$category = htmlspecialchars($_POST['category']);
		$points = !empty($_POST['points']) ? floatval($_POST['points']) : null;
		
		// Validate input
		$validation_errors = validateCPDEntry($_POST);
		
		if (empty($validation_errors)) {
			try {
				// Insert CPD entry first
				$stmt = $pdo->prepare("INSERT INTO cpd_entries (user_id, title, description, date_completed, hours, points, category) VALUES (?, ?, ?, ?, ?, ?, ?)");
				$stmt->execute([
					$_SESSION['user_id'], 
					$title, 
					$description, 
					$date_completed, 
					$hours,
					$points,  // This is now in the correct position
					$category
				]);
				
				$entry_id = $pdo->lastInsertId();
				
				// Handle multiple file uploads
				if (isset($_FILES['supporting_docs']) && !empty($_FILES['supporting_docs']['name'][0])) {
					$uploaded_files = handleMultipleFileUploads($_FILES['supporting_docs'], $_SESSION['user_id']);
					
					if (!empty($uploaded_files)) {
						saveCPDDocuments($pdo, $entry_id, $uploaded_files);
						echo "<div class='alert alert-success'>CPD entry added successfully with " . count($uploaded_files) . " document(s)!</div>";
					} else {
						echo "<div class='alert alert-warning'>CPD entry added but no documents were uploaded.</div>";
					}
				} else {
					echo "<div class='alert alert-success'>CPD entry added successfully!</div>";
				}
				
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

// Get user's CPD entries with documents
try {
    $stmt = $pdo->prepare("
        SELECT ce.*, 
               u.username as reviewed_by_username,
               r.name as reviewer_role
        FROM cpd_entries ce
        LEFT JOIN users u ON ce.reviewed_by = u.id
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE ce.user_id = ? 
        ORDER BY ce.date_completed DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $entries = $stmt->fetchAll();
    
    // Get documents for each entry
    foreach ($entries as &$entry) {
        $entry['documents'] = getCPDDocuments($pdo, $entry['id']);
    }
    
    debugLog("Loaded " . count($entries) . " entries for user");
} catch (PDOException $e) {
    error_log("Fetch entries error: " . $e->getMessage());
    $entries = [];
}

// Get total hours
$total_hours = getTotalCPDHours($pdo, $_SESSION['user_id']);
debugLog("Total hours: $total_hours");

// Get total points
$total_points = 0;
foreach ($entries as $entry) {
    if (isset($entry['points']) && $entry['points'] !== null && $entry['points'] > 0) {
        $total_points += floatval($entry['points']);
    }
}
debugLog("Total points: $total_points");
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
        
        /* Review status styles */
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }

        .status-approved {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .review-info {
            background: #e7f3ff;
            padding: 0.75rem;
            margin-top: 0.5rem;
            border-radius: 4px;
            font-size: 0.9rem;
            border-left: 3px solid #007cba;
        }

        .review-info strong {
            color: #007cba;
        }

        .review-comments {
            margin-top: 0.5rem;
            font-style: italic;
            color: #333;
        }

        .pending-review-row {
            background: #fffef5;
        }
		
		/* Document display styles */
		.document-list {
			display: flex;
			flex-direction: column;
			gap: 0.5rem;
		}

		.document-item {
			display: flex;
			align-items: center;
			gap: 0.5rem;
			padding: 0.5rem;
			background: #f8f9fa;
			border-radius: 4px;
			font-size: 0.9rem;
		}

		.document-item a {
			flex: 1;
			color: #007cba;
			text-decoration: none;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}

		.document-item a:hover {
			text-decoration: underline;
		}

		.btn-delete-doc {
			background: none;
			border: none;
			cursor: pointer;
			font-size: 1rem;
			padding: 0.25rem;
			opacity: 0.6;
			transition: opacity 0.2s;
		}

		.btn-delete-doc:hover {
			opacity: 1;
		}

		/* Existing documents in edit modal */
		.existing-documents {
			background: #f8f9fa;
			border: 1px solid #ddd;
			border-radius: 4px;
			padding: 1rem;
			max-height: 200px;
			overflow-y: auto;
		}

		.existing-doc-item {
			display: flex;
			align-items: center;
			justify-content: space-between;
			padding: 0.5rem;
			margin-bottom: 0.5rem;
			background: white;
			border-radius: 4px;
			border: 1px solid #e0e0e0;
		}

		.existing-doc-item:last-child {
			margin-bottom: 0;
		}

		.doc-info {
			display: flex;
			align-items: center;
			gap: 0.5rem;
			flex: 1;
		}

		.doc-name {
			font-weight: 500;
			color: #333;
		}

		.doc-size {
			font-size: 0.8rem;
			color: #666;
		}

		.btn-remove-doc {
			background: #dc3545;
			color: white;
			border: none;
			padding: 0.25rem 0.75rem;
			border-radius: 4px;
			cursor: pointer;
			font-size: 0.8rem;
		}

		.btn-remove-doc:hover {
			background: #c82333;
		}

		.no-documents {
			color: #666;
			font-style: italic;
			padding: 0.5rem;
		}
		
		/* Export Section Styles */
		.export-section {
			background: #fff;
			padding: 1.5rem;
			border-radius: 8px;
			box-shadow: 0 2px 10px rgba(0,0,0,0.1);
			margin-bottom: 2rem;
			border-left: 4px solid #007cba;
		}

		.export-section h2 {
			color: #007cba;
			margin-top: 0;
			margin-bottom: 1.5rem;
		}

		.export-section .form-group {
			margin-bottom: 1rem;
		}

		.export-section label {
			display: block;
			margin-bottom: 0.5rem;
			font-weight: 600;
			color: #333;
		}

		.export-section input[type="date"],
		.export-section select {
			width: 100%;
			padding: 0.75rem;
			border: 1px solid #ddd;
			border-radius: 4px;
			font-size: 1rem;
			transition: border-color 0.3s ease;
		}

		.export-section input[type="date"]:focus,
		.export-section select:focus {
			outline: none;
			border-color: #007cba;
			box-shadow: 0 0 0 2px rgba(0,124,186,0.2);
		}

		.export-section button[type="submit"] {
			width: 100%;
			transition: all 0.3s ease;
		}

		.export-section button[type="submit"]:hover {
			transform: translateY(-2px);
			box-shadow: 0 4px 12px rgba(0,0,0,0.2);
		}

		@media (max-width: 768px) {
			.export-section div[style*="grid-template-columns"] {
				grid-template-columns: 1fr !important;
			}
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
        
		
		
		<?php include_once 'includes/header.php';?>
		
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
                    <label>Points (Optional):</label>
                    <input type="number" name="points" step="0.01" min="0" max="9999.99" placeholder="e.g., 5.5">
                    <small>Optional CPD points for this activity</small>
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
						<input type="file" name="supporting_docs[]" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" multiple>
						<small>Max 10 files, 10MB each - PDF, JPEG, PNG, or Word docs</small>
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
							<th>Points</th>
                            <th>Documentation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entries as $entry): ?>
                        <tr data-entry-id="<?php echo $entry['id']; ?>" 
                            data-description="<?php echo htmlspecialchars($entry['description']); ?>"
							data-points="<?php echo htmlspecialchars($entry['points'] ?? ''); ?>"
                            class="<?php echo $entry['review_status'] === 'pending' ? 'pending-review-row' : ''; ?>">
                            <td>
                                <input type="checkbox" name="selected_entries[]" value="<?php echo $entry['id']; ?>" class="entry-checkbox">
                            </td>
                            <td><?php echo htmlspecialchars($entry['date_completed']); ?></td>
                            <td>
                                <div>
                                    <?php echo htmlspecialchars($entry['title']); ?>
                                    <?php if ($entry['review_status'] === 'approved'): ?>
                                        <span class="status-badge status-approved" title="Approved by <?php echo htmlspecialchars($entry['reviewed_by_username'] ?? 'Unknown'); ?>">
                                            ✓ Approved
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge status-pending">⏳ Pending Review</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($entry['review_comments']): ?>
                                <div class="review-info">
                                    <strong>Review from <?php echo htmlspecialchars($entry['reviewed_by_username']); ?>:</strong>
                                    <div class="review-comments"><?php echo nl2br(htmlspecialchars($entry['review_comments'])); ?></div>
                                    <?php if ($entry['reviewed_at']): ?>
                                    <div style="font-size: 0.8rem; color: #666; margin-top: 0.25rem;">
                                        <?php echo date('M d, Y g:i A', strtotime($entry['reviewed_at'])); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($entry['category']); ?></td>
                            <td><?php echo htmlspecialchars($entry['hours']); ?> hours</td>
							<td>
                                <?php if ($entry['points'] !== null && $entry['points'] > 0): ?>
                                    <?php echo htmlspecialchars(number_format($entry['points'], 2)); ?> pts
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
								<?php if (!empty($entry['documents'])): ?>
									<div class="document-list">
										<?php foreach ($entry['documents'] as $doc): ?>
											<div class="document-item">
												<a href="download.php?file=<?php echo urlencode($doc['filename']); ?>" target="_blank" title="<?php echo htmlspecialchars($doc['original_filename']); ?>">
													📄 <?php echo htmlspecialchars(strlen($doc['original_filename']) > 20 ? substr($doc['original_filename'], 0, 20) . '...' : $doc['original_filename']); ?>
												</a>
												<button type="button" class="btn-delete-doc" data-doc-id="<?php echo $doc['id']; ?>" data-entry-id="<?php echo $entry['id']; ?>" title="Delete document">
													🗑️
												</button>
											</div>
										<?php endforeach; ?>
									</div>
								<?php else: ?>
									No documents
								<?php endif; ?>
							</td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="total-hours-row">
                            <td colspan="4" style="text-align: right; font-weight: bold;">Total:</td>
                            <td style="font-weight: bold;"><?php echo number_format($total_hours, 1); ?> hours</td>
                            <td style="font-weight: bold;"><?php echo number_format($total_points, 2); ?> pts</td>
                            <td></td>
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
            <form method="GET" id="exportForm">
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
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <button type="submit" formaction="export_csv.php" class="btn" style="background: #28a745;">
                        📊 Export CSV
                    </button>
                    <button type="submit" formaction="export_pdf.php" class="btn" style="background: #dc3545;">
                        📄 Export PDF
                    </button>
                </div>
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
                    <label>Points (Optional):</label>
                    <input type="number" id="edit_points" name="edit_points" step="0.01" min="0" max="9999.99" placeholder="e.g., 5.5">
                    <small>Optional CPD points for this activity</small>
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
				
				<!-- Existing Documents Section -->
				<div class="form-group">
					<label>Current Documents:</label>
					<div id="existingDocuments" class="existing-documents">
						<!-- Documents will be populated by JavaScript -->
					</div>
				</div>
				
				<!-- Add New Documents -->
				<div class="form-group">
					<label>Add More Documentation:</label>
					<div class="file-upload">
						<input type="file" id="edit_supporting_docs" name="edit_supporting_docs[]" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" multiple>
						<small>Max 10 files, 10MB each - PDF, JPEG, PNG or Word docs</small>
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
	<script>
	// Function to delete document
	async function deleteDocument(docId, entryId) {
		if (!confirm('Are you sure you want to delete this document?')) {
			return;
		}
		
		try {
			const formData = new FormData();
			formData.append('delete_document', '1');
			formData.append('document_id', docId);
			
			const response = await fetch('ajax_delete_document.php', {
				method: 'POST',
				body: formData
			});
			
			const result = await response.json();
			
			if (result.success) {
				// Remove document from display
				const docElement = document.querySelector(`[data-doc-id="${docId}"]`).closest('.document-item');
				if (docElement) {
					docElement.remove();
				}
				
				// Check if this was the last document
				const entryRow = document.querySelector(`tr[data-entry-id="${entryId}"]`);
				const docList = entryRow.querySelector('.document-list');
				if (docList && docList.children.length === 0) {
					docList.parentElement.innerHTML = 'No documents';
				}
				
				alert('Document deleted successfully!');
			} else {
				alert('Error deleting document: ' + result.message);
			}
		} catch (error) {
			console.error('Delete error:', error);
			alert('Error deleting document');
		}
	}

	// Attach event listeners to delete buttons
	document.addEventListener('click', function(e) {
		if (e.target.classList.contains('btn-delete-doc') || e.target.parentElement.classList.contains('btn-delete-doc')) {
			const btn = e.target.classList.contains('btn-delete-doc') ? e.target : e.target.parentElement;
			const docId = btn.dataset.docId;
			const entryId = btn.dataset.entryId;
			deleteDocument(docId, entryId);
		}
	});

	// Update the edit modal population to show documents
	document.querySelectorAll('.entries-table tbody tr[data-entry-id]').forEach(row => {
		row.addEventListener('dblclick', async function() {
			const entryId = this.dataset.entryId;
			const title = this.querySelector('td:nth-child(3) div').textContent.split('\n')[0].trim();
			const description = this.dataset.description;
			const date = this.querySelector('td:nth-child(2)').textContent;
			const category = this.querySelector('td:nth-child(4)').textContent;
			const hours = parseFloat(this.querySelector('td:nth-child(5)').textContent);
			
			// Populate form
			document.getElementById('edit_entry_id').value = entryId;
			document.getElementById('edit_title').value = title;
			document.getElementById('edit_description').value = description;
			document.getElementById('edit_date_completed').value = date;
			document.getElementById('edit_hours').value = hours;
			document.getElementById('edit_category').value = category;
			
			// Load existing documents
			try {
				const response = await fetch(`ajax_get_documents.php?entry_id=${entryId}`);
				const documents = await response.json();
				
				const container = document.getElementById('existingDocuments');
				if (documents.length > 0) {
					container.innerHTML = documents.map(doc => `
						<div class="existing-doc-item">
							<div class="doc-info">
								<span>📄</span>
								<div>
									<div class="doc-name">${doc.original_filename}</div>
									<div class="doc-size">${formatFileSize(doc.file_size)}</div>
								</div>
							</div>
							<button type="button" class="btn-remove-doc" onclick="deleteDocumentFromModal(${doc.id}, ${entryId})">
								Remove
							</button>
						</div>
					`).join('');
				} else {
					container.innerHTML = '<div class="no-documents">No documents attached</div>';
				}
			} catch (error) {
				console.error('Error loading documents:', error);
				document.getElementById('existingDocuments').innerHTML = '<div class="no-documents">Error loading documents</div>';
			}
			
			// Show modal
			document.getElementById('editModal').style.display = 'block';
		});
	});

	// Function to delete document from edit modal
	async function deleteDocumentFromModal(docId, entryId) {
		if (!confirm('Are you sure you want to delete this document?')) {
			return;
		}
		
		try {
			const formData = new FormData();
			formData.append('delete_document', '1');
			formData.append('document_id', docId);
			
			const response = await fetch('ajax_delete_document.php', {
				method: 'POST',
				body: formData
			});
			
			const result = await response.json();
			
			if (result.success) {
				// Reload the documents in the modal
				const response2 = await fetch(`ajax_get_documents.php?entry_id=${entryId}`);
				const documents = await response2.json();
				
				const container = document.getElementById('existingDocuments');
				if (documents.length > 0) {
					container.innerHTML = documents.map(doc => `
						<div class="existing-doc-item">
							<div class="doc-info">
								<span>📄</span>
								<div>
									<div class="doc-name">${doc.original_filename}</div>
									<div class="doc-size">${formatFileSize(doc.file_size)}</div>
								</div>
							</div>
							<button type="button" class="btn-remove-doc" onclick="deleteDocumentFromModal(${doc.id}, ${entryId})">
								Remove
							</button>
						</div>
					`).join('');
				} else {
					container.innerHTML = '<div class="no-documents">No documents attached</div>';
				}
				
				// Also update the main table
				location.reload();
			} else {
				alert('Error deleting document: ' + result.message);
			}
		} catch (error) {
			console.error('Delete error:', error);
			alert('Error deleting document');
		}
	}

	// Helper function to format file sizes
	function formatFileSize(bytes) {
		if (bytes === 0) return '0 Bytes';
		const k = 1024;
		const sizes = ['Bytes', 'KB', 'MB'];
		const i = Math.floor(Math.log(bytes) / Math.log(k));
		return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
	}
	</script>
</body>
</html>