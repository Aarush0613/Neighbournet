/**
 * NeighbourNet — Interactive Community Map Engine
 * Powered by Leaflet.js & OpenStreetMap
 * Prompt 6: Full filters, custom category markers, geolocation & popups
 */

const NeighbourMap = {
    map: null,
    markersGroup: null,
    markerByIncident: new Map(),
    userLocationMarker: null,
    userAccuracyCircle: null,
    allIncidents: [],
    categories: {},
    defaultCenter: [28.6139, 77.2090], // Metro Area default
    defaultZoom: 14,

    /**
     * Initialize Leaflet Map
     */
    init() {
        const mapContainer = document.getElementById('communityMap');
        if (!mapContainer) return;

        this.map = L.map('communityMap', {
            zoomControl: false // Reposition zoom control for better mobile layout
        }).setView(this.defaultCenter, this.defaultZoom);

        // Add Zoom Control at bottom right
        L.control.zoom({ position: 'bottomright' }).addTo(this.map);

        // OpenStreetMap Tile Layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(this.map);

        this.markersGroup = L.featureGroup().addTo(this.map);

        // Wire event listeners
        this.bindEvents();

        // Initial Data Fetch
        this.fetchIncidents();
    },

    /**
     * Bind UI & Filter Events
     */
    bindEvents() {
        // Filter inputs
        const filterCategory = document.getElementById('filterCategory');
        const filterStatus   = document.getElementById('filterStatus');
        const filterDate     = document.getElementById('filterDate');
        const filterSociety  = document.getElementById('filterSociety');
        const searchInput    = document.getElementById('mapSearchInput');
        const btnReset       = document.getElementById('btnResetFilters');
        const btnRefresh     = document.getElementById('btnRefreshMap');
        const btnLocateMe    = document.getElementById('btnLocateMe');
        const btnFitBounds   = document.getElementById('btnFitBounds');

        const triggerFilter = () => this.fetchIncidents();

        if (filterCategory) filterCategory.addEventListener('change', triggerFilter);
        if (filterStatus) filterStatus.addEventListener('change', triggerFilter);
        if (filterDate) filterDate.addEventListener('change', triggerFilter);
        if (filterSociety) filterSociety.addEventListener('change', triggerFilter);

        // Debounced search input
        if (searchInput) {
            let debounceTimer = null;
            searchInput.addEventListener('input', () => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => triggerFilter(), 350);
            });
        }

        if (btnReset) {
            btnReset.addEventListener('click', () => {
                if (filterCategory) filterCategory.value = '0';
                if (filterStatus) filterStatus.value = 'all';
                if (filterDate) filterDate.value = 'all';
                if (filterSociety) filterSociety.value = '0';
                if (searchInput) searchInput.value = '';
                triggerFilter();
            });
        }

        if (btnRefresh) {
            btnRefresh.addEventListener('click', () => triggerFilter());
        }

        if (btnLocateMe) {
            btnLocateMe.addEventListener('click', () => this.locateUser());
        }

        if (btnFitBounds) {
            btnFitBounds.addEventListener('click', () => this.fitToMarkers());
        }
    },

    /**
     * Fetch Incidents from PHP API
     */
    async fetchIncidents() {
        const loadingIndicator = document.getElementById('mapLoading');
        const emptyStateNotice = document.getElementById('mapEmptyNotice');
        const sidebarList = document.getElementById('mapIncidentList');
        const countBadge = document.getElementById('visibleIncidentCount');

        if (loadingIndicator) loadingIndicator.classList.remove('d-none');
        if (emptyStateNotice) emptyStateNotice.classList.add('d-none');

        // Build query string
        const categoryId = document.getElementById('filterCategory')?.value || '0';
        const status     = document.getElementById('filterStatus')?.value || 'all';
        const dateRange  = document.getElementById('filterDate')?.value || 'all';
        const societyId  = document.getElementById('filterSociety')?.value || '0';
        const search     = document.getElementById('mapSearchInput')?.value.trim() || '';

        const params = new URLSearchParams();
        if (categoryId !== '0') params.append('category_id', categoryId);
        if (status !== 'all') params.append('status', status);
        if (dateRange !== 'all') params.append('date_range', dateRange);
        if (societyId !== '0') params.append('society_id', societyId);
        if (search) params.append('search', search);

        try {
            const url = `/neighbournet/api/incidents/list.php?${params.toString()}`;
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();

            if (!res.ok || !data.success) {
                throw new Error(data.message || 'Error loading incident data');
            }

            this.allIncidents = data.incidents || [];
            this.renderMarkers(this.allIncidents);
            this.renderSidebarList(this.allIncidents);

            if (countBadge) {
                countBadge.innerText = `${this.allIncidents.length} Reported`;
            }

            if (this.allIncidents.length === 0 && emptyStateNotice) {
                emptyStateNotice.classList.remove('d-none');
            }

        } catch (err) {
            console.error('Map fetch error:', err);
            if (sidebarList) {
                sidebarList.innerHTML = `
                    <div class="alert alert-danger small p-2 m-2">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i>
                        Could not load incidents. Please verify database connection.
                    </div>
                `;
            }
        } finally {
            if (loadingIndicator) loadingIndicator.classList.add('d-none');
        }
    },

    /**
     * Render Markers onto Map with Custom divIcon
     */
    renderMarkers(incidents) {
        const visibleIds = new Set();
        let plottedCount = 0;

        incidents.forEach(inc => {
            if (inc.latitude === null || inc.longitude === null || !Number.isFinite(Number(inc.latitude)) || !Number.isFinite(Number(inc.longitude))) return;
            const lat = Number(inc.latitude), lng = Number(inc.longitude);
            if ((lat === 0 && lng === 0) || lat < -90 || lat > 90 || lng < -180 || lng > 180) return;

            const id = Number(inc.id);
            visibleIds.add(id);
            const status = ['reported','pending','under_verification','verified','in_progress','resolved'].includes(inc.status) ? inc.status : 'reported';
            const color = /^#[0-9a-f]{6}$/i.test(String(inc.color_code || '')) ? inc.color_code : '#2563eb';
            const iconClass = /^fa-[a-z0-9-]+$/i.test(String(inc.icon_class || '')) ? inc.icon_class : 'fa-triangle-exclamation';
            const category = this.escapeHtml(inc.category_name || 'Community report');
            const title = this.escapeHtml(inc.title || 'Untitled report');
            const location = this.escapeHtml(inc.location_text || 'Location not provided');
            const statusLabel = status.replaceAll('_', ' ');

            const customIcon = L.divIcon({
                className: 'leaflet-civic-pin',
                html: `<div class="civic-pin-badge pin-status-${status}" style="background-color:${color}" title="${title}"><i class="fa-solid ${iconClass}"></i></div>`,
                iconSize: [36, 36], iconAnchor: [18, 18], popupAnchor: [0, -20]
            });
            const popupHtml = `
                <div class="map-popup-card">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="category-pill" style="font-size:.72rem"><i class="fa-solid ${iconClass}" style="color:${color}"></i> ${category}</span>
                        <span class="status-pill status-${status}" style="font-size:.68rem">${this.escapeHtml(statusLabel)}</span>
                    </div>
                    <div class="popup-title">${title}</div>
                    <div class="popup-location"><i class="fa-solid fa-location-dot text-danger me-1"></i>${location}</div>
                    ${inc.cluster_id ? `<div class="small mt-2"><span class="badge ${inc.cluster_status === 'approved' ? 'bg-success' : 'bg-warning text-dark'}">${inc.cluster_status === 'approved' ? 'Reviewed cluster' : 'Suggested related reports'}</span> <span class="text-muted">${Number(inc.cluster_report_count)||0} reports · not independent confirmations</span></div>` : ''}
                    <div class="popup-footer"><small class="text-muted">${this.escapeHtml(new Date(inc.incident_time).toLocaleDateString())}</small> <a href="/neighbournet/pages/incident-details.php?id=${id}" class="btn btn-sm btn-civic-primary py-1 px-2">View Details</a></div>
                </div>`;

            let marker = this.markerByIncident.get(id);
            if (marker) {
                marker.setLatLng([lat, lng]);
                marker.setIcon(customIcon);
                marker.setPopupContent(popupHtml);
            } else {
                marker = L.marker([lat, lng], { icon: customIcon });
                marker.bindPopup(popupHtml);
                this.markerByIncident.set(id, marker);
                this.markersGroup.addLayer(marker);
            }
            plottedCount++;
        });

        // Remove only markers that no longer match the active server-side filters.
        for (const [id, marker] of this.markerByIncident.entries()) {
            if (!visibleIds.has(id)) {
                this.markersGroup.removeLayer(marker);
                this.markerByIncident.delete(id);
            }
        }
        if (plottedCount > 0 && this.markersGroup.getLayers().length > 0) {
            this.map.fitBounds(this.markersGroup.getBounds().pad(0.12));
        }
    },

    /**
     * Render Incidents into Sidebar List
     */
    renderSidebarList(incidents) {
        const container = document.getElementById('mapIncidentList');
        if (!container) return;

        if (incidents.length === 0) {
            container.innerHTML = `
                <div class="text-center py-4 text-muted small">
                    <i class="fa-solid fa-map-location-dot fs-3 mb-2 text-secondary"></i>
                    <p class="mb-1">No incidents match your current filters.</p>
                    <button class="btn btn-sm btn-outline-primary mt-1" onclick="document.getElementById('btnResetFilters').click()">
                        Reset Filters
                    </button>
                </div>
            `;
            return;
        }

        let html = '';
        incidents.forEach(inc => {
            const hasGps = (inc.latitude !== null && inc.longitude !== null);
            html += `
                <div class="p-3 bg-white rounded-2 border mb-2 incident-list-item" style="cursor: pointer;" onclick="NeighbourMap.focusIncident(${inc.latitude}, ${inc.longitude}, ${inc.id})">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="category-pill" style="font-size: 0.7rem;">
                            <i class="fa-solid ${inc.icon_class}" style="color: ${inc.color_code}"></i> ${inc.category_name}
                        </span>
                        <span class="status-pill status-${inc.status}" style="font-size: 0.68rem;">
                            ${inc.status.replace('_', ' ')}
                        </span>
                    </div>
                    <div class="fw-bold text-navy small mb-1">${this.escapeHtml(inc.title)}</div>
                    <div class="small text-muted d-flex align-items-center justify-content-between">
                        <span><i class="fa-solid fa-location-dot text-danger me-1"></i>${this.escapeHtml(inc.location_text)}</span>
                        ${!hasGps ? '<span class="badge bg-light text-secondary border">No GPS</span>' : ''}
                    </div>
                    ${inc.cluster_id ? `<div class="small mt-2"><span class="badge ${inc.cluster_status === 'approved' ? 'bg-success' : 'bg-warning text-dark'}">${inc.cluster_status === 'approved' ? 'Reviewed cluster' : 'Suggested cluster'}</span> <span class="text-muted">${inc.cluster_report_count} related reports</span></div>` : ''}
                    <div class="mt-2"><a class="small text-primary" href="/neighbournet/pages/incident-details.php?id=${inc.id}">View report and related incidents</a></div>
                </div>
            `;
        });

        container.innerHTML = html;
    },

    /**
     * Center map and open popup on incident click from list
     */
    focusIncident(lat, lng, id) {
        if (!lat || !lng) {
            alert('This incident does not have GPS coordinates attached.');
            return;
        }
        this.map.setView([lat, lng], 17, { animate: true });

        // Open matching popup
        this.markersGroup.eachLayer(layer => {
            const pos = layer.getLatLng();
            if (Math.abs(pos.lat - lat) < 0.0001 && Math.abs(pos.lng - lng) < 0.0001) {
                layer.openPopup();
            }
        });
    },

    /**
     * Locate User's Current GPS Position
     */
    locateUser() {
        const btnLocate = document.getElementById('btnLocateMe');
        if (!navigator.geolocation) {
            alert('Geolocation is not supported by your browser.');
            return;
        }

        if (btnLocate) {
            btnLocate.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Locating...';
            btnLocate.disabled = true;
        }

        navigator.geolocation.getCurrentPosition(
            (pos) => {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                const accuracy = pos.coords.accuracy;

                // Remove previous user markers
                if (this.userLocationMarker) this.map.removeLayer(this.userLocationMarker);
                if (this.userAccuracyCircle) this.map.removeLayer(this.userAccuracyCircle);

                // Add Accuracy Circle
                this.userAccuracyCircle = L.circle([lat, lng], {
                    radius: Math.min(accuracy, 1000),
                    color: '#2563eb',
                    fillColor: '#bfdbfe',
                    fillOpacity: 0.2,
                    weight: 1
                }).addTo(this.map);

                // Add Pulsing Location Pin
                const userIcon = L.divIcon({
                    className: 'user-location-marker',
                    iconSize: [16, 16],
                    iconAnchor: [8, 8]
                });
                this.userLocationMarker = L.marker([lat, lng], { icon: userIcon })
                    .addTo(this.map)
                    .bindPopup("<b>You are here</b><br>Showing nearby neighbourhood incidents.")
                    .openPopup();

                this.map.setView([lat, lng], 15, { animate: true });

                if (btnLocate) {
                    btnLocate.innerHTML = '<i class="fa-solid fa-location-crosshairs text-success me-1"></i> Located';
                    btnLocate.disabled = false;
                }
            },
            (err) => {
                alert(`Could not detect location: ${err.message}. Please verify browser GPS permission.`);
                if (btnLocate) {
                    btnLocate.innerHTML = '<i class="fa-solid fa-location-crosshairs text-primary me-1"></i> My Location';
                    btnLocate.disabled = false;
                }
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    },

    /**
     * Fit Map to all visible markers
     */
    fitToMarkers() {
        if (this.markersGroup && this.markersGroup.getLayers().length > 0) {
            this.map.fitBounds(this.markersGroup.getBounds().pad(0.12));
        } else {
            this.map.setView(this.defaultCenter, this.defaultZoom);
        }
    },

    escapeHtml(str) {
        if (!str) return '';
        return str
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
};

window.NeighbourMap = NeighbourMap;

// Initialize on DOM load
document.addEventListener('DOMContentLoaded', () => {
    NeighbourMap.init();
});
