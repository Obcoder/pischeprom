<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import axios from 'axios'
import { route } from 'ziggy-js'
import { ref } from 'vue'

const innLoading = ref(false)
const innSuggestions = ref([])

function lookupEntityByInn() {
    const inn = String(form.organization_INN || '').replace(/\D+/g, '')

    if (![10, 12].includes(inn.length)) {
        innSuggestions.value = []
        return
    }

    innLoading.value = true

    axios.get(route('web.entities.lookup-by-inn'), {
        params: {
            inn,
        },
    })
        .then((response) => {
            innSuggestions.value = response.data.data || []

            if (innSuggestions.value.length === 1) {
                applyEntitySuggestion(innSuggestions.value[0])
            }
        })
        .finally(() => {
            innLoading.value = false
        })
}

function applyEntitySuggestion(suggestion) {
    const entity = suggestion.entity || {}

    form.organization_name = entity.name || ''
    form.organization_full_name = entity.full_name || ''
    form.organization_KPP = entity.KPP || ''
    form.organization_OGRN = entity.OGRN || ''
    form.organization_legal_address = entity.legal_address || ''
    form.entity_classification_id = entity.entity_classification_id || null
    form.organization_opf = entity.opf || ''
    form.organization_dadata_raw = suggestion.raw || null
}

const form = useForm({
    account_type: 'individual',

    name: '',
    email: '',
    phone: '',
    city_id: null,
    password: '',
    password_confirmation: '',
    avatar: null,

    personal_data_consent: false,
    marketing_consent: false,

    organization_INN: '',
    organization_KPP: '',
    organization_OGRN: '',
    organization_name: '',
    organization_full_name: '',
    organization_legal_address: '',
    organization_opf: '',
    organization_dadata_raw: null,
    entity_classification_id: null,
})

const submit = () => {
    form.post(route('register'), {
        forceFormData: true,
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Register" />

    <AuthenticationCard>
        <template #logo>
            <AuthenticationCardLogo />
        </template>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="name" value="Name" />
                <TextInput
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="mt-1 block w-full"
                    required
                    autofocus
                    autocomplete="name"
                />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div class="mt-4">
                <InputLabel for="email" value="Email" />
                <TextInput
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="mt-1 block w-full"
                    required
                    autocomplete="username"
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="Password" />
                <TextInput
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4">
                <InputLabel for="password_confirmation" value="Confirm Password" />
                <TextInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-2" :message="form.errors.password_confirmation" />
            </div>

            <div v-if="$page.props.jetstream.hasTermsAndPrivacyPolicyFeature" class="mt-4">
                <InputLabel for="terms">
                    <div class="flex items-center">
                        <Checkbox id="terms" v-model:checked="form.terms" name="terms" required />

                        <div class="ms-2">
                            I agree to the <a target="_blank" :href="route('terms.show')" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Terms of Service</a> and <a target="_blank" :href="route('policy.show')" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Privacy Policy</a>
                        </div>
                    </div>
                    <InputError class="mt-2" :message="form.errors.terms" />
                </InputLabel>
            </div>

            <div class="flex items-center justify-end mt-4">
                <Link :href="route('login')" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Already registered?
                </Link>

                <PrimaryButton class="ms-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Register
                </PrimaryButton>
            </div>
        </form>
    </AuthenticationCard>
</template>
