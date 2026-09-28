<script setup>
import { computed, nextTick, ref, useId, watch } from 'vue'

const props = defineProps({
    modelValue: { type: Object, required: true },
    search: { type: String, default: '' },
    cities: { type: Array, default: () => [] },
    cityLoading: Boolean,
    buildingTypes: { type: Array, default: () => [] },
    errors: { type: Object, default: () => ({}) },
    disabled: Boolean,
})

const emit = defineEmits(['update:modelValue', 'update:search'])
const fieldId = `building-${useId()}`
const cityOpen = ref(false)
const activeCityIndex = ref(-1)
const cityList = ref(null)
const knownCities = ref(new Map())
const cityLabel = city => city?.label || city?.name || ''
const cityText = computed(() => cityLabel(knownCities.value.get(String(props.modelValue.city_id))) || props.search)
const activeCityId = computed(() => cityOpen.value && !props.cityLoading && activeCityIndex.value >= 0
    ? `${fieldId}-city-option-${activeCityIndex.value}` : undefined)

watch(() => props.cities, cities => {
    const cache = new Map(knownCities.value)
    for (const city of cities) cache.set(String(city.id), city)
    knownCities.value = cache
    activeCityIndex.value = -1
}, { immediate: true })

watch(() => props.disabled, disabled => { if (disabled) closeCities() })
watch(() => props.cityLoading, () => { activeCityIndex.value = -1 })

function updateField(field, value) {
    if (!props.disabled) emit('update:modelValue', { ...props.modelValue, [field]: value })
}

function errorText(field) {
    const error = props.errors[field]
    return Array.isArray(error) ? error[0] : error
}

function errorId(field) {
    return errorText(field) ? `${fieldId}-${field}-error` : undefined
}

function openCities() {
    if (!props.disabled) cityOpen.value = true
}

function closeCities() {
    cityOpen.value = false
    activeCityIndex.value = -1
}

function searchCities(event) {
    if (props.disabled) return
    updateField('city_id', null)
    emit('update:search', event.target.value)
    activeCityIndex.value = -1
    cityOpen.value = true
}

function selectCity(city) {
    if (props.disabled || props.cityLoading || !city) return
    updateField('city_id', city.id)
    emit('update:search', cityLabel(city))
    closeCities()
}

function cityKeydown(event) {
    if (props.disabled || event.isComposing) return
    if (event.key === 'Escape' && cityOpen.value) {
        event.preventDefault()
        event.stopPropagation()
        closeCities()
        return
    }
    if (event.key === 'Tab') {
        closeCities()
        return
    }
    if (event.key === 'Enter' && cityOpen.value) {
        event.preventDefault()
        if (!props.cityLoading) selectCity(props.cities[activeCityIndex.value])
        return
    }
    if (!['ArrowDown', 'ArrowUp'].includes(event.key)) return
    event.preventDefault()
    openCities()
    if (props.cityLoading || !props.cities.length) return
    const direction = event.key === 'ArrowDown' ? 1 : -1
    activeCityIndex.value = activeCityIndex.value < 0
        ? (direction > 0 ? 0 : props.cities.length - 1)
        : (activeCityIndex.value + direction + props.cities.length) % props.cities.length
    nextTick(() => cityList.value?.children[activeCityIndex.value]?.scrollIntoView?.({ block: 'nearest' }))
}
</script>

<template>
    <div class="compact-building-fields">
        <div class="building-field building-city">
            <label :for="`${fieldId}-city`">Город <span class="required-mark" aria-hidden="true">*</span></label>
            <div class="city-control" :class="{ 'is-open': cityOpen }">
                <svg class="city-control__icon" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="8.5" cy="8.5" r="5.5" /><path d="m13 13 4 4" /></svg>
                <input
                    :id="`${fieldId}-city`"
                    :value="cityText"
                    class="ym-disable-keys"
                    name="city_search"
                    type="text"
                    role="combobox"
                    autocomplete="off"
                    placeholder="Начните вводить город"
                    aria-autocomplete="list"
                    aria-required="true"
                    :aria-expanded="cityOpen"
                    :aria-controls="`${fieldId}-city-list`"
                    :aria-activedescendant="activeCityId"
                    :aria-invalid="Boolean(errorText('city_id'))"
                    :aria-describedby="errorId('city_id')"
                    :aria-busy="cityLoading"
                    :disabled="disabled"
                    @focus="openCities"
                    @click="openCities"
                    @blur="closeCities"
                    @input="searchCities"
                    @keydown="cityKeydown"
                >
                <span v-if="cityLoading" class="city-control__spinner" aria-hidden="true" />
                <svg v-else class="city-control__arrow" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m4 6 4 4 4-4" /></svg>
            </div>
            <div v-if="cityOpen" class="city-dropdown">
                <ul :id="`${fieldId}-city-list`" ref="cityList" role="listbox" aria-label="Города" :aria-busy="cityLoading">
                    <li v-for="(city, index) in (cityLoading ? [] : cities)" :id="`${fieldId}-city-option-${index}`" :key="city.id" role="option" :aria-selected="String(modelValue.city_id) === String(city.id)" :class="{ 'is-active': index === activeCityIndex }" @mousedown.prevent @click="selectCity(city)">
                        <span>{{ cityLabel(city) }}</span>
                        <span v-if="String(modelValue.city_id) === String(city.id)" aria-hidden="true">✓</span>
                    </li>
                </ul>
                <span v-if="cityLoading || !cities.length" class="city-dropdown__status" role="status">{{ cityLoading ? 'Ищем города…' : 'Город не найден. Уточните название.' }}</span>
            </div>
            <small v-if="errorText('city_id')" :id="errorId('city_id')" class="field-error" role="alert">{{ errorText('city_id') }}</small>
        </div>

        <div class="building-field">
            <label :for="`${fieldId}-address`">Улица и дом <span class="required-mark" aria-hidden="true">*</span></label>
            <textarea :id="`${fieldId}-address`" :value="modelValue.address" class="ym-disable-keys" name="address" rows="2" maxlength="255" autocomplete="street-address" placeholder="Улица, дом, корпус" required :disabled="disabled" :aria-invalid="Boolean(errorText('address'))" :aria-describedby="errorId('address')" @input="updateField('address', $event.target.value)" />
            <small v-if="errorText('address')" :id="errorId('address')" class="field-error" role="alert">{{ errorText('address') }}</small>
        </div>

        <div class="building-field-row">
            <div class="building-field">
                <label :for="`${fieldId}-type`">Тип здания</label>
                <select :id="`${fieldId}-type`" :value="modelValue.building_type_id ?? ''" name="building_type_id" :disabled="disabled" :aria-invalid="Boolean(errorText('building_type_id'))" :aria-describedby="errorId('building_type_id')" @change="updateField('building_type_id', buildingTypes.find(type => String(type.id) === $event.target.value)?.id ?? null)">
                    <option value="">Не указан</option>
                    <option v-for="type in buildingTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                </select>
                <small v-if="errorText('building_type_id')" :id="errorId('building_type_id')" class="field-error" role="alert">{{ errorText('building_type_id') }}</small>
            </div>
            <div class="building-field">
                <label :for="`${fieldId}-postcode`">Индекс</label>
                <input :id="`${fieldId}-postcode`" :value="modelValue.postcode" class="ym-disable-keys" name="postcode" type="text" maxlength="32" autocomplete="postal-code" placeholder="101000" :disabled="disabled" :aria-invalid="Boolean(errorText('postcode'))" :aria-describedby="errorId('postcode')" @input="updateField('postcode', $event.target.value)">
                <small v-if="errorText('postcode')" :id="errorId('postcode')" class="field-error" role="alert">{{ errorText('postcode') }}</small>
            </div>
        </div>

        <div class="building-field-row building-field-row--apartment">
            <div class="building-field">
                <label :for="`${fieldId}-apartment-type`">Помещение</label>
                <select :id="`${fieldId}-apartment-type`" :value="modelValue.delivery_apartment_type || 'apartment'" name="delivery_apartment_type" :disabled="disabled" :aria-invalid="Boolean(errorText('delivery_apartment_type'))" :aria-describedby="errorId('delivery_apartment_type')" @change="updateField('delivery_apartment_type', $event.target.value)">
                    <option value="apartment">Квартира</option>
                    <option value="office">Офис</option>
                    <option value="premise">Помещение</option>
                </select>
                <small v-if="errorText('delivery_apartment_type')" :id="errorId('delivery_apartment_type')" class="field-error" role="alert">{{ errorText('delivery_apartment_type') }}</small>
            </div>
            <div class="building-field">
                <label :for="`${fieldId}-apartment-number`">Номер <span class="optional-label">необяз.</span></label>
                <input :id="`${fieldId}-apartment-number`" :value="modelValue.delivery_apartment_number" class="ym-disable-keys" name="delivery_apartment_number" type="text" maxlength="50" placeholder="12А" :disabled="disabled" :aria-invalid="Boolean(errorText('delivery_apartment_number'))" :aria-describedby="errorId('delivery_apartment_number')" @input="updateField('delivery_apartment_number', $event.target.value)">
                <small v-if="errorText('delivery_apartment_number')" :id="errorId('delivery_apartment_number')" class="field-error" role="alert">{{ errorText('delivery_apartment_number') }}</small>
            </div>
        </div>
    </div>
</template>

<style scoped>
.compact-building-fields { container-type: inline-size; display: grid; min-width: 0; gap: 7px; color: #e9ebff; }
.building-field { display: grid; min-width: 0; align-content: start; gap: 3px; }
.building-field label { display: flex; align-items: baseline; gap: 4px; color: #a5abc9; font-size: 9px; font-weight: 500; line-height: 1.3; }
.required-mark { color: #b19af9; }
.optional-label { margin-left: auto; color: #8189ad; font-size: 8px; font-weight: 400; }
.building-field-row { display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr); gap: 6px; }
.building-field-row--apartment { padding-top: 7px; border-top: 1px solid #2e334c; }
.building-field input, .building-field select, .building-field textarea { box-sizing: border-box; width: 100%; min-width: 0; height: 30px; margin: 0; padding: 5px 8px; color: #e9ebff; font-family: inherit; font-size: 10px; font-weight: 400; line-height: 18px; border: 1px solid #383e5a; border-radius: 6px; outline: none; background: #13172a; box-shadow: none; transition: border-color .15s, box-shadow .15s; color-scheme: dark; }
.building-field textarea { min-height: 42px; height: 42px; line-height: 15px; resize: vertical; }
.building-field input::placeholder, .building-field textarea::placeholder { color: #737d9f; opacity: 1; }
.building-field input:hover:not(:disabled), .building-field select:hover:not(:disabled), .building-field textarea:hover:not(:disabled) { border-color: #5c5680; }
.building-field input:focus, .building-field select:focus, .building-field textarea:focus { border-color: #a18ae9; box-shadow: 0 0 0 2px rgb(161 138 233 / 13%); }
.building-field select { appearance: none; padding-right: 24px; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='none' stroke='%23939cbe' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m4 6 4 4 4-4'/%3E%3C/svg%3E"); background-position: right 6px center; background-repeat: no-repeat; background-size: 13px; }
.building-field :disabled { opacity: .55; cursor: not-allowed; }
.building-field [aria-invalid='true'] { border-color: #e98298; }
.field-error { color: #ffadb9; font-size: 9px; line-height: 1.35; overflow-wrap: anywhere; }
.building-city { position: relative; }
.city-control { position: relative; }
.city-control input { padding-right: 27px; padding-left: 27px; }
.city-control svg { position: absolute; top: 50%; width: 13px; height: 13px; pointer-events: none; stroke: #909abd; stroke-width: 1.5; stroke-linecap: round; stroke-linejoin: round; transform: translateY(-50%); }
.city-control__icon { left: 8px; }
.city-control__arrow { right: 8px; }
.city-control.is-open .city-control__arrow { transform: translateY(-50%) rotate(180deg); }
.city-control__spinner { position: absolute; top: 9px; right: 9px; width: 12px; height: 12px; pointer-events: none; border: 1.5px solid #484362; border-top-color: #b19af9; border-radius: 50%; animation: city-search-spin .8s linear infinite; }
.city-dropdown { position: absolute; top: 47px; right: 0; left: 0; z-index: 10; overflow: hidden; padding: 3px; border: 1px solid #4a4568; border-radius: 7px; background: #20243b; box-shadow: 0 10px 24px rgb(0 0 0 / 30%); }
.city-dropdown ul { overflow-y: auto; max-height: 168px; padding: 0; margin: 0; list-style: none; overscroll-behavior: contain; }
.city-dropdown li { display: flex; justify-content: space-between; gap: 6px; padding: 7px; color: #dce0f5; font-size: 10px; line-height: 1.4; border-radius: 4px; cursor: pointer; overflow-wrap: anywhere; }
.city-dropdown li:hover, .city-dropdown li.is-active { color: #fff; background: #373051; }
.city-dropdown li[aria-selected='true'] { color: #c6b5ff; }
.city-dropdown__status { display: block; padding: 8px; color: #a5abc9; font-size: 10px; line-height: 1.4; }
@keyframes city-search-spin { to { transform: rotate(360deg); } }
@container (max-width: 200px) { .building-field-row { grid-template-columns: minmax(0, 1fr); } }
@media (prefers-reduced-motion: reduce) { .city-control__spinner { animation: none; }.building-field input, .building-field select, .building-field textarea { transition: none; } }
</style>
