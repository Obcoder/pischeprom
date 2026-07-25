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
    terms: false,

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
                <InputLabel for="account_type" value="Тип покупателя" />
                <select
                    id="account_type"
                    v-model="form.account_type"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="individual">Физическое лицо</option>
                    <option value="organization">Организация / ИП</option>
                </select>
                <InputError class="mt-2" :message="form.errors.account_type" />
            </div>

            <div class="mt-4">
                <InputLabel for="name" value="Имя / контактное лицо" />
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
                <InputLabel for="phone" value="Телефон" />
                <TextInput
                    id="phone"
                    v-model="form.phone"
                    type="tel"
                    class="mt-1 block w-full"
                    autocomplete="tel"
                    placeholder="+7"
                />
                <InputError class="mt-2" :message="form.errors.phone" />
            </div>

            <div
                v-if="form.account_type === 'organization'"
                class="mt-4 space-y-4 rounded-lg border border-gray-200 bg-gray-50 p-4"
            >
                <div>
                    <InputLabel for="organization_INN" value="ИНН" />
                    <div class="mt-1 flex gap-2">
                        <TextInput
                            id="organization_INN"
                            v-model="form.organization_INN"
                            type="text"
                            class="block w-full"
                            inputmode="numeric"
                            @blur="lookupEntityByInn"
                        />
                        <button
                            type="button"
                            class="rounded-md border border-gray-300 px-3 text-sm font-semibold text-gray-700"
                            :disabled="innLoading"
                            @click="lookupEntityByInn"
                        >
                            Найти
                        </button>
                    </div>
                    <InputError class="mt-2" :message="form.errors.organization_inn || form.errors.organization_INN" />
                </div>

                <div v-if="innSuggestions.length > 1" class="grid gap-2">
                    <button
                        v-for="suggestion in innSuggestions"
                        :key="suggestion.entity?.INN || suggestion.value"
                        type="button"
                        class="rounded-md border border-gray-200 bg-white p-2 text-left text-sm"
                        @click="applyEntitySuggestion(suggestion)"
                    >
                        {{ suggestion.entity?.name || suggestion.value }}
                    </button>
                </div>

                <div>
                    <InputLabel for="organization_name" value="Название организации" />
                    <TextInput
                        id="organization_name"
                        v-model="form.organization_name"
                        type="text"
                        class="mt-1 block w-full"
                    />
                    <InputError class="mt-2" :message="form.errors.organization_name" />
                </div>
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="Пароль" />
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
                <InputLabel for="password_confirmation" value="Подтверждение пароля" />
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

            <div class="mt-4">
                <label class="flex items-start gap-2 text-sm text-gray-700">
                    <Checkbox
                        id="personal_data_consent"
                        v-model:checked="form.personal_data_consent"
                        name="personal_data_consent"
                        required
                    />
                    <span>Согласен на обработку персональных данных</span>
                </label>
                <InputError class="mt-2" :message="form.errors.personal_data_consent" />
            </div>

            <div class="mt-4">
                <label class="flex items-start gap-2 text-sm text-gray-700">
                    <Checkbox
                        id="marketing_consent"
                        v-model:checked="form.marketing_consent"
                        name="marketing_consent"
                    />
                    <span>Получать новости и специальные предложения</span>
                </label>
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
                    Уже зарегистрированы?
                </Link>

                <PrimaryButton class="ms-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Зарегистрироваться
                </PrimaryButton>
            </div>
        </form>
    </AuthenticationCard>
</template>
