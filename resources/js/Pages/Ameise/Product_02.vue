<script setup>
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
    id: {
        type: [Number, String],
        required: true,
    },
})

const loading = ref(false)
const error = ref('')
const product = ref(null)
const tab = ref('main')

const productTitle = computed(() => {
    const p = product.value
    if (!p) return 'Продукт'
    return p.rus || p.eng || `Продукт #${p.id}`
})

const languageRows = computed(() => {
    const p = product.value || {}

    return [
        { key: 'rus', label: 'Русский', value: p.rus },
        { key: 'eng', label: 'English', value: p.eng },
        { key: 'zh', label: '中文', value: p.zh },
        { key: 'es', label: 'Español', value: p.es },
        { key: 'ar', label: 'العربية', value: p.ar },
        { key: 'po', label: 'Português', value: p.po },
        { key: 'de', label: 'Deutsch', value: p.de },
        { key: 'fr', label: 'Français', value: p.fr },
        { key: 'hi', label: 'हिन्दी', value: p.hi },
        { key: 'tu', label: 'Türkçe', value: p.tu },
        { key: 'vi', label: 'Tiếng Việt', value: p.vi },
        { key: 'it', label: 'Italiano', value: p.it },
    ]
})

const summaryChips = computed(() => {
    const p = product.value || {}

    return [
        { label: 'Производители', value: p.manufacturers?.length || 0 },
        { label: 'Компоненты', value: p.components?.length || 0 },
        { label: 'Goods', value: p.goods?.length || 0 },
        { label: 'Units', value: p.units?.length || 0 },
        { label: 'Consumers', value: p.consumers?.length || 0 },
        { label: 'Sales', value: p.sales?.length || 0 },
    ]
})

function formatDate(value) {
    if (!value) return '—'

    const d = new Date(value)
    if (Number.isNaN(d.getTime())) return value

    return new Intl.DateTimeFormat('ru-RU', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(d)
}

function entityTitle(entity) {
    if (!entity) return '—'

    return (
        entity.rus ||
        entity.name ||
        entity.title ||
        entity.eng ||
        entity.full_name ||
        entity.short_name ||
        `#${entity.id ?? '—'}`
    )
}

function goBack() {
    if (window.history.length > 1) {
        window.history.back()
    } else {
        window.location.href = '/Ameise/products'
    }
}

async function loadProduct() {
    loading.value = true
    error.value = ''

    try {
        const { data } = await axios.get(`/api/products/${props.id}`)
        product.value = data
    } catch (e) {
        console.error(e)
        error.value = e?.response?.data?.message || 'Не удалось загрузить продукт'
    } finally {
        loading.value = false
    }
}

onMounted(loadProduct)
</script>

<template>
    <v-container fluid class="page-wrap pa-3 pa-md-4">
        <div class="page-header mb-3">
            <div class="d-flex align-center justify-space-between ga-3 flex-wrap">
                <div class="d-flex align-center ga-2 min-w-0">
                    <v-btn
                        variant="text"
                        density="comfortable"
                        prepend-icon="mdi-arrow-left"
                        @click="goBack"
                    >
                        Назад
                    </v-btn>

                    <div class="min-w-0">
                        <div class="text-h5 font-weight-bold text-truncate">
                            {{ productTitle }}
                        </div>
                        <div class="text-caption text-medium-emphasis">
                            Карточка продукта
                        </div>
                    </div>
                </div>

                <div v-if="product" class="d-flex align-center ga-2 flex-wrap">
                    <v-chip size="small" variant="outlined">ID: {{ product.id }}</v-chip>
                    <v-chip size="small" variant="outlined">
                        {{ product.category?.rus || product.category?.name || 'Без категории' }}
                    </v-chip>
                </div>
            </div>
        </div>

        <v-skeleton-loader
            v-if="loading"
            type="article, table"
        />

        <v-alert
            v-else-if="error"
            type="error"
            variant="tonal"
        >
            {{ error }}
        </v-alert>

        <v-card
            v-else-if="product"
            rounded="xl"
            class="product-card"
        >
            <div class="tabs-header">
                <v-tabs
                    v-model="tab"
                    color="primary"
                    align-tabs="start"
                    density="compact"
                    show-arrows
                    class="px-2"
                >
                    <v-tab value="main">
                        <v-icon start size="18">mdi-view-dashboard-outline</v-icon>
                        Основное
                    </v-tab>

                    <v-tab value="translations">
                        <v-icon start size="18">mdi-translate</v-icon>
                        Переводы
                    </v-tab>

                    <v-tab value="manufacturers">
                        <v-icon start size="18">mdi-factory</v-icon>
                        Производители
                    </v-tab>

                    <v-tab value="components">
                        <v-icon start size="18">mdi-puzzle-outline</v-icon>
                        Компоненты
                    </v-tab>

                    <v-tab value="goods">
                        <v-icon start size="18">mdi-package-variant-closed</v-icon>
                        Goods
                    </v-tab>

                    <v-tab value="units">
                        <v-icon start size="18">mdi-domain</v-icon>
                        Units
                    </v-tab>

                    <v-tab value="consumers">
                        <v-icon start size="18">mdi-account-group-outline</v-icon>
                        Consumers
                    </v-tab>

                    <v-tab value="sales">
                        <v-icon start size="18">mdi-cash-multiple</v-icon>
                        Sales
                    </v-tab>
                </v-tabs>
            </div>

            <v-divider />

            <div class="tabs-body">
                <v-window v-model="tab" class="window-fill" :touch="false">
                    <v-window-item value="main" class="window-pane">
                        <div class="tab-scroll pa-4">
                            <v-row class="mb-2" dense>
                                <v-col
                                    v-for="item in summaryChips"
                                    :key="item.label"
                                    cols="6"
                                    md="4"
                                    lg="2"
                                >
                                    <v-card rounded="lg" variant="tonal" class="stat-card">
                                        <v-card-text class="py-3">
                                            <div class="text-caption text-medium-emphasis mb-1">
                                                {{ item.label }}
                                            </div>
                                            <div class="text-h6 font-weight-bold">
                                                {{ item.value }}
                                            </div>
                                        </v-card-text>
                                    </v-card>
                                </v-col>
                            </v-row>

                            <v-row dense>
                                <v-col cols="12" lg="7">
                                    <v-card rounded="lg" variant="tonal" class="h-100">
                                        <v-card-title class="text-subtitle-1">
                                            Общая информация
                                        </v-card-title>

                                        <v-divider />

                                        <v-card-text class="pt-3">
                                            <div class="info-grid">
                                                <div class="info-row">
                                                    <div class="info-key">ID</div>
                                                    <div class="info-value">{{ product.id || '—' }}</div>
                                                </div>

                                                <div class="info-row">
                                                    <div class="info-key">Название</div>
                                                    <div class="info-value break-word">{{ product.rus || '—' }}</div>
                                                </div>

                                                <div class="info-row">
                                                    <div class="info-key">English</div>
                                                    <div class="info-value break-word">{{ product.eng || '—' }}</div>
                                                </div>

                                                <div class="info-row">
                                                    <div class="info-key">Категория</div>
                                                    <div class="info-value">
                                                        {{ product.category?.rus || product.category?.name || '—' }}
                                                    </div>
                                                </div>

                                                <div class="info-row">
                                                    <div class="info-key">Создан</div>
                                                    <div class="info-value">{{ formatDate(product.created_at) }}</div>
                                                </div>

                                                <div class="info-row">
                                                    <div class="info-key">Обновлён</div>
                                                    <div class="info-value">{{ formatDate(product.updated_at) }}</div>
                                                </div>
                                            </div>
                                        </v-card-text>
                                    </v-card>
                                </v-col>

                                <v-col cols="12" lg="5">
                                    <v-card rounded="lg" variant="tonal" class="mb-3">
                                        <v-card-title class="text-subtitle-1">
                                            Быстрые связи
                                        </v-card-title>

                                        <v-divider />

                                        <v-card-text class="pt-3">
                                            <div class="text-caption text-medium-emphasis mb-2">
                                                Производители
                                            </div>

                                            <div
                                                v-if="product.manufacturers?.length"
                                                class="d-flex flex-wrap ga-2"
                                            >
                                                <Link
                                                    v-for="manufacturer in product.manufacturers"
                                                    :key="manufacturer.id"
                                                    :href="route('web.unit.show', manufacturer.id)"
                                                    class="text-decoration-none"
                                                >
                                                    <v-chip size="small" variant="outlined">
                                                        {{ entityTitle(manufacturer) }}
                                                    </v-chip>
                                                </Link>
                                            </div>

                                            <div v-else class="text-medium-emphasis">
                                                Нет производителей
                                            </div>
                                        </v-card-text>
                                    </v-card>

                                    <v-card rounded="lg" variant="tonal">
                                        <v-card-title class="text-subtitle-1">
                                            Кратко
                                        </v-card-title>

                                        <v-divider />

                                        <v-card-text class="pt-3">
                                            <div class="d-flex flex-column ga-2">
                                                <div class="d-flex justify-space-between ga-4">
                                                    <span class="text-medium-emphasis">Goods</span>
                                                    <strong>{{ product.goods?.length || 0 }}</strong>
                                                </div>
                                                <div class="d-flex justify-space-between ga-4">
                                                    <span class="text-medium-emphasis">Components</span>
                                                    <strong>{{ product.components?.length || 0 }}</strong>
                                                </div>
                                                <div class="d-flex justify-space-between ga-4">
                                                    <span class="text-medium-emphasis">Consumers</span>
                                                    <strong>{{ product.consumers?.length || 0 }}</strong>
                                                </div>
                                                <div class="d-flex justify-space-between ga-4">
                                                    <span class="text-medium-emphasis">Sales</span>
                                                    <strong>{{ product.sales?.length || 0 }}</strong>
                                                </div>
                                            </div>
                                        </v-card-text>
                                    </v-card>
                                </v-col>
                            </v-row>
                        </div>
                    </v-window-item>

                    <v-window-item value="translations" class="window-pane">
                        <div class="tab-scroll pa-4">
                            <v-card rounded="lg" variant="tonal">
                                <v-card-title class="text-subtitle-1">
                                    Названия и переводы
                                </v-card-title>

                                <v-divider />

                                <v-card-text class="pt-3">
                                    <v-table density="compact" class="data-table-fixed">
                                        <thead>
                                        <tr>
                                            <th class="text-left">Поле</th>
                                            <th class="text-left">Значение</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <tr v-for="row in languageRows" :key="row.key">
                                            <td class="table-key">{{ row.label }}</td>
                                            <td class="break-word">{{ row.value || '—' }}</td>
                                        </tr>
                                        </tbody>
                                    </v-table>
                                </v-card-text>
                            </v-card>
                        </div>
                    </v-window-item>

                    <v-window-item value="manufacturers" class="window-pane">
                        <div class="tab-scroll pa-4">
                            <template v-if="product.manufacturers?.length">
                                <v-row dense>
                                    <v-col
                                        v-for="manufacturer in product.manufacturers"
                                        :key="manufacturer.id"
                                        cols="12"
                                        sm="6"
                                        md="4"
                                        xl="3"
                                    >
                                        <v-card rounded="lg" variant="tonal" class="h-100">
                                            <v-card-text>
                                                <div class="text-subtitle-2 font-weight-medium mb-2">
                                                    <Link
                                                        :href="route('web.unit.show', manufacturer.id)"
                                                        class="text-decoration-none"
                                                    >
                                                        {{ entityTitle(manufacturer) }}
                                                    </Link>
                                                </div>
                                                <div class="text-caption text-medium-emphasis">
                                                    ID: {{ manufacturer.id }}
                                                </div>
                                            </v-card-text>
                                        </v-card>
                                    </v-col>
                                </v-row>
                            </template>

                            <v-alert v-else type="info" variant="tonal">
                                Производители не найдены
                            </v-alert>
                        </div>
                    </v-window-item>

                    <v-window-item value="components" class="window-pane">
                        <div class="tab-scroll pa-4">
                            <template v-if="product.components?.length">
                                <v-card rounded="lg" variant="tonal">
                                    <v-card-text class="pt-3">
                                        <v-table density="compact" class="data-table-fixed">
                                            <thead>
                                            <tr>
                                                <th class="text-left">ID</th>
                                                <th class="text-left">Название</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <tr v-for="component in product.components" :key="component.id">
                                                <td class="table-id">{{ component.id }}</td>
                                                <td class="break-word">{{ entityTitle(component) }}</td>
                                            </tr>
                                            </tbody>
                                        </v-table>
                                    </v-card-text>
                                </v-card>
                            </template>

                            <v-alert v-else type="info" variant="tonal">
                                Компоненты отсутствуют
                            </v-alert>
                        </div>
                    </v-window-item>

                    <v-window-item value="goods" class="window-pane">
                        <div class="tab-scroll pa-4">
                            <template v-if="product.goods?.length">
                                <v-expansion-panels variant="accordion">
                                    <v-expansion-panel
                                        v-for="good in product.goods"
                                        :key="good.id"
                                    >
                                        <v-expansion-panel-title>
                                            <div class="d-flex align-center justify-space-between w-100 ga-3 pr-4">
                                                <div class="min-w-0">
                                                    <div class="font-weight-medium text-truncate">
                                                        {{ entityTitle(good) }}
                                                    </div>
                                                    <div class="text-caption text-medium-emphasis">
                                                        ID: {{ good.id }}
                                                    </div>
                                                </div>

                                                <v-chip size="small" variant="outlined">
                                                    quotations: {{ good.quotations?.length || 0 }}
                                                </v-chip>
                                            </div>
                                        </v-expansion-panel-title>

                                        <v-expansion-panel-text>
                                            <template v-if="good.quotations?.length">
                                                <v-table density="compact" class="data-table-fixed">
                                                    <thead>
                                                    <tr>
                                                        <th class="text-left">ID</th>
                                                        <th class="text-left">Цена</th>
                                                        <th class="text-left">Дата</th>
                                                    </tr>
                                                    </thead>
                                                    <tbody>
                                                    <tr
                                                        v-for="quotation in good.quotations"
                                                        :key="quotation.id"
                                                    >
                                                        <td class="table-id">{{ quotation.id }}</td>
                                                        <td>{{ quotation.price ?? quotation.value ?? '—' }}</td>
                                                        <td>{{ formatDate(quotation.created_at || quotation.date) }}</td>
                                                    </tr>
                                                    </tbody>
                                                </v-table>
                                            </template>

                                            <v-alert v-else type="info" variant="tonal">
                                                Quotations отсутствуют
                                            </v-alert>
                                        </v-expansion-panel-text>
                                    </v-expansion-panel>
                                </v-expansion-panels>
                            </template>

                            <v-alert v-else type="info" variant="tonal">
                                Goods не найдены
                            </v-alert>
                        </div>
                    </v-window-item>

                    <v-window-item value="units" class="window-pane">
                        <div class="tab-scroll pa-4">
                            <template v-if="product.units?.length">
                                <v-card rounded="lg" variant="tonal">
                                    <v-card-text class="pt-3">
                                        <v-table density="compact" class="data-table-fixed">
                                            <thead>
                                            <tr>
                                                <th class="text-left">ID</th>
                                                <th class="text-left">Название</th>
                                                <th class="text-left">action_id</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <tr v-for="unit in product.units" :key="unit.id">
                                                <td class="table-id">{{ unit.id }}</td>
                                                <td class="break-word">{{ entityTitle(unit) }}</td>
                                                <td>{{ unit.pivot?.action_id ?? '—' }}</td>
                                            </tr>
                                            </tbody>
                                        </v-table>
                                    </v-card-text>
                                </v-card>
                            </template>

                            <v-alert v-else type="info" variant="tonal">
                                Units не найдены
                            </v-alert>
                        </div>
                    </v-window-item>

                    <v-window-item value="consumers" class="window-pane">
                        <div class="tab-scroll pa-4">
                            <template v-if="product.consumers?.length">
                                <v-card rounded="lg" variant="tonal">
                                    <v-card-text class="pt-3">
                                        <v-table density="compact" class="data-table-fixed">
                                            <thead>
                                            <tr>
                                                <th class="text-left">ID</th>
                                                <th class="text-left">Продукт</th>
                                                <th class="text-left">Unit</th>
                                                <th class="text-left">Measure</th>
                                                <th class="text-left">Количество</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <tr v-for="consumer in product.consumers" :key="consumer.id">
                                                <td class="table-id">{{ consumer.id }}</td>
                                                <td class="break-word">{{ entityTitle(consumer.product) }}</td>
                                                <td class="break-word">{{ entityTitle(consumer.unit) }}</td>
                                                <td class="break-word">{{ entityTitle(consumer.measure) }}</td>
                                                <td>{{ consumer.quantity ?? consumer.amount ?? '—' }}</td>
                                            </tr>
                                            </tbody>
                                        </v-table>
                                    </v-card-text>
                                </v-card>
                            </template>

                            <v-alert v-else type="info" variant="tonal">
                                Consumers не найдены
                            </v-alert>
                        </div>
                    </v-window-item>

                    <v-window-item value="sales" class="window-pane">
                        <div class="tab-scroll pa-4">
                            <template v-if="product.sales?.length">
                                <v-card rounded="lg" variant="tonal">
                                    <v-card-text class="pt-3">
                                        <v-table density="compact" class="data-table-fixed">
                                            <thead>
                                            <tr>
                                                <th class="text-left">ID</th>
                                                <th class="text-left">Дата</th>
                                                <th class="text-left">Контрагент</th>
                                                <th class="text-left">Goods</th>
                                                <th class="text-left">Сумма</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <tr v-for="sale in product.sales" :key="sale.id">
                                                <td class="table-id">{{ sale.id }}</td>
                                                <td>{{ formatDate(sale.date || sale.created_at) }}</td>
                                                <td class="break-word">{{ entityTitle(sale.entity) }}</td>
                                                <td>
                                                    <div class="d-flex flex-wrap ga-1">
                                                        <v-chip
                                                            v-for="good in sale.goods"
                                                            :key="good.id"
                                                            size="x-small"
                                                            variant="outlined"
                                                        >
                                                            {{ entityTitle(good) }}
                                                        </v-chip>
                                                    </div>
                                                </td>
                                                <td>{{ sale.total ?? '—' }}</td>
                                            </tr>
                                            </tbody>
                                        </v-table>
                                    </v-card-text>
                                </v-card>
                            </template>

                            <v-alert v-else type="info" variant="tonal">
                                Продажи отсутствуют
                            </v-alert>
                        </div>
                    </v-window-item>
                </v-window>
            </div>
        </v-card>

        <v-alert
            v-else
            type="warning"
            variant="tonal"
        >
            Продукт не найден
        </v-alert>
    </v-container>
</template>

<style scoped>
.page-wrap {
    height: 100vh;
    overflow: hidden;
}

.page-header {
    flex: 0 0 auto;
}

.product-card {
    height: calc(100vh - 92px);
    min-height: 620px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.tabs-header {
    flex: 0 0 auto;
    overflow-x: auto;
    position: sticky;
    top: 0;
    z-index: 2;
    background: rgb(var(--v-theme-surface));
}

.tabs-body {
    flex: 1 1 auto;
    min-height: 0;
    overflow: hidden;
}

.window-fill {
    height: 100%;
}

.window-pane {
    height: 100%;
}

.tab-scroll {
    height: 100%;
    overflow-y: auto;
    overflow-x: hidden;
}

.info-grid {
    display: grid;
    grid-template-columns: minmax(140px, 180px) 1fr;
    row-gap: 10px;
    column-gap: 16px;
}

.info-row {
    display: contents;
}

.info-key {
    font-weight: 600;
    color: rgba(var(--v-theme-on-surface), 0.68);
    white-space: nowrap;
}

.info-value {
    min-width: 0;
}

.table-id {
    width: 72px;
    white-space: nowrap;
}

.table-key {
    width: 180px;
    white-space: nowrap;
    font-weight: 600;
}

.stat-card {
    min-height: 84px;
}

.break-word {
    word-break: break-word;
}

.min-w-0 {
    min-width: 0;
}

.h-100 {
    height: 100%;
}

.data-table-fixed {
    table-layout: fixed;
    width: 100%;
}
</style>
