<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { format } from 'date-fns';
import es from 'date-fns/locale/es';

const props = defineProps({
    period: Object,
    employeesData: Array,
});

const home = ref({ icon: 'pi pi-home', url: route('dashboard') });
const items = ref([
    { label: 'Incidencias', url: route('incidents.index') },
    { label: `Semana ${props.period.week_number}`, url: route('incidents.show', props.period.id) },
    { label: 'Imprimir incidencias' }
]);

// --- CAMBIO: --- Se mejora la función para evitar problemas de zona horaria.
const formatDate = (dateString, formatStr = "EEEE, dd 'de' MMMM") => {
    if (!dateString) return '';
    const date = new Date(dateString);
    // Se suma el offset del timezone para que la fecha se mantenga consistente
    return format(new Date(date.valueOf() + date.getTimezoneOffset() * 60000), formatStr, { locale: es });
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
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 print:py-3">
            <div class="flex justify-end mb-4 print:hidden">
                <Button label="Imprimir" icon="pi pi-print" @click="print" />
            </div>

            <div class="space-y-4 print:space-y-2" id="print-container">
                <div v-for="employee in employeesData" :key="employee.id"
                    class="bg-white dark:bg-gray-800 shadow-md rounded-lg px-6 py-3 print:py-1 print:shadow-none print:border print:rounded-none print:flex print:flex-col print:justify-between print:break-inside-avoid">
                    <div>
                        <!-- ... (Cabecera del empleado sin cambios) ... -->
                        <div class="overflow-x-auto mt-1">
                            <table class="w-full text-sm text-left">
                                <!-- ... (thead sin cambios) ... -->
                                <tbody>
                                    <tr v-for="day in employee.daily_data" :key="day.date"
                                        class="border-b dark:border-gray-700">
                                        <td class="py-2 print:py-[3px] font-medium">
                                            <!-- --- CAMBIO: --- Se añaden íconos para festivos y descansos laborados. -->
                                            <div class="flex items-center gap-2">
                                                <span>{{ formatDate(day.date, "EEE, dd MMM yyyy") }}</span>
                                                <i v-if="day.holiday_name && day.entry_time"
                                                    v-tooltip.top="`Festivo laborado: ${day.holiday_name}`"
                                                    class="pi pi-star-fill text-yellow-500"></i>
                                                <i v-if="day.is_rest_day && day.entry_time"
                                                    v-tooltip.top="`Descanso laborado`"
                                                    class="pi pi-briefcase text-blue-500"></i>
                                            </div>
                                        </td>

                                        <!-- --- CAMBIO: --- Se implementa la misma lógica de visualización que en Show.vue --- -->
                                        <!-- 1. Festivo DESCANSADO -->
                                        <template v-if="day.holiday_name && !day.entry_time">
                                            <td colspan="5" class="py-2 print:py-[3px]">
                                                <Tag :value="day.holiday_name" severity="success"
                                                    class="w-full text-center" />
                                            </td>
                                        </template>

                                        <!-- 2. Descanso programado (y no trabajado) -->
                                        <template v-else-if="day.is_rest_day && !day.entry_time && !day.incident">
                                            <td colspan="5" class="py-2 print:py-[3px]">
                                                <Tag value="Descanso" severity="success" class="w-full text-center" />
                                            </td>
                                        </template>

                                        <!-- 3. Otra incidencia (vacaciones, incapacidad, etc.) -->
                                        <template v-else-if="day.incident">
                                            <td colspan="5" class="py-2 print:py-[3px]">
                                                <Tag :value="day.incident" :severity="getIncidentSeverity(day.incident)"
                                                    class="w-full text-center" />
                                            </td>
                                        </template>

                                        <!-- 4. Falta Injustificada (auto-detectada) -->
                                        <template v-else-if="day.is_unjustified_absence">
                                            <td colspan="5" class="py-2 print:py-[3px]">
                                                <Tag value="Falta Injustificada" severity="danger"
                                                    class="w-full text-center" />
                                            </td>
                                        </template>

                                        <!-- 5. Día normal O festivo/descanso TRABAJADO -->
                                        <template v-else>
                                            <td class="py-2 print:py-[3px] flex items-center gap-1">
                                                <span>{{ day.entry_time || '-' }}</span>
                                                <i v-if="day.late_minutes && !day.late_ignored"
                                                    class="pi pi-exclamation-circle text-orange-500"></i>
                                            </td>
                                            <td class="py-2 print:py-[3px]">{{ day.exit_time || '-' }}</td>
                                            <td class="py-2 print:py-[3px]">{{ day.break_time }}</td>
                                            <td class="py-2 print:py-[3px]">{{ day.extra_time }}</td>
                                            <td class="py-2 print:py-[3px]">{{ day.total_hours }}</td>
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
