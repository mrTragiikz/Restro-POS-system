// assets/js/app.js

const App = {
    pollingInterval: 10000,
    pollingTimer: null,
    liveSyncTimer: null,
    liveSyncVersion: 0,
    liveSyncCallback: null,

    showToast: (msg, type = 'info') => {
        App.toastQueue.push({ msg, type });
        if (!App.toastActive) App.processToastQueue();
    },

    toastQueue: [],
    toastActive: false,

    processToastQueue: () => {
        if (App.toastQueue.length === 0) {
            App.toastActive = false;
            return;
        }

        App.toastActive = true;
        const { msg, type } = App.toastQueue.shift();

        let toast = document.getElementById('toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'toast';
            toast.className = 'toast';
            document.body.appendChild(toast);
        }

        toast.textContent = msg;
        toast.className = `toast show ${type}`;

        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => App.processToastQueue(), 400);
        }, 3000);
    },

    moneyFormatter: new Intl.NumberFormat('en-NP', {
        style: 'currency',
        currency: 'NPR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }),

    formatMoney: (amount) => {
        return App.moneyFormatter.format(amount).replace('NPR', 'Rs.');
    },

    _prepareUrl: (url) => {
        if (!window.CURRENT_PORTAL) return url;
        const separator = url.includes('?') ? '&' : '?';
        return url.includes('portal=') ? url : `${url}${separator}portal=${window.CURRENT_PORTAL}`;
    },

    request: async (url, method = 'GET', data = null, options = {}) => {
        url = App._prepareUrl(url);

        const fetchOptions = {
            method,
            credentials: 'include',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-Portal': window.CURRENT_PORTAL || 'DEFAULT'
            },
            ...options
        };

        if (data) {
            if (data instanceof FormData) {
                fetchOptions.body = data;
            } else {
                fetchOptions.headers['Content-Type'] = 'application/x-www-form-urlencoded';
                fetchOptions.body = new URLSearchParams(data);
            }
        }

        try {
            const res = await fetch(url, fetchOptions);
            if (!res.ok) {
                let errorMsg = `Server Error (${res.status})`;
                try {
                    const json = await res.json();
                    if (json.error) errorMsg = json.error;
                    // Kill-switch: any API that reports the portal is disabled
                    // forces an immediate logout (no orders can go through).
                    if (json.portal_disabled) {
                        App.handlePortalDisabled();
                        return { error: errorMsg, portal_disabled: true };
                    }
                } catch (e) { }
                throw new Error(errorMsg);
            }
            const okJson = await res.json();
            if (okJson && okJson.portal_disabled) {
                App.handlePortalDisabled();
            }
            return okJson;
        } catch (err) {
            if (err.name === 'AbortError') return null;
            console.error('App.request Error:', err);
            return { error: err.message };
        }
    },

    startPolling: (callback, interval = null) => {
        if (App.pollingTimer) clearTimeout(App.pollingTimer);
        const pollInterval = interval || App.pollingInterval;

        const executePoll = async () => {
            try {
                await callback();
            } catch (e) {
                console.error('Polling Error:', e);
            }
            App.pollingTimer = setTimeout(executePoll, pollInterval);
        };

        executePoll();
    },

    stopPolling: () => {
        if (App.pollingTimer) {
            clearTimeout(App.pollingTimer);
            App.pollingTimer = null;
        }
    },

    // Live order/status sync — polls a tiny version endpoint, refreshes only on change
    startLiveSync: (pingUrl, callback, intervalMs = 800) => {
        App.stopLiveSync();
        App.liveSyncCallback = callback;

        let ready = false;

        const tick = async () => {
            try {
                const data = await App.request(pingUrl);
                if (!data || data.error) {
                    App.liveSyncTimer = setTimeout(tick, Math.max(intervalMs, 2000));
                    return;
                }

                // Kill-switch: server says this portal was deactivated by admin.
                // Only set for the WAITER role; admins always receive false.
                if (data.portal_disabled) {
                    App.handlePortalDisabled();
                    return; // stop polling
                }

                const version = parseInt(data.version, 10) || 0;

                if (!ready) {
                    App.liveSyncVersion = version;
                    ready = true;
                } else if (version > App.liveSyncVersion) {
                    App.liveSyncVersion = version;
                    if (typeof App.liveSyncCallback === 'function') {
                        App.liveSyncCallback(data);
                    }
                }
            } catch (err) {
                console.error('LiveSync error:', err);
            }

            App.liveSyncTimer = setTimeout(tick, intervalMs);
        };

        tick();
    },

    stopLiveSync: () => {
        if (App.liveSyncTimer) {
            clearTimeout(App.liveSyncTimer);
            App.liveSyncTimer = null;
        }
        App.liveSyncCallback = null;
    },

    // Shows a full-screen overlay and logs the waiter out when the portal is
    // deactivated by an admin. Runs once.
    handlePortalDisabled: () => {
        if (App._portalDisabledShown) return;
        App._portalDisabledShown = true;
        App.stopLiveSync();

        const logoutUrl = (window.BASE_URL || '/') + 'logout.php?portal=WAITER';

        const overlay = document.createElement('div');
        overlay.setAttribute('id', 'portal-disabled-overlay');
        overlay.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(43,32,20,0.92);' +
            'display:flex;align-items:center;justify-content:center;backdrop-filter:blur(4px);' +
            'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;';
        overlay.innerHTML =
            '<div style="background:#fff;border-radius:20px;max-width:380px;width:88%;padding:34px 28px;' +
            'text-align:center;box-shadow:0 24px 70px rgba(0,0,0,0.4);">' +
            '<div style="width:64px;height:64px;margin:0 auto 18px;border-radius:50%;background:#FBEBEA;' +
            'display:flex;align-items:center;justify-content:center;">' +
            '<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" ' +
            'stroke="#d9534f" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">' +
            '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>' +
            '<path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg></div>' +
            '<div style="font-weight:700;font-size:1.15rem;color:#2b2014;margin-bottom:8px;">Session Ended</div>' +
            '<div style="color:#777;font-size:0.95rem;line-height:1.5;margin-bottom:6px;">' +
            'The waiter portal has been turned off by the administrator.</div>' +
            '<div style="color:#aaa;font-size:0.82rem;">Signing you out…</div>' +
            '</div>';
        document.body.appendChild(overlay);

        setTimeout(() => { window.location.href = logoutUrl; }, 2200);
    }
};
