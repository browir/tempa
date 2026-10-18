import { computed, ref, watch } from 'vue';
import { cancelPending, enqueue, newClientId } from '@/composables/useOutbox';

/**
 * Sesi latihan aktif, offline-first: sumber kebenaran di HP (localStorage `tempa:workout`),
 * disimpan setiap perubahan sehingga reload / tab tertutup / HP mati tidak menghilangkan set.
 * Setiap perubahan juga diantrekan ke server lewat outbox (idempoten, dirujuk dengan client_id).
 *
 * Bentuk sesi sama dengan App\Support\WorkoutPayload::full():
 * { client_id, name, notes, started_at, ended_at, exercises: [{ client_id, exercise_id, sort, notes, sets: [set] }] }
 * set: { client_id, sort, kind, weight_g, reps, duration_s, distance_m, rpe, done_at }
 */
const KEY = 'tempa:workout';
const session = ref(read());

function read() {
    try {
        const value = JSON.parse(localStorage.getItem(KEY) || 'null');
        return value && value.client_id && !value.ended_at ? value : null;
    } catch {
        return null;
    }
}

watch(
    session,
    (value) => {
        try {
            if (value) localStorage.setItem(KEY, JSON.stringify(value));
            else localStorage.removeItem(KEY);
        } catch {
            // Penyimpanan diblokir: sesi tetap hidup selama tab terbuka, outbox tetap mengirim.
        }
    },
    { deep: true },
);

const SET_FIELDS = ['weight_g', 'reps', 'duration_s', 'distance_m', 'rpe'];

function defaultName(date = new Date()) {
    const h = date.getHours();
    const part = h < 11 ? 'pagi' : h < 15 ? 'siang' : h < 18 ? 'sore' : 'malam';
    return `Latihan ${part}`;
}

function blankSet(sort, from = null) {
    const set = { client_id: newClientId(), sort, kind: 'normal', done_at: null };
    for (const f of SET_FIELDS) set[f] = from?.[f] ?? null;
    if (from?.kind && from.kind !== 'warmup') set.kind = from.kind;
    return set;
}

/* ---------- Sinkronisasi ---------- */

function workoutParams() {
    return { workout: session.value.client_id };
}

function syncSet(we, set, title) {
    enqueue({
        key: `set:${set.client_id}`,
        routeName: 'sets.upsert',
        method: 'PUT',
        params: { ...workoutParams(), clientId: set.client_id },
        payload: {
            workout_exercise: we.client_id,
            sort: set.sort,
            kind: set.kind,
            ...Object.fromEntries(SET_FIELDS.map((f) => [f, set[f]])),
            done_at: set.done_at,
        },
        summary: { title },
    });
}

function syncExercise(we, title) {
    enqueue({
        key: `we:${we.client_id}`,
        routeName: 'workout-exercises.store',
        params: workoutParams(),
        payload: { client_id: we.client_id, exercise_id: we.exercise_id, sort: we.sort },
        summary: { title: `Tambah ${title}` },
    });
}

/* ---------- Aksi ---------- */

export function startWorkout(name = null) {
    if (session.value) return session.value;
    session.value = {
        client_id: newClientId(),
        name: name ?? defaultName(),
        notes: null,
        started_at: new Date().toISOString(),
        ended_at: null,
        exercises: [],
    };
    enqueue({
        key: `workout:${session.value.client_id}`,
        routeName: 'workouts.store',
        payload: { client_id: session.value.client_id, started_at: session.value.started_at, name: session.value.name },
        summary: { title: `Mulai ${session.value.name}` },
    });
    return session.value;
}

/** Ambil sesi aktif dari server bila HP ini belum punya (mis. dimulai di perangkat lain). */
export function hydrateFromServer(active) {
    // Salinan JSON, bukan structuredClone: props Inertia adalah Proxy reaktif (DataCloneError).
    if (!session.value && active && !active.ended_at) session.value = JSON.parse(JSON.stringify(active));
}

export function renameWorkout(name) {
    session.value.name = name;
    enqueue({
        key: `workout-meta:${session.value.client_id}`,
        routeName: 'workouts.update',
        method: 'PUT',
        params: workoutParams(),
        payload: { name, notes: session.value.notes },
        summary: { title: 'Ubah nama sesi' },
    });
}

/**
 * Tambah gerakan; set diisi dari sesi terakhir gerakan itu ("Sebelumnya"),
 * belum ditandai selesai. Tanpa riwayat: satu set kosong.
 */
export function addExercises(exercises, history = {}) {
    for (const exercise of exercises) {
        const sort = Math.max(-1, ...session.value.exercises.map((e) => e.sort)) + 1;
        const previous = history[exercise.id]?.sets ?? [];
        const sets = (previous.length ? previous.slice(0, 12) : [null]).map((p, i) => {
            const set = blankSet(i, p);
            if (p?.kind) set.kind = p.kind;
            return set;
        });
        const we = { client_id: newClientId(), exercise_id: exercise.id, sort, notes: null, sets };
        session.value.exercises.push(we);
        syncExercise(we, exercise.name);
        sets.forEach((s, i) => syncSet(we, s, `${exercise.name} · set ${i + 1}`));
    }
}

/** Set baru menyalin set terakhir (atau set ke-n sesi sebelumnya), belum selesai. */
export function addSet(we, exercise, history = {}) {
    const last = we.sets.at(-1);
    const prev = history[exercise.id]?.sets?.[we.sets.length];
    const set = blankSet(Math.max(-1, ...we.sets.map((s) => s.sort)) + 1, last ?? prev);
    we.sets.push(set);
    syncSet(we, set, `${exercise.name} · set ${we.sets.length}`);
    return set;
}

export function updateSet(we, set, patch, title) {
    Object.assign(set, patch);
    syncSet(we, set, title);
}

export function removeSet(we, set, title) {
    const index = we.sets.indexOf(set);
    if (index === -1) return () => {};
    we.sets.splice(index, 1);
    enqueue({
        key: `set:${set.client_id}`,
        routeName: 'sets.destroy',
        method: 'DELETE',
        params: { ...workoutParams(), clientId: set.client_id },
        summary: { title: `Hapus ${title}` },
    });
    // Urungkan: kembalikan di posisi semula; upsert memulihkan set yang terhapus di server.
    return () => {
        const target = session.value?.exercises.find((e) => e.client_id === we.client_id);
        if (!target) return;
        target.sets.splice(Math.min(index, target.sets.length), 0, set);
        syncSet(target, set, title);
    };
}

export function removeExercise(we, title) {
    const index = session.value.exercises.indexOf(we);
    if (index === -1) return;
    session.value.exercises.splice(index, 1);
    // Set yang belum terkirim tidak perlu dikirim lagi; server menghapus set ikut gerakannya.
    const setKeys = new Set(we.sets.map((s) => `set:${s.client_id}`));
    cancelPending((i) => setKeys.has(i.key));
    enqueue({
        key: `we:${we.client_id}`,
        routeName: 'workout-exercises.destroy',
        method: 'DELETE',
        params: { ...workoutParams(), clientId: we.client_id },
        summary: { title: `Hapus ${title} dari sesi` },
    });
}

/** Selesai: kirim waktu selesai, lalu sesi lokal dikosongkan. Mengembalikan ringkasan sesi. */
export function finishWorkout() {
    const finished = { ...session.value, ended_at: new Date().toISOString() };
    enqueue({
        key: `workout-finish:${finished.client_id}`,
        routeName: 'workouts.finish',
        params: { workout: finished.client_id },
        payload: { ended_at: finished.ended_at },
        summary: { title: `Selesai ${finished.name}` },
        reload: true,
    });
    session.value = null;
    return finished;
}

/** Buang sesi: batalkan yang belum terkirim, minta server menghapus (soft delete). */
export function discardWorkout() {
    const id = session.value.client_id;
    cancelPending((i) => i.params?.workout === id || i.payload?.client_id === id);
    enqueue({
        key: `workout:${id}`,
        routeName: 'workouts.destroy',
        method: 'DELETE',
        params: { workout: id },
        summary: { title: 'Buang sesi' },
    });
    session.value = null;
}

export function useWorkout() {
    return {
        session,
        active: computed(() => session.value !== null),
    };
}
