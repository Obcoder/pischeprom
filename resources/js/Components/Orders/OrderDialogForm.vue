<script setup>
import { computed } from 'vue'
import ApartmentSelector from '@/Components/Geography/Buildings/ApartmentSelector.vue'

const props = defineProps({
    editor: { type: Object, required: true },
})
const emit = defineEmits(['cancel'])
const disabled = computed(() => props.editor.loadingOptions || !props.editor.optionsReady || props.editor.saving || props.editor.savingDate || props.editor.stale)
const selectedBuildings = computed(() => props.editor.options.buildings.filter(building => props.editor.form.building_ids.some(id => Number(id) === Number(building.id))))
const buildingTitle = building => [typeof building.city === 'string' ? building.city : building.city?.name, building.address].filter(Boolean).join(', ') || `Здание #${building.id}`
const money = value => `${Number(value || 0).toLocaleString('ru-RU', { maximumFractionDigits: 2 })} ${props.editor.form.currency_code === 'RUB' ? '₽' : props.editor.form.currency_code}`
</script>

<template>
    <form class="order-form" aria-label="Редактирование заказа" @submit.prevent="editor.saveOrder">
        <div v-if="editor.loadingOptions" class="order-form__loading" role="status">Загрузка справочников…</div>
        <fieldset :disabled="disabled" class="order-form__fields">
            <section class="order-form__section">
                <h3><v-icon icon="mdi-receipt-text-outline" size="15" /> Параметры заказа</h3>
                <div class="order-form__grid order-form__grid--three">
                    <label class="order-form__field"><span>Номер заказа</span><input v-model="editor.form.number" maxlength="40" :aria-invalid="!!editor.errors.number" /></label>
                    <label class="order-form__field"><span>Статус</span><select v-model="editor.form.order_status_id" :aria-invalid="!!editor.errors.order_status_id"><option v-for="status in editor.options.statuses" :key="status.id" :value="status.id">{{ status.name }}</option></select></label>
                    <label class="order-form__field"><span>Валюта</span><select v-model="editor.form.currency_code" :aria-invalid="!!editor.errors.currency_code"><option v-for="currency in editor.options.currency_codes" :key="currency" :value="currency">{{ currency }}</option></select></label>
                </div>
            </section>

            <section class="order-form__section">
                <h3><v-icon icon="mdi-domain" size="15" /> Покупатель и адрес</h3>
                <v-autocomplete v-model="editor.form.entity_id" :items="editor.options.entities" item-title="name" item-value="id" label="Покупатель" :disabled="disabled" :error-messages="editor.errors.entity_id" variant="outlined" density="compact" hide-details="auto" no-data-text="Покупатель не найден" />
                <v-autocomplete v-model="editor.form.building_ids" :items="editor.options.buildings" :item-title="buildingTitle" item-value="id" label="Адреса доставки" :disabled="disabled" :error-messages="editor.errors.building_ids" variant="outlined" density="compact" hide-details="auto" multiple chips closable-chips clearable no-data-text="Адрес не найден" />
                <ApartmentSelector v-for="building in selectedBuildings" :key="building.id" v-model="editor.form.building_apartments[building.id]" :building="building" :disabled="disabled" :error-messages="editor.errors[`building_apartments.${building.id}`]" />
            </section>

            <section class="order-form__section">
                <h3><v-icon icon="mdi-truck-delivery-outline" size="15" /> Доставка</h3>
                <div class="order-form__grid">
                    <label v-if="editor.canEditDelivery" class="order-form__field"><span>Дата доставки</span><input v-model="editor.form.delivery_date" type="date" min="1000-01-01" max="9999-12-31" :aria-invalid="!!editor.errors.delivery_date" /></label>
                    <label class="order-form__field"><span>Дата и время заказа</span><input v-model="editor.form.submitted_at" type="datetime-local" :aria-invalid="!!editor.errors.submitted_at" /></label>
                </div>
                <label class="order-form__field"><span>Удобное время доставки</span><input v-model="editor.form.preferred_delivery_time" placeholder="Например, после 14:00" :aria-invalid="!!editor.errors.preferred_delivery_time" /></label>
            </section>

            <section class="order-form__section">
                <div class="order-form__section-heading"><h3><v-icon icon="mdi-package-variant-closed" size="15" /> Товары</h3><button type="button" class="order-form__text-button" @click="editor.addItem">+ Добавить товар</button></div>
                <div v-for="(item, index) in editor.form.items" :key="item._key" class="order-form__line">
                    <v-autocomplete v-model="item.good_id" class="order-form__good" :items="editor.options.goods" item-title="name" item-value="id" :label="`Товар ${index + 1}`" :disabled="disabled" :error-messages="editor.errors[`items.${index}.good_id`]" variant="outlined" density="compact" hide-details="auto" no-data-text="Товар не найден" @update:model-value="editor.selectItemGood(item)" />
                    <label class="order-form__field"><span>Количество, {{ editor.itemUnit(item) }}</span><input v-model="item.quantity" :aria-label="`Количество товара ${index + 1}, ${editor.itemUnit(item)}`" type="number" min="0.001" step="any" inputmode="decimal" :aria-invalid="!!editor.errors[`items.${index}.quantity`]" /></label>
                    <label class="order-form__field"><span>Цена / {{ editor.itemUnit(item) }}</span><input v-model="item.unit_price" :aria-label="`Цена товара ${index + 1} за ${editor.itemUnit(item)}`" type="number" min="0" step="any" inputmode="decimal" :aria-invalid="!!editor.errors[`items.${index}.unit_price`]" /></label>
                    <small v-if="item.good_id && !editor.itemMeasurement(item).measure_id" class="order-form__good">Задайте единицу учёта в карточке товара.</small>
                    <div class="order-form__line-total"><span>Сумма</span><strong>{{ money(editor.itemTotal(item)) }}</strong></div>
                    <button type="button" class="order-form__remove" :aria-label="`Удалить товар ${index + 1}`" @click="editor.removeItem(index)"><v-icon icon="mdi-close" size="17" /></button>
                </div>
            </section>

            <section class="order-form__section">
                <label class="order-form__field"><span>Внутренний комментарий</span><textarea v-model="editor.form.internal_comment" rows="2" :aria-invalid="!!editor.errors.internal_comment" /></label>
            </section>
        </fieldset>
        <footer class="order-form__actions">
            <span>Изменения сохранятся в этом окне</span>
            <button type="button" class="order-form__button" :disabled="editor.saving || editor.savingDate" @click="emit('cancel')">Отмена</button>
            <button type="submit" class="order-form__button order-form__button--primary" :disabled="disabled || !editor.dirty">{{ (editor.saving || editor.savingDate) ? 'Сохранение…' : 'Сохранить изменения' }}</button>
        </footer>
    </form>
</template>

<style scoped>
.order-form { font-size: 12px; }
.order-form__fields { min-width: 0; border: 0; padding: 0; margin: 0; }
.order-form__section { display: grid; gap: 12px; padding: 14px 16px; border-bottom: 1px solid var(--details-border); }
.order-form h3 { display: flex; align-items: center; gap: 5px; margin: 0; color: var(--details-muted); font-size: 11px; font-weight: 700; }
.order-form__grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.order-form__grid--three { grid-template-columns: 1.2fr 1fr .7fr; }
.order-form__field { display: flex; flex-direction: column; gap: 5px; min-width: 0; }
.order-form__field > span, .order-form__line-total > span { font-size: 10px; color: var(--details-muted); }
.order-form input, .order-form select, .order-form textarea { width: 100%; min-width: 0; min-height: 34px; padding: 6px 9px; border: 1px solid var(--details-border); border-radius: 5px; background: var(--details-panel); color: var(--details-text); font: inherit; }
.order-form textarea { resize: vertical; }
.order-form input:focus, .order-form select:focus, .order-form textarea:focus { outline: 2px solid var(--details-accent); outline-offset: 1px; }
.order-form [aria-invalid="true"] { border-color: #d85e70; }
.order-form__section-heading { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.order-form__text-button { color: var(--details-accent); font-size: 11px; }
.order-form__line { display: grid; grid-template-columns: minmax(160px, 1fr) 88px 100px 90px 24px; align-items: end; gap: 9px; }
.order-form__line-total { display: flex; flex-direction: column; justify-content: center; gap: 6px; min-height: 50px; text-align: right; }
.order-form__line-total strong { font-size: 11px; overflow-wrap: anywhere; }
.order-form__remove { height: 34px; color: var(--details-muted); }
.order-form__remove:hover { color: #e77888; }
.order-form__actions { display: flex; align-items: center; justify-content: flex-end; flex-wrap: wrap; gap: 8px; position: sticky; bottom: 0; padding: 12px 16px; background: var(--details-header); border-top: 1px solid var(--details-border); }
.order-form__actions > span { flex: 1; color: var(--details-muted); font-size: 10px; }
.order-form__button { min-height: 34px; padding: 6px 12px; border: 1px solid var(--details-border); border-radius: 5px; color: var(--details-text); background: var(--details-panel); font-size: 11px; font-weight: 600; }
.order-form__button--primary { color: var(--details-bg); background: var(--details-accent); border-color: var(--details-accent); }
.order-form button:focus-visible { outline: 2px solid var(--details-accent); outline-offset: 2px; }
.order-form button:disabled, .order-form__fields:disabled { opacity: .6; }
.order-form__loading { padding: 12px 16px; color: var(--details-muted); }
.order-form :deep(.v-field) { background: var(--details-panel); border-radius: 5px; font-size: 12px; }
.order-form :deep(.v-field__input) { min-height: 36px; padding-top: 5px; padding-bottom: 5px; }
.order-form :deep(.v-field-label) { font-size: 12px; }
.order-form :deep(.v-chip) { max-width: 100%; font-size: 11px; }
.order-form :deep(.v-input__details) { min-height: 0; }
.order-form :deep(.apartment-selector__control > .v-select) { min-width: 0; flex-basis: 100%; }
@media (max-width: 600px) {
    .order-form__section { padding: 12px; }
    .order-form__grid, .order-form__grid--three { grid-template-columns: 1fr; gap: 10px; }
    .order-form__line { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) 24px; padding-bottom: 12px; border-bottom: 1px solid var(--details-border); }
    .order-form__good { grid-column: 1 / -1; }
    .order-form__line-total { grid-column: 1 / 3; min-height: 24px; flex-direction: row; align-items: center; justify-content: flex-end; }
    .order-form__remove { grid-column: 3; grid-row: 2; }
    .order-form__actions { padding: 10px 12px; }
    .order-form__actions > span { flex-basis: 100%; }
    .order-form__button { flex: 1; padding: 6px 9px; }
}
</style>
