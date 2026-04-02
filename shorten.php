<?php
require_once 'includes/db.php';
// Check if config exists, if not we will define BASE_URL dynamically below
if (file_exists('includes/config.php')) {
    require_once 'includes/config.php';
}
// Require the new Gemini AI functions
if (file_exists('includes/gemini_ai.php')) {
    require_once 'includes/gemini_ai.php';
}

header('Content-Type: application/json');
header("Cache-Control: no-cache, no-store, must-revalidate");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $long_url = $_POST['long_url'] ?? '';
    $custom_alias = trim($_POST['custom_alias'] ?? '');
    $expiry = $_POST['expiry'] ?? null;
    $user_ip = $_SERVER['REMOTE_ADDR'];

    // --- COOKIE IDENTIFICATION ---
    if (isset($_COOKIE['swiftly_uid'])) {
        $creator_id = $_COOKIE['swiftly_uid'];
    } else {
        $creator_id = bin2hex(random_bytes(16)); // Generate unique 32-char ID
        setcookie('swiftly_uid', $creator_id, time() + (86400 * 365), "/"); // Valid for 1 year
    }

    // --- ABUSE PREVENTION: RATE LIMITING ---
    try {
        $rate_limit_period = 60; // seconds (1 minute)
        $rate_limit_count = 10; // requests per period

        // Clean up old records to keep the table small
        $pdo->prepare("DELETE FROM rate_limits WHERE created_at < NOW() - INTERVAL ? SECOND")->execute([$rate_limit_period]);

        // Check current request count for this user
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM rate_limits WHERE identifier = ?");
        $stmt->execute([$creator_id]);
        if ($stmt->fetchColumn() >= $rate_limit_count) {
            http_response_code(429); // Too Many Requests
            echo json_encode(['status' => 'error', 'message' => 'Rate limit exceeded. Please try again in a minute.']);
            exit;
        }
    } catch (PDOException $e) {
        // If rate limiting fails, we log the error and allow the request to proceed ("fail open").
        error_log("Rate limiting check failed: " . $e->getMessage());
    }

    // 1. Basic Validation
    if (!filter_var($long_url, FILTER_VALIDATE_URL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid URL provided.']);
        exit;
    }

    // --- GET LINK INTELLIGENCE ---
    $link_intelligence = fetchLinkIntelligence($long_url);
    $page_title = $link_intelligence['page_title'];
    $summary = $link_intelligence['summary'];
    $category = $link_intelligence['category'];
    $brand_color = $link_intelligence['brand_color'];

    // --- ABUSE PREVENTION: BLACKLIST FILTERING ---
    $blacklist_file = 'includes/blacklist.txt';
    if (file_exists($blacklist_file)) {
        $blacklisted_domains = file($blacklist_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $domain_to_check = parse_url($long_url, PHP_URL_HOST);
        foreach ($blacklisted_domains as $blocked_domain) {
            if (str_ends_with(strtolower($domain_to_check), strtolower(trim($blocked_domain)))) {
                echo json_encode(['status' => 'error', 'message' => 'The destination URL is not allowed.']);
                exit;
            }
        }
    }

    // 2. Handle the Short Code (B1 & B2)
    if (!empty($custom_alias)) {
        // Check if custom alias is taken
        // Check both shards for uniqueness
        foreach (['links_shard_1', 'links_shard_2'] as $shardTable) {
            $stmt = $pdo->prepare("SELECT id FROM $shardTable WHERE short_code = ?");
            $stmt->execute([$custom_alias]);
            if ($stmt->fetch()) {
                echo json_encode(['status' => 'error', 'message' => 'That alias is already taken!']);
                exit;
            }
        }
        $short_code = $custom_alias;
    } else {
        // Generate random 6-char code (B1)
        $chars = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
        do {
            $short_code = substr(str_shuffle($chars), 0, 6);
            $exists = false;
            foreach (['links_shard_1', 'links_shard_2'] as $shardTable) {
                $stmt = $pdo->prepare("SELECT id FROM $shardTable WHERE short_code = ?");
                $stmt->execute([$short_code]);
                if ($stmt->fetch()) { $exists = true; break; }
            }
        } while ($exists);
    }

    // 3. Insert into Database
    try {
        // Determine Shard using Hash strategy (Deterministic Round Robin)
        // We use CRC32 to map the short code to a shard (1 or 2)
        $shardId = (abs(crc32($short_code)) % 2) + 1;
        $targetTable = "links_shard_" . $shardId;
        
        $stmt = $pdo->prepare("INSERT INTO $targetTable (long_url, short_code, expires_at, user_ip, creator_id, page_title, summary, category, brand_color) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$long_url, $short_code, $expiry ?: null, $user_ip, $creator_id, $page_title, $summary, $category, $brand_color]);

        // Log the successful request for rate limiting
        $pdo->prepare("INSERT INTO rate_limits (identifier) VALUES (?)")->execute([$creator_id]);

        // --- DYNAMIC URL DETECTION ---
        // This detects if you are using http or https, grabs your host (localhost or domain),
        // and identifies the subfolder automatically.
        if (!defined('BASE_URL')) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
            $host = $_SERVER['HTTP_HOST'];
            $currentDir = str_replace(basename($_SERVER['PHP_SELF']), '', $_SERVER['PHP_SELF']);
            $dynamic_base = $protocol . $host . $currentDir;
        } else {
            $dynamic_base = BASE_URL;
        }

        echo json_encode([
            'status' => 'success',
            'short_url' => $dynamic_base . $short_code
        ]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}