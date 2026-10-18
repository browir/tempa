<script setup>
import { useIntervalFn, useOnline } from '@vueuse/core';
import { ChevronDown, CloudUpload, TriangleAlert, WifiOff } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { flushOutbox, removeFromOutbox, useOutbox } from '@/composables/useOutbox';

/**
 * Status offline + antrean yang belum terkirim. Saat online, perubahan biasa terkirim dalam
 * hitungan detik, jadi bar baru muncul bila antrean tertahan (> 8 dtk), ada yang ditolak, atau offline.
 */
const online = useOnline();
const { items, sending, needsLogin } = useOutbox();
const expanded = ref(false);
const now = ref(Date.now());
useIntervalFn(() => (now.value = Date.now()), 2000);

const failed = computed(() => items.value.filter((i) => i.error));
const stuck = computed(() => items.value.some((i) => now.value - i.created_at > 8000));
const visible = computed(() => !online.value || failed.value.length > 0 || needsLogin.value || stuck.value);

const message = computed(() => {
    const n = items.value.length;
    if (failed.value.length) return 'Ada perubahan yang ditolak server';
    if (!online.value) return n ? `Offline · ${n} perubahan tersimpan di HP` : 'Offline · menampilkan data terakhir yang tersimpan';
    if (needsLogin.value) return `${n} perubahan menunggu · masuk lagi untuk mengirim`;
    return sending.value ? `Mengirim ${n} perubahan…` : `${n} perubahan menunggu dikirim`;
});
</script>

<template>
    <Transition name="offline-bar">
        <div v-if="visible" class="mb-5 overflow-hidden rounded-2xl border border-line bg-surface text-[13px]">
            <div class="flex min-h-12 items-center gap-2.5 px-3.5 py-2">
                <TriangleAlert v-if="failed.length" class="size-4 shrink-0 text-neg" />
                <WifiOff v-else-if="!online" class="size-4 shrink-0 text-muted" />
                <CloudUpload v-else class="size-4 shrink-0 text-accent-text" :class="sending && 'animate-pulse'" />
                <span class="min-w-0 flex-1 text-ink-2">{{ message }}</span>
                <button
                    v-if="online && items.length && !sending && !failed.length"
                    type="button"
                    class="h-9 rounded-full px-3 font-medium text-accent-text hover:bg-sunken"
                    @click="flushOutbox"
                >
                    Kirim
                </button>
                <button
                    v-if="items.length"
                    type="button"
                    class="icon-btn -mr-1.5 size-10"
                    :aria-expanded="expanded"
                    aria-label="Lihat antrean"
                    @click="expanded = !expanded"
                >
                    <ChevronDown class="size-4 transition-transform" :class="expanded && 'rotate-180'" />
                </button>
            </div>
            <ul v-if="expanded && items.length" class="max-h-64 overflow-y-auto border-t border-line px-3.5 py-1">
                <li v-for="item in items" :key="item.id" class="flex min-h-11 items-center gap-3 py-2">
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-ink">{{ item.summary?.title ?? 'Perubahan' }}</span>
                        <span v-if="item.error" class="block text-small text-neg">{{ item.error }}</span>
                    </span>
                    <button
                        v-if="item.error"
                        type="button"
                        class="h-9 px-2 text-muted hover:text-neg"
                        @click="removeFromOutbox(item.id)"
                    >
                        Buang
                    </button>
                </li>
            </ul>
        </div>
    </Transition>
</template>

<style scoped>
.offline-bar-enter-active,
.offline-bar-leave-active {
    transition:
        opacity 0.25s ease,
        transform 0.3s var(--ease-out-soft);
}
.offline-bar-enter-from,
.offline-bar-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}
</style>
