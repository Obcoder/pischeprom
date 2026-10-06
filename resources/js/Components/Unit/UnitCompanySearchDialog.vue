<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import axios from 'axios'
import { route } from 'ziggy-js'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    industries: { type: Array, default: () => [] },
})
const emit = defineEmits(['update:modelValue'])

const form = reactive({ query: '', okved: [], status: ['ACTIVE'], type: null, region_code: '' })
const codeSearch = ref('')
const results = ref([])
const loading = ref(false)
const searched = ref(false)
const error = ref('')
const errors = ref({})
const copyMessage = ref('')
let requestController = null
let requestId = 0

const statuses = [
    { title: 'Действует', value: 'ACTIVE' },
    { title: 'Ликвидируется', value: 'LIQUIDATING' },
    { title: 'Ликвидирована', value: 'LIQUIDATED' },
    { title: 'Банкротство', value: 'BANKRUPT' },
    { title: 'Реорганизация', value: 'REORGANIZING' },
]
const companyTypes = [
    { title: 'Юридические лица', value: 'LEGAL' },
    { title: 'Индивидуальные предприниматели', value: 'INDIVIDUAL' },
]
const industryOptions = computed(() => props.industries.filter(industry => industry.code))
const okvedPattern = /^[0-9]{2}(?:\.[0-9](?:[0-9](?:\.[0-9]{1,2})?)?)?$/

function fieldErrors(field) {
    return Object.entries(errors.value)
        .filter(([key]) => key === field || key.startsWith(`${field}.`))
        .flatMap(([, messages]) => Array.isArray(messages) ? messages : [messages])
}

function cancelRequest() {
    requestId += 1
    requestController?.abort()
    requestController = null
    loading.value = false
}

function updateDialog(opened) {
    if (!opened) cancelRequest()
    emit('update:modelValue', opened)
}

function resetResults() {
    cancelRequest()
    results.value = []
    searched.value = false
    error.value = ''
    errors.value = {}
    copyMessage.value = ''
}

function searchPayload() {
    const codes = [...form.okved]
    if (codeSearch.value?.trim()) codes.push(codeSearch.value)

    return {
        query: String(form.query || '').trim(),
        okved: [...new Set(codes.map(code => String(code?.code ?? code).trim()).filter(Boolean))],
        status: [...form.status],
        ...(form.type ? { type: form.type } : {}),
        ...(String(form.region_code || '').trim() ? { region_code: String(form.region_code).trim() } : {}),
        count: 20,
    }
}

async function searchCompanies() {
    if (!props.modelValue) return
    resetResults()
    const payload = searchPayload()

    if (payload.query.length < 2 || payload.query.length > 300) {
        errors.value.query = ['Введите название, ИНН или адрес: от 2 до 300 символов.']
    }
    if (!payload.okved.length || payload.okved.length > 10 || payload.okved.some(code => !okvedPattern.test(code))) {
        errors.value.okved = ['Укажите от 1 до 10 кодов ОКВЭД, например 10.51 или 46.38.']
    }
    if (payload.region_code && !/^(?:0[1-9]|[1-9][0-9])$/.test(payload.region_code)) {
        errors.value.region_code = ['Укажите код региона от 01 до 99, например 77.']
    }
    if (Object.keys(errors.value).length) return

    const currentRequest = ++requestId
    requestController = new AbortController()
    loading.value = true

    try {
        const { data } = await axios.post(route('web.units.company-search'), payload, {
            signal: requestController.signal,
        })
        if (currentRequest !== requestId || !props.modelValue) return
        results.value = Array.isArray(data.data) ? data.data : []
        searched.value = true
    } catch (exception) {
        if (currentRequest !== requestId || axios.isCancel(exception)) return
        const response = exception?.response
        if (response?.status === 422) {
            errors.value = response.data?.errors || {}
            error.value = 'Проверьте параметры поиска.'
        } else {
            error.value = response?.data?.message || (response?.status === 503
                ? 'Поиск DaData сейчас недоступен. Повторите попытку позже.'
                : 'Не удалось получить компании. Попробуйте ещё раз.')
        }
    } finally {
        if (currentRequest === requestId) {
            loading.value = false
            requestController = null
        }
    }
}

function statusTitle(status) {
    return statuses.find(item => item.value === status)?.title || status || 'Статус не указан'
}

async function copyInn(inn) {
    try {
        await navigator.clipboard.writeText(String(inn))
        copyMessage.value = `ИНН ${inn} скопирован.`
    } catch {
        copyMessage.value = 'Не удалось скопировать ИНН. Выделите номер и скопируйте вручную.'
    }
}

watch(form, resetResults, { deep: true, flush: 'sync' })
watch(codeSearch, resetResults, { flush: 'sync' })
watch(() => props.modelValue, opened => {
    if (!opened) cancelRequest()
})
onBeforeUnmount(cancelRequest)
</script>

<template>
    <v-dialog :model-value="modelValue" max-width="1120" scrollable @update:model-value="updateDialog">
        <v-card theme="dark" class="company-search">
            <v-toolbar title="Компании по ОКВЭД" color="blue-grey-darken-4" density="compact">
                <v-btn icon="mdi-close" aria-label="Закрыть поиск компаний" @click="updateDialog(false)" />
            </v-toolbar>
            <v-card-text>
                <v-alert type="info" variant="tonal" density="compact" class="mb-4">
                    DaData показывает до 20 совпадений по названию, ИНН или адресу.
                    Фильтр учитывает точное совпадение основного ОКВЭД: код 10.51 не включает 10.51.1.
                    Полная выгрузка компаний по отрасли недоступна.
                </v-alert>

                <v-form @submit.prevent="searchCompanies">
                    <v-row dense>
                        <v-col cols="12" md="6">
                            <v-text-field
                                v-model="form.query"
                                label="Название, ИНН или адрес"
                                placeholder="Например, молоко или Москва"
                                hint="Обязательный поисковый текст, минимум 2 символа"
                                persistent-hint
                                :maxlength="300"
                                :error-messages="fieldErrors('query')"
                                variant="outlined"
                                density="compact"
                                clearable
                            />
                        </v-col>
                        <v-col cols="12" md="6">
                            <v-combobox
                                v-model="form.okved"
                                v-model:search="codeSearch"
                                :items="industryOptions"
                                item-title="code"
                                item-value="code"
                                :return-object="false"
                                label="Основной ОКВЭД"
                                hint="До 10 точных кодов. Введите код и нажмите Enter."
                                persistent-hint
                                :error-messages="fieldErrors('okved')"
                                multiple
                                chips
                                closable-chips
                                clearable
                                variant="outlined"
                                density="compact"
                                @keydown.enter.stop
                            >
                                <template #item="{ props: itemProps, item }">
                                    <v-list-item v-bind="itemProps" :subtitle="item.raw.title" />
                                </template>
                            </v-combobox>
                        </v-col>
                        <v-col cols="12" md="5">
                            <v-select
                                v-model="form.status"
                                :items="statuses"
                                label="Статус компании"
                                hint="Пустой выбор — все статусы"
                                persistent-hint
                                :error-messages="fieldErrors('status')"
                                multiple
                                chips
                                clearable
                                variant="outlined"
                                density="compact"
                            />
                        </v-col>
                        <v-col cols="12" sm="8" md="5">
                            <v-select
                                v-model="form.type"
                                :items="companyTypes"
                                label="Тип организации"
                                placeholder="Юрлица и ИП"
                                persistent-placeholder
                                :error-messages="fieldErrors('type')"
                                clearable
                                variant="outlined"
                                density="compact"
                            />
                        </v-col>
                        <v-col cols="12" sm="4" md="2">
                            <v-text-field
                                v-model="form.region_code"
                                label="Код региона"
                                placeholder="77"
                                inputmode="numeric"
                                :maxlength="2"
                                :error-messages="fieldErrors('region_code')"
                                variant="outlined"
                                density="compact"
                                clearable
                            />
                        </v-col>
                    </v-row>
                    <div class="d-flex ga-2 align-center mb-4">
                        <v-btn type="submit" color="teal" variant="tonal" prepend-icon="mdi-magnify" :loading="loading">
                            Найти компании
                        </v-btn>
                        <v-btn v-if="loading" variant="text" @click="cancelRequest">Отменить</v-btn>
                    </div>
                </v-form>

                <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-4" role="alert">
                    {{ error }}
                </v-alert>
                <div v-if="copyMessage" class="text-body-2 mb-3" role="status">{{ copyMessage }}</div>

                <template v-if="searched">
                    <p class="text-body-2 mb-3" role="status">
                        <template v-if="results.length">
                            Показано компаний: {{ results.length }}. Это часть результатов DaData; уточняйте запрос для поиска других компаний.
                        </template>
                        <template v-else>
                            Совпадений не найдено. Измените поисковый текст, код ОКВЭД или другие фильтры.
                        </template>
                    </p>

                    <v-card
                        v-for="(result, index) in results"
                        :key="`${result.entity.INN}-${result.entity.KPP || ''}-${index}`"
                        variant="outlined"
                        class="company-search__result mb-3 pa-4"
                    >
                        <div class="d-flex flex-wrap ga-2 align-center mb-2">
                            <h2 class="company-search__name">{{ result.entity.name || result.entity.full_name || 'Без названия' }}</h2>
                            <v-chip size="small" :color="result.entity.status === 'ACTIVE' ? 'teal' : 'blue-grey'">
                                {{ statusTitle(result.entity.status) }}
                            </v-chip>
                        </div>
                        <p v-if="result.entity.full_name && result.entity.full_name !== result.entity.name" class="text-body-2 mb-2">
                            {{ result.entity.full_name }}
                        </p>
                        <div class="company-search__details">
                            <span>
                                ИНН <span class="company-search__inn">{{ result.entity.INN || '—' }}</span>
                                <v-btn
                                    v-if="result.entity.INN"
                                    icon="mdi-content-copy"
                                    size="x-small"
                                    variant="text"
                                    :aria-label="`Скопировать ИНН ${result.entity.INN}`"
                                    @click="copyInn(result.entity.INN)"
                                />
                            </span>
                            <span v-if="result.entity.KPP">КПП {{ result.entity.KPP }}</span>
                            <span v-if="result.entity.OGRN">ОГРН {{ result.entity.OGRN }}</span>
                            <span>Основной ОКВЭД {{ result.entity.okved || '—' }}</span>
                        </div>
                        <p class="text-body-2 mt-2">{{ result.entity.legal_address || 'Адрес не указан' }}</p>
                        <div v-if="result.existing_entities?.length" class="company-search__existing mt-3">
                            <div class="text-caption mb-1">Уже есть в приложении:</div>
                            <div v-for="entity in result.existing_entities" :key="entity.id" class="d-flex flex-wrap ga-2 mb-1">
                                <Link :href="route('Ameise.entity.show', entity.id)">Entity: {{ entity.name }}</Link>
                                <Link v-for="unit in entity.units || []" :key="unit.id" :href="route('web.unit.show', unit.id)">
                                    Unit: {{ unit.name }}
                                </Link>
                            </div>
                        </div>
                    </v-card>
                </template>
            </v-card-text>
            <v-card-actions class="justify-end">
                <v-btn variant="text" @click="updateDialog(false)">Закрыть</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>
.company-search {
    background: #0f172a;
    color: #e2e8f0;
}

.company-search__result {
    border-color: #334155;
}

.company-search__name {
    font-size: 1rem;
    font-weight: 600;
    overflow-wrap: anywhere;
}

.company-search__details {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 4px 20px;
    color: #cbd5e1;
    font-size: 0.8rem;
}

.company-search__inn {
    user-select: text;
}

.company-search__existing {
    border-top: 1px solid #334155;
    padding-top: 10px;
}

.company-search__existing a {
    color: #5eead4;
    font-size: 0.85rem;
    text-decoration: underline;
    text-underline-offset: 3px;
}
</style>
