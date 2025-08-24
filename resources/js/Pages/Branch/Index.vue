<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { debounce } from 'lodash';

// --- Props ---
const props = defineProps({
    branches: Object,
    filters: Object,
});

// --- Refs and State ---
const search = ref(props.filters.search);
const home = ref({ icon: 'pi pi-home', url: route('dashboard') });
const items = ref([{ label: 'Sucursales' }]);
const menu = ref();
const selectedBranchMenu = ref(null);
const rows = ref(props.branches.per_page);

// --- Watchers ---
watch(search, debounce((value) => {
    router.get(route('branches.index'), {
        search: value,
        per_page: rows.value
    }, {
        preserveState: true,
        replace: true,
    });
}, 300));

// --- Methods ---
const onSort = (event) => {
    router.get(route('branches.index'), {
        ...props.filters,
        sort_by: event.sortField,
        sort_direction: event.sortOrder === 1 ? 'asc' : 'desc',
        per_page: rows.value
    }, {
        preserveState: true,
        replace: true,
    });
};

const onPage = (event) => {
    rows.value = event.rows;
    router.get(route('branches.index'), {
        page: event.page + 1,
        per_page: event.rows
    }, {
        preserveState: true,
        replace: true,
    });
};

const toggleMenu = (event, branch) => {
    selectedBranchMenu.value = [
        { label: 'Ver detalles', icon: 'pi pi-eye', command: () => router.get(route('branches.show', branch.id)) },
        { label: 'Editar', icon: 'pi pi-pencil', command: () => router.get(route('branches.edit', branch.id)) },
        { label: 'Eliminar', icon: 'pi pi-trash', class: 'p-menuitem-text-danger', command: () => console.log('Eliminar', branch.id) }
    ];
    menu.value.toggle(event);
};

</script>

<template>
    <Head title="Sucursales" />

    <AppLayout>
        <Breadcrumb :home="home" :model="items" class="!bg-transparent" />
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <!-- TARJETA PRINCIPAL -->
            <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg">
                <div class="flex justify-end items-center p-6 pb-0">
                    <Link :href="route('branches.create')" class="sm:mt-0 w-full sm:w-auto">
                        <Button label="Crear sucursal" icon="pi pi-plus" class="w-full" />
                    </Link>
                </div>
                <div class="flex flex-col sm:flex-row justify-between items-center p-6 border-b border-gray-200 dark:border-gray-700">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 text-center sm:text-left">Sucursales</h1>
                    <IconField>
                        <InputText v-model="search" placeholder="Buscar" class="w-full" />
                        <InputIcon class="pi pi-search" />
                    </IconField>
                </div>

                <!-- DATA TABLE -->
                <div class="overflow-x-auto">
                    <DataTable :value="branches.data" @sort="onSort" :sortField="filters.sort_by || 'id'"
                        :sortOrder="filters.sort_direction === 'asc' ? 1 : -1" removableSort>

                        <Column field="id" header="ID" sortable></Column>
                        <Column field="name" header="Nombre" sortable></Column>
                        <Column field="address" header="Dirección" sortable></Column>
                        <Column field="phone" header="Teléfono"></Column>
                        <Column field="schedule" header="Horario"></Column>

                        <Column bodyStyle="text-align:center; overflow:visible">
                            <template #body="slotProps">
                                <Button @click="toggleMenu($event, slotProps.data)" icon="pi pi-ellipsis-v" text rounded
                                    aria-haspopup="true" />
                            </template>
                        </Column>

                        <template #empty>
                            <div class="text-center p-8">
                                <p class="text-gray-500 dark:text-gray-400">No se encontraron sucursales.</p>
                            </div>
                        </template>
                    </DataTable>
                    <Menu ref="menu" :model="selectedBranchMenu" :popup="true" />
                </div>

                <!-- PAGINACIÓN -->
                <Paginator v-if="branches.total > branches.per_page" :first="branches.from - 1" :rows="branches.per_page"
                    :totalRecords="branches.total" :rowsPerPageOptions="[10, 20, 30, 50]" @page="onPage"
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
.p-datatable {
    border-radius: 0 !important;
}
</style>
