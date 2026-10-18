<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useNow } from '@vueuse/core';
import { ArrowRight, Bike, Dumbbell, Plus, Timer } from 'lucide-vue-next';
import { computed } from 'vue';
import { route } from 'ziggy-js';
import TempaMark from '@/components/ui/TempaMark.vue';
import { startWorkout, useWorkout } from '@/composables/useWorkout';
import { workoutSeconds } from '@/lib/summary';
import { formatDuration } from '@/lib/units';

const page = usePage();
const firstName = computed(() => (page.props.auth.user?.name ?? '').split(' ')[0]);
const { session } = useWorkout();
const now = useNow({ interval: 1000 });

const today = new Date();
const greeting = (() => {
    const h = today.getHours();
    if (h < 11) return 'Selamat pagi';
    if (h < 15) return 'Selamat siang';
    if (h < 18) return 'Selamat sore';
    return 'Selamat malam';
})();
const dateLabel = new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long' }).format(today);

const elapsed = computed(() => (session.value ? formatDuration(workoutSeconds(session.value, now.value)) : ''));
const doneSets = computed(() => session.value?.exercises.flatMap((e) => e.sets).filter((s) => s.done_at).length ?? 0);

// Sesi yang belum selesai di server tapi tidak ada di HP ini (dimulai di perangkat lain / data lokal terhapus).
const remote = computed(() => (session.value ? null : (page.props.activeWorkout ?? null)));
const remoteStarted = computed(() =>
    remote.value
        ? new Intl.DateTimeFormat('id-ID', { weekday: 'long', hour: '2-digit', minute: '2-digit' }).format(new Date(remote.value.started_at))
        : '',
);

function start() {
    startWorkout();
    router.visit(route('workouts.index'));
}

const upcoming = [
    { icon: Dumbbell, title: 'Rutinitas & jadwal', text: 'Program per hari (pola 3-1-2-1), mulai sesi langsung dari rutinitas.' },
    { icon: Timer, title: 'Ubah riwayat', text: 'Perbaiki atau hapus sesi lama, dengan urungkan.' },
    { icon: Bike, title: 'Progres', text: 'Grafik per gerakan, rekor pribadi, dan total jarak bersepeda.' },
];
</script>

<template>
    <Head title="Beranda" />

    <header class="mb-6">
        <p class="eyebrow mb-1">{{ dateLabel }}</p>
        <h1 class="page-title">{{ greeting }}{{ firstName ? `, ${firstName}` : '' }}</h1>
    </header>

    <div class="grid gap-5 lg:grid-cols-[1.4fr_1fr]">
        <section class="hero-card p-6 md:p-8">
            <TempaMark class="absolute -right-6 -bottom-20 -z-10 size-48 text-white opacity-[0.07] md:size-56" />
            <template v-if="session">
                <p class="text-small font-medium tracking-[0.08em] text-(--hero-muted) uppercase">
                    {{ session.paused_at ? 'Sesi dijeda' : 'Sesi berjalan' }}
                </p>
                <p class="mt-2 text-[26px] leading-tight font-semibold tracking-[-0.03em]">{{ session.name }}</p>
                <p class="mt-1 text-[15px] text-(--hero-muted)">
                    <span class="num text-(--hero-fg)">{{ elapsed }}</span> · {{ session.exercises.length }} gerakan · {{ doneSets }} set selesai
                </p>
                <Link
                    :href="route('workouts.index')"
                    class="mt-6 inline-flex h-14 w-full items-center justify-center gap-2 rounded-2xl bg-white text-[16px] font-semibold text-(--hero-from) transition active:scale-[0.98] sm:w-auto sm:px-8"
                >
                    Lanjutkan
                    <ArrowRight class="size-5" :stroke-width="2.4" />
                </Link>
            </template>
            <template v-else-if="remote">
                <p class="text-small font-medium tracking-[0.08em] text-(--hero-muted) uppercase">Sesi belum selesai</p>
                <p class="mt-2 text-[26px] leading-tight font-semibold tracking-[-0.03em]">{{ remote.name || 'Latihan' }}</p>
                <p class="mt-1 text-[15px] text-(--hero-muted)">Dimulai {{ remoteStarted }}</p>
                <Link
                    :href="route('workouts.index')"
                    class="mt-6 inline-flex h-14 w-full items-center justify-center gap-2 rounded-2xl bg-white text-[16px] font-semibold text-(--hero-from) transition active:scale-[0.98] sm:w-auto sm:px-8"
                >
                    Lanjutkan
                    <ArrowRight class="size-5" :stroke-width="2.4" />
                </Link>
            </template>
            <template v-else>
                <p class="text-small font-medium tracking-[0.08em] text-(--hero-muted) uppercase">Hari ini</p>
                <p class="mt-2 max-w-sm text-[26px] leading-tight font-semibold tracking-[-0.03em]">Siap menempa?</p>
                <p class="mt-2 max-w-sm text-[15px] text-(--hero-muted)">Gym, rumahan, atau bersepeda — catat semuanya di sini.</p>
                <button
                    type="button"
                    class="mt-6 inline-flex h-14 w-full items-center justify-center gap-2 rounded-2xl bg-white text-[16px] font-semibold text-(--hero-from) transition active:scale-[0.98] sm:w-auto sm:px-8"
                    @click="start"
                >
                    <Plus class="size-5" :stroke-width="2.6" />
                    Mulai latihan
                </button>
            </template>
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
