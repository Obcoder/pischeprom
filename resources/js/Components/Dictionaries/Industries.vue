<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import axios from 'axios'

// ============ state ============
const industries = ref([])
const loading = ref(false)
const errorMsg = ref('')

const search = ref('')
const groupMode = ref('section') // section | letter | none

// dialog create
const showCreate = ref(false)
const form = ref({
    code: '',
    title: '',
})
const formErrors = ref({})
const saving = ref(false)

// ============ helpers ============
function normalizeCode(code) {
    return (code ?? '').toString().trim()
}

function getOkvedSection(code) {
    // "62.01" -> "62"
    const c = normalizeCode(code)
    if (!c) return '—'
    return c.split('.')[0] || c
}

function getFirstLetter(code) {
    // "A" -> "A", "62.01" -> "6"
    const c = normalizeCode(code)
    if (!c) return '—'
    return c[0].toUpperCase()
}

// ============ API ============
const fetchIndustries = async () => {
    loading.value = true
    errorMsg.value = ''
    try {
        // серверная фильтрация (если в контроллере search поддержан)
        const { data } = await axios.get('/api/industries', {
            params: {
                search: search.value || null,
                // Если контроллер отдаёт paginate, то data.data — массив
                per_page: 5000, // если справочник большой — лучше пагинацию/виртуализацию
            },
        })

        industries.value = Array.isArray(data) ? data : (data.data || [])
    } catch (e) {
        console.error(e)
        industries.value = []
        errorMsg.value = 'Не удалось загрузить справочник ОКВЭД'
    } finally {
        loading.value = false
    }
}

const createIndustry = async () => {
    saving.value = true
    formErrors.value = {}
    try {
        await axios.post('/api/industries', {
            code: form.value.code,
            title: form.value.title,
        })

        // reset + close
        form.value.code = ''
        form.value.title = ''
        showCreate.value = false

        // refresh list
        await fetchIndustries()
    } catch (e) {
        // Laravel validation errors
        if (e?.response?.status === 422) {
            formErrors.value = e.response.data.errors || {}
            return
        }
        console.error(e)
        errorMsg.value = 'Не удалось создать ОКВЭД'
    } finally {
        saving.value = false
    }
}

// ============ client filtering (доп.) ============
const filteredIndustries = computed(() => {
    let list = industries.value

    if (search.value) {
        const s = search.value.toLowerCase()
        list = list.filter(i =>
            (i.code || '').toLowerCase().includes(s) ||
            (i.title || '').toLowerCase().includes(s)
        )
    }

    return list
})

// ============ grouping ============
const groupBy = computed(() => {
    if (groupMode.value === 'none') return []
    if (groupMode.value === 'letter') {
        return [{ key: 'group_letter', order: 'asc' }]
    }
    // section
    return [{ key: 'group_section', order: 'asc' }]
})

const itemsForTable = computed(() => {
    return filteredIndustries.value.map(i => ({
        ...i,
        group_section: getOkvedSection(i.code),
        group_letter: getFirstLetter(i.code),
    }))
})

// ============ headers ============
const headers = [
    { key: 'code', title: 'ОКВЭД', sortable: true, width: '160px' },
    { key: 'title', title: 'Название', sortable: true },
    { key: 'created_at', title: 'Создан', sortable: true, width: '160px' },
]

// ============ debounce search ============
let t = null
watch(search, () => {
    // если хочешь только серверный поиск — оставь fetchIndustries, а client filter убери
    clearTimeout(t)
    t = setTimeout(() => {
        fetchIndustries()
    }, 250)
})

// ============ init ============
onMounted(() => {
    fetchIndustries()
})
</script>

<template>
    <v-container fluid>
        <v-row>
            <v-col>
                <v-row class="align-center">
                    <v-col cols="12" lg="3">
                        <v-text-field
                            v-model="search"
                            label="Поиск по ОКВЭД (код или название)"
                            variant="solo-inverted"
                            density="compact"
                            hide-details
                            clearable
                        />
                    </v-col>

                    <v-col cols="12" lg="3">
                        <v-select
                            v-model="groupMode"
                            :items="[
                { title: 'Группировка: раздел (до точки)', value: 'section' },
                { title: 'Группировка: первая буква/цифра', value: 'letter' },
                { title: 'Без группировки', value: 'none' },
              ]"
                            label="Группировка"
                            density="compact"
                            variant="solo-inverted"
                            hide-details
                        />
                    </v-col>

                    <v-col cols="12" lg="2">
                        <v-btn
                            text="+ Industry"
                            color="deep-purple-darken-1"
                            variant="tonal"
                            density="comfortable"
                            @click="showCreate = true"
                        />
                    </v-col>

                    <v-col cols="12" lg="4">
                        <v-alert
                            v-if="errorMsg"
                            type="error"
                            density="compact"
                            variant="tonal"
                            class="ma-0"
                        >
                            {{ errorMsg }}
                        </v-alert>
                    </v-col>
                </v-row>

                <v-row>
                    <v-col>
                        <v-data-table
                            :headers="headers"
                            :items="itemsForTable"
                            :loading="loading"
                            :group-by="groupBy"
                            items-per-page="100"
                            fixed-header
                            height="760px"
                            density="compact"
                            hover
                            class="border border-orange-800 rounded"
                        >
                            <!-- group header -->
                            <template #group-header="{ item, columns, toggleGroup, isGroupOpen }">
                                <tr>
                                    <td :colspan="columns.length" class="text-left">
                                        <v-btn
                                            variant="text"
                                            density="compact"
                                            @click="toggleGroup(item)"
                                        >
                                              <span class="mr-2">
                                                {{ isGroupOpen(item) ? '▾' : '▸' }}
                                              </span>
                                            <strong>
                                                {{
                                                    groupMode === 'section'
                                                        ? `Раздел: ${item.value}`
                                                        : `Группа: ${item.value}`
                                                }}
                                            </strong>
                                            <span class="ml-2 text-caption">
                        ({{ item.items.length }})
                      </span>
                                        </v-btn>
                                    </td>
                                </tr>
                            </template>

                            <template #item.code="{ item }">
                                <span class="text-teal-200 font-weight-bold">
                                  {{ item.code }}
                                </span>
                                            </template>

                                            <template #item.title="{ item }">
                                <span class="text-sm">
                                  {{ item.title }}
                                </span>
                                            </template>

                                            <template #item.created_at="{ item }">
                                <span class="text-caption">
                                  {{ item.created_at ? item.created_at.slice(0, 10) : '—' }}
                                </span>
                            </template>

                            <template #no-data>
                                <div class="pa-4 text-caption">
                                    Ничего не найдено
                                </div>
                            </template>
                        </v-data-table>
                    </v-col>
                </v-row>

                <!-- Create dialog -->
                <v-dialog v-model="showCreate" width="720">
                    <v-card>
                        <v-toolbar title="Добавить ОКВЭД (Industry)" />

                        <v-card-text>
                            <v-form @submit.prevent>
                                <v-row>
                                    <v-col cols="12" lg="4">
                                        <v-text-field
                                            v-model="form.code"
                                            label="Код ОКВЭД"
                                            placeholder="например: 62.01"
                                            variant="solo-filled"
                                            density="comfortable"
                                            :error-messages="formErrors.code"
                                        />
                                    </v-col>

                                    <v-col cols="12" lg="8">
                                        <v-text-field
                                            v-model="form.title"
                                            label="Название"
                                            placeholder="Разработка компьютерного программного обеспечения"
                                            variant="solo-filled"
                                            density="comfortable"
                                            :error-messages="formErrors.title"
                                        />
                                    </v-col>
                                </v-row>
                            </v-form>
                        </v-card-text>

                        <v-card-actions class="justify-start">
                            <v-btn
                                text="Закрыть"
                                variant="text"
                                @click="showCreate = false"
                                :disabled="saving"
                            />
                            <v-btn
                                text="Сохранить"
                                color="deep-purple-darken-1"
                                variant="tonal"
                                :loading="saving"
                                @click="createIndustry"
                            />
                        </v-card-actions>
                    </v-card>
                </v-dialog>

            </v-col>
        </v-row>
    </v-container>
</template>

<style scoped>
</style>
