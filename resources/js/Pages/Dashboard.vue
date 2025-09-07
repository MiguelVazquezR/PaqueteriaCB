<script setup>
import { ref, onMounted, watch } from 'vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { format } from 'date-fns';
import es from 'date-fns/locale/es';
import FingerPrintIcon from '@/Components/Icons/FingerPrintIcon.vue';

const props = defineProps({
    stats: Object,
    attendanceByBranch: Array,
    absenteeismTrend: Object,
    upcomingBirthdays: Array,
    filters: Object,
});

const user = usePage().props.auth.user;

// --- Refs para los datos y opciones de las gráficas ---
const barData = ref(null);
const barOptions = ref(null);
const lineData = ref(null);
const lineOptions = ref(null);

const rangeOptions = ref([
    { label: 'Últimos 7 días', value: 7 },
    { label: 'Últimos 30 días', value: 30 }
]);
const selectedRange = ref(props.filters.range || 7);

watch(selectedRange, (newValue) => {
    router.get(route('dashboard'), { range: newValue }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
});

// Función para inicializar o actualizar las gráficas
const setupCharts = () => {
    const documentStyle = getComputedStyle(document.documentElement);
    const textColorSecondary = documentStyle.getPropertyValue('--p-text-muted-color');
    const surfaceBorder = documentStyle.getPropertyValue('--p-surface-border');

    // Datos y opciones para la gráfica de barras
    barData.value = {
        labels: props.attendanceByBranch.map(b => b.name),
        datasets: [{
            data: props.attendanceByBranch.map(b => b.attendance_count),
            backgroundColor: ['#EC4899', '#7C3AED', '#3B82F6', '#10B981', '#F59E0B', '#EF4444'],
            borderRadius: 8,
        }]
    };
    barOptions.value = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: {
                ticks: { color: textColorSecondary },
                grid: { color: surfaceBorder }
            },
            x: {
                ticks: { color: textColorSecondary },
                grid: { display: false }
            }
        }
    };

    // Datos y opciones para la gráfica de línea
    lineData.value = {
        labels: props.absenteeismTrend.labels,
        datasets: [{
            data: props.absenteeismTrend.data,
            borderColor: documentStyle.getPropertyValue('--p-primary-500'),
            tension: 0.4
        }]
    };
    lineOptions.value = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: {
                ticks: { color: textColorSecondary },
                grid: { color: surfaceBorder }
            },
            x: {
                ticks: { color: textColorSecondary },
                grid: { color: surfaceBorder }
            }
        }
    };
};

watch(() => [props.attendanceByBranch, props.absenteeismTrend], () => {
    setupCharts();
}, { deep: true });


onMounted(() => {
    setupCharts();
});

const formatDate = (dateString) => {
    return format(new Date(dateString), "dd 'de' MMMM", { locale: es });
};
</script>

<template>

    <Head title="Dashboard" />

    <AppLayout>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Saludo -->
            <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-200 mb-6">¡Hola {{ user.name.split(' ')[0] }}!
            </h1>

            <!-- KPIs -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white dark:bg-neutral-900 p-6 rounded-lg shadow-md flex items-start gap-4">
                    <svg width="40" height="34" viewBox="0 0 29 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M14.5542 9.63086C16.4699 9.63088 18.307 10.3925 19.6616 11.7471C21.0162 13.1017 21.7777 14.9388 21.7778 16.8545C21.7778 18.1876 7.33057 18.3174 7.33057 16.8545C7.33065 14.9388 8.09219 13.1017 9.44678 11.7471C10.8014 10.3925 12.6385 9.63086 14.5542 9.63086ZM20.5483 10.9121C25.0565 9.22179 29.0005 11.6296 29.0005 16.8037C28.9999 17.9999 23.0813 17.7348 23.0073 16.8037C22.7933 14.0767 22.2262 12.7726 20.5483 10.9121ZM0.0561523 16.8037C-0.609849 11.4758 4.92307 9.06782 8.40674 10.9121C6.68048 12.7846 6.15089 14.0732 5.99854 16.8037C5.94926 17.6736 0.144424 17.5 0.0561523 16.8037ZM5.79346 2.45898C7.71731 2.45898 9.27666 4.01857 9.27686 5.94238C9.27686 7.86636 7.71743 9.42676 5.79346 9.42676C3.86954 9.42669 2.31006 7.86632 2.31006 5.94238C2.31025 4.01861 3.86966 2.45905 5.79346 2.45898ZM23.2114 2.45898C25.1353 2.45898 26.6956 4.01857 26.6958 5.94238C26.6958 7.86636 25.1354 9.42676 23.2114 9.42676C21.2876 9.42658 19.728 7.86625 19.728 5.94238C19.7282 4.01868 21.2877 2.45916 23.2114 2.45898ZM14.5024 0C16.7659 0 18.6011 1.83513 18.6011 4.09863C18.601 6.36204 16.7659 8.19727 14.5024 8.19727C12.2391 8.1972 10.4039 6.362 10.4038 4.09863C10.4038 1.83517 12.239 6.9063e-05 14.5024 0Z"
                            fill="currentColor" />
                    </svg>
                    <div>
                        <p class="text-gray-500">Empleados activos</p>
                        <p class="text-3xl font-bold">{{ stats.active_employees }}</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-neutral-900 p-6 rounded-lg shadow-md flex items-start gap-4">
                    <FingerPrintIcon class="size-10 text-black dark:text-white" />
                    <div>
                        <p class="text-gray-500">Asistencia hoy</p>
                        <p class="text-3xl font-bold">{{ stats.attendance_today.percentage }}%</p>
                        <p class="text-sm text-gray-400">{{ stats.attendance_today.count }} de {{
                            stats.attendance_today.total }} empleados</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-neutral-900 p-6 rounded-lg shadow-md flex items-start gap-4">
                    <i class="pi pi-stopwatch" style="font-size: 30px;"></i>
                    <div>
                        <p class="text-gray-500">Puntualidad del mes</p>
                        <p class="text-3xl font-bold">{{ stats.punctuality_month.percentage }}%</p>
                        <p class="text-sm text-gray-400">{{ stats.punctuality_month.count }} de {{
                            stats.punctuality_month.total }} empleados</p>
                    </div>
                </div>
            </div>

            <!-- Análisis de Asistencia -->
            <div class="bg-white dark:bg-neutral-900 p-6 rounded-lg shadow-md mb-8">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold">Análisis de asistencia</h2>
                    <Select v-model="selectedRange" :options="rangeOptions" optionLabel="label" optionValue="value" />
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div>
                        <h3 class="font-semibold mb-2">Asistencia por sucursal</h3>
                        <Chart type="bar" :data="barData" :options="barOptions" class="h-80" />
                    </div>
                    <div class="border p-4 rounded-lg">
                        <h3 class="font-semibold mb-2">Tendencia de ausentismo</h3>
                        <Chart type="line" :data="lineData" :options="lineOptions" class="h-80" />
                    </div>
                </div>
            </div>

            <!-- Cumpleaños -->
            <div>
                <h2 class="text-xl font-bold mb-4">Cumpleaños del personal</h2>
                <div v-if="upcomingBirthdays && upcomingBirthdays.length > 0"
                    class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <div v-for="employee in upcomingBirthdays" :key="employee.id"
                        class="bg-white dark:bg-neutral-900 p-4 rounded-lg shadow-md text-center">
                        <Avatar :image="employee.user?.profile_photo_url" :label="employee.first_name[0]" shape="circle"
                            size="xlarge" class="mb-3" />
                        <p class="font-bold">{{ employee.first_name }} {{ employee.last_name }}</p>
                        <p class="font-semibold text-primary-500">{{ formatDate(employee.birth_date) }}</p>
                        <p class="text-sm text-gray-500">{{ employee.branch.name }}</p>
                    </div>
                </div>
                <div v-else class="bg-white dark:bg-neutral-900 p-6 rounded-lg shadow-md text-center">
                    <p class="text-gray-500">No hay cumpleaños en los próximos 7 días. 🎉</p>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
