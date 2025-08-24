<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PrimeIcons } from '@primevue/core/api';
import { format } from 'date-fns';
import es from 'date-fns/locale/es';

// --- Props ---
const props = defineProps({
    branch: Object,
    schedule: Object,
});

// --- Refs and State ---
const home = ref({ icon: 'pi pi-home', url: route('dashboard') });
const items = ref([
    { label: 'Sucursales', url: route('branches.index'), icon: PrimeIcons.BUILDING },
    { label: 'Detalles de la sucursal' }
]);

// --- Computed Properties ---
const formatDate = (dateString) => {
    if (!dateString) return '-';
    try {
        const date = new Date(dateString);
        return format(date, "d 'de' MMMM 'de' yyyy", { locale: es });
    } catch (e) {
        return '-';
    }
};

const formatTime = (timeString) => {
    if (!timeString) return 'Cerrado';
    const [hours, minutes] = timeString.split(':');
    const date = new Date();
    date.setHours(hours, minutes);
    return format(date, 'hh:mm a');
};

const scheduleGrouped = computed(() => {
    const details = props.schedule?.details || [];
    if (!details.length) return [];

    const grouped = {
        'Lunes a viernes': { days: [], start: '', end: '' },
        'Sábado': { days: [], start: '', end: '' },
        'Domingo': { days: [], start: '', end: '' },
    };

    details.forEach(detail => {
        if (detail.day_of_week >= 1 && detail.day_of_week <= 5) {
            grouped['Lunes a viernes'].days.push(detail.day_of_week);
            grouped['Lunes a viernes'].start = formatTime(detail.start_time);
            grouped['Lunes a viernes'].end = formatTime(detail.end_time);
        } else if (detail.day_of_week === 6) {
             grouped['Sábado'].start = formatTime(detail.start_time);
             grouped['Sábado'].end = formatTime(detail.end_time);
        }
    });
    
    // Si Domingo no tiene horario, se marca como cerrado
    if (!grouped['Domingo'].start) {
        grouped['Domingo'].start = 'Cerrado';
    }

    return grouped;
});


</script>

<template>
    <Head :title="`Detalles de ${branch.name}`" />

    <AppLayout>
        <Breadcrumb :home="home" :model="items" class="!bg-transparent" />
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <!-- === HEADER === -->
            <div class="flex flex-col sm:flex-row justify-between items-start mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-black dark:text-gray-100">{{ branch.name }}</h1>
                    <p class="text-black dark:text-gray-400">{{ branch.address }}</p>
                    <p class="text-[#3f3f3f] dark:text-gray-400">{{ branch.phone || '-' }}</p>
                </div>
                <Link :href="route('branches.edit', branch.id)" class="mt-4 sm:mt-0">
                    <Button label="Editar" icon="pi pi-pencil" outlined />
                </Link>
            </div>

            <!-- === GRID DE INFORMACIÓN === -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Columna Izquierda -->
                <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg p-2">
                    <div
                        class="font-semibold flex items-center space-x-3 text-base bg-[#f8f8f8] rounded-[7px] text-[#3f3f3f] dark:text-gray-200 mb-2 px-2 py-px">
                        <i class="pi pi-building"></i>
                        <span>Información general</span>
                    </div>
                    <div class="space-y-3 text-sm px-3">
                        <div class="flex justify-between border-b pb-2"><span class="text-gray-500">ID</span><span class="font-medium text-gray-700 dark:text-gray-300">{{ branch.id }}</span></div>
                        <div class="flex justify-between border-b pb-2"><span class="text-gray-500">Fecha de creación</span><span class="font-medium text-gray-700 dark:text-gray-300">{{ formatDate(branch.created_at) }}</span></div>
                        <div class="flex justify-between border-b pb-2"><span class="text-gray-500">Creado por</span><span class="font-medium text-gray-700 dark:text-gray-300">Cristina Olvera</span></div>
                        <div class="flex justify-between border-b pb-2"><span class="text-gray-500">Nombre de la sucursal</span><span class="font-medium text-gray-700 dark:text-gray-300">{{ branch.name }}</span></div>
                        <div class="flex justify-between border-b pb-2"><span class="text-gray-500">Teléfono</span><span class="font-medium text-gray-700 dark:text-gray-300">{{ branch.phone || '-' }}</span></div>
                        <div class="flex justify-between border-b pb-2"><span class="text-gray-500">Dirección</span><span class="font-medium text-gray-700 dark:text-gray-300 text-right">{{ branch.address }}</span></div>
                        <Link :href="route('users.index', { branch: branch.id })" class="flex justify-between items-center pt-2 text-primary-600 hover:underline">
                            <span class="font-medium">Empleados asignados</span>
                            <div class="flex items-center">
                                <span class="font-bold mr-2">{{ branch.employees_count }} empleados</span>
                                <i class="pi pi-arrow-right"></i>
                            </div>
                        </Link>
                    </div>
                </div>

                <!-- Columna Derecha -->
                <div class="space-y-6">
                    <!-- Card: Configuración General -->
                    <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg p-2">
                        <div
                            class="font-semibold flex items-center space-x-3 text-base bg-[#f8f8f8] rounded-[7px] text-[#3f3f3f] dark:text-gray-200 mb-2 px-2 py-px">
                            <i class="pi pi-sliders-h"></i>
                            <span>Configuración general</span>
                        </div>
                        <div class="flex justify-between text-sm px-3 mt-3"><span class="text-gray-500">Zona horaria</span><span class="font-medium text-gray-700 dark:text-gray-300">{{ branch.settings?.timezone || '-' }}</span></div>
                    </div>

                    <!-- Card: Horario de Operación -->
                    <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg p-2">
                        <div
                            class="font-semibold flex items-center space-x-3 text-base bg-[#f8f8f8] rounded-[7px] text-[#3f3f3f] dark:text-gray-200 mb-2 px-2 py-px">
                            <i class="pi pi-calendar"></i>
                            <span>Horario de operación</span>
                        </div>
                        <div class="space-y-3 text-sm px-3">
                            <div class="flex justify-between"><span class="text-gray-500">Lunes a viernes</span><span class="font-medium text-gray-700 dark:text-gray-300">{{ scheduleGrouped['Lunes a viernes'].start === 'Cerrado' ? 'Cerrado' : `${scheduleGrouped['Lunes a viernes'].start} - ${scheduleGrouped['Lunes a viernes'].end}` }}</span></div>
                            <div class="flex justify-between"><span class="text-gray-500">Sábado</span><span class="font-medium text-gray-700 dark:text-gray-300">{{ scheduleGrouped['Sábado'].start === 'Cerrado' ? 'Cerrado' : `${scheduleGrouped['Sábado'].start} - ${scheduleGrouped['Sábado'].end}` }}</span></div>
                            <div class="flex justify-between"><span class="text-gray-500">Domingo</span><span class="font-medium text-gray-700 dark:text-gray-300">{{ scheduleGrouped['Domingo'].start }}</span></div>
                        </div>
                    </div>

                    <!-- Card: Horarios de Personal -->
                    <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg p-2">
                         <div
                            class="font-semibold flex items-center space-x-3 text-base bg-[#f8f8f8] rounded-[7px] text-[#3f3f3f] dark:text-gray-200 mb-2 px-2 py-px">
                            <i class="pi pi-users"></i>
                            <span>Horarios de personal asociados</span>
                        </div>
                        <div class="space-y-3 text-sm px-3">
                            <div class="flex justify-between items-center"><span class="text-gray-500">Turno completo</span><div class="flex items-center"><span class="font-medium text-gray-700 dark:text-gray-300 mr-4">40 hrs totales</span><i class="pi pi-book cursor-pointer"></i></div></div>
                            <div class="flex justify-between items-center"><span class="text-gray-500">Medio día 1</span><div class="flex items-center"><span class="font-medium text-gray-700 dark:text-gray-300 mr-4">26 hrs totales</span><i class="pi pi-book cursor-pointer"></i></div></div>
                            <div class="flex justify-between items-center"><span class="text-gray-500">Medio día 2</span><div class="flex items-center"><span class="font-medium text-gray-700 dark:text-gray-300 mr-4">20 hrs totales</span><i class="pi pi-book cursor-pointer"></i></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
