<script setup>
import { computed, ref, watch } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import { route as ziggyRoute } from 'ziggy-js'
import axios from 'axios'

import LayoutDefault from '@/Layouts/LayoutDefault.vue'

defineOptions({
    layout: LayoutDefault,
})

const props = defineProps({
    profile: {
        type: Object,
        required: true,
    },
    entity: {
        type: Object,
        default: null,
    },
    entityClassifications: {
        type: Array,
        default: () => [],
    },
})

const page = usePage()

function route(name, params = {}, absolute = false) {
    const ziggy = page.props.ziggy

    if (ziggy?.routes?.[name]) {
        return ziggyRoute(name, params, absolute, ziggy)
    }

    const fallback = {
        dashboard: '/dashboard',
        'customer.profile.edit': '/dashboard/profile',
        'customer.profile.update': '/dashboard/profile',
        'location.cities': '/location/cities',
        'web.entities.lookup-by-inn': '/web/entities/lookup-by-inn',
        'profile.show': '/user/profile',
    }

    return fallback[name] || '#'
}

function hasRoute(name) {
    return Boolean(page.props.ziggy?.routes?.[name])
}

const initialCity = props.profile.city
    ? {
        id: props.profile.city.id,
        name: props.profile.city.name,
        region: props.profile.city.region,
        label: props.profile.city.label
            || `${props.profile.city.name}${props.profile.city.region ? ', ' + props.profile.city.region : ''}`,
    }
    : null

const form = useForm({
    account_type: props.profile.account_type || 'individual',

    name: props.profile.name || '',
    email: props.profile.email || '',
    phone: props.profile.phone || '',
    max_chat_id: props.profile.max_chat_id || '',
    delivery_address: props.profile.delivery_address || '',
    city_id: props.profile.city?.id || null,

    avatar: null,

    organization_inn: props.entity?.INN || '',
    organization_kpp: props.entity?.KPP || '',
    organization_ogrn: props.entity?.OGRN || '',
    organization_name: props.entity?.name || '',
    organization_full_name: props.entity?.full_name || '',
    organization_legal_address: props.entity?.legal_address || '',
    organization_opf: props.entity?.entity_classification_name || '',
    organization_dadata_raw: '',
    entity_classification_id: props.entity?.entity_classification_id || null,
})

const citySearch = ref('')
const cityLoading = ref(false)
const cityHint = ref('')
const cities = ref(initialCity ? [initialCity] : [])
const selectedCity = ref(initialCity)

const innLoading = ref(false)
const innMessage = ref('')

let cityTimer = null
let innTimer = null

const accountTypeLabel = computed(() => {
    return form.account_type === 'organization'
        ? 'Организация / ИП'
        : 'Физическое лицо'
})

watch(selectedCity, (city) => {
    form.city_id = city?.id || null
})

watch(citySearch, (value) => {
    clearTimeout(cityTimer)

    if (!value || value.length < 2) {
        return
    }

    cityTimer = setTimeout(fetchCities, 250)
})

watch(
    () => form.organization_inn,
    (value) => {
        clearTimeout(innTimer)

        form.organization_inn = onlyDigits(value)

        if (form.account_type !== 'organization') {
            return
        }

        if (![10, 12].includes(form.organization_inn.length)) {
            innMessage.value = ''
            return
        }

        innTimer = setTimeout(lookupByInn, 500)
    }
)

function onlyDigits(value) {
    return String(value || '').replace(/\D+/g, '')
}

function setAvatar(value) {
    if (Array.isArray(value)) {
        form.avatar = value[0] || null
        return
    }

    form.avatar = value || null
}

function fetchCities() {
    cityLoading.value = true
    cityHint.value = ''

    axios
        .get(route('location.cities'), {
            params: {
                q: citySearch.value,
            },
        })
        .then((response) => {
            cities.value = Array.isArray(response.data)
                ? response.data
                : response.data.data || []
        })
        .catch(() => {
            cityHint.value = 'Не удалось загрузить города.'
            cities.value = []
        })
        .finally(() => {
            cityLoading.value = false
        })
}

function lookupByInn() {
    if (form.account_type !== 'organization') {
        return
    }

    const inn = onlyDigits(form.organization_inn)

    if (![10, 12].includes(inn.length)) {
        innMessage.value = 'ИНН должен содержать 10 или 12 цифр.'
        return
    }

    innLoading.value = true
    innMessage.value = 'Ищем реквизиты по ИНН...'

    axios
        .get(route('web.entities.lookup-by-inn'), {
            params: {
                inn,
            },
        })
        .then((response) => {
            const suggestions = response.data?.data || []

            if (!suggestions.length) {
                innMessage.value = 'Организация не найдена. Можно заполнить реквизиты вручную.'
                return
            }

            applyEntitySuggestion(suggestions[0])
            innMessage.value = 'Реквизиты заполнены автоматически.'
        })
        .catch(() => {
            innMessage.value = 'Не удалось получить реквизиты. Можно заполнить вручную.'
        })
        .finally(() => {
            innLoading.value = false
        })
}

function applyEntitySuggestion(suggestion) {
    const entity = suggestion.entity || {}

    form.organization_name = entity.name || form.organization_name
    form.organization_full_name = entity.full_name || form.organization_full_name
    form.organization_kpp = entity.KPP || form.organization_kpp
    form.organization_ogrn = entity.OGRN || form.organization_ogrn
    form.organization_legal_address = entity.legal_address || form.organization_legal_address
    form.organization_opf = entity.opf || form.organization_opf
    form.entity_classification_id = entity.entity_classification_id || form.entity_classification_id
    form.organization_dadata_raw = JSON.stringify(suggestion.raw || {})
}

function submit() {
    form.clearErrors()

    form.post(route('customer.profile.update'), {
        forceFormData: true,
        preserveScroll: true,
    })
}
</script>

<template>
    <div class="profile-edit-page">
        <v-container>
            <div class="profile-edit-hero">
                <div>
                    <div class="profile-edit-eyebrow">
                        Личный кабинет
                    </div>

                    <h1 class="profile-edit-title">
                        Редактирование профиля
                    </h1>

                    <p class="profile-edit-text">
                        Здесь можно изменить личные данные, город доставки,
                        аватар и тип аккаунта: физическое лицо или организация.
                    </p>
                </div>

                <v-avatar size="78" class="profile-edit-avatar">
                    <v-img
                        v-if="profile.profile_photo_url"
                        :src="profile.profile_photo_url"
                        :alt="profile.name || 'Пользователь'"
                        cover
                    />

                    <span v-else>
                        {{ profile.name?.[0] || 'П' }}
                    </span>
                </v-avatar>
            </div>

            <form @submit.prevent="submit">
                <v-row dense>
                    <v-col cols="12" lg="8">
                        <v-card rounded="xl" elevation="2" class="profile-edit-card">
                            <v-card-title class="font-weight-bold">
                                Основные данные
                            </v-card-title>

                            <v-card-text>
                                <v-alert
                                    v-if="Object.keys(form.errors).length"
                                    type="error"
                                    variant="tonal"
                                    rounded="lg"
                                    class="mb-4"
                                >
                                    Проверьте поля формы. Некоторые данные заполнены некорректно.
                                </v-alert>

                                <v-row dense>
                                    <v-col cols="12">
                                        <div class="profile-edit-section-title">
                                            Тип аккаунта
                                        </div>

                                        <v-btn-toggle
                                            v-model="form.account_type"
                                            mandatory
                                            divided
                                            rounded="xl"
                                            class="mb-2"
                                        >
                                            <v-btn value="individual">
                                                Физическое лицо
                                            </v-btn>

                                            <v-btn value="organization">
                                                Организация / ИП
                                            </v-btn>
                                        </v-btn-toggle>

                                        <div
                                            v-if="form.errors.account_type"
                                            class="profile-edit-error"
                                        >
                                            {{ form.errors.account_type }}
                                        </div>

                                        <p class="profile-edit-hint">
                                            Текущий тип: {{ accountTypeLabel }}.
                                            Для организаций в дальнейшем можно показывать другие цены.
                                        </p>
                                    </v-col>

                                    <v-col cols="12" md="6">
                                        <v-text-field
                                            v-model="form.name"
                                            label="Имя / контактное лицо"
                                            variant="outlined"
                                            rounded="lg"
                                            :error-messages="form.errors.name"
                                            autocomplete="name"
                                        />
                                    </v-col>

                                    <v-col cols="12" md="6">
                                        <v-text-field
                                            v-model="form.email"
                                            label="Email"
                                            variant="outlined"
                                            rounded="lg"
                                            :error-messages="form.errors.email"
                                            autocomplete="email"
                                        />
                                    </v-col>

                                    <v-col cols="12" md="6">
                                        <v-text-field
                                            v-model="form.phone"
                                            label="Телефон"
                                            variant="outlined"
                                            rounded="lg"
                                            :error-messages="form.errors.phone"
                                            autocomplete="tel"
                                        />
                                    </v-col>

                                    <v-col cols="12" md="6">
                                        <v-text-field
                                            v-model="form.max_chat_id"
                                            label="MAX chat id"
                                            variant="outlined"
                                            rounded="lg"
                                            :error-messages="form.errors.max_chat_id"
                                        />
                                    </v-col>

                                    <v-col cols="12" md="6">
                                        <v-autocomplete
                                            v-model="selectedCity"
                                            v-model:search="citySearch"
                                            :items="cities"
                                            :loading="cityLoading"
                                            item-title="label"
                                            item-value="id"
                                            return-object
                                            clearable
                                            hide-no-data
                                            label="Город / село / деревня"
                                            variant="outlined"
                                            rounded="lg"
                                            :error-messages="form.errors.city_id || cityHint"
                                        />
                                    </v-col>

                                    <v-col cols="12">
                                        <v-textarea
                                            v-model="form.delivery_address"
                                            label="Адрес доставки"
                                            variant="outlined"
                                            rounded="lg"
                                            rows="2"
                                            auto-grow
                                            :error-messages="form.errors.delivery_address"
                                        />
                                    </v-col>

                                    <v-col cols="12">
                                        <v-file-input
                                            label="Новый аватар"
                                            variant="outlined"
                                            rounded="lg"
                                            accept="image/*"
                                            prepend-icon=""
                                            prepend-inner-icon="mdi-camera"
                                            :error-messages="form.errors.avatar"
                                            @update:model-value="setAvatar"
                                        />
                                    </v-col>
                                </v-row>
                            </v-card-text>
                        </v-card>

                        <v-expand-transition>
                            <v-card
                                v-if="form.account_type === 'organization'"
                                rounded="xl"
                                elevation="2"
                                class="profile-edit-card mt-4"
                            >
                                <v-card-title class="font-weight-bold">
                                    Реквизиты организации
                                </v-card-title>

                                <v-card-text>
                                    <v-row dense>
                                        <v-col cols="12" md="6">
                                            <v-text-field
                                                v-model="form.organization_inn"
                                                label="ИНН"
                                                variant="outlined"
                                                rounded="lg"
                                                maxlength="12"
                                                :loading="innLoading"
                                                :error-messages="form.errors.organization_inn"
                                                hint="Введите ИНН — реквизиты можно заполнить автоматически"
                                                persistent-hint
                                            />
                                        </v-col>

                                        <v-col cols="12" md="6" class="d-flex align-center">
                                            <v-btn
                                                color="#800000"
                                                rounded="xl"
                                                variant="tonal"
                                                :loading="innLoading"
                                                :disabled="![10, 12].includes(onlyDigits(form.organization_inn).length)"
                                                @click="lookupByInn"
                                            >
                                                Заполнить по ИНН
                                            </v-btn>
                                        </v-col>

                                        <v-col
                                            v-if="innMessage"
                                            cols="12"
                                        >
                                            <v-alert
                                                rounded="lg"
                                                variant="tonal"
                                                density="compact"
                                                :type="innMessage.includes('автоматически') ? 'success' : 'info'"
                                            >
                                                {{ innMessage }}
                                            </v-alert>
                                        </v-col>

                                        <v-col cols="12" md="6">
                                            <v-select
                                                v-model="form.entity_classification_id"
                                                :items="entityClassifications"
                                                item-title="name"
                                                item-value="id"
                                                label="Тип организации"
                                                variant="outlined"
                                                rounded="lg"
                                                clearable
                                                :error-messages="form.errors.entity_classification_id"
                                            />
                                        </v-col>

                                        <v-col cols="12" md="6">
                                            <v-text-field
                                                v-model="form.organization_kpp"
                                                label="КПП"
                                                variant="outlined"
                                                rounded="lg"
                                                :error-messages="form.errors.organization_kpp"
                                            />
                                        </v-col>

                                        <v-col cols="12" md="6">
                                            <v-text-field
                                                v-model="form.organization_ogrn"
                                                label="ОГРН / ОГРНИП"
                                                variant="outlined"
                                                rounded="lg"
                                                :error-messages="form.errors.organization_ogrn"
                                            />
                                        </v-col>

                                        <v-col cols="12" md="6">
                                            <v-text-field
                                                v-model="form.organization_opf"
                                                label="ОПФ"
                                                variant="outlined"
                                                rounded="lg"
                                                :error-messages="form.errors.organization_opf"
                                            />
                                        </v-col>

                                        <v-col cols="12">
                                            <v-text-field
                                                v-model="form.organization_name"
                                                label="Краткое название"
                                                variant="outlined"
                                                rounded="lg"
                                                :error-messages="form.errors.organization_name"
                                            />
                                        </v-col>

                                        <v-col cols="12">
                                            <v-text-field
                                                v-model="form.organization_full_name"
                                                label="Полное название"
                                                variant="outlined"
                                                rounded="lg"
                                                :error-messages="form.errors.organization_full_name"
                                            />
                                        </v-col>

                                        <v-col cols="12">
                                            <v-textarea
                                                v-model="form.organization_legal_address"
                                                label="Юридический адрес"
                                                variant="outlined"
                                                rounded="lg"
                                                rows="2"
                                                auto-grow
                                                :error-messages="form.errors.organization_legal_address"
                                            />
                                        </v-col>
                                    </v-row>
                                </v-card-text>
                            </v-card>
                        </v-expand-transition>
                    </v-col>

                    <v-col cols="12" lg="4">
                        <v-card rounded="xl" elevation="2" class="profile-edit-card profile-edit-card--sticky">
                            <v-card-title class="font-weight-bold">
                                Сохранение
                            </v-card-title>

                            <v-card-text>
                                <p class="profile-edit-text mb-4">
                                    После сохранения данные обновятся в личном кабинете.
                                    Если аккаунт переключить на организацию, пользователь будет
                                    связан с карточкой `Entity`.
                                </p>

                                <div class="profile-edit-summary">
                                    <div>
                                        <span>Тип</span>
                                        <strong>{{ accountTypeLabel }}</strong>
                                    </div>

                                    <div>
                                        <span>Город</span>
                                        <strong>{{ selectedCity?.name || 'Не выбран' }}</strong>
                                    </div>

                                    <div v-if="form.account_type === 'organization'">
                                        <span>ИНН</span>
                                        <strong>{{ form.organization_inn || 'Не указан' }}</strong>
                                    </div>
                                </div>
                            </v-card-text>

                            <v-card-actions class="px-4 pb-4">
                                <div class="profile-edit-actions">
                                    <v-btn
                                        color="#800000"
                                        rounded="xl"
                                        size="large"
                                        type="submit"
                                        :loading="form.processing"
                                        block
                                    >
                                        Сохранить профиль
                                    </v-btn>

                                    <Link :href="route('dashboard')" class="profile-edit-link-button">
                                        Вернуться в кабинет
                                    </Link>

                                    <Link
                                        v-if="hasRoute('profile.show')"
                                        :href="route('profile.show')"
                                        class="profile-edit-link-button profile-edit-link-button--muted"
                                    >
                                        Пароль и безопасность
                                    </Link>
                                </div>
                            </v-card-actions>
                        </v-card>
                    </v-col>
                </v-row>
            </form>
        </v-container>
    </div>
</template>

<style scoped>
.profile-edit-page {
    padding: 32px 0 44px;
    background: linear-gradient(180deg, #fffaf8 0%, #f8fafc 100%);
}

.profile-edit-hero {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 24px;
    padding: 28px;
    margin-bottom: 24px;
    border-radius: 28px;
    background:
        radial-gradient(circle at top right, rgba(128, 0, 0, 0.12), transparent 34%),
        linear-gradient(135deg, #fff 0%, #fff6f3 100%);
    border: 1px solid rgba(128, 0, 0, 0.08);
    box-shadow: 0 16px 35px rgba(63, 29, 29, 0.08);
}

.profile-edit-eyebrow {
    display: inline-flex;
    padding: 7px 12px;
    border-radius: 999px;
    background: rgba(128, 0, 0, 0.08);
    color: #800000;
    font-weight: 800;
    font-size: 0.86rem;
}

.profile-edit-title {
    margin: 12px 0 10px;
    color: #3f1d1d;
    font-size: 2rem;
    line-height: 1.1;
    font-weight: 900;
}

.profile-edit-text,
.profile-edit-hint {
    color: #655c57;
    line-height: 1.65;
}

.profile-edit-hint {
    margin-top: 8px;
    font-size: 0.92rem;
}

.profile-edit-avatar {
    background: #800000;
    color: #fff;
    font-size: 1.7rem;
    font-weight: 900;
    flex-shrink: 0;
}

.profile-edit-card {
    background: #fff;
}

.profile-edit-card--sticky {
    position: sticky;
    top: 92px;
}

.profile-edit-section-title {
    margin-bottom: 12px;
    color: #3f1d1d;
    font-weight: 900;
}

.profile-edit-error {
    margin-top: 6px;
    color: #b91c1c;
    font-size: 0.86rem;
}

.profile-edit-summary {
    display: grid;
    gap: 10px;
}

.profile-edit-summary div {
    display: flex;
    justify-content: space-between;
    gap: 14px;
    padding: 10px 0;
    border-bottom: 1px solid rgba(128, 0, 0, 0.08);
}

.profile-edit-summary span {
    color: #7c6f6a;
}

.profile-edit-summary strong {
    color: #3f1d1d;
    text-align: right;
}

.profile-edit-actions {
    width: 100%;
    display: grid;
    gap: 10px;
}

.profile-edit-link-button {
    min-height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    text-decoration: none;
    color: #800000;
    border: 1px solid rgba(128, 0, 0, 0.35);
    font-weight: 800;
}

.profile-edit-link-button--muted {
    color: #655c57;
    border-color: rgba(101, 92, 87, 0.25);
}

@media (max-width: 960px) {
    .profile-edit-card--sticky {
        position: static;
    }
}

@media (max-width: 600px) {
    .profile-edit-hero {
        flex-direction: column;
        align-items: flex-start;
        padding: 22px;
    }

    .profile-edit-title {
        font-size: 1.55rem;
    }
}
</style>
