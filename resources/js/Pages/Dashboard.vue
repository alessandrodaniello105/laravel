<script setup>
import EchoConnectionBadge from '@/Components/EchoConnectionBadge.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { echo, echoIsConfigured } from '@laravel/echo-vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    registeredWorkshops: {
        type: Array,
        required: true,
    },
    waitlistedWorkshops: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();

function cloneWorkshopsFromProps() {
    return props.registeredWorkshops.map((w) => ({
        ...w,
        id: Number(w.id),
        remaining_spots: Number(w.remaining_spots),
        active_registrations_count: Number(w.active_registrations_count),
        capacity: Number(w.capacity),
        duration_minutes: Number(w.duration_minutes),
    }));
}

function cloneWaitlistedFromProps() {
    return props.waitlistedWorkshops.map((w) => ({
        ...w,
        id: Number(w.id),
        remaining_spots: Number(w.remaining_spots),
        active_registrations_count: Number(w.active_registrations_count),
        capacity: Number(w.capacity),
        duration_minutes: Number(w.duration_minutes),
        waitlist_position: Number(w.waitlist_position),
    }));
}

const workshops = ref(cloneWorkshopsFromProps());
const waitlisted = ref(cloneWaitlistedFromProps());

const liveUpdatesEnabled = ref(false);

const registeredWorkshopsServerFingerprint = computed(() =>
    [...props.registeredWorkshops]
        .sort((a, b) => Number(a.id) - Number(b.id))
        .map(
            (w) =>
                `${Number(w.id)}:${Number(w.active_registrations_count)}:${Number(w.remaining_spots)}:${Number(w.capacity)}`,
        )
        .join('|'),
);

const waitlistedWorkshopsServerFingerprint = computed(() =>
    [...props.waitlistedWorkshops]
        .sort((a, b) => Number(a.id) - Number(b.id))
        .map(
            (w) =>
                `${Number(w.id)}:${Number(w.active_registrations_count)}:${Number(w.remaining_spots)}:${Number(w.capacity)}:${Number(w.waitlist_position)}`,
        )
        .join('|'),
);

watch(
    registeredWorkshopsServerFingerprint,
    () => {
        workshops.value = cloneWorkshopsFromProps();
    },
    { immediate: true },
);

watch(
    waitlistedWorkshopsServerFingerprint,
    () => {
        waitlisted.value = cloneWaitlistedFromProps();
    },
    { immediate: true },
);

let lastVisibilityReloadMs = 0;

function reloadDashboardWorkshopsFromServer() {
    if (document.visibilityState !== 'visible') {
        return;
    }
    const now = Date.now();
    if (now - lastVisibilityReloadMs < 1500) {
        return;
    }
    lastVisibilityReloadMs = now;
    router.reload({
        only: ['registeredWorkshops', 'waitlistedWorkshops'],
        preserveScroll: true,
    });
}

const subscribedWorkshopIdsKey = computed(() => {
    const ids = new Set([
        ...workshops.value.map((w) => Number(w.id)),
        ...waitlisted.value.map((w) => Number(w.id)),
    ]);
    return [...ids].sort((a, b) => a - b).join(',');
});

/** @type {(() => void) | null} */
let tearDownEcho = null;

function bindWorkshopChannels(ids) {
    if (!echoIsConfigured() || ids.length === 0) {
        liveUpdatesEnabled.value = false;

        return () => {};
    }

    const echoClient = echo();
    const cleanups = [];

    for (const id of ids) {
        const channelName = `workshop.${id}`;
        const channel = echoClient.channel(channelName);

        channel.listen('.WorkshopRegistrationUpdated', (payload) => {
            const wid = Number(payload.workshop_id);
            workshops.value = workshops.value.map((w) => {
                if (Number(w.id) !== wid) {
                    return w;
                }

                return {
                    ...w,
                    remaining_spots: Number(payload.remaining_spots),
                    active_registrations_count: Number(
                        payload.active_registrations_count,
                    ),
                    capacity: Number(payload.capacity),
                };
            });
            waitlisted.value = waitlisted.value.map((w) => {
                if (Number(w.id) !== wid) {
                    return w;
                }

                return {
                    ...w,
                    remaining_spots: Number(payload.remaining_spots),
                    active_registrations_count: Number(
                        payload.active_registrations_count,
                    ),
                    capacity: Number(payload.capacity),
                };
            });

            const promotedId = payload.promoted_user_id;
            const authId = page.props.auth?.user?.id;
            if (
                promotedId != null &&
                authId != null &&
                Number(promotedId) === Number(authId)
            ) {
                router.reload({
                    only: ['registeredWorkshops', 'waitlistedWorkshops'],
                    preserveScroll: true,
                });
            }
        });
        cleanups.push(() => {
            echoClient.leave(channelName);
        });
    }

    liveUpdatesEnabled.value = true;

    return () => {
        for (const cleanup of cleanups) {
            cleanup();
        }
    };
}

watch(
    subscribedWorkshopIdsKey,
    () => {
        if (tearDownEcho) {
            tearDownEcho();
        }
        const ids = [...new Set([...workshops.value, ...waitlisted.value].map((w) => w.id))];
        tearDownEcho = bindWorkshopChannels(ids);
    },
    { immediate: true },
);

onMounted(() => {
    document.addEventListener(
        'visibilitychange',
        reloadDashboardWorkshopsFromServer,
    );
});

onUnmounted(() => {
    document.removeEventListener(
        'visibilitychange',
        reloadDashboardWorkshopsFromServer,
    );
    if (tearDownEcho) {
        tearDownEcho();
    }
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
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div
                class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-3"
            >
                <h2
                    class="text-xl font-semibold leading-tight text-gray-800"
                >
                    Dashboard
                </h2>
                <EchoConnectionBadge
                    v-if="echoIsConfigured()"
                    class="shrink-0"
                />
                <span
                    v-else
                    class="inline-flex shrink-0 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/10"
                    title="Set VITE_REVERB_APP_KEY and run Vite so Echo can connect to Reverb."
                >
                    Live: not configured
                </span>
            </div>
        </template>

        <div class="space-y-10 py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div
                    class="overflow-hidden bg-white shadow-sm sm:rounded-lg"
                >
                    <div class="border-b border-gray-100 p-6">
                        <h3 class="text-lg font-medium text-gray-900">
                            Your upcoming workshops
                        </h3>
                        <p
                            v-if="
                                liveUpdatesEnabled &&
                                (workshops.length || waitlisted.length)
                            "
                            class="mt-1 text-xs text-green-700"
                        >
                            Spot counts update live while this page is open.
                        </p>
                    </div>

                    <ul
                        v-if="workshops.length"
                        class="divide-y divide-gray-200"
                    >
                        <li
                            v-for="w in workshops"
                            :key="w.id"
                            class="flex flex-col gap-2 px-6 py-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <Link
                                    :href="route('workshops.show', w.slug)"
                                    class="text-lg font-medium text-indigo-700 hover:text-indigo-600"
                                >
                                    {{ w.name }}
                                </Link>
                                <p class="mt-1 text-sm text-gray-600">
                                    {{ formatWhen(w.starts_at) }} ·
                                    {{ formatDuration(w.duration_minutes) }}
                                </p>
                            </div>
                            <div class="text-right text-sm text-gray-600">
                                <p>
                                    {{ w.remaining_spots }} /
                                    {{ w.capacity }}
                                    spots left
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ w.active_registrations_count }} registered
                                </p>
                            </div>
                        </li>
                    </ul>

                    <div v-else class="p-6 text-gray-600">
                        <p>You have no upcoming workshop registrations.</p>
                        <p class="mt-2">
                            <Link
                                :href="route('workshops.index')"
                                class="font-medium text-indigo-600 hover:text-indigo-500"
                            >
                                Browse workshops
                            </Link>
                            to sign up.
                        </p>
                    </div>
                </div>
            </div>

            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div
                    class="overflow-hidden bg-white shadow-sm sm:rounded-lg"
                >
                    <div class="border-b border-gray-100 p-6">
                        <h3 class="text-lg font-medium text-gray-900">
                            Waiting list
                        </h3>
                        <p class="mt-1 text-sm text-gray-600">
                            Full workshops you are queued for. If a spot opens,
                            you are registered automatically in order.
                        </p>
                    </div>

                    <ul
                        v-if="waitlisted.length"
                        class="divide-y divide-gray-200"
                    >
                        <li
                            v-for="w in waitlisted"
                            :key="`w-${w.id}`"
                            class="flex flex-col gap-2 px-6 py-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <Link
                                    :href="route('workshops.show', w.slug)"
                                    class="text-lg font-medium text-sky-800 hover:text-sky-700"
                                >
                                    {{ w.name }}
                                </Link>
                                <p class="mt-1 text-sm text-gray-600">
                                    {{ formatWhen(w.starts_at) }} ·
                                    {{ formatDuration(w.duration_minutes) }}
                                </p>
                                <p class="mt-1 text-sm font-medium text-sky-900">
                                    Your position:
                                    <strong>#{{ w.waitlist_position }}</strong>
                                </p>
                            </div>
                            <div class="text-right text-sm text-gray-600">
                                <p>
                                    {{ w.remaining_spots }} /
                                    {{ w.capacity }}
                                    spots left
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ w.active_registrations_count }} registered
                                </p>
                            </div>
                        </li>
                    </ul>

                    <div v-else class="p-6 text-gray-600">
                        <p>You are not on any waiting lists.</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
