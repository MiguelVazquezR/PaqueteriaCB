<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { startOfMonth, endOfMonth } from 'date-fns';
import { useFormatters } from '@/Composables';

const props = defineProps({ periods: Object });

// --- Composables ---
const { formatDate, getStatusInfo } = useFormatters();

// --- State & Config ---
const home = ref({ icon: 'pi pi-home', url: route('dashboard') });
const items = ref([{ label: 'Bonos' }]);
const bonusStatusMap = {
    draft: { label: 'Borrador', severity: 'warn' },
    finalized: { label: 'Finalizado', severity: 'success' },
};
const defaultStatus = { label: 'Abierto', severity: 'info' };

// --- Computed ---
const processedPeriods = computed(() => {
    return props.periods.data.map(p => {
        const periodDate = new Date(p.period);
        const status = p.status ? getStatusInfo(p.status, bonusStatusMap) : defaultStatus;

        return {
            ...p,
            displayPeriod: `${formatDate(startOfMonth(periodDate))} - ${formatDate(endOfMonth(periodDate))}`,
            displayMonth: formatDate(periodDate, 'MMMM'),
            statusLabel: status.label,
            statusSeverity: status.severity,
        };
    });
});

// --- Methods ---
const onRowSelect = (event) => {
    const period_date = new Date(event.data.period);
    const formatted_period = `${period_date.getFullYear()}-${String(period_date.getMonth() + 1).padStart(2, '0')}`;
    router.get(route('bonuses.show', formatted_period));
};
</script>

<template>
    <Head title="Bonos" />
    <AppLayout>
        <Breadcrumb :home="home" :model="items" class="!bg-transparent" />
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="bg-white dark:bg-neutral-900 shadow-md rounded-lg">
                <div class="p-6 border-b flex justify-between items-center">
                    <h1 class="text-2xl font-bold">Reporte de Bonos Mensuales</h1>
                </div>
                <div class="overflow-x-auto">
                    <DataTable :value="processedPeriods" @row-select="onRowSelect" selectionMode="single" dataKey="period" class="cursor-pointer">
                        <Column field="period" header="Mes">
                            <template #body="{ data }"><span class="capitalize">{{ data.displayMonth }}</span></template>
                        </Column>
                        <Column header="Periodo">
                            <template #body="{ data }">{{ data.displayPeriod }}</template>
                        </Column>
                        <Column header="Estatus">
                            <template #body="{ data }">
                                <Tag :value="data.statusLabel" :severity="data.statusSeverity" />
                            </template>
                        </Column>
                        <template #empty><div class="text-center p-8"><p class="text-gray-500 dark:text-gray-400">No se encontraron periodos de bonos.</p></div></template>
                    </DataTable>
                </div>
            </div>
        </div>
    </AppLayout>
</template>