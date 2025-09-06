<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { debounce } from 'lodash';
// --- CAMBIO: --- Importar 'format' para la hora.
import { format } from 'date-fns';
import { useConfirm } from 'primevue';

// --- Props (sin cambios) ---
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

const confirm = useConfirm(); // Se inicializa

// --- CAMBIO: --- Refs para el popover del horario.
const schedulePopover = ref();
const selectedHours = ref(null);

// --- Watchers (sin cambios) ---
watch(search, debounce((value) => {
    router.get(route('branches.index'), { search: value, per_page: rows.value }, { preserveState: true, replace: true, });
}, 300));

// --- Methods (lógica de filtros sin cambios) ---
const hasPermission = (permission) => {
    return usePage().props.auth.permissions?.includes(permission) ?? false;
};

const confirmDeleteBranch = (branch) => {
    confirm.require({
        message: `¿Estás seguro de que quieres eliminar la sucursal "${branch.name}"? Esta acción no se puede deshacer.`,
        header: 'Confirmar Eliminación',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Sí, eliminar',
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
            router.delete(route('branches.destroy', branch.id), {
                preserveScroll: true,
            });
        }
    });
};

const onSort = (event) => {
    router.get(route('branches.index'), { ...props.filters, sort_by: event.sortField, sort_direction: event.sortOrder === 1 ? 'asc' : 'desc', per_page: rows.value }, { preserveState: true, replace: true, });
};

const onPage = (event) => {
    rows.value = event.rows;
    router.get(route('branches.index'), { page: event.page + 1, per_page: event.rows }, { preserveState: true, replace: true, });
};

const toggleMenu = (event, branch) => {
    const menuItems = [];

    // --- CAMBIO: --- Se añaden los elementos al menú solo si el usuario tiene el permiso correspondiente.
    if (hasPermission('ver_sucursales')) {
        menuItems.push({ label: 'Ver detalles', icon: 'pi pi-eye', command: () => router.get(route('branches.show', branch.id)) });
    }
    if (hasPermission('editar_sucursales')) {
        menuItems.push({ label: 'Editar', icon: 'pi pi-pencil', command: () => router.get(route('branches.edit', branch.id)) });
    }
    if (hasPermission('eliminar_sucursales')) {
        menuItems.push({ label: 'Eliminar', icon: 'pi pi-trash', class: 'p-menuitem-text-danger', command: () => confirmDeleteBranch(branch) });
    }
    
    selectedBranchMenu.value = menuItems;

    // Solo se muestra el menú si contiene al menos una opción.
    if (menuItems.length > 0) {
        menu.value.toggle(event);
    }
};

// --- CAMBIO: --- Nuevos métodos para formatear y mostrar el horario.
const formatTime = (timeString) => {
    if (!timeString) return '';
    const [hours, minutes] = timeString.split(':');
    const date = new Date();
    date.setHours(parseInt(hours), parseInt(minutes));
    return format(date, 'hh:mm a');
};

const getDisplaySchedule = (businessHours) => {
    if (!businessHours) return 'No definido';
    const firstActiveDay = Object.values(businessHours).find(day => day.is_active);
    if (firstActiveDay) {
        return `${firstActiveDay.day_name}: ${formatTime(firstActiveDay.start_time)} - ${formatTime(firstActiveDay.end_time)}`;
    }
    return 'Cerrado todos los días';
};

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
            <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg">
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
                        
                        <!-- --- CAMBIO: --- Columna de horario actualizada para usar el popover. -->
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
                        <template #empty>
                            <div class="text-center p-8">
                                <p class="text-gray-500 dark:text-gray-400">No se encontraron sucursales.</p>
                            </div>
                        </template>
                    </DataTable>
                    <Menu ref="menu" :model="selectedBranchMenu" :popup="true" />
                </div>

                <Paginator v-if="branches.total > branches.per_page" :first="branches.from - 1" :rows="branches.per_page" :totalRecords="branches.total" :rowsPerPageOptions="[10, 20, 30, 50]" @page="onPage" class="p-6 border-t border-gray-200 dark:border-gray-700" />
            </div>
        </div>
    </AppLayout>

    <!-- --- CAMBIO: --- Popover para mostrar los detalles del horario. -->
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
/* Estilos existentes (sin cambios) */
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

