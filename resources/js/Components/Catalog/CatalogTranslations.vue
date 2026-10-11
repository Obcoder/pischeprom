<script setup>
import axios from 'axios'
import { computed, onScopeDispose, reactive, ref, watch } from 'vue'
import { catalogTranslationFields, catalogTranslationForm, emptyCatalogTranslationForm } from '@/Pages/Helpers/catalogLanguages.js'

const props = defineProps({
    node: { type: Object, required: true },
    active: { type: Boolean, default: true },
    disabled: { type: Boolean, default: false },
})
const emit = defineEmits(['state', 'saved'])
const record = ref(null)
const loading = ref(false)
const saving = ref(false)
const editing = ref(false)
const saved = ref(false)
const error = ref('')
const fieldErrors = ref({})
const form = reactive(emptyCatalogTranslationForm())
const baseline = ref(emptyCatalogTranslationForm())
const dirty = computed(() => catalogTranslationFields.some(({ key }) => form[key] !== baseline.value[key]))
const busy = computed(() => loading.value || saving.value)
const ready = computed(() => record.value !== null)
const filledLanguages = computed(() => catalogTranslationFields.filter(({ key }) => form[key].trim()).length)
let loadVersion = 0
let saveVersion = 0
let loadController = null
let saveController = null
let disposed = false

function cancelLoad() {
    loadVersion++
    loadController?.abort()
    loadController = null
    loading.value = false
}

function cancelSave() {
    saveVersion++
    saveController?.abort()
    saveController = null
    saving.value = false
}

function accept(data, previous = null) {
    const confirmed = catalogTranslationForm(data)
    catalogTranslationFields.forEach(({ key }) => {
        if (!previous || form[key] === previous[key]) form[key] = confirmed[key]
    })
    baseline.value = confirmed
    record.value = data
}

function responseNode(response, id) {
    const data = response?.data
    if (!data?.id || String(data.id) !== String(id) || !data.translations || typeof data.translations !== 'object' || Array.isArray(data.translations)) {
        throw new Error('Invalid catalogue translation response')
    }
    return data
}

function reset() {
    Object.assign(form, baseline.value)
    editing.value = false
    saved.value = false
    fieldErrors.value = {}
    error.value = ''
}

function syncName(name) {
    if (!record.value || typeof name !== 'string') return
    cancelLoad()
    if (form.ru === baseline.value.ru) form.ru = name
    baseline.value = { ...baseline.value, ru: name }
    record.value = { ...record.value, name, translations: { ...record.value.translations, ru: name } }
}

function edit() {
    if (props.disabled || saving.value) return
    editing.value = true
    saved.value = false
}

async function refresh({ throwOnError = false } = {}) {
    if (saving.value) return null
    cancelLoad()
    if (!props.active || !props.node?.id) return null
    const id = props.node.id
    const version = loadVersion
    loadController = new AbortController()
    loading.value = true
    error.value = ''
    try {
        const { data: response } = await axios.get(`/api/catalog/nodes/${id}/translations`, { signal: loadController.signal })
        if (disposed || version !== loadVersion || String(id) !== String(props.node?.id)) return null
        const data = responseNode(response, id)
        accept(data, baseline.value)
        return data
    } catch (failure) {
        if (disposed || version !== loadVersion) return null
        error.value = failure.response?.data?.message || 'Не удалось загрузить языковые варианты.'
        if (throwOnError) throw failure
        return null
    } finally {
        if (version === loadVersion) {
            loading.value = false
            loadController = null
        }
    }
}

async function saveTranslations() {
    if (!record.value || props.disabled || saving.value || !dirty.value) return
    fieldErrors.value = {}
    error.value = ''
    saved.value = false
    const errors = {}
    catalogTranslationFields.forEach(({ key }) => {
        if (Array.from(form[key].trim()).length > 255) errors[key] = ['Название не должно превышать 255 символов.']
    })
    if (!form.ru.trim()) errors.ru = ['Укажите название на русском языке.']
    if (Object.keys(errors).length) {
        fieldErrors.value = errors
        return
    }
    const id = props.node.id
    const submitted = { ...form }
    const translations = Object.fromEntries(catalogTranslationFields
        .filter(({ key }) => submitted[key] !== baseline.value[key])
        .map(({ key }) => [key, submitted[key].trim() || null]))
    cancelLoad()
    const version = ++saveVersion
    saveController = new AbortController()
    saving.value = true
    try {
        const { data: response } = await axios.patch(`/api/catalog/nodes/${id}/translations`, { translations }, { signal: saveController.signal })
        if (disposed || version !== saveVersion || String(id) !== String(props.node?.id)) return
        const data = responseNode(response, id)
        accept(data, submitted)
        editing.value = dirty.value
        saved.value = !dirty.value
        emit('saved', data)
    } catch (failure) {
        if (disposed || version !== saveVersion) return
        const errors = failure.response?.data?.errors || {}
        fieldErrors.value = Object.fromEntries(catalogTranslationFields.map(({ key }) => [key, errors[`translations.${key}`] || errors[key] || []]))
        error.value = failure.response?.data?.message || 'Не удалось сохранить языковые варианты.'
    } finally {
        if (version === saveVersion) {
            saving.value = false
            saveController = null
        }
    }
}

watch(() => props.node?.id, () => {
    cancelLoad()
    cancelSave()
    record.value = null
    baseline.value = emptyCatalogTranslationForm()
    reset()
    if (props.active) refresh()
}, { immediate: true, flush: 'sync' })
watch(() => props.active, active => {
    if (!active) cancelLoad()
    else if (!record.value) refresh()
})
watch([dirty, busy, ready, error], ([hasDraft, isBusy, isReady, message]) => {
    emit('state', { dirty: hasDraft, busy: isBusy, ready: isReady, error: message })
}, { immediate: true, flush: 'sync' })
onScopeDispose(() => {
    disposed = true
    cancelLoad()
    cancelSave()
})
defineExpose({ reset, refresh, syncName })
</script>

<template>
    <section class="catalog-translations">
        <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-3">
            {{ error }}
            <template v-if="!record" #append><v-btn variant="text" size="small" :disabled="loading" @click="refresh">Повторить</v-btn></template>
        </v-alert>
        <v-skeleton-loader v-if="loading && !record" type="article, table" />
        <v-card v-if="record" variant="outlined">
            <v-card-title class="d-flex align-center ga-2 flex-wrap">
                Языковые варианты
                <v-chip size="small" variant="tonal">{{ filledLanguages }} / {{ catalogTranslationFields.length }}</v-chip>
                <v-chip v-if="saved" size="small" color="success">Сохранено</v-chip>
                <v-spacer />
                <v-btn icon="mdi-refresh" size="small" variant="text" :loading="loading" :disabled="saving" aria-label="Обновить языковые варианты" @click="refresh" />
                <v-btn v-if="!editing" size="small" variant="tonal" prepend-icon="mdi-pencil-outline" :disabled="disabled || saving" @click="edit">Редактировать</v-btn>
            </v-card-title>
            <v-card-text>
                <p class="text-body-2 text-medium-emphasis mb-4">Название на русском языке используется в карточке и дереве каталога.</p>
                <v-form @submit.prevent="saveTranslations">
                    <div class="catalog-translations__grid">
                        <div v-for="field in catalogTranslationFields" :key="field.key" class="catalog-translations__field">
                            <v-text-field v-if="editing" v-model="form[field.key]" :label="`${field.label} (${field.code})${field.key === 'ru' ? ' *' : ''}`" :error-messages="fieldErrors[field.key]" :disabled="disabled || saving" :required="field.key === 'ru'" variant="outlined" density="compact" hide-details="auto" maxlength="255" dir="auto" />
                            <template v-else>
                                <div class="text-caption text-medium-emphasis">{{ field.label }} · {{ field.code }}</div>
                                <div class="text-body-2" dir="auto">{{ form[field.key] || '—' }}</div>
                            </template>
                        </div>
                    </div>
                    <div v-if="editing" class="d-flex justify-end ga-2 mt-4 flex-wrap">
                        <v-btn variant="text" :disabled="disabled || saving" @click="reset">Отмена</v-btn>
                        <v-btn type="submit" color="primary" :loading="saving" :disabled="disabled || saving || !dirty">Сохранить переводы</v-btn>
                    </div>
                </v-form>
            </v-card-text>
        </v-card>
    </section>
</template>

<style scoped>
.catalog-translations { min-width: 0; }
.catalog-translations :deep(.v-card-title) { white-space: normal; font-size: 1rem; }
.catalog-translations__grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
.catalog-translations__field { min-width: 0; overflow-wrap: anywhere; }
@media (max-width: 960px) { .catalog-translations__grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 600px) { .catalog-translations__grid { grid-template-columns: 1fr; } }
</style>
