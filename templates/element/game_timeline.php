<?php
/**
 * Minute by minute: the team score (or the top players' frags) as a chart and
 * the scoreboard of any minute with a slider, from the server's minute status
 * blocks (game_timelines) - and the votes called in the game.
 *
 * @var \App\View\AppView $this
 * @var array $timeline GamesController::timelineAndVotes()
 * @var array $votes
 * @var bool $flagMode
 */
if (count($timeline) < 2 && !$votes) {
    return;
}
$clock = fn(?int $s) => $s === null ? '' : intdiv($s, 60) . ':' . str_pad((string)($s % 60), 2, '0', STR_PAD_LEFT);
$resultStyle = [
    'passed' => ['passed', 'bg-green-500/20 text-green-300'],
    'failed' => ['failed', 'bg-red-500/20 text-red-300'],
    'refused' => ['not allowed', 'bg-white/10 text-zinc-400'],
];
$voteWhat = function (array $v): string {
    $who = fn(?string $name, ?string $id) => $id
        ? $this->Html->link(h((string)$name), ['controller' => 'Players', 'action' => 'view', $id], ['class' => 'font-semibold hover:text-blue-300', 'escape' => false])
        : '<span class="font-semibold">' . h((string)$name) . '</span>';

    return match ($v['kind']) {
        'map' => 'map <b>' . h($this->Layout->cleanMapName((string)$v['map'])) . '</b> <span class="text-zinc-400">' . h((string)$v['mode']) . '</span>',
        'kick', 'ban' => $v['kind'] . ' ' . $who($v['target'], $v['target_id'])
            . (!empty($v['reason']) ? ' <span class="text-zinc-400">&ldquo;' . h($v['reason']) . '&rdquo;</span>' : ''),
        'shuffle' => 'shuffle teams',
        'mastermode' => 'mastermode <b>' . h((string)$v['target']) . '</b>',
        default => h((string)($v['desc'] ?? '')),
    } . ' <span class="text-zinc-500">by</span> ' . $who($v['name'], $v['actor_id']);
};
?>
<section class="mt-6 rounded-xl bg-black/60 p-4 backdrop-blur-[2px] md:p-5">
    <?php if (count($timeline) >= 2): ?>
        <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
            <h3 class="text-lg font-bold"><i class="fa-solid fa-chart-line mr-1 text-sky-300"></i>Minute by minute</h3>
            <span class="flex flex-wrap items-center gap-x-5 gap-y-1 text-xs text-zinc-400" id="tl-legend"></span>
        </div>
        <svg id="tl-chart" class="block h-44 w-full" role="img" aria-label="Score over the game"></svg>

        <div class="mt-3 flex items-center gap-3">
            <label for="tl-slider" class="shrink-0 font-mono text-sm text-zinc-300">
                <i class="fa-regular fa-clock mr-1"></i><span id="tl-time"></span>
            </label>
            <input id="tl-slider" type="range" min="0" max="<?= count($timeline) - 1 ?>" value="<?= count($timeline) - 1 ?>"
                   class="w-full accent-sky-400" aria-label="Minute of the game">
        </div>

        <div class="mt-3 max-w-3xl overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-[11px] uppercase tracking-wide text-zinc-400">
                <tr>
                    <th class="px-2 py-1 text-left">Player</th>
                    <?php if ($flagMode): ?><th class="px-2 py-1 text-right">Flags</th><?php endif; ?>
                    <th class="px-2 py-1 text-right">Frags</th>
                    <th class="px-2 py-1 text-right">Deaths</th>
                    <th class="px-2 py-1 text-right" title="Ping in this minute">Ping</th>
                </tr>
                </thead>
                <tbody id="tl-rows"></tbody>
            </table>
        </div>
        <p class="mt-2 text-[11px] text-zinc-500">The server's scoreboard once a minute &ndash; green: since the minute before.
            <?php if (!empty(end($timeline)['t'])): ?>Team totals count the players on the team at that minute, so a player leaving or switching takes their score along.<?php endif; ?></p>
    <?php endif; ?>

    <?php if ($votes): ?>
        <h3 class="<?= count($timeline) >= 2 ? 'mt-6' : '' ?> mb-2 text-lg font-bold"><i class="fa-solid fa-check-to-slot mr-1 text-amber-300"></i>Votes</h3>
        <?php
        // votes the server refused (too often, invalid map ...) are folded away
        $refused = array_filter($votes, fn($v) => ($v['result'] ?? null) === 'refused');
        $voteList = function (array $list) use ($resultStyle, $clock, $voteWhat) {
            ob_start(); ?>
        <ul class="space-y-1 text-sm">
            <?php foreach ($list as $v): [$label, $class] = $resultStyle[$v['result'] ?? ''] ?? ['?', 'bg-white/5 text-zinc-500']; ?>
                <li class="flex items-baseline gap-2">
                    <span class="w-10 shrink-0 text-right font-mono text-xs text-zinc-500"><?= $clock($v['at']) ?></span>
                    <span class="min-w-0 flex-1"><?= $voteWhat($v) ?></span>
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase <?= $class ?>"
                          <?php if (!empty($v['error'])): ?>title="<?= h($v['error']) ?>"<?php endif; ?>
                          <?php if (($v['result'] ?? null) === null): ?>title="The log doesn't show whether this one passed"<?php endif; ?>><?= h($label) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
            <?php return ob_get_clean();
        };
        ?>
        <?= $voteList(array_filter($votes, fn($v) => ($v['result'] ?? null) !== 'refused')) ?>
        <?php if ($refused): ?>
            <details class="mt-2">
                <summary class="cursor-pointer text-xs text-zinc-400 hover:text-zinc-200"><?= count($refused) ?> more the server didn't allow</summary>
                <div class="mt-1"><?= $voteList($refused) ?></div>
            </details>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php if (count($timeline) >= 2): ?>
<?php $this->append('scriptBottom'); ?>
<script>
(() => {
    const tl = <?= json_encode($timeline, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    const flagMode = <?= json_encode((bool)$flagMode) ?>;
    const svg = document.getElementById('tl-chart');
    const slider = document.getElementById('tl-slider');
    const rowsEl = document.getElementById('tl-rows');
    const timeEl = document.getElementById('tl-time');
    const H = 176, PAD = 18;
    let W = 600;
    const NS = 'http://www.w3.org/2000/svg';
    const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]));
    const clock = (s) => Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
    const flag = (cc) => cc && cc.length === 2 ? String.fromCodePoint(...[...cc.toUpperCase()].map(c => 0x1F1E6 + c.charCodeAt(0) - 65)) : '';
    const key = (p) => p.id || ('name:' + p.n);
    const last = tl[tl.length - 1];
    const teamGame = !!(last.t && last.t.CLA && last.t.RVSF);

    // series: the two teams (flags in flag modes, else frags), or the top 5 players' frags
    const palette = ['#fbbf24', '#34d399', '#f472b6', '#a78bfa', '#22d3ee'];
    let series;
    if (teamGame) {
        const idx = flagMode ? 2 : 1;
        series = [['CLA', '#f87171'], ['RVSF', '#60a5fa']].map(([team, color]) => ({
            label: team, color, points: tl.map(s => s.t && s.t[team] ? (s.t[team][idx] ?? 0) : null),
        }));
    } else {
        const top = [...last.p].sort((a, b) => b.fr - a.fr).slice(0, 5);
        series = top.map((p, i) => ({
            label: p.n, color: palette[i],
            points: tl.map(s => { const r = s.p.find(q => key(q) === key(p)); return r ? r.fr : null; }),
        }));
    }
    document.getElementById('tl-legend').innerHTML = series.map(s =>
        `<span class="whitespace-nowrap"><span class="mr-1.5 inline-block h-2 w-3 rounded-sm align-middle" style="background:${s.color}"></span>${esc(s.label)}</span>`
    ).join('') + `<span class="border-l border-white/20 pl-5 uppercase tracking-wide text-zinc-500">${teamGame && flagMode ? 'flags' : 'frags'}</span>`;

    const maxS = Math.max(1, last.s);
    const maxY = Math.max(1, ...series.flatMap(s => s.points.filter(v => v !== null)));
    const x = (s) => PAD + 8 + (W - 2 * PAD - 8) * s / maxS;
    const y = (v) => H - PAD - (H - 2 * PAD) * v / maxY;
    const el = (name, attrs) => { const e = document.createElementNS(NS, name); for (const k in attrs) e.setAttribute(k, attrs[k]); svg.appendChild(e); return e; };

    // drawn at the chart's real width (no stretched text), again on resize
    let marker, current = +slider.value;
    const draw = () => {
        W = Math.max(280, svg.clientWidth || 600);
        svg.setAttribute('viewBox', `0 0 ${W} ${H}`);
        svg.replaceChildren();
        // grid: baseline, top value, one tick per 5 minutes
        el('line', {x1: x(0), x2: W - PAD, y1: y(0), y2: y(0), stroke: 'rgba(255,255,255,.25)'});
        el('line', {x1: x(0), x2: W - PAD, y1: y(maxY), y2: y(maxY), stroke: 'rgba(255,255,255,.08)'});
        el('text', {x: 2, y: y(maxY) + 4, fill: '#a1a1aa', 'font-size': 11}).textContent = maxY;
        el('text', {x: 2, y: y(0) + 4, fill: '#71717a', 'font-size': 11}).textContent = 0;
        for (let m = 5; m * 60 < maxS; m += 5) {
            el('line', {x1: x(m * 60), x2: x(m * 60), y1: PAD, y2: H - PAD, stroke: 'rgba(255,255,255,.06)'});
            el('text', {x: x(m * 60), y: H - 3, fill: '#71717a', 'font-size': 11, 'text-anchor': 'middle'}).textContent = m + "'";
        }
        for (const s of series) {
            const pts = tl.map((snap, i) => s.points[i] === null ? null : x(snap.s) + ',' + y(s.points[i])).filter(Boolean);
            el('polyline', {points: pts.join(' '), fill: 'none', stroke: s.color, 'stroke-width': 2.5, 'stroke-linejoin': 'round'});
        }
        marker = el('line', {y1: PAD - 6, y2: H - PAD, stroke: '#e4e4e7', 'stroke-dasharray': '3 3'});
        moveMarker();
    };
    const moveMarker = () => { marker.setAttribute('x1', x(tl[current].s)); marker.setAttribute('x2', x(tl[current].s)); };
    window.addEventListener('resize', draw);

    const teamClass = {CLA: 'text-red-400', RVSF: 'text-blue-400'};
    const show = (i) => {
        const snap = tl[i];
        const prev = i > 0 ? Object.fromEntries(tl[i - 1].p.map(p => [key(p), p])) : {};
        current = i;
        moveMarker();
        timeEl.textContent = clock(snap.s);
        const order = {CLA: 0, RVSF: 1};
        const rows = [...snap.p].sort((a, b) => (order[a.team] ?? 2) - (order[b.team] ?? 2) || (b.fl ?? 0) - (a.fl ?? 0) || b.fr - a.fr);
        const delta = (now, before) => before !== undefined && now > before ? ` <span class="text-[10px] text-green-400">+${now - before}</span>` : '';
        rowsEl.innerHTML = rows.map(p => {
            const b = prev[key(p)];
            const name = p.id ? `<a href="/players/view/${esc(p.id)}" class="hover:text-blue-300">${esc(p.n)}</a>` : `<span class="${p.n === 'Anonymous' ? 'italic text-zinc-400' : ''}">${esc(p.n)}</span>`;
            const pingClass = p.ping >= 200 ? 'text-red-400' : (p.ping >= 100 ? 'text-amber-300' : 'text-zinc-300');
            return `<tr class="border-t border-white/5">
                <td class="px-2 py-1 whitespace-nowrap"><span class="mr-1 ${teamClass[p.team] || 'text-zinc-500'}">●</span>${name} ${flag(p.c)}</td>
                ${flagMode ? `<td class="px-2 py-1 text-right font-mono">${p.fl ?? ''}${delta(p.fl, b && b.fl)}</td>` : ''}
                <td class="px-2 py-1 text-right font-mono">${p.fr}${delta(p.fr, b && b.fr)}</td>
                <td class="px-2 py-1 text-right font-mono text-zinc-400">${p.d}</td>
                <td class="px-2 py-1 text-right font-mono ${pingClass}">${p.ping || ''}</td>
            </tr>`;
        }).join('');
    };
    slider.addEventListener('input', () => show(+slider.value));
    draw();
    show(current);
})();
</script>
<?php $this->end(); ?>
<?php endif; ?>
