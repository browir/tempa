<script setup>
import { Timer, X } from 'lucide-vue-next';
import { adjustRest, stopRest, useRestTimer } from '@/composables/useRestTimer';
import { formatDuration } from '@/lib/units';

/** Bar timer istirahat mengambang di zona jempol (di atas navigasi bawah HP; pojok kanan bawah desktop). */
const { rest, remaining, progress } = useRestTimer();
</script>

<template>
    <Transition name="rest">
        <div
            v-if="rest"
            class="fixed inset-x-3 bottom-[calc(max(12px,env(safe-area-inset-bottom))+72px)] z-30 mx-auto max-w-md overflow-hidden rounded-[22px] border border-line bg-surface/95 shadow-lift backdrop-blur-xl md:inset-x-auto md:right-6 md:bottom-6 md:w-[380px]"
            role="timer"
            :aria-label="`Istirahat, sisa ${formatDuration(remaining)}`"
        >
            <div class="flex items-center gap-2 px-3 py-2">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-accent-soft text-accent-text">
                    <Timer class="size-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-small text-muted">Istirahat{{ rest.label ? ` · ${rest.label}` : '' }}</p>
                    <p class="num text-[26px] leading-none font-semibold" aria-hidden="true">{{ formatDuration(remaining) }}</p>
                </div>
                <button type="button" class="adj" aria-label="Kurangi 15 detik" @click="adjustRest(-15)">−15</button>
                <button type="button" class="adj" aria-label="Tambah 15 detik" @click="adjustRest(15)">+15</button>
                <button type="button" class="icon-btn size-12" aria-label="Lewati istirahat" @click="stopRest">
                    <X class="size-5" />
                </button>
            </div>
            <div class="h-1 bg-sunken">
                <div class="h-full bg-accent transition-[width] duration-300 ease-linear" :style="{ width: `${progress * 100}%` }" />
            </div>
        </div>
    </Transition>
</template>

<style scoped>
@reference '../../../css/app.css';

.adj {
    @apply flex h-12 w-12 font-mono tnum shrink-0 items-center justify-center rounded-xl bg-sunken text-[14px] font-semibold text-ink-2 transition-transform active:scale-95;
}
.rest-enter-active,
.rest-leave-active {
    transition:
        opacity 0.25s ease,
        transform 0.35s var(--ease-sheet);
}
.rest-enter-from,
.rest-leave-to {
    opacity: 0;
    transform: translateY(16px);
}
</style>
