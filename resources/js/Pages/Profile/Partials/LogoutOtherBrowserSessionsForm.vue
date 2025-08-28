<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

defineProps({
    sessions: Array,
});

const confirmingLogout = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmLogout = () => {
    confirmingLogout.value = true;
    setTimeout(() => passwordInput.value?.$el.focus(), 250);
};

const logoutOtherBrowserSessions = () => {
    form.delete(route('other-browser-sessions.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value?.$el.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingLogout.value = false;
    form.reset();
};
</script>

<template>
    <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Sesiones del navegador
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Administra y cierra la sesión en tus sesiones activas en otros navegadores y dispositivos.
            </p>

            <div v-if="sessions.length > 0" class="mt-5 space-y-6">
                <div v-for="(session, i) in sessions" :key="i" class="flex items-center">
                    <div>
                        <svg v-if="session.agent.is_desktop" class="size-8 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25" /></svg>
                        <svg v-else class="size-8 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" /></svg>
                    </div>
                    <div class="ms-3">
                        <div class="text-sm text-gray-600 dark:text-gray-300">
                            {{ session.agent.platform ? session.agent.platform : 'Unknown' }} - {{ session.agent.browser ? session.agent.browser : 'Unknown' }}
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">
                                {{ session.ip_address }},
                                <span v-if="session.is_current_device" class="text-green-500 font-semibold">Este dispositivo</span>
                                <span v-else>Última vez activo {{ session.last_active }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 px-6 py-4 bg-gray-50 dark:bg-gray-700/50 text-right rounded-b-lg">
            <transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0" leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
                <p v-if="form.recentlySuccessful" class="text-sm text-gray-600 dark:text-gray-300">Hecho.</p>
            </transition>
            <Button @click="confirmLogout" label="Cerrar otras sesiones" />
        </div>

        <!-- Modal de Confirmación -->
        <Dialog v-model:visible="confirmingLogout" modal header="Cerrar otras sesiones" :style="{ width: '30rem' }" @hide="closeModal">
            <p class="text-gray-600 dark:text-gray-400">
                Por favor, introduce tu contraseña para confirmar que deseas cerrar la sesión en tus otros navegadores.
            </p>
            <div class="mt-4">
                <InputText
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    class="w-full"
                    placeholder="Contraseña"
                    @keyup.enter="logoutOtherBrowserSessions"
                    :invalid="!!form.errors.password"
                />
                <small v-if="form.errors.password" class="text-red-500 mt-1">{{ form.errors.password }}</small>
            </div>
            <template #footer>
                <Button label="Cancelar" severity="secondary" @click="closeModal" outlined />
                <Button label="Cerrar sesiones" @click="logoutOtherBrowserSessions" :loading="form.processing" />
            </template>
        </Dialog>
    </div>
</template>
