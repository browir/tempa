import { Bike, Dumbbell, HeartPulse, PersonStanding, Timer } from 'lucide-vue-next';

/** Ikon gerakan: sepeda mendapat ikon sendiri, kardio lain memakai detak jantung. */
export function exerciseIcon(exercise) {
    if (exercise.kind === 'cardio') return exercise.equipment === 'bike' ? Bike : HeartPulse;
    if (exercise.kind === 'time') return Timer;
    if (exercise.kind === 'bodyweight') return PersonStanding;
    return Dumbbell;
}

/** Huruf kecil tanpa diakritik, untuk pencarian. */
export function normalize(text) {
    return String(text ?? '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .trim();
}
