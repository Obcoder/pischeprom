<script setup>
import { computed, onMounted } from 'vue'
import { useHead } from '@vueuse/head'
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'

import { useUnitPage } from '@/Composables/useUnitPage'

import UnitOverviewCard from '@/Components/Unit/UnitOverviewCard.vue'
import UnitLabelsCard from '@/Components/Unit/UnitLabelsCard.vue'
import UnitEntitiesCard from '@/Components/Unit/UnitEntitiesCard.vue'
import UnitQuotationsCard from '@/Components/Unit/UnitQuotationsCard.vue'

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
} = useUnitPage(props.unit, props.dictionaries, props.files)

const pageTitle = computed(() => `Unit: ${unit.value?.name ?? ''}`)

useHead({
    title: pageTitle,
    meta: [
        {
            name: 'description',
            content: `Информация о блоке ${unit.value?.name ?? ''}`
        }
    ]
})

onMounted(async () => {
    if (!props.files?.length) {
        await loadFiles()
    }

    if (!Object.keys(props.dictionaries || {}).length) {
        await loadDictionaries()
    }
})
</script>

<template>
    <v-container fluid class="pa-4">
        <v-row>
            <v-col cols="12" lg="4">
                <UnitOverviewCard
                    :unit="unit"
                    :files="files"
                    :dict="dict"
                    :loading="loading"
                    @refresh="refreshUnit"
                />
            </v-col>

            <v-col cols="12" lg="2">
                <UnitLabelsCard
                    :unit="unit"
                    :dict="dict"
                    @refresh="refreshUnit"
                />
            </v-col>

            <v-col cols="12" lg="6">
                <UnitEntitiesCard
                    :unit="unit"
                    :dict="dict"
                    @refresh="refreshUnit"
                />
            </v-col>
        </v-row>

        <v-row>
            <v-col cols="12" lg="6">
                <UnitQuotationsCard
                    :unit="unit"
                    :dict="dict"
                    @refresh="refreshUnit"
                />
            </v-col>

            <v-col cols="12" lg="6">
                <v-card rounded="xl" elevation="1" border class="h-100">
                    <v-card-title class="text-h6">Следующий блок</v-card-title>
                    <v-card-text>
                        Тут можно вставить `Manufactures`, `Consumptions`, `Stages`, `Sendings`.
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
    </v-container>
</template>
