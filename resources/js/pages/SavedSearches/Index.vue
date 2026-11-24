<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../components/AppLayout.vue';
import SavedSearchSummary from '../../components/SavedSearchSummary.vue';
import { currentMatchesUrl } from '../../saved-search';

defineProps({
    savedSearches: { type: Array, required: true },
});

function destroy(search) {
    router.delete(`/saved-searches/${search.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Saved searches" />

    <AppLayout heading="Saved searches" subheading="We'll alert you when new listings match.">
        <ul v-if="savedSearches.length" class="space-y-3">
            <li
                v-for="search in savedSearches"
                :key="search.id"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4"
            >
                <SavedSearchSummary :search="search" class="font-medium" />

                <div class="flex items-center gap-4 text-sm">
                    <Link
                        :href="currentMatchesUrl(search)"
                        class="text-slate-600 underline-offset-2 hover:text-slate-900 hover:underline"
                    >
                        View current matches
                    </Link>
                    <button
                        type="button"
                        class="text-red-600 underline-offset-2 hover:underline"
                        @click="destroy(search)"
                    >
                        Delete
                    </button>
                </div>
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
