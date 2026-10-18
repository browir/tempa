<script setup>
import { computed } from 'vue';
import { exerciseIcon } from '@/lib/exercises';
import { cardioSpeed, describeSet, setBadges } from '@/lib/sets';
import { summarize } from '@/lib/summary';
import { formatDuration, formatKg, formatKm, formatSpeed } from '@/lib/units';

/** Ringkasan satu sesi: angka utama + set selesai per gerakan. Dipakai setelah selesai & di riwayat. */
const props = defineProps({
    workout: { type: Object, required: true },
    exercises: { type: Array, required: true },
});

const byId = computed(() => Object.fromEntries(props.exercises.map((e) => [e.id, e])));
const summary = computed(() => summarize(props.workout, byId.value));
const nf = new Intl.NumberFormat('id-ID');

const stats = computed(() => {
    const s = summary.value;
    const list = [{ label: 'Durasi', value: formatDuration(s.seconds) }, { label: 'Set selesai', value: nf.format(s.sets) }];
    if (s.volume) list.push({ label: 'Volume', value: `${nf.format(Math.round(s.volume / 1000))} kg` });
    if (s.distance) {
        list.push({ label: 'Jarak', value: `${formatKm(s.distance)} km` });
        const speed = formatSpeed(s.distance, s.cardioSeconds);
        if (speed) list.push({ label: 'Rata-rata', value: `${speed} km/j` });
    }
    return list;
});

function bestLabel(item) {
    if (!item.best) return '';
    if (item.exercise.kind === 'weight_reps' && item.bestOneRepMax && item.best.reps > 1) {
        // Estimasi: cukup 1 desimal (79,2 kg), presisi lebih hanya kesan palsu.
        return `Terbaik ${describeSet('weight_reps', item.best)} · est. 1RM ${formatKg(Math.round(item.bestOneRepMax / 100) * 100)} kg`;
    }
    if (item.exercise.kind === 'cardio') {
        const speed = cardioSpeed(item.best);
        return speed ? `${speed} km/jam rata-rata` : '';
    }
    return `Terbaik ${describeSet(item.exercise.kind, item.best)}`;
}

const badgeFor = (item, set) => setBadges(item.entry.sets)[item.entry.sets.indexOf(set)];
</script>

<template>
    <div class="flex flex-col gap-4">
        <dl class="grid grid-cols-2 gap-2 sm:grid-cols-4">
            <div v-for="stat in stats" :key="stat.label" class="rounded-2xl bg-sunken px-3 py-3">
                <dt class="text-small text-muted">{{ stat.label }}</dt>
                <dd class="num text-[22px] font-semibold">{{ stat.value }}</dd>
            </div>
        </dl>

        <section v-for="item in summary.exercises" :key="item.entry.client_id" class="card p-4">
            <header class="mb-2 flex items-center gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-accent-soft text-accent-text">
                    <component :is="exerciseIcon(item.exercise)" class="size-5" :stroke-width="1.9" />
                </span>
                <div class="min-w-0 flex-1">
                    <h3 class="truncate text-[16px] font-semibold tracking-tight">{{ item.exercise.name }}</h3>
                    <p class="line-clamp-2 text-small text-muted">
                        {{ item.done.length }} set selesai<template v-if="bestLabel(item)"> · {{ bestLabel(item) }}</template>
                    </p>
                </div>
            </header>
            <ul v-if="item.done.length" class="flex flex-wrap gap-1.5">
                <li
                    v-for="set in item.done"
                    :key="set.client_id"
                    class="flex h-9 items-center gap-1.5 rounded-xl bg-sunken px-2.5 text-small"
                    :class="set === item.best && 'bg-accent-soft text-accent-text'"
                >
                    <span class="font-semibold text-muted">{{ badgeFor(item, set) }}</span>
                    <span class="num text-ink">{{ describeSet(item.exercise.kind, set) }}</span>
                </li>
            </ul>
            <p v-else class="text-small text-muted">Tidak ada set yang dicentang.</p>
        </section>
    </div>
</template>
