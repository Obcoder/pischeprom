<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import axios from 'axios'
import { route } from 'ziggy-js'

const countries = ref([])
const loading = ref(false)
const saving = ref(false)
const deletingId = ref(null)
const dialog = ref(false)
const selectedCountry = ref(null)
const notice = ref('')
const error = ref('')
const sortBy = ref([{ key: 'name', order: 'asc' }])

const filters = reactive({
    search: '',
    codeISO: '',
    codeTelefon: '',
    flag: null,
    usage: null,
})

const form = reactive({
    name: '',
    codeISO: '',
    codeTelefon: '',
    flag_url: '',
    flag_file: null,
    remove_flag: false,
})

const flagFilterOptions = [
    { title: 'Все', value: null },
    { title: 'С флагом', value: 'yes' },
    { title: 'Без флага', value: 'no' },
]

const usageFilterOptions = [
    { title: 'Все', value: null },
    { title: 'Есть связи', value: 'used' },
    { title: 'Без связей', value: 'free' },
]

const headers = [
    { title: 'Флаг', key: 'flag', sortable: false, width: 92 },
    { title: 'Страна', key: 'name', minWidth: 220 },
    { title: 'ISO', key: 'codeISO', width: 92 },
    { title: 'Телефон', key: 'codeTelefon', width: 120 },
    { title: 'Regions', key: 'regions_count', align: 'end', width: 92 },
    { title: 'Goods', key: 'goods_count', align: 'end', width: 92 },
    { title: 'Entities', key: 'entities_count', align: 'end', width: 96 },
    { title: 'Updated', key: 'updated_at', width: 170 },
    { title: '', key: 'actions', sortable: false, width: 116 },
]

const summary = computed(() => {
    const withFlag = countries.value.filter((country) => Boolean(country.flag)).length
    const used = countries.value.filter((country) => relationCount(country) > 0).length

    return {
        total: countries.value.length,
        filtered: filteredCountries.value.length,
        withFlag,
        withoutFlag: countries.value.length - withFlag,
        used,
    }
})

const filteredCountries = computed(() => {
    const search = normalize(filters.search)
    const codeISO = normalize(filters.codeISO)
    const codeTelefon = normalize(filters.codeTelefon)

    return countries.value.filter((country) => {
        const values = [
            country.name,
            country.codeISO,
            country.codeTelefon,
        ].map(normalize)

        const matchesSearch = !search || values.some((value) => value.includes(search))
        const matchesISO = !codeISO || normalize(country.codeISO).includes(codeISO)
        const matchesPhone = !codeTelefon || normalize(country.codeTelefon).includes(codeTelefon)
        const matchesFlag = filters.flag === 'yes'
            ? Boolean(country.flag)
            : filters.flag === 'no'
                ? !country.flag
                : true
        const matchesUsage = filters.usage === 'used'
            ? relationCount(country) > 0
            : filters.usage === 'free'
                ? relationCount(country) === 0
                : true

        return matchesSearch && matchesISO && matchesPhone && matchesFlag && matchesUsage
    })
})

function normalize(value) {
    return String(value || '').trim().toLowerCase()
}

function relationCount(country) {
    return Number(country?.regions_count || 0)
        + Number(country?.goods_count || 0)
        + Number(country?.entities_count || 0)
}

function formatDate(value) {
    if (!value) {
        return '-'
    }

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value))
}

function setNotice(message) {
    notice.value = message
    error.value = ''
    window.setTimeout(() => { notice.value = '' }, 2600)
}

function setError(message) {
    error.value = message
    notice.value = ''
}

function resetFilters() {
    filters.search = ''
    filters.codeISO = ''
    filters.codeTelefon = ''
    filters.flag = null
    filters.usage = null
}

async function loadCountries() {
    loading.value = true

    try {
        const { data } = await axios.get(route('countries.index'), {
            params: {
                itemsPerPage: -1,
            },
        })
        countries.value = Array.isArray(data) ? data : data.data || []
    } catch (e) {
        setError(e.response?.data?.message || 'Не удалось загрузить страны.')
    } finally {
        loading.value = false
    }
}

function resetForm() {
    form.name = ''
    form.codeISO = ''
    form.codeTelefon = ''
    form.flag_url = ''
    form.flag_file = null
    form.remove_flag = false
}

function openCreate() {
    selectedCountry.value = null
    resetForm()
    error.value = ''
    dialog.value = true
}

function openEdit(country) {
    selectedCountry.value = country
    form.name = country.name || ''
    form.codeISO = country.codeISO || country.сodeISO || ''
    form.codeTelefon = country.codeTelefon || country.сodeTelefon || ''
    form.flag_url = country.flag || ''
    form.flag_file = null
    form.remove_flag = false
    error.value = ''
    dialog.value = true
}

function selectedFlagFile() {
    return Array.isArray(form.flag_file) ? form.flag_file[0] : form.flag_file
}

function countryPayload() {
    const payload = new FormData()

    payload.append('name', form.name || '')
    payload.append('codeISO', String(form.codeISO || '').toUpperCase())
    payload.append('codeTelefon', form.codeTelefon || '')
    payload.append('flag_url', form.flag_url || '')
    payload.append('remove_flag', form.remove_flag ? '1' : '0')

    const file = selectedFlagFile()

    if (file) {
        payload.append('flag_file', file)
    }

    return payload
}

async function saveCountry() {
    saving.value = true

    try {
        const payload = countryPayload()

        if (selectedCountry.value?.id) {
            payload.append('_method', 'PUT')
            await axios.post(route('countries.update', selectedCountry.value.id), payload)
            setNotice('Страна обновлена.')
        } else {
            await axios.post(route('countries.store'), payload)
            setNotice('Страна создана.')
        }

        dialog.value = false
        await loadCountries()
    } catch (e) {
        setError(e.response?.data?.message || 'Не удалось сохранить страну.')
    } finally {
        saving.value = false
    }
}

async function deleteCountry(country) {
    if (!window.confirm(`Удалить страну "${country.name}"?`)) {
        return
    }

    deletingId.value = country.id

    try {
        await axios.delete(route('countries.destroy', country.id))
        setNotice('Страна удалена.')
        await loadCountries()
    } catch (e) {
        setError(e.response?.data?.message || 'Не удалось удалить страну.')
    } finally {
        deletingId.value = null
    }
}

onMounted(loadCountries)
</script>

<template>
    <section class="countries-admin">
        <div class="countries-admin__header">
            <div>
                <div class="countries-admin__eyebrow">Geography / Countries</div>
                <h2>Страны</h2>
            </div>

            <div class="countries-admin__actions">
                <v-btn
                    color="#800000"
                    variant="tonal"
                    prepend-icon="mdi-refresh"
                    :loading="loading"
                    @click="loadCountries"
                >
                    Обновить
                </v-btn>
                <v-btn
                    color="#800000"
                    variant="elevated"
                    prepend-icon="mdi-plus"
                    @click="openCreate"
                >
                    Страна
                </v-btn>
            </div>
        </div>

        <v-alert
            v-if="notice"
            type="success"
            variant="tonal"
            density="compact"
            class="mb-3"
        >
            {{ notice }}
        </v-alert>

        <v-alert
            v-if="error"
            type="error"
            variant="tonal"
            density="compact"
            class="mb-3"
        >
            {{ error }}
        </v-alert>

        <div class="countries-summary">
            <div><span>Total</span><strong>{{ summary.total }}</strong></div>
            <div><span>Filtered</span><strong>{{ summary.filtered }}</strong></div>
            <div><span>Flags</span><strong>{{ summary.withFlag }}</strong></div>
            <div><span>No flag</span><strong>{{ summary.withoutFlag }}</strong></div>
            <div><span>Used</span><strong>{{ summary.used }}</strong></div>
        </div>

        <section class="countries-filters">
            <v-text-field
                v-model="filters.search"
                label="Поиск"
                density="compact"
                variant="outlined"
                hide-details
                clearable
                prepend-inner-icon="mdi-magnify"
            />
            <v-text-field
                v-model="filters.codeISO"
                label="ISO"
                density="compact"
                variant="outlined"
                hide-details
                clearable
            />
            <v-text-field
                v-model="filters.codeTelefon"
                label="Телефонный код"
                density="compact"
                variant="outlined"
                hide-details
                clearable
            />
            <v-select
                v-model="filters.flag"
                :items="flagFilterOptions"
                label="Флаг"
                density="compact"
                variant="outlined"
                hide-details
            />
            <v-select
                v-model="filters.usage"
                :items="usageFilterOptions"
                label="Связи"
                density="compact"
                variant="outlined"
                hide-details
            />
            <v-btn
                variant="text"
                color="#800000"
                prepend-icon="mdi-filter-remove-outline"
                @click="resetFilters"
            >
                Сбросить
            </v-btn>
        </section>

        <v-data-table
            v-model:sort-by="sortBy"
            :headers="headers"
            :items="filteredCountries"
            :loading="loading"
            :items-per-page="-1"
            class="countries-table"
            density="compact"
            hover
            hide-default-footer
        >
            <template #item.flag="{ item }">
                <div class="country-flag-cell">
                    <img
                        v-if="item.flag"
                        :src="item.flag"
                        :alt="item.name"
                    >
                    <span v-else>{{ item.name?.slice(0, 1) || '?' }}</span>
                </div>
            </template>

            <template #item.name="{ item }">
                <button
                    type="button"
                    class="country-name-button"
                    @click="openEdit(item)"
                >
                    {{ item.name }}
                </button>
                <a
                    v-if="item.flag"
                    :href="item.flag"
                    target="_blank"
                    class="country-flag-link"
                >
                    flag url
                </a>
            </template>

            <template #item.codeISO="{ item }">
                <span class="country-code">{{ item.codeISO || item.сodeISO || '-' }}</span>
            </template>

            <template #item.codeTelefon="{ item }">
                <span class="country-code">{{ item.codeTelefon || item.сodeTelefon || '-' }}</span>
            </template>

            <template #item.updated_at="{ item }">
                <span class="country-date">{{ formatDate(item.updated_at) }}</span>
            </template>

            <template #item.actions="{ item }">
                <div class="country-actions">
                    <v-btn
                        icon="mdi-pencil"
                        size="x-small"
                        variant="text"
                        color="#800000"
                        @click="openEdit(item)"
                    />
                    <v-btn
                        icon="mdi-delete-outline"
                        size="x-small"
                        variant="text"
                        color="red"
                        :loading="deletingId === item.id"
                        @click="deleteCountry(item)"
                    />
                </div>
            </template>
        </v-data-table>

        <v-dialog v-model="dialog" max-width="720" persistent>
            <v-card class="country-dialog">
                <v-card-title class="country-dialog__title">
                    <div>
                        <span>{{ selectedCountry ? 'Редактирование страны' : 'Новая страна' }}</span>
                        <small v-if="selectedCountry">#{{ selectedCountry.id }}</small>
                    </div>
                    <v-btn icon="mdi-close" variant="text" @click="dialog = false" />
                </v-card-title>

                <v-card-text>
                    <div class="country-form-grid">
                        <v-text-field
                            v-model="form.name"
                            label="Название"
                            density="compact"
                            variant="outlined"
                            hide-details
                        />
                        <v-text-field
                            v-model="form.codeISO"
                            label="ISO 2"
                            density="compact"
                            variant="outlined"
                            hide-details
                            maxlength="2"
                            @update:model-value="form.codeISO = String(form.codeISO || '').toUpperCase()"
                        />
                        <v-text-field
                            v-model="form.codeTelefon"
                            label="Телефонный код"
                            density="compact"
                            variant="outlined"
                            hide-details
                        />
                        <v-text-field
                            v-model="form.flag_url"
                            label="Flag URL"
                            density="compact"
                            variant="outlined"
                            hide-details
                            clearable
                        />
                        <v-file-input
                            v-model="form.flag_file"
                            label="Загрузить flag image в Yandex S3"
                            density="compact"
                            variant="outlined"
                            hide-details
                            clearable
                            accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml"
                            prepend-icon=""
                            prepend-inner-icon="mdi-image-plus-outline"
                        />
                        <v-checkbox
                            v-if="selectedCountry?.flag"
                            v-model="form.remove_flag"
                            label="Удалить текущий флаг"
                            density="compact"
                            hide-details
                        />
                    </div>

                    <div v-if="selectedCountry?.flag || form.flag_url" class="country-dialog-preview">
                        <span>Текущий флаг</span>
                        <img
                            :src="form.flag_url || selectedCountry?.flag"
                            :alt="form.name"
                        >
                    </div>
                </v-card-text>

                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="dialog = false">Отмена</v-btn>
                    <v-btn
                        color="#800000"
                        variant="elevated"
                        prepend-icon="mdi-content-save-outline"
                        :loading="saving"
                        @click="saveCountry"
                    >
                        Сохранить
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </section>
</template>

<style scoped>
.countries-admin {
    display: grid;
    gap: 14px;
    padding: 16px 4px 24px;
    color: #2f160d;
}

.countries-admin__header,
.countries-admin__actions,
.countries-summary,
.country-actions,
.country-dialog__title {
    display: flex;
    align-items: center;
}

.countries-admin__header {
    justify-content: space-between;
    gap: 16px;
}

.countries-admin__eyebrow {
    color: #800000;
    font-size: 10px;
    font-weight: 900;
    letter-spacing: 0.14em;
    text-transform: uppercase;
}

.countries-admin h2 {
    margin: 0;
    color: #35160d;
    font-size: 1.35rem;
    font-weight: 950;
    line-height: 1.1;
}

.countries-admin__actions {
    flex-wrap: wrap;
    gap: 8px;
}

.countries-summary {
    flex-wrap: wrap;
    gap: 8px;
}

.countries-summary div {
    display: inline-grid;
    min-width: 112px;
    padding: 8px 10px;
    border: 1px solid rgba(128, 0, 0, 0.09);
    border-radius: 8px;
    background: #fffaf2;
}

.countries-summary span {
    color: #7c5b4b;
    font-size: 10px;
    font-weight: 850;
    text-transform: uppercase;
}

.countries-summary strong {
    color: #35160d;
    font-size: 1.1rem;
    font-weight: 950;
}

.countries-filters {
    display: grid;
    grid-template-columns: minmax(220px, 1.4fr) minmax(90px, 0.55fr) minmax(140px, 0.8fr) minmax(130px, 0.7fr) minmax(130px, 0.7fr) auto;
    gap: 8px;
    align-items: center;
}

.countries-table {
    border: 1px solid rgba(128, 0, 0, 0.08);
    border-radius: 8px;
    overflow: hidden;
    background: #ffffff;
}

.countries-table :deep(th) {
    color: #5b2b1b;
    background: #fff8ef !important;
    font-size: 0.72rem;
    font-weight: 900;
}

.country-flag-cell {
    display: grid;
    width: 56px;
    height: 38px;
    place-items: center;
    overflow: hidden;
    border: 1px solid rgba(68, 64, 60, 0.14);
    border-radius: 7px;
    background: #fafaf9;
    color: #800000;
    font-weight: 950;
}

.country-flag-cell img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.country-name-button {
    display: block;
    padding: 0;
    border: 0;
    background: transparent;
    color: #6b2d09;
    cursor: pointer;
    font: inherit;
    font-weight: 900;
    text-align: left;
}

.country-name-button:hover {
    color: #9f1239;
    text-decoration: underline;
    text-underline-offset: 2px;
}

.country-flag-link {
    color: #64748b;
    font-size: 10px;
    font-weight: 800;
    text-decoration: none;
}

.country-flag-link:hover {
    color: #800000;
}

.country-code,
.country-date {
    color: #4c3b2e;
    font-family: "JetBrains Mono", "IBM Plex Mono", monospace;
    font-size: 0.76rem;
    font-weight: 850;
}

.country-actions {
    justify-content: flex-end;
    gap: 2px;
}

.country-dialog__title {
    justify-content: space-between;
    gap: 12px;
}

.country-dialog__title div {
    display: grid;
}

.country-dialog__title span {
    color: #35160d;
    font-weight: 950;
}

.country-dialog__title small {
    color: #7c5b4b;
    font-size: 11px;
    font-weight: 800;
}

.country-form-grid {
    display: grid;
    grid-template-columns: minmax(220px, 1fr) 110px 150px;
    gap: 10px;
}

.country-form-grid > :nth-child(4),
.country-form-grid > :nth-child(5),
.country-form-grid > :nth-child(6) {
    grid-column: 1 / -1;
}

.country-dialog-preview {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 12px;
    padding: 9px;
    border: 1px solid rgba(128, 0, 0, 0.08);
    border-radius: 8px;
    background: #fffaf2;
    color: #7c5b4b;
    font-size: 11px;
    font-weight: 850;
}

.country-dialog-preview img {
    width: 72px;
    height: 48px;
    object-fit: contain;
    border: 1px solid rgba(68, 64, 60, 0.14);
    border-radius: 7px;
    background: #ffffff;
}

@media (max-width: 1100px) {
    .countries-filters,
    .country-form-grid {
        grid-template-columns: 1fr;
    }
}
</style>
