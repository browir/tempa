<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { Bike, Dumbbell, Timer } from 'lucide-vue-next';
import { computed } from 'vue';
import TempaMark from '@/components/ui/TempaMark.vue';

const page = usePage();
const firstName = computed(() => (page.props.auth.user?.name ?? '').split(' ')[0]);

const now = new Date();
const greeting = (() => {
    const h = now.getHours();
    if (h < 11) return 'Selamat pagi';
    if (h < 15) return 'Selamat siang';
    if (h < 18) return 'Selamat sore';
    return 'Selamat malam';
})();
const today = new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long' }).format(now);

const upcoming = [
    { icon: Dumbbell, title: 'Sesi latihan', text: 'Catat set berat × reps, isi otomatis dari sesi sebelumnya.' },
    { icon: Timer, title: 'Timer istirahat', text: 'Berjalan sendiri setelah set selesai, layar tetap menyala.' },
    { icon: Bike, title: 'Kardio & sepeda', text: 'Jarak, durasi, dan kecepatan rata-rata per perjalanan.' },
];
</script>

<template>
    <Head title="Beranda" />

    <header class="mb-6">
        <p class="eyebrow mb-1">{{ today }}</p>
        <h1 class="page-title">{{ greeting }}{{ firstName ? `, ${firstName}` : '' }}</h1>
    </header>

    <div class="grid gap-5 lg:grid-cols-[1.4fr_1fr]">
        <section class="hero-card p-6 md:p-8">
            <TempaMark class="absolute -right-6 -bottom-20 -z-10 size-48 text-white opacity-[0.07] md:size-56" />
            <p class="text-small font-medium tracking-[0.08em] text-(--hero-muted) uppercase">Hari ini</p>
            <p class="mt-2 max-w-sm text-[26px] leading-tight font-semibold tracking-[-0.03em]">Belum ada sesi latihan.</p>
            <p class="mt-2 max-w-sm text-[15px] text-(--hero-muted)">
                Fondasi Tempa sudah siap. Pencatatan latihan hadir di langkah berikutnya.
            </p>
        </section>

        <section class="card p-5">
            <h2 class="section-title mb-3">Segera hadir</h2>
            <ul class="flex flex-col gap-3">
                <li v-for="item in upcoming" :key="item.title" class="flex gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-accent-soft text-accent-text">
                        <component :is="item.icon" class="size-5" />
                    </span>
                    <span class="min-w-0">
                        <span class="block text-[14px] font-medium">{{ item.title }}</span>
                        <span class="block text-small text-muted">{{ item.text }}</span>
                    </span>
                </li>
            </ul>
        </section>
    </div>
</template>
