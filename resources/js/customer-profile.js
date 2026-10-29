document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('customer-profile-form');
    if (!form) return;

    const address = document.getElementById('profile-address');
    const reference = document.getElementById('profile-reference');
    const lat = document.getElementById('profile-latitude');
    const lng = document.getElementById('profile-longitude');
    const status = document.getElementById('profile-location-status');
    let map = null;
    let marker = null;

    const clearPoint = () => {
        lat.value = '';
        lng.value = '';
        if (marker && map) map.removeLayer(marker);
        marker = null;
        status.textContent = 'Vuelve a marcar el punto que corresponde a la dirección.';
    };

    address.addEventListener('input', clearPoint);
    document.getElementById('profile-clear-address').addEventListener('click', () => {
        address.value = '';
        reference.value = '';
        clearPoint();
        status.textContent = 'La dirección se quitará de tu cuenta al pulsar Guardar mis datos.';
    });

    if (!window.L) {
        status.textContent = 'No se pudo cargar el mapa. Recarga para seleccionar una ubicación.';
        return;
    }
    const L = window.L;
    map = L.map('profile-map').setView([-12.0464, -77.0428], 12);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        referrerPolicy: 'origin',
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" rel="noopener noreferrer">OpenStreetMap</a> contributors',
    }).addTo(map);

    const selectPoint = (latitude, longitude) => {
        if (!Number.isFinite(latitude) || !Number.isFinite(longitude)
            || Math.abs(latitude) > 90 || Math.abs(longitude) > 180) return;
        lat.value = latitude.toFixed(7);
        lng.value = longitude.toFixed(7);
        if (marker) {
            marker.setLatLng([latitude, longitude]);
        } else {
            marker = L.marker([latitude, longitude], {draggable: true}).addTo(map);
            marker.on('dragend', () => {
                const point = marker.getLatLng();
                selectPoint(point.lat, point.lng);
            });
        }
        status.textContent = 'Ubicación seleccionada. Revisa el punto y pulsa Guardar mis datos.';
    };

    map.on('click', event => selectPoint(event.latlng.lat, event.latlng.lng));
    if (lat.value !== '' && lng.value !== '') {
        selectPoint(Number(lat.value), Number(lng.value));
        if (marker) {
            map.setView(marker.getLatLng(), 17);
            status.textContent = 'Revisa la ubicación mostrada antes de guardar tus datos.';
        }
    }

    document.getElementById('profile-use-location').addEventListener('click', () => {
        if (!navigator.geolocation) {
            status.textContent = 'Tu navegador no admite GPS. Puedes marcar el punto en el mapa.';
            return;
        }
        status.textContent = 'Buscando tu ubicación…';
        navigator.geolocation.getCurrentPosition(position => {
            selectPoint(position.coords.latitude, position.coords.longitude);
            if (marker) map.setView(marker.getLatLng(), 17);
        }, () => {
            status.textContent = 'No se pudo obtener tu ubicación. Selecciona el punto en el mapa.';
        }, {enableHighAccuracy: true, timeout: 15000, maximumAge: 0});
    });
});
