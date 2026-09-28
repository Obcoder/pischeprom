import axios from 'axios'
import { computed, reactive, ref, watch } from 'vue'
import { selectedApartment, selectedBuildingApartments } from '../utils/buildingApartments.js'

export function useOrderDialogEditor({ order, orderId, visible, editable, onSaved = () => {}, client = axios }) {
    const editing = ref(false)
    const editingDate = ref(false)
    const loadingOptions = ref(false)
    const saving = ref(false)
    const savingDate = ref(false)
    const errors = ref({})
    const error = ref('')
    const success = ref('')
    const stale = ref(false)
    const dateDraft = ref('')
    const form = reactive(emptyForm())
    const options = reactive(emptyOptions())
    const optionsReady = ref(false)
    let lineKey = 0
    let generation = 0
    let optionsController = null
    const savedContent = ref('')
    const savedDate = ref('')
    const savedSubmittedAt = ref(null)
    const savedSubmittedLocal = ref('')
    let savedVersion = null
    let disposed = false

    const currentOrder = computed(() => Boolean(visible() && order.value?.id)
        && String(order.value.id) === String(orderId()))
    const canEdit = computed(() => currentOrder.value && editable() !== false
        && !order.value.shipped_at && (order.value.permissions?.edit ?? true) === true)
    const canEditDelivery = computed(() => currentOrder.value && editable() !== false
        && order.value.permissions?.delivery_edit === true)
    const busy = computed(() => saving.value || savingDate.value)
    const dateDirty = computed(() => (editing.value ? form.delivery_date || '' : dateDraft.value || '') !== savedDate.value)
    const dirty = computed(() => (editing.value && (JSON.stringify(contentPayload()) !== savedContent.value
        || (canEditDelivery.value && dateDirty.value))) || (editingDate.value && dateDirty.value))
    const total = computed(() => form.items.reduce((sum, item) => sum + itemTotal(item), 0))
    const weight = computed(() => form.items.reduce((sum, item) => {
        const quantity = Number(item.quantity)
        const denominator = Number(goodById(item.good_id)?.denominator)
        return sum + (Number.isFinite(quantity) && Number.isFinite(denominator) ? quantity * denominator : 0)
    }, 0))

    function emptyForm() {
        return {
            number: '', entity_id: null, order_status_id: null, building_ids: [], building_apartments: {},
            currency_code: 'RUB', submitted_at: '', delivery_date: '', preferred_delivery_time: '',
            internal_comment: '', items: [],
        }
    }

    function emptyOptions() {
        return { statuses: [], entities: [], buildings: [], goods: [], currency_codes: ['RUB'] }
    }

    function clearMessages() {
        errors.value = {}
        error.value = ''
        success.value = ''
    }

    function cancelOptions() {
        optionsController?.abort()
        optionsController = null
        loadingOptions.value = false
    }

    function reset() {
        generation += 1
        cancelOptions()
        editing.value = false
        editingDate.value = false
        saving.value = false
        savingDate.value = false
        stale.value = false
        optionsReady.value = false
        dateDraft.value = ''
        savedContent.value = ''
        savedDate.value = ''
        savedSubmittedAt.value = null
        savedSubmittedLocal.value = ''
        savedVersion = null
        Object.assign(form, emptyForm())
        Object.assign(options, emptyOptions())
        clearMessages()
    }

    function makeLine(source = {}) {
        return {
            _key: ++lineKey,
            id: source.id ?? null,
            good_id: source.good_id ?? source.good?.id ?? null,
            quantity: source.quantity ?? 1,
            unit_price: Object.hasOwn(source, 'unit_price') ? source.unit_price : source.price_gross ?? null,
        }
    }

    function fillForm(source) {
        savedSubmittedAt.value = source.submitted_at || null
        savedSubmittedLocal.value = toDateTimeLocal(source.submitted_at)
        Object.assign(form, {
            number: source.number || '',
            entity_id: source.entity_id ?? source.entity?.id ?? null,
            order_status_id: source.order_status_id ?? source.status?.id ?? null,
            building_ids: (source.buildings || []).map(building => building.id),
            building_apartments: Object.fromEntries((source.buildings || []).map(building => [
                building.id, selectedApartment(building)?.id ?? building.apartment_id ?? building.pivot?.apartment_id ?? null,
            ])),
            currency_code: source.currency_code || 'RUB',
            submitted_at: savedSubmittedLocal.value,
            delivery_date: source.delivery_date || '',
            preferred_delivery_time: source.preferred_delivery_time || '',
            internal_comment: source.internal_comment || '',
            items: (source.items || []).map(makeLine),
        })
        savedContent.value = JSON.stringify(contentPayload())
        savedDate.value = source.delivery_date || ''
        savedVersion = source.delivery_version
        dateDraft.value = savedDate.value
    }

    function toDateTimeLocal(value) {
        if (!value) return ''
        const date = new Date(value)
        if (Number.isNaN(date.getTime())) return String(value).slice(0, 16)
        return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16)
    }

    function contentPayload() {
        return {
            number: form.number || null,
            entity_id: form.entity_id,
            order_status_id: form.order_status_id,
            building_ids: [...form.building_ids],
            building_apartments: selectedBuildingApartments(form.building_ids, form.building_apartments),
            currency_code: form.currency_code,
            submitted_at: submittedAtPayload(),
            preferred_delivery_time: form.preferred_delivery_time || null,
            internal_comment: form.internal_comment || null,
            items: form.items.map(item => ({
                good_id: item.good_id,
                quantity: item.quantity,
                unit_price: item.unit_price === '' ? null : item.unit_price,
            })),
        }
    }

    function submittedAtPayload() {
        // The field displays local minutes; preserve the server timestamp until it changes.
        if (form.submitted_at === savedSubmittedLocal.value) return savedSubmittedAt.value
        if (!form.submitted_at) return null
        const date = new Date(form.submitted_at)
        return Number.isNaN(date.getTime()) ? form.submitted_at : date.toISOString()
    }

    function context() {
        const token = generation
        const id = orderId()
        return {
            id,
            isCurrent: () => !disposed && token === generation && visible()
                && String(orderId()) === String(id) && String(order.value?.id) === String(id),
        }
    }

    async function loadOptions() {
        cancelOptions()
        const request = context()
        const controller = new AbortController()
        optionsController = controller
        loadingOptions.value = true
        optionsReady.value = false
        const isCurrent = () => request.isCurrent() && editing.value
            && optionsController === controller && !controller.signal.aborted
        try {
            const { data } = await client.get('/api/orders/options', { signal: controller.signal })
            if (!isCurrent()) return
            Object.assign(options, {
                statuses: data.statuses || [],
                entities: data.entities || [],
                buildings: data.buildings || [],
                goods: data.goods || [],
                currency_codes: data.currency_codes?.length ? data.currency_codes : ['RUB'],
            })
            optionsReady.value = true
        } catch (failure) {
            if (!isCurrent() || axios.isCancel(failure)) return
            error.value = 'Не удалось загрузить поля заказа. Попробуйте ещё раз.'
        } finally {
            if (isCurrent()) {
                loadingOptions.value = false
                optionsController = null
            }
        }
    }

    async function beginEdit() {
        if (disposed || !canEdit.value || busy.value || stale.value || editingDate.value) return
        clearMessages()
        if (!editing.value) fillForm(order.value)
        editing.value = true
        await loadOptions()
    }

    function cancelEdit() {
        if (busy.value) return false
        cancelOptions()
        editing.value = false
        if (!stale.value) clearMessages()
        return true
    }

    function beginDateEdit() {
        if (disposed || !canEditDelivery.value || busy.value || stale.value || editing.value) return
        clearMessages()
        savedDate.value = order.value.delivery_date || ''
        savedVersion = order.value.delivery_version
        dateDraft.value = savedDate.value
        editingDate.value = true
    }

    function cancelDateEdit() {
        if (busy.value) return false
        editingDate.value = false
        dateDraft.value = savedDate.value
        if (!stale.value) clearMessages()
        return true
    }

    function saveFailure(failure, dateOnly) {
        errors.value = failure.response?.data?.errors || {}
        const status = failure.response?.status
        stale.value = status === 409 || !failure.response || status >= 500
        error.value = status === 409
            ? 'Заказ изменился. Обновите карточку и проверьте данные перед сохранением.'
            : stale.value
                ? 'Не удалось подтвердить сохранение. Обновите карточку, чтобы проверить данные на сервере.'
                : failure.response?.data?.message || (dateOnly ? 'Не удалось сохранить дату доставки.' : 'Не удалось сохранить заказ.')
    }

    async function persist(dateOnly, dateValue = '') {
        const request = context()
        const flag = dateOnly ? savingDate : saving
        flag.value = true
        clearMessages()
        try {
            const response = dateOnly
                ? await client.patch(`/api/orders/${request.id}/delivery-date`, {
                    version: savedVersion,
                    delivery_date: dateValue || null,
                })
                : await client.put(`/api/orders/${request.id}`, {
                    ...contentPayload(),
                    ...(canEditDelivery.value ? {
                        delivery_date: form.delivery_date || null,
                        delivery_version: savedVersion,
                    } : {}),
                })
            if (!request.isCurrent()) return
            const savedOrder = response.data?.data
            if (!savedOrder?.id || String(savedOrder.id) !== String(request.id)) throw new Error('Invalid saved order')
            order.value = savedOrder
            fillForm(savedOrder)
            editing.value = false
            editingDate.value = false
            success.value = dateOnly ? 'Дата доставки сохранена.' : 'Заказ сохранён.'
            onSaved(savedOrder)
        } catch (failure) {
            if (request.isCurrent()) saveFailure(failure, dateOnly)
        } finally {
            if (request.isCurrent()) flag.value = false
        }
    }

    async function saveOrder() {
        if (!editing.value || !canEdit.value || busy.value || loadingOptions.value || !optionsReady.value || stale.value || disposed) return
        if (!dirty.value) {
            cancelEdit()
            return
        }
        if (canEditDelivery.value && dateDirty.value && JSON.stringify(contentPayload()) === savedContent.value) {
            await persist(true, form.delivery_date)
            return
        }
        await persist(false)
    }

    async function saveDate() {
        if (!editingDate.value || !canEditDelivery.value || busy.value || stale.value || !dateDirty.value || disposed) return
        await persist(true, dateDraft.value)
    }

    function addItem() {
        if (!editing.value || busy.value || stale.value) return
        form.items.push(makeLine())
    }

    function removeItem(index) {
        if (!editing.value || busy.value || stale.value || index < 0 || index >= form.items.length) return
        if (form.items.length === 1) form.items[0] = makeLine()
        else form.items.splice(index, 1)
    }

    function goodById(id) {
        return options.goods.find(good => Number(good.id) === Number(id))
            || order.value?.items?.find(item => Number(item.good_id) === Number(id))
    }

    function itemTotal(item) {
        const quantity = Number(item.quantity)
        const price = Number(item.unit_price)
        return Number.isFinite(quantity) && Number.isFinite(price) ? quantity * price : 0
    }

    const stopWatching = watch(() => [visible(), orderId()], reset, { flush: 'sync' })

    function dispose() {
        disposed = true
        stopWatching()
        reset()
    }

    return {
        editing, editingDate, form, options, optionsReady, loadingOptions, saving, savingDate, errors, error, success,
        stale, dirty, dateDirty, canEdit, canEditDelivery, dateDraft, beginEdit, cancelEdit, saveOrder,
        beginDateEdit, cancelDateEdit, saveDate, reset, dispose, addItem, removeItem, total, weight,
        itemTotal, goodById,
    }
}
