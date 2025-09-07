<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('user-password.update'), {
        errorBag: 'updatePassword',
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value.focus();
            }
        },
    });
};
</script>

<template>
    <div class="bg-white dark:bg-neutral-900 shadow-md rounded-lg">
        <form @submit.prevent="updatePassword">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Actualizar contraseña
                </h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Asegúrate de que tu cuenta utiliza una contraseña larga y aleatoria para mantenerla segura.
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                    <div>
                        <InputLabel for="current_password" value="Contraseña actual" />
                        <Password
                            id="current_password"
                            ref="currentPasswordInput"
                            v-model="form.current_password"
                            class="mt-1 w-full"
                            inputClass="w-full"
                            :feedback="false"
                            toggleMask
                            :invalid="!!form.errors.current_password"
                        />
                        <small v-if="form.errors.current_password" class="text-red-500 mt-1">{{ form.errors.current_password }}</small>
                    </div>
                </div>
                 <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                    <div>
                        <InputLabel for="password" value="Nueva contraseña" />
                        <Password
                            id="password"
                            ref="passwordInput"
                            v-model="form.password"
                            class="mt-1 w-full"
                            inputClass="w-full"
                            :feedback="false"
                            toggleMask
                            :invalid="!!form.errors.password"
                        />
                        <small v-if="form.errors.password" class="text-red-500 mt-1">{{ form.errors.password }}</small>
                    </div>
                    <div>
                        <InputLabel for="password_confirmation" value="Confirmar contraseña" />
                         <Password
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            class="mt-1 w-full"
                            inputClass="w-full"
                            :feedback="false"
                            toggleMask
                            :invalid="!!form.errors.password_confirmation"
                        />
                        <small v-if="form.errors.password_confirmation" class="text-red-500 mt-1">{{ form.errors.password_confirmation }}</small>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 px-6 py-4 bg-gray-50 dark:bg-gray-700/50 text-right rounded-b-lg">
                 <transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p v-if="form.recentlySuccessful" class="text-sm text-gray-600 dark:text-gray-300">Guardado.</p>
                </transition>
                <Button type="submit" label="Guardar" :loading="form.processing" />
            </div>
        </form>
    </div>
</template>
