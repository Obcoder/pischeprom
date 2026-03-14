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
    <v-container fluid class="pa-4">
        <v-row class="mb-4" align="center" justify="space-between">
            <v-col cols="12" md="8">
                <div class="d-flex align-center ga-3 flex-wrap">
                    <v-btn
                        variant="text"
                        prepend-icon="mdi-arrow-left"
                        @click="goBack"
                    >
                        Назад
                    </v-btn>

                    <div>
                        <h1 class="text-h4 font-weight-bold mb-1">
                            {{ productTitle }}
                        </h1>
                        <div class="text-medium-emphasis">
                            Карточка продукта
                        </div>
                    </div>
                </div>
            </v-col>

            <v-col cols="12" md="4" class="text-md-right">
                <v-chip
                    v-if="product?.id"
                    color="primary"
                    variant="outlined"
                    size="large"
                >
                    ID: {{ product.id }}
                </v-chip>
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

        <template v-else-if="product">
            <v-row>
                <v-col cols="12" lg="6">
                    <v-card class="mb-4" rounded="xl">
                        <v-card-title class="text-h6">
                            Основная информация
                        </v-card-title>

                        <v-divider />

                        <v-card-text>
                            <v-row>
                                <v-col cols="12" sm="6">
                                    <div class="text-caption text-medium-emphasis">ID</div>
                                    <div>{{ product.id || '—' }}</div>
                                </v-col>

                                <v-col cols="12" sm="6">
                                    <div class="text-caption text-medium-emphasis">Категория</div>
                                    <div>
                                        {{ product.category?.rus || product.category?.name || '—' }}
                                    </div>
                                </v-col>

                                <v-col cols="12" sm="6">
                                    <div class="text-caption text-medium-emphasis">Создан</div>
                                    <div>{{ formatDate(product.created_at) }}</div>
                                </v-col>

                                <v-col cols="12" sm="6">
                                    <div class="text-caption text-medium-emphasis">Обновлён</div>
                                    <div>{{ formatDate(product.updated_at) }}</div>
                                </v-col>
                            </v-row>
                        </v-card-text>
                    </v-card>

                    <v-card class="mb-4" rounded="xl">
                        <v-card-title class="text-h6">
                            Названия / переводы
                        </v-card-title>

                        <v-divider />

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

                    <v-card class="mb-4" rounded="xl">
                        <v-card-title class="text-h6">
                            Производители
                            <v-chip class="ml-2" size="small" variant="outlined">
                                {{ product.manufacturers?.length || 0 }}
                            </v-chip>
                        </v-card-title>

                        <v-divider />

                        <v-card-text>
                            <template v-if="product.manufacturers?.length">
                                <v-row>
                                    <v-col
                                        v-for="manufacturer in product.manufacturers"
                                        :key="manufacturer.id"
                                        cols="12"
                                        sm="6"
                                    >
                                        <v-card variant="tonal" rounded="lg">
                                            <v-card-text>
                                                <div class="font-weight-medium">
                                                    <Link
                                                        :href="route('web.unit.show', manufacturer.id)"
                                                        class="text-decoration-none"
                                                    >
                                                        {{ entityTitle(manufacturer) }}
                                                    </Link>
                                                </div>
                                                <div class="text-medium-emphasis text-body-2">
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
                        </v-card-text>
                    </v-card>
                </v-col>

                <v-col cols="12" lg="6">
                    <v-card class="mb-4" rounded="xl">
                        <v-card-title class="text-h6">
                            Компоненты
                            <v-chip class="ml-2" size="small" variant="outlined">
                                {{ product.components?.length || 0 }}
                            </v-chip>
                        </v-card-title>

                        <v-divider />

                        <v-card-text>
                            <template v-if="product.components?.length">
                                <v-list lines="two">
                                    <v-list-item
                                        v-for="component in product.components"
                                        :key="component.id"
                                    >
                                        <template #prepend>
                                            <v-avatar color="primary" variant="tonal">
                                                {{ component.id }}
                                            </v-avatar>
                                        </template>

                                        <v-list-item-title>
                                            {{ entityTitle(component) }}
                                        </v-list-item-title>

                                        <v-list-item-subtitle>
                                            ID: {{ component.id }}
                                        </v-list-item-subtitle>
                                    </v-list-item>
                                </v-list>
                            </template>

                            <v-alert v-else type="info" variant="tonal">
                                Компоненты отсутствуют
                            </v-alert>
                        </v-card-text>
                    </v-card>

                    <v-card class="mb-4" rounded="xl">
                        <v-card-title class="text-h6">
                            Units
                            <v-chip class="ml-2" size="small" variant="outlined">
                                {{ product.units?.length || 0 }}
                            </v-chip>
                        </v-card-title>

                        <v-divider />

                        <v-card-text>
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
                        </v-card-text>
                    </v-card>

                    <v-card class="mb-4" rounded="xl">
                        <v-card-title class="text-h6">
                            Consumers
                            <v-chip class="ml-2" size="small" variant="outlined">
                                {{ product.consumers?.length || 0 }}
                            </v-chip>
                        </v-card-title>

                        <v-divider />

                        <v-card-text>
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
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>

            <v-row>
                <v-col cols="12">
                    <v-card class="mb-4" rounded="xl">
                        <v-card-title class="text-h6">
                            Goods
                            <v-chip class="ml-2" size="small" variant="outlined">
                                {{ product.goods?.length || 0 }}
                            </v-chip>
                        </v-card-title>

                        <v-divider />

                        <v-card-text>
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
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>

            <v-row>
                <v-col cols="12">
                    <v-card rounded="xl">
                        <v-card-title class="text-h6">
                            Sales
                            <v-chip class="ml-2" size="small" variant="outlined">
                                {{ product.sales?.length || 0 }}
                            </v-chip>
                        </v-card-title>

                        <v-divider />

                        <v-card-text>
                            <template v-if="product.sales?.length">
                                <v-table density="comfortable">
                                    <thead>
                                    <tr>
                                        <th class="text-left">ID</th>
                                        <th class="text-left">Дата</th>
                                        <th class="text-left">Количество</th>
                                        <th class="text-left">Сумма</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr v-for="sale in product.sales" :key="sale.id">
                                        <td>{{ sale.id }}</td>
                                        <td>{{ formatDate(sale.created_at || sale.date) }}</td>
                                        <td>{{ sale.quantity ?? sale.amount ?? '—' }}</td>
                                        <td>{{ sale.total ?? sale.price ?? '—' }}</td>
                                    </tr>
                                    </tbody>
                                </v-table>
                            </template>

                            <v-alert v-else type="info" variant="tonal">
                                Продажи отсутствуют
                            </v-alert>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>
        </template>

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
.w-100 {
    width: 100%;
}
</style>
