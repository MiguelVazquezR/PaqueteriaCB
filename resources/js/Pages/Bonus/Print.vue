<script setup>
import { Head } from '@inertiajs/vue3';
import { format, startOfMonth, endOfMonth } from 'date-fns';
import es from 'date-fns/locale/es';
import Button from 'primevue/button'; // Se asume que importas Button globalmente o aquí.

const props = defineProps({
    report: Object,
    branches: Object,
});

const periodFormatted = (date) => {
    // Se añade un parseo para asegurar que la fecha sea un objeto Date válido
    const periodDate = new Date(date); // Asumir zona horaria local
    return `Periodo: ${format(startOfMonth(periodDate), 'dd MMMM', { locale: es })} al ${format(endOfMonth(periodDate), 'dd MMMM yyyy', { locale: es })}`;
};

const generatedAtFormatted = (date) => {
    return `Generado ${format(new Date(date), 'dd MMMM yyyy, hh:mm a', { locale: es })}`;
};

const printReport = () => window.print();
const closeWindow = () => window.history.back();

</script>

<template>
    <Head title="Imprimir Reporte de Bonos" />
    <div class="bg-gray-100 p-4 sm:p-8 font-sans">
        <div class="max-w-4xl mx-auto bg-white p-6 rounded-lg shadow-sm">
            <div class="flex justify-between items-start border-b pb-4 mb-6">
                <div>
                    <p class="text-sm text-gray-500">Bonos › Mes {{ format(new Date(report.period), 'MM') }}</p>
                    <h1 class="text-2xl font-bold mt-1">Reporte bonos</h1>
                    <p class="text-gray-600">{{ periodFormatted(report.period) }}</p>
                </div>
                <div class="text-right">
                    <div class="flex items-center gap-2 print-hide">
                        <Button label="Cerrar" icon="pi pi-times" severity="secondary" outlined @click="closeWindow"/>
                        <Button label="Imprimir o guardar en PDF" icon="pi pi-print" @click="printReport"/>
                    </div>
                    <div class="flex items-center justify-end gap-2 mt-2">
                        <span class="font-bold text-sm">Paquetería Casa Blanca</span>
                    </div>
                    <p class="text-sm text-gray-500">{{ generatedAtFormatted(report.created_at) }}</p>
                </div>
            </div>

            <!-- Content -->
            <div class="space-y-8">
                <div v-for="(employees, branchName) in branches" :key="branchName">
                    <div class="flex items-center gap-2 mb-3">
                        <i class="pi pi-building"></i>
                        <h2 class="text-lg font-semibold">Sucursal {{ branchName }}</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="border-b">
                                <tr>
                                    <th class="py-2 px-3 text-left font-semibold text-gray-600">N° empleado</th>
                                    <th class="py-2 px-3 text-left font-semibold text-gray-600">Colaborador</th>
                                    <th class="py-2 px-3 text-center font-semibold text-gray-600">Puntualidad</th>
                                    <th class="py-2 px-3 text-center font-semibold text-gray-600">Asistencia</th>
                                    <th class="py-2 px-3 text-left font-semibold text-gray-600">Retardos</th>
                                    <th class="py-2 px-3 text-left font-semibold text-gray-600">Faltas inj.</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="employee in employees" :key="employee.employee_id" class="border-b">
                                    <td class="py-3 px-3">{{ employee.employee_number }}</td>
                                    <td class="py-3 px-3">{{ employee.employee_name }}</td>
                                    <td class="py-3 px-3 text-center">
                                        <i v-if="employee.punctuality_earned" class="pi pi-check-circle text-green-500 text-lg"></i>
                                        <i v-else class="pi pi-times-circle text-red-500 text-lg"></i>
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        <i v-if="employee.attendance_earned" class="pi pi-check-circle text-green-500 text-lg"></i>
                                        <i v-else class="pi pi-times-circle text-red-500 text-lg"></i>
                                    </td>
                                    <td class="py-3 px-3">{{ employee.total_late_minutes }} min</td>
                                    <td class="py-3 px-3">{{ employee.total_unjustified_absences }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
@media print {
    .print-hide {
        display: none !important;
    }
    body {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
</style>
