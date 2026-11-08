<?php
/**
 * QR hunt gold badge (unlock 'avatars2'): the player found every code. Next
 * to the name in the rankings, the Hall of Fame, the hover card and the
 * player page; QrHunt::nameStyles() says who has it.
 *
 * @var \App\View\AppView $this
 * @var bool|null $large player page / hover card size
 */
$need = \App\Service\QrHunt::UNLOCKS['avatars2'][0];
?>
<span class="inline-flex shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-yellow-200 via-yellow-400 to-amber-600 text-black shadow-[0_0_10px_rgba(250,204,21,.45)] <?= !empty($large) ? 'h-7 w-7 text-sm' : 'h-4 w-4 text-[9px]' ?>"
      title="QR hunt: every code found (<?= (int)$need ?>) - <?= h((string)\App\Service\QrHunt::levelName($need)) ?>" aria-label="QR hunt: every code found"><i class="fa-solid fa-crown"></i></span>
