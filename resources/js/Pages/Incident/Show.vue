<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { debounce } from 'lodash';

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
    { label: 'Incidencias', url: route('incidents.index') },
    { label: `Semana ${props.period.week_number}` }
]);
const search = ref(props.filters.search);
const selectedBranch = ref(props.filters.branch_id);
const commentModalVisible = ref(false);
const currentEmployeeForComment = ref(null);
const commentText = ref('');
const menu = ref();
const selectedDayMenu = ref(null);

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


// --- Methods ---
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

const toggleLateStatus = (day) => {
    router.post(route('incidents.toggleLateStatus'), {
        entry_id: day.entry_id,
    }, {
        preserveScroll: true,
    });
};

const toggleDayMenu = (event, day, employee) => {
    let menuItems = [];

    // Lógica para "Quitar/Poner retardo"
    if (day.late_minutes && !day.late_ignored) {
        menuItems.push({
            label: 'Quitar retardo',
            icon: 'pi pi-check-circle',
            command: () => toggleLateStatus(day)
        });
    }
    if (day.late_minutes && day.late_ignored) {
        menuItems.push({
            label: 'Poner retardo',
            icon: 'pi pi-exclamation-circle',
            command: () => toggleLateStatus(day)
        });
    }

    // Lógica para incidencias
    if (day.incident) {
        menuItems.push({
            label: 'Quitar incidencia',
            icon: 'pi pi-times-circle',
            class: 'p-menuitem-text-danger',
            command: () => removeIncident(employee, day)
        });
    }

    if (menuItems.length > 0) {
        menuItems.push({ separator: true });
    }

    // Opciones generales
    props.incidentTypes.forEach(type => {
        menuItems.push({
            label: type.name,
            command: () => addIncident(employee, day, type.id)
        });
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
                        <div>
                            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Semana {{ period.week_number
                            }}</h1>
                            <p class="text-sm text-gray-500">{{ period.start_date_formatted_short }} - {{
                                period.end_date_formatted_full }}</p>
                        </div>
                        <Button icon="pi pi-chevron-right" text rounded :disabled="!navigation.next_period_id"
                            @click="navigation.next_period_id && router.get(route('incidents.show', navigation.next_period_id))" />
                    </div>
                    <div class="flex items-center gap-2 w-full md:w-auto">
                        <Select v-model="selectedBranch" :options="branches" optionLabel="name" optionValue="id"
                            placeholder="Todas las sucursales" class="w-full md:w-56" showClear />
                        <IconField class="w-full md:w-auto">
                            <InputText v-model="search" placeholder="Buscar empleado" class="w-full" />
                            <InputIcon class="pi pi-search" />
                        </IconField>
                    </div>
                    <div class="flex items-center gap-2 mt-4 md:mt-0">
                        <Button label="Imprimir incidencias" severity="secondary" outlined />
                        <Button label="Generar pre-nómina" icon="pi pi-angle-down" iconPos="right" />
                    </div>
                </div>
            </div>

            <!-- === LISTA DE EMPLEADOS === -->
            <div class="space-y-6">
                <div v-for="employee in employeesData" :key="employee.id"
                    class="bg-white dark:bg-gray-800 shadow-md rounded-lg">
                    <!-- Cabecera del empleado -->
                    <div class="flex justify-between items-center p-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-3">
                            <Avatar :image="employee.avatar_url" :label="employee.name[0]" shape="circle" size="large"
                                class="!bg-[#d9d9d9]" />
                            <div>
                                <h2 class="font-bold text-gray-900 dark:text-gray-100">{{ employee.name }}</h2>
                                <p class="text-sm text-gray-500">N° {{ employee.employee_number }} • {{
                                    employee.position }}</p>
                            </div>
                        </div>
                        <span class="text-sm font-semibold text-gray-600 dark:text-gray-300">{{ employee.branch_name
                            }}</span>
                    </div>

                    <!-- Tabla de días -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead
                                class="bg-gray-50 dark:bg-gray-700 text-xs text-gray-700 dark:text-gray-400 uppercase">
                                <tr>
                                    <th class="px-4 py-3">Día</th>
                                    <th class="px-4 py-3">Entrada</th>
                                    <th class="px-4 py-3">Salida</th>
                                    <th class="px-4 py-3">T. Descanso</th>
                                    <th class="px-4 py-3">T. Extra</th>
                                    <th class="px-4 py-3">Horas totales</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="day in employee.daily_data" :key="day.date"
                                    class="border-b dark:border-gray-700">
                                    <td class="px-4 py-3 font-medium">{{ day.date_formatted }}</td>
                                    <!-- Si HAY incidencia -->
                                    <template v-if="day.incident">
                                        <td colspan="5" class="px-4 py-3">
                                            <Tag :value="day.incident" :severity="getIncidentSeverity(day.incident)"
                                                class="w-full text-center" />
                                        </td>
                                    </template>
                                    <!-- Si NO HAY incidencia -->
                                    <template v-else>
                                        <td class="px-4 py-3">
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
                                        <td class="px-4 py-3">{{ day.exit_time || '-' }}</td>
                                        <td class="px-4 py-3">{{ day.break_time }} <i
                                                class="pi pi-info-circle text-gray-400 ml-1"></i></td>
                                        <td class="px-4 py-3">{{ day.extra_time }}</td>
                                        <td class="px-4 py-3">{{ day.total_hours }}</td>
                                    </template>
                                    <!-- Columna 7: Opciones (Siempre visible) -->
                                    <td class="px-4 py-3 text-center">
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
                        <p class="text-gray-600 dark:text-gray-400 mt-1">{{ employee.comments || 'Sin comentarios.' }}
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

            <Menu ref="menu" :model="selectedDayMenu" :popup="true" />

        </div>
    </AppLayout>
</template>