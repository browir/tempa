<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight, History } from 'lucide-vue-next';
import { computed } from 'vue';
import { route } from 'ziggy-js';
import { exerciseIcon } from '@/lib/exercises';
import { formatDuration, formatKm } from '@/lib/units';

const props = defineProps({
    workouts: { type: Array, required: true },
    exercises: { type: Array, required: true },
});

const byId = computed(() => Object.fromEntries(props.exercises.map((e) => [e.id, e])));
const nf = new Intl.NumberFormat('id-ID');
const monthFmt = new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric' });
const dayFmt = new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'short' });
const timeFmt = new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit' });

// Dikelompokkan per bulan (data sudah urut terbaru dulu dari server).
const months = computed(() => {
    const groups = [];
    for (const w of props.workouts) {
        const label = monthFmt.format(new Date(w.started_at));
        if (groups.at(-1)?.label !== label) groups.push({ label, items: [] });
        groups.at(-1).items.push(w);
    }
    return groups;
});

function names(workout) {
    const list = workout.exercise_ids.map((id) => byId.value[id]?.name).filter(Boolean);
    return list.length > 3 ? `${list.slice(0, 3).join(', ')} +${list.length - 3}` : list.join(', ');
}

function icons(workout) {
    const seen = new Set();
    return workout.exercise_ids
        .map((id) => byId.value[id])
        .filter(Boolean)
        .map((e) => exerciseIcon(e))
        .filter((icon) => (seen.has(icon) ? false : seen.add(icon)))
        .slice(0, 3);
}
</script>

<template>
    <Head title="Riwayat" />

    <header class="mb-6">
        <p class="eyebrow mb-1">{{ workouts.length }} sesi</p>
        <h1 class="page-title">Riwayat</h1>
    </header>

    <div v-if="!workouts.length" class="card flex flex-col items-center gap-3 px-6 py-12 text-center">
        <History class="size-8 text-muted" :stroke-width="1.6" />
        <p class="text-[15px] text-ink-2">Belum ada latihan yang selesai.</p>
        <Link :href="route('workouts.index')" class="btn btn-primary">Mulai latihan</Link>
    </div>

    <div class="flex max-w-2xl flex-col gap-6">
        <section v-for="month in months" :key="month.label">
            <h2 class="eyebrow mb-2 px-1">{{ month.label }}</h2>
            <ul class="card divide-y divide-line overflow-hidden">
                <li v-for="w in month.items" :key="w.client_id">
                    <Link
                        :href="route('history.show', w.client_id)"
                        class="flex min-h-18 items-center gap-3 px-4 py-3 transition-colors pointer:hover:bg-sunken/60"
                    >
                        <!-- Lebar tetap agar teks tiap baris sejajar walau jumlah ikon berbeda. -->
                        <span class="flex w-[4.75rem] shrink-0 -space-x-2">
                            <span
                                v-for="(icon, i) in icons(w)"
                                :key="i"
                                class="flex size-9 items-center justify-center rounded-full bg-accent-soft text-accent-text ring-2 ring-surface"
                            >
                                <component :is="icon" class="size-4" :stroke-width="2" />
                            </span>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-[15px] font-medium">{{ w.name || 'Latihan' }}</span>
                            <span class="block truncate text-small text-muted">
                                {{ dayFmt.format(new Date(w.started_at)) }} · {{ timeFmt.format(new Date(w.started_at)) }}
                            </span>
                            <span class="block truncate text-small text-muted">{{ names(w) }}</span>
                        </span>
                        <span class="shrink-0 text-right">
                            <span class="num block text-[15px] font-semibold">{{ formatDuration(w.duration_s) }}</span>
                            <span class="block text-small text-muted">
                                <template v-if="w.volume_g">{{ nf.format(Math.round(w.volume_g / 1000)) }} kg</template>
                                <template v-else-if="w.distance_m">{{ formatKm(w.distance_m) }} km</template>
                                <template v-else>{{ w.sets_done }} set</template>
                            </span>
                        </span>
                        <ChevronRight class="size-4 shrink-0 text-muted" />
                    </Link>
                </li>
            </ul>
        </section>
    </div>
</template>
