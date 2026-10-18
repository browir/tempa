<script setup>
import { Check, Ellipsis, Plus } from 'lucide-vue-next';
import { computed } from 'vue';
import { useCatalog } from '@/composables/useCatalog';
import { exerciseIcon } from '@/lib/exercises';
import { cardioSpeed, describeSet, fieldsFor, formatField, setBadges } from '@/lib/sets';

/**
 * Satu gerakan di sesi aktif. Baris set: nomor (ketuk = ganti jenis) · sebelumnya · angka (ketuk = ubah) · ✓.
 * Set yang sudah terisi dari sesi sebelumnya cukup diketuk ✓ sekali.
 */
const props = defineProps({
    entry: { type: Object, required: true }, // workout exercise lokal { client_id, exercise_id, sets }
    exercise: { type: Object, required: true }, // { id, name, kind, muscle_group, equipment }
    previous: { type: Object, default: null }, // { date, sets }
});
const emit = defineEmits(['edit', 'toggle', 'cycle-kind', 'add-set', 'menu']);

const { muscleLabel } = useCatalog();
const fields = computed(() => fieldsFor(props.exercise.kind));
const badges = computed(() => setBadges(props.entry.sets));
const doneCount = computed(() => props.entry.sets.filter((s) => s.done_at).length);

// Kolom: nomor · sebelumnya · isian (1–2) · ✓
const columns = computed(() =>
    fields.value.length > 1 ? '2.75rem minmax(0,1fr) 4.75rem 4rem 3rem' : '2.75rem minmax(0,1fr) 6.5rem 3rem',
);

const badgeClass = (set) =>
    ({
        warmup: 'text-warn',
        drop: 'text-(--c-iris)',
        failure: 'text-neg',
    })[set.kind] ?? 'text-ink-2';

const previousSet = (i) => props.previous?.sets?.[i] ?? null;
</script>

<template>
    <section class="card overflow-hidden">
        <header class="flex items-center gap-3 px-4 pt-3.5 pb-2">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-accent-soft text-accent-text">
                <component :is="exerciseIcon(exercise)" class="size-5" :stroke-width="1.9" />
            </span>
            <div class="min-w-0 flex-1">
                <h3 class="truncate text-[16px] font-semibold tracking-tight">{{ exercise.name }}</h3>
                <p class="truncate text-small text-muted">
                    {{ muscleLabel(exercise.muscle_group) }} · {{ doneCount }}/{{ entry.sets.length }} set
                </p>
            </div>
            <button type="button" class="icon-btn -mr-2" :aria-label="`Opsi ${exercise.name}`" @click="emit('menu')">
                <Ellipsis class="size-5" />
            </button>
        </header>

        <div class="px-2 pb-2">
            <div class="grid items-center gap-x-1 px-1 pb-1 text-2xs font-medium tracking-[0.06em] text-muted uppercase" :style="{ gridTemplateColumns: columns }">
                <span class="text-center">Set</span>
                <span class="pl-1">Sebelumnya</span>
                <span v-for="field in fields" :key="field.key" class="text-center">{{ field.unit ?? field.label }}</span>
                <span class="sr-only">Selesai</span>
            </div>

            <TransitionGroup name="list" tag="ul" class="relative">
                <li
                    v-for="(set, i) in entry.sets"
                    :key="set.client_id"
                    class="grid items-center gap-x-1 rounded-xl px-1 py-0.5 transition-colors duration-200"
                    :class="set.done_at ? 'bg-accent-soft/70' : ''"
                    :style="{ gridTemplateColumns: columns }"
                >
                    <button
                        type="button"
                        class="num flex h-12 items-center justify-center rounded-lg text-[15px] font-semibold"
                        :class="badgeClass(set)"
                        :aria-label="`Set ${badges[i]}, ketuk untuk ganti jenis`"
                        @click="emit('cycle-kind', set)"
                    >
                        {{ badges[i] }}
                    </button>
                    <button
                        type="button"
                        class="num h-12 truncate pl-1 text-left text-small text-muted"
                        :aria-label="`Sebelumnya ${describeSet(exercise.kind, previousSet(i)) || 'tidak ada'}`"
                        @click="emit('edit', set)"
                    >
                        {{ describeSet(exercise.kind, previousSet(i)) || '—' }}
                    </button>
                    <button
                        v-for="field in fields"
                        :key="field.key"
                        type="button"
                        class="num flex h-12 items-center justify-center rounded-lg text-[17px] font-semibold transition-colors"
                        :class="set.done_at ? 'text-ink' : 'bg-sunken text-ink'"
                        :aria-label="`Ubah ${field.label}`"
                        @click="emit('edit', set)"
                    >
                        <span v-if="set[field.key] != null">{{ formatField(field, set[field.key]) }}</span>
                        <span v-else class="text-muted">–</span>
                    </button>
                    <button
                        type="button"
                        class="flex size-12 items-center justify-center justify-self-end rounded-xl transition-[background-color,transform] active:scale-90"
                        :class="set.done_at ? 'bg-accent text-accent-fg' : 'bg-sunken text-muted'"
                        :aria-pressed="!!set.done_at"
                        :aria-label="set.done_at ? 'Tandai belum selesai' : 'Tandai selesai'"
                        @click="emit('toggle', set)"
                    >
                        <Check class="size-5" :stroke-width="2.8" />
                    </button>
                    <p
                        v-if="exercise.kind === 'cardio' && set.done_at && cardioSpeed(set)"
                        class="col-span-full -mt-0.5 pb-1 pl-12 text-small text-muted"
                    >
                        Rata-rata <span class="font-mono tnum">{{ cardioSpeed(set) }}</span> km/jam
                    </p>
                </li>
            </TransitionGroup>

            <button
                type="button"
                class="mt-1 flex h-12 w-full items-center justify-center gap-2 rounded-xl text-sm font-medium text-accent-text transition-colors pointer:hover:bg-sunken"
                @click="emit('add-set')"
            >
                <Plus class="size-4" :stroke-width="2.4" />
                Tambah set
            </button>
        </div>
    </section>
</template>
