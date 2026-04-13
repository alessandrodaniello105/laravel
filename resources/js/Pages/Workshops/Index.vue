<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    workshops: {
        type: Object,
        required: true,
    },
    canLogin: {
        type: Boolean,
        default: true,
    },
    canRegister: {
        type: Boolean,
        default: true,
    },
});

function formatWhen(iso) {
    return new Date(iso).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

function formatDuration(minutes) {
    if (minutes < 60) {
        return `${minutes} min`;
    }
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return m ? `${h} h ${m} min` : `${h} h`;
}
</script>

<template>
    <Head title="Workshops" />

    <PublicLayout :can-login="canLogin" :can-register="canRegister">
        <div class="py-10">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <h1 class="text-2xl font-semibold text-gray-900">
                    Workshops
                </h1>
                <p class="mt-2 text-sm text-gray-600">
                    Browse upcoming workshops and reserve your seat.
                </p>

                <div class="mt-8 overflow-hidden bg-white shadow sm:rounded-lg">
                    <ul
                        v-if="workshops.data.length"
                        class="divide-y divide-gray-200"
                    >
                        <li
                            v-for="w in workshops.data"
                            :key="w.id"
                            class="flex flex-col gap-2 px-4 py-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <Link
                                    :href="route('workshops.show', w.slug)"
                                    class="text-lg font-medium text-gray-900 hover:text-gray-700"
                                >
                                    {{ w.name }}
                                </Link>
                                <p class="mt-1 text-sm text-gray-600">
                                    {{ formatWhen(w.starts_at) }} ·
                                    {{ formatDuration(w.duration_minutes) }}
                                </p>
                            </div>
                            <div class="text-sm text-gray-600">
                                {{ w.remaining_spots }} /
                                {{ w.capacity }} spots left
                            </div>
                        </li>
                    </ul>
                    <p v-else class="px-4 py-8 text-center text-sm text-gray-600">
                        No workshops scheduled yet.
                    </p>
                </div>

                <div
                    v-if="workshops.links.length > 3"
                    class="mt-6 flex justify-center gap-2"
                >
                    <Link
                        v-for="link in workshops.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        class="rounded px-3 py-1 text-sm"
                        :class="[
                            link.active
                                ? 'bg-gray-800 text-white'
                                : 'bg-white text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50',
                            !link.url ? 'pointer-events-none opacity-50' : '',
                        ]"
                        preserve-scroll
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
