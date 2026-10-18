<script setup>
import { Delete } from 'lucide-vue-next';

/** Keypad angka besar untuk satu tangan (gaya Keypad.vue Sisih). Emit: 0–9, ",", "back", "clear". */
defineProps({
    decimal: { type: Boolean, default: false },
});
const emit = defineEmits(['press']);

let holdTimer = null;

function press(key) {
    navigator.vibrate?.(4);
    emit('press', key);
}

// Tahan tombol hapus untuk mengosongkan.
function holdStart() {
    holdTimer = setTimeout(() => {
        navigator.vibrate?.(12);
        emit('press', 'clear');
        holdTimer = null;
    }, 450);
}
function holdEnd() {
    if (holdTimer) {
        clearTimeout(holdTimer);
        holdTimer = null;
        press('back');
    }
}
function holdCancel() {
    clearTimeout(holdTimer);
    holdTimer = null;
}
</script>

<template>
    <div class="grid grid-cols-3 gap-1.5 select-none" @contextmenu.prevent>
        <button v-for="key in ['1', '2', '3', '4', '5', '6', '7', '8', '9']" :key="key" type="button" class="key" @click="press(key)">
            {{ key }}
        </button>
        <button v-if="decimal" type="button" class="key" aria-label="Koma" @click="press(',')">,</button>
        <span v-else aria-hidden="true" />
        <button type="button" class="key" @click="press('0')">0</button>
        <button
            type="button"
            class="key text-ink-2"
            aria-label="Hapus (tahan untuk kosongkan)"
            @pointerdown="holdStart"
            @pointerup="holdEnd"
            @pointerleave="holdCancel"
        >
            <Delete class="size-[22px]" :stroke-width="1.7" />
        </button>
    </div>
</template>

<style scoped>
@reference '../../../css/app.css';

.key {
    @apply inline-flex h-[52px] items-center justify-center rounded-2xl bg-sunken font-mono text-[21px] font-medium text-ink transition-[transform,background-color] duration-100 active:scale-[0.94] active:bg-line;
}
</style>
