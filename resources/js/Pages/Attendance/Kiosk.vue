<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';

const video = ref(null);
const canvas = ref(null);
const stream = ref(null);
const form = useForm({ image: null });

onMounted(() => {
    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        navigator.mediaDevices.getUserMedia({ video: true })
            .then(s => {
                stream.value = s;
                video.value.srcObject = s;
            });
    }
});

onUnmounted(() => {
    stream.value?.getTracks().forEach(track => track.stop());
});

const capture = () => {
    const context = canvas.value.getContext('2d');
    canvas.value.width = video.value.videoWidth;
    canvas.value.height = video.value.videoHeight;
    context.drawImage(video.value, 0, 0, video.value.videoWidth, video.value.videoHeight);
    
    form.image = canvas.value.toDataURL('image/jpeg');
    form.post(route('attendances.store'));
};
</script>

<template>
    <Head title="Quiosco de Fichaje" />
    <div class="h-screen bg-gray-900 flex flex-col items-center justify-center text-white">
        <h1 class="text-4xl font-bold mb-4">Quiosco de Fichaje</h1>
        <div class="w-full max-w-2xl border-4 border-gray-700 rounded-lg overflow-hidden">
            <video ref="video" autoplay playsinline class="w-full"></video>
            <canvas ref="canvas" class="hidden"></canvas>
        </div>
        <Button label="Registrar Fichaje" icon="pi pi-camera" class="mt-6" size="large" @click="capture" :loading="form.processing" />
    </div>
</template>
