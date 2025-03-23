<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {onMounted, ref} from "vue";
import axios from "axios";
import {useForm} from "@inertiajs/vue3";
defineOptions({
    layout: VerwalterLayout,
})

let commodities = ref();
function indexCommodities(like){
    axios.get(route('api.commodities'), {
        params: {
            search: like,
        }
    }).then(function (response){
        commodities.value = response.data;
    }).catch(function (error){
        console.log(error)
    })
}
let searchCommodities = ref();

let showFormCommodity = ref(false);
const formCommodity = useForm({
    name: null,
})
function storeCommodity(){
    formCommodity.post(route('api.commodity.store'), {
        replace: false,
        preserveState: false,
        preserveScroll: true,
        onSuccess: ()=> {
            formCommodity.reset()
            indexCommodities();
        },
    })
}

onMounted(()=>{
    indexCommodities()
})
</script>

<template>
    <v-container>
        <v-row>
            <v-col>
                <v-row>
                    <v-col cols="9">
                        <v-text-field v-model="searchCommodities"
                                      @input="indexCommodities(searchCommodities)"
                                      label="Commodities"
                                      placeholder="search"
                                      variant="outlined"
                                      density="compact"
                        ></v-text-field>
                    </v-col>
                    <v-col cols="3">
                        <v-btn text="+ commodity"
                               @click="showFormCommodity = !showFormCommodity"
                               variant="elevated"
                               color="grey"
                        ></v-btn>
                        <v-dialog v-model="showFormCommodity"
                                  width="800"
                        >
                            <template v-slot:default="{isActive}">
                                <v-card>
                                    <v-card-title>Form Commodity</v-card-title>
                                    <v-card-text>
                                        <v-form @submit.prevent>
                                            <v-row>
                                                <v-col>
                                                    <v-text-field v-model="formCommodity.name"
                                                                  label="Name"
                                                                  variant="outlined"
                                                    ></v-text-field>
                                                </v-col>
                                            </v-row>
                                        </v-form>
                                    </v-card-text>
                                    <v-card-actions>
                                        <v-btn text="store"
                                               @click="storeCommodity"
                                               variant="tonal"
                                               color="orange"
                                        ></v-btn>
                                    </v-card-actions>
                                </v-card>
                            </template>
                        </v-dialog>
                    </v-col>
                </v-row>
                <v-row>
                    <v-col>
                        <v-list>
                            <v-list-item v-for="commodity in commodities">
                                {{commodity.name}}
                            </v-list-item>
                        </v-list>
                    </v-col>
                    <v-col></v-col>
                    <v-col></v-col>
                </v-row>
            </v-col>
        </v-row>
    </v-container>
</template>
