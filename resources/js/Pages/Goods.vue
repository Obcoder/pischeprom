<script setup>
import LayoutDefault from '@/Layouts/LayoutDefault.vue'
import {logo} from "@/Pages/Helpers/consts.js";
import {ref} from "vue";
import {Link, router} from "@inertiajs/vue3";
import {useHead} from "@vueuse/head";
import {usePublicGoodUrl} from "@/Composables/usePublicGoodUrl";

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
})

const { goodPublicUrl } = usePublicGoodUrl()
const searchGoods = ref(props.filters.search || '')
let searchTimer = null

function indexGoods() {
    clearTimeout(searchTimer)

    searchTimer = setTimeout(() => {
        router.get(route('public.goods.index'), {
            search: searchGoods.value || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        })
    }, 250)
}

useHead({
    title: `Товары, которые Вы можете приобрести на ПИЩЕПРОМ-СЕРВЕРЕ: пищевое сырьё, пищевые ингредиенты, пищевые добавки`,
    meta: [
        {
            name: 'description',
            content: `Товары, которые Вы можете приобрести на ПИЩЕПРОМ-СЕРВЕРЕ: пищевое сырьё, пищевые ингредиенты, пищевые добавки`,
        }
    ]
})
</script>

<template>
    <v-container fluid>
        <v-row>
            <v-col>
                <v-text-field v-model="searchGoods"
                              @input="indexGoods"
                              density="compact"
                              variant="outlined"
                              label="Поиск по товарам"
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
