<script setup>
import { computed, ref } from 'vue'
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
const status = computed(() => catalogStatus(props.good))
const offer = computed(() => publicCatalogOffer(props.good))
const packageWeight = computed(() => Number(props.good.denominator) > 0 ? `${Number(props.good.denominator).toLocaleString('ru-RU')} кг` : null)
</script>

<template>
    <article class="catalog-card">
        <Link :href="good.public_url || goodPublicUrl(good)" class="catalog-card__image" :aria-label="good.name">
            <img v-if="catalogImage(good) && !imageFailed" :src="catalogImage(good)" :alt="good.name" loading="lazy" @error="imageFailed = true">
            <v-icon v-else icon="mdi-package-variant-closed" size="44" />
            <span class="catalog-card__status" :class="{ 'catalog-card__status--stock': status.value === 'in_stock' }"><i />{{ status.label }}</span>
        </Link>
        <div class="catalog-card__body">
            <span class="catalog-card__country"><img v-if="good.country?.flag" :src="good.country.flag" alt="">{{ good.country?.name || 'Происхождение уточняется' }}</span>
            <Link :href="good.public_url || goodPublicUrl(good)" class="catalog-card__title">{{ good.name }}</Link>
            <p v-if="good.description" class="catalog-card__description">{{ good.description }}</p>
            <div class="catalog-card__facts"><span v-if="packageWeight">Фасовка <b>{{ packageWeight }}</b></span><span v-if="offer.measurement?.measure_id">Ед. <b>{{ unitLabel(offer.measurement) }}</b></span></div>
            <div class="catalog-card__price"><strong>{{ catalogMoney(good) }}</strong><span v-if="offer.price">/ {{ unitLabel(offer.measurement) }}<small v-if="offer.includes_vat"> · с НДС</small></span></div>
            <PublicCatalogGoodActions :good="good" @inquiry="$emit('inquiry', $event)" @notice="$emit('notice', $event)" />
            <GoodStockAlertButton v-if="status.value !== 'in_stock'" :good="good" compact label="Оповестить о поступлении" />
        </div>
    </article>
</template>

<style scoped>
.catalog-card { display: flex; flex-direction: column; overflow: hidden; border: 1px solid #e4e2dc; border-radius: 12px; background: #fff; transition: border-color .16s, box-shadow .16s; }
.catalog-card:hover { border-color: #c7b6a8; box-shadow: 0 8px 24px #3020150a; }
.catalog-card__image { position: relative; display: grid; height: 142px; place-items: center; overflow: hidden; background: #f3f2ee; color: #c5c3b8; }
.catalog-card__image img { width: 100%; height: 100%; object-fit: contain; padding: 9px; }
.catalog-card__status { position: absolute; left: 10px; bottom: 10px; display: inline-flex; align-items: center; gap: 5px; max-width: calc(100% - 20px); padding: 4px 7px; background: #fffef5ed; border: 1px solid #eae6d6; border-radius: 5px; color: #79622f; font-size: 10px; font-weight: 650; }
.catalog-card__status i { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
.catalog-card__status--stock { color: #30633f; background: #f2fff5ed; border-color: #dce9dd; }
.catalog-card__body { display: flex; flex-direction: column; flex: 1; gap: 9px; padding: 13px; }
.catalog-card__country { display: flex; align-items: center; gap: 5px; color: #7a7e75; font-size: 10px; }
.catalog-card__country img { width: 16px; height: 11px; object-fit: cover; }
.catalog-card__title { color: #2c3029; font-size: 14px; font-weight: 750; line-height: 1.4; text-decoration: none; overflow-wrap: anywhere; }
.catalog-card__title:hover { color: #800000; }
.catalog-card__description { display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; overflow: hidden; margin: 0; color: #7c7c72; font-size: 11px; line-height: 1.5; }
.catalog-card__facts { display: flex; gap: 13px; margin-top: auto; color: #878479; font-size: 10px; }
.catalog-card__facts b { margin-left: 3px; color: #575b50; font-weight: 600; }
.catalog-card__price { display: flex; flex-wrap: wrap; align-items: baseline; gap: 5px; padding-top: 10px; border-top: 1px solid #efeee8; }
.catalog-card__price strong { color: #342b27; font-size: 18px; font-weight: 750; }
.catalog-card__price span { color: #848076; font-size: 11px; }
.catalog-card__price small { font-size: inherit; }
.catalog-card :deep(.good-stock-alert-action .v-btn) { min-height: 28px; height: 28px; background: #f6f1e4 !important; color: #785c27 !important; border-radius: 5px !important; box-shadow: none; }
@media (max-width: 520px) { .catalog-card__image { height: 160px; } }
</style>
