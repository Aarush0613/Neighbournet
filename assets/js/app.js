/**
 * NeighbourNet - Global Application Logic
 * Tagline: Report It. Verify It. Act Together.
 */

const NeighbourNet = {
    /**
     * Reusable JSON Fetch wrapper
     */
    async api(url, options = {}) {
        const defaults = {
            headers: {
                'Accept': 'application/json'
            }
        };

        if (options.body && !(options.body instanceof FormData)) {
            defaults.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(options.body);
        }

        const config = { ...defaults, ...options };

        try {
            const res = await fetch(url, config);
            const data = await res.json();
            if (!res.ok) {
                throw new Error(data.message || `HTTP ${res.status}`);
            }
            return data;
        } catch (err) {
            console.error('API Request Failed:', err);
            throw err;
        }
    },

    /**
     * Show Bootstrap Alert Notification dynamically
     */
    showAlert(message, type = 'success', containerId = 'alert-container') {
        let container = document.getElementById(containerId);
        if (!container) {
            container = document.createElement('div');
            container.id = 'alert-container';
            container.style.position = 'fixed';
            container.style.top = '20px';
            container.style.right = '20px';
            container.style.zIndex = '9999';
            container.style.maxWidth = '360px';
            document.body.appendChild(container);
        }

        const alertEl = document.createElement('div');
        alertEl.className = `alert alert-${type} alert-dismissible fade show shadow-sm`;
        alertEl.role = 'alert';
        const body = document.createElement('div');
        body.textContent = String(message ?? '');
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'btn-close';
        close.setAttribute('aria-label', 'Close notification');
        close.setAttribute('data-bs-dismiss', 'alert');
        alertEl.append(body, close);

        container.prepend(alertEl);
        setTimeout(() => {
            alertEl.classList.remove('show');
            setTimeout(() => alertEl.remove(), 250);
        }, 4500);
    },

    /**
     * Format Status to Badge HTML
     */
    formatStatusBadge(status) {
        const map = {
            reported: { label: 'Reported', cls: 'status-reported' },
            verified: { label: 'Verified', cls: 'status-verified' },
            in_progress: { label: 'In Progress', cls: 'status-in_progress' },
            resolved: { label: 'Resolved', cls: 'status-resolved' }
        };
        const item = map[status] || { label: status, cls: 'bg-secondary text-white' };
        return `<span class="status-pill ${item.cls}">${item.label}</span>`;
    },

    /**
     * Format Severity Badge HTML
     */
    formatSeverityBadge(severity) {
        const map = {
            low: 'badge-sev-low',
            medium: 'badge-sev-medium',
            high: 'badge-sev-high',
            critical: 'badge-sev-critical'
        };
        const cls = map[severity] || 'bg-secondary';
        return `<span class="badge ${cls} text-uppercase px-2 py-1">${severity}</span>`;
    }
};

window.NeighbourNet = NeighbourNet;

// Smooth scrolling and interactive pin click handling
document.addEventListener('DOMContentLoaded', () => {
    // Hero preview pins click interaction
    document.querySelectorAll('.map-pin-marker').forEach(pin => {
        pin.addEventListener('click', () => {
            const label = pin.innerText.trim();
            NeighbourNet.showAlert(`<strong>${label}</strong><br>Click 'Explore Community Map' to view on live OpenStreetMap.`, 'info');
        });
    });

    // Close mobile navbar on link click
    const navCollapse = document.getElementById('navbarMain');
    if (navCollapse) {
        const navLinks = navCollapse.querySelectorAll('.nav-link-custom');
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                const bsCollapse = bootstrap.Collapse.getInstance(navCollapse);
                if (bsCollapse && window.innerWidth < 992) {
                    bsCollapse.hide();
                }
            });
        });
    }
});


// Connectivity is advisory only: the server response remains authoritative.
document.addEventListener('DOMContentLoaded', () => {
    const status = document.getElementById('networkStatus');
    if (!status) return;
    const update = () => {
        const offline = !navigator.onLine;
        status.hidden = !offline;
        status.setAttribute('aria-hidden', String(!offline));
    };
    window.addEventListener('online', update);
    window.addEventListener('offline', update);
    update();
});


// Offer installation only when the browser confirms that the app is installable.
let deferredInstallPrompt = null;
window.addEventListener('beforeinstallprompt', event => {
    event.preventDefault();
    deferredInstallPrompt = event;
    if (document.getElementById('pwaInstallButton')) return;
    const button = document.createElement('button');
    button.id = 'pwaInstallButton';
    button.type = 'button';
    button.className = 'btn btn-primary shadow position-fixed';
    button.style.cssText = 'right:1rem;bottom:1rem;z-index:1070';
    button.textContent = 'Install NeighbourNet';
    button.addEventListener('click', async () => {
        if (!deferredInstallPrompt) return;
        deferredInstallPrompt.prompt();
        await deferredInstallPrompt.userChoice;
        deferredInstallPrompt = null;
        button.remove();
    });
    document.body.appendChild(button);
});
window.addEventListener('appinstalled', () => {
    deferredInstallPrompt = null;
    document.getElementById('pwaInstallButton')?.remove();
});
