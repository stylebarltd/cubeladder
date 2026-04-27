<?php
/**
 * @var \App\View\AppView $this
 * @var int $total
 * @var int $bots
 * @var int $humans
 * @var int $uniqueIPs
 * @var array $visitsPerDay
 * @var array $topPaths
 * @var array $methods
 * @var array $topIPs
 * @var array $byCountry
 * @var array $heatmap
 */

// Prepare data for JS
$daysLabels  = json_encode(array_column($visitsPerDay, 'day'));
$daysTotal   = json_encode(array_map('intval', array_column($visitsPerDay, 'total')));
$daysBots    = json_encode(array_map('intval', array_column($visitsPerDay, 'bots')));
$pathLabels  = json_encode(array_column($topPaths, 'path'));
$pathTotals  = json_encode(array_map('intval', array_column($topPaths, 'total')));
$pathBots    = json_encode(array_map('intval', array_column($topPaths, 'bot_hits')));
$methodLabels = json_encode(array_column($methods, 'method'));
$methodCounts = json_encode(array_map('intval', array_column($methods, 'cnt')));

// Country chart data — attach emoji flags for labels
$countryLabels = [];
foreach ($byCountry as $row) {
    $iso = strtolower($row['country_iso']);
    $flag = '';
    if (strlen($iso) === 2) {
        $flag = mb_chr(0x1F1E6 + ord($iso[0]) - ord('a'))
            . mb_chr(0x1F1E6 + ord($iso[1]) - ord('a'))
            . ' ';
    }
    $countryLabels[] = $flag . strtoupper($row['country_iso']);
}
$countryLabelsJson = json_encode($countryLabels);
$countryCounts     = json_encode(array_map('intval', array_column($byCountry, 'cnt')));

// Build heatmap matrix [dow 1-7][hour 0-23]
$heatMatrix = array_fill(0, 7, array_fill(0, 24, 0));
$heatMax    = 1;
foreach ($heatmap as $row) {
    $d = (int)$row['dow'] - 1; // 0=Sun … 6=Sat
    $h = (int)$row['hour'];
    $v = (int)$row['cnt'];
    $heatMatrix[$d][$h] = $v;
    if ($v > $heatMax) $heatMax = $v;
}
$heatMatrixJson = json_encode($heatMatrix);
$botPct = $total > 0 ? round($bots / $total * 100, 1) : 0;
?>

<div class="min-h-screen bg-blue-300 dark:bg-gray-950 py-10 px-4 sm:px-8">
    <div class="max-w-7xl mx-auto space-y-8">

        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-50 tracking-tight">
                    Traffic Analytics
                </h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Last 30 days · all-time totals</p>
            </div>
            <a href="<?= $this->Url->build(['action' => 'index']) ?>"
               class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                ← Raw visit log
            </a>
        </div>

        <!-- Summary cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <?php
            $cards = [
                ['label' => 'Total visits',    'value' => number_format($total),      'color' => 'indigo'],
                ['label' => 'Unique IPs',       'value' => number_format($uniqueIPs),  'color' => 'sky'],
                ['label' => 'Human visits',     'value' => number_format($humans),     'color' => 'emerald'],
                ['label' => 'Bot visits',       'value' => number_format($bots) . ' (' . $botPct . '%)', 'color' => 'rose'],
            ];
            $colorMap = [
                'indigo'  => 'bg-indigo-50 dark:bg-indigo-950 border-indigo-200 dark:border-indigo-800 text-indigo-700 dark:text-indigo-300',
                'sky'     => 'bg-sky-50 dark:bg-sky-950 border-sky-200 dark:border-sky-800 text-sky-700 dark:text-sky-300',
                'emerald' => 'bg-emerald-50 dark:bg-emerald-950 border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300',
                'rose'    => 'bg-rose-50 dark:bg-rose-950 border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300',
            ];
            foreach ($cards as $card):
                $cls = $colorMap[$card['color']];
                ?>
                <div class="rounded-xl border p-5 <?= $cls ?>">
                    <p class="text-xs font-medium uppercase tracking-wider opacity-70"><?= h($card['label']) ?></p>
                    <p class="text-2xl font-bold mt-2"><?= h($card['value']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Visits over time -->
        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4">
                Visits over time — last 30 days
            </h2>
            <div class="flex gap-4 text-xs mb-3">
                <span class="flex items-center gap-1.5">
                    <span class="inline-block w-3 h-3 rounded-sm bg-indigo-500"></span>
                    <span class="text-gray-500 dark:text-gray-400">Total</span>
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="inline-block w-3 h-3 rounded-sm bg-rose-400"></span>
                    <span class="text-gray-500 dark:text-gray-400">Bots</span>
                </span>
            </div>
            <div style="position:relative;height:280px;">
                <canvas id="chartTimeline"></canvas>
            </div>
        </div>

        <!-- Two-column row -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- Top paths -->
            <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-6">
                <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4">
                    Top paths
                </h2>
                <div class="flex gap-4 text-xs mb-3">
                    <span class="flex items-center gap-1.5">
                        <span class="inline-block w-3 h-3 rounded-sm bg-indigo-500"></span>
                        <span class="text-gray-500 dark:text-gray-400">Human</span>
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="inline-block w-3 h-3 rounded-sm bg-rose-400"></span>
                        <span class="text-gray-500 dark:text-gray-400">Bot</span>
                    </span>
                </div>
                <div style="position:relative;height:<?= max(200, count($topPaths) * 38 + 60) ?>px;">
                    <canvas id="chartPaths"></canvas>
                </div>
            </div>

            <!-- HTTP methods donut -->
            <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-6">
                <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4">
                    HTTP methods
                </h2>
                <div id="methodLegend" class="flex flex-wrap gap-3 text-xs mb-3"></div>
                <div style="position:relative;height:260px;max-width:260px;margin:0 auto;">
                    <canvas id="chartMethods"></canvas>
                </div>
            </div>
        </div>

        <!-- Visits by country -->
        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4">
                Human visits by country
            </h2>
            <div style="position:relative;height:<?= max(200, count($byCountry) * 36 + 60) ?>px;">
                <canvas id="chartCountries"></canvas>
            </div>
        </div>

        <!-- Heatmap -->
        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-5">
                Activity heatmap — hour of day × day of week
            </h2>
            <div class="overflow-x-auto">
                <div id="heatmap" class="inline-block min-w-full"></div>
            </div>
        </div>

        <!-- Top IPs table -->
        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Top IP addresses
                </h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800 text-sm">
                    <thead>
                    <tr class="bg-gray-50 dark:bg-gray-800">
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">#</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">IP Address</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Player</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Country</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Visits</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Share</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <?php foreach ($topIPs as $i => $row): ?>
                        <?php
                        // Country: prefer player's country, fall back to visit-level GeoIP
                        $iso     = strtolower($row['player_country'] ?? $row['country_iso'] ?? '');
                        $isoUp   = strtoupper($iso);
                        // Convert ISO to emoji flag (each letter → regional indicator A=U+1F1E6)
                        $flag    = '';
                        if (strlen($iso) === 2) {
                            $flag = mb_chr(0x1F1E6 + ord($iso[0]) - ord('a'))
                                . mb_chr(0x1F1E6 + ord($iso[1]) - ord('a'));
                        }
                        ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            <td class="px-4 py-3 text-gray-400 dark:text-gray-600 font-mono text-xs"><?= $i + 1 ?></td>

                            <td class="px-4 py-3 font-mono text-xs text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                <?= h($row['ip_address']) ?>
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap">
                                <?php if (!empty($row['player_name'])): ?>
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-950 px-2 py-0.5 rounded-full">
                                    <?= h($row['player_name']) ?>
                                </span>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400 dark:text-gray-600">—</span>
                                <?php endif; ?>
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap">
                                <?php if ($flag): ?>
                                    <span class="inline-flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-400">
                                    <span class="text-base leading-none"><?= $flag ?></span>
                                    <span class="font-mono"><?= h($isoUp) ?></span>
                                </span>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400 dark:text-gray-600">—</span>
                                <?php endif; ?>
                            </td>

                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300 font-semibold whitespace-nowrap">
                                <?= number_format((int)$row['cnt']) ?>
                            </td>

                            <td class="px-4 py-3">
                                <?php $pct = $total > 0 ? round((int)$row['cnt'] / $total * 100, 1) : 0; ?>
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 bg-gray-100 dark:bg-gray-700 rounded-full h-1.5" style="min-width:60px;max-width:80px">
                                        <div class="bg-indigo-500 h-1.5 rounded-full" style="width:<?= min(100, $pct) ?>%"></div>
                                    </div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap"><?= $pct ?>%</span>
                                </div>
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap">
                                <?php if ($row['is_bot']): ?>
                                    <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-rose-100 text-rose-700 dark:bg-rose-900 dark:text-rose-300">Bot</span>
                                <?php else: ?>
                                    <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300">Human</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- /max-w -->
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<script>
    (function () {
        const dark = document.documentElement.classList.contains('dark')
            || window.matchMedia('(prefers-color-scheme: dark)').matches;

        const gridColor  = dark ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.07)';
        const tickColor  = dark ? '#9ca3af' : '#6b7280';
        const bgColor    = dark ? '#111827' : '#ffffff';

        // ---------- Timeline ----------
        const tlLabels = <?= $daysLabels ?>;
        const tlTotal  = <?= $daysTotal ?>;
        const tlBots   = <?= $daysBots ?>;

        new Chart(document.getElementById('chartTimeline'), {
            type: 'bar',
            data: {
                labels: tlLabels,
                datasets: [
                    {
                        label: 'Total',
                        data: tlTotal,
                        backgroundColor: 'rgba(99,102,241,.65)',
                        borderColor: 'rgba(99,102,241,1)',
                        borderWidth: 1,
                        borderRadius: 3,
                        order: 2,
                    },
                    {
                        label: 'Bots',
                        data: tlBots,
                        type: 'line',
                        borderColor: 'rgba(251,113,133,1)',
                        backgroundColor: 'rgba(251,113,133,.15)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 2,
                        borderWidth: 2,
                        order: 1,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        ticks: { color: tickColor, maxRotation: 45, autoSkip: true, maxTicksLimit: 12 },
                        grid: { color: gridColor }
                    },
                    y: {
                        ticks: { color: tickColor },
                        grid: { color: gridColor },
                        beginAtZero: true
                    }
                }
            }
        });

        // ---------- Top paths (horizontal stacked bar) ----------
        const pathLabels = <?= $pathLabels ?>;
        const pathTotals = <?= $pathTotals ?>;
        const pathBots   = <?= $pathBots ?>;
        const pathHumans = pathTotals.map((t, i) => Math.max(0, t - pathBots[i]));

        new Chart(document.getElementById('chartPaths'), {
            type: 'bar',
            data: {
                labels: pathLabels,
                datasets: [
                    {
                        label: 'Human',
                        data: pathHumans,
                        backgroundColor: 'rgba(99,102,241,.7)',
                        borderRadius: { topRight: 4, bottomRight: 4 },
                    },
                    {
                        label: 'Bot',
                        data: pathBots,
                        backgroundColor: 'rgba(251,113,133,.7)',
                    }
                ]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        stacked: true,
                        ticks: { color: tickColor },
                        grid: { color: gridColor },
                        beginAtZero: true
                    },
                    y: {
                        stacked: true,
                        ticks: {
                            color: tickColor,
                            callback: v => v.length > 28 ? v.slice(0, 27) + '…' : v
                        },
                        grid: { display: false }
                    }
                }
            }
        });

        // ---------- Methods donut ----------
        const methodLabels = <?= $methodLabels ?>;
        const methodCounts = <?= $methodCounts ?>;
        const palette = ['#6366f1','#38bdf8','#34d399','#fb7185','#fbbf24','#a78bfa'];

        // Build legend
        const legend = document.getElementById('methodLegend');
        methodLabels.forEach((lbl, i) => {
            const total = methodCounts.reduce((a, b) => a + b, 0);
            const pct   = total > 0 ? Math.round(methodCounts[i] / total * 100) : 0;
            const span  = document.createElement('span');
            span.className = 'flex items-center gap-1.5 text-gray-500 dark:text-gray-400';
            span.innerHTML = `<span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:${palette[i % palette.length]}"></span>${lbl} ${pct}%`;
            legend.appendChild(span);
        });

        new Chart(document.getElementById('chartMethods'), {
            type: 'doughnut',
            data: {
                labels: methodLabels,
                datasets: [{
                    data: methodCounts,
                    backgroundColor: palette,
                    borderWidth: 2,
                    borderColor: bgColor,
                    hoverOffset: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: { legend: { display: false } }
            }
        });

        // ---------- Countries ----------
        const countryLabels = <?= $countryLabelsJson ?>;
        const countryCounts = <?= $countryCounts ?>;

        new Chart(document.getElementById('chartCountries'), {
            type: 'bar',
            data: {
                labels: countryLabels,
                datasets: [{
                    data: countryCounts,
                    backgroundColor: 'rgba(99,102,241,.7)',
                    borderRadius: 4,
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        ticks: { color: tickColor },
                        grid: { color: gridColor },
                        beginAtZero: true
                    },
                    y: {
                        ticks: { color: tickColor, font: { size: 13 } },
                        grid: { display: false }
                    }
                }
            }
        });

        // ---------- Heatmap ----------
        const matrix  = <?= $heatMatrixJson ?>;
        const maxVal  = <?= $heatMax ?>;
        const days    = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        const hours   = Array.from({length: 24}, (_, i) => i);

        const cellW   = 28, cellH = 22, labelW = 36, labelH = 24;
        const svgW    = labelW + cellW * 24 + 1;
        const svgH    = labelH + cellH * 7 + 1;

        function hexToRgb(hex) {
            const r = parseInt(hex.slice(1,3),16);
            const g = parseInt(hex.slice(3,5),16);
            const b = parseInt(hex.slice(5,7),16);
            return [r,g,b];
        }

        // Color scale: 0 → cool gray, 1 → indigo
        function cellColor(val) {
            const t = maxVal > 0 ? val / maxVal : 0;
            if (dark) {
                const r = Math.round(30  + t * (99  - 30));
                const g = Math.round(30  + t * (102 - 30));
                const b = Math.round(50  + t * (241 - 50));
                return `rgb(${r},${g},${b})`;
            } else {
                const r = Math.round(243 - t * (243 - 79));
                const g = Math.round(244 - t * (244 - 70));
                const b = Math.round(246 - t * (246 - 229));
                return `rgb(${r},${g},${b})`;
            }
        }

        let svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${svgW}" height="${svgH}" style="font-family:inherit;font-size:9px;fill:${tickColor}">`;

        // Hour labels
        hours.forEach(h => {
            if (h % 3 === 0) {
                svg += `<text x="${labelW + h * cellW + cellW / 2}" y="${labelH - 4}" text-anchor="middle">${h}:00</text>`;
            }
        });

        // Day labels + cells
        days.forEach((day, d) => {
            const y = labelH + d * cellH;
            svg += `<text x="${labelW - 4}" y="${y + cellH / 2 + 3}" text-anchor="end">${day}</text>`;
            hours.forEach(h => {
                const val = matrix[d][h];
                const x   = labelW + h * cellW;
                const col = cellColor(val);
                const tip = `${day} ${h}:00 — ${val} visits`;
                svg += `<rect x="${x+1}" y="${y+1}" width="${cellW-2}" height="${cellH-2}" rx="3" fill="${col}">`;
                svg += `<title>${tip}</title></rect>`;
            });
        });

        svg += '</svg>';
        document.getElementById('heatmap').innerHTML = svg;

    })();
</script>
