<x-filament-panels::page>
    <div
        x-data="tsMobileAppManager({
            vapidPublicKey: @js($this->getVapidPublicKey()),
            subscribeUrl: @js(route('push.subscribe')),
            unsubscribeUrl: @js(route('push.unsubscribe')),
            testUrl: @js(route('push.test')),
            csrfToken: @js(csrf_token()),
            initialDevicesCount: {{ $this->getDevicesCount() }},
            labels: {
                enabled: @js(__('ui.push_enabled_status')),
                disabled: @js(__('ui.push_disabled_status')),
                unsupported: @js(__('ui.push_unsupported')),
                denied: @js(__('ui.push_permission_denied')),
                iosInstallFirst: @js(__('ui.ios_install_first')),
                testSent: @js(__('ui.push_test_sent')),
                testFailed: @js(__('ui.push_test_failed')),
                enabledWithWelcome: @js(__('ui.push_enabled_with_welcome')),
            },
        })"
        x-init="init()"
        style="display: grid; gap: 1.25rem;"
    >
        {{-- Card 1: Install PWA on Phone --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ __('ui.install_app_heading') }}
            </x-slot>
            <x-slot name="description">
                {{ __('ui.install_app_desc') }}
            </x-slot>

            <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem;">
                <div style="display: flex; align-items: center; gap: 0.875rem;">
                    <img
                        src="{{ asset('images/pwa-icon-192.png') }}"
                        alt="TOURISM-SALES"
                        style="width: 3.25rem; height: 3.25rem; border-radius: 0.75rem; flex-shrink: 0;"
                    >
                    <div>
                        <div style="font-weight: 700; font-size: 0.95rem;">TOURISM-SALES</div>
                        <template x-if="isStandalone">
                            <div style="color: #16a34a; font-size: 0.85rem; font-weight: 600;">
                                {{ __('ui.app_installed_badge') }}
                            </div>
                        </template>
                        <template x-if="!isStandalone && isIos">
                            <div style="font-size: 0.85rem; opacity: 0.85; max-width: 34rem;">
                                {{ __('ui.ios_install_steps') }}
                            </div>
                        </template>
                        <template x-if="!isStandalone && !isIos">
                            <div style="font-size: 0.85rem; opacity: 0.85; max-width: 34rem;">
                                {{ __('ui.android_install_hint') }}
                            </div>
                        </template>
                    </div>
                </div>

                <div x-show="!isStandalone && canPromptInstall">
                    <x-filament::button
                        type="button"
                        color="primary"
                        icon="heroicon-o-arrow-down-tray"
                        x-on:click="installApp()"
                    >
                        {{ __('ui.install_app_button') }}
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>

        {{-- Card 2: Phone Push Notifications --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ __('ui.phone_push_heading') }}
            </x-slot>
            <x-slot name="description">
                {{ __('ui.phone_push_desc') }}
            </x-slot>

            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem;">
                    <div>
                        <div style="font-weight: 600; font-size: 0.95rem;" x-text="statusText"></div>
                        <div style="font-size: 0.825rem; opacity: 0.75; margin-top: 0.2rem;">
                            {{ __('ui.connected_devices') }}: <strong x-text="devicesCount"></strong>
                        </div>
                    </div>

                    <div style="display: flex; flex-wrap: wrap; gap: 0.625rem;">
                        <template x-if="!isSubscribed">
                            <x-filament::button
                                type="button"
                                color="primary"
                                icon="heroicon-o-bell-alert"
                                x-bind:disabled="busy"
                                x-on:click="subscribe()"
                            >
                                {{ __('ui.enable_push_btn') }}
                            </x-filament::button>
                        </template>

                        <template x-if="isSubscribed">
                            <div style="display: flex; flex-wrap: wrap; gap: 0.625rem;">
                                <x-filament::button
                                    type="button"
                                    color="primary"
                                    icon="heroicon-o-paper-airplane"
                                    x-bind:disabled="busy"
                                    x-on:click="sendTest()"
                                >
                                    {{ __('ui.send_test_push_btn') }}
                                </x-filament::button>

                                <x-filament::button
                                    type="button"
                                    color="gray"
                                    icon="heroicon-o-bell-slash"
                                    x-bind:disabled="busy"
                                    x-on:click="unsubscribe()"
                                >
                                    {{ __('ui.disable_push_btn') }}
                                </x-filament::button>
                            </div>
                        </template>
                    </div>
                </div>

                <template x-if="feedbackMessage">
                    <div
                        style="padding: 0.65rem 0.9rem; border-radius: 0.5rem; font-size: 0.85rem; font-weight: 500;"
                        :style="feedbackIsError ? 'background: rgba(220,38,38,0.12); color: #dc2626;' : 'background: rgba(22,163,74,0.12); color: #16a34a;'"
                        x-text="feedbackMessage"
                    ></div>
                </template>
            </div>
        </x-filament::section>

        {{-- Card 3: Structured Notification Preferences --}}
        <x-filament-panels::form wire:submit="save">
            {{ $this->form }}

            <x-filament-panels::form.actions
                :actions="$this->getFormActions()"
            />
        </x-filament-panels::form>
    </div>

    <script>
        function tsMobileAppManager(cfg) {
            return {
                isStandalone: false,
                isIos: false,
                canPromptInstall: false,
                pushSupported: false,
                isSubscribed: false,
                busy: false,
                devicesCount: cfg.initialDevicesCount || 0,
                statusText: cfg.labels.disabled,
                feedbackMessage: '',
                feedbackIsError: false,

                async init() {
                    this.isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
                    this.isIos = /iphone|ipad|ipod/i.test(window.navigator.userAgent);
                    this.canPromptInstall = Boolean(window.__tsInstallPrompt);

                    window.addEventListener('ts-install-available', () => {
                        this.canPromptInstall = Boolean(window.__tsInstallPrompt);
                    });

                    window.addEventListener('appinstalled', () => {
                        this.isStandalone = true;
                        this.canPromptInstall = false;
                    });

                    const hasPushApi = ('serviceWorker' in navigator)
                        && (('PushManager' in window) || ('ServiceWorkerRegistration' in window && 'pushManager' in ServiceWorkerRegistration.prototype))
                        && ('Notification' in window);

                    this.pushSupported = hasPushApi && Boolean(cfg.vapidPublicKey);

                    if (!this.pushSupported) {
                        if (!cfg.vapidPublicKey) {
                            this.statusText = 'חסר מפתח VAPID_PUBLIC_KEY בהגדרות השרת (.env).';
                        } else if (this.isIos) {
                            this.statusText = cfg.labels.iosInstallFirst;
                        } else {
                            this.statusText = cfg.labels.unsupported;
                        }
                        return;
                    }

                    try {
                        const reg = await navigator.serviceWorker.ready;
                        const sub = await reg.pushManager.getSubscription();
                        this.isSubscribed = Boolean(sub);
                        this.statusText = this.isSubscribed ? cfg.labels.enabled : cfg.labels.disabled;
                    } catch (e) {
                        this.statusText = cfg.labels.disabled;
                    }
                },

                async installApp() {
                    if (!window.__tsInstallPrompt) return;
                    window.__tsInstallPrompt.prompt();
                    const choice = await window.__tsInstallPrompt.userChoice;
                    if (choice && choice.outcome === 'accepted') {
                        this.isStandalone = true;
                    }
                    window.__tsInstallPrompt = null;
                    this.canPromptInstall = false;
                },

                urlBase64ToUint8Array(base64String) {
                    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
                    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
                    const rawData = window.atob(base64);
                    const outputArray = new Uint8Array(rawData.length);
                    for (let i = 0; i < rawData.length; ++i) {
                        outputArray[i] = rawData.charCodeAt(i);
                    }
                    return outputArray;
                },

                async subscribe() {
                    this.feedbackMessage = '';
                    this.feedbackIsError = false;

                    if (!this.pushSupported) {
                        this.feedbackIsError = true;
                        this.feedbackMessage = this.statusText || cfg.labels.unsupported;
                        return;
                    }

                    this.busy = true;

                    try {
                        const permission = await Notification.requestPermission();
                        if (permission !== 'granted') {
                            this.feedbackIsError = true;
                            this.feedbackMessage = cfg.labels.denied;
                            return;
                        }

                        if (!await navigator.serviceWorker.getRegistration('/')) {
                            await navigator.serviceWorker.register('/sw.js', { scope: '/' });
                        }

                        const reg = await navigator.serviceWorker.ready;
                        let sub = await reg.pushManager.getSubscription();
                        if (!sub) {
                            sub = await reg.pushManager.subscribe({
                                userVisibleOnly: true,
                                applicationServerKey: this.urlBase64ToUint8Array(cfg.vapidPublicKey),
                            });
                        }

                        const json = sub.toJSON();
                        const res = await fetch(cfg.subscribeUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': cfg.csrfToken,
                            },
                            body: JSON.stringify({
                                endpoint: json.endpoint,
                                keys: json.keys,
                                contentEncoding: (PushManager.supportedContentEncodings || ['aes128gcm'])[0],
                                deviceLabel: navigator.platform || 'Mobile',
                            }),
                        });

                        const data = await res.json();
                        if (res.ok && data.ok) {
                            this.isSubscribed = true;
                            this.devicesCount = data.count;
                            this.statusText = cfg.labels.enabled;
                            this.feedbackMessage = cfg.labels.enabledWithWelcome;
                        } else {
                            throw new Error(data.message || 'Subscribe failed');
                        }
                    } catch (e) {
                        this.feedbackIsError = true;
                        this.feedbackMessage = (e && e.message) ? (cfg.labels.unsupported + ' (' + e.message + ')') : cfg.labels.unsupported;
                    } finally {
                        this.busy = false;
                    }
                },

                async unsubscribe() {
                    this.busy = true;
                    this.feedbackMessage = '';
                    this.feedbackIsError = false;

                    try {
                        const reg = await navigator.serviceWorker.ready;
                        const sub = await reg.pushManager.getSubscription();
                        if (sub) {
                            const endpoint = sub.endpoint;
                            await sub.unsubscribe();
                            const res = await fetch(cfg.unsubscribeUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': cfg.csrfToken,
                                },
                                body: JSON.stringify({ endpoint }),
                            });
                            const data = await res.json();
                            if (res.ok && data.ok) {
                                this.devicesCount = data.count;
                            }
                        }
                        this.isSubscribed = false;
                        this.statusText = cfg.labels.disabled;
                        this.feedbackMessage = cfg.labels.disabled;
                    } catch (e) {
                        this.feedbackIsError = true;
                    } finally {
                        this.busy = false;
                    }
                },

                async sendTest() {
                    this.busy = true;
                    this.feedbackMessage = '';
                    this.feedbackIsError = false;

                    try {
                        const res = await fetch(cfg.testUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': cfg.csrfToken,
                            },
                        });
                        const data = await res.json();
                        if (res.ok && data.ok) {
                            this.feedbackMessage = cfg.labels.testSent;
                        } else {
                            this.feedbackIsError = true;
                            this.feedbackMessage = cfg.labels.testFailed;
                        }
                    } catch (e) {
                        this.feedbackIsError = true;
                        this.feedbackMessage = cfg.labels.testFailed;
                    } finally {
                        this.busy = false;
                    }
                },
            };
        }
    </script>
</x-filament-panels::page>
