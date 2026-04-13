<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import NavLink from '@/Components/NavLink.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
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
    <div class="min-h-screen bg-gray-100">
        <nav class="border-b border-gray-100 bg-white">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-8">
                    <Link :href="route('home')" class="flex items-center">
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
                <div class="flex items-center gap-4">
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
                            Admin workshops
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
</template>
