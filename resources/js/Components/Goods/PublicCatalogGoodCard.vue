<script setup>
import { computed, ref, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import { usePublicGoodUrl } from '@/Composables/usePublicGoodUrl'
import { catalogImage, catalogMoney, catalogStatus, publicCatalogOffer } from '@/utils/publicCatalog'
import { unitLabel } from '@/utils/goodMeasurement'
import PublicCatalogGoodActions from '@/Components/Goods/PublicCatalogGoodActions.vue'
import GoodStockAlertButton from '@/Components/Goods/GoodStockAlertButton.vue'

const props = defineProps({ good: { type: Object, required: true } })
defineEmits(['inquiry', 'notice'])
const { goodPublicUrl } = usePublicGoodUrl()
const imageFailed = ref(false)
const image = computed(() => catalogImage(props.good))
watch(image, () => { imageFailed.value = false })
const productUrl = computed(() => props.good.public_url || goodPublicUrl(props.good))
const status = computed(() => catalogStatus(props.good))
const offer = computed(() => publicCatalogOffer(props.good))
const hasUnit = computed(() => Boolean(offer.value.measurement?.measure_id && offer.value.measurement?.unit_label))
const packageWeight = computed(() => {
    const weight = Number(offer.value.package_weight ?? props.good.denominator)
    return Number.isFinite(weight) && weight > 0 ? weight : null
})
const packagePrice = computed(() => {
    const factor = Number(offer.value.measurement?.kilograms_per_unit)
    if (!offer.value.price || !packageWeight.value || !hasUnit.value || !Number.isFinite(factor) || factor <= 0) return null
    const units = packageWeight.value / factor
    // Packaging describes physical weight; the customer's quantity always uses the configured unit.
    if (Math.abs(units - 1) < 0.000001) return null
    const amount = Number(offer.value.package_price ?? offer.value.price * units)
    return Number.isFinite(amount) && amount > 0 ? catalogMoney({ public_purchase: { ...offer.value, price: amount } }) : null
})
const weightLabel = computed(() => packageWeight.value?.toLocaleString('ru-RU', { maximumFractionDigits: 3 }))
</script>

<template>
    <article class="catalog-card">
        <div class="catalog-card__index">
            <span class="catalog-card__status" :class="{ 'catalog-card__status--stock': status.value === 'in_stock' }"><i aria-hidden="true" />{{ status.label }}</span>
            <span v-if="good.id != null" class="catalog-card__reference">Товар № {{ good.id }}</span>
        </div>
        <div class="catalog-card__identity">
            <Link :href="productUrl" class="catalog-card__image" :aria-label="`Открыть ${good.name}`">
                <img v-if="image && !imageFailed" :src="image" :alt="good.name" loading="lazy" @error="imageFailed = true">
                <span v-else class="catalog-card__placeholder">
                    <svg viewBox="0 0 32 32" fill="none" aria-hidden="true"><path d="m5 10 11-6 11 6v13l-11 6L5 23V10Z M5 10l11 6 11-6 M16 16v13 M10.5 7l11 6v5" /></svg>
                    <span>Фото уточним</span>
                </span>
            </Link>
            <div class="catalog-card__heading">
                <Link :href="productUrl" class="catalog-card__title">{{ good.name }}</Link>
                <span class="catalog-card__country"><img v-if="good.country?.flag" :src="good.country.flag" alt="">{{ good.country?.name || 'Страна уточняется' }}</span>
            </div>
        </div>
        <p v-if="good.description" class="catalog-card__description">{{ good.description }}</p>
        <dl class="catalog-card__facts">
            <div><dt>Фасовка</dt><dd>{{ packageWeight ? `${weightLabel} кг` : 'Уточняется' }}</dd></div>
            <div><dt>Единица заказа</dt><dd>{{ hasUnit ? unitLabel(offer.measurement) : 'Уточняется' }}</dd></div>
        </dl>
        <div class="catalog-card__quote">
            <div class="catalog-card__price" :class="{ 'catalog-card__price--request': !offer.price }">
                <strong>{{ catalogMoney(good) }}</strong>
                <span v-if="offer.price && hasUnit">/ {{ unitLabel(offer.measurement) }}</span>
                <small v-if="offer.price && offer.includes_vat">с НДС</small>
            </div>
            <span v-if="packagePrice" class="catalog-card__package-price">≈ {{ packagePrice }} <span>за упаковку {{ weightLabel }} кг</span></span>
        </div>
        <PublicCatalogGoodActions :good="good" @inquiry="$emit('inquiry', $event)" @notice="$emit('notice', $event)" />
        <GoodStockAlertButton v-if="status.value !== 'in_stock'" :good="good" compact label="Оповестить о поступлении">
            <template #trigger="{ subscribe, loading, label }">
                <button type="button" class="catalog-card__stock-alert" :disabled="loading" :aria-busy="loading" @click.stop.prevent="subscribe">
                    <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 14h12l-1.5-2V8a4.5 4.5 0 0 0-9 0v4L4 14Z M8 17h4 M10 2v1" /></svg>
                    {{ loading ? 'Оформляем оповещение…' : label }}
                    <span v-if="!loading" class="catalog-card__stock-channel">MAX</span>
                </button>
            </template>
        </GoodStockAlertButton>
    </article>
</template>

<style scoped>
.catalog-card { position: relative; display: flex; flex-direction: column; min-width: 0; gap: 12px; padding: 13px 14px 12px; border: 1px solid #dddcd3; border-radius: 3px 14px 3px 3px; background: #fffefa; color: #333b31; box-shadow: 0 2px 0 #e9e7df; transition: border-color .16s, box-shadow .16s; }
.catalog-card::before { position: absolute; top: -1px; left: 14px; width: 30px; height: 3px; background: #800000; content: ''; }
.catalog-card:hover, .catalog-card:focus-within { border-color: #b9b4a5; box-shadow: 0 3px 0 #dedbd0, 0 8px 20px #30201508; }
.catalog-card__index { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: baseline; gap: 4px 8px; min-height: 15px; }
.catalog-card__status { display: inline-flex; align-items: center; gap: 5px; color: #806327; font-size: 10px; font-weight: 650; line-height: 1.4; }
.catalog-card__status i { flex: 0 0 5px; width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
.catalog-card__status--stock { color: #386443; }
.catalog-card__reference { color: #838173; font-size: 9px; font-variant-numeric: tabular-nums; }
.catalog-card__identity { display: grid; grid-template-columns: 76px minmax(0, 1fr); align-items: start; gap: 12px; }
.catalog-card__image { display: grid; width: 76px; height: 76px; place-items: center; overflow: hidden; border: 1px solid #ece9df; border-radius: 2px; background: #f5f3ec; color: #a7a493; text-decoration: none; }
.catalog-card__image img { display: block; width: 100%; height: 100%; object-fit: contain; padding: 4px; mix-blend-mode: multiply; }
.catalog-card__placeholder { display: grid; justify-items: center; gap: 6px; }
.catalog-card__placeholder svg { width: 28px; height: 28px; stroke: currentColor; stroke-width: 1.2; stroke-linejoin: round; }
.catalog-card__placeholder span { color: #858272; font-size: 8px; }
.catalog-card__heading { display: flex; flex-direction: column; gap: 8px; min-width: 0; }
.catalog-card__title { color: #30372d; font-size: 14px; font-weight: 750; line-height: 1.4; text-decoration: none; overflow-wrap: anywhere; }
.catalog-card__title:hover { color: #800000; text-decoration: underline; text-underline-offset: 3px; }
.catalog-card__country { display: inline-flex; align-items: center; gap: 5px; color: #777b6e; font-size: 10px; line-height: 1.4; }
.catalog-card__country img { flex: 0 0 14px; width: 14px; height: 10px; object-fit: cover; }
.catalog-card__description { display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; overflow: hidden; margin: -2px 0 0; color: #777b6e; font-size: 11px; line-height: 1.5; overflow-wrap: anywhere; }
.catalog-card__facts { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: auto 0 0; padding: 10px 0; border-block: 1px solid #e9e7dd; }
.catalog-card__facts > div + div { padding-left: 12px; border-left: 1px solid #e9e7dd; }
.catalog-card__facts dt { margin-bottom: 3px; color: #7c8072; font-size: 9px; line-height: 1.3; }
.catalog-card__facts dd { margin: 0; color: #51594a; font-size: 11px; font-weight: 650; line-height: 1.3; overflow-wrap: anywhere; }
.catalog-card__quote { display: grid; gap: 3px; }
.catalog-card__price { display: flex; flex-wrap: wrap; align-items: baseline; gap: 4px; line-height: 1.25; }
.catalog-card__price strong { color: #492c26; font-size: 23px; font-weight: 750; letter-spacing: -.7px; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
.catalog-card__price--request strong { font-size: 17px; letter-spacing: -.2px; }
.catalog-card__price > span { color: #747568; font-size: 11px; }
.catalog-card__price small { margin-left: auto; color: #747568; font-size: 9px; white-space: nowrap; }
.catalog-card__package-price { color: #6d7465; font-size: 10px; line-height: 1.5; font-variant-numeric: tabular-nums; }
.catalog-card__package-price > span { color: #868776; }
.catalog-card__stock-alert { display: flex; align-items: center; justify-content: flex-start; gap: 5px; width: 100%; padding: 7px 0 0; border-top: 1px dashed #d9d5c7; color: #796b43; font-size: 10px; line-height: 1.4; text-align: left; cursor: pointer; }
.catalog-card__stock-alert svg { flex: 0 0 15px; width: 15px; height: 15px; stroke: currentColor; stroke-width: 1.4; stroke-linecap: round; stroke-linejoin: round; }
.catalog-card__stock-alert:hover { color: #800000; }
.catalog-card__stock-alert:disabled { cursor: wait; opacity: .6; }
.catalog-card__stock-channel { margin-left: auto; color: #898375; font-size: 8px; letter-spacing: .6px; }
a:focus-visible, button:focus-visible { outline: 2px solid #800000; outline-offset: 3px; }
@media (prefers-reduced-motion: reduce) { .catalog-card { transition: none; } }
</style>
