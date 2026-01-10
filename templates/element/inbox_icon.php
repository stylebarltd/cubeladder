<a href="<?= $this->Url->build('/messages/inbox') ?>" class="nav-link position-relative">
    <i class="fa fa-inbox"></i>

    <?php if (!empty($inboxUnread) && $inboxUnread > 0): ?>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
            <?= $inboxUnread ?>
        </span>
    <?php endif ?>
</a>
