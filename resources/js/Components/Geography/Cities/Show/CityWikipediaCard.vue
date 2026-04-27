<script setup>
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
            Wikipedia
        </v-card-title>

        <v-card-text>
            <div
                v-if="city.wiki_title"
                class="text-h6 font-ComfortaaVariableFont"
            >
                {{ city.wiki_title }}
            </div>

            <div
                v-if="city.wiki_summary"
                class="mt-2 text-sm text-grey-lighten-1"
            >
                {{ city.wiki_summary }}
            </div>

            <div
                v-else
                class="text-grey text-sm"
            >
                Описание из Wikipedia пока не загружено.
            </div>

            <div class="mt-4 d-flex flex-column ga-1">
                <div class="text-caption text-teal-darken-1">
                    Page ID: {{ city.wiki_page_id ?? '—' }}
                </div>

                <div class="text-caption text-teal-darken-1">
                    Lang: {{ city.wiki_lang ?? '—' }}
                </div>
            </div>

            <div class="mt-4">
                <v-btn
                    v-if="city.wiki"
                    :href="city.wiki"
                    target="_blank"
                    rel="noopener noreferrer"
                    text="Открыть статью"
                    color="teal"
                    variant="tonal"
                    density="compact"
                    append-icon="mdi-open-in-new"
                />
            </div>
        </v-card-text>
    </v-card>
</template>
