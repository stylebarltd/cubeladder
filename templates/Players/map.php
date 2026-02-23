<?= $this->Html->css('https://unpkg.com/leaflet@1.9.4/dist/leaflet.css') ?>

<?= $this->Html->script('https://unpkg.com/leaflet@1.9.4/dist/leaflet.js') ?>
<div class="max-w-6xl mx-auto px-6 py-10">

    <h1 class="text-3xl font-bold mb-6 text-white text-center">
        WorldWide AC Player Map
    </h1>

    <p class="text-sm mb-6 text-center">
        Player latitude and longitude is randomized (~100 km diameter circle)
    </p>
</div>
<div id="map" style="height:600px;width:100%"></div>

<div class="max-w-6xl mx-auto px-6 py-10">



    <p class="text-sm mb-6 text-center">

                Write us a message if you wan't to <b><?= $this->Html->link(
            'OPT OUT',
            ['controller' => 'Messages', 'action' => 'sendToAdmin']
        ) ?></b>.

    </p>
</div>



<script>
    document.addEventListener('DOMContentLoaded', () => {

        const map = L.map('map').setView([20, 0], 2);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '© OpenStreetMap'
        }).addTo(map);

        fetch('<?= $this->Url->build([
            'controller' => 'Players',
            'action' => 'mapData'
        ]) ?>')
            .then(r => r.json())
            .then(players => {
                players.forEach(p => {
                    if (!p.latitude || !p.longitude) return;

                    L.circleMarker([p.latitude, p.longitude], {
                        radius: 6,
                        fillOpacity: 0.7
                    })
                        .bindPopup(`<strong><a href="/players/view/${p.id}">${p.name} (${p.country})</a></strong>`)
                        .addTo(map);
                });
            });

    });
</script>

