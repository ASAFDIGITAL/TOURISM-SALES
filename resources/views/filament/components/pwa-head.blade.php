<link rel="manifest" href="{{ asset('manifest.json') }}">
<meta name="theme-color" content="#F59E0B">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="TOURISM-SALES">
<link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">

<script>
    window.__tsInstallPrompt = null;
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        window.__tsInstallPrompt = e;
        window.dispatchEvent(new CustomEvent('ts-install-available'));
    });

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {});
        });
    }
</script>
