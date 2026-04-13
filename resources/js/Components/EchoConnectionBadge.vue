<script setup>
import { useConnectionStatus } from '@laravel/echo-vue';
import { computed } from 'vue';

const status = useConnectionStatus();

const label = computed(() => {
    switch (status.value) {
        case 'connected':
            return 'WebSocket: connected';
        case 'connecting':
            return 'WebSocket: connecting…';
        case 'reconnecting':
            return 'WebSocket: reconnecting…';
        case 'failed':
            return 'WebSocket: failed';
        default:
            return 'WebSocket: disconnected';
    }
});

const chipClass = computed(() => {
    const base =
        'inline-flex max-w-full items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset';

    switch (status.value) {
        case 'connected':
            return `${base} bg-green-50 text-green-800 ring-green-600/20`;
        case 'connecting':
            return `${base} bg-amber-50 text-amber-900 ring-amber-600/20`;
        case 'reconnecting':
            return `${base} bg-orange-50 text-orange-900 ring-orange-600/20`;
        case 'failed':
            return `${base} bg-red-50 text-red-800 ring-red-600/20`;
        default:
            return `${base} bg-gray-50 text-gray-600 ring-gray-500/10`;
    }
});
</script>

<template>
    <span :class="chipClass" :title="label">
        {{ label }}
    </span>
</template>
