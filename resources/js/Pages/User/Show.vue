<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import { useConfirmDelete } from '@/Composables/useConfirmDelete'; // <-- 1. IMPORTAR COMPOSABLE
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
const { confirmDelete } = useConfirmDelete(); // <-- 2. INSTANCIAR COMPOSABLE
const home = ref({ icon: 'pi pi-home', url: route('dashboard') });
const items = ref([
    { label: 'Usuarios', url: route('users.index'), icon: PrimeIcons.USER },
    { label: 'Detalles de usuario' }
]);
const isInitialBalanceModalVisible = ref(false);
const transactionModalVisible = ref(false);
const transactionType = ref(''); // 'taken', 'earned', 'adjustment'
const descriptionOp = ref(); // Ref para el popover de descripción
const scheduleOp = ref();
const selectedDescription = ref(''); // Ref para guardar la descripción seleccionada

const initialBalanceForm = useForm({ initial_balance: props.user.employee?.vacation_balance || 0 });
const transactionForm = useForm({
    type: '',
    days: null,
    start_date: null,
    end_date: null,
    description: '',
});

// --- Computed Properties ---
const availableVacationDays = computed(() => {
    return parseFloat(props.user.employee?.vacation_balance) || 0;
});

const fullName = computed(() => {
    return props.user.name;
});

const employeeSchedule = computed(() => {
    return (props.user.employee?.schedules && props.user.employee.schedules.length > 0)
        ? props.user.employee.schedules[0]
        : null;
});

const totalWeeklyHours = computed(() => {
    if (!employeeSchedule.value || !employeeSchedule.value.details) {
        return 0;
    }
    return employeeSchedule.value.details.reduce((total, detail) => {
        return total + calculateDayTotalHours(detail);
    }, 0);
});

const positionAndBranch = computed(() => {
    const position = props.user.employee?.position;
    const branch = props.user.employee?.branch?.name;
    const fallbackRole = props.user.roles && props.user.roles.length > 0
        ? props.user.roles[0].charAt(0).toUpperCase() + props.user.roles[0].slice(1)
        : 'Usuario del Sistema';

    return [position, branch].filter(Boolean).join(' - ') || fallbackRole;
});

const takenVacationDays = computed(() => {
    if (!props.user.employee?.vacation_history) return 0;
    return props.user.employee?.vacation_history.reduce((total, item) => {
        return item.type === 'taken' ? total + Math.abs(item.days) : total;
    }, 0);
});

// --- Methods ---
const translateVacationType = (type) => {
    const translations = {
        initial: 'Inicial',
        earned: 'Ganado',
        taken: 'Tomado',
        adjustment: 'Ajuste'
    };
    return translations[type] || type;
};

const saveInitialBalance = () => {
    initialBalanceForm.post(route('vacations.updateInitialBalance', props.user.employee.id), {
        onSuccess: () => {
            isInitialBalanceModalVisible.value = false;
        }
    });
};

const openTransactionModal = (type) => {
    transactionType.value = type;
    transactionForm.reset();
    transactionForm.type = type;
    transactionModalVisible.value = true;
};

const saveTransaction = () => {
    transactionForm.post(route('vacations.storeTransaction', props.user.employee.id), {
        preserveScroll: true,
        onSuccess: () => {
            transactionForm.reset();
            transactionModalVisible.value = false;
        },
        onError: (err) => console.log(err),
    });
};

// --- 3. REFACTORIZAR MÉTODO DE CONFIRMACIÓN ---
const confirmDeleteTransaction = (ledgerId) => {
    confirmDelete({
        item: { id: ledgerId }, // El composable espera un objeto con `id`
        routeName: 'vacations.destroyTransaction',
        message: '¿Estás seguro de que quieres eliminar este registro? Esta acción recalculará el saldo de vacaciones del empleado.',
    });
};

const toggleDescription = (event, description) => {
    selectedDescription.value = description;
    descriptionOp.value.toggle(event);
};

const transactionMenuItems = ref([
    { label: 'Registrar días tomados', icon: 'pi pi-calendar-minus', command: () => openTransactionModal('taken') },
    { label: 'Registrar días ganados', icon: 'pi pi-calendar-plus', command: () => openTransactionModal('earned') },
    { label: 'Hacer un ajuste manual', icon: 'pi pi-sliders-h', command: () => openTransactionModal('adjustment') }
]);

const formatDate = (dateString) => {
    if (!dateString) return '-';
    try {
        const date = new Date(dateString);
        return format(date, "d 'de' MMMM 'de' yyyy", { locale: es });
    } catch (error) {
        return '-';
    }
};

const hasPermission = (permission) => {
    return usePage().props.auth.permissions?.includes(permission) ?? false;
};

const toggleSchedulePopover = (event) => {
    scheduleOp.value.toggle(event);
};

const formatTime = (timeString) => {
    if (!timeString) return '';
    const [hours, minutes] = timeString.split(':');
    const date = new Date();
    date.setHours(hours, minutes);
    return format(date, 'hh:mm a');
};

const getDayFromSchedule = (dayOfWeek) => {
    return employeeSchedule.value?.details.find(d => d.day_of_week === dayOfWeek);
};

const calculateDayTotalHours = (detail) => {
    if (!detail || !detail.start_time || !detail.end_time) {
        return 0;
    }
    const start = new Date(`1970-01-01T${detail.start_time}`);
    const end = new Date(`1970-01-01T${detail.end_time}`);
    const diffHours = (end - start) / (1000 * 60 * 60);
    const mealHours = (detail.meal_minutes || 0) / 60;
    return diffHours - mealHours;
};

</script>

<template>
    <Head :title="`Detalles de ${fullName}`" />
    <AppLayout>
        <Breadcrumb :home="home" :model="items" class="!bg-transparent" />
        <div class="max-w-[90rem] mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <!-- === HEADER DE PERFIL === -->
            <div class="relative">
                <div class="bg-black dark:bg-neutral-900 h-32 md:h-48 shadow-md rounded-3xl overflow-hidden mb-8">
                    <span class="text-white text-6xl absolute bottom-4 right-7 font-bold opacity-40">CB</span>
                    <img :src="user.avatar_url" :alt="user.name"
                        class="size-44 bg-[#D8BBFC] rounded-full object-cover absolute left-9 -bottom-7 border-8 border-[#d9d9d9] dark:border-gray-800" />
                </div>
            </div>
            <div>
                <div class="flex flex-row items-center justify-between lg:items-start mb-3">
                    <div class="sm:ml-[200px] lg:ml-3 mt-4 sm:mt-0 text-center sm:text-left">
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 m-0">{{ fullName }}</h1>
                        <p class="text-gray-600 dark:text-gray-400">{{ positionAndBranch }}</p>
                        <Tag v-if="user.employee" :value="user.employee.is_active ? 'Activo' : 'Inactivo'"
                            :severity="user.employee.is_active ? 'success' : 'danger'" />
                        <Tag v-else value="Activo" severity="success" />
                    </div>
                    <div class="sm:ml-auto">
                        <Link :href="route('users.edit', user.id)">
                        <Button v-if="hasPermission('editar_usuarios')" label="Editar perfil" icon="pi pi-pencil"
                            outlined />
                        </Link>
                    </div>
                </div>
            </div>

            <div v-if="!user.employee" class="text-center p-8 bg-white dark:bg-neutral-900 shadow-md rounded-lg">
                <i class="pi pi-user text-5xl text-gray-400"></i>
                <h2 class="mt-4 text-xl font-semibold text-gray-800 dark:text-gray-200">Usuario del Sistema</h2>
                <p class="text-gray-500 dark:text-gray-400 mt-2">Este usuario no tiene un perfil de empleado asociado.
                </p>
            </div>

            <!-- === GRID DE INFORMACIÓN (Solo si es empleado) === -->
            <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Columna Izquierda -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Card: Información Laboral -->
                    <div class="bg-white dark:bg-neutral-900 shadow-md rounded-lg p-2">
                         <h2 class="font-semibold flex items-center space-x-3 text-base bg-[#f8f8f8] dark:bg-neutral-700 rounded-[7px] text-[#3f3f3f] dark:text-gray-200 mb-2 px-2 py-px">
                            <i class="pi pi-briefcase"></i>
                            <span>Información laboral</span>
                        </h2>
                        <div class="text-sm px-4 divide-y-[1px]">
                            <div class="flex py-2"><span class="text-gray-500 w-[40%]">N° de empleado</span><span
                                    class="font-medium text-gray-700 dark:text-gray-300">{{
                                        user.employee?.employee_number || '-' }}</span></div>
                            <div class="flex py-2"><span class="text-gray-500 w-[40%]">Fecha de ingreso</span><span
                                    class="font-medium text-gray-700 dark:text-gray-300">{{
                                        formatDate(user.employee?.hire_date) }}</span></div>
                            <div class="flex py-2"><span class="text-gray-500 w-[40%]">Sucursal</span><span
                                    class="font-medium text-gray-700 dark:text-gray-300">{{
                                        user.employee?.branch?.name || '-' }}</span></div>
                            <div class="flex py-2"><span class="text-gray-500 w-[40%]">Puesto</span><span
                                    class="font-medium text-gray-700 dark:text-gray-300">{{ user.employee?.position ||
                                        '-' }}</span></div>
                            <div class="flex py-2"><span class="text-gray-500 w-[40%]">Horario</span> <span
                                    class="font-medium text-gray-700 dark:text-gray-300 flex items-center">
                                    {{ employeeSchedule?.name || 'No asignado' }}
                                    <i v-if="employeeSchedule" class="pi pi-book ml-2 cursor-pointer"
                                        @click="toggleSchedulePopover"></i>
                                </span></div>
                                <div class="flex py-2"><span class="text-gray-500 w-[40%]">CURP</span><span
                                    class="font-medium text-gray-700 dark:text-gray-300">{{ user.employee?.curp || '-'
                                    }}</span></div>
                            <div class="flex py-2"><span class="text-gray-500 w-[40%]">RFC</span><span
                                    class="font-medium text-gray-700 dark:text-gray-300">{{ user.employee?.rfc || '-'
                                    }}</span></div>
                            <div class="flex py-2"><span class="text-gray-500 w-[40%]">Número de seguridad
                                    social</span><span class="font-medium text-gray-700 dark:text-gray-300">{{
                                        user.employee?.nss || '-' }}</span></div>
                            <div class="flex py-2"><span class="text-gray-500 w-[40%]">Salario base</span><span
                                    class="font-medium text-gray-700 dark:text-gray-300">${{ new
                                        Intl.NumberFormat('es-MX').format(user.employee?.base_salary) }} semanales</span>
                            </div>
                        </div>
                    </div>

                    <!-- Card: Historial de vacaciones -->
                    <div class="bg-white dark:bg-neutral-900 shadow-md rounded-lg p-6">
                        <div class="flex flex-col sm:flex-row justify-between items-start mb-4">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Historial de
                                    vacaciones</h2>
                                <p class="text-sm text-gray-500">Visualiza y gestiona el historial de vacaciones del
                                    empleado.</p>
                            </div>
                            <div class="flex gap-2 mt-4 sm:mt-0">
                                <SplitButton v-if="hasPermission('vacaciones_usuarios')" label="Ajustar saldo inicial"
                                    :model="transactionMenuItems" @click="isInitialBalanceModalVisible = true"
                                    severity="secondary" size="small" outlined />
                            </div>
                        </div>
                        <DataTable :value="user.employee?.vacation_history" scrollable scrollHeight="250px"
                            size="small">
                            <Column field="date" header="Fecha">
                                <template #body="{ data }">
                                    {{ formatDate(data.date) }}
                                </template>
                            </Column>
                            <Column field="type" header="Tipo de movimiento">
                                <template #body="{ data }">
                                    <div class="flex items-center">
                                        <span>{{ translateVacationType(data.type) }}</span>
                                        <i v-if="data.description"
                                            class="pi pi-info-circle ml-3 cursor-pointer text-gray-400"
                                            @click="toggleDescription($event, data.description)"></i>
                                    </div>
                                </template>
                            </Column>
                            <Column field="days" header="Días"></Column>
                            <Column field="balance" header="Saldo"></Column>
                            <Column header="Acciones" style="min-width: 5rem; text-align: center;">
                                <template #body="{ data }">
                                    <Button v-if="hasPermission('vacaciones_usuarios')" icon="pi pi-trash" text rounded
                                        severity="danger" size="small" @click="confirmDeleteTransaction(data.id)"
                                        v-tooltip.top="'Eliminar registro'" />
                                </template>
                            </Column>
                            <template #empty>
                                <div class="text-center p-8">
                                    <p class="text-gray-500 dark:text-gray-400">No ha habido ningún movimiento de
                                        vacaciones.</p>
                                </div>
                            </template>
                        </DataTable>

                        <!-- SECCIÓN DE RESUMEN DE VACACIONES (MOVIDA AQUÍ) -->
                        <div
                            class="mt-6 p-4 bg-gray-50 dark:bg-neutral-800 rounded-lg flex flex-col sm:flex-row justify-around items-center gap-4 text-center">
                            <div class="flex items-center gap-3">
                                <AvionIcon class="size-8 text-blue-500" />
                                <div>
                                    <p class="text-gray-500 dark:text-gray-400 text-sm">Días disponibles</p>
                                    <span class="font-bold text-2xl text-blue-600 dark:text-blue-400">{{
                                        availableVacationDays.toFixed(2) }}</span>
                                </div>
                            </div>
                            <div class="h-10 w-px bg-gray-200 dark:bg-neutral-700 hidden sm:block"></div>
                            <div class="flex items-center gap-3">
                                <i class="pi pi-calendar-minus !text-3xl text-green-500"></i>
                                <div>
                                    <p class="text-gray-500 dark:text-gray-400 text-sm">Días tomados (histórico)</p>
                                    <span class="font-bold text-2xl text-green-600 dark:text-green-400">{{
                                        takenVacationDays }}</span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Columna Derecha -->
                <div class="space-y-6">
                    <!-- Card: Información Personal -->
                    <div class="bg-white dark:bg-neutral-900 shadow-md rounded-lg p-2">
                         <h2 class="font-semibold flex items-center space-x-3 text-base bg-[#f8f8f8] dark:bg-neutral-700 rounded-[7px] text-[#3f3f3f] dark:text-gray-200 mb-2 px-2 py-px">
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
                            <div class="flex py-2"><span class="text-gray-500 w-[40%]">Fecha de nacimiento</span><span
                                    class="font-medium text-gray-700 dark:text-gray-300">{{
                                        formatDate(user.employee?.birth_date)
                                    }}</span></div>
                        </div>
                    </div>
                    <!-- Card: Contacto de emergencia -->
                    <div class="bg-white dark:bg-neutral-900 shadow-md rounded-lg p-2">
                        <h2 class="font-semibold flex items-center space-x-3 text-base bg-[#f8f8f8] dark:bg-neutral-700 rounded-[7px] text-[#3f3f3f] dark:text-gray-200 mb-2 px-2 py-px">
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
                </div>
            </div>
        </div>

        <!-- MODALES -->
        <template v-if="user.employee">
            <!-- MODAL: Ajuste de saldo inicial -->
            <Dialog v-model:visible="isInitialBalanceModalVisible" modal header="Ajuste de saldo inicial"
                :style="{ width: '25rem' }">
                <span class="text-gray-500 dark:text-gray-400 block mb-4">Establece los días que el empleado tenía
                    acumulados antes
                    de usar el sistema.</span>
                <div class="flex flex-col gap-2">
                    <label for="initial_balance" class="font-semibold">Saldo inicial (migración)</label>
                    <InputNumber v-model="initialBalanceForm.initial_balance" inputId="initial_balance" />
                </div>
                <div class="flex justify-end gap-2 mt-6">
                    <Button type="button" label="Cancelar" severity="secondary"
                        @click="isInitialBalanceModalVisible = false"></Button>
                    <Button type="button" label="Establecer" @click="saveInitialBalance"
                        :loading="initialBalanceForm.processing"></Button>
                </div>
            </Dialog>

            <!-- MODAL: Transacción de Vacaciones -->
            <Dialog v-model:visible="transactionModalVisible" modal header="Registrar movimiento de vacaciones"
                :style="{ width: '30rem' }">
                <form @submit.prevent="saveTransaction">
                    <div class="flex flex-col gap-4">
                        <template v-if="transactionType === 'taken'">
                            <div class="flex flex-col gap-2">
                                <label>Rango de fechas</label>
                                <div class="flex gap-2">
                                    <DatePicker v-model="transactionForm.start_date" placeholder="Inicio"
                                        class="w-full" :invalid="!!transactionForm.errors.start_date" />
                                    <DatePicker v-model="transactionForm.end_date" placeholder="Fin" class="w-full"
                                        :invalid="!!transactionForm.errors.end_date" />
                                </div>
                                <small v-if="transactionForm.errors.start_date" class="text-red-500">{{
                                    transactionForm.errors.start_date }}</small>
                                <small v-if="transactionForm.errors.end_date" class="text-red-500">{{
                                    transactionForm.errors.end_date }}</small>
                            </div>
                        </template>
                        <template v-else>
                            <div class="flex flex-col gap-2">
                                <label>Días a {{ transactionType === 'earned' ? 'otorgar' : 'ajustar' }}</label>
                                <InputNumber v-model="transactionForm.days"
                                    :placeholder="transactionType === 'adjustment' ? 'Puede ser negativo' : ''" />
                            </div>
                        </template>
                        <div class="flex flex-col gap-2">
                            <label>Descripción / Motivo</label>
                            <Textarea v-model="transactionForm.description" rows="3" autoResize />
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 mt-6">
                        <Button type="button" label="Cancelar" severity="secondary"
                            @click="transactionModalVisible = false" outlined />
                        <Button type="submit" label="Guardar" :loading="transactionForm.processing" />
                    </div>
                </form>
            </Dialog>

            <Popover ref="descriptionOp">
                <p class="p-2 text-sm max-w-xs">{{ selectedDescription }}</p>
            </Popover>
             <!-- POPOVER PARA DETALLES DEL HORARIO -->
            <Popover ref="scheduleOp">
                <div class="p-4 w-[400px]">
                    <h3 class="font-bold text-lg mb-2">Detalles del horario</h3>
                    <div v-if="employeeSchedule">
                        <div>
                            <h4 class="font-semibold text-base mb-2">Horario de "{{ employeeSchedule.name }}"</h4>
                            <table class="w-full text-sm text-left">
                                <thead class="bg-gray-50 text-xs uppercase">
                                    <tr>
                                        <th class="px-2 py-1">Día</th>
                                        <th class="px-2 py-1">Entrada</th>
                                        <th class="px-2 py-1">Salida</th>
                                        <th class="px-2 py-1">Comida (min)</th>
                                        <th class="px-2 py-1">Total hrs</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(dayName, index) in ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo']"
                                        :key="dayName">
                                        <td class="px-2 py-1 font-medium">{{ dayName }}</td>
                                        <template v-if="getDayFromSchedule(index + 1)">
                                            <td class="px-2 py-1">{{ formatTime(getDayFromSchedule(index +
                                                1).start_time) }}</td>
                                            <td class="px-2 py-1">{{ formatTime(getDayFromSchedule(index + 1).end_time)
                                            }}</td>
                                            <td class="px-2 py-1">{{ getDayFromSchedule(index + 1).meal_minutes }}</td>
                                            <td class="px-2 py-1 font-semibold">{{
                                                calculateDayTotalHours(getDayFromSchedule(index + 1)).toFixed(1) }}</td>
                                        </template>
                                        <template v-else>
                                            <td colspan="4"
                                                class="px-2 py-1 text-center bg-green-100 text-green-700 rounded">
                                                Descanso</td>
                                        </template>
                                    </tr>
                                </tbody>
                            </table>
                            <p class="text-right font-bold mt-2">Total de horas por semana: {{
                                totalWeeklyHours.toFixed(2) }}</p>
                        </div>
                    </div>
                </div>
            </Popover>
        </template>
    </AppLayout>
</template>