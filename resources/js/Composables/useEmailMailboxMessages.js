import { ref, watch } from 'vue'
import axios from 'axios'

export function useEmailMailboxMessages() {
    const messages = ref([])
    const totalItems = ref(0)
    const loading = ref(false)
    const search = ref('')
    const direction = ref(null)

    const options = ref({
        page: 1,
        itemsPerPage: 25,
        sortBy: [],
    })

    async function fetchMessages(email) {
        if (!email?.id) {
            messages.value = []
            totalItems.value = 0
            return
        }

        loading.value = true

        try {
            const { data } = await axios.get(`/api/emails/${email.id}/mailbox`, {
                params: {
                    search: search.value,
                    direction: direction.value,
                    page: options.value.page,
                    itemsPerPage: options.value.itemsPerPage,
                },
            })

            messages.value = data.data ?? []
            totalItems.value = data.total ?? 0
        } catch (error) {
            console.error('Email mailbox messages loading error:', error)
        } finally {
            loading.value = false
        }
    }

    function resetMailbox() {
        messages.value = []
        totalItems.value = 0
        search.value = ''
        direction.value = null
        options.value = {
            page: 1,
            itemsPerPage: 25,
            sortBy: [],
        }
    }

    return {
        messages,
        totalItems,
        loading,
        search,
        direction,
        options,
        fetchMessages,
        resetMailbox,
    }
}
