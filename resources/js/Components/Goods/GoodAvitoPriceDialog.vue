<script setup>
import { computed, onScopeDispose, ref, watch } from 'vue'
import axios from 'axios'
import { avitoError, avitoMoney } from '@/Services/goodAvito'

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    goodId: { type: [Number, String], required: true },
    priceValueId: { type: [Number, String], default: null },
    linkId: { type: [Number, String], default: null },
})
const emit = defineEmits(['update:modelValue', 'applied', 'state', 'request-avito'])
const context = ref(null), status = ref(null), loading = ref(false), preparing = ref(false), applying = ref(false)
const error = ref(''), notice = ref(''), selectedLink = ref(null), selectedPrice = ref(null)
const preview = ref(null), confirmed = ref(false)
let generation = 0, previewVersion = 0
const base = () => `/api/avito/goods/${props.goodId}`
const links = computed(() => context.value?.links || [])
const prices = computed(() => (context.value?.good?.prices || []).filter(price => price.is_public))
const unit = computed(() => preview.value?.price?.unit_label || preview.value?.price?.measurement?.unit_label || context.value?.measurement?.unit_label || context.value?.good?.measure?.name || 'единицу товара')
const busy = computed(() => loading.value || preparing.value || applying.value)
const ready = computed(() => Boolean(status.value?.enabled && status.value?.mutations_enabled && status.value?.configured))
const canPreview = computed(() => ready.value && selectedLink.value && selectedPrice.value && !busy.value)
const canApply = computed(() => Boolean(ready.value && confirmed.value && preview.value?.price?.can_apply && preview.value?.source_fingerprint && !busy.value))
const body = () => ({ fields: ['price'], price_value_id: Number(selectedPrice.value) })

function invalidate() { previewVersion++; preview.value = null; confirmed.value = false; notice.value = '' }
function close() { if (!busy.value) emit('update:modelValue', false) }
async function load() {
    const version = ++generation
    invalidate(); context.value = null; status.value = null; error.value = ''; loading.value = true
    try {
        const [good, connection] = await Promise.all([axios.get(base()), axios.get('/api/avito/status')])
        if (version !== generation) return
        context.value = good.data; status.value = connection.data
        selectedLink.value = props.linkId ? links.value.find(link => Number(link.id) === Number(props.linkId))?.id || null : (links.value.length === 1 ? links.value[0].id : null)
        selectedPrice.value = props.priceValueId ? prices.value.find(price => Number(price.id) === Number(props.priceValueId))?.id || null : (prices.value.length === 1 ? prices.value[0].id : null)
        if (props.priceValueId && !prices.value.some(price => Number(price.id) === Number(props.priceValueId))) error.value = 'Эта цена больше недоступна для Авито. Обновите вкладку «Цены».'
        if (props.linkId && !selectedLink.value) error.value = 'Связь с выбранным объявлением больше недоступна. Обновите вкладку «Авито».'
    } catch (failure) { if (version === generation) error.value = avitoError(failure) }
    finally { if (version === generation) loading.value = false }
}
async function prepare() {
    if (!canPreview.value) return
    invalidate(); error.value = ''; preparing.value = true
    const version = generation, requestVersion = previewVersion
    try {
        const { data } = await axios.post(`${base()}/links/${selectedLink.value}/preview`, body())
        if (version !== generation || requestVersion !== previewVersion) return
        preview.value = data.preview
    } catch (failure) { if (version === generation && requestVersion === previewVersion) error.value = avitoError(failure) }
    finally { if (version === generation) preparing.value = false }
}
async function apply() {
    if (!canApply.value) return
    const version = generation
    applying.value = true; error.value = ''
    try {
        const { data } = await axios.post(`${base()}/links/${selectedLink.value}/apply`, {
            ...body(), expected_fingerprint: preview.value.source_fingerprint, confirmed: true,
        })
        if (version !== generation) return
        notice.value = `Цена ${avitoMoney(data.transfer?.price?.avito_value ?? preview.value.price.avito_value)} передана в Авито.`
        if (data.transfer?.link_changed) notice.value += ' Связь объявления с товаром изменилась. Обновите карточку.'
        preview.value = null; confirmed.value = false
        emit('applied', data)
    } catch (failure) {
        if (version !== generation) return
        error.value = failure.response ? avitoError(failure) : 'Связь прервалась; результат передачи неизвестен. Проверьте цену в Авито перед новой попыткой.'; invalidate()
    } finally { if (version === generation) applying.value = false }
}
watch([selectedLink, selectedPrice], invalidate)
watch(busy, value => emit('state', { dirty: false, busy: value }), { immediate: true })
watch(() => [props.modelValue, props.goodId, props.priceValueId, props.linkId], ([open]) => {
    generation++; preparing.value = false; applying.value = false; loading.value = false
    if (open) load(); else { invalidate(); context.value = null; status.value = null }
}, { immediate: true })
onScopeDispose(() => { generation++; previewVersion++ })
</script>

<template>
    <v-dialog :model-value="modelValue" :persistent="busy" max-width="640" @update:model-value="value => !value && close()">
        <section class="avito-price-dialog" aria-label="Передать цену товара в Авито">
            <header><div><small>Синхронизация с Авито</small><h3>Передать цену в объявление</h3></div><button :disabled="busy" aria-label="Закрыть передачу цены" @click="close">×</button></header>
            <div class="dialog-body">
                <p v-if="loading" role="status">Загрузка связей и сохранённых цен…</p>
                <p v-if="error" class="error" role="alert">{{ error }}</p>
                <p v-if="notice" class="success" role="status">{{ notice }}</p>
                <template v-if="context && !loading">
                    <p v-if="!ready" class="hint">Передача недоступна: проверьте подключение и разрешение изменений в настройках Авито.</p>
                    <template v-if="!links.length"><p class="empty">Сначала свяжите этот товар с объявлением во вкладке «Авито».</p><button @click="close(); emit('request-avito')">Открыть вкладку «Авито»</button></template>
                    <p v-else-if="!prices.length" class="empty">Нет общедоступной сохранённой цены. Назначьте её во вкладке «Цены».</p>
                    <template v-else>
                        <label>Объявление<select v-model="selectedLink" :disabled="busy"><option :value="null" disabled>Выберите объявление</option><option v-for="link in links" :key="link.id" :value="link.id">№ {{ link.avito_item_id }} · аккаунт {{ link.avito_account_id }}</option></select></label>
                        <label>Сохранённая цена<select v-model="selectedPrice" :disabled="busy"><option :value="null" disabled>Выберите цену</option><option v-for="price in prices" :key="price.id" :value="price.id">{{ price.name }} · {{ avitoMoney(price.amount, price.currency_code) }} / {{ context.measurement?.unit_label || 'ед.' }}</option></select></label>
                        <p class="hint">Передаётся цена с НДС за 1 {{ context.measurement?.unit_label || 'единицу товара' }}. Проверьте, что в объявлении указана та же единица.</p>
                        <button :disabled="!canPreview" @click="prepare">{{ preparing ? 'Подготовка…' : 'Проверить перед передачей' }}</button>
                        <div v-if="preview" class="preview">
                            <table><tbody><tr><th>В приложении · {{ preview.price?.price_type }}</th><td>{{ avitoMoney(preview.price?.good_value, preview.price?.currency_code) }}</td></tr><tr><th>Авито сейчас</th><td>{{ preview.price?.avito_current_value == null ? 'API не вернул значение' : avitoMoney(preview.price.avito_current_value) }}</td></tr><tr class="total"><th>Будет в Авито · за 1 {{ unit }}</th><td>{{ avitoMoney(preview.price?.avito_value) }}</td></tr></tbody></table>
                            <p v-for="warning in preview.warnings || []" :key="warning" class="hint">{{ warning }}</p>
                            <label class="confirmation"><input v-model="confirmed" type="checkbox" :disabled="busy || !preview.price?.can_apply">Подтверждаю цену {{ avitoMoney(preview.price?.avito_value) }} за 1 {{ unit }} для объявления № {{ links.find(link => Number(link.id) === Number(selectedLink))?.avito_item_id }}.</label>
                        </div>
                    </template>
                </template>
            </div>
            <footer><button :disabled="busy" @click="close">Закрыть</button><button class="primary" :disabled="!canApply" @click="apply">{{ applying ? 'Передача…' : 'Передать цену в Авито' }}</button></footer>
        </section>
    </v-dialog>
</template>

<style scoped>
.avito-price-dialog { overflow: hidden; border: 1px solid #ded9e5; border-radius: 12px; background: #fff; color: #39333f; font: 12px/1.5 -apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; }
header, footer { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 18px; background: #f7f5fa; } header { border-bottom: 1px solid #e5e0ea; } header h3 { margin: 0; font-size: 17px; } header small { color: #8c8398; } header > button { font-size: 20px; padding: 0 8px; } footer { justify-content: flex-end; border-top: 1px solid #e5e0ea; }
.dialog-body { display: grid; gap: 12px; max-height: 70vh; overflow-y: auto; padding: 18px; }.dialog-body > button { justify-self: start; } label { display: grid; gap: 4px; font-size: 11px; color: #766b82; } button, select { border: 1px solid #d8d1e0; border-radius: 5px; padding: 6px 9px; background: #fff; color: #494050; font: inherit; } select { width: 100%; min-width: 0; } button:disabled { cursor: default; opacity: .5; } button:not(:disabled) { cursor: pointer; } button.primary { border-color: #4b699a; background: #4b699a; color: #fff; } button:focus-visible, select:focus-visible, input:focus-visible { outline: 2px solid #7c9ad6; outline-offset: 2px; } p { margin: 0; }.hint { color: #83788e; font-size: 11px; }.error { padding: 9px; background: #fff1f2; color: #a53248; border-radius: 5px; }.success { padding: 9px; background: #eff8f1; color: #3c7350; border-radius: 5px; }.empty { padding: 10px 0; color: #756a80; }
.preview { display: grid; gap: 12px; } table { width: 100%; border-collapse: collapse; border: 1px solid #ddd6e6; font-variant-numeric: tabular-nums; } th, td { text-align: left; padding: 8px; border-bottom: 1px solid #e7e1ed; } th { color: #7a6e88; font-size: 11px; font-weight: 500; } td { text-align: right; } .total { background: #f2f5fd; font-weight: 600; }.confirmation { display: flex; align-items: start; gap: 8px; color: #494050; }.confirmation input { margin-top: 3px; flex: 0 0 auto; }
</style>
