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

const {
    seo,
    loading,
    saving,
    fetchSeo,
    saveSeo,
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
    form.og_image = data?.og_image || ''

    form.twitter_title = data?.twitter_title || ''
    form.twitter_description = data?.twitter_description || ''
    form.twitter_image = data?.twitter_image || ''

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
    }
}

async function submit() {
    const data = await saveSeo(payload())
    fillForm(data)
}

watch(seo, (value) => {
    if (value) fillForm(value)
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
