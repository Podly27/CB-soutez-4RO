<link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css"
    integrity="sha512-xodZBNTC5n17Xt2atTPuE1HxjVMSvLVW9ocqUKLsCC5CXdbqCmblAshOMAS6/keqq/sMZMZ19scR4PsZChSR7A=="
    crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"
    integrity="sha512-XQoYMqMTK8LvdxXYG3nZ448hOEQiglfqkJs1NOQV44cWnUrBc8PkAOcXy20w0vlaXaVUearIOBhiXZ5V3ynxwA=="
    crossorigin=""></script>

<script>
    var zoom = 5
    if (window.matchMedia('(min-width: 992px)').matches) {
        zoom = 6.5;
    } else if (window.matchMedia('(min-width: 768px)').matches) {
        zoom = 6;
    } else if (window.matchMedia('(min-width: 576px)').matches) {
        zoom = 5.5;
    }

    var defaultCenterPos = [49.06685705, 17.8955521]
    var map = L.map('map', {zoomSnap: 0.5, zoomDelta: 0.5, wheelPxPerZoomLevel: 120, tap: false}).setView(defaultCenterPos, zoom);

    const mapboxAccessToken = @json(config("ctvero.mapboxAccessToken"));
    const mapboxTilesUrl = 'https://api.mapbox.com/styles/v1/{id}/tiles/{z}/{x}/{y}?access_token={accessToken}';
    const osmTilesUrl = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
    const tilesUrl = mapboxAccessToken ? mapboxTilesUrl : osmTilesUrl;
    const tilesOptions = mapboxAccessToken ? {
        attribution: 'Map data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors | Imagery &copy; <a href="https://www.mapbox.com">Mapbox</a>',
        id: 'mapbox/outdoors-v11',
        tileSize: 512,
        zoomOffset: -1,
        accessToken: mapboxAccessToken,
    } : {
        attribution: 'Map data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19,
    };

    L.tileLayer(tilesUrl, tilesOptions).addTo(map);
</script>
