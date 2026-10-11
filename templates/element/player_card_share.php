<?php
/**
 * Share button for the player card (PlayersController::card): on phones the
 * system share sheet with the card picture, elsewhere a menu - copy link
 * (Discord & co. show the card as the link preview), WhatsApp, Telegram, X,
 * Facebook, Reddit and downloading the card.
 *
 * @var \App\View\AppView $this
 * @var string $url player page URL
 * @var string $card card picture path (/players/card/<id>?v=...)
 * @var string $text share text
 * @var string $file download file name
 */
$u = rawurlencode($url);
$t = rawurlencode($text);
$links = [
    ['fa-brands fa-whatsapp', 'WhatsApp', 'https://wa.me/?text=' . rawurlencode($text . ' ' . $url)],
    ['fa-brands fa-telegram', 'Telegram', 'https://t.me/share/url?url=' . $u . '&text=' . $t],
    ['fa-brands fa-x-twitter', 'X', 'https://x.com/intent/post?url=' . $u . '&text=' . $t],
    ['fa-brands fa-facebook', 'Facebook', 'https://www.facebook.com/sharer/sharer.php?u=' . $u],
    ['fa-brands fa-reddit', 'Reddit', 'https://www.reddit.com/submit?url=' . $u . '&title=' . $t],
];
$item = 'flex w-full items-center gap-3 rounded-md px-3 py-2 text-left text-sm text-zinc-200 hover:bg-white/10';
?>
<details class="player-share relative" data-url="<?= h($url) ?>" data-card="<?= h($card) ?>" data-text="<?= h($text) ?>" data-file="<?= h($file) ?>">
    <summary class="list-none cursor-pointer select-none rounded-lg border border-emerald-400/50 bg-black/40 px-4 py-2 text-sm font-semibold text-emerald-300 hover:bg-emerald-500/20 transition [&::-webkit-details-marker]:hidden">
        <i class="fa-solid fa-share-nodes mr-1"></i> Share card
    </summary>
    <div class="absolute left-0 z-30 mt-2 w-72 max-w-[calc(100vw-2rem)] rounded-xl border border-white/15 bg-zinc-950 p-2 shadow-2xl">
        <a href="<?= h($card) ?>" target="_blank" rel="noopener" class="mb-2 block overflow-hidden rounded-lg ring-1 ring-white/10">
            <img src="<?= h($card) ?>" alt="Player card" loading="lazy" class="block w-full" width="1200" height="630">
        </a>
        <button type="button" data-share="native" class="<?= $item ?> hidden"><i class="fa-solid fa-share-from-square w-5 text-center"></i>Share&hellip;</button>
        <button type="button" data-share="copy" class="<?= $item ?>"><i class="fa-solid fa-link w-5 text-center"></i><span>Copy link <span class="text-zinc-400">(Discord shows the card)</span></span></button>
        <?php foreach ($links as [$icon, $label, $href]): ?>
            <a href="<?= h($href) ?>" target="_blank" rel="noopener" class="<?= $item ?>"><i class="<?= $icon ?> w-5 text-center"></i><?= $label ?></a>
        <?php endforeach; ?>
        <a href="<?= h($card) ?>" download="<?= h($file) ?>" class="<?= $item ?>"><i class="fa-solid fa-download w-5 text-center"></i>Download card</a>
    </div>
</details>
<script>
(() => {
    const box = document.currentScript.previousElementSibling;
    const { url, card, text, file } = box.dataset;
    const native = box.querySelector('[data-share="native"]');
    if (navigator.share) {
        native.classList.remove('hidden');
    }
    native.addEventListener('click', async () => {
        const data = { title: document.title, text, url };
        try {
            // with the card picture where the browser can share files (phones)
            const blob = await (await fetch(card)).blob();
            const img = new File([blob], file, { type: blob.type || 'image/jpeg' });
            if (navigator.canShare && navigator.canShare({ files: [img] })) {
                data.files = [img];
            }
        } catch (e) {}
        try {
            await navigator.share(data);
            box.open = false;
        } catch (e) {}
    });
    const copy = box.querySelector('[data-share="copy"]');
    copy.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(url);
        } catch (e) {
            const tmp = Object.assign(document.createElement('textarea'), { value: url });
            document.body.append(tmp);
            tmp.select();
            document.execCommand('copy');
            tmp.remove();
        }
        const label = copy.innerHTML;
        copy.innerHTML = '<i class="fa-solid fa-check w-5 text-center text-emerald-400"></i>Link copied';
        setTimeout(() => { copy.innerHTML = label; }, 1800);
    });
    // close when clicking elsewhere
    document.addEventListener('click', (e) => {
        if (box.open && !box.contains(e.target)) {
            box.open = false;
        }
    });
})();
</script>
