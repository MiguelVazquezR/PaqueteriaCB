<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { PrimeIcons } from '@primevue/core/api';

// --- Props ---
const props = defineProps({
    permissions: Object,
    errors: Object,
});

// --- Refs and State ---
const home = ref({ icon: 'pi pi-home', url: route('dashboard') });
const items = ref([
    { label: 'Configuraciones' },
    { label: 'Roles y permisos', url: route('settings.roles-permissions.index') },
    { label: 'Crear rol' }
]);

const form = useForm({
    name: '',
    permissions: [], // Este array guardará los nombres de los permisos seleccionados
});

// --- Methods ---
const submit = () => {
    form.post(route('settings.roles-permissions.store'));
};

</script>

<template>
    <Head title="Crear Rol" />

    <AppLayout>
        <Breadcrumb :home="home" :model="items" class="!bg-transparent" />
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <form @submit.prevent="submit">
                <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg px-6 py-4">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Crear rol</h1>

                    <!-- === INFORMACIÓN DEL ROL === -->
                    <div class="mb-6">
                        <InputLabel for="name" value="Nombre del rol*" />
                        <InputText id="name" v-model="form.name" class="w-full md:w-1/2" :invalid="!!form.errors.name" />
                        <small v-if="form.errors.name" class="text-red-500 mt-1">{{ form.errors.name }}</small>
                    </div>
                    <Divider />

                    <!-- === PERMISOS === -->
                    <div class="flex items-center space-x-3 mb-4">
                        <i class="pi pi-lock text-xl text-gray-600 dark:text-gray-400"></i>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Asignar permisos</h2>
                    </div>

                    <div class="space-y-6">
                        <div v-for="(permissionGroup, groupName) in permissions" :key="groupName">
                            <h3 class="font-semibold text-gray-700 dark:text-gray-300 capitalize mb-2 flex items-center gap-2 border-b pb-2">
                                <i class="pi pi-folder"></i>
                                <span>{{ groupName }}</span>
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-2 mt-4">
                                <div v-for="permission in permissionGroup" :key="permission.id" class="flex items-center">
                                    <Checkbox v-model="form.permissions" :inputId="`perm-${permission.id}`" :value="permission.name" />
                                    <label :for="`perm-${permission.id}`" class="ml-2 text-sm text-gray-600 dark:text-gray-400 capitalize">{{ permission.name.replace(/_/g, ' ') }}</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- === ACCIONES === -->
                    <div class="flex justify-end gap-3 mt-8">
                        <Link :href="route('settings.roles-permissions.index')">
                            <Button label="Cancelar" severity="secondary" outlined />
                        </Link>
                        <Button type="submit" label="Crear rol" :loading="form.processing" />
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
