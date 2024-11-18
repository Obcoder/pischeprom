<script setup>
const props = defineProps({
    unit: Object,
})
</script>

<template>
    <Head>
        <title>Unit</title>
    </Head>

    <v-container>
        <v-row>
            <v-col>
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
            <v-col></v-col>
            <v-col></v-col>
        </v-row>
    </v-container>
</template>
