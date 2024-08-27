<script setup>
import {onMounted, ref} from "vue";

const props = defineProps({
    goods: {
        type: Object,
    }
})

let listGoods = ref([]);
onMounted(()=>{
    listGoods.value = props.goods;
})
</script>

<template>
    <v-data-table
        :items="listGoods">
    </v-data-table>
</template>
