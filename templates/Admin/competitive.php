<?php
/**
 * Admin: the competitive players and their names, similar names next to each
 * other. Tick the ones that are one person, pick who they are (one of them, or
 * a ladder profile) and merge (CompetitiveIdentity::merge).
 *
 * @var \App\View\AppView $this
 * @var array $players player_key => overview row
 * @var string $tab
 */
$linked = count(array_filter($players, fn($p) => $p['profile']));
?>
<div class="relative min-h-[calc(100svh-4rem)] bg-zinc-950 text-white">
<div class="mx-auto flex w-full max-w-6xl flex-col gap-5 px-4 py-6 pb-28 md:px-10 md:py-8 md:pb-28">
    <?= $this->element('admin_nav') ?>
    <p class="max-w-3xl text-sm text-zinc-400">
        <?= count($players) ?> competitive players, <?= $linked ?> of them linked to a ladder profile. Similar names stand together.
        Tick the ones that are the same person, choose with <i class="fa-regular fa-circle-dot"></i> which one they are, or enter their ladder profile,
        and merge. Merged into a ladder profile, all their names become aliases of that profile. The Elo is recalculated right away.
    </p>

    <div class="flex flex-wrap items-center gap-3 text-sm">
        <input type="search" id="filter" placeholder="Filter names…" autocomplete="off"
               class="w-64 rounded-lg border border-white/15 bg-black/60 px-3 py-1.5 text-white placeholder-zinc-500 focus:border-white/40 focus:outline-none">
        <label class="flex items-center gap-1.5 text-zinc-300"><input type="checkbox" id="unlinked"> only without a ladder profile</label>
        <span id="shown" class="text-xs text-zinc-500"></span>
    </div>

    <?= $this->Form->create(null, ['id' => 'merge-form']) ?>
    <div class="overflow-x-auto rounded-xl border border-white/10 bg-black/50">
        <table class="w-full text-sm">
            <thead class="text-[11px] uppercase tracking-wide text-zinc-400">
            <tr>
                <th class="w-8 px-2 py-2"></th>
                <th class="w-8 px-2 py-2" title="Merge into this one">is</th>
                <th class="px-2 py-2 text-left">Name</th>
                <th class="px-2 py-2 text-left">Also seen as</th>
                <th class="px-2 py-2 text-right">Games</th>
                <th class="px-2 py-2 text-right">Elo</th>
                <th class="px-2 py-2 text-left">Ladder profile</th>
                <th class="px-2 py-2 text-right" title="Networks (/24) seen in live games">Nets</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($players as $key => $p): $others = array_values(array_diff($p['names'], [$p['name']])); ?>
                <tr class="js-row border-t border-white/5 hover:bg-white/5"
                    data-search="<?= h(mb_strtolower(implode(' ', $p['names']) . ' ' . $p['name'] . ' ' . ($p['profile']['name'] ?? ''))) ?>"
                    data-linked="<?= $p['profile'] ? 1 : 0 ?>" data-games="<?= (int)$p['games'] ?>">
                    <td class="px-2 py-1 text-center"><input type="checkbox" name="keys[]" value="<?= h($key) ?>" class="js-pick h-4 w-4 accent-amber-400"></td>
                    <td class="px-2 py-1 text-center"><input type="radio" name="into" value="<?= h($key) ?>" class="js-into h-4 w-4 accent-amber-400" disabled></td>
                    <td class="px-2 py-1 font-semibold"><?= h($p['name']) ?></td>
                    <td class="max-w-xs truncate px-2 py-1 text-xs text-zinc-400" title="<?= h(implode(', ', $others)) ?>"><?= h(implode(', ', $others)) ?></td>
                    <td class="px-2 py-1 text-right font-mono"><?= (int)$p['games'] ?></td>
                    <td class="px-2 py-1 text-right font-mono text-amber-300"><?= $p['rating'] !== null ? (int)round($p['rating']) : '<span class="text-zinc-600">–</span>' ?></td>
                    <td class="px-2 py-1">
                        <?= $p['profile']
                            ? $this->Html->link(h($p['profile']['name']) . ' ' . $this->Layout->flag($p['profile']['country']), ['controller' => 'Players', 'action' => 'view', $p['profile']['id']], ['escape' => false, 'class' => 'text-sky-300 hover:underline', 'target' => '_blank'])
                            : '<span class="text-zinc-600">–</span>' ?>
                    </td>
                    <td class="px-2 py-1 text-right font-mono text-zinc-400"><?= $p['nets'] ?: '' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="fixed inset-x-0 bottom-0 z-30 border-t border-white/15 bg-zinc-900/95 backdrop-blur">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-3 px-4 py-3 text-sm md:px-10">
            <span id="picked" class="text-zinc-300">Nothing ticked</span>
            <label class="flex items-center gap-2 text-zinc-400">or ladder profile
                <input type="text" name="profile" placeholder="name or id" autocomplete="off"
                       class="w-48 rounded-lg border border-white/15 bg-black/60 px-2 py-1 text-white placeholder-zinc-600 focus:outline-none">
            </label>
            <button type="submit" id="merge" disabled
                    class="ml-auto rounded-lg bg-amber-500 px-4 py-1.5 font-bold text-black transition hover:bg-amber-400 disabled:cursor-not-allowed disabled:opacity-40">
                <i class="fa-solid fa-code-merge mr-1"></i>Merge
            </button>
        </div>
    </div>
    <?= $this->Form->end() ?>
</div>
</div>
<script>
(() => {
    const rows = [...document.querySelectorAll('.js-row')];
    const filter = document.getElementById('filter');
    const unlinked = document.getElementById('unlinked');
    const form = document.getElementById('merge-form');
    const profile = form.querySelector('[name=profile]');
    const button = document.getElementById('merge');
    const picked = document.getElementById('picked');

    const applyFilter = () => {
        const q = filter.value.trim().toLowerCase();
        let n = 0;
        rows.forEach(r => {
            const show = (!q || r.dataset.search.includes(q)) && (!unlinked.checked || r.dataset.linked === '0')
                || r.querySelector('.js-pick').checked; // ticked ones stay visible
            r.hidden = !show;
            n += show;
        });
        document.getElementById('shown').textContent = `${n} shown`;
    };

    const update = () => {
        const ticked = rows.filter(r => r.querySelector('.js-pick').checked);
        rows.forEach(r => {
            const radio = r.querySelector('.js-into');
            radio.disabled = !r.querySelector('.js-pick').checked;
            if (radio.disabled) radio.checked = false;
        });
        // default: the linked one, else the one with the most games
        if (ticked.length) {
            const best = ticked.find(r => r.dataset.linked === '1')
                || ticked.reduce((a, b) => +b.dataset.games > +a.dataset.games ? b : a);
            best.querySelector('.js-into').checked = true;
        }
        const hasProfile = profile.value.trim() !== '';
        const into = ticked.find(r => r.querySelector('.js-into').checked);
        picked.innerHTML = !ticked.length ? 'Nothing ticked'
            : `<b>${ticked.length}</b> ticked → ` + (hasProfile ? 'ladder profile <b></b>' : `<b></b>`);
        if (ticked.length) picked.querySelector('b:last-child').textContent = hasProfile ? profile.value.trim() : into.cells[2].textContent;
        button.disabled = !(ticked.length >= 2 || (ticked.length === 1 && hasProfile));
    };

    filter.addEventListener('input', applyFilter);
    unlinked.addEventListener('change', applyFilter);
    form.addEventListener('change', e => { if (e.target.classList.contains('js-pick')) rows.forEach(r => r.querySelector('.js-into').checked = false); update(); });
    profile.addEventListener('input', update);
    form.addEventListener('submit', e => {
        if (!confirm(picked.textContent + '\nMerge them?')) e.preventDefault();
    });
    applyFilter();
})();
</script>
