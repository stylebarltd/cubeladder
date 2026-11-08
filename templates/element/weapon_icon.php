<?php
/**
 * One weapon icon (webroot/img/weapons/<key>.svg, white). With a tint (QR hunt
 * unlock 'weapons': QrHunt::weaponColors() / the player's weapon_color, only
 * from that level on) the SVG becomes a CSS mask over the colour, else the
 * plain <img>.
 *
 * @var \App\View\AppView $this
 * @var string $key weapon icon file name without .svg
 * @var string $class size classes, e.g. 'h-4 w-4'
 * @var array|null $tint [r, g, b] or null
 * @var string|null $title
 * @var string|null $alt
 */
$src = '/img/weapons/' . rawurlencode((string)$key) . '.svg';
$class = $class ?? 'h-4 w-4';
$title = isset($title) && $title !== null ? ' title="' . h($title) . '"' : '';
if (!empty($tint)):
    $mask = "url('" . $src . "') center / contain no-repeat"; ?>
<span role="img" aria-label="<?= h($alt ?? $key) ?>"<?= $title ?> class="inline-block <?= h($class) ?>"
      style="background-color: rgb(<?= implode(',', array_map('intval', $tint)) ?>); -webkit-mask: <?= $mask ?>; mask: <?= $mask ?>;"></span>
<?php else: ?>
<img src="<?= $src ?>" alt="<?= h($alt ?? '') ?>"<?= $title ?> class="<?= h($class) ?>">
<?php endif;
