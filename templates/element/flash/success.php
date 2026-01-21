<?php
/**
 * @var \App\View\AppView $this
 * @var array $params
 * @var string $message
 */
if (!isset($params['escape']) || $params['escape'] !== false) {
    $message = h($message);
}
?>
<div class="text-center py-6 text-blue-500 m-5">
    <div class="message success" onclick="this.classList.add('hidden')"><?= $message ?></div>
</div>

