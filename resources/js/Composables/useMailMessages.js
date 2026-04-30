import { ref, watch } from 'vue'
import axios from 'axios'

export function useMailMessages() {
    const messages = ref([])
    const totalItems = ref(0)
    const loading = ref(false)
    const reading = ref(false)
    const selectedMessage = ref(null)

    const search = ref('')

    const filters = ref({
        direction: null,
        folder: null,
        email_id: null,
    })

    const options = ref({
        page: 1,
        itemsPerPage: 25,
        sortBy: [],
    })

    async function fetchMessages() {
        loading.value = true

        try {
            const { data } = await axios.get('/api/mail-messages', {
                params: {
                    search: search.value,
                    filters: filters.value,
                    page: options.value.page,
                    itemsPerPage: options.value.itemsPerPage,
                },
            })

            messages.value = data.data ?? []
            totalItems.value = data.total ?? 0
        } catch (error) {
            console.error('Mail messages loading error:', error)
        } finally {
            loading.value = false
        }
    }

    async function readMessage(message, force = false) {
        if (!message?.id) {
            return
        }

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

    let searchTimer = null

    watch(search, () => {
        clearTimeout(searchTimer)

        searchTimer = setTimeout(() => {
            options.value.page = 1
            fetchMessages()
        }, 350)
    })

    watch(filters, () => {
        options.value.page = 1
        fetchMessages()
    }, {
        deep: true,
    })

    watch(options, () => {
        fetchMessages()
    }, {
        deep: true,
    })

    return {
        messages,
        totalItems,
        loading,
        reading,
        selectedMessage,
        search,
        filters,
        options,
        fetchMessages,
        readMessage,
    }
}
