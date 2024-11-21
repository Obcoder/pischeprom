<script setup>
import { useDate } from 'vuetify'
const date = useDate()
const props = defineProps({
    unit: Object,
})

function timeDiff(time){
    let d = new Date();
    return Math.abs(d - time);
}
</script>

<template>
    <Head>
        <title>Unit</title>
    </Head>

    <v-container>
        <v-row>
            <v-col cols="4">
                <v-card>
                    <v-card-title class="bg-orange-accent-3">
                        {{unit.name}}</v-card-title>
                    <v-card-subtitle class="d-flex">
                        <v-sheet>
                            <div v-for="uri in unit.uris"
                            >
                                <a :href="uri.address"
                                   target="_blank"
                                >
                                    {{uri.address}}
                                </a>
                            </div>
                        </v-sheet>
                    </v-card-subtitle>
                    <v-card-subtitle>
                        <v-sheet>
                            <div v-for="label in unit.labels">
                                {{label.name}}
                            </div>
                        </v-sheet>
                    </v-card-subtitle>
                </v-card>
            </v-col>
            <v-col cols="3">
                <v-card>
                    <v-card-title class="bg-gray-100">
                        Stages
                    </v-card-title>
                    <v-card-text class="text-amber">
                        <v-row v-for="stage in unit.stages">
                            <v-col cols="8">
                                <v-chip >
                                    {{stage.name}}
                                </v-chip>
                            </v-col>
                            <v-col cols="3">
                                <v-sheet>
                                    {{stage.pivot.startDate}}
                                </v-sheet>
                                <v-sheet class="font-sans text-sm">
                                    {{date.format(stage.startDate, 'normalDate')}}
                                </v-sheet>
                                <v-sheet>
                                    {{date.format(stage.startDate, 'year')}}
                                </v-sheet>
                                <v-chip class="text-red-700">
                                    {{new Date.now()}}
                                </v-chip>
                                <div class="bg-zinc-100 text-zinc-900">
                                    {{timeDiff(stage.startDate)}}
                                </div>
                            </v-col>
                        </v-row>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col></v-col>
        </v-row>
    </v-container>
</template>
