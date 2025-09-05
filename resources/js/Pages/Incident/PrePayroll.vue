<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { format } from 'date-fns';
import es from 'date-fns/locale/es';
import { PrimeIcons } from '@primevue/core/api';

// --- Props ---
const props = defineProps({
    period: Object,
    reportData: Object,
});

// --- Refs and State ---
const home = ref({ icon: 'pi pi-home', url: route('dashboard') });
const items = ref([
    { label: 'Incidencias', url: route('incidents.index'), icon: PrimeIcons.CALENDAR_TIMES },
    { label: `Semana ${props.period.week_number}`, url: route('incidents.show', props.period.id) },
    { label: 'Prenómina' }
]);

// --- Methods ---
const formatDate = (dateString, formatStr = "EEEE, dd 'de' MMMM") => {
    if (!dateString) return '';
    const date = new Date(dateString).toISOString();
    return format(date, formatStr, { locale: es });
};

const printReport = () => {
    window.print();
};

const filteredIncidents = (incidents) => {
    if (!incidents || !incidents.length) return [];
    // Filtra cualquier incidencia que sea EXACTAMENTE "Descanso (...)"
    return incidents.filter(inc => !/^Descanso \(/.test(inc));
};
</script>

<template>

    <Head :title="`Prenómina - Semana ${period.week_number}`" />

    <AppLayout>
        <Breadcrumb :home="home" :model="items" class="!bg-transparent print:hidden" />
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg p-6">
                <!-- HEADER -->
                <div class="flex justify-between items-start pb-4 border-b">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 m-0">Reporte de incidencias para
                            nómina</h1>
                        <p class="text-gray-500">Periodo: {{ formatDate(period.start_date) }} - {{
                            formatDate(period.end_date) }}</p>
                    </div>
                    <div class="text-right">
                        <p class="font-bold">Paquetería Casa Blanca</p>
                        <p class="text-sm text-gray-500">Generado {{ formatDate(new Date(), "dd MMMM yyyy, hh:mm a") }}
                        </p>
                        <!-- <p class="text-sm text-gray-500">Generado {{ new Date(), "dd MMMM yyyy, hh:mm a" }}</p> -->
                    </div>
                </div>
                <div class="flex justify-end mt-4 print:hidden">
                    <Button label="Imprimir o guardar en PDF" icon="pi pi-print" @click="printReport" />
                </div>

                <!-- TABLAS POR SUCURSAL -->
                <div v-for="(employees, branchName) in reportData" :key="branchName" class="mt-8">
                    <div class="bg-gray-100 dark:bg-gray-700 p-3 rounded-t-lg">
                        <h2 class="font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2 text-base m-0">
                            <i class="pi pi-building"></i>
                            <span>Sucursal {{ branchName }}</span>
                        </h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left">
                            <thead
                                class="bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-400 uppercase">
                                <tr>
                                    <th class="px-4 w-1/5 py-2">N° empleado</th>
                                    <th class="px-4 w-1/5 py-2">Colaborador</th>
                                    <th class="px-4 w-[15%] py-2">Días a pagar</th>
                                    <th class="px-4 py-2">Incidencias</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="employee in employees" :key="employee.id"
                                    class="border-b dark:border-gray-700">
                                    <td class="px-4 py-2 font-medium">{{ employee.employee_number }}</td>
                                    <td class="px-4 py-2">{{ employee.name }}</td>
                                    <td class="px-4 py-2">{{ employee.days_to_pay }}</td>
                                    <td class="px-4 py-2">
                                        <div v-if="filteredIncidents(employee.incidents).length" class="space-y-1">
                                            <div v-for="(incident, index) in filteredIncidents(employee.incidents)"
                                                :key="index">
                                                <div v-if="incident.includes('Día Festivo Laborado')"
                                                    class="flex items-center gap-2 font-semibold text-yellow-600">
                                                    <i class="pi pi-star-fill"></i>
                                                    <span>{{ incident }}</span>
                                                </div>
                                                <div v-else-if="incident.includes('Falta Injustificada (auto-detectada)')"
                                                    class="flex items-center gap-2 font-semibold text-red-600">
                                                    <span>{{ incident }}</span>
                                                </div>
                                                <span v-else>{{ incident }}</span>
                                            </div>
                                        </div>
                                        <span v-else class="text-gray-400">-</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style>
@media print {
    body * {
        visibility: hidden;
    }

    .max-w-6xl,
    .max-w-6xl * {
        visibility: visible;
    }

    .max-w-6xl {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        margin: 0;
        padding: 0;
    }
}
</style>
