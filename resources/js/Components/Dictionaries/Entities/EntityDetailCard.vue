<script setup>
import { computed } from 'vue'
import { usePhoneFormatter } from '@/Composables/entities/usePhoneFormatter'

const props = defineProps({
    entity: {
        type: Object,
        default: null,
    },
})

const { formatPhones } = usePhoneFormatter()

const phones = computed(() => props.entity?.telephones || [])
const cities = computed(() => props.entity?.cities || [])
const buildings = computed(() => props.entity?.buildings || [])
const units = computed(() => props.entity?.units || [])
const chats = computed(() => props.entity?.chats || [])

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
</script>

<template>
    <v-card min-height="520" class="entity-detail-card">
        <template v-if="entity">
            <div class="entity-detail-card__hero">
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
                            <strong>{{ formatPhones(phones) || '—' }}</strong>
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
                                <div class="entity-chip-list">
                                    <span v-for="unit in units" :key="unit.id" class="entity-chip">
                                        {{ unit.name }}
                                    </span>

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
    .entity-relation-columns {
        grid-template-columns: 1fr;
    }

    .entity-detail-card__hero {
        display: grid;
    }

    .entity-panel--wide {
        grid-column: auto;
    }
}
</style>
