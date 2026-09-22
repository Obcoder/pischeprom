<script setup>
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue'
import axios from 'axios'
import UnitSendingsCard from '@/Components/Unit/UnitSendingsCard.vue'
import UnitCallsCard from '@/Components/Unit/UnitCallsCard.vue'
import UnitActivityTabsPanel from '@/Components/Unit/UnitActivityTabsPanel.vue'
import MaxContactButton from '@/Components/Max/MaxContactButton.vue'
import { collectUnitCommunications, communicationTypes, contactKey, websiteHref } from '@/Composables/unitCommunications.js'
import { usePhoneFormatter } from '@/Composables/entities/usePhoneFormatter.js'

const props = defineProps({
    unit: { type: Object, required: true },
    dict: { type: Object, default: () => ({}) },
    canManage: Boolean,
    canSend: Boolean,
    canViewOrders: Boolean,
    canCreateOrders: Boolean,
})
const emit = defineEmits(['refresh'])
const { formatPhone } = usePhoneFormatter()
const mailCard = ref(null)
const callsCard = ref(null)
const contacts = computed(() => collectUnitCommunications(props.unit))
const dialog = ref(false)
const mode = ref('create')
const activeType = ref(communicationTypes[0])
const activeContact = ref(null)
const selectedContact = ref(null)
const search = ref('')
const searchedOptions = ref([])
const searching = ref(false)
const saving = ref(false)
const feedback = ref(null)
const errors = ref({})
const form = reactive({ value: '', name: '', owner: 'unit', source: 0, deleteRecord: false })
const dialDialog = ref(false)
const dialContact = ref(null)
const employeePhone = ref('79650160001')
const dialing = ref(false)
let searchTimer = null
let searchRequest = null
let searchSequence = 0

const ownerItems = computed(() => [
    { title: 'Unit', value: 'unit' },
    ...(props.unit.entities || []).map((entity) => ({ title: entity.name || `Entity #${entity.id}`, value: `entity:${entity.id}` })),
])
const sourceItems = computed(() => (activeContact.value?.sources || []).map((source, index) => ({
    title: source.owner.name,
    value: index,
})))
const optionItems = computed(() => {
    const records = new Map()
    for (const contact of [...(props.dict[activeType.value.key] || []), ...searchedOptions.value]) {
        if (contact?.id && contact[activeType.value.field]) records.set(contact.id, contact)
    }
    return [...records.values()].map((contact) => ({ ...contact, title: contact[activeType.value.field] }))
})
const enteredValue = computed(() => typeof selectedContact.value === 'object' && selectedContact.value
    ? selectedContact.value[activeType.value.field]
    : String(selectedContact.value || search.value || '').trim())
const matchedOption = computed(() => {
    if (selectedContact.value && typeof selectedContact.value === 'object') return selectedContact.value
    const key = contactKey(activeType.value.key, enteredValue.value)
    return key ? optionItems.value.find((contact) => contactKey(activeType.value.key, contact[activeType.value.field]) === key) : null
})
const fieldErrors = computed(() => [
    ...(errors.value[activeType.value.field] || []),
    ...(errors.value.contact_id || []),
])
const dialogTitle = computed(() => `${mode.value === 'create' ? 'Добавить' : mode.value === 'edit' ? 'Изменить' : 'Удалить'}: ${activeType.value.singular.toLowerCase()}`)

function ownerPayload() {
    if (mode.value === 'create') return { entity_id: form.owner === 'unit' ? null : Number(form.owner.split(':')[1]) }
    const owner = activeContact.value?.sources[form.source]?.owner
    return { entity_id: owner?.type === 'entity' ? owner.id : null }
}

function openContact(type, action = 'create', contact = null) {
    if (!props.canManage) return
    activeType.value = type
    mode.value = action
    activeContact.value = contact
    form.value = contact?.value || ''
    form.name = contact?.name || ''
    form.owner = 'unit'
    form.source = 0
    form.deleteRecord = false
    selectedContact.value = null
    search.value = ''
    searchedOptions.value = []
    feedback.value = null
    errors.value = {}
    dialog.value = true
    if (action === 'create') loadOptions('')
}

async function loadOptions(value) {
    searchRequest?.abort()
    const controller = new AbortController()
    searchRequest = controller
    const sequence = ++searchSequence
    searching.value = true
    try {
        const { data } = await axios.get(`/api/units/${props.unit.id}/communications/${activeType.value.key}/options`, {
            params: { search: String(value || '').trim() },
            signal: controller.signal,
        })
        if (sequence === searchSequence) searchedOptions.value = data.data || []
    } catch (error) {
        if (!controller.signal.aborted && sequence === searchSequence) {
            feedback.value = { error: true, text: error.response?.data?.message || 'Не удалось загрузить контакты из базы. Можно ввести новый контакт.' }
        }
    } finally {
        if (sequence === searchSequence) searching.value = false
    }
}

watch(search, (value) => {
    clearTimeout(searchTimer)
    if (dialog.value && mode.value === 'create') searchTimer = setTimeout(() => loadOptions(value), 250)
})
watch(dialog, (open) => {
    if (!open) {
        clearTimeout(searchTimer)
        searchRequest?.abort()
        searchSequence++
        searching.value = false
    }
})
onBeforeUnmount(() => { clearTimeout(searchTimer); searchRequest?.abort() })

async function saveContact() {
    if (saving.value || !props.canManage) return
    if (mode.value === 'create' && !enteredValue.value) return
    saving.value = true
    errors.value = {}
    feedback.value = null
    try {
        const url = `/api/units/${props.unit.id}/communications/${activeType.value.key}`
        const payload = ownerPayload()
        if (mode.value === 'create') {
            if (matchedOption.value?.id) payload.contact_id = matchedOption.value.id
            else {
                payload[activeType.value.field] = enteredValue.value
                if (activeType.value.key === 'emails') payload.name = form.name || null
            }
            await axios.post(url, payload)
        } else {
            const source = activeContact.value.sources[form.source]
            if (mode.value === 'edit') {
                payload[activeType.value.field] = form.value
                if (activeType.value.key === 'emails') payload.name = form.name || null
                await axios.put(`${url}/${source.id}`, payload)
            } else {
                await axios.delete(`${url}/${source.id}`, { params: { ...payload, delete_record: form.deleteRecord ? 1 : 0 } })
            }
        }
        dialog.value = false
        emit('refresh')
        feedback.value = { error: false, text: mode.value === 'remove' ? (form.deleteRecord ? 'Контакт удалён.' : 'Связь с контактом удалена.') : 'Контакт сохранён.' }
    } catch (error) {
        errors.value = error.response?.data?.errors || {}
        feedback.value = { error: true, text: error.response?.data?.message || 'Не удалось сохранить контакт.' }
    } finally {
        saving.value = false
    }
}

async function openNewMessage(address = null) {
    if (!props.canSend) return
    await nextTick()
    mailCard.value?.openNewMessage(typeof address === 'string' ? address : null)
}

function openDial(contact) {
    dialContact.value = contact
    feedback.value = null
    dialDialog.value = true
}

async function dial() {
    if (dialing.value || !dialContact.value) return
    dialing.value = true
    feedback.value = null
    try {
        await axios.post('/api/phone-calls/dial', {
            client_phone: String(dialContact.value.value).replace(/\D/g, ''),
            employee_phone: String(employeePhone.value).replace(/\D/g, ''),
        })
        dialDialog.value = false
        feedback.value = { error: false, text: 'Звонок запущен. Ожидайте соединения.' }
        callsCard.value?.refresh()
    } catch (error) {
        feedback.value = { error: true, text: error.response?.data?.message || 'Не удалось запустить звонок.' }
    } finally {
        dialing.value = false
    }
}

function ownerLabel(contact) {
    return [...new Set(contact.sources.map((source) => source.owner.name))].join(' · ')
}

watch(() => props.unit, () => {
    mailCard.value?.refresh()
    callsCard.value?.refresh()
})

defineExpose({ openNewMessage })
</script>

<template>
    <section class="unit-communications">
        <div v-if="feedback && !dialog && !dialDialog" class="communication-feedback" :class="{ 'is-error': feedback.error }" role="status">
            {{ feedback.text }}
            <button type="button" aria-label="Закрыть уведомление" @click="feedback = null">×</button>
        </div>

        <div class="communication-grid">
            <section v-for="type in communicationTypes" :key="type.key" class="communication-column" :aria-label="type.title">
                <header class="communication-heading">
                    <v-icon :icon="type.icon" size="17" />
                    <h3>{{ type.title }}</h3>
                    <span class="communication-count">{{ contacts[type.key].length }}</span>
                    <v-btn v-if="canManage" icon="mdi-plus" size="x-small" variant="text" :aria-label="`Добавить: ${type.singular}`" :title="`Добавить: ${type.singular}`" @click="openContact(type)" />
                </header>
                <div class="communication-list">
                    <article v-for="contact in contacts[type.key]" :key="contact.key" class="communication-contact">
                        <div class="communication-contact__main">
                            <a v-if="type.key === 'uris' && websiteHref(contact.value)" :href="websiteHref(contact.value)" target="_blank" rel="noopener noreferrer" :title="contact.value">{{ contact.value }}</a>
                            <a v-else-if="type.key === 'telephones'" :href="`tel:+${contact.value.replace(/\D/g, '')}`">{{ formatPhone(contact.value) }}</a>
                            <button v-else-if="type.key === 'emails' && canSend" type="button" :title="contact.value" @click="openNewMessage(contact.value)">{{ contact.value }}</button>
                            <span v-else :title="contact.value">{{ contact.value }}</span>
                            <small :title="ownerLabel(contact)">{{ ownerLabel(contact) }}</small>
                        </div>
                        <v-btn v-if="type.key === 'telephones'" icon="mdi-phone-outgoing-outline" size="x-small" variant="text" aria-label="Позвонить через Билайн" title="Позвонить через Билайн" @click="openDial(contact)" />
                        <MaxContactButton v-if="type.key === 'telephones'" :phone="contact.value" :unit-id="unit.id" :entity-id="contact.sources.find(source => source.owner.type === 'entity')?.owner.id || null" :context-title="`${unit.name} · ${ownerLabel(contact)}`" color="#382447" />
                        <v-menu v-if="canManage">
                            <template #activator="{ props: menuProps }">
                                <v-btn v-bind="menuProps" icon="mdi-dots-vertical" size="x-small" variant="text" :aria-label="`Действия: ${contact.value}`" />
                            </template>
                            <v-list density="compact">
                                <v-list-item prepend-icon="mdi-pencil-outline" title="Изменить" @click="openContact(type, 'edit', contact)" />
                                <v-list-item prepend-icon="mdi-link-off" title="Удалить связь / контакт" @click="openContact(type, 'remove', contact)" />
                            </v-list>
                        </v-menu>
                    </article>
                    <p v-if="!contacts[type.key].length" class="communication-empty">Контакты не добавлены</p>
                </div>
            </section>
        </div>

        <div class="communication-history">
            <UnitActivityTabsPanel :unit="unit" :can-view-orders="canViewOrders" :can-create-orders="canCreateOrders" @refresh="emit('refresh')" />
            <UnitSendingsCard :key="`mail-${unit.id}`" ref="mailCard" :unit="unit" :can-send="canSend" />
            <UnitCallsCard :key="`calls-${unit.id}`" ref="callsCard" :unit="unit" />
        </div>

        <v-dialog v-model="dialog" max-width="560" :persistent="saving">
            <v-card class="communication-dialog" rounded="0" elevation="0">
                <v-card-title>{{ dialogTitle }}</v-card-title>
                <v-card-text>
                    <div v-if="feedback?.error" class="communication-feedback is-error mb-4" role="alert">{{ feedback.text }}</div>
                    <v-select v-if="mode === 'create'" v-model="form.owner" :items="ownerItems" label="Принадлежит" variant="outlined" density="compact" :disabled="saving" :error-messages="errors.entity_id || []" />
                    <v-select v-else-if="sourceItems.length > 1" v-model="form.source" :items="sourceItems" label="Связь с владельцем" variant="outlined" density="compact" :disabled="saving" />
                    <v-combobox v-if="mode === 'create'" v-model="selectedContact" v-model:search="search" :items="optionItems" item-title="title" item-value="id" :label="activeType.singular" :placeholder="activeType.placeholder" :loading="searching" :disabled="saving" :error-messages="fieldErrors" hint="Выберите контакт из базы или введите новый." persistent-hint return-object clearable variant="outlined" density="compact" />
                    <v-text-field v-else-if="mode === 'edit'" v-model="form.value" :label="activeType.singular" :error-messages="fieldErrors" :disabled="saving" variant="outlined" density="compact" />
                    <v-text-field v-if="activeType.key === 'emails' && mode !== 'remove' && (mode === 'edit' || !matchedOption)" v-model="form.name" label="Имя контакта" :error-messages="errors.name || []" :disabled="saving" variant="outlined" density="compact" class="mt-3" />
                    <p v-if="mode === 'edit'" class="communication-help">Изменение контакта будет видно всем его владельцам.</p>
                    <template v-if="mode === 'remove'">
                        <p class="mb-2">{{ activeContact?.value }}</p>
                        <p class="communication-help">Удалить связь с {{ sourceItems[form.source]?.title }}. Сам контакт и другие связи сохранятся.</p>
                        <v-checkbox v-model="form.deleteRecord" label="Удалить сам контакт из базы" hide-details density="compact" :disabled="saving" />
                        <p v-if="form.deleteRecord" class="communication-help">Удаление возможно, если контакт не используется другими владельцами. Контакты с историей звонков можно только отвязать.</p>
                    </template>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" :disabled="saving" @click="dialog = false">Отмена</v-btn>
                    <v-btn :color="mode === 'remove' ? '#6b2032' : '#382447'" variant="flat" :loading="saving" :disabled="mode === 'create' ? !enteredValue : mode === 'edit' && !form.value.trim()" @click="saveContact">{{ mode === 'remove' ? (form.deleteRecord ? 'Удалить контакт' : 'Удалить связь') : 'Сохранить' }}</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="dialDialog" max-width="460" :persistent="dialing">
            <v-card class="communication-dialog" rounded="0" elevation="0">
                <v-card-title>Позвонить через Билайн</v-card-title>
                <v-card-text>
                    <div v-if="feedback?.error" class="communication-feedback is-error mb-4" role="alert">{{ feedback.text }}</div>
                    <p class="mb-4">{{ formatPhone(dialContact?.value) }}</p>
                    <v-text-field v-model="employeePhone" label="Ваш номер для соединения" type="tel" variant="outlined" density="compact" :disabled="dialing" hide-details />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn :disabled="dialing" @click="dialDialog = false">Отмена</v-btn>
                    <v-btn variant="flat" color="#382447" :loading="dialing" :disabled="!employeePhone.replace(/\D/g, '')" @click="dial">Позвонить</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </section>
</template>

<style scoped>
.unit-communications { display: flex; flex-direction: column; gap: 14px; width: 100%; height: 100%; min-width: 0; min-height: 0; color: #252329; }
.communication-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); flex-shrink: 0; border: 1px solid #d6d3d9; background: #fff; }
.communication-column { min-width: 0; }
.communication-column + .communication-column { border-left: 1px solid #d6d3d9; }
.communication-heading { display: flex; align-items: center; gap: 8px; height: 42px; padding: 0 12px; border-bottom: 1px solid #e4e2e6; }
.communication-heading h3 { font-size: 12px; font-weight: 700; }
.communication-count { margin-left: auto; color: #79737e; font-size: 11px; font-variant-numeric: tabular-nums; }
.communication-list { max-height: min(200px, 20dvh); overflow-y: auto; }
.communication-contact { display: flex; align-items: center; min-height: 58px; padding: 7px 8px 7px 12px; gap: 2px; }
.communication-contact + .communication-contact { border-top: 1px solid #eeecef; }
.communication-contact__main { display: flex; flex: 1; flex-direction: column; min-width: 0; gap: 3px; }
.communication-contact__main > a, .communication-contact__main > button, .communication-contact__main > span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; text-align: left; font-size: 12px; font-weight: 600; color: #382447; text-decoration: none; }
.communication-contact__main > a:hover, .communication-contact__main > button:hover { text-decoration: underline; }
.communication-contact__main small { color: #77727c; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 10px; }
.communication-empty { display: flex; align-items: center; min-height: 58px; margin: 0; padding: 12px; font-size: 12px; color: #827c86; }
.communication-history { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 2fr) minmax(0, 1fr); flex: 1; min-height: 0; align-items: stretch; gap: 14px; }
.communication-history > * { min-width: 0; min-height: 0; }
.communication-feedback { display: flex; flex-shrink: 0; justify-content: space-between; gap: 12px; padding: 10px 12px; border: 1px solid #d6d3d9; background: #f7f6f8; color: #382447; font-size: 12px; }
.communication-feedback.is-error { border-color: #b98a94; color: #6b2032; }
.communication-feedback button { font-size: 17px; line-height: 1; }
.communication-help { font-size: 12px; line-height: 1.5; color: #77727c; }
.communication-dialog { border: 1px solid #d6d3d9; }
@media (max-width: 1100px), (max-height: 640px) {
    .unit-communications { height: auto; }
    .communication-history { grid-template-columns: minmax(0, 1fr); flex: none; }
}
@media (max-width: 760px) {
    .communication-grid { grid-template-columns: minmax(0, 1fr); }
    .communication-column + .communication-column { border-left: 0; border-top: 1px solid #d6d3d9; }
}
</style>
