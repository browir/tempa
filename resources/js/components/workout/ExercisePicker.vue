<script setup>
import { Check, Search, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import Sheet from '@/components/ui/Sheet.vue';
import { useCatalog } from '@/composables/useCatalog';
import { exerciseIcon, normalize } from '@/lib/exercises';

/** Pilih satu atau beberapa gerakan untuk sesi. Yang baru dipakai tampil paling atas. */
const props = defineProps({
    open: { type: Boolean, default: false },
    exercises: { type: Array, required: true },
    recent: { type: Array, default: () => [] },
    inSession: { type: Array, default: () => [] }, // exercise_id yang sudah ada di sesi
});
const emit = defineEmits(['close', 'add']);

const { catalog, muscleLabel, equipmentLabel } = useCatalog();
const query = ref('');
const selected = ref([]);

watch(
    () => props.open,
    (open) => {
        if (open) {
            query.value = '';
            selected.value = [];
        }
    },
);

const available = computed(() => props.exercises.filter((e) => !e.archived));

const sections = computed(() => {
    const q = normalize(query.value);
    if (q) {
        const hits = available.value.filter((e) =>
            [e.name, muscleLabel(e.muscle_group), equipmentLabel(e.equipment)].some((t) => normalize(t).includes(q)),
        );
        return [{ key: 'hasil', label: null, items: hits }];
    }
    const byId = Object.fromEntries(available.value.map((e) => [e.id, e]));
    const recent = props.recent.map((id) => byId[id]).filter(Boolean);
    return [
        ...(recent.length ? [{ key: 'recent', label: 'Terakhir dipakai', items: recent }] : []),
        ...catalog.value.muscleGroups
            .map((m) => ({ key: m.value, label: m.label, items: available.value.filter((e) => e.muscle_group === m.value) }))
            .filter((s) => s.items.length),
    ];
});

function toggle(exercise) {
    const i = selected.value.findIndex((e) => e.id === exercise.id);
    if (i === -1) selected.value.push(exercise);
    else selected.value.splice(i, 1);
}

const isSelected = (exercise) => selected.value.some((e) => e.id === exercise.id);
</script>

<template>
    <Sheet :open="open" title="Tambah gerakan" @close="emit('close')">
        <div class="sticky top-0 z-10 -mx-5 bg-surface px-5 pb-2">
            <div class="relative">
                <Search class="pointer-events-none absolute top-1/2 left-3.5 size-[18px] -translate-y-1/2 text-muted" />
                <input
                    v-model="query"
                    type="search"
                    class="field pr-12 pl-11"
                    placeholder="Cari gerakan, otot, atau alat"
                    aria-label="Cari gerakan"
                    enterkeyhint="search"
                />
                <button
                    v-if="query"
                    type="button"
                    class="icon-btn absolute top-1/2 right-0.5 -translate-y-1/2"
                    aria-label="Hapus pencarian"
                    @click="query = ''"
                >
                    <X class="size-4" />
                </button>
            </div>
        </div>

        <div class="flex flex-col gap-4 pb-3">
            <section v-for="section in sections" :key="section.key">
                <h3 v-if="section.label" class="eyebrow mb-1 px-1">{{ section.label }}</h3>
                <ul>
                    <li v-for="exercise in section.items" :key="`${section.key}-${exercise.id}`">
                        <button
                            type="button"
                            class="flex min-h-14 w-full items-center gap-3 rounded-xl px-1.5 py-2 text-left transition-colors pointer:hover:bg-sunken/60"
                            :aria-pressed="isSelected(exercise)"
                            @click="toggle(exercise)"
                        >
                            <span
                                class="flex size-10 shrink-0 items-center justify-center rounded-xl transition-colors"
                                :class="isSelected(exercise) ? 'bg-accent text-accent-fg' : 'bg-accent-soft text-accent-text'"
                            >
                                <Check v-if="isSelected(exercise)" class="size-5" :stroke-width="2.6" />
                                <component :is="exerciseIcon(exercise)" v-else class="size-5" :stroke-width="1.9" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[15px] font-medium">{{ exercise.name }}</span>
                                <span class="block truncate text-small text-muted">
                                    {{ muscleLabel(exercise.muscle_group) }} · {{ equipmentLabel(exercise.equipment) }}
                                    <template v-if="inSession.includes(exercise.id)"> · sudah di sesi</template>
                                </span>
                            </span>
                        </button>
                    </li>
                </ul>
            </section>
            <p v-if="!sections.some((s) => s.items.length)" class="py-8 text-center text-muted">
                Tidak ada gerakan “{{ query }}”. Tambahkan dulu di menu Gerakan.
            </p>
        </div>

        <template #footer>
            <button
                type="button"
                class="btn btn-primary h-12 w-full"
                :disabled="!selected.length"
                @click="emit('add', [...selected])"
            >
                {{ selected.length ? `Tambah ${selected.length} gerakan` : 'Pilih gerakan' }}
            </button>
        </template>
    </Sheet>
</template>
