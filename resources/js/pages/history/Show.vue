<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { route } from 'ziggy-js';
import WorkoutSummary from '@/components/workout/WorkoutSummary.vue';

const props = defineProps({
    workout: { type: Object, required: true },
    exercises: { type: Array, required: true },
});

const started = new Date(props.workout.started_at);
const dateLabel = new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(started);
const timeLabel = new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit' }).format(started);
</script>

<template>
    <Head :title="workout.name || 'Latihan'" />

    <div class="max-w-2xl">
        <Link :href="route('history.index')" class="btn btn-ghost -ml-3 mb-2 px-3">
            <ArrowLeft class="size-4" />
            Riwayat
        </Link>
        <header class="mb-5">
            <p class="eyebrow mb-1">{{ dateLabel }} · {{ timeLabel }}</p>
            <h1 class="page-title">{{ workout.name || 'Latihan' }}</h1>
        </header>

        <WorkoutSummary :workout="workout" :exercises="exercises" />
    </div>
</template>
