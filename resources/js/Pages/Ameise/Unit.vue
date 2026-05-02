<script setup>
import { computed, onMounted } from 'vue'
import { useHead } from '@vueuse/head'
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'

import { useUnitPage } from '@/Composables/useUnitPage'

import UnitOverviewCard from '@/Components/Unit/UnitOverviewCard.vue'
import UnitEntitiesCard from '@/Components/Unit/UnitEntitiesCard.vue'
import UnitQuotationsCard from '@/Components/Unit/UnitQuotationsCard.vue'
import UnitManufacturesCard from '@/Components/Unit/UnitManufacturesCard.vue'
import UnitConsumptionsCard from '@/Components/Unit/UnitConsumptionsCard.vue'
import UnitStagesCard from '@/Components/Unit/UnitStagesCard.vue'
import UnitSendingsCard from '@/Components/Unit/UnitSendingsCard.vue'
import UnitSalesCard from '@/Components/Unit/UnitSalesCard.vue'

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
    'cities',
    'emails',
    'entities',
    'entityClassifications',
    'fields',
    'goods',
    'labels',
    'measures',
    'products',
    'telephones',
    'uris',
]

function hasAllDictionaries(source = {}) {
    return requiredDictionaryKeys.every((key) => Array.isArray(source?.[key]))
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
    <v-container fluid class="pa-4">
        <v-row align="start" dense>
            <v-col cols="12" lg="4">
                <UnitOverviewCard
                    :unit="unit"
                    :files="files"
                    :dict="dict"
                    :loading="loading"
                    @refresh="refreshUnit"
                />
            </v-col>

            <v-col cols="12" lg="5">
                <UnitSendingsCard :unit="unit" />
            </v-col>

            <v-col cols="12" lg="3">
                <UnitStagesCard :stages="unit.stages || []" />
            </v-col>
        </v-row>

        <v-row>
            <v-col cols="12" lg="4">
                <UnitManufacturesCard
                    :unit="unit"
                    :dict="dict"
                    @refresh="refreshUnit"
                />
            </v-col>

            <v-col cols="12" lg="4">
                <UnitQuotationsCard
                    :unit="unit"
                    :dict="dict"
                    :goods-loading="loading.goods"
                    :search-goods="searchGoods"
                    @refresh="refreshUnit"
                />
            </v-col>

            <v-col cols="12" lg="4">
                <UnitConsumptionsCard
                    :unit="unit"
                    :dict="dict"
                    @refresh="refreshUnit"
                />
            </v-col>
        </v-row>

        <v-row>
            <v-col cols="12" lg="5">
                <UnitEntitiesCard
                    :unit="unit"
                    :dict="dict"
                    @refresh="refreshUnit"
                />
            </v-col>

            <v-col cols="12" lg="7">
                <UnitSalesCard :entities="unit.entities || []" />
            </v-col>
        </v-row>
    </v-container>
</template>
