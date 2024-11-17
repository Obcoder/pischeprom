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
            <v-col></v-col>
            <v-col>
                <v-card>
                    <v-card-title>{{unit.name}}</v-card-title>
                    <v-card-subtitle>
                        <v-sheet>
                            <div v-for="uri in unit.uris">
                                {{uri.address}}
                            </div>
                        </v-sheet>
                    </v-card-subtitle>
                </v-card>
            </v-col>
            <v-col></v-col>
        </v-row>
    </v-container>
</template>
