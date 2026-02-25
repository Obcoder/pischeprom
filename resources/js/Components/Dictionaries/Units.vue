<script setup>
import {ref, watch, computed, onMounted} from 'vue'
import axios from 'axios'
import { route } from 'ziggy-js'
import {useForm, Link} from "@inertiajs/vue3";

const fields = ref([])
const selectedFieldIDs = ref([]) // массив выбранных field.id
const units = ref([])
const searchUnits = ref('')
const labels = ref([])
const selectedLabelsIDs = ref([])


const showFormUnit = ref(false)

// Функция загрузки Units
const indexUnits = async () => {
    try {
        const { data } = await axios.get(route('units.index'), {
            params: {
                search: searchUnits.value, // Добавьте search в params, если бэкенд поддерживает
                limit: null, // Для всех записей
            }
        })
        units.value = Array.isArray(data) ? data : (data.data || [])
    } catch (e) {
        console.error(e)
        units.value = []
    }
}
// Filtered Units (осталось без изменений)
const filteredUnits = computed(() => {
    let filtered = units.value

    // Фильтрация по поиску
    if (searchUnits.value) {
        const search = searchUnits.value.toLowerCase()
        filtered = filtered.filter(item => item.name.toLowerCase().includes(search))
    }

    // Фильтрация по labels (предполагаем selectedLabelsIDs существует)
    if (selectedLabelsIDs.value.length > 0) {
        filtered = filtered.filter(unit => {
            const unitLabelIds = unit.labels?.map(label => label.id) || []
            return selectedLabelsIDs.value.some(labelId => unitLabelIds.includes(labelId))
        })
    }

    // ✅ Fields (аналогично labels)
    if (selectedFieldIDs.value.length > 0) {
        filtered = filtered.filter(unit => {
            const unitFieldIds = unit.fields?.map(f => f.id) || []
            return selectedFieldIDs.value.some(id => unitFieldIds.includes(id))
        })
    }

    return filtered
})

const headerUnits = [
    {
        key: 'cities',
        title: 'Города',
        align: 'start',
        width: '14%',
    },
    { key: 'name', title: 'name'},
    { key: 'labels', title: 'labels', align: 'start', sortable: true},
    {
        key: 'uris',
        title: 'Uris',
    },
    {
        key: 'fields',
        title: 'Fields',
        align: 'start',
        sortable: true,
        width: '16%',
    },
    {
        key: 'stages',
        title: 'Stages',
    },
]
const formUnit = useForm({
    name: null,
    buildings: null,
    emails: null,
    fields: null,
    uris: null,
    labels: null,
})
function storeUnit(){
    formUnit.post(route('web.unit.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: false,
        onSuccess: ()=> {
            formUnit.reset()
            indexUnits()
        },
    });
}

// Fields
const indexFields = async () => {
    try {
        const { data } = await axios.get(route('fields.index'), {
            params: {}
        })
        fields.value = Array.isArray(data) ? data : (data.data || [])
    } catch (e) {
        console.error(e)
        fields.value = []
    }
}

//  Labels
const indexLabels = async () => {
    try {
        const { data } = await axios.get(route('labels.index'), {
            params: {}
        })
        labels.value = Array.isArray(data) ? data : (data.data || [])
    } catch (e) {
        console.error(e)
        labels.value = []
    }
}

onMounted(() => {
    indexFields()
    indexLabels()
})

// Используйте watch для загрузки при изменениях searchUnits (и immediate для начальной загрузки)
watch(searchUnits, () => {
    indexUnits()
}, { immediate: true }) // immediate: true — запустит сразу при монтировании

// Если есть другие зависимости (e.g., selectedLabelsIDs), добавьте watch на них:
watch(selectedLabelsIDs, () => {
    indexUnits()
}, { immediate: true, deep: true }) // deep: true, если array/object

watch(selectedFieldIDs, () => {
    indexUnits()
}, { deep: true })

const toggleLabel = (labelId) => {
    if (selectedLabelsIDs.value.includes(labelId)) {
        selectedLabelsIDs.value = selectedLabelsIDs.value.filter((id) => id !== labelId);
    } else {
        selectedLabelsIDs.value.push(labelId)
    }
}
</script>

<template>
    <v-container fluid>
        <v-row>
            <v-col>
                <v-row>
                    <v-col lg="2">
                        <v-text-field v-model="searchUnits"
                                      label="Поиск по юнитам"
                                      variant="solo-inverted"
                                      density="compact"
                                      color="deep-orange-accent-3"
                                      hide-details
                                      class="border-2 rounded border-rose-950"
                        ></v-text-field>
                    </v-col>
                    <v-col cols="1">
                        <v-btn text="+ Unit"
                               @click="showFormUnit = !showFormUnit"
                               variant="tonal"
                               density="comfortable"
                               color="deep-purple-darken-1"
                        ></v-btn>
                        <v-dialog v-model="showFormUnit"
                                  width="909">
                            <v-card>
                                <v-card-title>Form Unit</v-card-title>
                                <v-card-text>
                                    <v-form @submit.prevent>
                                        <v-container>
                                            <v-row>
                                                <v-col>
                                                    <v-text-field v-model="formUnit.name"
                                                                  label="Name"
                                                                  variant="solo-filled"
                                                                  density="comfortable"
                                                                  hide-details
                                                    ></v-text-field>
                                                </v-col>
                                            </v-row>
                                            <v-row>
                                                <v-col cols="9">
                                                    <v-autocomplete v-model="formUnit.uris"
                                                                    :items="uris"
                                                                    :item-value="'id'"
                                                                    :item-title="'address'"
                                                                    label="Uris selected"
                                                                    chips
                                                                    multiple
                                                    ></v-autocomplete>
                                                </v-col>
                                                <v-col cols="3">
                                                    <v-btn text="+ uri"
                                                           @click="dialogFormUri = !dialogFormUri"
                                                    ></v-btn>
                                                    <v-dialog v-model="dialogFormUri"
                                                              width="800"
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
                                                <v-col>
                                                    <v-autocomplete v-model="formUnit.fields"
                                                                    :items="fields"
                                                                    :item-value="'id'"
                                                                    :item-title="'title'"
                                                                    label="Fields"
                                                                    placeholder="Выбери поле"
                                                                    variant="solo"
                                                                    density="comfortable"
                                                                    hide-details
                                                                    multiple
                                                                    chips
                                                                    bg-color="indigo-accent-4"></v-autocomplete>
                                                </v-col>
                                                <v-col>
                                                    <v-autocomplete v-model="formUnit.labels"
                                                                    :items="labels"
                                                                    :item-value="'id'"
                                                                    :item-title="'name'"
                                                                    label="Labels"
                                                                    variant="solo"
                                                                    density="comfortable"
                                                                    bg-color="blue-grey-darken-4"
                                                                    item-color="deep-orange-darken-2"
                                                                    multiple
                                                                    chips
                                                                    hide-details
                                                    ></v-autocomplete>
                                                </v-col>
                                            </v-row>
                                            <v-row>
                                                <v-col>
                                                    <v-autocomplete v-model="formUnit.buildings"
                                                                    :items="buildings"
                                                                    :item-title="formatBuildingTitle"
                                                                    :item-value="'id'"
                                                                    label="Buildings"
                                                                    multiple
                                                                    chips
                                                                    variant="solo"
                                                                    density="comfortable"
                                                                    bg-color="blue-grey-darken-4"
                                                    ></v-autocomplete>
                                                </v-col>
                                                <v-col>
                                                    <v-autocomplete v-model="formUnit.emails"
                                                                    :items="emails"
                                                                    :item-value="'id'"
                                                                    :item-title="'address'"
                                                                    multiple
                                                                    chips
                                                                    label="Emails"
                                                                    variant="solo-filled"
                                                                    density="comfortable"
                                                                    bg-color="deep-orange-accent-4"
                                                                    hide-details></v-autocomplete>
                                                </v-col>
                                            </v-row>
                                        </v-container>
                                    </v-form>
                                </v-card-text>
                                <v-card-actions class="justify-start">
                                    <v-btn
                                        text="Close"
                                        @click="isActive.value = false"
                                    ></v-btn>
                                    <v-btn text="Сохранить"
                                           @click="storeUnit"
                                    ></v-btn>
                                </v-card-actions>
                            </v-card>
                        </v-dialog>
                    </v-col>
                </v-row>
                <v-row>
                    <v-col lg="8">
                        <v-data-table :items="filteredUnits"
                                      items-per-page="100"
                                      :headers="headerUnits"
                                      fixed-header
                                      height="888px"
                                      density="compact"
                                      class="border border-orange-800 rounded"
                                      hover
                        >
                            <template v-slot:item.cities="{item}">
                                <div v-for="building in item.buildings"
                                     class="text-sm font-sans text-lime-600 hover:text-lime-300"
                                >{{building.city.name}}</div>
                            </template>
                            <template v-slot:item.name="{item}">
                                <Link :href="route('web.unit.show', item.id)"
                                      class="text-sm"
                                >
                                    {{item.name}}
                                </Link>
                            </template>
                            <template v-slot:item.labels="{item}">
                                <div v-for="label in item.labels"
                                     class="text-xs font-sans text-blue-500"
                                >
                                    {{label.name}}
                                </div>
                            </template>
                            <template v-slot:item.uris="{item}">
                                <a v-for="uri in item.uris"
                                   :href="uri.address" target="_blank"
                                   class="text-xs inline-block mr-1 text-yellow-200">{{uri.address}}</a>
                            </template>
                            <template v-slot:item.fields="{item}">
                                <div v-for="field in item.fields"
                                     class="text-[10px] font-sans"
                                >{{field.title}}</div>
                            </template>
                            <template v-slot:item.stages="{item}">
                                <v-chip v-for="stage in item.stages"
                                        size="x-small"
                                        color="teal-accent-4"
                                >{{stage.name}}</v-chip>
                            </template>
                        </v-data-table>
                    </v-col>
                    <v-col>
                        <v-row>
                            <v-col cols="4">
                                <v-autocomplete
                                    v-model="selectedFieldIDs"
                                    :items="fields"
                                    item-title="title"
                                    item-value="id"
                                    label="Фильтр по полям"
                                    multiple
                                    chips
                                    clearable
                                    closable-chips
                                    variant="solo-inverted"
                                    density="compact"
                                    hide-details
                                />
                            </v-col>
                        </v-row>
                        <v-row>
                            <v-col>
                                <v-sheet class="flex flex-row justify-normal flex-wrap">
                                    <v-btn
                                        v-for="label in labels"
                                        :key="label.id"
                                        v-model="selectedLabelsIDs"
                                        :color="selectedLabelsIDs.includes(label.id) ? 'cyan' : 'blue-grey-darken-2'"
                                        :class="{ 'active-btn': selectedLabelsIDs.includes(label.id) }"
                                        @click="toggleLabel(label.id)"
                                        size="x-small"
                                        class="ma-1"
                                    >
                                        <!-- текст -->
                                        {{ label.name }}

                                        <!-- бейдж сразу после текста -->
                                        <span class="ml-1 text-[8px] bg-teal-700 rounded">{{label.rank}}</span>
                                    </v-btn>
                                </v-sheet>
                            </v-col>
                        </v-row>
                    </v-col>
                </v-row>
            </v-col>
        </v-row>
    </v-container>
</template>

<style scoped>

</style>
