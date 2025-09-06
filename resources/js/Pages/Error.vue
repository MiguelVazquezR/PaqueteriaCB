<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    status: Number,
});

const title = computed(() => {
    return {
        503: 'Servicio No Disponible',
        500: 'Error del Servidor',
        404: 'Página No Encontrada',
        403: 'Acceso Denegado',
    }[props.status];
});

const description = computed(() => {
    return {
        503: 'Disculpa, estamos realizando mantenimiento. Por favor, vuelve más tarde.',
        500: 'Vaya, algo salió mal en nuestros servidores.',
        404: 'Disculpa, la página que estás buscando no pudo ser encontrada.',
        403: 'Lo sentimos, no tienes los permisos necesarios para acceder a esta ruta.',
    }[props.status];
});
</script>

<template>
    <Head :title="title" />
    <div class="bg-gray-100 dark:bg-gray-900 flex items-center justify-center min-h-screen font-sans">
        <div class="w-full max-w-md p-8 text-center">
            <div class="mb-6">
                <!-- Ilustración SVG -->
                <svg viewBox="0 0 128 128" width="128" height="128" class="mx-auto">
                    <defs>
                        <linearGradient id="grad1" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" style="stop-color:rgb(167, 139, 250);stop-opacity:1" />
                            <stop offset="100%" style="stop-color:rgb(129, 140, 248);stop-opacity:1" />
                        </linearGradient>
                    </defs>
                    <path d="M104.9,48.2H89.3V32.6a4.3,4.3,0,0,0-4.3-4.3H43a4.3,4.3,0,0,0-4.3,4.3V48.2H23.1a4.3,4.3,0,0,0-4.3,4.3V95.1a4.3,4.3,0,0,0,4.3,4.3H104.9a4.3,4.3,0,0,0,4.3-4.3V52.5A4.3,4.3,0,0,0,104.9,48.2ZM43,32.6H85V48.2H43ZM104.9,95.1H23.1V52.5H104.9Z" fill="url(#grad1)" class="opacity-30 dark:opacity-40"/>
                    <circle cx="64" cy="64" r="30" fill="none" stroke="rgb(239, 68, 68)" stroke-width="6" stroke-linecap="round" />
                    <line x1="48" y1="48" x2="80" y2="80" stroke="rgb(239, 68, 68)" stroke-width="6" stroke-linecap="round" />
                </svg>
            </div>
            
            <h1 class="text-6xl font-bold text-primary-600 dark:text-primary-400">{{ status }}</h1>
            <h2 class="mt-4 text-2xl font-semibold text-gray-800 dark:text-gray-100">{{ title }}</h2>
            <p class="mt-2 text-gray-600 dark:text-gray-400">
                {{ description }}
            </p>
            <div class="mt-8">
                <Link :href="route('dashboard')">
                    <Button label="Regresar al Inicio" icon="pi pi-home" />
                </Link>
            </div>
        </div>
    </div>
</template>