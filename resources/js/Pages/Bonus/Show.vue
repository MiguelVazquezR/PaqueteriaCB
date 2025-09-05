<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { format } from 'date-fns';
import es from 'date-fns/locale/es';
import { useConfirm } from "primevue/useconfirm";
import { PrimeIcons } from '@primevue/core/api';

const props = defineProps({
    report: Object,
    employeeBonuses: Array, // Recibimos la nueva prop con los datos procesados
});
const confirm = useConfirm();

const pageTitle = computed(() => {
    const date = new Date(props.report.period);
    return `Reporte de Bonos - ${format(date, 'MMMM yyyy', { locale: es })}`;
});

const home = ref({ icon: 'pi pi-home', url: route('dashboard') });
const items = ref([
    { label: 'Bonos', url: route('bonuses.index'), icon: PrimeIcons.WALLET },
    { label: `Detalles - ${format(new Date(props.report.period), 'MMMM yyyy', { locale: es })}` }
]);

const periodFormatted = computed(() => {
    const date = new Date(props.report.period);
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
});

const finalizeReport = () => {
    confirm.require({
        message: '¿Estás seguro de que quieres finalizar este reporte? Una vez finalizado, los cálculos no podrán ser modificados.',
        header: 'Confirmación de Finalización',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Sí, finalizar',
        rejectLabel: 'Cancelar',
        accept: () => {
            router.post(route('bonuses.finalize', periodFormatted.value));
        }
    });
};

const recalculateReport = () => {
    confirm.require({
        message: 'Esta acción volverá a calcular todos los bonos para este periodo usando los datos más recientes de asistencias e incidencias. ¿Deseas continuar?',
        header: 'Confirmación de Recálculo',
        icon: 'pi pi-refresh',
        acceptLabel: 'Sí, recalcular',
        rejectLabel: 'Cancelar',
        accept: () => {
            router.post(route('bonuses.recalculate', periodFormatted.value), {}, {
                preserveScroll: true,
            });
        }
    });
};

// Modelo de datos para el SplitButton
const finalizeOptions = ref([
    {
        label: 'Recalcular Borrador',
        icon: 'pi pi-refresh',
        command: () => {
            recalculateReport();
        }
    }
]);
</script>

<template>

    <Head :title="pageTitle" />
    <AppLayout>
        <Breadcrumb :home="home" :model="items" class="!bg-transparent" />
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg">
                <div class="p-6 border-b flex flex-col sm:flex-row justify-between items-start">
                    <div>
                        <h1 class="text-2xl font-bold capitalize">{{ pageTitle }}</h1>
                        <div class="flex items-center gap-2 mt-2">
                            <span class="font-semibold">Estatus:</span>
                            <Tag v-if="report.status === 'draft'" value="Borrador" severity="warn" />
                            <Tag v-if="report.status === 'finalized'" value="Finalizado" severity="success" />
                        </div>
                    </div>
                    <div class="flex items-center gap-2 mt-4 sm:mt-0">
                        <Link :href="route('bonuses.print', periodFormatted)" target="_blank">
                        <Button label="Imprimir Reporte" icon="pi pi-print" outlined />
                        </Link>
                        <SplitButton v-if="report.status === 'draft'" label="Finalizar y Aprobar"
                            icon="pi pi-check-circle" @click="finalizeReport" :model="finalizeOptions" />
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <!-- --- CAMBIO: --- La tabla ahora muestra a todos los empleados y sus resultados -->
                    <DataTable :value="employeeBonuses" dataKey="id">
                        <Column field="employee_number" header="N° Empleado"></Column>
                        <Column field="name" header="Empleado"></Column>
                        <Column header="Puntualidad" bodyClass="text-center">
                            <template #body="{ data }">
                                <i v-if="data.punctuality_earned" class="pi pi-check-circle text-green-500 text-lg"></i>
                                <i v-else class="pi pi-times-circle text-red-500 text-lg"></i>
                            </template>
                        </Column>
                        <Column header="Asistencia" bodyClass="text-center">
                            <template #body="{ data }">
                                <i v-if="data.attendance_earned" class="pi pi-check-circle text-green-500 text-lg"></i>
                                <i v-else class="pi pi-times-circle text-red-500 text-lg"></i>
                            </template>
                        </Column>
                        <Column field="late_minutes" header="Minutos Retardo"></Column>
                        <Column field="unjustified_absences" header="Faltas Injust."></Column>
                        <template #empty>
                            <div class="text-center p-8">
                                <p class="text-gray-500">No hay empleados activos para este periodo.</p>
                            </div>
                        </template>
                    </DataTable>
                </div>
            </div>
        </div>
    </AppLayout>
</template>