<script setup>
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { format } from 'date-fns';
import es from 'date-fns/locale/es';

const props = defineProps({ periods: Object });

const onRowSelect = (event) => {
    const period_date = new Date(event.data.period);
    const formatted_period = `${period_date.getFullYear()}-${String(period_date.getMonth() + 1).padStart(2, '0')}`;
    router.get(route('bonuses.show', formatted_period));
};

const formatDate = (dateString, formatStr) => {
    return format(new Date(dateString), formatStr, { locale: es });
};
</script>

<template>

    <Head title="Bonos" />
    <AppLayout>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg">
                <div class="p-6 border-b">
                    <h1 class="text-2xl font-bold">Bonos</h1>
                </div>
                <div class="overflow-x-auto">
                    <DataTable :value="periods.data" @row-select="onRowSelect" selectionMode="single" dataKey="period"
                        class="cursor-pointer">
                        <Column field="period" header="Mes">
                            <template #body="{ data }">{{ formatDate(data.period, 'MM') }}</template>
                        </Column>
                        <Column header="Periodo">
                            <template #body="{ data }">{{ formatDate(data.period, 'dd MMMM yyyy') }} - {{ formatDate(new
                                Date(data.period).setMonth(new Date(data.period).getMonth() + 1) - 1, 'dd MMMM yyyy')
                                }}</template>
                        </Column>
                        <Column header="Estatus">
                            <template #body="{ data }">
                                <Tag value="Abierta" severity="success" />
                            </template>
                        </Column>
                        <template #empty>
                            <div class="text-center p-8">
                                <p class="text-gray-500 dark:text-gray-400">No se encontraron periodos de bonos.</p>
                            </div>
                        </template>
                    </DataTable>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
