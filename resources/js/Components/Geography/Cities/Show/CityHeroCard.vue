<script setup>
const props = defineProps({
    city: {
        type: Object,
        required: true,
    },
    latestPopulation: {
        type: [Number, String],
        default: null,
    },
    latestPopulationYear: {
        type: [Number, String],
        default: null,
    },
})

defineEmits(['edit', 'population'])

function formatNumber(value) {
    if (value === null || value === undefined || value === '') {
        return '—'
    }

    return new Intl.NumberFormat('ru-RU').format(value)
}
</script>

<template>
    <v-sheet class="pa-4 rounded border border-teal-800 bg-slate-900">
        <v-row align="center">
            <v-col cols="12" md="2" class="d-flex justify-center">
                <a
                    v-if="city.wiki"
                    :href="city.wiki"
                    target="_blank"
                    rel="noopener noreferrer"
                    title="Открыть статью Wikipedia"
                >
                    <v-avatar
                        size="112"
                        rounded="xl"
                        class="border border-teal-600 bg-slate-800 cursor-pointer hover:opacity-80"
                    >
                        <v-img
                            v-if="city.wiki_thumbnail"
                            :src="city.wiki_thumbnail"
                            cover
                        />

                        <span
                            v-else
                            class="text-h3 text-teal-lighten-3"
                        >
                            {{ city.name?.slice(0, 1) }}
                        </span>
                    </v-avatar>
                </a>

                <v-avatar
                    v-else
                    size="112"
                    rounded="xl"
                    class="border border-teal-600 bg-slate-800"
                >
                    <v-img
                        v-if="city.wiki_thumbnail"
                        :src="city.wiki_thumbnail"
                        cover
                    />

                    <span
                        v-else
                        class="text-h3 text-teal-lighten-3"
                    >
                        {{ city.name?.slice(0, 1) }}
                    </span>
                </v-avatar>
            </v-col>

            <v-col cols="12" md="7">
                <div class="text-h4 text-teal-lighten-3 font-ComfortaaVariableFont">
                    {{ city.name }}
                </div>

                <div class="mt-1 text-caption text-teal-darken-1">
                    {{ city.region?.name || '—' }}
                    <span class="mx-1">/</span>
                    {{ city.region?.country?.name || '—' }}
                </div>

                <div class="mt-3 d-flex flex-wrap ga-2">
                    <v-chip
                        color="teal"
                        variant="tonal"
                        size="small"
                    >
                        👥 {{ formatNumber(latestPopulation) }}
                        <span v-if="latestPopulationYear" class="ml-1">
                            / {{ latestPopulationYear }}
                        </span>
                    </v-chip>

                    <v-chip
                        color="blue-grey"
                        variant="tonal"
                        size="small"
                    >
                        lat: {{ city.latitude ?? '—' }}
                    </v-chip>

                    <v-chip
                        color="blue-grey"
                        variant="tonal"
                        size="small"
                    >
                        lon: {{ city.longitude ?? '—' }}
                    </v-chip>
                </div>

                <div class="mt-3 d-flex flex-wrap ga-2">
                    <a
                        v-if="city.wiki"
                        :href="city.wiki"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-teal-lighten-3 hover:text-white text-sm"
                    >
                        Wikipedia →
                    </a>

                    <a
                        v-if="city.yandexmapsgeo"
                        :href="city.yandexmapsgeo"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-teal-lighten-3 hover:text-white text-sm"
                    >
                        Yandex Maps →
                    </a>

                    <a
                        v-if="city.twogis"
                        :href="city.twogis"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-teal-lighten-3 hover:text-white text-sm"
                    >
                        2GIS →
                    </a>
                </div>
            </v-col>

            <v-col cols="12" md="3">
                <div class="d-flex flex-column ga-2">
                    <v-btn
                        text="Редактировать"
                        color="amber"
                        variant="tonal"
                        density="comfortable"
                        prepend-icon="mdi-pencil"
                        @click="$emit('edit')"
                    />

                    <v-btn
                        text="Население по годам"
                        color="teal"
                        variant="tonal"
                        density="comfortable"
                        prepend-icon="mdi-chart-line"
                        @click="$emit('population')"
                    />
                </div>
            </v-col>
        </v-row>
    </v-sheet>
</template>
