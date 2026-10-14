function showConnectivity() {
    const banner = document.getElementById('offline-notice');
    if (banner) banner.hidden = navigator.onLine;
    document.querySelectorAll('form button[type="submit"], form button:not([type])').forEach(button => {
        if (!navigator.onLine) {
            if (!button.hasAttribute('data-offline-disabled')) button.dataset.offlineDisabled = String(button.disabled);
            button.disabled = true;
        } else if (button.hasAttribute('data-offline-disabled')) {
            button.disabled = button.dataset.offlineDisabled === 'true';
            delete button.dataset.offlineDisabled;
        }
    });
}
showConnectivity();
window.addEventListener('online', showConnectivity);
window.addEventListener('offline', showConnectivity);
if ('serviceWorker' in navigator && window.isSecureContext) {
    let requestedUpdate = false;
    navigator.serviceWorker.register('/sw.js').then(registration => {
        const button = document.getElementById('app-update');
        function offerUpdate() { if (button && registration.waiting) button.hidden = false; }
        offerUpdate();
        registration.addEventListener('updatefound', () => registration.installing?.addEventListener('statechange', offerUpdate));
        button?.addEventListener('click', () => { if (registration.waiting) { requestedUpdate = true; registration.waiting.postMessage({ type: 'SKIP_WAITING' }); } });
    }).catch(() => { /* A aplicação segue disponível sem instalação PWA. */ });
    navigator.serviceWorker.addEventListener('controllerchange', () => { if (requestedUpdate) window.location.reload(); });
}
