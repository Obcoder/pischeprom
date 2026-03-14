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
    <v-container fluid class="pa-4 fill-height">
        <v-row>
            <v-col cols="12" lg="8">
                <div class="d-flex align-center ga-3 flex-wrap">
                    <v-btn
                        variant="text"
                        prepend-icon="mdi-arrow-left"
                        @click="goBack"
                    >
                        Назад
                    </v-btn>

                    <div>
                        <div class="text-h4 font-weight-bold">
                            {{ productTitle }}
                        </div>
                        <div class="text-medium-emphasis">
                            Карточка продукта
                        </div>
                    </div>
                </div>
            </v-col>
        </v-row>

        <v-row class="mb-4" align="center" justify="space-between">
            <v-col cols="12" lg="4" class="text-lg-right">
                <div class="d-flex justify-lg-end flex-wrap ga-2">
                    <v-chip
                        v-if="product?.id"
                        color="primary"
                        variant="outlined"
                    >
                        ID: {{ product.id }}
                    </v-chip>

                    <v-chip variant="outlined">
                        Категория:
                        {{ product?.category?.rus || product?.category?.name || '—' }}
                    </v-chip>

                    <v-chip variant="outlined">
                        Создан: {{ formatDate(product?.created_at) }}
                    </v-chip>
                </div>
            </v-col>
        </v-row>

        <v-skeleton-loader
            v-if="loading"
            type="article, article, table"
        />

        <v-alert
            v-else-if="error"
            type="error"
            variant="tonal"
            class="mb-4"
        >
            {{ error }}
        </v-alert>

        <v-card
            v-else-if="product"
            rounded="xl"
            class="d-flex flex-column product-card"
        >
            <v-tabs
                v-model="tab"
                color="primary"
                align-tabs="start"
                density="comfortable"
                class="px-2"
            >
                <v-tab value="main">Основное</v-tab>
                <v-tab value="translations">Переводы</v-tab>
                <v-tab value="manufacturers">Производители</v-tab>
                <v-tab value="components">Компоненты</v-tab>
                <v-tab value="goods">Goods</v-tab>
                <v-tab value="units">Units</v-tab>
                <v-tab value="consumers">Consumers</v-tab>
                <v-tab value="sales">Sales</v-tab>
            </v-tabs>

            <v-divider />

            <v-window v-model="tab" class="flex-grow-1">
                <!-- Основное -->
                <v-window-item value="main">
                    <div class="tab-scroll pa-4">
                        <v-row>
                            <v-col cols="12" md="6">
                                <v-card variant="tonal" rounded="lg">
                                    <v-card-title>Общая информация</v-card-title>
                                    <v-card-text>
                                        <v-table density="comfortable">
                                            <tbody>
                                            <tr>
                                                <td class="font-weight-medium">ID</td>
                                                <td>{{ product.id || '—' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="font-weight-medium">Русское название</td>
                                                <td>{{ product.rus || '—' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="font-weight-medium">English</td>
                                                <td>{{ product.eng || '—' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="font-weight-medium">Категория</td>
                                                <td>{{ product.category?.rus || product.category?.name || '—' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="font-weight-medium">Создан</td>
                                                <td>{{ formatDate(product.created_at) }}</td>
                                            </tr>
                                            <tr>
                                                <td class="font-weight-medium">Обновлён</td>
                                                <td>{{ formatDate(product.updated_at) }}</td>
                                            </tr>
                                            </tbody>
                                        </v-table>
                                    </v-card-text>
                                </v-card>
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-card variant="tonal" rounded="lg">
                                    <v-card-title>Сводка</v-card-title>
                                    <v-card-text>
                                        <div class="d-flex flex-wrap ga-2">
                                            <v-chip color="primary" variant="outlined">
                                                manufacturers: {{ product.manufacturers?.length || 0 }}
                                            </v-chip>
                                            <v-chip color="primary" variant="outlined">
                                                components: {{ product.components?.length || 0 }}
                                            </v-chip>
                                            <v-chip color="primary" variant="outlined">
                                                goods: {{ product.goods?.length || 0 }}
                                            </v-chip>
                                            <v-chip color="primary" variant="outlined">
                                                units: {{ product.units?.length || 0 }}
                                            </v-chip>
                                            <v-chip color="primary" variant="outlined">
                                                consumers: {{ product.consumers?.length || 0 }}
                                            </v-chip>
                                            <v-chip color="primary" variant="outlined">
                                                sales: {{ product.sales?.length || 0 }}
                                            </v-chip>
                                        </div>
                                    </v-card-text>
                                </v-card>
                            </v-col>
                        </v-row>
                    </div>
                </v-window-item>

                <!-- Переводы -->
                <v-window-item value="translations">
                    <div class="tab-scroll pa-4">
                        <v-card variant="tonal" rounded="lg">
                            <v-card-title>Названия и переводы</v-card-title>
                            <v-card-text>
                                <v-table density="comfortable">
                                    <thead>
                                    <tr>
                                        <th class="text-left">Поле</th>
                                        <th class="text-left">Значение</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr v-for="row in languageRows" :key="row.key">
                                        <td class="font-weight-medium">{{ row.label }}</td>
                                        <td>{{ row.value || '—' }}</td>
                                    </tr>
                                    </tbody>
                                </v-table>
                            </v-card-text>
                        </v-card>
                    </div>
                </v-window-item>

                <!-- Производители -->
                <v-window-item value="manufacturers">
                    <div class="tab-scroll pa-4">
                        <template v-if="product.manufacturers?.length">
                            <v-row>
                                <v-col
                                    v-for="manufacturer in product.manufacturers"
                                    :key="manufacturer.id"
                                    cols="12"
                                    sm="6"
                                    md="4"
                                    lg="3"
                                >
                                    <v-card rounded="lg" variant="tonal" height="100%">
                                        <v-card-text>
                                            <div class="text-subtitle-1 font-weight-medium mb-2">
                                                <Link
                                                    :href="route('web.unit.show', manufacturer.id)"
                                                    class="text-decoration-none"
                                                >
                                                    {{ entityTitle(manufacturer) }}
                                                </Link>
                                            </div>
                                            <div class="text-medium-emphasis">
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

                <!-- Компоненты -->
                <v-window-item value="components">
                    <div class="tab-scroll pa-4">
                        <template v-if="product.components?.length">
                            <v-table density="comfortable">
                                <thead>
                                <tr>
                                    <th class="text-left">ID</th>
                                    <th class="text-left">Название</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr v-for="component in product.components" :key="component.id">
                                    <td>{{ component.id }}</td>
                                    <td>{{ entityTitle(component) }}</td>
                                </tr>
                                </tbody>
                            </v-table>
                        </template>

                        <v-alert v-else type="info" variant="tonal">
                            Компоненты отсутствуют
                        </v-alert>
                    </div>
                </v-window-item>

                <!-- Goods -->
                <v-window-item value="goods">
                    <div class="tab-scroll pa-4">
                        <template v-if="product.goods?.length">
                            <v-expansion-panels variant="accordion">
                                <v-expansion-panel
                                    v-for="good in product.goods"
                                    :key="good.id"
                                >
                                    <v-expansion-panel-title>
                                        <div class="d-flex align-center justify-space-between w-100 pr-4">
                                            <div>
                                                <div class="font-weight-medium">
                                                    {{ entityTitle(good) }}
                                                </div>
                                                <div class="text-medium-emphasis text-body-2">
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
                                            <v-table density="compact">
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
                                                    <td>{{ quotation.id }}</td>
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

                <!-- Units -->
                <v-window-item value="units">
                    <div class="tab-scroll pa-4">
                        <template v-if="product.units?.length">
                            <v-table density="comfortable">
                                <thead>
                                <tr>
                                    <th class="text-left">ID</th>
                                    <th class="text-left">Название</th>
                                    <th class="text-left">action_id</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr v-for="unit in product.units" :key="unit.id">
                                    <td>{{ unit.id }}</td>
                                    <td>{{ entityTitle(unit) }}</td>
                                    <td>{{ unit.pivot?.action_id ?? '—' }}</td>
                                </tr>
                                </tbody>
                            </v-table>
                        </template>

                        <v-alert v-else type="info" variant="tonal">
                            Units не найдены
                        </v-alert>
                    </div>
                </v-window-item>

                <!-- Consumers -->
                <v-window-item value="consumers">
                    <div class="tab-scroll pa-4">
                        <template v-if="product.consumers?.length">
                            <v-table density="comfortable">
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
                                    <td>{{ consumer.id }}</td>
                                    <td>{{ entityTitle(consumer.product) }}</td>
                                    <td>{{ entityTitle(consumer.unit) }}</td>
                                    <td>{{ entityTitle(consumer.measure) }}</td>
                                    <td>{{ consumer.quantity ?? consumer.amount ?? '—' }}</td>
                                </tr>
                                </tbody>
                            </v-table>
                        </template>

                        <v-alert v-else type="info" variant="tonal">
                            Consumers не найдены
                        </v-alert>
                    </div>
                </v-window-item>

                <!-- Sales -->
                <v-window-item value="sales">
                    <div class="tab-scroll pa-4">
                        <template v-if="product.sales?.length">
                            <v-table density="comfortable">
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
                                    <td>{{ sale.id }}</td>
                                    <td>{{ formatDate(sale.date || sale.created_at) }}</td>
                                    <td>{{ entityTitle(sale.entity) }}</td>
                                    <td>
                                        <div class="d-flex flex-wrap ga-1">
                                            <v-chip
                                                v-for="good in sale.goods"
                                                :key="good.id"
                                                size="small"
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
                        </template>

                        <v-alert v-else type="info" variant="tonal">
                            Продажи отсутствуют
                        </v-alert>
                    </div>
                </v-window-item>
            </v-window>
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
.product-card {
    height: calc(100vh - 140px);
    min-height: 650px;
}

.tab-scroll {
    height: 100%;
    overflow-y: auto;
}

.w-100 {
    width: 100%;
}
</style>
