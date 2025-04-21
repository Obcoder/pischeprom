<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {Link, useForm} from "@inertiajs/vue3";
import {onMounted, ref} from "vue";
import axios from "axios";
defineOptions({
    layout: VerwalterLayout,
})

let products = ref();
let searchProducts = ref();
function indexProducts(){
    axios.get(route('products.index')).then(function (response) {
        // handle success
        products.value = response.data
    })
        .catch(function (error) {
            // handle error
            console.log(error);
        })
        .finally(function () {
            // always executed
        });
}
const headersProducts = [
    {
        title: 'rus',
        key: 'rus',
    },
    {
        title: 'eng',
        key: 'eng',
    },
]
let showFormProduct = ref(false);
const formProduct = useForm({
    rus: null,
    eng: null,
    zh: null,
    es: null,
})
function storeProduct(){
    formProduct.post(route('products.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: true,
        onSuccess: ()=> {
            formProduct.reset();
            indexProducts(searchProducts);
        },
    })
}

onMounted(()=>{
    indexProducts();
})
</script>

<template>
    <v-container>
        <v-row>
            <v-col>
                <v-card
                    flat
                    color="grey"
                >
                    <v-data-table :headers="headersProducts"
                                  :items="products"
                                  :search="searchProducts"
                                  items-per-page="124"
                                  density="compact"
                                  hover="hover"
                    >
                        <template v-slot:top>
                            <v-row>
                                <v-col cols="9">
                                    <v-text-field
                                        class="py-2"
                                        v-model="searchProducts"
                                        label="Filter products"
                                        prepend-inner-icon="mdi-magnify"
                                        variant="outlined"
                                        clearable
                                    ></v-text-field>
                                </v-col>
                                <v-col cols="3">
                                    <v-dialog
                                        v-model="showFormProduct"
                                        max-width="500px"
                                    >
                                        <template v-slot:activator="{ props }">
                                            <v-btn
                                                class="mb-2"
                                                color="primary"
                                                dark
                                                v-bind="props"
                                            >
                                                New Item
                                            </v-btn>
                                        </template>

                                        <v-card>
                                            <v-card-text>
                                                <v-form @submit.prevent>
                                                    <v-row>
                                                        <v-text-field
                                                            v-model="formProduct.rus"
                                                            label="Product"
                                                        ></v-text-field>
                                                    </v-row>
                                                    <v-row>
                                                        <v-text-field
                                                            v-model="formProduct.eng"
                                                            label="Product eng"
                                                        ></v-text-field>
                                                    </v-row>
                                                    <v-row>
                                                        <v-text-field
                                                            v-model="formProduct.zh"
                                                            label="Product zh"
                                                        ></v-text-field>
                                                    </v-row>
                                                    <v-row>
                                                        <v-text-field
                                                            v-model="formProduct.es"
                                                            label="Product es"
                                                        ></v-text-field>
                                                    </v-row>
                                                </v-form>
                                            </v-card-text>

                                            <v-card-actions>
                                                <v-spacer></v-spacer>
                                                <v-btn
                                                    color="blue-darken-1"
                                                    variant="text"
                                                    @click="close"
                                                >
                                                    Cancel
                                                </v-btn>

                                                <v-divider></v-divider>

                                                <v-btn
                                                    color="blue-darken-1"
                                                    variant="text"
                                                    @click="storeProduct"
                                                >
                                                    Store
                                                </v-btn>
                                            </v-card-actions>
                                        </v-card>
                                    </v-dialog>
                                </v-col>
                            </v-row>
                        </template>
                        <template v-slot:item.rus="{item}">
                            <Link :href="route('product.show', item.id)"
                                  class="text-blue-950"
                            >
                                <span class="font-Screpka text-xl">{{item.rus}}</span>
                            </Link>
                        </template>
                    </v-data-table>
                </v-card>
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
