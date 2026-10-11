<script setup>
import { computed } from 'vue'
import ApartmentSelector from '@/Components/Geography/Buildings/ApartmentSelector.vue'
import OrderEntityPicker from '@/Components/Orders/OrderEntityPicker.vue'
import { unitLabel, measurementForGood } from '@/utils/goodMeasurement.js'

const props = defineProps({ editor: { type: Object, required: true } })
const emit = defineEmits(['cancel'])
const disabled = computed(() => props.editor.loadingOptions || !props.editor.optionsReady || props.editor.saving || props.editor.savingDate || props.editor.stale)
const selectedBuildings = computed(() => props.editor.options.buildings.filter(building => props.editor.form.building_ids.some(id => Number(id) === Number(building.id))))
const buildingTitle = building => [typeof building.city === 'string' ? building.city : building.city?.name, building.address].filter(Boolean).join(', ') || `Здание #${building.id}`
const money = (value, currency = 'RUB', digits = 2) => `${Number(value || 0).toLocaleString('ru-RU', { maximumFractionDigits: digits })} ${currency === 'RUB' ? '₽' : currency}`
const priceTitle = (price, item) => `${price.price_type?.name || 'Цена'} · ${money(price.unit_price ?? price.price_gross, price.currency_code || 'RUB', 4)} / ${price.unit_label || props.editor.itemUnit(item)}`
const errorFor = field => (props.editor.errors[field] || []).join(' ')
const goodTitle = good => [good.name || good.good_name, good.article ? `арт. ${good.article}` : '', unitLabel(measurementForGood(good))].filter(Boolean).join(' · ')
</script>

<template>
    <form class="order-form" :aria-label="editor.isCreating ? 'Создание заказа' : 'Редактирование заказа'" @submit.prevent="editor.saveOrder">
        <div v-if="editor.loadingOptions" class="order-form__loading" role="status">Загрузка справочников…</div>
        <fieldset :disabled="disabled" class="order-form__fields">
            <section class="order-form__section">
                <h3><v-icon icon="mdi-receipt-text-outline" size="15" /> Параметры заказа</h3>
                <div class="order-form__grid order-form__grid--three">
                    <label class="order-form__field"><span>Номер заказа</span><input v-model="editor.form.number" maxlength="40" :placeholder="editor.isCreating ? 'Автоматически' : ''" :aria-invalid="!!editor.errors.number" /><small v-if="errorFor('number')" class="order-form__error">{{ errorFor('number') }}</small></label>
                    <label class="order-form__field"><span>Статус</span><select v-model="editor.form.order_status_id" :aria-invalid="!!editor.errors.order_status_id"><option v-for="status in editor.options.statuses" :key="status.id" :value="status.id">{{ status.name }}</option></select></label>
                    <label class="order-form__field"><span>Дата и время заказа</span><input v-model="editor.form.submitted_at" type="datetime-local" :aria-invalid="!!editor.errors.submitted_at" /></label>
                </div>
            </section>

            <section class="order-form__section">
                <h3><v-icon icon="mdi-domain" size="15" /> Покупатель и доставка</h3>
                <OrderEntityPicker v-model="editor.form.entity_id" :entities="editor.options.entities" :disabled="disabled" :error-messages="editor.errors.entity_id" />
                <v-autocomplete v-model="editor.form.building_ids" :items="editor.sortedBuildings" :item-title="buildingTitle" item-value="id" label="Адреса доставки" :disabled="disabled" :error-messages="editor.errors.building_ids" variant="outlined" density="compact" hide-details="auto" multiple chips closable-chips clearable no-data-text="Адрес не найден">
                    <template #item="{ props: optionProps, item }">
                        <v-list-item v-bind="optionProps" role="option" :aria-selected="editor.form.building_ids.some(id => String(id) === String(item.raw.id))" :title="buildingTitle(item.raw)" :subtitle="editor.relatedBuildingIds.has(String(item.raw.id)) ? 'Связано с покупателем' : undefined">
                            <template #append><v-icon v-if="editor.relatedBuildingIds.has(String(item.raw.id))" icon="mdi-link-variant" size="16" color="primary" /></template>
                        </v-list-item>
                    </template>
                </v-autocomplete>
                <small v-if="editor.relatedBuildingIds.size" class="order-form__hint">Связанные с покупателем здания — в начале списка. Можно выбрать любой адрес.</small>
                <ApartmentSelector v-for="building in selectedBuildings" :key="building.id" v-model="editor.form.building_apartments[building.id]" :building="building" :disabled="disabled" :error-messages="editor.errors[`building_apartments.${building.id}`]" />
                <div class="order-form__grid" :class="{ 'order-form__grid--single': !editor.canEditDelivery }">
                    <label v-if="editor.canEditDelivery" class="order-form__field"><span>Дата доставки</span><input v-model="editor.form.delivery_date" type="date" min="1000-01-01" max="9999-12-31" :aria-invalid="!!editor.errors.delivery_date" /><small v-if="errorFor('delivery_date')" class="order-form__error">{{ errorFor('delivery_date') }}</small></label>
                    <label class="order-form__field"><span>Удобное время доставки</span><input v-model="editor.form.preferred_delivery_time" placeholder="Например, после 14:00" :aria-invalid="!!editor.errors.preferred_delivery_time" /></label>
                </div>
            </section>

            <section class="order-form__section order-form__section--items">
                <div class="order-form__section-heading"><h3><v-icon icon="mdi-package-variant-closed" size="15" /> Состав заказа <span class="order-form__count">{{ editor.form.items.length }}</span></h3><button type="button" class="order-form__text-button" @click="editor.addItem">+ Добавить товар</button></div>
                <div v-for="(item, index) in editor.form.items" :key="item._key" class="order-form__line">
                    <div class="order-form__line-selection">
                        <v-autocomplete v-model="item.good_id" class="order-form__good" :items="editor.options.goods" :item-title="goodTitle" item-value="id" :label="`Товар ${index + 1}`" :disabled="disabled" :error-messages="editor.errors[`items.${index}.good_id`]" variant="outlined" density="compact" hide-details="auto" no-data-text="Товар не найден" @update:model-value="editor.selectItemGood(item)">
                            <template #item="{ props: optionProps, item: good }"><v-list-item v-bind="optionProps" role="option" :aria-selected="String(item.good_id) === String(good.raw.id)" :title="good.raw.name" :subtitle="[good.raw.article ? `Артикул ${good.raw.article}` : '', `Единица учёта: ${unitLabel(measurementForGood(good.raw))}`].filter(Boolean).join(' · ')" /></template>
                            <template #selection="{ item: good }"><span class="order-form__good-name">{{ good.raw.name }}</span><span class="order-form__unit">{{ editor.itemUnit(item) }}</span></template>
                        </v-autocomplete>
                        <label class="order-form__field order-form__catalog-price"><span>Цена из базы</span><select :value="item._price_id ?? ''" :aria-label="`Цена из базы для товара ${index + 1}`" :disabled="!editor.itemPrices(item).length" @change="editor.selectItemPrice(item, $event.target.value)"><option value="">{{ editor.itemPrices(item).length ? 'Выбрать цену · или ввести ниже' : 'Нет цен · введите вручную' }}</option><option v-for="price in editor.itemPrices(item)" :key="price.id" :value="price.id">{{ priceTitle(price, item) }}</option></select></label>
                    </div>
                    <small v-if="item.good_id && !editor.itemMeasurement(item).measure_id" class="order-form__error">Задайте единицу учёта в карточке товара.</small>
                    <div class="order-form__line-values">
                        <label class="order-form__field"><span>Количество, {{ editor.itemUnit(item) }}</span><input v-model="item.quantity" :aria-label="`Количество товара ${index + 1}, ${editor.itemUnit(item)}`" type="number" min="0.001" step="any" inputmode="decimal" :aria-invalid="!!editor.errors[`items.${index}.quantity`]" @input="editor.changeItemQuantity(item)" /></label>
                        <label class="order-form__field"><span>Цена / {{ editor.itemUnit(item) }}</span><input v-model="item.unit_price" :aria-label="`Цена товара ${index + 1} за ${editor.itemUnit(item)}`" type="number" min="0" step="any" inputmode="decimal" :aria-invalid="!!editor.errors[`items.${index}.unit_price`]" @input="editor.changeItemPrice(item)" /></label>
                        <label class="order-form__field"><span>Валюта</span><select v-model="item.currency_code" :aria-label="`Валюта товара ${index + 1}`" :aria-invalid="!!editor.errors[`items.${index}.currency_code`]" @change="item._price_id = null"><option v-for="currency in editor.options.currency_codes" :key="currency" :value="currency">{{ currency }}</option></select></label>
                        <label class="order-form__field order-form__amount"><span>Сумма</span><input :value="editor.itemAmount(item)" :aria-label="`Сумма товара ${index + 1}`" :disabled="!(Number(item.quantity) > 0)" type="number" min="0" step="any" inputmode="decimal" :aria-invalid="!!editor.errors[`items.${index}.line_total`]" @input="editor.setItemTotal(item, $event.target.value)" /></label>
                        <button type="button" class="order-form__remove" :aria-label="`Удалить товар ${index + 1}`" @click="editor.removeItem(index)"><v-icon icon="mdi-trash-can-outline" size="17" /></button>
                    </div>
                    <small v-for="field in ['quantity', 'unit_price', 'currency_code', 'line_total', 'measure_id']" v-show="errorFor(`items.${index}.${field}`)" :key="field" class="order-form__error">{{ errorFor(`items.${index}.${field}`) }}</small>
                </div>
                <div class="order-form__totals"><span class="order-form__hint">При изменении суммы цена пересчитывается автоматически.</span><div><span>Итого</span><strong v-for="(amount, currency) in editor.totalsByCurrency" :key="currency">{{ money(amount, currency) }}</strong><strong v-if="!Object.keys(editor.totalsByCurrency).length">0 ₽</strong></div></div>
            </section>

            <section class="order-form__section">
                <label class="order-form__field"><span>Внутренний комментарий</span><textarea v-model="editor.form.internal_comment" rows="2" placeholder="Дополнительная информация для команды" :aria-invalid="!!editor.errors.internal_comment" /></label>
            </section>
        </fieldset>
        <footer class="order-form__actions">
            <span>{{ editor.form.items.filter(item => item.good_id).length }} поз. <template v-if="editor.weight !== null">· {{ Number(editor.weight).toLocaleString('ru-RU', { maximumFractionDigits: 3 }) }} кг</template></span>
            <button type="button" class="order-form__button" :disabled="editor.saving || editor.savingDate" @click="emit('cancel')">Отмена</button>
            <button type="submit" class="order-form__button order-form__button--primary" :disabled="disabled || (!editor.isCreating && !editor.dirty)">{{ (editor.saving || editor.savingDate) ? 'Сохранение…' : editor.isCreating ? 'Создать заказ' : 'Сохранить изменения' }}</button>
        </footer>
    </form>
</template>

<style scoped>
.order-form { font-size: 12px; color: var(--details-text, #253047); }
.order-form__fields { min-width: 0; border: 0; padding: 0; margin: 0; }
.order-form__section { display: grid; gap: 11px; padding: 14px 18px; border-bottom: 1px solid var(--details-border, #dce2ea); }
.order-form h3 { display: flex; align-items: center; gap: 6px; margin: 0; color: var(--details-muted, #6b7280); font-size: 11px; font-weight: 700; }
.order-form__grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 11px; }
.order-form__grid--three { grid-template-columns: 1fr 1fr 1.3fr; }
.order-form__grid--single { grid-template-columns: 1fr; }
.order-form__field { display: flex; flex-direction: column; gap: 5px; min-width: 0; }
.order-form__field > span { font-size: 10px; color: var(--details-muted, #6b7280); }
.order-form__field input, .order-form__field select, .order-form__field textarea { width: 100%; min-width: 0; min-height: 34px; padding: 6px 9px; border: 1px solid var(--details-border, #dce2ea); border-radius: 6px; background: var(--details-panel, #fff); color: var(--details-text, #253047); font: inherit; }
.order-form__field select { padding-right: 23px; }
.order-form__field textarea { resize: vertical; }
.order-form__field input:focus, .order-form__field select:focus, .order-form__field textarea:focus { outline: 2px solid var(--details-accent, #3674d9); outline-offset: 1px; }
.order-form [aria-invalid="true"] { border-color: #d85e70; }
.order-form__section-heading { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.order-form__count { padding: 1px 6px; border-radius: 8px; background: var(--details-panel, #fff); color: var(--details-text, #253047); font-size: 10px; }
.order-form__text-button { color: var(--details-accent, #3674d9); font-size: 11px; font-weight: 600; }
.order-form__line { display: grid; gap: 9px; padding: 11px; border: 1px solid var(--details-border, #dce2ea); border-radius: 8px; background: var(--details-panel, #fff); }
.order-form__line-selection { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr); gap: 10px; align-items: end; }
.order-form__line-values { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.1fr) 78px minmax(0, 1.2fr) 25px; align-items: end; gap: 9px; }
.order-form__good-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.order-form__unit { flex-shrink: 0; margin-left: 6px; padding: 1px 5px; border-radius: 4px; background: color-mix(in srgb, var(--details-accent, #3674d9) 10%, transparent); color: var(--details-accent, #3674d9); font-size: 10px; }
.order-form__amount input { font-weight: 650; }
.order-form__remove { height: 34px; border-radius: 5px; color: var(--details-muted, #6b7280); }
.order-form__remove:hover { background: #e7788815; color: #d85e70; }
.order-form__hint { color: var(--details-muted, #6b7280); font-size: 10px; }
.order-form__error { color: #d85e70; font-size: 10px; }
.order-form__totals { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.order-form__totals > div { display: flex; align-items: baseline; justify-content: flex-end; flex-wrap: wrap; gap: 6px 12px; }
.order-form__totals > div > span { color: var(--details-muted, #6b7280); font-size: 10px; }
.order-form__totals strong { white-space: nowrap; font-size: 14px; }
.order-form__actions { display: flex; align-items: center; justify-content: flex-end; flex-wrap: wrap; gap: 8px; position: sticky; bottom: 0; z-index: 2; padding: 12px 18px; background: var(--details-header, #f7f9fc); border-top: 1px solid var(--details-border, #dce2ea); }
.order-form__actions > span { flex: 1; color: var(--details-muted, #6b7280); font-size: 10px; }
.order-form__button { min-height: 35px; padding: 6px 14px; border: 1px solid var(--details-border, #dce2ea); border-radius: 6px; color: var(--details-text, #253047); background: var(--details-panel, #fff); font-size: 11px; font-weight: 600; }
.order-form__button--primary { color: var(--details-bg, #fff); background: var(--details-accent, #3674d9); border-color: var(--details-accent, #3674d9); }
.order-form button:focus-visible { outline: 2px solid var(--details-accent, #3674d9); outline-offset: 2px; }
.order-form button:disabled, .order-form__fields:disabled { opacity: .6; }
.order-form__loading { padding: 12px 18px; color: var(--details-muted, #6b7280); }
.order-form :deep(.v-field) { background: var(--details-panel, #fff); border-radius: 6px; font-size: 12px; }
.order-form :deep(.v-field__input) { min-height: 36px; padding-top: 5px; padding-bottom: 5px; }
.order-form :deep(.v-field-label) { font-size: 12px; }
.order-form :deep(.v-chip) { max-width: 100%; font-size: 11px; }
.order-form :deep(.v-input__details) { min-height: 0; }
.order-form :deep(.apartment-selector__control > .v-select) { min-width: 0; flex-basis: 100%; }
@media (max-width: 600px) {
    .order-form__section { padding: 12px; }
    .order-form__grid--three { grid-template-columns: 1fr 1fr; }
    .order-form__grid--three > :last-child { grid-column: 1 / -1; }
    .order-form__line-selection { grid-template-columns: 1fr; }
    .order-form__line-values { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) 26px; }
    .order-form__line-values > :nth-child(3) { grid-column: 1; grid-row: 2; }
    .order-form__line-values > :nth-child(4) { grid-column: 2; grid-row: 2; }
    .order-form__remove { grid-column: 3; grid-row: 2; }
    .order-form__totals { align-items: flex-end; }
    .order-form__totals > .order-form__hint { max-width: 50%; }
    .order-form__actions { padding: 10px 12px; }
    .order-form__button { padding: 6px 10px; }
}
</style>
