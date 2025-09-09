<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import axios from 'axios';
import { format } from 'date-fns';
import es from 'date-fns/locale/es';

// --- State and Refs ---
const video = ref(null);
const canvas = ref(null);
const stream = ref(null);
const toast = useToast();
const processing = ref(false);
const isModalVisible = ref(false);
const currentAction = ref(null); // 'clock' para entrada/salida, 'break' para descanso
const currentTime = ref('');
const currentDate = ref('');
let timeInterval = null;

// --- Hooks ---
onMounted(() => {
    updateTime();
    timeInterval = setInterval(updateTime, 1000);
    currentDate.value = format(new Date(), "EEEE, d 'de' MMMM 'de' yyyy", { locale: es });
});

onUnmounted(() => {
    stopCamera();
    clearInterval(timeInterval);
});

// --- Methods ---
const updateTime = () => {
    currentTime.value = format(new Date(), 'hh:mm:ss a');
};

const startCamera = async () => {
    // Esperamos a que el DOM del modal esté listo
    await nextTick();
    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        try {
            const s = await navigator.mediaDevices.getUserMedia({ video: { width: 1280, height: 720 } });
            stream.value = s;
            if (video.value) {
                video.value.srcObject = s;
            }
        } catch (err) {
            toast.add({ severity: 'error', summary: 'Error de Cámara', detail: 'No se pudo acceder a la cámara. Revisa los permisos.', life: 5000 });
            closeModal();
        }
    } else {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Tu navegador no soporta el acceso a la cámara.', life: 5000 });
    }
};

const stopCamera = () => {
    stream.value?.getTracks().forEach(track => track.stop());
    stream.value = null;
};

const openModal = (action) => {
    currentAction.value = action;
    isModalVisible.value = true;
    startCamera();
};

const closeModal = () => {
    isModalVisible.value = false;
    stopCamera();
};

const capture = async () => {
    if (processing.value) return;
    processing.value = true;

    const context = canvas.value.getContext('2d');
    canvas.value.width = video.value.videoWidth;
    canvas.value.height = video.value.videoHeight;
    context.drawImage(video.value, 0, 0, canvas.value.width, canvas.value.height);

    const imageData = canvas.value.toDataURL('image/jpeg');
    const routeName = currentAction.value === 'clock'
        ? route('attendances.kiosk.store')
        : route('attendances.kiosk.storeBreak');

    try {
        const response = await axios.post(routeName, { image: imageData });
        if (response.data.success) {
            toast.add({ severity: 'success', summary: 'Éxito', detail: response.data.message, life: 5000 });
        }
    } catch (error) {
        const errorMessage = error.response?.data?.message || 'Ha ocurrido un error inesperado.';
        toast.add({ severity: 'error', summary: 'Error', detail: errorMessage, life: 5000 });
    } finally {
        setTimeout(() => {
            processing.value = false;
            closeModal();
        }, 2000); // Damos tiempo al usuario para leer el mensaje
    }
};
</script>

<template>

    <Head title="Quiosco de Asistencia" />
    <Toast position="top-center" />

    <div class="h-screen bg-primary-100 flex flex-col items-center justify-center text-white text-center">
        <!-- Pantalla Principal del Quiosco -->
        <div v-if="!isModalVisible">
            <svg width="100" height="100" viewBox="0 0 39 39" fill="none" xmlns="http://www.w3.org/2000/svg"
                class="mb-20 w-52 shrink-0 mx-auto">
                <rect x="0.287744" y="0.287744" width="38.4245" height="38.4245" rx="2.87442" stroke="black"
                    stroke-width="0.575488" />
                <path
                    d="M10.0134 12.5604C13.6146 11.155 16.0354 11.5445 19.5876 12.5604C19.1694 13.5219 19.0248 14.1989 18.9734 15.7234H18.446V14.4051C18.4458 13.6147 17.4793 12.8497 16.2498 12.5604C15.0201 12.2711 13.439 12.22 11.8582 12.7361C7.5541 14.1415 5.88518 22.5748 11.8582 25.4734C13.7906 26.4111 17.2159 26.3512 19.324 24.8582C19.4118 24.796 19.6148 24.968 19.5876 25.034C19.3896 25.5163 19.319 25.912 19.1482 25.9998C16.0739 27.5808 12.4728 27.518 10.0134 26.3514C3.16213 23.1014 5.35803 14.3771 10.0134 12.5604ZM28.3718 11.9461C32.9391 11.9463 33.1148 17.4798 28.3718 18.4461C35.3106 18.7098 32.6757 26.3514 28.3718 26.7029C26.0002 26.8965 24.303 26.8101 21.696 26.8787V26.3514C22.1352 26.3513 22.5545 26.0875 22.574 25.824C22.7496 23.452 22.8375 14.7568 22.574 13.4393C22.4878 13.0085 22.3987 12.6484 21.9597 12.6483H20.905V11.9461H28.3718ZM26.9666 18.9734C26.1432 18.9734 25.6691 18.9481 24.7703 19.0604V25.824C25.7365 25.9997 26.1615 26.0658 26.9666 25.9998C31.6215 25.618 31.6215 18.9738 26.9666 18.9734ZM26.9666 12.824C26.0604 12.7855 25.5735 12.7727 24.7703 12.9998V18.1824C25.6612 18.2117 26.1479 18.2166 26.9666 18.1824C30.2162 18.0463 31.2701 13.0074 26.9666 12.824Z"
                    fill="var(--primary-color)" />
            </svg>
            <h1 class="text-7xl font-bold font-mono">{{ currentTime }}</h1>
            <p class="text-2xl text-gray-500 capitalize">{{ currentDate }}</p>

            <div class="mt-12 flex flex-col md:flex-row gap-6">
                <Button label="Registrar Entrada / Salida" icon="pi pi-sign-in" class="!text-xl !py-6 !px-8"
                    @click="openModal('clock')" />
                <Button label="Iniciar / Terminar Descanso" icon="pi pi-pause" class="!text-xl !py-6 !px-8"
                    severity="warning" @click="openModal('break')" />
            </div>
        </div>
    </div>

    <!-- Modal de Captura de Rostro -->
    <Dialog v-model:visible="isModalVisible" modal header="Captura de Rostro" :style="{ width: '50rem' }"
        @hide="closeModal" :draggable="false">
        <div class="w-full border-4 border-gray-700 rounded-lg overflow-hidden shadow-lg aspect-video bg-black">
            <video ref="video" autoplay playsinline class="w-full h-full object-cover"></video>
            <canvas ref="canvas" class="hidden"></canvas>
        </div>
        <template #footer>
            <Button label="Cancelar" severity="secondary" @click="closeModal" outlined />
            <Button label="Capturar" icon="pi pi-camera" @click="capture" :loading="processing" />
        </template>
    </Dialog>
</template>