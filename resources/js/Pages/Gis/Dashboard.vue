<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'

import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'

defineOptions({
    layout: VerwalterLayout,
})

const props = defineProps({
    overview: {
        type: Object,
        default: () => ({}),
    },
    providers: {
        type: Array,
        default: () => [],
    },
    settings: {
        type: Object,
        default: () => ({}),
    },
    links: {
        type: Object,
        default: () => ({}),
    },
})

const sourceLabels = {
    manual: 'Вручную',
    '2gis': '2ГИС',
    yandex: 'Яндекс',
    import: 'Импорт',
}

const coveragePercent = computed(() => {
    const total = Number(props.overview.total_entities || 0)
    const withLocation = Number(props.overview.with_location || 0)

    if (!total) {
        return 0
    }

    return Math.round((withLocation / total) * 100)
})

const stats = computed(() => [
    {
        label: 'Всего entities',
        value: props.overview.total_entities,
        icon: 'mdi-domain',
    },
    {
        label: 'С координатами',
        value: props.overview.with_location,
        icon: 'mdi-map-marker-check',
    },
    {
        label: 'Без координат',
        value: props.overview.without_location,
        icon: 'mdi-map-marker-question',
        tone: 'warning',
    },
    {
        label: 'Подтверждены',
        value: props.overview.confirmed_locations,
        icon: 'mdi-shield-check',
    },
    {
        label: 'Ждут проверки',
        value: props.overview.unconfirmed_locations,
        icon: 'mdi-shield-alert',
        tone: 'warning',
    },
    {
        label: 'Черновики маршрутов',
        value: props.overview.route_drafts,
        icon: 'mdi-routes',
    },
])

const sourceRows = computed(() => Object.entries(props.overview.source_counts || {}).map(([source, total]) => ({
    source,
    label: sourceLabels[source] || source || 'unknown',
    total,
})))

function number(value) {
    return Number(value || 0).toLocaleString('ru-RU')
}

function providerReady(provider) {
    return provider.api_key_configured
        && provider.geocode_url_configured
        && provider.map_script_url_configured
}

function providerStatusText(provider) {
    return providerReady(provider) ? 'Готов к работе' : 'Нужна настройка env'
}
</script>

<template>
    <Head title="GIS CRM" />

    <div class="gis-admin">
        <section class="gis-admin__hero">
            <div>
                <p class="gis-admin__eyebrow">Ameise GIS control</p>
                <h1>Карта CRM и координаты</h1>
                <span>
                    Управление GIS MVP: покрытие entities координатами, провайдеры карт, геокодирование и черновики маршрутов.
                </span>
            </div>

            <div class="gis-admin__actions">
                <Link :href="links.two_gis || '/Ameise/gis/2gis'" class="gis-admin-action gis-admin-action--primary">
                    <v-icon icon="mdi-map" size="18" />
                    2ГИС карта
                </Link>
                <Link :href="links.yandex || '/Ameise/gis/yandex'" class="gis-admin-action">
                    <v-icon icon="mdi-map-outline" size="18" />
                    Яндекс карта
                </Link>
                <Link :href="links.no_location || '/Ameise/gis/entities/no-location'" class="gis-admin-action">
                    <v-icon icon="mdi-map-marker-question-outline" size="18" />
                    Без координат
                </Link>
            </div>
        </section>

        <section class="gis-admin__coverage">
            <div>
                <strong>{{ coveragePercent }}%</strong>
                <span>entities имеют координаты</span>
            </div>
            <v-progress-linear
                :model-value="coveragePercent"
                color="#7f1d1d"
                bg-color="rgba(127, 29, 29, 0.12)"
                height="12"
                rounded
            />
        </section>

        <section class="gis-admin-stats">
            <article
                v-for="item in stats"
                :key="item.label"
                class="gis-admin-stat"
                :class="{ 'gis-admin-stat--warning': item.tone === 'warning' }"
            >
                <v-icon :icon="item.icon" size="24" />
                <div>
                    <span>{{ item.label }}</span>
                    <strong>{{ number(item.value) }}</strong>
                </div>
            </article>
        </section>

        <section class="gis-admin-grid">
            <article class="gis-admin-card gis-admin-card--providers">
                <div class="gis-admin-card__head">
                    <div>
                        <p>Providers</p>
                        <h2>2ГИС и Яндекс</h2>
                    </div>
                    <v-icon icon="mdi-api" size="28" />
                </div>

                <div class="gis-provider-list">
                    <div v-for="provider in providers" :key="provider.value" class="gis-provider">
                        <div>
                            <strong>{{ provider.label }}</strong>
                            <span>{{ providerStatusText(provider) }}</span>
                        </div>
                        <div class="gis-provider__checks">
                            <v-chip size="x-small" :color="provider.api_key_configured ? 'green' : 'red'" variant="tonal">
                                API key
                            </v-chip>
                            <v-chip size="x-small" :color="provider.geocode_url_configured ? 'green' : 'red'" variant="tonal">
                                geocode
                            </v-chip>
                            <v-chip size="x-small" :color="provider.map_script_url_configured ? 'green' : 'red'" variant="tonal">
                                map SDK
                            </v-chip>
                        </div>
                    </div>
                </div>
            </article>

            <article class="gis-admin-card">
                <div class="gis-admin-card__head">
                    <div>
                        <p>Runtime config</p>
                        <h2>Настройки сервиса</h2>
                    </div>
                    <v-icon icon="mdi-cog-outline" size="28" />
                </div>

                <dl class="gis-settings">
                    <dt>Провайдер по умолчанию</dt>
                    <dd>{{ settings.default_provider || '-' }}</dd>
                    <dt>Timeout запросов</dt>
                    <dd>{{ settings.request_timeout || 0 }} сек.</dd>
                    <dt>Центр карты</dt>
                    <dd>{{ settings.default_center?.lat }}, {{ settings.default_center?.lon }}</dd>
                    <dt>Zoom</dt>
                    <dd>{{ settings.default_zoom }}</dd>
                </dl>

                <p class="gis-admin-note">
                    API-ключи не редактируются из админки и не выводятся в интерфейс. Меняются через server env и ограничиваются по HTTP Referer в кабинетах провайдеров.
                </p>
            </article>

            <article class="gis-admin-card">
                <div class="gis-admin-card__head">
                    <div>
                        <p>Location sources</p>
                        <h2>Источники координат</h2>
                    </div>
                    <v-icon icon="mdi-source-branch" size="28" />
                </div>

                <div v-if="sourceRows.length" class="gis-source-list">
                    <div v-for="row in sourceRows" :key="row.source" class="gis-source-row">
                        <span>{{ row.label }}</span>
                        <strong>{{ number(row.total) }}</strong>
                    </div>
                </div>
                <div v-else class="gis-empty-state">
                    Координаты пока не сохранены.
                </div>
            </article>

            <article class="gis-admin-card gis-admin-card--workflow">
                <div class="gis-admin-card__head">
                    <div>
                        <p>MVP workflow</p>
                        <h2>Что делать дальше</h2>
                    </div>
                    <v-icon icon="mdi-clipboard-list-outline" size="28" />
                </div>

                <div class="gis-workflow">
                    <Link :href="links.no_location || '/Ameise/gis/entities/no-location'">
                        <span>1</span>
                        Геокодировать entities без координат
                    </Link>
                    <Link :href="links.two_gis || '/Ameise/gis/2gis'">
                        <span>2</span>
                        Проверить точки на 2ГИС
                    </Link>
                    <Link :href="links.yandex || '/Ameise/gis/yandex'">
                        <span>3</span>
                        Сравнить те же данные на Яндекс Картах
                    </Link>
                </div>
            </article>
        </section>
    </div>
</template>

<style scoped>
.gis-admin {
    min-height: 100vh;
    width: 100%;
    padding: 24px;
    background:
        radial-gradient(circle at 14% 8%, rgba(251, 146, 60, 0.22), transparent 28%),
        radial-gradient(circle at 86% 0%, rgba(127, 29, 29, 0.16), transparent 30%),
        linear-gradient(135deg, #fff7ed 0%, #fef2f2 45%, #f8fafc 100%);
    color: #3f1710;
}

.gis-admin__hero {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 24px;
    align-items: end;
    margin-bottom: 18px;
}

.gis-admin__eyebrow,
.gis-admin-card__head p {
    margin: 0 0 8px;
    color: #991b1b;
    font-family: "JetBrains Mono", "IBM Plex Mono", monospace;
    font-size: 11px;
    font-weight: 900;
    letter-spacing: 0.14em;
    text-transform: uppercase;
}

.gis-admin__hero h1 {
    max-width: 940px;
    margin: 0;
    color: #450a0a;
    font-size: clamp(38px, 6vw, 78px);
    font-weight: 950;
    letter-spacing: -0.06em;
    line-height: 0.92;
}

.gis-admin__hero span {
    display: block;
    max-width: 760px;
    margin-top: 14px;
    color: #7f1d1d;
    font-size: 16px;
}

.gis-admin__actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 10px;
}

.gis-admin-action {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 11px 14px;
    border: 1px solid rgba(127, 29, 29, 0.24);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.74);
    color: #7f1d1d;
    font-size: 13px;
    font-weight: 900;
    text-decoration: none;
    transition: transform 0.16s ease, box-shadow 0.16s ease, background 0.16s ease;
}

.gis-admin-action:hover {
    background: #ffffff;
    box-shadow: 0 16px 36px rgba(127, 29, 29, 0.14);
    transform: translateY(-1px);
}

.gis-admin-action--primary {
    background: #7f1d1d;
    color: #fff7ed;
}

.gis-admin__coverage {
    display: grid;
    grid-template-columns: auto minmax(220px, 1fr);
    gap: 16px;
    align-items: center;
    padding: 16px;
    margin-bottom: 16px;
    border: 1px solid rgba(127, 29, 29, 0.12);
    border-radius: 26px;
    background: rgba(255, 255, 255, 0.82);
    backdrop-filter: blur(12px);
}

.gis-admin__coverage div {
    display: grid;
    gap: 2px;
}

.gis-admin__coverage strong {
    color: #450a0a;
    font-size: 34px;
    font-weight: 950;
    line-height: 0.95;
}

.gis-admin__coverage span {
    color: #7f1d1d;
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
}

.gis-admin-stats {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 16px;
}

.gis-admin-stat,
.gis-admin-card {
    border: 1px solid rgba(127, 29, 29, 0.12);
    background: rgba(255, 255, 255, 0.88);
    box-shadow: 0 18px 44px rgba(69, 10, 10, 0.07);
}

.gis-admin-stat {
    display: flex;
    gap: 12px;
    align-items: center;
    padding: 14px;
    border-radius: 22px;
    color: #7f1d1d;
}

.gis-admin-stat--warning {
    background: rgba(255, 247, 237, 0.94);
}

.gis-admin-stat div {
    display: grid;
    gap: 4px;
}

.gis-admin-stat span {
    color: #8a4a3c;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
}

.gis-admin-stat strong {
    color: #450a0a;
    font-size: 24px;
    font-weight: 950;
}

.gis-admin-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.gis-admin-card {
    padding: 18px;
    border-radius: 28px;
}

.gis-admin-card__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 16px;
    color: #7f1d1d;
}

.gis-admin-card__head h2 {
    margin: 0;
    color: #450a0a;
    font-size: 25px;
    font-weight: 950;
    letter-spacing: -0.04em;
}

.gis-provider-list,
.gis-source-list,
.gis-workflow {
    display: grid;
    gap: 10px;
}

.gis-provider,
.gis-source-row,
.gis-workflow a {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px;
    border-radius: 18px;
    background: #fff7ed;
}

.gis-provider div:first-child {
    display: grid;
    gap: 4px;
}

.gis-provider strong,
.gis-source-row strong {
    color: #450a0a;
    font-weight: 950;
}

.gis-provider span,
.gis-source-row span {
    color: #7f1d1d;
    font-size: 13px;
}

.gis-provider__checks {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 6px;
}

.gis-settings {
    display: grid;
    grid-template-columns: minmax(160px, auto) 1fr;
    gap: 10px 16px;
    margin: 0;
}

.gis-settings dt {
    color: #8a4a3c;
    font-size: 12px;
    font-weight: 900;
    text-transform: uppercase;
}

.gis-settings dd {
    margin: 0;
    color: #450a0a;
    font-weight: 850;
}

.gis-admin-note {
    margin: 16px 0 0;
    padding: 12px;
    border-radius: 18px;
    background: rgba(127, 29, 29, 0.08);
    color: #7f1d1d;
    font-size: 13px;
    line-height: 1.45;
}

.gis-empty-state {
    padding: 16px;
    border: 1px dashed rgba(127, 29, 29, 0.22);
    border-radius: 18px;
    color: #7f1d1d;
    text-align: center;
}

.gis-workflow a {
    justify-content: flex-start;
    color: #450a0a;
    font-weight: 900;
    text-decoration: none;
}

.gis-workflow span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #7f1d1d;
    color: #fff7ed;
    font-family: "JetBrains Mono", "IBM Plex Mono", monospace;
}

@media (max-width: 1180px) {
    .gis-admin-stats {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 900px) {
    .gis-admin {
        padding: 16px;
    }

    .gis-admin__hero,
    .gis-admin__coverage,
    .gis-admin-grid {
        grid-template-columns: 1fr;
    }

    .gis-admin__actions {
        justify-content: flex-start;
    }

    .gis-admin-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 620px) {
    .gis-admin-stats {
        grid-template-columns: 1fr;
    }

    .gis-provider,
    .gis-source-row {
        align-items: flex-start;
        flex-direction: column;
    }

    .gis-provider__checks {
        justify-content: flex-start;
    }
}
</style>
