import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

const config = window.Laravel || {
    broadcaster: 'reverb',
    reverb: {
        key: process.env.MIX_REVERB_APP_KEY,
        host: process.env.MIX_REVERB_HOST,
        port: process.env.MIX_REVERB_PORT,
        scheme: process.env.MIX_REVERB_SCHEME
    }
};

const broadcaster = config.broadcaster || 'reverb';

if (broadcaster === 'pusher' && config.pusher && config.pusher.key) {
    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: config.pusher.key,
        cluster: config.pusher.cluster,
        forceTLS: true
    });
} else if (config.reverb && config.reverb.key) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: config.reverb.key,
        wsHost: config.reverb.host,
        wsPort: config.reverb.port ?? 80,
        wssPort: config.reverb.port ?? 443,
        forceTLS: config.reverb.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}
