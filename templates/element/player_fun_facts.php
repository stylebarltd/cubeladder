<?php
/**
 * Fun facts strip under the player page header (PlayersController::funFacts).
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Player $player
 * @var array $funFacts
 * @var array $timePlayed
 * @var string $panel
 */

// [icon, html] per fact, in this order
$facts = [];
$minutes = (int)($timePlayed['all'] ?? 0);
if ($minutes >= 60) {
    $d = intdiv($minutes, 1440);
    $h = intdiv($minutes % 1440, 60);
    $facts[] = ['fa-hourglass-half', 'That&rsquo;s <b>' . ($d > 0 ? $d . ' day' . ($d === 1 ? '' : 's') . ' and ' : '') . $h . ' hour' . ($h === 1 ? '' : 's') . '</b> non-stop on our servers'];
}
if (!empty($funFacts['players'])) {
    $facts[] = ['fa-users', 'Met <b>' . number_format($funFacts['players']) . '</b> players from <b>' . (int)$funFacts['countries'] . '</b> countries'];
}
if (!empty($funFacts['teammate'])) {
    $facts[] = ['fa-handshake', 'Best buddy '
        . $this->Html->link(h($funFacts['teammate']['name']), ['controller' => 'Players', 'action' => 'view', $funFacts['teammate']['id']], ['class' => 'font-bold hover:text-blue-300', 'escape' => false])
        . ' &ndash; <b>' . number_format((int)$funFacts['teammate']['n']) . '</b> games on the same team'];
}
if (!empty($funFacts['server'])) {
    $facts[] = ['fa-heart', 'Favourite server <b>' . h($funFacts['server']['name']) . '</b> (' . number_format($funFacts['server']['n']) . ' games)'];
}
if (!empty($funFacts['weekday'])) {
    $facts[] = ['fa-calendar-week', 'Plays most on <b>' . h($funFacts['weekday']['day']) . 's</b>'];
}
if (!empty($funFacts['gg'])) {
    $facts[] = ['fa-comment', 'Said <b>gg</b> ' . number_format($funFacts['gg']) . ' time' . ($funFacts['gg'] === 1 ? '' : 's') . ' in the chat'];
}
if (($funFacts['names'] ?? 0) > 1) {
    $facts[] = ['fa-masks-theater', 'Played under <b>' . (int)$funFacts['names'] . '</b> different names'];
}
if (!empty($funFacts['since'])) {
    $facts[] = ['fa-seedling', 'On cubeLadder since <b>' . h((new \Cake\I18n\DateTime($funFacts['since']))->format('j M Y')) . '</b>'];
}
if (!$facts) {
    return;
}
?>
<!-- Fun facts: two columns with thin lines between them -->
<section class="<?= $panel ?> p-5 md:p-6">
    <h2 class="flex items-center gap-3 border-b border-white/10 pb-4 text-sm uppercase tracking-[0.2em] text-zinc-300">
        <i class="fa-solid fa-users text-emerald-300"></i>Fun facts
    </h2>
    <ul class="grid grid-cols-1 text-sm text-zinc-300 sm:grid-cols-2">
        <?php foreach (array_slice($facts, 0, 8) as $i => [$factIcon, $html]): ?>
            <li class="flex items-center gap-4 border-white/10 py-4 <?= $i > 0 ? 'border-t' : '' ?> <?= $i === 1 ? 'sm:border-t-0' : '' ?> <?= $i % 2 ? 'sm:border-l sm:pl-5' : 'sm:pr-5' ?>">
                <i class="fa-solid <?= $factIcon ?> w-6 shrink-0 text-center text-xl text-emerald-300"></i>
                <span class="leading-relaxed [&_b]:text-white"><?= $html ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
