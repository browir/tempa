import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Label otot / alat / jenis gerakan dari props bersama `catalog`
 * (sumbernya App\Support\ExerciseCatalog, jadi tidak diduplikasi di JS).
 */
export function useCatalog() {
    const page = usePage();
    const catalog = computed(() => page.props.catalog ?? { muscleGroups: [], equipment: [], kinds: [] });

    const byValue = (key) => computed(() => Object.fromEntries(catalog.value[key].map((o) => [o.value, o])));
    const muscles = byValue('muscleGroups');
    const equipment = byValue('equipment');
    const kinds = byValue('kinds');

    return {
        catalog,
        muscleLabel: (value) => muscles.value[value]?.label ?? value,
        equipmentLabel: (value) => equipment.value[value]?.label ?? value,
        kindLabel: (value) => kinds.value[value]?.label ?? value,
        kindHint: (value) => kinds.value[value]?.hint ?? '',
        kindShort: (value) => kinds.value[value]?.short ?? '',
    };
}
