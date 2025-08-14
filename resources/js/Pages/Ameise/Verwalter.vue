<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {useHead} from "@vueuse/head";
import {onMounted, ref} from "vue";
import axios from "axios";
import {route} from "ziggy-js";
defineOptions({
    layout: VerwalterLayout,
})
const props = defineProps({
    title: String,
})

const goods = ref([])

//      G O O D S
function indexGoods(){
    axios.get(route('goods.index')).then(function (response){
        goods.value = response.data
    }).catch(function (error){
        console.error(error)
    })
}

onMounted(()=>{
    indexGoods()
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
                <v-sheet>
                    <v-row>
                        <v-col cols="9">Товаров</v-col>
                        <v-col cols="3">{{goods.length}}</v-col>
                    </v-row>
                </v-sheet>
            </v-col>
        </v-row>
    </v-container>
</template>
