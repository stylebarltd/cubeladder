<?php
/**
 * @var \App\View\AppView $this
 * @var array $params
 * @var string $message
 */
$class = 'message';
if (!empty($params['class'])) {
    $class .= ' ' . $params['class'];
}
if (!isset($params['escape']) || $params['escape'] !== false) {
    $message = h($message);
}
?>

<div class="text-center py-6 text-blue-500 m-5">
    <div class="<?= h($class) ?>" onclick="this.classList.add('hidden');"><?= $message ?></div>
</div>


