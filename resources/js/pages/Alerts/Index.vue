<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '../../components/AppLayout.vue';
import ListingCard from '../../components/ListingCard.vue';
import Pagination from '../../components/Pagination.vue';
import SavedSearchSummary from '../../components/SavedSearchSummary.vue';
import { formatDate } from '../../format';

defineProps({
    alerts: { type: Object, required: true },
});
</script>

<template>
    <Head title="Alerts" />

    <AppLayout heading="Alerts" subheading="New listings that matched your saved searches.">
        <ul v-if="alerts.data.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <li v-for="alert in alerts.data" :key="alert.id" class="flex flex-col gap-2">
                <ListingCard :listing="alert.listing" />

                <div class="px-1 text-xs text-slate-500">
                    <p>
                        Matched:
                        <template v-for="(search, index) in alert.saved_searches" :key="search.id">
                            <span v-if="index > 0">; </span>
                            <SavedSearchSummary :search="search" class="text-slate-700" />
                        </template>
                        <span v-if="!alert.saved_searches.length">a search you've since deleted</span>
                    </p>
                    <p class="mt-1">Alerted {{ formatDate(alert.alerted_at) }}</p>
                </div>
            </li>
        </ul>

        <p
            v-else
            class="rounded-xl border border-dashed border-slate-300 p-10 text-center text-slate-500"
        >
            No alerts yet. When a new listing matches one of your saved searches, it will appear here.
        </p>

        <Pagination :meta="alerts.meta" :links="alerts.links" />
    </AppLayout>
</template>
