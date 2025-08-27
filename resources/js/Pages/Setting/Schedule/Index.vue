<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useConfirm } from "primevue/useconfirm";
import { debounce } from 'lodash';

const props = defineProps({ schedules: Object, filters: Object });
const confirm = useConfirm();
const home = ref({ icon: 'pi pi-home', url: route('dashboard') });
const items = ref([{ label: 'Configuraciones' }, { label: 'Horarios del personal' }]);
const search = ref(props.filters.search);
const menu = ref();

watch(search, debounce((value) => {
    router.get(route('settings.schedules.index'), { search: value }, { preserveState: true, replace: true });
}, 300));

const onPage = (event) => {
    router.get(route('settings.schedules.index'), { page: event.page + 1 }, { preserveState: true, replace: true });
};

const confirmDelete = (schedule) => {
    confirm.require({
        message: `¿Estás seguro de que quieres eliminar "${schedule.name}"?`,
        header: 'Confirmación de eliminación',
        accept: () => router.delete(route('settings.schedules.destroy', schedule.id), { preserveScroll: true })
    });
};
</script>

<template>
    <Head title="Horarios del Personal" />
    <AppLayout>
        <Breadcrumb :home="home" :model="items" class="!bg-transparent" />
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg">
                <div class="flex justify-end items-center p-6 pb-0">
                    <Link :href="route('settings.schedules.create')">
                        <Button label="Crear nuevo horario" icon="pi pi-plus" />
                    </Link>
                </div>
                <div class="flex flex-col sm:flex-row justify-between items-center p-6 border-b">
                    <h1 class="text-2xl font-bold">Horarios definidos</h1>
                    <IconField><InputText v-model="search" placeholder="Buscar" /><InputIcon class="pi pi-search" /></IconField>
                </div>
                <div class="overflow-x-auto">
                    <DataTable :value="schedules.data">
                        <Column field="id" header="ID"></Column>
                        <Column field="name" header="Nombre del horario"></Column>
                        <Column header="Sucursales vinculadas">
                            <template #body="{ data }">
                                <ul v-if="data.branches.length" class="list-disc list-inside">
                                    <li v-for="branch in data.branches" :key="branch.id">{{ branch.name }}</li>
                                </ul>
                                <span v-else class="text-gray-400">Ninguna</span>
                            </template>
                        </Column>
                        <Column>
                            <template #body="{ data }">
                                <Menu ref="menu" :model="[{ label: 'Editar', icon: 'pi pi-pencil', command: () => router.get(route('settings.schedules.edit', data.id)) }, { label: 'Eliminar', icon: 'pi pi-trash', command: () => confirmDelete(data) }]" :popup="true" />
                                <Button @click="(event) => menu.toggle(event)" icon="pi pi-ellipsis-v" text rounded />
                            </template>
                        </Column>
                         <template #empty>
                            <div class="text-center p-8">
                                <p class="text-gray-500 dark:text-gray-400">No se encontraron horarios.</p>
                            </div>
                        </template>
                    </DataTable>
                </div>
                <Paginator v-if="schedules.total > schedules.per_page" :first="schedules.from - 1" :rows="schedules.per_page" :totalRecords="schedules.total" @page="onPage" class="p-6 border-t" />
            </div>
        </div>
    </AppLayout>
</template>
