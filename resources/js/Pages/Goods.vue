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
        <v-row class="d-flex justify-start">
            <v-card v-for="good in goods"
                    color="red-lighten-2"
            >
                <div class="d-flex">
                    <div>
                        <v-card-title class="text-h6">
                            {{good.name}}
                        </v-card-title>
                        <v-card-actions>
                            <v-btn text="заказать"
                                   density="comfortable"
                                   class="ms-2"
                                   variant="tonal"
                            ></v-btn>
                        </v-card-actions>
                    </div>

                    <v-avatar
                        class="ma-3"
                        rounded="0"
                        size="125"
                    >
                        <v-img :src="good.ava_image || logo"></v-img>
                    </v-avatar>
                </div>
            </v-card>
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
