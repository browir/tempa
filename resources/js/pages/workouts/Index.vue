<script setup>
import { Head } from '@inertiajs/vue3';
import { useNow } from '@vueuse/core';
import { Flag, Flame, Plus, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import Sheet from '@/components/ui/Sheet.vue';
import ExercisePicker from '@/components/workout/ExercisePicker.vue';
import SetEditor from '@/components/workout/SetEditor.vue';
import WorkoutExerciseCard from '@/components/workout/WorkoutExerciseCard.vue';
import { pushToast } from '@/composables/useToast';
import {
    addExercises,
    addSet,
    discardWorkout,
    finishWorkout,
    hydrateFromServer,
    removeExercise,
    removeSet,
    renameWorkout,
    startWorkout,
    updateSet,
    useWorkout,
} from '@/composables/useWorkout';
import { SET_KINDS, fieldsFor, isFilled, setBadges, volume } from '@/lib/sets';
import { formatDuration, formatKm } from '@/lib/units';

/**
 * Sesi latihan aktif. Isi sesi dibaca dari HP (useWorkout), bukan dari props, supaya tetap jalan
 * offline dan tidak hilang saat reload. Props server hanya sebagai cadangan & data pendukung.
 */
const props = defineProps({
    active: { type: Object, default: null },
    exercises: { type: Array, required: true },
    history: { type: Object, required: true },
    recent: { type: Array, required: true },
});

const { session } = useWorkout();
const now = useNow({ interval: 1000 });

onMounted(() => {
    hydrateFromServer(props.active);
    // Pintasan ikon PWA "Mulai latihan": /latihan?mulai=1
    const url = new URL(window.location.href);
    if (url.searchParams.has('mulai')) {
        url.searchParams.delete('mulai');
        window.history.replaceState(window.history.state, '', url);
        if (!session.value) startWorkout();
    }
});

const exercisesById = computed(() => Object.fromEntries(props.exercises.map((e) => [e.id, e])));
const exerciseOf = (entry) =>
    exercisesById.value[entry.exercise_id] ?? { id: entry.exercise_id, name: 'Gerakan', kind: 'weight_reps', muscle_group: 'other' };
const previousOf = (entry) => props.history[entry.exercise_id] ?? null;

const elapsed = computed(() => {
    if (!session.value) return '';
    const s = Math.max(0, Math.floor((now.value - new Date(session.value.started_at)) / 1000));
    return formatDuration(s);
});

function setTitle(entry, set) {
    const i = entry.sets.indexOf(set);
    return `${exerciseOf(entry).name} · set ${setBadges(entry.sets)[i] ?? i + 1}`;
}

/* ---------- Set ---------- */

function toggle(entry, set) {
    const exercise = exerciseOf(entry);
    if (set.done_at) {
        updateSet(entry, set, { done_at: null }, setTitle(entry, set));
        return;
    }
    const patch = { done_at: new Date().toISOString() };
    if (!isFilled(exercise.kind, set)) {
        // Kosong: pakai angka sesi sebelumnya bila ada, selain itu buka editor.
        const prev = previousOf(entry)?.sets?.[entry.sets.indexOf(set)];
        if (!prev || !isFilled(exercise.kind, prev)) {
            openEditor(entry, set);
            return;
        }
        for (const f of fieldsFor(exercise.kind)) patch[f.key] = prev[f.key];
    }
    updateSet(entry, set, patch, setTitle(entry, set));
    navigator.vibrate?.(15);
}

const KIND_ORDER = SET_KINDS.map((k) => k.value);
function cycleKind(entry, set) {
    const next = KIND_ORDER[(KIND_ORDER.indexOf(set.kind) + 1) % KIND_ORDER.length];
    updateSet(entry, set, { kind: next }, setTitle(entry, set));
    navigator.vibrate?.(6);
}

const editor = ref({ open: false, entry: null, set: null });
function openEditor(entry, set) {
    editor.value = { open: true, entry, set };
}
const editorLabel = computed(() => {
    const { entry, set } = editor.value;
    if (!entry || !set) return '';
    const badge = setBadges(entry.sets)[entry.sets.indexOf(set)];
    return SET_KINDS.find((k) => k.badge === badge)?.label ?? `Set ${badge}`;
});
const editorPrevious = computed(() => {
    const { entry, set } = editor.value;
    return entry && set ? (previousOf(entry)?.sets?.[entry.sets.indexOf(set)] ?? null) : null;
});

function saveSet({ patch, done }) {
    const { entry, set } = editor.value;
    const values = { ...patch };
    if (done === true) values.done_at = new Date().toISOString();
    if (done === false) values.done_at = null;
    updateSet(entry, set, values, setTitle(entry, set));
    editor.value.open = false;
    if (done) navigator.vibrate?.(15);
}

function deleteSet(entry = editor.value.entry, set = editor.value.set) {
    const title = setTitle(entry, set);
    const undo = removeSet(entry, set, title);
    editor.value.open = false;
    pushToast({ message: 'Set dihapus', undo });
}

/* ---------- Gerakan ---------- */

const pickerOpen = ref(false);
function add(list) {
    addExercises(list, props.history);
    pickerOpen.value = false;
    // Gulir ke gerakan yang baru ditambahkan.
    setTimeout(() => document.querySelector('[data-exercise]:last-of-type')?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 350);
}

const menu = ref({ open: false, entry: null, confirm: false });
function openMenu(entry) {
    menu.value = { open: true, entry, confirm: false };
}
function deleteExercise() {
    const entry = menu.value.entry;
    if (entry.sets.some((s) => s.done_at) && !menu.value.confirm) {
        menu.value.confirm = true;
        return;
    }
    removeExercise(entry, exerciseOf(entry).name);
    menu.value.open = false;
}

/* ---------- Selesai / buang ---------- */

const finishOpen = ref(false);
const confirmDiscard = ref(false);
const allSets = computed(() => session.value?.exercises.flatMap((e) => e.sets) ?? []);
const summary = computed(() => {
    const done = allSets.value.filter((s) => s.done_at);
    const cardio = (session.value?.exercises ?? [])
        .filter((e) => exerciseOf(e).kind === 'cardio')
        .flatMap((e) => e.sets.filter((s) => s.done_at));
    return {
        done: done.length,
        pending: allSets.value.length - done.length,
        volumeKg: Math.round(volume(done) / 1000),
        distance: cardio.reduce((sum, s) => sum + (s.distance_m ?? 0), 0),
    };
});

function openFinish() {
    confirmDiscard.value = false;
    finishOpen.value = true;
}

function finish() {
    const finished = finishWorkout();
    finishOpen.value = false;
    const minutes = Math.max(1, Math.round((new Date(finished.ended_at) - new Date(finished.started_at)) / 60000));
    pushToast({ message: `${finished.name} selesai · ${minutes} menit` });
    window.scrollTo({ top: 0 });
}

function discard() {
    if (!confirmDiscard.value) {
        confirmDiscard.value = true;
        return;
    }
    discardWorkout();
    finishOpen.value = false;
    pushToast({ message: 'Sesi dibuang' });
}

const nameDraft = ref('');
function commitName(event) {
    const name = event.target.value.trim();
    if (name && name !== session.value.name) renameWorkout(name.slice(0, 80));
    else event.target.value = session.value.name;
}

const nf = new Intl.NumberFormat('id-ID');
</script>

<template>
    <Head title="Latihan" />

    <!-- Belum ada sesi -->
    <template v-if="!session">
        <h1 class="page-title mb-6">Latihan</h1>
        <section class="hero-card p-6 md:p-8">
            <Flame class="absolute -right-6 -bottom-8 -z-10 size-48 text-white opacity-[0.08]" :stroke-width="1.5" />
            <p class="text-small font-medium tracking-[0.08em] text-(--hero-muted) uppercase">Siap menempa?</p>
            <p class="mt-2 max-w-sm text-[24px] leading-tight font-semibold tracking-[-0.03em]">
                Mulai sesi, tambah gerakan, centang setiap set.
            </p>
            <p class="mt-2 max-w-sm text-[14px] text-(--hero-muted)">
                Semua tersimpan di HP dulu — aman walau sinyal gym hilang.
            </p>
            <button
                type="button"
                class="mt-6 inline-flex h-14 w-full items-center justify-center gap-2 rounded-2xl bg-white text-[16px] font-semibold text-(--hero-from) transition active:scale-[0.98] sm:w-auto sm:px-8"
                @click="startWorkout()"
            >
                <Plus class="size-5" :stroke-width="2.6" />
                Mulai latihan
            </button>
        </section>
    </template>

    <!-- Sesi aktif (desktop: satu kolom selebar 2xl agar angka tetap dekat nama gerakan) -->
    <div v-else class="md:max-w-2xl">
        <header
            class="sticky top-0 z-20 -mx-4 mb-4 flex items-center gap-3 bg-paper/90 px-4 pt-[max(0.5rem,env(safe-area-inset-top))] pb-2 backdrop-blur-xl md:mx-0 md:px-0"
        >
            <div class="min-w-0 flex-1">
                <input
                    :value="session.name"
                    class="w-full truncate rounded-lg bg-transparent text-[20px] font-semibold tracking-tight outline-none focus:bg-sunken focus:px-2"
                    maxlength="80"
                    aria-label="Nama sesi"
                    enterkeyhint="done"
                    @focus="nameDraft = session.name"
                    @change="commitName"
                    @keydown.enter="$event.target.blur()"
                />
                <p class="text-small text-muted">
                    <span class="num text-ink-2">{{ elapsed }}</span>
                    · {{ summary.done }} set selesai
                </p>
            </div>
            <button type="button" class="btn btn-primary shrink-0" @click="openFinish">
                <Flag class="size-4" />
                Selesai
            </button>
        </header>

        <div class="flex flex-col gap-4">
            <WorkoutExerciseCard
                v-for="entry in session.exercises"
                :key="entry.client_id"
                data-exercise
                :entry="entry"
                :exercise="exerciseOf(entry)"
                :previous="previousOf(entry)"
                @edit="(set) => openEditor(entry, set)"
                @toggle="(set) => toggle(entry, set)"
                @cycle-kind="(set) => cycleKind(entry, set)"
                @add-set="addSet(entry, exerciseOf(entry), props.history)"
                @menu="openMenu(entry)"
            />

            <div v-if="!session.exercises.length" class="card px-6 py-10 text-center">
                <p class="text-[15px] text-ink-2">Sesi dimulai. Tambahkan gerakan pertama.</p>
            </div>

            <button
                type="button"
                class="flex h-14 w-full items-center justify-center gap-2 rounded-2xl border border-dashed border-line-strong text-[15px] font-medium text-accent-text transition-colors active:scale-[0.99] pointer:hover:bg-surface"
                @click="pickerOpen = true"
            >
                <Plus class="size-5" :stroke-width="2.4" />
                Tambah gerakan
            </button>
        </div>
    </div>

    <ExercisePicker
        :open="pickerOpen"
        :exercises="exercises"
        :recent="recent"
        :in-session="session?.exercises.map((e) => e.exercise_id) ?? []"
        @close="pickerOpen = false"
        @add="add"
    />

    <SetEditor
        :open="editor.open"
        :exercise="editor.entry ? exerciseOf(editor.entry) : null"
        :set="editor.set"
        :label="editorLabel"
        :previous="editorPrevious"
        @close="editor.open = false"
        @save="saveSet"
        @remove="deleteSet()"
    />

    <Sheet :open="menu.open" :title="menu.entry ? exerciseOf(menu.entry).name : ''" @close="menu.open = false">
        <div class="pb-2">
            <button
                type="button"
                class="btn h-12 w-full justify-start"
                :class="menu.confirm ? 'bg-neg text-white' : 'btn-quiet text-neg'"
                @click="deleteExercise"
            >
                <Trash2 class="size-4" />
                {{ menu.confirm ? 'Yakin? Set yang sudah selesai ikut terhapus' : 'Hapus dari sesi' }}
            </button>
        </div>
    </Sheet>

    <Sheet :open="finishOpen" title="Selesaikan latihan?" @close="finishOpen = false">
        <div v-if="session" class="flex flex-col gap-4 pb-2">
            <dl class="grid grid-cols-3 gap-2 text-center">
                <div class="rounded-2xl bg-sunken px-2 py-3">
                    <dt class="text-small text-muted">Durasi</dt>
                    <dd class="num text-[20px] font-semibold">{{ elapsed }}</dd>
                </div>
                <div class="rounded-2xl bg-sunken px-2 py-3">
                    <dt class="text-small text-muted">Set</dt>
                    <dd class="num text-[20px] font-semibold">{{ summary.done }}</dd>
                </div>
                <div class="rounded-2xl bg-sunken px-2 py-3">
                    <dt class="text-small text-muted">{{ summary.distance && !summary.volumeKg ? 'Jarak' : 'Volume' }}</dt>
                    <dd class="num text-[20px] font-semibold">
                        <template v-if="summary.distance && !summary.volumeKg">{{ formatKm(summary.distance) }} km</template>
                        <template v-else>{{ nf.format(summary.volumeKg) }} kg</template>
                    </dd>
                </div>
            </dl>
            <p v-if="summary.pending" class="text-small text-muted">
                {{ summary.pending }} set belum dicentang — tetap tersimpan, tapi tidak dihitung.
            </p>
        </div>
        <template #footer>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="btn h-12"
                    :class="confirmDiscard ? 'bg-neg text-white' : 'btn-quiet text-neg'"
                    @click="discard"
                >
                    <Trash2 class="size-4" />
                    {{ confirmDiscard ? 'Yakin buang?' : 'Buang' }}
                </button>
                <button type="button" class="btn btn-primary h-12 flex-1" @click="finish">
                    <Flag class="size-4" />
                    Selesai latihan
                </button>
            </div>
        </template>
    </Sheet>
</template>
