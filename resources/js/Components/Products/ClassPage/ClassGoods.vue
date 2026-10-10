<script setup>
import { ref } from 'vue'

defineProps({
    title: { type: String, required: true },
    goods: { type: Array, default: () => [] },
    copy: { type: Object, default: () => ({}) },
    helpUrl: { type: String, default: '#guide' },
})
const brokenImages = ref(new Set())
const number = value => Number(value).toLocaleString('ru-RU', { maximumFractionDigits: 2 })
const money = (value, currency) => `${number(value)} ${currency === 'RUB' ? '₽' : currency || ''}`.trim()
const stockLabel = good => ({ in_stock: 'В наличии', out_of_stock: 'Ожидаем поступление', preorder: 'Под заказ', on_request: 'Наличие уточним' }[good.availability?.status] || 'Наличие уточним')
const hasPrice = good => Number.isFinite(Number(good.offer?.price)) && Number(good.offer?.price) > 0
function imageFailed(id) { brokenImages.value = new Set([...brokenImages.value, id]) }
</script>

<template>
    <section id="catalog" class="section wrap" data-class-goods>
        <div class="section-heading"><div><div class="eyebrow">{{ copy.eyebrow ?? '01 / ВЫБРАТЬ И ЗАКАЗАТЬ' }}</div><h2>{{ title }}<span class="count">{{ goods.length }}</span></h2></div><p style="white-space: pre-line">{{ copy.description ?? 'Опт и розница.\nЦена и условия — в карточке товара.' }}</p></div>
        <div v-if="goods.length" class="product-grid">
            <article v-for="(good, index) in goods" :key="good.id" class="product-card" :data-good-id="good.id">
                <a class="caliber-tile product-image" :class="index % 2 ? 'ice' : 'sea'" :href="good.url" :aria-label="good.name">
                    <img v-if="good.image && !brokenImages.has(good.id)" :src="good.image" :alt="good.image_alt || good.name" width="320" height="360" loading="lazy" @error="imageFailed(good.id)">
                    <span v-else class="product-image-empty">Фото уточняется<br>в карточке товара</span>
                </a>
                <div class="product-content">
                    <span class="product-tag" :class="{ 'product-tag--available': good.availability?.is_in_stock }">{{ stockLabel(good) }}</span>
                    <h3><a :href="good.url" :data-good-id="good.id">{{ good.name }}</a></h3>
                    <dl v-if="good.attributes?.length" class="product-facts">
                        <div v-for="attribute in good.attributes" :key="attribute.label"><dt>{{ attribute.label }}</dt><dd>{{ attribute.value }}</dd></div>
                    </dl>
                    <div class="product-offer">
                        <template v-if="hasPrice(good)">
                            <strong>{{ money(good.offer.price, good.offer.currency_code) }}<small> / {{ good.offer.price_unit_label }}</small></strong>
                            <span>{{ good.offer.includes_vat ? 'С НДС' : 'НДС уточняется при подтверждении' }}</span>
                            <span v-if="good.offer.package_weight">Фасовка: {{ number(good.offer.package_weight) }} кг / упаковка</span>
                        </template>
                        <span v-else>Цена по запросу</span>
                    </div>
                    <div class="product-bottom"><span>Условия в карточке</span><a class="button compact dark" :href="good.url" :data-good-id="good.id" :aria-label="`Открыть товар ${good.name}`">К товару</a></div>
                </div>
            </article>
        </div>
        <p v-else class="catalog-empty" role="status">Сейчас в этом разделе нет опубликованных товаров. Гид поможет подготовиться к выбору партии.</p>
        <div class="catalog-note"><span>{{ copy.note ?? 'Характеристики, наличие и документы конкретной партии уточняются при заказе.' }}</span><a v-if="helpUrl" :href="helpUrl">Помочь с выбором?</a></div>
    </section>
</template>
