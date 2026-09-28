<script setup>
defineProps({
    number: { type: String, default: '' },
    type: { type: String, default: 'apartment' },
    errors: { type: Object, default: () => ({}) },
    disabled: Boolean,
})
defineEmits(['update:number', 'update:type'])

function errorText(error) {
    return Array.isArray(error) ? error[0] : error
}
</script>

<template>
    <div class="delivery-apartment-fields">
        <label>
            <span>Тип помещения</span>
            <select :value="type" name="delivery_apartment_type" :disabled="disabled" @change="$emit('update:type', $event.target.value)">
                <option value="apartment">Квартира</option>
                <option value="office">Офис</option>
                <option value="premise">Помещение</option>
            </select>
            <small v-if="errors.delivery_apartment_type">{{ errorText(errors.delivery_apartment_type) }}</small>
        </label>
        <label>
            <span>Номер (необязательно)</span>
            <input
                :value="number"
                name="delivery_apartment_number"
                class="ym-disable-keys"
                type="text"
                maxlength="50"
                placeholder="Например: 12А"
                :disabled="disabled"
                :aria-invalid="Boolean(errors.delivery_apartment_number)"
                @input="$emit('update:number', $event.target.value)"
            >
            <small v-if="errors.delivery_apartment_number">{{ errorText(errors.delivery_apartment_number) }}</small>
        </label>
    </div>
</template>

<style scoped>
.delivery-apartment-fields { display: flex; flex-wrap: wrap; gap: 12px; width: 100%; }
label { display: flex; flex: 1 1 150px; flex-direction: column; gap: 6px; font-size: 13px; }
input, select { width: 100%; border: 1px solid #d6d3d1; border-radius: 8px; padding: 10px 12px; background: #fff; color: #292524; font: inherit; }
small { color: #b91c1c; }
</style>
