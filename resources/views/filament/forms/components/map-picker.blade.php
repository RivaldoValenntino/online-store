<div x-data="{
    lat: parseFloat($wire.get('data.latitude')) || -7.227414,
    lng: parseFloat($wire.get('data.longitude')) || 107.908699,
    map: null,
    marker: null,
    isLoading: true,
    loadError: false,
    searchQuery: '',
    isSearching: false,

    init() {
        this.ensureLeaflet(() => {
            this.renderMap();
        });

        this.$wire.$watch('data.latitude', (val) => {
            let parsed = parseFloat(val);
            if (!isNaN(parsed) && this.marker && Math.abs(parsed - this.lat) > 0.0001) {
                this.lat = parsed;
                this.syncMapToCoords();
            }
        });

        this.$wire.$watch('data.longitude', (val) => {
            let parsed = parseFloat(val);
            if (!isNaN(parsed) && this.marker && Math.abs(parsed - this.lng) > 0.0001) {
                this.lng = parsed;
                this.syncMapToCoords();
            }
        });
    },

    ensureLeaflet(callback) {
        if (window.L && window.L.map) {
            this.isLoading = false;
            callback();
            return;
        }

        if (!document.getElementById('leaflet-css')) {
            let link = document.createElement('link');
            link.id = 'leaflet-css';
            link.rel = 'stylesheet';
            link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
            document.head.appendChild(link);
        }

        if (!document.getElementById('leaflet-js')) {
            let script = document.createElement('script');
            script.id = 'leaflet-js';
            script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
            script.onload = () => {
                this.isLoading = false;
                callback();
            };
            script.onerror = () => {
                this.isLoading = false;
                this.loadError = true;
            };
            document.head.appendChild(script);
        } else {
            let attempts = 0;
            let check = setInterval(() => {
                attempts++;
                if (window.L && window.L.map) {
                    clearInterval(check);
                    this.isLoading = false;
                    callback();
                } else if (attempts > 60) {
                    clearInterval(check);
                    this.isLoading = false;
                    this.loadError = true;
                }
            }, 100);
        }
    },

    renderMap() {
        let container = this.$refs.mapContainer;
        if (!container || this.map || !window.L) return;

        let curLat = parseFloat(this.$wire.get('data.latitude')) || this.lat;
        let curLng = parseFloat(this.$wire.get('data.longitude')) || this.lng;

        this.map = L.map(container, {
            center: [curLat, curLng],
            zoom: 15,
            scrollWheelZoom: true
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(this.map);

        // Custom Red SVG Pin Icon agar tidak pernah 404 / broken image
        let pinIcon = L.divIcon({
            className: 'custom-pin-marker',
            html: `<div style='transform: translate(-50%, -100%); filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3)); cursor: grab;'>
                <svg width='36' height='48' viewBox='0 0 24 24' fill='#ef4444' stroke='#ffffff' stroke-width='1.5'>
                    <path d='M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z'/>
                    <circle cx='12' cy='9' r='2.5' fill='#ffffff'/>
                </svg>
            </div>`,
            iconSize: [0, 0],
            iconAnchor: [0, 0]
        });

        this.marker = L.marker([curLat, curLng], {
            icon: pinIcon,
            draggable: true
        }).addTo(this.map);

        this.marker.on('dragend', (e) => {
            let pos = e.target.getLatLng();
            this.setCoordinates(pos.lat, pos.lng);
        });

        this.map.on('click', (e) => {
            let pos = e.latlng;
            this.marker.setLatLng(pos);
            this.setCoordinates(pos.lat, pos.lng);
        });

        setTimeout(() => {
            if (this.map) {
                this.map.invalidateSize();
            }
        }, 300);
    },

    syncMapToCoords() {
        if (this.marker && this.map) {
            let pos = [this.lat, this.lng];
            this.marker.setLatLng(pos);
            this.map.setView(pos, Math.max(this.map.getZoom(), 14));
        }
    },

    setCoordinates(newLat, newLng) {
        let fixedLat = parseFloat(Number(newLat).toFixed(7));
        let fixedLng = parseFloat(Number(newLng).toFixed(7));
        this.lat = fixedLat;
        this.lng = fixedLng;
        this.$wire.set('data.latitude', fixedLat);
        this.$wire.set('data.longitude', fixedLng);
    },

    searchLocation() {
        if (!this.searchQuery.trim()) return;
        this.isSearching = true;

        fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(this.searchQuery) + '&countrycodes=id&limit=1')
            .then(res => res.json())
            .then(data => {
                this.isSearching = false;
                if (data && data.length > 0) {
                    let foundLat = parseFloat(data[0].lat);
                    let foundLng = parseFloat(data[0].lon);
                    this.setCoordinates(foundLat, foundLng);
                    if (this.map && this.marker) {
                        let pos = [foundLat, foundLng];
                        this.marker.setLatLng(pos);
                        this.map.setView(pos, 16);
                    }
                } else {
                    alert('Lokasi tidak ditemukan. Coba ketik nama jalan / kecamatan / kota lain.');
                }
            })
            .catch(() => {
                this.isSearching = false;
                alert('Gagal mencari lokasi via OpenStreetMap.');
            });
    },

    locateMe() {
        if (!navigator.geolocation) {
            alert('Geolokasi tidak didukung oleh browser Anda.');
            return;
        }
        navigator.geolocation.getCurrentPosition((pos) => {
            let myLat = parseFloat(pos.coords.latitude.toFixed(7));
            let myLng = parseFloat(pos.coords.longitude.toFixed(7));
            this.setCoordinates(myLat, myLng);
            if (this.map && this.marker) {
                let coords = [myLat, myLng];
                this.marker.setLatLng(coords);
                this.map.setView(coords, 16);
            }
        }, (err) => {
            alert('Gagal mendeteksi lokasi GPS: ' + err.message);
        });
    }
}" x-init="init()" class="space-y-3 w-full">

    {{-- Header Peta & Quick Tools --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
        <span class="font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
            <span class="inline-block w-2.5 h-2.5 rounded-full bg-red-500"></span>
            <span>Titik Penjemputan Paket (Klik peta atau geser pin merah)</span>
        </span>

        <button type="button" @click="locateMe()"
            class="font-semibold text-primary-600 dark:text-primary-400 hover:text-primary-700 inline-flex items-center gap-1.5 cursor-pointer self-start sm:self-auto py-1 px-2.5 rounded-md bg-primary-50 dark:bg-primary-950/40 border border-primary-200 dark:border-primary-800 transition">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span>Deteksi GPS Saya</span>
        </button>
    </div>

    {{-- Mini Search Box untuk Cari Tempat Langsung di Peta --}}
    <div class="flex items-center gap-2">
        <div class="relative flex-1">
            <input type="text"
                x-model="searchQuery"
                @keydown.enter.prevent="searchLocation()"
                placeholder="Cari lokasi gudang / nama jalan / area di peta..."
                class="w-full text-xs rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-1.5 text-gray-800 dark:text-gray-200 focus:border-primary-500 focus:ring-1 focus:ring-primary-500" />
        </div>
        <button type="button"
            @click="searchLocation()"
            :disabled="isSearching"
            class="text-xs px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 font-medium text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-700 flex items-center gap-1 shrink-0 transition">
            <span x-show="!isSearching">Cari di Peta</span>
            <span x-show="isSearching" class="inline-flex items-center gap-1">
                <svg class="animate-spin h-3 w-3" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                Mencari...
            </span>
        </button>
    </div>

    {{-- Map Container --}}
    <div class="relative w-full rounded-xl overflow-hidden border border-gray-300 dark:border-gray-700 shadow-xs" style="height: 320px; min-height: 320px;">
        {{-- Loading Overlay --}}
        <div x-show="isLoading" class="absolute inset-0 z-10 flex flex-col items-center justify-center bg-gray-50/90 dark:bg-gray-900/90 gap-2">
            <svg class="animate-spin h-6 w-6 text-primary-600" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
            </svg>
            <span class="text-xs text-gray-500 font-medium">Memuat peta Leaflet OpenStreetMap...</span>
        </div>

        {{-- Error State --}}
        <div x-show="loadError" x-cloak class="absolute inset-0 z-10 flex flex-col items-center justify-center bg-gray-100 dark:bg-gray-900 p-4 text-center">
            <p class="text-xs text-red-600 font-semibold mb-1">Gagal memuat peta OpenStreetMap</p>
            <p class="text-[11px] text-gray-500">Periksa koneksi internet Anda atau masukkan Latitude & Longitude secara manual pada input di atas.</p>
        </div>

        <div x-ref="mapContainer" style="height: 100%; width: 100%; position: relative; z-index: 1;"></div>
    </div>

    {{-- Coordinates Info Helper --}}
    <div class="flex items-center justify-between text-[11px] text-gray-500 dark:text-gray-400">
        <span>* Koordinat tersinkronisasi otomatis dengan field <b>Latitude</b> & <b>Longitude</b>.</span>
        <span class="font-mono text-gray-600 dark:text-gray-300">
            [<span x-text="lat"></span>, <span x-text="lng"></span>]
        </span>
    </div>
</div>
