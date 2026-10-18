<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff } from 'lucide-vue-next';
import { ref } from 'vue';
import { route } from 'ziggy-js';
import TempaMark from '@/components/ui/TempaMark.vue';

const form = useForm({ email: '', password: '' });
const reveal = ref(false);

function submit() {
    form.post(route('login.store'), { onFinish: () => form.reset('password') });
}

// Potongan "buku latihan" dekoratif di sisi kiri (desktop).
const log = [
    ['Bench Press', '60 kg × 8'],
    ['Back Squat', '80 kg × 5'],
    ['Pull Up', '10 reps'],
    ['Plank', '1:30'],
    ['Bersepeda', '42,3 km · 1:38:20'],
];
</script>

<template>
    <Head title="Masuk" />

    <div class="grid min-h-dvh lg:grid-cols-[1.1fr_1fr]">
        <!-- Sisi kiri: pernyataan + buku latihan -->
        <aside class="hero-card relative m-3 hidden flex-col justify-between p-12 lg:flex">
            <span class="absolute -top-32 -right-24 -z-10 size-96 rounded-full bg-white/10 blur-3xl" aria-hidden="true" />
            <TempaMark class="absolute -right-20 -bottom-44 -z-10 size-[380px] text-white opacity-[0.06]" />
            <div class="flex items-center gap-2.5">
                <span class="hero-glass flex size-8 items-center justify-center rounded-[10px]">
                    <TempaMark class="size-5" />
                </span>
                <span class="text-[17px] font-semibold tracking-tight">Tempa</span>
            </div>

            <div>
                <p class="max-w-md text-[44px] leading-[1.05] font-semibold tracking-[-0.04em]">
                    Satu repetisi lagi,<br />
                    <span class="text-(--hero-muted)">setiap minggu sedikit lebih kuat.</span>
                </p>

                <ul class="mt-12 max-w-md">
                    <li
                        v-for="([label, value], i) in log"
                        :key="label"
                        class="log-row flex items-baseline gap-3 border-b border-dashed border-white/15 py-3 text-[15px]"
                        :style="{ animationDelay: `${120 + i * 90}ms` }"
                    >
                        <span class="text-(--hero-fg)/85">{{ label }}</span>
                        <span class="flex-1" />
                        <span class="font-mono tnum">{{ value }}</span>
                    </li>
                </ul>
            </div>

            <p class="text-[13px] text-(--hero-muted)">Buku latihan pribadi.</p>
        </aside>

        <!-- Form -->
        <main class="relative isolate flex items-center justify-center px-6 py-12">
            <div
                class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-80"
                style="background: radial-gradient(70% 100% at 50% -20%, var(--glow), transparent 70%)"
                aria-hidden="true"
            />
            <form class="w-full max-w-[360px]" @submit.prevent="submit">
                <div class="mb-10 flex items-center gap-2.5 lg:hidden">
                    <span
                        class="flex size-9 items-center justify-center rounded-[11px] bg-gradient-to-br from-(--hero-from) to-(--hero-to) text-white shadow-[0_8px_18px_-8px_var(--hero-to)]"
                    >
                        <TempaMark class="size-5" />
                    </span>
                    <span class="text-[19px] font-semibold tracking-tight">Tempa</span>
                </div>

                <h1 class="page-title mb-1.5">Masuk</h1>
                <p class="mb-8 text-[15px] text-muted">Lanjutkan menempa dirimu.</p>

                <div class="mb-4">
                    <label class="field-label" for="email">Email</label>
                    <input id="email" v-model="form.email" type="email" class="field h-12" autocomplete="email" autofocus required />
                    <p v-if="form.errors.email" class="field-error">{{ form.errors.email }}</p>
                </div>

                <div class="mb-8">
                    <label class="field-label" for="password">Kata sandi</label>
                    <div class="relative">
                        <input
                            id="password"
                            v-model="form.password"
                            :type="reveal ? 'text' : 'password'"
                            class="field h-12 pr-14"
                            autocomplete="current-password"
                            required
                        />
                        <button
                            type="button"
                            class="icon-btn absolute top-1/2 right-1 -translate-y-1/2 touch:size-10"
                            :aria-label="reveal ? 'Sembunyikan' : 'Tampilkan'"
                            @click="reveal = !reveal"
                        >
                            <EyeOff v-if="reveal" class="size-[18px]" />
                            <Eye v-else class="size-[18px]" />
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary h-12 w-full text-[15px]" :disabled="form.processing">
                    {{ form.processing ? 'Memeriksa…' : 'Masuk' }}
                </button>
            </form>
        </main>
    </div>
</template>

<style scoped>
.log-row {
    animation: rise 0.5s var(--ease-out-soft) both;
}
</style>
