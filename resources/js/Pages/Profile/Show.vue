<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm.vue';
import LogoutOtherBrowserSessionsForm from '@/Pages/Profile/Partials/LogoutOtherBrowserSessionsForm.vue';
import TwoFactorAuthenticationForm from '@/Pages/Profile/Partials/TwoFactorAuthenticationForm.vue';
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from '@/Pages/Profile/Partials/UpdateProfileInformationForm.vue';
import { ref } from 'vue';

defineProps({
    confirmsTwoFactorAuthentication: Boolean,
    sessions: Array,
});

const home = ref({ icon: 'pi pi-home', url: route('dashboard') });
const items = ref([{ label: 'Perfil' }]);
</script>

<template>
    <Head title="Perfil" />

    <AppLayout>
        <Breadcrumb :home="home" :model="items" class="!bg-transparent" />
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="space-y-6">
                <div v-if="$page.props.jetstream.canUpdateProfileInformation">
                    <UpdateProfileInformationForm :user="$page.props.auth.user" />
                </div>

                <div v-if="$page.props.jetstream.canUpdatePassword">
                    <UpdatePasswordForm />
                </div>

                <div v-if="$page.props.jetstream.canManageTwoFactorAuthentication">
                    <TwoFactorAuthenticationForm :requires-confirmation="confirmsTwoFactorAuthentication" />
                </div>

                <LogoutOtherBrowserSessionsForm :sessions="sessions" />

                <template v-if="$page.props.jetstream.hasAccountDeletionFeatures">
                    <Divider />
                    <DeleteUserForm />
                </template>
            </div>
        </div>
    </AppLayout>
</template>
