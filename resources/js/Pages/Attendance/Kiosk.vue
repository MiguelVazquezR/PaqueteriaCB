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
    
    <div class="h-screen bg-gray-900 flex flex-col items-center justify-center text-white p-4 text-center">
        <!-- Pantalla Principal del Quiosco -->
        <div v-if="!isModalVisible">
            <h1 class="text-7xl font-bold font-mono">{{ currentTime }}</h1>
            <p class="text-2xl text-gray-400 capitalize">{{ currentDate }}</p>
            
            <div class="mt-12 flex flex-col md:flex-row gap-6">
                <Button 
                    label="Registrar Entrada / Salida" 
                    icon="pi pi-sign-in" 
                    class="!text-xl !py-6 !px-8" 
                    @click="openModal('clock')" 
                />
                <Button 
                    label="Iniciar / Terminar Descanso" 
                    icon="pi pi-pause" 
                    class="!text-xl !py-6 !px-8" 
                    severity="warning"
                    @click="openModal('break')" 
                />
            </div>
        </div>
    </div>

    <!-- Modal de Captura de Rostro -->
    <Dialog v-model:visible="isModalVisible" modal header="Captura de Rostro" :style="{ width: '50rem' }" @hide="closeModal" :draggable="false">
        <div class="w-full border-4 border-gray-700 rounded-lg overflow-hidden shadow-lg aspect-video bg-black">
            <video ref="video" autoplay playsinline class="w-full h-full object-cover"></video>
            <canvas ref="canvas" class="hidden"></canvas>
        </div>
        <template #footer>
            <Button label="Cancelar" severity="secondary" @click="closeModal" outlined />
            <Button 
                label="Capturar" 
                icon="pi pi-camera" 
                @click="capture" 
                :loading="processing" 
            />
        </template>
    </Dialog>
</template>