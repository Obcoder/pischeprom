<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
defineOptions({
    layout: VerwalterLayout,
})
const props = defineProps({
    city: Object,
})

</script>

<template>
    <v-container>
        <v-row>
            <v-col>
                <span class="text-xl"
                >
                    {{city.name}}</span>
            </v-col>
            <v-col></v-col>
            <v-col></v-col>
        </v-row>
    </v-container>
</template>
