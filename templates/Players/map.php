<?php
/**
 * World map of the players, full screen in the site style: dark map tiles,
 * info panel on top with opacity. Positions are randomized (~100 km).
 *
 * @var \App\View\AppView $this
 */
?>
<?= $this->Html->css('https://unpkg.com/leaflet@1.9.4/dist/leaflet.css') ?>
<?= $this->Html->script('https://unpkg.com/leaflet@1.9.4/dist/leaflet.js') ?>
<style>
#player-map { background: #0b0b0e; }
#player-map .leaflet-tile-pane { filter: invert(1) hue-rotate(180deg) brightness(.85) contrast(.9) saturate(.6); }
#player-map .leaflet-popup-content-wrapper, #player-map .leaflet-popup-tip {
    background: rgb(0 0 0 / .85); color: #fff; border: 1px solid rgb(255 255 255 / .15); box-shadow: 0 10px 30px rgb(0 0 0 / .6);
}
#player-map .leaflet-popup-content { margin: .6rem .8rem; font-family: inherit; }
#player-map .leaflet-popup-content a { color: #fff; }
#player-map .leaflet-popup-close-button { color: #a1a1aa; }
#player-map .leaflet-control-zoom a { background: rgb(0 0 0 / .7); color: #fff; border-color: rgb(255 255 255 / .15); }
#player-map .leaflet-control-attribution { background: rgb(0 0 0 / .55); color: #a1a1aa; }
#player-map .leaflet-control-attribution a { color: #d4d4d8; }
</style>

<div class="relative h-[calc(100svh-4rem)] w-full text-white">
    <div id="player-map" class="absolute inset-0 z-0"></div>

    <!-- Info panel -->
    <div class="pointer-events-none absolute inset-x-0 top-0 z-[500] mx-auto w-full max-w-7xl px-4 py-5 md:px-16 md:py-8">
        <div class="pointer-events-auto inline-block max-w-md rounded-xl border border-white/15 bg-black/60 p-5 backdrop-blur-[2px]">
            <h1 class="text-2xl md:text-4xl font-extrabold tracking-wide">
                <i class="fa-solid fa-earth-europe mr-2 text-green-400"></i>Player map
            </h1>
            <p class="mt-2 text-sm text-zinc-300">Where in the world cubeLadder players come from.</p>
            <div class="mt-4 flex gap-3">
                <div class="rounded-lg bg-white/5 px-4 py-2 text-center">
                    <div id="map-players" class="font-mono text-2xl font-extrabold">&hellip;</div>
                    <div class="text-[10px] uppercase tracking-wider text-zinc-400">players</div>
                </div>
                <div class="rounded-lg bg-white/5 px-4 py-2 text-center">
                    <div id="map-countries" class="font-mono text-2xl font-extrabold">&hellip;</div>
                    <div class="text-[10px] uppercase tracking-wider text-zinc-400">countries</div>
                </div>
            </div>
            <p class="mt-3 text-[11px] text-zinc-400">
                <i class="fa-solid fa-shield-halved mr-1"></i>Positions are randomized within ~100&nbsp;km.
            </p>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const map = L.map('player-map', { worldCopyJump: true, zoomControl: false }).setView([25, 10], 3);
        L.control.zoom({ position: 'bottomright' }).addTo(map);

        // standard OSM tiles, darkened in CSS (.leaflet-tile-pane) to match the site
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(map);

        const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        const flag = (iso) => /^[A-Za-z]{2}$/.test(iso || '')
            ? String.fromCodePoint(...[...iso.toUpperCase()].map(c => 0x1F1E6 + c.charCodeAt(0) - 65)) : '';

        fetch('<?= $this->Url->build(['controller' => 'Players', 'action' => 'mapData']) ?>')
            .then(r => r.json())
            .then(players => {
                const countries = new Set();
                let shown = 0;
                players.forEach(p => {
                    if (!p.latitude || !p.longitude) return;
                    shown++;
                    if (p.country) countries.add(p.country);
                    L.circleMarker([p.latitude, p.longitude], {
                        radius: 4, color: '#93c5fd', weight: 1, fillColor: '#3b82f6', fillOpacity: 0.8
                    })
                        .bindPopup(
                            '<a href="/players/view/' + esc(p.id) + '" class="flex items-center gap-2 font-semibold hover:text-blue-300">' +
                                '<img src="' + (p.picture ? '/img/players/' + esc(p.picture) : '/img/acl.png') + '" class="h-8 w-8 rounded-full object-cover" alt="">' +
                                '<span>' + esc(p.name) + ' ' + flag(p.country) + '</span>' +
                            '</a>'
                        )
                        .addTo(map);
                });
                document.getElementById('map-players').textContent = shown.toLocaleString();
                document.getElementById('map-countries').textContent = countries.size.toLocaleString();
            });
    });
</script>
