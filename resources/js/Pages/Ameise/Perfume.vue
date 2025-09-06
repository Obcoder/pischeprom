<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {onMounted, ref} from "vue";
import axios from "axios";
import {route} from "ziggy-js";
import {useForm} from "@inertiajs/vue3";
defineOptions({
    layout: VerwalterLayout,
})

const brands = ref([])
const fragrances = ref([])
const notes = ref([])

//     B R A N D S
function indexBrands(){
    axios.get(route('brands.index')).then(function (response){
        brands.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}
//     F R A G R A N C E S
function indexFragrances(){
    axios.get(route('fragrances.index')).then(function (response){
        fragrances.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}
const headerFragrances = ref([
    {
        key: 'brand.name',
        title: 'Brand',
        align: 'start',
        sortable: true,
    },
    {
        key: 'name',
        title: 'name',
        align: 'start',
        sortable: true,
    },
])
const dialogFormFragrance = ref(false)
const menu = ref(false)
const formFragrance = useForm({
    brand_id: null,
    name: null,
})
const submitForm = () => {
    formFragrance.post(route('web.fragrance.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            menu.value = false   // Закрываем меню только после успеха
            formFragrance.reset() // Сброс формы
            indexFragrances()
        }
    })
}
// E N D  F R A G R A N C E S



//      N O T E S
function indexNotes(){
    axios.get(route('notes.index')).then(function (response){
        notes.value = response.data
    }).catch(function (error){
        console.error(error)
    })
}
const headerNotes = ref([
    {
        key: 'name',
        title: 'Name',
        align: 'start',
        sortable: true,
    },
])

onMounted(()=>{
    indexBrands()
    indexFragrances()
    indexNotes()
})
</script>

<template>
    <v-container fluid>
        <v-row>
            <v-toolbar density="compact">
                <v-toolbar-items>
                    <!-- Триггер меню -->
                    <v-menu v-model="menu"
                            :close-on-content-click="false"
                            transition="scale-transition"
                            offset-y
                    >
                        <template #activator="{ props }">
                            <v-btn
                                v-bind="props"
                                text
                                variant="text"
                            >
                                +F
                            </v-btn>
                        </template>

                        <!-- Содержимое меню (форма) -->
                        <v-card class="pa-4" min-width="500">
                            <v-form @submit.prevent="submitForm">
                                <v-container fluid>
                                    <v-row>
                                        <v-autocomplete :items="brands"
                                                        :item-value="'id'"
                                                        :item-title="'name'"
                                                        v-model="formFragrance.brand_id"
                                                        label="Выбрать brand"
                                                        placeholder="Брэнды"
                                                        variant="solo"
                                                        density="comfortable"
                                                        hide-details
                                                        chips
                                        ></v-autocomplete>
                                    </v-row>
                                    <v-row>
                                        <v-text-field v-model="formFragrance.name"
                                                      label="Название"
                                                      variant="solo"
                                                      dense
                                                      required
                                                      :error-messages="formFragrance.errors.name"
                                                      hide-details
                                        />
                                    </v-row>
                                </v-container>
                                <v-card-actions class="d-flex justify-end">
                                    <v-btn text @click="menu = false">Отмена</v-btn>
                                    <v-btn text="сохранить"
                                           variant="elevated"
                                           density="comfortable"
                                           color="light-blue"
                                           type="submit"
                                           :loading="formFragrance.processing"
                                    ></v-btn>
                                </v-card-actions>
                            </v-form>
                        </v-card>
                    </v-menu>
                </v-toolbar-items>
            </v-toolbar>
        </v-row>
        <v-row>
            <v-col lg="2">
                <v-data-table :items="notes"
                              items-per-page="100"
                              :headers="headerNotes"
                              fixed-header
                              height="600px"
                              density="compact"
                              class="border rounded text-xs"
                              hover
                ></v-data-table>
            </v-col>
            <v-col cols="3">
                <v-data-table :items="fragrances"
                              items-per-page="100"
                              :headers="headerFragrances"
                              fixed-header
                              height="600px"
                              density="compact"
                              class="border rounded"
                              hover
                ></v-data-table>
            </v-col>
        </v-row>
    </v-container>
</template>

<style scoped>

</style>
