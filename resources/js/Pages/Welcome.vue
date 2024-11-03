<script setup>
import { Head, Link } from '@inertiajs/vue3';
import LayoutDefault from '@/Layouts/LayoutDefault.vue'

defineOptions({
    layout: LayoutDefault,
})

const props = defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
    laravelVersion: {
        type: String,
        required: true,
    },
    phpVersion: {
        type: String,
        required: true,
    },
    categories: {
        type: Object,
    }
});


</script>

<template>
    <Head title="ПИЩЕПРОМ-СЕРВЕР: пищевое сырьё, пищевые ингредиенты и добавки, стабильно, каталог пищевых технологий" />

    <v-container>
        <v-row>
            <v-col>
                <v-card>
                    <v-card-title>Лецитин</v-card-title>
                    <v-card-subtitle>подсолнечный</v-card-subtitle>
                </v-card>
            </v-col>
            <v-col>
                <v-card
                    class="mx-auto"
                    max-width="344"
                >
                    <v-img
                        height="200px"
                        src="https://storage.yandexcloud.net/cold-reserve/Goods/Glycerol/glycerol-25.jpg"
                        cover
                    ></v-img>

                    <v-card-title>
                        Глицерин
                    </v-card-title>

                    <v-card-subtitle>
                        ПК-94, Д-98
                    </v-card-subtitle>

                    <v-card-actions>
                        <v-btn
                            color="orange-lighten-2"
                            text="Информация"
                        ></v-btn>

                        <v-spacer></v-spacer>

                        <v-btn
                            :icon="show ? 'mdi-chevron-up' : 'mdi-chevron-down'"
                            @click="show = !show"
                        ></v-btn>
                    </v-card-actions>

                    <v-expand-transition>
                        <div v-show="show">
                            <v-divider></v-divider>

                            <v-card-text>
                                Пищевая добавка Е422

                                Канистры: 10 кг, 25 кг

                                Получают из глицерина натурального сырого, произведенного из растительного рапсового масла, способом дистилляции и очистки.

                                Применяется для фармакопейных целей, а также в пищевой, косметической и дургих отраслях промышленности.

                                Срок хранения до вскрытия упаковки: 5 лет
                            </v-card-text>
                        </div>
                    </v-expand-transition>
                </v-card>
            </v-col>
            <v-col></v-col>
        </v-row>
    </v-container>
</template>
