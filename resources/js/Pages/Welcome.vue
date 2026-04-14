<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
    laravelVersion: {
        type: String,
        required: true,
    },
    phpVersion: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <Head title="Welcome" />

    <div
        class="flex min-h-screen flex-col bg-white text-zinc-900 bg-white dark:text-zinc-900"
    >
        <header
            v-if="canLogin"
            class="flex justify-end gap-1 border-b border-zinc-100 bg-white px-4 py-3 dark:border-zinc-800"
        >
            <Link
                :href="route('workshops.index')"
                class="rounded-md px-3 py-2 text-sm text-zinc-900 transition hover:bg-zinc-50 hover:text-zinc-900 "
            >
                Workshops
            </Link>
            <Link
                v-if="$page.props.auth.user"
                :href="route('dashboard')"
                class="rounded-md px-3 py-2 text-sm text-zinc-700 transition hover:bg-zinc-50 hover:text-zinc-900 "
            >
                Dashboard
            </Link>
            <template v-else>
                <Link
                    :href="route('login')"
                    class="rounded-md px-3 py-2 text-sm text-zinc-700 transition hover:bg-zinc-50 hover:text-zinc-900 "
                >
                    Log in
                </Link>
                <Link
                    v-if="canRegister"
                    :href="route('register')"
                    class="rounded-md px-3 py-2 text-sm text-zinc-700 transition hover:bg-zinc-50 hover:text-zinc-900 "
                >
                    Register
                </Link>
            </template>
        </header>

        <main
            class="flex flex-1 flex-col items-center justify-center px-6 py-16 text-center"
        >
            <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">
                Internal Academy
            </h1>
            <p class="mt-4 max-w-md text-zinc-600 dark:text-zinc-700">
                Browse workshops, sign up when you are signed in, and manage
                sessions. Admins can create workshops and view live stats.
            </p>
            <Link
                :href="route('workshops.index')"
                class="mt-8 rounded-lg bg-zinc-900 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200"
            >
                View workshops
            </Link>
        </main>

        <footer
            class="border-t border-zinc-100 bg-white py-4 text-center text-xs text-zinc-500 dark:border-zinc-300  dark:text-zinc-700"
        >
            Laravel {{ laravelVersion }} · PHP {{ phpVersion }}
        </footer>
    </div>
</template>
