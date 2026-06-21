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
    '{{to_name}}',
    '{{first_name}}',
    '{{last_name}}',
    '{{company_name}}',
    '{{manager_name}}',
    '{{unsubscribe_url}}',
    '{{offer_items_html}}',
]

const activeTab = ref('dashboard')
const busy = ref(false)
const notice = ref('')
const error = ref('')
const dashboard = ref(props.dashboard)

const campaigns = ref([])
const contacts = ref([])
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
    html_markup: '<h1>{{campaign_name}}</h1><p>Здравствуйте, {{first_name}}.</p>{{offer_items_html}}<p><a href="{{unsubscribe_url}}">Отписаться</a></p>',
    plaintext: 'Здравствуйте, {{first_name}}. Коммерческое предложение: {{unsubscribe_url}}',
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
    html_markup: '<table width="100%"><tr><td><h1>{{campaign_name}}</h1>{{offer_items_html}}<p><a href="{{unsubscribe_url}}">Отписаться</a></p></td></tr></table>',
    plaintext: 'Коммерческое предложение: {{unsubscribe_url}}',
})
const productSearch = ref('')
const productCampaignId = ref('')
const eventStatus = ref('')
const testEmail = ref(props.settings?.test_recipient || '')
const pasteBuffer = ref('')

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

function percent(value) {
    const number = Number(value || 0)
    return `${(number * 100).toFixed(2)}%`
}

function endpoint(path) {
    return `/Ameise/commercial-offers${path}`
}

async function request(action, callback) {
    busy.value = true
    error.value = ''
    notice.value = ''
    try {
        const result = await callback()
        notice.value = action
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

async function refreshAll() {
    await request('loaded', async () => {
        await Promise.all(['campaigns', 'contacts', 'sets', 'templates', 'events', 'suppression'].map(loadTable))
    })
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

async function createSet() {
    await request('set created', async () => {
        await axios.post(endpoint('/sets'), cleanPayload(setForm))
        Object.assign(setForm, { name: '', description: '', type: 'manual' })
        await loadTable('sets')
    })
}

async function createTemplate() {
    await request('template saved', async () => {
        await axios.post(endpoint('/templates'), cleanPayload(templateForm))
        await loadTable('templates')
    })
}

async function syncTemplate(id) {
    await request('template synced', async () => {
        await axios.post(endpoint(`/templates/${id}/sync-unisender`))
        await loadTable('templates')
    })
}

async function searchProducts() {
    await request('products loaded', async () => {
        const { data } = await axios.get(endpoint('/products/search'), { params: { q: productSearch.value } })
        products.value = data.products || []
        categories.value = data.categories || []
    })
}

async function addProduct(product) {
    if (!productCampaignId.value) {
        error.value = 'campaign id required'
        return
    }
    await request('product added', async () => {
        await axios.post(endpoint(`/campaigns/${productCampaignId.value}/offer-items`), { product_id: product.id, item_type: 'product' })
    })
}

async function addSuppression(email, cause = 'manual_block') {
    await request('suppression added', async () => {
        await axios.post(endpoint('/suppression'), { email, cause })
        await loadTable('suppression')
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
                @click="activeTab = key"
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
            <div class="form-row compact">
                <input v-model="campaignForm.name" placeholder="campaign name">
                <input v-model="campaignForm.subject" placeholder="subject">
                <select v-model="campaignForm.type"><option>mass_offer</option><option>personal_offer</option><option>follow_up</option><option>test</option></select>
                <input v-model="campaignForm.contact_set_id" placeholder="set id">
                <input v-model="campaignForm.template_id" placeholder="template id">
                <button type="button" @click="createCampaign">create campaign</button>
            </div>
            <div class="table-shell">
                <table>
                    <thead><tr><th class="sticky-col">id</th><th>status</th><th>name</th><th>type</th><th>subject</th><th>recipients</th><th>delivered</th><th>opened</th><th>clicked</th><th>unsub</th><th>soft</th><th>hard</th><th>spam</th><th>scheduled</th><th>actions</th></tr></thead>
                    <tbody>
                        <tr v-for="item in campaigns" :key="item.id" tabindex="0" @click="toggleRow(item.id)">
                            <td class="sticky-col">{{ item.id }}</td>
                            <td :class="statusClass(item.status)">{{ item.status }}</td>
                            <td>{{ item.name }}</td>
                            <td>{{ item.type }}</td>
                            <td>{{ item.subject }}</td>
                            <td>{{ item.total_recipients ?? item.recipients_count }}</td>
                            <td>{{ item.delivered_count }}</td>
                            <td>{{ item.unique_opened_count }}/{{ item.opened_count }}</td>
                            <td>{{ item.unique_clicked_count }}/{{ item.clicked_count }}</td>
                            <td>{{ item.unsubscribed_count }}</td>
                            <td>{{ item.soft_bounced_count }}</td>
                            <td>{{ item.hard_bounced_count }}</td>
                            <td>{{ item.spam_count }}</td>
                            <td>{{ formatDate(item.scheduled_at) }}</td>
                            <td class="actions">
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
            <aside class="side-panel">
                <h3>paste from Excel</h3>
                <textarea v-model="pasteBuffer" placeholder="email list / rows"></textarea>
                <button @click="pasteContacts">import pasted emails</button>
                <button @click="loadTable('contacts')">deduplicate view refresh</button>
                <p>mass eligibility: confirmed consent, no do_not_email, no unsubscribe, no hard bounce, no local suppression.</p>
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
                <table><thead><tr><th class="sticky-col">id</th><th>name</th><th>type</th><th>contacts</th><th>active</th><th>updated</th><th>actions</th></tr></thead><tbody><tr v-for="item in sets" :key="item.id"><td class="sticky-col">{{ item.id }}</td><td>{{ item.name }}</td><td>{{ item.type }}</td><td>{{ item.contacts_count }}</td><td>{{ item.active ? '1' : '0' }}</td><td>{{ formatDate(item.updated_at) }}</td><td><button @click="productCampaignId = item.id">preview recipients</button></td></tr></tbody></table>
            </div>
        </section>

        <section v-if="activeTab === 'editor'" class="panel editor-grid">
            <div class="editor-card">
                <h2>block-based КП skeleton</h2>
                <input v-model="campaignForm.name" placeholder="campaign_name">
                <input v-model="campaignForm.subject" placeholder="subject">
                <textarea v-model="campaignForm.html_markup" class="codebox"></textarea>
                <textarea v-model="campaignForm.plaintext" class="codebox small"></textarea>
                <div class="form-row compact"><button @click="createCampaign">save as campaign draft</button><button @click="createTemplate">save template</button></div>
                <p>variables: <code v-for="variable in mailingVariables" :key="variable">{{ variable }} </code></p>
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
                <button @click="createTemplate">create template</button>
            </div>
            <div class="table-shell"><table><thead><tr><th class="sticky-col">id</th><th>name</th><th>type</th><th>subject</th><th>updated</th><th>active</th><th>unisender_template_id</th><th>actions</th></tr></thead><tbody><tr v-for="item in templates" :key="item.id"><td class="sticky-col">{{ item.id }}</td><td>{{ item.name }}</td><td>{{ item.type }}</td><td>{{ item.subject }}</td><td>{{ formatDate(item.updated_at) }}</td><td>{{ item.active ? '1' : '0' }}</td><td>{{ item.unisender_template_id || '-' }}</td><td><button @click="syncTemplate(item.id)">sync</button><button @click="campaignForm.template_id = item.id; activeTab = 'editor'">use</button></td></tr></tbody></table></div>
        </section>

        <section v-if="activeTab === 'products'" class="panel split">
            <div>
                <div class="form-row compact">
                    <input v-model="productCampaignId" placeholder="campaign id">
                    <input v-model="productSearch" placeholder="name / sku / category / brand" @keyup.enter="searchProducts">
                    <button @click="searchProducts">search</button>
                </div>
                <div class="table-shell mid"><table><thead><tr><th class="sticky-col">id</th><th>title</th><th>price</th><th>url</th><th>thumb</th><th>actions</th></tr></thead><tbody><tr v-for="item in products" :key="item.id"><td class="sticky-col">{{ item.id }}</td><td>{{ item.title || item.name }}</td><td>{{ item.price }}</td><td>{{ item.canonical_url }}</td><td>{{ item.thumbnail_url ? 'img' : '-' }}</td><td><button @click="addProduct(item)">add to КП</button></td></tr></tbody></table></div>
            </div>
            <aside class="side-panel"><h3>categories</h3><div v-for="item in categories" :key="item.id" class="mini-row"><span>{{ item.id }}</span><span>{{ item.title || item.name }}</span></div><p>Offer item stores snapshot. Edited КП price does not update catalog price.</p></aside>
        </section>

        <section v-if="activeTab === 'events'" class="panel">
            <div class="form-row compact"><select v-model="eventStatus" @change="loadTable('events')"><option value="">all</option><option>delivered</option><option>opened</option><option>clicked</option><option>unsubscribed</option><option>soft_bounced</option><option>hard_bounced</option><option>spam</option></select><button @click="loadTable('events')">filter</button></div>
            <div class="table-shell"><table><thead><tr><th class="sticky-col">time</th><th>campaign</th><th>email</th><th>event</th><th>status</th><th>url</th><th>delivery_status</th><th>destination_response</th><th>ua</th><th>ip</th><th>metadata</th></tr></thead><tbody><tr v-for="item in events" :key="item.id"><td class="sticky-col">{{ formatDate(item.event_time || item.created_at) }}</td><td>{{ item.campaign_id }}</td><td>{{ item.email }}</td><td>{{ item.event_name }}</td><td :class="statusClass(item.status)">{{ item.status }}</td><td>{{ item.url }}</td><td>{{ item.delivery_status }}</td><td>{{ item.destination_response }}</td><td>{{ item.user_agent }}</td><td>{{ item.ip }} {{ item.country }}</td><td>{{ JSON.stringify(item.metadata || {}) }}</td></tr></tbody></table></div>
        </section>

        <section v-if="activeTab === 'suppression'" class="panel">
            <div class="form-row compact"><input v-model="contactForm.email" placeholder="email"><select v-model="contactForm.consent_status"><option>manual_block</option><option>unsubscribed</option><option>temporary_unavailable</option><option>permanent_unavailable</option><option>complained</option></select><button @click="addSuppression(contactForm.email, contactForm.consent_status)">block</button></div>
            <div class="table-shell"><table><thead><tr><th class="sticky-col">id</th><th>email</th><th>cause</th><th>source</th><th>note</th><th>created_at</th></tr></thead><tbody><tr v-for="item in suppression" :key="item.id"><td class="sticky-col">{{ item.id }}</td><td>{{ item.email }}</td><td :class="statusClass(item.cause)">{{ item.cause }}</td><td>{{ item.source }}</td><td>{{ item.note }}</td><td>{{ formatDate(item.created_at) }}</td></tr></tbody></table></div>
        </section>

        <section v-if="activeTab === 'settings'" class="panel grid-panel">
            <article v-for="(value, key) in props.settings" :key="key" class="kpi-row"><span>{{ key }}</span><strong>{{ value }}</strong></article>
            <div class="wide-actions"><button @click="testApi">test API connection</button><button @click="setWebhook">set webhook</button><a href="/docs/unisender-go.md">docs/unisender-go.md</a></div>
        </section>
    </main>
</template>

<style scoped>
.terminal-mailings{--bg:#050805;--panel:#071007;--text:#9cff57;--text2:#8ee66b;--muted:#5c8f4f;--border:#1c3a1c;--warn:#d9b94f;--danger:#ff4d4d;width:100%;max-width:none;align-self:stretch;min-height:100vh;background:radial-gradient(circle at top left,#0b1c0b 0,#050805 42rem),var(--bg);color:var(--text);font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,"Liberation Mono",monospace;font-size:12px;padding:10px}.topbar{display:flex;justify-content:space-between;gap:12px;align-items:flex-end;border:1px solid var(--border);background:linear-gradient(90deg,#071007,#081808);padding:10px 12px}.eyebrow{color:var(--muted);letter-spacing:.14em;font-size:10px}.topbar h1{font-size:18px;margin:2px 0 0}.status-line{display:flex;gap:8px;align-items:center;color:var(--muted)}.dot{width:8px;height:8px;border-radius:50%;background:#444}.dot.on{background:var(--text);box-shadow:0 0 10px var(--text)}.dot.off{background:var(--danger)}.tabs,.toolbar{display:flex;gap:4px;flex-wrap:wrap;border:1px solid var(--border);border-top:0;background:#061006;padding:5px}.sticky{position:sticky;top:0;z-index:20}.tabs button,.toolbar button,.actions button,.form-row button,.wide-actions button,.side-panel button{height:24px;border:1px solid var(--border);background:#091709;color:var(--text);font:inherit;padding:0 8px;cursor:pointer}.tabs button.active,.toolbar button:hover,.form-row button:hover{background:var(--text);color:#050805}.toolbar span{line-height:24px}.ok{color:var(--text2)}.danger,.is-danger{color:var(--danger)!important}.is-warn{color:var(--warn)!important}.is-ok{color:var(--text2)!important}.panel{border:1px solid var(--border);background:rgba(7,16,7,.92);margin-top:8px;padding:8px}.grid-panel{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:6px}.kpi-row{display:flex;justify-content:space-between;gap:12px;border:1px solid var(--border);background:#061106;padding:5px 7px;min-height:24px}.kpi-row span{color:var(--muted)}.form-row{display:flex;gap:4px;margin-bottom:6px}.form-row.compact input,.form-row.compact select{height:24px}.stackable{flex-wrap:wrap}input,select,textarea{border:1px solid var(--border);background:#030603;color:var(--text);font:inherit;padding:2px 6px;outline:none}input:focus,select:focus,textarea:focus{border-color:var(--text)}.table-shell{max-height:68vh;overflow:auto;border:1px solid var(--border)}.table-shell.mid{max-height:58vh}table{width:max-content;min-width:100%;border-collapse:separate;border-spacing:0}th,td{border-right:1px solid var(--border);border-bottom:1px solid var(--border);height:24px;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;padding:1px 6px;text-align:left}th{position:sticky;top:0;z-index:4;background:#0a180a;color:var(--text2)}tr:focus,tbody tr:hover{background:#0d210d}.sticky-col{position:sticky;left:0;z-index:3;background:#071407}th.sticky-col{z-index:5}.actions{display:flex;gap:3px}.actions button{height:20px;padding:0 5px}.split{display:grid;grid-template-columns:minmax(0,1fr) 310px;gap:8px}.side-panel,.editor-card{border:1px solid var(--border);background:#061106;padding:8px}.side-panel textarea,.codebox{width:100%;min-height:190px}.codebox{min-height:42vh}.codebox.small{min-height:120px}.editor-grid{display:grid;grid-template-columns:minmax(0,1fr);gap:8px}.preview{background:#0b120b;color:#d6ffd0}.preview-frame{display:block;width:100%;min-height:72vh;border:1px solid var(--border);background:#fff}.mini-row{display:grid;grid-template-columns:48px 1fr;gap:6px;border-bottom:1px solid var(--border);height:24px;align-items:center}.wide-actions{grid-column:1/-1;display:flex;gap:6px;align-items:center}a{color:var(--text2)}@media (max-width:900px){.topbar,.split,.editor-grid{display:block}.status-line{margin-top:8px}.side-panel,.editor-card{margin-top:8px}.terminal-mailings{padding:6px}.table-shell{max-height:60vh}}
</style>
