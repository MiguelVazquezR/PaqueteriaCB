<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useDataTable, useActionMenu, useFormatters, usePermissions } from '@/Composables';

// --- Props ---
const props = defineProps({
    users: Object,
    filters: Object,
});

// --- Composables ---
const { search, onSort, onPage } = useDataTable('users.index', props.filters, props.users);
const { menuComponentRef, menuItems, generateAndShowMenu } = useActionMenu();
const { getStatusInfo } = useFormatters();
const { hasPermission } = usePermissions();

// --- State ---
const home = ref({ icon: 'pi pi-home', url: route('dashboard') });
const items = ref([{ label: 'Usuarios' }]);

// --- Configs ---
const userStatusMap = {
    true: { label: 'Activo', severity: 'success' },
    false: { label: 'Inactivo', severity: 'danger' },
};

const menuConfig = [
    {
        label: 'Ver detalles', icon: 'pi pi-eye',
        // AJUSTADO: Se usa `user.id` que ahora viene del recurso unificado.
        action: (user) => router.get(route('users.show', user.id)),
    },
    {
        label: 'Editar', icon: 'pi pi-pencil', permission: 'editar_usuarios',
        // AJUSTADO: Se usa `user.id`.
        action: (user) => router.get(route('users.edit', user.id)),
    },
];

const toggleMenu = (event, user) => {
    if (user.id) {
        generateAndShowMenu(event, user, menuConfig);
    }
};
</script>

<template>
    <Head title="Usuarios" />
    <AppLayout>
        <Breadcrumb :home="home" :model="items" class="!bg-transparent" />
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="bg-white dark:bg-neutral-900 shadow-md rounded-lg">
                <div class="flex justify-end items-center p-6 pb-0">
                    <Link :href="route('users.create')" class="sm:mt-0 w-full sm:w-auto">
                        <Button v-if="hasPermission('crear_usuarios')" label="Crear usuario" icon="pi pi-plus" class="w-full" />
                    </Link>
                </div>
                <div class="flex flex-col sm:flex-row justify-between items-center p-6 border-b border-gray-200 dark:border-gray-700">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 text-center sm:text-left">Usuarios</h1>
                    <IconField>
                        <InputText v-model="search" placeholder="Buscar" class="w-full" />
                        <InputIcon class="pi pi-search" />
                    </IconField>
                </div>
                <div class="overflow-x-auto">
                    <DataTable :value="users.data" @sort="onSort" :sortField="filters.sort_by || 'id'" :sortOrder="filters.sort_direction === 'asc' ? 1 : -1" removableSort>
                        <Column field="avatar_url" header="Imagen">
                            <template #body="slotProps"><img :src="slotProps.data.avatar_url" :alt="slotProps.data.name" class="size-12 rounded-full object-cover" /></template>
                        </Column>
                        <!-- AJUSTES EN LAS COLUMNAS PARA LEER LA NUEVA ESTRUCTURA ANIDADA -->
                        <Column field="employee_number" header="N° empleado" class="w-[140px]" sortable>
                            <template #body="{ data }">
                                {{ data.employee?.employee_number }}
                            </template>
                        </Column>
                        <Column field="name" header="Nombre" sortable></Column>
                        <Column field="position" header="Puesto" sortable>
                             <template #body="{ data }">
                                {{ data.employee?.position ?? 'Usuario del Sistema' }}
                            </template>
                        </Column>
                        <Column field="branch" header="Sucursal" sortable>
                             <template #body="{ data }">
                                {{ data.employee?.branch?.name }}
                            </template>
                        </Column>
                        <Column field="phone" header="Teléfono">
                             <template #body="{ data }">
                                {{ data.employee?.phone }}
                            </template>
                        </Column>
                        <Column field="roles" header="Rol">
                            <template #body="{ data }">
                                <!-- Capitaliza el primer rol encontrado -->
                                <span v-if="data.roles && data.roles.length > 0" class="capitalize">
                                   {{ data.roles[0] }}
                                </span>
                            </template>
                        </Column>
                        <Column field="status" header="Estatus" sortable>
                            <template #body="slotProps">
                                <!-- Si no hay empleado, se asume que el usuario está activo -->
                                <Tag :value="getStatusInfo(slotProps.data.employee?.is_active ?? true, userStatusMap).label"
                                     :severity="getStatusInfo(slotProps.data.employee?.is_active ?? true, userStatusMap).severity"
                                     class="rounded-full px-3 py-1 text-xs font-semibold" />
                            </template>
                        </Column>
                        <Column v-if="hasPermission('editar_usuarios')" bodyStyle="text-align:center; overflow:visible">
                            <template #body="slotProps">
                                <Button @click="toggleMenu($event, slotProps.data)" icon="pi pi-ellipsis-v" text rounded aria-haspopup="true" />
                            </template>
                        </Column>
                        <template #empty><div class="text-center p-8"><p class="text-gray-500 dark:text-gray-400">No se encontraron usuarios.</p></div></template>
                    </DataTable>
                    <Menu ref="menuComponentRef" :model="menuItems" :popup="true" />
                </div>
                <Paginator v-if="users.total > 0" :first="users.from - 1" :rows="users.per_page" :totalRecords="users.total" :rowsPerPageOptions="[10, 20, 30, 50]" @page="onPage" class="p-6 border-t border-gray-200 dark:border-gray-700" />
            </div>
        </div>
    </AppLayout>
</template>