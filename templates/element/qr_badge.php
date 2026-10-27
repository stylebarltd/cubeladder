<?php
/**
 * QR hunt level badge (QrHunt): codes found in our maps, next to the other badges.
 *
 * @var \App\View\AppView $this
 * @var int $level codes found
 * @var bool|null $large player page / hover card size
 */
$name = \App\Service\QrHunt::levelName((int)$level);
if (!$name) {
    return;
}
?>
<span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-yellow-400/20 <?= !empty($large) ? 'px-3 py-1 text-sm' : 'px-2.5 py-0.5 text-xs' ?> font-semibold tracking-normal text-yellow-200 ring-1 ring-yellow-400/60"
      title="QR hunt: <?= (int)$level ?> hidden code<?= (int)$level === 1 ? '' : 's' ?> found in cubeLadder maps">
    <i class="fa-solid fa-qrcode"></i><?= h($name) ?>
</span>
