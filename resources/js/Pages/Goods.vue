<template>
    <v-container fluid>
        <v-row>
            <v-col>
                <v-text-field v-model="searchGoods"
                              @input="indexGoods(searchGoods)"
                              density="compact"
                              variant="outlined"
                              label="Поиск по товарам"
                              placeholder="вводите название товара"
                ></v-text-field>
            </v-col>
            <v-col></v-col>
            <v-col></v-col>
        </v-row>
        <v-row>
            <v-col cols="12"
                   class="flex flex-row"
            >
                <v-card v-for="good in goods"
                        :key="good.id"
                        class="w-60 mr-2"
                        rounded
                >
                    <v-img :src="good.ava_image || logo"></v-img>
                    <v-card-subtitle class="font-sans text-wrap">
                        {{good.name}}
                    </v-card-subtitle>
                    <v-card-actions>
                        <v-btn text="заказать"
                               density="comfortable"
                               class="ms-2"
                               variant="tonal"
                        ></v-btn>
                        <v-divider opacity="80"
                                   color="grey"
                                   vertical
                        ></v-divider>
                        <v-btn text="подробнее"
                               density="comfortable"
                               class="ms-2"
                               variant="flat"
                        ></v-btn>
                    </v-card-actions>
                </v-card>
            </v-col>
        </v-row>
    </v-container>
</template>

<script setup>
import LayoutDefault from '@/Layouts/LayoutDefault.vue'
import {logo} from "@/Pages/Helpers/consts.js";
import {onMounted, ref} from "vue";
import axios from "axios";
defineOptions({
    layout: LayoutDefault,
})

let goods = ref()
function indexGoods(like){
    axios.get(route('api.goods.index'), {
        params: {
            search: like,
        }
    }).then(function (response){
        goods.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}
let searchGoods = ref()

onMounted(()=>{
    indexGoods()
})
</script>
