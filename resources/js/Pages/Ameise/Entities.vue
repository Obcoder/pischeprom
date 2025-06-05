<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {onMounted, ref} from "vue";
import axios from "axios";
import {useForm} from "@inertiajs/vue3";
import {useDate} from "vuetify";
import {useHead} from "@vueuse/head";
import {route} from "ziggy-js";
defineOptions({
    layout: VerwalterLayout,
})
const date = useDate()

const buildings = ref([])
const cities = ref([])
const entities = ref([])
const telephones = ref()

let searchEntitiesLike = ref()
let searchTelephones = ref()

const indexBuildings = async ()=>{
    try {
        const response = await axios.get(route('buildings.index'))
        buildings.value = response.data
    } catch (error){
        console.log(error)
    }
}
const indexCities = async () => {
    try {
        const response = await axios.get(route('cities.index'))
        cities.value = response.data;
    } catch (error) {
        console.log(error)
    }
}
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

let showFormEntity = ref(false);

const formEntity = useForm({
    name: null,
    entity_classification_id: null,
    buildings: null,
    telephones: null,
    cities: null,
})


let listEntityClassifications = ref();
function apiIndexEntityClassifications(){
    axios.get(route('api.entitiesclassifications')).then(function (response){
        listEntityClassifications.value = response.data;
    }).catch(function (error){
        console.log(error);
    })
}

let showFormTelephone = ref(false);
const formTelephone = useForm({
    number: null,
})

function storeEntity(){
    formEntity.post(route('web.entity.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: true,
        onSuccess: ()=> {
            formEntity.reset()
            indexEntities()
        },
    })
}
function storeTelephone(){
    formTelephone.post(route('telephones.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: true,
        onSuccess: ()=> {
            formTelephone.reset();
            indexTelephones(searchTelephones.value)
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
    indexBuildings()
    indexCities()
    indexEntities();
    apiIndexEntityClassifications();
    indexTelephones();
})

useHead({
    title: `Entities: ${entities.value.length}`,
    meta: [
        {
            name: 'description',
            content: `Информация о блоке Entities`,
        }
    ]
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
                                        <v-text-field v-model="formEntity.name"
                                                      label="Name"
                                                      variant="outlined"
                                        ></v-text-field>
                                    </v-row>
                                    <v-row>
                                        <v-col>
                                            <v-select :items="listEntityClassifications"
                                                      :item-value="'id'"
                                                      :item-title="'name'"
                                                      v-model="formEntity.entity_classification_id"
                                                      variant="outlined"
                                                      label="Вид"
                                            ></v-select>
                                        </v-col>
                                        <v-col>
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
                                        </v-col>
                                    </v-row>
                                    <v-row>
                                        <v-col>
                                            <v-autocomplete :items="cities"
                                                            :item-value="'id'"
                                                            :item-title="'name'"
                                                            v-model="formEntity.cities"
                                                            label="Cities"
                                                            placeholder="Населенный пункт"
                                                            variant="solo"
                                                            density="comfortable"
                                                            color="grey"
                                                            multiple
                                            ></v-autocomplete>
                                        </v-col>
                                        <v-col>
                                            <v-autocomplete :items="buildings"
                                                            :item-value="'id'"
                                                            :item-title="'address'"
                                                            v-model="formEntity.buildings"
                                                            multiple
                                                            label="Buildings"
                                                            placeholder="Адрес здания"
                                                            variant="outlined"
                                                            density="comfortable"
                                                            color="teal"
                                            ></v-autocomplete>
                                        </v-col>
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
            <v-col lg="5">
                <v-list lines="one"
                >
                    <v-list-item v-for="entity in entities"
                                 class="hover:teal-lighten-3"
                                 base-color="teal-darken-4"
                                 border="border"
                                 color="teal-lighten-5"
                                 density="comfortable"
                                 elevation="1"
                                 link
                                 ripple
                                 slim
                                 rounded
                    >
                        <v-list-item-title>
                            {{entity.name}}
                        </v-list-item-title>
                        <v-list-item-subtitle>{{entity.classification.name}}</v-list-item-subtitle>
                        <v-divider opacity="0.75"
                                   thickness="1"
                                   color="teal-darken-3"></v-divider>
                        <v-row>
                            <v-col>
                                <div v-for="city in entity.cities">
                                    <span class="text-xs font-serif text-slate-800">{{city.name}}</span>
                                </div>
                            </v-col>
                            <v-col>
                                <div v-for="building in entity.buildings"
                                     class="text-[8px]"
                                >
                                    {{building.address}}
                                </div>
                            </v-col>
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
                                      items-per-page="25"
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
            <v-col></v-col>
        </v-row>
    </v-container>
</template>
