<script setup>
import {onMounted, ref} from "vue";
import {Head, useForm} from "@inertiajs/vue3";
import axios from "axios";
import {orange} from "vuetify/util/colors";
const props = defineProps({
    title: String,
    goods: Object,
    uris: Object,
})
onMounted(()=>{
    headersGoods.value = [
        {
            title: 'name',
            key: 'name',
        },
    ];

    getManufacturers();
    getProducts();
    getUnits();
    getUris();
})
let manufacturers = ref();
let listProducts = ref();
let listUnits = ref();
let listUris = ref();
let headersGoods = ref([]);
let searchUnits = ref('');
let tab = ref();
let selectedUris = ref([]);
let showFormUri = ref(false);

const formUnit = useForm({
    name: null,
    uris: null,
});
const formUri = useForm({
    address: null,
})

function getManufacturers(){
    axios.get(route('api.manufacturers')).then(function (response) {
        // handle success
        manufacturers.value = response.data;
    })
        .catch(function (error) {
            // handle error
            console.log(error);
        })
        .finally(function () {
            // always executed
        });
}
function getProducts(){
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
function getUnits(){
    axios.get(route('api.units')).then(function (response) {
        // handle success
        listUnits.value = response.data;
    })
        .catch(function (error) {
            // handle error
            console.log(error);
        })
        .finally(function () {
            // always executed
        });
}
function getUris(){
    axios.get(route('api.uris')).then(function (response) {
        // handle success
        listUris.value = response.data;
    })
        .catch(function (error) {
            // handle error
            console.log(error);
        })
        .finally(function () {
            // always executed
        });
}

function storeUnit(){
    formUnit.uris = selectedUris.value;
    formUnit.post(route('api.units.store'), {
        replace: false,
        preserveState: false,
        preserveScroll: false,
        onSuccess: ()=> {
            formUnit.reset();
        },
    });
}
function storeUri(){
    formUri.post(route('api.uri.store'), {
        replace: false,
        preserveState: false,
        preserveScroll: false,
        onSuccess: ()=> {
            formUri.reset();
        },
    });
}
</script>

<template>
    <Head>
        <title>УТ</title>
    </Head>

    <v-container>
        <v-row>
            <v-col>

                <v-card>
                    <v-tabs
                        v-model="tab"
                        bg-color="primary"
                    >
                        <v-tab value="one">
                            Goods
                        </v-tab>
                        <v-tab value="two">
                            Manufacturers
                        </v-tab>
                        <v-tab value="three">
                            Products
                        </v-tab>
                        <v-tab value="four">
                            Units
                        </v-tab>
                        <v-tab value="five">
                            Uris
                        </v-tab>
                    </v-tabs>

                    <v-card-text>
                        <v-tabs-window v-model="tab">
                            <v-tabs-window-item value="one">
                                <v-data-table :items="goods"
                                              :headers="headersGoods"
                                              density="compact"
                                              hover="hover"
                                >
                                </v-data-table>
                            </v-tabs-window-item>

                            <v-tabs-window-item value="two">
                                <v-data-table :items="manufacturers"
                                              density="compact"
                                              hover="hover"
                                ></v-data-table>
                            </v-tabs-window-item>

                            <v-tabs-window-item value="three">
                                <v-data-table :items="listProducts"
                                              ></v-data-table>
                            </v-tabs-window-item>

                            <!--     U N I T S     -->
                            <v-tabs-window-item value="four">
                                <v-text-field v-model="searchUnits"
                                              label="Искать по Units"
                                              variant="outlined"
                                              class="mt-1 py-1 ring-0 focus:outline-none"
                                ></v-text-field>
                                <v-dialog
                                    transition="dialog-bottom-transition"
                                    width="auto"
                                >
                                    <template v-slot:activator="{ props: activatorProps }">
                                        <v-btn
                                            v-bind="activatorProps"
                                            text="Новый Unit"
                                            block
                                        ></v-btn>
                                    </template>

                                    <template v-slot:default="{ isActive }">
                                        <v-card min-width="500px">
                                            <v-toolbar title="Unit form"></v-toolbar>

                                            <v-card-text class="text-h2 pa-12">
                                                <form @submit.prevent>
                                                    <v-container>
                                                        <v-row>
                                                            <v-text-field v-model="formUnit.name"
                                                                          label="Name"
                                                                          variant="outlined"
                                                            ></v-text-field>
                                                        </v-row>
                                                        <v-row>
                                                            <v-col cols="9">
                                                                <v-autocomplete
                                                                    v-model="selectedUris"
                                                                    :items="props.uris"
                                                                    :item-value="'id'"
                                                                    :item-title="'address'"
                                                                    label="Uris selected"
                                                                    chips
                                                                    multiple
                                                                ></v-autocomplete>
                                                            </v-col>
                                                            <v-col cols="3">
                                                                <v-dialog>
                                                                    <template v-slot:activator="{ props: activatorProps }">
                                                                        <v-btn
                                                                            v-bind="activatorProps"
                                                                            text="+ uri"
                                                                            flat
                                                                        ></v-btn>
                                                                    </template>

                                                                    <template v-slot:default="{ isActive }">
                                                                        <v-card width="auto">
                                                                            <v-toolbar title="FORM: Uri"></v-toolbar>

                                                                            <v-card-text>
                                                                                <v-form @submit.prevent>
                                                                                    <v-row>
                                                                                        <v-text-field v-model="formUri.address"
                                                                                                      label="Uri address"
                                                                                                      variant="outlined"
                                                                                        ></v-text-field>
                                                                                    </v-row>
                                                                                    <v-row>
                                                                                        <v-col cols="4">
                                                                                            <v-btn
                                                                                                text="store"
                                                                                                block
                                                                                                @click="storeUri"
                                                                                            ></v-btn>
                                                                                        </v-col>
                                                                                    </v-row>
                                                                                </v-form>
                                                                            </v-card-text>
                                                                        </v-card>
                                                                    </template>
                                                                </v-dialog>
                                                            </v-col>
                                                        </v-row>
                                                    </v-container>
                                                </form>
                                            </v-card-text>

                                            <v-card-actions class="justify-end">
                                                <v-btn
                                                    text="Close"
                                                    @click="isActive.value = false"
                                                ></v-btn>
                                                <v-btn text="Сохранить"
                                                       @click="storeUnit"
                                                ></v-btn>
                                            </v-card-actions>
                                        </v-card>
                                    </template>
                                </v-dialog>

                                <v-data-table :items="listUnits"
                                              :search="searchUnits"
                                              density="compact"
                                              hover="hover"
                                ></v-data-table>
                            </v-tabs-window-item>
                            <!--     E N D  U N I T S     -->

                            <v-tabs-window-item value="five">
                                <v-data-table :items="listUris"
                                              density="compact"
                                              hover="hover"
                                ></v-data-table>
                            </v-tabs-window-item>

                        </v-tabs-window>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col></v-col>
            <v-col></v-col>
        </v-row>
    </v-container>
</template>
