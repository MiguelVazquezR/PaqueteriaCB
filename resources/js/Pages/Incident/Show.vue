<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { format } from 'date-fns';
import es from 'date-fns/locale/es';

// --- Props ---
const props = defineProps({
    period: Object,
    employeesData: Array,
    branches: Array,
    filters: Object,
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

// --- Methods ---
const formatDate = (dateString, formatStr = "EEEE, dd 'de' MMMM") => {
    if (!dateString) return '';
    return format(new Date(dateString + 'T00:00:00'), formatStr, { locale: es });
};

const filterBranch = (event) => {
    router.get(route('incidents.show', props.period.id), { branch_id: event.value }, { preserveState: true, preserveScroll: true });
};

const openCommentModal = (employee) => {
    currentEmployeeForComment.value = employee;
    commentText.value = employee.comments;
    commentModalVisible.value = true;
};

const saveComment = () => {
    // Lógica para guardar el comentario aquí...
    console.log('Guardando comentario para el empleado:', currentEmployeeForComment.value.id, 'Comentario:', commentText.value);
    commentModalVisible.value = false;
};

const toggleDayMenu = (event, day, employee) => {
    selectedDayMenu.value = [
        { label: 'Descanso', command: () => console.log('Añadir descanso', day, employee) },
        { label: 'Quitar retardo', command: () => console.log('Quitar retardo', day, employee) },
        { label: 'Modificar registro', command: () => console.log('Modificar', day, employee) },
        { separator: true },
        { label: 'Falta justificada', command: () => console.log('Falta justificada', day, employee) },
        { label: 'Incapacidad general', command: () => console.log('Incapacidad', day, employee) },
        // ... más opciones
    ];
    menu.value.toggle(event);
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
                        <Button icon="pi pi-chevron-left" text rounded />
                        <div>
                            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Semana {{ period.week_number }}</h1>
                            <p class="text-sm text-gray-500">{{ formatDate(period.start_date, 'dd MMM') }} - {{ formatDate(period.end_date, 'dd MMM yyyy') }}</p>
                        </div>
                        <Button icon="pi pi-chevron-right" text rounded />
                    </div>
                    <div class="flex items-center gap-2 w-full md:w-auto">
                        <Select v-model="selectedBranch" :options="branches" optionLabel="name" optionValue="id" placeholder="Todas las sucursales" class="w-full md:w-56" @change="filterBranch" />
                        <IconField class="w-full md:w-auto">
                            <InputText v-model="search" placeholder="Buscar" class="w-full" />
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
                <div v-for="employee in employeesData" :key="employee.id" class="bg-white dark:bg-gray-800 shadow-md rounded-lg">
                    <!-- Cabecera del empleado -->
                    <div class="flex justify-between items-center p-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-3">
                            <Avatar :image="employee.avatar_url" :label="employee.name[0]" shape="circle" size="large" />
                            <div>
                                <h2 class="font-bold text-gray-900 dark:text-gray-100">{{ employee.name }}</h2>
                                <p class="text-sm text-gray-500">N° {{ employee.employee_number }} • {{ employee.position }}</p>
                            </div>
                        </div>
                        <span class="text-sm font-semibold text-gray-600 dark:text-gray-300">{{ employee.branch_name }}</span>
                    </div>

                    <!-- Tabla de días -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-gray-50 dark:bg-gray-700 text-xs text-gray-700 dark:text-gray-400 uppercase">
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
                                <tr v-for="day in employee.daily_data" :key="day.date" class="border-b dark:border-gray-700">
                                    <td class="px-4 py-3 font-medium">{{ formatDate(day.date) }}</td>
                                    <td class="px-4 py-3" :class="{'text-yellow-500': day.entry_time === '12:30 am'}">{{ day.entry_time || '-' }}</td>
                                    <td class="px-4 py-3">{{ day.exit_time || '-' }}</td>
                                    <td class="px-4 py-3">{{ day.break_time }} <i class="pi pi-info-circle text-gray-400 ml-1"></i></td>
                                    <td class="px-4 py-3">{{ day.extra_time }}</td>
                                    <td class="px-4 py-3">{{ day.total_hours }}</td>
                                    <td class="px-4 py-3">
                                        <div v-if="day.incident" class="w-full">
                                            <Tag :value="day.incident" :severity="day.incident === 'Falta injustificada' ? 'danger' : 'warning'" class="w-full text-center" />
                                        </div>
                                        <Button v-else @click="toggleDayMenu($event, day, employee)" icon="pi pi-ellipsis-v" text rounded />
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
                        <p class="text-gray-600 dark:text-gray-400 mt-1">{{ employee.comments || 'Sin comentarios.' }}</p>
                    </div>
                </div>
            </div>

            <!-- MODAL: Agregar Comentarios -->
            <Dialog v-model:visible="commentModalVisible" modal header="Agregar comentarios" :style="{ width: '30rem' }">
                <div class="flex flex-col gap-2">
                    <label for="comment_text" class="font-semibold">Comentarios*</label>
                    <Textarea id="comment_text" v-model="commentText" rows="5" placeholder="Escribe los comentarios relevantes para la nómina" autoResize />
                </div>
                <div class="flex justify-end gap-2 mt-6">
                    <Button type="button" label="Cancelar" severity="secondary" @click="commentModalVisible = false"></Button>
                    <Button type="button" label="Guardar" @click="saveComment"></Button>
                </div>
            </Dialog>
            
            <Menu ref="menu" :model="selectedDayMenu" :popup="true" />

        </div>
    </AppLayout>
</template>