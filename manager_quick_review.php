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

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $entry_id = intval($_POST['entry_id']);
    $review_status = $_POST['review_status'];
    $review_comments = trim($_POST['review_comments']);
    
    if (canUserReviewEntry($pdo, $_SESSION['user_id'], $entry_id)) {
        if (reviewCPDEntry($pdo, $entry_id, $_SESSION['user_id'], $review_status, $review_comments)) {
            // Redirect to next pending entry or back to dashboard
            $stmt = $pdo->prepare("
                SELECT ce.id
                FROM cpd_entries ce
                JOIN user_teams ut ON ce.user_id = ut.user_id
                JOIN team_managers tm ON ut.team_id = tm.team_id
                WHERE tm.manager_id = ? AND ce.review_status = 'pending' AND ce.id != ?
                ORDER BY ce.date_completed DESC
                LIMIT 1
            ");
            $stmt->execute([$_SESSION['user_id'], $entry_id]);
            $next_entry = $stmt->fetch();
            
            if ($next_entry) {
                header('Location: manager_quick_review.php?entry_id=' . $next_entry['id'] . '&success=1');
            } else {
                header('Location: manager_dashboard.php?reviews_complete=1');
            }
            exit();
        }
    }
}

// Get entry to review
$entry_id = isset($_GET['entry_id']) ? intval($_GET['entry_id']) : 0;

if (!$entry_id) {
    header('Location: manager_dashboard.php');
    exit();
}

// Get entry details with user and team info
$stmt = $pdo->prepare("
    SELECT ce.*, 
           u.username, u.email,
           t.name as team_name, t.id as team_id
    FROM cpd_entries ce
    JOIN users u ON ce.user_id = u.id
    JOIN user_teams ut ON u.id = ut.user_id
    JOIN teams t ON ut.team_id = t.id
    JOIN team_managers tm ON t.id = tm.team_id
    WHERE ce.id = ? AND tm.manager_id = ?
");
$stmt->execute([$entry_id, $_SESSION['user_id']]);
$entry = $stmt->fetch();

if (!$entry) {
    header('Location: manager_dashboard.php');
    exit();
}

// Get documents
$entry['documents'] = getCPDDocuments($pdo, $entry['id']);

// Get count of remaining pending reviews
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM cpd_entries ce
    JOIN user_teams ut ON ce.user_id = ut.user_id
    JOIN team_managers tm ON ut.team_id = tm.team_id
    WHERE tm.manager_id = ? AND ce.review_status = 'pending'
");
$stmt->execute([$_SESSION['user_id']]);
$remaining_count = $stmt->fetchColumn();

$pageTitle = 'Review CPD Entry';
include 'includes/header.php';
?>

<style>
    .review-container {
        max-width: 900px;
        margin: 0 auto;
    }
    
    .review-progress {
        background: white;
        padding: 1rem 1.5rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    
    .progress-info {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .progress-badge {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.9rem;
    }
    
    .entry-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        margin-bottom: 1.5rem;
        overflow: hidden;
    }
    
    .entry-header {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        padding: 2rem;
        border-bottom: 2px solid #dee2e6;
    }
    
    .entry-title {
        font-size: 1.75rem;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 1rem;
    }
    
    .entry-meta-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
    }
    
    .meta-item {
        display: flex;
        flex-direction: column;
    }
    
    .meta-label {
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #666;
        margin-bottom: 0.25rem;
    }
    
    .meta-value {
        font-size: 1.1rem;
        font-weight: 600;
        color: #2c3e50;
    }
    
    .team-badge-large {
        display: inline-block;
        padding: 0.4rem 1rem;
        background: #e3f2fd;
        color: #1976d2;
        border-radius: 20px;
        font-weight: 600;
    }
    
    .entry-body {
        padding: 2rem;
    }
    
    .section-title {
        font-size: 1rem;
        font-weight: 600;
        color: #666;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .entry-description {
        background: #f8f9fa;
        padding: 1.5rem;
        border-radius: 8px;
        border-left: 4px solid #667eea;
        line-height: 1.8;
        color: #2c3e50;
        margin-bottom: 2rem;
    }
    
    .documents-grid {
        display: grid;
        gap: 1rem;
        margin-bottom: 2rem;
    }
    
    .document-card {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 8px;
        border: 1px solid #e1e8ed;
        transition: all 0.2s;
    }
    
    .document-card:hover {
        border-color: #667eea;
        background: white;
        box-shadow: 0 2px 8px rgba(102, 126, 234, 0.15);
    }
    
    .document-icon {
        font-size: 2rem;
    }
    
    .document-info {
        flex: 1;
    }
    
    .document-name {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 0.25rem;
    }
    
    .document-size {
        font-size: 0.85rem;
        color: #666;
    }
    
    .document-link {
        padding: 0.5rem 1rem;
        background: #667eea;
        color: white;
        text-decoration: none;
        border-radius: 6px;
        font-size: 0.9rem;
        font-weight: 600;
        transition: background 0.2s;
    }
    
    .document-link:hover {
        background: #5568d3;
    }
    
    .review-form-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        padding: 2rem;
    }
    
    .review-decision {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-bottom: 2rem;
    }
    
    .decision-option {
        position: relative;
    }
    
    .decision-option input[type="radio"] {
        position: absolute;
        opacity: 0;
    }
    
    .decision-label {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 1.5rem;
        border: 2px solid #e1e8ed;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .decision-label:hover {
        border-color: #667eea;
        background: #f8f9fa;
    }
    
    .decision-option input[type="radio"]:checked + .decision-label {
        border-color: #667eea;
        background: linear-gradient(135deg, #f0f4ff 0%, #e8f0fe 100%);
    }
    
    .decision-option.approve input[type="radio"]:checked + .decision-label {
        border-color: #28a745;
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    }
    
    .decision-icon {
        font-size: 2.5rem;
        margin-bottom: 0.5rem;
    }
    
    .decision-text {
        font-weight: 600;
        font-size: 1.1rem;
        color: #2c3e50;
    }
    
    .form-actions-sticky {
        position: sticky;
        bottom: 0;
        background: white;
        padding: 1.5rem;
        border-top: 2px solid #e1e8ed;
        display: flex;
        gap: 1rem;
        justify-content: space-between;
        margin: -2rem;
        margin-top: 2rem;
        border-radius: 0 0 12px 12px;
    }
    
    .btn-large {
        padding: 1rem 2rem;
        font-size: 1.1rem;
        font-weight: 600;
    }
    
    .btn-approve {
        background: #28a745;
        flex: 1;
    }
    
    .btn-approve:hover {
        background: #218838;
    }
    
    .btn-skip {
        background: #6c757d;
    }
    
    .btn-skip:hover {
        background: #545b62;
    }
</style>

<div class="review-container">
    <!-- Progress Bar -->
    <div class="review-progress">
        <div class="progress-info">
            <span class="progress-badge"><?php echo $remaining_count; ?> pending</span>
            <span>Reviewing CPD entries</span>
        </div>
        <a href="manager_dashboard.php" class="stat-link">← Back to dashboard</a>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            ✓ Review submitted successfully! <?php echo $remaining_count > 0 ? "Here's the next entry:" : "All reviews complete!"; ?>
        </div>
    <?php endif; ?>

    <!-- Entry Details -->
    <div class="entry-card">
        <div class="entry-header">
            <div class="entry-title"><?php echo htmlspecialchars($entry['title']); ?></div>
            
            <div class="entry-meta-grid">
                <div class="meta-item">
                    <span class="meta-label">Submitted by</span>
                    <span class="meta-value"><?php echo htmlspecialchars($entry['username']); ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Team</span>
                    <span class="team-badge-large"><?php echo htmlspecialchars($entry['team_name']); ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Date Completed</span>
                    <span class="meta-value"><?php echo date('M d, Y', strtotime($entry['date_completed'])); ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Hours</span>
                    <span class="meta-value" style="color: #667eea;"><?php echo $entry['hours']; ?> hours</span>
                </div>
                <?php if ($entry['points'] && $entry['points'] > 0): ?>
                <div class="meta-item">
                    <span class="meta-label">Points</span>
                    <span class="meta-value" style="color: #667eea;"><?php echo number_format($entry['points'], 2); ?> pts</span>
                </div>
                <?php endif; ?>
                <div class="meta-item">
                    <span class="meta-label">Category</span>
                    <span class="meta-value"><?php echo htmlspecialchars($entry['category']); ?></span>
                </div>
            </div>
        </div>
        
        <div class="entry-body">
            <?php if ($entry['description']): ?>
                <div class="section-title">📝 Description</div>
                <div class="entry-description">
                    <?php echo nl2br(htmlspecialchars($entry['description'])); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($entry['documents'])): ?>
                <div class="section-title">📎 Supporting Documents (<?php echo count($entry['documents']); ?>)</div>
                <div class="documents-grid">
                    <?php foreach ($entry['documents'] as $doc): ?>
                        <div class="document-card">
                            <div class="document-icon">📄</div>
                            <div class="document-info">
                                <div class="document-name"><?php echo htmlspecialchars($doc['original_filename']); ?></div>
                                <div class="document-size"><?php echo number_format($doc['file_size'] / 1024, 1); ?> KB</div>
                            </div>
                            <a href="download.php?file=<?php echo urlencode($doc['filename']); ?>" target="_blank" class="document-link">
                                View
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Review Form -->
    <div class="review-form-card">
        <div class="section-title">✏️ Your Review</div>
        
        <form method="POST">
            <input type="hidden" name="entry_id" value="<?php echo $entry['id']; ?>">
            
            <div class="review-decision">
                <div class="decision-option approve">
                    <input type="radio" name="review_status" value="approved" id="approve" required>
                    <label for="approve" class="decision-label">
                        <span class="decision-icon">✅</span>
                        <span class="decision-text">Approve</span>
                    </label>
                </div>
                
                <div class="decision-option">
                    <input type="radio" name="review_status" value="pending" id="pending">
                    <label for="pending" class="decision-label">
                        <span class="decision-icon">⏳</span>
                        <span class="decision-text">Keep Pending</span>
                    </label>
                </div>
            </div>
            
            <div class="form-group">
                <label>Comments (Optional)</label>
                <textarea name="review_comments" rows="4" placeholder="Add any feedback for <?php echo htmlspecialchars($entry['username']); ?>..."></textarea>
            </div>
            
            <div class="form-actions-sticky">
                <a href="manager_dashboard.php" class="btn btn-skip btn-large">Skip for Now</a>
                <button type="submit" name="submit_review" class="btn btn-approve btn-large">
                    Submit Review <?php echo $remaining_count > 1 ? '& Next' : ''; ?> →
                </button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>