<?php
// Security functions

// Prevent XSS attacks
function sanitize_output($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// Validate integer input
function validate_integer($value, $min = null, $max = null) {
    if (!is_numeric($value) || intval($value) != $value) {
        return false;
    }
    
    $int_value = intval($value);
    
    if ($min !== null && $int_value < $min) {
        return false;
    }
    
    if ($max !== null && $int_value > $max) {
        return false;
    }
    
    return true;
}

// Validate float input
function validate_float($value, $min = null, $max = null) {
    if (!is_numeric($value)) {
        return false;
    }
    
    $float_value = floatval($value);
    
    if ($min !== null && $float_value < $min) {
        return false;
    }
    
    if ($max !== null && $float_value > $max) {
        return false;
    }
    
    return true;
}

// Prevent SQL injection (already done with prepared statements, but extra layer)
function sanitize_sql($pdo, $string) {
    return $pdo->quote($string);
}

// Validate date format
function validate_date($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

// CSRF protection (if you implement forms that need it)
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>