<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import { useConfirmDelete } from '@/Composables/useConfirmDelete'; 
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
const { confirmDelete } = useConfirmDelete(); 
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

// --- NUEVOS REFS PARA PERIODOS ---
const periodModalVisible = ref(false);
const isEditingPeriod = ref(false);
const editingPeriodId = ref(null);

const initialBalanceForm = useForm({ initial_balance: props.user.employee?.vacation_balance || 0 });
const transactionForm = useForm({
    type: '',
    days: null,
    start_date: null,
    end_date: null,
    description: '',
});

// Formulario para crear/editar periodos
const periodForm = useForm({
    year_number: 1,
    period_start: null,
    period_end: null,
    days_entitled: 0,
    days_accrued: 0,
    days_taken: 0,
    is_premium_paid: false,
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

const confirmDeleteTransaction = (ledgerId) => {
    confirmDelete({
        item: { id: ledgerId }, 
        routeName: 'vacations.destroyTransaction',
        message: '¿Estás seguro de que quieres eliminar este registro? Esta acción recalculará el saldo de vacaciones del empleado.',
    });
};

const markPremiumAsPaid = (periodId, yearNumber) => {
    if(!confirm(`¿Confirmas que se ha pagado la prima vacacional correspondiente al Año ${yearNumber}?`)) return;

    router.post(route('vacations.markPremiumAsPaid', periodId), {}, {
        preserveScroll: true,
        onSuccess: () => { }
    });
};

// --- MÉTODOS PARA GESTIÓN DE PERIODOS ---

const openCreatePeriodModal = () => {
    isEditingPeriod.value = false;
    editingPeriodId.value = null;
    periodForm.reset();
    
    // Valores por defecto inteligentes (opcional)
    const lastPeriod = props.user.employee?.vacation_periods?.slice(-1)[0];
    if (lastPeriod) {
        periodForm.year_number = lastPeriod.year_number + 1;
        // Fechas sugeridas no se implementan aquí por simplicidad, pero podrían calcularse
    } else {
        periodForm.year_number = 1;
    }
    
    periodModalVisible.value = true;
};

const openEditPeriodModal = (period) => {
    isEditingPeriod.value = true;
    editingPeriodId.value = period.id;
    
    // Cargar datos en el form
    periodForm.year_number = period.year_number;
    periodForm.period_start = new Date(period.period_start); // Asegurar objeto Date
    periodForm.period_end = new Date(period.period_end);
    periodForm.days_entitled = parseFloat(period.days_entitled);
    periodForm.days_accrued = parseFloat(period.days_accrued);
    periodForm.days_taken = parseFloat(period.days_taken);
    periodForm.is_premium_paid = !!period.is_premium_paid;

    periodModalVisible.value = true;
};

const savePeriod = () => {
    if (isEditingPeriod.value) {
        periodForm.put(route('vacations.periods.update', editingPeriodId.value), {
            onSuccess: () => periodModalVisible.value = false,
        });
    } else {
        periodForm.post(route('vacations.periods.store', props.user.employee.id), {
            onSuccess: () => periodModalVisible.value = false,
        });
    }
};

const confirmDeletePeriod = (periodId) => {
    confirmDelete({
        item: { id: periodId },
        routeName: 'vacations.periods.destroy',
        message: '¿Estás seguro de eliminar este periodo vacacional? Esto podría afectar cálculos históricos.',
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

const formatDateShort = (dateString) => {
    if (!dateString) return '-';
    try {
        const date = new Date(dateString);
        return format(date, "dd/MM/yyyy", { locale: es });
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

const isPeriodExhausted = (period) => {
    return parseFloat(period.days_taken) >= (parseFloat(period.days_entitled) - 0.1);
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

                    <!-- NUEVA CARD: PERIODOS VACACIONALES -->
                    <div class="bg-white dark:bg-neutral-900 shadow-md rounded-lg p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Periodos y primas vacacionales</h2>
                                <p class="text-sm text-gray-500">Estado de vacaciones por año de servicio.</p>
                            </div>
                            <Button v-if="hasPermission('vacaciones_usuarios')" icon="pi pi-plus" rounded size="small" outlined @click="openCreatePeriodModal" v-tooltip.top="'Agregar periodo manualmente'" />
                        </div>

                        <DataTable :value="user.employee?.vacation_periods" size="small" stripedRows tableStyle="min-width: 45rem">
                            <Column field="year_number" header="Año">
                                <template #body="{ data }">
                                    <span class="font-bold">Año {{ data.year_number }}</span>
                                    <div class="text-xs text-gray-500">
                                        {{ formatDateShort(data.period_start) }} - {{ formatDateShort(data.period_end) }}
                                    </div>
                                </template>
                            </Column>
                            <Column header="Días">
                                <template #body="{ data }">
                                    <div class="text-sm">
                                        <div class="flex justify-between mb-1">
                                            <span>Otorgados:</span>
                                            <span class="font-medium">{{ parseFloat(data.days_entitled) }}</span>
                                        </div>
                                        <div class="flex justify-between mb-1">
                                            <span>Devengados:</span>
                                            <span class="font-medium text-gray-500">{{ parseFloat(data.days_accrued) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Tomados:</span>
                                            <span class="font-medium text-blue-600">{{ parseFloat(data.days_taken) }}</span>
                                        </div>
                                    </div>
                                </template>
                            </Column>
                            <Column header="Estado">
                                <template #body="{ data }">
                                    <Tag v-if="isPeriodExhausted(data)" value="Completado" severity="info" />
                                    <Tag v-else value="En curso" severity="success" />
                                </template>
                            </Column>
                            <Column header="Prima Vacacional">
                                <template #body="{ data }">
                                    <div v-if="data.is_premium_paid" class="flex items-center text-green-600 gap-2">
                                        <i class="pi pi-check-circle text-xl"></i>
                                        <div class="flex flex-col">
                                            <span class="font-bold text-sm">Pagada</span>
                                            <span class="text-xs text-gray-500">{{ formatDateShort(data.premium_paid_at) }}</span>
                                        </div>
                                    </div>
                                    <div v-else>
                                        <Button 
                                            v-if="isPeriodExhausted(data)"
                                            label="Pagar Prima" 
                                            icon="pi pi-wallet" 
                                            severity="warning" 
                                            size="small" 
                                            @click="markPremiumAsPaid(data.id, data.year_number)"
                                            v-tooltip.top="'El empleado ya tomó sus vacaciones de este año'"
                                        />
                                        <span v-else class="text-gray-400 text-sm italic">Pendiente</span>
                                    </div>
                                </template>
                            </Column>
                            <!-- COLUMNA DE ACCIONES -->
                            <Column header="Acciones" v-if="hasPermission('vacaciones_usuarios')">
                                <template #body="{ data }">
                                    <div class="flex gap-2">
                                        <Button icon="pi pi-pencil" text rounded size="small" severity="secondary" @click="openEditPeriodModal(data)" />
                                        <Button icon="pi pi-trash" text rounded size="small" severity="danger" @click="confirmDeletePeriod(data.id)" />
                                    </div>
                                </template>
                            </Column>
                            <template #empty>
                                <div class="text-center p-4 text-gray-500">
                                    No hay periodos registrados.
                                </div>
                            </template>
                        </DataTable>
                    </div>

                    <!-- Card: Historial de vacaciones (Ledger) -->
                    <div class="bg-white dark:bg-neutral-900 shadow-md rounded-lg p-6">
                        <div class="flex flex-col sm:flex-row justify-between items-start mb-4">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Historial de movimientos</h2>
                                <p class="text-sm text-gray-500">Bitácora detallada de transacciones.</p>
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
                            <Column field="type" header="Tipo">
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
                            <Column field="balance" header="Saldo Global"></Column>
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

                        <!-- SECCIÓN DE RESUMEN DE VACACIONES -->
                        <div
                            class="mt-6 p-4 bg-gray-50 dark:bg-neutral-800 rounded-lg flex flex-col sm:flex-row justify-around items-center gap-4 text-center">
                            <div class="flex items-center gap-3">
                                <AvionIcon class="size-8 text-blue-500" />
                                <div>
                                    <p class="text-gray-500 dark:text-gray-400 text-sm">Días disponibles (Global)</p>
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
                    <InputNumber v-model="initialBalanceForm.initial_balance" inputId="initial_balance" :maxFractionDigits="2" />
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
                                    :placeholder="transactionType === 'adjustment' ? 'Puede ser negativo' : ''" :maxFractionDigits="2" />
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

            <!-- NUEVO MODAL: Gestión de Periodos -->
            <Dialog v-model:visible="periodModalVisible" modal :header="isEditingPeriod ? 'Editar Periodo Vacacional' : 'Agregar Periodo Vacacional'" :style="{ width: '38rem' }">
                <form @submit.prevent="savePeriod">
                    <div class="flex flex-col gap-4">
                        <div class="flex gap-2">
                            <div class="w-1/3 flex flex-col gap-2">
                                <label>Número de año</label>
                                <InputNumber fluid v-model="periodForm.year_number" :useGrouping="false" :min="1" />
                            </div>
                            <div class="w-2/3 flex items-end pb-2">
                                <div class="flex items-center gap-2">
                                    <Checkbox v-model="periodForm.is_premium_paid" :binary="true" inputId="is_premium_paid" />
                                    <label for="is_premium_paid" class="cursor-pointer">Prima Vacacional Pagada</label>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col gap-2">
                            <label>Rango del Periodo (Aniversario a Aniversario)</label>
                            <div class="flex gap-2">
                                <DatePicker v-model="periodForm.period_start" placeholder="Inicio" class="w-full" />
                                <DatePicker v-model="periodForm.period_end" placeholder="Fin" class="w-full" />
                            </div>
                            <small class="text-red-500" v-if="periodForm.errors.period_end">{{ periodForm.errors.period_end }}</small>
                        </div>

                        <div class="grid grid-cols-3 gap-2">
                            <div class="flex flex-col gap-2">
                                <label class="text-sm">Días otorgados</label>
                                <InputNumber fluid v-model="periodForm.days_entitled" :maxFractionDigits="2" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-sm">Días devengados</label>
                                <InputNumber fluid v-model="periodForm.days_accrued" :maxFractionDigits="2" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-sm">Días tomados</label>
                                <InputNumber fluid v-model="periodForm.days_taken" :maxFractionDigits="2" />
                            </div>
                        </div>
                        <Message severity="warn" icon="pi pi-exclamation-triangle">
                            Modificar "Días Tomados" manualmente puede causar inconsistencias con el historial de transacciones.
                        </Message>
                    </div>

                    <div class="flex justify-end gap-2 mt-6">
                        <Button type="button" label="Cancelar" severity="secondary" @click="periodModalVisible = false" outlined />
                        <Button type="submit" label="Guardar" :loading="periodForm.processing" />
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