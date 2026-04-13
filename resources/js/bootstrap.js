import axios from 'axios';
import { configureEcho, echo } from '@laravel/echo-vue';
import Pusher from 'pusher-js';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

window.Pusher = Pusher;

const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;

if (reverbKey) {
    const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'https';
    const port = Number(import.meta.env.VITE_REVERB_PORT) || 8080;
    const wsHost =
        import.meta.env.VITE_REVERB_HOST ||
        (typeof window !== 'undefined' ? window.location.hostname : '127.0.0.1');

    configureEcho({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        disableStats: true,
        // Must be empty so `broadcastAs()` names match (default is App.Events.*).
        namespace: '',
    });

    // Keep for DevTools and any code that still expects a global.
    window.Echo = echo();
} else {
    window.Echo = null;
}
