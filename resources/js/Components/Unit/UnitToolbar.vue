<script setup>
defineProps({
    unit: { type: Object, required: true },
})

const emit = defineEmits(['manage'])

function open(action) {
    emit('manage', action)
}
</script>

<template>
    <section class="unit-toolbar">
        <div class="unit-toolbar__main">
            <div class="unit-toolbar__eyebrow">Unit toolbar</div>
            <strong>{{ unit.name }}</strong>
        </div>

        <div class="unit-toolbar__industries">
            <span
                v-for="industry in (unit.industries || [])"
                :key="industry.id"
                class="unit-toolbar__industry"
                :class="{ 'is-primary': industry.pivot?.is_primary }"
            >
                <b>{{ industry.code }}</b>
                <small>{{ industry.title }}</small>
            </span>
            <span v-if="!(unit.industries || []).length" class="unit-toolbar__empty">ОКВЭД не привязаны</span>
        </div>

        <div class="unit-toolbar__actions">
            <button type="button" @click="open('unit')">Unit CRUD</button>
            <button type="button" @click="open('industry-create')">New ОКВЭД</button>
            <button type="button" @click="open('attach')">Attach ОКВЭД</button>
        </div>
    </section>
</template>

<style scoped>
.unit-toolbar {
    display: grid;
    grid-template-columns: minmax(170px, 0.7fr) minmax(0, 1fr) auto;
    gap: 8px;
    align-items: center;
    margin-bottom: 10px;
    padding: 8px 10px;
    border: 1px solid rgba(0, 128, 128, 0.28);
    border-radius: 16px;
    background: linear-gradient(135deg, #043f3d 0%, #008b8b 100%);
    color: #e8fffb;
    box-shadow: 0 12px 28px rgba(0, 76, 76, 0.18);
}

.unit-toolbar__eyebrow {
    color: rgba(232, 255, 251, 0.68);
    font-size: 0.62rem;
    font-weight: 900;
    letter-spacing: 0.16em;
    text-transform: uppercase;
}

.unit-toolbar__main strong {
    display: block;
    overflow: hidden;
    font-size: 1rem;
    font-weight: 950;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.unit-toolbar__industries,
.unit-toolbar__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    align-items: center;
}

.unit-toolbar__industry,
.unit-toolbar__empty {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    max-width: 260px;
    padding: 3px 6px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.12);
    font-size: 0.68rem;
}

.unit-toolbar__industry.is-primary {
    background: #e8fffb;
    color: #034341;
}

.unit-toolbar__industry small,
.unit-toolbar__empty {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.unit-toolbar__actions button {
    border: 1px solid rgba(255, 255, 255, 0.22);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.14);
    color: inherit;
    cursor: pointer;
    font-size: 0.62rem;
    font-weight: 900;
    letter-spacing: 0.06em;
    padding: 5px 7px;
    text-transform: uppercase;
}

@media (max-width: 1180px) {
    .unit-toolbar {
        grid-template-columns: 1fr;
    }
}
</style>
