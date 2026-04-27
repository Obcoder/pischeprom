<script setup>
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

defineProps({
    city: {
        type: Object,
        required: true,
    },
})
</script>

<template>
    <v-card class="rounded border border-teal-900 bg-slate-950 h-100">
        <v-card-title class="text-teal-lighten-3">
            Region / Country
        </v-card-title>

        <v-card-text>
            <div v-if="city.region">
                <div class="text-caption text-teal-darken-1">
                    Регион
                </div>

                <Link
                    :href="route('Ameise.region', city.region.id)"
                    class="text-h6 text-teal-lighten-3 hover:text-white font-ComfortaaVariableFont"
                >
                    {{ city.region.name }}
                </Link>

                <div class="mt-4 text-caption text-teal-darken-1">
                    Страна
                </div>

                <div class="d-flex align-center ga-3 mt-1">
                    <v-avatar
                        v-if="city.region.country?.flag"
                        size="42"
                        rounded="lg"
                        class="border"
                    >
                        <v-img
                            :src="city.region.country.flag"
                            cover
                        />
                    </v-avatar>

                    <div>
                        <div class="text-teal-lighten-4">
                            {{ city.region.country?.name || '—' }}
                        </div>

                        <div class="text-caption text-grey">
                            ISO: {{ city.region.country?.сodeISO ?? '—' }}
                        </div>
                    </div>
                </div>

                <div class="mt-4 text-caption text-grey">
                    Площадь региона:
                    {{ city.region.area ?? '—' }}
                </div>
            </div>

            <div
                v-else
                class="text-grey text-sm"
            >
                Регион не указан.
            </div>
        </v-card-text>
    </v-card>
</template>
