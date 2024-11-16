<script setup>
const props = defineProps({
    unit: Object,
})
</script>

<template>
    <Head>
        <title>Unit::{{unit.name}}</title>
    </Head>

    <v-container>
        <v-row>
            <v-col></v-col>
            <v-col>
                {{unit.name}}
            </v-col>
            <v-col></v-col>
        </v-row>
    </v-container>
</template>
