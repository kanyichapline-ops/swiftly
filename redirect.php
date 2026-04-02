<?php
require_once 'includes/db.php';

if (isset($_GET['code'])) {
    $code = $_GET['code'];

    // 1. Find the link in the database
    // Determine which shard this code belongs to
    $shardId = (abs(crc32($code)) % 2) + 1;
    $targetTable = "links_shard_" . $shardId;

    $stmt = $pdo->prepare("SELECT * FROM $targetTable WHERE short_code = ? LIMIT 1");
    $stmt->execute([$code]);
    $link = $stmt->fetch();

    if ($link) {
        // 2. Check Expiration (B2 Feature)
        if ($link['expires_at'] && strtotime($link['expires_at']) < time()) {
            die("<h1>Link Expired</h1><p>This Swiftly link is no longer active.</p>");
        }

        // 3. Log the Click (B3 Analytics)
        $update = $pdo->prepare("UPDATE $targetTable SET clicks = clicks + 1 WHERE id = ?");
        $update->execute([$link['id']]);

        // 3.5 Log detailed analytics for the chart
        try {
            $log = $pdo->prepare("INSERT INTO analytics (link_id) VALUES (?)");
            $log->execute([$link['id']]);
        } catch (PDOException $e) {
            // Ignore analytics errors so the redirect still happens
        }

        // 4. Redirect to the long URL
        header("Location: " . $link['long_url']);
        exit;
    } else {
        // Code not found
        header("Location: index.php?error=notfound");
        exit;
    }
} else {
    // No code provided, send back to home
    header("Location: index.php");
    exit;
}