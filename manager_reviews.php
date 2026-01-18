<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/manager_partner_functions.php';
require_once 'includes/review_functions.php';
require_once 'includes/team_functions.php';

checkAuth();
if (!isManager() && !isPartner()) {
    header('Location: dashboard.php');
    exit();
}

// Handle bulk approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_approve'])) {
    if (isset($_POST['selected_entries']) && is_array($_POST['selected_entries'])) {
        $approved_count = 0;
        $failed_count = 0;
        
        foreach ($_POST['selected_entries'] as $entry_id) {
            $entry_id = intval($entry_id);
            if (canUserReviewEntry($pdo, $_SESSION['user_id'], $entry_id)) {
                if (reviewCPDEntry($pdo, $entry_id, $_SESSION['user_id'], 'approved', 'Bulk approved')) {
                    $approved_count++;
                } else {
                    $failed_count++;
                }
            } else {
                $failed_count++;
            }
        }
        
        if ($approved_count > 0) {
            $success_message = "Successfully approved $approved_count entry(ies)!";
        }
        if ($failed_count > 0) {
            $error_message = "Failed to approve $failed_count entry(ies).";
        }
    }
}

// Handle individual review from modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $entry_id = intval($_POST['entry_id']);
    $review_status = $_POST['review_status'];
    $review_comments = trim($_POST['review_comments']);
    
    if (canUserReviewEntry($pdo, $_SESSION['user_id'], $entry_id)) {
        if (reviewCPDEntry($pdo, $entry_id, $_SESSION['user_id'], $review_status, $review_comments)) {
            $success_message = "Review submitted successfully!";
        } else {
            $error_message = "Failed to submit review.";
        }
    } else {
        $error_message = "You do not have permission to review this entry.";
    }
}

// Get filter parameters
$filter_team = isset($_GET['team']) ? intval($_GET['team']) : null;
$filter_user = isset($_GET['user']) ? intval($_GET['user']) : null;
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'pending';

// Build query for pending reviews
$query = "
    SELECT ce.*, 
           u.username, u.email,
           t.name as team_name, t.id as team_id
    FROM cpd_entries ce
    JOIN users u ON ce.user_id = u.id
    JOIN user_teams ut ON u.id = ut.user_id
    JOIN teams t ON ut.team_id = t.id
    JOIN team_managers tm ON t.id = tm.team_id
    WHERE tm.manager_id = ?
";

$params = [$_SESSION['user_id']];

if ($filter_status) {
    $query .= " AND ce.review_status = ?";
    $params[] = $filter_status;
}

if ($filter_team) {
    $query .= " AND t.id = ?";
    $params[] = $filter_team;
}

if ($filter_user) {
    $query .= " AND u.id = ?";
    $params[] = $filter_user;
}

$query .= " ORDER BY ce.date_completed DESC, ce.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$entries = $stmt->fetchAll();

// Get documents for each entry
foreach ($entries as &$entry) {
    $entry['documents'] = getCPDDocuments($pdo, $entry['id']);
}

// Get teams for filter
$managed_teams = isManager() ? getManagerTeams($pdo, $_SESSION['user_id']) : getPartnerTeams($pdo, $_SESSION['user_id']);

// Get users in managed teams for filter
$all_users = [];
foreach ($managed_teams as $team) {
    $team_members = getTeamMembers($pdo, $team['id']);
    foreach ($team_members as $member) {
        if (!isset($all_users[$member['id']])) {
            $all_users[$member['id']] = $member;
        }
    }
}

$pageTitle = 'Review CPD Entries';
include 'includes/header.php';
?>

<style>
    .reviews-hero {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 16px;
        padding: 2rem;
        margin-bottom: 2rem;
        color: white;
        box-shadow: 0 8px 24px rgba(102, 126, 234, 0.25);
    }
    
    .reviews-hero h1 {
        margin: 0 0 0.5rem 0;
        font-size: 2rem;
    }
    
    .reviews-hero p {
        margin: 0;
        opacity: 0.9;
    }
    
    .filter-bar {
        background: white;
        padding: 1.5rem;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        margin-bottom: 1.5rem;
    }
    
    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        align-items: end;
    }
    
    .filter-actions {
        display: flex;
        gap: 0.5rem;
    }
    
    .bulk-actions-bar {
        background: #f8f9fa;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        margin-bottom: 1rem;
        display: flex;
        gap: 1rem;
        align-items: center;
        flex-wrap: wrap;
        border: 2px dashed #e1e8ed;
    }
    
    .bulk-actions-bar.has-selection {
        background: linear-gradient(135deg, #e8f0fe 0%, #f0f4ff 100%);
        border-color: #667eea;
    }
    
    .reviews-table-container {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        overflow: hidden;
    }
    
    .reviews-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .reviews-table thead {
        background: #f8f9fa;
        border-bottom: 2px solid #e1e8ed;
    }
    
    .reviews-table th {
        padding: 1rem;
        text-align: left;
        font-weight: 600;
        color: #666;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .reviews-table td {
        padding: 1rem;
        border-bottom: 1px solid #f3f4f6;
        vertical-align: top;
    }
    
    .reviews-table tbody tr {
        transition: background 0.2s;
    }
    
    .reviews-table tbody tr:hover {
        background: #f8f9fa;
    }
    
    .reviews-table tbody tr.selected {
        background: linear-gradient(135deg, #e8f0fe 0%, #f0f4ff 100%);
    }
    
    .entry-cell {
        max-width: 300px;
    }
    
    .entry-title-link {
        font-weight: 600;
        color: #2c3e50;
        text-decoration: none;
        display: block;
        margin-bottom: 0.25rem;
    }
    
    .entry-title-link:hover {
        color: #667eea;
        text-decoration: underline;
    }
    
    .entry-description-preview {
        color: #666;
        font-size: 0.9rem;
        line-height: 1.4;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .user-info {
        display: flex;
        flex-direction: column;
    }
    
    .user-name {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 0.25rem;
    }
    
    .team-badge-small {
        display: inline-block;
        padding: 0.25rem 0.6rem;
        background: #e3f2fd;
        color: #1976d2;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .hours-badge {
        display: inline-block;
        padding: 0.4rem 0.8rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 20px;
        font-weight: 700;
        font-size: 0.9rem;
    }
    
    .docs-count {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.25rem 0.6rem;
        background: #f8f9fa;
        border-radius: 12px;
        font-size: 0.85rem;
        color: #666;
    }
    
    .action-buttons {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    
    .btn-review {
        padding: 0.5rem 1rem;
        background: #667eea;
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s;
    }
    
    .btn-review:hover {
        background: #5568d3;
    }
    
    .btn-approve-quick {
        padding: 0.5rem 1rem;
        background: #28a745;
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s;
    }
    
    .btn-approve-quick:hover {
        background: #218838;
    }
    
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        color: #999;
    }
    
    .empty-state-icon {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }
    
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
        overflow-y: auto;
    }
    
    .modal-content {
        background-color: #fff;
        margin: 3% auto;
        padding: 2rem;
        border-radius: 12px;
        width: 90%;
        max-width: 700px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.2);
        position: relative;
    }
    
    .close {
        position: absolute;
        right: 1.5rem;
        top: 1rem;
        font-size: 1.8rem;
        cursor: pointer;
        color: #666;
        line-height: 1;
    }
    
    .close:hover {
        color: #000;
    }
    
    .modal-section {
        margin-bottom: 1.5rem;
    }
    
    .modal-section-title {
        font-weight: 600;
        color: #666;
        font-size: 0.85rem;
        text-transform: uppercase;
        margin-bottom: 0.75rem;
        letter-spacing: 0.5px;
    }
    
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
        background: #f8f9fa;
        padding: 1rem;
        border-radius: 8px;
    }
    
    .info-item {
        display: flex;
        flex-direction: column;
    }
    
    .info-label {
        font-size: 0.75rem;
        color: #666;
        margin-bottom: 0.25rem;
    }
    
    .info-value {
        font-weight: 600;
        color: #2c3e50;
    }
    
    .modal-description {
        background: #f8f9fa;
        padding: 1rem;
        border-radius: 8px;
        border-left: 4px solid #667eea;
        line-height: 1.6;
    }
    
    .document-list-modal {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .document-item-modal {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem;
        background: #f8f9fa;
        border-radius: 6px;
        border: 1px solid #e1e8ed;
    }
    
    .document-item-modal:hover {
        background: white;
        border-color: #667eea;
    }
    
    @media (max-width: 768px) {
        .filter-grid {
            grid-template-columns: 1fr;
        }
        
        .reviews-table {
            font-size: 0.85rem;
        }
        
        .reviews-table th,
        .reviews-table td {
            padding: 0.75rem;
        }
    }
</style>

<div class="container">
    <!-- Hero Section -->
    <div class="reviews-hero">
        <h1>⏳ Review CPD Entries</h1>
        <p>Review and approve CPD entries from your team members</p>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-error"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <form method="GET" id="filterForm">
            <div class="filter-grid">
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Status:</label>
                    <select name="status" onchange="this.form.submit()">
                        <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending Review</option>
                        <option value="approved" <?php echo $filter_status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="" <?php echo $filter_status === '' ? 'selected' : ''; ?>>All Statuses</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Team:</label>
                    <select name="team" onchange="this.form.submit()">
                        <option value="">All Teams</option>
                        <?php foreach ($managed_teams as $team): ?>
                            <option value="<?php echo $team['id']; ?>" <?php echo $filter_team == $team['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($team['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Team Member:</label>
                    <select name="user" onchange="this.form.submit()">
                        <option value="">All Members</option>
                        <?php foreach ($all_users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo $filter_user == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-actions">
                    <a href="manager_reviews.php" class="btn btn-secondary">Clear Filters</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Bulk Actions -->
    <?php if ($filter_status === 'pending' && count($entries) > 0): ?>
    <form method="POST" id="bulkForm">
        <div class="bulk-actions-bar" id="bulkActionsBar">
            <input type="checkbox" id="selectAll" onclick="toggleSelectAll()">
            <label for="selectAll" style="margin: 0; cursor: pointer; font-weight: 600;">Select All</label>
            
            <button type="submit" name="bulk_approve" class="btn btn-approve-quick" id="bulkApproveBtn" style="display: none;">
                ✓ Approve Selected
            </button>
            
            <span id="selectedCount" style="font-weight: 600; color: #667eea;"></span>
        </div>
    <?php endif; ?>

        <!-- Reviews Table -->
        <div class="reviews-table-container">
            <?php if (count($entries) > 0): ?>
                <table class="reviews-table">
                    <thead>
                        <tr>
                            <?php if ($filter_status === 'pending'): ?>
                            <th style="width: 40px;"></th>
                            <?php endif; ?>
                            <th>Entry Details</th>
                            <th>Submitted By</th>
                            <th>Date</th>
                            <th>Hours</th>
                            <th>Docs</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entries as $entry): ?>
                        <tr class="review-row" data-entry-id="<?php echo $entry['id']; ?>">
                            <?php if ($filter_status === 'pending'): ?>
                            <td>
                                <input type="checkbox" name="selected_entries[]" value="<?php echo $entry['id']; ?>" class="entry-checkbox" onclick="updateBulkActions()">
                            </td>
                            <?php endif; ?>
                            <td class="entry-cell">
                                <a href="#" onclick="openReviewModal(<?php echo $entry['id']; ?>); return false;" class="entry-title-link">
                                    <?php echo htmlspecialchars($entry['title']); ?>
                                </a>
                                <?php if ($entry['description']): ?>
                                    <div class="entry-description-preview">
                                        <?php echo htmlspecialchars($entry['description']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="user-info">
                                    <span class="user-name"><?php echo htmlspecialchars($entry['username']); ?></span>
                                    <span class="team-badge-small"><?php echo htmlspecialchars($entry['team_name']); ?></span>
                                </div>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($entry['date_completed'])); ?></td>
                            <td><span class="hours-badge"><?php echo $entry['hours']; ?> hrs</span></td>
                            <td>
                                <?php if (count($entry['documents']) > 0): ?>
                                    <span class="docs-count">📎 <?php echo count($entry['documents']); ?></span>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($entry['review_status'] === 'approved'): ?>
                                    <span class="status-badge status-approved">✓ Approved</span>
                                <?php else: ?>
                                    <span class="status-badge status-pending">⏳ Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button type="button" onclick="openReviewModal(<?php echo $entry['id']; ?>)" class="btn-review">
                                        Review
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">✅</div>
                    <h3>No entries found</h3>
                    <p>
                        <?php if ($filter_status === 'pending'): ?>
                            All caught up! No pending reviews.
                        <?php else: ?>
                            No entries match your filters.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    
    <?php if ($filter_status === 'pending' && count($entries) > 0): ?>
    </form>
    <?php endif; ?>
</div>

<!-- Review Modal -->
<div id="reviewModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeReviewModal()">&times;</span>
        <h2>Review CPD Entry</h2>
        <div id="reviewModalContent"></div>
    </div>
</div>

<script>
// Store entry data for modal
const entryData = <?php echo json_encode($entries); ?>;

function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.entry-checkbox');
    
    checkboxes.forEach(cb => {
        cb.checked = selectAll.checked;
    });
    
    updateBulkActions();
}

function updateBulkActions() {
    const checkboxes = document.querySelectorAll('.entry-checkbox:checked');
    const count = checkboxes.length;
    const bulkBar = document.getElementById('bulkActionsBar');
    const bulkBtn = document.getElementById('bulkApproveBtn');
    const countSpan = document.getElementById('selectedCount');
    
    if (count > 0) {
        bulkBar.classList.add('has-selection');
        bulkBtn.style.display = 'inline-block';
        countSpan.textContent = `${count} selected`;
    } else {
        bulkBar.classList.remove('has-selection');
        bulkBtn.style.display = 'none';
        countSpan.textContent = '';
    }
}

function openReviewModal(entryId) {
    const entry = entryData.find(e => e.id == entryId);
    if (!entry) return;
    
    const modal = document.getElementById('reviewModal');
    const content = document.getElementById('reviewModalContent');
    
    let documentsHtml = '';
    if (entry.documents && entry.documents.length > 0) {
        documentsHtml = `
            <div class="modal-section">
                <div class="modal-section-title">📎 Supporting Documents</div>
                <div class="document-list-modal">
                    ${entry.documents.map(doc => `
                        <div class="document-item-modal">
                            <span style="font-size: 1.5rem;">📄</span>
                            <div style="flex: 1;">
                                <div style="font-weight: 600; color: #2c3e50;">${escapeHtml(doc.original_filename)}</div>
                                <div style="font-size: 0.85rem; color: #666;">${formatFileSize(doc.file_size)}</div>
                            </div>
                            <a href="download.php?file=${encodeURIComponent(doc.filename)}" target="_blank" class="btn btn-small">View</a>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }
    
    content.innerHTML = `
        <div class="modal-section">
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Submitted By</span>
                    <span class="info-value">${escapeHtml(entry.username)}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Team</span>
                    <span class="info-value">${escapeHtml(entry.team_name)}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Date Completed</span>
                    <span class="info-value">${new Date(entry.date_completed).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Hours</span>
                    <span class="info-value">${entry.hours} hours</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Category</span>
                    <span class="info-value">${escapeHtml(entry.category)}</span>
                </div>
            </div>
        </div>
        
        ${entry.description ? `
            <div class="modal-section">
                <div class="modal-section-title">📝 Description</div>
                <div class="modal-description">${escapeHtml(entry.description)}</div>
            </div>
        ` : ''}
        
        ${documentsHtml}
        
        <div class="modal-section">
            <div class="modal-section-title">✏️ Your Review</div>
            <form method="POST">
                <input type="hidden" name="entry_id" value="${entry.id}">
                
                <div class="form-group">
                    <label>Review Status:</label>
                    <select name="review_status" required>
                        <option value="approved">Approve</option>
                        <option value="pending" selected>Keep Pending</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Comments (Optional):</label>
                    <textarea name="review_comments" rows="3" placeholder="Add feedback for ${escapeHtml(entry.username)}..."></textarea>
                </div>
                
                <div class="modal-actions" style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="submit" name="submit_review" class="btn">Submit Review</button>
                    <button type="button" onclick="closeReviewModal()" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    `;
    
    modal.style.display = 'block';
}

function closeReviewModal() {
    document.getElementById('reviewModal').style.display = 'none';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatFileSize(bytes) {
    if (!bytes) return '0 KB';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('reviewModal');
    if (event.target == modal) {
        closeReviewModal();
    }
};

// Confirm bulk approval
document.getElementById('bulkForm')?.addEventListener('submit', function(e) {
    if (e.submitter && e.submitter.name === 'bulk_approve') {
        const count = document.querySelectorAll('.entry-checkbox:checked').length;
        if (!confirm(`Approve ${count} selected entry(ies)?`)) {
            e.preventDefault();
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>
