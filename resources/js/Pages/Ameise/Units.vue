<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {onMounted, ref} from "vue";
import {Link, useForm} from "@inertiajs/vue3";
import axios from "axios";
defineOptions({
    layout: VerwalterLayout,
})

let searchUnitsLike = ref('');
let limitUnits = ref(135);
let listUnits = ref();
let listUris = ref();
function apiIndexUnits(like, limit){
    axios.get(route('api.units'), {
        params: {
            search: like,
            limit: limit,
        }
    }).then(function (response){
        listUnits.value = response.data;
    }).catch(function (error){
        console.log();
    });
}
function apiIndexUris(){
    axios.get(route('api.uris'), {
        params: {

        }
    }).then(function (response){
        listUris.value = response.data;
    }).catch(function (error){
        console.log(error);
    });
}
let showFormUri = ref(false);
const formUri = useForm({
    address: null,
})
function storeUri(){
    formUri.post(route('api.uri.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: false,
        onSuccess: ()=> {
            formUri.reset();
            apiIndexUris();
        },
    });
}
let listLabels = ref();
function apiIndexLabels(){
    axios.get(route('api.labels')).then(function (response) {
        listLabels.value = response.data;
    })
        .catch(function (error) {
            console.log(error);
        });
}
let listBuildings = ref();
function apiIndexBuildings(){
    axios.get(route('api.buildings')).then(function (response) {
        listBuildings.value = response.data;
    })
        .catch(function (error) {
            console.log(error);
        });
}
// Функция для корректного отображения "Город - Адрес"
const formatBuildingTitle = (building) => {
    if (!building) return '';
    return `${building.city?.name || ' - '} , ${building.address}`;
};

const formUnit = useForm({
    name: null,
    uris: null,
    labels: null,
    buildings: null,
});
function storeUnit(){
    formUnit.post(route('api.units.store'), {
        replace: false,
        preserveState: false,
        preserveScroll: false,
        onSuccess: ()=> {
            formUnit.reset();
        },
    });
}

onMounted(()=>{
    apiIndexUnits('', limitUnits.value);
    apiIndexUris();
    apiIndexLabels();
    apiIndexBuildings();
})
</script>

<template>
    <v-container>
        <v-row>
            <v-col cols="7">
                <v-text-field v-model="searchUnitsLike"
                              @input="apiIndexUnits(searchUnitsLike, limitUnits)"
                              label="Search"
                              variant="outlined"
                              density="compact"
                ></v-text-field>
            </v-col>
            <v-col cols="3">
                <v-label>Limit</v-label>
                <input type="number"
                       v-model="limitUnits"
                       @change="apiIndexUnits(searchUnitsLike, limitUnits)"
                >
            </v-col>
            <v-col cols="2">
                <v-dialog
                    transition="dialog-top-transition"
                    width="990"
                >
                    <template v-slot:activator="{ props: activatorProps }">
                        <v-btn
                            v-bind="activatorProps"
                            text="Новый Unit"
                            block
                            variant="elevated"
                            color="teal-lighten-3"
                        ></v-btn>
                    </template>
                    <template v-slot:default="{ isActive }">
                        <v-card>
                            <v-card-title>Form Unit</v-card-title>

                            <v-card-text class="text-h2 pa-12">
                                <v-form @submit.prevent>
                                    <v-container>
                                        <v-row>
                                            <v-text-field v-model="formUnit.name"
                                                          label="Name"
                                                          variant="outlined"
                                            ></v-text-field>
                                        </v-row>
                                        <v-row>
                                            <v-col cols="9">
                                                <v-autocomplete v-model="formUnit.uris"
                                                                :items="listUris"
                                                                :item-value="'id'"
                                                                :item-title="'address'"
                                                                label="Uris selected"
                                                                chips
                                                                multiple
                                                ></v-autocomplete>
                                            </v-col>
                                            <v-col cols="3">
                                                <v-btn class="my-2"
                                                       text="+ uri"
                                                       @click="showFormUri = true"
                                                ></v-btn>

                                                <v-dialog v-model="showFormUri"
                                                          width="501"
                                                >
                                                    <v-card>
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
                                                </v-dialog>
                                            </v-col>
                                        </v-row>
                                        <v-row>
                                            <v-col cols="4">
                                                <v-select v-model="formUnit.labels"
                                                          :items="listLabels"
                                                          :item-value="'id'"
                                                          :item-title="'name'"
                                                          label="Labels"
                                                          multiple
                                                ></v-select>
                                            </v-col>
                                        </v-row>
                                        <v-row>
                                            <v-col>
                                                <v-autocomplete v-model="formUnit.buildings"
                                                                :items="listBuildings"
                                                                :item-title="formatBuildingTitle"
                                                                :item-value="'id'"
                                                                label="Buildings"
                                                                color="blue"
                                                                multiple
                                                                chips
                                                ></v-autocomplete>
                                            </v-col>
                                        </v-row>
                                    </v-container>
                                </v-form>
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
            </v-col>
        </v-row>

        <v-row>
            <v-col>
                <v-list>
                    <v-list-item v-for="unit in listUnits"
                                 class="rounded border border-emerald-900"
                    >
                        <v-card>
                            <v-card-title>
                                <Link :href="route('unit.show', unit.id)">
                                    <span class="font-FIFARussia2018 font-[11px]"
                                    >
                                    {{unit.name}}</span>
                                </Link>
                            </v-card-title>
                            <v-card-text>
                                <v-chip v-if="unit.entities.length > 0"
                                >{{unit.entities.length}}</v-chip>
                            </v-card-text>
                        </v-card>
                    </v-list-item>
                </v-list>
            </v-col>
            <v-col></v-col>
            <v-col></v-col>
        </v-row>
    </v-container>
</template>
