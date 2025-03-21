<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {onMounted, ref} from "vue";
import axios from "axios";
import {useForm} from "@inertiajs/vue3";
defineOptions({
    layout: VerwalterLayout,
})

let listEntities = ref();
let searchEntitiesLike = ref();
function apiIndexEntities(like){
    axios.get(route('api.entities'), {
        params: {
            search: like,
        }
    }).then(function (response){
        listEntities.value = response.data;
    }).catch(function (error){
        console.log(error);
    })
}
let showFormEntity = ref(false);
let formEntity = useForm({
    name: null,
    entity_classification_id: null,
    telephones: null,
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
    axios.get(route('api.entitiesclassifications')).then(function (response){
        listEntityClassifications.value = response.data;
    }).catch(function (error){
        console.log(error);
    })
}

let telephones = ref();
function apiIndexTelephones(){
    axios.get(route('api.telephones')).then(function (response){
        telephones.value = response.data;
    }).catch(function (error){
        console.log(error);
    })
}

onMounted(()=>{
    apiIndexEntities();
    apiIndexEntityClassifications();
    apiIndexTelephones();
})
</script>

<template>
    <v-container>
        <v-row>
            <v-col>
                <v-text-field v-model="searchEntitiesLike"
                              @input="apiIndexEntities(searchEntitiesLike)"
                              label="Search"
                              variant="solo"
                              density="compact"
                ></v-text-field>
            </v-col>
            <v-col></v-col>
            <v-col>
                <v-btn text="+"
                       @click="showFormEntity = true"
                       variant="elevated"
                       color="purple-darken-4"
                ></v-btn>
                <v-dialog v-model="showFormEntity"
                          width="990"
                          transition="dialog-top-transition"
                >
                    <template v-slot:default="{ isActive }">
                        <v-card>
                            <v-card-title>Form Entity</v-card-title>
                            <v-card-text>
                                <v-form @submit.prevent>
                                    <v-row>
                                        <v-select :items="listEntityClassifications"
                                                  :item-value="'id'"
                                                  :item-title="'name'"
                                                  v-model="formEntity.entity_classification_id"
                                                  variant="outlined"
                                                  label="Вид"
                                        ></v-select>
                                    </v-row>
                                    <v-row>
                                        <v-text-field v-model="formEntity.name"
                                                      label="Name"
                                                      variant="outlined"
                                        ></v-text-field>
                                    </v-row>
                                    <v-row>
                                        <v-autocomplete :items="telephones"
                                                        :item-value="'id'"
                                                        :item-title="'number'"
                                                        v-model="formEntity.telephones"
                                                        label="Telephones"
                                                        placeholder="number without +7"
                                                        variant="outlined"
                                                        density="comfortable"
                                                        color="purple-darken-4"
                                                        multiple
                                                        chips
                                        ></v-autocomplete>
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
            </v-col>
        </v-row>
        <v-row>
            <v-col>
                <v-list lines="one"
                >
                    <v-list-item v-for="entity in listEntities"
                                 class="hover:bg-blue-200"
                    >
                        <v-list-item-title>
                            {{entity.name}}
                        </v-list-item-title>
                        <v-list-item-subtitle>{{entity.classification.name}}</v-list-item-subtitle>
                        <v-row>
                            <v-col>
                                <div v-for="telephone in entity.telephones">{{telephone.number}}</div>
                            </v-col>
                            <v-col>
                                <v-row v-for="chat in entity.chats">
                                    <v-col>
                                        {{chat.numbers}}
                                    </v-col>
                                    <v-col>
                                        {{chat.first_name}}
                                    </v-col>
                                </v-row>
                            </v-col>
                            <v-col>
                                <v-row v-for="unit in entity.units">
                                    <v-col>
                                        {{unit.name}}
                                    </v-col>
                                </v-row>
                            </v-col>
                        </v-row>
                    </v-list-item>
                </v-list>
            </v-col>
            <v-col></v-col>
        </v-row>
    </v-container>
</template>
