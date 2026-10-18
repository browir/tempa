/**
 * Notifikasi lokal (tanpa server push). Di Android Chrome `new Notification()` dilarang di halaman,
 * jadi lewat service worker bila ada. Catatan: tanpa push server, notifikasi hanya bisa muncul selama
 * halaman masih hidup — wake lock sesi menjaga layar & halaman tetap aktif di gym.
 */
export function notifySupported() {
    return typeof window !== 'undefined' && 'Notification' in window;
}

export function notifyPermission() {
    return notifySupported() ? Notification.permission : 'unsupported';
}

/** Minta izin (harus dipanggil dari ketukan pengguna). */
export async function requestNotify() {
    if (!notifySupported() || Notification.permission !== 'default') return notifyPermission();
    try {
        return await Notification.requestPermission();
    } catch {
        return notifyPermission();
    }
}

export async function notify(title, body, tag = 'tempa') {
    if (notifyPermission() !== 'granted') return;
    const options = { body, tag, renotify: true, icon: '/icon-192.png', badge: '/icon-192.png', vibrate: [200, 100, 200] };
    try {
        const registration = await navigator.serviceWorker?.getRegistration();
        if (registration) return await registration.showNotification(title, options);
        new Notification(title, options);
    } catch {
        // Diblokir peramban: getar & suara di halaman tetap jalan.
    }
}
