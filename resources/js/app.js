import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import { ZiggyVue, route } from 'ziggy-js';
import AppLayout from './layouts/AppLayout.vue';
import { startOutbox } from './composables/useOutbox';
import { pushToast } from './composables/useToast';

// Aplikasi terasa seperti aplikasi asli: zoom dimatikan. iOS Safari mengabaikan
// user-scalable=no di meta viewport, jadi gestur cubit dicegah langsung.
for (const type of ['gesturestart', 'gesturechange', 'gestureend']) {
    document.addEventListener(type, (e) => e.preventDefault(), { passive: false });
}
document.addEventListener(
    'touchmove',
    (e) => {
        if (e.touches.length > 1) e.preventDefault();
    },
    { passive: false },
);

const pages = import.meta.glob('./pages/**/*.vue', { eager: true });

createInertiaApp({
    title: (title) => (title ? `${title} · Tempa` : 'Tempa'),
    resolve: (name) => pages[`./pages/${name}.vue`],
    layout: (name) => (name.startsWith('auth/') ? null : AppLayout),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: 'var(--accent-text)',
        delay: 180,
    },
});

// Pesan sekali-tampil dari server (Inertia::flash('toast', ...)).
router.on('flash', (event) => {
    const { toast } = event.detail.flash ?? {};
    if (toast) pushToast(toast);
});

// Koneksi putus saat pindah halaman / aksi: beri tahu, jangan biarkan galat tak tertangani.
router.on('networkError', (event) => {
    event.preventDefault();
    pushToast({ message: navigator.onLine ? 'Gagal terhubung ke server' : 'Sedang offline. Coba lagi saat tersambung.' });
});

// Keluar / sesi habis: hapus salinan halaman offline agar data tidak bisa dibuka tanpa login.
router.on('navigate', (event) => {
    if (event.detail.page.component.startsWith('auth/') && 'caches' in window) {
        caches.delete('tempa-data-v1');
        caches.open('tempa-pages-v1').then(async (cache) => {
            for (const req of await cache.keys()) if (!req.url.endsWith('/offline.html')) cache.delete(req);
        });
    }
});

startOutbox();

// Siapkan salinan halaman Latihan, Gerakan & Riwayat di cache service worker selagi online, supaya
// tetap bisa dibuka walau sinyal gym hilang sebelum halaman itu sempat dikunjungi.
// Sengaja fetch biasa, bukan router.prefetch: prefetch Inertia menyimpan salinan di memori dan
// menampilkannya saat dikunjungi (data basi, mis. sesi yang baru selesai tidak muncul di riwayat).
router.on('navigate', (event) => {
    const { page } = event.detail;
    if (!navigator.onLine || page.component.startsWith('auth/') || !('serviceWorker' in navigator)) return;
    clearTimeout(window.__tempaWarm);
    window.__tempaWarm = setTimeout(() => {
        for (const name of ['workouts.index', 'exercises.index', 'history.index']) {
            if (!route().has(name) || route().current() === name) continue;
            fetch(route(name), {
                credentials: 'same-origin',
                headers: { 'X-Inertia': 'true', 'X-Inertia-Version': page.version ?? '', 'X-Requested-With': 'XMLHttpRequest' },
            }).catch(() => {});
        }
    }, 2500);
});

// Dibuka saat offline: HTML tersimpan bisa lebih tua dari salinan data Inertia terakhir, jadi muat ulang datanya.
if (!navigator.onLine) setTimeout(() => router.reload(), 0);

if ('serviceWorker' in navigator && import.meta.env.PROD) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}
