<script setup>
import { computed } from 'vue'

const props = defineProps({
    city: {
        type: Object,
        required: true,
    },
})

const apiKey = import.meta.env.VITE_YANDEX_STATIC_MAPS_API_KEY

const parsedYandexLink = computed(() => {
    if (!props.city.yandexmapsgeo) {
        return {
            center: null,
            zoom: null,
        }
    }

    try {
        const url = new URL(props.city.yandexmapsgeo)
        const ll = url.searchParams.get('ll')
        const z = url.searchParams.get('z')

        return {
            center: ll ? decodeURIComponent(ll) : null,
            zoom: z ? Math.floor(Number(z)) : null,
        }
    } catch (error) {
        return {
            center: null,
            zoom: null,
        }
    }
})

const center = computed(() => {
    if (props.city.longitude && props.city.latitude) {
        return `${props.city.longitude},${props.city.latitude}`
    }

    return parsedYandexLink.value.center
})

const zoom = computed(() => {
    return parsedYandexLink.value.zoom || 11
})

const staticMapUrl = computed(() => {
    if (!apiKey || !center.value) {
        return null
    }

    const params = new URLSearchParams({
        lang: 'ru_RU',
        ll: center.value,
        z: String(zoom.value),
        size: '650,330',
        pt: `${center.value},pm2rdm`,
        apikey: apiKey,
    })

    return `https://static-maps.yandex.ru/v1?${params.toString()}`
})

const hasMap = computed(() => {
    return Boolean(staticMapUrl.value)
})
</script>

<template>
    <v-card class="rounded border border-teal-900 bg-slate-950 h-100">
        <v-card-title class="d-flex justify-space-between align-center">
            <span class="text-teal-lighten-3">
                Yandex Map
            </span>

            <v-btn
                v-if="city.yandexmapsgeo"
                :href="city.yandexmapsgeo"
                target="_blank"
                rel="noopener noreferrer"
                icon="mdi-open-in-new"
                size="x-small"
                variant="text"
                color="teal-lighten-3"
                title="Открыть в Яндекс.Картах"
            />
        </v-card-title>

        <v-card-text>
            <a
                v-if="hasMap"
                :href="city.yandexmapsgeo || '#'"
                target="_blank"
                rel="noopener noreferrer"
                class="block"
            >
                <v-img
                    :src="staticMapUrl"
                    height="330"
                    cover
                    class="rounded border border-teal-800 bg-slate-900 cursor-pointer"
                >
                    <template #placeholder>
                        <div class="d-flex align-center justify-center fill-height">
                            <v-progress-circular
                                indeterminate
                                color="teal"
                            />
                        </div>
                    </template>
                </v-img>
            </a>

            <v-alert
                v-else
                type="info"
                variant="tonal"
                density="compact"
            >
                Для отображения карты нужны координаты города и ключ
                <code>VITE_YANDEX_STATIC_MAPS_API_KEY</code>.
            </v-alert>

            <div class="mt-3 d-flex flex-wrap ga-2">
                <v-chip
                    size="x-small"
                    color="blue-grey"
                    variant="tonal"
                >
                    lat: {{ city.latitude ?? '—' }}
                </v-chip>

                <v-chip
                    size="x-small"
                    color="blue-grey"
                    variant="tonal"
                >
                    lon: {{ city.longitude ?? '—' }}
                </v-chip>

                <v-chip
                    size="x-small"
                    color="teal"
                    variant="tonal"
                >
                    zoom: {{ zoom }}
                </v-chip>
            </div>
        </v-card-text>
    </v-card>
</template>
