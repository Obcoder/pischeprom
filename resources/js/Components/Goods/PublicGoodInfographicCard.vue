<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { logo } from '@/Pages/Helpers/consts.js'
import { usePublicGoodUrl } from '@/Composables/usePublicGoodUrl'

const props = defineProps({
    good: {
        type: Object,
        required: true,
    },
    isAuthenticated: {
        type: Boolean,
        default: false,
    },
})

const { goodPublicUrl } = usePublicGoodUrl()

const detailUrl = computed(() => goodPublicUrl(props.good))

const orderedImages = computed(() => {
    const media = props.good.published_media || props.good.publishedMedia || []
    const mediaImages = media
        .filter((item) => item?.type === 'image')
        .sort((a, b) => {
            if (Number(b.is_ava) !== Number(a.is_ava)) {
                return Number(b.is_ava) - Number(a.is_ava)
            }

            const orderA = Number(a.sort_order || 100)
            const orderB = Number(b.sort_order || 100)

            if (orderA !== orderB) {
                return orderA - orderB
            }

            return Number(a.id || 0) - Number(b.id || 0)
        })
        .map((item) => ({
            src: item.url || item.thumb_url,
            thumb: item.thumb_url || item.url,
            alt: item.alt || item.title || props.good.name,
        }))

    const fallbackImages = [
        props.good.ava_image,
        props.good.ava_thumb,
        logo,
    ].filter(Boolean).map((src) => ({
        src,
        thumb: src,
        alt: props.good.name,
    }))

    const seen = new Set()

    return [...mediaImages, ...fallbackImages].filter((item) => {
        if (!item.src || seen.has(item.src)) {
            return false
        }

        seen.add(item.src)

        return true
    })
})

const mainImage = computed(() => orderedImages.value[0] || {
    src: logo,
    thumb: logo,
    alt: props.good.name,
})

const thumbSlots = computed(() => {
    return [orderedImages.value[1] || null, orderedImages.value[2] || null]
})

const priceValues = computed(() => {
    const values = props.good.price_type_values || props.good.priceTypeValues || []

    return values
        .filter((item) => item?.is_published !== false)
        .sort((a, b) => {
            const orderA = Number(priceType(a)?.sort_order || 100)
            const orderB = Number(priceType(b)?.sort_order || 100)

            if (orderA !== orderB) {
                return orderA - orderB
            }

            return String(priceType(a)?.name || '').localeCompare(String(priceType(b)?.name || ''), 'ru')
        })
})

const retailPrice = computed(() => {
    return priceValues.value.find(isRetailPrice)
        || priceValues.value.find(isPublicPrice)
        || null
})

const partnerPrice = computed(() => {
    return priceValues.value.find(isPartnerPrice)
        || null
})

const packageText = computed(() => {
    const denominator = Number(props.good.denominator || 0)

    if (!Number.isFinite(denominator) || denominator <= 0) {
        return 'уточняется'
    }

    return `${denominator.toLocaleString('ru-RU', {
        maximumFractionDigits: 3,
    })} кг`
})

const countryName = computed(() => props.good.country?.name || 'страна уточняется')
const countryFlag = computed(() => props.good.country?.flag || '')

const orderHref = computed(() => {
    const subject = `Заказ товара: ${props.good.name}`
    const body = [
        `Здравствуйте, хочу заказать: ${props.good.name}`,
        `Ссылка: ${detailUrl.value}`,
    ].join('\n')

    return `mailto:office@180022.ru?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`
})

function priceType(price) {
    return price?.price_type || price?.priceType || null
}

function priceTypeText(price) {
    const type = priceType(price)

    return `${type?.code || ''} ${type?.name || ''}`.toLowerCase()
}

function isPublicPrice(price) {
    return Boolean(priceType(price)?.is_public)
}

function isRetailPrice(price) {
    const text = priceTypeText(price)

    return text.includes('retail')
        || text.includes('rozn')
        || text.includes('рознич')
        || text.includes('розница')
}

function isPartnerPrice(price) {
    const text = priceTypeText(price)

    return text.includes('partner')
        || text.includes('партн')
        || text.includes('дилер')
        || text.includes('dealer')
        || text.includes('diler')
}

function priceValue(price) {
    return price?.price_gross ?? price?.price_net ?? price?.price ?? null
}

function currencyText(price) {
    const currency = price?.currency?.code
        || priceType(price)?.currency?.code
        || 'RUB'

    return currency === 'RUB' ? '₽' : currency
}

function formatMoney(value) {
    const amount = Number(value)

    if (!Number.isFinite(amount) || amount <= 0) {
        return 'по запросу'
    }

    return amount.toLocaleString('ru-RU', {
        maximumFractionDigits: 2,
    })
}

function priceDisplay(price) {
    const value = priceValue(price)

    if (value === null || value === undefined || value === '') {
        return 'по запросу'
    }

    return `${formatMoney(value)} ${currencyText(price)}`
}
</script>

<template>
    <article class="good-info-card">
        <div class="good-info-card__media">
            <Link :href="detailUrl" class="good-info-card__main-image">
                <img
                    :src="mainImage.src"
                    :alt="mainImage.alt"
                    loading="lazy"
                >
            </Link>

            <div class="good-info-card__thumbs">
                <div
                    v-for="(thumb, index) in thumbSlots"
                    :key="thumb?.src || index"
                    class="good-info-card__thumb"
                    :class="{ 'good-info-card__thumb--empty': !thumb }"
                >
                    <img
                        v-if="thumb"
                        :src="thumb.thumb"
                        :alt="thumb.alt"
                        loading="lazy"
                    >
                    <span v-else>Фото</span>
                </div>
            </div>
        </div>

        <div class="good-info-card__body">
            <div class="good-info-card__topline">
                <span>Товар</span>
                <span v-if="good.products?.length">
                    {{ good.products.length }} связ.
                </span>
            </div>

            <Link :href="detailUrl" class="good-info-card__title">
                {{ good.name }}
            </Link>

            <p class="good-info-card__description">
                {{ good.description || 'Описание товара пока уточняется.' }}
            </p>

            <table class="good-info-card__prices">
                <tbody>
                    <tr>
                        <th>Розничная</th>
                        <td>{{ priceDisplay(retailPrice) }}</td>
                    </tr>
                    <tr>
                        <th>Партнёр</th>
                        <td v-if="isAuthenticated">
                            {{ priceDisplay(partnerPrice) }}
                        </td>
                        <td v-else class="good-info-card__login-cell">
                            <Link href="/login">Авторизуйтесь, чтобы увидеть Ваши цены</Link>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="good-info-card__footer">
                <div class="good-info-card__package">
                    <span>В упаковке</span>
                    <strong>{{ packageText }}</strong>
                </div>

                <div class="good-info-card__country">
                    <span v-if="countryFlag" class="good-info-card__flag">
                        <img :src="countryFlag" :alt="countryName">
                    </span>
                    <span>{{ countryName }}</span>
                </div>
            </div>

            <div class="good-info-card__actions">
                <a :href="orderHref" class="good-info-card__order">
                    Заказать
                </a>

                <Link :href="detailUrl" class="good-info-card__details">
                    Подробнее
                    <v-icon icon="mdi-arrow-right" size="15" />
                </Link>
            </div>
        </div>
    </article>
</template>

<style scoped>
.good-info-card {
    --card-ink: #2d201d;
    --card-muted: #756761;
    --card-red: #8a100c;
    --card-green: #47765a;
    display: grid;
    min-height: 260px;
    overflow: hidden;
    grid-template-columns: 132px minmax(0, 1fr);
    border: 1px solid rgba(128, 0, 0, 0.13);
    border-radius: 8px;
    background:
        linear-gradient(90deg, rgba(71, 118, 90, 0.07), transparent 42%),
        #fffdf9;
    box-shadow: 0 16px 34px rgba(74, 45, 33, 0.10);
    color: var(--card-ink);
}

.good-info-card__media {
    display: grid;
    align-content: start;
    gap: 8px;
    padding: 10px;
    border-right: 1px solid rgba(128, 0, 0, 0.08);
    background: #f7f1e8;
}

.good-info-card__main-image,
.good-info-card__thumb {
    overflow: hidden;
    border: 1px solid rgba(76, 57, 45, 0.12);
    border-radius: 6px;
    background: #fff;
}

.good-info-card__main-image {
    display: block;
    aspect-ratio: 1;
}

.good-info-card__main-image img,
.good-info-card__thumb img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.good-info-card__thumbs {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}

.good-info-card__thumb {
    display: grid;
    aspect-ratio: 1;
    place-items: center;
}

.good-info-card__thumb--empty {
    color: rgba(45, 32, 29, 0.36);
    font-size: 10px;
    font-weight: 900;
    text-transform: uppercase;
}

.good-info-card__body {
    display: flex;
    min-width: 0;
    flex-direction: column;
    padding: 12px 14px 10px;
}

.good-info-card__topline {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    color: rgba(138, 16, 12, 0.72);
    font-size: 10px;
    font-weight: 950;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.good-info-card__title {
    display: -webkit-box;
    overflow: hidden;
    margin-top: 6px;
    color: var(--card-ink);
    font-size: 17px;
    font-weight: 950;
    line-height: 1.18;
    text-decoration: none;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.good-info-card__description {
    display: -webkit-box;
    overflow: hidden;
    min-height: 35px;
    margin: 7px 0 10px;
    color: var(--card-muted);
    font-size: 12px;
    line-height: 1.45;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.good-info-card__prices {
    width: 100%;
    overflow: hidden;
    border-collapse: collapse;
    border: 1px solid rgba(128, 0, 0, 0.12);
    border-radius: 6px;
    font-size: 12px;
}

.good-info-card__prices th,
.good-info-card__prices td {
    padding: 8px 9px;
    border-bottom: 1px solid rgba(128, 0, 0, 0.08);
    text-align: left;
    vertical-align: middle;
}

.good-info-card__prices tr:last-child th,
.good-info-card__prices tr:last-child td {
    border-bottom: 0;
}

.good-info-card__prices th {
    width: 92px;
    background: rgba(71, 118, 90, 0.08);
    color: #425c49;
    font-size: 11px;
    font-weight: 950;
    text-transform: uppercase;
}

.good-info-card__prices td {
    color: var(--card-red);
    font-weight: 950;
}

.good-info-card__login-cell a {
    color: #70451c;
    font-size: 11px;
    font-weight: 850;
    line-height: 1.25;
    text-decoration: none;
}

.good-info-card__footer {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 10px;
    margin-top: auto;
    padding-top: 10px;
}

.good-info-card__package {
    display: grid;
    gap: 1px;
}

.good-info-card__package span {
    color: rgba(45, 32, 29, 0.54);
    font-size: 10px;
    font-weight: 900;
    text-transform: uppercase;
}

.good-info-card__package strong {
    color: #273c2e;
    font-size: 14px;
    font-weight: 950;
}

.good-info-card__country {
    display: inline-flex;
    max-width: 48%;
    align-items: center;
    justify-content: flex-end;
    gap: 5px;
    color: #30463a;
    font-size: 11px;
    font-weight: 900;
    text-align: right;
}

.good-info-card__flag {
    display: block;
    width: 18px;
    height: 18px;
    overflow: hidden;
    flex: 0 0 auto;
    border-radius: 50%;
    background: #fff;
}

.good-info-card__flag img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.good-info-card__actions {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 8px;
    margin-top: 10px;
}

.good-info-card__order,
.good-info-card__details {
    display: inline-flex;
    min-height: 34px;
    align-items: center;
    justify-content: center;
    gap: 5px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 950;
    text-decoration: none;
}

.good-info-card__order {
    background: #8a100c;
    color: #fffaf6;
}

.good-info-card__details {
    border: 1px solid rgba(128, 0, 0, 0.18);
    color: #5f0a08;
}

@media (max-width: 520px) {
    .good-info-card {
        grid-template-columns: 118px minmax(0, 1fr);
    }

    .good-info-card__body {
        padding: 10px;
    }

    .good-info-card__title {
        font-size: 15px;
    }

    .good-info-card__actions {
        grid-template-columns: 1fr;
    }
}
</style>
