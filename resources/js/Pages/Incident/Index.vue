<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { debounce } from 'lodash';
import { format } from 'date-fns';
import es from 'date-fns/locale/es';

// --- Props ---
const props = defineProps({
    periods: Object,
    filters: Object,
});

// --- Refs and State ---
const search = ref(props.filters.search);
const home = ref({ icon: 'pi pi-home', url: route('dashboard') });
const items = ref([{ label: 'Incidencias' }]);
const rows = ref(props.periods.per_page);

// --- Watchers ---
watch(search, debounce((value) => {
    router.get(route('incidents.index'), {
        search: value,
        per_page: rows.value
    }, {
        preserveState: true,
        replace: true,
    });
}, 300));

// --- Methods ---
const onSort = (event) => {
    router.get(route('incidents.index'), {
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
    router.get(route('incidents.index'), {
        page: event.page + 1,
        per_page: event.rows
    }, {
        preserveState: true,
        replace: true,
    });
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    // const date = new Date(dateString + 'T00:00:00');
    return format(dateString, "dd MMMM yyyy", { locale: es });
};

const getStatusSeverity = (status) => {
    return status === 'open' ? 'success' : 'secondary';
};

const getStatusLabel = (status) => {
    return status === 'open' ? 'Abierta' : 'Cerrada';
};

const onRowSelect = (event) => {
    router.get(route('incidents.show', event.data.id));
};

</script>

<template>
    <Head title="Incidencias" />

    <AppLayout>
        <Breadcrumb :home="home" :model="items" class="!bg-transparent" />
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg">
                <div class="flex flex-col sm:flex-row justify-between items-center p-6 border-b border-gray-200 dark:border-gray-700">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 text-center sm:text-left">Incidencias</h1>
                    <IconField>
                        <InputText v-model="search" placeholder="Buscar por semana" class="w-full" />
                        <InputIcon class="pi pi-search" />
                    </IconField>
                </div>

                <div class="overflow-x-auto">
                    <DataTable :value="periods.data" @sort="onSort" @row-select="onRowSelect" selectionMode="single"
                        dataKey="id" :sortField="filters.sort_by || 'week_number'"
                        :sortOrder="filters.sort_direction === 'asc' ? 1 : -1" removableSort
                        class="cursor-pointer">

                        <Column field="week_number" header="Semana" sortable></Column>
                        <Column header="Periodo">
                            <template #body="slotProps">
                                {{ formatDate(slotProps.data.start_date) }} - {{ formatDate(slotProps.data.end_date) }}
                            </template>
                        </Column>
                        <Column field="payment_date" header="Fecha de pago" sortable>
                             <template #body="slotProps">
                                {{ formatDate(slotProps.data.payment_date) }}
                            </template>
                        </Column>
                        <Column field="status" header="Estatus" sortable>
                            <template #body="slotProps">
                                <Tag :value="getStatusLabel(slotProps.data.status)"
                                    :severity="getStatusSeverity(slotProps.data.status)"
                                    class="rounded-full px-3 py-1 text-xs font-semibold" />
                            </template>
                        </Column>

                        <template #empty>
                            <div class="text-center p-8">
                                <p class="text-gray-500 dark:text-gray-400">No se encontraron periodos de incidencias.</p>
                            </div>
                        </template>
                    </DataTable>
                </div>

                <Paginator v-if="periods.total > periods.per_page" :first="periods.from - 1" :rows="periods.per_page"
                    :totalRecords="periods.total" :rowsPerPageOptions="[10, 20, 30, 50]" @page="onPage"
                    class="p-6 border-t border-gray-200 dark:border-gray-700" />
            </div>
        </div>
    </AppLayout>
</template>
