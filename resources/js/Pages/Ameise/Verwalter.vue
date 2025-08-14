<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {useHead} from "@vueuse/head";
defineOptions({
    layout: VerwalterLayout,
})
const props = defineProps({
    title: String,
    goods: Object,
})

useHead({
    title: `Управление торговлей`,
    meta: [
        {
            name: 'description',
            content: `Управление торговлей`,
        }
    ]
})
</script>

<template>
    <v-container>
        <v-row>
            <v-col cols="1">
                <v-sheet>{{}}</v-sheet>
            </v-col>
        </v-row>
    </v-container>
</template>
