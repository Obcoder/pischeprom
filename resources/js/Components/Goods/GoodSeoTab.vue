<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import axios from 'axios'
import { useGoodSeo } from '@/Composables/useGoodSeo'
import { usePublicGoodUrl } from '@/Composables/usePublicGoodUrl'

const props = defineProps({
    good: {
        type: Object,
        required: true,
    },
})

const goodId = computed(() => props.good.id)
const seoImageFallback = computed(() => props.good?.ava_thumb || '')

const {
    loading,
    saving,
    generating,
    aiGenerating,
    fetchSeo,
    saveSeo,
    generateStructuredData,
    generateAi,
} = useGoodSeo(goodId)
const { goodPublicUrl } = usePublicGoodUrl()

const form = reactive({
    meta_title: '',
    meta_description: '',
    h1: '',
    slug_override: '',
    canonical_url: '',
    robots: 'index,follow',

    og_title: '',
    og_description: '',
    og_image: '',

    twitter_title: '',
    twitter_description: '',
    twitter_image: '',

    short_seo_text: '',
    seo_text: '',

    semantic_core_text: '',
    keywords_text: '',
    search_queries_text: '',
    structured_data_text: '',

    focus_keyword: '',
    breadcrumbs_title: '',

    is_active: true,

    include_in_sitemap: true,
    include_in_yandex_feed: true,

    yandex_direct_title_1: '',
    yandex_direct_title_2: '',
    yandex_direct_text: '',
    utm_template: '',

    availability_status: 'on_request',
    min_order: '',
    delivery_note: '',
    payment_note: '',
    faq_text: '',
})

const loaded = ref(false)
const savedSnapshot = ref('')
const formError = ref('')
const formMessage = ref('')
const panels = ref([])
const aiDialog = ref(false)
const aiField = ref('h1')
const aiValue = ref('')
const aiError = ref('')
const aiAvailability = ref(null)
const primaryFields = [
    { key: 'h1', label: 'H1 — заголовок страницы', placeholder: 'Название товара на странице', max: 255, aiLabel: 'AI Придумать H1' },
    { key: 'meta_title', label: 'Title — заголовок в поиске', placeholder: 'Название товара и ключевое преимущество', max: 255, guide: 'Ориентир: 50–70 символов', aiLabel: 'AI Придумать Title' },
    { key: 'meta_description', label: 'Description — описание в поиске', placeholder: 'Кратко о товаре и его применении', rows: 2, guide: 'Ориентир: 120–160 символов', aiLabel: 'AI Придумать описание' },
]
const textFields = [
    { key: 'short_seo_text', label: 'Короткий SEO-текст', rows: 2, placeholder: 'Краткое вступление для карточки товара' },
    { key: 'seo_text', label: 'Большой SEO-текст', rows: 7, placeholder: 'Описание, применение и особенности товара' },
]
const aiFieldLabel = computed(() => [...primaryFields, ...textFields].find((field) => field.key === aiField.value)?.label)
const hasChanges = computed(() => loaded.value && JSON.stringify(form) !== savedSnapshot.value)
const controlsBusy = computed(() => loading.value || saving.value || generating.value || aiGenerating.value || !loaded.value)
const fieldDefaults = computed(() => ({
    VTextField: { variant: 'outlined', density: 'compact', hideDetails: 'auto', readonly: controlsBusy.value },
    VTextarea: { variant: 'outlined', density: 'compact', hideDetails: 'auto', readonly: controlsBusy.value, autoGrow: true },
    VSelect: { variant: 'outlined', density: 'compact', hideDetails: 'auto', disabled: controlsBusy.value },
    VSwitch: { color: 'primary', density: 'compact', hideDetails: true, inset: true, disabled: controlsBusy.value },
}))
const completeness = computed(() => [...primaryFields, ...textFields].filter((field) => form[field.key].trim()).length)
const previewUrl = computed(() => goodPublicUrl({
    ...props.good,
    slug: (form.is_active && form.slug_override.trim()) || props.good.slug,
}))
const previewTitle = computed(() => (form.is_active && form.meta_title.trim()) || `${props.good.name} купить оптом для пищевой промышленности`)
const previewDescription = computed(() => ((form.is_active && form.meta_description.trim()) || props.good.description || `${props.good.name}: оптовые поставки для пищевой промышленности, HoReCa, производств и дистрибьюторов.`).replace(/<[^>]*>/g, '').trim().slice(0, 160))
const indexingLabel = computed(() => {
    if (!props.good.is_published) return 'Товар не опубликован'
    if (!form.is_active) return 'SEO отключено'
    if (form.robots.startsWith('noindex')) return 'Индексация запрещена'
    return 'Индексация разрешена'
})
const indexingAllowed = computed(() => props.good.is_published && form.is_active && form.robots.startsWith('index'))

const directLoading = ref(false)
const directActionLoading = ref(false)
const fullLaunchLoading = ref(false)
const directMessage = ref('')
const directError = ref('')
const directAdId = ref(null)
const directStatus = ref(null)
const directStats = ref({
    impressions: 0,
    clicks: 0,
    cost: 0,
    conversions: 0,
    ctr: null,
    cost_per_conversion: null,
})

const directLimits = {
    title_1: 56,
    title_2: 30,
    text: 81,
}

const directLimitErrors = computed(() => {
    const errors = []

    if (form.yandex_direct_title_1.length > directLimits.title_1) {
        errors.push(`Заголовок 1: ${form.yandex_direct_title_1.length}/${directLimits.title_1}`)
    }

    if (form.yandex_direct_title_2.length > directLimits.title_2) {
        errors.push(`Заголовок 2: ${form.yandex_direct_title_2.length}/${directLimits.title_2}`)
    }

    if (form.yandex_direct_text.length > directLimits.text) {
        errors.push(`Текст: ${form.yandex_direct_text.length}/${directLimits.text}`)
    }

    return errors
})

const hasDirectLimitErrors = computed(() => directLimitErrors.value.length > 0)

function linesToArray(value) {
    return String(value || '')
        .split('\n')
        .map((item) => item.trim())
        .filter(Boolean)
}

function arrayToLines(value) {
    if (!Array.isArray(value)) return ''
    return value.join('\n')
}

function fillForm(data) {
    form.meta_title = data?.meta_title || ''
    form.meta_description = data?.meta_description || ''
    form.h1 = data?.h1 || ''
    form.slug_override = data?.slug_override || ''
    form.canonical_url = data?.canonical_url || ''
    form.robots = data?.robots || 'index,follow'

    form.og_title = data?.og_title || ''
    form.og_description = data?.og_description || ''
    form.og_image = data?.og_image || seoImageFallback.value

    form.twitter_title = data?.twitter_title || ''
    form.twitter_description = data?.twitter_description || ''
    form.twitter_image = data?.twitter_image || seoImageFallback.value

    form.short_seo_text = data?.short_seo_text || ''
    form.seo_text = data?.seo_text || ''

    form.semantic_core_text = arrayToLines(data?.semantic_core)
    form.keywords_text = arrayToLines(data?.keywords)
    form.search_queries_text = arrayToLines(data?.search_queries)

    form.structured_data_text = data?.structured_data
        ? JSON.stringify(data.structured_data, null, 2)
        : ''

    form.focus_keyword = data?.focus_keyword || ''
    form.breadcrumbs_title = data?.breadcrumbs_title || ''

    form.is_active = data?.is_active ?? true

    form.include_in_sitemap = data?.include_in_sitemap ?? true
    form.include_in_yandex_feed = data?.include_in_yandex_feed ?? true

    form.yandex_direct_title_1 = data?.yandex_direct_title_1 || ''
    form.yandex_direct_title_2 = data?.yandex_direct_title_2 || ''
    form.yandex_direct_text = data?.yandex_direct_text || ''
    form.utm_template = data?.utm_template || ''

    form.availability_status = data?.availability_status || 'on_request'
    form.min_order = data?.min_order || ''
    form.delivery_note = data?.delivery_note || ''
    form.payment_note = data?.payment_note || ''
    form.faq_text = arrayToLines((data?.faq || []).map((item) => `${item.question} | ${item.answer}`))
}

function payload() {
    let structuredData = null

    if (form.structured_data_text.trim()) {
        try {
            structuredData = JSON.parse(form.structured_data_text)
        } catch {
            throw new Error('В микроразметке JSON-LD некорректный JSON. Исправьте его перед сохранением.')
        }
        if (!structuredData || typeof structuredData !== 'object') {
            throw new Error('Микроразметка JSON-LD должна быть объектом или массивом JSON.')
        }
    }

    return {
        meta_title: form.meta_title,
        meta_description: form.meta_description,
        h1: form.h1,
        slug_override: form.slug_override,
        canonical_url: form.canonical_url,
        robots: form.robots,

        og_title: form.og_title,
        og_description: form.og_description,
        og_image: form.og_image,

        twitter_title: form.twitter_title,
        twitter_description: form.twitter_description,
        twitter_image: form.twitter_image,

        short_seo_text: form.short_seo_text,
        seo_text: form.seo_text,

        semantic_core: linesToArray(form.semantic_core_text),
        keywords: linesToArray(form.keywords_text),
        search_queries: linesToArray(form.search_queries_text),
        structured_data: structuredData,

        focus_keyword: form.focus_keyword,
        breadcrumbs_title: form.breadcrumbs_title,

        is_active: form.is_active,

        include_in_sitemap: form.include_in_sitemap,
        include_in_yandex_feed: form.include_in_yandex_feed,

        yandex_direct_title_1: form.yandex_direct_title_1,
        yandex_direct_title_2: form.yandex_direct_title_2,
        yandex_direct_text: form.yandex_direct_text,
        utm_template: form.utm_template,

        availability_status: form.availability_status,
        min_order: form.min_order,
        delivery_note: form.delivery_note,
        payment_note: form.payment_note,
        faq: faqToArray(form.faq_text),
    }
}

async function generateJsonLd() {
    formError.value = ''
    formMessage.value = ''
    try {
        const data = await generateStructuredData()
        form.structured_data_text = JSON.stringify(data.structured_data, null, 2)
        const saved = JSON.parse(savedSnapshot.value)
        saved.structured_data_text = form.structured_data_text
        savedSnapshot.value = JSON.stringify(saved)
        formMessage.value = 'Микроразметка создана и сохранена на основе сохранённых данных товара.'
    } catch (error) {
        formError.value = errorMessage(error, 'Не удалось создать микроразметку.')
    }
}

function faqToArray(value) {
    return String(value || '')
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean)
        .map((line) => {
            const [question, ...answerParts] = line.split('|').map((item) => item?.trim())

            return {
                question: question || '',
                answer: answerParts.join(' | '),
            }
        })
        .filter((item) => item.question && item.answer)
}

async function submit() {
    formError.value = ''
    formMessage.value = ''
    const data = await saveSeo(payload())
    fillForm(data)
    savedSnapshot.value = JSON.stringify(form)
    formMessage.value = 'SEO сохранено.'
}

function errorMessage(error, fallback) {
    if (error.response?.status === 401) return 'Войдите в систему, чтобы продолжить.'
    if (error.response?.status === 403) return 'Действие доступно сотруднику или администратору с подтверждённой почтой.'
    if (error.response?.status === 429 && !error.response?.data?.code) return 'Слишком много запросов. Подождите минуту и повторите попытку.'
    const details = responseErrorMessages(error)
    return details.length ? details.join(' ') : (error.response?.data?.message || (error.response ? fallback : error.message) || fallback)
}

async function saveForm() {
    try {
        await submit()
    } catch (error) {
        formError.value = errorMessage(error, 'Не удалось сохранить SEO.')
    }
}

async function loadSeo() {
    formError.value = ''
    try {
        const data = await fetchSeo()
        fillForm(data)
        aiAvailability.value = data.ai_generation || null
        savedSnapshot.value = JSON.stringify(form)
        loaded.value = true
    } catch (error) {
        formError.value = errorMessage(error, 'Не удалось загрузить SEO. Попробуйте ещё раз.')
    }
}

function aiContext() {
    const limits = {
        focus_keyword: 255, h1: 255, meta_title: 255, meta_description: 2000,
        short_seo_text: 5000, seo_text: 12000, min_order: 255, delivery_note: 2000, payment_note: 2000,
    }
    const context = Object.fromEntries(Object.entries(limits).map(([field, limit]) => [field, form[field].slice(0, limit)]))
    for (const field of ['semantic_core', 'keywords', 'search_queries']) {
        context[field] = linesToArray(form[`${field}_text`]).slice(0, 40).map((value) => value.slice(0, 255))
    }
    return context
}

async function requestAi(field = aiField.value) {
    if (aiGenerating.value) return
    aiField.value = field
    aiDialog.value = true
    aiValue.value = ''
    aiError.value = ''
    try {
        aiValue.value = await generateAi(field, aiContext())
    } catch (error) {
        aiError.value = errorMessage(error, 'Не удалось получить текст от AI. Попробуйте ещё раз.')
    }
}

function applyAi() {
    if (!aiValue.value.trim() || aiGenerating.value) return
    form[aiField.value] = aiValue.value.trim()
    formMessage.value = 'AI-текст добавлен в форму. Проверьте его и сохраните SEO.'
    formError.value = ''
    aiDialog.value = false
}

function compactText(value, limit) {
    const text = String(value || '').replace(/\s+/g, ' ').trim()

    return text.length > limit ? text.slice(0, limit).trim() : text
}

function setDirectMessage(message) {
    directMessage.value = message
    directError.value = ''
}

function setDirectError(message) {
    directError.value = message
    directMessage.value = ''
}

function responseErrorMessages(error) {
    const errors = error.response?.data?.errors

    if (Array.isArray(errors)) {
        return errors
    }

    if (errors && typeof errors === 'object') {
        return Object.values(errors).flat().filter(Boolean)
    }

    return []
}

function launchMessage(data, dryRun) {
    const warnings = Array.isArray(data.warnings) ? data.warnings.filter(Boolean) : []
    const message = data.message || (dryRun ? 'FULL AUTO LAUNCH dry-run выполнен.' : 'FULL AUTO LAUNCH отправлен в Яндекс.')

    return warnings.length ? `${message} Предупреждения: ${warnings.join('; ')}` : message
}

function generateDirectFields() {
    if (!form.yandex_direct_title_1) {
        form.yandex_direct_title_1 = compactText(props.good.name, directLimits.title_1)
    }

    if (!form.yandex_direct_title_2) {
        form.yandex_direct_title_2 = 'Опт и розница'
    }

    if (!form.yandex_direct_text) {
        form.yandex_direct_text = compactText(
            form.short_seo_text || props.good.description || 'Поставки для пищевой промышленности. Опт и розница.',
            directLimits.text,
        )
    }

    if (!form.utm_template) {
        form.utm_template = 'utm_source=yandex&utm_medium=cpc&utm_campaign=direct_goods&utm_content={ad_id}&utm_term={keyword}&utm_campaign_id={campaign_id}&utm_device={device_type}'
    }

    setDirectMessage('Поля объявления заполнены локально. Сохрани SEO и создай черновик.')
}

function checkDirectLimits() {
    if (hasDirectLimitErrors.value) {
        setDirectError(`Превышены лимиты: ${directLimitErrors.value.join('; ')}`)
        return
    }

    setDirectMessage('Лимиты символов соблюдены.')
}

async function loadDirectInfo() {
    if (!props.good?.id) return

    directLoading.value = true

    try {
        const { data } = await axios.get('/api/marketing/direct/goods', {
            params: {
                search: props.good.name,
                per_page: 10,
            },
        })
        const item = (data.data || []).find((row) => Number(row.id) === Number(props.good.id))

        directAdId.value = item?.direct_ad_id || null
        directStatus.value = item?.direct_status || null
        directStats.value = item?.stats || directStats.value
    } catch (error) {
        // Блок не должен ломать SEO-вкладку, если маркетинговые таблицы еще не мигрированы.
        directError.value = error.response?.data?.message || ''
    } finally {
        directLoading.value = false
    }
}

async function createDirectDraft() {
    directActionLoading.value = true

    try {
        await submit()
        const { data } = await axios.post(`/api/marketing/direct/goods/${props.good.id}/generate-draft`)
        directAdId.value = data.id
        directStatus.value = data.status
        setDirectMessage('Рекламный черновик создан.')
        await loadDirectInfo()
    } catch (error) {
        setDirectError(error.response?.data?.message || 'Не удалось создать рекламный черновик.')
    } finally {
        directActionLoading.value = false
    }
}

async function validateDirectDraft() {
    if (!directAdId.value) {
        checkDirectLimits()
        return
    }

    directActionLoading.value = true

    try {
        const { data } = await axios.post(`/api/marketing/direct/ads/${directAdId.value}/validate`)
        directStatus.value = data.ad?.status || directStatus.value
        const errors = data.errors || {}
        if (Object.keys(errors).length) {
            setDirectError('Черновик содержит ошибки валидации.')
        } else {
            setDirectMessage('Черновик прошёл проверку лимитов.')
        }
    } catch (error) {
        setDirectError(error.response?.data?.message || 'Не удалось проверить черновик.')
    } finally {
        directActionLoading.value = false
    }
}

async function sendDirectDraft() {
    if (!directAdId.value) {
        await createDirectDraft()
    }

    if (!directAdId.value) return

    directActionLoading.value = true

    try {
        const { data } = await axios.post(`/api/marketing/direct/ads/${directAdId.value}/send`)
        setDirectMessage(data.message || 'Отправка обработана.')
        await loadDirectInfo()
    } catch (error) {
        setDirectError(error.response?.data?.message || 'Не удалось отправить черновик в Директ.')
    } finally {
        directActionLoading.value = false
    }
}

async function fullAutoLaunch(dryRun = true) {
    if (!dryRun) {
        const confirmed = window.confirm('Запустить реальную отправку в Яндекс.Директ? Будут созданы кампания, группы, объявления и ключи. Бюджет будет взят из guard-настроек Direct.')

        if (!confirmed) {
            return
        }
    }

    fullLaunchLoading.value = true

    try {
        await submit()
        const { data } = await axios.post(`/api/marketing/direct/launch/${props.good.id}`, {
            dry_run: dryRun,
            budget_approved: !dryRun,
        })
        directStatus.value = data.status || directStatus.value
        setDirectMessage(launchMessage(data, dryRun))
        await loadDirectInfo()
    } catch (error) {
        const errors = responseErrorMessages(error)
        setDirectError(errors.length ? errors.join('; ') : (error.response?.data?.message || 'Не удалось выполнить FULL AUTO LAUNCH.'))
    } finally {
        fullLaunchLoading.value = false
    }
}

onMounted(async () => {
    await loadSeo()
    await loadDirectInfo()
})
</script>

<template>
    <v-card class="seo-workspace" variant="flat" border>
        <v-defaults-provider :defaults="fieldDefaults">
            <div class="seo-toolbar">
                <div class="seo-toolbar__title">
                    <v-icon icon="mdi-text-search" color="primary" size="24" />
                    <div>
                        <h2>SEO товара</h2>
                        <p>Поисковая выдача, тексты и продвижение</p>
                    </div>
                </div>
                <div class="seo-toolbar__actions">
                    <v-chip size="small" variant="tonal" :color="indexingAllowed ? 'success' : 'warning'">{{ indexingLabel }}</v-chip>
                    <v-switch v-model="form.is_active" label="SEO активно" />
                </div>
            </div>
            <v-progress-linear v-if="loading" indeterminate color="primary" height="2" />
            <div class="seo-body">
                <v-alert v-if="formError" type="error" variant="tonal" density="compact" class="mb-3" role="alert">
                    {{ formError }}
                    <template v-if="!loaded" #append>
                        <v-btn size="small" variant="text" :loading="loading" @click="loadSeo">Повторить</v-btn>
                    </template>
                </v-alert>
                <v-alert v-if="formMessage" type="success" variant="tonal" density="compact" class="mb-3" closable @click:close="formMessage = ''" role="status">{{ formMessage }}</v-alert>
                <div class="seo-main-grid">
                    <div class="seo-main">
                        <section class="seo-section">
                            <div class="seo-section__heading">
                                <h3>Заголовки и описание</h3>
                                <span class="seo-muted">Основное для поиска</span>
                            </div>
                            <div v-for="field in primaryFields" :key="field.key" class="seo-field">
                                <div class="seo-field__heading">
                                    <label :for="`seo-${field.key}`">{{ field.label }}</label>
                                    <v-btn size="small" variant="tonal" color="primary" prepend-icon="mdi-auto-fix" :loading="aiGenerating && aiField === field.key" :disabled="controlsBusy || aiAvailability?.available === false" @click="requestAi(field.key)">{{ field.aiLabel }}</v-btn>
                                </div>
                                <v-textarea v-if="field.rows" :id="`seo-${field.key}`" v-model="form[field.key]" :aria-label="field.label" :placeholder="field.placeholder" :rows="field.rows" max-rows="5" />
                                <v-text-field v-else :id="`seo-${field.key}`" v-model="form[field.key]" :aria-label="field.label" :placeholder="field.placeholder" :maxlength="field.max" />
                                <div class="seo-field__hint"><span>{{ field.guide || 'Один главный заголовок на странице' }}</span><span>{{ form[field.key].length }}{{ field.max ? ` / ${field.max}` : '' }}</span></div>
                            </div>
                        </section>
                        <section class="seo-section">
                            <div class="seo-section__heading">
                                <h3>Тексты карточки</h3>
                                <v-icon icon="mdi-text-box-outline" size="18" color="medium-emphasis" />
                            </div>
                            <div v-for="field in textFields" :key="field.key" class="seo-field">
                                <div class="seo-field__heading">
                                    <label :for="`seo-${field.key}`">{{ field.label }}</label>
                                    <v-btn size="small" variant="tonal" color="primary" prepend-icon="mdi-auto-fix" :loading="aiGenerating && aiField === field.key" :disabled="controlsBusy || aiAvailability?.available === false" @click="requestAi(field.key)">Заполнить с помощью AI</v-btn>
                                </div>
                                <v-textarea :id="`seo-${field.key}`" v-model="form[field.key]" :aria-label="field.label" :placeholder="field.placeholder" :rows="field.rows" :max-rows="field.key === 'seo_text' ? 16 : 6" />
                                <div class="seo-field__hint"><span>Обычный текст, абзацы разделяются переносом строки</span><span>{{ form[field.key].length }} симв.</span></div>
                            </div>
                        </section>
                    </div>
                    <aside class="seo-sidebar">
                        <section class="seo-section">
                            <div class="seo-section__heading"><h3>Предпросмотр в поиске</h3><v-icon icon="mdi-magnify" size="18" /></div>
                            <div class="seo-snippet">
                                <div class="seo-snippet__url">{{ previewUrl }}</div>
                                <div class="seo-snippet__title">{{ previewTitle }}</div>
                                <div class="seo-snippet__description">{{ previewDescription }}</div>
                            </div>
                            <p class="seo-note">Пример отображения. Поисковик может выбрать другой заголовок или описание.</p>
                        </section>
                        <section class="seo-section seo-ai-context">
                            <div class="seo-section__heading"><h3><v-icon icon="mdi-auto-fix" size="18" class="mr-1" /> AI-помощник</h3><v-chip size="x-small" color="primary" variant="tonal">Timeweb</v-chip></div>
                            <p class="seo-note mt-0 mb-3">Создаёт текст по данным товара и ключевым фразам. Результат можно отредактировать перед вставкой.</p>
                            <v-text-field v-model="form.focus_keyword" label="Фокусный ключ" placeholder="Главная поисковая фраза" maxlength="255" />
                            <p v-if="aiAvailability?.available === false" class="seo-note text-warning" role="status">{{ aiAvailability.message || 'AI-заполнение через Timeweb пока не настроено.' }}</p>
                            <p v-else class="seo-note">Проверьте факты в предложении AI. Изменения публикуются после сохранения SEO.</p>
                        </section>
                        <section class="seo-section">
                            <div class="seo-section__heading"><h3>Заполнение</h3><span class="seo-muted">{{ completeness }} / 5</span></div>
                            <v-progress-linear :model-value="completeness * 20" color="primary" height="4" rounded class="mb-3" />
                            <div v-for="field in [...primaryFields, ...textFields]" :key="field.key" class="seo-check">
                                <v-icon :icon="form[field.key].trim() ? 'mdi-check-circle-outline' : 'mdi-circle-outline'" :color="form[field.key].trim() ? 'success' : 'medium-emphasis'" size="16" />
                                <span>{{ field.label.split(' — ')[0] }}</span>
                            </div>
                            <p class="seo-note">Незаполненные метатеги используют данные товара.</p>
                        </section>
                    </aside>
                </div>
                <div class="seo-advanced-heading"><h3>Дополнительные настройки</h3><span class="seo-muted">Семантика, публикация и рекламные каналы</span></div>
                <v-expansion-panels v-model="panels" multiple variant="accordion" class="seo-panels">
                    <v-expansion-panel value="semantics">
                        <v-expansion-panel-title><v-icon icon="mdi-key-outline" size="20" class="mr-3" /><span>Семантика и FAQ</span><span class="seo-panel-summary">{{ linesToArray(form.semantic_core_text).length }} ключевых фраз</span></v-expansion-panel-title>
                        <v-expansion-panel-text>
                            <div class="seo-grid seo-grid--three">
                                <v-textarea v-model="form.semantic_core_text" label="Семантическое ядро" rows="4" hint="Каждая фраза с новой строки" persistent-hint />
                                <v-textarea v-model="form.keywords_text" label="Keywords" rows="4" hint="Каждое слово или фраза с новой строки" persistent-hint />
                                <v-textarea v-model="form.search_queries_text" label="Поисковые запросы" rows="4" hint="Каждый запрос с новой строки" persistent-hint />
                            </div>
                            <v-textarea v-model="form.faq_text" label="Вопросы и ответы (FAQ)" rows="3" class="mt-4" hint="Вопрос | ответ. Каждая пара с новой строки." persistent-hint />
                        </v-expansion-panel-text>
                    </v-expansion-panel>
                    <v-expansion-panel value="publication">
                        <v-expansion-panel-title><v-icon icon="mdi-web" size="20" class="mr-3" /><span>Адрес и индексация</span><span class="seo-panel-summary">{{ form.robots }}</span></v-expansion-panel-title>
                        <v-expansion-panel-text>
                            <div class="seo-grid">
                                <v-text-field v-model="form.slug_override" label="SEO-адрес (slug)" maxlength="255" hint="Альтернативный адрес публичной карточки при активном SEO" persistent-hint />
                                <v-text-field v-model="form.canonical_url" label="Канонический URL" maxlength="255" :placeholder="previewUrl" />
                                <v-text-field v-model="form.breadcrumbs_title" label="Название в хлебных крошках" maxlength="255" />
                                <v-select v-model="form.robots" :items="['index,follow', 'noindex,follow', 'index,nofollow', 'noindex,nofollow']" label="Robots" />
                                <v-switch v-model="form.include_in_sitemap" label="Включить в Sitemap" />
                                <v-switch v-model="form.include_in_yandex_feed" label="Включить в фид Яндекс.Директа" />
                            </div>
                        </v-expansion-panel-text>
                    </v-expansion-panel>
                    <v-expansion-panel value="commerce">
                        <v-expansion-panel-title><v-icon icon="mdi-truck-outline" size="20" class="mr-3" /><span>Наличие, доставка и оплата</span></v-expansion-panel-title>
                        <v-expansion-panel-text>
                            <div class="seo-grid">
                                <v-select v-model="form.availability_status" :items="[{ title: 'В наличии', value: 'in_stock' }, { title: 'По запросу', value: 'on_request' }, { title: 'Под заказ', value: 'preorder' }, { title: 'Нет в наличии', value: 'out_of_stock' }]" label="Наличие" />
                                <v-text-field v-model="form.min_order" label="Минимальная партия" placeholder="Например, от 100 кг" maxlength="255" />
                                <v-textarea v-model="form.delivery_note" label="Доставка" rows="2" />
                                <v-textarea v-model="form.payment_note" label="Оплата" rows="2" />
                            </div>
                        </v-expansion-panel-text>
                    </v-expansion-panel>
                    <v-expansion-panel value="social">
                        <v-expansion-panel-title><v-icon icon="mdi-share-variant-outline" size="20" class="mr-3" /><span>Соцсети и мессенджеры</span><span class="seo-panel-summary">Open Graph · Twitter</span></v-expansion-panel-title>
                        <v-expansion-panel-text>
                            <div class="seo-grid">
                                <div class="seo-grid-column">
                                    <h4>Open Graph</h4>
                                    <v-text-field v-model="form.og_title" label="Заголовок OG" maxlength="255" />
                                    <v-textarea v-model="form.og_description" label="Описание OG" rows="2" />
                                    <v-text-field v-model="form.og_image" label="URL изображения OG" maxlength="255" />
                                </div>
                                <div class="seo-grid-column">
                                    <h4>Twitter</h4>
                                    <v-text-field v-model="form.twitter_title" label="Заголовок Twitter" maxlength="255" />
                                    <v-textarea v-model="form.twitter_description" label="Описание Twitter" rows="2" />
                                    <v-text-field v-model="form.twitter_image" label="URL изображения Twitter" maxlength="255" />
                                </div>
                            </div>
                        </v-expansion-panel-text>
                    </v-expansion-panel>
                    <v-expansion-panel value="schema">
                        <v-expansion-panel-title><v-icon icon="mdi-code-json" size="20" class="mr-3" /><span>Микроразметка JSON-LD</span><span class="seo-panel-summary">{{ form.structured_data_text.trim() ? 'Заполнена' : 'Автоматическая' }}</span></v-expansion-panel-title>
                        <v-expansion-panel-text>
                            <div class="seo-field__heading mb-3">
                                <p class="seo-note mt-0">Генерация использует сохранённые данные товара и сразу сохраняет микроразметку. Пустое поле включает автоматическую разметку.</p>
                                <v-btn size="small" color="primary" variant="tonal" prepend-icon="mdi-refresh" :loading="generating" :disabled="controlsBusy" @click="generateJsonLd">Сформировать Product</v-btn>
                            </div>
                            <v-textarea v-model="form.structured_data_text" label="JSON-LD" rows="7" max-rows="18" class="seo-json" />
                        </v-expansion-panel-text>
                    </v-expansion-panel>
                    <v-expansion-panel value="direct">
                        <v-expansion-panel-title><v-icon icon="mdi-bullhorn-outline" size="20" class="mr-3" /><span>Яндекс.Директ</span><span class="seo-panel-summary">{{ directStatus || 'Нет черновика' }}</span></v-expansion-panel-title>
                        <v-expansion-panel-text>
                            <div class="seo-grid mb-4">
                                <v-text-field v-model="form.yandex_direct_title_1" label="Заголовок 1" :counter="directLimits.title_1" />
                                <v-text-field v-model="form.yandex_direct_title_2" label="Заголовок 2" :counter="directLimits.title_2" />
                                <v-textarea v-model="form.yandex_direct_text" label="Текст объявления" rows="2" :counter="directLimits.text" />
                                <v-textarea v-model="form.utm_template" label="UTM-шаблон" rows="2" />
                            </div>
                    <div class="good-direct-panel">
                        <div class="good-direct-panel__top">
                            <div>
                                <strong>Яндекс.Директ</strong>
                                <span>Черновик, лимиты, отправка и последние показатели товара</span>
                            </div>
                            <v-chip
                                size="small"
                                :color="directStatus === 'error' ? 'red' : directStatus ? 'deep-purple' : 'grey'"
                                variant="tonal"
                            >
                                {{ directStatus || 'нет черновика' }}
                            </v-chip>
                        </div>

                        <div class="good-direct-panel__metrics">
                            <div><span>Показы</span><strong>{{ directStats.impressions || 0 }}</strong></div>
                            <div><span>Клики</span><strong>{{ directStats.clicks || 0 }}</strong></div>
                            <div><span>CTR</span><strong>{{ directStats.ctr ?? '-' }}</strong></div>
                            <div><span>Расход</span><strong>{{ directStats.cost || 0 }}</strong></div>
                            <div><span>Заявки</span><strong>{{ directStats.conversions || 0 }}</strong></div>
                            <div><span>CPL</span><strong>{{ directStats.cost_per_conversion ?? '-' }}</strong></div>
                        </div>

                        <div class="good-direct-panel__limits">
                            <span :class="{ 'is-error': form.yandex_direct_title_1.length > directLimits.title_1 }">
                                T1 {{ form.yandex_direct_title_1.length }}/{{ directLimits.title_1 }}
                            </span>
                            <span :class="{ 'is-error': form.yandex_direct_title_2.length > directLimits.title_2 }">
                                T2 {{ form.yandex_direct_title_2.length }}/{{ directLimits.title_2 }}
                            </span>
                            <span :class="{ 'is-error': form.yandex_direct_text.length > directLimits.text }">
                                Text {{ form.yandex_direct_text.length }}/{{ directLimits.text }}
                            </span>
                        </div>

                        <div class="good-direct-panel__actions">
                            <v-btn size="small" color="deep-purple" variant="tonal" :disabled="controlsBusy" @click="generateDirectFields">
                                Заполнить по шаблону
                            </v-btn>
                            <v-btn size="small" color="teal" variant="tonal" @click="checkDirectLimits">
                                Проверить лимиты
                            </v-btn>
                            <v-btn
                                size="small"
                                color="deep-purple-darken-2"
                                variant="flat"
                                :loading="directActionLoading"
                                :disabled="controlsBusy || hasDirectLimitErrors || directActionLoading || fullLaunchLoading"
                                @click="createDirectDraft"
                            >
                                Создать рекламный черновик
                            </v-btn>
                            <v-btn
                                size="small"
                                color="orange-darken-3"
                                variant="tonal"
                                :loading="directActionLoading"
                                :disabled="controlsBusy || directActionLoading || fullLaunchLoading" @click="validateDirectDraft"
                            >
                                Проверить черновик
                            </v-btn>
                            <v-btn
                                size="small"
                                color="red-darken-2"
                                variant="tonal"
                                :loading="directActionLoading"
                                :disabled="controlsBusy || hasDirectLimitErrors || directActionLoading || fullLaunchLoading"
                                title="Отправить только в уже существующую группу Яндекс.Директа. Для автосоздания кампании используйте FULL AUTO."
                                @click="sendDirectDraft"
                            >
                                Отправить в существующую группу
                            </v-btn>
                            <v-btn
                                size="small"
                                color="deep-purple-darken-4"
                                variant="flat"
                                :loading="fullLaunchLoading"
                                :disabled="controlsBusy || hasDirectLimitErrors || directActionLoading || fullLaunchLoading"
                                title="Dry-run: построить структуру без отправки в Яндекс"
                                @click="fullAutoLaunch()"
                            >
                                Проверить автозапуск
                            </v-btn>
                            <v-btn
                                size="small"
                                color="red-darken-3"
                                variant="flat"
                                :loading="fullLaunchLoading"
                                :disabled="controlsBusy || hasDirectLimitErrors || directActionLoading || fullLaunchLoading"
                                title="Реально создать кампанию, группы, объявления и ключи в Яндекс.Директе после подтверждения"
                                @click="fullAutoLaunch(false)"
                            >
                                Запустить рекламу
                            </v-btn>
                            <a
                                v-if="directAdId"
                                class="good-direct-panel__link"
                                :href="`/Ameise/marketing/yandex-direct`"
                            >
                                Статистика товара
                            </a>
                        </div>

                        <v-progress-linear v-if="directLoading" indeterminate height="2" class="mt-2" />
                        <v-alert v-if="directMessage" type="success" density="compact" variant="tonal" class="mt-2">
                            {{ directMessage }}
                        </v-alert>
                        <v-alert v-if="directError" type="error" density="compact" variant="tonal" class="mt-2">
                            {{ directError }}
                        </v-alert>
                    </div>
                        </v-expansion-panel-text>
                    </v-expansion-panel>
                </v-expansion-panels>
            </div>
            <div class="seo-savebar">
                <div class="seo-savebar__status" role="status">
                    <span class="seo-status-dot" :class="{ 'seo-status-dot--dirty': hasChanges }" />
                    <span>{{ !loaded ? 'Загрузка SEO…' : hasChanges ? 'Есть несохранённые изменения' : 'Все изменения сохранены' }}</span>
                </div>
                <v-btn color="primary" variant="flat" prepend-icon="mdi-content-save-outline" :loading="saving" :disabled="controlsBusy || directActionLoading || fullLaunchLoading || !hasChanges" @click="saveForm">Сохранить SEO</v-btn>
            </div>
        </v-defaults-provider>
        <v-dialog v-model="aiDialog" max-width="760" :persistent="aiGenerating" scrollable>
            <v-card class="seo-ai-dialog">
                <v-card-title class="d-flex align-center ga-2 text-wrap"><v-icon icon="mdi-auto-fix" color="primary" size="22" /><span>{{ aiFieldLabel }}</span><v-spacer /><v-btn icon="mdi-close" variant="text" size="small" aria-label="Закрыть предложение AI" :disabled="aiGenerating" @click="aiDialog = false" /></v-card-title>
                <v-card-text>
                    <p class="seo-note mt-0 mb-4">Предложение AI · Timeweb. Проверьте факты и при необходимости отредактируйте текст перед вставкой.</p>
                    <div v-if="aiGenerating" class="seo-ai-progress" role="status"><v-progress-circular indeterminate color="primary" size="28" /><span>AI готовит текст… Это может занять около минуты.</span></div>
                    <v-alert v-if="aiError" type="error" variant="tonal" density="compact" class="mb-3" role="alert">{{ aiError }}</v-alert>
                    <v-textarea v-if="!aiGenerating && !aiError" v-model="aiValue" label="Предложенный текст" variant="outlined" density="compact" :rows="aiField === 'seo_text' ? 12 : 4" auto-grow max-rows="18" counter :maxlength="['h1', 'meta_title'].includes(aiField) ? 255 : undefined" />
                    <details v-if="form[aiField]" class="seo-current-text"><summary>Текущее значение поля</summary><p>{{ form[aiField] }}</p></details>
                </v-card-text>
                <v-card-actions class="seo-ai-dialog__actions">
                    <v-btn variant="text" :disabled="aiGenerating" @click="aiDialog = false">Отмена</v-btn>
                    <v-spacer />
                    <v-btn variant="tonal" color="primary" prepend-icon="mdi-refresh" :disabled="aiGenerating" @click="requestAi()">Другой вариант</v-btn>
                    <v-btn variant="flat" color="primary" :disabled="aiGenerating || !!aiError || !aiValue.trim()" @click="applyAi">Вставить в поле</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-card>
</template>

<style scoped>
.seo-workspace { overflow: visible; border-radius: 14px; }
.seo-toolbar, .seo-toolbar__title, .seo-toolbar__actions, .seo-section__heading, .seo-field__heading, .seo-savebar, .seo-savebar__status { display: flex; align-items: center; gap: 12px; }
.seo-toolbar { justify-content: space-between; padding: 16px 20px; border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
.seo-toolbar h2 { font-size: 18px; font-weight: 650; line-height: 1.4; }
.seo-toolbar p { color: rgba(var(--v-theme-on-surface), .6); font-size: 12px; margin: 2px 0 0; }
.seo-toolbar__actions { flex-wrap: wrap; }
.seo-toolbar__actions :deep(.v-switch) { flex: none; }
.seo-toolbar__actions :deep(.v-label) { font-size: 13px; }
.seo-body { padding: 16px; background: rgba(var(--v-theme-on-surface), .025); }
.seo-main-grid { display: grid; grid-template-columns: minmax(0, 1fr) 320px; align-items: start; gap: 16px; }
.seo-main, .seo-sidebar { min-width: 0; display: grid; gap: 16px; }
.seo-section { min-width: 0; padding: 16px; border: 1px solid rgba(var(--v-border-color), .1); border-radius: 10px; background: rgb(var(--v-theme-surface)); }
.seo-section__heading { justify-content: space-between; margin-bottom: 14px; }
.seo-section h3, .seo-advanced-heading h3 { font-size: 14px; font-weight: 650; }
.seo-muted { color: rgba(var(--v-theme-on-surface), .55); font-size: 12px; }
.seo-field + .seo-field { margin-top: 14px; }
.seo-field__heading { justify-content: space-between; flex-wrap: wrap; gap: 6px; margin-bottom: 7px; }
.seo-field__heading label { font-size: 13px; font-weight: 550; }
.seo-field__heading :deep(.v-btn) { text-transform: none; letter-spacing: 0; }
.seo-field__hint { display: flex; justify-content: space-between; gap: 12px; margin-top: 4px; font-size: 11px; color: rgba(var(--v-theme-on-surface), .55); }
.seo-field__hint span:last-child { white-space: nowrap; }
.seo-workspace :deep(.v-field__input) { font-size: 13px; }
.seo-workspace :deep(.v-textarea textarea) { line-height: 1.6; }
.seo-snippet { overflow-wrap: anywhere; }
.seo-snippet__url { color: rgba(var(--v-theme-on-surface), .75); font-size: 12px; margin-bottom: 6px; }
.seo-snippet__title { color: rgb(var(--v-theme-primary)); font-size: 19px; line-height: 1.3; margin-bottom: 6px; }
.seo-snippet__description { color: rgba(var(--v-theme-on-surface), .75); font-size: 13px; line-height: 1.6; }
.seo-note { font-size: 12px; line-height: 1.5; color: rgba(var(--v-theme-on-surface), .6); margin-top: 12px; }
.seo-ai-context { border-color: rgba(var(--v-theme-primary), .22); }
.seo-check { display: flex; gap: 8px; align-items: center; font-size: 12px; margin-top: 8px; }
.seo-advanced-heading { display: flex; gap: 12px; align-items: baseline; flex-wrap: wrap; margin: 22px 0 10px; }
.seo-panels { border: 1px solid rgba(var(--v-border-color), .1); border-radius: 10px; overflow: hidden; }
.seo-panels :deep(.v-expansion-panel-title) { min-height: 52px; padding: 12px 16px; font-size: 13px; }
.seo-panels :deep(.v-expansion-panel-text__wrapper) { padding: 4px 16px 18px; }
.seo-panel-summary { font-size: 12px; color: rgba(var(--v-theme-on-surface), .5); margin-left: auto; padding: 0 12px; }
.seo-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
.seo-grid--three { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.seo-grid-column { display: grid; gap: 12px; min-width: 0; }
.seo-grid-column h4 { font-size: 13px; font-weight: 550; }
.seo-json :deep(textarea) { font-family: ui-monospace, monospace; font-size: 12px; }
.seo-savebar { justify-content: space-between; position: sticky; bottom: 12px; z-index: 3; padding: 12px 20px; background: rgb(var(--v-theme-surface)); border-top: 1px solid rgba(var(--v-border-color), .12); border-radius: 0 0 14px 14px; box-shadow: 0 -3px 14px rgba(0, 0, 0, .035); }
.seo-savebar__status { font-size: 12px; color: rgba(var(--v-theme-on-surface), .65); gap: 8px; }
.seo-status-dot { width: 7px; height: 7px; flex: 0 0 7px; background: rgb(var(--v-theme-success)); border-radius: 50%; }
.seo-status-dot--dirty { background: rgb(var(--v-theme-warning)); }
.seo-ai-progress { display: flex; align-items: center; gap: 16px; padding: 30px 0; font-size: 14px; }
.seo-current-text { font-size: 12px; color: rgba(var(--v-theme-on-surface), .65); margin-top: 16px; }
.seo-current-text summary { cursor: pointer; }
.seo-current-text p { margin-top: 10px; white-space: pre-wrap; max-height: 200px; overflow-y: auto; }
.seo-ai-dialog__actions { padding: 12px 16px; flex-wrap: wrap; gap: 8px; }
.good-direct-panel { border-top: 1px solid rgba(var(--v-border-color), .12); padding-top: 14px; }
.good-direct-panel__top, .good-direct-panel__actions, .good-direct-panel__limits { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.good-direct-panel__top { justify-content: space-between; margin-bottom: 12px; }
.good-direct-panel__top strong, .good-direct-panel__top span { display: block; }
.good-direct-panel__top strong { font-size: 14px; }
.good-direct-panel__top span, .good-direct-panel__limits span { font-size: 12px; color: rgba(var(--v-theme-on-surface), .6); }
.good-direct-panel__metrics { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 8px; margin-bottom: 12px; }
.good-direct-panel__metrics div { padding: 8px 10px; border-radius: 6px; background: rgba(var(--v-theme-on-surface), .035); }
.good-direct-panel__metrics span, .good-direct-panel__metrics strong { display: block; }
.good-direct-panel__metrics span { font-size: 11px; color: rgba(var(--v-theme-on-surface), .6); }
.good-direct-panel__metrics strong { font-size: 14px; font-weight: 600; margin-top: 4px; }
.good-direct-panel__limits { margin-bottom: 12px; }
.good-direct-panel__limits .is-error { color: rgb(var(--v-theme-error)); }
.good-direct-panel__actions :deep(.v-btn) { max-width: 100%; height: auto; min-height: 32px; padding-block: 8px; }
.good-direct-panel__actions :deep(.v-btn__content) { white-space: normal; }
.good-direct-panel__link { color: rgb(var(--v-theme-primary)); font-size: 12px; }
@media (max-width: 1100px) {
    .seo-main-grid { grid-template-columns: minmax(0, 1fr) 280px; }
    .seo-toolbar { flex-wrap: wrap; gap: 8px; }
}
@media (max-width: 800px) {
    .seo-main-grid { grid-template-columns: 1fr; }
    .seo-sidebar { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .seo-sidebar .seo-section:last-child { grid-column: 1 / -1; }
    .seo-panel-summary { display: none; }
    .seo-grid--three { grid-template-columns: 1fr; }
}
@media (max-width: 520px) {
    .seo-body { padding: 10px; }
    .seo-section { padding: 12px; }
    .seo-toolbar { padding: 12px; }
    .seo-toolbar__actions { width: 100%; justify-content: space-between; gap: 4px; }
    .seo-grid, .seo-sidebar { grid-template-columns: 1fr; }
    .seo-savebar { padding: 10px 12px; gap: 8px; flex-wrap: wrap; bottom: 0; }
    .seo-savebar :deep(.v-btn) { width: 100%; }
    .seo-field__heading label { flex-basis: 100%; }
    .seo-field__hint { font-size: 10px; }
    .good-direct-panel__metrics { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
</style>
