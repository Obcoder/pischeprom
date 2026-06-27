<script setup>
import axios from 'axios'
import { computed, reactive, ref, watch } from 'vue'
import { route } from 'ziggy-js'

const props = defineProps({
    modelValue: Boolean,
    loading: Boolean,
    isEdit: Boolean,
    form: { type: Object, required: true },
    meta: { type: Object, required: true },
})

const emit = defineEmits(['update:modelValue', 'submit', 'building-created'])

const buildingFormOpen = ref(false)
const buildingCreating = ref(false)
const buildingError = ref('')
const createdBuildings = ref([])
const dadataSearch = ref('')
const dadataSelected = ref(null)
const dadataSuggestions = ref([])
const dadataLoading = ref(false)
const dadataMessage = ref('')
let dadataTimer = null
const buildingForm = reactive({
    city_id: null,
    address: '',
    postcode: '',
})

const close = () => emit('update:modelValue', false)
const save = () => emit('submit')

const normalizeSearchText = (value) => String(value ?? '')
    .normalize('NFKD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/\s+/g, ' ')
    .trim()

const searchText = (...values) => normalizeSearchText(values.filter(Boolean).join(' '))

const autocompleteTextFilter = (value, query) => {
    const normalizedValue = normalizeSearchText(value)
    const tokens = normalizeSearchText(query).split(' ').filter(Boolean)

    if (!tokens.length) {
        return 0
    }

    return tokens.every((token) => normalizedValue.includes(token)) ? 0 : -1
}

const emailOptions = computed(() => {
    return (props.meta.emails ?? []).map((email) => {
        const address = email.address ?? ''
        const domain = address.includes('@') ? address.split('@').pop() : ''

        return {
            ...email,
            domain,
            search_text: searchText(address, domain, email.name, email.comment),
        }
    })
})

const unitOptions = computed(() => {
    return (props.meta.units ?? []).map((unit) => ({
        ...unit,
        search_text: searchText(unit.name, unit.full_name, unit.short_name),
    }))
})

const cityNameById = computed(() => new Map(
    (props.meta.cities ?? []).map(city => [Number(city.id), city.name])
))

const normalizeBuilding = (building) => {
    const cityId = Number(building.city_id ?? building.city?.id) || null
    const cityName = building.city?.name || cityNameById.value.get(cityId) || 'Без города'
    const address = building.address ?? ''
    const postcode = building.postcode ?? ''

    return {
        ...building,
        id: Number(building.id),
        city_id: cityId,
        address,
        postcode,
        city: {
            id: cityId,
            name: cityName,
        },
        city_name: cityName,
        display_address: postcode ? `${address} · ${postcode}` : address,
        search_title: `${cityName} ${address} ${postcode}`.trim(),
    }
}

const buildingOptions = computed(() => {
    const map = new Map()
    const sourceBuildings = [
        ...(props.meta.buildings ?? []),
        ...createdBuildings.value,
    ]

    sourceBuildings.forEach((building) => {
        if (!building?.id) {
            return
        }

        const normalized = normalizeBuilding(building)
        map.set(normalized.id, normalized)
    })

    return [...map.values()].sort((a, b) => {
        const cityComparison = a.city_name.localeCompare(b.city_name, 'ru')

        if (cityComparison !== 0) {
            return cityComparison
        }

        return a.address.localeCompare(b.address, 'ru')
    })
})

const groupedBuildingItems = computed(() => {
    const items = []
    let currentCity = null

    buildingOptions.value.forEach((building) => {
        if (building.city_name !== currentCity) {
            currentCity = building.city_name
            items.push({
                id: `city-${building.city_id ?? currentCity}`,
                isHeader: true,
                city_name: currentCity,
                search_title: currentCity,
            })
        }

        items.push(building)
    })

    return items
})

const buildingItemProps = (item) => {
    const raw = item?.raw ?? item

    return raw?.isHeader
        ? { disabled: true, class: 'building-autocomplete__header-row' }
        : { class: 'building-autocomplete__option-row' }
}

const mergeCreatedBuilding = (building) => {
    createdBuildings.value = [
        building,
        ...createdBuildings.value.filter(item => Number(item.id) !== Number(building.id)),
    ]

    emit('building-created', building)
}

const countryIdByName = (countryName) => {
    const normalized = String(countryName || '').trim().toLowerCase()

    if (!normalized) {
        return null
    }

    return props.meta.countries?.find((country) => {
        return String(country.name || '').trim().toLowerCase() === normalized
    })?.id ?? null
}

const searchDaData = async () => {
    const query = dadataSearch.value.trim()

    if (query.length < 2) {
        dadataSuggestions.value = []
        dadataMessage.value = ''
        return
    }

    dadataLoading.value = true
    dadataMessage.value = 'Ищем организацию в DaData...'

    try {
        const { data } = await axios.get(route('web.entities.lookup'), {
            params: { query },
        })

        dadataSuggestions.value = data.data || []
        dadataMessage.value = dadataSuggestions.value.length
            ? 'Выберите организацию из справочника DaData.'
            : 'DaData не вернул подсказок. Можно заполнить вручную.'
    } catch (error) {
        dadataMessage.value = error?.response?.data?.message || 'Не удалось получить подсказки DaData.'
        dadataSuggestions.value = []
    } finally {
        dadataLoading.value = false
    }
}

const scheduleDaDataSearch = (value) => {
    dadataSearch.value = value || ''
    clearTimeout(dadataTimer)

    dadataTimer = setTimeout(searchDaData, 450)
}

const applyDaDataSuggestion = (suggestion) => {
    if (!suggestion?.entity) {
        return
    }

    const entity = suggestion.entity

    props.form.name = entity.name || props.form.name
    props.form.full_name = entity.full_name || props.form.full_name
    props.form.entity_classification_id = entity.entity_classification_id || props.form.entity_classification_id
    props.form.INN = entity.INN || props.form.INN
    props.form.KPP = entity.KPP || props.form.KPP
    props.form.OGRN = entity.OGRN || props.form.OGRN
    props.form.legal_address = entity.legal_address || props.form.legal_address
    props.form.country_id = countryIdByName(entity.country_name) || props.form.country_id
    props.form.dadata_raw = suggestion.raw || null

    dadataMessage.value = 'Реквизиты заполнены из DaData.'
}

const createBuilding = async () => {
    buildingError.value = ''

    const cityId = Number(buildingForm.city_id)
    const address = buildingForm.address.trim()
    const postcode = buildingForm.postcode.trim()

    if (!cityId || !address) {
        buildingError.value = 'Укажите город и адрес здания.'
        return
    }

    buildingCreating.value = true

    try {
        const { data } = await axios.post('/api/buildings', {
            city_id: cityId,
            address,
            postcode: postcode || null,
        })

        const building = normalizeBuilding(data)
        mergeCreatedBuilding(building)

        if (!props.form.buildings.some(id => Number(id) === building.id)) {
            props.form.buildings.push(building.id)
        }

        buildingForm.address = ''
        buildingForm.postcode = ''
        buildingFormOpen.value = false
    } catch (error) {
        buildingError.value = error?.response?.data?.message || 'Не удалось создать здание.'
    } finally {
        buildingCreating.value = false
    }
}

watch(dadataSelected, (value) => {
    applyDaDataSuggestion(value)
})

watch(
    () => props.modelValue,
    (isOpen) => {
        if (!isOpen) {
            clearTimeout(dadataTimer)
            dadataSearch.value = ''
            dadataSelected.value = null
            dadataSuggestions.value = []
            dadataMessage.value = ''
        }
    }
)
</script>

<template>
    <v-dialog
        :model-value="modelValue"
        max-width="950"
        @update:model-value="emit('update:modelValue', $event)"
    >
        <v-card>
            <v-card-title>
                {{ isEdit ? 'Редактирование Entity' : 'Новая Entity' }}
            </v-card-title>

            <v-card-text>
                <v-row dense>
                    <v-col cols="12">
                        <div class="dadata-panel">
                            <div class="dadata-panel__header">
                                <div>
                                    <span class="dadata-panel__eyebrow">DaData</span>
                                    <strong>Поиск по ИНН или названию</strong>
                                </div>

                                <v-chip
                                    v-if="form.dadata_raw"
                                    size="small"
                                    color="teal"
                                    variant="flat"
                                >
                                    данные выбраны
                                </v-chip>
                            </div>

                            <v-autocomplete
                                v-model="dadataSelected"
                                :items="dadataSuggestions"
                                :loading="dadataLoading"
                                :search="dadataSearch"
                                item-title="entity.name"
                                return-object
                                label="ИНН или название организации"
                                placeholder="Например: 7707083893 или Ромашка"
                                variant="solo-filled"
                                density="comfortable"
                                clearable
                                hide-details="auto"
                                no-filter
                                menu-icon="mdi-database-search"
                                :menu-props="{ contentClass: 'entity-dadata-menu' }"
                                @update:search="scheduleDaDataSearch"
                            >
                                <template #item="{ props, item }">
                                    <v-list-item
                                        v-bind="props"
                                        class="dadata-suggestion"
                                        prepend-icon="mdi-domain"
                                    >
                                        <template #title>
                                            <span>{{ item.raw.entity.name }}</span>
                                        </template>

                                        <template #subtitle>
                                            <span>{{ item.raw.entity.INN || 'ИНН не указан' }}</span>
                                            <span v-if="item.raw.entity.KPP"> · КПП {{ item.raw.entity.KPP }}</span>
                                            <span v-if="item.raw.entity.legal_address"> · {{ item.raw.entity.legal_address }}</span>
                                        </template>
                                    </v-list-item>
                                </template>
                            </v-autocomplete>

                            <v-alert
                                v-if="dadataMessage"
                                :type="dadataMessage.includes('заполнены') ? 'success' : 'info'"
                                variant="tonal"
                                density="compact"
                                class="mt-2 mb-0"
                            >
                                {{ dadataMessage }}
                            </v-alert>
                        </div>
                    </v-col>

                    <v-col cols="12" md="6">
                        <v-text-field v-model="form.name" label="Название" variant="solo-filled"/>
                    </v-col>

                    <v-col cols="12" md="6">
                        <v-text-field v-model="form.full_name" label="Полное название" variant="solo-filled"/>
                    </v-col>

                    <v-col cols="12" md="4">
                        <v-text-field v-model="form.INN" label="INN" variant="solo-filled" />
                    </v-col>

                    <v-col cols="12" md="4">
                        <v-text-field v-model="form.KPP" label="KPP" variant="solo-filled" />
                    </v-col>

                    <v-col cols="12" md="4">
                        <v-text-field v-model="form.OGRN" label="OGRN" variant="solo-filled" />
                    </v-col>

                    <v-col cols="12">
                        <v-textarea
                            v-model="form.legal_address"
                            label="Юридический адрес"
                            variant="solo-filled"
                            rows="2"
                            auto-grow
                        />
                    </v-col>

                    <v-col cols="12" md="6">
                        <v-select
                            v-model="form.entity_classification_id"
                            :items="meta.classifications"
                            item-title="name"
                            item-value="id"
                            label="Классификация"
                            variant="solo-filled"
                            clearable
                        />
                    </v-col>

                    <v-col cols="12" md="6">
                        <v-select
                            v-model="form.country_id"
                            :items="meta.countries"
                            item-title="name"
                            item-value="id"
                            label="Страна"
                            variant="solo-filled"
                            clearable
                        />
                    </v-col>

                    <v-col cols="12" md="6">
                        <v-autocomplete
                            v-model="form.cities"
                            :items="meta.cities"
                            item-title="name"
                            item-value="id"
                            label="Города"
                            multiple
                            chips
                            closable-chips
                            variant="solo-filled"
                        />
                    </v-col>

                    <v-col cols="12">
                        <div class="buildings-panel">
                            <div class="buildings-panel__header">
                                <div>
                                    <span class="buildings-panel__eyebrow">География присутствия</span>
                                    <strong>Здания</strong>
                                </div>

                                <div class="buildings-panel__actions">
                                    <span class="buildings-panel__counter">
                                        {{ form.buildings.length }} выбрано
                                    </span>

                                    <v-btn
                                        size="small"
                                        variant="flat"
                                        color="#1d4d3c"
                                        rounded="lg"
                                        prepend-icon="mdi-plus"
                                        @click="buildingFormOpen = !buildingFormOpen"
                                    >
                                        Новое
                                    </v-btn>
                                </div>
                            </div>

                            <v-autocomplete
                                v-model="form.buildings"
                                :items="groupedBuildingItems"
                                :item-props="buildingItemProps"
                                item-title="search_title"
                                item-value="id"
                                label="Поиск зданий по городу или адресу"
                                placeholder="Город, адрес, индекс"
                                multiple
                                chips
                                closable-chips
                                clearable
                                hide-details="auto"
                                variant="solo"
                                density="comfortable"
                                class="buildings-autocomplete"
                                menu-icon="mdi-map-marker-radius"
                                :menu-props="{ contentClass: 'entity-buildings-menu' }"
                            >
                                <template #chip="{ props, item }">
                                    <v-chip
                                        v-bind="props"
                                        class="buildings-autocomplete__chip"
                                        size="small"
                                        variant="flat"
                                    >
                                        <span class="buildings-autocomplete__chip-city">
                                            {{ item.raw.city_name }}
                                        </span>
                                        <span>{{ item.raw.display_address }}</span>
                                    </v-chip>
                                </template>

                                <template #item="{ props, item }">
                                    <v-list-subheader
                                        v-if="item.raw.isHeader"
                                        class="buildings-autocomplete__subheader"
                                    >
                                        <v-icon icon="mdi-city-variant-outline" size="16" />
                                        {{ item.raw.city_name }}
                                    </v-list-subheader>

                                    <v-list-item
                                        v-else
                                        v-bind="props"
                                        class="buildings-autocomplete__item"
                                        prepend-icon="mdi-office-building-marker-outline"
                                    >
                                        <template #title>
                                            <span>{{ item.raw.address }}</span>
                                        </template>

                                        <template #subtitle>
                                            <span>{{ item.raw.city_name }}</span>
                                            <span v-if="item.raw.postcode"> · {{ item.raw.postcode }}</span>
                                        </template>
                                    </v-list-item>
                                </template>
                            </v-autocomplete>

                            <v-expand-transition>
                                <div v-if="buildingFormOpen" class="building-create-panel">
                                    <v-row dense>
                                        <v-col cols="12" md="4">
                                            <v-autocomplete
                                                v-model="buildingForm.city_id"
                                                :items="meta.cities"
                                                item-title="name"
                                                item-value="id"
                                                label="City"
                                                placeholder="Выберите город"
                                                variant="solo-filled"
                                                density="compact"
                                                hide-details="auto"
                                            />
                                        </v-col>

                                        <v-col cols="12" md="5">
                                            <v-text-field
                                                v-model="buildingForm.address"
                                                label="Адрес"
                                                placeholder="Улица, дом, корпус"
                                                variant="solo-filled"
                                                density="compact"
                                                hide-details="auto"
                                            />
                                        </v-col>

                                        <v-col cols="12" md="3">
                                            <v-text-field
                                                v-model="buildingForm.postcode"
                                                label="Индекс"
                                                variant="solo-filled"
                                                density="compact"
                                                hide-details="auto"
                                            />
                                        </v-col>
                                    </v-row>

                                    <div class="building-create-panel__footer">
                                        <v-alert
                                            v-if="buildingError"
                                            type="error"
                                            variant="tonal"
                                            density="compact"
                                            class="building-create-panel__error"
                                        >
                                            {{ buildingError }}
                                        </v-alert>

                                        <v-btn
                                            color="#1d4d3c"
                                            rounded="lg"
                                            :loading="buildingCreating"
                                            prepend-icon="mdi-content-save"
                                            @click="createBuilding"
                                        >
                                            Создать и привязать
                                        </v-btn>
                                    </div>
                                </div>
                            </v-expand-transition>
                        </div>
                    </v-col>

                    <v-col cols="12" md="6">
                        <v-autocomplete
                            v-model="form.emails"
                            :items="emailOptions"
                            :custom-filter="autocompleteTextFilter"
                            :filter-keys="['raw.search_text']"
                            item-title="address"
                            item-value="id"
                            label="Emails"
                            placeholder="Адрес, домен или часть email"
                            multiple
                            chips
                            closable-chips
                            clearable
                            hide-details="auto"
                            variant="solo-filled"
                            class="emails-autocomplete"
                            menu-icon="mdi-email-search-outline"
                            :menu-props="{ contentClass: 'entity-emails-menu' }"
                        >
                            <template #item="{ props, item }">
                                <v-list-item
                                    v-bind="props"
                                    class="emails-autocomplete__item"
                                    prepend-icon="mdi-email-outline"
                                >
                                    <template #title>
                                        <span>{{ item.raw.address }}</span>
                                    </template>

                                    <template
                                        v-if="item.raw.domain"
                                        #subtitle
                                    >
                                        <span>{{ item.raw.domain }}</span>
                                    </template>
                                </v-list-item>
                            </template>
                        </v-autocomplete>
                    </v-col>

                    <v-col cols="12" md="6">
                        <v-autocomplete
                            v-model="form.telephones"
                            :items="meta.telephones"
                            item-title="number"
                            item-value="id"
                            label="Телефоны"
                            multiple
                            chips
                            closable-chips
                            variant="solo-filled"
                        />
                    </v-col>

                    <v-col cols="12">
                        <div class="units-autocomplete-panel">
                            <div class="units-autocomplete-panel__header">
                                <div>
                                    <span class="units-autocomplete-panel__eyebrow">Связанные компании</span>
                                    <strong>Units</strong>
                                </div>

                                <span class="units-autocomplete-panel__counter">
                                    {{ form.units.length }} выбрано
                                </span>
                            </div>

                            <v-autocomplete
                                v-model="form.units"
                                :items="unitOptions"
                                :custom-filter="autocompleteTextFilter"
                                :filter-keys="['raw.search_text']"
                                item-title="name"
                                item-value="id"
                                aria-label="Поиск и привязка Units"
                                placeholder="Поиск по любой части названия Unit"
                                multiple
                                chips
                                closable-chips
                                clearable
                                hide-details="auto"
                                variant="solo"
                                density="comfortable"
                                class="units-autocomplete"
                                menu-icon="mdi-magnify"
                                :menu-props="{ contentClass: 'entity-units-menu' }"
                            >
                                <template #chip="{ props, item }">
                                    <v-chip
                                        v-bind="props"
                                        class="units-autocomplete__chip"
                                        size="small"
                                        variant="flat"
                                    >
                                        {{ item.raw.name }}
                                    </v-chip>
                                </template>

                                <template #item="{ props, item }">
                                    <v-list-item
                                        v-bind="props"
                                        class="units-autocomplete__item"
                                        prepend-icon="mdi-factory"
                                    >
                                        <template #title>
                                            <span>{{ item.raw.name }}</span>
                                        </template>
                                    </v-list-item>
                                </template>
                            </v-autocomplete>
                        </div>
                    </v-col>

                    <v-col cols="12" md="6">
                        <v-select
                            v-model="form.chats"
                            :items="meta.chats"
                            item-title="numbers"
                            item-value="id"
                            label="Chats"
                            multiple
                            chips
                            closable-chips
                            variant="solo-filled"
                        />
                    </v-col>
                </v-row>
            </v-card-text>

            <v-card-actions>
                <v-spacer />
                <v-btn variant="text" @click="close">Отмена</v-btn>
                <v-btn color="primary" :loading="loading" @click="save">Сохранить</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>
.dadata-panel {
    padding: 14px;
    border: 1px solid rgba(24, 93, 99, 0.18);
    border-radius: 18px;
    background:
        radial-gradient(circle at 0% 0%, rgba(54, 151, 159, 0.20), transparent 34%),
        linear-gradient(135deg, #effbfb 0%, #ffffff 60%, #edf6f3 100%);
    box-shadow: 0 16px 32px rgba(17, 79, 82, 0.09);
}

.dadata-panel__header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 10px;
    color: #123f42;
}

.dadata-panel__header strong {
    display: block;
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 19px;
    line-height: 1.05;
}

.dadata-panel__eyebrow {
    display: block;
    margin-bottom: 4px;
    font-size: 10px;
    font-weight: 900;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: rgba(18, 63, 66, 0.58);
}

.dadata-suggestion :deep(.v-list-item-title) {
    color: #123f42;
    font-weight: 800;
}

.dadata-suggestion :deep(.v-list-item-subtitle) {
    color: rgba(18, 63, 66, 0.68);
}

.units-autocomplete-panel {
    padding: 14px;
    border: 1px solid rgba(64, 44, 22, 0.14);
    border-radius: 18px;
    background:
        linear-gradient(135deg, rgba(255, 248, 235, 0.96), rgba(244, 237, 222, 0.88)),
        repeating-linear-gradient(90deg, rgba(121, 84, 35, 0.08) 0 1px, transparent 1px 9px);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.74);
}

.units-autocomplete-panel__header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 10px;
    color: #3a2613;
}

.units-autocomplete-panel__header strong {
    display: block;
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 19px;
    line-height: 1;
}

.units-autocomplete-panel__eyebrow {
    display: block;
    margin-bottom: 4px;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: rgba(58, 38, 19, 0.56);
}

.units-autocomplete-panel__counter {
    flex: 0 0 auto;
    padding: 5px 9px;
    border: 1px solid rgba(111, 70, 28, 0.22);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.58);
    font-size: 11px;
    font-weight: 800;
    color: #6a411f;
}

.units-autocomplete :deep(.v-field) {
    border-radius: 14px;
    border: 1px solid rgba(255, 231, 190, 0.22);
    background: linear-gradient(135deg, #2f2114 0%, #4a2f18 100%);
    box-shadow: 0 10px 24px rgba(84, 58, 24, 0.18);
    color: #fff7e8;
}

.units-autocomplete :deep(.v-label) {
    color: rgba(255, 247, 232, 0.74);
}

.units-autocomplete :deep(.v-field__input),
.units-autocomplete :deep(.v-field__input input),
.units-autocomplete :deep(.v-select__selection-text) {
    color: #fff7e8 !important;
    opacity: 1;
}

.units-autocomplete :deep(.v-field__input input::placeholder) {
    color: rgba(255, 247, 232, 0.56) !important;
    opacity: 1;
}

.units-autocomplete :deep(.v-icon),
.units-autocomplete :deep(.v-field__clearable) {
    color: rgba(255, 247, 232, 0.78);
}

.units-autocomplete__chip {
    border: 1px solid rgba(255, 247, 232, 0.22);
    background: #fff7e8;
    color: #2f2114;
    font-family: 'Courier New', monospace;
    font-weight: 700;
}

.units-autocomplete__item :deep(.v-list-item-title) {
    font-family: Georgia, 'Times New Roman', serif;
    color: #2f2114;
}

.buildings-panel {
    padding: 14px;
    border: 1px solid rgba(29, 77, 60, 0.16);
    border-radius: 18px;
    background:
        radial-gradient(circle at 0% 0%, rgba(29, 77, 60, 0.14), transparent 36%),
        linear-gradient(135deg, #f3fbf6 0%, #ffffff 58%, #eef6f1 100%);
    box-shadow: 0 14px 30px rgba(20, 59, 45, 0.08);
}

.buildings-panel__header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 10px;
    color: #153629;
}

.buildings-panel__header strong {
    display: block;
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 19px;
    line-height: 1;
}

.buildings-panel__eyebrow {
    display: block;
    margin-bottom: 4px;
    font-size: 10px;
    font-weight: 900;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: rgba(21, 54, 41, 0.56);
}

.buildings-panel__actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
}

.buildings-panel__counter {
    padding: 5px 9px;
    border: 1px solid rgba(29, 77, 60, 0.18);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.72);
    font-size: 11px;
    font-weight: 900;
    color: #1d4d3c;
}

.buildings-autocomplete :deep(.v-field) {
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.96);
    box-shadow: 0 10px 22px rgba(21, 54, 41, 0.08);
    color: #153629;
}

.buildings-autocomplete :deep(.v-field__input),
.buildings-autocomplete :deep(.v-field__input input),
.buildings-autocomplete :deep(.v-select__selection-text) {
    color: #153629 !important;
    opacity: 1;
}

.buildings-autocomplete :deep(.v-field__input input::placeholder) {
    color: rgba(21, 54, 41, 0.58) !important;
    opacity: 1;
}

.buildings-autocomplete :deep(.v-label),
.buildings-autocomplete :deep(.v-icon),
.buildings-autocomplete :deep(.v-field__clearable) {
    color: rgba(21, 54, 41, 0.72) !important;
}

.buildings-autocomplete :deep(.v-field__overlay) {
    opacity: 0;
}

.buildings-autocomplete__chip {
    gap: 6px;
    background: #1d4d3c;
    color: #f3fbf6;
    font-family: 'Courier New', monospace;
    font-weight: 700;
}

.buildings-autocomplete__chip-city {
    padding: 1px 5px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.17);
    font-size: 9px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.buildings-autocomplete__subheader {
    display: flex;
    align-items: center;
    gap: 6px;
    min-height: 30px;
    background: linear-gradient(90deg, rgba(29, 77, 60, 0.10), transparent);
    color: #1d4d3c;
    font-size: 10px;
    font-weight: 900;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.buildings-autocomplete__item :deep(.v-list-item-title) {
    font-family: Georgia, 'Times New Roman', serif;
    color: #153629;
}

.buildings-autocomplete__item :deep(.v-list-item-subtitle) {
    color: rgba(21, 54, 41, 0.62);
    font-family: 'Courier New', monospace;
    font-size: 11px;
}

.building-create-panel {
    margin-top: 12px;
    padding: 12px;
    border: 1px dashed rgba(29, 77, 60, 0.26);
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.70);
}

.building-create-panel__footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 10px;
}

.building-create-panel__error {
    flex: 1 1 auto;
}

@media (max-width: 640px) {
    .buildings-panel__header,
    .building-create-panel__footer {
        align-items: stretch;
        flex-direction: column;
    }

    .buildings-panel__actions {
        justify-content: flex-start;
    }
}
</style>

<style>
.entity-emails-menu .v-list,
.entity-units-menu .v-list,
.entity-buildings-menu .v-list,
.entity-dadata-menu .v-list {
    background: #fffaf0 !important;
    color: #2f2114 !important;
    border: 1px solid rgba(64, 44, 22, 0.16);
    box-shadow: 0 24px 64px rgba(26, 18, 10, 0.24);
}

.entity-emails-menu .v-list-item,
.entity-units-menu .v-list-item,
.entity-buildings-menu .v-list-item,
.entity-dadata-menu .v-list-item {
    color: #2f2114 !important;
}

.entity-emails-menu .v-list-item-title,
.entity-units-menu .v-list-item-title,
.entity-buildings-menu .v-list-item-title,
.entity-dadata-menu .v-list-item-title {
    color: #2f2114 !important;
}

.entity-emails-menu .v-list-item-subtitle,
.entity-units-menu .v-list-item-subtitle,
.entity-buildings-menu .v-list-item-subtitle,
.entity-dadata-menu .v-list-item-subtitle {
    color: rgba(47, 33, 20, 0.68) !important;
}

.entity-emails-menu .v-list-item:hover,
.entity-units-menu .v-list-item:hover,
.entity-buildings-menu .v-list-item:hover,
.entity-dadata-menu .v-list-item:hover {
    background: rgba(121, 84, 35, 0.12) !important;
}
</style>
