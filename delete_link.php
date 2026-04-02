<?php
require_once 'includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $creator_id = $_COOKIE['swiftly_uid'] ?? '';

    if (!$short_code || !$creator_id) {
        ecit;
    }

    try {
        // Determine Shard
        $ect shard
        $stmt = $pdo->prepare("DELETE FROM $targetTable WHERE short_code = ? AND creator_id = ?");
        $stmt->execute([$short_code, $creator_id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Link not found or unauthorized.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    }
}