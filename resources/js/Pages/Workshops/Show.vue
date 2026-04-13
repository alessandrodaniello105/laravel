<script setup>
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

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
});

const page = usePage();

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
                        <h1 class="text-2xl font-semibold text-gray-900">
                            {{ workshop.name }}
                        </h1>
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
                                    {{ workshop.remaining_spots }} /
                                    {{ workshop.capacity }} spots left
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
                            <p class="text-sm text-gray-600">
                                <Link
                                    :href="route('login')"
                                    class="font-medium text-indigo-600 hover:text-indigo-500"
                                >
                                    Log in
                                </Link>
                                to register for this workshop.
                            </p>
                        </template>
                    </div>
                </article>
            </div>
        </div>
    </PublicLayout>
</template>
