<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue'; // Usando AppLayout
import { debounce } from 'lodash';

// --- Props ---
const props = defineProps({
    users: Object,
    filters: Object,
});

// --- Refs and State ---
const search = ref(props.filters.search);
const home = ref({ icon: 'pi pi-home', url: route('dashboard') });
const items = ref([{ label: 'Usuarios' }]);
const menu = ref();
const selectedUserMenu = ref(null);
// Guardamos el número de filas por página para mantener el estado
const rows = ref(props.users.per_page);

// --- Watchers ---
watch(search, debounce((value) => {
    router.get(route('users.index'), {
        search: value,
        per_page: rows.value // Mantenemos el número de filas al buscar
    }, {
        preserveState: true,
        replace: true,
    });
}, 300));

// --- Methods ---
const onSort = (event) => {
    router.get(route('users.index'), {
        ...props.filters,
        sort_by: event.sortField,
        sort_direction: event.sortOrder === 1 ? 'asc' : 'desc',
        per_page: rows.value // Mantenemos el número de filas al ordenar
    }, {
        preserveState: true,
        replace: true,
    });
};

// El método onPage ahora también maneja el cambio de filas
const onPage = (event) => {
    rows.value = event.rows; // Actualizamos el ref con el nuevo valor
    router.get(route('users.index'), {
        page: event.page + 1,
        per_page: event.rows // Enviamos el nuevo número de filas al backend
    }, {
        preserveState: true,
        replace: true,
    });
};

const hasPermission = (permission) => {
    return usePage().props.auth.permissions?.includes(permission) ?? false;
};

const getStatusSeverity = (status) => {
    return status ? 'success' : 'danger';
};

const getStatusLabel = (status) => {
    return status ? 'Activo' : 'Inactivo';
};

const toggleMenu = (event, person) => {
    const items = [];
    if (person.user_id) {
        items.push({ label: 'Ver detalles', icon: 'pi pi-eye', command: () => router.get(route('users.show', person.user_id)) });
        
        if (hasPermission('editar_usuarios')) {
            items.push({ label: 'Editar', icon: 'pi pi-pencil', command: () => router.get(route('users.edit', person.user_id)) });
        }
    } 
    // else {
    //     items.push({ label: 'Editar Empleado', icon: 'pi pi-pencil', disabled: false });
    // }
    // items.push({ label: 'Eliminar', icon: 'pi pi-trash', class: 'p-menuitem-text-danger', command: () => confirmDeletePerson(person) });
    selectedUserMenu.value = items;
    menu.value.toggle(event);
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
                <div
                    class="flex flex-col sm:flex-row justify-between items-center p-6 border-b border-gray-200 dark:border-gray-700">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 text-center sm:text-left">Usuarios
                    </h1>
                    <IconField>
                        <InputText v-model="search" placeholder="Buscar" class="w-full" />
                        <InputIcon class="pi pi-search" />
                    </IconField>
                </div>
                <div class="overflow-x-auto">
                    <DataTable :value="users.data" @sort="onSort" :sortField="filters.sort_by || 'id'"
                        :sortOrder="filters.sort_direction === 'asc' ? 1 : -1" removableSort>
                        <Column field="avatar_url" header="Imagen">
                            <template #body="slotProps">
                                <img :src="slotProps.data.avatar_url" :alt="slotProps.data.name"
                                    class="size-12 rounded-full object-cover" />
                            </template>
                        </Column>
                        <Column field="employee_number" header="N° empleado" class="w-[140px]" sortable></Column>
                        <Column field="name" header="Nombre" sortable></Column>
                        <Column field="position" header="Puesto" sortable></Column>
                        <Column field="branch" header="Sucursal" sortable></Column>
                        <Column field="phone" header="Teléfono"></Column>
                        <Column field="role" header="Rol"></Column>
                        <Column field="status" header="Estatus" sortable>
                            <template #body="slotProps">
                                <Tag :value="getStatusLabel(slotProps.data.status)"
                                    :severity="getStatusSeverity(slotProps.data.status)"
                                    class="rounded-full px-3 py-1 text-xs font-semibold" />
                            </template>
                        </Column>
                        <Column v-if="hasPermission('editar_usuarios')" bodyStyle="text-align:center; overflow:visible">
                            <template #body="slotProps">
                                <Button @click="toggleMenu($event, slotProps.data)" icon="pi pi-ellipsis-v" text rounded
                                    aria-haspopup="true" />
                            </template>
                        </Column>
                        <template #empty>
                            <div class="text-center p-8">
                                <p class="text-gray-500 dark:text-gray-400">No se encontraron usuarios.</p>
                            </div>
                        </template>
                    </DataTable>
                    <Menu ref="menu" :model="selectedUserMenu" :popup="true" />
                </div>
                <Paginator v-if="users.total > 0" :first="users.from - 1" :rows="users.per_page"
                    :totalRecords="users.total" :rowsPerPageOptions="[10, 20, 30, 50]" @page="onPage"
                    class="p-6 border-t border-gray-200 dark:border-gray-700" />
            </div>
        </div>
    </AppLayout>
</template>