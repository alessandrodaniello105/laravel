<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    workshop: {
        type: Object,
        required: true,
    },
});

function formatWhen(iso) {
    if (!iso) {
        return '—';
    }
    return new Date(iso).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}
</script>

<template>
    <Head :title="`Admin · ${workshop.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2
                    class="text-xl font-semibold leading-tight text-gray-800"
                >
                    {{ workshop.name }}
                </h2>
                <Link
                    :href="route('admin.workshops.edit', workshop.slug)"
                    class="text-sm font-medium text-indigo-600 hover:text-indigo-500"
                >
                    Edit
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
                <Link
                    :href="route('admin.workshops.index')"
                    class="text-sm text-gray-600 hover:text-gray-900"
                >
                    ← Back to list
                </Link>

                <dl
                    class="mt-6 grid grid-cols-1 gap-4 rounded-lg bg-white p-6 shadow sm:grid-cols-2"
                >
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Slug</dt>
                        <dd class="mt-1 text-gray-900">{{ workshop.slug }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Starts</dt>
                        <dd class="mt-1 text-gray-900">
                            {{ formatWhen(workshop.starts_at) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">
                            Duration (min)
                        </dt>
                        <dd class="mt-1 text-gray-900">
                            {{ workshop.duration_minutes }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">
                            Active registrations
                        </dt>
                        <dd class="mt-1 text-gray-900">
                            {{ workshop.active_registrations_count }} /
                            {{ workshop.capacity }}
                        </dd>
                    </div>
                </dl>

                <div
                    v-if="workshop.description"
                    class="mt-6 rounded-lg bg-white p-6 shadow"
                >
                    <h3 class="text-sm font-medium text-gray-700">
                        Description
                    </h3>
                    <p class="mt-2 whitespace-pre-wrap text-gray-600">
                        {{ workshop.description }}
                    </p>
                </div>

                <div class="mt-6 overflow-hidden rounded-lg bg-white shadow">
                    <div class="border-b border-gray-200 px-6 py-4">
                        <h3 class="text-lg font-medium text-gray-900">
                            Registrations
                        </h3>
                    </div>
                    <table
                        class="min-w-full divide-y divide-gray-200 text-left text-sm"
                    >
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 font-medium text-gray-700">
                                    User
                                </th>
                                <th class="px-4 py-2 font-medium text-gray-700">
                                    Email
                                </th>
                                <th class="px-4 py-2 font-medium text-gray-700">
                                    Status
                                </th>
                                <th class="px-4 py-2 font-medium text-gray-700">
                                    Registered at
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr
                                v-for="r in workshop.registrations"
                                :key="r.id"
                            >
                                <td class="px-4 py-2">
                                    {{ r.user.name }}
                                </td>
                                <td class="px-4 py-2 text-gray-600">
                                    {{ r.user.email }}
                                </td>
                                <td class="px-4 py-2">
                                    <span
                                        v-if="r.cancelled_at"
                                        class="text-amber-700"
                                    >
                                        Cancelled
                                    </span>
                                    <span v-else class="text-green-700">
                                        Active
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-gray-600">
                                    {{ formatWhen(r.created_at) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p
                        v-if="!workshop.registrations.length"
                        class="px-4 py-6 text-center text-gray-600"
                    >
                        No registrations yet.
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
