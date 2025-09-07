<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useDataTable, useActionMenu, useConfirmDelete, useFormatters, usePermissions } from '@/Composables'; // Asumiendo un archivo index en Composables

// --- Props ---
const props = defineProps({
    branches: Object,
    filters: Object,
});

// --- Composables ---
const { search, onSort, onPage } = useDataTable('branches.index', props.filters, props.branches);
const { menuComponentRef, menuItems, generateAndShowMenu } = useActionMenu();
const { confirmDelete } = useConfirmDelete();
const { formatTime, getDisplaySchedule } = useFormatters();
const { hasPermission } = usePermissions();

// --- State ---
const home = ref({ icon: 'pi pi-home', url: route('dashboard') });
const items = ref([{ label: 'Sucursales' }]);
const schedulePopover = ref();
const selectedHours = ref(null);

// --- Menu Configuration ---
const menuConfig = [
    {
        label: 'Ver detalles', icon: 'pi pi-eye', permission: 'ver_sucursales',
        action: (branch) => router.get(route('branches.show', branch.id)),
    },
    {
        label: 'Editar', icon: 'pi pi-pencil', permission: 'editar_sucursales',
        action: (branch) => router.get(route('branches.edit', branch.id)),
    },
    {
        label: 'Eliminar', icon: 'pi pi-trash', class: 'p-menuitem-text-danger', permission: 'eliminar_sucursales',
        action: (branch) => confirmDelete({ item: branch, routeName: 'branches.destroy', itemNameKey: 'name' }),
    },
];

const toggleMenu = (event, branch) => generateAndShowMenu(event, branch, menuConfig);

const toggleSchedulePopover = (event, branch) => {
    selectedHours.value = branch.business_hours;
    schedulePopover.value.toggle(event);
};
</script>

<template>
    <Head title="Sucursales" />
    <AppLayout>
        <Breadcrumb :home="home" :model="items" class="!bg-transparent" />
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="bg-white dark:bg-neutral-900 shadow-md rounded-lg">
                <div class="flex justify-end items-center p-6 pb-0">
                    <Link :href="route('branches.create')" class="sm:mt-0 w-full sm:w-auto">
                        <Button v-if="hasPermission('crear_sucursales')" label="Crear sucursal" icon="pi pi-plus" class="w-full" />
                    </Link>
                </div>
                <div class="flex flex-col sm:flex-row justify-between items-center p-6 border-b border-gray-200 dark:border-gray-700">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 text-center sm:text-left">Sucursales</h1>
                    <IconField>
                        <InputText v-model="search" placeholder="Buscar" class="w-full" />
                        <InputIcon class="pi pi-search" />
                    </IconField>
                </div>
                <div class="overflow-x-auto">
                    <DataTable :value="branches.data" @sort="onSort" :sortField="filters.sort_by || 'id'" :sortOrder="filters.sort_direction === 'asc' ? 1 : -1" removableSort>
                        <Column field="id" header="ID" sortable></Column>
                        <Column field="name" header="Nombre" sortable></Column>
                        <Column field="address" header="Dirección" sortable></Column>
                        <Column field="phone" header="Teléfono"></Column>
                        <Column field="business_hours" header="Horario de Atención">
                            <template #body="slotProps">
                                <div v-if="slotProps.data.business_hours" @click="toggleSchedulePopover($event, slotProps.data)" class="cursor-pointer hover:text-primary-500 flex items-center gap-2">
                                    <span>{{ getDisplaySchedule(slotProps.data.business_hours) }}</span>
                                    <i class="pi pi-info-circle text-xs text-gray-400"></i>
                                </div>
                                <span v-else class="text-gray-400">No definido</span>
                            </template>
                        </Column>
                        <Column v-if="hasPermission('editar_sucursales') || hasPermission('eliminar_sucursales')" bodyStyle="text-align:center; overflow:visible">
                            <template #body="slotProps">
                                <Button @click="toggleMenu($event, slotProps.data)" icon="pi pi-ellipsis-v" text rounded aria-haspopup="true" />
                            </template>
                        </Column>
                        <template #empty><div class="text-center p-8"><p class="text-gray-500 dark:text-gray-400">No se encontraron sucursales.</p></div></template>
                    </DataTable>
                    <Menu ref="menuComponentRef" :model="menuItems" :popup="true" />
                </div>
                <Paginator v-if="branches.total > branches.per_page" :first="branches.from - 1" :rows="branches.per_page" :totalRecords="branches.total" :rowsPerPageOptions="[10, 20, 30, 50]" @page="onPage" class="p-6 border-t border-gray-200 dark:border-gray-700" />
            </div>
        </div>
    </AppLayout>
    <Popover ref="schedulePopover">
        <div class="p-4 w-96">
            <h3 class="font-bold text-lg mb-4">Horario Completo</h3>
            <div v-if="selectedHours" class="space-y-2">
                <div v-for="day in selectedHours" :key="day.day_name" class="flex justify-between items-center text-sm">
                    <span class="font-semibold">{{ day.day_name }}:</span>
                    <Tag v-if="day.is_active" :value="`${formatTime(day.start_time)} - ${formatTime(day.end_time)}`" />
                    <Tag v-else value="Cerrado" severity="danger" />
                </div>
            </div>
        </div>
    </Popover>
</template>

<style>
.p-menuitem-text-danger > .p-menuitem-link { color: var(--red-500) !important; }
.p-menuitem-text-danger > .p-menuitem-link:hover { background-color: var(--red-50) !important; }
.p-datatable { border-radius: 0 !important; }
</style>