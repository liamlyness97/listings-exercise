<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '../../components/AppLayout.vue';

defineProps({
    alerts: { type: Object, required: true },
});
</script>

<template>
    <Head title="Alerts" />

    <AppLayout heading="Alerts" subheading="New listings that matched your saved searches.">
        <ul v-if="alerts.data.length" class="space-y-3">
            <li v-for="alert in alerts.data" :key="alert.id">Alert #{{ alert.id }}</li>
        </ul>

        <p
            v-else
            class="rounded-xl border border-dashed border-slate-300 p-10 text-center text-slate-500"
        >
            No alerts yet. When a new listing matches one of your saved searches, it will appear here.
        </p>
    </AppLayout>
</template>
