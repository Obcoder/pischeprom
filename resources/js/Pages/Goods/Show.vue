<script setup>
import { computed, h, onMounted, ref, watch } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import LayoutProduct from '@/Layouts/LayoutProduct.vue'
import GoodInquiryDialog from '@/Components/Goods/GoodInquiryDialog.vue'
import GoodProductReel from '@/Components/Goods/GoodProductReel.vue'
import MobileGoodPurchase from '@/Components/Goods/MobileGoodPurchase.vue'
import GoodStockAlertButton from '@/Components/Goods/GoodStockAlertButton.vue'
import { useYandexMetrica } from '@/Composables/useYandexMetrica'
import { usePublicGoodUrl } from '@/Composables/usePublicGoodUrl'
import { goodAvailabilityStatus, canSubscribeToGoodStock } from '@/Pages/Helpers/goodAvailability'

defineOptions({ layout: LayoutProduct })
const props = defineProps({
    good: { type: Object, required: true },
    relatedGoods: { type: Array, default: () => [] },
    seo: { type: Object, default: () => ({}) },
    availability: { type: Object, default: () => ({}) },
    publicPurchase: { type: Object, default: () => ({}) },
})
const { goodPublicUrl } = usePublicGoodUrl()
const { reachGoal, ecommerceViewItem } = useYandexMetrica(props.seo.metricaCounterId || import.meta.env.VITE_YANDEX_METRICA_COUNTER_ID)
const selectedMedia = ref(0)
const quantity = ref(1)
const dialogOpen = ref(false)
const dialogKind = ref('order')
const zoomOpen = ref(false)
const maxUnavailable = ref(false)
const brokenImages = ref(new Set())
const purchase = computed(() => ({
    ...props.publicPurchase,
    unit: props.publicPurchase.price_unit_label || 'упаковка',
    currency: props.publicPurchase.currency_code || 'RUB',
}))
const weight = computed(() => Number(purchase.value.package_weight || props.good.denominator) || null)
const safeQuantity = computed(() => Math.min(9999, Math.max(1, Math.floor(Number(quantity.value) || 1))))
const total = computed(() => purchase.value.package_price > 0 ? purchase.value.package_price * safeQuantity.value : null)
const status = computed(() => goodAvailabilityStatus(props.good, props.availability))
const statusText = computed(() => ({ in_stock: 'В наличии', out_of_stock: 'Ожидаем поступление', preorder: 'Под заказ', on_request: 'Наличие уточним' }[status.value] || 'Наличие уточним'))
const subscribeAvailable = computed(() => canSubscribeToGoodStock(props.good, props.availability))
const country = computed(() => props.good.country?.name)
const products = computed(() => (props.good.products || []).map(p => ({ id: p.id, name: p.rus || p.name || p.eng })).filter(p => p.name))
const mainVideo = computed(() => {
    const videos = (props.good.published_media || []).filter(m => m.is_published && m.type === 'video' && (m.video_mp4_url || m.url))
    return videos.find(m => m.is_main_video && m.processing_status === 'done')
        || videos.find(m => m.processing_status === 'done')
        || videos[0] || null
})
const media = computed(() => {
    const all = (props.good.published_media || []).filter(m => m.is_published && (m.type === 'image' || m.type === 'video'))
        .sort((a, b) => Number(b.is_ava) - Number(a.is_ava) || (a.sort_order ?? 100) - (b.sort_order ?? 100) || a.id - b.id)
    const images = all.filter(m => m.type === 'image' && m.url)
    if (!images.length && (props.good.ava_image || props.good.ava_thumb)) images.push({ id: 'avatar', type: 'image', url: props.good.ava_image || props.good.ava_thumb })
    return [...images, ...all.filter(m => m.type === 'video' && m.id !== mainVideo.value?.id && (m.video_mp4_url || m.url))]
})
const activeMedia = computed(() => media.value[selectedMedia.value] || media.value[0])
const pageTitle = computed(() => props.seo.title || `${props.good.name} — Пищепром-сервер`)
const description = computed(() => props.seo.description || props.good.description || `Закажите ${props.good.name}. Обсудим объём, цену и условия поставки.`)
const pageH1 = computed(() => props.seo.h1 || props.good.name)
const seoText = computed(() => props.good.seo?.is_active !== false ? props.good.seo?.seo_text : null)
const detailParagraphs = computed(() => String(seoText.value || props.good.description || '').split(/\n+/).map(t => t.trim()).filter(Boolean))
const JsonLdHead = () => h('script', { 'head-key': 'good-structured-data', type: 'application/ld+json' }, JSON.stringify(props.seo.jsonLd).replace(/</g, '\\u003c'))
const facts = computed(() => [
    { label: 'Фасовка', value: weight.value ? `${number(weight.value)} кг / упаковка` : 'Уточним для вашей партии', icon: 'mdi-package-variant-closed' },
    ...(country.value ? [{ label: 'Страна происхождения', value: country.value, icon: 'mdi-earth' }] : []),
    { label: 'Формат покупки', value: 'Разовая или регулярная поставка', icon: 'mdi-calendar-sync-outline' },
])
const faqs = computed(() => [
    { q: 'Как заказать этот товар?', a: 'Выберите количество упаковок и нажмите «Заказать». Укажите email и данные для доставки. Мы сохраним заказ и передадим его менеджеру: он подтвердит наличие, итоговую стоимость и условия. Регистрация не нужна.' },
    { q: 'Можно ли предложить свою цену?', a: `Да. Нажмите «Торг» и укажите цену за ${purchase.value.unit}, объём и удобный способ связи. Для большой партии или регулярных закупок можно обсудить индивидуальные условия. Менеджер рассмотрит предложение и ответит — отправка формы не означает автоматическое согласование скидки.` },
    { q: 'Как узнать стоимость и срок доставки?', a: 'Укажите город и адрес в заявке. Менеджер проверит возможность доставки, предложит доступные варианты и согласует стоимость до подтверждения заказа.' },
    { q: 'Нужно ли сразу оплачивать заказ?', a: 'Нет. На этом этапе вы отправляете заявку. Наличие, цену, документы и способ оплаты согласуем с вами отдельно.' },
])
function number(value) { return Number(value).toLocaleString('ru-RU', { maximumFractionDigits: 3 }) }
function money(value) { return `${Number(value).toLocaleString('ru-RU', { maximumFractionDigits: 2 })} ${purchase.value.currency === 'RUB' ? '₽' : purchase.value.currency}` }
function normalizeQuantity() { quantity.value = safeQuantity.value }
function openInquiry(kind) {
    normalizeQuantity()
    dialogKind.value = kind
    dialogOpen.value = true
    reachGoal(`good_${kind}_open`, { good_id: props.good.id })
}
function openMax() {
    reachGoal('good_max_click', { good_id: props.good.id })
    if (purchase.value.max_url) window.open(purchase.value.max_url, '_blank', 'noopener,noreferrer')
    else maxUnavailable.value = true
}
function imageFailed(url) { brokenImages.value = new Set([...brokenImages.value, url]) }
function relatedImage(item) { return item.ava_thumb || item.ava_image || item.published_media?.find(m => m.type === 'image')?.thumb_url || item.published_media?.find(m => m.type === 'image')?.url }
function trackView() {
    reachGoal('view_good', { good_id: props.good.id, good_name: props.good.name })
    ecommerceViewItem({ id: props.good.id, name: props.good.name, category: products.value[0]?.name || '', price: purchase.value.price || 0, currency: purchase.value.currency })
}
watch(() => props.good.id, () => { selectedMedia.value = 0; quantity.value = 1; dialogOpen.value = false; brokenImages.value = new Set(); trackView() })
onMounted(trackView)
</script>

<template>
    <Head>
        <title>{{ pageTitle }}</title>
        <meta head-key="description" name="description" :content="description">
        <meta head-key="robots" name="robots" :content="seo.robots || 'index,follow'">
        <link v-if="seo.canonical" head-key="canonical" rel="canonical" :href="seo.canonical">
        <meta head-key="og:title" property="og:title" :content="pageTitle">
        <meta head-key="og:description" property="og:description" :content="description">
        <meta head-key="og:type" property="og:type" content="product">
        <meta v-if="seo.canonical" head-key="og:url" property="og:url" :content="seo.canonical">
        <meta v-if="seo.image" head-key="og:image" property="og:image" :content="seo.image">
        <meta head-key="twitter:card" name="twitter:card" content="summary_large_image">
        <JsonLdHead v-if="seo.jsonLd" />
    </Head>

    <div class="good-landing">
        <nav class="breadcrumbs" aria-label="Хлебные крошки">
            <Link href="/">Главная</Link><span>/</span><Link href="/g">Каталог</Link>
            <template v-if="products[0]"><span>/</span><Link :href="`/p/${products[0].id}`">{{ products[0].name }}</Link></template>
            <span>/</span><span class="breadcrumbs__current">{{ good.name }}</span>
        </nav>

        <section class="product-hero" aria-labelledby="product-heading">
            <div class="product-intro">
                <div class="product-eyebrow"><span class="stock-badge" :class="{ 'stock-badge--available': status === 'in_stock' }"><span />{{ statusText }}</span><span>Артикул {{ good.id }}</span></div>
                <h1 id="product-heading">{{ pageH1 }}</h1>
                <p class="hero-description">{{ good.description || 'Подберите объём под вашу задачу. Обсудим цену, наличие и удобные условия поставки.' }}</p>
                <div class="product-quick-facts"><span v-if="weight"><v-icon icon="mdi-package-variant-closed" size="17" /> Упаковка {{ number(weight) }} кг</span><span v-if="country"><v-icon icon="mdi-earth" size="17" /> {{ country }}</span></div>

            </div>

            <div class="product-gallery">
                <div class="product-gallery__visuals" :class="{ 'product-gallery__visuals--with-reel': mainVideo }">
                    <GoodProductReel v-if="mainVideo" :key="`${good.id}-${mainVideo.id}`" :video="mainVideo" :name="good.name" class="product-gallery__reel" />
                <div class="product-gallery__stage">
                    <span class="gallery-label"><v-icon icon="mdi-image-outline" size="15" /> Товар в деталях</span>
                    <video v-if="activeMedia?.type === 'video'" :key="activeMedia.id" :src="activeMedia.video_mp4_url || activeMedia.url" :poster="activeMedia.poster_url || undefined" controls playsinline preload="metadata" :aria-label="`Видео: ${good.name}`" />
                    <button v-else-if="activeMedia?.url && !brokenImages.has(activeMedia.url)" class="product-gallery__image-button" type="button" aria-label="Увеличить фотографию товара" @click="zoomOpen = true">
                        <img :src="activeMedia.url" :alt="activeMedia.alt || good.name" fetchpriority="high" @error="imageFailed(activeMedia.url)">
                        <span class="gallery-zoom"><v-icon icon="mdi-arrow-expand" size="20" /></span>
                    </button>
                    <div v-else class="gallery-empty"><v-icon icon="mdi-package-variant" size="72" /><span>{{ good.name }}</span><small>Фотографии можно запросить у менеджера</small></div>
                    <span v-if="country" class="gallery-country"><v-icon icon="mdi-earth" size="15" /> {{ country }}</span>
                </div>
                </div>
                <div v-if="media.length > 1" class="gallery-thumbnails" aria-label="Фотографии и видео товара">
                    <button v-for="(item, index) in media" :key="item.id" type="button" :class="{ selected: selectedMedia === index }" :aria-label="item.type === 'video' ? 'Смотреть видео товара' : `Фотография ${index + 1}`" :aria-pressed="selectedMedia === index" @click="selectedMedia = index">
                        <img v-if="item.thumb_url || item.poster_url || item.type === 'image'" :src="item.thumb_url || item.poster_url || item.url" alt="" loading="lazy">
                        <v-icon v-if="item.type === 'video'" class="thumbnail-play" icon="mdi-play-circle" size="25" />
                    </button>
                </div>
                <div class="gallery-caption"><v-icon icon="mdi-magnify" size="17" /> Рассмотрите товар перед заказом <span v-if="mainVideo">· Видео товара в движении</span></div>
            </div>

            <div class="product-summary">
                <div class="purchase-panel">
                    <div class="price-row">
                        <div><span class="field-eyebrow">{{ purchase.price ? 'Цена товара' : 'Индивидуальные условия' }}</span><div class="product-price">{{ purchase.price ? money(purchase.price) : 'Договоримся о цене' }}<small v-if="purchase.price"> / {{ purchase.unit }}</small></div><span class="tax-note">{{ purchase.price ? (publicPurchase.includes_vat === false ? 'НДС уточняется при подтверждении' : 'С НДС') : 'Укажите объём — подготовим предложение' }}</span></div>
                        <button type="button" class="bargain-pill" @click="openInquiry('bargain')"><v-icon icon="mdi-handshake-outline" size="19" /> Торг <v-icon icon="mdi-arrow-top-right" size="16" /></button>
                    </div>
                    <div class="quantity-row">
                        <div><label for="product-quantity">Количество упаковок</label><span v-if="weight">{{ number(safeQuantity * weight) }} кг{{ purchase.package_price ? ` · ${money(purchase.package_price)} / уп.` : '' }}</span><span v-else>Фасовку подтвердит менеджер</span></div>
                        <div class="quantity-control"><button type="button" aria-label="Уменьшить количество" :disabled="safeQuantity <= 1" @click="quantity = safeQuantity - 1">−</button><input id="product-quantity" v-model="quantity" type="number" inputmode="numeric" min="1" max="9999" step="1" @change="normalizeQuantity"><button type="button" aria-label="Увеличить количество" :disabled="safeQuantity >= 9999" @click="quantity = safeQuantity + 1">+</button></div>
                    </div>
                    <div class="estimate"><span>Сумма товаров <small>без доставки</small></span><strong>{{ total ? money(total) : 'Рассчитаем для вас' }}</strong></div>
                    <button type="button" class="landing-button landing-button--primary order-button" @click="openInquiry('order')"><v-icon icon="mdi-basket-outline" size="21" /> Заказать <v-icon icon="mdi-arrow-right" size="21" /></button>
                    <div class="purchase-note"><v-icon icon="mdi-check-circle-outline" size="14" /> Без регистрации и предоплаты на сайте</div>
                </div>
                <div class="contact-actions"><button type="button" class="landing-button landing-button--outline" @click="openInquiry('email')"><v-icon icon="mdi-email-outline" size="19" /> Написать на email</button><button type="button" class="landing-button landing-button--max" @click="openMax"><span class="max-mark">M</span> Написать в MAX <v-icon icon="mdi-arrow-top-right" size="16" /></button></div>
                <p class="contact-note">Есть вопрос о товаре? Обсудим состав, документы и поставку.</p>
            </div>
            <MobileGoodPurchase v-model:quantity="quantity" :purchase="publicPurchase" class="mobile-product-purchase" @inquiry="openInquiry" @max="openMax" />
            <GoodStockAlertButton v-if="subscribeAvailable" :good="good" :availability="availability" label="Сообщить о поступлении в MAX" class="stock-subscription" />
        </section>

        <div class="service-strip">
            <div><v-icon icon="mdi-tune-variant" size="24" /><span><strong>Условия под ваш объём</strong><small>Разовая закупка или постоянные поставки</small></span></div>
            <div><v-icon icon="mdi-truck-outline" size="26" /><span><strong>Доставка по согласованию</strong><small>Рассчитаем маршрут, срок и стоимость</small></span></div>
            <div><v-icon icon="mdi-message-text-outline" size="24" /><span><strong>На связи с менеджером</strong><small>Вопросы и договорённости в одном диалоге</small></span></div>
        </div>

        <nav class="section-navigation" aria-label="Разделы страницы"><a href="#about-product">О товаре</a><a href="#your-price">Ваша цена</a><a href="#how-to-order">Как заказать</a><a href="#questions">Вопросы и ответы</a></nav>

        <section id="about-product" class="about-section landing-section">
            <div><span class="section-kicker">ЗНАКОМЬТЕСЬ БЛИЖЕ</span><h2>Всё начинается<br>с хорошего продукта.</h2><div class="product-story"><p v-for="(paragraph, index) in detailParagraphs" :key="index">{{ paragraph }}</p><p v-if="!detailParagraphs.length">{{ good.name }} — оставьте заявку, чтобы уточнить характеристики, фасовку и условия поставки. Менеджер поможет подобрать подходящий объём.</p></div><button type="button" class="text-action" @click="openInquiry('email')">Запросить подробности и документы <v-icon icon="mdi-arrow-right" size="18" /></button></div>
            <aside class="facts-panel"><span class="section-kicker">КАРТОЧКА ТОВАРА</span><h3>Детали для вашей закупки</h3><dl><div v-for="fact in facts" :key="fact.label"><dt><v-icon :icon="fact.icon" size="19" />{{ fact.label }}</dt><dd>{{ fact.value }}</dd></div><div><dt><v-icon icon="mdi-check-circle-outline" size="19" />Наличие</dt><dd>{{ statusText }}</dd></div></dl><p>Характеристики партии, документы и дату отгрузки подтвердим при согласовании заказа.</p></aside>
        </section>

        <section id="your-price" class="bargain-section">
            <div class="bargain-section__copy"><span class="section-kicker">ХОРОШАЯ СДЕЛКА НАЧИНАЕТСЯ С ДИАЛОГА</span><h2>Ваш объём.<br>Ваше предложение.</h2><p>Знаете, по какой цене готовы купить?<br>Предложите её нам. Попробуем договориться.</p><button type="button" class="landing-button landing-button--warm" @click="openInquiry('bargain')">Торг — предложить свою цену <v-icon icon="mdi-arrow-top-right" size="20" /></button><small>Каждое предложение рассматривает менеджер.</small></div>
            <div class="bargain-reasons"><div><span>01</span><div><h3>Беру объём</h3><p>Расскажите о размере партии — обсудим условия именно для неё.</p></div></div><div><span>02</span><div><h3>Планирую закупать регулярно</h3><p>Укажите периодичность. Давайте начнём долгосрочное сотрудничество.</p></div></div><div><span>03</span><div><h3>Есть другое предложение</h3><p>Назовите ориентир по цене и важные для вас условия.</p></div></div></div>
        </section>

        <section id="how-to-order" class="landing-section order-steps"><div class="section-heading"><div><span class="section-kicker">ОТ ВЫБОРА ДО ПОСТАВКИ</span><h2>Заказать проще,<br>чем кажется.</h2></div><p>Никаких сложных регистраций.<br>Начните с одной заявки.</p></div><div class="steps-grid"><article><span>01</span><h3>Выберите объём</h3><p>Укажите количество упаковок. Сумму товаров посчитаем сразу, если цена опубликована.</p></article><article><span>02</span><h3>Оставьте контакты</h3><p>Имя, email и город доставки помогут нам подготовить предметный ответ.</p></article><article><span>03</span><h3>Согласуйте детали</h3><p>Менеджер подтвердит наличие, цену, доставку и порядок оплаты.</p></article><article><span>04</span><h3>Получите товар</h3><p>После подтверждения заказа организуем отгрузку на согласованных условиях.</p></article></div></section>

        <section id="questions" class="landing-section faq-section"><div><span class="section-kicker">ДАВАЙТЕ РАЗБЕРЁМСЯ</span><h2>До заказа<br>остался вопрос?</h2><button type="button" class="text-action" @click="openInquiry('email')">Задать свой вопрос <v-icon icon="mdi-arrow-top-right" size="18" /></button></div><div class="faq-items"><details v-for="item in faqs" :key="item.q"><summary>{{ item.q }}<v-icon icon="mdi-plus" size="20" /></summary><p>{{ item.a }}</p></details></div></section>

        <section v-if="relatedGoods.length" class="landing-section related-section"><div class="section-heading"><div><span class="section-kicker">ДОПОЛНИТЕ ВАШ ЗАКАЗ</span><h2>Ещё в нашем каталоге</h2></div><Link href="/g" class="text-action">Все товары <v-icon icon="mdi-arrow-right" size="18" /></Link></div><div class="related-grid"><Link v-for="item in relatedGoods.slice(0, 4)" :key="item.id" :href="goodPublicUrl(item) || '/g'" class="related-product"><div class="related-product__image"><img v-if="relatedImage(item) && !brokenImages.has(relatedImage(item))" :src="relatedImage(item)" :alt="item.name" loading="lazy" @error="imageFailed(relatedImage(item))"><v-icon v-else icon="mdi-package-variant" size="44" /></div><span v-if="item.country?.name" class="related-product__country">{{ item.country.name }}</span><h3>{{ item.name }}</h3><span class="related-product__link">Подробнее <v-icon icon="mdi-arrow-top-right" size="18" /></span></Link></div></section>

        <div class="closing-cta"><div><h2>Обсудим вашу поставку?</h2><p>Вы уже выбрали товар. Поможем с остальным.</p></div><button type="button" class="landing-button landing-button--primary" @click="openInquiry('order')">Заказать <v-icon icon="mdi-arrow-right" size="20" /></button></div>
        <div class="mobile-purchase"><div><small>{{ total ? `${safeQuantity} уп.${weight ? ` · ${number(safeQuantity * weight)} кг` : ''}` : 'Под ваш объём' }}</small><strong>{{ total ? money(total) : 'Уточним цену' }}</strong></div><button type="button" class="landing-button landing-button--primary" @click="openInquiry('order')">Заказать <v-icon icon="mdi-arrow-right" size="18" /></button></div>
    </div>
    <GoodInquiryDialog :key="good.id" v-model="dialogOpen" :kind="dialogKind" :good="good" :quantity="safeQuantity" :purchase="purchase" @submitted="reachGoal('good_inquiry_submitted', { good_id: good.id, kind: dialogKind })" />
    <v-dialog v-model="zoomOpen" max-width="1100" aria-label="Фотография товара"><div class="zoom-view"><button type="button" class="zoom-close" aria-label="Закрыть фотографию" @click="zoomOpen = false"><v-icon icon="mdi-close" /></button><img v-if="activeMedia?.type === 'image'" :src="activeMedia.url" :alt="activeMedia.alt || good.name"></div></v-dialog>
    <v-dialog v-model="maxUnavailable" max-width="440"><v-card rounded="xl" class="pa-6"><v-card-title class="px-0">Связаться с менеджером</v-card-title><p class="my-4">Ссылка на MAX сейчас недоступна. Оставьте заявку с вашим контактом в MAX — менеджер свяжется с вами.</p><v-btn color="#800000" variant="flat" @click="maxUnavailable = false; openInquiry('email')">Оставить заявку</v-btn><v-btn class="mt-2" variant="text" @click="maxUnavailable = false">Закрыть</v-btn></v-card></v-dialog>
</template>

<style scoped>
.good-landing { --brand: #800000; --ink: #302323; --muted: #887573; --line: #e9ddda; max-width: 1320px; padding: 0 32px; margin: auto; color: var(--ink); }
.breadcrumbs { display: flex; align-items: center; gap: 10px; font-size: 11px; color: #a08c89; padding: 25px 0; white-space: nowrap; overflow: hidden; }
.breadcrumbs a { color: #87716e; text-decoration: none; }.breadcrumbs__current { overflow: hidden; text-overflow: ellipsis; }
.product-hero { display: grid; grid-template-columns: 1.05fr 1fr; grid-template-areas: "gallery intro" "gallery purchase" "gallery stock"; column-gap: 48px; row-gap: 0; align-items: start; grid-template-rows: auto auto 1fr; }
.product-intro { grid-area: intro; min-width: 0; }.product-summary { grid-area: purchase; min-width: 0; }.stock-subscription { grid-area: stock; }.product-gallery { grid-area: gallery; }.mobile-product-purchase { grid-area: mobile; }
.product-gallery__visuals { display: grid; grid-template-columns: 1fr; gap: 12px; align-items: stretch; }.product-gallery__visuals--with-reel { grid-template-columns: minmax(0, .7fr) minmax(0, 1fr); }.product-gallery__reel { width: 100%; min-width: 0; height: 530px; aspect-ratio: auto; }.product-gallery__visuals--with-reel .product-gallery__stage { height: 530px; }.product-gallery__visuals--with-reel .gallery-label { font-size: 10px; padding: 8px; left: 10px; top: 10px; }.product-gallery__visuals--with-reel .gallery-country { left: 10px; bottom: 10px; padding: 8px; font-size: 10px; }
.product-gallery { min-width: 0; }.product-gallery__stage { position: relative; height: 530px; border-radius: 12px; overflow: hidden; background: #f3e9e5; }
.product-gallery__image-button { width: 100%; height: 100%; display: block; cursor: zoom-in; }.product-gallery__stage img { width: 100%; height: 100%; object-fit: cover; }.product-gallery__stage video { width: 100%; height: 100%; object-fit: contain; background: #241c1b; }
.gallery-label, .gallery-country { position: absolute; z-index: 1; display: flex; gap: 7px; align-items: center; font-size: 11px; background: #fffc; backdrop-filter: blur(10px); padding: 9px 12px; border-radius: 5px; pointer-events: none; }.gallery-label { top: 18px; left: 18px; }.gallery-country { bottom: 18px; left: 18px; }.gallery-zoom { position: absolute; bottom: 16px; right: 16px; background: white; width: 36px; height: 36px; border-radius: 50%; display: grid; place-items: center; }
.gallery-empty { height: 100%; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 35px; text-align: center; gap: 18px; color: #846763; }.gallery-empty small { color: var(--muted); }
.gallery-thumbnails { display: flex; gap: 10px; overflow-x: auto; padding: 14px 2px 3px; }.gallery-thumbnails button { position: relative; width: 72px; height: 70px; flex-shrink: 0; border: 2px solid transparent; border-radius: 7px; overflow: hidden; background: #eee1dc; }.gallery-thumbnails button.selected { border-color: var(--brand); box-shadow: 0 0 0 2px #fffaf8 inset; }.gallery-thumbnails img { width: 100%; height: 100%; object-fit: cover; }.thumbnail-play { position: absolute; top: 23px; left: 23px; color: #fff; filter: drop-shadow(0 1px 4px #000); }.gallery-caption { display: flex; align-items: center; gap: 5px; color: var(--muted); font-size: 11px; margin-top: 15px; }
.product-eyebrow { display: flex; align-items: center; justify-content: space-between; color: #a08a86; font-size: 11px; margin: 3px 0 16px; }.stock-badge { display: flex; gap: 7px; align-items: center; color: #8b672f; font-size: 12px; }.stock-badge > span { width: 6px; height: 6px; background: currentColor; border-radius: 50%; }.stock-badge--available { color: #497345; }
h1 { font-size: clamp(29px, 3vw, 42px); line-height: 1.1; letter-spacing: -1.5px; font-weight: 600; max-width: 630px; } .hero-description { font-size: 13px; line-height: 1.7; color: #86716e; margin: 16px 0 17px; white-space: pre-line; display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden; }.product-quick-facts { display: flex; flex-wrap: wrap; gap: 18px; margin-bottom: 24px; font-size: 12px; color: #795b56; }.product-quick-facts > span { display: flex; gap: 6px; align-items: center; }
.purchase-panel { background: white; border: 1px solid var(--line); border-radius: 10px; padding: 23px; }.price-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; }.field-eyebrow { font-size: 11px; color: var(--muted); }.product-price { font-size: 33px; line-height: 1.4; font-weight: 600; letter-spacing: -1px; }.product-price small { font-weight: 400; font-size: 14px; color: var(--muted); letter-spacing: 0; }.tax-note { font-size: 11px; color: #9c8680; }.bargain-pill { display: flex; align-items: center; gap: 6px; background: #fff1ea; color: #800000; border: 1px solid #e4bdb2; padding: 10px 12px; font-size: 12px; font-weight: 600; border-radius: 6px; flex-shrink: 0; }
.quantity-row { display: flex; justify-content: space-between; align-items: center; gap: 14px; margin-top: 22px; padding: 18px 0; border-top: 1px solid #f3e7e2; border-bottom: 1px solid #f3e7e2; }.quantity-row label { display: block; font-size: 12px; font-weight: 600; }.quantity-row span { display: block; font-size: 11px; color: var(--muted); margin-top: 5px; }.quantity-control { display: flex; border: 1px solid #e3cfc8; border-radius: 6px; height: 40px; }.quantity-control button { width: 34px; font-size: 19px; }.quantity-control button:disabled { opacity: .25; }.quantity-control input { width: 48px; text-align: center; font-size: 13px; padding: 0; border: 0; background: transparent; appearance: textfield; -moz-appearance: textfield; }.quantity-control input::-webkit-inner-spin-button { -webkit-appearance: none; }
.estimate { display: flex; align-items: center; justify-content: space-between; margin: 16px 0 18px; gap: 12px; font-size: 12px; }.estimate small { color: var(--muted); display: block; font-size: 10px; margin-top: 2px; }.estimate strong { font-size: 20px; letter-spacing: -.5px; }.landing-button { display: inline-flex; align-items: center; justify-content: center; gap: 10px; min-height: 46px; border-radius: 6px; padding: 13px 20px; font-size: 13px; font-weight: 600; text-decoration: none; transition: background .15s, transform .15s; }.landing-button:hover { transform: translateY(-1px); }.landing-button--primary { background: var(--brand); color: #fff; }.landing-button--primary:hover { background: #a02020; }.order-button { width: 100%; font-size: 15px; }.order-button > :last-child { margin-left: auto; }.order-button > :first-child { margin-right: auto; }.purchase-note { display: flex; gap: 5px; justify-content: center; align-items: center; font-size: 10px; color: #99817b; margin-top: 10px; }
.contact-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 12px; }.landing-button--outline { border: 1px solid #e6d2cc; background: transparent; color: #800000; font-size: 12px; padding: 11px; }.landing-button--max { background: #eceef6; color: #4958a5; font-size: 12px; padding: 11px; }.max-mark { display: inline-flex; width: 19px; height: 19px; background: #6573cf; border-radius: 6px 6px 6px 2px; color: white; font-size: 11px; justify-content: center; align-items: center; }.contact-note { text-align: center; font-size: 10px; color: #9c8580; margin-top: 12px; }.stock-subscription { margin-top: 14px; }
.service-strip { display: grid; grid-template-columns: repeat(3,1fr); gap: 28px; padding: 28px 0; margin-top: 39px; border-bottom: 1px solid var(--line); border-top: 1px solid var(--line); }.service-strip > div { display: flex; align-items: center; gap: 14px; color: #a16b5e; }.service-strip strong { font-size: 12px; font-weight: 600; display: block; color: #60413a; }.service-strip small { font-size: 10px; color: var(--muted); margin-top: 6px; display: block; }
.section-navigation { display: flex; gap: 34px; margin-top: 18px; border-bottom: 1px solid var(--line); }.section-navigation a { color: #91726a; font-size: 12px; text-decoration: none; padding: 19px 0; white-space: nowrap; }.section-navigation a:first-child { color: var(--brand); border-bottom: 2px solid var(--brand); }.landing-section { padding: 64px 0; scroll-margin-top: 24px; }.section-kicker { display: block; color: #a1786d; font-size: 9px; letter-spacing: 1.6px; font-weight: 600; margin-bottom: 16px; }h2 { font-size: clamp(27px, 2.7vw, 36px); line-height: 1.18; letter-spacing: -1.15px; font-weight: 500; }h3 { font-weight: 600; }.about-section { display: grid; grid-template-columns: 1.35fr 1fr; gap: 90px; }.product-story { margin: 24px 0; color: #887069; font-size: 13px; line-height: 1.9; }.product-story p + p { margin-top: 14px; }.text-action { display: inline-flex; align-items: center; gap: 10px; font-size: 12px; font-weight: 600; color: var(--brand); text-decoration: none; }.facts-panel { background: #fff1eb; border: 1px solid #ead7cf; padding: 29px; border-radius: 10px; align-self: start; }.facts-panel h3 { font-size: 18px; margin-bottom: 24px; letter-spacing: -.5px; }.facts-panel dl > div { padding: 14px 0; border-bottom: 1px solid #ead6cc; }.facts-panel dt { color: #a18073; font-size: 11px; display: flex; gap: 7px; align-items: center; }.facts-panel dd { font-size: 12px; margin: 8px 0 0 26px; color: #684c40; }.facts-panel > p { font-size: 10px; color: #a18778; line-height: 1.7; margin-top: 20px; }
.bargain-section { display: grid; grid-template-columns: 1.1fr 1fr; gap: 70px; padding: 46px; border-radius: 12px; background: var(--brand); color: #fff6f1; scroll-margin-top: 24px; }.bargain-section .section-kicker { color: #e1b2a1; font-size: 8px; }.bargain-section h2 { font-size: 42px; }.bargain-section__copy > p { color: #e5c2b4; font-size: 13px; line-height: 1.8; margin: 19px 0 25px; }.landing-button--warm { background: #fff0e5; color: #800000; font-size: 12px; }.bargain-section__copy > small { display: block; font-size: 9px; color: #deb5a2; margin-top: 12px; }.bargain-reasons { align-self: center; }.bargain-reasons > div { display: flex; gap: 20px; padding: 22px 0; border-bottom: 1px solid #a34b40; }.bargain-reasons > div:first-child { padding-top: 0; }.bargain-reasons > div:last-child { border-bottom: 0; padding-bottom: 0; }.bargain-reasons > div > span { color: #f1ccab; font-size: 12px; padding-top: 2px; }.bargain-reasons h3 { font-size: 16px; font-weight: 500; }.bargain-reasons p { color: #e4bda9; font-size: 12px; line-height: 1.7; margin-top: 9px; max-width: 280px; }
.section-heading { display: flex; justify-content: space-between; align-items: end; gap: 25px; margin-bottom: 32px; }.section-heading > p { color: var(--muted); font-size: 12px; line-height: 1.8; }.steps-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 25px; margin-top: 36px; }.steps-grid article { border-top: 1px solid var(--line); padding-top: 20px; }.steps-grid article > span { display: grid; place-items: center; width: 33px; height: 33px; border-radius: 50%; background: #fbece4; color: #a46b50; font-size: 11px; margin-bottom: 20px; }.steps-grid h3 { font-size: 14px; margin-bottom: 10px; }.steps-grid p { font-size: 12px; line-height: 1.8; color: var(--muted); max-width: 235px; }
.faq-section { border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); display: grid; grid-template-columns: 1fr 1.5fr; gap: 70px; }.faq-section .text-action { margin-top: 24px; }.faq-items details { border-bottom: 1px solid var(--line); }.faq-items details:last-child { border-bottom: 0; }.faq-items summary { list-style: none; cursor: pointer; display: flex; gap: 20px; justify-content: space-between; padding: 20px 0; font-size: 13px; font-weight: 600; }.faq-items summary::-webkit-details-marker { display: none; }.faq-items p { color: var(--muted); font-size: 12px; line-height: 1.8; padding: 0 25px 22px 0; }.faq-items details[open] .v-icon { transform: rotate(45deg); }
.related-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 22px; }.related-product { display: flex; flex-direction: column; color: var(--ink); text-decoration: none; min-width: 0; }.related-product__image { aspect-ratio: 1.2; border-radius: 9px; overflow: hidden; background: #f5e9e1; display: grid; place-items: center; color: #c9aa99; }.related-product__image img { width: 100%; height: 100%; object-fit: cover; transition: transform .2s; }.related-product:hover img { transform: scale(1.035); }.related-product__country { color: var(--muted); font-size: 10px; margin-top: 17px; }.related-product h3 { font-size: 14px; margin: 10px 0 16px; line-height: 1.5; }.related-product__link { display: flex; justify-content: space-between; color: #9d5c42; margin-top: auto; font-size: 11px; }.closing-cta { border-top: 1px solid var(--line); padding: 36px 0 46px; display: flex; align-items: center; justify-content: space-between; gap: 22px; }.closing-cta h2 { font-size: 27px; }.closing-cta p { font-size: 12px; margin-top: 9px; color: var(--muted); }.closing-cta .landing-button { min-width: 190px; }
.mobile-purchase { display: none; }.zoom-view { position: relative; background: #fff8f4; border-radius: 10px; padding: 12px; }.zoom-view img { display: block; width: 100%; max-height: 85vh; object-fit: contain; }.zoom-close { position: absolute; top: 15px; right: 15px; border-radius: 50%; width: 38px; height: 38px; background: white; z-index: 1; }
button, a, summary, input { -webkit-tap-highlight-color: transparent; }button:focus-visible, a:focus-visible, summary:focus-visible { outline: 3px solid #b04d33; outline-offset: 4px; }button { cursor: pointer; }button:disabled { cursor: default; }
@media(min-width: 1500px) { .product-gallery__stage { height: 590px; } }
@media(max-width: 1100px) { .product-hero { gap: 30px; }.product-gallery__stage { height: 470px; }.about-section { gap: 45px; }.bargain-section { gap: 35px; padding: 35px; }.contact-actions { grid-template-columns: 1fr; gap: 8px; }.product-price { font-size: 29px; }.service-strip { gap: 15px; }.service-strip > div { gap: 8px; }.service-strip small { line-height: 1.5; } }
@media(max-width: 800px) { .good-landing { padding: 0 22px; }.product-hero { grid-template-columns: 1fr 1fr; gap: 22px; }.product-gallery__stage { height: 405px; }h1 { font-size: 30px; }.purchase-panel { padding: 16px; }.price-row { flex-wrap: wrap; }.product-price { font-size: 29px; }.quantity-row { flex-wrap: wrap; }.about-section { gap: 30px; }.bargain-section h2 { font-size: 34px; }.bargain-reasons h3 { font-size: 14px; }.facts-panel { padding: 22px; }.service-strip { grid-template-columns: 1fr; gap: 19px; padding: 24px 0; }.service-strip > div { gap: 14px; }.service-strip small { margin-top: 3px; }.faq-section { gap: 30px; }.steps-grid { gap: 15px; }.related-grid { grid-template-columns: repeat(2,1fr); gap: 26px 18px; } }
@media (min-width: 768px) and (max-width: 1100px) {
    .product-hero { column-gap: 26px; row-gap: 0; }
    .product-gallery__visuals--with-reel { grid-template-columns: 1fr; }
    .product-gallery__reel { height: 330px; aspect-ratio: auto; }
    .product-gallery__visuals--with-reel .product-gallery__stage { height: 225px; }
}
@media (max-width: 767px) {
    .good-landing { padding: 0 16px; }
    .breadcrumbs { padding: 14px 0; font-size: 10px; gap: 7px; }
    .breadcrumbs__current { display: none; }
    .breadcrumbs > span:last-of-type { display: none; }
    .breadcrumbs > span:nth-last-child(2) { display: none; }
    .product-hero { grid-template-columns: minmax(0, 1fr); grid-template-areas: "intro" "gallery" "mobile" "stock"; gap: 16px; grid-template-rows: auto; }
    .product-summary { display: none; }
    .product-intro h1 { font-size: clamp(25px, 6.4vw, 34px); letter-spacing: -.8px; line-height: 1.15; }
    .product-eyebrow { margin: 0 0 10px; font-size: 10px; }
    .stock-badge { font-size: 11px; }
    .hero-description { display: none; }
    .product-quick-facts { margin: 12px 0 0; font-size: 11px; gap: 14px; }
    .product-gallery__visuals { gap: 8px; }
    .product-gallery__visuals--with-reel { grid-template-columns: minmax(0, .78fr) minmax(0, 1fr); }
    .product-gallery__reel { height: clamp(246px, 66vw, 355px); aspect-ratio: auto; border-radius: 12px; }
    .product-gallery__reel :deep(.product-reel__caption) { display: none; }
    .product-gallery__reel :deep(.product-reel__badge) { left: 9px; top: 10px; padding: 7px; font-size: 10px; gap: 5px; }
    .product-gallery__stage, .product-gallery__visuals--with-reel .product-gallery__stage { height: clamp(246px, 66vw, 355px); aspect-ratio: auto; border-radius: 12px; }
    .gallery-label { top: 10px; left: 10px; font-size: 10px; padding: 8px; }
    .gallery-label .v-icon { display: none; }
    .gallery-country { bottom: 10px; left: 10px; font-size: 9px; max-width: calc(100% - 54px); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; padding: 7px; }
    .gallery-zoom { right: 8px; bottom: 8px; width: 34px; height: 34px; }
    .gallery-thumbnails { gap: 8px; padding: 10px 1px 3px; scroll-snap-type: x proximity; scrollbar-width: none; }
    .gallery-thumbnails::-webkit-scrollbar { display: none; }
    .gallery-thumbnails button { width: 52px; height: 50px; scroll-snap-align: start; }
    .thumbnail-play { top: 12px; left: 13px; }
    .gallery-caption { display: none; }
    .stock-subscription { margin-top: 0; }
    .stock-subscription :deep(.v-btn) { max-width: 100%; font-size: 11px; letter-spacing: 0; }
    .service-strip { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; padding: 22px 0; margin-top: 24px; }
    .service-strip > div { flex-direction: column; align-items: flex-start; gap: 8px; }
    .service-strip strong { font-size: 10px; line-height: 1.5; }
    .service-strip small { display: none; }
    .section-navigation { gap: 22px; overflow-x: auto; margin-top: 0; scrollbar-width: none; }
    .section-navigation a { font-size: 11px; padding: 16px 0; }
    .landing-section { padding: 32px 0; }
    .about-section, .faq-section { grid-template-columns: minmax(0, 1fr); gap: 24px; }
    .section-kicker { font-size: 8px; margin-bottom: 10px; line-height: 1.6; }
    h2 { font-size: 27px; }
    .product-story { font-size: 13px; margin: 18px 0; line-height: 1.8; }
    .facts-panel { padding: 22px; border-radius: 14px; }
    .bargain-section { grid-template-columns: minmax(0, 1fr); gap: 26px; padding: 27px 23px; border-radius: 16px; }
    .bargain-section h2 { font-size: 32px; }
    .bargain-section__copy > p { font-size: 13px; }
    .bargain-section__copy > .landing-button { width: 100%; font-size: 12px; min-height: 50px; padding-inline: 10px; }
    .bargain-reasons > div { padding: 16px 0; gap: 15px; }
    .bargain-reasons p { max-width: none; font-size: 12px; }
    .section-heading { flex-direction: column; align-items: flex-start; gap: 14px; margin-bottom: 23px; }
    .section-heading > p { font-size: 12px; }
    .steps-grid { grid-template-columns: minmax(0, 1fr); gap: 0; margin-top: 22px; }
    .steps-grid article { display: grid; grid-template-columns: 34px minmax(0, 1fr); column-gap: 15px; padding: 20px 0; }
    .steps-grid article > span { grid-row: span 2; margin: 0; }
    .steps-grid h3 { font-size: 14px; margin-bottom: 7px; }
    .steps-grid p { max-width: none; font-size: 12px; }
    .faq-items summary { font-size: 13px; padding: 19px 0; }
    .faq-items p { font-size: 13px; }
    .related-grid { display: flex; gap: 14px; overflow-x: auto; scroll-snap-type: x mandatory; padding-bottom: 10px; }
    .related-product { flex: 0 0 68%; scroll-snap-align: start; }
    .related-product h3 { font-size: 13px; }
    .related-product__country { margin-top: 12px; }
    .closing-cta { flex-direction: column; align-items: flex-start; padding: 28px 0 32px; }
    .closing-cta h2 { font-size: 25px; }
    .closing-cta .landing-button { width: 100%; min-height: 50px; }
    .mobile-purchase { display: flex; align-items: center; justify-content: space-between; position: fixed; bottom: 0; left: 0; width: 100%; z-index: 20; background: #fffdfcf5; backdrop-filter: blur(15px); border-top: 1px solid #ead6cc; padding: 10px 16px max(10px, env(safe-area-inset-bottom)); gap: 14px; box-shadow: 0 -4px 20px #54221908; }
    .mobile-purchase small { font-size: 10px; color: var(--muted); display: block; margin-bottom: 3px; }
    .mobile-purchase strong { font-size: 20px; }
    .mobile-purchase .landing-button { min-width: 146px; min-height: 48px; border-radius: 10px; }
    .gallery-empty { min-height: 0; padding: 15px; font-size: 11px; }
    .gallery-empty .v-icon { font-size: 42px !important; }
    .gallery-empty small { font-size: 10px; }
}
@media(prefers-reduced-motion: reduce) { *, *::before, *::after { transition: none !important; scroll-behavior: auto !important; } }
</style>
