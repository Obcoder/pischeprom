import { computed, onBeforeUnmount, ref, watch } from 'vue'

/** Each picker owns its search so form and table filters cannot replace one another's results. */
export function useCheckEntityLookup({ get, url, selectedId, delay = 250 }) {
    const search = ref('')
    const results = ref([])
    const selected = ref(null)
    const loading = ref(false)
    const error = ref('')
    const currentPage = ref(0)
    const lastPage = ref(0)
    const items = computed(() => {
        const rows = [...results.value]
        if (selected.value && !rows.some(item => String(item.id) === String(selected.value.id))) {
            rows.unshift(selected.value)
        }
        return rows
    })
    const hasMore = computed(() => currentPage.value < lastPage.value)
    let timer
    let generation = 0
    let controller
    let selectionController
    let selectionGeneration = 0
    let disposed = false

    function remember(entity) {
        if (entity?.id) {
            selected.value = entity
            results.value = results.value.map(item => String(item.id) === String(entity.id) ? entity : item)
        }
    }

    function invalidate() {
        generation += 1
        clearTimeout(timer)
        controller?.abort()
        loading.value = false
    }

    async function load(page = 1) {
        if (disposed) return
        invalidate()
        const request = generation
        controller = new AbortController()
        loading.value = true
        error.value = ''
        try {
            const response = await get(url(), {
                params: { search: String(search.value || '').trim(), page, per_page: 50 },
                signal: controller.signal,
            })
            if (request !== generation) return
            const body = response.data
            if (!Array.isArray(body?.data)) throw new Error('Некорректный ответ справочника.')
            results.value = page === 1 ? body.data : [...results.value, ...body.data]
            currentPage.value = Number(body.meta?.current_page || page)
            lastPage.value = Number(body.meta?.last_page || page)
            const option = results.value.find(item => String(item.id) === String(selectedId.value))
            if (option) remember(option)
        } catch (reason) {
            if (request !== generation) return
            error.value = 'Не удалось загрузить Entities. Повторите поиск.'
        } finally {
            if (request === generation) loading.value = false
        }
    }

    watch(search, () => {
        invalidate()
        results.value = []
        currentPage.value = 0
        lastPage.value = 0
        error.value = ''
        timer = setTimeout(() => load(), delay)
    }, { flush: 'sync' })

    watch(selectedId, async id => {
        const request = ++selectionGeneration
        selectionController?.abort()
        if (!id) {
            selected.value = null
            return
        }
        const option = items.value.find(item => String(item.id) === String(id))
        if (option) {
            remember(option)
            return
        }
        selected.value = null
        selectionController = new AbortController()
        try {
            const response = await get(url(), {
                params: { ids: [id] },
                signal: selectionController.signal,
            })
            if (request === selectionGeneration && !disposed) remember(response.data?.data?.[0])
        } catch (reason) {
            if (request === selectionGeneration && !disposed) error.value = 'Не удалось загрузить выбранный Entity.'
        }
    }, { flush: 'sync' })

    onBeforeUnmount(() => {
        disposed = true
        invalidate()
        selectionGeneration += 1
        selectionController?.abort()
    })

    return {
        search, items, loading, error, hasMore, remember,
        refresh: () => load(),
        loadMore: () => !loading.value && hasMore.value ? load(currentPage.value + 1) : undefined,
    }
}
