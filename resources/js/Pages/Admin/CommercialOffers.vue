<script setup>
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'
import axios from 'axios'
import { computed, onMounted, reactive, ref } from 'vue'
import { useHead } from '@vueuse/head'

defineOptions({
    layout: VerwalterLayout,
})

const props = defineProps({
    dashboard: { type: Object, default: () => ({}) },
    settings: { type: Object, default: () => ({}) },
    permissions: { type: Object, default: () => ({}) },
})

useHead({ title: 'Commercial Offers' })

const tabs = [
    ['dashboard', 'dashboard'],
    ['campaigns', 'campaigns'],
    ['contacts', 'recipients'],
    ['sets', 'sets'],
    ['editor', 'mark-up editor'],
    ['templates', 'templates'],
    ['products', 'products'],
    ['events', 'events'],
    ['suppression', 'suppression'],
    ['settings', 'settings'],
]

const mailingVariables = [
    ['{{greeting}}', 'готовое приветствие: “Здравствуйте, Имя.” или “Добрый день!”, если имени нет'],
    ['{{to_name}}', 'имя/фамилия получателя; пусто, если имени нет'],
    ['{{first_name}}', 'поле first_name из Recipients'],
    ['{{last_name}}', 'поле last_name из Recipients'],
    ['{{company_name}}', 'компания получателя'],
    ['{{manager_name}}', 'имя менеджера'],
    ['{{manager_phone}}', 'телефон менеджера'],
    ['{{manager_email}}', 'email менеджера'],
    ['{{campaign_name}}', 'название campaign'],
    ['{{unsubscribe_url}}', 'ссылка отписки, если нужна вручную; обычно Unisender добавляет свой unsubscribe'],
    ['{{offer_items_html}}', 'HTML товаров/категорий из Product picker'],
]

const defaultCampaignHtml = '<h1>{{campaign_name}}</h1><p>{{greeting}}</p>{{offer_items_html}}'
const defaultCampaignPlaintext = '{{greeting}} Коммерческое предложение'
const defaultTemplateHtml = '<table width="100%" role="presentation" cellspacing="0" cellpadding="0"><tr><td><h1>{{campaign_name}}</h1><p>{{greeting}}</p>{{offer_items_html}}</td></tr></table>'
const defaultTemplatePlaintext = '{{greeting}} Коммерческое предложение'

const activeTab = ref('dashboard')
const busy = ref(false)
const notice = ref('')
const error = ref('')
const dashboard = ref(props.dashboard)

const campaigns = ref([])
const contacts = ref([])
const sourceEmails = ref([])
const sets = ref([])
const templates = ref([])
const events = ref([])
const suppression = ref([])
const products = ref([])
const categories = ref([])
const selectedRows = ref(new Set())

const campaignForm = reactive({
    name: '',
    type: 'mass_offer',
    subject: '',
    contact_set_id: '',
    template_id: '',
    html_markup: defaultCampaignHtml,
    plaintext: defaultCampaignPlaintext,
})

const contactForm = reactive({
    email: '',
    first_name: '',
    last_name: '',
    company_name: '',
    position: '',
    consent_status: 'unknown',
    contact_source: '',
    source_url: '',
})

const setForm = reactive({ name: '', description: '', type: 'manual' })
const templateForm = reactive({
    name: '',
    subject: '',
    type: 'commercial_offer',
    html_markup: defaultTemplateHtml,
    plaintext: defaultTemplatePlaintext,
})
const productSearch = ref('')
const productCampaignId = ref('')
const productActiveTab = ref('goods')
const productPublishedFilter = ref('')
const productPerPage = ref(50)
const productMeta = reactive({ current_page: 1, last_page: 1, total: 0, per_page: 50 })
const categoryMeta = reactive({ current_page: 1, last_page: 1, total: 0, per_page: 50 })
const campaignOfferItems = ref([])
const eventStatus = ref('')
const testEmail = ref(props.settings?.test_recipient || '')
const pasteBuffer = ref('')
const sourceEmailSearch = ref('')
const sourceEmailSetId = ref('')
const sourceEmailConsentStatus = ref('unknown')
const selectedSourceEmailIds = ref(new Set())
const recipientDialogOpen = ref(false)
const recipientCampaign = ref(null)
const recipientPickerTab = ref('emails')
const recipientSearch = ref('')
const recipientEmails = ref([])
const recipientUnits = ref([])
const campaignRecipients = ref([])
const selectedCampaignRecipients = ref([])
const imageFile = ref(null)
const imageForm = reactive({
    url: '',
    alt: '',
    link: '',
})

const kpiRows = computed(() => [
    ['drafts', dashboard.value.drafts ?? 0],
    ['scheduled', dashboard.value.scheduled ?? 0],
    ['sending', dashboard.value.sending ?? 0],
    ['completed', dashboard.value.completed ?? 0],
    ['paused_by_system', dashboard.value.paused_by_system ?? 0],
    ['total recipients', dashboard.value.total_recipients ?? 0],
    ['delivered rate', percent(dashboard.value.delivered_rate)],
    ['open rate', percent(dashboard.value.open_rate)],
    ['click rate', percent(dashboard.value.click_rate)],
    ['unsubscribe rate', percent(dashboard.value.unsubscribe_rate)],
    ['hard bounce rate', percent(dashboard.value.hard_bounce_rate)],
    ['spam rate', percent(dashboard.value.spam_rate)],
    ['last webhook', dashboard.value.last_webhook_time || '-'],
    ['api', dashboard.value.unisender_api_status || 'unknown'],
])

const campaignOptions = computed(() => campaigns.value.map((campaign) => ({
    id: campaign.id,
    label: `#${campaign.id} ${campaign.name || campaign.subject || 'campaign'} [${campaign.status}]`,
})))

const templateOptions = computed(() => templates.value.map((template) => ({
    id: template.id,
    label: `#${template.id} ${template.name || template.subject || 'template'} [${template.type || 'template'}]`,
})))

const activeProductMeta = computed(() => productActiveTab.value === 'categories' ? categoryMeta : productMeta)

const selectedRecipientSet = computed(() => new Set(selectedCampaignRecipients.value.map((item) => normalizeEmail(item.email))))

function percent(value) {
    const number = Number(value || 0)
    return `${(number * 100).toFixed(2)}%`
}

function endpoint(path) {
    return `/Ameise/commercial-offers${path}`
}

function normalizeEmail(value) {
    return String(value || '').trim().toLowerCase()
}

async function request(action, callback) {
    busy.value = true
    error.value = ''
    notice.value = ''
    try {
        const result = await callback()
        const responseWarning = result?.data?.response?.warning || result?.data?.warning
        notice.value = responseWarning ? `${action}: ${responseWarning}` : action
        return result
    } catch (err) {
        console.error(err)
        error.value = err?.response?.data?.message || err?.message || 'request failed'
        return null
    } finally {
        busy.value = false
    }
}

async function loadTable(name) {
    const url = {
        campaigns: '/campaigns',
        contacts: '/contacts',
        sets: '/sets',
        templates: '/templates',
        events: `/events${eventStatus.value ? `?status=${eventStatus.value}` : ''}`,
        suppression: '/suppression',
    }[name]

    if (!url) return

    const { data } = await axios.get(endpoint(url))
    const rows = data.data || data
    if (name === 'campaigns') campaigns.value = rows
    if (name === 'contacts') contacts.value = rows
    if (name === 'sets') sets.value = rows
    if (name === 'templates') templates.value = rows
    if (name === 'events') events.value = rows
    if (name === 'suppression') suppression.value = rows
}

async function loadSourceEmails() {
    const { data } = await axios.get(endpoint('/source-emails'), {
        params: {
            q: sourceEmailSearch.value,
            per_page: 100,
        },
    })

    sourceEmails.value = data.data || data
}

async function refreshAll() {
    await request('loaded', async () => {
        await Promise.all([
            ...['campaigns', 'contacts', 'sets', 'templates', 'events', 'suppression'].map(loadTable),
            loadSourceEmails(),
        ])
    })
}

async function selectTab(key) {
    activeTab.value = key

    if (key === 'products' && products.value.length === 0 && categories.value.length === 0) {
        await searchProducts(1)
    }
}

async function createCampaign() {
    await request('campaign created', async () => {
        await axios.post(endpoint('/campaigns'), cleanPayload(campaignForm))
        await loadTable('campaigns')
    })
}

async function campaignAction(id, action, payload = {}) {
    await request(`${action} queued`, async () => {
        await axios.post(endpoint(`/campaigns/${id}/${action}`), payload)
        await loadTable('campaigns')
    })
}

async function changeCampaignTemplate(campaign, templateId) {
    const selectedId = Number(templateId || 0)
    const selected = templates.value.find((template) => Number(template.id) === selectedId)

    if (!selectedId) {
        await request('campaign template cleared', async () => {
            await axios.put(endpoint(`/campaigns/${campaign.id}`), { template_id: null })
            await loadTable('campaigns')
        })
        return
    }

    if (!selected) {
        error.value = 'template not loaded; press refresh or open Templates tab'
        return
    }

    await request(`campaign #${campaign.id} template changed`, async () => {
        await axios.put(endpoint(`/campaigns/${campaign.id}`), {
            template_id: selected.id,
            subject: selected.subject,
            preheader: selected.preheader,
            html_markup: selected.html_markup,
            plaintext: selected.plaintext,
        })
        await loadTable('campaigns')
    })
}

async function openRecipientsDialog(campaign) {
    recipientCampaign.value = campaign
    recipientDialogOpen.value = true
    recipientPickerTab.value = 'emails'
    recipientSearch.value = ''
    await request('recipients loaded', async () => {
        await Promise.all([
            loadCampaignRecipients(),
            loadRecipientPicker(),
        ])
    })
}

async function loadCampaignRecipients() {
    if (!recipientCampaign.value?.id) return
    const { data } = await axios.get(endpoint(`/campaigns/${recipientCampaign.value.id}/recipients`), {
        params: { per_page: 500 },
    })
    campaignRecipients.value = data.data || data
    selectedCampaignRecipients.value = campaignRecipients.value.map((item) => ({
        email: item.email,
        label: item.company_name || item.first_name || item.source_unit_name || item.email,
        source: item.source || 'campaign',
        status: item.status,
        id: item.id,
        locked: item.locked,
    }))
}

async function loadRecipientPicker() {
    if (!recipientCampaign.value?.id) return
    const tab = recipientPickerTab.value === 'units' ? 'units' : 'emails'
    const { data } = await axios.get(endpoint(`/campaigns/${recipientCampaign.value.id}/recipient-picker/${tab}`), {
        params: { q: recipientSearch.value, per_page: tab === 'units' ? 50 : 100 },
    })
    if (tab === 'units') {
        recipientUnits.value = data.data || data
    } else {
        recipientEmails.value = data.data || data
    }
}

function switchRecipientTab(tab) {
    recipientPickerTab.value = tab
    if (tab !== 'selected') {
        loadRecipientPicker()
    }
}

function isRecipientSelected(email) {
    return selectedRecipientSet.value.has(normalizeEmail(email))
}

function addRecipientEmail(email, label = '', source = 'email') {
    const normalized = normalizeEmail(email)
    if (!normalized || isRecipientSelected(normalized)) return

    selectedCampaignRecipients.value = [
        ...selectedCampaignRecipients.value,
        { email: normalized, label: label || normalized, source, status: 'pending', locked: false },
    ]
}

function toggleRecipientEmail(item) {
    const normalized = normalizeEmail(item.address || item.email)
    if (!normalized) return

    if (isRecipientSelected(normalized)) {
        removeSelectedRecipient(normalized)
        return
    }

    addRecipientEmail(normalized, item.name || item.address || normalized, item.source || 'email')
}

function addRecipientUnit(unit) {
    for (const email of unit.emails || []) {
        addRecipientEmail(email.address, email.name || unit.name || email.address, `unit:${unit.name || unit.id}`)
    }
}

function removeSelectedRecipient(email) {
    const normalized = normalizeEmail(email)
    const existing = selectedCampaignRecipients.value.find((item) => normalizeEmail(item.email) === normalized)
    if (existing?.locked) {
        error.value = 'recipient already has send history and cannot be removed'
        return
    }
    selectedCampaignRecipients.value = selectedCampaignRecipients.value.filter((item) => normalizeEmail(item.email) !== normalized)
}

async function saveCampaignRecipients() {
    if (!recipientCampaign.value?.id) return

    await request('campaign recipients saved', async () => {
        const response = await axios.post(endpoint(`/campaigns/${recipientCampaign.value.id}/recipients`), {
            replace: true,
            emails: selectedCampaignRecipients.value.map((item) => item.email),
        })
        campaignRecipients.value = response.data.recipients || []
        selectedCampaignRecipients.value = campaignRecipients.value.map((item) => ({
            email: item.email,
            label: item.company_name || item.first_name || item.source_unit_name || item.email,
            source: item.source || 'campaign',
            status: item.status,
            id: item.id,
            locked: item.locked,
        }))
        await loadTable('campaigns')
        return response
    })
}

async function createContact() {
    await request('contact saved', async () => {
        await axios.post(endpoint('/contacts'), cleanPayload(contactForm))
        Object.assign(contactForm, { email: '', first_name: '', last_name: '', company_name: '', position: '', consent_status: 'unknown', contact_source: '', source_url: '' })
        await loadTable('contacts')
    })
}

async function pasteContacts() {
    const emails = pasteBuffer.value.split(/[\n\t,; ]+/).map((item) => item.trim()).filter(Boolean)
    await request('contacts pasted', async () => {
        for (const email of emails) {
            await axios.post(endpoint('/contacts'), { email, consent_status: 'unknown', contact_source: 'clipboard' })
        }
        pasteBuffer.value = ''
        await loadTable('contacts')
    })
}

async function importSelectedSourceEmails() {
    const emailIds = Array.from(selectedSourceEmailIds.value)
    if (!emailIds.length) {
        error.value = 'select emails from DB'
        return
    }

    await request('emails synced to recipients', async () => {
        await axios.post(endpoint('/contacts/import-existing-emails'), cleanPayload({
            email_ids: emailIds,
            set_id: sourceEmailSetId.value,
            consent_status: sourceEmailConsentStatus.value,
        }))
        selectedSourceEmailIds.value = new Set()
        await Promise.all([loadTable('contacts'), loadSourceEmails(), loadTable('sets')])
    })
}

async function importVisibleSourceEmails() {
    const emailIds = sourceEmails.value.map((item) => item.id)
    if (!emailIds.length) {
        error.value = 'no source emails loaded'
        return
    }

    selectedSourceEmailIds.value = new Set(emailIds)
    await importSelectedSourceEmails()
}

async function createSet() {
    await request('set created', async () => {
        await axios.post(endpoint('/sets'), cleanPayload(setForm))
        Object.assign(setForm, { name: '', description: '', type: 'manual' })
        await loadTable('sets')
    })
}

async function createTemplate() {
    await request('template saved', async () => {
        const response = await axios.post(endpoint('/templates'), cleanPayload(templateForm))
        fillTemplateForm(response.data)
        await loadTable('templates')
        return response
    })
}

async function saveEditorAsTemplate() {
    const name = campaignForm.name || campaignForm.subject || `КП ${new Date().toLocaleString('ru-RU')}`
    const subject = campaignForm.subject || name

    if (!subject.trim() || !campaignForm.html_markup.trim()) {
        error.value = 'template requires subject and html'
        return
    }

    await request('editor saved as template', async () => {
        const response = await axios.post(endpoint('/templates'), cleanPayload({
            name,
            subject,
            type: campaignForm.type === 'personal_offer' ? 'personal_offer' : 'commercial_offer',
            html_markup: campaignForm.html_markup,
            plaintext: campaignForm.plaintext,
        }))
        fillTemplateForm(response.data)
        campaignForm.template_id = response.data.id
        await loadTable('templates')
        return response
    })
}

function fillTemplateForm(template) {
    if (!template) return

    Object.assign(templateForm, {
        name: template.name || templateForm.name,
        subject: template.subject || templateForm.subject,
        type: template.type || templateForm.type,
        html_markup: template.html_markup || templateForm.html_markup,
        plaintext: template.plaintext || templateForm.plaintext,
    })
}

function copyEditorToTemplateForm() {
    Object.assign(templateForm, {
        name: campaignForm.name || templateForm.name,
        subject: campaignForm.subject || templateForm.subject,
        type: campaignForm.type === 'personal_offer' ? 'personal_offer' : 'commercial_offer',
        html_markup: campaignForm.html_markup,
        plaintext: campaignForm.plaintext,
    })
    activeTab.value = 'templates'
}

function applySelectedTemplate() {
    const selected = templates.value.find((template) => Number(template.id) === Number(campaignForm.template_id))
    if (!selected) {
        error.value = 'template not loaded; press refresh or open Templates tab'
        return
    }

    applyTemplateToCampaign(selected)
}

function applyTemplateToCampaign(template) {
    if (!template) return

    campaignForm.template_id = template.id
    campaignForm.subject = template.subject || campaignForm.subject
    campaignForm.html_markup = template.html_markup || campaignForm.html_markup
    campaignForm.plaintext = template.plaintext || campaignForm.plaintext
    if (!campaignForm.name) {
        campaignForm.name = template.name || ''
    }
    if (template.type === 'personal_offer') {
        campaignForm.type = 'personal_offer'
    }

    notice.value = `template loaded: #${template.id}`
    error.value = ''
}

async function syncTemplate(id) {
    await request('template synced', async () => {
        await axios.post(endpoint(`/templates/${id}/sync-unisender`))
        await loadTable('templates')
    })
}

function assignPagination(target, payload = {}) {
    target.current_page = Number(payload.current_page || 1)
    target.last_page = Number(payload.last_page || 1)
    target.total = Number(payload.total || 0)
    target.per_page = Number(payload.per_page || productPerPage.value || 50)
}

async function searchProducts(page = 1) {
    const type = productActiveTab.value === 'categories' ? 'categories' : 'goods'

    await request(`${type} loaded`, async () => {
        const { data } = await axios.get(endpoint('/products/search'), {
            params: {
                type,
                q: productSearch.value,
                published: productPublishedFilter.value,
                per_page: productPerPage.value,
                page,
            },
        })

        if (type === 'categories') {
            const payload = data.categories || { data: [] }
            categories.value = payload.data || []
            assignPagination(categoryMeta, payload)
        } else {
            const payload = data.products || { data: [] }
            products.value = payload.data || []
            assignPagination(productMeta, payload)
        }
    })
}

function switchProductTab(tab) {
    productActiveTab.value = tab
    searchProducts(1)
}

async function loadCampaignOfferItems() {
    const campaignId = Number(productCampaignId.value)
    if (!Number.isInteger(campaignId) || campaignId <= 0) {
        campaignOfferItems.value = []
        return
    }

    const { data } = await axios.get(endpoint(`/campaigns/${campaignId}`))
    campaignOfferItems.value = data.offer_items || data.offerItems || []
}

async function addProduct(product) {
    const campaignId = Number(productCampaignId.value)
    if (!Number.isInteger(campaignId) || campaignId <= 0) {
        error.value = 'select campaign first'
        return
    }
    await request('product added', async () => {
        await axios.post(endpoint(`/campaigns/${campaignId}/offer-items`), { product_id: product.id, item_type: 'product' })
        await Promise.all([loadCampaignOfferItems(), loadTable('campaigns')])
    })
}

async function addCategory(category) {
    const campaignId = Number(productCampaignId.value)
    if (!Number.isInteger(campaignId) || campaignId <= 0) {
        error.value = 'select campaign first'
        return
    }
    await request('category added', async () => {
        await axios.post(endpoint(`/campaigns/${campaignId}/offer-items`), { category_id: category.id, item_type: 'category' })
        await Promise.all([loadCampaignOfferItems(), loadTable('campaigns')])
    })
}

async function deleteOfferItem(item) {
    if (!item?.id) return
    if (!window.confirm(`Remove ${item.title || item.name || 'offer item'} from campaign КП?`)) return

    await request('offer item removed', async () => {
        await axios.delete(endpoint(`/offer-items/${item.id}`))
        await Promise.all([loadCampaignOfferItems(), loadTable('campaigns')])
    })
}

function onImageSelected(event) {
    imageFile.value = event.target.files?.[0] || null
}

function escapeAttr(value) {
    return String(value || '')
        .replaceAll('&', '&amp;')
        .replaceAll('"', '&quot;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
}

function normalizeHttpsUrl(value) {
    const url = String(value || '').trim()
    if (!url) return ''
    if (url.startsWith('//')) return `https:${url}`
    return url
}

function isHttpsUrl(value) {
    return /^https:\/\/[^ ]+/i.test(normalizeHttpsUrl(value))
}

function isEmailImageUrl(value) {
    const url = normalizeHttpsUrl(value)
    if (!isHttpsUrl(url)) return false
    if (/^data:/i.test(url)) return false
    return !/\.(mp4|m4v|mov|webm|avi|mkv|mpeg|mpg|ogv|m3u8|pdf|doc|docx|xls|xlsx|zip|rar)(?:[?#].*)?$/i.test(url)
}

function imageSnippet(url, alt = '', link = '') {
    const safeUrl = escapeAttr(normalizeHttpsUrl(url))
    const safeAlt = escapeAttr(alt || 'Изображение')
    const safeLink = isHttpsUrl(link) ? escapeAttr(normalizeHttpsUrl(link)) : ''
    const image = `<img src="${safeUrl}" alt="${safeAlt}" width="600" style="display:block;width:100%;max-width:600px;height:auto;border:0;border-radius:8px;">`

    return `<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:16px 0;"><tr><td align="center">${safeLink ? `<a href="${safeLink}" target="_blank" style="text-decoration:none;">${image}</a>` : image}</td></tr></table>`
}

function logoSignatureSnippet(url, alt = '', link = '') {
    const safeUrl = escapeAttr(normalizeHttpsUrl(url))
    const safeAlt = escapeAttr(alt || 'Pischeprom')
    const safeLink = isHttpsUrl(link) ? escapeAttr(normalizeHttpsUrl(link)) : ''
    const image = `<img src="${safeUrl}" alt="${safeAlt}" width="72" style="display:block;width:72px;max-width:72px;height:auto;border:0;">`

    return `<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:18px 0 8px;"><tr><td width="84" valign="top">${safeLink ? `<a href="${safeLink}" target="_blank" style="text-decoration:none;">${image}</a>` : image}</td><td valign="top" style="font-family:Arial,sans-serif;font-size:14px;line-height:20px;color:#1d2b1d;"><strong>{{manager_name}}</strong><br><span>{{manager_phone}}</span><br><a href="mailto:{{manager_email}}" style="color:#1b6f1b;">{{manager_email}}</a></td></tr></table>`
}

function appendEditorHtml(snippet) {
    campaignForm.html_markup = `${campaignForm.html_markup.trim()}\n${snippet}`
}

function insertImageFromUrl(mode = 'image') {
    const url = normalizeHttpsUrl(imageForm.url)
    if (!isHttpsUrl(url)) {
        error.value = 'image URL must be absolute HTTPS, for example https://food-server.ru/logo.png'
        return
    }
    if (!isEmailImageUrl(url)) {
        error.value = 'image URL must point to an image, not video/document/archive. Use product page link for video.'
        return
    }

    appendEditorHtml(mode === 'logo'
        ? logoSignatureSnippet(url, imageForm.alt, imageForm.link)
        : imageSnippet(url, imageForm.alt, imageForm.link))
    notice.value = mode === 'logo' ? 'logo signature inserted' : 'image inserted'
    error.value = ''
}

async function uploadAndInsertImage(mode = 'image') {
    if (!imageFile.value) {
        error.value = 'select image file first'
        return
    }

    await request(mode === 'logo' ? 'logo uploaded' : 'image uploaded', async () => {
        const form = new FormData()
        form.append('image', imageFile.value)
        form.append('alt', imageForm.alt || '')
        const response = await axios.post(endpoint('/images'), form, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })

        imageForm.url = response.data.url
        appendEditorHtml(mode === 'logo'
            ? logoSignatureSnippet(response.data.url, imageForm.alt || response.data.alt, imageForm.link)
            : imageSnippet(response.data.url, imageForm.alt || response.data.alt, imageForm.link))

        return response
    })
}

async function addSuppression(email, cause = 'manual_block') {
    await request('suppression added', async () => {
        await axios.post(endpoint('/suppression'), { email, cause })
        await loadTable('suppression')
    })
}

async function removeSuppression(item) {
    if (!item?.id) return
    if (!window.confirm(`Remove ${item.email} from suppression list and unblock local contact flags?`)) return

    await request('suppression removed', async () => {
        await axios.delete(endpoint(`/suppression/${item.id}`), {
            data: { clear_contact_block: true },
        })
        await Promise.all([
            loadTable('suppression'),
            loadTable('contacts'),
            loadTable('campaigns'),
        ])
    })
}

async function testApi() {
    await request('api ok', async () => axios.post(endpoint('/settings/test-api')))
}

async function setWebhook() {
    await request('webhook configured', async () => axios.post(endpoint('/settings/set-webhook')))
}

function cleanPayload(source) {
    return Object.fromEntries(Object.entries(source).filter(([, value]) => value !== '' && value !== null && value !== undefined))
}

function toggleRow(id) {
    const next = new Set(selectedRows.value)
    next.has(id) ? next.delete(id) : next.add(id)
    selectedRows.value = next
}

function toggleSourceEmail(id) {
    const next = new Set(selectedSourceEmailIds.value)
    next.has(id) ? next.delete(id) : next.add(id)
    selectedSourceEmailIds.value = next
}

function statusClass(value) {
    if (['spam', 'hard_bounced', 'failed', 'cancelled', 'paused_by_system'].includes(value)) return 'is-danger'
    if (['soft_bounced', 'paused', 'scheduled'].includes(value)) return 'is-warn'
    if (['delivered', 'opened', 'clicked', 'completed', 'confirmed'].includes(value)) return 'is-ok'
    return ''
}

function formatDate(value) {
    if (!value) return '-'
    return String(value).replace('T', ' ').slice(0, 16)
}

function systemPauseReason(campaign) {
    const metadata = campaign?.metadata
    if (!metadata) return ''

    if (typeof metadata === 'string') {
        try {
            return JSON.parse(metadata)?.system_pause_reason || ''
        } catch {
            return ''
        }
    }

    return metadata.system_pause_reason || ''
}

onMounted(refreshAll)
</script>

<template>
    <main class="terminal-mailings">
        <header class="topbar">
            <div>
                <div class="eyebrow">PISCHEPROM :: SALES MAILING CONTROL</div>
                <h1>/Ameise/commercial-offers</h1>
            </div>
            <div class="status-line">
                <span :class="['dot', props.settings?.enabled ? 'on' : 'off']" />
                <span>{{ props.settings?.provider || 'provider?' }}</span>
                <span>{{ props.settings?.api_base }}</span>
            </div>
        </header>

        <nav class="tabs" aria-label="commercial offers sections">
            <button
                v-for="[key, label] in tabs"
                :key="key"
                type="button"
                :class="{ active: activeTab === key }"
                @click="selectTab(key)"
            >
                {{ label }}
            </button>
        </nav>

        <div class="toolbar sticky">
            <button type="button" :disabled="busy" @click="refreshAll">refresh</button>
            <button type="button" :disabled="busy" @click="activeTab = 'editor'">new personal КП</button>
            <button type="button" :disabled="busy" @click="activeTab = 'campaigns'">mass КП</button>
            <span v-if="busy">busy...</span>
            <span v-if="notice" class="ok">{{ notice }}</span>
            <span v-if="error" class="danger">{{ error }}</span>
        </div>

        <section v-if="activeTab === 'dashboard'" class="panel grid-panel">
            <article v-for="row in kpiRows" :key="row[0]" class="kpi-row">
                <span>{{ row[0] }}</span>
                <strong>{{ row[1] }}</strong>
            </article>
        </section>

        <section v-if="activeTab === 'campaigns'" class="panel">
            <div class="form-row compact campaign-form-row">
                <input v-model="campaignForm.name" placeholder="campaign name">
                <input v-model="campaignForm.subject" placeholder="subject">
                <select v-model="campaignForm.type"><option>mass_offer</option><option>personal_offer</option><option>follow_up</option><option>test</option></select>
                <input v-model="campaignForm.contact_set_id" placeholder="set id">
                <select v-model="campaignForm.template_id" @change="applySelectedTemplate">
                    <option value="">template</option>
                    <option v-for="template in templateOptions" :key="template.id" :value="template.id">{{ template.label }}</option>
                </select>
                <button type="button" @click="applySelectedTemplate">load template</button>
                <button type="button" @click="createCampaign">create campaign</button>
                <input v-model="testEmail" placeholder="test recipient email">
            </div>
            <div class="table-shell">
                <table>
                    <thead><tr><th class="sticky-col">id</th><th>status</th><th>system_reason</th><th>name</th><th>type</th><th>subject</th><th>template</th><th>recipients</th><th>delivered</th><th>opened</th><th>clicked</th><th>unsub</th><th>soft</th><th>hard</th><th>spam</th><th>scheduled</th><th class="actions-col">actions</th></tr></thead>
                    <tbody>
                        <tr v-for="item in campaigns" :key="item.id" tabindex="0" @click="toggleRow(item.id)">
                            <td class="sticky-col">{{ item.id }}</td>
                            <td :class="statusClass(item.status)">{{ item.status }}</td>
                            <td :class="{ 'is-danger': systemPauseReason(item) }" :title="systemPauseReason(item)">{{ systemPauseReason(item) || '-' }}</td>
                            <td>{{ item.name }}</td>
                            <td>{{ item.type }}</td>
                            <td>{{ item.subject }}</td>
                            <td>
                                <select class="row-select" :value="item.template_id || ''" :title="item.template?.name || 'No template'" :disabled="['sending', 'completed', 'cancelled'].includes(item.status)" @click.stop @change="changeCampaignTemplate(item, $event.target.value)">
                                    <option value="">no template</option>
                                    <option v-for="template in templates" :key="template.id" :value="template.id">#{{ template.id }} {{ template.name || template.subject }}</option>
                                </select>
                            </td>
                            <td>{{ item.total_recipients ?? item.recipients_count }}</td>
                            <td>{{ item.delivered_count }}</td>
                            <td>{{ item.unique_opened_count }}/{{ item.opened_count }}</td>
                            <td>{{ item.unique_clicked_count }}/{{ item.clicked_count }}</td>
                            <td>{{ item.unsubscribed_count }}</td>
                            <td>{{ item.soft_bounced_count }}</td>
                            <td>{{ item.hard_bounced_count }}</td>
                            <td>{{ item.spam_count }}</td>
                            <td>{{ formatDate(item.scheduled_at) }}</td>
                            <td class="actions actions-col">
                                <button @click.stop="openRecipientsDialog(item)">recipients</button>
                                <button @click.stop="campaignAction(item.id, 'send-test', { email: testEmail })">test</button>
                                <button @click.stop="campaignAction(item.id, 'approve')">approve</button>
                                <button @click.stop="campaignAction(item.id, 'start')">start</button>
                                <button @click.stop="campaignAction(item.id, 'pause')">pause</button>
                                <button @click.stop="campaignAction(item.id, 'resume')">resume</button>
                                <button @click.stop="campaignAction(item.id, 'cancel')">cancel</button>
                                <button @click.stop="campaignAction(item.id, 'duplicate')">dup</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div v-if="recipientDialogOpen" class="modal-backdrop" @click.self="recipientDialogOpen = false">
            <section class="recipient-dialog">
                <header class="dialog-head">
                    <div>
                        <div class="eyebrow">CAMPAIGN RECIPIENTS</div>
                        <h2>#{{ recipientCampaign?.id }} {{ recipientCampaign?.name || recipientCampaign?.subject }}</h2>
                    </div>
                    <button type="button" @click="recipientDialogOpen = false">close</button>
                </header>

                <div class="dialog-toolbar">
                    <button type="button" :class="{ active: recipientPickerTab === 'emails' }" @click="switchRecipientTab('emails')">emails</button>
                    <button type="button" :class="{ active: recipientPickerTab === 'units' }" @click="switchRecipientTab('units')">units</button>
                    <button type="button" :class="{ active: recipientPickerTab === 'selected' }" @click="switchRecipientTab('selected')">selected {{ selectedCampaignRecipients.length }}</button>
                    <input v-if="recipientPickerTab !== 'selected'" v-model="recipientSearch" placeholder="search emails / units" @keyup.enter="loadRecipientPicker">
                    <button v-if="recipientPickerTab !== 'selected'" type="button" @click="loadRecipientPicker">find</button>
                    <button type="button" @click="saveCampaignRecipients">save recipients</button>
                </div>

                <div v-if="recipientPickerTab === 'emails'" class="dialog-grid">
                    <div class="table-shell dialog-table">
                        <table>
                            <thead><tr><th class="sticky-col">sel</th><th>email</th><th>name</th><th>source</th><th>last_seen</th></tr></thead>
                            <tbody>
                                <tr v-for="item in recipientEmails" :key="item.id" :class="{ 'is-muted-row': isRecipientSelected(item.address) }">
                                    <td class="sticky-col"><input type="checkbox" :checked="isRecipientSelected(item.address)" @change="toggleRecipientEmail(item)"></td>
                                    <td>{{ item.address }}</td>
                                    <td>{{ item.name || '-' }}</td>
                                    <td>{{ item.source || item.domain || '-' }}</td>
                                    <td>{{ formatDate(item.last_seen_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <aside class="side-panel selected-panel">
                        <h3>selected</h3>
                        <div v-for="item in selectedCampaignRecipients" :key="item.email" class="selected-chip">
                            <span>{{ item.email }}</span>
                            <button type="button" :disabled="item.locked" @click="removeSelectedRecipient(item.email)">x</button>
                        </div>
                    </aside>
                </div>

                <div v-if="recipientPickerTab === 'units'" class="dialog-grid">
                    <div class="table-shell dialog-table">
                        <table>
                            <thead><tr><th class="sticky-col">add</th><th>unit</th><th>emails</th><th>selected</th><th>preview</th></tr></thead>
                            <tbody>
                                <tr v-for="unit in recipientUnits" :key="unit.id">
                                    <td class="sticky-col"><button type="button" @click="addRecipientUnit(unit)">add unit</button></td>
                                    <td>{{ unit.name }}</td>
                                    <td>{{ unit.selectable_count }}/{{ unit.emails_count }}</td>
                                    <td>{{ unit.selected_count }}</td>
                                    <td>{{ (unit.emails || []).map((email) => email.address).join(', ') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <aside class="side-panel selected-panel">
                        <h3>selected</h3>
                        <div v-for="item in selectedCampaignRecipients" :key="item.email" class="selected-chip">
                            <span>{{ item.email }}</span>
                            <button type="button" :disabled="item.locked" @click="removeSelectedRecipient(item.email)">x</button>
                        </div>
                    </aside>
                </div>

                <div v-if="recipientPickerTab === 'selected'" class="table-shell dialog-table full">
                    <table>
                        <thead><tr><th class="sticky-col">email</th><th>label</th><th>source</th><th>status</th><th>locked</th><th>actions</th></tr></thead>
                        <tbody>
                            <tr v-for="item in selectedCampaignRecipients" :key="item.email">
                                <td class="sticky-col">{{ item.email }}</td>
                                <td>{{ item.label || '-' }}</td>
                                <td>{{ item.source || '-' }}</td>
                                <td :class="statusClass(item.status)">{{ item.status || 'pending' }}</td>
                                <td>{{ item.locked ? 'yes' : 'no' }}</td>
                                <td><button type="button" :disabled="item.locked" @click="removeSelectedRecipient(item.email)">remove</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <section v-if="activeTab === 'contacts'" class="panel split">
            <div>
                <div class="form-row compact">
                    <input v-model="contactForm.email" placeholder="email">
                    <input v-model="contactForm.first_name" placeholder="first_name">
                    <input v-model="contactForm.last_name" placeholder="last_name">
                    <input v-model="contactForm.company_name" placeholder="company">
                    <select v-model="contactForm.consent_status"><option>unknown</option><option>confirmed</option><option>revoked</option><option>not_required_internal</option><option>rejected</option></select>
                    <button @click="createContact">save</button>
                </div>
                <div class="table-shell mid">
                    <table>
                        <thead><tr><th class="sticky-col">sel</th><th>email</th><th>first</th><th>last</th><th>company</th><th>position</th><th>consent</th><th>source</th><th>do_not</th><th>unsub</th><th>complained</th><th>hard</th><th>soft#</th><th>last open</th><th>last click</th><th>tags</th></tr></thead>
                        <tbody>
                            <tr v-for="item in contacts" :key="item.id" tabindex="0">
                                <td class="sticky-col"><input type="checkbox" :checked="selectedRows.has(item.id)" @change="toggleRow(item.id)"></td>
                                <td>{{ item.email }}</td>
                                <td>{{ item.first_name }}</td>
                                <td>{{ item.last_name }}</td>
                                <td>{{ item.company_name }}</td>
                                <td>{{ item.position }}</td>
                                <td :class="statusClass(item.consent_status)">{{ item.consent_status }}</td>
                                <td>{{ item.contact_source || item.source_type }}</td>
                                <td>{{ item.do_not_email ? '1' : '0' }}</td>
                                <td>{{ formatDate(item.unsubscribed_at) }}</td>
                                <td>{{ formatDate(item.complained_at) }}</td>
                                <td>{{ formatDate(item.hard_bounced_at) }}</td>
                                <td>{{ item.soft_bounce_count }}</td>
                                <td>{{ formatDate(item.last_opened_at) }}</td>
                                <td>{{ formatDate(item.last_clicked_at) }}</td>
                                <td>{{ JSON.stringify(item.tags || []) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <aside class="side-panel source-email-panel">
                <h3>emails DB -> recipients</h3>
                <div class="form-row compact source-toolbar">
                    <input v-model="sourceEmailSearch" placeholder="search current emails DB" @keyup.enter="loadSourceEmails">
                    <button @click="loadSourceEmails">find</button>
                </div>
                <div class="form-row compact source-toolbar">
                    <input v-model="sourceEmailSetId" placeholder="set id optional">
                    <select v-model="sourceEmailConsentStatus">
                        <option>unknown</option>
                        <option>confirmed</option>
                        <option>not_required_internal</option>
                    </select>
                </div>
                <div class="source-actions">
                    <button @click="importSelectedSourceEmails">sync selected</button>
                    <button @click="importVisibleSourceEmails">sync visible</button>
                    <span>{{ selectedSourceEmailIds.size }} selected</span>
                </div>
                <div class="source-email-shell">
                    <table>
                        <thead><tr><th>sel</th><th>email</th><th>name</th><th>src</th><th>in recipients</th></tr></thead>
                        <tbody>
                            <tr v-for="item in sourceEmails" :key="item.id" :class="{ 'is-muted-row': item.imported }">
                                <td><input type="checkbox" :checked="selectedSourceEmailIds.has(item.id)" @change="toggleSourceEmail(item.id)"></td>
                                <td>{{ item.address }}</td>
                                <td>{{ item.name || '-' }}</td>
                                <td>{{ item.source || item.domain || '-' }}</td>
                                <td :class="item.mailing_blocked ? 'is-danger' : (item.imported ? 'is-ok' : '')">
                                    {{ item.imported ? (item.mailing_consent_status || 'yes') : 'no' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <h3>paste from Excel</h3>
                <textarea v-model="pasteBuffer" placeholder="email list / rows"></textarea>
                <button @click="pasteContacts">import pasted emails</button>
                <button @click="loadTable('contacts')">deduplicate view refresh</button>
                <p>Mass sends still use mailing recipients: confirmed consent, no do_not_email, no unsubscribe, no hard bounce, no local suppression.</p>
            </aside>
        </section>

        <section v-if="activeTab === 'sets'" class="panel">
            <div class="form-row compact">
                <input v-model="setForm.name" placeholder="set name">
                <input v-model="setForm.description" placeholder="description">
                <select v-model="setForm.type"><option>manual</option><option>import</option><option>dynamic</option><option>saved_filter</option></select>
                <button @click="createSet">create set</button>
            </div>
            <div class="table-shell">
                <table><thead><tr><th class="sticky-col">id</th><th>name</th><th>type</th><th>contacts</th><th>active</th><th>updated</th><th>actions</th></tr></thead><tbody><tr v-for="item in sets" :key="item.id"><td class="sticky-col">{{ item.id }}</td><td>{{ item.name }}</td><td>{{ item.type }}</td><td>{{ item.contacts_count }}</td><td>{{ item.active ? '1' : '0' }}</td><td>{{ formatDate(item.updated_at) }}</td><td><button @click="campaignForm.contact_set_id = item.id; activeTab = 'campaigns'">use in campaign</button></td></tr></tbody></table>
            </div>
        </section>

        <section v-if="activeTab === 'editor'" class="panel editor-grid">
            <div class="editor-card">
                <h2>block-based КП skeleton</h2>
                <div class="form-row compact stackable">
                    <input v-model="campaignForm.name" placeholder="campaign_name">
                    <input v-model="campaignForm.subject" placeholder="subject">
                    <select v-model="campaignForm.template_id" @change="applySelectedTemplate">
                        <option value="">load template</option>
                        <option v-for="template in templateOptions" :key="template.id" :value="template.id">{{ template.label }}</option>
                    </select>
                    <button type="button" @click="applySelectedTemplate">load</button>
                </div>
                <div class="form-row compact stackable image-tools">
                    <input v-model="imageForm.url" placeholder="https image/logo URL">
                    <input v-model="imageForm.alt" placeholder="alt text">
                    <input v-model="imageForm.link" placeholder="optional https link">
                    <button type="button" @click="insertImageFromUrl('image')">insert image URL</button>
                    <button type="button" @click="insertImageFromUrl('logo')">insert logo/signature</button>
                </div>
                <div class="form-row compact stackable image-tools">
                    <input type="file" accept="image/png,image/jpeg,image/webp,image/gif" @change="onImageSelected">
                    <button type="button" @click="uploadAndInsertImage('image')">upload image</button>
                    <button type="button" @click="uploadAndInsertImage('logo')">upload logo</button>
                    <span class="mini-help">email images must be public HTTPS; uploaded files use Laravel public storage.</span>
                </div>
                <textarea v-model="campaignForm.html_markup" class="codebox"></textarea>
                <textarea v-model="campaignForm.plaintext" class="codebox small"></textarea>
                <div class="form-row compact">
                    <button type="button" @click="createCampaign">save as campaign draft</button>
                    <button type="button" @click="saveEditorAsTemplate">save current editor as template</button>
                    <button type="button" @click="copyEditorToTemplateForm">copy to Templates tab</button>
                </div>
                <div class="variables-grid">
                    <div v-for="[variable, description] in mailingVariables" :key="variable">
                        <code>{{ variable }}</code>
                        <span>{{ description }}</span>
                    </div>
                </div>
            </div>
            <div class="editor-card preview">
                <h2>preview / desktop</h2>
                <iframe sandbox class="preview-frame" :srcdoc="campaignForm.html_markup"></iframe>
            </div>
        </section>

        <section v-if="activeTab === 'templates'" class="panel">
            <div class="form-row compact stackable">
                <input v-model="templateForm.name" placeholder="template name">
                <input v-model="templateForm.subject" placeholder="subject">
                <select v-model="templateForm.type"><option>commercial_offer</option><option>personal_offer</option><option>follow_up</option><option>custom</option></select>
                <button type="button" @click="createTemplate">create template</button>
                <button type="button" @click="copyEditorToTemplateForm">copy from editor</button>
            </div>
            <div class="template-editor-grid">
                <textarea v-model="templateForm.html_markup" class="codebox template-codebox" placeholder="template HTML"></textarea>
                <textarea v-model="templateForm.plaintext" class="codebox template-codebox small" placeholder="template plaintext"></textarea>
            </div>
            <div class="table-shell"><table><thead><tr><th class="sticky-col">id</th><th>name</th><th>type</th><th>subject</th><th>updated</th><th>active</th><th>unisender_template_id</th><th>actions</th></tr></thead><tbody><tr v-for="item in templates" :key="item.id"><td class="sticky-col">{{ item.id }}</td><td>{{ item.name }}</td><td>{{ item.type }}</td><td>{{ item.subject }}</td><td>{{ formatDate(item.updated_at) }}</td><td>{{ item.active ? '1' : '0' }}</td><td>{{ item.unisender_template_id || '-' }}</td><td><button @click="syncTemplate(item.id)">sync</button><button @click="applyTemplateToCampaign(item); activeTab = 'editor'">use</button><button @click="fillTemplateForm(item)">edit form</button></td></tr></tbody></table></div>
        </section>

        <section v-if="activeTab === 'products'" class="panel products-panel">
            <div class="form-row compact stackable products-toolbar">
                <select v-model="productCampaignId" @change="loadCampaignOfferItems">
                    <option value="">select campaign</option>
                    <option v-for="campaign in campaignOptions" :key="campaign.id" :value="campaign.id">{{ campaign.label }}</option>
                </select>
                <button type="button" :class="{ active: productActiveTab === 'goods' }" @click="switchProductTab('goods')">goods</button>
                <button type="button" :class="{ active: productActiveTab === 'categories' }" @click="switchProductTab('categories')">categories</button>
                <input v-model="productSearch" placeholder="search by name / category / brand / id" @keyup.enter="searchProducts(1)">
                <select v-model="productPublishedFilter" @change="searchProducts(1)">
                    <option value="">all</option>
                    <option value="true">published</option>
                    <option value="false">hidden</option>
                </select>
                <select v-model.number="productPerPage" @change="searchProducts(1)">
                    <option :value="25">25/page</option>
                    <option :value="50">50/page</option>
                    <option :value="100">100/page</option>
                </select>
                <button type="button" @click="searchProducts(1)">search</button>
            </div>

            <div class="mini-help">Goods and categories are loaded from pischeprom DB with server-side pagination. Adding stores snapshot in КП; catalog price is not changed.</div>

            <div class="products-workspace">
                <div class="products-catalog">
                    <div class="product-subhead">
                        <strong>{{ productActiveTab === 'categories' ? 'categories DB' : 'goods DB' }}</strong>
                        <span>{{ activeProductMeta.total }} total / page {{ activeProductMeta.current_page }} of {{ activeProductMeta.last_page }}</span>
                        <button type="button" :disabled="activeProductMeta.current_page <= 1" @click="searchProducts(activeProductMeta.current_page - 1)">prev</button>
                        <button type="button" :disabled="activeProductMeta.current_page >= activeProductMeta.last_page" @click="searchProducts(activeProductMeta.current_page + 1)">next</button>
                    </div>

                    <div v-if="productActiveTab === 'goods'" class="table-shell products-table">
                        <table>
                            <thead><tr><th class="sticky-col">id</th><th>title</th><th>price</th><th>category/url</th><th>thumb</th><th>published</th><th>actions</th></tr></thead>
                            <tbody>
                                <tr v-for="item in products" :key="item.id">
                                    <td class="sticky-col">{{ item.id }}</td>
                                    <td>{{ item.title || item.name }}</td>
                                    <td>{{ item.price_formatted || item.price || '-' }}</td>
                                    <td>{{ item.category || item.canonical_url }}</td>
                                    <td>{{ item.thumbnail_url ? 'img' : '-' }}</td>
                                    <td>{{ item.is_published ? '1' : '0' }}</td>
                                    <td><button type="button" @click="addProduct(item)">add to КП</button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="productActiveTab === 'categories'" class="table-shell products-table">
                        <table>
                            <thead><tr><th class="sticky-col">id</th><th>category</th><th>url</th><th>source</th><th>actions</th></tr></thead>
                            <tbody>
                                <tr v-for="item in categories" :key="item.id">
                                    <td class="sticky-col">{{ item.id }}</td>
                                    <td>{{ item.title || item.name }}</td>
                                    <td>{{ item.canonical_url }}</td>
                                    <td>{{ item.source_table || 'categories' }}</td>
                                    <td><button type="button" @click="addCategory(item)">add to КП</button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <aside class="side-panel offer-items-panel">
                    <div class="product-subhead">
                        <strong>campaign items</strong>
                        <button type="button" @click="loadCampaignOfferItems">refresh</button>
                    </div>
                    <p v-if="!productCampaignId" class="mini-help">Select campaign to see saved КП items.</p>
                    <div class="table-shell offer-items-table">
                        <table>
                            <thead><tr><th class="sticky-col">id</th><th>type</th><th>title</th><th>price</th><th>actions</th></tr></thead>
                            <tbody>
                                <tr v-for="item in campaignOfferItems" :key="item.id">
                                    <td class="sticky-col">{{ item.id }}</td>
                                    <td>{{ item.item_type }}</td>
                                    <td>{{ item.title }}</td>
                                    <td>{{ item.offer_price || item.original_price || '-' }} {{ item.currency || '' }}</td>
                                    <td><button type="button" class="danger" @click="deleteOfferItem(item)">delete</button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </aside>
            </div>
        </section>

        <section v-if="activeTab === 'events'" class="panel">
            <div class="form-row compact"><select v-model="eventStatus" @change="loadTable('events')"><option value="">all</option><option>delivered</option><option>opened</option><option>clicked</option><option>unsubscribed</option><option>soft_bounced</option><option>hard_bounced</option><option>spam</option></select><button @click="loadTable('events')">filter</button></div>
            <div class="table-shell"><table><thead><tr><th class="sticky-col">time</th><th>campaign</th><th>email</th><th>event</th><th>status</th><th>url</th><th>delivery_status</th><th>destination_response</th><th>ua</th><th>ip</th><th>metadata</th></tr></thead><tbody><tr v-for="item in events" :key="item.id"><td class="sticky-col">{{ formatDate(item.event_time || item.created_at) }}</td><td>{{ item.campaign_id }}</td><td>{{ item.email }}</td><td>{{ item.event_name }}</td><td :class="statusClass(item.status)">{{ item.status }}</td><td>{{ item.url }}</td><td>{{ item.delivery_status }}</td><td>{{ item.destination_response }}</td><td>{{ item.user_agent }}</td><td>{{ item.ip }} {{ item.country }}</td><td>{{ JSON.stringify(item.metadata || {}) }}</td></tr></tbody></table></div>
        </section>

        <section v-if="activeTab === 'suppression'" class="panel">
            <div class="form-row compact"><input v-model="contactForm.email" placeholder="email"><select v-model="contactForm.consent_status"><option>manual_block</option><option>unsubscribed</option><option>temporary_unavailable</option><option>permanent_unavailable</option><option>complained</option></select><button @click="addSuppression(contactForm.email, contactForm.consent_status)">block</button></div>
            <div class="table-shell"><table><thead><tr><th class="sticky-col">id</th><th>email</th><th>cause</th><th>source</th><th>note</th><th>created_at</th><th>actions</th></tr></thead><tbody><tr v-for="item in suppression" :key="item.id"><td class="sticky-col">{{ item.id }}</td><td>{{ item.email }}</td><td :class="statusClass(item.cause)">{{ item.cause }}</td><td>{{ item.source }}</td><td>{{ item.note }}</td><td>{{ formatDate(item.created_at) }}</td><td class="actions"><button type="button" class="danger" @click="removeSuppression(item)">remove + unblock</button></td></tr></tbody></table></div>
        </section>

        <section v-if="activeTab === 'settings'" class="panel grid-panel">
            <article v-for="(value, key) in props.settings" :key="key" class="kpi-row"><span>{{ key }}</span><strong>{{ value }}</strong></article>
            <div class="wide-actions"><button @click="testApi">test API connection</button><button @click="setWebhook">set webhook</button><a href="/docs/unisender-go.md">docs/unisender-go.md</a></div>
        </section>
    </main>
</template>

<style scoped>
.terminal-mailings{--bg:#050805;--panel:#071007;--text:#9cff57;--text2:#8ee66b;--muted:#5c8f4f;--border:#1c3a1c;--warn:#d9b94f;--danger:#ff4d4d;width:100%;max-width:none;align-self:stretch;min-height:100vh;background:radial-gradient(circle at top left,#0b1c0b 0,#050805 42rem),var(--bg);color:var(--text);font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,"Liberation Mono",monospace;font-size:12px;padding:10px}.topbar{display:flex;justify-content:space-between;gap:12px;align-items:flex-end;border:1px solid var(--border);background:linear-gradient(90deg,#071007,#081808);padding:10px 12px}.eyebrow{color:var(--muted);letter-spacing:.14em;font-size:10px}.topbar h1{font-size:18px;margin:2px 0 0}.status-line{display:flex;gap:8px;align-items:center;color:var(--muted)}.dot{width:8px;height:8px;border-radius:50%;background:#444}.dot.on{background:var(--text);box-shadow:0 0 10px var(--text)}.dot.off{background:var(--danger)}.tabs,.toolbar{display:flex;gap:4px;flex-wrap:wrap;border:1px solid var(--border);border-top:0;background:#061006;padding:5px}.sticky{position:sticky;top:0;z-index:20}.tabs button,.toolbar button,.actions button,.form-row button,.wide-actions button,.side-panel button{height:24px;border:1px solid var(--border);background:#091709;color:var(--text);font:inherit;padding:0 8px;cursor:pointer}.tabs button.active,.toolbar button:hover,.form-row button:hover{background:var(--text);color:#050805}.toolbar span{line-height:24px}.ok{color:var(--text2)}.danger,.is-danger{color:var(--danger)!important}.is-warn{color:var(--warn)!important}.is-ok{color:var(--text2)!important}.panel{border:1px solid var(--border);background:rgba(7,16,7,.92);margin-top:8px;padding:8px}.grid-panel{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:6px}.kpi-row{display:flex;justify-content:space-between;gap:12px;border:1px solid var(--border);background:#061106;padding:5px 7px;min-height:24px}.kpi-row span{color:var(--muted)}.form-row{display:flex;gap:4px;margin-bottom:6px}.form-row.compact input,.form-row.compact select{height:24px}.form-row button,.actions button{white-space:nowrap;flex:0 0 auto}.campaign-form-row{flex-wrap:wrap}.campaign-form-row input,.campaign-form-row select{min-width:140px;flex:1 1 140px}.campaign-form-row input[placeholder="test recipient email"]{max-width:260px}.row-select{min-width:220px;max-width:260px;height:22px}.stackable{flex-wrap:wrap}input,select,textarea{border:1px solid var(--border);background:#030603;color:var(--text);font:inherit;padding:2px 6px;outline:none}input:focus,select:focus,textarea:focus{border-color:var(--text)}input[type=file]{min-width:260px;color:var(--muted)}.table-shell{max-height:68vh;overflow:auto;border:1px solid var(--border)}.table-shell.mid{max-height:58vh}table{width:max-content;min-width:100%;border-collapse:separate;border-spacing:0}th,td{border-right:1px solid var(--border);border-bottom:1px solid var(--border);height:24px;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;padding:1px 6px;text-align:left}th{position:sticky;top:0;z-index:4;background:#0a180a;color:var(--text2)}tr:focus,tbody tr:hover{background:#0d210d}.sticky-col{position:sticky;left:0;z-index:3;background:#071407}th.sticky-col{z-index:5}.actions{display:flex;gap:3px}.actions button{height:20px;padding:0 5px}.actions-col{position:sticky;right:0;z-index:3;background:#071407;min-width:410px;max-width:none}.actions-col.actions{display:flex}.actions-col button{white-space:nowrap}th.actions-col{z-index:6;background:#0a180a}.split{display:grid;grid-template-columns:minmax(0,1fr) 620px;gap:8px}.side-panel,.editor-card{border:1px solid var(--border);background:#061106;padding:8px}.side-panel textarea,.codebox{width:100%;min-height:140px}.codebox{min-height:42vh}.codebox.small{min-height:120px}.editor-grid{display:grid;grid-template-columns:minmax(0,1fr);gap:8px}.preview{background:#0b120b;color:#d6ffd0}.preview-frame{display:block;width:100%;min-height:72vh;border:1px solid var(--border);background:#fff}.mini-row{display:grid;grid-template-columns:48px minmax(0,1fr) 54px;gap:6px;border-bottom:1px solid var(--border);height:24px;align-items:center}.mini-help{margin:0 0 6px;color:var(--muted)}.wide-actions{grid-column:1/-1;display:flex;gap:6px;align-items:center}.source-email-panel{min-width:0}.source-toolbar{margin-bottom:4px}.source-toolbar input{min-width:0;width:100%}.source-actions{display:flex;gap:4px;align-items:center;margin-bottom:6px}.source-email-shell{max-height:34vh;overflow:auto;border:1px solid var(--border);margin-bottom:8px}.source-email-shell table{font-size:11px}.source-email-shell th,.source-email-shell td{height:22px;max-width:190px}.variables-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:3px;margin-top:6px}.variables-grid div{display:flex;gap:6px;border:1px solid var(--border);padding:3px 5px;background:#040904}.variables-grid span{color:var(--muted)}.image-tools input{min-width:210px}.template-editor-grid{display:grid;grid-template-columns:minmax(0,2fr) minmax(280px,1fr);gap:6px;margin-bottom:6px}.template-codebox{min-height:240px}.products-panel{display:flex;flex-direction:column;min-height:calc(100vh - 230px)}.products-toolbar input{min-width:320px;flex:1 1 320px}.products-toolbar button.active{background:var(--text);color:#050805}.products-workspace{display:grid;grid-template-columns:minmax(0,1fr) 430px;gap:8px;min-height:0;flex:1}.products-catalog,.offer-items-panel{min-height:0;display:flex;flex-direction:column}.product-subhead{display:flex;gap:8px;align-items:center;border:1px solid var(--border);border-bottom:0;background:#061006;padding:4px 6px;min-height:28px}.product-subhead span{color:var(--muted);margin-left:auto}.product-subhead button{height:22px;border:1px solid var(--border);background:#091709;color:var(--text);font:inherit;padding:0 7px}.product-subhead button:disabled{opacity:.45;cursor:not-allowed}.products-table,.offer-items-table{max-height:none;min-height:0;flex:1}.modal-backdrop{position:fixed;inset:0;z-index:100;background:rgba(0,0,0,.74);display:flex;align-items:stretch;justify-content:center;padding:18px}.recipient-dialog{width:min(1600px,100%);height:calc(100vh - 36px);border:1px solid var(--border);background:#050805;color:var(--text);display:flex;flex-direction:column;box-shadow:0 0 0 1px #000,0 18px 80px rgba(0,0,0,.65)}.dialog-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;border-bottom:1px solid var(--border);padding:10px 12px;background:#071007}.dialog-head h2{font-size:15px;margin:2px 0 0}.dialog-toolbar{display:flex;gap:4px;flex-wrap:wrap;padding:6px;border-bottom:1px solid var(--border);background:#061006}.dialog-toolbar button{height:24px;border:1px solid var(--border);background:#091709;color:var(--text);font:inherit;padding:0 8px;cursor:pointer;white-space:nowrap}.dialog-toolbar button.active,.dialog-toolbar button:hover{background:var(--text);color:#050805}.dialog-toolbar input{height:24px;min-width:320px;flex:1 1 320px}.dialog-grid{display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:8px;min-height:0;flex:1;padding:8px}.dialog-table{max-height:none;height:100%;min-height:0}.dialog-table.full{margin:8px;height:auto;max-height:calc(100vh - 155px)}.selected-panel{min-height:0;overflow:auto}.selected-chip{display:grid;grid-template-columns:minmax(0,1fr) 24px;gap:4px;align-items:center;height:24px;border-bottom:1px solid var(--border)}.selected-chip span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.selected-chip button{height:20px;padding:0}.is-muted-row{opacity:.78}a{color:var(--text2)}@media (max-width:900px){.topbar,.split,.editor-grid,.template-editor-grid,.products-workspace{display:block}.status-line{margin-top:8px}.side-panel,.editor-card{margin-top:8px}.terminal-mailings{padding:6px}.table-shell{max-height:60vh}}
</style>
