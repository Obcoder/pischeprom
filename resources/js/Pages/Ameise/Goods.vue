<script setup>
import { logo } from "@/Pages/Helpers/consts.js";
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {onMounted, ref} from "vue";
import axios from "axios";
import {useForm} from "@inertiajs/vue3";
import {useDate} from "vuetify";
defineOptions({
    layout: VerwalterLayout,
})

const date = useDate()

let goods = ref();
function indexGoods(){
    axios.get(route('api.goods.index')).then(function (response){
        goods.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}
let searchGoods = ref();
const formGood = useForm({
    name: null,
    ava_image: null,
})
function storeGood(){
    formGood.post(route('api.goods.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: false,
        onSuccess: ()=> {
            formGood.reset()
        },
    })
}

const headersGoods = [
    {
        title: 'Avatar',
        key: 'ava_image',
    },
    {
        title: 'name',
        key: 'name',
    },
];

onMounted(()=>{
    indexGoods()
})
</script>

<template>
    <v-container fluid>
        <v-row>
            <v-col>
                <v-row>
                    <v-col cols="9">
                        <v-text-field label="Поиск: Товары"
                                      v-model="searchGoods"
                                      variant="outlined"
                                      class="mt-1 py-1"
                        ></v-text-field>
                    </v-col>
                    <v-col cols="3">
                        <v-dialog transition="dialog-top-transition"
                                  width="900"
                        >
                            <template v-slot:activator="{ props: activatorProps }">
                                <v-btn
                                    v-bind="activatorProps"
                                    text="Новый товар"
                                    block
                                ></v-btn>
                            </template>

                            <template v-slot:default="{ isActive }">
                                <v-card>
                                    <v-card-title>Form Good</v-card-title>
                                    <v-card-text>
                                        <v-form @submit.prevent>
                                            <v-row>
                                                <v-text-field v-model="formGood.name"
                                                              label="Good name"
                                                              variant="outlined"
                                                ></v-text-field>
                                            </v-row>
                                            <v-row>
                                                <v-file-input v-model="formGood.ava_image"
                                                              label="Good's avatar"
                                                              chips
                                                ></v-file-input>
                                            </v-row>
                                        </v-form>
                                    </v-card-text>
                                    <v-card-actions>
                                        <v-spacer></v-spacer>
                                        <v-divider></v-divider>

                                        <v-btn text="save"
                                               @click="storeGood"
                                               variant="plain"
                                               color="indigo-darken-4"
                                        ></v-btn>
                                    </v-card-actions>
                                </v-card>
                            </template>
                        </v-dialog>
                    </v-col>
                </v-row>
            </v-col>
            <v-col></v-col>
        </v-row>
        <v-row>
            <v-col class="flex flex-row">
                <v-card v-for="good in goods"
                        color="#952175">
                    <div class="d-flex flex-no-wrap justify-space-between">
                        <div>
                            <v-card-title class="text-h5">
                                {{good.name}}
                            </v-card-title>
                            <v-card-actions>
                                <v-btn
                                    class="ms-2"
                                    icon="mdi-play"
                                    variant="text"
                                ></v-btn>
                            </v-card-actions>
                        </div>

                        <v-avatar
                            class="ma-3"
                            rounded="0"
                            size="125"
                        >
                            <v-img src={{good.ava_image}}></v-img>
                        </v-avatar>
                    </div>
                </v-card>
            </v-col>
        </v-row>
    </v-container>
</template>
