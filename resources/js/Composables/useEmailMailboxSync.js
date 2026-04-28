import { ref } from 'vue'
import axios from 'axios'

export function useEmailMailboxSync() {
    const syncing = ref(false)

    async function syncYandex(limit = 1000) {
        syncing.value = true

        try {
            await axios.post('/api/emails/sync-yandex', {
                limit,
            })
        } catch (error) {
            console.error('Yandex mailbox sync error:', error)
            throw error
        } finally {
            syncing.value = false
        }
    }

    return {
        syncing,
        syncYandex,
    }
}
