<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PrimeIcons } from '@primevue/core/api';
import { format } from 'date-fns';
import es from 'date-fns/locale/es';

import CruzIcon from '@/Components/Icons/CruzIcon.vue';
import AvionIcon from '@/Components/Icons/AvionIcon.vue';

// --- Props ---
const props = defineProps({
    user: Object,
});

// --- Refs and State ---
const home = ref({ icon: 'pi pi-home', url: route('dashboard') });
const items = ref([
    { label: 'Usuarios', url: route('users.index'), icon: PrimeIcons.USER },
    { label: 'Detalles de usuario' }
]);
const isModalVisible = ref(false);
const initialBalance = ref(10); // Valor de ejemplo

// --- Computed Properties ---
const fullName = computed(() => {
    return `${props.user.employee?.first_name || ''} ${props.user.employee?.last_name || ''}`.trim();
});

const positionAndBranch = computed(() => {
    const position = props.user.employee?.position;
    const branch = props.user.employee?.branch?.name;
    return [position, branch].filter(Boolean).join(' - ');
});

// ✨ --- SOLUCIÓN AQUÍ --- ✨
const formatDate = (dateString) => {
    if (!dateString) return '-';
    try {
        // Simplemente pasamos la cadena de fecha directamente.
        // El constructor de Date es lo suficientemente robusto para manejar el formato de Laravel.
        const date = new Date(dateString);
        return format(date, "d 'de' MMMM 'de' yyyy", { locale: es });
    } catch (error) {
        console.error("Error al formatear la fecha:", error);
        return '-'; // Devolver un valor por defecto en caso de error
    }
};

// --- Datos de ejemplo para la tabla ---
const vacationHistory = ref([
    { date: '2025-09-01', type: 'Iniciales', days: '+6', balance: 6 },
    { date: '2025-09-10', type: 'Tomadas', days: '-1', balance: 5 },
    { date: '2026-01-13', type: 'Otorgadas', days: '+12', balance: 17 },
]);

</script>

<template>

    <Head :title="`Detalles de ${fullName}`" />

    <AppLayout>
        <Breadcrumb :home="home" :model="items" class="!bg-transparent" />
        <div class="max-w-[90rem] mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <!-- === HEADER DE PERFIL === -->
            <div class="relative">
                <div class="bg-black dark:bg-gray-800 h-32 md:h-48 shadow-md rounded-3xl overflow-hidden mb-8">
                    <span class="text-white text-6xl absolute bottom-4 right-7 font-bold opacity-40">CB</span>
                    <Avatar :image="user.profile_photo_url" :label="user.name[0]"
                        class="!size-40 !text-5xl absolute left-9 -bottom-7 border-8 border-[#d9d9d9] dark:border-gray-800 !bg-pink-500"
                        shape="circle" />
                </div>
            </div>
            <div class="py-4">
                <div class="flex flex-col sm:flex-row items-start">
                    <div class="sm:ml-6 mt-4 sm:mt-0 text-center sm:text-left">
                        <h1 class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ fullName }}</h1>
                        <p class="text-gray-600 dark:text-gray-400">{{ positionAndBranch }}</p>
                        <Tag v-if="user.employee?.is_active" value="Activo" severity="success" />
                        <Tag v-else value="Inactivo" severity="danger" />
                    </div>
                    <div class="sm:ml-auto">
                        <Link :href="route('users.edit', user.id)">
                        <Button label="Editar perfil" icon="pi pi-pencil" outlined />
                        </Link>
                    </div>
                </div>
            </div>

            <!-- === GRID DE INFORMACIÓN === -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Columna Izquierda -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Card: Información Laboral -->
                    <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg p-2">
                        <h2
                            class="font-semibold flex items-center space-x-3 text-base bg-[#f8f8f8] rounded-[7px] text-[#3f3f3f] dark:text-gray-200 mb-2 px-2 py-px">
                            <i class="pi pi-briefcase"></i>
                            <span>Información laboral</span>
                        </h2>
                        <div class="text-sm px-4 divide-y-[1px]">
                            <div class="flex py-2"><span class="text-gray-500 w-[40%]">N° de
                                    empleado</span><span class="font-medium text-gray-700 dark:text-gray-300">{{
                                        user.employee?.employee_number || '-' }}</span></div>
                            <div class="flex py-2"><span class="text-gray-500 w-[40%]">Fecha de
                                    ingreso</span><span class="font-medium text-gray-700 dark:text-gray-300">{{
                                        formatDate(user.employee?.hire_date) }}</span></div>
                            <div class="flex py-2"><span class="text-gray-500 w-[40%]">Sucursal</span><span
                                    class="font-medium text-gray-700 dark:text-gray-300">{{ user.employee?.branch?.name
                                        || '-' }}</span></div>
                            <div class="flex py-2"><span class="text-gray-500 w-[40%]">Puesto</span><span
                                    class="font-medium text-gray-700 dark:text-gray-300">{{ user.employee?.position ||
                                        '-' }}</span></div>
                            <div class="flex py-2"><span class="text-gray-500 w-[40%]">Horario</span><span
                                    class="font-medium text-gray-700 dark:text-gray-300">Medio día 1 <i
                                        class="pi pi-external-link ml-1"></i></span></div>
                            <div class="flex py-2"><span class="text-gray-500 w-[40%]">Correo
                                    empresarial</span><span class="font-medium text-gray-700 dark:text-gray-300">{{
                                        user.email || '-' }}</span></div>
                            <div class="flex py-2"><span class="text-gray-500 w-[40%]">CURP</span><span
                                    class="font-medium text-gray-700 dark:text-gray-300">{{ user.employee?.curp || '-'
                                    }}</span></div>
                            <div class="flex py-2"><span class="text-gray-500 w-[40%]">RFC</span><span
                                    class="font-medium text-gray-700 dark:text-gray-300">{{ user.employee?.rfc || '-'
                                    }}</span></div>
                            <div class="flex py-2"><span class="text-gray-500 w-[40%]">Número de
                                    seguridad social</span><span class="font-medium text-gray-700 dark:text-gray-300">{{
                                        user.employee?.nss || '-' }}</span></div>
                            <div class="flex py-2"><span class="text-gray-500 w-[40%]">Salario
                                    base</span><span class="font-medium text-gray-700 dark:text-gray-300">${{ new
                                        Intl.NumberFormat('es-MX').format(user.employee?.base_salary) }} mensuales</span>
                            </div>
                            <div class="flex py-2"><span class="text-gray-500 w-[40%]">Rol de
                                    usuario</span><span
                                    class="font-medium text-gray-700 dark:text-gray-300">Empleado</span></div>
                        </div>
                    </div>

                    <!-- Card: Historial de vacaciones -->
                    <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg p-6">
                        <div class="flex justify-between items-start mb-4">
                            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Historial de vacaciones
                            </h2>
                            <Button label="Ajustar saldo inicial" @click="isModalVisible = true" icon="pi pi-pencil" outlined />
                        </div>
                        <p class="text-sm text-gray-500 mb-4">Visualiza el historial detallado de vacaciones iniciales,
                            otorgadas, tomadas y el saldo disponible en cada periodo.</p>
                        <DataTable :value="vacationHistory" size="small">
                            <Column field="date" header="Fecha"><template #body="{ data }">{{ formatDate(data.date)
                            }}</template>
                            </Column>
                            <Column field="type" header="Tipo de vacaciones"></Column>
                            <Column field="days" header="Días"></Column>
                            <Column field="balance" header="Saldo disponible"></Column>
                        </DataTable>
                    </div>
                </div>

                <!-- Columna Derecha -->
                <div class="space-y-6">
                    <!-- Card: Información Personal -->
                    <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg p-2">
                        <h2
                            class="font-semibold flex items-center space-x-3 text-base bg-[#f8f8f8] rounded-[7px] text-[#3f3f3f] dark:text-gray-200 mb-2 px-2 py-px">
                            <i class="pi pi-user"></i>
                            <span>Información personal</span>
                        </h2>
                        <div class="px-4 divide-y-[1px] text-sm">
                            <div class="flex py-2"><span class="text-gray-500 w-[40%]">Correo electrónico</span><span
                                    class="font-medium text-gray-700 dark:text-gray-300">{{ user.email || '-' }}</span>
                            </div>
                            <div class="flex py-2"><span class="text-gray-500 w-[40%]">Teléfono</span><span
                                    class="font-medium text-gray-700 dark:text-gray-300">{{ user.employee?.phone || '-'
                                    }}</span></div>
                            <div class="flex py-2"><span class="text-gray-500 w-[40%]">Fecha de
                                    nacimiento</span><span class="font-medium text-gray-700 dark:text-gray-300">{{
                                        formatDate(user.employee?.birth_date)
                                    }}</span></div>
                        </div>
                    </div>
                    <!-- Card: Contacto de emergencia -->
                    <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg p-2">
                        <h2
                            class="font-semibold flex items-center space-x-3 text-base bg-[#f8f8f8] rounded-[7px] text-[#3f3f3f] dark:text-gray-200 mb-2 px-2 py-px">
                            <CruzIcon class="size-4" />
                            <span>Contacto de emergencia</span>
                        </h2>
                        <div class="px-4 divide-y-[1px] text-sm">
                            <div class="flex py-2"><span class="text-gray-500 w-[40%]">Nombre</span><span
                                    class="font-medium text-gray-700 dark:text-gray-300">{{
                                        user.employee?.emergency_contact_name || '-'
                                    }}</span></div>
                            <div class="flex py-2"><span class="text-gray-500 w-[40%]">Teléfono</span><span
                                    class="font-medium text-gray-700 dark:text-gray-300">{{
                                        user.employee?.emergency_contact_phone ||
                                        '-' }}</span></div>
                            <div class="flex py-2"><span class="text-gray-500 w-[40%]">Parentesco</span><span
                                    class="font-medium text-gray-700 dark:text-gray-300">{{
                                        user.employee?.emergency_contact_relationship || '-' }}</span></div>
                        </div>
                    </div>
                    <!-- Card: Gestión de vacaciones -->
                    <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg p-2">
                         <h2
                            class="font-semibold flex items-center space-x-3 text-base bg-[#f8f8f8] rounded-[7px] text-[#3f3f3f] dark:text-gray-200 mb-2 px-2 py-px">
                            <AvionIcon class="size-4" />
                            <span>Gestión de vacaciones</span>
                        </h2>
                        <div class="px-4 divide-y-[1px] text-sm">
                            <div class="flex py-2"><span class="text-gray-500 w-[40%]">Días disponibles</span><span
                                    class="font-medium text-gray-700 dark:text-gray-300">7</span></div>
                            <div class="flex py-2"><span class="text-gray-500 w-[40%]">Días tomados</span><span
                                    class="font-medium text-gray-700 dark:text-gray-300">0</span></div>
                            <div class="flex py-2"><span class="text-gray-500 w-[40%]">Historial</span><span
                                    class="font-medium text-gray-700 dark:text-gray-300"><i
                                        class="pi pi-external-link ml-1"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MODAL: Ajuste de saldo inicial -->
            <Dialog v-model:visible="isModalVisible" modal header="Ajuste de saldo inicial" :style="{ width: '25rem' }">
                <span class="text-gray-500 dark:text-gray-400 block mb-4">Establece los días que el empleado tenía
                    acumulados antes
                    de usar el sistema.</span>
                <div class="flex flex-col gap-2">
                    <label for="initial_balance" class="font-semibold">Saldo inicial (migración)</label>
                    <InputNumber v-model="initialBalance" inputId="initial_balance" />
                </div>
                <div class="flex justify-end gap-2 mt-6">
                    <Button type="button" label="Cancelar" severity="secondary"
                        @click="isModalVisible = false"></Button>
                    <Button type="button" label="Establecer" @click="isModalVisible = false"></Button>
                </div>
            </Dialog>
        </div>
    </AppLayout>
</template>
