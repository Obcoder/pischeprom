import axios from 'axios'
import { ref } from 'vue'

export function useOrderQuickEdit({ onSaved = () => {}, client = axios } = {}) {
    const saving = ref({})
    const errors = ref({})
    const stale = ref({})
    const success = ref('')
    let disposed = false

    function available(order, reload = false) {
        return !disposed && Boolean(order?.id) && !saving.value[order.id]
            && (reload || !stale.value[order.id])
    }

    function orderLabel(order) {
        return `Заказ ${order.number || `№ ${order.id}`}`
    }

    function failureMessage(failure, fallback) {
        const fields = failure?.response?.data?.errors
        const messages = fields && typeof fields === 'object'
            ? Object.values(fields).flat().filter(message => typeof message === 'string')
            : []
        return messages.join(' ') || failure?.response?.data?.message || fallback
    }

    async function persist(order, action, request) {
        const id = order.id
        saving.value[id] = action
        delete errors.value[id]
        success.value = ''

        try {
            const response = await request()
            if (disposed) return false
            const savedOrder = response?.data?.data
            if (!savedOrder?.id || String(savedOrder.id) !== String(id)) {
                throw new Error('Invalid saved order')
            }

            onSaved(savedOrder)
            delete stale.value[id]
            success.value = `${orderLabel(savedOrder)}: ${action === 'date'
                ? 'дата доставки сохранена.'
                : action === 'status' ? 'статус изменён.' : 'данные обновлены.'}`
            return true
        } catch (failure) {
            if (disposed) return false
            const status = failure?.response?.status
            if (action === 'reload') {
                errors.value[id] = failureMessage(failure, 'Не удалось обновить заказ. Попробуйте ещё раз.')
            } else {
                const uncertain = status === 409 || status === 408 || !failure?.response || status >= 500
                if (uncertain) stale.value[id] = true
                errors.value[id] = status === 409
                    ? 'Заказ изменился. Обновите строку и проверьте данные перед сохранением.'
                    : uncertain
                        ? 'Не удалось подтвердить сохранение. Обновите строку, чтобы проверить данные на сервере.'
                        : failureMessage(failure, action === 'date'
                            ? 'Не удалось сохранить дату доставки.'
                            : 'Не удалось изменить статус заказа.')
            }
            return false
        } finally {
            if (!disposed) delete saving.value[id]
        }
    }

    async function saveDate(order, value) {
        if (!available(order) || order.permissions?.delivery_edit !== true
            || (value || null) === (order.delivery_date || null)) return false

        return persist(order, 'date', () => client.patch(`/api/orders/${order.id}/delivery-date`, {
            delivery_date: value || null,
            version: order.delivery_version,
        }))
    }

    async function saveStatus(order, statusId) {
        const currentStatusId = order?.order_status_id ?? order?.status?.id ?? null
        const nextStatusId = Number(statusId)
        if (!available(order) || order.permissions?.edit !== true || order.shipped_at || order.shipped_sale_id
            || !Number.isSafeInteger(nextStatusId) || nextStatusId <= 0
            || nextStatusId === Number(currentStatusId)) return false

        return persist(order, 'status', () => client.patch(`/api/orders/${order.id}/status`, {
            order_status_id: nextStatusId,
            expected_order_status_id: currentStatusId,
        }))
    }

    async function reload(order) {
        if (!available(order, true)) return false
        return persist(order, 'reload', () => client.get(`/api/orders/${order.id}`))
    }

    function dispose() {
        disposed = true
        saving.value = {}
        errors.value = {}
        stale.value = {}
        success.value = ''
    }

    return { saving, errors, stale, success, saveDate, saveStatus, reload, dispose }
}
