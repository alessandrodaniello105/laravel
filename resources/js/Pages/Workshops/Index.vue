<script setup>
import EchoConnectionBadge from '@/Components/EchoConnectionBadge.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { echo, echoIsConfigured } from '@laravel/echo-vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    workshops: {
        type: Object,
        required: true,
    },
    pastWorkshops: {
        type: Array,
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

const page = usePage();

const layoutComponent = computed(() =>
    page.props.auth?.user ? AuthenticatedLayout : GuestLayout,
);

const layoutProps = computed(() =>
    page.props.auth?.user
        ? {}
        : {
              variant: 'public',
              canLogin: props.canLogin,
              canRegister: props.canRegister,
          },
);

function cloneUpcomingRowsFromProps() {
    return props.workshops.data.map((w) => ({
        ...w,
        id: Number(w.id),
        duration_minutes: Number(w.duration_minutes),
        capacity: Number(w.capacity),
        active_registrations_count: Number(w.active_registrations_count),
        remaining_spots: Number(w.remaining_spots),
    }));
}

const upcomingWorkshops = ref(cloneUpcomingRowsFromProps());

const liveUpdatesEnabled = ref(false);

const upcomingServerFingerprint = computed(() =>
    [...props.workshops.data]
        .sort((a, b) => Number(a.id) - Number(b.id))
        .map(
            (w) =>
                `${Number(w.id)}:${Number(w.active_registrations_count)}:${Number(w.remaining_spots)}:${Number(w.capacity)}`,
        )
        .join('|'),
);

watch(
    upcomingServerFingerprint,
    () => {
        upcomingWorkshops.value = cloneUpcomingRowsFromProps();
    },
    { immediate: true },
);

const upcomingIdsKey = computed(() =>
    upcomingWorkshops.value
        .map((w) => Number(w.id))
        .slice()
        .sort((a, b) => a - b)
        .join(','),
);

/** @type {(() => void) | null} */
let tearDownEcho = null;

function bindUpcomingChannels(ids) {
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
            upcomingWorkshops.value = upcomingWorkshops.value.map((w) => {
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
    upcomingIdsKey,
    () => {
        if (tearDownEcho) {
            tearDownEcho();
        }
        tearDownEcho = bindUpcomingChannels(
            upcomingWorkshops.value.map((w) => w.id),
        );
    },
    { immediate: true },
);

onUnmounted(() => {
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
    <Head title="Workshops" />

    <component :is="layoutComponent" v-bind="layoutProps">
        <div class="py-10">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div
                    class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-3"
                >
                    <h1 class="text-2xl font-semibold text-gray-900">
                        Workshops
                    </h1>
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
                <p class="mt-2 text-sm text-gray-600">
                    Upcoming sessions you can register for, and past sessions
                    for reference.
                </p>
                <p
                    v-if="liveUpdatesEnabled && upcomingWorkshops.length"
                    class="mt-1 text-xs text-green-700"
                >
                    Spot counts update live while this page is open.
                </p>

                <h2 class="mt-10 text-lg font-medium text-gray-900">
                    Upcoming workshops
                </h2>
                <div class="mt-3 overflow-hidden bg-white shadow sm:rounded-lg">
                    <ul
                        v-if="upcomingWorkshops.length"
                        class="divide-y divide-gray-200"
                    >
                        <li
                            v-for="w in upcomingWorkshops"
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
                    <p v-else class="px-4 py-8 text-center text-sm text-gray-600">
                        No upcoming workshops.
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

                <h2 class="mt-10 text-lg font-medium text-gray-900">
                    Past workshops
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    Start date and time are before now (registration is
                    closed).
                </p>
                <div class="mt-3 overflow-hidden bg-white shadow sm:rounded-lg">
                    <ul
                        v-if="pastWorkshops.length"
                        class="divide-y divide-gray-200"
                    >
                        <li
                            v-for="w in pastWorkshops"
                            :key="w.id"
                            class="flex flex-col gap-2 px-4 py-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <Link
                                    :href="route('workshops.show', w.slug)"
                                    class="text-lg font-medium text-gray-700 hover:text-gray-900"
                                >
                                    {{ w.name }}
                                </Link>
                                <p class="mt-1 text-sm text-gray-600">
                                    {{ formatWhen(w.starts_at) }} ·
                                    {{ formatDuration(w.duration_minutes) }}
                                </p>
                            </div>
                            <div class="text-sm text-gray-500">Ended</div>
                        </li>
                    </ul>
                    <p v-else class="px-4 py-8 text-center text-sm text-gray-600">
                        No past workshops yet.
                    </p>
                </div>
            </div>
        </div>
    </component>
</template>
