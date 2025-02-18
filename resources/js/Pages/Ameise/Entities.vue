<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {onMounted, ref} from "vue";
import axios from "axios";
import {useForm} from "@inertiajs/vue3";
defineOptions({
    layout: VerwalterLayout,
})

let listEntities = ref();
function apiIndexEntities(){
    axios.get(route('api.entities')).then(function (response){
        listEntities.value = response.data;
    }).catch(function (error){
        console.log(error);
    })
}
let showFormEntity = ref(false);
let formEntity = useForm({
    name: null,
    entity_classification_id: null,
})
function storeEntity(){
    formEntity.post(route('api.entity.store'), {
        replace: false,
        preserveState: false,
        preserveScroll: false,
        onSuccess: ()=> {
            formEntity.reset();
            apiIndexEntities();
        },
    })
}

let listEntityClassifications = ref();
function apiIndexEntityClassifications(){
    axios.get(route()).then(function (response){
        listEntityClassifications.value = response.data;
    }).catch(function (error){
        console.log(error);
    })
}

onMounted(()=>{
    apiIndexEntities();
    apiIndexEntityClassifications();
})
</script>

<template>
    <v-container>
        <v-toolbar border
                   density="compact"
                   title="Entities"
        >
            <v-toolbar-items>
                <v-btn text="+"
                       @click="showFormEntity = true"
                       variant="elevated"
                       color="green"
                ></v-btn>
                <v-dialog width="990"
                          transition="dialog-top-transition"
                >
                    <template v-slot:default="{ isActive }">
                        <v-card>
                            <v-card-title>Form Entity</v-card-title>
                            <v-card-text>
                                <v-form @submit.prevent>
                                    <v-row>
                                        <v-text-field v-model="formEntity.name"
                                                      label="Name"
                                                      variant="outlined"
                                        ></v-text-field>
                                    </v-row>
                                    <v-row>
                                        <v-select :items="listEntityClassifications"
                                                  :item-value="'id'"
                                                  :item-title="'name'"
                                                  v-model="formEntity.entity_classification_id"
                                                  variant="outlined"
                                                  label="Вид"
                                        ></v-select>
                                    </v-row>
                                </v-form>
                            </v-card-text>
                            <v-card-actions>
                                <v-btn @click="storeEntity"
                                       text="сохранить"
                                       variant="flat"
                                ></v-btn>
                            </v-card-actions>
                        </v-card>
                    </template>
                </v-dialog>
            </v-toolbar-items>
        </v-toolbar>
        <v-row>
            <v-col>
                <v-list>
                    <v-list-item v-for="entity in listEntities">
                        {{entity.name}}
                    </v-list-item>
                </v-list>
            </v-col>
            <v-col></v-col>
        </v-row>
    </v-container>
</template>
