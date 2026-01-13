<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'vendor/autoload.php';

use setasign\Fpdi\Tcpdf\Fpdi;

// Check authentication
checkAuth();

// Enable error logging
ini_set('display_errors', 0); // Don't display errors to user
error_reporting(E_ALL);

// Define tool paths
define('QPDF_PATH', 'qpdf');
define('PDFTK_PATH', 'pdftk');

// Validate and sanitize filter parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;
$category = isset($_GET['category']) ? $_GET['category'] : null;

// Validate date formats
if ($start_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
    die("Invalid start date format");
}

if ($end_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
    die("Invalid end date format");
}

// Validate category
$allowed_categories = ['Training', 'Conference', 'Reading', 'Online Course', 'Other', 'all'];
if ($category && !in_array($category, $allowed_categories)) {
    die("Invalid category");
}

// Build query with filters
$query = "SELECT * FROM cpd_entries WHERE user_id = ?";
$params = [$_SESSION['user_id']];

if ($start_date) {
    $query .= " AND date_completed >= ?";
    $params[] = $start_date;
}

if ($end_date) {
    $query .= " AND date_completed <= ?";
    $params[] = $end_date;
}

if ($category && $category !== 'all') {
    $query .= " AND category = ?";
    $params[] = $category;
}

$query .= " ORDER BY date_completed ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$entries = $stmt->fetchAll();

// Get user information
$user_stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
$user_stmt->execute([$_SESSION['user_id']]);
$user_info = $user_stmt->fetch();

// Calculate total hours and points
$total_hours = 0;
$total_points = 0;
foreach ($entries as $entry) {
    $total_hours += $entry['hours'];
    if ($entry['points']) {
        $total_points += $entry['points'];
    }
}

// Check tool availability
$qpdf_available = false;
$pdftk_available = false;

exec('"' . QPDF_PATH . '" --version 2>&1', $qpdf_test, $qpdf_test_return);
$qpdf_available = ($qpdf_test_return === 0);

exec('"' . PDFTK_PATH . '" --version 2>&1', $pdftk_test, $pdftk_test_return);
$pdftk_available = ($pdftk_test_return === 0);

// Create PDF
$pdf = new Fpdi();
$pdf->SetCreator('CPD Tracker');
$pdf->SetAuthor($user_info['username']);
$pdf->SetTitle('Continuing Professional Development Log');
$pdf->SetSubject('CPD Log Export');

// Add first page with header and table
$pdf->AddPage('L', 'A4'); // Landscape for wider table
$pdf->SetFont('helvetica', 'B', 16);

// Header
$pdf->Cell(0, 10, 'Continuing Professional Development Log', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, 'User: ' . htmlspecialchars($user_info['username']), 0, 1);

// Date range
$date_range_text = 'Period: ';
if ($start_date && $end_date) {
    $date_range_text .= date('d/m/Y', strtotime($start_date)) . ' to ' . date('d/m/Y', strtotime($end_date));
} elseif ($start_date) {
    $date_range_text .= 'From ' . date('d/m/Y', strtotime($start_date));
} elseif ($end_date) {
    $date_range_text .= 'Until ' . date('d/m/Y', strtotime($end_date));
} else {
    $date_range_text .= 'All entries';
}
$pdf->Cell(0, 6, $date_range_text, 0, 1);

if ($category && $category !== 'all') {
    $pdf->Cell(0, 6, 'Category: ' . htmlspecialchars($category), 0, 1);
}

$pdf->Cell(0, 6, 'Generated: ' . date('d/m/Y H:i'), 0, 1);
$pdf->Ln(5);

// Table header
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(230, 230, 230);

// Column widths (total = 277mm for A4 landscape minus margins)
$col_widths = [
    'date' => 25,
    'category' => 30,
    'title' => 50,
    'description' => 70,
    'hours' => 20,
    'points' => 20,
    'documents' => 42
];

$pdf->Cell($col_widths['date'], 8, 'Date', 1, 0, 'C', true);
$pdf->Cell($col_widths['category'], 8, 'Category', 1, 0, 'C', true);
$pdf->Cell($col_widths['title'], 8, 'Title', 1, 0, 'C', true);
$pdf->Cell($col_widths['description'], 8, 'Description', 1, 0, 'C', true);
$pdf->Cell($col_widths['hours'], 8, 'Hours', 1, 0, 'C', true);
$pdf->Cell($col_widths['points'], 8, 'Points', 1, 0, 'C', true);
$pdf->Cell($col_widths['documents'], 8, 'Documentation', 1, 1, 'C', true);

// Table content
$pdf->SetFont('helvetica', '', 8);

if (count($entries) > 0) {
    foreach ($entries as $entry) {
        // Get documents for this entry
        $docs = getCPDDocuments($pdo, $entry['id']);
        $doc_text = count($docs) > 0 ? count($docs) . ' document(s)' : 'None';
        
        // Calculate row height based on content
        $date_height = $pdf->getStringHeight($col_widths['date'], date('d/m/Y', strtotime($entry['date_completed'])));
        $category_height = $pdf->getStringHeight($col_widths['category'], $entry['category']);
        $title_height = $pdf->getStringHeight($col_widths['title'], $entry['title']);
        $desc_height = $pdf->getStringHeight($col_widths['description'], $entry['description'] ?: '-');
        $doc_height = $pdf->getStringHeight($col_widths['documents'], $doc_text);
        
        $row_height = max($date_height, $category_height, $title_height, $desc_height, $doc_height, 8);
        
        // Check if we need a new page
        if ($pdf->GetY() + $row_height > 180) { // Leave space for footer
            $pdf->AddPage('L', 'A4');
            
            // Repeat header on new page
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetFillColor(230, 230, 230);
            $pdf->Cell($col_widths['date'], 8, 'Date', 1, 0, 'C', true);
            $pdf->Cell($col_widths['category'], 8, 'Category', 1, 0, 'C', true);
            $pdf->Cell($col_widths['title'], 8, 'Title', 1, 0, 'C', true);
            $pdf->Cell($col_widths['description'], 8, 'Description', 1, 0, 'C', true);
            $pdf->Cell($col_widths['hours'], 8, 'Hours', 1, 0, 'C', true);
            $pdf->Cell($col_widths['points'], 8, 'Points', 1, 0, 'C', true);
            $pdf->Cell($col_widths['documents'], 8, 'Documentation', 1, 1, 'C', true);
            $pdf->SetFont('helvetica', '', 8);
        }
        
        // Store current Y position
        $start_y = $pdf->GetY();
        
        // Date
        $pdf->MultiCell($col_widths['date'], $row_height, date('d/m/Y', strtotime($entry['date_completed'])), 1, 'C', false, 0);
        
        // Category
        $pdf->MultiCell($col_widths['category'], $row_height, $entry['category'], 1, 'L', false, 0);
        
        // Title
        $pdf->MultiCell($col_widths['title'], $row_height, $entry['title'], 1, 'L', false, 0);
        
        // Description
        $pdf->MultiCell($col_widths['description'], $row_height, $entry['description'] ?: '-', 1, 'L', false, 0);
        
        // Hours
        $pdf->MultiCell($col_widths['hours'], $row_height, number_format($entry['hours'], 1), 1, 'C', false, 0);
        
        // Points
        $points_display = ($entry['points'] !== null && $entry['points'] > 0) ? number_format($entry['points'], 2) : '-';
        $pdf->MultiCell($col_widths['points'], $row_height, $points_display, 1, 'C', false, 0);
        
        // Documents
        $pdf->MultiCell($col_widths['documents'], $row_height, $doc_text, 1, 'L', false, 1);
    }
} else {
    $pdf->Cell(array_sum($col_widths), 10, 'No CPD entries found for the selected criteria', 1, 1, 'C');
}

// Summary row
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell($col_widths['date'] + $col_widths['category'] + $col_widths['title'] + $col_widths['description'], 8, 'TOTAL', 1, 0, 'R', true);
$pdf->Cell($col_widths['hours'], 8, number_format($total_hours, 1), 1, 0, 'C', true);
$pdf->Cell($col_widths['points'], 8, number_format($total_points, 2), 1, 0, 'C', true);
$pdf->Cell($col_widths['documents'], 8, '', 1, 1, 'C', true);

// Now append supporting documents
if (count($entries) > 0) {
    foreach ($entries as $entry) {
        $docs = getCPDDocuments($pdo, $entry['id']);
        
        if (count($docs) > 0) {
            // Add separator page before documents for this entry
            $pdf->AddPage();
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->Cell(0, 15, 'Supporting Documentation', 0, 1, 'C');
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 10, 'CPD Entry: ' . htmlspecialchars($entry['title']), 0, 1);
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Cell(0, 8, 'Date: ' . date('d/m/Y', strtotime($entry['date_completed'])), 0, 1);
            $pdf->Cell(0, 8, 'Category: ' . htmlspecialchars($entry['category']), 0, 1);
            $pdf->Ln(5);
            
            foreach ($docs as $doc) {
                $filepath = 'uploads/' . $doc['filename'];
                
                if (!file_exists($filepath)) {
                    error_log("Document not found: $filepath");
                    continue;
                }
                
                $file_ext = strtolower(pathinfo($doc['original_filename'], PATHINFO_EXTENSION));
                
                try {
                    if ($file_ext === 'pdf') {
                        // Process PDF document
                        $temp_file = $filepath;
                        $processed = false;
                        
                        // Try to decompress PDF for better compatibility
                        if ($qpdf_available || $pdftk_available) {
                            $uncompressed = 'uploads/temp_unc_' . uniqid() . '.pdf';
                            
                            if ($qpdf_available) {
                                $qpdf_command = '"' . QPDF_PATH . '" --stream-data=uncompress --object-streams=disable "' . 
                                              $filepath . '" "' . $uncompressed . '" 2>&1';
                                exec($qpdf_command, $qpdf_output, $qpdf_return);
                                
                                if ($qpdf_return === 0 && file_exists($uncompressed) && filesize($uncompressed) > 0) {
                                    $temp_file = $uncompressed;
                                    $processed = true;
                                }
                            }
                            
                            if (!$processed && $pdftk_available) {
                                $pdftk_command = '"' . PDFTK_PATH . '" "' . $filepath . '" output "' . $uncompressed . '" uncompress 2>&1';
                                exec($pdftk_command, $pdftk_output, $pdftk_return);
                                
                                if ($pdftk_return === 0 && file_exists($uncompressed) && filesize($uncompressed) > 0) {
                                    $temp_file = $uncompressed;
                                    $processed = true;
                                }
                            }
                        }
                        
                        // Import PDF pages
                        $pageCount = $pdf->setSourceFile($temp_file);
                        
                        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                            $templateId = $pdf->importPage($pageNo);
                            $size = $pdf->getTemplateSize($templateId);
                            
                            if ($size['width'] > $size['height']) {
                                $pdf->AddPage('L', [$size['width'], $size['height']]);
                            } else {
                                $pdf->AddPage('P', [$size['width'], $size['height']]);
                            }
                            
                            $pdf->useTemplate($templateId, 0, 0, $size['width'], $size['height']);
                        }
                        
                        // Clean up temp file
                        if ($processed && file_exists($uncompressed)) {
                            unlink($uncompressed);
                        }
                        
                    } elseif (in_array($file_ext, ['jpg', 'jpeg', 'png'])) {
                        // Process image document
                        list($width, $height) = getimagesize($filepath);
                        
                        if ($width > $height) {
                            $pdf->AddPage('L', 'A4');
                            $pageWidth = 297;
                            $pageHeight = 210;
                        } else {
                            $pdf->AddPage('P', 'A4');
                            $pageWidth = 210;
                            $pageHeight = 297;
                        }
                        
                        // Calculate scaling
                        $margin = 10;
                        $maxWidth = $pageWidth - (2 * $margin);
                        $maxHeight = $pageHeight - (2 * $margin);
                        
                        $ratio = min($maxWidth / ($width / 3.78), $maxHeight / ($height / 3.78));
                        $imgWidth = ($width / 3.78) * $ratio;
                        $imgHeight = ($height / 3.78) * $ratio;
                        
                        // Center the image
                        $x = ($pageWidth - $imgWidth) / 2;
                        $y = ($pageHeight - $imgHeight) / 2;
                        
                        $pdf->Image($filepath, $x, $y, $imgWidth, $imgHeight, '', '', '', true, 300, '', false, false, 0);
                    }
                    
                } catch (Exception $e) {
                    error_log("Error importing document {$doc['original_filename']}: " . $e->getMessage());
                    
                    // Add error page
                    $pdf->AddPage();
                    $pdf->SetFont('helvetica', 'B', 14);
                    $pdf->SetTextColor(255, 0, 0);
                    $pdf->Cell(0, 10, 'Error Loading Document', 0, 1);
                    $pdf->SetFont('helvetica', '', 11);
                    $pdf->SetTextColor(0, 0, 0);
                    $pdf->Cell(0, 8, 'Filename: ' . htmlspecialchars($doc['original_filename']), 0, 1);
                    $pdf->MultiCell(0, 6, 'Error: ' . $e->getMessage());
                }
            }
        }
    }
}

// Output PDF
$filename = 'CPD_Log_' . date('Y-m-d_His') . '.pdf';
$pdf->Output($filename, 'D'); // 'D' = force download
exit;
?>