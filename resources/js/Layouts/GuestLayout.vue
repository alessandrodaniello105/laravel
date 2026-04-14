<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import NavLink from '@/Components/NavLink.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    /**
     * auth: centered card (login, register, …).
     * public: top nav + full-width main for guests browsing the app.
     */
    variant: {
        type: String,
        default: 'auth',
        validator: (value) => ['auth', 'public'].includes(value),
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
</script>

<template>
    <div
        v-if="props.variant === 'public'"
        class="min-h-screen bg-gray-100"
    >
        <nav class="border-b border-gray-100 bg-white">
            <div
                class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8"
            >
                <div class="flex min-w-0 flex-1 items-center gap-8">
                    <Link
                        :href="route('home')"
                        class="flex shrink-0 items-center"
                    >
                        <ApplicationLogo
                            class="block h-9 w-auto fill-current text-gray-800"
                        />
                    </Link>
                    <NavLink
                        :href="route('workshops.index')"
                        :active="
                            route().current('workshops.index') ||
                            route().current('workshops.show')
                        "
                    >
                        Workshops
                    </NavLink>
                </div>
                <div
                    class="flex shrink-0 items-center justify-end gap-4"
                >
                    <template v-if="$page.props.auth.user">
                        <NavLink
                            :href="route('dashboard')"
                            :active="route().current('dashboard')"
                        >
                            Dashboard
                        </NavLink>
                        <Link
                            v-if="$page.props.auth.user.role === 'admin'"
                            :href="route('admin.workshops.index')"
                            class="text-sm text-gray-600 hover:text-gray-900"
                        >
                            Admin Dashboard
                        </Link>
                    </template>
                    <template v-else-if="canLogin">
                        <Link
                            :href="route('login')"
                            class="text-sm text-gray-600 hover:text-gray-900"
                        >
                            Log in
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="text-sm text-gray-600 hover:text-gray-900"
                        >
                            Register
                        </Link>
                    </template>
                </div>
            </div>
        </nav>

        <main>
            <slot />
        </main>
    </div>

    <div
        v-else
        class="flex min-h-screen flex-col items-center bg-gray-100 pt-6 sm:justify-center sm:pt-0"
    >
        <div>
            <Link href="/">
                <ApplicationLogo
                    class="h-20 w-20 fill-current text-gray-500"
                />
            </Link>
        </div>

        <div
            class="mt-6 w-full overflow-hidden bg-white px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg"
        >
            <slot />
        </div>
    </div>
</template>
