<script setup>
import LayoutDefault from '@/Layouts/LayoutDefault.vue'
import {logo} from "@/Pages/Helpers/consts.js";
import {computed, ref} from "vue";
import {Link, router} from "@inertiajs/vue3";
import {useHead} from "@vueuse/head";
import {usePublicGoodUrl} from "@/Composables/usePublicGoodUrl";
import {useAppRoute} from "@/Composables/useAppRoute";

defineOptions({
    layout: LayoutDefault,
})

const props = defineProps({
    goods: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
        }),
    },
    field: {
        type: Object,
        default: null,
    },
})

const { goodPublicUrl } = usePublicGoodUrl()
const { route: appRoute } = useAppRoute()
const searchGoods = ref(props.filters.search || '')
let searchTimer = null

const isFieldPage = computed(() => Boolean(props.field?.id))
const pageTitle = computed(() => {
    if (isFieldPage.value) {
        return `${props.field.title || props.field.name} — подборка товаров ПИЩЕПРОМ-СЕРВЕР`
    }

    return 'Товары, которые Вы можете приобрести на ПИЩЕПРОМ-СЕРВЕРЕ: пищевое сырьё, пищевые ингредиенты, пищевые добавки'
})

const pageDescription = computed(() => {
    if (isFieldPage.value) {
        return props.field.description || `Товары подборки ${props.field.title || props.field.name}`
    }

    return 'Товары, которые Вы можете приобрести на ПИЩЕПРОМ-СЕРВЕРЕ: пищевое сырьё, пищевые ингредиенты, пищевые добавки'
})

function indexGoods() {
    clearTimeout(searchTimer)

    searchTimer = setTimeout(() => {
        const target = isFieldPage.value
            ? appRoute('public.fields.show', props.field.slug || props.field.id)
            : appRoute('public.goods.index')

        router.get(target, {
            search: searchGoods.value || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        })
    }, 250)
}

useHead({
    title: pageTitle,
    meta: [
        {
            name: 'description',
            content: pageDescription,
        }
    ]
})
</script>

<template>
    <v-container fluid>
        <v-row v-if="isFieldPage" class="mb-2">
            <v-col cols="12">
                <v-card rounded="xl" elevation="1" class="field-heading-card">
                    <v-card-text>
                        <div class="text-caption text-medium-emphasis mb-1">Подборка</div>
                        <h1 class="text-h5 font-weight-bold mb-2">
                            {{ field.title || field.name }}
                        </h1>
                        <p class="mb-0 text-body-2 text-medium-emphasis">
                            {{ field.description || 'Товары, привязанные к этой подборке.' }}
                        </p>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <v-row>
            <v-col>
                <v-text-field v-model="searchGoods"
                              @input="indexGoods"
                              density="compact"
                              variant="outlined"
                              :label="isFieldPage ? 'Поиск по товарам подборки' : 'Поиск по товарам'"
                              placeholder="вводите название товара"
                ></v-text-field>
            </v-col>
            <v-col></v-col>
            <v-col></v-col>
        </v-row>
        <v-row>
            <v-col cols="12"
                   class="flex flex-row flex-wrap"
            >
                <v-card v-for="good in goods"
                        :key="good.id"
                        class="w-72 mr-2 mb-2"
                        rounded
                >
                    <v-img :src="good.ava_image || logo"></v-img>
                    <v-card-subtitle class="font-sans text-wrap">
                        {{good.name}}
                    </v-card-subtitle>
                    <v-card-actions>
                        <v-btn text="заказать"
                               density="comfortable"
                               class="ms-2"
                               variant="tonal"
                        ></v-btn>
                        <v-divider opacity="80"
                                   color="grey"
                                   vertical
                        ></v-divider>
                        <Link :href="goodPublicUrl(good)">
                            <v-btn text="подробнее"
                                   density="comfortable"
                                   class="ms-2"
                                   variant="flat"
                            ></v-btn>
                        </Link>
                    </v-card-actions>
                </v-card>

                <v-alert
                    v-if="!goods.length"
                    type="info"
                    variant="tonal"
                    class="w-100"
                >
                    По заданному поиску товары не найдены.
                </v-alert>
            </v-col>
        </v-row>
    </v-container>
</template>

<style scoped>
.field-heading-card {
    background:
        radial-gradient(circle at 100% 0%, rgba(220, 122, 81, 0.14), transparent 28%),
        linear-gradient(135deg, #fffdfa 0%, #f5f0e8 100%);
    border: 1px solid rgba(71, 118, 90, 0.14);
}
</style>
