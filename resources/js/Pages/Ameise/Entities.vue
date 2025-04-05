<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {onMounted, ref} from "vue";
import axios from "axios";
import {useForm} from "@inertiajs/vue3";
import {useDate} from "vuetify";
defineOptions({
    layout: VerwalterLayout,
})
const date = useDate()

let entities = ref();
let searchEntitiesLike = ref();
function indexEntities(like){
    axios.get(route('entities.index'), {
        params: {
            search: like,
        }
    }).then(function (response){
        entities.value = response.data;
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
    formEntity.post(route('entities.store'), {
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
function indexTelephones(like){
    axios.get(route('telephones.index'), {
        params: {
            search: like,
        }
    }).then(function (response) {
        // handle success
        telephones.value = response.data;
    })
        .catch(function (error) {
            // handle error
            console.log(error);
        });
}
let searchTelephones = ref();
let showFormTelephone = ref(false);
const formTelephone = useForm({
    number: null,
})
function storeTelephone(){
    formTelephone.post(route('telephones.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: true,
        onSuccess: ()=> {
            formTelephone.reset();
            indexTelephones();
        },
    })
}
const headersTelephones = [
    {
        title: 'Number',
        key: 'number',
    },
    {
        title: 'Создан',
        key: 'created_at'
    },
]

onMounted(()=>{
    indexEntities();
    apiIndexEntityClassifications();
    indexTelephones();
})
</script>

<template>
    <v-container>
        <v-row>
            <v-col>
                <v-text-field v-model="searchEntitiesLike"
                              @input="indexEntities(searchEntitiesLike)"
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
            <v-col cols="3">
                <v-row>
                    <v-col cols="10">
                        <v-text-field v-model="searchTelephones"
                                      @input="indexTelephones(searchTelephones)"
                                      density="comfortable"
                                      label="search Telephones"
                                      variant="solo"
                        ></v-text-field>
                    </v-col>
                    <v-col cols="2">
                        <v-btn @click="showFormTelephone = !showFormTelephone"
                               text="New telephone"
                               variant="elevated"
                               color="purple"
                        ></v-btn>
                        <v-dialog v-model="showFormTelephone"
                                  width="600"
                        >
                            <template v-slot:default="{ isActive }">
                                <v-card>
                                    <v-card-title>Form Telephone</v-card-title>
                                    <v-card-text>
                                        <v-form @submit.prevent>
                                            <v-row>
                                                <v-col>
                                                    <v-text-field v-model="formTelephone.number"
                                                                  label="Number"
                                                                  placeholder="+...."
                                                                  variant="outlined"
                                                                  color="red"
                                                    ></v-text-field>
                                                </v-col>
                                            </v-row>
                                        </v-form>
                                    </v-card-text>
                                    <v-card-actions>
                                        <v-divider vertical
                                                   thickness="1"
                                                   opacity="90"
                                        ></v-divider>

                                        <v-btn @click="storeTelephone"
                                               text="save"
                                               variant="elevated"
                                               color="success"
                                        ></v-btn>
                                    </v-card-actions>
                                </v-card>
                            </template>
                        </v-dialog>
                    </v-col>
                </v-row>
                <v-row>
                    <v-col cols="12">
                        <v-data-table :items="telephones"
                                      :headers="headersTelephones"
                                      density="compact"
                                      hover="true"
                        >
                            <template v-slot:item.created_at="{item}">
                                <span>{{date.format(item.created_at, 'fullDate')}}</span>
                            </template>
                        </v-data-table>
                    </v-col>
                </v-row>
            </v-col>
        </v-row>
    </v-container>
</template>
