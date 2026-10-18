<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { LogOut } from 'lucide-vue-next';
import { computed } from 'vue';
import { route } from 'ziggy-js';
import Segmented from '@/components/ui/Segmented.vue';
import { setTheme, useTheme } from '@/composables/useTheme';

const page = usePage();
const user = computed(() => page.props.auth.user);

const preference = useTheme();
const theme = computed({ get: () => preference.value, set: setTheme });
const themes = [
    { value: 'light', label: 'Terang' },
    { value: 'dark', label: 'Gelap' },
    { value: 'system', label: 'Sistem' },
];
</script>

<template>
    <Head title="Pengaturan" />

    <h1 class="page-title mb-6">Pengaturan</h1>

    <div class="grid max-w-xl gap-5">
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
