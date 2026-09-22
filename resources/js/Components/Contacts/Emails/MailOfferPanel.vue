<script setup>
import axios from 'axios'
import { computed, onBeforeUnmount, ref, watch } from 'vue'

const offer = defineModel('offer', { type: Object, required: true })
const props = defineProps({
    section: { type: String, default: 'products' },
    disabled: Boolean,
})
const emit = defineEmits(['close'])

const MAX_PRODUCTS = 10
const MAX_SPECIFICATIONS = 12
const MAX_LOGISTICS_OPTIONS = 4
const items = computed(() => offer.value?.items || [])
const logistics = computed(() => offer.value?.logistics || null)
const selectedIds = computed(() => new Set(items.value.map((item) => String(item.good_id))))
const search = ref('')
const goods = ref([])
const loading = ref(false)
const loadError = ref('')
const currentPage = ref(1)
const lastPage = ref(1)
const failedImages = ref(new Set())
const numberDrafts = ref({})
let searchTimer = null
let requestController = null
let requestRevision = 0

function cancelSearch() {
    clearTimeout(searchTimer)
    requestRevision++
    requestController?.abort()
    requestController = null
    loading.value = false
}

async function fetchGoods(page = 1) {
    cancelSearch()
    if (props.section !== 'products') return

    const revision = requestRevision
    const controller = new AbortController()
    requestController = controller
    currentPage.value = page
    loading.value = true
    loadError.value = ''

    try {
        const { data } = await axios.get('/api/mail-offers/goods', {
            params: { search: search.value.trim(), page },
            signal: controller.signal,
        })
        if (revision !== requestRevision || controller.signal.aborted) return

        goods.value = Array.isArray(data.data) ? data.data : []
        currentPage.value = Number(data.meta?.current_page || page)
        lastPage.value = Math.max(1, Number(data.meta?.last_page || 1))
    } catch (error) {
        if (revision !== requestRevision || controller.signal.aborted || axios.isCancel(error)) return
        goods.value = []
        loadError.value = error?.response?.data?.message || 'Не удалось загрузить товары. Попробуйте ещё раз.'
    } finally {
        if (revision === requestRevision) {
            loading.value = false
            requestController = null
        }
    }
}

watch(search, () => {
    cancelSearch()
    currentPage.value = 1
    lastPage.value = 1
    goods.value = []
    loadError.value = ''
    loading.value = true
    searchTimer = setTimeout(() => fetchGoods(1), 300)
})

watch(() => props.section, (section) => {
    cancelSearch()
    if (section === 'products') fetchGoods(1)
}, { immediate: true })

onBeforeUnmount(cancelSearch)

function updateOffer(patch) {
    if (props.disabled) return
    offer.value = { items: [], logistics: null, ...offer.value, ...patch }
}

function addGood(good) {
    if (props.disabled || !good?.id || selectedIds.value.has(String(good.id)) || items.value.length >= MAX_PRODUCTS) return

    updateOffer({
        items: [...items.value, {
            good_id: good.id,
            quantity: null,
            price_override: null,
            include_description: false,
            include_specifications: false,
            include_image: true,
            specifications: [],
            good,
        }],
    })
}

function updateItem(index, patch) {
    updateOffer({ items: items.value.map((item, position) => position === index ? { ...item, ...patch } : item) })
}

function moveItem(index, offset) {
    const next = index + offset
    if (next < 0 || next >= items.value.length) return
    const reordered = [...items.value]
    ;[reordered[index], reordered[next]] = [reordered[next], reordered[index]]
    updateOffer({ items: reordered })
}

function removeItem(index) {
    clearNumberDraft(`good:${items.value[index].good_id}:quantity`)
    clearNumberDraft(`good:${items.value[index].good_id}:price_override`)
    updateOffer({ items: items.value.filter((_, position) => position !== index) })
}

function nullableNumber(event, minimum = 0) {
    const value = event.target.value
    if (value === '') return null
    const number = Number(value)
    if (!Number.isFinite(number) || number < minimum || number > 1_000_000_000) {
        return null
    }
    return number
}

function numberValue(key, value) {
    return numberDrafts.value[key] ?? value ?? ''
}

function clearNumberDraft(key) {
    delete numberDrafts.value[key]
}

function updateItemNumber(index, field, event) {
    const item = items.value[index]
    numberDrafts.value[`good:${item.good_id}:${field}`] = event.target.value
    updateItem(index, { [field]: nullableNumber(event, field === 'quantity' ? 0.001 : 0) })
}

function updateLogisticsPrice(index, event) {
    numberDrafts.value[`logistics:${index}`] = event.target.value
    updateLogisticsOption(index, { price: nullableNumber(event) })
}

function addSpecification(index) {
    const specifications = items.value[index]?.specifications || []
    if (specifications.length >= MAX_SPECIFICATIONS) return
    updateItem(index, { specifications: [...specifications, { label: '', value: '' }] })
}

function updateSpecification(index, specificationIndex, patch) {
    updateItem(index, {
        specifications: (items.value[index]?.specifications || []).map((specification, position) => (
            position === specificationIndex ? { ...specification, ...patch } : specification
        )),
    })
}

function removeSpecification(index, specificationIndex) {
    updateItem(index, {
        specifications: (items.value[index]?.specifications || []).filter((_, position) => position !== specificationIndex),
    })
}

function newLogisticsOption() {
    return { name: '', price: null, currency_code: 'RUB', duration: '', note: '' }
}

function addLogistics() {
    if (logistics.value) return
    updateOffer({ logistics: { origin: '', destination: '', note: '', options: [newLogisticsOption()] } })
}

function updateLogistics(patch) {
    if (!logistics.value) return
    updateOffer({ logistics: { ...logistics.value, ...patch } })
}

function addLogisticsOption() {
    const options = logistics.value?.options || []
    if (!logistics.value || options.length >= MAX_LOGISTICS_OPTIONS) return
    updateLogistics({ options: [...options, newLogisticsOption()] })
}

function updateLogisticsOption(index, patch) {
    updateLogistics({ options: (logistics.value?.options || []).map((option, position) => position === index ? { ...option, ...patch } : option) })
}

function removeLogisticsOption(index) {
    Object.keys(numberDrafts.value).filter((key) => key.startsWith('logistics:')).forEach(clearNumberDraft)
    updateLogistics({ options: (logistics.value?.options || []).filter((_, position) => position !== index) })
}

function money(value, currency = 'RUB') {
    if (value === null || value === undefined || value === '' || !Number.isFinite(Number(value))) return 'Цена по запросу'
    const code = String(currency || 'RUB').trim().toUpperCase()
    if (/^[A-Z]{3}$/.test(code)) {
        try {
            return new Intl.NumberFormat('ru-RU', {
                style: 'currency',
                currency: code,
                maximumFractionDigits: 2,
            }).format(Number(value))
        } catch {
            // Keep the catalog currency visible if Intl cannot format it.
        }
    }
    return `${new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 2 }).format(Number(value))} ${code}`
}

function goodPrice(good) {
    const price = money(good?.price, good?.currency_code)
    return good?.price !== null && good?.price !== undefined && good?.price !== '' && good?.price_unit_label
        ? `${price} / ${good.price_unit_label}`
        : price
}

function imageUrl(good) {
    const url = good?.image_url
    return url && !failedImages.value.has(url) ? url : null
}

function imageFailed(url) {
    failedImages.value = new Set([...failedImages.value, url])
}
</script>

<template>
    <v-theme-provider theme="light">
        <aside class="mail-offer-panel" :aria-label="section === 'products' ? 'Товары для письма' : 'Логистика для письма'">
            <header class="offer-panel-heading">
                <div class="offer-panel-heading__icon"><v-icon :icon="section === 'products' ? 'mdi-package-variant-closed' : 'mdi-truck-outline'" size="20" /></div>
                <div class="offer-panel-heading__text">
                    <h3>{{ section === 'products' ? 'Товары для письма' : 'Логистика' }}</h3>
                    <p>{{ section === 'products' ? 'Соберите короткое предложение' : 'Согласованные условия доставки' }}</p>
                </div>
                <button type="button" class="offer-icon-button" title="Закрыть панель" aria-label="Закрыть панель предложения" :disabled="disabled" @click="emit('close')"><v-icon icon="mdi-close" size="18" /></button>
            </header>

            <fieldset class="offer-panel-body" :disabled="disabled">
                <template v-if="section === 'products'">
                    <section class="offer-catalog" aria-label="Поиск товаров">
                        <label class="offer-search">
                            <v-icon icon="mdi-magnify" size="18" />
                            <input v-model="search" type="search" placeholder="Название товара" aria-label="Поиск товара для предложения" autocomplete="off">
                        </label>
                        <div v-if="loading" class="offer-catalog-state" role="status"><v-progress-circular indeterminate size="18" width="2" color="#800000" /> Загрузка товаров…</div>
                        <div v-else-if="loadError" class="offer-catalog-state offer-catalog-state--error" role="alert">
                            <span>{{ loadError }}</span>
                            <button type="button" class="offer-text-button" @click="fetchGoods(currentPage)">Повторить</button>
                        </div>
                        <div v-else-if="!goods.length" class="offer-catalog-state">{{ search.trim() ? 'По этому запросу товаров нет.' : 'Товаров для предложения пока нет.' }}</div>
                        <div v-else class="offer-catalog-results">
                            <article v-for="good in goods" :key="good.id" class="offer-catalog-good">
                                <div class="offer-good-image">
                                    <img v-if="imageUrl(good)" :src="imageUrl(good)" alt="" loading="lazy" @error="imageFailed(good.image_url)">
                                    <v-icon v-else icon="mdi-package-variant" size="21" />
                                </div>
                                <div class="offer-catalog-good__text">
                                    <span class="offer-catalog-good__name" :title="good.name">{{ good.name }}</span>
                                    <span class="offer-catalog-good__price">{{ goodPrice(good) }}</span>
                                </div>
                                <button
                                    type="button" class="offer-icon-button offer-icon-button--add"
                                    :class="{ 'is-selected': selectedIds.has(String(good.id)) }"
                                    :disabled="disabled || selectedIds.has(String(good.id)) || items.length >= MAX_PRODUCTS"
                                    :aria-label="`${selectedIds.has(String(good.id)) ? 'Уже добавлен' : 'Добавить товар'}: ${good.name}`"
                                    :title="selectedIds.has(String(good.id)) ? 'Уже в письме' : items.length >= MAX_PRODUCTS ? 'В письме может быть до 10 товаров' : 'Добавить в письмо'"
                                    @click="addGood(good)"
                                ><v-icon :icon="selectedIds.has(String(good.id)) ? 'mdi-check' : 'mdi-plus'" size="18" /></button>
                            </article>
                        </div>
                        <div v-if="lastPage > 1 && !loadError" class="offer-catalog-pagination">
                            <button type="button" class="offer-icon-button" aria-label="Предыдущая страница товаров" :disabled="disabled || loading || currentPage <= 1" @click="fetchGoods(currentPage - 1)"><v-icon icon="mdi-chevron-left" size="18" /></button>
                            <span>{{ currentPage }} / {{ lastPage }}</span>
                            <button type="button" class="offer-icon-button" aria-label="Следующая страница товаров" :disabled="disabled || loading || currentPage >= lastPage" @click="fetchGoods(currentPage + 1)"><v-icon icon="mdi-chevron-right" size="18" /></button>
                        </div>
                    </section>

                    <div class="offer-section-heading"><h4>В письме</h4><span>{{ items.length }} / {{ MAX_PRODUCTS }}</span></div>
                    <p v-if="!items.length" class="offer-empty-hint">Добавьте товар из каталога. Цена и единица продажи подставятся автоматически.</p>
                    <p v-else-if="items.length >= MAX_PRODUCTS" class="offer-empty-hint">Добавлено максимум 10 товаров.</p>

                    <article v-for="(item, index) in items" :key="item.good_id" class="offer-item-card">
                        <div class="offer-item-heading">
                            <span class="offer-item-number">{{ index + 1 }}</span>
                            <h4>{{ item.good?.name || `Товар #${item.good_id}` }}</h4>
                            <div class="offer-item-tools">
                                <button type="button" class="offer-icon-button" :disabled="disabled || index === 0" :aria-label="`Поднять товар: ${item.good?.name || item.good_id}`" title="Выше" @click="moveItem(index, -1)"><v-icon icon="mdi-chevron-up" size="16" /></button>
                                <button type="button" class="offer-icon-button" :disabled="disabled || index === items.length - 1" :aria-label="`Опустить товар: ${item.good?.name || item.good_id}`" title="Ниже" @click="moveItem(index, 1)"><v-icon icon="mdi-chevron-down" size="16" /></button>
                                <button type="button" class="offer-icon-button offer-icon-button--remove" :aria-label="`Убрать товар: ${item.good?.name || item.good_id}`" title="Убрать из письма" @click="removeItem(index)"><v-icon icon="mdi-close" size="16" /></button>
                            </div>
                        </div>
                        <p class="offer-catalog-price">Из каталога: <strong>{{ goodPrice(item.good) }}</strong><span v-if="item.good?.includes_vat === true"> · с НДС</span><span v-else-if="item.good?.includes_vat === false"> · без НДС</span></p>
                        <div class="offer-fields-row">
                            <label class="offer-field"><span>Количество<span v-if="item.good?.price_unit_label">, {{ item.good.price_unit_label }}</span></span><input :value="numberValue(`good:${item.good_id}:quantity`, item.quantity)" type="number" min="0.001" max="1000000000" step="any" inputmode="decimal" placeholder="Не указано" @input="updateItemNumber(index, 'quantity', $event)" @blur="clearNumberDraft(`good:${item.good_id}:quantity`)"></label>
                            <label class="offer-field"><span>Своя цена, {{ item.good?.currency_code || 'RUB' }}<span v-if="item.good?.price_unit_label"> / {{ item.good.price_unit_label }}</span></span><input :value="numberValue(`good:${item.good_id}:price_override`, item.price_override)" type="number" min="0" max="1000000000" step="any" inputmode="decimal" :placeholder="item.good?.price != null ? String(item.good.price) : 'По запросу'" @input="updateItemNumber(index, 'price_override', $event)" @blur="clearNumberDraft(`good:${item.good_id}:price_override`)"></label>
                        </div>
                        <p class="offer-field-hint">Пустая цена — из каталога. Количество — в единице продажи.</p>
                        <div class="offer-toggles">
                            <label :class="{ 'is-unavailable': !item.good?.image_url }"><input type="checkbox" :checked="item.include_image" :disabled="disabled || !item.good?.image_url" @change="updateItem(index, { include_image: $event.target.checked })">Фото</label>
                            <label :class="{ 'is-unavailable': !item.good?.description }"><input type="checkbox" :checked="item.include_description" :disabled="disabled || !item.good?.description" @change="updateItem(index, { include_description: $event.target.checked })">Описание</label>
                            <label><input type="checkbox" :checked="item.include_specifications" @change="updateItem(index, { include_specifications: $event.target.checked })">Характеристики</label>
                        </div>
                        <p v-if="item.include_description && item.good?.description" class="offer-description-preview">{{ item.good.description }}</p>
                        <div v-if="item.include_specifications" class="offer-specifications">
                            <dl v-if="item.good?.specifications?.length" class="offer-base-specifications">
                                <div v-for="(specification, position) in item.good.specifications" :key="position"><dt>{{ specification.label }}</dt><dd>{{ specification.value }}</dd></div>
                            </dl>
                            <div v-for="(specification, position) in item.specifications" :key="position" class="offer-specification-row">
                                <input :value="specification.label" :aria-label="`Название характеристики ${position + 1} товара ${index + 1}`" placeholder="Параметр" maxlength="120" @input="updateSpecification(index, position, { label: $event.target.value })">
                                <input :value="specification.value" :aria-label="`Значение характеристики ${position + 1} товара ${index + 1}`" placeholder="Значение" maxlength="500" @input="updateSpecification(index, position, { value: $event.target.value })">
                                <button type="button" class="offer-icon-button offer-icon-button--remove" :aria-label="`Удалить характеристику ${position + 1} товара ${index + 1}`" @click="removeSpecification(index, position)"><v-icon icon="mdi-close" size="15" /></button>
                            </div>
                            <button type="button" class="offer-text-button" :disabled="disabled || (item.specifications?.length || 0) >= MAX_SPECIFICATIONS" @click="addSpecification(index)"><v-icon icon="mdi-plus" size="14" /> Добавить характеристику <span>{{ item.specifications?.length || 0 }}/{{ MAX_SPECIFICATIONS }}</span></button>
                        </div>
                    </article>
                </template>

                <template v-else>
                    <div v-if="!logistics" class="offer-logistics-empty">
                        <div class="offer-logistics-empty__icon"><v-icon icon="mdi-truck-delivery-outline" size="30" /></div>
                        <h4>Доставка в предложении</h4>
                        <p>Укажите маршрут и согласованные варианты доставки для получателя.</p>
                        <button type="button" class="offer-primary-button" @click="addLogistics"><v-icon icon="mdi-plus" size="16" /> Добавить логистику</button>
                    </div>
                    <template v-else>
                        <div class="offer-section-heading"><h4>Маршрут</h4><button type="button" class="offer-text-button offer-text-button--danger" @click="updateOffer({ logistics: null })">Убрать блок</button></div>
                        <label class="offer-field"><span>Откуда</span><input :value="logistics.origin" placeholder="Санкт-Петербург" maxlength="160" @input="updateLogistics({ origin: $event.target.value })"></label>
                        <label class="offer-field"><span>Куда</span><input :value="logistics.destination" placeholder="Курск" maxlength="160" @input="updateLogistics({ destination: $event.target.value })"></label>
                        <label class="offer-field"><span>Условия доставки</span><textarea :value="logistics.note" rows="2" placeholder="Например, адрес отгрузки или температурный режим" maxlength="1000" @input="updateLogistics({ note: $event.target.value })" /></label>
                        <div class="offer-section-heading"><h4>Варианты доставки</h4><span>{{ logistics.options?.length || 0 }} / {{ MAX_LOGISTICS_OPTIONS }}</span></div>
                        <article v-for="(option, index) in logistics.options" :key="index" class="offer-logistics-card">
                            <div class="offer-item-heading"><span class="offer-item-number">{{ index + 1 }}</span><h4>Вариант доставки</h4><button type="button" class="offer-icon-button offer-icon-button--remove" :aria-label="`Удалить вариант доставки ${index + 1}`" @click="removeLogisticsOption(index)"><v-icon icon="mdi-close" size="16" /></button></div>
                            <label class="offer-field"><span>Перевозчик или способ</span><input :value="option.name" placeholder="Транспортная компания / самовывоз" maxlength="120" @input="updateLogisticsOption(index, { name: $event.target.value })"></label>
                            <div class="offer-fields-row offer-fields-row--price">
                                <label class="offer-field"><span>Согласованная стоимость</span><input :value="numberValue(`logistics:${index}`, option.price)" type="number" min="0" max="1000000000" step="any" inputmode="decimal" placeholder="Не указана" @input="updateLogisticsPrice(index, $event)" @blur="clearNumberDraft(`logistics:${index}`)"></label>
                                <label class="offer-field"><span>Валюта</span><select :value="option.currency_code" @change="updateLogisticsOption(index, { currency_code: $event.target.value })"><option value="RUB">₽ RUB</option><option value="USD">$ USD</option><option value="EUR">€ EUR</option></select></label>
                            </div>
                            <label class="offer-field"><span>Срок</span><input :value="option.duration" placeholder="2–3 рабочих дня" maxlength="120" @input="updateLogisticsOption(index, { duration: $event.target.value })"></label>
                            <label class="offer-field"><span>Комментарий к варианту</span><textarea :value="option.note" rows="2" placeholder="Дополнительные условия" maxlength="500" @input="updateLogisticsOption(index, { note: $event.target.value })" /></label>
                        </article>
                        <button type="button" class="offer-secondary-button" :disabled="disabled || (logistics.options?.length || 0) >= MAX_LOGISTICS_OPTIONS" @click="addLogisticsOption"><v-icon icon="mdi-plus" size="16" /> Добавить вариант доставки</button>
                    </template>
                </template>
            </fieldset>
        </aside>
    </v-theme-provider>
</template>

<style scoped>
.mail-offer-panel { width: 350px; max-width: 100%; min-width: 0; color: #213047; background: #f5f7fa; border: 1px solid #dce3ed; border-radius: 14px; font-size: 12px; }
.offer-panel-heading { display: flex; align-items: center; gap: 9px; padding: 13px 12px; border-bottom: 1px solid #dce3ed; background: #fff; border-radius: 14px 14px 0 0; }
.offer-panel-heading__icon { display: grid; place-items: center; width: 34px; height: 34px; flex: 0 0 auto; border-radius: 10px; color: #800000; background: #f9eded; }
.offer-panel-heading__text { min-width: 0; flex: 1; }
.offer-panel-heading h3 { margin: 0; font-size: 14px; font-weight: 750; }
.offer-panel-heading p { margin: 2px 0 0; color: #748094; font-size: 10px; }
.offer-panel-body { display: flex; flex-direction: column; gap: 10px; min-width: 0; margin: 0; padding: 12px; border: 0; }
.offer-panel-body:disabled { opacity: .7; }
.offer-search { display: flex; align-items: center; gap: 7px; min-width: 0; padding: 0 9px; color: #76839a; border: 1px solid #ccd6e3; border-radius: 8px; background: #fff; }
.offer-search input { width: 100%; min-width: 0; padding: 9px 0; color: #213047; border: 0; background: transparent; outline: none; font-size: 12px; }
.offer-search:focus-within { border-color: #a45050; box-shadow: 0 0 0 2px #8000000b; }
.offer-catalog-state { display: flex; align-items: center; justify-content: center; flex-wrap: wrap; gap: 8px; min-height: 76px; padding: 12px 8px; text-align: center; color: #68778e; font-size: 11px; }
.offer-catalog-state--error { color: #9c3030; }
.offer-catalog-results { max-height: 255px; overflow-y: auto; overscroll-behavior: contain; margin-top: 6px; border: 1px solid #e0e6ee; border-radius: 8px; background: #fff; }
.offer-catalog-good { display: flex; align-items: center; gap: 8px; padding: 8px; }
.offer-catalog-good + .offer-catalog-good { border-top: 1px solid #edf0f5; }
.offer-good-image { display: grid; place-items: center; flex: 0 0 auto; width: 39px; height: 39px; overflow: hidden; border-radius: 7px; background: #f0f3f8; color: #9daac0; }
.offer-good-image img { width: 100%; height: 100%; object-fit: cover; }
.offer-catalog-good__text { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 3px; }
.offer-catalog-good__name { display: -webkit-box; overflow: hidden; -webkit-line-clamp: 2; -webkit-box-orient: vertical; font-size: 11px; font-weight: 650; line-height: 1.35; overflow-wrap: anywhere; }
.offer-catalog-good__price { color: #75829a; font-size: 10px; }
.offer-catalog-pagination { display: flex; justify-content: center; align-items: center; gap: 8px; margin-top: 5px; color: #748094; font-size: 10px; }
.offer-icon-button { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto; width: 26px; height: 26px; padding: 0; border-radius: 6px; color: #718098; transition: background .15s, color .15s; }
.offer-icon-button:hover:not(:disabled) { background: #e8edf4; color: #24364e; }
.offer-icon-button--add { color: #800000; background: #faeeee; }
.offer-icon-button--add:hover:not(:disabled) { color: #fff; background: #800000; }
.offer-icon-button--add.is-selected { color: #227657; background: #eaf6ef; opacity: 1; }
.offer-icon-button--remove:hover:not(:disabled) { color: #9c3030; background: #fcecec; }
.mail-offer-panel button:disabled { cursor: default; opacity: .4; }
.mail-offer-panel button:focus-visible { outline: 2px solid #9a4646; outline-offset: 2px; }
.offer-section-heading { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding-top: 3px; }
.offer-section-heading h4 { font-size: 12px; font-weight: 750; }
.offer-section-heading > span { color: #8691a2; font-size: 10px; }
.offer-empty-hint { margin: 0; color: #7d899a; font-size: 11px; line-height: 1.55; }
.offer-item-card, .offer-logistics-card { display: flex; flex-direction: column; gap: 10px; padding: 10px; border: 1px solid #dce3ed; border-radius: 10px; background: #fff; box-shadow: 0 2px 6px #182e4904; }
.offer-item-heading { display: flex; align-items: flex-start; gap: 6px; }
.offer-item-heading h4 { flex: 1; min-width: 0; margin: 2px 0 0; font-size: 11px; line-height: 1.45; font-weight: 700; overflow-wrap: anywhere; }
.offer-item-number { display: inline-grid; place-items: center; flex: 0 0 auto; width: 21px; height: 21px; color: #8b3b3b; border-radius: 6px; background: #f9eeee; font-size: 10px; font-weight: 700; }
.offer-item-tools { display: flex; flex: 0 0 auto; gap: 0; }
.offer-item-tools .offer-icon-button { width: 22px; height: 22px; }
.offer-catalog-price { margin: -2px 0 0; font-size: 10px; line-height: 1.5; color: #7a879a; }
.offer-catalog-price strong { font-weight: 600; color: #435572; }
.offer-fields-row { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 8px; }
.offer-fields-row--price { grid-template-columns: minmax(0, 1fr) 85px; }
.offer-field { display: flex; flex-direction: column; gap: 5px; min-width: 0; color: #5b6980; font-size: 10px; }
.offer-field > span { line-height: 1.35; }
.offer-field input, .offer-field select, .offer-field textarea, .offer-specification-row input { width: 100%; min-width: 0; padding: 7px 8px; color: #24364e; border: 1px solid #d6dfe9; border-radius: 6px; background: #fbfcfe; font: inherit; font-size: 11px; line-height: 1.4; outline: none; }
.offer-field textarea { resize: vertical; min-height: 53px; }
.offer-field input:focus, .offer-field select:focus, .offer-field textarea:focus, .offer-specification-row input:focus { border-color: #a45050; box-shadow: 0 0 0 2px #8000000b; }
.offer-field input::placeholder, .offer-field textarea::placeholder, .offer-specification-row input::placeholder { color: #98a3b2; opacity: 1; }
.offer-field-hint { margin: -4px 0 0; color: #99a3b1; font-size: 9px; line-height: 1.45; }
.offer-toggles { display: flex; flex-wrap: wrap; gap: 6px 10px; padding-top: 1px; }
.offer-toggles label { display: flex; align-items: center; gap: 4px; color: #61718a; font-size: 10px; cursor: pointer; }
.offer-toggles input { width: 12px; height: 12px; margin: 0; accent-color: #800000; }
.offer-toggles .is-unavailable { opacity: .4; cursor: default; }
.offer-description-preview { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; margin: 0; padding: 7px 8px; border-radius: 6px; color: #7c8798; background: #f5f7fa; font-size: 10px; line-height: 1.5; white-space: pre-line; }
.offer-specifications { display: flex; flex-direction: column; gap: 7px; padding-top: 8px; border-top: 1px solid #edf0f5; }
.offer-base-specifications { display: flex; flex-direction: column; gap: 4px; margin: 0; color: #7d899c; font-size: 10px; }
.offer-base-specifications > div { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 8px; }
.offer-base-specifications dt, .offer-base-specifications dd { margin: 0; overflow-wrap: anywhere; }
.offer-base-specifications dd { color: #4e607b; }
.offer-specification-row { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.1fr) 20px; align-items: center; gap: 5px; }
.offer-specification-row input { padding: 6px; font-size: 10px; }
.offer-specification-row .offer-icon-button { width: 20px; }
.offer-text-button { display: inline-flex; align-items: center; justify-content: flex-start; gap: 4px; padding: 0; color: #7f3131; font-size: 10px; line-height: 1.5; text-align: left; }
.offer-text-button > span { margin-left: auto; color: #99a3b1; font-size: 9px; }
.offer-text-button:hover:not(:disabled) { color: #a21515; }
.offer-text-button--danger { color: #a45a5a; }
.offer-primary-button, .offer-secondary-button { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 9px 11px; border: 1px solid #800000; border-radius: 8px; background: #800000; color: #fff; font-size: 11px; font-weight: 650; }
.offer-primary-button:hover:not(:disabled) { background: #680000; }
.offer-secondary-button { background: #fff; color: #800000; border-color: #e2cccc; }
.offer-secondary-button:hover:not(:disabled) { background: #faf1f1; }
.offer-logistics-empty { display: flex; flex-direction: column; align-items: center; gap: 10px; padding: 18px 7px; text-align: center; }
.offer-logistics-empty__icon { display: grid; place-items: center; width: 60px; height: 60px; border-radius: 18px; background: #eaf0f8; color: #667d9c; }
.offer-logistics-empty h4 { margin: 2px 0 0; font-size: 13px; font-weight: 700; }
.offer-logistics-empty p { max-width: 260px; margin: 0 0 5px; color: #7c899c; font-size: 11px; line-height: 1.6; }
@media (max-width: 600px) { .mail-offer-panel { width: 100%; } }
</style>
