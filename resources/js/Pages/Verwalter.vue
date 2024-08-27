<script setup>
import {onMounted, ref} from "vue";
import {Head} from "@inertiajs/vue3"

const props = defineProps({
    title:{
        type: String,
    },
    goods: Object,
})

let listGoods = ref([]);

onMounted(()=>{
    listGoods.value = props.goods;
})
</script>

<template>
    <Head title={{title}} />

    <v-data-table
        :items="listGoods">
    </v-data-table>
</template>
