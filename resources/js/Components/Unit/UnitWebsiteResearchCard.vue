<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import axios from 'axios'
import BaseSectionCard from '@/Components/Unit/BaseSectionCard.vue'

const props = defineProps({
    unitId: { type: Number, required: true },
})

const records = ref([])
const loading = ref(false)
const error = ref('')
const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)
const expanded = ref(new Set())
const hasPrevious = computed(() => currentPage.value > 1)
const hasNext = computed(() => currentPage.value < lastPage.value)
let controller = null
let revision = 0
let requestedPage = 1

async function load(page = currentPage.value) {
    controller?.abort()
    const request = ++revision
    controller = new AbortController()
    requestedPage = Math.max(1, Number(page) || 1)
    if (!props.unitId) return
    loading.value = true
    error.value = ''

    try {
        const { data } = await axios.get(`/api/units/${props.unitId}/website-research`, {
            params: { page: requestedPage },
            signal: controller.signal,
        })
        if (request !== revision) return

        records.value = Array.isArray(data.data) ? data.data : []
        currentPage.value = Math.max(1, Number(data.meta?.current_page) || requestedPage)
        lastPage.value = Math.max(currentPage.value, Number(data.meta?.last_page) || 1)
        total.value = Math.max(0, Number(data.meta?.total) || records.value.length)
        expanded.value = new Set([...expanded.value].filter(id => records.value.some(record => record.id === id)))
    } catch (failure) {
        if (request !== revision || axios.isCancel(failure)) return
        error.value = failure?.response?.status === 403
            ? 'Нет доступа к исследованиям этого Unit.'
            : failure?.response?.data?.message || 'Не удалось загрузить исследования сайтов.'
    } finally {
        if (request === revision) loading.value = false
    }
}

function retry() {
    return load(requestedPage)
}

function toggle(id) {
    const next = new Set(expanded.value)
    if (next.has(id)) next.delete(id)
    else next.add(id)
    expanded.value = next
}

function products(record) {
    return Array.isArray(record.result?.products) ? record.result.products : []
}

function pages(record) {
    return Array.isArray(record.result?.pages) ? record.result.pages : []
}

function warnings(record) {
    return Array.isArray(record.result?.warnings) ? record.result.warnings : []
}

function safeLink(value) {
    if (typeof value !== 'string' || !/^https?:\/\//i.test(value) || /[\s\u0000-\u001f\u007f\\]/.test(value)) return null
    try {
        const url = new URL(value)
        return ['http:', 'https:'].includes(url.protocol) && url.hostname && !url.username && !url.password ? url.href : null
    } catch {
        return null
    }
}

function siteLabel(value) {
    const link = safeLink(value)
    return link ? new URL(link).hostname.replace(/^www\./i, '') : String(value || 'Сайт не указан')
}

function dateLabel(value) {
    if (!value) return 'Дата не указана'
    const date = new Date(value)
    return Number.isNaN(date.getTime()) ? 'Дата не указана' : new Intl.DateTimeFormat('ru-RU', { dateStyle: 'short', timeStyle: 'short' }).format(date)
}

watch(() => props.unitId, () => {
    records.value = []
    currentPage.value = 1
    lastPage.value = 1
    total.value = 0
    expanded.value = new Set()
    load(1)
}, { immediate: true })

onBeforeUnmount(() => { revision++; controller?.abort() })
</script>

<template>
    <section id="website-research" class="unit-website-research" aria-label="Сохранённые исследования сайтов">
        <BaseSectionCard title="Исследования сайтов" icon="mdi-web-check" compact>
            <template #actions>
                <span v-if="total" class="unit-website-research__count">{{ total }}</span>
                <button type="button" class="unit-website-research__icon" :disabled="loading" aria-label="Обновить список исследований" title="Обновить список сохранённых результатов" @click="load(1)">
                    <v-icon icon="mdi-refresh" size="19" />
                </button>
            </template>

            <p class="unit-website-research__intro">Сохранённые сведения о компании, её товарах и услугах.</p>
            <v-progress-linear v-if="loading && records.length" indeterminate height="2" color="#800000" class="mb-2" aria-label="Обновление исследований" />

            <div v-if="error" class="unit-website-research__error" role="alert">
                <v-icon icon="mdi-alert-circle-outline" size="17" /><span>{{ error }}</span>
                <button type="button" :disabled="loading" @click="retry">Повторить</button>
            </div>
            <div v-if="loading && !records.length" class="unit-website-research__state" role="status">
                <v-progress-circular indeterminate size="20" width="2" color="#800000" /><span>Загружаем исследования…</span>
            </div>
            <div v-else-if="!error && !records.length" class="unit-website-research__empty">
                <v-icon icon="mdi-text-box-search-outline" size="26" />
                <div><strong>Сохранённых исследований пока нет</strong><span>Результат анализа сайта можно сохранить в Unit из инструментов письма.</span></div>
            </div>

            <div v-if="records.length" class="unit-website-research__records">
                <article v-for="record in records" :key="record.id" class="unit-website-research__record">
                    <div class="unit-website-research__record-heading">
                        <button type="button" class="unit-website-research__toggle" :aria-expanded="expanded.has(record.id)" :aria-controls="`website-research-${record.id}`" :aria-label="`${expanded.has(record.id) ? 'Свернуть' : 'Показать'} исследование ${siteLabel(record.url)}`" @click="toggle(record.id)">
                            <span class="unit-website-research__chevron"><v-icon :icon="expanded.has(record.id) ? 'mdi-chevron-up' : 'mdi-chevron-down'" size="19" /></span>
                            <span class="unit-website-research__site"><strong>{{ siteLabel(record.url) }}</strong><span>{{ dateLabel(record.researched_at || record.saved_at) }}</span></span>
                            <span v-if="record.result?.partial" class="unit-website-research__partial">Частично</span>
                            <span class="unit-website-research__product-count">Товаров: {{ products(record).length }}</span>
                        </button>
                        <a v-if="safeLink(record.url)" :href="safeLink(record.url)" target="_blank" rel="noopener noreferrer" class="unit-website-research__icon" :aria-label="`Открыть сайт ${siteLabel(record.url)}`" title="Открыть сайт"><v-icon icon="mdi-open-in-new" size="15" /></a>
                    </div>

                    <div v-if="expanded.has(record.id)" :id="`website-research-${record.id}`" class="unit-website-research__details">
                        <p v-if="record.result?.summary" class="unit-website-research__summary">{{ record.result.summary }}</p>
                        <div v-if="record.result?.partial || warnings(record).length" class="unit-website-research__warnings">
                            <p v-if="record.result?.partial"><v-icon icon="mdi-information-outline" size="14" /> Исследована часть страниц сайта; список товаров может быть неполным.</p>
                            <p v-for="(warning, index) in warnings(record)" :key="index">{{ warning }}</p>
                        </div>

                        <div v-if="products(record).length" class="unit-website-research__products" aria-label="Товары и услуги из исследования">
                            <div v-for="(product, index) in products(record)" :key="index" class="unit-website-research__product">
                                <strong>{{ product.name }}</strong>
                                <p v-if="product.description">{{ product.description }}</p>
                                <details v-if="product.evidence" class="unit-website-research__evidence"><summary>Фрагмент источника</summary><blockquote>{{ product.evidence }}</blockquote></details>
                                <a v-if="safeLink(product.source_url)" :href="safeLink(product.source_url)" target="_blank" rel="noopener noreferrer">Источник <v-icon icon="mdi-open-in-new" size="11" /></a>
                            </div>
                        </div>
                        <p v-else class="unit-website-research__no-products">В сохранённом результате нет отдельных товаров или услуг.</p>

                        <details v-if="pages(record).length" class="unit-website-research__sources">
                            <summary>Изученные страницы <span>{{ pages(record).length }}</span></summary>
                            <ul><li v-for="(page, index) in pages(record)" :key="index"><a v-if="safeLink(page.url)" :href="safeLink(page.url)" target="_blank" rel="noopener noreferrer">{{ page.title || page.url }} <v-icon icon="mdi-open-in-new" size="11" /></a><span v-else>{{ page.title || page.url }}</span></li></ul>
                        </details>
                        <p v-if="record.saved_at" class="unit-website-research__saved">Сохранено в Unit: {{ dateLabel(record.saved_at) }}</p>
                    </div>
                </article>
            </div>

            <nav v-if="lastPage > 1" class="unit-website-research__pagination" aria-label="Страницы исследований сайтов">
                <span>Всего: {{ total }}</span>
                <div>
                    <button type="button" class="unit-website-research__icon" :disabled="loading || !hasPrevious" aria-label="Предыдущая страница исследований" @click="load(currentPage - 1)"><v-icon icon="mdi-chevron-left" size="18" /></button>
                    <span>{{ currentPage }} / {{ lastPage }}</span>
                    <button type="button" class="unit-website-research__icon" :disabled="loading || !hasNext" aria-label="Следующая страница исследований" @click="load(currentPage + 1)"><v-icon icon="mdi-chevron-right" size="18" /></button>
                </div>
            </nav>
        </BaseSectionCard>
    </section>
</template>

<style scoped>
.unit-website-research { min-width: 0; scroll-margin-top: 88px; }
.unit-website-research__count { display: inline-grid; place-items: center; min-width: 22px; height: 22px; padding: 0 6px; border-radius: 0; background: #80002010; color: #7c3045; font-size: 10px; font-weight: 700; }
.unit-website-research__icon { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; flex: 0 0 auto; border-radius: 0; color: #7a6670; }
.unit-website-research__icon:hover:not(:disabled) { color: #800020; background: #8000200b; }
.unit-website-research button:disabled { opacity: .4; cursor: default; }
.unit-website-research button:focus-visible, .unit-website-research a:focus-visible, .unit-website-research summary:focus-visible { outline: 2px solid #8c5262; outline-offset: 2px; }
.unit-website-research__intro { margin: 0 0 10px; color: #77727b; font-size: 11px; line-height: 1.5; }
.unit-website-research__state { display: flex; align-items: center; justify-content: center; gap: 10px; min-height: 80px; color: #77727b; font-size: 12px; }
.unit-website-research__empty { display: flex; align-items: center; gap: 12px; padding: 13px; background: #f6f5f7; border: 1px dashed #d9d7dc; border-radius: 0; color: #77727b; }
.unit-website-research__empty > div { display: flex; flex-direction: column; gap: 5px; }
.unit-website-research__empty strong { color: #352345; font-size: 12px; font-weight: 600; }
.unit-website-research__empty span { font-size: 11px; line-height: 1.5; }
.unit-website-research__error { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; padding: 10px; margin-bottom: 10px; border-radius: 0; background: #fff2f2; color: #985057; font-size: 11px; }
.unit-website-research__error > span { flex: 1; }
.unit-website-research__error button { font-weight: 700; text-decoration: underline; }
.unit-website-research__records { display: flex; flex-direction: column; gap: 7px; }
.unit-website-research__record { min-width: 0; border: 1px solid #d9d7dc; border-radius: 0; overflow: hidden; }
.unit-website-research__record-heading { display: flex; align-items: center; padding-right: 8px; background: #fafafa; }
.unit-website-research__toggle { display: flex; flex: 1; min-width: 0; align-items: center; gap: 12px; padding: 10px; text-align: left; }
.unit-website-research__toggle:hover { background: #f3f2f4; }
.unit-website-research__chevron { color: #77727b; flex: 0 0 auto; }
.unit-website-research__site { display: flex; flex-direction: column; gap: 3px; min-width: 0; flex: 1; }
.unit-website-research__site strong { color: #352345; font-size: 12px; font-weight: 700; overflow-wrap: anywhere; }
.unit-website-research__site > span { color: #77727b; font-size: 10px; }
.unit-website-research__product-count { flex: 0 0 auto; color: #77727b; font-size: 10px; }
.unit-website-research__partial { padding: 3px 6px; border-radius: 0; background: #f3f1f4; color: #651c2e; font-size: 9px; }
.unit-website-research__details { padding: 14px; border-top: 1px solid #d9d7dc; }
.unit-website-research__summary { margin: 0 0 12px; color: #242127; font-size: 12px; line-height: 1.7; white-space: pre-line; overflow-wrap: anywhere; }
.unit-website-research__warnings { display: flex; flex-direction: column; gap: 5px; margin-bottom: 12px; padding: 9px 10px; border: 1px solid #d9d7dc; border-radius: 0; background: #f7f6f8; color: #651c2e; font-size: 10px; line-height: 1.6; }
.unit-website-research__warnings p { margin: 0; overflow-wrap: anywhere; }
.unit-website-research__products { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; max-height: 460px; overflow: auto; overscroll-behavior: contain; scrollbar-width: thin; }
.unit-website-research__product { display: flex; flex-direction: column; align-items: flex-start; gap: 7px; padding: 12px; border: 1px solid #d9d7dc; border-radius: 0; background: #ffffff; overflow-wrap: anywhere; }
.unit-website-research__product > strong { color: #352345; font-size: 12px; line-height: 1.5; }
.unit-website-research__product > p { margin: 0; color: #77727b; font-size: 11px; line-height: 1.6; white-space: pre-line; }
.unit-website-research a { color: #876273; font-size: 10px; text-decoration: none; overflow-wrap: anywhere; }
.unit-website-research a:hover { text-decoration: underline; }
.unit-website-research__evidence { width: 100%; color: #82968b; font-size: 10px; line-height: 1.6; }
.unit-website-research summary { cursor: pointer; }
.unit-website-research__evidence blockquote { margin: 7px 0 0; padding-left: 9px; border-left: 2px solid #d2e0d8; white-space: pre-line; }
.unit-website-research__no-products { margin: 0 0 10px; color: #81958b; font-size: 11px; }
.unit-website-research__sources { margin-top: 12px; border-top: 1px solid #e6ece8; padding-top: 10px; color: #667f71; font-size: 11px; }
.unit-website-research__sources summary span { margin-left: 5px; color: #9aac9f; font-size: 10px; }
.unit-website-research__sources ul { display: flex; flex-direction: column; gap: 6px; margin: 9px 0 0; padding-left: 17px; }
.unit-website-research__sources li { overflow-wrap: anywhere; }
.unit-website-research__saved { margin: 12px 0 0; color: #9bac9f; font-size: 9px; }
.unit-website-research__pagination { display: flex; align-items: center; justify-content: space-between; margin-top: 10px; color: #80958a; font-size: 10px; }
.unit-website-research__pagination > div { display: flex; align-items: center; gap: 9px; }
@media (max-width: 650px) {
    .unit-website-research__products { grid-template-columns: 1fr; }
    .unit-website-research__toggle { flex-wrap: wrap; gap: 6px; }
    .unit-website-research__site { flex-basis: calc(100% - 27px); }
    .unit-website-research__product-count { margin-left: auto; }
    .unit-website-research__partial { margin-left: 25px; }
    .unit-website-research__details { padding: 11px; }
}
</style>
