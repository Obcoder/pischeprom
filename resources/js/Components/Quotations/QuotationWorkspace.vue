<script setup>
import { computed, onScopeDispose, reactive, ref, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
    good: { type: Object, default: null }, unit: { type: Object, default: null },
    quotations: { type: Array, default: () => [] }, dict: { type: Object, default: () => ({}) },
})
const emit = defineEmits(['changed', 'state'])
const dialog = ref(false), editing = ref(null), saving = ref(false), error = ref(''), errors = ref({})
const removing = ref(null), deleting = ref(false), notice = ref(''), tableError = ref('')
const search = ref(''), statusFilter = ref('all'), baseline = ref('')
const goodSearch = ref(''), lookupGoods = ref([]), goodsLoading = ref(false), lookupError = ref('')
const extraDict = reactive({ currencies: [], measures: [], units: [] })
let generation = 0, searchVersion = 0, searchTimer, searchController, disposed = false
const today = () => { const date = new Date(); return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}` }
const blank = () => ({ good_id: null, unit_id: null, price: '', currency_id: null, measure_id: null, denominator: 1, quoted_at: today(), valid_until: '', vat_mode: 'unknown', vat_rate: '', minimum_quantity: '', lead_time_days: '', notes: '' })
const form = reactive(blank())
const fixedGood = computed(() => Boolean(props.good?.id))
const fixedUnit = computed(() => Boolean(props.unit?.id))
const currencies = computed(() => props.dict.currencies?.length ? props.dict.currencies : extraDict.currencies)
const measures = computed(() => props.dict.measures?.length ? props.dict.measures : extraDict.measures)
const units = computed(() => props.dict.units?.length ? props.dict.units : extraDict.units)
const merge = items => [...new Map(items.filter(Boolean).map(item => [Number(item.id), item])).values()]
const goods = computed(() => merge([...(props.dict.goods || []), ...lookupGoods.value, editing.value?.good, props.good]))
const suppliers = computed(() => merge([...units.value, editing.value?.unit, props.unit]))
const dirty = computed(() => dialog.value && JSON.stringify(form) !== baseline.value)
const busy = computed(() => saving.value || deleting.value)
const number = value => value === '' || value == null ? null : Number(String(value).replace(',', '.'))
const money = value => number(value) == null || !Number.isFinite(number(value)) ? '—' : number(value).toLocaleString('ru-RU', { maximumFractionDigits: 4 })
const dateLabel = value => value ? String(value).slice(0, 10).split('-').reverse().join('.') : '—'
const currencyCode = row => row.currency?.code || currencies.value.find(item => Number(item.id) === Number(row.currency_id))?.code || 'Валюта не указана'
const measureName = row => row.measure?.name || measures.value.find(item => Number(item.id) === Number(row.measure_id))?.name || 'ед. не указана'
const quoteDate = row => String(row.quoted_at || row.created_at || '').slice(0, 10)
function missing(row) {
    const fields = []
    if (!row.currency_id) fields.push('валюта')
    if (!row.measure_id) fields.push('единица')
    if (number(row.price) == null || !Number.isFinite(number(row.price)) || number(row.price) < 0) fields.push('цена')
    if (!(Number(row.denominator) > 0)) fields.push('количество')
    return fields
}
function status(row) {
    if (missing(row).length) return { key: 'incomplete', title: 'Дополнить', hint: `Не указаны: ${missing(row).join(', ')}` }
    if (row.valid_until && String(row.valid_until).slice(0, 10) < today()) return { key: 'expired', title: 'Срок истёк', hint: 'Обновите срок действия перед использованием.' }
    if (row.quoted_at && quoteDate(row) > today()) return { key: 'future', title: 'С будущей даты', hint: `Действует с ${dateLabel(row.quoted_at)}` }
    return { key: 'ready', title: 'Действует', hint: 'Доступно в прайс-листе и ассортименте поставщика.' }
}
function vatLabel(row) {
    if (row.price_includes_vat == null || row.vat_rate == null) return 'НДС не указан'
    if (Number(row.vat_rate) === 0) return 'НДС 0%'
    return `${row.price_includes_vat ? 'Включая' : 'Сверху'} НДС ${money(row.vat_rate)}%`
}
const visibleRows = computed(() => props.quotations.filter(row => {
    const text = [row.unit?.name, row.good?.name, row.currency?.code, row.notes, row.id].join(' ').toLocaleLowerCase('ru')
    return text.includes(search.value.trim().toLocaleLowerCase('ru')) && (statusFilter.value === 'all' || status(row).key === statusFilter.value)
}).slice().sort((a, b) => quoteDate(b).localeCompare(quoteDate(a)) || Number(b.id) - Number(a.id)))
const incompleteCount = computed(() => props.quotations.filter(row => missing(row).length).length)
const selectedCurrency = computed(() => currencies.value.find(item => Number(item.id) === Number(form.currency_id))?.code || '—')
const selectedMeasure = computed(() => measures.value.find(item => Number(item.id) === Number(form.measure_id))?.name || 'ед.')
const unitPrice = computed(() => number(form.price) != null && number(form.denominator) > 0 ? number(form.price) / number(form.denominator) : null)
const grossUnitPrice = computed(() => form.vat_mode === 'unknown' || number(form.vat_rate) == null ? null : unitPrice.value == null ? null : form.vat_mode === 'excluded' ? unitPrice.value * (1 + number(form.vat_rate) / 100) : unitPrice.value)
const contextName = computed(() => props.good?.name || props.unit?.name || '')
const failureMessage = failure => failure.response?.data?.message || 'Не удалось выполнить действие. Попробуйте ещё раз.'
const payload = () => ({
    good_id: form.good_id, unit_id: form.unit_id, price: number(form.price), currency_id: form.currency_id, measure_id: form.measure_id,
    denominator: number(form.denominator), quoted_at: form.quoted_at || null, valid_until: form.valid_until || null,
    price_includes_vat: form.vat_mode === 'unknown' ? null : form.vat_mode === 'included',
    vat_rate: form.vat_mode === 'unknown' ? null : number(form.vat_rate), minimum_quantity: number(form.minimum_quantity),
    lead_time_days: number(form.lead_time_days), notes: form.notes.trim() || null,
})

async function loadDictionaries() {
    const version = generation
    await Promise.all(['currencies', 'measures', 'units'].map(async key => {
        if (props.dict[key]?.length || extraDict[key].length || (key === 'units' && fixedUnit.value)) return
        try {
            const { data } = await axios.get(`/api/${key}`)
            if (disposed || version !== generation) return
            extraDict[key] = Array.isArray(data) ? data : data.data || []
        } catch (failure) { if (!disposed && version === generation) lookupError.value = 'Не удалось загрузить справочники. Закройте и откройте форму для повтора.' }
    }))
    if (!disposed && version === generation && !editing.value && !form.currency_id) {
        form.currency_id = currencies.value.find(item => item.code === 'RUB')?.id || null
        baseline.value = JSON.stringify({ ...JSON.parse(baseline.value || '{}'), currency_id: form.currency_id })
    }
}
function open(row = null) {
    generation++
    editing.value = row
    errors.value = {}; error.value = ''; lookupError.value = ''; notice.value = ''
    Object.assign(form, blank(), {
        good_id: props.good?.id || row?.good_id || row?.good?.id || null,
        unit_id: props.unit?.id || row?.unit_id || row?.unit?.id || null,
        price: row?.price ?? '', currency_id: row ? row.currency_id || null : currencies.value.find(item => item.code === 'RUB')?.id || null,
        measure_id: row ? row.measure_id || null : props.good?.measure_id || props.good?.measure?.id || null,
        denominator: row?.denominator ?? 1, quoted_at: row ? String(row.quoted_at || '').slice(0, 10) : today(), valid_until: String(row?.valid_until || '').slice(0, 10),
        vat_mode: row?.price_includes_vat == null ? 'unknown' : row.price_includes_vat ? 'included' : 'excluded',
        vat_rate: row?.vat_rate ?? '', minimum_quantity: row?.minimum_quantity ?? '', lead_time_days: row?.lead_time_days ?? '', notes: row?.notes || '',
    })
    baseline.value = JSON.stringify(form)
    dialog.value = true
    loadDictionaries()
}
function close() { if (!saving.value) { dialog.value = false; resetForm() } }
function resetForm() { Object.assign(form, blank()); editing.value = null; errors.value = {}; error.value = ''; baseline.value = JSON.stringify(form) }
function reset() {
    generation++; searchVersion++; clearTimeout(searchTimer); searchController?.abort()
    goodSearch.value = ''; lookupGoods.value = []; goodsLoading.value = false; lookupError.value = ''
    saving.value = false; deleting.value = false; dialog.value = false; removing.value = null
    resetForm(); tableError.value = ''; notice.value = ''
}
async function searchGoods(value) {
    goodSearch.value = value || ''
    clearTimeout(searchTimer); searchController?.abort(); const version = ++searchVersion
    if (fixedGood.value) return
    searchTimer = setTimeout(async () => {
        searchController = new AbortController(); goodsLoading.value = true
        try {
            const { data } = await axios.get('/api/goods', { params: { search: goodSearch.value, per_page: 40 }, signal: searchController.signal })
            if (!disposed && version === searchVersion) lookupGoods.value = Array.isArray(data) ? data : data.data || []
        } catch (failure) { if (!disposed && version === searchVersion && failure.code !== 'ERR_CANCELED') lookupError.value = 'Не удалось найти товары. Повторите поиск.' }
        finally { if (!disposed && version === searchVersion) goodsLoading.value = false }
    }, 250)
}
function selectedGood(id) {
    const good = goods.value.find(item => Number(item.id) === Number(id))
    if (!editing.value && good?.measure_id) form.measure_id = good.measure_id
}
async function save() {
    if (saving.value) return
    errors.value = {}; error.value = ''
    const content = payload()
    for (const field of ['good_id', 'unit_id', 'currency_id', 'measure_id']) if (!content[field]) errors.value[field] = ['Выберите значение.']
    if (content.price == null || !Number.isFinite(content.price) || content.price < 0) errors.value.price = ['Укажите неотрицательную цену.']
    if (!(content.denominator > 0)) errors.value.denominator = ['Количество должно быть больше нуля.']
    if (form.vat_mode !== 'unknown' && (content.vat_rate == null || !Number.isFinite(content.vat_rate) || content.vat_rate < 0 || content.vat_rate > 100)) errors.value.vat_rate = ['Укажите ставку НДС от 0 до 100%.']
    if (Object.keys(errors.value).length) return
    const version = generation, id = editing.value?.id
    saving.value = true
    try {
        const { data } = id ? await axios.put(`/api/quotations/${id}`, content) : await axios.post('/api/quotations', content)
        if (disposed || version !== generation) return
        dialog.value = false; resetForm()
        notice.value = id ? 'Предложение обновлено в прайс-листе поставщика.' : 'Предложение добавлено в прайс-лист и ассортимент поставщика.'
        emit('changed', data.data || data)
    } catch (failure) {
        if (!disposed && version === generation) {
            errors.value = failure.response?.data?.errors || {}
            error.value = Object.keys(errors.value).length ? 'Проверьте реквизиты предложения.' : failureMessage(failure)
        }
    } finally { if (version === generation) saving.value = false }
}
async function remove() {
    if (deleting.value || !removing.value?.id) return
    const version = generation, id = removing.value.id
    deleting.value = true; tableError.value = ''
    try {
        await axios.delete(`/api/quotations/${id}`)
        if (disposed || version !== generation) return
        removing.value = null; emit('changed', null); notice.value = 'Предложение удалено.'
    } catch (failure) { if (!disposed && version === generation) { tableError.value = failureMessage(failure); removing.value = null } }
    finally { if (version === generation) deleting.value = false }
}
watch([dirty, busy], ([dirty, busy]) => emit('state', { dirty, busy }), { immediate: true })
watch([() => props.good?.id, () => props.unit?.id], reset)
onScopeDispose(() => { disposed = true; generation++; searchVersion++; clearTimeout(searchTimer); searchController?.abort() })
defineExpose({ reset })
</script>

<template>
    <section class="quotation-workspace">
        <header class="quotation-workspace__header">
            <div><h3>{{ fixedGood ? 'Предложения поставщиков' : 'Прайс-лист поставщика' }} <span>{{ quotations.length }}</span></h3><p>Единые предложения для расчёта цен и заказа поставщику.</p></div>
            <button type="button" class="q-button q-button--primary" :disabled="busy" @click="open()"><v-icon icon="mdi-plus" size="15" /> {{ fixedGood ? 'Предложение поставщика' : 'Добавить товар' }}</button>
        </header>
        <div v-if="notice" class="q-feedback" role="status">{{ notice }}</div>
        <div v-if="tableError" class="q-feedback q-feedback--error" role="alert">{{ tableError }}</div>
        <div class="quotation-workspace__filters">
            <div class="q-search"><v-icon icon="mdi-magnify" size="17" /><input v-model="search" aria-label="Поиск предложений" placeholder="Поиск по товару, поставщику, валюте…"><button v-if="search" type="button" aria-label="Очистить поиск предложений" @click="search = ''"><v-icon icon="mdi-close" size="15" /></button></div>
            <select v-model="statusFilter" aria-label="Статус предложений"><option value="all">Все предложения</option><option value="ready">Действующие</option><option value="incomplete">Требуют реквизитов</option><option value="expired">Срок истёк</option><option value="future">С будущей даты</option></select>
            <span v-if="incompleteCount" class="q-warning">Дополнить реквизиты: {{ incompleteCount }}</span>
        </div>
        <div class="quotation-workspace__sheet">
            <table class="q-table"><thead><tr><th>{{ fixedGood ? 'Поставщик / Unit' : 'Товар' }}</th><th class="q-number">Цена предложения</th><th class="q-number">За 1 ед.</th><th>НДС</th><th>Срок действия</th><th>Условия</th><th>Статус</th><th><span class="sr-only">Действия</span></th></tr></thead><tbody>
                <tr v-for="row in visibleRows" :key="row.id">
                    <td class="q-name"><a :href="fixedGood ? `/Ameise/unit/${row.unit_id}` : `/Ameise/goods/${row.good_id}`">{{ fixedGood ? row.unit?.name || `Unit #${row.unit_id}` : row.good?.name || `Товар #${row.good_id}` }}</a><small>№ {{ row.id }}<span v-if="row.notes" :title="row.notes"> · {{ row.notes }}</span></small></td>
                    <td class="q-number"><strong>{{ money(row.price) }} <span :class="{ 'q-warning': !row.currency_id }">{{ currencyCode(row) }}</span></strong><small>за {{ money(row.denominator) }} {{ measureName(row) }}</small></td>
                    <td class="q-number">{{ Number(row.denominator) > 0 ? money(number(row.price) == null ? null : number(row.price) / Number(row.denominator)) : '—' }}<small>{{ row.currency_id ? currencyCode(row) : '—' }} / {{ measureName(row) }}</small></td>
                    <td><span :class="{ 'q-muted': row.price_includes_vat == null }">{{ vatLabel(row) }}</span></td>
                    <td><span>{{ row.quoted_at ? dateLabel(row.quoted_at) : 'Дата не указана' }}</span><small>{{ row.valid_until ? `до ${dateLabel(row.valid_until)}` : 'Без окончания' }}</small></td>
                    <td><span>{{ row.minimum_quantity ? `От ${money(row.minimum_quantity)} ${measureName(row)}` : 'Минимум не задан' }}</span><small>{{ row.lead_time_days != null ? `Поставка: ${row.lead_time_days} дн.` : 'Срок уточняется' }}</small></td>
                    <td><span class="q-status" :class="`q-status--${status(row).key}`" :title="status(row).hint">{{ status(row).title }}</span></td>
                    <td class="q-actions"><button type="button" :aria-label="`Редактировать предложение № ${row.id}`" title="Редактировать" :disabled="busy" @click="open(row)"><v-icon icon="mdi-pencil-outline" size="16" /></button><button type="button" :aria-label="`Удалить предложение № ${row.id}`" title="Удалить" :disabled="busy" @click="removing = row"><v-icon icon="mdi-trash-can-outline" size="16" /></button></td>
                </tr>
                <tr v-if="!visibleRows.length"><td colspan="8" class="q-empty">{{ quotations.length ? 'Нет предложений по выбранным условиям.' : 'Предложений пока нет. Добавьте цену и условия поставщика.' }}</td></tr>
            </tbody></table>
        </div>
        <footer class="quotation-workspace__caption">{{ visibleRows.length }} из {{ quotations.length }} · Цена предложения относится к указанному количеству. Валюты не конвертируются.</footer>

        <v-dialog v-model="dialog" max-width="920" :persistent="saving" aria-labelledby="quotation-editor-title">
            <v-card class="quotation-editor" theme="light" rounded="lg">
                <header class="quotation-editor__header"><div><small>ПРЕДЛОЖЕНИЕ ПОСТАВЩИКА {{ editing ? ` / № ${editing.id}` : '' }}</small><h2 id="quotation-editor-title">{{ editing ? 'Редактировать предложение' : 'Новое предложение' }}</h2><p>{{ contextName }}</p></div><v-btn icon="mdi-close" size="small" variant="text" aria-label="Закрыть предложение" :disabled="saving" @click="close" /></header>
                <form @submit.prevent="save">
                    <div class="quotation-editor__body">
                        <v-alert v-if="error || lookupError" type="error" variant="tonal" density="compact" class="mb-4">{{ error || lookupError }}</v-alert>
                        <v-alert v-if="editing && missing(editing).length" type="warning" variant="tonal" density="compact" class="mb-4">В прежнем предложении не указаны: {{ missing(editing).join(', ') }}. Дополните их, чтобы использовать цену в расчётах и заказах.</v-alert>
                        <div class="quotation-editor__grid">
                            <v-autocomplete v-if="!fixedUnit" v-model="form.unit_id" :items="suppliers" item-title="name" item-value="id" label="Поставщик / Unit *" variant="outlined" density="compact" hide-details="auto" :disabled="saving" :error-messages="errors.unit_id" class="q-span-2" />
                            <v-autocomplete v-if="!fixedGood" v-model="form.good_id" :search="goodSearch" :items="goods" item-title="name" item-value="id" label="Товар *" variant="outlined" density="compact" hide-details="auto" no-filter :loading="goodsLoading" :disabled="saving" :error-messages="errors.good_id" class="q-span-2" @update:search="searchGoods" @update:model-value="selectedGood" @update:focused="value => { if (value && !goods.length) searchGoods('') }" />
                            <v-text-field v-model="form.price" label="Цена предложения *" inputmode="decimal" variant="outlined" density="compact" hide-details="auto" :disabled="saving" :error-messages="errors.price" />
                            <v-select v-model="form.currency_id" :items="currencies" :item-title="item => [item.code, item.name].filter(Boolean).join(' · ')" item-value="id" label="Валюта *" variant="outlined" density="compact" hide-details="auto" :disabled="saving" :error-messages="errors.currency_id" />
                            <v-text-field v-model="form.denominator" label="Цена за количество *" inputmode="decimal" variant="outlined" density="compact" hide-details="auto" :disabled="saving" :error-messages="errors.denominator" />
                            <v-autocomplete v-model="form.measure_id" :items="measures" item-title="name" item-value="id" label="Единица количества *" variant="outlined" density="compact" hide-details="auto" :disabled="saving" :error-messages="errors.measure_id" />
                        </div>
                        <div class="quotation-editor__preview"><v-icon icon="mdi-calculator-variant-outline" size="20" /><div><strong>{{ money(form.price) }} {{ selectedCurrency }} за {{ money(form.denominator) }} {{ selectedMeasure }} <span>→ {{ money(unitPrice) }} {{ selectedCurrency }} / {{ selectedMeasure }}</span></strong><small>Например: 2 580 RUB за 10 кг = 258 RUB/кг. Указывайте количество, к которому относится цена.</small></div></div>
                        <div class="quotation-editor__grid">
                            <v-select v-model="form.vat_mode" :items="[{value:'unknown',title:'Не указан'},{value:'included',title:'Включён в цену'},{value:'excluded',title:'Начисляется сверху'}]" label="Как указан НДС" variant="outlined" density="compact" hide-details="auto" :disabled="saving" :error-messages="errors.price_includes_vat" />
                            <v-text-field v-model="form.vat_rate" label="Ставка НДС, %" inputmode="decimal" variant="outlined" density="compact" hide-details="auto" :disabled="saving || form.vat_mode === 'unknown'" :error-messages="errors.vat_rate" />
                        </div>
                        <p class="quotation-editor__tax-note">{{ grossUnitPrice == null ? 'НДС не определён — уточните условия у поставщика.' : `Для заказа с НДС: ${money(grossUnitPrice)} ${selectedCurrency} / ${selectedMeasure}.` }}</p>
                        <h3>Сроки и условия</h3>
                        <div class="quotation-editor__grid">
                            <v-text-field v-model="form.quoted_at" type="date" label="Дата предложения" variant="outlined" density="compact" hide-details="auto" :disabled="saving" :error-messages="errors.quoted_at" />
                            <v-text-field v-model="form.valid_until" type="date" label="Действует до" variant="outlined" density="compact" hide-details="auto" :disabled="saving" :error-messages="errors.valid_until" />
                            <v-text-field v-model="form.minimum_quantity" :label="`Минимальный заказ, ${selectedMeasure}`" inputmode="decimal" variant="outlined" density="compact" hide-details="auto" :disabled="saving" :error-messages="errors.minimum_quantity" />
                            <v-text-field v-model="form.lead_time_days" label="Срок поставки, дней" inputmode="numeric" variant="outlined" density="compact" hide-details="auto" :disabled="saving" :error-messages="errors.lead_time_days" />
                            <v-textarea v-model="form.notes" label="Условия и комментарий" placeholder="Оплата, отгрузка, источник предложения…" variant="outlined" density="compact" rows="2" auto-grow hide-details="auto" :disabled="saving" :error-messages="errors.notes" class="q-span-2" />
                        </div>
                    </div>
                    <footer class="quotation-editor__footer"><span>Сохранится в прайс-листе Unit и ассортименте для заказа.</span><v-btn variant="text" :disabled="saving" @click="close">Отмена</v-btn><v-btn type="submit" variant="flat" color="#65518d" :loading="saving" :disabled="saving">Сохранить предложение</v-btn></footer>
                </form>
            </v-card>
        </v-dialog>
        <v-dialog :model-value="Boolean(removing)" max-width="440" :persistent="deleting" @update:model-value="value => { if (!value && !deleting) removing = null }"><v-card theme="light" rounded="lg" title="Удалить предложение?" text="Оно исчезнет из прайс-листа. Товар остаётся в каталоге."><v-card-actions><v-spacer /><v-btn :disabled="deleting" @click="removing = null">Отмена</v-btn><v-btn color="error" :loading="deleting" @click="remove">Удалить</v-btn></v-card-actions></v-card></v-dialog>
    </section>
</template>

<style scoped>
.quotation-workspace { color: #34313a; min-width: 0; padding: 16px 4px; }
.quotation-workspace__header { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 16px; }
.quotation-workspace h3 { font-size: 17px; font-weight: 650; margin: 0; letter-spacing: -.02em; }
.quotation-workspace h3 span { padding: 2px 7px; border-radius: 6px; margin-left: 6px; color: #83788f; background: #f0edf5; font-size: 11px; }
.quotation-workspace__header p { color: #8b8494; font-size: 11px; margin-top: 5px; }
.q-button { display: inline-flex; align-items: center; gap: 6px; padding: 8px 12px; border: 1px solid #ded9e6; border-radius: 7px; font-size: 11px; font-weight: 600; background: #fff; white-space: nowrap; }
.q-button--primary { color: #fff; background: #65518d; border-color: #65518d; }
.quotation-workspace__filters { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 9px; }
.q-search { display: flex; align-items: center; gap: 6px; border: 1px solid #ded9e6; border-radius: 7px; padding: 5px 8px; width: min(350px,100%); background: #fff; color: #8b8195; }
.q-search input { padding: 0; border: 0; outline: 0; box-shadow: none; background: transparent; font-size: 11px; width: 100%; min-width: 0; }
.quotation-workspace__filters select { border: 1px solid #ded9e6; background-color: #fff; border-radius: 6px; padding: 5px 27px 5px 8px; font-size: 11px; }
.q-warning { color: #a66e22; font-size: 10px; }
.quotation-workspace__sheet { border: 1px solid #dedbe3; border-radius: 8px; overflow: auto; max-height: min(55dvh, 520px); background: #fff; }
.q-table { width: 100%; border-collapse: collapse; font-size: 11px; }
.q-table th { position: sticky; top: 0; z-index: 1; background: #f4f3f6; color: #82798b; font-weight: 550; text-align: left; white-space: nowrap; }
.q-table th, .q-table td { padding: 9px 12px; border-bottom: 1px solid #ece9f0; }
.q-table tbody tr:last-child td { border-bottom: 0; }
.q-table tbody tr:nth-child(even) { background: #fbfafc; }
.q-table tbody tr:hover { background: #f7f4fc; }
.q-table small { display: block; color: #9a91a3; font-size: 10px; margin-top: 3px; }
.q-table strong { font-weight: 650; }
.q-table .q-number { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
.q-name { min-width: 170px; max-width: 320px; }
.q-name a { color: #4e4263; font-weight: 600; text-decoration: none; }
.q-name a:hover { text-decoration: underline; }
.q-name small { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 270px; }
.q-muted { color: #9b93a4; }
.q-status { display: inline-block; padding: 3px 6px; border-radius: 5px; font-size: 10px; white-space: nowrap; background: #f1eef6; color: #81738f; }
.q-status--ready { background: #edf5f1; color: #527a66; }
.q-status--incomplete, .q-status--expired { background: #fcf0e5; color: #aa762f; }
.q-actions { white-space: nowrap; text-align: right; }
.q-actions button { padding: 4px; border: 1px solid #e5dfea; border-radius: 5px; color: #7d6f8b; margin-left: 4px; }
.q-actions button:hover { background: #eae3f3; }
.q-empty { text-align: center; color: #9b93a4; padding: 35px !important; }
.quotation-workspace__caption { color: #aaa2b2; font-size: 10px; margin-top: 9px; }
.q-feedback { border-radius: 6px; padding: 8px 11px; margin-bottom: 10px; background: #edf6f1; color: #527a66; font-size: 12px; }
.q-feedback--error { color: #a54555; background: #fbedf0; }
.quotation-editor { display: flex; flex-direction: column; max-height: calc(100dvh - 48px); color: #37313f; }
.quotation-editor__header { display: flex; justify-content: space-between; align-items: flex-start; padding: 20px 24px; border-bottom: 1px solid #ebe6f0; background: #fbf9fd; flex-shrink: 0; }
.quotation-editor__header small { font-size: 9px; letter-spacing: .1em; color: #9a8ca7; }
.quotation-editor h2 { margin: 5px 0; font-size: 21px; font-weight: 650; letter-spacing: -.025em; }
.quotation-editor__header p { font-size: 12px; color: #8b7f98; margin: 0; overflow-wrap: anywhere; }
.quotation-editor form { display: flex; flex-direction: column; min-height: 0; }
.quotation-editor__body { padding: 22px 24px; overflow-y: auto; min-height: 0; }
.quotation-editor__grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 16px; }
.q-span-2 { grid-column: 1 / -1; }
.quotation-editor__preview { display: flex; gap: 10px; align-items: center; background: #f7f4fc; border: 1px solid #e6deef; border-radius: 8px; padding: 12px; margin: 16px 0; color: #78648f; }
.quotation-editor__preview strong { font-size: 12px; font-weight: 600; }
.quotation-editor__preview strong span { color: #58466c; }
.quotation-editor__preview small { display: block; font-size: 10px; margin-top: 4px; color: #9d90ac; }
.quotation-editor__tax-note { margin: 8px 0 22px; font-size: 10px; color: #93839e; }
.quotation-editor h3 { font-size: 12px; font-weight: 650; margin-bottom: 14px; }
.quotation-editor__footer { display: flex; align-items: center; gap: 8px; padding: 14px 22px; border-top: 1px solid #e9e4ed; flex-shrink: 0; }
.quotation-editor__footer > span { font-size: 10px; color: #9d91a7; margin-right: auto; max-width: 250px; }
.quotation-editor :deep(.v-btn) { font-size: 11px; text-transform: none; letter-spacing: 0; border-radius: 7px; }
.quotation-editor :deep(.v-field) { font-size: 12px; border-radius: 7px; }
.quotation-editor :deep(.v-label) { font-size: 12px; }
.quotation-workspace button:focus-visible { outline: 2px solid #8e74b3; outline-offset: 2px; }
.quotation-workspace button:disabled { opacity: .5; }
@media (max-width: 680px) {
    .quotation-workspace__header { align-items: flex-start; flex-direction: column; gap: 10px; }
    .quotation-editor__grid { grid-template-columns: 1fr; gap: 14px; }
    .quotation-editor__header, .quotation-editor__body { padding: 16px; }
    .quotation-editor h2 { font-size: 18px; }
    .quotation-editor__footer { padding: 12px; flex-wrap: wrap; justify-content: flex-end; }
    .quotation-editor__footer > span { flex-basis: 100%; max-width: none; }
}
</style>
