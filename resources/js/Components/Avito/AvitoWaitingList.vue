<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import axios from 'axios'
import { useAvitoRealtime } from '@/Composables/useAvitoRealtime.js'
import AvitoMessages from './AvitoMessages.vue'

const waiters = ref([])
const pagination = ref({ current_page: 1, last_page: 1, total: 0 })
const search = ref('')
const loading = ref(false)
const error = ref('')
const notice = ref('')
const selectedChat = ref(null)
const editingId = ref(null)
const noteDraft = ref('')
const pendingId = ref(null)
const pickerOpen = ref(false)
const pickerSearch = ref('')
const pickerChats = ref([])
const pickerPagination = ref({ current_page: 1, last_page: 1 })
const pickerLoading = ref(false)
const pickerError = ref('')
const pickerSelected = ref(null)
const addNote = ref('')
let listController = null
let pickerController = null
let mutationController = null
let listVersion = 0
let pickerVersion = 0
let searchTimer = null
let pickerTimer = null
let disposed = false

useAvitoRealtime({
    key: 'waiting-list',
    topics: ['avito_messages'],
    load: (options) => loadWaiters(pagination.value.current_page, options),
})

function messageFrom(exception, fallback) {
    const validation = Object.values(exception.response?.data?.errors || {}).flat()[0]
    return validation || exception.response?.data?.message || fallback
}

async function loadWaiters(page = pagination.value.current_page, options = {}) {
    if (disposed || options.signal?.aborted) return
    listController?.abort()
    const controller = new AbortController()
    listController = controller
    const version = ++listVersion
    const abort = () => controller.abort()
    options.signal?.addEventListener('abort', abort, { once: true })
    loading.value = true
    try {
        const { data } = await axios.get('/api/avito/messenger/waiting-list', {
            params: { search: search.value.trim() || undefined, page, per_page: 10 },
            signal: controller.signal,
        })
        if (disposed || controller.signal.aborted || version !== listVersion) return
        if (page > data.last_page) return await loadWaiters(data.last_page || 1, options)
        waiters.value = data.data || []
        pagination.value = { current_page: data.current_page, last_page: data.last_page, total: data.total }
        error.value = ''
    } catch (exception) {
        if (disposed || controller.signal.aborted || version !== listVersion) return
        error.value = messageFrom(exception, 'Не удалось загрузить лист ожидания.')
        if (options.signal) throw exception
    } finally {
        options.signal?.removeEventListener('abort', abort)
        if (!disposed && version === listVersion) loading.value = false
    }
}

async function loadPicker(page = 1) {
    if (disposed || !pickerOpen.value) return
    pickerController?.abort()
    const controller = new AbortController()
    pickerController = controller
    const version = ++pickerVersion
    pickerLoading.value = true
    pickerError.value = ''
    try {
        const { data } = await axios.get('/api/avito/messenger/chats', {
            params: { search: pickerSearch.value.trim() || undefined, page, per_page: 20 },
            signal: controller.signal,
        })
        if (disposed || controller.signal.aborted || version !== pickerVersion) return
        pickerChats.value = data.data || []
        pickerPagination.value = { current_page: data.current_page, last_page: data.last_page }
    } catch (exception) {
        if (!disposed && !controller.signal.aborted && version === pickerVersion) {
            pickerError.value = messageFrom(exception, 'Не удалось найти чаты.')
        }
    } finally {
        if (!disposed && version === pickerVersion) pickerLoading.value = false
    }
}

function togglePicker() {
    pickerOpen.value = !pickerOpen.value
    pickerSelected.value = null
    addNote.value = ''
    if (pickerOpen.value) {
        editingId.value = null
        void loadPicker()
    } else {
        pickerController?.abort()
        ++pickerVersion
        pickerLoading.value = false
        clearTimeout(pickerTimer)
    }
}

function openChat(chat) {
    selectedChat.value = chat
    pickerOpen.value = false
    pickerController?.abort()
    editingId.value = null
    notice.value = ''
    error.value = ''
}

function startEditing(chat) {
    editingId.value = chat.id
    noteDraft.value = chat.waiting_note || ''
    notice.value = ''
}

async function mutate(chat, method, payload, success) {
    if (!chat || pendingId.value !== null || disposed) return false
    pendingId.value = chat.id
    error.value = ''
    notice.value = ''
    const controller = new AbortController()
    mutationController = controller
    try {
        const { data } = await axios({
            method,
            url: `/api/avito/messenger/chats/${chat.id}/waiting-list`,
            data: payload,
            signal: controller.signal,
        })
        if (disposed || controller.signal.aborted) return false
        if (selectedChat.value?.id === data.chat.id) selectedChat.value = { ...selectedChat.value, ...data.chat }
        pickerChats.value = pickerChats.value.map((item) => item.id === data.chat.id ? data.chat : item)
        notice.value = success
        await loadWaiters()
        return true
    } catch (exception) {
        if (!disposed && !controller.signal.aborted) {
            error.value = messageFrom(exception, 'Не удалось сохранить изменения листа ожидания.')
        }
        return false
    } finally {
        if (!disposed) pendingId.value = null
    }
}

async function addChat() {
    if (await mutate(pickerSelected.value, 'put', { note: addNote.value.trim() || null }, 'Чат добавлен в лист ожидания.')) {
        if (!disposed) togglePicker()
    }
}

async function saveNote(chat) {
    if (await mutate(chat, 'patch', { note: noteDraft.value.trim() || null }, 'Заметка сохранена.')) {
        if (!disposed) editingId.value = null
    }
}

async function removeChat(chat) {
    if (await mutate(chat, 'delete', undefined, 'Чат убран из листа ожидания.')) {
        if (!disposed && editingId.value === chat.id) editingId.value = null
    }
}

function onWaitingChange(chat) {
    if (selectedChat.value?.id === chat.id) selectedChat.value = { ...selectedChat.value, ...chat }
    void loadWaiters()
}

function chatName(chat) {
    return chat.peer_name || chat.title || 'Чат Avito'
}

function accountName(chat) {
    return chat.account?.name || chat.account?.external_user_id || 'Avito'
}

function formatDate(value) {
    const date = new Date(value)
    if (!value || Number.isNaN(date.getTime())) return '—'
    return new Intl.DateTimeFormat('ru-RU', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }).format(date)
}

watch(search, () => {
    clearTimeout(searchTimer)
    listController?.abort()
    ++listVersion
    pagination.value.current_page = 1
    loading.value = true
    searchTimer = setTimeout(() => void loadWaiters(1), 280)
})

watch(pickerSearch, () => {
    clearTimeout(pickerTimer)
    pickerController?.abort()
    ++pickerVersion
    pickerSelected.value = null
    pickerChats.value = []
    pickerLoading.value = pickerOpen.value
    pickerTimer = setTimeout(() => void loadPicker(), 280)
})

onMounted(() => void loadWaiters())
onBeforeUnmount(() => {
    disposed = true
    listController?.abort()
    pickerController?.abort()
    mutationController?.abort()
    clearTimeout(searchTimer)
    clearTimeout(pickerTimer)
})
</script>

<template>
    <section class="waiting-list" aria-labelledby="avito-waiting-title">
        <header class="waiting-list__header">
            <button v-if="selectedChat" type="button" class="waiting-list__icon" title="Вернуться к листу ожидания" aria-label="Вернуться к листу ожидания" @click="selectedChat = null">
                <v-icon icon="mdi-arrow-left" size="18" />
            </button>
            <div class="waiting-list__heading">
                <span class="waiting-list__eyebrow">Avito · ответить позже</span>
                <h2 id="avito-waiting-title">Лист ожидания</h2>
            </div>
            <span class="waiting-list__count" :aria-label="`Чатов в листе ожидания по текущему поиску: ${pagination.total}`">{{ pagination.total }}</span>
            <button v-if="!selectedChat" type="button" class="waiting-list__button waiting-list__button--pink" :aria-expanded="pickerOpen" :disabled="pendingId !== null" @click="togglePicker">
                <v-icon :icon="pickerOpen ? 'mdi-close' : 'mdi-plus'" size="15" />{{ pickerOpen ? 'Закрыть' : 'Добавить' }}
            </button>
        </header>

        <div v-if="error" class="waiting-list__feedback waiting-list__feedback--error" role="alert">
            <span>{{ error }}</span>
            <button type="button" class="waiting-list__icon" title="Закрыть сообщение" aria-label="Закрыть сообщение об ошибке" @click="error = ''"><v-icon icon="mdi-close" size="14" /></button>
        </div>
        <div v-else-if="notice" class="waiting-list__feedback" role="status">
            <span>{{ notice }}</span>
            <button type="button" class="waiting-list__icon" title="Закрыть сообщение" aria-label="Закрыть сообщение" @click="notice = ''"><v-icon icon="mdi-close" size="14" /></button>
        </div>

        <AvitoMessages
            v-if="selectedChat"
            class="waiting-list__conversation"
            embedded
            :chat="selectedChat"
            @waiting-change="onWaitingChange"
            @notice="notice = $event"
            @error="error = $event"
        />

        <template v-else>
            <form v-if="pickerOpen" class="waiting-picker" @submit.prevent="addChat">
                <label class="waiting-list__field">
                    <span>Найти чат для ожидания</span>
                    <input v-model="pickerSearch" type="search" placeholder="Клиент, объявление или сообщение" maxlength="200" :disabled="pendingId !== null">
                </label>
                <div v-if="pickerError" class="waiting-list__feedback waiting-list__feedback--error" role="alert">
                    {{ pickerError }}<button type="button" @click="loadPicker()">Повторить</button>
                </div>
                <div class="waiting-picker__results" :aria-busy="pickerLoading">
                    <p v-if="pickerLoading" class="waiting-list__empty" role="status">Поиск чатов…</p>
                    <template v-else>
                        <button
                            v-for="chat in pickerChats"
                            :key="chat.id"
                            type="button"
                            class="waiting-picker__chat"
                            :class="{ 'is-selected': pickerSelected?.id === chat.id }"
                            :aria-pressed="pickerSelected?.id === chat.id"
                            :disabled="!!chat.waiting_since || pendingId !== null"
                            @click="pickerSelected = chat"
                        >
                            <span><strong>{{ chatName(chat) }}</strong><small>{{ accountName(chat) }} · {{ chat.title || chat.last_message_preview || 'Без объявления' }}</small></span>
                            <em v-if="chat.waiting_since">В ожидании</em>
                            <v-icon v-else-if="pickerSelected?.id === chat.id" icon="mdi-check" size="16" />
                            <v-icon v-else icon="mdi-plus" size="16" />
                        </button>
                        <p v-if="!pickerChats.length && !pickerError" class="waiting-list__empty">Чаты не найдены</p>
                    </template>
                </div>
                <div v-if="pickerPagination.last_page > 1" class="waiting-picker__pagination">
                    <button type="button" :disabled="pickerLoading || pickerPagination.current_page <= 1" @click="loadPicker(pickerPagination.current_page - 1)">Назад</button>
                    <span>{{ pickerPagination.current_page }} / {{ pickerPagination.last_page }}</span>
                    <button type="button" :disabled="pickerLoading || pickerPagination.current_page >= pickerPagination.last_page" @click="loadPicker(pickerPagination.current_page + 1)">Далее</button>
                </div>
                <label class="waiting-list__field">
                    <span>Что нужно уточнить или ответить</span>
                    <textarea v-model="addNote" rows="2" maxlength="2000" placeholder="Заметка (необязательно)" :disabled="pendingId !== null" />
                </label>
                <button type="submit" class="waiting-list__button waiting-list__button--pink" :disabled="!pickerSelected || pendingId !== null">
                    {{ pendingId !== null ? 'Сохранение…' : `В ожидание${pickerSelected ? ': ' + chatName(pickerSelected) : ''}` }}
                </button>
            </form>

            <template v-else>
                <div class="waiting-list__toolbar">
                    <label class="waiting-list__search">
                        <v-icon icon="mdi-magnify" size="16" />
                        <input v-model="search" type="search" aria-label="Поиск в листе ожидания" placeholder="Найти чат или заметку" maxlength="200">
                    </label>
                    <button type="button" class="waiting-list__icon" title="Обновить лист ожидания" aria-label="Обновить лист ожидания" :disabled="loading" @click="loadWaiters()"><v-icon icon="mdi-refresh" size="17" /></button>
                </div>
                <div class="waiting-list__ledger" :aria-busy="loading">
                    <table>
                        <thead><tr><th scope="col">Чат / аккаунт</th><th scope="col">Ожидание / заметка</th><th scope="col"><span class="waiting-list__sr-only">Действия</span></th></tr></thead>
                        <tbody>
                            <template v-for="chat in waiters" :key="chat.id">
                                <tr :class="{ 'is-unread': chat.is_unread }">
                                    <td>
                                        <button type="button" class="waiting-list__chat" :title="`Открыть переписку: ${chatName(chat)}`" @click="openChat(chat)">
                                            <strong><i v-if="chat.is_unread" title="Есть непрочитанные сообщения" aria-label="Есть непрочитанные сообщения" />{{ chatName(chat) }}</strong>
                                            <span :title="chat.last_message_preview">{{ chat.last_message_preview || 'Нет сообщений' }}</span>
                                            <small :title="accountName(chat)">{{ accountName(chat) }}</small>
                                        </button>
                                    </td>
                                    <td>
                                        <time :datetime="chat.waiting_since" :title="`Добавлен в ожидание: ${formatDate(chat.waiting_since)}`">{{ formatDate(chat.waiting_since) }}</time>
                                        <button type="button" class="waiting-list__note" :class="{ 'waiting-list__note--empty': !chat.waiting_note }" :title="chat.waiting_note || 'Добавить заметку'" :disabled="pendingId !== null" @click="startEditing(chat)">{{ chat.waiting_note || '+ Заметка' }}</button>
                                    </td>
                                    <td class="waiting-list__actions">
                                        <button type="button" class="waiting-list__icon" :title="`Ответить: ${chatName(chat)}`" :aria-label="`Открыть переписку: ${chatName(chat)}`" @click="openChat(chat)"><v-icon icon="mdi-message-text-outline" size="16" /></button>
                                        <button type="button" class="waiting-list__icon" :title="`Убрать из ожидания: ${chatName(chat)}`" :aria-label="`Убрать из ожидания: ${chatName(chat)}`" :disabled="pendingId !== null" @click="removeChat(chat)"><v-icon :icon="pendingId === chat.id ? 'mdi-loading' : 'mdi-check'" size="16" /></button>
                                    </td>
                                </tr>
                                <tr v-if="editingId === chat.id" class="waiting-list__edit-row">
                                    <td colspan="3">
                                        <form class="waiting-list__edit" @submit.prevent="saveNote(chat)">
                                            <textarea v-model="noteDraft" rows="2" maxlength="2000" aria-label="Заметка к чату" placeholder="Что нужно уточнить или ответить" :disabled="pendingId !== null" @keydown.esc="editingId = null" />
                                            <div><button type="submit" class="waiting-list__button waiting-list__button--pink" :disabled="pendingId !== null">{{ pendingId === chat.id ? 'Сохранение…' : 'Сохранить' }}</button><button type="button" class="waiting-list__button" :disabled="pendingId !== null" @click="editingId = null">Отмена</button></div>
                                        </form>
                                    </td>
                                </tr>
                            </template>
                            <tr v-if="!waiters.length"><td colspan="3" class="waiting-list__empty"><template v-if="loading">Загрузка…</template><template v-else-if="error">Не удалось загрузить чаты</template><template v-else-if="search">По вашему запросу ничего не найдено</template><template v-else><strong>Чатов в ожидании нет</strong><span>Добавьте чат, к которому нужно вернуться позже.</span></template></td></tr>
                        </tbody>
                    </table>
                </div>
                <footer class="waiting-list__footer">
                    <span role="status">{{ loading ? 'Обновление…' : 'Сначала добавленные раньше' }}</span>
                    <div v-if="pagination.last_page > 1" class="waiting-list__pages">
                        <button type="button" class="waiting-list__icon" :disabled="loading || pagination.current_page <= 1" aria-label="Предыдущая страница ожидания" @click="loadWaiters(pagination.current_page - 1)"><v-icon icon="mdi-chevron-left" size="17" /></button>
                        <span>{{ pagination.current_page }} / {{ pagination.last_page }}</span>
                        <button type="button" class="waiting-list__icon" :disabled="loading || pagination.current_page >= pagination.last_page" aria-label="Следующая страница ожидания" @click="loadWaiters(pagination.current_page + 1)"><v-icon icon="mdi-chevron-right" size="17" /></button>
                    </div>
                </footer>
            </template>
        </template>
    </section>
</template>

<style scoped>
.waiting-list { display: flex; flex-direction: column; min-width: 0; height: 50vh; min-height: 380px; overflow: hidden; border: 1px solid #d4c8ce; border-top: 3px solid #c85988; border-radius: 8px; background: #f1f0f2; color: #343038; }
.waiting-list__header { display: flex; align-items: center; gap: 9px; min-height: 56px; padding: 9px 12px; border-bottom: 1px solid #dcd3d8; background: #ece8ec; }
.waiting-list__heading { flex: 1; min-width: 0; }
.waiting-list__eyebrow { display: block; margin-bottom: 2px; color: #96576f; font-size: 9px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
.waiting-list h2 { margin: 0; color: #753851; font-size: 18px; font-weight: 800; line-height: 1.2; }
.waiting-list__count { display: inline-flex; justify-content: center; min-width: 27px; padding: 3px 6px; border: 1px solid #d9b0c2; border-radius: 4px; background: #f3dce6; color: #8c355a; font: 700 11px "JetBrains Mono", monospace; }
.waiting-list button { cursor: pointer; }
.waiting-list button:disabled { opacity: .5; cursor: default; }
.waiting-list button:focus-visible, .waiting-list input:focus-visible, .waiting-list textarea:focus-visible { outline: 2px solid #b94c7b; outline-offset: 2px; }
.waiting-list__button { display: inline-flex; align-items: center; justify-content: center; gap: 3px; min-height: 28px; max-width: 100%; padding: 4px 8px; border: 1px solid #d2c9ce; border-radius: 4px; background: #f5f3f5; color: #6b5d65; font-size: 11px; font-weight: 700; }
.waiting-list__button--pink { border-color: #d8a6bc; background: #f1cfdf; color: #80314f; }
.waiting-list__button--pink:hover:not(:disabled) { background: #ebbbd0; }
.waiting-list__icon { display: inline-flex; flex: 0 0 auto; align-items: center; justify-content: center; width: 27px; height: 27px; border-radius: 4px; color: #995373; }
.waiting-list__icon:hover:not(:disabled) { background: #eedbe4; color: #762945; }
.waiting-list__toolbar { display: flex; align-items: center; gap: 6px; padding: 7px 10px; border-bottom: 1px solid #dcd5db; }
.waiting-list__search { display: flex; flex: 1; align-items: center; gap: 5px; min-width: 0; padding: 3px 7px; border: 1px solid #d8ced4; border-radius: 4px; background: #faf9fa; color: #9b6e83; }
.waiting-list__search input { width: 100%; min-width: 0; padding: 2px 0; border: 0; background: transparent; color: #40383e; font-size: 11px; }
.waiting-list__ledger { flex: 1; min-height: 0; overflow: auto; }
.waiting-list table { width: 100%; border-collapse: collapse; table-layout: fixed; }
.waiting-list th { position: sticky; top: 0; z-index: 1; padding: 7px 9px; background: #e6e2e7; color: #78626f; text-align: left; font-size: 9px; font-weight: 800; text-transform: uppercase; }
.waiting-list th:first-child { width: 52%; }
.waiting-list th:last-child { width: 36px; }
.waiting-list td { padding: 5px 9px; border-bottom: 1px solid #ded8dd; vertical-align: middle; font-size: 11px; }
.waiting-list tbody tr:hover { background: #f5e6ed; }
.waiting-list__chat { display: block; width: 100%; text-align: left; }
.waiting-list__chat strong, .waiting-list__chat span, .waiting-list__chat small { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.waiting-list__chat strong { color: #443641; font-size: 11px; font-weight: 750; }
.waiting-list__chat span { margin-top: 2px; color: #716b73; font-size: 10px; }
.waiting-list__chat small { margin-top: 2px; color: #95667d; font-size: 9px; }
.waiting-list__chat i { display: inline-block; width: 6px; height: 6px; margin-right: 5px; border-radius: 50%; background: #c4497c; }
.waiting-list tr.is-unread { background: #f4e5ed; }
.waiting-list time { color: #857580; font: 9px "JetBrains Mono", monospace; }
.waiting-list__note { display: -webkit-box; width: 100%; overflow: hidden; margin-top: 4px; color: #6a4054; text-align: left; overflow-wrap: anywhere; -webkit-line-clamp: 2; -webkit-box-orient: vertical; line-height: 1.35; }
.waiting-list__note--empty { color: #9c617d; font-size: 10px; }
.waiting-list td.waiting-list__actions { padding: 3px 4px; }
.waiting-list__empty { padding: 24px 12px !important; color: #847580; text-align: center; font-size: 11px; }
.waiting-list__empty strong, .waiting-list__empty span { display: block; }
.waiting-list__empty strong { margin-bottom: 5px; color: #82516a; font-size: 12px; }
.waiting-list__footer { display: flex; align-items: center; justify-content: space-between; gap: 6px; min-height: 31px; padding: 3px 10px; border-top: 1px solid #d8cfd5; color: #8b7480; font-size: 9px; }
.waiting-list__pages, .waiting-list__feedback { display: flex; align-items: center; justify-content: space-between; gap: 5px; }
.waiting-list__feedback { padding: 4px 10px; border-bottom: 1px solid #dcc4cf; background: #f3e2eb; color: #85445f; font-size: 11px; }
.waiting-list__feedback--error { background: #fae4e8; color: #9b2641; }
.waiting-list__feedback span { flex: 1; }
.waiting-list > .waiting-list__conversation.messenger-module { flex: 1; height: auto; min-height: 0; }
.waiting-picker { display: flex; flex: 1; flex-direction: column; gap: 8px; min-height: 0; overflow-y: auto; padding: 10px; }
.waiting-list__field { display: flex; flex-direction: column; gap: 4px; font-size: 10px; font-weight: 650; }
.waiting-list__field input, .waiting-list textarea { width: 100%; padding: 6px 8px; border: 1px solid #d7c6d0; border-radius: 4px; background: #faf8fa; color: #4b3d47; font-size: 11px; font-weight: 400; }
.waiting-list textarea { resize: vertical; }
.waiting-picker__results { flex: 1 1 110px; min-height: 90px; overflow-y: auto; border: 1px solid #ddd3da; border-radius: 4px; }
.waiting-picker__chat { display: flex; align-items: center; justify-content: space-between; gap: 8px; width: 100%; padding: 6px 8px; border-bottom: 1px solid #ddd3da; color: #6e465b; text-align: left; }
.waiting-picker__chat:last-child { border-bottom: 0; }
.waiting-picker__chat:hover:not(:disabled), .waiting-picker__chat.is-selected { background: #f1d9e5; }
.waiting-picker__chat > span { min-width: 0; }
.waiting-picker__chat strong, .waiting-picker__chat small { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.waiting-picker__chat strong { font-size: 11px; }
.waiting-picker__chat small { margin-top: 2px; color: #8c7883; font-size: 9px; }
.waiting-picker__chat em { flex: 0 0 auto; font-size: 9px; font-style: normal; }
.waiting-picker__pagination { display: flex; justify-content: flex-end; gap: 12px; color: #8a4e6a; font-size: 10px; }
.waiting-list__edit { display: grid; gap: 6px; }
.waiting-list__edit > div { display: flex; gap: 6px; }
.waiting-list__edit-row { background: #ede0e8; }
.waiting-list__sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; }
@media (max-width: 700px) {
    .waiting-list { height: 65vh; min-height: 400px; }
    .waiting-list__header { gap: 6px; padding-inline: 9px; }
    .waiting-list h2 { font-size: 17px; }
}
</style>
