<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Bell, LogOut } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';
import Segmented from '@/components/ui/Segmented.vue';
import { usePrefs } from '@/composables/usePrefs';
import { setTheme, useTheme } from '@/composables/useTheme';
import { notifyPermission, requestNotify } from '@/lib/notify';

const page = usePage();
const user = computed(() => page.props.auth.user);

const preference = useTheme();
const theme = computed({ get: () => preference.value, set: setTheme });
const themes = [
    { value: 'light', label: 'Terang' },
    { value: 'dark', label: 'Gelap' },
    { value: 'system', label: 'Sistem' },
];

const prefs = usePrefs();
const restOptions = [
    { value: 0, label: 'Mati' },
    { value: 60, label: '1:00' },
    { value: 90, label: '1:30' },
    { value: 120, label: '2:00' },
    { value: 180, label: '3:00' },
];
const restSeconds = computed({ get: () => prefs.restSeconds, set: (v) => (prefs.restSeconds = v) });

const permission = ref(notifyPermission());
async function enableNotify() {
    permission.value = await requestNotify();
}
const permissionText = computed(
    () =>
        ({
            granted: 'Aktif — muncul saat istirahat selesai dan layar tidak sedang menampilkan Tempa.',
            denied: 'Diblokir. Izinkan lewat pengaturan situs di peramban.',
            unsupported: 'Peramban ini tidak mendukung notifikasi. Getar tetap jalan.',
            default: 'Belum diizinkan.',
        })[permission.value],
);
</script>

<template>
    <Head title="Pengaturan" />

    <h1 class="page-title mb-6">Pengaturan</h1>

    <div class="grid max-w-xl gap-5">
        <section class="card p-5">
            <h2 class="section-title mb-1">Istirahat antar-set</h2>
            <p class="mb-4 text-small text-muted">Timer mulai sendiri setiap set dicentang (kecuali kardio).</p>
            <Segmented v-model="restSeconds" :options="restOptions" />

            <div class="mt-5 flex items-center gap-3 border-t border-line pt-4">
                <Bell class="size-5 shrink-0 text-muted" />
                <div class="min-w-0 flex-1">
                    <p class="text-[14px] font-medium">Notifikasi</p>
                    <p class="text-small text-muted">{{ permissionText }}</p>
                </div>
                <button v-if="permission === 'default'" type="button" class="btn btn-quiet shrink-0" @click="enableNotify">
                    Izinkan
                </button>
            </div>
        </section>

        <section class="card p-5">
            <h2 class="section-title mb-1">Tampilan</h2>
            <p class="mb-4 text-small text-muted">Tema gelap lebih nyaman di gym yang redup.</p>
            <Segmented v-model="theme" :options="themes" />
        </section>

        <section class="card p-5">
            <h2 class="section-title mb-3">Akun</h2>
            <p class="text-[15px] font-medium">{{ user?.name }}</p>
            <p class="mb-5 text-small text-muted">{{ user?.email }}</p>
            <Link :href="route('logout')" method="post" as="button" class="btn btn-quiet w-full sm:w-auto">
                <LogOut class="size-4" />
                Keluar
            </Link>
        </section>
    </div>
</template>
