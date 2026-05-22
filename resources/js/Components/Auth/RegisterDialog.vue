<script setup>
import { computed, ref, watch } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import { route as ziggyRoute } from 'ziggy-js'

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits([
    'update:modelValue',
])

const page = usePage()

const dialog = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
})

const hasRoute = (name) => {
    return Boolean(page.props.ziggy?.routes?.[name])
}

const route = (name, params = {}, absolute = true) => {
    return ziggyRoute(name, params, absolute, page.props.ziggy)
}

const registerRouteName = computed(() => {
    return hasRoute('register.store')
        ? 'register.store'
        : 'register'
})

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

    organization_inn: '',
    organization_kpp: '',
    organization_ogrn: '',
    organization_name: '',
    organization_full_name: '',
    organization_legal_address: '',
    organization_opf: '',
    organization_dadata_raw: '',
    entity_classification_id: null,
})

const citySearch = ref('')
const cityLoading = ref(false)
const cities = ref([])
const selectedCity = ref(null)
const cityHint = ref('')

const innLoading = ref(false)
const innMessage = ref('')
const submitError = ref('')

let cityTimer = null
let innTimer = null

watch(dialog, (isOpen) => {
    if (isOpen) {
        const currentCity = page.props.location?.city

        if (currentCity?.id && !form.city_id) {
            selectedCity.value = currentCity
            form.city_id = currentCity.id
            cities.value = [currentCity]
        }
    }
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

        const inn = onlyDigits(value)

        form.organization_inn = inn

        if (form.account_type !== 'organization') {
            return
        }

        if (![10, 12].includes(inn.length)) {
            innMessage.value = ''
            return
        }

        innTimer = setTimeout(lookupByInn, 450)
    }
)

watch(
    () => form.account_type,
    (value) => {
        submitError.value = ''

        if (value === 'individual') {
            form.organization_inn = ''
            form.organization_kpp = ''
            form.organization_ogrn = ''
            form.organization_name = ''
            form.organization_full_name = ''
            form.organization_legal_address = ''
            form.organization_opf = ''
            form.organization_dadata_raw = ''
            form.entity_classification_id = null
            innMessage.value = ''
        }
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
    if (!hasRoute('location.cities')) {
        cityHint.value = 'Маршрут location.cities пока не подключён.'
        return
    }

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
            cities.value = []
            cityHint.value = 'Не удалось загрузить города.'
        })
        .finally(() => {
            cityLoading.value = false
        })
}

function lookupByInn() {
    if (!hasRoute('web.entities.lookup-by-inn')) {
        innMessage.value = 'Маршрут поиска организации по ИНН пока не подключён.'
        return
    }

    const inn = onlyDigits(form.organization_inn)

    if (![10, 12].includes(inn.length)) {
        return
    }

    innLoading.value = true
    innMessage.value = 'Ищем организацию по ИНН...'

    axios
        .get(route('web.entities.lookup-by-inn'), {
            params: {
                inn,
            },
        })
        .then((response) => {
            const suggestions = response.data?.data || []

            if (!suggestions.length) {
                innMessage.value = 'Организация не найдена. Реквизиты можно заполнить вручную.'
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

    form.organization_name = entity.name || ''
    form.organization_full_name = entity.full_name || ''
    form.organization_kpp = entity.KPP || ''
    form.organization_ogrn = entity.OGRN || ''
    form.organization_legal_address = entity.legal_address || ''
    form.organization_opf = entity.opf || ''
    form.entity_classification_id = entity.entity_classification_id || null
    form.organization_dadata_raw = JSON.stringify(suggestion.raw || {})
}

function submit() {
    submitError.value = ''
    form.clearErrors()

    if (!hasRoute(registerRouteName.value)) {
        submitError.value = 'Маршрут регистрации не найден в Ziggy.'
        return
    }

    form.post(route(registerRouteName.value), {
        forceFormData: true,
        preserveScroll: true,

        onSuccess: () => {
            dialog.value = false
            form.reset('password', 'password_confirmation')
        },

        onError: () => {
            submitError.value = 'Проверьте поля формы. Ошибки подсвечены ниже.'
        },
    })
}
</script>

<template>
    <v-dialog
        v-model="dialog"
        max-width="760"
        scrollable
    >
        <v-card rounded="xl" class="register-dialog">
            <v-card-title class="register-dialog__title">
                <div>
                    <div class="register-dialog__eyebrow">
                        ПИЩЕПРОМ-СЕРВЕР
                    </div>

                    <div class="text-h5 font-weight-black">
                        Регистрация покупателя
                    </div>
                </div>

                <v-btn
                    icon="mdi-close"
                    variant="text"
                    size="small"
                    @click="dialog = false"
                />
            </v-card-title>

            <v-divider />

            <v-card-text class="register-dialog__body">
                <v-alert
                    v-if="submitError"
                    type="error"
                    variant="tonal"
                    rounded="lg"
                    class="mb-4"
                >
                    {{ submitError }}
                </v-alert>

                <form @submit.prevent="submit">
                    <div class="register-dialog__section">
                        <div class="register-dialog__section-title">
                            Тип покупателя
                        </div>

                        <v-btn-toggle
                            v-model="form.account_type"
                            mandatory
                            divided
                            rounded="xl"
                            class="register-dialog__toggle"
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
                            class="register-dialog__error"
                        >
                            {{ form.errors.account_type }}
                        </div>
                    </div>

                    <div class="register-dialog__section">
                        <div class="register-dialog__section-title">
                            Основные данные
                        </div>

                        <v-row dense>
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
                                    @click:clear="selectedCity = null; form.city_id = null"
                                />
                            </v-col>

                            <v-col cols="12">
                                <v-file-input
                                    label="Аватар, необязательно"
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
                    </div>

                    <v-expand-transition>
                        <div
                            v-if="form.account_type === 'organization'"
                            class="register-dialog__section"
                        >
                            <div class="register-dialog__section-title">
                                Реквизиты организации
                            </div>

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
                                        hint="Введите ИНН — реквизиты попробуем заполнить автоматически"
                                        persistent-hint
                                    />
                                </v-col>

                                <v-col cols="12" md="6" class="d-flex align-center">
                                    <v-btn
                                        color="#800000"
                                        variant="tonal"
                                        rounded="xl"
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
                                        variant="tonal"
                                        rounded="lg"
                                        :type="innMessage.includes('автоматически') ? 'success' : 'info'"
                                        density="compact"
                                    >
                                        {{ innMessage }}
                                    </v-alert>
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
                        </div>
                    </v-expand-transition>

                    <div class="register-dialog__section">
                        <div class="register-dialog__section-title">
                            Пароль
                        </div>

                        <v-row dense>
                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="form.password"
                                    label="Пароль"
                                    type="password"
                                    variant="outlined"
                                    rounded="lg"
                                    :error-messages="form.errors.password"
                                    autocomplete="new-password"
                                />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="form.password_confirmation"
                                    label="Повторите пароль"
                                    type="password"
                                    variant="outlined"
                                    rounded="lg"
                                    :error-messages="form.errors.password_confirmation"
                                    autocomplete="new-password"
                                />
                            </v-col>
                        </v-row>
                    </div>

                    <div class="register-dialog__section register-dialog__section--consents">
                        <v-checkbox
                            v-model="form.personal_data_consent"
                            :error-messages="form.errors.personal_data_consent"
                            density="compact"
                            hide-details="auto"
                        >
                            <template #label>
                                <span>
                                    Согласен на обработку персональных данных
                                    <a
                                        href="/personal-data-consent"
                                        target="_blank"
                                        class="register-dialog__link"
                                    >
                                        открыть текст согласия
                                    </a>
                                </span>
                            </template>
                        </v-checkbox>

                        <v-checkbox
                            v-model="form.marketing_consent"
                            density="compact"
                            hide-details
                            label="Согласен получать информационные и акционные сообщения"
                        />
                    </div>

                    <div class="register-dialog__actions">
                        <v-btn
                            variant="text"
                            rounded="xl"
                            @click="dialog = false"
                        >
                            Отмена
                        </v-btn>

                        <v-btn
                            color="#800000"
                            rounded="xl"
                            size="large"
                            type="submit"
                            :loading="form.processing"
                        >
                            Зарегистрироваться
                        </v-btn>
                    </div>
                </form>
            </v-card-text>

            <v-divider />

            <v-card-actions class="register-dialog__footer">
                <span class="text-body-2 text-medium-emphasis">
                    Уже есть аккаунт?
                </span>

                <Link
                    :href="route('login')"
                    class="register-dialog__login-link"
                >
                    Войти
                </Link>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>
.register-dialog {
    background:
        radial-gradient(circle at top right, rgba(128, 0, 0, 0.08), transparent 32%),
        #fffaf8;
}

.register-dialog__title {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    padding: 22px 24px;
    color: #3f1d1d;
}

.register-dialog__eyebrow {
    display: inline-flex;
    margin-bottom: 8px;
    padding: 6px 10px;
    border-radius: 999px;
    background: rgba(128, 0, 0, 0.08);
    color: #800000;
    font-size: 0.78rem;
    font-weight: 900;
    letter-spacing: 0.04em;
}

.register-dialog__body {
    padding: 22px 24px 24px;
}

.register-dialog__section {
    padding: 18px;
    margin-bottom: 16px;
    border-radius: 22px;
    background: rgba(255, 255, 255, 0.86);
    border: 1px solid rgba(128, 0, 0, 0.08);
}

.register-dialog__section--consents {
    padding-top: 10px;
    padding-bottom: 10px;
}

.register-dialog__section-title {
    margin-bottom: 14px;
    color: #3f1d1d;
    font-weight: 900;
}

.register-dialog__toggle {
    flex-wrap: wrap;
}

.register-dialog__error {
    margin-top: 8px;
    color: #b91c1c;
    font-size: 0.82rem;
}

.register-dialog__actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.register-dialog__footer {
    justify-content: center;
    gap: 8px;
    padding: 14px 24px 18px;
}

.register-dialog__link,
.register-dialog__login-link {
    color: #800000;
    font-weight: 700;
    text-decoration: none;
}

.register-dialog__link:hover,
.register-dialog__login-link:hover {
    text-decoration: underline;
}

@media (max-width: 600px) {
    .register-dialog__title,
    .register-dialog__body {
        padding-left: 16px;
        padding-right: 16px;
    }

    .register-dialog__section {
        padding: 14px;
    }

    .register-dialog__actions {
        flex-direction: column-reverse;
    }

    .register-dialog__actions :deep(.v-btn) {
        width: 100%;
    }
}
</style>
