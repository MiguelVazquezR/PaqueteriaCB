<script setup>
import { ref, watch, computed } from 'vue';
import { useLayout } from '@/Layouts/composables/layout';
import { Link, router, usePage, useForm } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import { format } from 'date-fns';
import es from 'date-fns/locale/es';

const { toggleMenu: toggleSidebar, toggleDarkMode, isDarkTheme } = useLayout();
const user = usePage().props.auth.user;
const page = usePage(); // Crear una referencia a usePage()

// --- Lógica para el Menú de Usuario ---
const toast = useToast();
const userMenu = ref();
const userMenuItems = ref([
    { label: 'Perfil', icon: 'pi pi-user', command: () => router.get(route('profile.show')) },
    { label: 'Cerrar sesión', icon: 'pi pi-sign-out', command: () => router.post(route('logout')) }
]);
const toggleUserMenu = (event) => { userMenu.value.toggle(event); };

const attendancePopover = ref();
const attendanceModalVisible = ref(false);
const video = ref(null);
const canvas = ref(null);
const stream = ref(null);
const currentAction = ref(null);
const form = useForm({ image: null });


const toggleAttendancePopover = (event) => {
    attendancePopover.value.toggle(event);
};

const openAttendanceModal = (action) => {
    currentAction.value = action;
    attendanceModalVisible.value = true;
    setTimeout(() => {
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            navigator.mediaDevices.getUserMedia({ video: true })
                .then(s => {
                    stream.value = s;
                    if (video.value) video.value.srcObject = s;
                }).catch(err => {
                    toast.add({ severity: 'error', summary: 'Error de Cámara', detail: 'No se pudo acceder a la cámara.', life: 3000 });
                });
        }
    }, 100);
};

const closeAttendanceModal = () => {
    attendanceModalVisible.value = false;
    stream.value?.getTracks().forEach(track => track.stop());
};

const capture = () => {
    const context = canvas.value.getContext('2d');
    canvas.value.width = video.value.videoWidth;
    canvas.value.height = video.value.videoHeight;
    context.drawImage(video.value, 0, 0, video.value.videoWidth, video.value.videoHeight);
    form.image = canvas.value.toDataURL('image/jpeg');

    const routeName = currentAction.value === 'fichaje' ? 'attendances.store' : 'attendances.storeBreak';

    form.post(route(routeName), {
        preserveScroll: true,
        onSuccess: () => closeAttendanceModal(),
    });
};

// Usamos un watcher para reaccionar a los cambios en los mensajes flash de Jetstream.
watch(() => page.props.flash, (flash) => {
    if (flash && flash.success) {
        toast.add({ severity: 'success', summary: 'Éxito', detail: flash.success, life: 5000 });
    }
    if (flash && flash.error) {
        toast.add({ severity: 'error', summary: 'Error', detail: flash.error, life: 5000 });
    }
}, { deep: true });


const attendanceStatus = computed(() => {
    const status = page.props.auth.current_status;
    const userName = page.props.auth.user.name.split(' ')[0];
    const greeting = `Buenos días ${userName}`;

    if (!status) {
        return {
            greeting,
            message: 'No has registrado tu entrada.',
            icon: 'pi pi-sign-in',
            buttons: [
                { label: 'Registrar Entrada', action: 'fichaje', severity: 'primary', icon: 'pi pi-sign-in' }
            ]
        };
    }

    const time = format(new Date(status.created_at), 'hh:mm a', { locale: es });

    switch (status.type) {
        case 'entry':
        case 'break_end':
            return {
                greeting,
                message: `Estás trabajando desde las ${time}`,
                icon: 'pi pi-check-circle text-green-500',
                buttons: [
                    { label: 'Tomar Descanso', action: 'descanso', severity: 'warning', icon: 'pi pi-pause' },
                    { label: 'Registrar Salida', action: 'fichaje', severity: 'danger', icon: 'pi pi-stop-circle' }
                ]
            };
        case 'break_start':
            return {
                greeting,
                message: `Estás en descanso desde las ${time}`,
                icon: 'pi pi-coffee text-yellow-500',
                buttons: [
                    { label: 'Reanudar Trabajo', action: 'descanso', severity: 'info', icon: 'pi pi-play' },
                    { label: 'Registrar Salida', action: 'fichaje', severity: 'danger', icon: 'pi pi-stop-circle' }
                ]
            };
        case 'exit':
            return {
                greeting,
                message: `Jornada finalizada a las ${time}.`,
                icon: 'pi pi-check-square text-gray-500',
                buttons: [] // No hay acciones disponibles
            };
        default:
            return { greeting, message: 'Estado desconocido.', icon: '', buttons: [] };
    }
});

</script>

<template>
    <div class="layout-topbar">
        <div class="layout-topbar-logo-container">
            <button class="layout-menu-button layout-topbar-action" @click="toggleSidebar">
                <i class="pi pi-bars"></i>
            </button>
            <Link href="/" class="layout-topbar-logo">
            <svg width="32" height="32" viewBox="0 0 39 39" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="0.287744" y="0.287744" width="38.4245" height="38.4245" rx="2.87442"
                    stroke="var(--primary-color)" stroke-width="0.575488" />
                <path
                    d="M10.0134 12.5604C13.6146 11.155 16.0354 11.5445 19.5876 12.5604C19.1694 13.5219 19.0248 14.1989 18.9734 15.7234H18.446V14.4051C18.4458 13.6147 17.4793 12.8497 16.2498 12.5604C15.0201 12.2711 13.439 12.22 11.8582 12.7361C7.5541 14.1415 5.88518 22.5748 11.8582 25.4734C13.7906 26.4111 17.2159 26.3512 19.324 24.8582C19.4118 24.796 19.6148 24.968 19.5876 25.034C19.3896 25.5163 19.319 25.912 19.1482 25.9998C16.0739 27.5808 12.4728 27.518 10.0134 26.3514C3.16213 23.1014 5.35803 14.3771 10.0134 12.5604ZM28.3718 11.9461C32.9391 11.9463 33.1148 17.4798 28.3718 18.4461C35.3106 18.7098 32.6757 26.3514 28.3718 26.7029C26.0002 26.8965 24.303 26.8101 21.696 26.8787V26.3514C22.1352 26.3513 22.5545 26.0875 22.574 25.824C22.7496 23.452 22.8375 14.7568 22.574 13.4393C22.4878 13.0085 22.3987 12.6484 21.9597 12.6483H20.905V11.9461H28.3718ZM26.9666 18.9734C26.1432 18.9734 25.6691 18.9481 24.7703 19.0604V25.824C25.7365 25.9997 26.1615 26.0658 26.9666 25.9998C31.6215 25.618 31.6215 18.9738 26.9666 18.9734ZM26.9666 12.824C26.0604 12.7855 25.5735 12.7727 24.7703 12.9998V18.1824C25.6612 18.2117 26.1479 18.2166 26.9666 18.1824C30.2162 18.0463 31.2701 13.0074 26.9666 12.824Z"
                    fill="var(--primary-color)" />
            </svg>
            </Link>
        </div>

        <div class="layout-topbar-actions">
            <div class="layout-config-menu">
                <button type="button" class="layout-topbar-action" @click="toggleAttendancePopover">
                    <i class="pi pi-clock"></i>
                </button>
                <button type="button" class="layout-topbar-action" @click="toggleDarkMode">
                    <i :class="['pi', { 'pi-moon': isDarkTheme, 'pi-sun': !isDarkTheme }]"></i>
                </button>
            </div>

            <button class="layout-topbar-menu-button layout-topbar-action"
                v-styleclass="{ selector: '@next', enterFromClass: 'hidden', enterActiveClass: 'animate-scalein', leaveToClass: 'hidden', leaveActiveClass: 'animate-fadeout', hideOnOutsideClick: true }">
                <i class="pi pi-ellipsis-v"></i>
            </button>

            <div class="layout-topbar-menu hidden lg:block">
                <div class="layout-topbar-menu-content">
                    <button @click="toggleUserMenu"
                        class="flex text-sm border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 transition">
                        <img class="size-9 rounded-full object-cover" :src="user.profile_photo_url" :alt="user.name">
                    </button>
                    <Menu ref="userMenu" :model="userMenuItems" :popup="true" />
                </div>
            </div>
        </div>
    </div>

    <!-- ✨ --- POPOVER DE FICHAJE --- ✨ -->
    <Popover ref="attendancePopover">
        <div class="p-4 w-80">
            <div class="text-center mb-4">
                <p class="font-bold text-lg">{{ attendanceStatus.greeting }}</p>
                <p class="text-sm text-gray-500">Estado actual</p>
                <div class="flex items-center justify-center gap-2 mt-2">
                    <p class="font-semibold">{{ attendanceStatus.message }}</p>
                    <i :class="attendanceStatus.icon"></i>
                </div>
            </div>
            <div class="flex gap-2">
                <Button v-for="button in attendanceStatus.buttons" :key="button.label" :label="button.label"
                    :icon="button.icon" :severity="button.severity" @click="openAttendanceModal(button.action)"
                    class="flex-1" />
            </div>
        </div>
    </Popover>

    <!-- ✨ Modal de Fichaje -->
    <Dialog v-model:visible="attendanceModalVisible" modal header="Registrar Fichaje" :style="{ width: '40rem' }"
        @hide="closeAttendanceModal">
        <div class="w-full border-2 border-gray-300 rounded-lg overflow-hidden">
            <video ref="video" autoplay playsinline class="w-full"></video>
            <canvas ref="canvas" class="hidden"></canvas>
        </div>
        <template #footer>
            <Button label="Cancelar" severity="secondary" @click="closeAttendanceModal" outlined />
            <Button label="Registrar" icon="pi pi-check" @click="capture" :loading="form.processing" />
        </template>
    </Dialog>
</template>
