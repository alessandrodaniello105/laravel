<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    workshops: {
        type: Object,
        required: true,
    },
});

function formatWhen(iso) {
    return new Date(iso).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

function destroy(slug) {
    if (confirm('Delete this workshop? This cannot be undone.')) {
        router.delete(route('admin.workshops.destroy', slug));
    }
}
</script>

<template>
    <Head title="Admin · Workshops" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2
                    class="text-xl font-semibold leading-tight text-gray-800"
                >
                    Workshops (admin)
                </h2>
                <Link
                    :href="route('admin.workshops.create')"
                    class="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 active:bg-gray-900"
                >
                    New workshop
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div
                    class="overflow-hidden bg-white shadow-sm sm:rounded-lg"
                >
                    <table
                        class="min-w-full divide-y divide-gray-200 text-left text-sm"
                    >
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 font-medium text-gray-700">
                                    Name
                                </th>
                                <th class="px-4 py-3 font-medium text-gray-700">
                                    Starts
                                </th>
                                <th class="px-4 py-3 font-medium text-gray-700">
                                    Registered
                                </th>
                                <th class="px-4 py-3 font-medium text-gray-700">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr
                                v-for="w in workshops.data"
                                :key="w.id"
                            >
                                <td class="px-4 py-3">
                                    <Link
                                        :href="route('admin.workshops.show', w.slug)"
                                        class="font-medium text-indigo-600 hover:text-indigo-500"
                                    >
                                        {{ w.name }}
                                    </Link>
                                </td>
                                <td class="px-4 py-3 text-gray-600">
                                    {{ formatWhen(w.starts_at) }}
                                </td>
                                <td class="px-4 py-3 text-gray-600">
                                    {{ w.active_registrations_count }} /
                                    {{ w.capacity }}
                                </td>
                                <td class="space-x-2 px-4 py-3">
                                    <Link
                                        :href="route('admin.workshops.edit', w.slug)"
                                        class="text-indigo-600 hover:text-indigo-500"
                                    >
                                        Edit
                                    </Link>
                                    <button
                                        type="button"
                                        class="text-indigo-600 hover:text-indigo-500"
                                        @click="destroy(w.slug)"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p
                        v-if="!workshops.data.length"
                        class="px-4 py-8 text-center text-gray-600"
                    >
                        No workshops yet.
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
    </AuthenticatedLayout>
</template>
