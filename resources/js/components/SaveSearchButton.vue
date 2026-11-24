<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    // The filters the server applied, not the form's unsubmitted values: what
    // gets saved is exactly the search the user is looking at.
    filters: { type: Object, required: true },
});

const processing = ref(false);

const criteria = computed(() =>
    Object.fromEntries(
        Object.entries(props.filters).filter(([, value]) => value !== '' && value !== null),
    ),
);

const hasFilters = computed(() => Object.keys(criteria.value).length > 0);

const error = computed(() => usePage().props.errors?.criteria);

function save() {
    router.post('/saved-searches', criteria.value, {
        onStart: () => (processing.value = true),
        onFinish: () => (processing.value = false),
    });
}
</script>

<template>
    <div v-if="hasFilters" class="mb-6 -mt-3">
        <button
            type="button"
            :disabled="processing"
            class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 disabled:opacity-50"
            @click="save"
        >
            Save this search
        </button>

        <p v-if="error" class="mt-2 text-sm text-red-600">{{ error }}</p>
    </div>
</template>
