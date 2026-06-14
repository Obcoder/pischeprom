<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import axios from 'axios'
import { useGoodSeo } from '@/Composables/useGoodSeo'

const props = defineProps({
    good: {
        type: Object,
        required: true,
    },
})

const goodId = computed(() => props.good.id)
const seoImageFallback = computed(() => props.good?.ava_thumb || '')

const {
    seo,
    loading,
    saving,
    generating,
    fetchSeo,
    saveSeo,
    generateStructuredData,
} = useGoodSeo(goodId.value)

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
    form.og_image = seoImageFallback.value || data?.og_image || ''

    form.twitter_title = data?.twitter_title || ''
    form.twitter_description = data?.twitter_description || ''
    form.twitter_image = seoImageFallback.value || data?.twitter_image || ''

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
            structuredData = null
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
        og_image: seoImageFallback.value || form.og_image,

        twitter_title: form.twitter_title,
        twitter_description: form.twitter_description,
        twitter_image: seoImageFallback.value || form.twitter_image,

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
    const data = await generateStructuredData()
    fillForm(data)
}

function faqToArray(value) {
    return String(value || '')
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean)
        .map((line) => {
            const [question, answer] = line.split('|').map((item) => item?.trim())

            return {
                question: question || '',
                answer: answer || '',
            }
        })
        .filter((item) => item.question && item.answer)
}

async function submit() {
    const data = await saveSeo(payload())
    fillForm(data)
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

async function fullAutoLaunch() {
    fullLaunchLoading.value = true

    try {
        await submit()
        const { data } = await axios.post(`/api/marketing/direct/launch/${props.good.id}`, {
            dry_run: true,
        })
        directStatus.value = data.status || directStatus.value
        setDirectMessage(data.message || 'FULL AUTO LAUNCH dry-run выполнен.')
        await loadDirectInfo()
    } catch (error) {
        const errors = error.response?.data?.errors || []
        setDirectError(errors.length ? errors.join('; ') : (error.response?.data?.message || 'Не удалось выполнить FULL AUTO LAUNCH.'))
    } finally {
        fullLaunchLoading.value = false
    }
}

watch(seo, (value) => {
    if (value) fillForm(value)
})

watch(seoImageFallback, (value) => {
    if (!value) return

    form.og_image = value
    form.twitter_image = value
})

onMounted(async () => {
    const data = await fetchSeo()
    fillForm(data)
    await loadDirectInfo()
})
</script>

<template>
    <v-card>
        <v-card-title class="d-flex align-center justify-space-between">
            <span>SEO товара</span>

            <v-switch
                v-model="form.is_active"
                label="SEO активно"
                color="green"
                hide-details
                inset
                density="compact"
            />
        </v-card-title>

        <v-card-text>
            <v-alert
                type="info"
                variant="tonal"
                class="mb-4"
            >
                Здесь храним SEO-данные для будущей публичной карточки товара в интернет-магазине.
            </v-alert>

            <v-progress-linear
                v-if="loading"
                indeterminate
                class="mb-4"
            />

            <v-row>
                <v-col cols="12" md="8">
                    <v-text-field
                        v-model="form.meta_title"
                        label="Meta title"
                        variant="outlined"
                        density="compact"
                        counter="255"
                    />
                </v-col>

                <v-col cols="12" md="4">
                    <v-text-field
                        v-model="form.focus_keyword"
                        label="Фокусный ключ"
                        variant="outlined"
                        density="compact"
                    />
                </v-col>

                <v-col cols="12">
                    <v-textarea
                        v-model="form.meta_description"
                        label="Meta description"
                        variant="outlined"
                        density="compact"
                        rows="3"
                        counter
                    />
                </v-col>

                <v-col cols="12" md="6">
                    <v-text-field
                        v-model="form.h1"
                        label="H1"
                        variant="outlined"
                        density="compact"
                    />
                </v-col>

                <v-col cols="12" md="3">
                    <v-text-field
                        v-model="form.breadcrumbs_title"
                        label="Название в хлебных крошках"
                        variant="outlined"
                        density="compact"
                    />
                </v-col>

                <v-col cols="12" md="3">
                    <v-select
                        v-model="form.robots"
                        :items="[
                            'index,follow',
                            'noindex,follow',
                            'index,nofollow',
                            'noindex,nofollow'
                        ]"
                        label="Robots"
                        variant="outlined"
                        density="compact"
                    />
                </v-col>

                <v-col cols="12" md="6">
                    <v-text-field
                        v-model="form.slug_override"
                        label="SEO slug override"
                        variant="outlined"
                        density="compact"
                        hint="Пока не меняет основной slug товара, только хранится для SEO"
                        persistent-hint
                    />
                </v-col>

                <v-col cols="12" md="6">
                    <v-text-field
                        v-model="form.canonical_url"
                        label="Canonical URL"
                        variant="outlined"
                        density="compact"
                    />
                </v-col>
            </v-row>

            <v-divider class="my-4" />

            <v-row>
                <v-col cols="12">
                    <v-alert type="success" variant="tonal">
                        Публикация и продвижение
                    </v-alert>
                </v-col>

                <v-col cols="12" md="4">
                    <v-switch
                        v-model="form.include_in_sitemap"
                        label="Включить в sitemap"
                        color="green"
                        hide-details
                    />
                </v-col>

                <v-col cols="12" md="4">
                    <v-switch
                        v-model="form.include_in_yandex_feed"
                        label="Включить в Yandex Direct feed"
                        color="green"
                        hide-details
                    />
                </v-col>

                <v-col cols="12" md="4">
                    <v-select
                        v-model="form.availability_status"
                        :items="[
                { title: 'В наличии', value: 'in_stock' },
                { title: 'По запросу', value: 'on_request' },
                { title: 'Под заказ', value: 'preorder' },
                { title: 'Нет в наличии', value: 'out_of_stock' },
            ]"
                        item-title="title"
                        item-value="value"
                        label="Наличие"
                        variant="outlined"
                        density="compact"
                    />
                </v-col>

                <v-col cols="12" md="4">
                    <v-text-field
                        v-model="form.min_order"
                        label="Минимальная партия"
                        variant="outlined"
                        density="compact"
                        placeholder="Например: от 1 паллеты / от 100 кг"
                    />
                </v-col>

                <v-col cols="12" md="4">
                    <v-textarea
                        v-model="form.delivery_note"
                        label="Доставка"
                        variant="outlined"
                        density="compact"
                        rows="2"
                    />
                </v-col>

                <v-col cols="12" md="4">
                    <v-textarea
                        v-model="form.payment_note"
                        label="Оплата"
                        variant="outlined"
                        density="compact"
                        rows="2"
                    />
                </v-col>
            </v-row>

            <v-divider class="my-4" />

            <v-row>
                <v-col cols="12">
                    <v-alert type="info" variant="tonal">
                        Данные для Яндекс.Директа
                    </v-alert>
                </v-col>

                <v-col cols="12" md="6">
                    <v-text-field
                        v-model="form.yandex_direct_title_1"
                        label="Заголовок Директ 1"
                        variant="outlined"
                        density="compact"
                    />
                </v-col>

                <v-col cols="12" md="6">
                    <v-text-field
                        v-model="form.yandex_direct_title_2"
                        label="Заголовок Директ 2"
                        variant="outlined"
                        density="compact"
                    />
                </v-col>

                <v-col cols="12">
                    <v-textarea
                        v-model="form.yandex_direct_text"
                        label="Текст объявления"
                        variant="outlined"
                        density="compact"
                        rows="3"
                    />
                </v-col>

                <v-col cols="12">
                    <v-textarea
                        v-model="form.utm_template"
                        label="UTM-шаблон"
                        variant="outlined"
                        density="compact"
                        rows="2"
                        placeholder="utm_source=yandex&utm_medium=cpc&utm_campaign={campaign_id}&utm_content={ad_id}&utm_term={keyword}"
                    />
                </v-col>

                <v-col cols="12">
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
                            <v-btn size="small" color="deep-purple" variant="tonal" @click="generateDirectFields">
                                Сгенерировать объявление
                            </v-btn>
                            <v-btn size="small" color="teal" variant="tonal" @click="checkDirectLimits">
                                Проверить лимиты
                            </v-btn>
                            <v-btn
                                size="small"
                                color="deep-purple-darken-2"
                                variant="flat"
                                :loading="directActionLoading"
                                :disabled="hasDirectLimitErrors"
                                @click="createDirectDraft"
                            >
                                Создать рекламный черновик
                            </v-btn>
                            <v-btn
                                size="small"
                                color="orange-darken-3"
                                variant="tonal"
                                :loading="directActionLoading"
                                @click="validateDirectDraft"
                            >
                                Проверить черновик
                            </v-btn>
                            <v-btn
                                size="small"
                                color="red-darken-2"
                                variant="tonal"
                                :loading="directActionLoading"
                                :disabled="hasDirectLimitErrors"
                                title="Отправить только в уже существующую группу Яндекс.Директа. Для автосоздания кампании используйте FULL AUTO."
                                @click="sendDirectDraft"
                            >
                                Отправить в existing AdGroup
                            </v-btn>
                            <v-btn
                                size="small"
                                color="deep-purple-darken-4"
                                variant="flat"
                                :loading="fullLaunchLoading"
                                :disabled="hasDirectLimitErrors"
                                title="Dry-run: построить структуру без отправки в Яндекс"
                                @click="fullAutoLaunch"
                            >
                                FULL AUTO DRY RUN
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
                </v-col>
            </v-row>

            <v-divider class="my-4" />

            <v-row>
                <v-col cols="12">
                    <v-textarea
                        v-model="form.faq_text"
                        label="FAQ"
                        variant="outlined"
                        density="compact"
                        rows="5"
                        hint="Формат: вопрос | ответ. Каждый FAQ с новой строки."
                        persistent-hint
                    />
                </v-col>

                <v-col cols="12">
                    <v-btn
                        color="teal"
                        variant="tonal"
                        :loading="generating"
                        @click="generateJsonLd"
                    >
                        Сгенерировать JSON-LD Product
                    </v-btn>
                </v-col>
            </v-row>

            <v-row>
                <v-col cols="12" md="6">
                    <v-textarea
                        v-model="form.semantic_core_text"
                        label="Семантическое ядро"
                        variant="outlined"
                        density="compact"
                        rows="7"
                        hint="Каждый ключ с новой строки"
                        persistent-hint
                    />
                </v-col>

                <v-col cols="12" md="3">
                    <v-textarea
                        v-model="form.keywords_text"
                        label="Keywords"
                        variant="outlined"
                        density="compact"
                        rows="7"
                        hint="Каждое слово/фраза с новой строки"
                        persistent-hint
                    />
                </v-col>

                <v-col cols="12" md="3">
                    <v-textarea
                        v-model="form.search_queries_text"
                        label="Поисковые запросы"
                        variant="outlined"
                        density="compact"
                        rows="7"
                        hint="Каждый запрос с новой строки"
                        persistent-hint
                    />
                </v-col>
            </v-row>

            <v-divider class="my-4" />

            <v-row>
                <v-col cols="12" md="6">
                    <v-text-field
                        v-model="form.og_title"
                        label="OG title"
                        variant="outlined"
                        density="compact"
                    />
                </v-col>

                <v-col cols="12" md="6">
                    <v-text-field
                        v-model="form.og_image"
                        label="OG image URL"
                        variant="outlined"
                        density="compact"
                    />
                </v-col>

                <v-col cols="12">
                    <v-textarea
                        v-model="form.og_description"
                        label="OG description"
                        variant="outlined"
                        density="compact"
                        rows="3"
                    />
                </v-col>

                <v-col cols="12" md="6">
                    <v-text-field
                        v-model="form.twitter_title"
                        label="Twitter title"
                        variant="outlined"
                        density="compact"
                    />
                </v-col>

                <v-col cols="12" md="6">
                    <v-text-field
                        v-model="form.twitter_image"
                        label="Twitter image URL"
                        variant="outlined"
                        density="compact"
                    />
                </v-col>

                <v-col cols="12">
                    <v-textarea
                        v-model="form.twitter_description"
                        label="Twitter description"
                        variant="outlined"
                        density="compact"
                        rows="3"
                    />
                </v-col>
            </v-row>

            <v-divider class="my-4" />

            <v-row>
                <v-col cols="12">
                    <v-textarea
                        v-model="form.short_seo_text"
                        label="Короткий SEO-текст"
                        variant="outlined"
                        density="compact"
                        rows="3"
                    />
                </v-col>

                <v-col cols="12">
                    <v-textarea
                        v-model="form.seo_text"
                        label="Большой SEO-текст"
                        variant="outlined"
                        density="compact"
                        rows="8"
                    />
                </v-col>

                <v-col cols="12">
                    <v-textarea
                        v-model="form.structured_data_text"
                        label="Structured data JSON-LD"
                        variant="outlined"
                        density="compact"
                        rows="8"
                        hint="Можно оставить пустым. Позже сделаем автогенерацию Product schema."
                        persistent-hint
                    />
                </v-col>
            </v-row>
        </v-card-text>

        <v-card-actions>
            <v-btn
                color="deep-purple-darken-1"
                variant="tonal"
                :loading="saving"
                @click="submit"
            >
                Сохранить SEO
            </v-btn>
        </v-card-actions>
    </v-card>
</template>

<style scoped>
.good-direct-panel {
    padding: 10px;
    border: 1px solid rgba(91, 33, 182, 0.18);
    border-radius: 12px;
    background: linear-gradient(135deg, #f5f0ff 0%, #ffffff 100%);
}

.good-direct-panel__top,
.good-direct-panel__actions,
.good-direct-panel__limits {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.good-direct-panel__top {
    justify-content: space-between;
    margin-bottom: 8px;
}

.good-direct-panel__top strong,
.good-direct-panel__top span {
    display: block;
}

.good-direct-panel__top strong {
    color: #3b0764;
    font-size: 14px;
    font-weight: 900;
}

.good-direct-panel__top span,
.good-direct-panel__limits span {
    color: rgba(59, 7, 100, 0.62);
    font-size: 11px;
    font-weight: 800;
}

.good-direct-panel__metrics {
    display: grid;
    grid-template-columns: repeat(6, minmax(72px, 1fr));
    gap: 6px;
    margin-bottom: 8px;
}

.good-direct-panel__metrics div {
    padding: 5px 7px;
    border: 1px solid rgba(91, 33, 182, 0.12);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.78);
}

.good-direct-panel__metrics span,
.good-direct-panel__metrics strong {
    display: block;
}

.good-direct-panel__metrics span {
    color: rgba(59, 7, 100, 0.56);
    font-size: 10px;
    font-weight: 800;
}

.good-direct-panel__metrics strong {
    color: #3b0764;
    font-size: 13px;
    font-weight: 900;
    text-align: right;
}

.good-direct-panel__limits {
    margin-bottom: 8px;
}

.good-direct-panel__limits .is-error {
    color: #b91c1c;
}

.good-direct-panel__link {
    color: #4c1d95;
    font-size: 12px;
    font-weight: 900;
    text-decoration: none;
}

@media (max-width: 900px) {
    .good-direct-panel__metrics {
        grid-template-columns: repeat(2, minmax(72px, 1fr));
    }
}
</style>
