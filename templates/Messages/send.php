<div class="container mt-4">
    <h2>Send Message to <?= h($receiver->name) ?></h2>

    <?= $this->Form->create($message) ?>
    <div class="mb-3">
        <?= $this->Form->control('subject', [
            'class' => 'form-control',
            'label' => 'Subject'
        ]) ?>
    </div>

    <div class="mb-3">
        <?= $this->Form->control('body', [
            'type' => 'textarea',
            'rows' => 6,
            'class' => 'form-control',
            'label' => 'Message'
        ]) ?>
    </div>

    <button class="btn btn-primary">Send</button>
    <?= $this->Form->end() ?>
</div>
