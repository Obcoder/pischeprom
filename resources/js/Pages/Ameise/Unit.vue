<script setup>
import { computed, onMounted } from 'vue'
import { useHead } from '@vueuse/head'
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'

import { useUnitPage } from '@/Composables/useUnitPage'

import UnitOverviewCard from '@/Components/Unit/UnitOverviewCard.vue'
import UnitSendingsCard from '@/Components/Unit/UnitSendingsCard.vue'
import UnitSalesCard from '@/Components/Unit/UnitSalesCard.vue'
import UnitCallsCard from '@/Components/Unit/UnitCallsCard.vue'
import UnitManufacturesCard from '@/Components/Unit/UnitManufacturesCard.vue'
import UnitTradeTabsCard from '@/Components/Unit/UnitTradeTabsCard.vue'

defineOptions({
    layout: VerwalterLayout,
})

const props = defineProps({
    unit: Object,
    dictionaries: {
        type: Object,
        default: () => ({})
    },
    files: {
        type: Array,
        default: () => []
    }
})

const {
    unit,
    files,
    dict,
    loading,
    refreshUnit,
    loadFiles,
    loadDictionaries,
    searchGoods,
} = useUnitPage(props.unit, props.dictionaries, props.files)

const pageTitle = computed(() => `Unit: ${unit.value?.name ?? ''}`)

useHead(() => ({
    title: pageTitle.value,
    meta: [
        {
            name: 'description',
            content: `Информация о блоке ${unit.value?.name ?? ''}`
        }
    ]
}))

const requiredDictionaryKeys = [
    'buildings',
    'buildingTypes',
    'cities',
    'currencies',
    'emails',
    'entities',
    'entityClassifications',
    'fields',
    'goods',
    'industries',
    'labels',
    'measures',
    'products',
    'telephones',
    'uris',
]

function hasAllDictionaries(source = {}) {
    return requiredDictionaryKeys.every((key) => Array.isArray(source?.[key]))
}

async function refreshAll() {
    await Promise.all([
        refreshUnit(),
        loadDictionaries(),
    ])
}

onMounted(async () => {
    if (!props.files?.length) {
        await loadFiles()
    }

    if (!hasAllDictionaries(props.dictionaries)) {
        await loadDictionaries()
    }
})
</script>

<template>
    <v-container fluid class="unit-page pa-3">
        <section class="unit-page__band unit-page__band--overview">
            <div>
                <UnitOverviewCard
                    :unit="unit"
                    :files="files"
                    :dict="dict"
                    :loading="loading"
                    @refresh="refreshAll"
                />
            </div>
        </section>

        <section class="unit-page__band unit-page__band--trade-tabs">
            <div>
                <UnitTradeTabsCard
                    :unit="unit"
                    :dict="dict"
                    :goods-loading="loading.goods"
                    :search-goods="searchGoods"
                    @refresh="refreshUnit"
                />
            </div>
        </section>

        <section class="unit-page__band unit-page__band--manufactures">
            <div>
                <UnitManufacturesCard
                    :unit="unit"
                    :dict="dict"
                    @refresh="refreshUnit"
                />
            </div>
        </section>

        <section class="unit-page__band unit-page__band--communications">
            <div>
                <UnitSendingsCard :unit="unit" />
            </div>

            <div>
                <UnitCallsCard :unit="unit" />
            </div>
        </section>

        <section class="unit-page__band unit-page__band--trade">
            <div>
                <UnitSalesCard :entities="unit.entities || []" />
            </div>
        </section>

    </v-container>
</template>

<style scoped>
.unit-page {
    --unit-gap: 10px;
    background:
        radial-gradient(circle at 12% 0%, rgba(128, 0, 32, 0.07), transparent 30%),
        linear-gradient(180deg, #fff, #fbf7f4);
}

.unit-page__band {
    display: grid;
    gap: var(--unit-gap);
    align-items: start;
    margin-bottom: var(--unit-gap);
}

.unit-page__band--overview {
    grid-template-columns: 1fr;
}

.unit-page__band--trade-tabs {
    grid-template-columns: 1fr;
}

.unit-page__band--manufactures {
    grid-template-columns: 1fr;
}

.unit-page__band--communications {
    grid-template-columns: minmax(0, 2fr) minmax(320px, 1fr);
}

.unit-page__band--trade {
    grid-template-columns: minmax(300px, 1fr);
}

.unit-page__band > div {
    min-width: 0;
}

@media (max-width: 1180px) {
    .unit-page__band--overview,
    .unit-page__band--trade-tabs,
    .unit-page__band--manufactures,
    .unit-page__band--communications,
    .unit-page__band--trade {
        grid-template-columns: 1fr;
    }
}
</style>
