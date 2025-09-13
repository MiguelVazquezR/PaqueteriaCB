<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useDataTable, useActionMenu, useConfirmDelete, usePermissions } from '@/Composables';

// --- Props ---
const props = defineProps({
    schedules: Object,
    filters: Object
});

// --- Composables ---
const { search, onPage } = useDataTable('settings.schedules.index', props.filters, props.schedules);
const { hasPermission } = usePermissions();
const { confirmDelete } = useConfirmDelete();
const { menuComponentRef, menuItems, generateAndShowMenu } = useActionMenu();

// --- State ---
const home = ref({ icon: 'pi pi-home', url: route('dashboard') });
const items = ref([{ label: 'Horarios del personal' }]);

// --- Menu Configuration ---
const menuConfig = [
    {
        label: 'Editar',
        icon: 'pi pi-pencil',
        permission: 'editar_horarios',
        action: (schedule) => router.get(route('settings.schedules.edit', schedule.id)),
    },
    {
        label: 'Eliminar',
        icon: 'pi pi-trash',
        permission: 'eliminar_horarios',
        action: (schedule) => confirmDelete({
            item: schedule,
            routeName: 'settings.schedules.destroy',
            message: `¿Estás seguro de que quieres eliminar "${schedule.name}"?`
        }),
    },
];

const toggleMenu = (event, schedule) => generateAndShowMenu(event, schedule, menuConfig);

</script>

<template>
    <Head title="Horarios del Personal" />
    <AppLayout>
        <Breadcrumb :home="home" :model="items" class="!bg-transparent" />
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="bg-white dark:bg-neutral-900 shadow-md rounded-lg">
                <div class="flex justify-end items-center p-6 pb-0">
                    <Link :href="route('settings.schedules.create')" v-if="hasPermission('crear_horarios')">
                        <Button label="Crear nuevo horario" icon="pi pi-plus" />
                    </Link>
                </div>
                <div class="flex flex-col sm:flex-row justify-between items-center p-6 border-b">
                    <h1 class="text-2xl font-bold">Horarios definidos</h1>
                    <IconField>
                        <InputText v-model="search" placeholder="Buscar" />
                        <InputIcon class="pi pi-search" />
                    </IconField>
                </div>
                <div class="overflow-x-auto">
                    <DataTable :value="schedules.data">
                        <Column field="id" header="ID"></Column>
                        <Column field="name" header="Nombre del horario"></Column>
                        <Column header="Sucursales vinculadas">
                            <template #body="{ data }">
                                <ul v-if="data.branches.length" class="list-disc list-inside">
                                    <li v-for="branch in data.branches" :key="branch.id">{{ branch.name }}</li>
                                </ul>
                                <span v-else class="text-gray-400">Ninguna</span>
                            </template>
                        </Column>
                        <Column v-if="hasPermission('editar_horarios') || hasPermission('eliminar_horarios')">
                            <template #body="{ data }">
                                <Button @click="toggleMenu($event, data)" icon="pi pi-ellipsis-v" text rounded />
                            </template>
                        </Column>
                        <template #empty>
                            <div class="text-center p-8">
                                <p class="text-gray-500 dark:text-gray-400">No se encontraron horarios.</p>
                            </div>
                        </template>
                    </DataTable>
                    <Menu ref="menuComponentRef" :model="menuItems" :popup="true" />
                </div>
                <Paginator v-if="schedules && schedules.total > 0" :first="schedules.from - 1" :rows="schedules.per_page" :totalRecords="schedules.total" :rowsPerPageOptions="[10, 20, 30, 50]" @page="onPage" class="p-6 border-t" />
            </div>
        </div>
    </AppLayout>
</template>
