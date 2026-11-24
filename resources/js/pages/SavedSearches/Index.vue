<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '../../components/AppLayout.vue';

defineProps({
    savedSearches: { type: Array, required: true },
});
</script>

<template>
    <Head title="Saved searches" />

    <AppLayout heading="Saved searches" subheading="We'll alert you when new listings match.">
        <ul v-if="savedSearches.length" class="space-y-3">
            <li
                v-for="search in savedSearches"
                :key="search.id"
                class="rounded-xl border border-slate-200 bg-white p-4"
            >
                Saved search #{{ search.id }}
            </li>
        </ul>

        <p
            v-else
            class="rounded-xl border border-dashed border-slate-300 p-10 text-center text-slate-500"
        >
            No saved searches yet. Filter the listings and choose "Save this search".
        </p>
    </AppLayout>
</template>
