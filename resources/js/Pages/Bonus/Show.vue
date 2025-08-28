<script setup>
import { ref } from 'vue'; // Importar ref
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { format } from 'date-fns';
import es from 'date-fns/locale/es';

const props = defineProps({ period: String, reportData: Object });

// --- Refs and State for Breadcrumb ---
const home = ref({ icon: 'pi pi-home', url: route('dashboard') });
const items = ref([
    { label: 'Bonos', url: route('bonuses.index') },
    { label: `Reporte Mes ${format(new Date(props.period), 'MM')}` }
]);

// --- Methods ---
const formatDate = (dateString, formatStr) => {
    // Agregamos una comprobación para evitar errores con fechas inválidas
    const date = new Date(dateString);
    if (isNaN(date)) return '-';
    return format(date, formatStr, { locale: es });
};

const printReport = () => {
    window.print();
};

</script>

<template>
    <Head title="Reporte de Bonos" />
    <AppLayout>
        <Breadcrumb :home="home" :model="items" class="!bg-transparent print:hidden" />
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg p-6" id="print-container">
                <!-- HEADER -->
                <div class="flex justify-between items-start pb-4 border-b">
                    <div>
                        <h1 class="text-2xl font-bold">Reporte bonos</h1>
                        <p class="text-gray-500">Periodo: {{ formatDate(period, "dd MMMM yyyy") }} - {{ formatDate(new Date(period).setMonth(new Date(period).getMonth() + 1) - 1, "dd MMMM yyyy") }}</p>
                    </div>
                    <div class="text-right">
                        <p class="font-bold">Paquetería Casa Blanca</p>
                        <p class="text-sm text-gray-500">Generado {{ formatDate(new Date(), "dd MMMM yyyy, hh:mm a") }}</p>
                    </div>
                </div>
                <!-- ✨ Botón de Imprimir ✨ -->
                <div class="flex justify-end mt-4 print:hidden">
                    <Button label="Imprimir o guardar en PDF" icon="pi pi-print" @click="printReport" />
                </div>

                <!-- TABLAS POR SUCURSAL -->
                <div v-for="(employees, branchName) in reportData" :key="branchName" class="mt-8">
                    <div class="bg-gray-100 p-3 rounded-t-lg">
                        <h2 class="font-bold flex items-center gap-2"><i class="pi pi-building"></i><span>Sucursal {{ branchName }}</span></h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-gray-50 text-xs uppercase">
                                <tr>
                                    <th class="px-4 py-3">N° empleado</th>
                                    <th class="px-4 py-3">Colaborador</th>
                                    <th class="px-4 py-3">Puntualidad</th>
                                    <th class="px-4 py-3">Asistencia</th>
                                    <th class="px-4 py-3">Retardos</th>
                                    <th class="px-4 py-3">Faltas inj.</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="employee in employees" :key="employee.id" class="border-b">
                                    <td class="px-4 py-3 font-medium">{{ employee.employee_number }}</td>
                                    <td class="px-4 py-3">{{ employee.name }}</td>
                                    <td class="px-4 py-3"><i :class="['pi', employee.punctuality_bonus ? 'pi-check-circle text-green-500' : 'pi-times-circle text-red-500']"></i></td>
                                    <td class="px-4 py-3"><i :class="['pi', employee.attendance_bonus ? 'pi-check-circle text-green-500' : 'pi-times-circle text-red-500']"></i></td>
                                    <td class="px-4 py-3">{{ employee.late_minutes }} min</td>
                                    <td class="px-4 py-3">{{ employee.unjustified_absences }}</td>
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
    #print-container, #print-container * {
        visibility: visible;
    }
    #print-container {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        margin: 0;
        padding: 1rem;
        box-shadow: none;
        border: none;
    }
}
</style>
