import { onScopeDispose, ref, watch } from 'vue'
import axios from 'axios'

export function useMailMessages() {
    const messages = ref([])
    const totalItems = ref(0)
    const loading = ref(false)
    const reading = ref(false)
    const selectedMessage = ref(null)
    const mailboxes = ref([])
    const fetchError = ref('')
    const markingReadIds = ref([])
    const markReadError = ref('')
    const markReadStatus = ref('')
    let fetchRequestId = 0
    let readRequestId = 0
    let markReadRevision = 0
    let disposed = false

    const search = ref('')

    const filters = ref({
        direction: null,
        folder: null,
        mailbox: null,
        email_id: null,
        today: false,
        date_from: null,
        date_to: null,
        subject_exact: null,
    })

    const options = ref({
        page: 1,
        itemsPerPage: 100,
        sortBy: [],
    })

    async function fetchMessages() {
        const requestId = ++fetchRequestId
        const readRevision = markReadRevision
        loading.value = true
        fetchError.value = ''

        try {
            const { data } = await axios.get('/api/mail-messages', {
                params: {
                    search: search.value,
                    filters: { ...filters.value },
                    page: options.value.page,
                    itemsPerPage: options.value.itemsPerPage,
                },
            })

            if (disposed || requestId !== fetchRequestId) {
                return
            }

            // A list requested before a successful manual action can contain stale flags.
            if (readRevision !== markReadRevision) {
                return fetchMessages()
            }

            messages.value = data.data ?? []
            totalItems.value = data.total ?? 0
        } catch (error) {
            if (!disposed && requestId === fetchRequestId) {
                fetchError.value = error?.response?.data?.message || 'Не удалось загрузить письма.'
            }
        } finally {
            if (!disposed && requestId === fetchRequestId) {
                loading.value = false
            }
        }
    }

    async function fetchMailboxes() {
        try {
            const { data } = await axios.get('/api/mailboxes')
            mailboxes.value = data.data ?? data ?? []
        } catch (error) {
            console.error('Mailboxes loading error:', error)
            mailboxes.value = []
        }
    }

    async function readMessage(message, force = false) {
        if (!message?.id) {
            return
        }

        const requestId = ++readRequestId
        const readRevision = markReadRevision
        reading.value = true

        try {
            const { data } = await axios.get(`/api/mail-messages/${message.id}`, {
                params: {
                    force,
                },
            })

            if (!disposed && requestId === readRequestId) {
                const current = messages.value.find((item) => String(item.id) === String(message.id))
                const updated = readRevision !== markReadRevision && current
                    ? { ...data, is_seen: current.is_seen }
                    : data
                selectedMessage.value = updated
                messages.value = messages.value.map((item) => String(item.id) === String(message.id)
                    ? { ...item, is_seen: updated.is_seen, body_loaded_at: updated.body_loaded_at }
                    : item)
            }
        } catch (error) {
            console.error('Mail message reading error:', error)
        } finally {
            if (!disposed && requestId === readRequestId) {
                reading.value = false
            }
        }
    }

    async function markMessageRead(message) {
        if (!message?.id || message.is_seen === true || markingReadIds.value.some((id) => String(id) === String(message.id))) {
            return
        }

        markingReadIds.value = [...markingReadIds.value, message.id]
        markReadError.value = ''
        markReadStatus.value = ''

        try {
            const { data } = await axios.post(`/api/mail-messages/${message.id}/mark-read`)

            if (disposed) {
                return
            }

            const updated = data.data ?? data
            markReadRevision++
            messages.value = messages.value.map((item) => String(item.id) === String(message.id)
                ? { ...item, ...updated }
                : item)

            if (String(selectedMessage.value?.id) === String(message.id)) {
                selectedMessage.value = { ...selectedMessage.value, ...updated }
            }

            markReadStatus.value = 'Письмо отмечено прочитанным на сервере.'
        } catch (error) {
            if (!disposed) {
                markReadError.value = error?.response?.data?.message || 'Не удалось отметить письмо прочитанным на сервере.'
            }
        } finally {
            markingReadIds.value = markingReadIds.value.filter((id) => String(id) !== String(message.id))
        }
    }

    function clearSelectedMessage() {
        readRequestId++
        reading.value = false
        selectedMessage.value = null
    }

    let searchTimer = null

    watch(search, () => {
        clearTimeout(searchTimer)

        searchTimer = setTimeout(() => {
            if (options.value.page === 1) {
                fetchMessages()
            } else {
                options.value.page = 1
            }
        }, 350)
    })

    watch(filters, () => {
        if (options.value.page === 1) {
            fetchMessages()
        } else {
            options.value.page = 1
        }
    }, {
        deep: true,
    })

    watch(options, () => {
        fetchMessages()
    }, {
        deep: true,
    })

    onScopeDispose(() => {
        disposed = true
        clearTimeout(searchTimer)
    })

    return {
        messages,
        totalItems,
        loading,
        reading,
        selectedMessage,
        mailboxes,
        fetchError,
        markingReadIds,
        markReadError,
        markReadStatus,
        search,
        filters,
        options,
        fetchMailboxes,
        fetchMessages,
        readMessage,
        markMessageRead,
        clearSelectedMessage,
    }
}
