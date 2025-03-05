<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {Link, useForm} from "@inertiajs/vue3";
import {onMounted, ref} from "vue";
import axios from "axios";
defineOptions({
    layout: VerwalterLayout,
})

let listProducts = ref();
let searchProducts = ref();
function apiIndexProducts(){
    axios.get(route('api.products')).then(function (response) {
        // handle success
        listProducts.value = response.data;
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
    'name',
]
let showFormProduct = ref(false);
const formProduct = useForm({
    rus: null,
    eng: null,
    zh: null,
    es: null,
})

onMounted(()=>{
    apiIndexProducts();
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
                                  :items="listProducts"
                                  :search="searchProducts"
                                  items-per-page="24"
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
                                {{item.rus}}
                            </Link>
                        </template>
                    </v-data-table>
                </v-card>
            </v-col>
        </v-row>
    </v-container>
</template>
