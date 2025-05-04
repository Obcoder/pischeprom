<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {computed, onMounted, ref} from "vue";
import axios from "axios";
import {useHead} from "@vueuse/head";
defineOptions({
    layout: VerwalterLayout,
})

const genera = ref([])
const plants = ref([])

const searchText = ref('')

const showOnlyAgriculturable = ref(false)

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

const filteredGenera = computed(() => {
    let list = genera.value

    if (showOnlyAgriculturable.value) {
        list = list.filter(g => g.agriculturable)
    }

    if (searchText.value.trim()) {
        const term = searchText.value.toLowerCase()
        list = list.filter(g => g.name.toLowerCase().includes(term))
    }

    return list
})

onMounted(()=>{
    indexGenera()
    indexPlants()
})

useHead({
    title: `Ботаника`,
    meta: [
        {
            name: 'description',
            content: `Материалы для изучения Ботаники и растений`,
        }
    ]
})
</script>

<template>
    <v-container fluid>
        <v-row>
            <v-col cols="4">
                <v-text-field v-model="searchText"
                              variant="underlined"
                              density="compact"
                ></v-text-field>
            </v-col>
            <v-col cols="2">
                <v-switch v-model="showOnlyAgriculturable"
                          label="Показать только пригодные для сельского хозяйства"
                          color="green"
                          inset
                          density="compact"
                />
            </v-col>
        </v-row>
        <v-row>
            <v-col cols="2">
                <v-list>
                    <v-list-item
                        v-for="genus in filteredGenera"
                        :key="genus.id"
                        class="text-sm d-flex flex-row flex-wrap justify-space-between"
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
                        <div class="d-flex align-center inline-block justify-start">
                            <span v-if="genus.agriculturable" class="mr-2">🌱</span>
                            <span>{{ genus.name }}</span>
                        </div>
                        <a :href="genus.wiki" target="_blank"
                           class="p-1 text-sm font-mono border rounded ml-4 inline-block"
                           @click.stop
                        >
                            w
                        </a>
                    </v-list-item>
                </v-list>
            </v-col>
            <v-col cols="1"></v-col>
            <v-col cols="4">
                <v-list>
                    <v-list-item v-for="plant in plants"
                                 variant="plain"
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
