<?php
require_once 'includes/db.php';
if (file_exists('includes/config.php')) {
    require_once 'includes/config.php';
}

$creator_id = $_COOKIE['swiftly_uid'] ?? '';
$search = $_GET['search'] ?? '';

// Pagination Setup
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

if ($creator_id) {
    // --- B5: Sharding Simulation (Traffic Cop Logic) ---
    $shards = ['links_shard_1', 'links_shard_2'];
    $allLinks = [];
    $totalLinks = 0;
    $totalClicks = 0;

    foreach ($shards as $shardIndex => $shardTable) {
        if ($search) {
            $searchTerm = "%$search%";
            // Get totals per shard
            $countStmt = $pdo->prepare("SELECT COUNT(*) as total_links, SUM(clicks) as total_clicks FROM $shardTable WHERE creator_id = ? AND (long_url LIKE ? OR short_code LIKE ? OR page_title LIKE ? OR category LIKE ?)");
            $countStmt->execute([$creator_id, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            
            // Fetch all matching links from shard (Merging in PHP)
            // Note: Mapping columns (link_title -> page_title, etc.) to match view expectations
            $stmt = $pdo->prepare("SELECT id, long_url, short_code, clicks, created_at, expires_at, page_title, category, brand_color FROM $shardTable WHERE creator_id = ? AND (long_url LIKE ? OR short_code LIKE ? OR page_title LIKE ? OR category LIKE ?)");
            $stmt->execute([$creator_id, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        } else {
            // Get totals per shard
            $countStmt = $pdo->prepare("SELECT COUNT(*) as total_links, SUM(clicks) as total_clicks FROM $shardTable WHERE creator_id = ?");
            $countStmt->execute([$creator_id]);
            
            // Fetch all links from shard
            $stmt = $pdo->prepare("SELECT id, long_url, short_code, clicks, created_at, expires_at, page_title, category, brand_color FROM $shardTable WHERE creator_id = ?");
            $stmt->execute([$creator_id]);
        }

        $shardStats = $countStmt->fetch();
        $totalLinks += ($shardStats['total_links'] ?? 0);
        $totalClicks += ($shardStats['total_clicks'] ?? 0);

        $shardRows = $stmt->fetchAll();
        foreach ($shardRows as &$row) {
            $row['shard_source'] = $shardIndex + 1; // 1 for Shard 1, 2 for Shard 2
        }
        $allLinks = array_merge($allLinks, $shardRows);
    }

    // Sort combined results by clicks DESC
    usort($allLinks, function($a, $b) {
        return $b['clicks'] <=> $a['clicks'];
    });

    $totalPages = ceil($totalLinks / $limit);
    
    // Slice for pagination
    $links = array_slice($allLinks, $offset, $limit);

    // Chart Data: Last 7 Days
    $linkIds = array_column($allLinks, 'id');
    $rawChartData = [];

    try {
        if (!empty($linkIds)) {
            $placeholders = implode(',', array_fill(0, count($linkIds), '?'));
            $chartStmt = $pdo->prepare("
                SELECT DATE(clicked_at) as date, COUNT(*) as clicks 
                FROM analytics 
                WHERE link_id IN ($placeholders)
                AND clicked_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                GROUP BY DATE(clicked_at)
            ");
            $chartStmt->execute($linkIds);
            $rawChartData = $chartStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        }
    } catch (PDOException $e) {
        // Fail silently
    }

    $chartLabels = [];
    $chartData = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $chartLabels[] = date('M j', strtotime($date));
        $chartData[] = $rawChartData[$date] ?? 0;
    }

    // Prepare Data for Circular Graph (Top 5 Links by clicks)
    // We can use the already sorted $allLinks array
    $topLinks = array_slice($allLinks, 0, 5);

    $pieLabels = [];
    $pieData = [];
    foreach ($topLinks as $l) {
        $pieLabels[] = '/' . $l['short_code'];
        $pieData[] = $l['clicks'];
    }
} else {
    $links = [];
    $totalLinks = 0;
    $totalClicks = 0;
    $totalPages = 1;
    $chartLabels = [];
    $chartData = [];
    $pieLabels = [];
    $pieData = [];
}

// Time-based Greeting
$hour = date('H');
if ($hour < 12) $greeting = "Good Morning";
elseif ($hour < 18) $greeting = "Good Afternoon";
else $greeting = "Good Evening";
?>

<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Swiftly | Analytics Dashboard</title>
    <link rel="icon" href="https://fav.farm/⚡">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background-color: #020617; 
            overflow-x: hidden;
        }
        
        /* Modern Thin Scrollbar */
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #06b6d4; }

        /* Ultra Modern Mesh Background */
        .mesh-bg {
            background-image: 
                radial-gradient(at 0% 0%, rgba(56, 189, 248, 0.18) 0, transparent 55%), 
                radial-gradient(at 100% 100%, rgba(139, 92, 246, 0.16) 0, transparent 55%);
            position: relative;
            min-height: 100vh;
        }

        /* Mouse Follower Spotlight */
        #cursor-glow {
            position: fixed;
            top: 0; 
            left: 0;
            width: 600px; 
            height: 600px;
            background: radial-gradient(circle, rgba(34, 211, 238, 0.08) 0%, transparent 70%);
            pointer-events: none;
            transform: translate(-50%, -50%);
            z-index: 0;
        }

        .glass { 
            background: rgba(15, 23, 42, 0.7); 
            backdrop-filter: blur(18px); 
            border: 1px solid rgba(148, 163, 184, 0.22); 
            box-shadow: 0 24px 80px rgba(15, 23, 42, 0.85);
        }

        .fade-in-up {
            animation: fadeInUp 0.6s ease-out forwards;
            opacity: 0;
            transform: translateY(20px);
        }

        @keyframes fadeInUp {
            to { opacity: 1; transform: translateY(0); }
        }

        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
    </style>
</head>
<body class="mesh-bg text-slate-200 antialiased">

    <div id="cursor-glow"></div>

    <nav class="flex justify-between items-center px-6 md:px-12 pt-6 max-w-6xl mx-auto relative z-10">
        <div class="text-2xl font-extrabold tracking-tighter text-white flex items-center gap-2">
            <div class="bg-cyan-500 p-1.5 rounded-lg shadow-[0_0_20px_rgba(6,182,212,0.5)]">
                <i data-lucide="zap" class="w-5 h-5 text-slate-950 fill-current"></i>
            </div>
            Swiftly<span class="text-cyan-400">.</span>
        </div>
        <div class="flex items-center gap-6 text-xs md:text-sm font-semibold uppercase tracking-[0.18em]">
            <a href="index.php" class="hover:text-cyan-400 text-slate-400 transition-colors hidden sm:inline-flex">Shortener</a>
            <span class="px-3 py-1 rounded-full bg-cyan-500/15 text-cyan-300 border border-cyan-400/40 shadow-[0_0_20px_rgba(6,182,212,0.3)]">
                Analytics
            </span>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-6 md:px-12 pt-14 pb-20 relative z-10">
        <header class="flex flex-col md:flex-row justify-between items-start md:items-center mb-16 fade-in-up">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="h-2 w-2 bg-cyan-500 rounded-full animate-pulse"></span>
                    <span class="text-xs font-bold uppercase tracking-[0.2em] text-cyan-500/80">Live Analytics</span>
                </div>
                <h1 class="text-5xl font-extrabold text-white tracking-tight"><?= $greeting ?>, Creator.</h1>
                <p class="text-slate-400 mt-2 text-lg">Here's what's happening with your links today.</p>
            </div>
            <div class="flex items-center gap-4 mt-6 md:mt-0">
                <button onclick="shareDashboard('<?= $creator_id ?>')" class="group relative flex items-center gap-2 bg-white/10 text-white px-8 py-4 rounded-2xl font-bold transition-all hover:scale-105 active:scale-95 hover:bg-white/20" title="Share a public link to your dashboard">
                    <i data-lucide="share-2" class="w-5 h-5"></i>
                    Share
                </button>
                <a href="index.php" class="group relative flex items-center gap-2 bg-white text-slate-950 px-8 py-4 rounded-2xl font-bold transition-all hover:scale-105 active:scale-95 shadow-xl shadow-cyan-500/10">
                    <i data-lucide="plus" class="w-5 h-5"></i>
                    Create New
                </a>
            </div>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-16">
            <div class="glass p-8 rounded-[2rem] fade-in-up delay-1 group hover:border-white/10 transition-colors">
                <div class="flex justify-between items-start mb-4">
                    <div class="p-3 bg-slate-800/50 rounded-2xl text-slate-400 group-hover:text-white transition-colors">
                        <i data-lucide="link"></i>
                    </div>
                </div>
                <p class="text-slate-400 text-sm font-medium uppercase tracking-widest">Total Links</p>
                <h2 class="text-5xl font-bold text-white mt-2"><?= number_format($totalLinks) ?></h2>
            </div>

            <div class="glass p-8 rounded-[2rem] fade-in-up delay-2 border-cyan-500/20 group hover:border-cyan-500/40 transition-colors">
                <div class="flex justify-between items-start mb-4">
                    <div class="p-3 bg-cyan-500/10 rounded-2xl text-cyan-400">
                        <i data-lucide="mouse-pointer-click"></i>
                    </div>
                </div>
                <p class="text-cyan-400/80 text-sm font-medium uppercase tracking-widest">Total Engagement</p>
                <h2 class="text-5xl font-bold text-white mt-2"><?= number_format($totalClicks) ?></h2>
            </div>

            <div class="glass p-8 rounded-[2rem] fade-in-up delay-3 group hover:border-emerald-500/20 transition-colors">
                <div class="flex justify-between items-start mb-4">
                    <div class="p-3 bg-emerald-500/10 rounded-2xl text-emerald-400">
                        <i data-lucide="activity"></i>
                    </div>
                    <span class="flex items-center gap-1.5 bg-emerald-500/10 text-emerald-400 px-3 py-1 rounded-full text-xs font-bold uppercase">
                        Online
                    </span>
                </div>
                <p class="text-slate-400 text-sm font-medium uppercase tracking-widest">Infrastructure</p>
                <h2 class="text-4xl font-bold text-white mt-2">Distributed: 2 Nodes</h2>
            </div>
        </div>

        <!-- Analysis Buttons Section -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-16 fade-in-up delay-2">
            <button onclick="openAnalytics('line')" class="group relative overflow-hidden glass p-8 rounded-[2rem] text-left transition-all hover:scale-[1.02] hover:bg-white/5">
                <div class="absolute top-0 right-0 p-8 opacity-20 group-hover:opacity-40 transition-opacity group-hover:scale-110 duration-500">
                    <i data-lucide="line-chart" class="w-24 h-24 text-cyan-400"></i>
                </div>
                <div class="relative z-10">
                    <div class="w-12 h-12 bg-cyan-500/20 rounded-2xl flex items-center justify-center mb-4 text-cyan-400 group-hover:bg-cyan-500 group-hover:text-white transition-colors">
                        <i data-lucide="activity" class="w-6 h-6"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-white mb-2">Trend Analysis</h3>
                    <p class="text-slate-400">View click performance over the last 7 days.</p>
                </div>
            </button>

            <button onclick="openAnalytics('doughnut')" class="group relative overflow-hidden glass p-8 rounded-[2rem] text-left transition-all hover:scale-[1.02] hover:bg-white/5">
                <div class="absolute top-0 right-0 p-8 opacity-20 group-hover:opacity-40 transition-opacity group-hover:scale-110 duration-500">
                    <i data-lucide="pie-chart" class="w-24 h-24 text-purple-400"></i>
                </div>
                <div class="relative z-10">
                    <div class="w-12 h-12 bg-purple-500/20 rounded-2xl flex items-center justify-center mb-4 text-purple-400 group-hover:bg-purple-500 group-hover:text-white transition-colors">
                        <i data-lucide="pie-chart" class="w-6 h-6"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-white mb-2">Traffic Distribution</h3>
                    <p class="text-slate-400">See which links are driving the most traffic.</p>
                </div>
            </button>
        </div>

        <!-- Analytics Modal -->
        <div id="analyticsModal" class="fixed inset-0 z-50 hidden flex items-center justify-center px-4">
            <div class="absolute inset-0 bg-slate-950/80 backdrop-blur-sm" onclick="closeAnalytics()"></div>
            <div class="relative w-full max-w-4xl bg-[#0f172a] border border-white/10 rounded-[2.5rem] p-8 shadow-2xl opacity-0 scale-90" id="modalContent">
                <button onclick="closeAnalytics()" class="absolute top-6 right-6 p-2 hover:bg-white/10 rounded-full transition-colors text-slate-400 hover:text-white z-10">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
                
                <div id="lineChartContainer" class="hidden h-[400px]">
                    <h3 class="text-2xl font-bold text-white mb-6 flex items-center gap-3">
                        <i data-lucide="activity" class="text-cyan-400"></i> Click Trends (Last 7 Days)
                    </h3>
                    <canvas id="clicksChart"></canvas>
                </div>

                <div id="doughnutChartContainer" class="hidden h-[400px]">
                    <h3 class="text-2xl font-bold text-white mb-6 flex items-center gap-3">
                        <i data-lucide="pie-chart" class="text-purple-400"></i> Top Performing Links
                    </h3>
                    <div class="h-full w-full flex justify-center pb-8">
                        <canvas id="distributionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="fade-in-up delay-3">
            <div class="glass rounded-[2.5rem] overflow-hidden">
                <div class="px-8 py-6 border-b border-white/5 flex flex-col md:flex-row items-center justify-between gap-4">
                    <h3 class="font-bold text-xl">Recent Links</h3>
                    <div class="flex items-center gap-3 w-full md:w-auto">
                        <form method="GET" class="relative flex-1 md:flex-none">
                            <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500"></i>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search links..." 
                                class="bg-white/5 border border-white/10 rounded-lg pl-10 pr-4 py-2 text-sm text-white focus:outline-none focus:border-cyan-500 transition-colors w-full md:w-64">
                        </form>
                        <a href="export_csv.php" class="flex items-center gap-2 px-4 py-2 bg-white/5 hover:bg-white/10 text-slate-300 hover:text-white rounded-lg transition-all text-sm font-semibold whitespace-nowrap">
                            <i data-lucide="download" class="w-4 h-4"></i>
                            Export CSV
                        </a>
                    </div>
                </div>
                <?php if (empty($links)): ?>
                <div class="text-center py-24 px-6">
                    <div class="inline-flex p-6 bg-white/5 rounded-full mb-6 ring-1 ring-white/10">
                        <i data-lucide="<?= $search ? 'search-x' : 'ghost' ?>" class="w-12 h-12 text-slate-600"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-white mb-2"><?= $search ? 'No results found' : "It's quiet in here..." ?></h3>
                    <p class="text-slate-400 mb-8 max-w-md mx-auto">
                        <?= $search ? "We couldn't find any links matching '" . htmlspecialchars($search) . "'." : "You haven't created any links yet. Once you do, their performance metrics will appear right here." ?>
                    </p>
                    <?php if (!$search): ?>
                    <a href="index.php" class="inline-flex items-center gap-2 bg-cyan-500 text-slate-950 px-8 py-4 rounded-2xl font-bold hover:bg-cyan-400 transition-all hover:scale-105 shadow-lg shadow-cyan-500/20">
                        <i data-lucide="zap" class="w-5 h-5"></i>
                        Create First Link
                    </a>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-slate-400 text-xs font-semibold uppercase tracking-wider">
                                <th class="px-8 py-6">Short Route</th>
                                <th class="px-8 py-6">Destination</th>
                                <th class="px-8 py-6 text-center">Engagement</th>
                                <th class="px-8 py-6">Timestamp</th>
                                <th class="px-8 py-6">Status</th>
                                <th class="px-8 py-6 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($links as $link): ?>
                            <tr class="border-b border-white/5 hover:bg-white/[0.02] group transition-all">
                                <td class="px-8 py-6">
                                    <a href="<?= (defined('BASE_URL') ? BASE_URL : '/') . $link['short_code'] ?>" target="_blank" class="font-mono text-cyan-400 font-bold bg-cyan-400/5 px-3 py-1.5 rounded-lg border border-cyan-400/10 hover:border-cyan-400/50 transition-colors">
                                        /<?= $link['short_code'] ?>
                                    </a>
                                </td>
                                <td class="px-8 py-6 max-w-[300px]">
                                    <div class="flex items-center gap-4">
                                        <div class="w-1.5 h-12 rounded-full" style="background-color: <?= htmlspecialchars($link['brand_color'] ?? '#334155') ?>;"></div>
                                        <div class="flex flex-col gap-1 overflow-hidden">
                                            <?php
                                                $url_parts = parse_url($link['long_url']);
                                                $domain = $url_parts['host'] ?? 'Invalid URL';
                                                $display_title = !empty($link['page_title']) ? $link['page_title'] : $domain;
                                            ?>
                                            <span class="text-white font-bold truncate" title="<?= htmlspecialchars($display_title) ?>">
                                                <?= htmlspecialchars($display_title) ?>
                                            </span>
                                            <span class="text-slate-500 text-sm truncate" title="<?= htmlspecialchars($link['long_url']) ?>">
                                                <?= htmlspecialchars($link['long_url']) ?>
                                            </span>
                                            <?php if (!empty($link['category'])): ?>
                                            <div class="mt-1">
                                                <span class="bg-white/5 text-cyan-400 text-xs font-semibold px-2.5 py-1 rounded-full">
                                                    <?= htmlspecialchars($link['category']) ?>
                                                </span>
                                                <!-- Shard Badge -->
                                                <?php if (isset($link['shard_source'])): ?>
                                                    <?php if ($link['shard_source'] == 1): ?>
                                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-mono border border-white/10 text-cyan-400 ml-2">Node-Alpha</span>
                                                    <?php elseif ($link['shard_source'] == 2): ?>
                                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-mono border border-white/10 text-purple-400 ml-2">Node-Beta</span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-8 py-6 text-center">
                                    <div class="inline-flex flex-col items-center">
                                        <span class="text-white font-bold text-lg leading-none"><?= $link['clicks'] ?></span>
                                        <span class="text-[10px] text-slate-500 uppercase mt-1">Clicks</span>
                                    </div>
                                </td>
                                <td class="px-8 py-6">
                                    <span class="text-slate-400 text-sm">
                                        <?= date('M j, Y', strtotime($link['created_at'])) ?>
                                    </span>
                                </td>
                                <td class="px-8 py-6">
                                    <?php if ($link['expires_at']):
                                        $expiry_time = strtotime($link['expires_at']);
                                        $is_expired = $expiry_time < time();
                                    ?>
                                        <?php if ($is_expired): ?>
                                            <span class="inline-flex items-center gap-2 text-xs font-semibold text-red-400 bg-red-500/10 px-3 py-1.5 rounded-full">
                                                <i data-lucide="alert-triangle" class="w-3 h-3"></i> Expired
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-2 text-xs font-semibold text-amber-400 bg-amber-500/10 px-3 py-1.5 rounded-full">
                                                <i data-lucide="clock" class="w-3 h-3"></i> <?= date('M j, Y', $expiry_time) ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-slate-500 text-sm italic">Never</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-8 py-6 text-right">
                                    <?php 
                                        $base = defined('BASE_URL') ? BASE_URL : 'http://' . $_SERVER['HTTP_HOST'] . '/swiftly/';
                                        $full_short_url = $base . $link['short_code'];
                                    ?>
                                    <div class="flex justify-end gap-2">
                                        <button onclick="copyLink('<?= $full_short_url ?>', this)" class="p-2 bg-slate-800/50 text-slate-400 rounded-lg hover:bg-cyan-500 hover:text-white transition-all" title="Copy Link"><i data-lucide="copy" class="w-4 h-4"></i></button>
                                        <button onclick="editLink('<?= $link['short_code'] ?>', this)" class="p-2 bg-slate-800/50 text-slate-400 rounded-lg hover:bg-blue-500 hover:text-white transition-all" title="Edit Destination"><i data-lucide="edit-2" class="w-4 h-4"></i></button>
                                        <button onclick="deleteLink('<?= $link['short_code'] ?>', this)" class="p-2 bg-slate-800/50 text-slate-400 rounded-lg hover:bg-red-500 hover:text-white transition-all" title="Delete Link"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($totalPages > 1): ?>
                <div class="px-8 py-6 border-t border-white/5 flex items-center justify-between">
                    <span class="text-slate-400 text-sm font-medium">
                        Page <span class="text-white"><?= $page ?></span> of <span class="text-white"><?= $totalPages ?></span>
                    </span>
                    <div class="flex gap-2">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>" class="px-4 py-2 bg-white/5 hover:bg-white/10 text-white rounded-lg text-sm font-bold transition-colors">Previous</a>
                        <?php endif; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>" class="px-4 py-2 bg-white/5 hover:bg-white/10 text-white rounded-lg text-sm font-bold transition-colors">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <footer class="border-t border-white/5 mt-20 pt-10 flex flex-col md:flex-row justify-between items-center gap-6 text-slate-500 text-sm bg-slate-950/20 backdrop-blur-sm rounded-t-[2rem] px-6 md:px-10">
            <div class="flex items-center gap-2">
                <div class="bg-cyan-500/10 p-1 rounded-md">
                    <i data-lucide="zap" class="w-3 h-3 text-cyan-500 fill-current"></i>
                </div>
                <span class="font-bold text-slate-400 tracking-tight">Swiftly Dashboard</span>
            </div>
            <div>
                &copy; <?= date('Y') ?> Swiftly. All rights reserved.
            </div>
        </footer>
    </main>

    <script>
        lucide.createIcons();

        // Cursor glow interaction
        const cursorGlow = document.getElementById('cursor-glow');
        window.addEventListener('mousemove', (e) => {
            if (!cursorGlow) return;
            cursorGlow.style.left = e.clientX + 'px';
            cursorGlow.style.top = e.clientY + 'px';
        });

        // --- Modal Logic ---
        const modal = document.getElementById('analyticsModal');
        const modalContent = document.getElementById('modalContent');
        const lineContainer = document.getElementById('lineChartContainer');
        const doughnutContainer = document.getElementById('doughnutChartContainer');

        function openAnalytics(type) {
            modal.classList.remove('hidden');
            
            // Reset containers
            lineContainer.classList.add('hidden');
            doughnutContainer.classList.add('hidden');

            if (type === 'line') {
                lineContainer.classList.remove('hidden');
            } else {
                doughnutContainer.classList.remove('hidden');
            }

            // Animate In
            gsap.to(modalContent, { opacity: 1, scale: 1, duration: 0.4, ease: "back.out(1.7)" });
        }

        function closeAnalytics() {
            gsap.to(modalContent, { 
                opacity: 0, 
                scale: 0.9, 
                duration: 0.3, 
                ease: "power2.in",
                onComplete: () => {
                    modal.classList.add('hidden');
                }
            });
        }

        // --- Chart.js Initialization ---
        
        // 1. Line Chart
        const ctx = document.getElementById('clicksChart').getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(6, 182, 212, 0.5)'); // Cyan
        gradient.addColorStop(1, 'rgba(6, 182, 212, 0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [{
                    label: 'Clicks',
                    data: <?= json_encode($chartData) ?>,
                    borderColor: '#22d3ee',
                    backgroundColor: gradient,
                    borderWidth: 3,
                    pointBackgroundColor: '#020617',
                    pointBorderColor: '#22d3ee',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        titleColor: '#fff',
                        bodyColor: '#cbd5e1',
                        padding: 10,
                        cornerRadius: 8,
                        displayColors: false
                    }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(255, 255, 255, 0.05)' }, ticks: { color: '#64748b' }, border: { display: false } },
                    x: { grid: { display: false }, ticks: { color: '#64748b' }, border: { display: false } }
                }
            }
        });

        // 2. Doughnut Chart
        const ctxPie = document.getElementById('distributionChart').getContext('2d');
        new Chart(ctxPie, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($pieLabels) ?>,
                datasets: [{
                    data: <?= json_encode($pieData) ?>,
                    backgroundColor: [
                        'rgba(168, 85, 247, 0.8)', // Purple
                        'rgba(6, 182, 212, 0.8)',  // Cyan
                        'rgba(59, 130, 246, 0.8)', // Blue
                        'rgba(16, 185, 129, 0.8)', // Emerald
                        'rgba(244, 63, 94, 0.8)'   // Rose
                    ],
                    borderColor: '#0f172a',
                    borderWidth: 4,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right', labels: { color: '#cbd5e1', font: { family: "'Plus Jakarta Sans', sans-serif" } } }
                }
            }
        });

        function copyLink(url, btn) {
            navigator.clipboard.writeText(url).then(() => {
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<i data-lucide="check" class="w-4 h-4"></i>';
                lucide.createIcons();
                setTimeout(() => {
                    btn.innerHTML = originalHTML;
                    lucide.createIcons();
                }, 2000);
            });
        }

        function editLink(shortCode, btn) {
            const row = btn.closest('tr');
            const urlCell = row.querySelector('td:nth-child(2) p');
            const currentUrl = urlCell.innerText;
            
            const newUrl = prompt("Enter the new destination URL:", currentUrl);
            
            if (newUrl && newUrl !== currentUrl) {
                fetch('edit_link.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ short_code: shortCode, long_url: newUrl })
                })
                .then(res => res.json())
                .then(data => {
                    if(data.status === 'success') {
                        urlCell.innerText = newUrl;
                        // Flash effect to show update
                        urlCell.classList.add('text-cyan-400');
                        setTimeout(() => urlCell.classList.remove('text-cyan-400'), 1000);
                    } else {
                        alert(data.message || 'Error updating link');
                    }
                })
                .catch(err => console.error(err));
            }
        }

        function deleteLink(shortCode, btn) {
            if(!confirm('Are you sure you want to delete this link? This action cannot be undone.')) return;

            fetch('delete_link.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ short_code: shortCode })
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    const row = btn.closest('tr');
                    row.style.transition = 'all 0.3s ease';
                    row.style.opacity = '0';
                    setTimeout(() => row.remove(), 300);
                } else {
                    alert(data.message || 'Error deleting link');
                }
            })
            .catch(err => console.error(err));
        }

        function shareDashboard(creatorId) {
            const shareUrl = `<?= defined('BASE_URL') ? rtrim(BASE_URL, '/') : '' ?>/public_dashboard.php?user=${creatorId}`;
            const shareData = {
                title: 'Swiftly Analytics',
                text: 'Check out my link statistics on Swiftly!',
                url: shareUrl,
            };

            if (navigator.share) {
                navigator.share(shareData).catch(err => {
                    if (err.name !== 'AbortError') {
                        console.error('Share failed:', err);
                    }
                });
            } else {
                // Fallback to copy to clipboard
                navigator.clipboard.writeText(shareUrl).then(() => {
                    alert('Public dashboard URL copied to clipboard!');
                }).catch(err => {
                    alert('Failed to copy URL. Please copy it manually:\n' + shareUrl);
                });
            }
        }
    </script>
</body>
</html>