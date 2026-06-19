<script setup>
import { computed, reactive, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import axios from 'axios'
import BaseSectionCard from '@/Components/Unit/BaseSectionCard.vue'

const props = defineProps({
    unit: {
        type: Object,
        required: true,
    },
    dict: {
        type: Object,
        default: () => ({}),
    },
})

const emit = defineEmits(['refresh'])

const showAttachForm = ref(false)
const localSearch = ref('')
const saving = ref(false)
const deletingProductId = ref(null)
const errors = ref({})

const form = reactive({
    product_id: null,
})

const manufactures = computed(() => {
    const unique = new Map()

    for (const product of props.unit?.manufactures || []) {
        if (product?.id && !unique.has(product.id)) {
            unique.set(product.id, product)
        }
    }

    return [...unique.values()].sort((a, b) => productTitle(a).localeCompare(productTitle(b)))
})

const existingProductIds = computed(() => new Set(manufactures.value.map((product) => product.id)))

const availableProducts = computed(() => {
    return (props.dict?.products || [])
        .filter((product) => product?.id && !existingProductIds.value.has(product.id))
        .map((product) => ({
            ...product,
            searchTitle: [
                product.rus,
                product.eng,
                product.category?.name,
                `#${product.id}`,
            ].filter(Boolean).join(' - '),
        }))
})

const filteredManufactures = computed(() => {
    const search = localSearch.value.trim().toLowerCase()

    if (!search) {
        return manufactures.value
    }

    return manufactures.value.filter((product) => [
        product.rus,
        product.eng,
        product.category?.name,
        String(product.id),
    ].filter(Boolean).some((value) => String(value).toLowerCase().includes(search)))
})

const categoryStats = computed(() => {
    const stats = new Map()

    for (const product of manufactures.value) {
        const name = product.category?.name || 'Без категории'
        stats.set(name, (stats.get(name) || 0) + 1)
    }

    return [...stats.entries()]
        .map(([name, count]) => ({ name, count }))
        .sort((a, b) => b.count - a.count || a.name.localeCompare(b.name))
})

function productTitle(product) {
    return product?.rus || product?.name || product?.eng || `Product #${product?.id ?? '-'}`
}

function productSubtitle(product) {
    return [
        product?.eng,
        product?.category?.name,
    ].filter(Boolean).join(' / ')
}

function productHref(product) {
    if (!product?.id) {
        return '#'
    }

    try {
        return route('product.show', product.id)
    } catch (error) {
        return `/Ameise/product/${product.id}`
    }
}

async function attachManufacture() {
    if (!form.product_id || saving.value) {
        return
    }

    saving.value = true
    errors.value = {}

    try {
        await axios.post(route('api.units.manufactures.attach', { unit: props.unit.id }), {
            product_id: form.product_id,
        })

        form.product_id = null
        showAttachForm.value = false
        emit('refresh')
    } catch (error) {
        errors.value = error.response?.data?.errors || {}
        console.error('Ошибка добавления manufacture:', error)
    } finally {
        saving.value = false
    }
}

async function detachManufacture(product) {
    if (!product?.id || deletingProductId.value) {
        return
    }

    if (!window.confirm(`Убрать "${productTitle(product)}" из производимых продуктов?`)) {
        return
    }

    deletingProductId.value = product.id

    try {
        await axios.delete(route('api.units.manufactures.detach', {
            unit: props.unit.id,
            product: product.id,
        }))

        emit('refresh')
    } catch (error) {
        console.error('Ошибка удаления manufacture:', error)
    } finally {
        deletingProductId.value = null
    }
}
</script>

<template>
    <BaseSectionCard
        title="Manufactures"
        icon="mdi-factory"
        header-color="teal"
        body-class="unit-manufactures"
    >
        <template #actions>
            <div class="unit-manufactures__header-actions">
                <span class="unit-manufactures__counter">
                    {{ manufactures.length }} products
                </span>

                <button
                    type="button"
                    class="unit-manufactures__add"
                    @click="showAttachForm = !showAttachForm"
                >
                    <v-icon icon="mdi-plus" size="15" />
                    <span>Add</span>
                </button>
            </div>
        </template>

        <div class="unit-manufactures">
            <div class="unit-manufactures__hero">
                <div>
                    <p class="unit-manufactures__eyebrow">Производственная матрица Unit</p>
                    <h3>{{ unit.name }} производит</h3>
                    <p>
                        Здесь собраны products, которые привязаны к unit как производимые позиции.
                        Блок синхронизируется с карточками Product и списками производителей.
                    </p>
                </div>

                <div class="unit-manufactures__metrics">
                    <span>
                        <strong>{{ manufactures.length }}</strong>
                        total
                    </span>
                    <span>
                        <strong>{{ categoryStats.length }}</strong>
                        categories
                    </span>
                    <span>
                        <strong>{{ availableProducts.length }}</strong>
                        available
                    </span>
                </div>
            </div>

            <v-expand-transition>
                <div v-if="showAttachForm" class="unit-manufactures__form">
                    <v-autocomplete
                        v-model="form.product_id"
                        :items="availableProducts"
                        item-title="searchTitle"
                        item-value="id"
                        label="Добавить product"
                        variant="outlined"
                        density="comfortable"
                        clearable
                        hide-details="auto"
                        prepend-inner-icon="mdi-magnify"
                        :error-messages="errors.product_id || []"
                        no-data-text="Нет доступных products для добавления"
                    >
                        <template #item="{ props: itemProps, item }">
                            <v-list-item v-bind="itemProps">
                                <template #title>
                                    <span class="unit-manufactures__select-title">
                                        {{ productTitle(item.raw) }}
                                    </span>
                                </template>

                                <template #subtitle>
                                    <span>{{ productSubtitle(item.raw) || `Product #${item.raw.id}` }}</span>
                                </template>
                            </v-list-item>
                        </template>
                    </v-autocomplete>

                    <v-btn
                        color="#006b63"
                        :disabled="!form.product_id"
                        :loading="saving"
                        @click="attachManufacture"
                    >
                        Attach
                    </v-btn>
                </div>
            </v-expand-transition>

            <div class="unit-manufactures__tools">
                <v-text-field
                    v-model="localSearch"
                    variant="outlined"
                    density="compact"
                    hide-details
                    clearable
                    prepend-inner-icon="mdi-magnify"
                    placeholder="Фильтр по названию, категории или ID"
                />

                <div v-if="categoryStats.length" class="unit-manufactures__chips">
                    <button
                        v-for="category in categoryStats.slice(0, 6)"
                        :key="category.name"
                        type="button"
                        @click="localSearch = category.name === 'Без категории' ? '' : category.name"
                    >
                        {{ category.name }}
                        <strong>{{ category.count }}</strong>
                    </button>
                </div>
            </div>

            <div v-if="filteredManufactures.length" class="unit-manufactures__grid">
                <article
                    v-for="product in filteredManufactures"
                    :key="product.id"
                    class="unit-manufactures__item"
                >
                    <div class="unit-manufactures__item-main">
                        <span class="unit-manufactures__badge">
                            #{{ product.id }}
                        </span>

                        <Link :href="productHref(product)" class="unit-manufactures__title">
                            {{ productTitle(product) }}
                        </Link>

                        <p v-if="productSubtitle(product)">
                            {{ productSubtitle(product) }}
                        </p>
                        <p v-else>
                            Категория не указана
                        </p>
                    </div>

                    <div class="unit-manufactures__item-actions">
                        <Link :href="productHref(product)">
                            Open
                        </Link>

                        <button
                            type="button"
                            :disabled="deletingProductId === product.id"
                            @click="detachManufacture(product)"
                        >
                            Remove
                        </button>
                    </div>
                </article>
            </div>

            <div v-else class="unit-manufactures__empty">
                <v-icon icon="mdi-factory-off" size="34" />
                <strong>{{ localSearch ? 'По фильтру ничего не найдено' : 'Производимые products ещё не добавлены' }}</strong>
                <span>
                    {{ localSearch ? 'Очистите фильтр или добавьте новый product.' : 'Нажмите Add и выберите product из справочника.' }}
                </span>
            </div>
        </div>
    </BaseSectionCard>
</template>

<style scoped>
.unit-manufactures,
.unit-manufactures__header-actions {
    --manufactures-green: #006b63;
    --manufactures-ink: #143c38;
    --manufactures-sand: #f3efe5;
}

.unit-manufactures__header-actions,
.unit-manufactures__tools,
.unit-manufactures__chips,
.unit-manufactures__item-actions {
    display: flex;
    align-items: center;
}

.unit-manufactures__header-actions {
    gap: 10px;
}

.unit-manufactures__counter {
    padding: 4px 9px;
    border: 1px solid rgba(255, 255, 255, 0.24);
    border-radius: 999px;
    color: #eafffb;
    font-size: 0.74rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.unit-manufactures__add {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    min-height: 28px;
    padding: 0 11px;
    border: 0;
    border-radius: 999px;
    color: var(--manufactures-ink);
    background: #f7d56c;
    font-size: 0.78rem;
    font-weight: 900;
    cursor: pointer;
}

.unit-manufactures__hero {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 18px;
    padding: 18px;
    border: 1px solid rgba(0, 107, 99, 0.15);
    border-radius: 20px;
    background:
        radial-gradient(circle at 92% 18%, rgba(247, 213, 108, 0.4), transparent 24%),
        linear-gradient(135deg, #f8f4e9, #e8f4f1);
}

.unit-manufactures__eyebrow {
    margin: 0 0 4px;
    color: var(--manufactures-green);
    font-size: 0.72rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.12em;
}

.unit-manufactures__hero h3 {
    margin: 0;
    color: var(--manufactures-ink);
    font-size: clamp(1.25rem, 2vw, 1.8rem);
    font-weight: 900;
    letter-spacing: -0.04em;
}

.unit-manufactures__hero p:last-child {
    max-width: 720px;
    margin: 8px 0 0;
    color: rgba(20, 60, 56, 0.78);
    line-height: 1.5;
}

.unit-manufactures__metrics {
    display: grid;
    grid-template-columns: repeat(3, minmax(84px, 1fr));
    gap: 8px;
    align-content: start;
}

.unit-manufactures__metrics span {
    display: grid;
    gap: 2px;
    min-width: 84px;
    padding: 10px 12px;
    border: 1px solid rgba(0, 107, 99, 0.14);
    border-radius: 16px;
    color: rgba(20, 60, 56, 0.7);
    background: rgba(255, 255, 255, 0.68);
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
}

.unit-manufactures__metrics strong {
    color: var(--manufactures-green);
    font-size: 1.35rem;
    line-height: 1;
}

.unit-manufactures__form {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 12px;
    align-items: start;
    margin-top: 14px;
    padding: 14px;
    border: 1px solid rgba(0, 107, 99, 0.18);
    border-radius: 18px;
    background: #fbfaf6;
}

.unit-manufactures__select-title {
    font-weight: 800;
}

.unit-manufactures__tools {
    justify-content: space-between;
    gap: 14px;
    margin-top: 14px;
}

.unit-manufactures__tools :deep(.v-input) {
    max-width: 420px;
}

.unit-manufactures__chips {
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 6px;
}

.unit-manufactures__chips button {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 30px;
    padding: 0 10px;
    border: 1px solid rgba(0, 107, 99, 0.14);
    border-radius: 999px;
    color: var(--manufactures-ink);
    background: #ffffff;
    font-size: 0.78rem;
    font-weight: 800;
    cursor: pointer;
}

.unit-manufactures__chips strong {
    color: var(--manufactures-green);
}

.unit-manufactures__grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    margin-top: 14px;
}

.unit-manufactures__item {
    display: grid;
    gap: 14px;
    min-height: 164px;
    padding: 15px;
    border: 1px solid rgba(20, 60, 56, 0.12);
    border-radius: 20px;
    background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(248, 244, 233, 0.92));
    box-shadow: 0 14px 34px rgba(20, 60, 56, 0.07);
}

.unit-manufactures__item-main {
    min-width: 0;
}

.unit-manufactures__badge {
    display: inline-flex;
    margin-bottom: 8px;
    padding: 3px 8px;
    border-radius: 999px;
    color: var(--manufactures-green);
    background: rgba(0, 107, 99, 0.09);
    font-size: 0.72rem;
    font-weight: 900;
}

.unit-manufactures__title {
    display: block;
    color: var(--manufactures-ink);
    font-size: 1rem;
    font-weight: 900;
    line-height: 1.2;
    text-decoration: none;
}

.unit-manufactures__title:hover {
    color: var(--manufactures-green);
}

.unit-manufactures__item p {
    margin: 7px 0 0;
    color: rgba(20, 60, 56, 0.62);
    font-size: 0.84rem;
    line-height: 1.35;
}

.unit-manufactures__item-actions {
    align-self: end;
    justify-content: space-between;
    gap: 8px;
}

.unit-manufactures__item-actions a,
.unit-manufactures__item-actions button {
    min-height: 30px;
    padding: 0 10px;
    border: 1px solid rgba(0, 107, 99, 0.16);
    border-radius: 999px;
    font-size: 0.76rem;
    font-weight: 900;
    text-decoration: none;
}

.unit-manufactures__item-actions a {
    display: inline-flex;
    align-items: center;
    color: #ffffff;
    background: var(--manufactures-green);
}

.unit-manufactures__item-actions button {
    color: #8a122f;
    background: #fff7f7;
    cursor: pointer;
}

.unit-manufactures__item-actions button:disabled {
    cursor: wait;
    opacity: 0.55;
}

.unit-manufactures__empty {
    display: grid;
    place-items: center;
    gap: 6px;
    min-height: 190px;
    margin-top: 14px;
    padding: 24px;
    border: 1px dashed rgba(0, 107, 99, 0.28);
    border-radius: 20px;
    color: rgba(20, 60, 56, 0.65);
    background:
        radial-gradient(circle at 50% 0%, rgba(0, 107, 99, 0.08), transparent 34%),
        #fbfaf6;
    text-align: center;
}

.unit-manufactures__empty strong {
    color: var(--manufactures-ink);
}

@media (max-width: 1180px) {
    .unit-manufactures__hero,
    .unit-manufactures__form,
    .unit-manufactures__tools {
        grid-template-columns: 1fr;
    }

    .unit-manufactures__metrics {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .unit-manufactures__grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .unit-manufactures__tools :deep(.v-input) {
        max-width: none;
    }
}

@media (max-width: 700px) {
    .unit-manufactures__header-actions {
        gap: 6px;
    }

    .unit-manufactures__counter {
        display: none;
    }

    .unit-manufactures__hero {
        padding: 14px;
    }

    .unit-manufactures__metrics,
    .unit-manufactures__grid {
        grid-template-columns: 1fr;
    }

    .unit-manufactures__chips {
        justify-content: flex-start;
    }
}
</style>
