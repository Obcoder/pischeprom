import { ref, watch } from 'vue'
import axios from 'axios'

export function useEmails() {
    const emails = ref([])
    const totalItems = ref(0)
    const loading = ref(false)
    const saving = ref(false)
    const deleting = ref(false)
    const error = ref(null)

    const search = ref('')

    const filters = ref({
        is_active: null,
        domain: null,
        unit_ids: [],
        entity_ids: [],
        has_incoming: false,
        has_outgoing: false,
    })

    const options = ref({
        page: 1,
        itemsPerPage: 25,
        sortBy: [
            {
                key: 'last_received_at',
                order: 'desc',
            },
        ],
    })

    async function fetchEmails() {
        loading.value = true
        error.value = null

        try {
            const { data } = await axios.get('/api/emails', {
                params: {
                    search: search.value,
                    filters: filters.value,
                    page: options.value.page,
                    itemsPerPage: options.value.itemsPerPage,
                    sortBy: options.value.sortBy,
                },
            })

            emails.value = data.data ?? []
            totalItems.value = data.total ?? data.data?.length ?? 0
        } catch (e) {
            error.value = e
            console.error('Emails loading error:', e)
        } finally {
            loading.value = false
        }
    }

    async function storeEmail(payload) {
        saving.value = true

        try {
            await axios.post('/api/emails', payload)
            await fetchEmails()
        } finally {
            saving.value = false
        }
    }

    async function updateEmail(email, payload) {
        saving.value = true

        try {
            await axios.put(`/api/emails/${email.id}`, payload)
            await fetchEmails()
        } finally {
            saving.value = false
        }
    }

    async function deleteEmail(email) {
        deleting.value = true

        try {
            await axios.delete(`/api/emails/${email.id}`)
            await fetchEmails()
        } finally {
            deleting.value = false
        }
    }

    async function syncUnits(email, unitIds) {
        await axios.post(`/api/emails/${email.id}/units/sync`, {
            unit_ids: unitIds,
        })

        await fetchEmails()
    }

    async function syncEntities(email, entityIds) {
        await axios.post(`/api/emails/${email.id}/entities/sync`, {
            entity_ids: entityIds,
        })

        await fetchEmails()
    }

    let searchTimer = null

    watch(search, () => {
        clearTimeout(searchTimer)

        searchTimer = setTimeout(() => {
            options.value.page = 1
            fetchEmails()
        }, 350)
    })

    watch(filters, () => {
        options.value.page = 1
        fetchEmails()
    }, {
        deep: true,
    })

    watch(options, () => {
        fetchEmails()
    }, {
        deep: true,
    })

    return {
        emails,
        totalItems,
        loading,
        saving,
        deleting,
        error,
        search,
        filters,
        options,
        fetchEmails,
        storeEmail,
        updateEmail,
        deleteEmail,
        syncUnits,
        syncEntities,
    }
}
