import { ref, watch } from 'vue'
import axios from 'axios'

export function useUnitMail(unitId) {
    const messages = ref([])
    const relatedEmails = ref([])
    const totalItems = ref(0)

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

    async function fetchMessages() {
        if (!unitId) return

        loading.value = true

        try {
            const { data } = await axios.get(`/api/units/${unitId}/mail-messages`, {
                params: {
                    search: search.value,
                    direction: direction.value,
                    mailbox: mailbox.value,
                    page: options.value.page,
                    per_page: options.value.itemsPerPage,
                },
            })

            messages.value = data.data ?? []
            totalItems.value = data.meta?.total ?? data.total ?? 0
            relatedEmails.value = data.related_emails ?? []
        } catch (error) {
            console.error('Unit mail loading error:', error)
        } finally {
            loading.value = false
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
        if (!message?.id) return

        reading.value = true

        try {
            const { data } = await axios.get(`/api/mail-messages/${message.id}`, {
                params: {
                    force,
                },
            })

            selectedMessage.value = data
        } catch (error) {
            console.error('Mail message reading error:', error)
        } finally {
            reading.value = false
        }
    }

    async function sendMail(payload) {
        sending.value = true

        try {
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
