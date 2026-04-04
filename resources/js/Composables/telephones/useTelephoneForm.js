import { computed, reactive, ref } from 'vue'
import { useTelephonesApi } from './useTelephonesApi'

function getDefaultForm() {
    return {
        id: null,
        number: '',
        entity_ids: [],
        unit_ids: [],
    }
}

export function useTelephoneForm(onSaved = null) {
    const api = useTelephonesApi()

    const dialog = ref(false)
    const saving = ref(false)
    const errors = ref({})

    const form = reactive(getDefaultForm())

    const isEdit = computed(() => !!form.id)

    const setForm = (payload) => {
        form.id = payload?.id ?? null
        form.number = payload?.number ?? ''
        form.entity_ids = Array.isArray(payload?.entity_ids) ? [...payload.entity_ids] : []
        form.unit_ids = Array.isArray(payload?.unit_ids) ? [...payload.unit_ids] : []
    }

    const resetForm = () => {
        setForm(getDefaultForm())
        errors.value = {}
    }

    const openCreate = () => {
        resetForm()
        dialog.value = true
    }

    const openEdit = (item) => {
        setForm({
            id: item.id,
            number: item.number,
            entity_ids: item.entities?.map((x) => x.id) || [],
            unit_ids: item.units?.map((x) => x.id) || [],
        })

        errors.value = {}
        dialog.value = true
    }

    const close = () => {
        dialog.value = false
    }

    const submit = async () => {
        saving.value = true
        errors.value = {}

        const payload = {
            number: form.number,
            entity_ids: form.entity_ids,
            unit_ids: form.unit_ids,
        }

        try {
            if (isEdit.value) {
                await api.updateItem(form.id, payload)
            } else {
                await api.createItem(payload)
            }

            dialog.value = false

            if (typeof onSaved === 'function') {
                await onSaved()
            }
        } catch (error) {
            if (error.response?.status === 422) {
                errors.value = error.response.data.errors || {}
            } else {
                console.error(error)
            }
        } finally {
            saving.value = false
        }
    }

    return {
        dialog,
        saving,
        errors,
        form,
        isEdit,
        setForm,
        resetForm,
        openCreate,
        openEdit,
        close,
        submit,
    }
}
