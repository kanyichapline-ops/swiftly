<?php
require_once 'includes/db.php';

// Increase time limit for large datasets
set_time_limit(300);

echo "<html><body style='font-family: sans-serif; background: #0f172a; color: #e2e8f0; padding: 2rem;'>";
echo "<h1>⚡ Swiftly Shard Migration</h1>";

try {
    // 1. Create Shard Tables if they don't exist (Cloning structure from 'links')
    $shards = ['links_shard_1', 'links_shard_2'];
    
    foreach ($shards as $shard) {
        // We use CREATE TABLE ... LIKE to copy the exact schema (indexes, etc.)
        $pdo->exec("CREATE TABLE IF NOT EXISTS $shard LIKE links");
        echo "<p>✅ Checked/Created table: <strong>$shard</strong></p>";
    }

    // 2. Fetch all data from the old 'links' table
    $stmt = $pdo->query("SELECT * FROM links");
    $links = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total = count($links);
    echo "<p>Found <strong>$total</strong> links to migrate.</p>";
    echo "<hr style='border-color: #334155; margin: 1rem 0;'>";

    $stats = [1 => 0, 2 => 0];

    // 3. Distribute links
    foreach ($links as $link) {
        // Determine Shard (Same logic as shorten.php)
        $shardId = (abs(crc32($link['short_code'])) % 2) + 1;
        $targetTable = "links_shard_" . $shardId;

        // Prepare Insert
        // We use INSERT IGNORE to prevent duplicates if run multiple times
        $columns = array_keys($link);
        $columnList = implode(", ", $columns);
        $placeholders = implode(", ", array_fill(0, count($columns), "?"));
        
        $sql = "INSERT IGNORE INTO $targetTable ($columnList) VALUES ($placeholders)";
        $insertStmt = $pdo->prepare($sql);
        $insertStmt->execute(array_values($link));

        $stats[$shardId]++;
    }

    echo "<h3>Migration Results:</h3>";
    echo "<ul><li>Shard 1 (Node-Alpha): " . $stats[1] . " links</li><li>Shard 2 (Node-Beta): " . $stats[2] . " links</li></ul>";
    echo "<p style='color: #4ade80; font-weight: bold;'>Migration completed successfully.</p>";

} catch (PDOException $e) {
    echo "<div style='background: #450a0a; color: #fca5a5; padding: 1rem; border-radius: 0.5rem;'><strong>Error:</strong> " . $e->getMessage() . "</div>";
}
echo "</body></html>";
?>