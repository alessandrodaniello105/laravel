<script setup>
import EchoConnectionBadge from '@/Components/EchoConnectionBadge.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { echo, echoIsConfigured } from '@laravel/echo-vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    workshops: {
        type: Object,
        required: true,
    },
    workshopStatistics: {
        type: Object,
        required: true,
    },
});

function cloneStatisticsFromProps() {
    const s = props.workshopStatistics;
    const popular = s.most_popular_workshop;
    return {
        total_active_registrations: Number(s.total_active_registrations),
        most_popular_workshop: popular
            ? {
                  id: Number(popular.id),
                  name: popular.name,
                  slug: popular.slug,
                  active_registrations_count: Number(
                      popular.active_registrations_count,
                  ),
              }
            : null,
    };
}

const statistics = ref(cloneStatisticsFromProps());

const statisticsServerFingerprint = computed(() => {
    const s = props.workshopStatistics;
    const p = s.most_popular_workshop;
    return `${Number(s.total_active_registrations)}:${p ? `${p.id}:${p.active_registrations_count}` : 'null'}`;
});

watch(
    statisticsServerFingerprint,
    () => {
        statistics.value = cloneStatisticsFromProps();
    },
    { immediate: true },
);

const statsLiveEnabled = ref(false);

/** @type {(() => void) | null} */
let tearDownStatsEcho = null;

function bindAdminStatisticsChannel() {
    if (!echoIsConfigured()) {
        statsLiveEnabled.value = false;

        return () => {};
    }

    const echoClient = echo();
    const channel = echoClient.private('admin.workshop-statistics');

    channel.listen('.AdminWorkshopStatisticsUpdated', (payload) => {
        statistics.value = {
            total_active_registrations: Number(
                payload.total_active_registrations,
            ),
            most_popular_workshop: payload.most_popular_workshop
                ? {
                      id: Number(payload.most_popular_workshop.id),
                      name: payload.most_popular_workshop.name,
                      slug: payload.most_popular_workshop.slug,
                      active_registrations_count: Number(
                          payload.most_popular_workshop
                              .active_registrations_count,
                      ),
                  }
                : null,
        };
    });

    statsLiveEnabled.value = true;

    return () => {
        echoClient.leave('private-admin.workshop-statistics');
    };
}

tearDownStatsEcho = bindAdminStatisticsChannel();

onUnmounted(() => {
    if (tearDownStatsEcho) {
        tearDownStatsEcho();
    }
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
            <div
                class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-3"
            >
                <h2
                    class="text-xl font-semibold leading-tight text-gray-800"
                >
                    Workshops (admin)
                </h2>
                <div class="flex flex-wrap items-center gap-2">
                    <EchoConnectionBadge
                        v-if="echoIsConfigured()"
                        class="shrink-0"
                    />
                    <Link
                        :href="route('admin.workshops.create')"
                        class="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 active:bg-gray-900"
                    >
                        New workshop
                    </Link>
                </div>
            </div>
        </template>

        <div class="space-y-8 py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div
                    class="overflow-hidden bg-white shadow-sm sm:rounded-lg"
                >
                    <div class="border-b border-gray-100 p-6">
                        <h3 class="text-lg font-medium text-gray-900">
                            Statistics
                        </h3>
                        <p
                            v-if="statsLiveEnabled"
                            class="mt-1 text-xs text-green-700"
                        >
                            Updates live when registrations change.
                        </p>
                        <p
                            v-else-if="echoIsConfigured()"
                            class="mt-1 text-xs text-amber-700"
                        >
                            Live stats unavailable (check broadcasting auth).
                        </p>
                    </div>
                    <div
                        class="grid gap-6 px-6 py-6 sm:grid-cols-2"
                    >
                        <div
                            class="rounded-lg bg-indigo-50/80 p-5 ring-1 ring-indigo-100"
                        >
                            <p
                                class="text-xs font-semibold uppercase tracking-wide text-indigo-800"
                            >
                                Total active registrations
                            </p>
                            <p
                                class="mt-2 text-3xl font-semibold tabular-nums text-indigo-950"
                            >
                                {{ statistics.total_active_registrations }}
                            </p>
                            <p class="mt-1 text-xs text-indigo-700">
                                Across all workshops (not cancelled)
                            </p>
                        </div>
                        <div
                            class="rounded-lg bg-emerald-50/80 p-5 ring-1 ring-emerald-100"
                        >
                            <p
                                class="text-xs font-semibold uppercase tracking-wide text-emerald-900"
                            >
                                Most popular workshop
                            </p>
                            <template v-if="statistics.most_popular_workshop">
                                <p class="mt-2 text-lg font-semibold text-emerald-950">
                                    <Link
                                        :href="
                                            route(
                                                'admin.workshops.show',
                                                statistics.most_popular_workshop
                                                    .slug,
                                            )
                                        "
                                        class="text-emerald-800 underline decoration-emerald-300 decoration-2 underline-offset-2 hover:text-emerald-700"
                                    >
                                        {{ statistics.most_popular_workshop.name }}
                                    </Link>
                                </p>
                                <p class="mt-2 text-2xl font-semibold tabular-nums text-emerald-950">
                                    {{
                                        statistics.most_popular_workshop
                                            .active_registrations_count
                                    }}
                                    <span class="text-base font-normal text-emerald-800">
                                        registrations
                                    </span>
                                </p>
                                <p class="mt-1 text-xs text-emerald-800">
                                    Tie-break: lowest workshop id
                                </p>
                            </template>
                            <p
                                v-else
                                class="mt-3 text-sm text-emerald-800"
                            >
                                No workshops yet.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

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
