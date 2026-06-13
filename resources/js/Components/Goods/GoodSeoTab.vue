<script setup>
import { computed, onMounted, reactive, watch } from 'vue'
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
