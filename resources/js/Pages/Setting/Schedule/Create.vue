<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { useToast } from 'primevue/usetoast';

const props = defineProps({ branches: Array, errors: Object });
const home = ref({ icon: 'pi pi-home', url: route('dashboard') });
const items = ref([{ label: 'Configuraciones' }, { label: 'Horarios', url: route('settings.schedules.index') }, { label: 'Crear horario' }]);
const Toast = useToast();

// Función para convertir 'HH:mm' a un objeto Date que DatePicker entiende
const timeStringToDate = (timeString) => {
    if (!timeString) return null;
    const [hours, minutes] = timeString.split(':');
    const date = new Date();
    date.setHours(parseInt(hours, 10), parseInt(minutes, 10), 0);
    return date;
};

const form = useForm({
    name: '',
    branch_ids: [],
    details: [
        { day_of_week: 1, day_name: 'Lunes', is_active: true, start_time: timeStringToDate('09:00'), end_time: timeStringToDate('18:00'), meal_minutes: 60 },
        { day_of_week: 2, day_name: 'Martes', is_active: true, start_time: timeStringToDate('09:00'), end_time: timeStringToDate('18:00'), meal_minutes: 60 },
        { day_of_week: 3, day_name: 'Miércoles', is_active: true, start_time: timeStringToDate('09:00'), end_time: timeStringToDate('18:00'), meal_minutes: 60 },
        { day_of_week: 4, day_name: 'Jueves', is_active: true, start_time: timeStringToDate('09:00'), end_time: timeStringToDate('18:00'), meal_minutes: 60 },
        { day_of_week: 5, day_name: 'Viernes', is_active: true, start_time: timeStringToDate('09:00'), end_time: timeStringToDate('18:00'), meal_minutes: 60 },
        { day_of_week: 6, day_name: 'Sábado', is_active: false, start_time: null, end_time: null, meal_minutes: 0 },
        { day_of_week: 7, day_name: 'Domingo', is_active: false, start_time: null, end_time: null, meal_minutes: 0 },
    ]
});

// Computed property para la guía de horarios de sucursales
const selectedBranchesSchedules = computed(() => {
    return props.branches.filter(branch => form.branch_ids.includes(branch.id));
});

const totalHours = computed(() => {
    return form.details.reduce((total, day) => {
        if (day.is_active && day.start_time && day.end_time) {
            const diff = (day.end_time - day.start_time) / (1000 * 60 * 60); // diferencia en horas
            return total + (diff - (day.meal_minutes / 60));
        }
        return total;
    }, 0);
});

const formatBranchSchedule = (schedule) => {
    if (!schedule || !schedule.details.length) return 'No definido';
    const weekday = schedule.details.find(d => d.day_of_week === 1);
    return weekday ? `Lunes a Viernes: ${weekday.start_time} - ${weekday.end_time}` : 'No definido';
};

const submit = () => {
    form.transform(data => ({
        ...data,
        details: data.details.map(day => ({
            ...day,
            start_time: day.start_time ? new Date(day.start_time).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }) : null,
            end_time: day.end_time ? new Date(day.end_time).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }) : null,
        }))
    })).post(route('settings.schedules.store'), {
        onSuccess: () => {
            Toast.add({ severity: 'success', summary: 'Éxito', detail: 'Horario actualizado.', life: 3000 });
        },
        onError: (err) => {
            console.log(err)
        }
    });
};
</script>

<template>

    <Head title="Crear Horario" />
    <AppLayout>
        <Breadcrumb :home="home" :model="items" class="!bg-transparent" />
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <form @submit.prevent="submit">
                <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg p-6">
                    <h1 class="text-2xl font-bold mb-6">Crear nuevo horario</h1>
                    <div>
                        <InputLabel value="Nombre del horario" />
                        <InputText v-model="form.name" class="w-full md:w-1/2" />
                    </div>
                    <Divider />
                    <div>
                        <h2 class="font-semibold mb-2">Sucursales vinculadas</h2>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 p-4 border rounded-lg">
                            <div v-for="branch in branches" :key="branch.id" class="flex items-center">
                                <Checkbox v-model="form.branch_ids" :value="branch.id"
                                    :inputId="`branch-${branch.id}`" />
                                <label :for="`branch-${branch.id}`" class="ml-2">{{ branch.name }}</label>
                            </div>
                        </div>
                    </div>

                    <!-- Guía de horarios de atención de sucursales -->
                    <div v-if="selectedBranchesSchedules.length"
                        class="mt-6 p-4 border rounded-lg bg-gray-50 dark:bg-gray-700/50">
                        <h3 class="font-semibold mb-2 text-gray-700 dark:text-gray-300">Guía de horario de atención de
                            las sucursales</h3>
                        <div v-for="(branch, index) in selectedBranchesSchedules" :key="branch.id">
                            <p class="text-sm"><span class="font-medium">{{ branch.name }}:</span> {{
                                formatBranchSchedule(branch.schedules[0]) }}</p>
                            <Divider v-if="index < selectedBranchesSchedules.length - 1" class="my-2" />
                        </div>
                    </div>

                    <Divider />
                    <div>
                        <h2 class="font-semibold mb-2">Horario para {{ form.name || 'el nuevo horario' }}</h2>
                        <div class="border rounded-lg p-4 space-y-4">
                            <div class="grid grid-cols-6 gap-3 font-semibold text-sm">
                                <span>Día</span>
                                <span>Entrada</span>
                                <span>Salida</span>
                                <span>Comida (min)</span>
                                <span>Total Hrs</span>
                                <span class="text-center">Descanso</span>
                            </div>
                            <div v-for="(day, index) in form.details" :key="day.day_of_week"
                                class="grid grid-cols-6 gap-3 items-center">
                                <span>{{ day.day_name }}</span>
                                <div>
                                    <DatePicker v-model="day.start_time" timeOnly hourFormat="12"
                                        :disabled="!day.is_active" />
                                    <small v-if="form.errors[`details.${index}.start_time`]" class="text-red-500">{{
                                        form.errors[`details.${index}.start_time`] }}</small>
                                </div>
                                <div>
                                    <DatePicker v-model="day.end_time" timeOnly hourFormat="12"
                                        :disabled="!day.is_active" />
                                    <small v-if="form.errors[`details.${index}.end_time`]" class="text-red-500">{{
                                        form.errors[`details.${index}.end_time`] }}</small>
                                </div>
                                <InputNumber v-model="day.meal_minutes" :disabled="!day.is_active" fluid />
                                <span class="font-medium text-center">
                                    {{ day.is_active && day.start_time && day.end_time
                                        ? (((day.end_time - day.start_time) / 3600000) - (day.meal_minutes / 60)).toFixed(1)
                                        : '-' }}
                                </span>
                                <div class="text-center">
                                    <Checkbox v-model="day.is_active" :binary="true"
                                        :inputId="`active-${day.day_of_week}`" :value="false" />
                                </div>
                            </div>
                            <div class="text-right font-bold">Total de horas semanales: {{ totalHours.toFixed(2) }}
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-8">
                        <Link :href="route('settings.schedules.index')"><Button label="Cancelar" severity="secondary"
                            outlined /></Link>
                        <Button type="submit" label="Crear horario" :loading="form.processing" />
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>