<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useHead } from '@vueuse/head'
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import axios from 'axios'
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'

defineOptions({
    layout: VerwalterLayout,
})

const tab = ref('connection')
const notice = ref('')
const error = ref('')

const accounts = ref([])
const integrations = ref({})
const categories = ref([])
const accountsLoading = ref(false)
const checkingAccountId = ref(null)
const yandexVerificationCode = ref('')
const exchangingVerificationCode = ref(false)

const goods = ref([])
const goodsTotal = ref(0)
const goodsLoading = ref(false)
const goodsOptions = ref({ page: 1, itemsPerPage: 50 })
const goodsFilters = reactive({
    search: '',
    category_id: null,
    seo_status: null,
    direct_status: null,
    is_published: null,
})

const ads = ref([])
const adsTotal = ref(0)
const adsLoading = ref(false)
const adsOptions = ref({ page: 1, itemsPerPage: 50 })
const adsFilters = reactive({ search: '', status: null })

const stats = ref([])
const statsSummary = ref({})
const statsTotal = ref(0)
const statsLoading = ref(false)
const statsOptions = ref({ page: 1, itemsPerPage: 50 })
const statsFilters = reactive({
    from: '',
    to: '',
    good_id: null,
    campaign_id: null,
    only_clicks: false,
    only_cost: false,
    only_conversions: false,
})

const logs = ref([])
const logsTotal = ref(0)
const logsLoading = ref(false)
const logsOptions = ref({ page: 1, itemsPerPage: 50 })
const logFilters = reactive({ status: null, action: '' })

const adDialog = ref(false)
const previewDialog = ref(false)
const payloadDialog = ref(false)
const selectedAd = ref(null)
const selectedPayload = ref(null)
const payloadLoading = ref(false)
const savingAd = ref(false)
const sendingAdId = ref(null)
const validatingAdId = ref(null)
const generatingGoodId = ref(null)
const launchingGoodId = ref(null)
const launchDashboard = ref({ summary: {}, last_launches: [] })

const adForm = reactive({
    title_1: '',
    title_2: '',
    text: '',
    href: '',
    utm_template: '',
    image_url: '',
    external_campaign_id: '',
    external_ad_group_id: '',
    keyword_text: '',
    minus_keyword_text: '',
})

const statusOptions = ['draft', 'ready', 'syncing', 'sent', 'moderation', 'active', 'suspended', 'error']
const seoStatusOptions = [
    { title: 'SEO заполнено', value: 'filled' },
    { title: 'SEO не заполнено', value: 'empty' },
]
const publishedOptions = [
    { title: 'Опубликован', value: true },
    { title: 'Не опубликован', value: false },
]
const pageSizes = [25, 50, 100, 200]
const limits = { title_1: 56, title_2: 30, text: 81 }

const goodsHeaders = [
    { title: 'ID', key: 'id', width: 64 },
    { title: 'Фото', key: 'ava_thumb', sortable: false, width: 64 },
    { title: 'Название', key: 'name', minWidth: 240 },
    { title: 'Категория', key: 'category.name', minWidth: 150 },
    { title: 'Slug', key: 'slug', minWidth: 160 },
    { title: 'SEO', key: 'seo_status', width: 92 },
    { title: 'Direct', key: 'direct_status', width: 108 },
    { title: 'Launch', key: 'direct_launch_status', width: 108 },
    { title: 'Ключей', key: 'direct_keywords_count', align: 'end', width: 82 },
    { title: 'Title 1', key: 'seo.yandex_direct_title_1', minWidth: 160 },
    { title: 'Title 2', key: 'seo.yandex_direct_title_2', minWidth: 140 },
    { title: 'Текст', key: 'seo.yandex_direct_text', minWidth: 220 },
    { title: 'Показы', key: 'stats.impressions', align: 'end', width: 90 },
    { title: 'Клики', key: 'stats.clicks', align: 'end', width: 80 },
    { title: 'CTR', key: 'stats.ctr', align: 'end', width: 80 },
    { title: 'Расход', key: 'stats.cost', align: 'end', width: 96 },
    { title: 'Заявки', key: 'stats.conversions', align: 'end', width: 84 },
    { title: 'CPL', key: 'stats.cost_per_conversion', align: 'end', width: 90 },
    { title: '', key: 'actions', sortable: false, width: 214 },
]

const adsHeaders = [
    { title: 'ID', key: 'id', width: 64 },
    { title: 'Товар', key: 'good.name', minWidth: 220 },
    { title: 'Статус', key: 'status', width: 108 },
    { title: 'Moderation', key: 'moderation_status', width: 120 },
    { title: 'Title 1', key: 'title_1', minWidth: 160 },
    { title: 'Title 2', key: 'title_2', minWidth: 130 },
    { title: 'Text', key: 'text', minWidth: 240 },
    { title: 'URL', key: 'href', minWidth: 220 },
    { title: 'Ключей', key: 'keywords_count', align: 'end', width: 82 },
    { title: 'Ошибки', key: 'validation_errors', minWidth: 160 },
    { title: 'Обновлено', key: 'updated_at', width: 132 },
    { title: '', key: 'actions', sortable: false, width: 148 },
]

const statsHeaders = [
    { title: 'Дата', key: 'date', width: 112 },
    { title: 'Товар', key: 'good.name', minWidth: 220 },
    { title: 'Кампания', key: 'campaign.name', minWidth: 180 },
    { title: 'Группа', key: 'ad_group.name', minWidth: 180 },
    { title: 'Объявление', key: 'ad.title_1', minWidth: 170 },
    { title: 'Показы', key: 'impressions', align: 'end', width: 90 },
    { title: 'Клики', key: 'clicks', align: 'end', width: 80 },
    { title: 'CTR', key: 'ctr', align: 'end', width: 80 },
    { title: 'CPC', key: 'avg_cpc', align: 'end', width: 90 },
    { title: 'Расход', key: 'cost', align: 'end', width: 96 },
    { title: 'Конверсии', key: 'conversions', align: 'end', width: 104 },
    { title: 'CPL', key: 'cost_per_conversion', align: 'end', width: 90 },
]

const logsHeaders = [
    { title: 'Дата', key: 'created_at', width: 132 },
    { title: 'Аккаунт', key: 'account.name', minWidth: 150 },
    { title: 'Сущность', key: 'entity_type', minWidth: 160 },
    { title: 'Действие', key: 'action', minWidth: 170 },
    { title: 'Статус', key: 'status', width: 100 },
    { title: 'Ошибка', key: 'error_message', minWidth: 220 },
    { title: '', key: 'actions', sortable: false, width: 74 },
]

const selectedAdErrors = computed(() => selectedAd.value?.validation_errors || {})
const title1Count = computed(() => adForm.title_1.length)
const title2Count = computed(() => adForm.title_2.length)
const textCount = computed(() => adForm.text.length)
const adFormInvalid = computed(() => title1Count.value > limits.title_1 || title2Count.value > limits.title_2 || textCount.value > limits.text)
const activeAccount = computed(() => accounts.value.find((item) => item.is_active) || accounts.value[0] || null)

function setNotice(message) {
    notice.value = message
    error.value = ''
    window.setTimeout(() => { notice.value = '' }, 2600)
}

function setError(message) {
    error.value = message
    notice.value = ''
}

function paramsFrom(options, filters = {}) {
    return {
        page: options.page,
        per_page: options.itemsPerPage,
        ...Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== null && value !== '')),
    }
}

function money(value) {
    return new Intl.NumberFormat('ru-RU', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(Number(value || 0))
}

function number(value) {
    return new Intl.NumberFormat('ru-RU').format(Number(value || 0))
}

function short(value, length = 80) {
    const text = String(value || '')
    return text.length > length ? `${text.slice(0, length)}...` : text
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

function statusColor(status) {
    return {
        draft: 'grey',
        ready: 'teal',
        syncing: 'blue',
        sent: 'indigo',
        moderation: 'orange',
        active: 'green',
        suspended: 'blue-grey',
        error: 'red',
        dry_run: 'purple',
        success: 'green',
        failed: 'red',
        partial: 'orange',
        pending: 'grey',
        validating: 'blue',
        building: 'indigo',
        sending: 'deep-purple',
    }[status] || 'grey'
}

function seoFilled(good) {
    return Boolean(good?.seo?.yandex_direct_title_1 && good?.seo?.yandex_direct_text)
}

function keywordLines(ad, negative = false) {
    return (ad?.ad_group?.keywords || ad?.adGroup?.keywords || [])
        .filter((item) => Boolean(item.is_negative) === negative)
        .map((item) => item.phrase)
        .join('\n')
}

function formKeywords() {
    const positive = adForm.keyword_text.split('\n').map((item) => item.trim()).filter(Boolean).map((phrase) => ({ phrase, is_negative: false }))
    const negative = adForm.minus_keyword_text.split('\n').map((item) => item.trim()).filter(Boolean).map((phrase) => ({ phrase, is_negative: true }))
    return [...positive, ...negative]
}

async function loadAccounts() {
    accountsLoading.value = true
    try {
        const { data } = await axios.get('/api/marketing/yandex/accounts')
        accounts.value = data.accounts || []
        integrations.value = data.integrations || {}
    } catch (e) {
        setError(e.response?.data?.message || 'Не удалось загрузить подключения Яндекса.')
    } finally {
        accountsLoading.value = false
    }
}

async function loadCategories() {
    const { data } = await axios.get('/api/categories', { params: { per_page: 500 } })
    categories.value = data.data || []
}

async function checkAccount(account) {
    checkingAccountId.value = account.id
    try {
        await axios.post(`/api/marketing/yandex/accounts/${account.id}/check`)
        await loadAccounts()
        setNotice('Подключение проверено.')
    } catch (e) {
        setError(e.response?.data?.message || 'Не удалось проверить подключение.')
    } finally {
        checkingAccountId.value = null
    }
}

async function disconnectAccount(account) {
    await axios.delete(`/api/marketing/yandex/accounts/${account.id}`)
    await loadAccounts()
    setNotice('Подключение отключено.')
}

function connectYandex() {
    window.location.href = route('api.marketing.yandex.oauth.redirect')
}

function connectYandexByVerificationCode() {
    window.open(route('api.marketing.yandex.oauth.verification-code'), '_blank', 'noopener')
}

async function exchangeVerificationCode() {
    const code = yandexVerificationCode.value.trim()
    if (!code) {
        setError('Вставьте код подтверждения Яндекса.')
        return
    }

    exchangingVerificationCode.value = true
    try {
        const { data } = await axios.post('/api/marketing/yandex/oauth/exchange-code', { code })
        yandexVerificationCode.value = ''
        await loadAccounts()
        setNotice(data.message || 'OAuth-токен Яндекса сохранён.')
    } catch (e) {
        setError(e.response?.data?.message || 'Не удалось обменять код на OAuth-токен.')
    } finally {
        exchangingVerificationCode.value = false
    }
}

async function loadGoods(options = goodsOptions.value) {
    goodsOptions.value = options
    goodsLoading.value = true
    try {
        const { data } = await axios.get('/api/marketing/direct/goods', { params: paramsFrom(options, goodsFilters) })
        goods.value = data.data || []
        goodsTotal.value = data.total || 0
    } catch (e) {
        setError(e.response?.data?.message || 'Не удалось загрузить товары.')
    } finally {
        goodsLoading.value = false
    }
}

async function loadAds(options = adsOptions.value) {
    adsOptions.value = options
    adsLoading.value = true
    try {
        const { data } = await axios.get('/api/marketing/direct/ads', { params: paramsFrom(options, adsFilters) })
        ads.value = data.data || []
        adsTotal.value = data.total || 0
    } catch (e) {
        setError(e.response?.data?.message || 'Не удалось загрузить объявления.')
    } finally {
        adsLoading.value = false
    }
}

async function loadStats(options = statsOptions.value) {
    statsOptions.value = options
    statsLoading.value = true
    try {
        const { data } = await axios.get('/api/marketing/direct/stats', { params: paramsFrom(options, statsFilters) })
        stats.value = data.data || []
        statsSummary.value = data.summary || {}
        statsTotal.value = data.total || 0
    } catch (e) {
        setError(e.response?.data?.message || 'Не удалось загрузить статистику.')
    } finally {
        statsLoading.value = false
    }
}

async function loadLogs(options = logsOptions.value, silent = false) {
    logsOptions.value = options
    logsLoading.value = true
    try {
        const { data } = await axios.get('/api/marketing/direct/logs', { params: paramsFrom(options, logFilters) })
        logs.value = data.data || []
        logsTotal.value = data.total || 0
    } catch (e) {
        if (!silent) {
            setError(e.response?.data?.message || 'Не удалось загрузить логи.')
        }
    } finally {
        logsLoading.value = false
    }
}

async function loadLaunchDashboard() {
    try {
        const { data } = await axios.get('/api/marketing/direct/launch-dashboard')
        launchDashboard.value = data || { summary: {}, last_launches: [] }
    } catch (e) {
        // Dashboard не должен блокировать основную таблицу товаров.
    }
}

async function generateDraft(good) {
    generatingGoodId.value = good.id
    try {
        const { data } = await axios.post(`/api/marketing/direct/goods/${good.id}/generate-draft`)
        selectedAd.value = data
        previewDialog.value = true
        await Promise.all([loadGoods(), loadAds()])
        setNotice('Черновик создан.')
    } catch (e) {
        setError(e.response?.data?.message || 'Не удалось создать черновик.')
    } finally {
        generatingGoodId.value = null
    }
}

async function fullAutoLaunch(good, dryRun = true) {
    if (!dryRun) {
        const confirmed = window.confirm('Запустить реальную отправку в Яндекс.Директ? Будут созданы кампания, группы, объявления и ключи. Бюджет будет взят из guard-настроек Direct.')

        if (!confirmed) {
            return
        }
    }

    launchingGoodId.value = good.id
    try {
        const { data } = await axios.post(`/api/marketing/direct/launch/${good.id}`, {
            dry_run: dryRun,
            budget_approved: !dryRun,
        })
        await Promise.all([loadGoods(), loadAds(), loadLogs(), loadLaunchDashboard()])
        setNotice(data.message || (dryRun ? 'FULL AUTO LAUNCH dry-run выполнен.' : 'FULL AUTO LAUNCH отправлен в Яндекс.'))
    } catch (e) {
        const errors = responseErrorMessages(e)
        setError(errors.length ? errors.join('; ') : (e.response?.data?.message || 'Не удалось выполнить FULL AUTO LAUNCH.'))
        await Promise.all([loadLogs(), loadLaunchDashboard()])
    } finally {
        launchingGoodId.value = null
    }
}

async function openAd(id, mode = 'edit') {
    if (!id) return
    const { data } = await axios.get(`/api/marketing/direct/ads/${id}`)
    selectedAd.value = data

    if (mode === 'preview') {
        previewDialog.value = true
        return
    }

    adForm.title_1 = data.title_1 || ''
    adForm.title_2 = data.title_2 || ''
    adForm.text = data.text || ''
    adForm.href = data.href || ''
    adForm.utm_template = data.utm_template || ''
    adForm.image_url = data.image_url || ''
    adForm.external_campaign_id = data.ad_group?.campaign?.external_campaign_id || data.adGroup?.campaign?.external_campaign_id || ''
    adForm.external_ad_group_id = data.ad_group?.external_ad_group_id || data.adGroup?.external_ad_group_id || ''
    adForm.keyword_text = keywordLines(data, false)
    adForm.minus_keyword_text = keywordLines(data, true)
    adDialog.value = true
}

async function saveAd() {
    if (!selectedAd.value || adFormInvalid.value) return
    savingAd.value = true
    try {
        const { data } = await axios.put(`/api/marketing/direct/ads/${selectedAd.value.id}`, {
            title_1: adForm.title_1,
            title_2: adForm.title_2,
            text: adForm.text,
            href: adForm.href,
            utm_template: adForm.utm_template,
            image_url: adForm.image_url,
            external_campaign_id: adForm.external_campaign_id,
            external_ad_group_id: adForm.external_ad_group_id,
            keywords: formKeywords(),
        })
        selectedAd.value = data
        adDialog.value = false
        await Promise.all([loadAds(), loadGoods()])
        setNotice('Объявление сохранено.')
    } catch (e) {
        setError(e.response?.data?.message || 'Не удалось сохранить объявление.')
    } finally {
        savingAd.value = false
    }
}

async function validateAd(ad) {
    validatingAdId.value = ad.id
    try {
        const { data } = await axios.post(`/api/marketing/direct/ads/${ad.id}/validate`)
        selectedAd.value = data.ad
        await loadAds()
        setNotice(Object.keys(data.errors || {}).length ? 'Есть ошибки валидации.' : 'Лимиты проверены, ошибок нет.')
    } catch (e) {
        setError(e.response?.data?.message || 'Не удалось проверить объявление.')
    } finally {
        validatingAdId.value = null
    }
}

async function sendAd(ad) {
    sendingAdId.value = ad.id
    try {
        const { data } = await axios.post(`/api/marketing/direct/ads/${ad.id}/send`)
        previewDialog.value = false
        adDialog.value = false
        tab.value = 'logs'
        await Promise.all([loadAds(), loadGoods(), loadLogs()])
        setNotice(data.message || 'Отправка обработана.')
    } catch (e) {
        tab.value = 'logs'
        setError(e.response?.data?.message || 'Не удалось отправить объявление.')
        await Promise.all([loadAds(), loadLogs(logsOptions.value, true)])
    } finally {
        sendingAdId.value = null
    }
}

async function suspendAd(ad) {
    await axios.post(`/api/marketing/direct/ads/${ad.id}/suspend`)
    await loadAds()
}

async function resumeAd(ad) {
    await axios.post(`/api/marketing/direct/ads/${ad.id}/resume`)
    await loadAds()
}

async function syncStats() {
    try {
        await axios.post('/api/marketing/direct/stats/sync', statsFilters)
        await loadLogs()
        setNotice('Синхронизация статистики поставлена в очередь.')
    } catch (e) {
        setError(e.response?.data?.message || 'Не удалось поставить синхронизацию в очередь.')
    }
}

async function openPayload(log) {
    payloadDialog.value = true
    payloadLoading.value = true
    selectedPayload.value = { loading: true }

    try {
        const { data } = await axios.get(`/api/marketing/direct/logs/${log.id}`)
        selectedPayload.value = {
            request: data.request_payload,
            response: data.response_payload,
            error: data.error_message,
        }
    } catch (e) {
        selectedPayload.value = {
            error: e.response?.data?.message || 'Не удалось загрузить payload лога.',
        }
    } finally {
        payloadLoading.value = false
    }
}

function refreshCurrentTab() {
    if (tab.value === 'connection') loadAccounts()
    if (tab.value === 'goods') Promise.all([loadGoods(), loadLaunchDashboard()])
    if (tab.value === 'ads') loadAds()
    if (tab.value === 'stats') loadStats()
    if (tab.value === 'logs') loadLogs()
}

onMounted(async () => {
    await Promise.all([loadAccounts(), loadCategories(), loadGoods(), loadAds(), loadStats(), loadLogs(), loadLaunchDashboard()])
})

useHead({ title: 'Яндекс.Директ - Маркетинг Ameise' })
</script>

<template>
    <v-container fluid class="yd-page">
        <div class="yd-topline">
            <div>
                <div class="yd-eyebrow">Ameise marketing</div>
                <h1>Яндекс.Директ</h1>
            </div>
            <div class="yd-topline__actions">
                <Link :href="route('Ameise.settings')" class="yd-link">Настройки</Link>
                <v-btn color="deep-purple-darken-2" size="small" variant="flat" @click="refreshCurrentTab">
                    Обновить
                </v-btn>
            </div>
        </div>

        <v-alert v-if="notice" type="success" variant="tonal" density="compact" class="mb-2">{{ notice }}</v-alert>
        <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-2">{{ error }}</v-alert>

        <v-tabs v-model="tab" class="yd-tabs" density="compact">
            <v-tab value="connection">Подключение</v-tab>
            <v-tab value="goods">Товары</v-tab>
            <v-tab value="ads">Объявления</v-tab>
            <v-tab value="stats">Статистика</v-tab>
            <v-tab value="logs">Логи</v-tab>
        </v-tabs>

        <v-window v-model="tab" class="yd-window">
            <v-window-item value="connection">
                <div class="yd-grid yd-grid--connection">
                    <section class="yd-panel">
                        <div class="yd-panel__head">
                            <strong>Подключение Яндекса</strong>
                            <div class="yd-panel__actions">
                                <v-btn color="deep-purple-darken-2" size="small" variant="flat" @click="connectYandexByVerificationCode">
                                    Получить код Яндекса
                                </v-btn>
                                <v-btn color="deep-purple-darken-2" size="small" variant="text" @click="connectYandex">
                                    Callback
                                </v-btn>
                            </div>
                        </div>

                        <div class="yd-code-flow">
                            <div>
                                <strong>Для приложения «Для доступа к API»</strong>
                                <span>Яндекс не показывает «Платформы» и выдаёт код на странице verification_code. Откройте Яндекс, разрешите доступ, скопируйте код и вставьте его сюда.</span>
                            </div>
                            <div class="yd-code-flow__controls">
                                <v-text-field
                                    v-model="yandexVerificationCode"
                                    label="Код подтверждения Яндекса"
                                    density="compact"
                                    hide-details
                                    clearable
                                    @keyup.enter="exchangeVerificationCode"
                                />
                                <v-btn
                                    color="teal-darken-3"
                                    size="small"
                                    variant="flat"
                                    :loading="exchangingVerificationCode"
                                    @click="exchangeVerificationCode"
                                >
                                    Сохранить токен
                                </v-btn>
                            </div>
                        </div>

                        <v-data-table
                            :headers="[
                                { title: 'ID', key: 'id', width: 64 },
                                { title: 'Название', key: 'name' },
                                { title: 'Логин', key: 'yandex_login' },
                                { title: 'Client Login', key: 'direct_client_login' },
                                { title: 'Metrica', key: 'metrica_counter_id' },
                                { title: 'Токен', key: 'token' },
                                { title: 'Проверка', key: 'last_checked_at' },
                                { title: '', key: 'actions', sortable: false, width: 130 },
                            ]"
                            :items="accounts"
                            :loading="accountsLoading"
                            density="compact"
                            class="yd-table"
                            fixed-header
                            height="360"
                        >
                            <template #item.token="{ item }">
                                <v-chip size="x-small" :color="item.has_access_token ? 'green' : 'red'" variant="tonal">
                                    {{ item.has_access_token ? 'есть' : 'нет' }}
                                </v-chip>
                            </template>
                            <template #item.actions="{ item }">
                                <v-btn icon="mdi-check-decagram-outline" size="x-small" variant="text" :loading="checkingAccountId === item.id" @click="checkAccount(item)" />
                                <v-btn icon="mdi-link-off" size="x-small" variant="text" color="red" @click="disconnectAccount(item)" />
                            </template>
                        </v-data-table>
                    </section>

                    <section class="yd-panel">
                        <div class="yd-panel__head"><strong>Найденные Yandex-интеграции</strong></div>
                        <div class="yd-integrations">
                            <article v-for="(item, key) in integrations" :key="key" class="yd-integration">
                                <div>
                                    <strong>{{ key }}</strong>
                                    <span>{{ item.note }}</span>
                                </div>
                                <v-chip size="x-small" :color="item.found ? 'teal' : 'grey'" variant="tonal">
                                    {{ item.found ? 'найдено' : 'нет' }}
                                </v-chip>
                            </article>
                        </div>
                    </section>
                </div>
            </v-window-item>

            <v-window-item value="goods">
                <section class="yd-panel yd-panel--table">
                    <div class="yd-auto-summary">
                        <div>
                            <span>FULL AUTO</span>
                            <strong>{{ number(launchDashboard.summary?.total) }}</strong>
                        </div>
                        <div>
                            <span>Dry-run</span>
                            <strong>{{ number(launchDashboard.summary?.dry_run) }}</strong>
                        </div>
                        <div>
                            <span>Success</span>
                            <strong>{{ number(launchDashboard.summary?.success) }}</strong>
                        </div>
                        <div>
                            <span>Failed</span>
                            <strong>{{ number(launchDashboard.summary?.failed) }}</strong>
                        </div>
                        <div>
                            <span>Rollback</span>
                            <strong>{{ number(launchDashboard.summary?.rollback_count) }}</strong>
                        </div>
                        <div>
                            <span>Success rate</span>
                            <strong>{{ launchDashboard.summary?.success_rate ?? '-' }}%</strong>
                        </div>
                        <div>
                            <span>Spend</span>
                            <strong>{{ money(launchDashboard.summary?.spend) }}</strong>
                        </div>
                    </div>

                    <div class="yd-filters">
                        <v-text-field v-model="goodsFilters.search" label="Поиск" density="compact" hide-details clearable @keyup.enter="loadGoods({ ...goodsOptions, page: 1 })" />
                        <v-select v-model="goodsFilters.category_id" :items="categories" item-title="name" item-value="id" label="Категория" density="compact" hide-details clearable />
                        <v-select v-model="goodsFilters.seo_status" :items="seoStatusOptions" label="SEO" density="compact" hide-details clearable />
                        <v-select v-model="goodsFilters.direct_status" :items="statusOptions" label="Direct" density="compact" hide-details clearable />
                        <v-select v-model="goodsFilters.is_published" :items="publishedOptions" label="Публикация" density="compact" hide-details clearable />
                        <v-btn color="deep-purple-darken-2" size="small" variant="flat" @click="loadGoods({ ...goodsOptions, page: 1 })">Фильтр</v-btn>
                    </div>

                    <v-data-table-server
                        v-model:items-per-page="goodsOptions.itemsPerPage"
                        :headers="goodsHeaders"
                        :items="goods"
                        :items-length="goodsTotal"
                        :items-per-page-options="pageSizes"
                        :loading="goodsLoading"
                        density="compact"
                        fixed-header
                        height="calc(100vh - 286px)"
                        class="yd-table yd-table--sticky"
                        @update:options="loadGoods"
                    >
                        <template #item.ava_thumb="{ item }">
                            <v-avatar size="28" rounded="0"><v-img :src="item.ava_thumb" cover /></v-avatar>
                        </template>
                        <template #item.name="{ item }">
                            <Link :href="route('Ameise.good.show', item.id)" class="yd-name">{{ item.name }}</Link>
                        </template>
                        <template #item.seo_status="{ item }">
                            <v-chip size="x-small" :color="seoFilled(item) ? 'green' : 'orange'" variant="tonal">
                                {{ seoFilled(item) ? 'filled' : 'empty' }}
                            </v-chip>
                        </template>
                        <template #item.direct_status="{ item }">
                            <v-chip v-if="item.direct_status" size="x-small" :color="statusColor(item.direct_status)" variant="flat">{{ item.direct_status }}</v-chip>
                            <span v-else class="yd-muted">нет</span>
                        </template>
                        <template #item.direct_launch_status="{ item }">
                            <v-chip v-if="item.direct_launch_status" size="x-small" :color="statusColor(item.direct_launch_status)" variant="tonal">{{ item.direct_launch_status }}</v-chip>
                            <span v-else class="yd-muted">нет</span>
                        </template>
                        <template #item.stats.ctr="{ item }">{{ item.stats.ctr ?? '-' }}</template>
                        <template #item.stats.cost="{ item }">{{ money(item.stats.cost) }}</template>
                        <template #item.stats.cost_per_conversion="{ item }">{{ item.stats.cost_per_conversion ? money(item.stats.cost_per_conversion) : '-' }}</template>
                        <template #item.seo.yandex_direct_text="{ item }">{{ short(item.seo?.yandex_direct_text, 90) }}</template>
                        <template #item.actions="{ item }">
                            <div class="yd-actions">
                                <v-btn icon="mdi-file-plus-outline" size="x-small" variant="text" :loading="generatingGoodId === item.id" title="Сгенерировать черновик" @click="generateDraft(item)" />
                                <v-btn icon="mdi-rocket-launch-outline" size="x-small" variant="text" color="deep-purple-darken-2" :loading="launchingGoodId === item.id" title="FULL AUTO dry-run: построить структуру без отправки в Яндекс" @click="fullAutoLaunch(item)" />
                                <v-btn icon="mdi-rocket" size="x-small" variant="text" color="red-darken-2" :loading="launchingGoodId === item.id" title="FULL AUTO REAL: создать кампанию в Яндекс.Директе после подтверждения" @click="fullAutoLaunch(item, false)" />
                                <Link :href="route('Ameise.good.show', item.id)" title="Открыть SEO"><v-icon size="17" icon="mdi-open-in-new" /></Link>
                                <v-btn icon="mdi-eye-outline" size="x-small" variant="text" :disabled="!item.direct_ad_id" title="Предпросмотр" @click="openAd(item.direct_ad_id, 'preview')" />
                                <v-btn icon="mdi-send-outline" size="x-small" variant="text" :disabled="!item.direct_ad_id" :loading="sendingAdId === item.direct_ad_id" title="Отправить в existing Direct AdGroup" @click="sendAd({ id: item.direct_ad_id })" />
                            </div>
                        </template>
                    </v-data-table-server>
                </section>
            </v-window-item>

            <v-window-item value="ads">
                <section class="yd-panel yd-panel--table">
                    <div class="yd-filters">
                        <v-text-field v-model="adsFilters.search" label="Поиск" density="compact" hide-details clearable @keyup.enter="loadAds({ ...adsOptions, page: 1 })" />
                        <v-select v-model="adsFilters.status" :items="statusOptions" label="Статус" density="compact" hide-details clearable />
                        <v-btn color="deep-purple-darken-2" size="small" variant="flat" @click="loadAds({ ...adsOptions, page: 1 })">Фильтр</v-btn>
                    </div>

                    <v-data-table-server
                        v-model:items-per-page="adsOptions.itemsPerPage"
                        :headers="adsHeaders"
                        :items="ads"
                        :items-length="adsTotal"
                        :items-per-page-options="pageSizes"
                        :loading="adsLoading"
                        density="compact"
                        fixed-header
                        height="calc(100vh - 238px)"
                        class="yd-table yd-table--sticky"
                        @update:options="loadAds"
                    >
                        <template #item.good.name="{ item }">
                            <Link :href="route('Ameise.good.show', item.good_id)" class="yd-name">{{ item.good?.name }}</Link>
                        </template>
                        <template #item.status="{ item }"><v-chip size="x-small" :color="statusColor(item.status)" variant="flat">{{ item.status }}</v-chip></template>
                        <template #item.text="{ item }">{{ short(item.text, 100) }}</template>
                        <template #item.href="{ item }"><a :href="item.href" target="_blank" rel="noopener" class="yd-url">{{ short(item.href, 70) }}</a></template>
                        <template #item.keywords_count="{ item }">{{ (item.ad_group?.keywords || []).filter((keyword) => !keyword.is_negative).length }}</template>
                        <template #item.validation_errors="{ item }">
                            <v-chip v-if="item.validation_errors" size="x-small" color="red" variant="tonal">ошибки</v-chip>
                            <span v-else class="yd-muted">ok</span>
                        </template>
                        <template #item.actions="{ item }">
                            <div class="yd-actions">
                                <v-btn icon="mdi-pencil" size="x-small" variant="text" @click="openAd(item.id)" />
                                <v-btn icon="mdi-check-circle-outline" size="x-small" variant="text" :loading="validatingAdId === item.id" @click="validateAd(item)" />
                                <v-btn icon="mdi-send-outline" size="x-small" variant="text" title="Отправить в existing Direct AdGroup" :loading="sendingAdId === item.id" @click="sendAd(item)" />
                                <v-btn v-if="item.status !== 'suspended'" icon="mdi-pause" size="x-small" variant="text" @click="suspendAd(item)" />
                                <v-btn v-else icon="mdi-play" size="x-small" variant="text" @click="resumeAd(item)" />
                            </div>
                        </template>
                    </v-data-table-server>
                </section>
            </v-window-item>

            <v-window-item value="stats">
                <section class="yd-panel yd-panel--table">
                    <div class="yd-summary">
                        <div><span>Расход</span><strong>{{ money(statsSummary.cost) }}</strong></div>
                        <div><span>Клики</span><strong>{{ number(statsSummary.clicks) }}</strong></div>
                        <div><span>Показы</span><strong>{{ number(statsSummary.impressions) }}</strong></div>
                        <div><span>CTR</span><strong>{{ statsSummary.ctr ?? '-' }}</strong></div>
                        <div><span>CPC</span><strong>{{ statsSummary.avg_cpc ? money(statsSummary.avg_cpc) : '-' }}</strong></div>
                        <div><span>Заявки</span><strong>{{ number(statsSummary.conversions) }}</strong></div>
                        <div><span>CPL</span><strong>{{ statsSummary.cpl ? money(statsSummary.cpl) : '-' }}</strong></div>
                    </div>
                    <div class="yd-filters">
                        <v-text-field v-model="statsFilters.from" type="date" label="С" density="compact" hide-details />
                        <v-text-field v-model="statsFilters.to" type="date" label="По" density="compact" hide-details />
                        <v-checkbox v-model="statsFilters.only_clicks" label="С кликами" density="compact" hide-details />
                        <v-checkbox v-model="statsFilters.only_cost" label="С расходом" density="compact" hide-details />
                        <v-checkbox v-model="statsFilters.only_conversions" label="С заявками" density="compact" hide-details />
                        <v-btn color="deep-purple-darken-2" size="small" variant="flat" @click="loadStats({ ...statsOptions, page: 1 })">Фильтр</v-btn>
                        <v-btn color="teal-darken-3" size="small" variant="tonal" @click="syncStats">Sync</v-btn>
                    </div>

                    <v-data-table-server
                        v-model:items-per-page="statsOptions.itemsPerPage"
                        :headers="statsHeaders"
                        :items="stats"
                        :items-length="statsTotal"
                        :items-per-page-options="pageSizes"
                        :loading="statsLoading"
                        density="compact"
                        fixed-header
                        height="calc(100vh - 286px)"
                        class="yd-table yd-table--sticky"
                        @update:options="loadStats"
                    >
                        <template #item.good.name="{ item }"><Link v-if="item.good" :href="route('Ameise.good.show', item.good.id)" class="yd-name">{{ item.good.name }}</Link></template>
                        <template #item.cost="{ item }">{{ money(item.cost) }}</template>
                        <template #item.avg_cpc="{ item }">{{ item.avg_cpc ? money(item.avg_cpc) : '-' }}</template>
                        <template #item.cost_per_conversion="{ item }">{{ item.cost_per_conversion ? money(item.cost_per_conversion) : '-' }}</template>
                    </v-data-table-server>
                </section>
            </v-window-item>

            <v-window-item value="logs">
                <section class="yd-panel yd-panel--table">
                    <div class="yd-filters">
                        <v-text-field v-model="logFilters.action" label="Action" density="compact" hide-details clearable @keyup.enter="loadLogs({ ...logsOptions, page: 1 })" />
                        <v-select v-model="logFilters.status" :items="['request', 'success', 'error', 'dry_run', 'prepared', 'failed', 'partial']" label="Статус" density="compact" hide-details clearable />
                        <v-btn color="deep-purple-darken-2" size="small" variant="flat" @click="loadLogs({ ...logsOptions, page: 1 })">Фильтр</v-btn>
                    </div>

                    <v-data-table-server
                        v-model:items-per-page="logsOptions.itemsPerPage"
                        :headers="logsHeaders"
                        :items="logs"
                        :items-length="logsTotal"
                        :items-per-page-options="pageSizes"
                        :loading="logsLoading"
                        density="compact"
                        fixed-header
                        height="calc(100vh - 238px)"
                        class="yd-table yd-table--sticky"
                        @update:options="loadLogs"
                    >
                        <template #item.status="{ item }"><v-chip size="x-small" :color="statusColor(item.status)" variant="flat">{{ item.status }}</v-chip></template>
                        <template #item.error_message="{ item }">{{ short(item.error_message, 110) }}</template>
                        <template #item.actions="{ item }"><v-btn icon="mdi-code-json" size="x-small" variant="text" @click="openPayload(item)" /></template>
                    </v-data-table-server>
                </section>
            </v-window-item>
        </v-window>

        <v-dialog v-model="adDialog" max-width="980">
            <v-card class="yd-dialog">
                <v-card-title>Редактировать объявление #{{ selectedAd?.id }}</v-card-title>
                <v-card-text>
                    <v-row dense>
                        <v-col cols="12" md="6">
                            <v-text-field v-model="adForm.title_1" label="Title 1" density="compact" :error="title1Count > limits.title_1" :hint="`${title1Count}/${limits.title_1}`" persistent-hint />
                        </v-col>
                        <v-col cols="12" md="6">
                            <v-text-field v-model="adForm.title_2" label="Title 2" density="compact" :error="title2Count > limits.title_2" :hint="`${title2Count}/${limits.title_2}`" persistent-hint />
                        </v-col>
                        <v-col cols="12">
                            <v-textarea v-model="adForm.text" label="Text" density="compact" rows="3" :error="textCount > limits.text" :hint="`${textCount}/${limits.text}`" persistent-hint />
                        </v-col>
                        <v-col cols="12">
                            <v-text-field v-model="adForm.href" label="URL" density="compact" />
                        </v-col>
                        <v-col cols="12" md="6">
                            <v-text-field v-model="adForm.external_campaign_id" label="Direct Campaign ID" density="compact" hint="Необязательно. Для связи с существующей кампанией в Яндекс.Директе." persistent-hint />
                        </v-col>
                        <v-col cols="12" md="6">
                            <v-text-field v-model="adForm.external_ad_group_id" label="Direct AdGroup ID" density="compact" hint="Обязательно для реальной отправки ads.add в MVP." persistent-hint />
                        </v-col>
                        <v-col cols="12" md="6">
                            <v-textarea v-model="adForm.utm_template" label="UTM-шаблон" density="compact" rows="3" />
                        </v-col>
                        <v-col cols="12" md="6">
                            <v-textarea v-model="adForm.image_url" label="Image URL" density="compact" rows="3" />
                        </v-col>
                        <v-col cols="12" md="6">
                            <v-textarea v-model="adForm.keyword_text" label="Keywords" density="compact" rows="8" />
                        </v-col>
                        <v-col cols="12" md="6">
                            <v-textarea v-model="adForm.minus_keyword_text" label="Minus keywords" density="compact" rows="8" />
                        </v-col>
                    </v-row>
                    <v-alert v-if="adFormInvalid" type="error" density="compact" variant="tonal">Превышены лимиты символов. Отправка недоступна.</v-alert>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="adDialog = false">Закрыть</v-btn>
                    <v-btn color="deep-purple-darken-2" variant="flat" :disabled="adFormInvalid" :loading="savingAd" @click="saveAd">Сохранить</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="previewDialog" max-width="760">
            <v-card class="yd-dialog">
                <v-card-title>Предпросмотр объявления</v-card-title>
                <v-card-text>
                    <v-alert type="info" density="compact" variant="tonal" class="mb-3">
                        После отправки откроется вкладка «Логи» с результатом. Для реальной отправки нужен YANDEX_DIRECT_ENABLE_REAL_SEND=true и Direct AdGroup ID в объявлении.
                    </v-alert>
                    <div class="yd-preview">
                        <img v-if="selectedAd?.image_url" :src="selectedAd.image_url" alt="" />
                        <div>
                            <h3>{{ selectedAd?.title_1 }}</h3>
                            <strong>{{ selectedAd?.title_2 }}</strong>
                            <p>{{ selectedAd?.text }}</p>
                            <a :href="selectedAd?.href" target="_blank" rel="noopener">{{ selectedAd?.href }}</a>
                        </div>
                    </div>
                    <pre v-if="Object.keys(selectedAdErrors).length" class="yd-json">{{ selectedAdErrors }}</pre>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="previewDialog = false">Закрыть</v-btn>
                    <v-btn v-if="selectedAd" color="deep-purple-darken-2" variant="flat" :loading="sendingAdId === selectedAd.id" @click="sendAd(selectedAd)">Отправить</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="payloadDialog" max-width="900">
            <v-card class="yd-dialog">
                <v-card-title>Payload</v-card-title>
                <v-progress-linear v-if="payloadLoading" indeterminate color="deep-purple-darken-2" />
                <v-card-text><pre class="yd-json">{{ JSON.stringify(selectedPayload, null, 2) }}</pre></v-card-text>
                <v-card-actions><v-spacer /><v-btn variant="text" @click="payloadDialog = false">Закрыть</v-btn></v-card-actions>
            </v-card>
        </v-dialog>
    </v-container>
</template>

<style scoped>
.yd-page {
    min-height: calc(100vh - 56px);
    padding: 10px;
    background: linear-gradient(135deg, #f4f0ff 0%, #fff 42%, #f7f2ff 100%);
    color: #1f1636;
}

.yd-topline {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 8px;
    padding: 10px 12px;
    border: 1px solid rgba(91, 33, 182, 0.16);
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.82);
}

.yd-eyebrow {
    color: rgba(52, 31, 94, 0.56);
    font-size: 9px;
    font-weight: 900;
    letter-spacing: 0.14em;
    text-transform: uppercase;
}

.yd-topline h1 {
    margin: 0;
    color: #2e1065;
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 26px;
    font-weight: 900;
    letter-spacing: -0.04em;
}

.yd-topline__actions,
.yd-actions,
.yd-panel__actions,
.yd-filters {
    display: flex;
    align-items: center;
    gap: 6px;
}

.yd-link,
.yd-name,
.yd-url {
    color: #4c1d95;
    font-weight: 800;
    text-decoration: none;
}

.yd-tabs {
    min-height: 30px;
    border: 1px solid rgba(91, 33, 182, 0.16);
    border-radius: 10px 10px 0 0;
    background: #4c1d95;
    color: #fff;
}

.yd-window {
    border: 1px solid rgba(91, 33, 182, 0.14);
    border-top: 0;
    border-radius: 0 0 12px 12px;
    background: rgba(255, 255, 255, 0.8);
}

.yd-grid {
    display: grid;
    gap: 10px;
    padding: 10px;
}

.yd-grid--connection {
    grid-template-columns: minmax(0, 1.35fr) minmax(320px, 0.65fr);
}

.yd-panel {
    border: 1px solid rgba(91, 33, 182, 0.14);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.9);
    overflow: hidden;
}

.yd-panel--table {
    padding: 8px;
}

.yd-panel__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 8px 10px;
    border-bottom: 1px solid rgba(91, 33, 182, 0.12);
    background: #ede9fe;
    color: #2e1065;
}

.yd-panel__actions {
    flex-wrap: wrap;
    justify-content: flex-end;
}

.yd-code-flow {
    display: grid;
    grid-template-columns: minmax(260px, 0.9fr) minmax(320px, 1.1fr);
    gap: 8px;
    padding: 8px 10px;
    border-bottom: 1px solid rgba(91, 33, 182, 0.12);
    background: #fbfaff;
}

.yd-code-flow strong,
.yd-code-flow span {
    display: block;
}

.yd-code-flow strong {
    color: #2e1065;
    font-size: 12px;
}

.yd-code-flow span {
    color: rgba(46, 16, 101, 0.66);
    font-size: 11px;
    line-height: 1.35;
}

.yd-code-flow__controls {
    display: grid;
    grid-template-columns: minmax(180px, 1fr) auto;
    gap: 6px;
    align-items: center;
}

.yd-filters {
    flex-wrap: wrap;
    margin-bottom: 6px;
}

.yd-filters :deep(.v-field) {
    min-height: 30px;
}

.yd-filters :deep(.v-input) {
    max-width: 220px;
}

.yd-table {
    border: 1px solid rgba(91, 33, 182, 0.12);
    font-size: 11px;
}

.yd-table :deep(th) {
    height: 28px !important;
    background: #5b21b6 !important;
    color: #fff !important;
    font-size: 11px;
    font-weight: 900 !important;
    white-space: nowrap;
}

.yd-table :deep(td) {
    height: 30px !important;
    padding: 2px 6px !important;
    border-bottom: 1px solid #eadcff !important;
    white-space: nowrap;
}

.yd-table :deep(tr:hover td) {
    background: #f5f0ff !important;
}

.yd-table--sticky :deep(th:first-child),
.yd-table--sticky :deep(td:first-child) {
    position: sticky;
    left: 0;
    z-index: 2;
}

.yd-table--sticky :deep(td:first-child) {
    background: #fff;
}

.yd-integrations {
    display: grid;
    gap: 6px;
    padding: 8px;
}

.yd-integration {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 7px 8px;
    border: 1px solid rgba(91, 33, 182, 0.12);
    border-radius: 8px;
    background: #faf7ff;
}

.yd-integration strong,
.yd-integration span {
    display: block;
}

.yd-integration strong {
    color: #2e1065;
    font-size: 12px;
}

.yd-integration span,
.yd-muted {
    color: rgba(46, 16, 101, 0.58);
    font-size: 11px;
}

.yd-summary {
    display: grid;
    grid-template-columns: repeat(7, minmax(86px, 1fr));
    gap: 6px;
    margin-bottom: 6px;
}

.yd-summary div {
    padding: 6px 8px;
    border: 1px solid rgba(91, 33, 182, 0.14);
    border-radius: 8px;
    background: #f4f0ff;
}

.yd-summary span,
.yd-summary strong {
    display: block;
}

.yd-summary span {
    color: rgba(46, 16, 101, 0.6);
    font-size: 10px;
    font-weight: 800;
}

.yd-summary strong {
    color: #2e1065;
    font-size: 16px;
    font-weight: 900;
    text-align: right;
}

.yd-auto-summary {
    display: grid;
    grid-template-columns: repeat(7, minmax(82px, 1fr));
    gap: 6px;
    margin-bottom: 6px;
}

.yd-auto-summary div {
    padding: 5px 7px;
    border: 1px solid rgba(91, 33, 182, 0.14);
    border-radius: 8px;
    background: #f6f0ff;
}

.yd-auto-summary span,
.yd-auto-summary strong {
    display: block;
}

.yd-auto-summary span {
    color: rgba(46, 16, 101, 0.62);
    font-size: 9px;
    font-weight: 900;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.yd-auto-summary strong {
    color: #2e1065;
    font-size: 14px;
    font-weight: 900;
    text-align: right;
}

.yd-dialog {
    border-radius: 12px;
}

.yd-preview {
    display: grid;
    grid-template-columns: 120px minmax(0, 1fr);
    gap: 12px;
}

.yd-preview img {
    width: 120px;
    height: 120px;
    object-fit: cover;
    border-radius: 8px;
}

.yd-preview h3 {
    margin: 0;
    color: #4c1d95;
}

.yd-preview p {
    margin: 6px 0;
}

.yd-json {
    max-height: 520px;
    overflow: auto;
    padding: 10px;
    border-radius: 8px;
    background: #160f2d;
    color: #d8b4fe;
    font-size: 11px;
}

@media (max-width: 1100px) {
    .yd-grid--connection,
    .yd-code-flow,
    .yd-summary {
        grid-template-columns: 1fr;
    }

    .yd-code-flow__controls {
        grid-template-columns: 1fr;
    }
}
</style>
