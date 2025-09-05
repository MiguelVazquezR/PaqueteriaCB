<script setup>
import { ref, computed, watch } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useConfirm } from "primevue/useconfirm";
import { debounce } from 'lodash';
import InputLabel from '@/Components/InputLabel.vue';

// --- Props ---
const props = defineProps({
    holidays: Object,
    filters: Object,
    branches: Array,
    errors: Object,
});

// --- Refs and State ---
const confirm = useConfirm();
const home = ref({ icon: 'pi pi-home', url: route('dashboard') });
const items = ref([
    { label: 'Configuraciones' },
    { label: 'Días festivos' }
]);
const modalVisible = ref(false);
const isEditing = ref(false);
const search = ref(props.filters.search);

const form = useForm({
    id: null,
    name: '',
    is_custom: false,
    rule_definition: {
        day: null,
        month: null,
        order: null,
        weekday: null,
    },
    applies_to_all: true,
    branch_ids: [],
    is_active: true,
});

// --- Data for dropdowns ---
const days = ref(Array.from({ length: 31 }, (_, i) => ({ label: i + 1, value: i + 1 })));
const months = ref([
    { label: 'Enero', value: 1 }, { label: 'Febrero', value: 2 }, { label: 'Marzo', value: 3 },
    { label: 'Abril', value: 4 }, { label: 'Mayo', value: 5 }, { label: 'Junio', value: 6 },
    { label: 'Julio', value: 7 }, { label: 'Agosto', value: 8 }, { label: 'Septiembre', value: 9 },
    { label: 'Octubre', value: 10 }, { label: 'Noviembre', value: 11 }, { label: 'Diciembre', value: 12 },
]);
const orders = ref(['Primer', 'Segundo', 'Tercer', 'Cuarto', 'Último']);
const weekdays = ref(['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo']);

// --- Watchers ---
watch(search, debounce((value) => {
    router.get(route('settings.holidays.index'), { search: value }, {
        preserveState: true,
        replace: true,
    });
}, 300));

// --- Methods ---
const openCreateModal = () => {
    isEditing.value = false;
    form.reset();
    modalVisible.value = true;
};

const openEditModal = (holiday) => {
    isEditing.value = true;
    form.id = holiday.id;
    form.name = holiday.name;
    form.is_custom = holiday.rule_definition.type === 'dynamic';
    form.rule_definition = {
        day: holiday.rule_definition.day,
        month: holiday.rule_definition.month,
        order: holiday.rule_definition.order,
        weekday: holiday.rule_definition.weekday,
    };
    form.applies_to_all = holiday.branches.length === 0;
    form.branch_ids = holiday.branches.map(b => b.id);
    form.is_active = !! holiday.is_active;
    modalVisible.value = true;
};

const submit = () => {
    const ruleData = { ...form.rule_definition };
    if (form.is_custom) {
        ruleData.type = 'dynamic';
        delete ruleData.day;
    } else {
        ruleData.type = 'fixed';
        delete ruleData.order;
        delete ruleData.weekday;
    }
    form.rule_definition = ruleData;

    if (isEditing.value) {
        form.put(route('settings.holidays.update', form.id), {
            onSuccess: () => modalVisible.value = false,
        });
    } else {
        form.post(route('settings.holidays.store'), {
            onSuccess: () => {
                modalVisible.value = false,
                form.reset();
            }
        });
    }
};

const confirmDelete = (holiday) => {
    confirm.require({
        message: `¿Estás seguro de que quieres eliminar "${holiday.name}"?`,
        header: 'Confirmación de eliminación',
        icon: 'pi pi-exclamation-triangle',
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
            router.delete(route('settings.holidays.destroy', holiday.id), {
                preserveScroll: true,
            });
        }
    });
};

const onPage = (event) => {
    router.get(route('settings.holidays.index'), { page: event.page + 1 }, {
        preserveState: true,
        replace: true,
    });
};

const formatDateRule = (rule) => {
    const monthName = months.value.find(m => m.value === rule.month)?.label || '';
    if (rule.type === 'fixed') {
        return `${rule.day} ${monthName}`;
    }
    if (rule.type === 'dynamic') {
        return `${rule.order} ${rule.weekday} de ${monthName}`;
    }
    return 'N/A';
};

const formatAppliedTo = (holiday) => {
    if (!holiday.branches.length) return '• Todas las sucursales';
    return holiday.branches.map(b => `• ${b.name}`).join('\n');
};

</script>

<template>

    <Head title="Días Festivos" />

    <AppLayout>
        <Breadcrumb :home="home" :model="items" class="!bg-transparent" />
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg">
                <div class="flex justify-end items-center p-6 pb-0">
                    <Button label="Crear día festivo" icon="pi pi-plus" @click="openCreateModal" />
                </div>
                <div class="flex flex-col sm:flex-row justify-between items-center p-6 border-b">
                    <h1 class="text-2xl font-bold">Días festivos</h1>
                    <IconField>
                        <InputText v-model="search" placeholder="Buscar" />
                        <InputIcon class="pi pi-search" />
                    </IconField>
                </div>
                <div class="overflow-x-auto">
                    <DataTable :value="holidays.data">
                        <Column field="id" header="ID"></Column>
                        <Column field="name" header="Nombre"></Column>
                        <Column header="Fecha">
                            <template #body="{ data }">
                                {{ formatDateRule(data.rule_definition) }}
                            </template>
                        </Column>
                        <Column header="Aplicado a">
                            <template #body="{ data }">
                                <p class="whitespace-pre-line">{{ formatAppliedTo(data) }}</p>
                            </template>
                        </Column>
                        <Column header="Estatus">
                            <template #body="{ data }">
                                <Tag :value="data.is_active ? 'Activo' : 'Inactivo'"
                                    :severity="data.is_active ? 'success' : 'danger'" />
                            </template>
                        </Column>
                        <Column>
                            <template #body="{ data }">
                                <div class="flex gap-2">
                                    <Button icon="pi pi-pencil" text rounded @click="openEditModal(data)" />
                                    <Button icon="pi pi-trash" text rounded severity="danger"
                                        @click="confirmDelete(data)" />
                                </div>
                            </template>
                        </Column>
                        <template #empty>
                            <div class="text-center p-8">
                                <p class="text-gray-500 dark:text-gray-400">No se encontraron días festivos.</p>
                            </div>
                        </template>
                    </DataTable>
                </div>
                <Paginator v-if="holidays.total > holidays.per_page" :first="holidays.from - 1"
                    :rows="holidays.per_page" :totalRecords="holidays.total" :rowsPerPageOptions="[10, 20, 30, 50]"
                    @page="onPage" class="p-6 border-t" />
            </div>
        </div>
    </AppLayout>

    <!-- MODAL -->
    <Dialog v-model:visible="modalVisible" modal :header="isEditing ? 'Editar día festivo' : 'Agregar día festivo'"
        :style="{ width: '40rem' }">
        <form @submit.prevent="submit">
            <p class="text-sm text-gray-500 mb-4">Elige entre días festivos con fecha exacta o personalizados por orden,
                día
                de la semana y mes.</p>
            <div class="flex flex-col gap-4">
                <div>
                    <InputLabel value="Nombre del día festivo*" />
                    <InputText v-model="form.name" class="w-full" />
                    <small class="text-red-500">{{ form.errors.name }}</small>
                </div>
                <div class="flex items-center">
                    <Checkbox v-model="form.is_custom" inputId="is_custom" :binary="true" />
                    <label for="is_custom" class="ml-2">Personalizar fechas</label>
                </div>

                <!-- Campos Personalizados -->
                <div v-if="form.is_custom" class="grid grid-cols-3 gap-3">
                    <div>
                        <InputLabel value="Orden*" /><Select v-model="form.rule_definition.order" :options="orders"
                            class="w-full" />
                    </div>
                    <div>
                        <InputLabel value="Día de la semana*" /><Select v-model="form.rule_definition.weekday"
                            :options="weekdays" class="w-full" />
                    </div>
                    <div>
                        <InputLabel value="Mes*" /><Select v-model="form.rule_definition.month" :options="months"
                            optionLabel="label" optionValue="value" class="w-full" />
                    </div>
                </div>
                <!-- Campos Fijos -->
                <div v-else class="grid grid-cols-2 gap-3">
                    <div>
                        <InputLabel value="Día*" /><Select v-model="form.rule_definition.day" :options="days"
                            optionLabel="label" optionValue="value" class="w-full" />
                    </div>
                    <div>
                        <InputLabel value="Mes*" /><Select v-model="form.rule_definition.month" :options="months"
                            optionLabel="label" optionValue="value" class="w-full" />
                    </div>
                </div>

                <!-- Aplicar a -->
                <div>
                    <InputLabel value="Aplicar a" />
                    <div class="flex gap-4 mt-2">
                        <div class="flex items-center">
                            <RadioButton v-model="form.applies_to_all" inputId="all" :value="true" /><label for="all"
                                class="ml-2">Todas las sucursales</label>
                        </div>
                        <div class="flex items-center">
                            <RadioButton v-model="form.applies_to_all" inputId="specific" :value="false" /><label
                                for="specific" class="ml-2">Sucursales específicas</label>
                        </div>
                    </div>
                </div>
                <div v-if="!form.applies_to_all" class="border rounded-lg p-4 max-h-48 overflow-y-auto">
                    <div v-for="branch in branches" :key="branch.id" class="flex items-center mb-2">
                        <Checkbox v-model="form.branch_ids" :inputId="`branch-${branch.id}`" :value="branch.id" />
                        <label :for="`branch-${branch.id}`" class="ml-2">{{ branch.name }}</label>
                    </div>
                </div>
                <div>
                    <InputLabel value="Estatus" />
                    <div class="flex items-center mt-2">
                        <ToggleSwitch v-model="form.is_active" inputId="is_active" />
                        <label for="is_active" class="ml-2">{{ form.is_active ? 'Activo' : 'Inactivo' }}</label>
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-2 mt-6">
                <Button label="Cancelar" severity="secondary" @click="modalVisible = false" outlined />
                <Button type="submit" :label="isEditing ? 'Guardar Cambios' : 'Guardar'" :loading="form.processing" />
            </div>
        </form>
    </Dialog>
</template>
