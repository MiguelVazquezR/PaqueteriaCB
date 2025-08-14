<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import FloatingConfigurator from '@/Components/FloatingConfigurator.vue';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.transform(data => ({
        ...data,
        remember: form.remember ? 'on' : '',
    })).post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>

    <Head title="Inicio de sesión" />
    <FloatingConfigurator />
    <div
        class="bg-surface-50 dark:bg-surface-950 flex items-center justify-center min-h-screen min-w-[100vw] overflow-hidden">
        <div class="flex flex-col items-center justify-center">
            <div
                style="border-radius: 56px; padding: 0.3rem; background: linear-gradient(180deg, var(--primary-color) 10%, rgba(33, 150, 243, 0) 30%)">
                <div class="w-full bg-surface-0 dark:bg-surface-900 py-20 px-8 sm:px-20" style="border-radius: 53px">
                    <div class="text-center mb-8">

                        <svg width="39" height="39" viewBox="0 0 39 39" fill="none" xmlns="http://www.w3.org/2000/svg"
                            class="mb-8 w-16 shrink-0 mx-auto">
                            <rect x="0.287744" y="0.287744" width="38.4245" height="38.4245" rx="2.87442" stroke="black"
                                stroke-width="0.575488" />
                            <path
                                d="M10.0134 12.5604C13.6146 11.155 16.0354 11.5445 19.5876 12.5604C19.1694 13.5219 19.0248 14.1989 18.9734 15.7234H18.446V14.4051C18.4458 13.6147 17.4793 12.8497 16.2498 12.5604C15.0201 12.2711 13.439 12.22 11.8582 12.7361C7.5541 14.1415 5.88518 22.5748 11.8582 25.4734C13.7906 26.4111 17.2159 26.3512 19.324 24.8582C19.4118 24.796 19.6148 24.968 19.5876 25.034C19.3896 25.5163 19.319 25.912 19.1482 25.9998C16.0739 27.5808 12.4728 27.518 10.0134 26.3514C3.16213 23.1014 5.35803 14.3771 10.0134 12.5604ZM28.3718 11.9461C32.9391 11.9463 33.1148 17.4798 28.3718 18.4461C35.3106 18.7098 32.6757 26.3514 28.3718 26.7029C26.0002 26.8965 24.303 26.8101 21.696 26.8787V26.3514C22.1352 26.3513 22.5545 26.0875 22.574 25.824C22.7496 23.452 22.8375 14.7568 22.574 13.4393C22.4878 13.0085 22.3987 12.6484 21.9597 12.6483H20.905V11.9461H28.3718ZM26.9666 18.9734C26.1432 18.9734 25.6691 18.9481 24.7703 19.0604V25.824C25.7365 25.9997 26.1615 26.0658 26.9666 25.9998C31.6215 25.618 31.6215 18.9738 26.9666 18.9734ZM26.9666 12.824C26.0604 12.7855 25.5735 12.7727 24.7703 12.9998V18.1824C25.6612 18.2117 26.1479 18.2166 26.9666 18.1824C30.2162 18.0463 31.2701 13.0074 26.9666 12.824Z"
                                fill="var(--primary-color)" />
                        </svg>
                        <div class="text-surface-900 dark:text-surface-0 text-3xl font-medium mb-4">Paquetería CB</div>
                        <span class="text-muted-color font-medium">Agrega tus credenciales para continuar</span>
                    </div>

                    <div>
                        <label for="email1"
                            class="block text-surface-900 dark:text-surface-0 text-xl font-medium mb-2">Correo
                            electrónico</label>
                        <InputText id="email1" type="text" placeholder="Correo electrónico" class="w-full md:w-[30rem]"
                            :invalid="form.errors.email" v-model="form.email" />
                        <Message v-if="form.errors.email" severity="error" variant="simple" size="small">
                            {{ form.errors.email }}
                        </Message>

                        <label for="password1"
                            class="block text-surface-900 dark:text-surface-0 font-medium text-xl mb-2 mt-4">Contraseña</label>
                        <Password id="password1" v-model="form.password" placeholder="Contraseña" :toggleMask="true"
                            fluid :feedback="false" :invalid="form.errors.password"></Password>
                        <Message v-if="form.errors.password" severity="error" variant="simple" size="small" >
                            {{ form.errors.password }}
                        </Message>

                        <div class="flex items-center justify-between mt-2 mb-8 gap-8">
                            <div class="flex items-center">
                                <Checkbox v-model="form.remember" id="rememberme1" binary class="mr-2"></Checkbox>
                                <label for="rememberme1">Mantener sesión abierta</label>
                            </div>
                            <Link v-if="canResetPassword" :href="route('password.request')"
                                class="font-medium no-underline ml-2 text-right cursor-pointer text-primary">
                            ¿Olvidaste tu contraseña?
                            </Link>
                        </div>
                        <Button @click="submit" label="Iniciar sesión" class="w-full"></Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- <AuthenticationCard>
        <template #logo>
            <AuthenticationCardLogo />
        </template>

<div v-if="status" class="mb-4 font-medium text-sm text-green-600">
    {{ status }}
</div>

<form @submit.prevent="submit">
    <div>
        <InputLabel for="email" value="Email" />
        <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required autofocus
            autocomplete="username" />
        <InputError class="mt-2" :message="form.errors.email" />
    </div>

    <div class="mt-4">
        <InputLabel for="password" value="Password" />
        <TextInput id="password" v-model="form.password" type="password" class="mt-1 block w-full" required
            autocomplete="current-password" />
        <InputError class="mt-2" :message="form.errors.password" />
    </div>

    <div class="block mt-4">
        <label class="flex items-center">
            <Checkbox v-model:checked="form.remember" name="remember" />
            <span class="ms-2 text-sm text-gray-600">Remember me</span>
        </label>
    </div>

    <div class="flex items-center justify-end mt-4">
        <Link v-if="canResetPassword" :href="route('password.request')"
            class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
        Forgot your password?
        </Link>

        <PrimaryButton class="ms-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
            Log in
        </PrimaryButton>
    </div>
</form>
</AuthenticationCard> -->
</template>
<style scoped>
.pi-eye {
    transform: scale(1.6);
    margin-right: 1rem;
}

.pi-eye-slash {
    transform: scale(1.6);
    margin-right: 1rem;
}
</style>