import { onScopeDispose, ref, watch } from 'vue'
import axios from 'axios'

export function useUnitMail(unitId) {
    const messages = ref([])
    const relatedEmails = ref([])
    const totalItems = ref(0)
    const error = ref('')

    const loading = ref(false)
    const sending = ref(false)
    const reading = ref(false)

    const selectedMessage = ref(null)
    const mailboxes = ref([])

    const search = ref('')
    const direction = ref(null)
    const mailbox = ref(null)

    const options = ref({
        page: 1,
        itemsPerPage: 15,
        sortBy: [],
    })
    let messageRequest = null
    let messageSequence = 0
    let readingRequest = null
    let readingSequence = 0

    async function fetchMessages() {
        if (!unitId) return

        messageRequest?.abort()
        const controller = new AbortController()
        messageRequest = controller
        const sequence = ++messageSequence
        loading.value = true
        error.value = ''

        try {
            const { data } = await axios.get(`/api/units/${unitId}/mail-messages`, {
                signal: controller.signal,
                params: {
                    search: search.value,
                    direction: direction.value,
                    mailbox: mailbox.value,
                    page: options.value.page,
                    per_page: options.value.itemsPerPage,
                },
            })

            if (sequence !== messageSequence) return
            messages.value = data.data ?? []
            totalItems.value = data.meta?.total ?? data.total ?? 0
            relatedEmails.value = data.related_emails ?? []
        } catch (failure) {
            if (!controller.signal.aborted && sequence === messageSequence) {
                error.value = failure.response?.data?.message || 'Не удалось загрузить письма. Повторите загрузку.'
            }
        } finally {
            if (sequence === messageSequence) loading.value = false
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
        if (!message?.id) return null

        readingRequest?.abort()
        const controller = new AbortController()
        readingRequest = controller
        const sequence = ++readingSequence
        selectedMessage.value = null
        reading.value = true

        try {
            const { data } = await axios.get(`/api/mail-messages/${message.id}`, {
                signal: controller.signal,
                params: {
                    force,
                },
            })

            if (sequence !== readingSequence) return null
            selectedMessage.value = data
            return data
        } catch (failure) {
            if (!controller.signal.aborted && sequence === readingSequence) {
                error.value = failure.response?.data?.message || 'Не удалось открыть письмо. Повторите загрузку.'
            }
            return null
        } finally {
            if (sequence === readingSequence) reading.value = false
        }
    }

    async function sendMail(payload) {
        sending.value = true

        try {
            if (payload instanceof FormData && !payload.has('idempotency_key')) {
                payload.append('idempotency_key', crypto.randomUUID())
            }

            await axios.post(`/api/units/${unitId}/mail/send`, payload, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            })

            await fetchMessages()
        } catch (error) {
            console.error('Unit mail sending error:', error)
            throw error
        } finally {
            sending.value = false
        }
    }

    let searchTimer = null

    onScopeDispose(() => {
        clearTimeout(searchTimer)
        messageRequest?.abort()
        messageSequence++
        readingRequest?.abort()
        readingSequence++
    })

    watch(search, () => {
        clearTimeout(searchTimer)

        searchTimer = setTimeout(() => {
            options.value.page = 1
            fetchMessages()
        }, 350)
    })

    watch(direction, () => {
        options.value.page = 1
        fetchMessages()
    })

    watch(mailbox, () => {
        options.value.page = 1
        fetchMessages()
    })

    watch(options, () => {
        fetchMessages()
    }, {
        deep: true,
    })

    return {
        messages,
        error,
        relatedEmails,
        totalItems,
        loading,
        sending,
        reading,
        selectedMessage,
        mailboxes,
        search,
        direction,
        mailbox,
        options,
        fetchMailboxes,
        fetchMessages,
        readMessage,
        sendMail,
    }
}
