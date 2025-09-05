<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { PrimeIcons } from '@primevue/core/api';
import { useToast } from 'primevue';
import { format } from 'date-fns'; // Necesario para la transformación de datos

// --- Refs and State ---
const toast = useToast();
const home = ref({ icon: 'pi pi-home', url: route('dashboard') });
const items = ref([
    { label: 'Sucursales', url: route('branches.index'), icon: PrimeIcons.BUILDING },
    { label: 'Crear sucursal' }
]);

const timezones = ref([
    { label: '(GMT-6) Ciudad de México, Guadalajara', value: 'America/Mexico_City' },
    { label: '(GMT-5) Cancún, Bogotá', value: 'America/Bogota' },
    { label: '(GMT-7) Tijuana, La Paz', value: 'America/Tijuana' },
]);

// --- CAMBIO: --- Helper para convertir strings 'HH:mm' a objetos Date.
const createTimeObject = (timeString) => {
    if (!timeString) return null;
    const [hours, minutes] = timeString.split(':');
    const date = new Date();
    date.setHours(parseInt(hours, 10), parseInt(minutes, 10), 0, 0);
    return date;
};

const form = useForm({
    name: '',
    address: '',
    phone: '',
    settings: {
        timezone: 'America/Mexico_City',
    },
    // --- CAMBIO: --- El horario ahora se inicializa con objetos Date.
    schedule: {
        1: { day_name: 'Lunes a viernes', is_active: true, start_time: createTimeObject('09:00'), end_time: createTimeObject('18:00') },
        6: { day_name: 'Sábado', is_active: true, start_time: createTimeObject('09:00'), end_time: createTimeObject('13:00') },
        7: { day_name: 'Domingo', is_active: false, start_time: null, end_time: null },
    }
});

// --- CAMBIO: --- Se transforman las fechas a formato HH:mm antes de enviarlas al backend.
form.transform((data) => {
    // Se crea una copia profunda para no alterar el estado del formulario en la UI.
    const transformedData = JSON.parse(JSON.stringify(data));

    for (const key in transformedData.schedule) {
        const day = transformedData.schedule[key];
        // Si el día está activo y tiene hora, se formatea. Si no, se envía null.
        day.start_time = day.is_active && day.start_time ? format(new Date(day.start_time), 'HH:mm') : null;
        day.end_time = day.is_active && day.end_time ? format(new Date(day.end_time), 'HH:mm') : null;
    }
    return transformedData;
});


// --- Methods ---
function showSuccess() {
    toast.add({ severity: 'success', summary: 'Éxito', detail: 'Sucursal creada correctamente', life: 3000 });
}

// El método submit ahora usa el 'form' transformado automáticamente.
const submit = () => {
    form.post(route('branches.store'), {
        onSuccess: () => {
            showSuccess();
        },
    });
};

</script>

<template>

    <Head title="Crear Sucursal" />

    <AppLayout>
        <Breadcrumb :home="home" :model="items" class="!bg-transparent" />
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <form @submit.prevent="submit">
                <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg px-6 py-4">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Crear sucursal</h1>

                    <!-- === INFORMACIÓN GENERAL === -->
                    <div class="flex items-center space-x-3 mb-2">
                        <i class="pi pi-building text-gray-600 dark:text-gray-400 mt-3"></i>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Información general</h2>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <InputLabel for="name" value="Nombre de la sucursal*" />
                            <InputText id="name" v-model="form.name" class="w-full" :invalid="!!form.errors.name"
                                placeholder="Ej. Vallarta" />
                            <small v-if="form.errors.name" class="text-red-500 mt-1">{{ form.errors.name }}</small>
                        </div>
                        <div>
                            <InputLabel for="phone" value="Teléfono de la sucursal" />
                            <InputText id="phone" v-model="form.phone" class="w-full" placeholder="Ej. 3349382608" />
                        </div>
                        <div class="md:col-span-2">
                            <InputLabel for="address" value="Domicilio*" />
                            <InputText id="address" v-model="form.address" class="w-full"
                                :invalid="!!form.errors.address" placeholder="Ej. Avenida Vallarta..." />
                            <small v-if="form.errors.address" class="text-red-500 mt-1">{{ form.errors.address
                                }}</small>
                        </div>
                    </div>
                    <Divider />

                    <!-- === CONFIGURACIÓN DE ASISTENCIA === -->
                    <div class="flex items-center space-x-3 mb-2">
                        <i class="pi pi-clock text-gray-600 dark:text-gray-400 mt-3"></i>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Configuración de asistencia
                        </h2>
                    </div>
                    <div>
                        <InputLabel for="timezone" value="Zona horaria" />
                        <Select id="timezone" v-model="form.settings.timezone" :options="timezones" optionLabel="label"
                            optionValue="value" class="w-full md:w-1/2" />
                    </div>
                    <Divider />

                    <!-- === HORARIO DE SERVICIO === -->
                    <div class="flex items-center space-x-3 mb-2">
                        <i class="pi pi-calendar text-gray-600 dark:text-gray-400 mt-3"></i>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Horario de servicio</h2>
                    </div>
                    <p class="text-sm text-gray-500 mb-4">Define el horario de apertura y cierre de la sucursal. Esto
                        ayuda a validar los registros de asistencia.</p>

                    <div class="border rounded-lg p-2 space-y-4">
                        <div
                            class="hidden md:grid grid-cols-4 gap-4 font-semibold text-sm bg-[#f8f8f8] dark:bg-gray-900 rounded-lg py-1 px-2 text-gray-600 dark:text-gray-400">
                            <span>Día</span>
                            <span>Apertura</span>
                            <span>Cierre</span>
                            <span class="text-center">Día no laborable</span>
                        </div>
                        <div v-for="(day, key) in form.schedule" :key="key"
                            class="grid grid-cols-2 md:grid-cols-4 gap-4 items-center px-2">
                            <span class="font-medium col-span-2 md:col-span-1">{{ day.day_name }}</span>
                            <DatePicker v-model="day.start_time" :disabled="!day.is_active" showIcon fluid
                                iconDisplay="input" class="w-full" timeOnly hourFormat="12">
                                <template #inputicon="slotProps">
                                    <i class="pi pi-clock" @click="slotProps.clickCallback" />
                                </template>
                            </DatePicker>
                            <DatePicker v-model="day.end_time" :disabled="!day.is_active" showIcon fluid
                                iconDisplay="input" class="w-full" timeOnly hourFormat="12">
                                <template #inputicon="slotProps">
                                    <i class="pi pi-clock" @click="slotProps.clickCallback" />
                                </template>
                            </DatePicker>
                            <div class="flex justify-center items-center">
                                <Checkbox :modelValue="!day.is_active" @update:modelValue="day.is_active = !$event"
                                    :binary="true" :inputId="`closed-${key}`" />
                                <label :for="`closed-${key}`" class="ml-2">Cerrado</label>
                            </div>
                        </div>
                    </div>

                    <!-- === ACCIONES === -->
                    <div class="flex justify-end gap-3 mt-8">
                        <Link :href="route('branches.index')">
                        <Button label="Cancelar" severity="secondary" outlined />
                        </Link>
                        <Button type="submit" label="Crear sucursal" :loading="form.processing" />
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
