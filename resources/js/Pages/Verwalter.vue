<script setup>
import {onMounted, ref} from "vue";
import {Head} from "@inertiajs/vue3";
const props = defineProps({
    title: String,
    goods: Object,
})
onMounted(()=>{
    headersGoods.value = [
        {
            title: 'name',
            key: 'name',
        },
    ]
})
let headersGoods = ref([]);

</script>

<template>
    <Head>
        <title>УТ</title>
    </Head>

    <v-container>
        <v-row>
            <v-col>
                <v-data-table :items="goods"
                              :headers="headersGoods"
                              density="compact"
                              hover="hover"
                >
                </v-data-table>
            </v-col>
            <v-col></v-col>
            <v-col></v-col>
        </v-row>
    </v-container>
</template>
