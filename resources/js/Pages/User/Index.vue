<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
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

// --- Watchers ---
watch(search, debounce((value) => {
    router.get(route('users.index'), { search: value }, {
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
    }, {
        preserveState: true,
        replace: true,
    });
};

const onPage = (event) => {
    router.get(route('users.index'), { page: event.page + 1 }, {
        preserveState: true,
        replace: true,
    });
};

const getStatusSeverity = (status) => {
    return status ? 'success' : 'danger';
};

const getStatusLabel = (status) => {
    return status ? 'Activo' : 'Inactivo';
};

const toggleMenu = (event, person) => {
    // El menú de acciones ahora se genera dinámicamente
    const items = [];

    // Solo se puede ver o editar si es un usuario del sistema
    if (person.user_id) {
        items.push({
            label: 'Ver detalles',
            icon: 'pi pi-eye',
            command: () => console.log('Ver usuario', person.user_id)
        });
        items.push({
            label: 'Editar',
            icon: 'pi pi-pencil',
            command: () => router.get(route('users.edit', person.user_id))
        });
    } else {
        // Si es solo un empleado, podríamos llevar a una ruta de edición de empleados en el futuro
        items.push({
            label: 'Editar Empleado',
            icon: 'pi pi-pencil',
            // command: () => router.get(route('employees.edit', person.employee_id)) // Descomentar cuando exista
            disabled: true // Deshabilitado por ahora
        });
    }

    // La opción de eliminar siempre está presente (ajustar lógica según tus reglas de negocio)
    items.push({
        label: 'Eliminar',
        icon: 'pi pi-trash',
        class: 'p-menuitem-text-danger',
        command: () => console.log('Eliminar', person.id)
    });

    selectedUserMenu.value = items;
    menu.value.toggle(event);
};

</script>

<template>

    <Head title="Usuarios" />

    <AppLayout>
        <Breadcrumb :home="home" :model="items" class="!bg-transparent" />
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <!-- TARJETA PRINCIPAL -->
            <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg">
                <!-- BREADCRUMB Y BOTÓN CREAR -->
                <div class="flex justify-end items-center p-6 pb-0">
                    <Link :href="route('users.create')" class="sm:mt-0 w-full sm:w-auto">
                    <Button label="Crear usuario" icon="pi pi-plus" class="w-full" />
                    </Link>
                </div>
                <!-- CABECERA DE LA TARJETA -->
                <div
                    class="flex flex-col sm:flex-row justify-between items-center p-6 border-b border-gray-200 dark:border-gray-700">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 text-center sm:text-left">Usuarios
                    </h1>
                    <IconField>
                        <InputText v-model="search" placeholder="Buscar" class="w-full" />
                        <InputIcon class="pi pi-search" />
                    </IconField>
                </div>
                <!-- DATA TABLE -->
                <div class="overflow-x-auto">
                    <DataTable :value="users.data" @sort="onSort" :sortField="filters.sort_by || 'id'"
                        :sortOrder="filters.sort_direction === 'asc' ? 1 : -1" removableSort>
                        <Column field="avatar_url" header="Imagen">
                            <template #body="slotProps">
                                <Avatar :image="slotProps.data.avatar_url" shape="circle" size="large" />
                            </template>
                        </Column>

                        <Column field="employee_number" header="N° empleado" sortable></Column>
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

                        <Column bodyStyle="text-align:center; overflow:visible">
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

                <!-- PAGINACIÓN -->
                <Paginator v-if="users.total > users.per_page" :first="users.from - 1" :rows="users.per_page"
                    :totalRecords="users.total" @page="onPage"
                    class="p-6 border-t border-gray-200 dark:border-gray-700" />
            </div>
        </div>
    </AppLayout>
</template>

<style>
/* Estilo para el botón de eliminar en el menú */
.p-menuitem-text-danger>.p-menuitem-link {
    color: var(--red-500) !important;
}

.p-menuitem-text-danger>.p-menuitem-link:hover {
    background-color: var(--red-50) !important;
}

/* Ajustes para que la tabla no tenga bordes extraños dentro de la tarjeta */
.p-datatable {
    border-radius: 0 !important;
}
</style>
