<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { format } from 'date-fns';
import es from 'date-fns/locale/es';

// --- Props ---
const props = defineProps({
    period: Object,
    employeesData: Array,
});

// --- Refs and State ---
const home = ref({ icon: 'pi pi-home', url: route('dashboard') });
const items = ref([
    { label: 'Incidencias', url: route('incidents.index') },
    { label: `Semana ${props.period.week_number}`, url: route('incidents.show', props.period.id) },
    { label: 'Imprimir incidencias' }
]);

// --- Methods ---
const formatDate = (dateString, formatStr = "EEEE, dd 'de' MMMM") => {
    if (!dateString) return '';
    const date = new Date(dateString).toDateString();
    return format(date, formatStr, { locale: es });
};

const getIncidentSeverity = (incidentName) => {
    const danger = ['Falta injustificada'];
    const warning = ['Incapacidad general', 'Permiso sin goce'];
    const success = ['Día Festivo', 'Descanso'];
    const info = ['Vacaciones', 'Permiso con goce'];

    if (danger.includes(incidentName)) return 'danger';
    if (warning.includes(incidentName)) return 'warning';
    if (success.includes(incidentName)) return 'success';
    if (info.includes(incidentName)) return 'info';
    return 'secondary';
};

const print = () => {
    window.print();
};

</script>

<template>

    <Head :title="`Imprimir Incidencias - Semana ${period.week_number}`" />

    <AppLayout>
        <Breadcrumb :home="home" :model="items" class="!bg-transparent print:hidden" />
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="flex justify-end mb-4 print:hidden">
                <Button label="Imprimir" icon="pi pi-print" @click="print" />
            </div>

            <!-- Contenedor principal que organiza las hojas de impresión -->
            <div class="space-y-4 print:space-y-5" id="print-container">
                <div v-for="employee in employeesData" :key="employee.id"
                    class="bg-white dark:bg-gray-800 shadow-md rounded-lg px-6 py-3 print:shadow-none print:border print:rounded-none print:flex print:flex-col print:justify-between print:break-inside-avoid">

                    <div>
                        <!-- Cabecera del empleado -->
                        <div class="flex justify-between items-start pb-2 border-b">
                            <div>
                                <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ employee.name }}</h2>
                                <p class="text-sm text-gray-500">N° {{ employee.employee_number }} • {{
                                    employee.position }}</p>
                            </div>
                            <div class="text-right text-sm">
                                <p class="font-semibold">Semana {{ period.week_number }}. Del {{
                                    formatDate(period.start_date, 'dd/MM/yy') }} al {{ formatDate(period.end_date,
                                        'dd/MM/yy') }}</p>
                                <p class="text-gray-500">{{ employee.branch_name }}</p>
                            </div>
                        </div>

                        <!-- Tabla de días -->
                        <div class="overflow-x-auto mt-1">
                            <table class="w-full text-sm text-left">
                                <thead class="text-xs text-gray-700 dark:text-gray-400 uppercase">
                                    <tr>
                                        <th class="py-2">Día</th>
                                        <th class="py-2">Entrada</th>
                                        <th class="py-2">Salida</th>
                                        <th class="py-2">T. Descanso</th>
                                        <th class="py-2">T. Extra</th>
                                        <th class="py-2">Horas totales</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="day in employee.daily_data" :key="day.date"
                                        class="border-b dark:border-gray-700">
                                        <td class="py-2 font-medium">{{ formatDate(day.date + 'T00:00:00', "EEE, dd MMM yyyy") }}</td>
                                        <template v-if="day.incident">
                                            <td colspan="5" class="py-2">
                                                <Tag :value="day.incident" :severity="getIncidentSeverity(day.incident)"
                                                    class="w-full text-center" />
                                            </td>
                                        </template>
                                        <template v-else>
                                            <td class="py-2 flex items-center gap-1">
                                                <span>{{ day.entry_time || '-' }}</span>
                                                <i v-if="day.late_minutes && !day.late_ignored"
                                                    class="pi pi-exclamation-circle text-orange-500"></i>
                                            </td>
                                            <td class="py-2">{{ day.exit_time || '-' }}</td>
                                            <td class="py-2">{{ day.break_time }}</td>
                                            <td class="py-2">{{ day.extra_time }}</td>
                                            <td class="py-2">{{ day.total_hours }}</td>
                                        </template>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Firma de conformidad -->
                    <div class="mt-1 pt-4 text-center">
                        <p class="text-xs text-gray-600 dark:text-gray-400">He revisado mis registros de asistencia para
                            este periodo y
                            estoy de acuerdo con las incidencias reportadas.</p>
                        <div class="mt-8 border-t border-gray-400 w-64 mx-auto">
                            <p class="text-sm font-semibold pt-1">Firma de conformidad</p>
                        </div>
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

    #print-container,
    #print-container * {
        visibility: visible;
    }

    #print-container {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        margin: 0;
        padding: 1rem;
    }
}
</style>
