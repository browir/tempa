<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { useOnline } from '@vueuse/core';
import { Archive, ArchiveRestore, ChevronRight, Plus, Search, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { route } from 'ziggy-js';
import Sheet from '@/components/ui/Sheet.vue';
import { useCatalog } from '@/composables/useCatalog';
import { exerciseIcon, normalize } from '@/lib/exercises';

const props = defineProps({
    exercises: { type: Array, required: true },
});

const { catalog, muscleLabel, equipmentLabel, kindShort } = useCatalog();
const online = useOnline();

/* ---------- Cari & saring (di klien: instan, tetap jalan offline) ---------- */
const query = ref('');
const filter = ref('all'); // 'all' | kunci otot | 'archived'

const active = computed(() => props.exercises.filter((e) => !e.archived));
const archived = computed(() => props.exercises.filter((e) => e.archived));

// Chip hanya untuk kelompok otot yang punya gerakan aktif, urutan mengikuti katalog.
const muscleChips = computed(() =>
    catalog.value.muscleGroups
        .map((m) => ({ ...m, count: active.value.filter((e) => e.muscle_group === m.value).length }))
        .filter((m) => m.count > 0),
);

const visible = computed(() => {
    const pool = filter.value === 'archived' ? archived.value : active.value;
    const byMuscle = filter.value === 'all' || filter.value === 'archived' ? pool : pool.filter((e) => e.muscle_group === filter.value);
    const q = normalize(query.value);
    if (!q) return byMuscle;
    // Cocokkan nama, kelompok otot, dan alat — "dada" atau "sepeda" juga menemukan gerakannya.
    return byMuscle.filter((e) =>
        [e.name, muscleLabel(e.muscle_group), equipmentLabel(e.equipment)].some((t) => normalize(t).includes(q)),
    );
});

// Tanpa pencarian & filter: dikelompokkan per otot. Selain itu: satu daftar.
const groups = computed(() => {
    if (filter.value !== 'all' || query.value.trim()) return [{ key: 'hasil', label: null, items: visible.value }];
    return muscleChips.value.map((m) => ({
        key: m.value,
        label: m.label,
        items: visible.value.filter((e) => e.muscle_group === m.value),
    }));
});

function setFilter(value) {
    filter.value = filter.value === value && value !== 'all' ? 'all' : value;
}

/* ---------- Tambah / ubah ---------- */
const sheetOpen = ref(false);
const editing = ref(null);
const form = useForm({ name: '', muscle_group: 'chest', equipment: 'barbell', kind: 'weight_reps', notes: '' });

function open(exercise = null, presetName = '') {
    editing.value = exercise;
    form.clearErrors();
    form.name = exercise?.name ?? presetName;
    form.muscle_group = exercise?.muscle_group ?? (['all', 'archived'].includes(filter.value) ? 'chest' : filter.value);
    form.equipment = exercise?.equipment ?? (form.muscle_group === 'cardio' ? 'none' : 'barbell');
    form.kind = exercise?.kind ?? (form.muscle_group === 'cardio' ? 'cardio' : 'weight_reps');
    form.notes = exercise?.notes ?? '';
    sheetOpen.value = true;
}

// Bantuan isian untuk gerakan baru: pilihan alat/otot menebak jenis pencatatan yang masuk akal.
watch(
    () => form.equipment,
    (equipment) => {
        if (editing.value) return;
        if (equipment === 'bike') {
            form.kind = 'cardio';
            form.muscle_group = 'cardio';
        } else if (equipment === 'bodyweight' && form.kind === 'weight_reps') {
            form.kind = 'bodyweight';
        }
    },
);
watch(
    () => form.muscle_group,
    (muscle) => {
        if (!editing.value && muscle === 'cardio' && !['cardio', 'time'].includes(form.kind)) form.kind = 'cardio';
    },
);

function save() {
    const options = { preserveScroll: true, onSuccess: () => (sheetOpen.value = false) };
    if (editing.value) form.put(route('exercises.update', editing.value.id), options);
    else form.post(route('exercises.store'), options);
}

function toggleArchive() {
    const name = editing.value.archived ? 'exercises.unarchive' : 'exercises.archive';
    router.post(route(name, editing.value.id), {}, { preserveScroll: true, onSuccess: () => (sheetOpen.value = false) });
}
</script>

<template>
    <Head title="Gerakan" />

    <header class="mb-4 flex items-end justify-between gap-4">
        <div>
            <p class="eyebrow mb-1">{{ active.length }} gerakan</p>
            <h1 class="page-title">Gerakan</h1>
        </div>
        <button type="button" class="btn btn-primary" @click="open()">
            <Plus class="size-4" :stroke-width="2.4" />
            Gerakan
        </button>
    </header>

    <!-- Cari + chip otot: menempel di atas saat menggulir daftar panjang. -->
    <div class="sticky top-0 z-20 -mx-4 bg-paper/90 px-4 pt-[max(0.5rem,env(safe-area-inset-top))] pb-1 backdrop-blur-xl md:-mx-10 md:px-10">
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

        <div class="no-scrollbar -mx-4 mt-2 flex gap-2 overflow-x-auto px-4 py-1.5 md:mx-0 md:flex-wrap md:px-0">
            <button type="button" class="chip" :aria-pressed="filter === 'all'" @click="setFilter('all')">Semua</button>
            <button
                v-for="m in muscleChips"
                :key="m.value"
                type="button"
                class="chip"
                :aria-pressed="filter === m.value"
                @click="setFilter(m.value)"
            >
                {{ m.label }}
                <span class="text-tiny opacity-70 tnum">{{ m.count }}</span>
            </button>
            <button
                v-if="archived.length"
                type="button"
                class="chip"
                :aria-pressed="filter === 'archived'"
                @click="setFilter('archived')"
            >
                <Archive class="size-3.5" />
                Arsip
                <span class="text-tiny opacity-70 tnum">{{ archived.length }}</span>
            </button>
        </div>
    </div>

    <div class="mt-3 flex flex-col gap-5">
        <section v-for="group in groups" :key="group.key">
            <h2 v-if="group.label" class="eyebrow mb-2 px-1">{{ group.label }}</h2>
            <!-- grid-cols-1 = minmax(0,1fr): tanpa ini kolom melebar mengikuti teks dan truncate tidak bekerja. -->
            <ul v-if="group.items.length" class="card grid grid-cols-1 gap-x-6 px-2 md:grid-cols-2 md:px-3">
                <li
                    v-for="exercise in group.items"
                    :key="exercise.id"
                    class="border-b border-line last:border-b-0 md:[&:nth-last-child(2):nth-child(odd)]:border-b-0"
                >
                    <button
                        type="button"
                        class="flex min-h-16 w-full items-center gap-3 rounded-xl px-2 py-2.5 text-left transition-colors pointer:hover:bg-sunken/60"
                        @click="open(exercise)"
                    >
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl"
                            :class="exercise.archived ? 'bg-sunken text-muted' : 'bg-accent-soft text-accent-text'"
                        >
                            <component :is="exerciseIcon(exercise)" class="size-5" :stroke-width="1.9" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-[15px] font-medium" :class="exercise.archived && 'text-muted'">
                                {{ exercise.name }}
                            </span>
                            <span class="block truncate text-small text-muted">
                                <template v-if="filter !== 'all' || query">{{ muscleLabel(exercise.muscle_group) }} · </template>
                                {{ equipmentLabel(exercise.equipment) }} · {{ kindShort(exercise.kind) }}
                            </span>
                        </span>
                        <ChevronRight class="size-4 shrink-0 text-muted" />
                    </button>
                </li>
            </ul>
        </section>

        <div v-if="!visible.length" class="card flex flex-col items-center gap-3 px-6 py-10 text-center">
            <p class="text-[15px] text-ink-2">
                <template v-if="query">Tidak ada gerakan “{{ query }}”.</template>
                <template v-else-if="filter === 'archived'">Tidak ada gerakan yang diarsipkan.</template>
                <template v-else>Belum ada gerakan.</template>
            </p>
            <button v-if="filter !== 'archived'" type="button" class="btn btn-quiet" @click="open(null, query.trim())">
                <Plus class="size-4" />
                {{ query.trim() ? `Tambah “${query.trim()}”` : 'Tambah gerakan' }}
            </button>
        </div>
    </div>

    <Sheet :open="sheetOpen" :title="editing ? 'Ubah gerakan' : 'Gerakan baru'" @close="sheetOpen = false">
        <form id="exercise-form" class="flex flex-col gap-5 pb-2" @submit.prevent="save">
            <div>
                <label class="field-label" for="exercise-name">Nama</label>
                <input
                    id="exercise-name"
                    v-model="form.name"
                    data-autofocus
                    class="field"
                    maxlength="80"
                    placeholder="mis. Incline Bench Press"
                    autocomplete="off"
                />
                <p v-if="form.errors.name" class="field-error">{{ form.errors.name }}</p>
            </div>

            <div>
                <span class="field-label">Dicatat sebagai</span>
                <div class="grid grid-cols-2 gap-2">
                    <button
                        v-for="kind in catalog.kinds"
                        :key="kind.value"
                        type="button"
                        class="flex min-h-16 flex-col justify-center rounded-xl border px-3.5 py-2.5 text-left transition-colors"
                        :class="form.kind === kind.value ? 'border-accent bg-accent-soft' : 'border-line bg-surface pointer:hover:border-line-strong'"
                        :aria-pressed="form.kind === kind.value"
                        @click="form.kind = kind.value"
                    >
                        <span class="text-sm font-medium" :class="form.kind === kind.value && 'text-accent-text'">{{ kind.label }}</span>
                        <span class="text-small text-muted">{{ kind.hint }}</span>
                    </button>
                </div>
                <p v-if="form.errors.kind" class="field-error">{{ form.errors.kind }}</p>
                <p v-if="editing && form.kind !== editing.kind" class="mt-1.5 text-small text-warn">
                    Mengubah jenis tidak mengubah set yang sudah tercatat.
                </p>
            </div>

            <div>
                <span class="field-label">Kelompok otot</span>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="m in catalog.muscleGroups"
                        :key="m.value"
                        type="button"
                        class="chip"
                        :aria-pressed="form.muscle_group === m.value"
                        @click="form.muscle_group = m.value"
                    >
                        {{ m.label }}
                    </button>
                </div>
                <p v-if="form.errors.muscle_group" class="field-error">{{ form.errors.muscle_group }}</p>
            </div>

            <div>
                <span class="field-label">Alat</span>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="e in catalog.equipment"
                        :key="e.value"
                        type="button"
                        class="chip"
                        :aria-pressed="form.equipment === e.value"
                        @click="form.equipment = e.value"
                    >
                        {{ e.label }}
                    </button>
                </div>
                <p v-if="form.errors.equipment" class="field-error">{{ form.errors.equipment }}</p>
            </div>

            <div>
                <label class="field-label" for="exercise-notes">Catatan <span class="font-normal text-muted">(opsional)</span></label>
                <textarea
                    id="exercise-notes"
                    v-model="form.notes"
                    rows="3"
                    maxlength="500"
                    class="field h-auto py-3"
                    placeholder="mis. setelan kursi 4, pegangan lebar"
                />
                <p v-if="form.errors.notes" class="field-error">{{ form.errors.notes }}</p>
            </div>
        </form>

        <template #footer>
            <p v-if="!online" class="mb-2 text-small text-muted">Sedang offline. Menyimpan gerakan butuh koneksi.</p>
            <div class="flex items-center gap-2">
                <button v-if="editing" type="button" class="btn btn-quiet h-12" :disabled="!online" @click="toggleArchive">
                    <ArchiveRestore v-if="editing.archived" class="size-4" />
                    <Archive v-else class="size-4" />
                    {{ editing.archived ? 'Pulihkan' : 'Arsipkan' }}
                </button>
                <button type="submit" form="exercise-form" class="btn btn-primary h-12 flex-1" :disabled="form.processing || !online">
                    {{ editing ? 'Simpan perubahan' : 'Tambah gerakan' }}
                </button>
            </div>
        </template>
    </Sheet>
</template>
