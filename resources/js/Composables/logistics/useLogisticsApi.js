import axios from 'axios'
import { computed, reactive, ref } from 'vue'

export function useLogisticsApi() {
    const pending = reactive(new Set())
    const fieldErrors = ref({})
    const snackbar = reactive({
        open: false,
        text: '',
        color: 'success',
    })

    const loading = computed(() => pending.size > 0)

    function notify(text, color = 'success') {
        snackbar.text = text
        snackbar.color = color
        snackbar.open = true
    }

    function errorMessage(error, fallback = 'Не удалось выполнить запрос.') {
        return error?.response?.data?.message || fallback
    }

    async function request(key, config, options = {}) {
        if (pending.has(key)) {
            return null
        }

        pending.add(key)
        fieldErrors.value = {}

        try {
            const response = await axios(config)
            if (options.success) {
                notify(options.success)
            }

            return response.data
        } catch (error) {
            fieldErrors.value = error?.response?.data?.errors || {}
            notify(errorMessage(error, options.error), 'error')
            throw error
        } finally {
            pending.delete(key)
        }
    }

    function isPending(key) {
        return pending.has(key)
    }

    function firstError(key) {
        const errors = fieldErrors.value?.[key]

        return Array.isArray(errors) ? errors[0] : errors || ''
    }

    return {
        loading,
        snackbar,
        fieldErrors,
        request,
        notify,
        errorMessage,
        isPending,
        firstError,
    }
}
