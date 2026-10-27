<?php
/**
 * Hover card in the style of the player card picture (PlayerCardImage): the
 * tier frame and pill by CTF rating, the player panel on a map with the
 * weapons of choice and the character, and below it $body, or the stats
 * panel - rating, type, attack / defense / combat and the totals. Used by the
 * rankings (Players/hover_card) and /maps (Maps/player_stats).
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Player $player
 * @var \Cake\Datasource\EntityInterface|null $rating
 * @var int $ratedPlayers
 * @var array $progress PlayerMilestoneProgress totals
 * @var string|null $lastSeen
 * @var array $badges last week's achievements
 * @var array $weapons PlayersController::weaponsOfChoice()
 * @var array|null $map map behind the player ['name'] (their most played one)
 * @var string|null $subtitle line under the name instead of hours / games
 * @var string|null $body HTML below the player panel instead of the stats panel
 */
use App\Command\CalculateRatingsCommand;
use App\Service\PlayerCardImage;

$ratedPlayers = $ratedPlayers ?? 0;
$progress = $progress ?? [];
$lastSeen = $lastSeen ?? null;
$badges = $badges ?? [];
$qrLevel = (int)($qrLevel ?? 0);   // QR hunt: codes found in our maps
$weapons = $weapons ?? [];
$map = $map ?? null;
$subtitle = $subtitle ?? null;
$body = $body ?? null;
$labels = [
    'total_score' => 'Most Points', 'games' => 'Most Games Played', 'teamkills' => 'Most Teamkills', 'kd_ratio' => 'Best KD Ratio',
    'gibbed' => 'Most Gibs', 'slashed' => 'Most Slashes', 'scored_with_the_flag' => 'Most Flags scored', 'headshot' => 'Most Headshots',
    'aim_trainer' => 'Best aim trainer round',
];
$seenLabel = null;
if ($lastSeen) {
    $days = (int)floor((time() - strtotime((string)$lastSeen)) / 86400);
    $seenLabel = $days <= 0 ? 'today' : ($days === 1 ? 'yesterday' : $days . ' days ago');
}
$inactive = $lastSeen !== null && !\App\Utility\Activity::isActive($lastSeen);

// the card tier (PlayerCardImage::TIERS) as CSS: frame gradient, pill, glow
$tier = PlayerCardImage::tier($rating ? (float)$rating->rating : null);
[$frame, $pill, $glow] = match ($tier['key'] ?? null) {
    'legend' => ['from-amber-200 via-orange-400 to-rose-600', 'text-amber-200 bg-orange-950/80', 'shadow-[0_0_28px_rgba(251,146,60,.45)]'],
    'diamond' => ['from-cyan-300 via-indigo-400 to-fuchsia-500', 'text-cyan-200 bg-indigo-950/80', 'shadow-[0_0_24px_rgba(129,140,248,.4)]'],
    'gold' => ['from-yellow-200 via-yellow-500 to-yellow-700', 'text-yellow-200 bg-yellow-950/80', 'shadow-[0_0_20px_rgba(234,179,8,.35)]'],
    'silver' => ['from-slate-50 via-slate-400 to-slate-600', 'text-slate-100 bg-slate-900/80', ''],
    default => ['from-blue-500/70 via-blue-500/40 to-blue-500/70', '', ''],
};
$tierIcon = ['legend' => 'fa-crown', 'diamond' => 'fa-gem', 'gold' => 'fa-medal', 'silver' => 'fa-shield-halved'][$tier['key'] ?? ''] ?? null;
$bg = !empty($map['name']) && is_file(WWW_ROOT . 'img/maps/' . $map['name'] . '.jpg')
    ? \App\View\Helper\LayoutHelper::mapUrl($map['name'] . '.jpg')
    : '/img/bullet.jpg';
$archetype = $rating?->archetype;
$archetypeColor = match ($archetype) {
    'Headhunter' => 'text-sky-300', 'Butcher' => 'text-red-300', 'Bomber' => 'text-orange-300',
    'Pistolero' => 'text-emerald-300', 'Berserker' => 'text-purple-300', default => 'text-zinc-200',
};
?>
<!-- the tier frame: a gradient border around the dark card -->
<div data-framed class="rounded-2xl bg-gradient-to-br <?= $frame ?> p-[3px] <?= $glow ?>">
<div class="rounded-[14px] bg-gradient-to-b from-[#0b1222] to-[#070b16] p-3 text-white">

    <!-- top line: cubeLadder and the tier pill -->
    <div class="mb-2 flex items-center justify-between border-b border-white/10 pb-2">
        <span class="flex items-center gap-1.5 text-sm font-extrabold"><img src="/img/brand/cubeladder-icon.svg" alt="" class="h-5 w-5"><span><span class="text-blue-400">cube</span>Ladder</span></span>
        <?php if ($tier): ?>
            <span class="inline-flex items-center gap-1.5 rounded-full p-[2px] bg-gradient-to-r <?= $frame ?>">
                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-extrabold tracking-wide <?= $pill ?>">
                    <i class="fa-solid <?= $tierIcon ?>"></i><?= h($tier['name']) ?><?= (int)$rating->rank <= 3 ? ' · #' . (int)$rating->rank : '' ?>
                </span>
            </span>
        <?php endif; ?>
    </div>

    <!-- player panel on the most played map -->
    <div class="relative overflow-hidden rounded-xl border border-white/10 bg-cover bg-center"
         style="background-image: linear-gradient(to right, rgba(11,18,34,1) 35%, rgba(11,18,34,.75) 65%, rgba(11,18,34,.35)), url('<?= h($bg) ?>');">
        <div class="flex items-center gap-3 p-3">
            <img src="<?= $this->Layout->playerPicture($player) ?>" alt=""
                 class="h-16 w-16 shrink-0 rounded-full object-cover ring-[3px] <?= $tier ? 'ring-yellow-400/80' : 'ring-blue-400/60' ?>">
            <div class="min-w-0 flex-1">
                <div class="truncate text-xl font-extrabold leading-tight"><?= h($player->name) ?></div>
                <div class="mt-0.5 text-[11px] text-zinc-300">
                    <?= $this->Layout->flag($player->country) ?>
                    <?php if ($subtitle !== null): ?>
                        &middot; <?= h($subtitle) ?>
                    <?php else: ?>
                        <?= !empty($progress['hours']) ? ' · ' . number_format((float)$progress['hours']) . ' h played' : '' ?>
                        <?= !empty($progress['games']) ? ' · ' . number_format((int)$progress['games']) . ' games' : '' ?>
                    <?php endif; ?>
                </div>
                <?php if (!empty($weapons['choice'])): ?>
                    <div class="mt-1.5 flex flex-wrap gap-x-3 gap-y-1">
                        <?php foreach (array_slice($weapons['choice'], 0, 3) as $weapon): ?>
                            <span class="flex items-center gap-1.5">
                                <img src="/img/weapons/<?= h($weapon['key']) ?>.svg" alt="" class="h-5 w-5 shrink-0">
                                <span class="leading-none">
                                    <span class="block text-[10px] font-extrabold uppercase"><?= h($weapon['name']) ?></span>
                                    <span class="block text-[9px] text-zinc-400"><?= $weapon['pct'] ?>% of kills</span>
                                </span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($archetype): ?>
                <!-- the character, like on the card -->
                <div class="flex shrink-0 items-center gap-2 text-right">
                    <div class="leading-tight">
                        <div class="text-[9px] uppercase tracking-wider text-zinc-400">Character</div>
                        <div class="text-sm font-extrabold uppercase <?= $archetypeColor ?>"><?= h(CalculateRatingsCommand::archetypeLabel($archetype, $rating->archetype_rank)) ?></div>
                        <div class="max-w-[9rem] text-[9px] text-zinc-300"><?= h(CalculateRatingsCommand::archetypeText($archetype, $rating->archetype_pct, $rating->archetype_rank)) ?></div>
                    </div>
                    <span class="relative <?= $archetypeColor ?>">
                        <img src="/img/characters/<?= h(strtolower($archetype)) ?>.svg" alt="" class="h-12 w-12 drop-shadow-[0_0_10px_currentColor]">
                        <?php if ((int)$rating->archetype_rank === 1): ?>
                            <i class="fa-solid fa-crown absolute -top-2 left-1/2 -translate-x-1/2 text-xs text-yellow-300"></i>
                        <?php endif; ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($inactive || $badges || $qrLevel): ?>
        <div class="mt-2 flex flex-wrap items-center gap-1.5">
            <?= $this->element('qr_badge', ['level' => $qrLevel]) ?>
            <?php if ($inactive): ?>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-0.5 text-xs font-semibold text-zinc-300"><i class="fa-solid fa-moon text-[10px]"></i>Inactive &ndash; no game in <?= \App\Utility\Activity::ACTIVE_DAYS ?> days</span>
            <?php endif; ?>
            <?php foreach ($badges as $event): ?>
                <?= $this->element('achievement_badge', ['event' => $event, 'title' => ($labels[$event] ?? $event) . ' last week', 'large' => true]) ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($body !== null): ?>
        <div class="mt-2"><?= $body ?></div>
    <?php else: ?>
    <!-- stats panel: rating, type and bars | totals -->
    <div class="mt-2 grid grid-cols-[auto_1fr] gap-3 rounded-xl border border-white/10 bg-[#0d1528] p-3">
        <?php if ($rating): ?>
            <div class="flex flex-col items-center justify-center border-r border-white/10 pr-3">
                <div class="font-mono text-5xl font-extrabold leading-none <?= $this->Layout->ratingClass((float)$rating->rating) ?> <?= !empty($tier['rays']) ? 'drop-shadow-[0_0_12px_currentColor]' : '' ?>">
                    <?= number_format((float)$rating->rating, 1) ?><?= $this->Layout->trendArrow($rating->trend !== null ? (float)$rating->trend : null, 'ml-0.5 align-top text-sm') ?>
                </div>
                <div class="mt-1 text-[10px] uppercase tracking-wider text-zinc-400">CTF rating</div>
                <div class="text-[10px] text-zinc-500">#<?= (int)$rating->rank ?> of <?= number_format($ratedPlayers) ?></div>
                <?php if ($rating->skill !== null): ?>
                    <div class="mt-1.5 border-t border-white/10 pt-1.5 text-center text-[10px] uppercase tracking-wider text-zinc-400">
                        Skill <b class="font-mono text-sm <?= $this->Layout->ratingClass((float)$rating->skill) ?>"><?= number_format((float)$rating->skill, 1) ?></b><?= $this->Layout->trendArrow($rating->skill_trend !== null ? (float)$rating->skill_trend : null, 'text-[9px]', 'games') ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="min-w-0 <?= $rating ? '' : 'col-span-2' ?>">
            <?php if ($rating): ?>
                <div class="flex items-baseline justify-between gap-2">
                    <span class="truncate text-base font-extrabold text-orange-300"><?= h(CalculateRatingsCommand::typeLabel($rating->type, (int)$rating->attack_pct, (int)$rating->defense_pct, (int)$rating->combat_pct)) ?></span>
                    <?php if ($rating->win_rate !== null): ?>
                        <span class="shrink-0 text-xs text-zinc-300"><?= (int)round((float)$rating->win_rate * 100) ?>% won</span>
                    <?php endif; ?>
                </div>
                <div class="mt-1.5 grid grid-cols-[3.75rem_1fr] items-center gap-x-2 gap-y-1 text-[9px] uppercase tracking-wider text-zinc-400">
                    <?php foreach (['Attack' => [$rating->attack_pct, 'bg-red-400'], 'Defense' => [$rating->defense_pct, 'bg-blue-400'], 'Combat' => [$rating->combat_pct, 'bg-amber-400']] as $label => [$value, $bar]): ?>
                        <span><?= $label ?></span>
                        <span class="h-1.5 w-full overflow-hidden rounded-full bg-white/10"><span class="block h-full rounded-full <?= $bar ?>" style="width: <?= max(2, (int)$value) ?>%"></span></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($progress): ?>
                <div class="mt-2 grid grid-cols-4 gap-1 border-t border-white/10 pt-2">
                    <?php foreach (['kills' => ['fa-skull', 'kills'], 'flags' => ['fa-flag', 'flags'], 'wins' => ['fa-trophy', 'wins'], 'mvp' => ['fa-star', 'MVP']] as $key => [$icon, $label]): ?>
                        <div class="flex items-center gap-1.5">
                            <i class="fa-solid <?= $icon ?> text-sm text-blue-400"></i>
                            <span class="leading-none">
                                <span class="block font-mono text-xs font-bold"><?= number_format((int)($progress[$key] ?? 0)) ?></span>
                                <span class="block text-[8px] uppercase tracking-wide text-zinc-400"><?= $label ?></span>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($seenLabel): ?>
        <div class="mt-1.5 text-right text-[10px] text-zinc-500"><i class="fa-regular fa-clock mr-1"></i>last seen <?= h($seenLabel) ?></div>
    <?php endif; ?>
</div>
</div>
