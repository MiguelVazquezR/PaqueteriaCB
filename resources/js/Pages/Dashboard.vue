<script setup>
import { ref, onMounted, watch } from 'vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { format } from 'date-fns';
import es from 'date-fns/locale/es';

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
            <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-200 mb-6">¡Bienvenida {{ user.name.split(' ')[0] }}!</h1>

            <!-- KPIs -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md flex items-center gap-4">
                    <i class="pi pi-users text-3xl text-primary-500"></i>
                    <div>
                        <p class="text-gray-500">Empleados activos</p>
                        <p class="text-3xl font-bold">{{ stats.active_employees }}</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md flex items-center gap-4">
                    <i class="pi pi-calendar-check text-3xl text-green-500"></i>
                    <div>
                        <p class="text-gray-500">Asistencia hoy</p>
                        <p class="text-3xl font-bold">{{ stats.attendance_today.percentage }}%</p>
                        <p class="text-sm text-gray-400">{{ stats.attendance_today.count }} de {{ stats.attendance_today.total }} empleados</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md flex items-center gap-4">
                    <i class="pi pi-stopwatch text-3xl text-orange-500"></i>
                    <div>
                        <p class="text-gray-500">Puntualidad del mes</p>
                        <p class="text-3xl font-bold">{{ stats.punctuality_month.percentage }}%</p>
                         <p class="text-sm text-gray-400">{{ stats.punctuality_month.count }} de {{ stats.punctuality_month.total }} empleados</p>
                    </div>
                </div>
            </div>

            <!-- Análisis de Asistencia -->
            <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md mb-8">
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
                <div v-if="upcomingBirthdays && upcomingBirthdays.length > 0" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <div v-for="employee in upcomingBirthdays" :key="employee.id" class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow-md text-center">
                        <Avatar :image="employee.user?.profile_photo_url" :label="employee.first_name[0]" shape="circle" size="xlarge" class="mb-3" />
                        <p class="font-bold">{{ employee.first_name }} {{ employee.last_name }}</p>
                        <p class="font-semibold text-primary-500">{{ formatDate(employee.birth_date) }}</p>
                        <p class="text-sm text-gray-500">{{ employee.branch.name }}</p>
                    </div>
                </div>
                <div v-else class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md text-center">
                    <p class="text-gray-500">No hay cumpleaños en los próximos 7 días. 🎉</p>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
