<script setup>
const props = defineProps({
    check: Object,
})
</script>

<template>
    <v-container>
        <v-row>
            <v-col></v-col>
            <v-col>
                {{check.amount}}
            </v-col>
            <v-col></v-col>
        </v-row>
    </v-container>
</template>
