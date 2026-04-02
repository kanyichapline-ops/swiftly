<?php
require_once 'includes/db.php';
if (file_exists('includes/config.php')) {
    require_once 'includes/config.php';
}

$creator_id = $_COOKIE['swiftly_uid'] ?? '';

if (!$creator_id) {
    header('Location: index.php');
    exit;
}

// Fetch all links for the user
$stmt = $pdo->prepare("SELECT * FROM links WHERE creator_id = ? ORDER BY created_at DESC");
$stmt->execute([$creator_id]);
$links = $stmt->fetchAll();

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="swiftly_links_' . date('Y-m-d') . '.csv"');

// Open output stream
$output = fopen('php://output', 'w');

// Add CSV headers
fputcsv($output, ['ID', 'Long URL', 'Short Code', 'Full Short URL', 'Clicks', 'Created At', 'Expires At']);

$base = defined('BASE_URL') ? BASE_URL : 'http://' . $_SERVER['HTTP_HOST'] . '/swiftly/';

// Add data rows
foreach ($links as $link) {
    fputcsv($output, [
        $link['id'],
        $link['long_url'],
        $link['short_code'],
        $base . $link['short_code'],
        $link['clicks'],
        $link['created_at'],
        $link['expires_at']
    ]);
}

fclose($output);
exit;