<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    appName: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <Head title="Home" />

    <div class="flex min-h-screen flex-col items-center justify-center bg-zinc-50 p-8 text-zinc-900">
        <div class="max-w-lg text-center">
            <h1 class="text-2xl font-semibold tracking-tight">
                {{ appName }}
            </h1>
            <p class="mt-3 text-zinc-600">
                Inertia.js with Vue 3 is set up. Add pages under
                <code class="rounded bg-zinc-200 px-1.5 py-0.5 text-sm">resources/js/Pages</code>
                and return <code class="rounded bg-zinc-200 px-1.5 py-0.5 text-sm">Inertia::render()</code> from your routes or controllers.
            </p>
        </div>
    </div>
</template>
