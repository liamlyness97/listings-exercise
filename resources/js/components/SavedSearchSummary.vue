<script setup>
import { computed } from 'vue';
import { formatPrice } from '../format';

const props = defineProps({
    search: { type: Object, required: true },
});

// Only the criteria that are set; a blank criterion means "any" and isn't shown.
const parts = computed(() =>
    [
        props.search.property_type_label,
        props.search.region,
        props.search.max_price !== null && `up to ${formatPrice(props.search.max_price)}`,
        props.search.min_bedrooms !== null && `${props.search.min_bedrooms}+ beds`,
    ].filter(Boolean),
);
</script>

<template>
    <span>{{ parts.join(' · ') }}</span>
</template>
