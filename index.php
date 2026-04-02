<?php
require_once 'includes/db.php';
if (file_exists('includes/config.php')) {
    require_once 'includes/config.php';
}

$creator_id = $_COOKIE['swiftly_uid'] ?? '';
$recentLinks = [];

if ($creator_id) {
    // Fetch from both shards for Recent Activity
    $recentLinks = [];
    foreach (['links_shard_1', 'links_shard_2'] as $shard) {
        $stmt = $pdo->prepare("SELECT * FROM $shard WHERE creator_id = ? ORDER BY created_at DESC LIMIT 3");
        $stmt->execute([$creator_id]);
        $recentLinks = array_merge($recentLinks, $stmt->fetchAll());
    }
    
    // Sort combined results by created_at DESC
    usort($recentLinks, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    // Take top 3
    $recentLinks = array_slice($recentLinks, 0, 3);
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Swiftly | Premium URL Management</title>
    <link rel="icon" href="https://fav.farm/⚡">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #020617; overflow-x: hidden; }
        
        /* Modern Thin Scrollbar */
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #06b6d4; }

        /* Ultra Modern Mesh Background */
        .mesh-bg {
            background-image: 
                radial-gradient(at 0% 0%, rgba(56, 189, 248, 0.15) 0, transparent 50%), 
                radial-gradient(at 100% 100%, rgba(139, 92, 246, 0.15) 0, transparent 50%);
            position: relative;
        }

        /* Mouse Follower Spotlight */
        #cursor-glow {
            position: fixed;
            top: 0; left: 0;
            width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(34, 211, 238, 0.08) 0%, transparent 70%);
            pointer-events: none;
            transform: translate(-50%, -50%);
            z-index: 0;
        }

        .glass { 
            background: rgba(15, 23, 42, 0.6); 
            backdrop-filter: blur(16px); 
            border: 1px solid rgba(255, 255, 255, 0.08); 
            z-index: 10;
            position: relative;
        }

        /* Floating Animation */
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        .float-anim { animation: float 6s ease-in-out infinite; }
        .float-anim-delayed { animation: float 8s ease-in-out infinite 1s; }

        input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(1);
            cursor: pointer;
        }
    </style>
</head>
<body class="mesh-bg text-slate-200 min-h-screen">
    <div id="cursor-glow"></div>

    <nav class="nav-el flex justify-between items-center px-8 py-6 max-w-7xl mx-auto relative z-10">
        <div class="text-2xl font-extrabold tracking-tighter text-white flex items-center gap-2">
            <div class="bg-cyan-500 p-1.5 rounded-lg shadow-[0_0_20px_rgba(6,182,212,0.5)]">
                <i data-lucide="zap" class="w-5 h-5 text-slate-950 fill-current"></i>
            </div>
            Swiftly<span class="text-cyan-400">.</span>
        </div>
        <div class="flex items-center gap-6 text-sm font-semibold">
            <a href="dashboard.php" class="hover:text-cyan-400 transition-colors uppercase tracking-widest text-[10px]">Analytics</a>
            <a href="https://github.com/CHAPLINE055/Swiftly-URL-Shortener/" target="_blank" rel="noopener noreferrer" class="bg-white text-slate-950 px-6 py-2.5 rounded-full hover:bg-cyan-400 transition-all active:scale-95">
                GitHub
            </a>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-6 pt-20 pb-32 text-center relative z-10">
        <div class="inline-block px-4 py-1.5 mb-6 border border-cyan-500/30 rounded-full bg-cyan-500/5 text-cyan-400 text-xs font-bold uppercase tracking-[0.2em] hero-el">
            V2.0 is now live
        </div>
        
        <h1 class="hero-el text-6xl md:text-8xl font-extrabold text-white tracking-tight mb-8">
            Shorten links. <br>
            <span class="bg-gradient-to-r from-cyan-400 via-blue-500 to-indigo-600 bg-clip-text text-transparent">Track everything.</span>
        </h1>
        
        <p class="hero-el text-slate-400 text-lg max-w-2xl mx-auto mb-16 leading-relaxed">
            Swiftly is the professional URL shortener designed for creators and developers. 
            Get custom aliases, set expiration dates, and monitor real-time analytics.
        </p>

        <div class="widget-el glass max-w-3xl mx-auto p-2 rounded-[40px] shadow-2xl mb-20">
            <form id="shortenForm">
                <div class="flex flex-col md:flex-row gap-2">
                    <input type="url" name="long_url" required placeholder="Paste a long URL here..." 
                        class="flex-1 bg-transparent border-none px-8 py-5 text-white focus:outline-none text-xl placeholder:text-slate-600">
                    <button type="submit" id="submitBtn" class="bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-black px-10 py-5 rounded-[32px] transition-all flex items-center justify-center gap-2 group shadow-xl shadow-cyan-500/20 active:scale-95">
                        <span>Shorten</span>
                        <i data-lucide="arrow-right" class="w-5 h-5 group-hover:translate-x-1 transition-transform"></i>
                    </button>
                </div>
                
                <div class="px-8 py-5 border-t border-white/5 flex flex-wrap gap-8 text-sm">
                    <div class="flex items-center gap-3 text-slate-400 focus-within:text-cyan-400 transition-colors">
                        <i data-lucide="at-sign" class="w-4 h-4"></i>
                        <input type="text" name="custom_alias" autocomplete="off" placeholder="Custom Alias" class="bg-transparent border-none focus:outline-none w-32 text-slate-200">
                    </div>
                    <div class="flex items-center gap-3 text-slate-400 focus-within:text-cyan-400 transition-colors">
                        <i data-lucide="calendar" class="w-4 h-4"></i>
                        <input type="date" name="expiry" class="bg-transparent border-none focus:outline-none text-slate-400 cursor-pointer">
                    </div>
                </div>
            </form>
        </div>

        <div id="result" class="hidden max-w-2xl mx-auto p-6 rounded-3xl bg-cyan-500/10 border border-cyan-500/30 flex flex-col md:flex-row items-center gap-6 shadow-[0_0_50px_rgba(6,182,212,0.15)] mb-20">
            <div class="bg-white p-2 rounded-xl shrink-0 relative group">
                <img id="qrCodeImage" src="" alt="QR Code" class="w-24 h-24">
                <div class="absolute inset-0 bg-black/50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300 rounded-xl">
                    <button onclick="shareQrCode()" class="p-3 bg-white/20 backdrop-blur-sm rounded-full text-white hover:bg-white/30" title="Share QR Code">
                        <i data-lucide="share-2" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>
            <div class="text-left flex-1 min-w-0">
                <p class="text-[10px] uppercase tracking-[0.3em] text-cyan-500 font-black mb-2">Ready to share</p>
                <a id="shortenedUrl" href="#" target="_blank" class="text-2xl md:text-3xl text-white font-bold tracking-tight hover:text-cyan-400 transition-colors break-all"></a>
            </div>
            <button onclick="copyToClipboard()" class="bg-white/10 hover:bg-white text-slate-950 p-4 rounded-2xl transition-all group shadow-lg shrink-0">
                <i data-lucide="copy" class="w-6 h-6 group-active:scale-90 transition-transform"></i>
            </button>
        </div>

        <?php if (!empty($recentLinks)): ?>
        <div class="max-w-3xl mx-auto mb-20 fade-in-up delay-1 text-left">
            <h3 class="text-xl font-bold text-white mb-6 flex items-center gap-2 pl-2">
                <i data-lucide="history" class="w-5 h-5 text-cyan-400"></i> Recent Activity
            </h3>
            <div class="space-y-3">
                <?php foreach ($recentLinks as $link): ?>
                    <?php 
                        $base = defined('BASE_URL') ? BASE_URL : 'http://' . $_SERVER['HTTP_HOST'] . '/swiftly/';
                        $shortUrl = $base . $link['short_code'];
                    ?>
                    <div class="glass p-4 rounded-2xl flex items-center justify-between gap-4 group hover:bg-white/5 transition-colors">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-cyan-400 font-mono font-bold text-sm">/<?= htmlspecialchars($link['short_code']) ?></span>
                                <span class="text-slate-500 text-xs">• <?= date('M j', strtotime($link['created_at'])) ?></span>
                            </div>
                            <p class="text-slate-400 text-sm truncate w-full"><?= htmlspecialchars($link['long_url']) ?></p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button onclick="navigator.clipboard.writeText('<?= $shortUrl ?>')" class="p-2 hover:bg-white/10 rounded-lg text-slate-400 hover:text-white transition-colors" title="Copy">
                                <i data-lucide="copy" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-left">
            <div data-aos="fade-up" data-aos-delay="100" class="glass p-10 rounded-[35px] float-anim">
                <div class="w-14 h-14 bg-gradient-to-br from-cyan-400 to-blue-600 text-slate-950 rounded-2xl flex items-center justify-center mb-8 shadow-lg shadow-cyan-500/20">
                    <i data-lucide="bar-chart-3" class="w-7 h-7"></i>
                </div>
                <h3 class="text-2xl font-bold text-white mb-4 tracking-tight">Analytics</h3>
                <p class="text-slate-400 leading-relaxed">Detailed insights into every click. Track devices, locations, and referral paths in real-time.</p>
            </div>
            
            <div data-aos="fade-up" data-aos-delay="200" class="glass p-10 rounded-[35px] float-anim-delayed">
                <div class="w-14 h-14 bg-gradient-to-br from-indigo-400 to-purple-600 text-white rounded-2xl flex items-center justify-center mb-8 shadow-lg shadow-indigo-500/20">
                    <i data-lucide="shield-check" class="w-7 h-7"></i>
                </div>
                <h3 class="text-2xl font-bold text-white mb-4 tracking-tight">Branding</h3>
                <p class="text-slate-400 leading-relaxed">Create trust with custom aliases. Turn messy URLs into clean, branded calls to action.</p>
            </div>

            <div data-aos="fade-up" data-aos-delay="300" class="glass p-10 rounded-[35px] float-anim">
                <div class="w-14 h-14 bg-gradient-to-br from-emerald-400 to-teal-600 text-slate-950 rounded-2xl flex items-center justify-center mb-8 shadow-lg shadow-emerald-500/20">
                    <i data-lucide="clock" class="w-7 h-7"></i>
                </div>
                <h3 class="text-2xl font-bold text-white mb-4 tracking-tight">Auto-Expiry</h3>
                <p class="text-slate-400 leading-relaxed">Secure your campaigns. Links automatically deactivate based on your custom set schedule.</p>
            </div>
        </div>

        <!-- FAQ Section -->
        <div class="mt-32 max-w-6xl mx-auto text-left" data-aos="fade-up">
            <h2 class="text-3xl md:text-4xl font-bold text-white mb-12 text-center">Frequently Asked Questions</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-start">
                <div class="glass p-6 rounded-2xl transition-all hover:bg-white/5 cursor-pointer group" onclick="toggleFaq(this)">
                    <div class="flex justify-between items-center">
                        <h3 class="font-bold text-lg text-slate-200">Is Swiftly free to use?</h3>
                        <i data-lucide="chevron-down" class="w-5 h-5 text-slate-400 transition-transform duration-300 group-hover:text-white"></i>
                    </div>
                    <div class="max-h-0 overflow-hidden transition-all duration-500 ease-in-out opacity-0">
                        <p class="text-slate-400 mt-4 leading-relaxed">Yes! Swiftly is completely free for personal use. You can shorten links, track analytics, and generate QR codes without any cost.</p>
                    </div>
                </div>
                <div class="glass p-6 rounded-2xl transition-all hover:bg-white/5 cursor-pointer group" onclick="toggleFaq(this)">
                    <div class="flex justify-between items-center">
                        <h3 class="font-bold text-lg text-slate-200">Do my links expire?</h3>
                        <i data-lucide="chevron-down" class="w-5 h-5 text-slate-400 transition-transform duration-300 group-hover:text-white"></i>
                    </div>
                    <div class="max-h-0 overflow-hidden transition-all duration-500 ease-in-out opacity-0">
                        <p class="text-slate-400 mt-4 leading-relaxed">Links are permanent by default. However, you can choose to set an expiration date if you want your link to be temporary.</p>
                    </div>
                </div>
                <div class="glass p-6 rounded-2xl transition-all hover:bg-white/5 cursor-pointer group" onclick="toggleFaq(this)">
                    <div class="flex justify-between items-center">
                        <h3 class="font-bold text-lg text-slate-200">Can I customize my short URL?</h3>
                        <i data-lucide="chevron-down" class="w-5 h-5 text-slate-400 transition-transform duration-300 group-hover:text-white"></i>
                    </div>
                    <div class="max-h-0 overflow-hidden transition-all duration-500 ease-in-out opacity-0">
                        <p class="text-slate-400 mt-4 leading-relaxed">Absolutely. You can choose a custom alias (like /my-brand) to make your links memorable and trustworthy.</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="border-t border-white/5 mt-20 relative z-10 bg-slate-950/30 backdrop-blur-sm">
        <div class="max-w-7xl mx-auto px-6 py-12 flex flex-col md:flex-row justify-between items-center gap-6">
            <div class="flex items-center gap-2">
                <div class="bg-cyan-500 p-1 rounded-md">
                    <i data-lucide="zap" class="w-3 h-3 text-slate-950 fill-current"></i>
                </div>
                <span class="font-bold text-white tracking-tight">Swiftly.</span>
            </div>
            <div class="text-slate-500 text-sm">
                &copy; <?= date('Y') ?> Swiftly. All rights reserved.
            </div>
            <div class="flex gap-6 text-sm font-semibold text-slate-400">
                <a href="#" class="hover:text-cyan-400 transition-colors">Privacy</a>
                <a href="#" class="hover:text-cyan-400 transition-colors">Terms</a>
                <a href="#" class="hover:text-cyan-400 transition-colors">Contact</a>
            </div>
        </div>
    </footer>

    <script>
        lucide.createIcons();
        AOS.init({ duration: 1000, once: true });

        // Mouse Spotlight Effect
        const glow = document.getElementById('cursor-glow');
        document.addEventListener('mousemove', (e) => {
            gsap.to(glow, {
                x: e.clientX,
                y: e.clientY,
                duration: 0.8,
                ease: "power2.out"
            });
        });

        // Entrance GSAP Timeline
        const tl = gsap.timeline();
        tl.from(".nav-el", { y: -30, opacity: 0, duration: 1, ease: "expo.out" })
          .from(".hero-el", { y: 50, opacity: 0, stagger: 0.15, duration: 1.2, ease: "expo.out" }, "-=0.6")
          .from(".widget-el", { scale: 0.9, opacity: 0, duration: 1.5, ease: "elastic.out(1, 0.8)" }, "-=0.8");

        // AJAX Handler
        document.getElementById('shortenForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            const formData = new FormData(this);

            btn.innerHTML = `<span class="animate-spin text-xl leading-none">◌</span>`;
            btn.disabled = true;

            fetch('shorten.php?t=' + new Date().getTime(), { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                btn.innerHTML = `<span>Shorten</span> <i data-lucide="arrow-right" class="w-5 h-5"></i>`;
                btn.disabled = false;
                lucide.createIcons();

                if(data.status === 'success') {
                    const resDiv = document.getElementById('result');
                    const urlLink = document.getElementById('shortenedUrl');
                    urlLink.innerText = data.short_url;
                    urlLink.href = data.short_url;
                    
                    // Generate QR Code
                    document.getElementById('qrCodeImage').src = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(data.short_url)}`;
                    
                    resDiv.classList.remove('hidden');
                    gsap.from("#result", { scale: 0.8, opacity: 0, duration: 0.8, ease: "back.out(1.7)" });
                } else {
                    alert(data.message);
                }
            });
        });

        function copyToClipboard() {
            const url = document.getElementById('shortenedUrl').innerText;
            navigator.clipboard.writeText(url);
            gsap.to("#result", { y: -5, yoyo: true, repeat: 1, duration: 0.1 });
        }

        function toggleFaq(el) {
            const content = el.querySelector('.overflow-hidden');
            const icon = el.querySelector('i') || el.querySelector('svg');
            
            if (content.classList.contains('max-h-0')) {
                content.classList.remove('max-h-0', 'opacity-0');
                content.classList.add('max-h-96', 'opacity-100');
                icon.style.transform = 'rotate(180deg)';
            } else {
                content.classList.remove('max-h-96', 'opacity-100');
                content.classList.add('max-h-0', 'opacity-0');
                icon.style.transform = 'rotate(0deg)';
            }
        }

        async function shareQrCode() {
            const qrImg = document.getElementById('qrCodeImage');
            const qrUrl = qrImg.src;

            if (!qrUrl) return;

            try {
                // Fetch the image and convert it to a blob
                const response = await fetch(qrUrl);
                const blob = await response.blob();
                const file = new File([blob], 'swiftly-qr.png', { type: 'image/png' });
                const filesArray = [file];

                // Check if the browser supports sharing files
                if (navigator.canShare && navigator.canShare({ files: filesArray })) {
                    await navigator.share({
                        files: filesArray,
                        title: 'My Swiftly QR Code',
                        text: 'Scan this QR code to visit my link!',
                    });
                } else {
                    // Fallback: download the image
                    const link = document.createElement('a');
                    const url = URL.createObjectURL(blob);
                    link.href = url;
                    link.download = 'swiftly-qr.png';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(url);
                }
            } catch (error) {
                console.error('Error sharing QR code:', error);
                alert('Could not share or download the QR code.');
            }
        }
    </script>
</body>
</html>