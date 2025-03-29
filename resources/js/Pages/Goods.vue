<template>
    <v-container>
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
            <v-col v-for="good in goods"
                   :key="good.id"
                   cols="12"
                   sm="6"
                   md="4"
                   lg="3"
            >
                <v-card color="white"
                        class="mx-3 border border-1 border-pink-950 border-opacity-75"
                        rounded
                >
                    <v-img :src="good.ava_image || logo"></v-img>
                    <div class="d-flex">
                        <div>
                            <v-card-subtitle class="font-sans">
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
                        </div>

                        <v-avatar
                            class="ga-1"
                            size="112"
                            rounded
                            border
                        >
                            <v-img :src="good.ava_image || logo"></v-img>
                        </v-avatar>
                    </div>
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
