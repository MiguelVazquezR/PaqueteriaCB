<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { useConfirm } from "primevue/useconfirm";
import { useToast } from "primevue/usetoast";
import { PrimeIcons } from '@primevue/core/api';

// --- Props ---
const props = defineProps({
    roles: Array,
    permissions: Object,
    errors: Object,
});

// --- Refs and State ---
const confirm = useConfirm();
const toast = useToast();
const home = ref({ icon: 'pi pi-home', url: route('dashboard') });
const items = ref([
    { label: 'Configuraciones' },
    { label: 'Roles y permisos' }
]);
const drawerVisible = ref(false);
const selectedRole = ref(null);

// ✨ Formulario para guardar los permisos de un rol
const permissionsForm = useForm({
    permissions: [],
});

// Formulario para crear permiso
const newPermissionModalVisible = ref(false);
const newPermissionForm = useForm({ action: '', category: '' });

// Formulario para editar permiso
const editPermissionModalVisible = ref(false);
const editPermissionForm = useForm({ id: null, action: '', category: '' });


// --- Computed Properties ---
const permissionCategories = computed(() => {
    return Object.keys(props.permissions);
});

// --- Methods ---
const openPermissionsDrawer = (role) => {
    selectedRole.value = role;
    permissionsForm.permissions = role.permissions.map(p => p.name);
    drawerVisible.value = true;
};

// ✨ Método para guardar los permisos actualizados
const savePermissions = () => {
    permissionsForm.put(route('settings.roles-permissions.updatePermissions', selectedRole.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            drawerVisible.value = false;
            toast.add({ severity: 'success', summary: 'Éxito', detail: 'Permisos actualizados', life: 3000 });
        }
    });
};

const openNewPermissionModal = () => {
    newPermissionForm.reset();
    newPermissionModalVisible.value = true;
};

const saveNewPermission = () => {
    newPermissionForm.post(route('settings.permissions.store'), {
        preserveScroll: true,
        onSuccess: () => {
            newPermissionModalVisible.value = false;
            newPermissionForm.reset();
        }
    });
};

// ---  Métodos para Editar y Eliminar Permiso ---
const openEditPermissionModal = (permission) => {
    const [action, ...categoryParts] = permission.name.split('_');
    editPermissionForm.id = permission.id;
    editPermissionForm.action = action;
    editPermissionForm.category = categoryParts.join('_');
    editPermissionModalVisible.value = true;
};

const updatePermission = () => {
    editPermissionForm.put(route('settings.permissions.update', editPermissionForm.id), {
        preserveScroll: true,
        onSuccess: () => { editPermissionModalVisible.value = false; }
    });
};

const confirmDeletePermission = (permission) => {
    confirm.require({
        message: `¿Estás seguro de que quieres eliminar el permiso "${permission.name.replace(/_/g, ' ')}"? Esta acción no se puede deshacer.`,
        header: 'Confirmación de eliminación',
        icon: 'pi pi-exclamation-triangle',
        rejectProps: {
            label: 'Cancelar',
            severity: 'secondary',
            outlined: true
        },
        acceptProps: {
            label: 'Eliminar',
            severity: 'danger'
        },
        accept: () => {
            router.delete(route('settings.permissions.destroy', permission.id), {
                preserveScroll: true,
                onSuccess: () => {
                    toast.add({ severity: 'success', summary: 'Confirmado', detail: 'Permiso eliminado', life: 3000 });
                }
            });
        },
        // reject: () => {
        //     toast.add({ severity: 'error', summary: 'Rechazado', detail: 'No se eliminó el permiso', life: 3000 });
        // }
    });
};

// Función auxiliar para verificar permisos
const hasPermission = (permission) => {
    return usePage().props.auth.permissions?.includes(permission) ?? false;
};

</script>

<template>

    <Head title="Roles y Permisos" />

    <AppLayout>
        <Breadcrumb :home="home" :model="items" class="!bg-transparent" />
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <!-- === HEADER === -->
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Roles y permisos</h1>
                <Button v-if="hasPermission('crear_roles')" label="Agregar rol" icon="pi pi-plus"
                    @click="router.get(route('settings.roles-permissions.create'))" />
            </div>

            <!-- === GRID DE ROLES === -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="role in roles" :key="role.id"
                    class="bg-white dark:bg-gray-800 shadow-md rounded-lg p-6 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="bg-primary-100 dark:bg-primary-900/50 p-3 rounded-full">
                            <i class="pi pi-users text-primary-600 dark:text-primary-300 text-2xl"></i>
                        </div>
                        <div>
                            <h2 class="font-semibold text-lg text-gray-800 dark:text-gray-200">{{ role.name }}</h2>
                            <button @click="openPermissionsDrawer(role)"
                                class="text-sm text-primary-600 hover:underline flex items-center gap-1">
                                <span>Ver permisos</span>
                                <i class="pi pi-arrow-right text-xs"></i>
                            </button>
                        </div>
                    </div>
                    <span class="text-sm text-gray-500">{{ role.permissions_count }} permisos</span>
                </div>
            </div>

            <!-- === DRAWER DE PERMISOS === -->
            <Drawer v-model:visible="drawerVisible" position="right" class="w-full md:w-96 lg:w-[30rem]">
                <template #header>
                    <div class="w-full flex justify-between items-center">
                        <div>
                            <h2 class="text-lg font-bold">Permisos para: {{ selectedRole?.name }}</h2>
                            <p class="text-sm text-gray-500">Este rol tiene {{ selectedRole?.permissions_count }} de {{
                                permissions.length }} permisos</p>
                        </div>
                        <Button icon="pi pi-trash" severity="danger" text rounded aria-label="Eliminar rol" />
                    </div>
                </template>

                <div class="space-y-6">
                    <div v-for="(permissionGroup, groupName) in permissions" :key="groupName">
                        <h3
                            class="font-semibold text-gray-700 dark:text-gray-300 capitalize mb-2 flex items-center gap-2">
                            <i class="pi pi-folder"></i>
                            <span>{{ groupName }}</span>
                        </h3>
                        <div v-for="permission in permissionGroup" :key="permission.id"
                            class="flex items-center justify-between p-2 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700">
                            <div class="flex items-center">
                                <Checkbox v-model="permissionsForm.permissions" :inputId="`perm-${permission.id}`"
                                    :value="permission.name" />
                                <label :for="`perm-${permission.id}`"
                                    class="ml-2 text-sm text-gray-600 dark:text-gray-400 capitalize">
                                    {{ permission.name.replace(/_/g, ' ') }}
                                </label>
                            </div>
                            <div class="flex items-center">
                                <Button icon="pi pi-pencil" text rounded size="small"
                                    @click="openEditPermissionModal(permission)" />
                                <Button icon="pi pi-trash" text rounded size="small" severity="danger"
                                    @click="confirmDeletePermission(permission)" />
                            </div>
                        </div>
                    </div>
                </div>

                <template #footer>
                    <div class="w-full">
                        <div class="border-t pt-4">
                            <button @click="openNewPermissionModal"
                                class="w-full text-left p-2 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                <i class="pi pi-plus-circle"></i>
                                <span>Agregar permisos</span>
                            </button>
                            <p class="text-xs text-gray-400 mt-1 pl-2">Este espacio está destinado para desarrolladores
                                del sistema.</p>
                        </div>
                        <div class="flex justify-end gap-2 mt-4">
                            <Button label="Cancelar" severity="secondary" @click="drawerVisible = false" />
                            <Button label="Guardar permisos" @click="savePermissions"
                                :loading="permissionsForm.processing" />
                        </div>
                    </div>
                </template>
            </Drawer>

            <!-- === MODAL PARA AGREGAR PERMISO === -->
            <Dialog v-model:visible="newPermissionModalVisible" modal header="Agregar nuevo permiso"
                :style="{ width: '30rem' }">
                <form @submit.prevent="saveNewPermission">
                    <div class="flex flex-col gap-4">
                        <div class="flex flex-col gap-2">
                            <InputLabel for="action" value="Acción del permiso*" />
                            <InputText id="action" v-model="newPermissionForm.action"
                                placeholder="Ej: ver, crear, eliminar" :invalid="!!newPermissionForm.errors.action" />
                            <small v-if="newPermissionForm.errors.action" class="text-red-500">{{
                                newPermissionForm.errors.action
                            }}</small>
                        </div>
                        <div class="flex flex-col gap-2">
                            <InputLabel for="category" value="Categoría del permiso*" />
                            <Dropdown id="category" v-model="newPermissionForm.category" :options="permissionCategories"
                                editable placeholder="Selecciona o escribe una categoría" class="w-full"
                                :invalid="!!newPermissionForm.errors.category" />
                            <small v-if="newPermissionForm.errors.category" class="text-red-500">{{
                                newPermissionForm.errors.category }}</small>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 mt-6">
                        <Button type="button" label="Cancelar" severity="secondary"
                            @click="newPermissionModalVisible = false"></Button>
                        <Button type="submit" label="Crear permiso" :loading="newPermissionForm.processing"></Button>
                    </div>
                </form>
            </Dialog>

            <!-- === MODAL PARA EDITAR PERMISO === -->
            <Dialog v-model:visible="editPermissionModalVisible" modal header="Editar permiso"
                :style="{ width: '30rem' }">
                <form @submit.prevent="updatePermission">
                    <div class="flex flex-col gap-4">
                        <div class="flex flex-col gap-2">
                            <InputLabel for="edit-action" value="Acción del permiso*" />
                            <InputText id="edit-action" v-model="editPermissionForm.action"
                                :invalid="!!editPermissionForm.errors.action" />
                            <small v-if="editPermissionForm.errors.action" class="text-red-500">{{
                                editPermissionForm.errors.action
                                }}</small>
                        </div>
                        <div class="flex flex-col gap-2">
                            <InputLabel for="edit-category" value="Categoría del permiso*" />
                            <Dropdown id="edit-category" v-model="editPermissionForm.category"
                                :options="permissionCategories" editable class="w-full"
                                :invalid="!!editPermissionForm.errors.category" />
                            <small v-if="editPermissionForm.errors.category" class="text-red-500">{{
                                editPermissionForm.errors.category }}</small>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 mt-6">
                        <Button type="button" label="Cancelar" severity="secondary"
                            @click="editPermissionModalVisible = false"></Button>
                        <Button type="submit" label="Guardar cambios" :loading="editPermissionForm.processing"></Button>
                    </div>
                </form>
            </Dialog>

        </div>
    </AppLayout>
</template>