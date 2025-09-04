<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PrimeIcons } from '@primevue/core/api';
import { format } from 'date-fns';
import es from 'date-fns/locale/es';

// --- CAMBIO: --- La prop 'schedule' se elimina, toda la info viene en 'branch'.
const props = defineProps({
    branch: Object,
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
    date.setHours(parseInt(hours), parseInt(minutes));
    return format(date, 'hh:mm a');
};

// --- CAMBIO: --- Propiedad computada para leer el horario desde 'business_hours'.
const businessHours = computed(() => {
    if (!props.branch.business_hours) return [];
    // Convertimos el objeto a un array para poder iterarlo fácilmente en el template.
    return Object.values(props.branch.business_hours);
});
</script>

<template>
    <Head :title="`Detalles de ${branch.name}`" />

    <AppLayout>
        <Breadcrumb :home="home" :model="items" class="!bg-transparent" />
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

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

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Columna Izquierda -->
                <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg p-2">
                    <div class="font-semibold flex items-center space-x-3 text-base bg-[#f8f8f8] rounded-[7px] text-[#3f3f3f] dark:text-gray-200 mb-2 px-2 py-px">
                        <i class="pi pi-building"></i>
                        <span>Información general</span>
                    </div>
                    <div class="space-y-3 text-sm px-3">
                        <div class="flex justify-between border-b pb-2"><span class="text-gray-500">ID</span><span class="font-medium text-gray-700 dark:text-gray-300">{{ branch.id }}</span></div>
                        <div class="flex justify-between border-b pb-2"><span class="text-gray-500">Fecha de creación</span><span class="font-medium text-gray-700 dark:text-gray-300">{{ formatDate(branch.created_at) }}</span></div>
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
                    <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg p-2">
                        <div class="font-semibold flex items-center space-x-3 text-base bg-[#f8f8f8] rounded-[7px] text-[#3f3f3f] dark:text-gray-200 mb-2 px-2 py-px">
                            <i class="pi pi-sliders-h"></i>
                            <span>Configuración general</span>
                        </div>
                        <div class="flex justify-between text-sm px-3 mt-3"><span class="text-gray-500">Zona horaria</span><span class="font-medium text-gray-700 dark:text-gray-300">{{ branch.settings?.timezone || '-' }}</span></div>
                    </div>

                    <!-- --- CAMBIO: --- La tarjeta de horario ahora lee de 'businessHours' y es más simple. -->
                    <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg p-2">
                        <div class="font-semibold flex items-center space-x-3 text-base bg-[#f8f8f8] rounded-[7px] text-[#3f3f3f] dark:text-gray-200 mb-2 px-2 py-px">
                            <i class="pi pi-calendar"></i>
                            <span>Horario de operación</span>
                        </div>
                        <div class="space-y-3 text-sm px-3">
                            <div v-for="day in businessHours" :key="day.day_name" class="flex justify-between">
                                <span class="text-gray-500">{{ day.day_name }}</span>
                                <span class="font-medium text-gray-700 dark:text-gray-300">
                                    <template v-if="day.is_active">
                                        {{ formatTime(day.start_time) }} - {{ formatTime(day.end_time) }}
                                    </template>
                                    <template v-else>
                                        Cerrado
                                    </template>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
