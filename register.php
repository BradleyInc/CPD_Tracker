<?php 
require_once 'includes/database.php';
require_once 'includes/organisation_functions.php';

if (!isset($_SESSION)) {
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Get active organisations for selection
try {
    $stmt = $pdo->query("
        SELECT id, name, subscription_status, subscription_plan, max_users,
               (SELECT COUNT(*) FROM users WHERE organisation_id = organisations.id AND archived = 0) as current_users
        FROM organisations 
        WHERE subscription_status IN ('trial', 'active')
        ORDER BY name
    ");
    $organisations = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching organisations: " . $e->getMessage());
    $organisations = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CPD Tracker - Register</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .org-info {
            background: #f8f9fa;
            padding: 0.75rem;
            border-radius: 4px;
            margin-top: 0.5rem;
            font-size: 0.875rem;
        }
        
        .org-info.warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .org-info.full {
            background: #f8d7da;
            color: #721c24;
        }
        
        .org-select-option {
            padding: 0.5rem;
        }
        
        .org-details {
            display: none;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <h2>Register for CPD Tracker</h2>
        
        <?php
        // Only process when form is submitted
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];
            $organisation_id = intval($_POST['organisation_id']);
            
            // Input validation
            $errors = [];
            
            if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
                $errors[] = "All fields are required";
            }
            
            if ($password !== $confirm_password) {
                $errors[] = "Passwords do not match";
            }
            
            if (strlen($password) < 6) {
                $errors[] = "Password must be at least 6 characters";
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Invalid email format";
            }
            
            if ($organisation_id <= 0) {
                $errors[] = "Please select an organisation";
            }
            
            // Check if organisation exists and is active
            if ($organisation_id > 0) {
                $stmt = $pdo->prepare("
                    SELECT id, subscription_status, max_users,
                           (SELECT COUNT(*) FROM users WHERE organisation_id = ? AND archived = 0) as current_users
                    FROM organisations 
                    WHERE id = ?
                ");
                $stmt->execute([$organisation_id, $organisation_id]);
                $selected_org = $stmt->fetch();
                
                if (!$selected_org) {
                    $errors[] = "Selected organisation does not exist";
                } elseif (!in_array($selected_org['subscription_status'], ['trial', 'active'])) {
                    $errors[] = "Selected organisation is not accepting new users";
                } elseif ($selected_org['current_users'] >= $selected_org['max_users']) {
                    $errors[] = "Selected organisation has reached its user limit. Please contact the organisation administrator.";
                }
            }
            
            // If no validation errors, proceed with registration
            if (empty($errors)) {
                try {
                    // Check if username or email already exists
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                    $stmt->execute([$username, $email]);
                    
                    if ($stmt->fetch()) {
                        echo "<div class='alert alert-error'>Username or email already exists</div>";
                    } else {
                        // Hash password and insert user with organisation_id
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role_id, organisation_id) VALUES (?, ?, ?, 1, ?)");
                        
                        if ($stmt->execute([$username, $email, $password_hash, $organisation_id])) {
                            echo "<div class='alert alert-success'>Registration successful! <a href='login.php'>Login here</a></div>";
                            
                            // Clear form fields
                            $username = $email = '';
                            $organisation_id = 0;
                        } else {
                            echo "<div class='alert alert-error'>Registration failed. Please try again.</div>";
                        }
                    }
                } catch (PDOException $e) {
                    error_log("Registration error: " . $e->getMessage());
                    echo "<div class='alert alert-error'>Database error. Please try again later.</div>";
                }
            } else {
                // Display validation errors
                foreach ($errors as $error) {
                    echo "<div class='alert alert-error'>$error</div>";
                }
            }
        }
        ?>
        
        <form method="POST" action="" id="registerForm">
            <div class="form-group">
                <label>Organisation:</label>
                <select name="organisation_id" id="organisationSelect" required onchange="updateOrgInfo()">
                    <option value="">-- Select your organisation --</option>
                    <?php foreach ($organisations as $org): 
                        $usage_percent = $org['max_users'] > 0 ? ($org['current_users'] / $org['max_users']) * 100 : 0;
                        $is_full = $org['current_users'] >= $org['max_users'];
                        $is_near_full = $usage_percent >= 90 && !$is_full;
                    ?>
                        <option value="<?php echo $org['id']; ?>" 
                                <?php echo $is_full ? 'disabled' : ''; ?>
                                data-status="<?php echo $org['subscription_status']; ?>"
                                data-plan="<?php echo $org['subscription_plan']; ?>"
                                data-current="<?php echo $org['current_users']; ?>"
                                data-max="<?php echo $org['max_users']; ?>"
                                <?php if (isset($organisation_id) && $organisation_id == $org['id']): ?>selected<?php endif; ?>>
                            <?php echo htmlspecialchars($org['name']); ?>
                            <?php if ($is_full): ?>(FULL)<?php endif; ?>
                            <?php if ($org['subscription_status'] === 'trial'): ?>(Trial)<?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div id="orgInfo" class="org-info" style="display: none;"></div>
                
                <?php if (empty($organisations)): ?>
                    <p style="color: #721c24; margin-top: 0.5rem;">
                        <strong>No organisations are currently accepting registrations.</strong><br>
                        Please contact your organisation administrator or system administrator.
                    </p>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label>Username:</label>
                <input type="text" name="username" value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label>Password:</label>
                <input type="password" name="password" required minlength="6">
                <small>Must be at least 6 characters</small>
            </div>
            
            <div class="form-group">
                <label>Confirm Password:</label>
                <input type="password" name="confirm_password" required>
            </div>
            
            <button type="submit" name="register" class="btn btn-block" <?php echo empty($organisations) ? 'disabled' : ''; ?>>
                Register
            </button>
        </form>
        
        <p class="text-center">Already have an account? <a href="login.php">Login here</a></p>
    </div>

    <script>
    function updateOrgInfo() {
        const select = document.getElementById('organisationSelect');
        const orgInfo = document.getElementById('orgInfo');
        const option = select.options[select.selectedIndex];
        
        if (option.value === '') {
            orgInfo.style.display = 'none';
            return;
        }
        
        const status = option.dataset.status;
        const plan = option.dataset.plan;
        const current = parseInt(option.dataset.current);
        const max = parseInt(option.dataset.max);
        const usage = max > 0 ? (current / max) * 100 : 0;
        
        let infoHTML = `<strong>Plan:</strong> ${plan.charAt(0).toUpperCase() + plan.slice(1)}`;
        
        if (status === 'trial') {
            infoHTML += ' (Trial)';
        }
        
        infoHTML += `<br><strong>Users:</strong> ${current} / ${max} (${usage.toFixed(0)}% full)`;
        
        orgInfo.innerHTML = infoHTML;
        orgInfo.style.display = 'block';
        
        // Apply styling based on capacity
        orgInfo.className = 'org-info';
        if (current >= max) {
            orgInfo.className += ' full';
        } else if (usage >= 90) {
            orgInfo.className += ' warning';
        }
    }
    
    // Call on page load if organisation is already selected
    document.addEventListener('DOMContentLoaded', function() {
        const select = document.getElementById('organisationSelect');
        if (select.value !== '') {
            updateOrgInfo();
        }
    });
    </script>
</body>
</html>
