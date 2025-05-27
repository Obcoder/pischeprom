<script setup>
import { logo } from "@/Pages/Helpers/consts.js";
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {onMounted, ref} from "vue";
import axios from "axios";
import {useForm, Link} from "@inertiajs/vue3";
import {useDate} from "vuetify";
import {useHead} from "@vueuse/head";
import {route} from "ziggy-js";
defineOptions({
    layout: VerwalterLayout,
})
const date = useDate()

let goods = ref()
const good = ref(null)
let products = ref()

let searchGoods = ref();
const formGood = useForm({
    name: null,
    ava_image: null,
    products: null,
})
function storeGood(){
    formGood.post(route('goods.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: false,
        onSuccess: ()=> {
            formGood.reset()
            indexGoods(searchGoods)
        },
    })
}

function fetchGood(id){
    axios.get(route('Ameise.good.show', id)).then(function (response){
        good.value = response.data
    }).catch(function (error){
        console.error(error)
    })
}
function indexGoods(){
    axios.get(route('goods.index')).then(function (response){
        goods.value = response.data;
        // Проверяем, есть ли элементы в goods и берём первый
        if (goods.value && goods.value.length > 0) {
            fetchGood(goods.value[0].id);
        }
    }).catch(function (error){
        console.log(error)
    })
}
function indexProducts(){
    axios.get(route('products.index')).then(function (response){
        products.value = response.data
    }).catch(function (error){
        console.log(error)
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
    indexProducts()
})

useHead({
    title: `Товары`,
    meta: [
        {
            name: 'description',
            content: `Товары в БД ПИЩЕПРОМ-СЕРВЕРА`,
        }
    ]
})
// Функция для генерации slug, если он отсутствует
const generateSlug = (name) => {
    return name
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/(^-|-$)/g, "");
}
</script>

<template>
    <v-container>
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
                                                <v-col cols="12"
                                                >
                                                    <v-autocomplete :items="products"
                                                                    :item-title="'rus'"
                                                                    :item-value="'id'"
                                                                    v-model="formGood.products"
                                                                    label="Products"
                                                                    placeholder="Выбери Product"
                                                                    density="compact"
                                                                    variant="outlined"
                                                                    color="red"
                                                                    multiple
                                                    ></v-autocomplete>
                                                </v-col>
                                            </v-row>
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
                <v-row>
                    <v-data-table :items="goods"
                                  :headers="headersGoods"
                                  :search="searchGoods"
                                  items-per-page="90"
                                  density="compact"
                                  hover="hover"
                    >
                        <template v-slot:item.ava_image="{item}">
                            <v-badge :content="item.id"
                                     color="teal"
                            >
                                <v-img :src="item.ava_image || logo"
                                       alt="Avatar"
                                       width="50"
                                       height="50"
                                       cover
                                       class="rounded"
                                ></v-img>
                            </v-badge>
                        </template>
                        <template v-slot:item.name="{ item }">
                            <Link :href="route('Ameise.good.show', { id: item.id, slug: item.slug || generateSlug(item.name) })"
                                  class="text-decoration-none"
                            >
                                {{ item.name }}
                            </Link>
                        </template>
                    </v-data-table>
                </v-row>
            </v-col>
            <v-col lg="3">
                <v-sheet>
                    <v-row>
                        <v-col>
                            <span v-if="good && good.name">{{ good.name }}</span>
                            <span v-else>Загрузка...</span>
                        </v-col>
                    </v-row>
                </v-sheet>
            </v-col>
        </v-row>
    </v-container>
</template>

<style scoped>
input{
    outline: none;
}
input:focus{
    outline: none;
}
</style>
