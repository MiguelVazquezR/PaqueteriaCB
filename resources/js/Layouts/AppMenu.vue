<script setup>
import { ref } from 'vue';

import AppMenuItem from './AppMenuItem.vue';

const model = ref([
    {
        // label: 'UI Components',
        items: [
            { label: 'Inicio', icon: 'pi pi-fw pi-home', to: route('dashboard'), routeName: 'dashboard' },
            { label: 'Usuarios', icon: 'pi pi-fw pi-user', to: route('users.index'), routeName: 'users.*' },
            { label: 'Sucursales', icon: 'pi pi-fw pi-building', to: route('branches.index'), routeName: 'branches.*' },
            { label: 'Incidencias', icon: 'pi pi-fw pi-calendar-times', to: route('incidents.index'), routeName: 'incidents.*' },
            { label: 'Bonos', icon: 'pi pi-fw pi-wallet', to: route('bonuses.index'), routeName: 'bonuses.*' },
            {
                label: 'Configuraciones', icon: 'pi pi-fw pi-cog',
                items: [
                    {
                        label: 'Roles y permisos',
                        icon: 'pi pi-key',
                        to: route('settings.roles-permissions.index'),
                        routeName: 'settings.roles-permissions.*'
                    },
                    {
                        label: 'Días festivos',
                        icon: 'pi pi-calendar-times',
                        to: route('settings.holidays.index'),
                        routeName: 'settings.holidays.*'
                    },
                    {
                        label: 'Horarios del personal',
                        icon: 'pi pi-clock',
                        to: route('settings.schedules.index'),
                        routeName: 'settings.schedules.*'
                    },
                ]
            },
        ]
    },
]);
</script>

<template>
    <ul class="layout-menu">
        <template v-for="(item, i) in model" :key="item">
            <app-menu-item v-if="!item.separator" :item="item" :index="i"></app-menu-item>
            <li v-if="item.separator" class="menu-separator"></li>
        </template>
    </ul>
</template>

<style lang="scss" scoped></style>
