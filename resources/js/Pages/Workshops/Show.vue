<script setup>
import EchoConnectionBadge from '@/Components/EchoConnectionBadge.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { echo, echoIsConfigured } from '@laravel/echo-vue';
import { computed, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    workshop: {
        type: Object,
        required: true,
    },
    isRegistered: {
        type: Boolean,
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
    registrationOpen: {
        type: Boolean,
        required: true,
    },
});

const page = usePage();

const remainingSpots = ref(Number(props.workshop.remaining_spots));
const activeRegistrationsCount = ref(
    Number(props.workshop.active_registrations_count),
);
const capacity = ref(Number(props.workshop.capacity));
const liveUpdatesEnabled = ref(false);

function syncCountsFromServerWorkshop() {
    const w = props.workshop;
    remainingSpots.value = Number(w.remaining_spots);
    activeRegistrationsCount.value = Number(w.active_registrations_count);
    capacity.value = Number(w.capacity);
}

/** Resync when Inertia sends new numbers (same workshop id). */
const workshopServerFingerprint = computed(
    () =>
        `${Number(props.workshop.id)}:${Number(props.workshop.active_registrations_count)}:${Number(props.workshop.remaining_spots)}:${Number(props.workshop.capacity)}`,
);

watch(workshopServerFingerprint, () => syncCountsFromServerWorkshop(), {
    immediate: true,
});

/** @type {(() => void) | null} */
let tearDownEcho = null;

function bindWorkshopChannel(workshopId) {
    if (!echoIsConfigured()) {
        liveUpdatesEnabled.value = false;

        return () => {};
    }

    const echoClient = echo();
    const channelName = `workshop.${workshopId}`;
    const channel = echoClient.channel(channelName);

    channel.listen('.WorkshopRegistrationUpdated', (payload) => {
        remainingSpots.value = Number(payload.remaining_spots);
        activeRegistrationsCount.value = Number(
            payload.active_registrations_count,
        );
        capacity.value = Number(payload.capacity);
    });

    liveUpdatesEnabled.value = true;

    return () => {
        echoClient.leave(channelName);
    };
}

watch(
    () => props.workshop.id,
    (id) => {
        if (tearDownEcho) {
            tearDownEcho();
        }
        tearDownEcho = bindWorkshopChannel(id);
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

function register() {
    router.post(route('workshops.register', props.workshop.slug));
}

function cancel() {
    if (confirm('Cancel your registration for this workshop?')) {
        router.delete(
            route('workshops.unregister', props.workshop.slug),
        );
    }
}
</script>

<template>
    <Head :title="workshop.name" />

    <PublicLayout :can-login="canLogin" :can-register="canRegister">
        <div class="py-10">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <Link
                    :href="route('workshops.index')"
                    class="text-sm text-gray-600 hover:text-gray-900"
                >
                    ← Back to workshops
                </Link>

                <article class="mt-6 overflow-hidden bg-white shadow sm:rounded-lg">
                    <div class="border-b border-gray-100 px-6 py-6">
                        <div
                            class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-3"
                        >
                            <h1 class="text-2xl font-semibold text-gray-900">
                                {{ workshop.name }}
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
                        <dl
                            class="mt-4 grid grid-cols-1 gap-3 text-sm text-gray-600 sm:grid-cols-2"
                        >
                            <div>
                                <dt class="font-medium text-gray-700">
                                    Starts
                                </dt>
                                <dd>{{ formatWhen(workshop.starts_at) }}</dd>
                            </div>
                            <div>
                                <dt class="font-medium text-gray-700">
                                    Duration
                                </dt>
                                <dd>
                                    {{ formatDuration(workshop.duration_minutes) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="font-medium text-gray-700">
                                    Capacity
                                </dt>
                                <dd>
                                    {{ remainingSpots }} /
                                    {{ capacity }} spots left
                                    <span class="block text-xs text-gray-500">
                                        {{ activeRegistrationsCount }} registered
                                    </span>
                                    <span
                                        v-if="liveUpdatesEnabled"
                                        class="text-xs font-normal text-green-700"
                                    >
                                        Live updates on
                                    </span>
                                </dd>
                            </div>
                        </dl>
                    </div>
                    <div
                        v-if="workshop.description"
                        class="prose max-w-none px-6 py-6 text-gray-700"
                    >
                        <p class="whitespace-pre-wrap">
                            {{ workshop.description }}
                        </p>
                    </div>
                    <div class="border-t border-gray-100 px-6 py-6">
                        <InputError
                            class="mb-3"
                            :message="page.props.errors.workshop"
                        />

                        <template v-if="$page.props.auth.user">
                            <template v-if="registrationOpen">
                                <PrimaryButton
                                    v-if="!isRegistered"
                                    type="button"
                                    @click="register"
                                >
                                    Register
                                </PrimaryButton>
                                <SecondaryButton
                                    v-else
                                    type="button"
                                    @click="cancel"
                                >
                                    Cancel registration
                                </SecondaryButton>
                            </template>
                            <template v-else>
                                <p
                                    v-if="isRegistered"
                                    class="text-sm text-gray-600"
                                >
                                    Registration is closed. You were signed up
                                    for this workshop.
                                </p>
                                <p v-else class="text-sm text-gray-600">
                                    Registration is closed; this workshop is
                                    no longer upcoming.
                                </p>
                            </template>
                        </template>
                        <template v-else>
                            <p
                                v-if="registrationOpen"
                                class="text-sm text-gray-600"
                            >
                                <Link
                                    :href="route('login')"
                                    class="font-medium text-indigo-600 hover:text-indigo-500"
                                >
                                    Log in
                                </Link>
                                to register for this workshop.
                            </p>
                            <p v-else class="text-sm text-gray-600">
                                Registration is closed; this workshop is no
                                longer upcoming.
                            </p>
                        </template>
                    </div>
                </article>
            </div>
        </div>
    </PublicLayout>
</template>
