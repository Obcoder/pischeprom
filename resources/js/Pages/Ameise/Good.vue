<script setup>
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'
import { ref, computed } from 'vue'
import axios from 'axios'
import { useHead } from '@unhead/vue'
import { route } from 'ziggy-js'
import { usePage } from '@inertiajs/vue3'
import CatalogGoodRecordDialog from '@/Components/Catalog/CatalogGoodRecordDialog.vue'
import CatalogGoodOperations from '@/Components/Catalog/CatalogGoodOperations.vue'

defineOptions({ layout: VerwalterLayout })
const props = defineProps({ good: { type: Object, required: true } })
const page = usePage()
const currentUrl = computed(() => String(page.props.ziggy?.location || page.props.ziggy?.url || 'https://пищепром-сервер.рф'))
const tabs = [
    { key: 'quotations', label: 'Quotations / Закупки' }, { key: 'prices', label: 'Цены' },
    { key: 'price-types', label: 'Виды цен' }, { key: 'recommendations', label: 'ОКВЭД-рекомендации' },
    { key: 'collections', label: 'Подборки' }, { key: 'media', label: 'Media' }, { key: 'sales', label: 'Продажи' },
]
function requestedTab() {
    try { return new URL(page.url || currentUrl.value, currentUrl.value).searchParams.get('tab') }
    catch { return null }
}
const requested = requestedTab()
const activeTab = ref(tabs.some(tab => tab.key === requested) ? requested : 'quotations')
const recordInitialTab = ref(requested === 'seo' ? 'seo' : 'overview')
const recordOpen = ref(requested === 'seo')
const goodData = ref(props.good)
const pageError = ref(null)
const operations = ref(null)
const currentVatRate = computed(() => goodData.value?.vat_rate || goodData.value?.vatRate || null)
const currentFields = computed(() => goodData.value?.fields || [])
function openRecord() { recordInitialTab.value = 'overview'; recordOpen.value = true }
function loaded(good) { goodData.value = good; pageError.value = null }
async function refreshAfterRecord() {
    pageError.value = null
    try {
        const good = operations.value ? await operations.value.refresh({ throwOnError: true }) : (await axios.get(route('good.fetch', props.good.id))).data
        if (good) loaded(good)
    } catch (failure) {
        if (failure.response?.status === 404) window.location.href = route('Ameise.products')
        else pageError.value = failure.response?.data?.message || 'Не удалось обновить товар'
    }
}
// --------------------------------------------------
// SEO HEAD FOR ADMIN PAGE
// --------------------------------------------------
const seoTitle = computed(() => {
    return goodData.value?.name
        ? `${goodData.value.name} - Ameise`
        : "Good - Ameise";
});

const seoDescription = computed(() => {
    const description = String(goodData.value?.description || "Страница товара в админке");

    return description.slice(0, 160);
});

useHead({
    title: seoTitle,
    meta: [
        {
            name: "description",
            content: seoDescription,
        },
        {
            name: "keywords",
            content: computed(() =>
                goodData.value?.name
                    ? `${goodData.value.name}, товар, pischeprom`
                    : "товар"
            ),
        },
        {
            property: "og:title",
            content: computed(() => goodData.value?.name || "Good"),
        },
        {
            property: "og:description",
            content: seoDescription,
        },
        {
            property: "og:image",
            content: computed(() => goodData.value?.ava_image || "/default-image.jpg"),
        },
        {
            property: "og:url",
            content: currentUrl,
        }
    ],
});

</script>

<template>
    <v-container fluid class="good-page">
        <CatalogGoodRecordDialog v-model="recordOpen" :good-id="good.id" :initial-tab="recordInitialTab" @saved="refreshAfterRecord" @deleted="refreshAfterRecord" />
        <div class="good-page__actions">
            <v-btn prepend-icon="mdi-card-text-outline" variant="tonal" density="compact" @click="openRecord">Карточка записи</v-btn>
            <v-chip size="small" variant="tonal" color="deep-purple">ID: {{ goodData.id }}</v-chip>
            <v-chip size="small" variant="tonal" :color="goodData.is_published ? 'green' : 'grey'">{{ goodData.is_published ? 'published' : 'hidden' }}</v-chip>
            <v-chip v-if="currentVatRate" size="small" variant="tonal" color="blue-grey">НДС: {{ currentVatRate.title }} / {{ currentVatRate.rate }}%</v-chip>
        </div>
        <v-alert v-if="pageError" type="error" variant="tonal" density="compact" class="mb-3">{{ pageError }}</v-alert>
        <v-card class="mb-4">
            <v-card-text>
                <div class="text-caption text-medium-emphasis mb-1">Good / товар</div>
                <h1 class="text-h5 font-weight-bold mb-1">{{ goodData.name }}</h1>
                <div class="text-caption text-medium-emphasis">slug: {{ goodData.slug || '—' }}</div>
                <div class="good-page__summary"><span>Единица учёта: <strong>{{ goodData.measurement?.unit_label || 'не задана' }}</strong></span><span>Масса упаковки: <strong>{{ goodData.denominator || '—' }} кг</strong></span><span>Предложения: <strong>{{ (goodData.quotations || []).length }}</strong></span></div>
            </v-card-text>
        </v-card>
        <v-card class="mb-4"><v-tabs v-model="activeTab" density="compact" color="deep-purple-darken-1" show-arrows><v-tab v-for="tab in tabs" :key="tab.key" :value="tab.key">{{ tab.label }}</v-tab></v-tabs></v-card>
        <CatalogGoodOperations ref="operations" :good-id="good.id" :active-tab="activeTab" :active="!recordOpen" @loaded="loaded" @changed="loaded" @request-basics="openRecord" />
    </v-container>
</template>

<style scoped>
.good-page { min-width: 0; }
.good-page__actions { display: flex; align-items: center; justify-content: flex-end; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
.good-page__summary { display: flex; flex-wrap: wrap; gap: 8px 24px; margin-top: 14px; font-size: 12px; color: #86738f; }
.good-page h1 { overflow-wrap: anywhere; }
</style>
