<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import CruzIcon from '@/Components/Icons/CruzIcon.vue';
import FacialIcon from '@/Components/Icons/FacialIcon.vue';
import { PrimeIcons } from '@primevue/core/api';
import { format } from 'date-fns';

// --- Props ---
const props = defineProps({
    user: Object,
    branches: Array,
    roles: Array,
    schedules: Array,
    errors: Object,
});

// --- Refs and State ---
const home = ref({ icon: 'pi pi-home', url: route('dashboard') });
const items = ref([
    { label: 'Usuarios', url: route('users.index'), icon: PrimeIcons.USER },
    { label: 'Editar usuario' }
]);
const op = ref(); // Referencia para el Popover
const scheduleOp = ref(); // Ref para el popover del horario
const imagePreview = ref(props.user.avatar_url || null); // ✨ Usa la URL del avatar si existe
const fileUploadRef = ref(null);
const relationships = [
    'Madre/Padre', 'Hermano/Hermana', 'Esposo/Esposa', 'Tío/Tía', 'Abuelo/Abuela', 'Otro',
];

// Corregir el manejo de fechas que vienen como string desde el backend
const parseDate = (dateString) => {
    return dateString ? new Date(dateString) : null;
};

// Inicializamos el formulario con los datos del usuario y su empleado
const form = useForm({
    _method: 'PUT',
    // Info Personal
    first_name: props.user.employee?.first_name || '',
    last_name: props.user.employee?.last_name || '',
    phone: props.user.employee?.phone || '',
    birth_date: parseDate(props.user.employee?.birth_date),
    address: props.user.employee?.address || '',

    // Info Laboral
    employee_number: props.user.employee?.employee_number || '',
    hire_date: parseDate(props.user.employee?.hire_date),
    branch_id: props.user.employee?.branch_id || null,
    position: props.user.employee?.position || '',
    schedule_id: (props.user.employee?.schedules && props.user.employee.schedules.length > 0) ? props.user.employee.schedules[0].id : null,
    curp: props.user.employee?.curp || '',
    rfc: props.user.employee?.rfc || '',
    nss: props.user.employee?.nss || '',
    base_salary: props.user.employee?.base_salary || null,
    is_active: props.user.employee?.is_active ?? true,
    termination_date: parseDate(props.user.employee?.termination_date),
    termination_reason: props.user.employee?.termination_reason || '',

    // Contacto Emergencia
    emergency_contact_name: props.user.employee?.emergency_contact_name || '',
    emergency_contact_phone: props.user.employee?.emergency_contact_phone || '',
    emergency_contact_relationship: props.user.employee?.emergency_contact_relationship || '',

    // Acceso al sistema
    email: props.user.email,
    password: '',
    role_id: props.user.roles[0]?.id || null,

    // Imagen
    facial_image: null,
    delete_photo: false,
});

// --- Computed Properties ---
const selectedRolePermissions = computed(() => {
    if (!form.role_id) return [];
    const selected = props.roles.find(r => r.id === form.role_id);
    return selected ? selected.permissions : [];
});

const selectedSchedule = computed(() => {
    if (!form.schedule_id) return null;
    return props.schedules.find(s => s.id === form.schedule_id);
});

// --- Methods ---
/**
 * ✨ SOLUCIÓN: Usamos FileReader para la vista previa, igual que en Create.vue.
 * Esto asegura que funcione en producción.
 */
const onFileSelect = (event) => {
    const file = event.files[0];
    if (file) {
        form.facial_image = file;
        form.delete_photo = false; // Si se sube una nueva, no se borra la anterior.
        
        const reader = new FileReader();
        reader.onload = (e) => {
            imagePreview.value = e.target.result;
        };
        reader.readAsDataURL(file);
    }
};

/**
 * ✨ MEJORA: Lógica para quitar la imagen existente o la nueva.
 */
const clearImage = (clearCallback) => {
    form.facial_image = null;
    imagePreview.value = null;
    form.delete_photo = true; // Se marca para que el backend elimine la foto guardada.

    if (clearCallback) {
        clearCallback();
    }
    if (fileUploadRef.value) {
        fileUploadRef.value.clear();
    }
};


const submit = () => {
    // Usamos POST porque los navegadores no soportan subida de archivos con PUT nativamente.
    // Inertia maneja esto automáticamente si añadimos _method: 'PUT' al formulario.
    form.post(route('users.update', props.user.id), {
        forceFormData: true, // Asegura que se envíe como multipart/form-data
    });
};

const togglePermissionsPopover = (event) => {
    if (form.role_id) {
        op.value.toggle(event);
    }
};

const toggleSchedulePopover = (event) => {
    if (form.schedule_id) {
        scheduleOp.value.toggle(event);
    }
};

const formatTime = (timeString) => {
    if (!timeString) return '';
    const [hours, minutes] = timeString.split(':');
    const date = new Date();
    date.setHours(hours, minutes);
    return format(date, 'hh:mm a');
};

const getDayFromSchedule = (dayOfWeek) => {
    return selectedSchedule.value?.details.find(d => d.day_of_week === dayOfWeek);
};
</script>

<template>

    <Head title="Editar Usuario" />

    <AppLayout>
        <Breadcrumb :home="home" :model="items" class="!bg-transparent" />
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <form @submit.prevent="submit">
                <div class="bg-white dark:bg-neutral-900 shadow-md rounded-lg px-6 py-4">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Editar Usuario</h1>

                    <!-- === INFORMACIÓN PERSONAL === -->
                    <div class="flex items-center space-x-3 mb-4">
                        <i class="pi pi-user text-xl text-gray-600 dark:text-gray-400 mt-3"></i>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Información personal</h2>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <InputLabel for="first_name" value="Nombre(s)*" />
                            <InputText id="first_name" v-model="form.first_name" class="w-full"
                                :invalid="!!form.errors.first_name" />
                            <small v-if="form.errors.first_name" class="text-red-500 mt-1">{{ form.errors.first_name
                                }}</small>
                        </div>
                        <div>
                            <InputLabel for="last_name" value="Apellidos*" />
                            <InputText id="last_name" v-model="form.last_name" class="w-full"
                                :invalid="!!form.errors.last_name" />
                            <small v-if="form.errors.last_name" class="text-red-500 mt-1">{{ form.errors.last_name
                                }}</small>
                        </div>
                        <div>
                            <InputLabel for="phone" value="Teléfono" />
                            <InputText id="phone" v-model="form.phone" class="w-full" />
                        </div>
                        <div>
                            <InputLabel for="birth_date" value="Fecha de nacimiento" />
                            <DatePicker id="birth_date" v-model="form.birth_date" dateFormat="dd/mm/yy" showIcon fluid
                                iconDisplay="input" class="w-full" />
                        </div>
                        <div class="md:col-span-2">
                            <InputLabel for="address" value="Domicilio" />
                            <InputText id="address" v-model="form.address" class="w-full" />
                        </div>
                    </div>
                    <Divider />

                    <!-- === INFORMACIÓN LABORAL === -->
                    <div class="flex items-center space-x-3 mb-4">
                        <i class="pi pi-briefcase text-gray-600 dark:text-gray-400 mt-3"></i>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Información laboral</h2>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <InputLabel for="employee_number" value="N° de empleado*" />
                            <InputText id="employee_number" v-model="form.employee_number" class="w-full"
                                :invalid="!!form.errors.employee_number" />
                            <small v-if="form.errors.employee_number" class="text-red-500 mt-1">{{
                                form.errors.employee_number }}</small>
                        </div>
                        <div>
                            <InputLabel for="hire_date" value="Fecha de contratación*" />
                            <DatePicker id="hire_date" v-model="form.hire_date" dateFormat="dd/mm/yy" showIcon fluid
                                iconDisplay="input" :invalid="!!form.errors.hire_date" class="w-full" />
                            <small v-if="form.errors.hire_date" class="text-red-500 mt-1">{{ form.errors.hire_date
                                }}</small>
                        </div>
                        <div>
                            <InputLabel for="branch_id" value="Sucursal asignada*" />
                            <Select id="branch_id" v-model="form.branch_id" :options="branches" optionLabel="name"
                                optionValue="id" size="large" placeholder="Selecciona la sucursal"
                                :invalid="!!form.errors.branch_id" class="w-full" />
                            <small v-if="form.errors.branch_id" class="text-red-500 mt-1">{{ form.errors.branch_id
                                }}</small>
                        </div>
                        <div>
                            <InputLabel for="position" value="Puesto*" />
                            <InputText id="position" v-model="form.position" class="w-full"
                                :invalid="!!form.errors.position" />
                            <small v-if="form.errors.position" class="text-red-500 mt-1">{{ form.errors.position
                                }}</small>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <InputLabel for="schedule_id" value="Horario*" />
                                <i class="pi pi-book cursor-pointer text-gray-400" @click="toggleSchedulePopover"></i>
                            </div>
                            <Select id="schedule_id" v-model="form.schedule_id" :options="schedules" optionLabel="name"
                                optionValue="id" placeholder="Selecciona un horario" class="w-full"
                                :invalid="!!form.errors.schedule_id" />
                            <small v-if="form.errors.schedule_id" class="text-red-500 mt-1">{{ form.errors.schedule_id
                                }}</small>
                        </div>
                        <div>
                            <InputLabel for="curp" value="CURP" />
                            <InputText id="curp" v-model="form.curp" class="w-full" />
                        </div>
                        <div>
                            <InputLabel for="rfc" value="RFC" />
                            <InputText id="rfc" v-model="form.rfc" class="w-full" />
                        </div>
                        <div>
                            <InputLabel for="nss" value="Número de seguridad social" />
                            <InputText id="nss" v-model="form.nss" class="w-full" />
                        </div>
                        <div>
                            <InputLabel for="base_salary" value="Salario base mensual*" />
                            <InputNumber id="base_salary" v-model="form.base_salary" mode="currency" currency="MXN"
                                locale="es-MX" class="w-full" :invalid="!!form.errors.base_salary" />
                            <small v-if="form.errors.base_salary" class="text-red-500 mt-1">{{ form.errors.base_salary
                                }}</small>
                        </div>
                        <div>
                            <InputLabel for="is_active" value="Estatus*" />
                            <Select id="is_active" v-model="form.is_active"
                                :options="[{ label: 'Activo', value: true }, { label: 'Inactivo', value: false }]"
                                optionLabel="label" optionValue="value" size="large" class="w-full" />
                        </div>
                        <template v-if="!form.is_active">
                            <div>
                                <InputLabel for="termination_date" value="Fecha de baja*" />
                                <DatePicker id="termination_date" v-model="form.termination_date" dateFormat="dd/mm/yy"
                                    showIcon fluid iconDisplay="input" class="w-full"
                                    :invalid="!!form.errors.termination_date" />
                                <small v-if="form.errors.termination_date" class="text-red-500 mt-1">{{
                                    form.errors.termination_date }}</small>
                            </div>
                            <div>
                                <InputLabel for="termination_reason" value="Motivo de baja*" />
                                <InputText id="termination_reason" v-model="form.termination_reason" class="w-full"
                                    :invalid="!!form.errors.termination_reason" />
                                <small v-if="form.errors.termination_reason" class="text-red-500 mt-1">{{
                                    form.errors.termination_reason }}</small>
                            </div>
                        </template>
                    </div>
                    <Divider />

                    <!-- === CONTACTO DE EMERGENCIA === -->
                    <div class="flex items-center gap-3 mb-4">
                        <CruzIcon class="text-gray-600 dark:text-gray-400 size-4 mt-2" />
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Contacto de emergencia</h2>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <InputLabel for="emergency_contact_name" value="Nombre completo" />
                            <InputText id="emergency_contact_name" v-model="form.emergency_contact_name"
                                class="w-full" />
                        </div>
                        <div>
                            <InputLabel for="emergency_contact_phone" value="Teléfono" />
                            <InputText id="emergency_contact_phone" v-model="form.emergency_contact_phone"
                                class="w-full" />
                        </div>
                        <div>
                            <InputLabel for="emergency_contact_relationship" value="Parentesco" />
                            <Select id="emergency_contact_relationship" v-model="form.emergency_contact_relationship"
                                :options="relationships" size="large" placeholder="Selecciona el parentesco"
                                class="w-full" />
                        </div>
                    </div>
                    <Divider />

                    <!-- === REGISTRO FACIAL === -->
                    <div class="flex items-center gap-3 mb-4">
                        <FacialIcon class="text-gray-600 dark:text-gray-400 size-5 mt-2" />
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Registro facial para
                            asistencia</h2>
                    </div>
                    <FileUpload ref="fileUploadRef" name="facial_image" @select="onFileSelect" :showUploadButton="false"
                        :showCancelButton="false" accept="image/*" :maxFileSize="1024000">
                        <template #header="{ chooseCallback }">
                            <Button label="Subir imagen" icon="pi pi-upload" outlined @click="chooseCallback" />
                        </template>
                        <template #content="{ clearCallback }">
                             <!-- ✨ El v-if ahora comprueba imagePreview en lugar de files.length -->
                            <div v-if="imagePreview"
                                class="flex flex-col items-center justify-center gap-4 p-8 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg">
                                <img :src="imagePreview" alt="Vista previa"
                                    class="w-48 h-48 rounded-full object-cover" />
                                <Button label="Quitar imagen" icon="pi pi-times" severity="danger" outlined
                                    @click="() => clearImage(clearCallback)" />
                            </div>
                            <div v-else
                                class="flex flex-col items-center justify-center gap-3 p-8 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg text-center">
                                <i class="pi pi-image text-5xl text-gray-400 dark:text-gray-500"></i>
                                <p class="text-gray-500 dark:text-gray-400">Sube o arrastra una nueva foto para actualizar el registro.</p>
                            </div>
                        </template>
                    </FileUpload>
                    <small v-if="form.errors.facial_image" class="text-red-500 mt-1">{{ form.errors.facial_image
                        }}</small>
                    <Divider />

                    <!-- === ACCESO AL SISTEMA === -->
                    <div class="flex items-center gap-3 mb-4">
                        <i class="pi pi-key text-gray-600 dark:text-gray-400 mt-3"></i>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Acceso al sistema</h2>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                        <div>
                            <InputLabel for="email" value="Correo electrónico*" />
                            <InputText id="email" v-model="form.email" type="email" class="w-full"
                                :invalid="!!form.errors.email" autocomplete="off" />
                            <small v-if="form.errors.email" class="text-red-500 mt-1">{{ form.errors.email }}</small>
                        </div>
                        <div>
                            <InputLabel for="password" value="Contraseña" />
                            <Password id="password" v-model="form.password" :feedback="false" toggleMask class="w-full"
                                inputClass="w-full" :invalid="!!form.errors.password" autocomplete="new-password" />
                            <small class="text-gray-500 mt-1">Deja en blanco para no cambiar la contraseña.</small>
                            <small v-if="form.errors.password" class="text-red-500 block mt-1">{{ form.errors.password
                                }}</small>
                        </div>
                        <div class="md:col-span-2">
                            <div class="flex items-center gap-2">
                                <InputLabel for="role_id" value="Rol en el sistema*" />
                                <i class="pi pi-book cursor-pointer text-gray-400"
                                    @click="togglePermissionsPopover"></i>
                            </div>
                            <Select id="role_id" v-model="form.role_id" :options="roles" optionLabel="name"
                                optionValue="id" placeholder="Selecciona un rol" class="w-full"
                                :invalid="!!form.errors.role_id" />
                            <small v-if="form.errors.role_id" class="text-red-500 mt-1">{{ form.errors.role_id
                                }}</small>
                        </div>
                    </div>

                    <!-- === ACCIONES === -->
                    <div class="flex justify-end gap-3 mt-8">
                        <Link :href="route('users.index')">
                        <Button label="Cancelar" severity="secondary" outlined />
                        </Link>
                        <Button type="submit" label="Guardar Cambios" :loading="form.processing" />
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>

    <!-- Popovers -->
    <Popover ref="op">
        <div class="p-2 w-72">
            <h3 class="font-bold text-lg mb-2">Detalles del rol</h3>
            <p class="font-semibold text-[#3f3f3f] mb-2">{{roles.find(r => r.id === form.role_id)?.name}}</p>
            <div class="border rounded-md p-2 max-h-48 overflow-y-auto">
                <p v-if="!selectedRolePermissions.length" class="text-sm text-gray-500">Este rol no tiene permisos
                    asignados.</p>
                <ul v-else class="space-y-1">
                    <li v-for="permission in selectedRolePermissions" :key="permission.name"
                        class="flex items-center gap-2 text-sm">
                        <i class="pi pi-check text-green-500"></i>
                        <span class="capitalize">{{ permission.name.replace(/_/g, ' ') }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </Popover>
    
    <Popover ref="scheduleOp">
        <div class="p-4 w-96">
            <h3 class="font-bold text-lg mb-2">Detalles del horario</h3>
            <div v-if="selectedSchedule">
                <h4 class="font-semibold text-base mb-2">Horario de "{{ selectedSchedule.name }}"</h4>
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 text-xs uppercase">
                        <tr>
                            <th class="px-2 py-1">Día</th>
                            <th class="px-2 py-1">Entrada</th>
                            <th class="px-2 py-1">Salida</th>
                            <th class="px-2 py-1">Comida (min)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="dayName in ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo']"
                            :key="dayName">
                            <td class="px-2 py-1 font-medium">{{ dayName }}</td>
                            <template
                                v-if="getDayFromSchedule(['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'].indexOf(dayName) + 1)">
                                <td class="px-2 py-1">{{ formatTime(getDayFromSchedule(['Lunes', 'Martes', 'Miércoles',
                                    'Jueves', 'Viernes', 'Sábado', 'Domingo'].indexOf(dayName) + 1).start_time) }}</td>
                                <td class="px-2 py-1">{{ formatTime(getDayFromSchedule(['Lunes', 'Martes', 'Miércoles',
                                    'Jueves', 'Viernes', 'Sábado', 'Domingo'].indexOf(dayName) + 1).end_time) }}</td>
                                <td class="px-2 py-1">{{ getDayFromSchedule(['Lunes', 'Martes', 'Miércoles', 'Jueves',
                                    'Viernes', 'Sábado', 'Domingo'].indexOf(dayName) + 1).meal_minutes }}</td>
                            </template>
                            <template v-else>
                                <td colspan="3" class="px-2 py-1 text-center bg-green-100 text-green-700 rounded">
                                    Descanso</td>
                            </template>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </Popover>
</template>