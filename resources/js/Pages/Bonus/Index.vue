<script setup>
import { computed } from 'vue'; // Se importa 'computed' para procesar los datos
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
// --- CAMBIO: --- Se importan más funciones de date-fns para cálculos precisos
import { format, startOfMonth, endOfMonth, isBefore, startOfToday } from 'date-fns';
import es from 'date-fns/locale/es';

const props = defineProps({ periods: Object });

// --- CAMBIO: --- Se crea una propiedad computada para transformar los datos antes de mostrarlos
const processedPeriods = computed(() => {
    const today = startOfToday(); // Obtenemos el inicio del día de hoy para una comparación justa

    return props.periods.data.map(p => {
        const periodDate = new Date(p.period);
        // Nos aseguramos de obtener la fecha de fin de mes correcta, sin importar el día que llegue
        const periodEndDate = endOfMonth(periodDate);

        // Comprobamos si la fecha de fin del periodo es anterior a hoy
        const isClosed = isBefore(periodEndDate, today);

        return {
            ...p, // Mantenemos los datos originales del periodo
            // Creamos las propiedades de visualización que usaremos en la tabla
            displayPeriod: `${format(startOfMonth(periodDate), 'dd MMMM yyyy', { locale: es })} - ${format(periodEndDate, 'dd MMMM yyyy', { locale: es })}`,
            displayMonth: format(periodDate, 'MMMM', { locale: es }),
            status: {
                label: isClosed ? 'Cerrado' : 'Abierto',
                severity: isClosed ? 'danger' : 'success'
            }
        };
    });
});

// Este método no necesita cambios, ya que usa el 'period' original del backend
const onRowSelect = (event) => {
    const period_date = new Date(event.data.period);
    const formatted_period = `${period_date.getFullYear()}-${String(period_date.getMonth() + 1).padStart(2, '0')}`;
    router.get(route('bonuses.show', formatted_period));
};

// Esta función ya no es necesaria directamente en el template, pero la dejamos por si se usa en otro lado
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
                    <!-- --- CAMBIO: --- La tabla ahora itera sobre 'processedPeriods' -->
                    <DataTable :value="processedPeriods" @row-select="onRowSelect" selectionMode="single" dataKey="period"
                        class="cursor-pointer">
                        
                        <!-- --- CAMBIO: --- Muestra el nombre completo y capitalizado del mes -->
                        <Column field="period" header="Mes">
                            <template #body="{ data }"><span class="capitalize">{{ data.displayMonth }}</span></template>
                        </Column>

                        <!-- --- CAMBIO: --- Muestra el rango de fechas correcto -->
                        <Column header="Periodo">
                            <template #body="{ data }">{{ data.displayPeriod }}</template>
                        </Column>

                        <!-- --- CAMBIO: --- Muestra el estatus dinámico (Abierto/Cerrado) -->
                        <Column header="Estatus">
                            <template #body="{ data }">
                                <Tag :value="data.status.label" :severity="data.status.severity" />
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