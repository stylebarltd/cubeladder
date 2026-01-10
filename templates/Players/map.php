<?= $this->Html->css('https://unpkg.com/leaflet@1.9.4/dist/leaflet.css') ?>

<?= $this->Html->script('https://unpkg.com/leaflet@1.9.4/dist/leaflet.js') ?>
<div id="map" style="height:600px;width:100%"></div>


<script>
    document.addEventListener('DOMContentLoaded', () => {

        const map = L.map('map').setView([20, 0], 2);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '© OpenStreetMap'
        }).addTo(map);

        fetch('<?= $this->Url->build([
            'controller' => 'Players',
            'action' => 'map'
        ]) ?>')
            .then(r => r.json())
            .then(players => {
                players.forEach(p => {
                    if (!p.latitude || !p.longitude) return;

                    L.circleMarker([p.latitude, p.longitude], {
                        radius: 6,
                        fillOpacity: 0.7
                    })
                        .bindPopup(`<strong>${p.nickname}</strong>`)
                        .addTo(map);
                });
            });

    });
</script>

