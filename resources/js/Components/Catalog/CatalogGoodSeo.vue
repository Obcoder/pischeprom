<script setup>
import axios from 'axios'
import { computed, onScopeDispose, reactive, ref, watch } from 'vue'
import { usePublicGoodUrl } from '../../Composables/usePublicGoodUrl.js'
import { safeGalleryUrl } from './gallery.js'

const props = defineProps({
    good: { type: Object, required: true },
    active: { type: Boolean, default: true },
    disabled: { type: Boolean, default: false },
})
const emit = defineEmits(['state', 'saved'])
const { goodPublicUrl } = usePublicGoodUrl()
const stringFields = ['meta_title', 'meta_description', 'h1', 'slug_override', 'canonical_url', 'og_title', 'og_description', 'og_image',
    'twitter_title', 'twitter_description', 'twitter_image', 'short_seo_text', 'seo_text', 'focus_keyword', 'breadcrumbs_title',
    'yandex_direct_title_1', 'yandex_direct_title_2', 'yandex_direct_text', 'utm_template', 'min_order', 'delivery_note', 'payment_note']
const listFields = ['semantic_core', 'keywords', 'search_queries']
const defaults = () => ({ ...Object.fromEntries(stringFields.map(key => [key, ''])),
    semantic_core_text: '', keywords_text: '', search_queries_text: '', structured_data_text: '', faq_text: '',
    robots: 'index,follow', availability_status: 'on_request', is_active: true, include_in_sitemap: true, include_in_yandex_feed: true })
const form = reactive(defaults())
const ready = ref(false)
const loading = ref(false)
const saving = ref(false)
const generating = ref(false)
const aiGenerating = ref(false)
const baseline = ref('')
const error = ref('')
const message = ref('')
const validationErrors = ref({})
const aiAvailability = ref(null)
const aiDialog = ref(false)
const aiField = ref('h1')
const aiValue = ref('')
const aiError = ref('')
const directLoading = ref(false)
const directActionLoading = ref(false)
const directAdId = ref(null)
const directStatus = ref(null)
const directStats = ref({})
const directError = ref('')
const directMessage = ref('')
let identityVersion = 0
let readVersion = 0
let aiVersion = 0
let readController = null
let directController = null
let aiController = null
let disposed = false
let savedFaq = []

const dirty = computed(() => ready.value && JSON.stringify(form) !== baseline.value)
const busy = computed(() => loading.value || saving.value || generating.value || aiGenerating.value || directActionLoading.value)
const controlsDisabled = computed(() => props.disabled || busy.value || !ready.value)
const directDisabled = computed(() => controlsDisabled.value || directLoading.value)
const primaryFields = [
    { key: 'h1', label: 'H1', hint: 'Заголовок страницы', max: 255 },
    { key: 'meta_title', label: 'Title', hint: 'Заголовок в поиске · 50–70 символов', max: 255 },
    { key: 'meta_description', label: 'Description', hint: 'Описание в поиске · 120–160 символов', rows: 2 },
]
const textFields = [{ key: 'short_seo_text', label: 'Краткий текст', rows: 2 }, { key: 'seo_text', label: 'Полный текст', rows: 4 }]
const aiLabel = computed(() => [...primaryFields, ...textFields].find(field => field.key === aiField.value)?.label || 'Текст')
const robotsOptions = ['index,follow', 'noindex,follow', 'index,nofollow', 'noindex,nofollow']
const availabilityOptions = [{ value: 'in_stock', label: 'В наличии' }, { value: 'on_request', label: 'По запросу' }, { value: 'preorder', label: 'Под заказ' }, { value: 'out_of_stock', label: 'Нет в наличии' }]
const directFields = [{ key: 'yandex_direct_title_1', label: 'Заголовок 1', limit: 56 }, { key: 'yandex_direct_title_2', label: 'Заголовок 2', limit: 30 }, { key: 'yandex_direct_text', label: 'Объявление', limit: 81, rows: 2 }]
const directLimitErrors = computed(() => directFields.filter(field => form[field.key].length > field.limit).map(field => `${field.label}: ${form[field.key].length}/${field.limit}`))
const previewUrl = computed(() => goodPublicUrl({ ...props.good, slug: form.is_active && form.slug_override.trim() ? form.slug_override.trim() : props.good.slug }))
const previewTitle = computed(() => form.meta_title.trim() || `${props.good.name || 'Товар'} купить оптом для пищевой промышленности`)
const previewDescription = computed(() => String(form.meta_description.trim() || props.good.description || `${props.good.name || 'Товар'}: оптовые поставки для пищевой промышленности, HoReCa, производств и дистрибьюторов.`).replace(/<[^>]*>/g, '').trim().slice(0, 160))
const indexingLabel = computed(() => !props.good.is_published ? 'Товар не опубликован' : !form.is_active ? 'SEO отключено' : form.robots.startsWith('noindex') ? 'Индексация запрещена' : 'Индексация разрешена')
const completeness = computed(() => [...primaryFields, ...textFields].filter(field => form[field.key].trim()).length)
const lines = value => String(value || '').split('\n').map(line => line.trim()).filter(Boolean)
const errorFor = key => (validationErrors.value[key] || []).join(' ')

function fill(data = {}) {
    Object.assign(form, defaults())
    for (const key of stringFields) form[key] = data[key] == null ? '' : String(data[key])
    for (const key of listFields) form[`${key}_text`] = Array.isArray(data[key]) ? data[key].join('\n') : ''
    form.og_image ||= props.good.ava_thumb || props.good.thumbnail_url || ''
    form.twitter_image ||= props.good.ava_thumb || props.good.thumbnail_url || ''
    form.robots = data.robots || 'index,follow'
    form.availability_status = data.availability_status || 'on_request'
    for (const key of ['is_active', 'include_in_sitemap', 'include_in_yandex_feed']) form[key] = data[key] ?? true
    form.structured_data_text = data.structured_data ? JSON.stringify(data.structured_data, null, 2) : ''
    savedFaq = JSON.parse(JSON.stringify(Array.isArray(data.faq) ? data.faq : []))
    form.faq_text = savedFaq.map(item => `${item.question || ''} | ${item.answer || ''}`).join('\n')
}
function payload() {
    let structured_data = null
    if (form.structured_data_text.trim()) {
        try { structured_data = JSON.parse(form.structured_data_text) }
        catch { throw new Error('В микроразметке JSON-LD некорректный JSON.') }
        if (!structured_data || typeof structured_data !== 'object') throw new Error('Микроразметка JSON-LD должна быть объектом или массивом JSON.')
    }
    const originalFaqText = baseline.value ? JSON.parse(baseline.value).faq_text : null
    const faq = form.faq_text === originalFaqText ? JSON.parse(JSON.stringify(savedFaq)) : lines(form.faq_text).map((line, index) => {
        const [question, ...answer] = line.split('|').map(part => part.trim())
        if (!question || !answer.join(' | ').trim()) throw new Error(`FAQ, строка ${index + 1}: укажите вопрос и ответ через |.`)
        return { question, answer: answer.join(' | ') }
    })
    return { ...Object.fromEntries(stringFields.map(key => [key, form[key]])),
        ...Object.fromEntries(listFields.map(key => [key, lines(form[`${key}_text`])])), structured_data, faq,
        robots: form.robots, availability_status: form.availability_status, is_active: form.is_active,
        include_in_sitemap: form.include_in_sitemap, include_in_yandex_feed: form.include_in_yandex_feed }
}
function failureMessage(failure, fallback) {
    if (failure.response?.status === 401) return 'Войдите в систему, чтобы продолжить.'
    if (failure.response?.status === 403) return 'Недостаточно прав для этого действия.'
    const details = Object.values(failure.response?.data?.errors || {}).flat().filter(Boolean)
    return details.length ? details.join(' ') : failure.response?.data?.message || (failure.response ? fallback : failure.message) || fallback
}
function cancelReads() {
    readVersion++
    readController?.abort(); directController?.abort()
    readController = null; directController = null
    loading.value = false; directLoading.value = false
}
function cancelAi() {
    aiVersion++
    aiController?.abort(); aiController = null
    aiGenerating.value = false; aiDialog.value = false
}
async function load() {
    if (!props.good?.id || !props.active || ready.value || loading.value) return ready.value
    const version = ++readVersion
    const identity = identityVersion
    const current = new AbortController()
    readController = current; loading.value = true; error.value = ''
    try {
        const { data } = await axios.get(`/api/goods/${props.good.id}/seo`, { signal: current.signal })
        if (disposed || identity !== identityVersion || version !== readVersion || current.signal.aborted) return false
        if (!data || typeof data !== 'object' || Array.isArray(data)) throw new Error('Сервер вернул неполные SEO-данные.')
        fill(data); aiAvailability.value = data.ai_generation || null
        baseline.value = JSON.stringify(form); ready.value = true
        return true
    } catch (failure) {
        if (!disposed && identity === identityVersion && version === readVersion && !current.signal.aborted) error.value = failureMessage(failure, 'Не удалось загрузить SEO. Повторите загрузку.')
        return false
    } finally {
        if (identity === identityVersion && version === readVersion) { loading.value = false; readController = null }
    }
}
function validate() {
    error.value = ''; validationErrors.value = {}
    if (!ready.value) { error.value = 'Сначала дождитесь загрузки SEO.'; return false }
    try { payload(); return true }
    catch (failure) { error.value = failure.message; return false }
}
async function save() {
    if (saving.value || generating.value || aiGenerating.value || !validate()) return false
    if (!dirty.value) return true
    const identity = identityVersion
    const goodId = props.good.id
    saving.value = true; message.value = ''
    try {
        const { data } = await axios.put(`/api/goods/${goodId}/seo`, payload())
        if (disposed || identity !== identityVersion) return false
        fill(data); baseline.value = JSON.stringify(form)
        message.value = 'SEO сохранено.'; emit('saved', data)
        return true
    } catch (failure) {
        if (!disposed && identity === identityVersion) {
            validationErrors.value = failure.response?.data?.errors || {}
            error.value = failureMessage(failure, 'Не удалось сохранить SEO.')
        }
        return false
    } finally { if (identity === identityVersion) saving.value = false }
}
function reset() {
    if (saving.value || generating.value || directActionLoading.value) return
    cancelAi()
    if (baseline.value) Object.assign(form, JSON.parse(baseline.value))
    error.value = ''; message.value = ''; validationErrors.value = {}; aiError.value = ''
}
async function generateJsonLd() {
    if (controlsDisabled.value) return
    const savedJson = baseline.value ? JSON.parse(baseline.value).structured_data_text : ''
    if (form.structured_data_text !== savedJson
        && !globalThis.confirm?.('Заменить несохранённую микроразметку новой разметкой Product? Текущие изменения этого поля будут потеряны.')) return
    const identity = identityVersion
    generating.value = true; error.value = ''; message.value = ''
    try {
        const { data } = await axios.post(`/api/goods/${props.good.id}/seo/generate-structured-data`)
        if (disposed || identity !== identityVersion) return
        form.structured_data_text = data.structured_data ? JSON.stringify(data.structured_data, null, 2) : ''
        const saved = JSON.parse(baseline.value)
        saved.structured_data_text = form.structured_data_text
        baseline.value = JSON.stringify(saved)
        message.value = 'Микроразметка создана и сохранена по сохранённым данным товара.'
        emit('saved', data)
    } catch (failure) { if (identity === identityVersion) error.value = failureMessage(failure, 'Не удалось создать микроразметку.') }
    finally { if (identity === identityVersion) generating.value = false }
}
function aiContext() {
    const limits = { focus_keyword: 255, h1: 255, meta_title: 255, meta_description: 2000, short_seo_text: 5000, seo_text: 12000, min_order: 255, delivery_note: 2000, payment_note: 2000 }
    return { ...Object.fromEntries(Object.entries(limits).map(([key, limit]) => [key, form[key].slice(0, limit)])),
        ...Object.fromEntries(listFields.map(key => [key, lines(form[`${key}_text`]).slice(0, 40).map(value => value.slice(0, 255))])) }
}
async function requestAi(field = aiField.value) {
    if (!props.active || controlsDisabled.value || aiAvailability.value?.available === false) return
    const version = ++aiVersion
    const identity = identityVersion
    const current = new AbortController()
    aiController = current; aiGenerating.value = true; aiDialog.value = true; aiField.value = field; aiValue.value = ''; aiError.value = ''
    try {
        const { data } = await axios.post(`/api/goods/${props.good.id}/seo/generate-ai`, { field, context: aiContext() }, { signal: current.signal, timeout: 90000 })
        if (disposed || identity !== identityVersion || version !== aiVersion || !props.active) return
        if (data.field !== field || typeof data.value !== 'string' || !data.value.trim()) throw new Error('AI вернул пустой или некорректный текст.')
        aiValue.value = data.value
    } catch (failure) {
        if (!disposed && identity === identityVersion && version === aiVersion && !current.signal.aborted) aiError.value = failureMessage(failure, 'Не удалось получить текст от AI.')
    } finally { if (identity === identityVersion && version === aiVersion) { aiGenerating.value = false; aiController = null } }
}
function applyAi() {
    if (!props.active || props.disabled || aiGenerating.value || aiError.value || !aiValue.value.trim()) return
    form[aiField.value] = aiValue.value.trim(); aiDialog.value = false
    message.value = 'AI-текст вставлен. Проверьте его перед сохранением.'
}
function generateDirectFields() {
    if (controlsDisabled.value) return
    const compact = (value, limit) => String(value || '').replace(/\s+/g, ' ').trim().slice(0, limit).trim()
    form.yandex_direct_title_1 ||= compact(props.good.name, 56)
    form.yandex_direct_title_2 ||= 'Опт и розница'
    form.yandex_direct_text ||= compact(form.short_seo_text || props.good.description || 'Поставки для пищевой промышленности. Опт и розница.', 81)
    form.utm_template ||= 'utm_source=yandex&utm_medium=cpc&utm_campaign=direct_goods&utm_content={ad_id}&utm_term={keyword}&utm_campaign_id={campaign_id}&utm_device={device_type}'
    directMessage.value = 'Поля заполнены по шаблону.'; directError.value = ''
}
function checkDirectLimits() {
    directError.value = directLimitErrors.value.length ? `Превышены лимиты: ${directLimitErrors.value.join('; ')}` : ''
    directMessage.value = directError.value ? '' : 'Лимиты символов соблюдены.'
    return !directError.value
}
async function loadDirectInfo() {
    if (!props.good.id || !props.active || directLoading.value) return
    const identity = identityVersion
    const current = new AbortController()
    directController = current; directLoading.value = true
    try {
        const { data } = await axios.get('/api/marketing/direct/goods', { params: { good_id: props.good.id, per_page: 1 }, signal: current.signal })
        if (disposed || identity !== identityVersion || current.signal.aborted) return
        const item = (data.data || []).find(row => Number(row.id) === Number(props.good.id))
        directAdId.value = item?.direct_ad_id || null; directStatus.value = item?.direct_status || null; directStats.value = item?.stats || {}
    } catch (failure) {
        if (!disposed && identity === identityVersion && !current.signal.aborted) directError.value = failureMessage(failure, 'Статистика Директа недоступна.')
    } finally { if (directController === current) { directLoading.value = false; directController = null } }
}
async function runDirect(action) {
    if (!props.active || directDisabled.value) return false
    if (['draft', 'send', 'dry-run', 'launch'].includes(action) && !checkDirectLimits()) return false
    if (action === 'launch' && !globalThis.confirm?.('Запустить рекламу в Яндекс.Директе? Будут созданы кампания, группы, объявления и ключи с бюджетом из настроек Директа.')) return false
    const identity = identityVersion
    const goodId = props.good.id
    directActionLoading.value = true; directError.value = ''; directMessage.value = ''
    try {
        if (['draft', 'send', 'dry-run', 'launch'].includes(action) && !await save()) throw new Error(error.value || 'Сначала сохраните SEO.')
        if (disposed || identity !== identityVersion) return false
        if (action === 'draft' || (action === 'send' && !directAdId.value)) {
            const { data } = await axios.post(`/api/marketing/direct/goods/${goodId}/generate-draft`)
            if (identity !== identityVersion || disposed) return false
            directAdId.value = data.id; directStatus.value = data.status
            directMessage.value = 'Рекламный черновик создан.'
        }
        if (action === 'validate') {
            if (!directAdId.value) return checkDirectLimits()
            const { data } = await axios.post(`/api/marketing/direct/ads/${directAdId.value}/validate`)
            if (identity !== identityVersion || disposed) return false
            directStatus.value = data.ad?.status || directStatus.value
            const details = Object.values(data.errors || {}).flat().filter(Boolean)
            directError.value = details.join(' ') || ''
            directMessage.value = directError.value ? '' : 'Черновик прошёл проверку.'
        }
        if (action === 'send') {
            const { data } = await axios.post(`/api/marketing/direct/ads/${directAdId.value}/send`)
            if (identity !== identityVersion || disposed) return false
            directMessage.value = data.message || 'Отправка обработана.'
        }
        if (action === 'dry-run' || action === 'launch') {
            const dryRun = action === 'dry-run'
            const { data } = await axios.post(`/api/marketing/direct/launch/${goodId}`, { dry_run: dryRun, budget_approved: !dryRun })
            if (identity !== identityVersion || disposed) return false
            directStatus.value = data.status || directStatus.value
            directMessage.value = [data.message || (dryRun ? 'Проверка автозапуска выполнена.' : 'Реклама отправлена в Яндекс.'), ...(data.warnings || [])].filter(Boolean).join(' ')
        }
        if (['draft', 'send', 'dry-run', 'launch'].includes(action)) await loadDirectInfo()
        return !directError.value
    } catch (failure) {
        if (identity === identityVersion && !disposed) directError.value = failureMessage(failure, 'Не удалось выполнить действие в Яндекс.Директе.')
        return false
    } finally { if (identity === identityVersion) directActionLoading.value = false }
}

watch(() => props.good?.id, () => {
    identityVersion++; cancelReads(); cancelAi()
    Object.assign(form, defaults()); ready.value = false; baseline.value = ''; savedFaq = []; error.value = ''; message.value = ''; validationErrors.value = {}
    saving.value = false; generating.value = false; directActionLoading.value = false
    aiAvailability.value = null; directAdId.value = null; directStatus.value = null; directStats.value = {}; directError.value = ''; directMessage.value = ''
    if (props.active) { load(); loadDirectInfo() }
}, { immediate: true, flush: 'sync' })
watch(() => props.active, active => { if (active) { load(); loadDirectInfo() } else { cancelReads(); cancelAi() } }, { flush: 'sync' })
watch([dirty, busy, ready, error], () => emit('state', { dirty: dirty.value, busy: busy.value, ready: ready.value, error: error.value }), { immediate: true, flush: 'sync' })
onScopeDispose(() => { disposed = true; identityVersion++; cancelReads(); cancelAi() })
defineExpose({ save, validate, reset, load, dirty, busy, ready, error })
</script>

<template>
    <div class="catalog-good-seo">
        <div class="catalog-good-seo__toolbar">
            <div><strong>SEO и продвижение</strong><span>{{ indexingLabel }} · {{ completeness }}/5 основных полей</span></div>
            <label class="catalog-good-seo__check"><input v-model="form.is_active" type="checkbox" :disabled="controlsDisabled" /> SEO активно</label>
            <v-btn size="small" variant="tonal" color="#695078" prepend-icon="mdi-content-save-outline" :loading="saving" :disabled="controlsDisabled || !dirty" @click="save">Сохранить SEO</v-btn>
        </div>
        <v-progress-linear v-if="loading" indeterminate color="#806592" height="2" />
        <div v-if="error" class="catalog-good-seo__notice is-error" role="alert"><span>{{ error }}</span><v-btn v-if="!ready" size="x-small" variant="text" :loading="loading" @click="load">Повторить</v-btn></div>
        <div v-if="message" class="catalog-good-seo__notice" role="status">{{ message }}</div>
        <div v-if="!good.id" class="catalog-good-seo__notice">Сохраните новый товар, чтобы настроить SEO.</div>
        <div class="catalog-good-seo__grid">
            <section class="catalog-good-seo__section">
                <h3><v-icon icon="mdi-magnify" size="16" /> Поиск и заголовки <span>{{ dirty ? 'Есть изменения' : 'Сохранено' }}</span></h3>
                <table class="catalog-good-seo__table"><tbody>
                    <tr v-for="field in primaryFields" :key="field.key"><th><label :for="`catalog-seo-${field.key}`">{{ field.label }}</label><small>{{ field.hint }}</small></th><td><textarea v-if="field.rows" :id="`catalog-seo-${field.key}`" v-model="form[field.key]" :rows="field.rows" :aria-label="field.label" :disabled="controlsDisabled" /><input v-else :id="`catalog-seo-${field.key}`" v-model="form[field.key]" :maxlength="field.max" :aria-label="field.label" :disabled="controlsDisabled" /><small class="catalog-good-seo__field-note">{{ form[field.key].length }} символов <span class="is-error">{{ errorFor(field.key) }}</span></small></td><td class="catalog-good-seo__ai-cell"><v-btn icon="mdi-auto-fix" size="x-small" variant="text" :aria-label="`AI: ${field.label}`" :loading="aiGenerating && aiField === field.key" :disabled="controlsDisabled || aiAvailability?.available === false" @click="requestAi(field.key)" /></td></tr>
                    <tr><th><label for="catalog-seo-focus">Ключевая фраза</label></th><td colspan="2"><input id="catalog-seo-focus" v-model="form.focus_keyword" maxlength="255" :disabled="controlsDisabled" /></td></tr>
                </tbody></table>
                <div class="catalog-good-seo__snippet"><a :href="safeGalleryUrl(previewUrl) || undefined" target="_blank" rel="noopener noreferrer">{{ previewUrl }}</a><strong>{{ previewTitle }}</strong><p>{{ previewDescription }}</p></div>
            </section>
            <section class="catalog-good-seo__section">
                <h3><v-icon icon="mdi-web" size="16" /> Адрес и индексация</h3>
                <table class="catalog-good-seo__table"><tbody>
                    <tr><th><label for="catalog-seo-slug">SEO-адрес</label></th><td><input id="catalog-seo-slug" v-model="form.slug_override" maxlength="255" :placeholder="good.slug || 'Адрес товара'" :disabled="controlsDisabled" /><small>Альтернативный адрес при активном SEO.</small></td></tr>
                    <tr><th><label for="catalog-seo-canonical">Canonical</label></th><td><input id="catalog-seo-canonical" v-model="form.canonical_url" maxlength="255" :placeholder="previewUrl" :disabled="controlsDisabled" /></td></tr>
                    <tr><th><label for="catalog-seo-breadcrumbs">Хлебные крошки</label></th><td><input id="catalog-seo-breadcrumbs" v-model="form.breadcrumbs_title" maxlength="255" :disabled="controlsDisabled" /></td></tr>
                    <tr><th><label for="catalog-seo-robots">Robots</label></th><td><select id="catalog-seo-robots" v-model="form.robots" :disabled="controlsDisabled"><option v-for="option in robotsOptions" :key="option" :value="option">{{ option }}</option></select></td></tr>
                    <tr><th>Каналы</th><td><div class="catalog-good-seo__checks"><label class="catalog-good-seo__check"><input v-model="form.include_in_sitemap" type="checkbox" :disabled="controlsDisabled" /> Sitemap</label><label class="catalog-good-seo__check"><input v-model="form.include_in_yandex_feed" type="checkbox" :disabled="controlsDisabled" /> Фид Яндекс.Директа</label></div></td></tr>
                </tbody></table>
                <p class="catalog-good-seo__note">{{ aiAvailability?.message || (aiAvailability?.available === false ? 'AI-помощник недоступен.' : 'AI-помощник предлагает тексты. Вставка и сохранение — после вашей проверки.') }}</p>
            </section>
            <section class="catalog-good-seo__section">
                <h3><v-icon icon="mdi-text-box-outline" size="16" /> Тексты карточки</h3>
                <table class="catalog-good-seo__table"><tbody><tr v-for="field in textFields" :key="field.key"><th><label :for="`catalog-seo-${field.key}`">{{ field.label }}</label><small>{{ form[field.key].length }} символов</small></th><td><textarea :id="`catalog-seo-${field.key}`" v-model="form[field.key]" :rows="field.rows" :aria-label="field.label" :disabled="controlsDisabled" /></td><td class="catalog-good-seo__ai-cell"><v-btn icon="mdi-auto-fix" size="x-small" variant="text" :aria-label="`AI: ${field.label}`" :loading="aiGenerating && aiField === field.key" :disabled="controlsDisabled || aiAvailability?.available === false" @click="requestAi(field.key)" /></td></tr></tbody></table>
            </section>
            <section class="catalog-good-seo__section">
                <h3><v-icon icon="mdi-key-outline" size="16" /> Семантика и вопросы <span>По одному значению в строке</span></h3>
                <table class="catalog-good-seo__table"><tbody>
                    <tr v-for="field in [{ key: 'semantic_core_text', label: 'Семантическое ядро' }, { key: 'keywords_text', label: 'Keywords' }, { key: 'search_queries_text', label: 'Поисковые запросы' }]" :key="field.key"><th><label :for="`catalog-seo-${field.key}`">{{ field.label }}</label><small>{{ lines(form[field.key]).length }} фраз</small></th><td><textarea :id="`catalog-seo-${field.key}`" v-model="form[field.key]" rows="2" :aria-label="field.label" :disabled="controlsDisabled" /></td></tr>
                    <tr><th><label for="catalog-seo-faq">FAQ</label><small>Вопрос | ответ</small></th><td><textarea id="catalog-seo-faq" v-model="form.faq_text" rows="2" aria-label="Вопросы и ответы" :disabled="controlsDisabled" /></td></tr>
                </tbody></table>
            </section>
            <section class="catalog-good-seo__section">
                <h3><v-icon icon="mdi-share-variant-outline" size="16" /> Соцсети и мессенджеры</h3>
                <table class="catalog-good-seo__table catalog-good-seo__social"><thead><tr><th>Поле</th><th>Open Graph</th><th>Twitter</th></tr></thead><tbody>
                    <tr><th>Заголовок</th><td><input v-model="form.og_title" aria-label="Заголовок Open Graph" maxlength="255" :disabled="controlsDisabled" /></td><td><input v-model="form.twitter_title" aria-label="Заголовок Twitter" maxlength="255" :disabled="controlsDisabled" /></td></tr>
                    <tr><th>Описание</th><td><textarea v-model="form.og_description" aria-label="Описание Open Graph" rows="2" :disabled="controlsDisabled" /></td><td><textarea v-model="form.twitter_description" aria-label="Описание Twitter" rows="2" :disabled="controlsDisabled" /></td></tr>
                    <tr><th>Изображение</th><td><input v-model="form.og_image" aria-label="Изображение Open Graph" maxlength="255" :disabled="controlsDisabled" /><img v-if="safeGalleryUrl(form.og_image)" :src="safeGalleryUrl(form.og_image)" alt="Open Graph" class="catalog-good-seo__image" /></td><td><input v-model="form.twitter_image" aria-label="Изображение Twitter" maxlength="255" :disabled="controlsDisabled" /><img v-if="safeGalleryUrl(form.twitter_image)" :src="safeGalleryUrl(form.twitter_image)" alt="Twitter" class="catalog-good-seo__image" /></td></tr>
                </tbody></table>
            </section>
            <section class="catalog-good-seo__section">
                <h3><v-icon icon="mdi-truck-outline" size="16" /> Условия предложения</h3>
                <table class="catalog-good-seo__table"><tbody>
                    <tr><th><label for="catalog-seo-availability">Наличие</label></th><td><select id="catalog-seo-availability" v-model="form.availability_status" :disabled="controlsDisabled"><option v-for="option in availabilityOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select></td></tr>
                    <tr><th><label for="catalog-seo-min-order">Мин. партия</label></th><td><input id="catalog-seo-min-order" v-model="form.min_order" maxlength="255" :disabled="controlsDisabled" /></td></tr>
                    <tr><th><label for="catalog-seo-delivery">Доставка</label></th><td><textarea id="catalog-seo-delivery" v-model="form.delivery_note" rows="2" :disabled="controlsDisabled" /></td></tr>
                    <tr><th><label for="catalog-seo-payment">Оплата</label></th><td><textarea id="catalog-seo-payment" v-model="form.payment_note" rows="2" :disabled="controlsDisabled" /></td></tr>
                </tbody></table>
            </section>
            <section class="catalog-good-seo__section">
                <h3><v-icon icon="mdi-code-json" size="16" /> Микроразметка JSON-LD <v-btn size="x-small" variant="text" prepend-icon="mdi-refresh" :loading="generating" :disabled="controlsDisabled" @click="generateJsonLd">Сформировать Product</v-btn></h3>
                <table class="catalog-good-seo__table"><tbody><tr><th><label for="catalog-seo-jsonld">JSON-LD</label><small>Пусто — автоматически</small></th><td><textarea id="catalog-seo-jsonld" v-model="form.structured_data_text" rows="7" class="catalog-good-seo__json" :disabled="controlsDisabled" spellcheck="false" /></td></tr></tbody></table>
                <p class="catalog-good-seo__note">Генерация использует сохранённые данные товара и сразу сохраняет микроразметку.</p>
            </section>
            <section class="catalog-good-seo__section">
                <h3><v-icon icon="mdi-bullhorn-outline" size="16" /> Объявление Яндекс.Директа <span>{{ directStatus || 'Нет черновика' }}</span></h3>
                <table class="catalog-good-seo__table"><tbody>
                    <tr v-for="field in directFields" :key="field.key"><th><label :for="`catalog-seo-${field.key}`">{{ field.label }}</label><small :class="{ 'is-error': form[field.key].length > field.limit }">{{ form[field.key].length }} / {{ field.limit }}</small></th><td><textarea v-if="field.rows" :id="`catalog-seo-${field.key}`" v-model="form[field.key]" :rows="field.rows" :disabled="controlsDisabled" /><input v-else :id="`catalog-seo-${field.key}`" v-model="form[field.key]" :disabled="controlsDisabled" /></td></tr>
                    <tr><th><label for="catalog-seo-utm">UTM</label></th><td><textarea id="catalog-seo-utm" v-model="form.utm_template" rows="2" :disabled="controlsDisabled" /></td></tr>
                </tbody></table>
            </section>
            <section class="catalog-good-seo__section catalog-good-seo__direct">
                <h3>Управление рекламой <span v-if="directAdId">Черновик № {{ directAdId }}</span><v-btn size="x-small" variant="text" prepend-icon="mdi-refresh" :loading="directLoading" :disabled="directActionLoading" @click="loadDirectInfo">Обновить статистику</v-btn></h3>
                <table class="catalog-good-seo__metrics"><thead><tr><th v-for="label in ['Показы', 'Клики', 'CTR', 'Расход', 'Заявки', 'CPL']" :key="label">{{ label }}</th></tr></thead><tbody><tr><td>{{ directStats.impressions || 0 }}</td><td>{{ directStats.clicks || 0 }}</td><td>{{ directStats.ctr ?? '—' }}</td><td>{{ directStats.cost || 0 }}</td><td>{{ directStats.conversions || 0 }}</td><td>{{ directStats.cost_per_conversion ?? '—' }}</td></tr></tbody></table>
                <div class="catalog-good-seo__direct-actions">
                    <v-btn size="x-small" variant="tonal" :disabled="controlsDisabled" @click="generateDirectFields">Заполнить по шаблону</v-btn><v-btn size="x-small" variant="tonal" :disabled="controlsDisabled" @click="checkDirectLimits">Проверить лимиты</v-btn>
                    <v-btn size="x-small" variant="tonal" :disabled="directDisabled || directLimitErrors.length > 0" :loading="directActionLoading" @click="runDirect('draft')">Создать черновик</v-btn><v-btn size="x-small" variant="tonal" :disabled="directDisabled" @click="runDirect('validate')">Проверить черновик</v-btn>
                    <v-btn size="x-small" variant="tonal" :disabled="directDisabled || directLimitErrors.length > 0" title="Отправить в существующую группу Яндекс.Директа" @click="runDirect('send')">Отправить в группу</v-btn><v-btn size="x-small" variant="tonal" :disabled="directDisabled || directLimitErrors.length > 0" title="Без отправки рекламы" @click="runDirect('dry-run')">Проверить автозапуск</v-btn><v-btn size="x-small" variant="tonal" color="error" :disabled="directDisabled || directLimitErrors.length > 0" @click="runDirect('launch')">Запустить рекламу</v-btn><a v-if="directAdId" href="/Ameise/marketing/yandex-direct" target="_blank" rel="noopener noreferrer">Статистика товара</a>
                </div>
                <div v-if="directError" class="catalog-good-seo__notice is-error" role="alert">{{ directError }}</div><div v-if="directMessage" class="catalog-good-seo__notice" role="status">{{ directMessage }}</div>
            </section>
        </div>
        <v-dialog v-model="aiDialog" max-width="760" :persistent="aiGenerating" scrollable><v-card class="catalog-good-seo__ai-dialog"><v-card-title>AI · {{ aiLabel }}</v-card-title><v-card-text><p class="catalog-good-seo__note">Проверьте факты и отредактируйте предложение перед вставкой.</p><v-progress-linear v-if="aiGenerating" indeterminate /><div v-if="aiError" class="catalog-good-seo__notice is-error" role="alert">{{ aiError }}</div><v-textarea v-if="!aiGenerating && !aiError" v-model="aiValue" label="Предложенный текст" variant="outlined" density="compact" :rows="aiField === 'seo_text' ? 10 : 4" :maxlength="['h1', 'meta_title'].includes(aiField) ? 255 : undefined" /><p v-if="form[aiField]" class="catalog-good-seo__current"><strong>Текущее значение:</strong> {{ form[aiField] }}</p></v-card-text><v-card-actions><v-btn size="small" variant="text" @click="cancelAi">Отмена</v-btn><v-spacer /><v-btn size="small" variant="tonal" :disabled="aiGenerating" @click="requestAi()">Другой вариант</v-btn><v-btn size="small" variant="flat" color="#695078" :disabled="aiGenerating || Boolean(aiError) || !aiValue.trim()" @click="applyAi">Вставить</v-btn></v-card-actions></v-card></v-dialog>
    </div>
</template>

<style scoped>
.catalog-good-seo { min-width: 0; color: #5c4c66; }
.catalog-good-seo__toolbar { display: flex; align-items: center; gap: 14px; margin-bottom: 12px; }
.catalog-good-seo__toolbar > div { flex: 1; min-width: 0; }
.catalog-good-seo__toolbar strong { display: block; font-size: 14px; }
.catalog-good-seo__toolbar > div > span { font-size: 10px; color: #94839f; }
.catalog-good-seo__grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; align-items: start; }
.catalog-good-seo__section { min-width: 0; border: 1px solid #e7dfec; border-radius: 8px; overflow: hidden; background: #fff; }
.catalog-good-seo__section h3 { display: flex; align-items: center; flex-wrap: wrap; gap: 7px; min-height: 34px; margin: 0; padding: 6px 10px; font-size: 11px; font-weight: 650; background: #f6f2f9; border-bottom: 1px solid #e7dfec; }
.catalog-good-seo__section h3 > span { margin-left: auto; color: #9985a6; font-size: 10px; font-weight: 400; }
.catalog-good-seo__section h3 > .v-btn { margin-left: auto; }
.catalog-good-seo__table { width: 100%; table-layout: fixed; border-collapse: collapse; }
.catalog-good-seo__table th { width: 108px; padding: 7px 9px; text-align: left; font-size: 10px; font-weight: 550; vertical-align: top; background: #fdfcfe; }
.catalog-good-seo__table td { padding: 5px 7px; vertical-align: top; }
.catalog-good-seo__table tr + tr > * { border-top: 1px solid #eee8f2; }
.catalog-good-seo__table small { display: block; font-size: 9px; font-weight: 400; line-height: 1.4; color: #a18fab; margin-top: 3px; }
.catalog-good-seo__table input:not([type=checkbox]), .catalog-good-seo__table textarea, .catalog-good-seo__table select { display: block; width: 100%; min-width: 0; border: 1px solid #e1d7e9; border-radius: 5px; padding: 5px 7px; font: inherit; font-size: 11px; line-height: 1.45; color: #5a4865; background: #fff; }
.catalog-good-seo__table textarea { resize: vertical; }
.catalog-good-seo__table input:focus, .catalog-good-seo__table textarea:focus, .catalog-good-seo__table select:focus { outline: 1px solid #9d80af; outline-offset: 1px; }
.catalog-good-seo__table :disabled { opacity: .62; background: #faf8fc; }
.catalog-good-seo__ai-cell { width: 34px; padding: 5px 2px !important; }
.catalog-good-seo__checks { display: flex; gap: 8px 14px; flex-wrap: wrap; }
.catalog-good-seo__check { display: inline-flex; align-items: center; gap: 6px; font-size: 10px; white-space: nowrap; }
.catalog-good-seo__check input { accent-color: #80608f; width: 13px; height: 13px; }
.catalog-good-seo__snippet { padding: 9px 12px; border-top: 1px solid #eee8f2; font-size: 10px; overflow-wrap: anywhere; background: #fdfcfe; }
.catalog-good-seo__snippet a { color: #7d6a89; font-size: 9px; text-decoration: none; }
.catalog-good-seo__snippet strong { display: block; color: #705285; font-weight: 550; font-size: 12px; margin: 3px 0; }
.catalog-good-seo__snippet p { color: #8d7d97; line-height: 1.5; }
.catalog-good-seo__note { font-size: 10px; color: #9a87a6; line-height: 1.5; padding: 7px 10px; margin: 0; }
.catalog-good-seo__social th { width: 83px; }
.catalog-good-seo__social thead th { width: auto; background: #faf7fc; font-weight: 400; }
.catalog-good-seo__social thead th:first-child { width: 83px; }
.catalog-good-seo__image { width: 44px; height: 32px; object-fit: contain; display: block; margin-top: 4px; background: #f8f5fb; border-radius: 3px; }
.catalog-good-seo__table .catalog-good-seo__json { font-family: ui-monospace, monospace; font-size: 10px; }
.catalog-good-seo__direct { grid-column: 1 / -1; }
.catalog-good-seo__metrics { width: 100%; table-layout: fixed; border-collapse: collapse; text-align: center; }
.catalog-good-seo__metrics th { padding: 7px 5px 2px; color: #9b87a8; font-size: 9px; font-weight: 400; }
.catalog-good-seo__metrics td { padding: 0 5px 7px; color: #695076; font-size: 14px; font-variant-numeric: tabular-nums; }
.catalog-good-seo__direct-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; padding: 8px 10px; border-top: 1px solid #eee8f2; }
.catalog-good-seo__direct-actions a { font-size: 10px; color: #80608f; }
.catalog-good-seo__notice { display: flex; align-items: center; gap: 8px; font-size: 11px; line-height: 1.5; padding: 7px 10px; color: #80608f; background: #f7f2fa; margin-bottom: 8px; border-radius: 5px; overflow-wrap: anywhere; }
.catalog-good-seo__notice.is-error { color: #a14352; background: #fff4f5; }
.catalog-good-seo .is-error { color: #b25566; }
.catalog-good-seo__ai-dialog .v-card-title { white-space: normal; font-size: 16px; }
.catalog-good-seo__current { font-size: 11px; line-height: 1.5; color: #96859f; white-space: pre-line; overflow-wrap: anywhere; }
.catalog-good-seo :deep(.v-btn) { letter-spacing: normal; text-transform: none; }
@media (max-width: 900px) { .catalog-good-seo__grid { grid-template-columns: 1fr; } }
@media (max-width: 600px) {
    .catalog-good-seo__toolbar { flex-wrap: wrap; gap: 8px; }
    .catalog-good-seo__toolbar > div { flex-basis: 100%; }
    .catalog-good-seo__toolbar > .v-btn { margin-left: auto; }
    .catalog-good-seo__table th { width: 85px; padding: 6px; }
    .catalog-good-seo__table td { padding: 5px; }
    .catalog-good-seo__social th, .catalog-good-seo__social thead th:first-child { width: 65px; }
    .catalog-good-seo__direct-actions { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .catalog-good-seo__direct-actions :deep(.v-btn) { max-width: 100%; min-width: 0; height: auto; min-height: 30px; padding: 5px 7px; }
    .catalog-good-seo__direct-actions :deep(.v-btn__content) { white-space: normal; overflow-wrap: anywhere; }
}
</style>
