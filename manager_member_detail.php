<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/team_functions.php';
require_once 'includes/manager_partner_functions.php';
require_once 'includes/review_functions.php';

// Check authentication and manager/partner role
checkAuth();
if (!isManager() && !isPartner()) {
    header('Location: dashboard.php');
    exit();
}

if (!isset($_GET['id']) || !isset($_GET['user_id'])) {
    header('Location: manager_dashboard.php');
    exit();
}

$team_id = intval($_GET['id']);
$user_id = intval($_GET['user_id']);

// Check if manager/partner has access to this team
if (isManager() && !isManagerOfTeam($pdo, $_SESSION['user_id'], $team_id)) {
    header('Location: manager_dashboard.php');
    exit();
}

if (isPartner() && !isPartnerOfTeam($pdo, $_SESSION['user_id'], $team_id)) {
    header('Location: partner_dashboard.php');
    exit();
}

$team = getTeamById($pdo, $team_id);
$member = getUserById($pdo, $user_id);

if (!$team || !$member) {
    header('Location: manager_dashboard.php');
    exit();
}

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $entry_id = intval($_POST['entry_id']);
    $review_status = $_POST['review_status'];
    $review_comments = trim($_POST['review_comments']);
    
    if (canUserReviewEntry($pdo, $_SESSION['user_id'], $entry_id)) {
        if (reviewCPDEntry($pdo, $entry_id, $_SESSION['user_id'], $review_status, $review_comments)) {
            $message = '<div class="alert alert-success">Review submitted successfully!</div>';
        } else {
            $message = '<div class="alert alert-error">Failed to submit review. Please try again.</div>';
        }
    } else {
        $message = '<div class="alert alert-error">You do not have permission to review this entry.</div>';
    }
}

// Handle bulk approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_approve'])) {
    if (isset($_POST['selected_entries']) && is_array($_POST['selected_entries'])) {
        if (bulkApproveEntries($pdo, $_POST['selected_entries'], $_SESSION['user_id'])) {
            $message = '<div class="alert alert-success">Selected entries approved successfully!</div>';
        } else {
            $message = '<div class="alert alert-error">Failed to approve some entries.</div>';
        }
    }
}

// Get filter parameters
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

// Get member's CPD entries with review information
$query = "
    SELECT ce.*, 
           u.username as reviewed_by_username,
           r.name as reviewer_role
    FROM cpd_entries ce
    LEFT JOIN users u ON ce.reviewed_by = u.id
    LEFT JOIN roles r ON u.role_id = r.id
    WHERE ce.user_id = ?
";

$params = [$user_id];

if ($start_date) {
    $query .= " AND ce.date_completed >= ?";
    $params[] = $start_date;
}

if ($end_date) {
    $query .= " AND ce.date_completed <= ?";
    $params[] = $end_date;
}

$query .= " ORDER BY ce.date_completed DESC, ce.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$cpd_entries = $stmt->fetchAll();

// Calculate totals and stats
$total_hours = 0;
$approved_count = 0;
$pending_count = 0;

foreach ($cpd_entries as $entry) {
    $total_hours += $entry['hours'];
    if ($entry['review_status'] === 'approved') {
        $approved_count++;
    } else {
        $pending_count++;
    }
}

$pageTitle = 'CPD Details: ' . $member['username'];
include 'includes/header.php';
?>

<div class="container">
    <div class="admin-header">
        <h1>CPD Details: <?php echo htmlspecialchars($member['username']); ?></h1>
        <?php if (isManager()): ?>
            <?php renderManagerNav($team_id, 'members'); ?>
        <?php else: ?>
            <?php renderPartnerNav(''); ?>
        <?php endif; ?>
    </div>

    <?php if (isset($message)) echo $message; ?>

    <div class="stats-grid" style="margin-bottom: 2rem;">
        <div class="stat-card">
            <h3>Team</h3>
            <p class="stat-number" style="font-size: 1.2rem;"><?php echo htmlspecialchars($team['name']); ?></p>
        </div>
        <div class="stat-card">
            <h3>Total Entries</h3>
            <p class="stat-number"><?php echo count($cpd_entries); ?></p>
        </div>
        <div class="stat-card">
            <h3>Total Hours</h3>
            <p class="stat-number"><?php echo round($total_hours, 1); ?></p>
        </div>
        <div class="stat-card" style="background: #d4edda; border-left-color: #28a745;">
            <h3>Approved</h3>
            <p class="stat-number" style="color: #28a745;"><?php echo $approved_count; ?></p>
        </div>
        <div class="stat-card" style="background: #fff3cd; border-left-color: #ffc107;">
            <h3>Pending Review</h3>
            <p class="stat-number" style="color: #856404;"><?php echo $pending_count; ?></p>
        </div>
    </div>

    <div class="admin-section">
        <h2>CPD Entries</h2>
        
        <?php if (count($cpd_entries) > 0): ?>
            <form method="POST" id="reviewForm">
                <?php if ($pending_count > 0): ?>
                <div class="bulk-actions" style="margin-bottom: 1rem; display: flex; gap: 10px; align-items: center;">
                    <button type="button" id="selectAllBtn" class="btn btn-secondary btn-small">Select All Pending</button>
                    <button type="submit" name="bulk_approve" class="btn btn-small" style="background: #28a745;">
                        ✓ Approve Selected
                    </button>
                    <span id="selectedCount" style="font-weight: bold;"></span>
                </div>
                <?php endif; ?>
                
                <table class="admin-table">
                    <thead>
                        <tr>
                            <?php if ($pending_count > 0): ?>
                            <th style="width: 40px;"><input type="checkbox" id="selectAll"></th>
                            <?php endif; ?>
                            <th>Date</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Hours</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cpd_entries as $entry): ?>
                        <tr data-entry-id="<?php echo $entry['id']; ?>" class="<?php echo $entry['review_status'] === 'pending' ? 'pending-row' : ''; ?>">
                            <?php if ($pending_count > 0): ?>
                            <td>
                                <?php if ($entry['review_status'] === 'pending'): ?>
                                <input type="checkbox" name="selected_entries[]" value="<?php echo $entry['id']; ?>" class="entry-checkbox">
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                            <td><?php echo htmlspecialchars($entry['date_completed']); ?></td>
                            <td><?php echo htmlspecialchars($entry['title']); ?></td>
                            <td><?php echo htmlspecialchars($entry['category']); ?></td>
                            <td><?php echo htmlspecialchars($entry['hours']); ?> hours</td>
                            <td>
                                <?php if ($entry['review_status'] === 'approved'): ?>
                                    <span class="status-badge status-approved">✓ Approved</span>
                                <?php else: ?>
                                    <span class="status-badge status-pending">⏳ Pending Review</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" 
                                        class="btn btn-small view-details-btn"
                                        data-entry-id="<?php echo $entry['id']; ?>"
                                        data-title="<?php echo htmlspecialchars($entry['title']); ?>"
                                        data-description="<?php echo htmlspecialchars($entry['description']); ?>"
                                        data-category="<?php echo htmlspecialchars($entry['category']); ?>"
                                        data-hours="<?php echo $entry['hours']; ?>"
                                        data-date="<?php echo $entry['date_completed']; ?>"
                                        data-status="<?php echo $entry['review_status']; ?>"
                                        data-comments="<?php echo htmlspecialchars($entry['review_comments'] ?? ''); ?>"
                                        data-reviewed-by="<?php echo htmlspecialchars($entry['reviewed_by_username'] ?? ''); ?>"
                                        data-reviewed-at="<?php echo $entry['reviewed_at'] ? date('M d, Y g:i A', strtotime($entry['reviewed_at'])) : ''; ?>"
                                        data-document-file="<?php echo $entry['supporting_docs'] ? urlencode($entry['supporting_docs']) : ''; ?>">
                                    View & Review
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
        <?php else: ?>
            <p>No CPD entries found for this team member<?php echo ($start_date || $end_date) ? ' in the selected date range' : ''; ?>.</p>
        <?php endif; ?>
    </div>

    <div style="text-align: center; margin-top: 2rem;">
        <a href="<?php echo isManager() ? 'manager_team_view.php' : 'partner_team_view.php'; ?>?id=<?php echo $team_id; ?><?php echo $start_date ? '&start_date=' . $start_date : ''; ?><?php echo $end_date ? '&end_date=' . $end_date : ''; ?>" 
           class="btn btn-secondary">Back to Team Overview</a>
    </div>
</div>

<!-- Review Modal -->
<div id="reviewModal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <span class="close">&times;</span>
        <h2>CPD Entry Details & Review</h2>
        
        <div id="entryDetails" style="background: #f8f9fa; padding: 1rem; border-radius: 4px; margin-bottom: 1.5rem;">
            <h3 id="modalTitle"></h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin: 1rem 0;">
                <div><strong>Date:</strong> <span id="modalDate"></span></div>
                <div><strong>Category:</strong> <span id="modalCategory"></span></div>
                <div><strong>Hours:</strong> <span id="modalHours"></span></div>
                <div><strong>Current Status:</strong> <span id="modalCurrentStatus"></span></div>
            </div>
            <div style="margin-top: 1rem;">
                <strong>Description:</strong>
                <p id="modalDescription" style="margin: 0.5rem 0;"></p>
            </div>
            <div id="documentSection" style="margin-top: 1rem; display: none;">
                <strong>Supporting Document:</strong>
                <a id="documentLink" href="#" target="_blank" class="btn btn-small" style="margin-left: 0.5rem;">View Document</a>
            </div>
        </div>

        <div id="existingReview" style="background: #e7f3ff; padding: 1rem; border-radius: 4px; margin-bottom: 1.5rem; display: none;">
            <h4 style="margin-top: 0;">Previous Review</h4>
            <p><strong>Reviewed by:</strong> <span id="reviewedBy"></span></p>
            <p><strong>Reviewed on:</strong> <span id="reviewedAt"></span></p>
            <p><strong>Comments:</strong></p>
            <p id="existingComments" style="font-style: italic;"></p>
        </div>
        
        <form method="POST">
            <input type="hidden" id="review_entry_id" name="entry_id">
            
            <div class="form-group">
                <label>Review Status:</label>
                <select name="review_status" id="review_status" required>
                    <option value="pending">Pending Review</option>
                    <option value="approved">Approved</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Review Comments:</label>
                <textarea name="review_comments" id="review_comments" rows="4" 
                          placeholder="Add your feedback or comments here..."></textarea>
                <small>Optional: Provide feedback to the team member about this entry</small>
            </div>
            
            <div class="modal-actions">
                <button type="submit" name="submit_review" class="btn">Submit Review</button>
                <button type="button" class="btn btn-secondary" id="cancelReview">Cancel</button>
            </div>
        </form>
    </div>
</div>

<style>
.status-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.875rem;
    font-weight: 600;
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

.pending-row {
    background: #fffef5;
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    overflow-y: auto;
}

.modal-content {
    background-color: #fff;
    margin: 2% auto;
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

.btn-small {
    padding: 0.4rem 0.8rem;
    font-size: 0.875rem;
}

.bulk-actions {
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 4px;
}
</style>

<script>
// Modal functionality - ensure all elements exist before accessing them
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('reviewModal');
    if (!modal) {
        console.error('Modal element not found!');
        return;
    }
    
    const closeBtn = modal.querySelector('.close');
    const cancelBtn = document.getElementById('cancelReview');
    const viewDetailsBtns = document.querySelectorAll('.view-details-btn');

    viewDetailsBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const entryId = this.dataset.entryId;
            const title = this.dataset.title;
            const description = this.dataset.description;
            const category = this.dataset.category;
            const hours = this.dataset.hours;
            const date = this.dataset.date;
            const status = this.dataset.status;
            const comments = this.dataset.comments;
            const reviewedBy = this.dataset.reviewedBy;
            const reviewedAt = this.dataset.reviewedAt;
            const documentFile = this.dataset.documentFile; // Changed from 'document' to avoid conflict
            
            // Populate modal - use window.document to be explicit
            const modalTitle = window.document.getElementById('modalTitle');
            const modalDate = window.document.getElementById('modalDate');
            const modalCategory = window.document.getElementById('modalCategory');
            const modalHours = window.document.getElementById('modalHours');
            const modalDescription = window.document.getElementById('modalDescription');
            const modalCurrentStatus = window.document.getElementById('modalCurrentStatus');
            const documentSection = window.document.getElementById('documentSection');
            const documentLink = window.document.getElementById('documentLink');
            const existingReview = window.document.getElementById('existingReview');
            const reviewedByElement = window.document.getElementById('reviewedBy');
            const reviewedAtElement = window.document.getElementById('reviewedAt');
            const existingComments = window.document.getElementById('existingComments');
            const reviewEntryId = window.document.getElementById('review_entry_id');
            const reviewStatus = window.document.getElementById('review_status');
            const reviewComments = window.document.getElementById('review_comments');
            
            if (modalTitle) modalTitle.textContent = title;
            if (modalDate) modalDate.textContent = date;
            if (modalCategory) modalCategory.textContent = category;
            if (modalHours) modalHours.textContent = hours + ' hours';
            if (modalDescription) modalDescription.textContent = description || 'No description provided';
            
            // Status badge
            if (modalCurrentStatus) {
                const statusBadge = status === 'approved' 
                    ? '<span class="status-badge status-approved">✓ Approved</span>'
                    : '<span class="status-badge status-pending">⏳ Pending Review</span>';
                modalCurrentStatus.innerHTML = statusBadge;
            }
            
            // Document link
            if (documentSection && documentLink && documentFile) {
                documentSection.style.display = 'block';
                documentLink.href = 'download.php?file=' + documentFile;
            } else if (documentSection) {
                documentSection.style.display = 'none';
            }
            
            // Existing review
            if (existingReview) {
                if (reviewedBy && reviewedAt) {
                    existingReview.style.display = 'block';
                    if (reviewedByElement) reviewedByElement.textContent = reviewedBy;
                    if (reviewedAtElement) reviewedAtElement.textContent = reviewedAt;
                    if (existingComments) existingComments.textContent = comments || 'No comments provided';
                } else {
                    existingReview.style.display = 'none';
                }
            }
            
            // Set form values
            if (reviewEntryId) reviewEntryId.value = entryId;
            if (reviewStatus) reviewStatus.value = status;
            if (reviewComments) reviewComments.value = comments || '';
            
            if (modal) modal.style.display = 'block';
        });
    });

    if (closeBtn) {
        closeBtn.onclick = () => modal.style.display = 'none';
    }
    
    if (cancelBtn) {
        cancelBtn.onclick = () => modal.style.display = 'none';
    }
    
    window.onclick = (event) => {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    };

    // Bulk selection functionality
    const selectAllCheckbox = document.getElementById('selectAll');
    const selectAllBtn = document.getElementById('selectAllBtn');
    const entryCheckboxes = document.querySelectorAll('.entry-checkbox');
    const selectedCountSpan = document.getElementById('selectedCount');

    function updateSelectedCount() {
        const checkedCount = document.querySelectorAll('.entry-checkbox:checked').length;
        if (selectedCountSpan && checkedCount > 0) {
            selectedCountSpan.textContent = `${checkedCount} selected`;
        } else if (selectedCountSpan) {
            selectedCountSpan.textContent = '';
        }
    }

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            entryCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelectedCount();
        });
    }

    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function() {
            entryCheckboxes.forEach(checkbox => {
                checkbox.checked = true;
            });
            if (selectAllCheckbox) selectAllCheckbox.checked = true;
            updateSelectedCount();
        });
    }

    entryCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedCount);
    });

    // Confirm bulk approval
    const reviewForm = document.getElementById('reviewForm');
    if (reviewForm) {
        reviewForm.addEventListener('submit', function(e) {
            if (e.submitter && e.submitter.name === 'bulk_approve') {
                const checkedCount = document.querySelectorAll('.entry-checkbox:checked').length;
                if (checkedCount === 0) {
                    e.preventDefault();
                    alert('Please select at least one entry to approve.');
                    return false;
                }
                if (!confirm(`Are you sure you want to approve ${checkedCount} selected entry(ies)?`)) {
                    e.preventDefault();
                    return false;
                }
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>