<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { ChartLine, Dumbbell, History, House, LogOut, Moon, Settings, Sun } from 'lucide-vue-next';
import { computed } from 'vue';
import { route } from 'ziggy-js';
import OfflineBar from '@/components/OfflineBar.vue';
import TempaMark from '@/components/ui/TempaMark.vue';
import Toaster from '@/components/ui/Toaster.vue';
import { toggleTheme } from '@/composables/useTheme';

const page = usePage();
const user = computed(() => page.props.auth.user);
const initials = computed(() =>
    (user.value?.name ?? '')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0])
        .join('')
        .toUpperCase(),
);

// Menu muncul sendiri begitu route-nya ada (fitur ditambahkan bertahap).
const nav = [
    { label: 'Beranda', route: 'dashboard', icon: House, match: 'Dashboard' },
    { label: 'Latihan', route: 'workouts.index', icon: Dumbbell, match: ['workouts/', 'exercises/', 'routines/'] },
    { label: 'Riwayat', route: 'history.index', icon: History, match: 'history/' },
    { label: 'Progres', route: 'progress.index', icon: ChartLine, match: 'progress/' },
    { label: 'Pengaturan', route: 'settings', icon: Settings, match: 'settings/' },
].filter((item) => route().has(item.route));

const isActive = (item) => [].concat(item.match).some((m) => page.component.startsWith(m));
</script>

<template>
    <div class="min-h-dvh md:grid md:grid-cols-[248px_1fr]">
        <!-- Cahaya bara lembut di atas halaman: memberi kedalaman tanpa mengganggu isi. -->
        <div
            class="pointer-events-none fixed inset-x-0 top-0 -z-10 h-[420px]"
            style="background: radial-gradient(70% 100% at 50% -20%, var(--glow), transparent 70%)"
            aria-hidden="true"
        />

        <!-- Sidebar (desktop) -->
        <aside class="sticky top-0 hidden h-dvh flex-col border-r border-line bg-surface/55 px-4 py-6 backdrop-blur-xl md:flex">
            <Link :href="route('dashboard')" class="mb-8 flex items-center gap-2.5 px-2">
                <span
                    class="flex size-8 items-center justify-center rounded-[10px] bg-gradient-to-br from-(--hero-from) to-(--hero-to) text-white shadow-[0_6px_14px_-6px_var(--hero-to)]"
                >
                    <TempaMark class="size-5" />
                </span>
                <span class="text-[18px] font-semibold tracking-tight">Tempa</span>
            </Link>

            <p class="eyebrow mb-2 px-3">Menu</p>
            <nav class="flex flex-col gap-0.5">
                <Link
                    v-for="item in nav"
                    :key="item.route"
                    :href="route(item.route)"
                    class="relative flex h-10 items-center gap-3 rounded-xl px-3 text-[14px] transition-colors"
                    :class="isActive(item) ? 'bg-accent-soft font-medium text-accent-text' : 'text-ink-2 hover:bg-sunken/70 hover:text-ink'"
                    :aria-current="isActive(item) ? 'page' : undefined"
                >
                    <span
                        v-if="isActive(item)"
                        class="absolute top-2 bottom-2 -left-4 w-[3px] rounded-r-full bg-accent-text"
                        aria-hidden="true"
                    />
                    <component :is="item.icon" class="size-[18px]" :stroke-width="isActive(item) ? 2.1 : 1.8" />
                    {{ item.label }}
                </Link>
            </nav>

            <div class="mt-auto flex items-center gap-2.5 rounded-2xl border border-line bg-surface p-2.5 shadow-card">
                <span
                    class="flex size-9 shrink-0 items-center justify-center rounded-full bg-accent-soft text-[13px] font-semibold text-accent-text"
                >
                    {{ initials || 'T' }}
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-[13px] font-medium">{{ user?.name }}</p>
                    <p class="truncate text-tiny text-muted">{{ user?.email }}</p>
                </div>
                <button type="button" class="icon-btn size-8" aria-label="Ganti tema" title="Ganti tema" @click="toggleTheme">
                    <Sun class="hidden size-4 dark:block" />
                    <Moon class="size-4 dark:hidden" />
                </button>
                <Link :href="route('logout')" method="post" as="button" class="icon-btn size-8" aria-label="Keluar" title="Keluar">
                    <LogOut class="size-4" />
                </Link>
            </div>
        </aside>

        <!-- Konten -->
        <main class="min-w-0 pb-[calc(112px+env(safe-area-inset-bottom))] md:pb-16">
            <div
                :key="page.component"
                class="page-enter mx-auto w-full max-w-[1080px] px-4 pt-[max(1.25rem,env(safe-area-inset-top))] md:px-10 md:pt-10"
            >
                <OfflineBar />
                <slot />
            </div>
        </main>

        <!-- Navigasi bawah (HP): pil mengambang di zona jempol -->
        <nav
            class="fixed inset-x-3 bottom-[max(12px,env(safe-area-inset-bottom))] z-30 mx-auto max-w-md rounded-[26px] border border-line bg-surface/85 shadow-lift backdrop-blur-xl md:hidden"
            aria-label="Navigasi utama"
        >
            <div class="grid h-16 items-center px-1.5" :style="{ gridTemplateColumns: `repeat(${nav.length}, minmax(0, 1fr))` }">
                <Link
                    v-for="item in nav"
                    :key="item.route"
                    :href="route(item.route)"
                    class="flex h-14 flex-col items-center justify-center gap-0.5 text-2xs transition-colors"
                    :class="isActive(item) ? 'font-semibold text-accent-text' : 'text-muted'"
                    :aria-current="isActive(item) ? 'page' : undefined"
                >
                    <span
                        class="flex h-7 w-12 items-center justify-center rounded-full transition-colors duration-200"
                        :class="isActive(item) && 'bg-accent-soft'"
                    >
                        <component :is="item.icon" class="size-[20px]" :stroke-width="isActive(item) ? 2.2 : 1.7" />
                    </span>
                    {{ item.label }}
                </Link>
            </div>
        </nav>

        <Toaster />
    </div>
</template>
