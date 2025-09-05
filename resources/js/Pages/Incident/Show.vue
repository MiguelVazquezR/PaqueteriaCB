<script setup>
import { ref, watch, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { debounce } from 'lodash';
import { format } from 'date-fns';
import es from 'date-fns/locale/es';
import { useConfirm } from 'primevue/useconfirm';
import { PrimeIcons } from '@primevue/core/api';

const confirm = useConfirm();
// --- Props ---
const props = defineProps({
    period: Object,
    employeesData: Array,
    branches: Array,
    filters: Object,
    navigation: Object,
    incidentTypes: Array,
});

// --- Refs and State ---
const home = ref({ icon: 'pi pi-home', url: route('dashboard') });
const items = ref([
    { label: 'Incidencias', url: route('incidents.index'), icon: PrimeIcons.CALENDAR_TIMES },
    { label: `Semana ${props.period.week_number}` }
]);
const search = ref(props.filters.search);
const selectedBranch = ref(props.filters.branch_id);
const menu = ref();
const selectedDayMenu = ref(null);

// State para el modal de comentarios
const commentModalVisible = ref(false);
const currentEmployeeForComment = ref(null);
const commentText = ref('');

// ✨ State para el modal de edición de asistencia
const attendanceModalVisible = ref(false);
const currentDayForEdit = ref(null);
const currentEmployeeForEdit = ref(null);
const attendanceForm = useForm({
    employee_id: null,
    date: null,
    entry_time: null,
    exit_time: null,
});

// ✨ State para el panel de resumen de descansos
const op = ref();
const selectedBreaks = ref([]);
const breakModalVisible = ref(false);
const currentDayForBreakEdit = ref(null);
const currentDayForBreakSummary = ref(null);

const breakForm = useForm({
    date: null,
    start_id: null,
    end_id: null,
    start_time: null,
    end_time: null,
});

const splitButtonItems = ref([
    {
        label: 'Imprimir incidencias',
        command: () => {
            router.get(route('incidents.printAttendances', props.period.id));
        }
    }
]);

// --- Watchers ---
watch([search, selectedBranch], debounce(() => {
    router.get(route('incidents.show', props.period.id), {
        search: search.value,
        branch_id: selectedBranch.value
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}, 300));

// --- Computed Properties ---
const totalSelectedBreakTime = computed(() => {
    return selectedBreaks.value.reduce((total, item) => total + item.duration, 0);
});

// --- Métodos ---
const formatDate = (dateString, formatStr = "EEEE, dd 'de' MMMM") => {
    if (!dateString) return '';
    return format(dateString, formatStr, { locale: es });
};

// --- Métodos para Comentarios ---
const openCommentModal = (employee) => {
    currentEmployeeForComment.value = employee;
    commentText.value = employee.comments;
    commentModalVisible.value = true;
};

const saveComment = () => {
    router.post(route('incidents.updateComment'), {
        employee_id: currentEmployeeForComment.value.id,
        period_id: props.period.id,
        comments: commentText.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            commentModalVisible.value = false;
        }
    });
};

// --- Métodos para Incidencias ---
const addIncident = (employee, day, incidentTypeId) => {
    router.post(route('incidents.storeDayIncident'), {
        employee_id: employee.id,
        incident_type_id: incidentTypeId,
        date: day.date,
    }, {
        preserveScroll: true,
    });
};

const removeIncident = (employee, day) => {
    router.post(route('incidents.removeDayIncident'), {
        employee_id: employee.id,
        date: day.date,
    }, {
        preserveScroll: true,
    });
};

// --- Métodos para Retardos ---
const toggleLateStatus = (day) => {
    router.post(route('incidents.toggleLateStatus'), {
        entry_id: day.entry_id,
    }, {
        preserveScroll: true,
    });
};

// Métodos para Edición de Asistencia ---
const openAttendanceModal = (day, employee) => {
    currentDayForEdit.value = day;
    currentEmployeeForEdit.value = employee;

    // Función auxiliar para convertir una cadena 'HH:mm' a un objeto Date
    const createDateFromTime = (timeString) => {
        if (!timeString) return null;
        // Creamos una fecha base con el día correcto
        const date = new Date(day.date + 'T00:00:00');
        // Extraemos las horas y minutos de la cadena
        const [hours, minutes] = timeString.split(':');
        // Establecemos la hora y los minutos en el objeto Date
        date.setHours(parseInt(hours, 10), parseInt(minutes, 10));
        return date;
    };

    attendanceForm.employee_id = employee.id;
    attendanceForm.date = day.date;
    // Usamos la función auxiliar para poblar el formulario con objetos Date válidos
    attendanceForm.entry_time = createDateFromTime(day.entry_time_raw);
    attendanceForm.exit_time = createDateFromTime(day.exit_time_raw);

    attendanceModalVisible.value = true;
};

const saveAttendance = () => {
    // Usamos transform para formatear los datos justo antes de enviarlos.
    attendanceForm.transform((data) => {
        const formattedData = { ...data };

        // Si entry_time es un objeto Date (del DatePicker), lo formateamos.
        if (data.entry_time instanceof Date) {
            formattedData.entry_time = format(data.entry_time, 'HH:mm');
        }
        // Si exit_time es un objeto Date, lo formateamos.
        if (data.exit_time instanceof Date) {
            formattedData.exit_time = format(data.exit_time, 'HH:mm');
        }

        return formattedData;
    }).post(route('incidents.updateAttendance'), {
        preserveScroll: true,
        onSuccess: () => {
            attendanceModalVisible.value = false;
            attendanceForm.reset();
        }
    });
};

// método para mostrar el resumen de descansos
const toggleBreakSummary = (event, day) => {
    selectedBreaks.value = day.breaks_summary;
    currentDayForBreakSummary.value = day;
    op.value.toggle(event);
};

const toggleDayMenu = (event, day, employee) => {
    let menuItems = [];
    // const isRestedHoliday = day.holiday_name && !day.entry_time;

    // Solo mostrar opciones de retardo y edición si no es un festivo descansado o una incidencia.
    if (!day.incident) {
        if (day.late_minutes && !day.late_ignored) {
            menuItems.push({ label: 'Quitar retardo', icon: 'pi pi-check-circle', command: () => toggleLateStatus(day) });
        }
        if (day.late_minutes && day.late_ignored) {
            menuItems.push({ label: 'Poner retardo', icon: 'pi pi-exclamation-circle', command: () => toggleLateStatus(day) });
        }
        menuItems.push({ label: 'Modificar registro', icon: 'pi pi-pencil', command: () => openAttendanceModal(day, employee) });
    }
    // Quitar incidencia si existe
    if (day.incident) {
        menuItems.push({ label: 'Quitar incidencia', icon: 'pi pi-times-circle', class: 'p-menuitem-text-danger', command: () => removeIncident(employee, day) });
    }
    // Separador si hay acciones previas
    if (menuItems.length > 0) {
        menuItems.push({ separator: true });
    }
    props.incidentTypes.forEach(type => {
        menuItems.push({ label: type.name, command: () => addIncident(employee, day, type.id) });
    });

    selectedDayMenu.value = menuItems;
    menu.value.toggle(event);
};

const getIncidentSeverity = (incidentName) => {
    const danger = ['Falta injustificada'];
    const warning = ['Incapacidad general', 'Permiso sin goce'];
    const success = ['Día Festivo', 'Descanso'];
    const info = ['Vacaciones', 'Permiso con goce'];

    if (danger.includes(incidentName)) return 'danger';
    if (warning.includes(incidentName)) return 'warning';
    if (success.includes(incidentName)) return 'success';
    if (info.includes(incidentName)) return 'info';
    return 'secondary';
};

const timeStringToDate = (timeString) => {
    if (!timeString) return null;
    const date = new Date();
    const [time, period] = timeString.split(' ');
    let [hours, minutes] = time.split(':');
    hours = parseInt(hours);
    if (period.toLowerCase() === 'pm' && hours < 12) hours += 12;
    if (period.toLowerCase() === 'am' && hours === 12) hours = 0;
    date.setHours(hours, parseInt(minutes), 0);
    return date;
};

const openBreakEditModal = (breakItem, date) => {
    currentDayForBreakEdit.value = date;
    breakForm.date = date;
    breakForm.start_id = breakItem.start_id;
    breakForm.end_id = breakItem.end_id;
    breakForm.start_time = timeStringToDate(breakItem.start);
    breakForm.end_time = timeStringToDate(breakItem.end);
    breakModalVisible.value = true;
};

const saveBreak = () => {
    breakForm.transform(data => ({
        ...data,
        start_time: data.start_time ? new Date(data.start_time).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }) : null,
        end_time: data.end_time ? new Date(data.end_time).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }) : null,
    })).put(route('incidents.updateBreak'), {
        preserveScroll: true,
        onSuccess: () => breakModalVisible.value = false,
        onError: (err) => console.log(err),
    });
};

const confirmDeleteBreak = (breakItem) => {
    confirm.require({
        message: '¿Estás seguro de que quieres eliminar este descanso?',
        header: 'Confirmar eliminación',
        rejectProps: {
            label: 'Cancelar',
            severity: 'secondary',
            outlined: true
        },
        acceptProps: {
            label: 'Eliminar',
            severity: 'danger'
        },
        accept: () => {
            router.delete(route('incidents.destroyBreak'), {
                data: { start_id: breakItem.start_id, end_id: breakItem.end_id },
                preserveScroll: true,
            });
        }
    });
};

</script>

<template>

    <Head :title="`Incidencias - Semana ${period.week_number}`" />

    <AppLayout>
        <Breadcrumb :home="home" :model="items" class="!bg-transparent" />
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <!-- === HEADER DE CONTROL === -->
            <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg p-4 mb-6">
                <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                    <div class="flex items-center gap-4">
                        <Button icon="pi pi-chevron-left" text rounded :disabled="!navigation.previous_period_id"
                            @click="navigation.previous_period_id && router.get(route('incidents.show', navigation.previous_period_id))" />
                        <div class="text-center">
                            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 m-0">Semana {{
                                period.week_number
                                }}</h1>
                            <p class="text-sm text-gray-500">{{ period.start_date_formatted_short }} - {{
                                period.end_date_formatted_full }}</p>
                        </div>
                        <Button icon="pi pi-chevron-right" text rounded :disabled="!navigation.next_period_id"
                            @click="navigation.next_period_id && router.get(route('incidents.show', navigation.next_period_id))" />
                    </div>
                    <div class="lg:flex items-center gap-2 w-full md:w-auto">
                        <Select v-model="selectedBranch" :options="branches" optionLabel="name" optionValue="id"
                            placeholder="Todas las sucursales" class="w-full md:w-56" size="large" showClear />
                        <IconField class="w-full md:w-auto mt-2 lg:mt-0">
                            <InputText v-model="search" placeholder="Buscar empleado" class="w-full" />
                            <InputIcon class="pi pi-search" />
                        </IconField>
                    </div>
                    <div class="flex items-center gap-2 mt-4 md:mt-0">
                        <SplitButton label="Generar pre-nómina" :model="splitButtonItems" size="large"
                            @click="router.get(route('incidents.prePayroll', period.id))" />
                    </div>
                </div>
            </div>

            <!-- === LISTA DE EMPLEADOS === -->
            <div class="space-y-6">
                <div v-for="employee in employeesData" :key="employee.id"
                    class="bg-white dark:bg-gray-800 shadow-md rounded-lg p-3">
                    <!-- Cabecera del empleado -->
                    <div
                        class="flex justify-between items-end p-4 bg-[#f8f8f8] text-[#3f3f3f] rounded-[9px] dark:bg-gray-700 dark:text-gray-100">
                        <div class="flex items-center gap-3">
                            <img :src="employee.avatar_url" :alt="employee.name"
                                class="size-12 rounded-full object-cover" />
                            <div>
                                <h2 class="font-bold text-lg m-0">{{ employee.name }}</h2>
                                <p class="text-sm">N° {{ employee.employee_number }} • {{
                                    employee.position }}</p>
                            </div>
                        </div>
                        <span class="text-sm font-semibold text-gray-600 dark:text-gray-300">{{ employee.branch_name
                            }}</span>
                    </div>

                    <!-- Tabla de días -->
                    <div class="overflow-x-auto mt-2">
                        <table class="w-full text-sm text-left">
                            <thead
                                class="bg-gray-50 dark:bg-gray-700 text-xs text-gray-700 dark:text-gray-400 uppercase">
                                <tr>
                                    <th class="px-2 py-1">Día</th>
                                    <th class="px-2 py-1">Entrada</th>
                                    <th class="px-2 py-1">Salida</th>
                                    <th class="px-2 py-1">T. Descanso</th>
                                    <th class="px-2 py-1">T. Extra</th>
                                    <th class="px-2 py-1">Horas totales</th>
                                    <th class="px-2 py-1"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="day in employee.daily_data" :key="day.date"
                                    class="border-b dark:border-gray-700">
                                    <td class="px-2 py-1 font-medium">
                                        <div class="flex items-center gap-2">
                                            <span>{{ day.date_formatted }}</span>
                                            <i v-if="day.holiday_name && day.entry_time"
                                                v-tooltip.top="`Festivo laborado: ${day.holiday_name}`"
                                                class="pi pi-star-fill text-yellow-500"></i>
                                            <!-- --- CAMBIO: --- Se añade un ícono para el descanso laborado. -->
                                            <i v-if="day.is_rest_day && day.entry_time"
                                                v-tooltip.top="`Descanso laborado`"
                                                class="pi pi-briefcase text-blue-500"></i>
                                        </div>
                                    </td>

                                    <!-- 1. Festivo DESCANSADO -->
                                    <template v-if="day.holiday_name && !day.entry_time">
                                        <td colspan="5" class="px-2 py-1">
                                            <Tag :value="day.holiday_name" severity="success"
                                                class="w-full text-center" />
                                        </td>
                                    </template>

                                    <!-- --- CAMBIO: --- 2. Descanso programado (y no trabajado) -->
                                    <template v-else-if="day.is_rest_day && !day.entry_time && !day.incident">
                                        <td colspan="5" class="px-2 py-1">
                                            <Tag value="Descanso" severity="succsess" class="w-full text-center" />
                                        </td>
                                    </template>

                                    <!-- 3. Otra incidencia (falta, vacaciones, etc.) -->
                                    <template v-else-if="day.incident">
                                        <td colspan="5" class="px-2 py-1">
                                            <Tag :value="day.incident" :severity="getIncidentSeverity(day.incident)"
                                                class="w-full text-center" />
                                        </td>
                                    </template>

                                    <!-- --- CAMBIO: --- 4. Falta Injustificada (detectada automáticamente) -->
                                    <template v-else-if="day.is_unjustified_absence">
                                        <td colspan="5" class="px-2 py-1">
                                            <Tag value="Falta Injustificada" severity="danger"
                                                class="w-full text-center" />
                                        </td>
                                    </template>

                                    <!-- 5. Es un día normal O un festivo TRABAJADO -->
                                    <template v-else>
                                        <td class="px-2 py-1">
                                            <span v-if="day.entry_time"
                                                v-tooltip.top="day.late_minutes && !day.late_ignored ? `${day.late_minutes} minutos de retardo` : null"
                                                class="flex items-center gap-2"
                                                :class="{ 'text-orange-500 font-semibold': day.late_minutes && !day.late_ignored }">
                                                {{ day.entry_time }}
                                                <i v-if="day.late_minutes && !day.late_ignored"
                                                    class="pi pi-exclamation-circle"></i>
                                            </span>
                                            <span v-else>-</span>
                                        </td>
                                        <td class="px-2 py-1">{{ day.exit_time || '-' }}</td>
                                        <td class="px-2 py-1">
                                            <div class="flex items-center">
                                                <span>{{ day.break_time }}</span>
                                                <Button v-if="day.breaks_summary && day.breaks_summary.length"
                                                    icon="pi pi-book" text rounded size="small" class="ml-2"
                                                    @click="toggleBreakSummary($event, day)" />
                                            </div>
                                        </td>
                                        <td class="px-2 py-1">{{ day.extra_time }}</td>
                                        <td class="px-2 py-1">{{ day.total_hours }}</td>
                                    </template>
                                    <!-- Columna 7: Opciones (Siempre visible) -->
                                    <td class="px-2 py-1 text-center">
                                        <Button @click="toggleDayMenu($event, day, employee)" icon="pi pi-ellipsis-v"
                                            text rounded />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Comentarios -->
                    <div class="p-4 text-sm">
                        <div class="flex justify-between items-center">
                            <span class="font-semibold">Comentarios</span>
                            <Button icon="pi pi-pencil" text rounded @click="openCommentModal(employee)" />
                        </div>
                        <p class="text-gray-600 dark:text-gray-400 mt-1" style="white-space: pre-line">
                            {{ employee.comments || 'Sin comentarios.' }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- MODAL: Agregar Comentarios -->
            <Dialog v-model:visible="commentModalVisible" modal header="Agregar comentarios"
                :style="{ width: '30rem' }">
                <div class="flex flex-col gap-2">
                    <label for="comment_text" class="font-semibold">Comentarios*</label>
                    <Textarea id="comment_text" v-model="commentText" rows="5"
                        placeholder="Escribe los comentarios relevantes para la nómina" autoResize />
                </div>
                <div class="flex justify-end gap-2 mt-6">
                    <Button type="button" label="Cancelar" severity="secondary"
                        @click="commentModalVisible = false"></Button>
                    <Button type="button" label="Guardar" @click="saveComment"></Button>
                </div>
            </Dialog>

            <!-- MODAL: Modificar Registro de Asistencia -->
            <Dialog v-model:visible="attendanceModalVisible" modal header="Modificar registro de asistencia"
                :style="{ width: '30rem' }">
                <div v-if="currentDayForEdit">
                    <p class="mb-4 text-gray-600 dark:text-gray-400">
                        Editando asistencia para <span class="font-bold">{{ currentEmployeeForEdit.name }}</span> del
                        día <span class="font-bold">{{ currentDayForEdit.date_formatted }}</span>.
                    </p>
                    <div class="flex flex-col gap-4">
                        <div class="flex flex-col gap-2">
                            <label for="entry_time">Hora de entrada</label>
                            <DatePicker v-model="attendanceForm.entry_time" timeOnly hourFormat="12" fluid />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label for="exit_time">Hora de salida</label>
                            <DatePicker v-model="attendanceForm.exit_time" timeOnly hourFormat="12" fluid />
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-6">
                    <Button type="button" label="Cancelar" severity="secondary"
                        @click="attendanceModalVisible = false"></Button>
                    <Button type="button" label="Guardar" @click="saveAttendance"
                        :loading="attendanceForm.processing"></Button>
                </div>
            </Dialog>

            <Popover ref="op">
                <div class="w-[300px]">
                    <h3 class="font-semibold text-[#3f3f3f] text-base rounded-md bg-[#f8f8f8] px-3 m-0">Resumen de
                        descansos</h3>
                    <div v-if="selectedBreaks.length">
                        <div v-for="(breakItem, index) in selectedBreaks" :key="index"
                            class="group flex justify-between items-center text-sm mb-1 text-gray-600">
                            <span><b>Descanso {{ index + 1 }}:</b> {{ breakItem.start }} - {{ breakItem.end }}</span>
                            <div class="flex items-center w-1/3">
                                <i class="pi pi-arrow-right !text-xs mx-2"></i>
                                <span class="font-medium text-[#3f3f3f] flex-shrink-0">{{ breakItem.duration }}
                                    min</span>
                                <div class="opacity-0 group-hover:opacity-100 transition-opacity">
                                    <Button icon="pi pi-pencil" text rounded size="small"
                                        @click="openBreakEditModal(breakItem, currentDayForBreakSummary.date)" />
                                    <Button icon="pi pi-trash" text rounded size="small" severity="danger"
                                        @click="confirmDeleteBreak(breakItem)" />
                                </div>
                            </div>
                        </div>
                        <Divider layout="horizontal" />
                        <div class="flex justify-end font-bold text-[#3f3f3f]">
                            <span>Total: {{ totalSelectedBreakTime }} min</span>
                        </div>
                    </div>
                    <div v-else>
                        <p class="text-sm text-gray-500">No se registraron descansos.</p>
                    </div>
                </div>
            </Popover>

            <!-- ✨ MODAL PARA EDITAR DESCANSO -->
            <Dialog v-model:visible="breakModalVisible" modal header="Editar Descanso" :style="{ width: '30rem' }">
                <p class="mb-4 text-gray-600">Editando descanso del día {{ currentDayForBreakEdit }}.</p>
                <div class="flex flex-col gap-4">
                    <div class="flex flex-col gap-2">
                        <label>Hora de inicio</label>
                        <DatePicker v-model="breakForm.start_time" timeOnly hourFormat="12" fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label>Hora de fin</label>
                        <DatePicker v-model="breakForm.end_time" timeOnly hourFormat="12" fluid />
                    </div>
                </div>
                <template #footer>
                    <Button label="Cancelar" severity="secondary" @click="breakModalVisible = false" outlined />
                    <Button label="Guardar" @click="saveBreak" :loading="breakForm.processing" />
                </template>
            </Dialog>

            <Menu ref="menu" :model="selectedDayMenu" :popup="true" />

        </div>
    </AppLayout>
</template>