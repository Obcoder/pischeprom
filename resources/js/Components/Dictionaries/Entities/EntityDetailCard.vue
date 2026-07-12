<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import MaxContactButton from '@/Components/Max/MaxContactButton.vue'
import { usePhoneFormatter } from '@/Composables/entities/usePhoneFormatter'

const props = defineProps({
    entity: {
        type: Object,
        default: null,
    },
    showHero: {
        type: Boolean,
        default: true,
    },
    showChecks: {
        type: Boolean,
        default: false,
    },
    checks: {
        type: Array,
        default: () => [],
    },
    checksLoading: {
        type: Boolean,
        default: false,
    },
    checksError: {
        type: String,
        default: null,
    },
    checksMeta: {
        type: Object,
        default: () => ({
            total_amount: 0,
            items_count: 0,
        }),
    },
})

const { formatPhones } = usePhoneFormatter()

const phones = computed(() => props.entity?.telephones || [])
const cities = computed(() => props.entity?.cities || [])
const buildings = computed(() => props.entity?.buildings || [])
const units = computed(() => props.entity?.units || [])
const chats = computed(() => props.entity?.chats || [])
const checks = computed(() => props.checks || [])
const checksSummary = computed(() => {
    const localTotal = checks.value.reduce((sum, check) => sum + numeric(check?.amount), 0)
    const localItems = checks.value.reduce((sum, check) => sum + numeric(checkItemCount(check)), 0)

    return {
        total: numeric(props.checksMeta?.total_amount) || localTotal,
        items: numeric(props.checksMeta?.items_count) || localItems,
    }
})

const requisites = computed(() => [
    { label: 'INN', value: props.entity?.INN },
    { label: 'KPP', value: props.entity?.KPP },
    { label: 'OGRN', value: props.entity?.OGRN },
    { label: 'Страна', value: props.entity?.country?.name },
])

const lastPurchaseDate = computed(() => {
    if (!props.entity?.last_purchase_date) {
        return '—'
    }

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(props.entity.last_purchase_date))
})

function emptyText(items, text = '—') {
    return items.length ? null : text
}

function unitUrl(unit) {
    return unit?.id ? `/Ameise/unit/${unit.id}` : '/Ameise/units'
}

function unitInitial(unit) {
    return String(unit?.name || 'U').trim().slice(0, 1).toUpperCase()
}

function unitRoles(unit) {
    const roles = []

    if (unit?.is_customer) {
        roles.push('customer')
    }

    if (unit?.is_supplier) {
        roles.push('supplier')
    }

    return roles.length ? roles : ['unit']
}

function relationNames(items, key = 'name', limit = 2) {
    const values = (items || [])
        .map((item) => item?.[key])
        .filter(Boolean)

    if (!values.length) {
        return '—'
    }

    const visible = values.slice(0, limit).join(', ')
    const hidden = values.length - limit

    return hidden > 0 ? `${visible} +${hidden}` : visible
}

function unitPhones(unit) {
    return relationNames(unit?.telephones, 'number', 2)
}

function phoneNumber(telephone) {
    return telephone?.number ?? telephone?.telephone ?? telephone?.phone ?? ''
}

function unitEmails(unit) {
    return relationNames(unit?.emails, 'address', 2)
}

function unitLocation(unit) {
    const cityText = relationNames(unit?.cities, 'name', 2)

    if (cityText !== '—') {
        return cityText
    }

    return relationNames(unit?.buildings, 'address', 1)
}

function unitTags(unit) {
    return [
        ...(unit?.labels || []).map((label) => label?.name),
        ...(unit?.fields || []).map((field) => field?.name || field?.title),
        ...(unit?.industries || []).map((industry) => industry?.name || industry?.code),
    ].filter(Boolean).slice(0, 5)
}

function countText(value) {
    if (value === null || value === undefined || value === '') {
        return '—'
    }

    return Number.isFinite(Number(value)) ? Number(value) : '—'
}

function numeric(value) {
    const number = Number(value)

    return Number.isFinite(number) ? number : 0
}

function formatMoney(value) {
    return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(numeric(value))
}

function formatCheckDate(value) {
    if (!value) {
        return '—'
    }

    const date = new Date(`${value}T00:00:00`)

    if (Number.isNaN(date.getTime())) {
        return value
    }

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: '2-digit',
    }).format(date)
}

function checkUrl(check) {
    return check?.id ? `/Ameise/checks?check=${encodeURIComponent(check.id)}` : '/Ameise/checks'
}

function checkItemCount(check) {
    if (check?.items_count !== null && check?.items_count !== undefined) {
        return check.items_count
    }

    return numeric(check?.commodity_items_count) + numeric(check?.service_items_count)
}
</script>

<template>
    <v-card
        min-height="520"
        class="entity-detail-card"
        :class="{ 'entity-detail-card--without-hero': !showHero }"
    >
        <template v-if="entity">
            <div v-if="showHero" class="entity-detail-card__hero">
                <div>
                    <div class="entity-detail-card__eyebrow">
                        Entity #{{ entity.id }}
                    </div>

                    <h2>{{ entity.name }}</h2>

                    <p>{{ entity.full_name || 'Полное название не заполнено' }}</p>
                </div>

                <v-chip
                    color="#800000"
                    variant="flat"
                    size="small"
                    class="entity-detail-card__classification"
                >
                    {{ entity.classification?.name || 'Без классификации' }}
                </v-chip>
            </div>

            <v-card-text class="entity-detail-card__content">
                <div class="entity-detail-card__stats">
                    <div class="entity-stat">
                        <span>Продаж</span>
                        <strong>{{ entity.sales_count ?? 0 }}</strong>
                    </div>

                    <div class="entity-stat">
                        <span>Последний purchase</span>
                        <strong>{{ lastPurchaseDate }}</strong>
                    </div>
                </div>

                <section v-if="showChecks" class="entity-panel entity-panel--checks">
                    <div class="entity-checks-head">
                        <div class="entity-panel__title">
                            <v-icon icon="mdi-receipt-text-outline" size="18" />
                            Checks
                            <span class="entity-checks-count">{{ checks.length }}</span>
                        </div>

                        <div class="entity-checks-summary">
                            <div>
                                <span>Сумма</span>
                                <strong>{{ formatMoney(checksSummary.total) }}</strong>
                            </div>
                            <div>
                                <span>Строк</span>
                                <strong>{{ checksSummary.items }}</strong>
                            </div>
                        </div>
                    </div>

                    <div class="entity-checks-table-wrap">
                        <table class="entity-checks-table">
                            <colgroup>
                                <col class="entity-checks-col-id">
                                <col class="entity-checks-col-date">
                                <col class="entity-checks-col-money">
                                <col class="entity-checks-col-count">
                                <col class="entity-checks-col-count">
                                <col class="entity-checks-col-count">
                                <col class="entity-checks-col-action">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Дата</th>
                                    <th>Сумма</th>
                                    <th>Строк</th>
                                    <th>Товары</th>
                                    <th>Услуги</th>
                                    <th>Открыть</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="checksLoading">
                                    <td colspan="7" class="entity-checks-state">Загрузка checks...</td>
                                </tr>
                                <tr v-else-if="checksError">
                                    <td colspan="7" class="entity-checks-state entity-checks-state--error">
                                        {{ checksError }}
                                    </td>
                                </tr>
                                <tr v-else-if="!checks.length">
                                    <td colspan="7" class="entity-checks-state">Связанных checks пока нет.</td>
                                </tr>
                                <template v-else>
                                    <tr
                                        v-for="check in checks"
                                        :key="check.id"
                                        class="entity-checks-row"
                                    >
                                        <td class="cell-id">#{{ check.id }}</td>
                                        <td>{{ formatCheckDate(check.date) }}</td>
                                        <td class="cell-money">{{ formatMoney(check.amount) }}</td>
                                        <td>{{ countText(checkItemCount(check)) }}</td>
                                        <td>{{ countText(check.commodity_items_count) }}</td>
                                        <td>{{ countText(check.service_items_count) }}</td>
                                        <td>
                                            <Link :href="checkUrl(check)" class="entity-checks-link">
                                                Check
                                            </Link>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </section>

                <div class="entity-detail-grid">
                    <section class="entity-panel entity-panel--requisites">
                        <div class="entity-panel__title">
                            <v-icon icon="mdi-file-document-outline" size="18" />
                            Реквизиты
                        </div>

                        <dl class="entity-facts">
                            <template v-for="item in requisites" :key="item.label">
                                <dt>{{ item.label }}</dt>
                                <dd>{{ item.value || '—' }}</dd>
                            </template>
                        </dl>

                        <div class="entity-panel__note">
                            <span>Юридический адрес</span>
                            <strong>{{ entity.legal_address || '—' }}</strong>
                        </div>
                    </section>

                    <section class="entity-panel">
                        <div class="entity-panel__title">
                            <v-icon icon="mdi-card-account-phone-outline" size="18" />
                            Контакты
                        </div>

                        <div class="entity-contact-block">
                            <span>Телефоны</span>
                            <div
                                v-if="phones.length"
                                class="entity-contact-phones"
                            >
                                <div
                                    v-for="telephone in phones"
                                    :key="telephone.id || phoneNumber(telephone)"
                                >
                                    <strong>{{ phoneNumber(telephone) }}</strong>
                                    <MaxContactButton
                                        :phone="phoneNumber(telephone)"
                                        :entity-id="entity.id"
                                        :context-title="entity.name"
                                        size="x-small"
                                        variant="tonal"
                                    />
                                </div>
                            </div>
                            <strong v-else>{{ formatPhones(phones) || '—' }}</strong>
                        </div>
                    </section>

                    <section class="entity-panel">
                        <div class="entity-panel__title">
                            <v-icon icon="mdi-map-marker-radius-outline" size="18" />
                            География
                        </div>

                        <div class="entity-chip-list">
                            <span v-for="city in cities" :key="city.id" class="entity-chip">
                                {{ city.name }}
                            </span>

                            <span v-if="emptyText(cities)" class="entity-empty">Города не указаны</span>
                        </div>

                        <div class="entity-address-list">
                            <div v-for="building in buildings" :key="building.id">
                                {{ building.address }}
                            </div>

                            <span v-if="emptyText(buildings)" class="entity-empty">Здания не указаны</span>
                        </div>
                    </section>

                    <section class="entity-panel entity-panel--wide">
                        <div class="entity-panel__title">
                            <v-icon icon="mdi-graph-outline" size="18" />
                            Связи
                        </div>

                        <div class="entity-relation-columns">
                            <div>
                                <span>Units</span>
                                <div class="entity-unit-card-list">
                                    <article
                                        v-for="unit in units"
                                        :key="unit.id"
                                        class="entity-unit-card"
                                    >
                                        <div class="entity-unit-card__head">
                                            <div class="entity-unit-card__avatar">
                                                {{ unitInitial(unit) }}
                                            </div>

                                            <div class="entity-unit-card__title">
                                                <Link
                                                    :href="unitUrl(unit)"
                                                    class="entity-unit-card__name"
                                                >
                                                    {{ unit.name }}
                                                </Link>

                                                <div class="entity-unit-card__roles">
                                                    <span
                                                        v-for="role in unitRoles(unit)"
                                                        :key="role"
                                                        :class="`is-${role}`"
                                                    >
                                                        {{ role }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="entity-unit-card__facts">
                                            <div>
                                                <span>Тел.</span>
                                                <strong>{{ unitPhones(unit) }}</strong>
                                                <div
                                                    v-if="unit.telephones?.length"
                                                    class="entity-unit-card__phone-actions"
                                                >
                                                    <MaxContactButton
                                                        v-for="telephone in unit.telephones.slice(0, 2)"
                                                        :key="telephone.id || phoneNumber(telephone)"
                                                        :phone="phoneNumber(telephone)"
                                                        :unit-id="unit.id"
                                                        :context-title="unit.name"
                                                        size="x-small"
                                                        variant="tonal"
                                                    />
                                                </div>
                                            </div>

                                            <div>
                                                <span>Email</span>
                                                <strong>{{ unitEmails(unit) }}</strong>
                                            </div>

                                            <div>
                                                <span>Гео</span>
                                                <strong>{{ unitLocation(unit) }}</strong>
                                            </div>
                                        </div>

                                        <div class="entity-unit-card__meta">
                                            <span>E {{ countText(unit.entities_count) }}</span>
                                            <span>M {{ countText(unit.emails_count ?? unit.emails?.length) }}</span>
                                            <span>B {{ countText(unit.buildings_count ?? unit.buildings?.length) }}</span>
                                        </div>

                                        <div v-if="unitTags(unit).length" class="entity-unit-card__tags">
                                            <span
                                                v-for="(tag, index) in unitTags(unit)"
                                                :key="`${tag}-${index}`"
                                            >
                                                {{ tag }}
                                            </span>
                                        </div>
                                    </article>

                                    <span v-if="emptyText(units)" class="entity-empty">Нет Units</span>
                                </div>
                            </div>

                            <div>
                                <span>Chats</span>
                                <div class="entity-chip-list">
                                    <span v-for="chat in chats" :key="chat.id" class="entity-chip">
                                        {{ chat.numbers }}
                                    </span>

                                    <span v-if="emptyText(chats)" class="entity-empty">Нет Chats</span>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </v-card-text>
        </template>

        <v-card-text v-else class="entity-detail-card__empty">
            <v-icon icon="mdi-domain-plus" size="42" />
            <strong>Entity не выбрана</strong>
            <span>Создайте новую Entity или откройте существующую карточку.</span>
        </v-card-text>
    </v-card>
</template>

<style scoped>
.entity-detail-card {
    container-type: inline-size;
    overflow: hidden;
    border: 1px solid rgba(128, 0, 0, 0.08);
    border-radius: 24px;
    background: #fffdf8;
    box-shadow: 0 18px 44px rgba(48, 20, 10, 0.10);
}

.entity-detail-card--without-hero {
    min-height: 0 !important;
}

.entity-detail-card__hero {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18px;
    padding: 24px;
    background:
        radial-gradient(circle at 92% 0%, rgba(128, 0, 0, 0.20), transparent 30%),
        linear-gradient(135deg, #3f1d1d 0%, #7f1d1d 100%);
    color: #fff;
}

.entity-detail-card__eyebrow {
    margin-bottom: 8px;
    color: rgba(255, 255, 255, 0.68);
    font-size: 0.78rem;
    font-weight: 900;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.entity-detail-card__hero h2 {
    margin: 0;
    font-size: clamp(1.5rem, 2.6vw, 2.5rem);
    font-weight: 950;
    line-height: 1.05;
}

.entity-detail-card__hero p {
    max-width: 860px;
    margin: 10px 0 0;
    color: rgba(255, 255, 255, 0.78);
    line-height: 1.55;
}

.entity-detail-card__classification {
    flex: 0 0 auto;
    background: rgba(255, 255, 255, 0.16) !important;
    color: #fff !important;
}

.entity-detail-card__content {
    display: grid;
    gap: 14px;
    padding: 16px;
}

.entity-detail-card__stats {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.entity-stat {
    display: grid;
    gap: 4px;
    padding: 14px 16px;
    border: 1px solid rgba(128, 0, 0, 0.08);
    border-radius: 18px;
    background: #fff;
}

.entity-stat span,
.entity-relation-columns > div > span,
.entity-contact-block span,
.entity-panel__note span {
    color: #8b6b61;
    font-size: 0.76rem;
    font-weight: 900;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.entity-stat strong {
    color: #32140c;
    font-size: 1.35rem;
    font-weight: 950;
}

.entity-contact-phones {
    display: grid;
    gap: 6px;
}

.entity-contact-phones > div,
.entity-unit-card__phone-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px;
}

.entity-detail-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

.entity-panel {
    display: grid;
    align-content: start;
    gap: 12px;
    min-height: 210px;
    padding: 16px;
    border: 1px solid rgba(128, 0, 0, 0.08);
    border-radius: 20px;
    background: #fff;
}

.entity-panel--checks {
    min-height: 0;
    padding: 12px;
    gap: 10px;
}

.entity-panel--wide {
    grid-column: span 3;
}

.entity-panel__title {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #3f1d1d;
    font-size: 1rem;
    font-weight: 950;
}

.entity-checks-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.entity-checks-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 24px;
    height: 22px;
    padding: 0 7px;
    border-radius: 999px;
    background: rgba(128, 0, 0, 0.10);
    color: #800000;
    font-size: 0.72rem;
    font-weight: 950;
}

.entity-checks-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.entity-checks-summary div {
    display: grid;
    min-width: 92px;
    gap: 2px;
    padding: 6px 9px;
    border: 1px solid rgba(128, 0, 0, 0.08);
    border-radius: 8px;
    background: #fff7ed;
}

.entity-checks-summary span {
    color: #8b6b61;
    font-size: 0.62rem;
    font-weight: 900;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.entity-checks-summary strong {
    color: #32140c;
    font-size: 0.84rem;
    font-weight: 950;
    line-height: 1.15;
}

.entity-checks-table-wrap {
    width: 100%;
    overflow: auto;
    border: 1px solid #d8cfb8;
    background: #fff;
}

.entity-checks-table {
    width: 100%;
    min-width: 720px;
    border-collapse: collapse;
    table-layout: fixed;
    color: #24180f;
    font-size: 0.75rem;
}

.entity-checks-table th,
.entity-checks-table td {
    overflow: hidden;
    padding: 4px 6px;
    border: 1px solid #d8cfb8;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.entity-checks-table thead th {
    background: #d9d9d9;
    color: #1f1b16;
    font-weight: 950;
    text-align: left;
}

.entity-checks-table tbody tr:nth-child(odd) {
    background: #fff4cc;
}

.entity-checks-table tbody tr:nth-child(even) {
    background: #fff;
}

.entity-checks-row:hover {
    background: #e8f1df !important;
}

.entity-checks-col-id {
    width: 72px;
}

.entity-checks-col-date {
    width: 110px;
}

.entity-checks-col-money {
    width: 132px;
}

.entity-checks-col-count {
    width: 78px;
}

.entity-checks-col-action {
    width: 96px;
}

.cell-id {
    color: #6f4439;
    font-weight: 950;
}

.cell-money {
    color: #2d1a12;
    font-weight: 950;
    text-align: right;
}

.entity-checks-link {
    color: #800000;
    font-weight: 950;
    text-decoration: none;
}

.entity-checks-link:hover {
    text-decoration: underline;
    text-decoration-thickness: 2px;
    text-underline-offset: 2px;
}

.entity-checks-state {
    color: #8b6b61;
    font-weight: 800;
    text-align: center;
}

.entity-checks-state--error {
    color: #a20f0f;
}

.entity-facts {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 8px 14px;
    margin: 0;
}

.entity-facts dt {
    color: #8b6b61;
    font-size: 0.76rem;
    font-weight: 900;
}

.entity-facts dd {
    margin: 0;
    color: #2d1a12;
    font-weight: 800;
    text-align: right;
}

.entity-panel__note,
.entity-contact-block {
    display: grid;
    gap: 6px;
    padding: 12px;
    border-radius: 14px;
    background: #fff7ed;
}

.entity-panel__note strong,
.entity-contact-block strong {
    color: #2d1a12;
    line-height: 1.45;
}

.entity-chip-list {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
}

.entity-chip {
    display: inline-flex;
    align-items: center;
    min-height: 30px;
    padding: 6px 10px;
    border: 1px solid rgba(128, 0, 0, 0.10);
    border-radius: 999px;
    background: #fffaf4;
    color: #4b2418;
    font-size: 0.86rem;
    font-weight: 800;
    text-decoration: none;
}

.entity-chip--link:hover {
    border-color: rgba(128, 0, 0, 0.34);
    color: #800000;
}

.entity-unit-card-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(270px, 1fr));
    gap: 10px;
}

.entity-unit-card {
    position: relative;
    display: grid;
    gap: 10px;
    min-height: 164px;
    padding: 13px;
    overflow: hidden;
    border: 1px solid rgba(128, 0, 0, 0.14);
    border-radius: 18px;
    background:
        radial-gradient(circle at 100% 0%, rgba(128, 0, 0, 0.14), transparent 34%),
        linear-gradient(135deg, #fffdf8 0%, #fff7ed 100%);
    box-shadow: 0 10px 24px rgba(48, 20, 10, 0.08);
    transition: border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
}

.entity-unit-card:hover {
    border-color: rgba(128, 0, 0, 0.34);
    box-shadow: 0 16px 34px rgba(48, 20, 10, 0.13);
    transform: translateY(-1px);
}

.entity-unit-card__head {
    display: grid;
    grid-template-columns: 42px minmax(0, 1fr);
    align-items: center;
    gap: 10px;
}

.entity-unit-card__avatar {
    display: grid;
    width: 42px;
    height: 42px;
    border: 1px solid rgba(128, 0, 0, 0.24);
    border-radius: 14px;
    background:
        linear-gradient(135deg, rgba(128, 0, 0, 0.95), rgba(63, 29, 29, 0.95));
    color: #fff7ed;
    font-size: 1.05rem;
    font-weight: 950;
    place-items: center;
    text-transform: uppercase;
}

.entity-unit-card__title {
    display: grid;
    min-width: 0;
    gap: 5px;
}

.entity-unit-card__name {
    overflow: hidden;
    color: #3f1d1d;
    font-size: 0.98rem;
    font-weight: 950;
    line-height: 1.15;
    text-decoration: none;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.entity-unit-card__name:hover {
    color: #800000;
    text-decoration: underline;
    text-decoration-thickness: 2px;
    text-underline-offset: 3px;
}

.entity-unit-card__roles,
.entity-unit-card__meta,
.entity-unit-card__tags {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}

.entity-unit-card__roles span {
    padding: 2px 7px;
    border-radius: 999px;
    background: rgba(128, 0, 0, 0.09);
    color: #800000;
    font-size: 0.62rem;
    font-weight: 950;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.entity-unit-card__roles span.is-supplier {
    background: rgba(63, 29, 29, 0.11);
    color: #4b2418;
}

.entity-unit-card__roles span.is-unit {
    background: #efe6de;
    color: #7c5148;
}

.entity-unit-card__facts {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 6px;
}

.entity-unit-card__facts div {
    display: grid;
    gap: 3px;
    min-width: 0;
    padding: 7px 8px;
    border: 1px solid rgba(128, 0, 0, 0.08);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.72);
}

.entity-unit-card__facts span {
    color: #9b8379;
    font-size: 0.58rem;
    font-weight: 950;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.entity-unit-card__facts strong {
    overflow: hidden;
    color: #32140c;
    font-size: 0.75rem;
    font-weight: 900;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.entity-unit-card__meta span,
.entity-unit-card__tags span {
    border-radius: 7px;
    font-size: 0.66rem;
    font-weight: 900;
    line-height: 1;
}

.entity-unit-card__meta span {
    padding: 4px 6px;
    background: #f4e7dc;
    color: #6f4439;
}

.entity-unit-card__tags span {
    max-width: 160px;
    padding: 5px 7px;
    overflow: hidden;
    background: #fff;
    color: #7c5148;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.entity-address-list {
    display: grid;
    gap: 8px;
}

.entity-address-list div {
    padding: 9px 10px;
    border-radius: 12px;
    background: #f8f3ed;
    color: #3b2a22;
    line-height: 1.35;
}

.entity-relation-columns {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.entity-relation-columns > div {
    display: grid;
    align-content: start;
    gap: 8px;
}

.entity-empty {
    color: #9b8379;
    font-size: 0.9rem;
}

.entity-detail-card__empty {
    display: grid;
    min-height: 420px;
    place-items: center;
    align-content: center;
    gap: 8px;
    color: #8b6b61;
    text-align: center;
}

.entity-detail-card__empty strong {
    color: #3f1d1d;
    font-size: 1.1rem;
}

@container (max-width: 900px) {
    .entity-detail-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .entity-panel--wide {
        grid-column: span 2;
    }
}

@container (max-width: 620px) {
    .entity-detail-card__hero,
    .entity-detail-card__stats,
    .entity-detail-grid,
    .entity-checks-head,
    .entity-relation-columns {
        grid-template-columns: 1fr;
    }

    .entity-detail-card__hero {
        display: grid;
    }

    .entity-checks-head {
        display: grid;
    }

    .entity-panel--wide {
        grid-column: auto;
    }

    .entity-unit-card-list,
    .entity-unit-card__facts {
        grid-template-columns: 1fr;
    }
}
</style>
