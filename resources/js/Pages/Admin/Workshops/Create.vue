<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    slug: '',
    description: '',
    starts_at: '',
    duration_minutes: 60,
    capacity: 10,
});

function submit() {
    form.post(route('admin.workshops.store'));
}
</script>

<template>
    <Head title="New workshop" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl font-semibold leading-tight text-gray-800"
            >
                New workshop
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <Link
                    :href="route('admin.workshops.index')"
                    class="text-sm text-gray-600 hover:text-gray-900"
                >
                    ← Back to list
                </Link>

                <form
                    class="mt-6 space-y-6 bg-white p-6 shadow sm:rounded-lg"
                    @submit.prevent="submit"
                >
                    <div>
                        <InputLabel for="name" value="Name" />
                        <TextInput
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="mt-1 block w-full"
                            required
                            autofocus
                        />
                        <InputError
                            class="mt-2"
                            :message="form.errors.name"
                        />
                    </div>

                    <div>
                        <InputLabel
                            for="slug"
                            value="Slug (optional, URL-friendly)"
                        />
                        <TextInput
                            id="slug"
                            v-model="form.slug"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="leave blank to auto-generate"
                        />
                        <InputError
                            class="mt-2"
                            :message="form.errors.slug"
                        />
                    </div>

                    <div>
                        <InputLabel for="description" value="Description" />
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="4"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <InputError
                            class="mt-2"
                            :message="form.errors.description"
                        />
                    </div>

                    <div>
                        <InputLabel for="starts_at" value="Starts at" />
                        <TextInput
                            id="starts_at"
                            v-model="form.starts_at"
                            type="datetime-local"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError
                            class="mt-2"
                            :message="form.errors.starts_at"
                        />
                    </div>

                    <div>
                        <InputLabel
                            for="duration_minutes"
                            value="Duration (minutes)"
                        />
                        <TextInput
                            id="duration_minutes"
                            v-model="form.duration_minutes"
                            type="number"
                            min="1"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError
                            class="mt-2"
                            :message="form.errors.duration_minutes"
                        />
                    </div>

                    <div>
                        <InputLabel for="capacity" value="Capacity" />
                        <TextInput
                            id="capacity"
                            v-model="form.capacity"
                            type="number"
                            min="1"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError
                            class="mt-2"
                            :message="form.errors.capacity"
                        />
                    </div>

                    <div class="flex items-center gap-4">
                        <PrimaryButton :disabled="form.processing">
                            Create
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
