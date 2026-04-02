<?php
require_once 'includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $link_id = $data['id'] ?? null;
    $new_url = trim($data['long_url'] ?? '');
    $creator_id = $_COOKIE['swiftly_uid'] ?? '';

    if (!$link_id || !$new_url || !$creator_id) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
        exit;
    }

    if (!filter_var($new_url, FILTER_VALIDATE_URL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid URL format.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE links SET long_url = ? WHERE id = ? AND creator_id = ?");
        $stmt->execute([$new_url, $link_id, $creator_id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['status' => 'success']);
        } else {
            // Check if link exists to distinguish between "no change" and "not found"
            $check = $pdo->prepare("SELECT id FROM links WHERE id = ? AND creator_id = ?");
            $check->execute([$link_id, $creator_id]);
            if ($check->fetch()) {
                echo json_encode(['status' => 'success', 'message' => 'No changes made.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Link not found or unauthorized.']);
            }
        }
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    }
}