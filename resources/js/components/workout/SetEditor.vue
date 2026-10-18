<script setup>
import { Check, Minus, Plus, RotateCcw, Trash2 } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import Keypad from '@/components/ui/Keypad.vue';
import Segmented from '@/components/ui/Segmented.vue';
import Sheet from '@/components/ui/Sheet.vue';
import { SET_KINDS, cardioSpeed, describeSet, durationTextDisplay, fieldsFor, fromText, toText } from '@/lib/sets';

/**
 * Ubah satu set: angka besar + stepper ± + keypad sendiri (tanpa keyboard HP).
 * Ketikan pertama setelah memilih isian mengganti nilainya; ± menambah/mengurangi sesuai langkah.
 */
const props = defineProps({
    open: { type: Boolean, default: false },
    exercise: { type: Object, default: null }, // { name, kind }
    set: { type: Object, default: null },
    label: { type: String, default: '' }, // "Set 2"
    previous: { type: Object, default: null },
});
const emit = defineEmits(['close', 'save', 'remove']);

const fields = computed(() => fieldsFor(props.exercise?.kind));
const text = reactive({});
const kind = ref('normal');
const activeKey = ref(null);
const fresh = ref(true);

watch(
    () => [props.open, props.set],
    ([open]) => {
        if (!open || !props.set) return;
        for (const f of fields.value) text[f.key] = toText(f, props.set[f.key]);
        kind.value = props.set.kind;
        activeKey.value = fields.value[0]?.key ?? null;
        fresh.value = true;
    },
    { immediate: true },
);

const active = computed(() => fields.value.find((f) => f.key === activeKey.value));

function select(key) {
    activeKey.value = key;
    fresh.value = true;
}

function display(field) {
    const t = text[field.key];
    if (field.type === 'duration') return t ? durationTextDisplay(t) : '0:00';
    return t || '0';
}

function press(key) {
    const f = active.value;
    if (!f) return;
    let t = fresh.value ? '' : (text[f.key] ?? '');
    fresh.value = false;

    if (key === 'clear') t = '';
    else if (key === 'back') t = t.slice(0, -1);
    else if (key === ',') {
        if (!t.includes(',')) t = (t || '0') + ',';
    } else {
        // Batas wajar panjang ketikan; desimal maks. 2 (berat) / 2 (km).
        const [, dec] = t.split(',');
        if (dec !== undefined && dec.length >= 2) return;
        if (t.replace(',', '').length >= (f.type === 'duration' ? 6 : 5)) return;
        t = t === '0' ? key : t + key;
    }
    text[f.key] = t;
}

function step(field, dir) {
    select(field.key);
    const current = fromText(field, text[field.key]) ?? 0;
    const next = Math.max(0, current + dir * field.step);
    text[field.key] = next === 0 && field.optional ? '' : toText(field, next);
    navigator.vibrate?.(6);
}

function values() {
    return Object.fromEntries(fields.value.map((f) => [f.key, fromText(f, text[f.key])]));
}

function usePrevious() {
    if (!props.previous) return;
    for (const f of fields.value) text[f.key] = toText(f, props.previous[f.key]);
    fresh.value = true;
}

function save(done) {
    emit('save', { patch: { ...values(), kind: kind.value }, done });
}

const kindOptions = SET_KINDS.map((k) => ({ value: k.value, label: k.label }));
const isDone = computed(() => props.set?.done_at != null);
const speed = computed(() => (props.exercise?.kind === 'cardio' ? cardioSpeed(values()) : ''));
</script>

<template>
    <Sheet :open="open" :title="exercise?.name ?? ''" @close="emit('close')">
        <template #header>
            <div class="min-w-0">
                <p class="eyebrow">{{ label }}</p>
                <h2 class="truncate text-[17px] font-semibold tracking-tight">{{ exercise?.name }}</h2>
            </div>
        </template>

        <div v-if="set" class="flex flex-col gap-4 pb-2">
            <button
                v-if="previous && describeSet(exercise?.kind, previous)"
                type="button"
                class="flex min-h-11 items-center justify-between gap-3 rounded-xl bg-sunken px-3.5 text-left text-small"
                @click="usePrevious"
            >
                <span class="text-muted">
                    Sebelumnya <span class="ml-1 font-mono text-ink tnum">{{ describeSet(exercise?.kind, previous) }}</span>
                </span>
                <span class="font-medium text-accent-text">Pakai</span>
            </button>

            <div class="grid gap-2" :class="fields.length > 1 ? 'grid-cols-2' : 'grid-cols-1'">
                <div
                    v-for="field in fields"
                    :key="field.key"
                    class="rounded-2xl border p-2 transition-colors"
                    :class="activeKey === field.key ? 'border-accent bg-accent-soft/60' : 'border-line bg-surface'"
                >
                    <p class="px-1.5 pt-0.5 text-small font-medium text-muted">
                        {{ field.label }}<span v-if="field.unit"> ({{ field.unit }})</span>
                    </p>
                    <button
                        type="button"
                        class="num flex h-14 w-full items-center justify-center text-[34px] font-semibold"
                        :class="!text[field.key] && 'text-muted'"
                        :aria-label="`Ketik ${field.label}`"
                        @click="select(field.key)"
                    >
                        {{ display(field) }}<span v-if="activeKey === field.key && !fresh" class="caret" aria-hidden="true" />
                    </button>
                    <div class="grid grid-cols-2 gap-1.5">
                        <button type="button" class="step" :aria-label="`Kurangi ${field.label}`" @click="step(field, -1)">
                            <Minus class="size-5" />
                        </button>
                        <button type="button" class="step" :aria-label="`Tambah ${field.label}`" @click="step(field, 1)">
                            <Plus class="size-5" />
                        </button>
                    </div>
                </div>
            </div>

            <p v-if="speed" class="-mt-1 text-center text-small text-muted">
                Kecepatan rata-rata <span class="font-mono text-ink tnum">{{ speed }}</span> km/jam
            </p>

            <Segmented v-model="kind" :options="kindOptions" size="sm" />

            <Keypad :decimal="active?.type === 'kg' || active?.type === 'km'" @press="press" />
        </div>

        <template #footer>
            <div class="flex items-center gap-2">
                <button type="button" class="btn btn-quiet h-12 px-3.5 text-neg" aria-label="Hapus set" @click="emit('remove')">
                    <Trash2 class="size-[18px]" />
                </button>
                <template v-if="isDone">
                    <button type="button" class="btn btn-quiet h-12" @click="save(false)">
                        <RotateCcw class="size-4" />
                        Belum
                    </button>
                    <button type="button" class="btn btn-primary h-12 flex-1" @click="save(null)">Simpan</button>
                </template>
                <template v-else>
                    <button type="button" class="btn btn-quiet h-12" @click="save(null)">Simpan</button>
                    <button type="button" class="btn btn-primary h-12 flex-1" @click="save(true)">
                        <Check class="size-[18px]" :stroke-width="2.6" />
                        Selesai set
                    </button>
                </template>
            </div>
        </template>
    </Sheet>
</template>

<style scoped>
@reference '../../../css/app.css';

.step {
    @apply flex h-12 items-center justify-center rounded-xl bg-sunken text-ink-2 transition-transform active:scale-95;
}
.caret {
    @apply ml-0.5 inline-block h-8 w-[2px] animate-pulse bg-accent-text align-middle;
}
</style>
