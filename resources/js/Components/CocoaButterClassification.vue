<template>
    <v-container fluid>
        <v-row class="mb-6">
            <v-col cols="12">
                <h2 class="text-h5 font-weight-bold">Классификация какао-масла</h2>
                <v-tabs v-model="activeTab" class="mt-4">
                    <v-tab v-for="(tab, index) in tabs" :key="index">{{ tab.label }}</v-tab>
                </v-tabs>
            </v-col>
        </v-row>

        <v-row>
            <v-col
                v-for="(item, index) in filteredItems"
                :key="index"
                cols="12"
                sm="6"
                md="4"
                lg="3"
            >
                <v-card class="rounded-xl h-100 d-flex flex-column justify-space-between" elevation="3">
                    <v-card-title class="text-subtitle-1 font-weight-bold">{{ item.title }}</v-card-title>
                    <v-card-text class="text-body-2">{{ item.description }}</v-card-text>
                </v-card>
            </v-col>
        </v-row>
    </v-container>
</template>

<script setup>
import { ref, computed } from 'vue'

const activeTab = ref(0)

const tabs = [
    { label: 'Способ получения', key: 'extraction' },
    { label: 'Степень очистки', key: 'purity' },
    { label: 'Назначение', key: 'purpose' },
    { label: 'Страна происхождения', key: 'country' }
]

const items = {
    extraction: [
        {
            title: 'Холодный отжим',
            description: 'Сохраняет аромат, цвет и максимум полезных веществ. Используется в премиальной косметике и еде.'
        },
        {
            title: 'Горячий отжим / экстракция',
            description: 'Производится с нагревом или растворителями. Более доступное, но теряет часть натуральных свойств.'
        }
    ],
    purity: [
        {
            title: 'Нерафинированное',
            description: 'Натуральный шоколадный аромат, желтоватый цвет. Идеально для органической продукции.'
        },
        {
            title: 'Рафинированное',
            description: 'Без запаха и цвета, с увеличенным сроком хранения. Отлично подходит для нейтральных рецептур.'
        }
    ],
    purpose: [
        {
            title: 'Пищевое',
            description: 'Используется в производстве шоколада, глазури, конфет, выпечки. Соответствует пищевым стандартам.'
        },
        {
            title: 'Косметическое',
            description: 'Подходит для мыла, бальзамов, кремов и масок. Богато жирными кислотами и антиоксидантами.'
        },
        {
            title: 'Фармацевтическое',
            description: 'Применяется как основа для суппозиториев, мазей и других лекарственных форм.'
        }
    ],
    country: [
        {
            title: 'Венесуэла',
            description: 'Мягкий вкус, ароматный профиль, высококачественные сорта (например, Criollo).'
        },
        {
            title: 'Гана',
            description: 'Глубокий шоколадный вкус и стабильность характеристик. Часто используется в производстве шоколада.'
        },
        {
            title: 'Эквадор',
            description: 'Фруктовые и цветочные ноты во вкусе. Используется в премиальных шоколадных продуктах.'
        },
        {
            title: 'Индонезия',
            description: 'Нейтральный вкус, подходит для массового производства и коммерческой косметики.'
        }
    ]
}

const filteredItems = computed(() => {
    const currentKey = tabs[activeTab.value].key
    return items[currentKey] || []
})
</script>

<style scoped>
.v-card {
    transition: transform 0.2s ease;
}
.v-card:hover {
    transform: translateY(-4px);
}
</style>
