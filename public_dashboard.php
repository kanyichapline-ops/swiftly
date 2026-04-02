<?php
require_once 'includes/db.php';
if (file_exists('includes/config.php')) {
    require_once 'includes/config.php';
}

$creator_id = $_GET['user'] ?? '';

if (!$creator_id) {
    header("Location: index.php?error=nouser");
    exit;
}

// --- Data Fetching ---
$shards = ['links_shard_1', 'links_shard_2'];
$links = [];
$totalLinks = 0;
$totalClicks = 0;

foreach ($shards as $shard) {
    // Get totals
    $countStmt = $pdo->prepare("SELECT COUNT(*) as total_links, SUM(clicks) as total_clicks FROM $shard WHERE creator_id = ?");
    $countStmt->execute([$creator_id]);
    $stats = $countStmt->fetch();
    $totalLinks += ($stats['total_links'] ?? 0);
    $totalClicks += ($stats['total_clicks'] ?? 0);

    // Get links
    $stmt = $pdo->prepare("SELECT * FROM $shard WHERE creator_id = ?");
    $stmt->execute([$creator_id]);
    $links = array_merge($links, $stmt->fetchAll());
}

// Sort links by created_at DESC for the table
usort($links, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Chart Data: Last 7 Days
$linkIds = array_column($links, 'id');
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
// We reuse the $links array but sort it by clicks first
$sortedByClicks = $links;
usort($sortedByClicks, function($a, $b) {
    return $b['clicks'] <=> $a['clicks'];
});
$topLinks = array_slice($sortedByClicks, 0, 5);

$pieLabels = [];
$pieData = [];
foreach ($topLinks as $l) {
    $pieLabels[] = '/' . $l['short_code'];
    $pieData[] = $l['clicks'];
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Analytics | Swiftly</title>
    <link rel="icon" href="https://fav.farm/📊">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #020617; overflow-x: hidden; }
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #06b6d4; }
        .glass { background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37); }
        .blob { position: absolute; width: 500px; height: 500px; background: linear-gradient(180deg, rgba(34, 211, 238, 0.15) 0%, rgba(59, 130, 246, 0.1) 100%); filter: blur(80px); border-radius: 50%; z-index: -1; animation: move 20s infinite alternate; }
        @keyframes move { from { transform: translate(-10%, -10%); } to { transform: translate(20%, 20%); } }
        .fade-in-up { animation: fadeInUp 0.6s ease-out forwards; opacity: 0; transform: translateY(20px); }
        @keyframes fadeInUp { to { opacity: 1; transform: translateY(0); } }
        .delay-1 { animation-delay: 0.1s; } .delay-2 { animation-delay: 0.2s; } .delay-3 { animation-delay: 0.3s; }
    </style>
</head>
<body class="p-6 md:p-12 text-slate-200 antialiased">

    <div class="blob top-0 left-0"></div>
    <div class="blob bottom-0 right-0" style="animation-delay: -5s; background: linear-gradient(180deg, rgba(139, 92, 246, 0.1) 0%, rgba(236, 72, 153, 0.05) 100%);"></div>

    <div class="max-w-6xl mx-auto">
        <header class="flex flex-col md:flex-row justify-between items-start md:items-center mb-16 fade-in-up">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="h-2 w-2 bg-cyan-500 rounded-full"></span>
                    <span class="text-xs font-bold uppercase tracking-[0.2em] text-cyan-500/80">Public Analytics</span>
                </div>
                <h1 class="text-5xl font-extrabold text-white tracking-tight">Creator's Dashboard</h1>
                <p class="text-slate-400 mt-2 text-lg">A public, read-only view of this creator's link performance.</p>
            </div>
             <a href="index.php" class="mt-6 md:mt-0 group relative flex items-center gap-2 bg-white text-slate-950 px-8 py-4 rounded-2xl font-bold transition-all hover:scale-105 active:scale-95 shadow-xl shadow-cyan-500/10">
                <i data-lucide="zap" class="w-5 h-5"></i>
                Create your own
            </a>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-16">
            <div class="glass p-8 rounded-[2rem] fade-in-up delay-1"><p class="text-slate-400 text-sm font-medium uppercase tracking-widest">Total Links</p><h2 class="text-5xl font-bold text-white mt-2"><?= number_format($totalLinks) ?></h2></div>
            <div class="glass p-8 rounded-[2rem] fade-in-up delay-2"><p class="text-cyan-400/80 text-sm font-medium uppercase tracking-widest">Total Engagement</p><h2 class="text-5xl font-bold text-white mt-2"><?= number_format($totalClicks) ?></h2></div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-16 fade-in-up delay-2">
            <button onclick="openAnalytics('line')" class="group relative overflow-hidden glass p-8 rounded-[2rem] text-left transition-all hover:scale-[1.02] hover:bg-white/5"><div class="absolute top-0 right-0 p-8 opacity-20 group-hover:opacity-40 transition-opacity group-hover:scale-110 duration-500"><i data-lucide="line-chart" class="w-24 h-24 text-cyan-400"></i></div><div class="relative z-10"><div class="w-12 h-12 bg-cyan-500/20 rounded-2xl flex items-center justify-center mb-4 text-cyan-400 group-hover:bg-cyan-500 group-hover:text-white transition-colors"><i data-lucide="activity" class="w-6 h-6"></i></div><h3 class="text-2xl font-bold text-white mb-2">Trend Analysis</h3><p class="text-slate-400">View click performance over the last 7 days.</p></div></button>
            <button onclick="openAnalytics('doughnut')" class="group relative overflow-hidden glass p-8 rounded-[2rem] text-left transition-all hover:scale-[1.02] hover:bg-white/5"><div class="absolute top-0 right-0 p-8 opacity-20 group-hover:opacity-40 transition-opacity group-hover:scale-110 duration-500"><i data-lucide="pie-chart" class="w-24 h-24 text-purple-400"></i></div><div class="relative z-10"><div class="w-12 h-12 bg-purple-500/20 rounded-2xl flex items-center justify-center mb-4 text-purple-400 group-hover:bg-purple-500 group-hover:text-white transition-colors"><i data-lucide="pie-chart" class="w-6 h-6"></i></div><h3 class="text-2xl font-bold text-white mb-2">Traffic Distribution</h3><p class="text-slate-400">See which links are driving the most traffic.</p></div></button>
        </div>

        <div id="analyticsModal" class="fixed inset-0 z-50 hidden flex items-center justify-center px-4"><div class="absolute inset-0 bg-slate-950/80 backdrop-blur-sm" onclick="closeAnalytics()"></div><div class="relative w-full max-w-4xl bg-[#0f172a] border border-white/10 rounded-[2.5rem] p-8 shadow-2xl opacity-0 scale-90" id="modalContent"><button onclick="closeAnalytics()" class="absolute top-6 right-6 p-2 hover:bg-white/10 rounded-full transition-colors text-slate-400 hover:text-white z-10"><i data-lucide="x" class="w-6 h-6"></i></button><div id="lineChartContainer" class="hidden h-[400px]"><h3 class="text-2xl font-bold text-white mb-6 flex items-center gap-3"><i data-lucide="activity" class="text-cyan-400"></i> Click Trends (Last 7 Days)</h3><canvas id="clicksChart"></canvas></div><div id="doughnutChartContainer" class="hidden h-[400px]"><h3 class="text-2xl font-bold text-white mb-6 flex items-center gap-3"><i data-lucide="pie-chart" class="text-purple-400"></i> Top Performing Links</h3><div class="h-full w-full flex justify-center pb-8"><canvas id="distributionChart"></canvas></div></div></div></div>

        <div class="fade-in-up delay-3">
            <div class="glass rounded-[2.5rem] overflow-hidden">
                <div class="px-8 py-6 border-b border-white/5"><h3 class="font-bold text-xl">All Links</h3></div>
                <?php if (empty($links)): ?>
                <div class="text-center py-24 px-6"><h3 class="text-2xl font-bold text-white mb-2">No links to show.</h3></div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead><tr class="text-slate-500 text-[11px] uppercase tracking-[0.2em] font-black"><th class="px-8 py-6">Short Route</th><th class="px-8 py-6">Destination</th><th class="px-8 py-6 text-center">Engagement</th><th class="px-8 py-6">Timestamp</th></tr></thead>
                        <tbody class="divide-y divide-white/5">
                            <?php foreach ($links as $link): ?>
                            <tr class="hover:bg-white/[0.02] group transition-all">
                                <td class="px-8 py-6"><span class="font-mono text-cyan-400 font-bold bg-cyan-400/5 px-3 py-1.5 rounded-lg border border-cyan-400/10">/<?= htmlspecialchars($link['short_code']) ?></span></td>
                                <td class="px-8 py-6"><p class="text-slate-300 truncate max-w-[240px] font-medium"><?= htmlspecialchars($link['long_url']) ?></p></td>
                                <td class="px-8 py-6 text-center"><div class="inline-flex flex-col items-center"><span class="text-white font-bold text-lg leading-none"><?= $link['clicks'] ?></span><span class="text-[10px] text-slate-500 uppercase mt-1">Clicks</span></div></td>
                                <td class="px-8 py-6"><span class="text-slate-500 text-sm italic"><?= date('M j, Y', strtotime($link['created_at'])) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <footer class="border-t border-white/5 mt-20 pt-10 flex justify-center text-slate-500 text-sm">
            <div>Powered by <a href="index.php" class="font-bold text-slate-400 hover:text-cyan-400">Swiftly</a> &copy; <?= date('Y') ?></div>
        </footer>
    </div>

    <script>
        lucide.createIcons();
        const modal = document.getElementById('analyticsModal');
        const modalContent = document.getElementById('modalContent');
        const lineContainer = document.getElementById('lineChartContainer');
        const doughnutContainer = document.getElementById('doughnutChartContainer');
        function openAnalytics(type) { modal.classList.remove('hidden'); lineContainer.classList.add('hidden'); doughnutContainer.classList.add('hidden'); if (type === 'line') { lineContainer.classList.remove('hidden'); } else { doughnutContainer.classList.remove('hidden'); } gsap.to(modalContent, { opacity: 1, scale: 1, duration: 0.4, ease: "back.out(1.7)" }); }
        function closeAnalytics() { gsap.to(modalContent, { opacity: 0, scale: 0.9, duration: 0.3, ease: "power2.in", onComplete: () => { modal.classList.add('hidden'); } }); }
        
        const ctx = document.getElementById('clicksChart').getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(6, 182, 212, 0.5)');
        gradient.addColorStop(1, 'rgba(6, 182, 212, 0)');
        new Chart(ctx, { type: 'line', data: { labels: <?= json_encode($chartLabels) ?>, datasets: [{ label: 'Clicks', data: <?= json_encode($chartData) ?>, borderColor: '#22d3ee', backgroundColor: gradient, borderWidth: 3, pointBackgroundColor: '#020617', pointBorderColor: '#22d3ee', pointBorderWidth: 2, pointRadius: 4, pointHoverRadius: 6, fill: true, tension: 0.4 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { backgroundColor: 'rgba(15, 23, 42, 0.9)', titleColor: '#fff', bodyColor: '#cbd5e1', padding: 10, cornerRadius: 8, displayColors: false } }, scales: { y: { beginAtZero: true, grid: { color: 'rgba(255, 255, 255, 0.05)' }, ticks: { color: '#64748b' }, border: { display: false } }, x: { grid: { display: false }, ticks: { color: '#64748b' }, border: { display: false } } } } });
        
        const ctxPie = document.getElementById('distributionChart').getContext('2d');
        new Chart(ctxPie, { type: 'doughnut', data: { labels: <?= json_encode($pieLabels) ?>, datasets: [{ data: <?= json_encode($pieData) ?>, backgroundColor: ['rgba(168, 85, 247, 0.8)', 'rgba(6, 182, 212, 0.8)', 'rgba(59, 130, 246, 0.8)', 'rgba(16, 185, 129, 0.8)', 'rgba(244, 63, 94, 0.8)'], borderColor: '#0f172a', borderWidth: 4, hoverOffset: 10 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { color: '#cbd5e1', font: { family: "'Plus Jakarta Sans', sans-serif" } } } } } });
    </script>
</body>
</html>