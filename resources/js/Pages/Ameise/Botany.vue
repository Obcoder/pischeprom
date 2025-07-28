<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {computed, onMounted, ref} from "vue";
import axios from "axios";
import {useHead} from "@vueuse/head";
import {useForm} from "@inertiajs/vue3";
import {route} from "ziggy-js";
defineOptions({
    layout: VerwalterLayout,
})

const genera = ref([])
const plants = ref([])

const showOnlyAgriculturable = ref(false)

//      G E N E R A
function indexGenera(like){
    axios.get(route('genera.index'), {
        params: {
            search: like,
        }
    }).then(function (response){
        genera.value = response.data
    }).catch(function (error){
        console.log(error);
    })
}
const searchGenera = ref('')
const filteredGenera = computed(() => {
    let list = genera.value

    if (showOnlyAgriculturable.value) {
        list = list.filter(g => g.agriculturable)
    }

    if (searchGenera.value.trim()) {
        const term = searchGenera.value.toLowerCase()
        list = list.filter(g => g.name.toLowerCase().includes(term))
    }

    return list
})
const headerGenera = [
    {
        title: 'agriculturable',
        key: 'agriculturable',
        align: 'center',
    },
    {
        title: 'name',
        key: 'name',
        align: 'start',
    },
    {
        title: 'nameLat',
        key: 'nameLat',
        align: 'start',
    },
    {
        title: 'Wiki',
        key: 'wiki',
        align: 'center',
    },
]
const dialogFormGenus = ref(false)
const formGenus = useForm({
    name: null,
    nameLat: null,
    wiki: null,
})
function storeGenus(){
    formGenus.post(route('web.genus.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: true,
        onSuccess: ()=> {
            formGenus.reset()
            indexGenera()
        },
    })
}
//      P L A N T S
function indexPlants(like){
    axios.get(route('plants.index'), {
        params: {
            search: like,
        }
    }).then(function (response){
        plants.value = response.data
    }).catch(function (error){
        console.log(error);
    })
}

function toggleAgriculturable(genus) {
    axios.patch(route('genera.toggleAgriculturable', genus.id))
        .then(response => {
            const updated = response.data
            // обновляем конкретный genus в списке
            const index = genera.value.findIndex(g => g.id === updated.id)
            if (index !== -1) {
                genera.value[index] = updated
            }
        })
        .catch(error => {
            console.log(error)
        })
}

onMounted(()=>{
    indexGenera()
    indexPlants()
})

useHead({
    title: `Ботаника`,
    meta: [
        {
            name: 'description',
            content: `Материалы для изучения Ботаники и растений, физиология растений,`,
        }
    ]
})
</script>

<template>
    <v-container fluid>
        <v-row>
            <v-col lg="3">
                <v-text-field v-model="searchGenera"
                              label="Поиск"
                              variant="underlined"
                              density="comfortable"
                              class="rounded"
                              hide-details
                ></v-text-field>
            </v-col>
            <v-col lg="1"></v-col>
            <v-col cols="2">
                <v-switch v-model="showOnlyAgriculturable"
                          label="Показать только пригодные для сельского хозяйства"
                          color="green"
                          inset
                          density="compact"
                />
            </v-col>
            <v-col cols="1">
                <v-btn text="+genus"
                       @click="dialogFormGenus = !dialogFormGenus"
                       variant="elevated"
                       size="small"
                       color="deep-purple"></v-btn>
                <v-dialog v-model="dialogFormGenus"
                          width="600"
                          transition="dialog-left-transition"
                          class="border-3"
                >
                    <template v-slot:default="{isActive}">
                        <v-card>
                            <v-card-title>Form Genus</v-card-title>
                            <v-card-text>
                                <v-form @submit.prevent>
                                    <v-row>
                                        <v-col>
                                            <v-text-field v-model="formGenus.name"
                                                          label="Name"
                                                          variant="outlined"
                                                          density="comfortable"></v-text-field>
                                        </v-col>
                                        <v-col>
                                            <v-text-field v-model="formGenus.nameLat"
                                                          label="Latin"
                                                          variant="outlined"
                                                          density="comfortable"></v-text-field>
                                        </v-col>
                                    </v-row>
                                    <v-row>
                                        <v-col>
                                            <v-text-field v-model="formGenus.wiki"
                                                          label="WIKI"
                                                          variant="solo"
                                                          density="comfortable"></v-text-field>
                                        </v-col>
                                    </v-row>
                                </v-form>
                            </v-card-text>
                            <v-card-actions>
                                <v-divider vertical
                                           opacity="0.91"
                                ></v-divider>
                                <v-btn text="store"
                                       @click="storeGenus"
                                       variant="elevated"
                                       density="comfortable"
                                       color="orange"></v-btn>
                            </v-card-actions>
                        </v-card>
                    </template>
                </v-dialog>
            </v-col>
        </v-row>
        <v-row>
            <v-col cols="3">
                <v-data-table :items="filteredGenera"
                              items-per-page="203"
                              :headers="headerGenera"
                              fixed-header
                              height="906px"
                              fixed-footer
                              density="compact"
                              hover
                >
                    <template v-slot:item.agriculturable="{item}">
                        <span v-if="item.agriculturable"
                              class="mr-1">🌱</span>
                    </template>
                    <template v-slot:item.nameLat="{item}">
                        <span class="text-xs">{{item.nameLat}}</span>
                    </template>
                    <template v-slot:item.wiki="{item}">
                        <a :href="item.wiki" target="_blank"
                           class="text-xs px-2 py-1 border"
                        >wiki</a>
                    </template>
                </v-data-table>

                <v-list>
                    <v-list-item v-for="genus in filteredGenera"
                                 :key="genus.id"
                                 class="text-sm"
                                 density="compact"
                                 variant="elevated"
                                 :class="{
                                    'bg-light-green-lighten-4': genus.agriculturable,
                                    'bg-grey-lighten-4': !genus.agriculturable,
                                    'border': true,
                                    'border-green': genus.agriculturable,
                                    'border-grey': !genus.agriculturable
                                 }"
                                 @click="toggleAgriculturable(genus)"
                    >
                        <div class="flex flex-row">
                            <span v-if="genus.agriculturable"
                                  class="mr-1">🌱</span>
                            <span>{{ genus.name }}</span>
                            <a :href="genus.wiki" target="_blank"
                               @click.stop
                               class="grow text-sm font-RubikMedium border rounded ml-2 elevation-2 text-center"
                            >
                                wiki
                            </a>
                        </div>
                    </v-list-item>
                </v-list>
            </v-col>
            <v-col cols="1"></v-col>
            <v-col cols="4">
                <v-list>
                    <v-list-item v-for="plant in plants"
                                 variant="text"
                                 density="compact"
                                 color="light-green-lighten-5"
                                 class="text-xs"
                    >
                        <span>{{plant.name}}</span>
                    </v-list-item>
                </v-list>
            </v-col>
            <v-col></v-col>
        </v-row>
    </v-container>
</template>

<style scoped>
.bg-light-green-lighten-4 {
    background-color: #dcedc8 !important;
}
.bg-grey-lighten-4 {
    background-color: #f5f5f5 !important;
}
.border {
    border: 1px solid transparent;
    border-radius: 8px;
}
.border-green {
    border-color: #81c784 !important;
}
.border-grey {
    border-color: #e0e0e0 !important;
}
</style>
