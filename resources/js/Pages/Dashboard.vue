<script setup>
import { Link } from '@inertiajs/vue3'
import { route } from "ziggy-js";
import LayoutDefault from '@/Layouts/LayoutDefault.vue'

defineOptions({
    layout: LayoutDefault,
})

const props = defineProps({
    profile: {
        type: Object,
        required: true,
    },
    orders: {
        type: Array,
        default: () => [],
    },
})

const accountTypeLabel = {
    individual: 'Физическое лицо',
    organization: 'Организация',
}

function formatMoney(value, currencyCode = 'RUB') {
    const amount = Number(value)

    if (!Number.isFinite(amount) || amount <= 0) {
        return 'по запросу'
    }

    const currency = currencyCode === 'RUB' ? '₽' : currencyCode

    return `${amount.toLocaleString('ru-RU', {
        maximumFractionDigits: 2,
    })} ${currency}`
}

function formatWeight(value) {
    const amount = Number(value)

    if (!Number.isFinite(amount) || amount <= 0) {
        return 'уточняется'
    }

    return `${amount.toLocaleString('ru-RU', {
        maximumFractionDigits: 3,
    })} кг`
}

function formatDate(value) {
    if (!value) {
        return ''
    }

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value))
}
</script>

<template>
    <div class="dashboard-page">
        <v-container>
            <div class="dashboard-hero">
                <div>
                    <div class="dashboard-eyebrow">
                        Личный кабинет
                    </div>

                    <h1 class="dashboard-title">
                        Добро пожаловать, {{ profile.name }}
                    </h1>

                    <p class="dashboard-text">
                        Здесь будет собираться информация по профилю, городу доставки,
                        заявкам, бонусам и специальным ценам.
                    </p>
                </div>

                <v-avatar size="76" class="dashboard-avatar">
                    <v-img
                        v-if="profile.profile_photo_url"
                        :src="profile.profile_photo_url"
                        alt="Аватар"
                    />
                    <span v-else>{{ profile.name?.[0] || 'П' }}</span>
                </v-avatar>
            </div>

            <v-row dense class="align-stretch">
                <v-col cols="12" md="6" lg="4">
                    <v-card rounded="xl" elevation="2" class="h-100 dashboard-card">
                        <v-card-title class="font-weight-bold">
                            Профиль
                        </v-card-title>

                        <v-card-text>
                            <div class="dashboard-field">
                                <span>Тип аккаунта</span>
                                <strong>{{ accountTypeLabel[profile.account_type] || 'Покупатель' }}</strong>
                            </div>

                            <div class="dashboard-field">
                                <span>Email</span>
                                <strong>{{ profile.email }}</strong>
                            </div>

                            <div class="dashboard-field">
                                <span>Телефон</span>
                                <strong>{{ profile.phone || 'Не указан' }}</strong>
                            </div>

                            <div class="dashboard-field">
                                <span>MAX</span>
                                <strong>{{ profile.max_chat_id ? 'Подключён' : 'Не указан' }}</strong>
                            </div>

                            <div class="dashboard-field">
                                <span>Адрес доставки</span>
                                <strong>{{ profile.delivery_address || 'Не указан' }}</strong>
                            </div>

                            <div class="dashboard-field">
                                <span>Email подтверждён</span>
                                <strong>{{ profile.email_verified_at ? 'Да' : 'Нет' }}</strong>
                            </div>
                        </v-card-text>

                        <v-card-actions class="px-4 pb-4">
                            <Link :href="route('customer.profile.edit')" class="w-100">
                                <v-btn block rounded="xl" color="#800000">
                                    Изменить профиль
                                </v-btn>
                            </Link>
                        </v-card-actions>
                    </v-card>
                </v-col>

                <v-col cols="12" md="6" lg="4">
                    <v-card rounded="xl" elevation="2" class="h-100 dashboard-card">
                        <v-card-title class="font-weight-bold">
                            Город / регион поставки
                        </v-card-title>

                        <v-card-text>
                            <div class="dashboard-location">
                                <div class="dashboard-location__icon">📍</div>
                                <div>
                                    <div class="dashboard-location__city">
                                        {{ profile.city?.name || 'Санкт-Петербург' }}
                                    </div>
                                    <div class="dashboard-location__region">
                                        {{ profile.city?.region || 'Город по умолчанию' }}
                                    </div>
                                </div>
                            </div>

                            <p class="dashboard-text mt-4 mb-0">
                                Город будет использоваться для расчёта доставки,
                                логистики и региональных условий.
                            </p>
                        </v-card-text>
                    </v-card>
                </v-col>

                <v-col cols="12" lg="4">
                    <v-card rounded="xl" elevation="2" class="h-100 dashboard-card dashboard-card--accent">
                        <v-card-title class="font-weight-bold">
                            Быстрые действия
                        </v-card-title>

                        <v-card-text>
                            <div class="dashboard-actions">
                                <Link :href="route('public.goods.index')">
                                    <v-btn block rounded="xl" color="#800000">
                                        Перейти в каталог
                                    </v-btn>
                                </Link>

                                <Link :href="route('home')">
                                    <v-btn block rounded="xl" variant="outlined" color="#800000">
                                        Поиск товара
                                    </v-btn>
                                </Link>

                                <a href="mailto:office@180022.ru" class="dashboard-link-button">
                                    Связаться с менеджером
                                </a>
                            </div>
                        </v-card-text>
                    </v-card>
                </v-col>

                <v-col cols="12" md="6">
                    <v-card rounded="xl" elevation="2" class="h-100 dashboard-card">
                        <v-card-title class="font-weight-bold">
                            Мои заказы
                        </v-card-title>

                        <v-card-text>
                            <div v-if="orders.length" class="dashboard-orders">
                                <article
                                    v-for="order in orders"
                                    :key="order.id"
                                    class="dashboard-order"
                                >
                                    <div class="dashboard-order__head">
                                        <div>
                                            <strong>{{ order.number }}</strong>
                                            <span>{{ formatDate(order.submitted_at) }}</span>
                                        </div>

                                        <span class="dashboard-order__status">
                                            {{ order.status_label }}
                                        </span>
                                    </div>

                                    <div class="dashboard-order__totals">
                                        <span>{{ formatMoney(order.total_amount, order.currency_code) }}</span>
                                        <span>{{ formatWeight(order.total_weight) }}</span>
                                    </div>

                                    <div class="dashboard-order__delivery">
                                        <span>{{ order.delivery_address || 'Адрес не указан' }}</span>
                                        <span>{{ order.preferred_delivery_time || 'Время не указано' }}</span>
                                    </div>

                                    <div class="dashboard-order__items">
                                        <div
                                            v-for="item in order.items"
                                            :key="item.id"
                                            class="dashboard-order-item"
                                        >
                                            <img
                                                v-if="item.image_url"
                                                :src="item.image_url"
                                                :alt="item.good_name"
                                                loading="lazy"
                                            >

                                            <div>
                                                <strong>{{ item.good_name }}</strong>
                                                <span>{{ item.quantity }} шт. · {{ formatMoney(item.line_total, item.currency_code) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            </div>

                            <p v-else class="dashboard-text mb-0">
                                Здесь будут появляться заказы, созданные из корзины.
                            </p>
                        </v-card-text>
                    </v-card>
                </v-col>

                <v-col cols="12" md="6">
                    <v-card rounded="xl" elevation="2" class="h-100 dashboard-card">
                        <v-card-title class="font-weight-bold">
                            Бонусы и специальные цены
                        </v-card-title>

                        <v-card-text>
                            <p class="dashboard-text mb-0">
                                Раздел скоро появится. Для организаций здесь можно будет
                                показывать индивидуальные условия, акции и специальные цены.
                            </p>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>
        </v-container>
    </div>
</template>

<style scoped>
.dashboard-page {
    padding: 32px 0 42px;
    background: linear-gradient(180deg, #fffaf8 0%, #f8fafc 100%);
}

.dashboard-hero {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 24px;
    padding: 28px;
    margin-bottom: 24px;
    border-radius: 28px;
    background:
        radial-gradient(circle at top right, rgba(128, 0, 0, 0.12), transparent 34%),
        linear-gradient(135deg, #fff 0%, #fff6f3 100%);
    border: 1px solid rgba(128, 0, 0, 0.08);
    box-shadow: 0 16px 35px rgba(63, 29, 29, 0.08);
}

.dashboard-eyebrow {
    display: inline-flex;
    padding: 7px 12px;
    border-radius: 999px;
    background: rgba(128, 0, 0, 0.08);
    color: #800000;
    font-weight: 800;
    font-size: 0.86rem;
}

.dashboard-title {
    margin: 12px 0 10px;
    color: #3f1d1d;
    font-size: 2rem;
    line-height: 1.1;
    font-weight: 900;
}

.dashboard-text {
    color: #655c57;
    line-height: 1.65;
}

.dashboard-avatar {
    background: #800000;
    color: white;
    font-size: 1.6rem;
    font-weight: 900;
    flex-shrink: 0;
}

.dashboard-card {
    background: #fff;
}

.dashboard-card--accent {
    background: linear-gradient(180deg, #fff 0%, #fff6f3 100%);
}

.dashboard-field {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid rgba(128, 0, 0, 0.08);
}

.dashboard-field span {
    color: #7c6f6a;
}

.dashboard-field strong {
    color: #3f1d1d;
    text-align: right;
}

.dashboard-location {
    display: flex;
    align-items: center;
    gap: 14px;
}

.dashboard-location__icon {
    width: 48px;
    height: 48px;
    display: grid;
    place-items: center;
    border-radius: 16px;
    background: rgba(128, 0, 0, 0.08);
    font-size: 1.3rem;
}

.dashboard-location__city {
    color: #3f1d1d;
    font-size: 1.3rem;
    font-weight: 900;
}

.dashboard-location__region {
    color: #7c6f6a;
}

.dashboard-actions {
    display: grid;
    gap: 12px;
}

.dashboard-orders {
    display: grid;
    gap: 12px;
}

.dashboard-order {
    display: grid;
    gap: 10px;
    padding: 12px;
    border: 1px solid rgba(128, 0, 0, 0.10);
    border-radius: 8px;
    background: #fffdf9;
}

.dashboard-order__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.dashboard-order__head div {
    display: grid;
    gap: 2px;
}

.dashboard-order__head strong {
    color: #3f1d1d;
    font-weight: 950;
}

.dashboard-order__head span {
    color: #756c67;
    font-size: 0.82rem;
}

.dashboard-order__status {
    display: inline-flex;
    padding: 5px 8px;
    border-radius: 6px;
    background: rgba(71, 118, 90, 0.10);
    color: #30463a !important;
    font-size: 0.76rem !important;
    font-weight: 900;
    white-space: nowrap;
}

.dashboard-order__totals {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.dashboard-order__totals span {
    padding: 5px 8px;
    border-radius: 6px;
    background: rgba(128, 0, 0, 0.07);
    color: #6b1b18;
    font-size: 0.82rem;
    font-weight: 900;
}

.dashboard-order__delivery {
    display: grid;
    gap: 4px;
    padding: 9px 10px;
    border: 1px solid rgba(71, 118, 90, 0.12);
    border-radius: 8px;
    background: rgba(71, 118, 90, 0.06);
}

.dashboard-order__delivery span {
    color: #30463a;
    font-size: 0.82rem;
    font-weight: 850;
    line-height: 1.35;
}

.dashboard-order__items {
    display: grid;
    gap: 7px;
}

.dashboard-order-item {
    display: grid;
    grid-template-columns: 38px minmax(0, 1fr);
    align-items: center;
    gap: 8px;
}

.dashboard-order-item img {
    width: 38px;
    height: 38px;
    border-radius: 6px;
    object-fit: cover;
    background: #f7f1e8;
}

.dashboard-order-item div {
    display: grid;
    min-width: 0;
    gap: 2px;
}

.dashboard-order-item strong {
    overflow: hidden;
    color: #3f1d1d;
    font-size: 0.86rem;
    line-height: 1.2;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.dashboard-order-item span {
    color: #756c67;
    font-size: 0.78rem;
}

.dashboard-link-button {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 40px;
    border-radius: 999px;
    text-decoration: none;
    color: #800000;
    border: 1px solid rgba(128, 0, 0, 0.35);
    font-weight: 700;
}

@media (max-width: 600px) {
    .dashboard-hero {
        align-items: flex-start;
        flex-direction: column;
        padding: 22px;
    }

    .dashboard-title {
        font-size: 1.55rem;
    }
}
</style>
