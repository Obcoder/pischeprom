<script setup>
import { Head, Link } from '@inertiajs/vue3';
import LayoutDefault from '@/Layouts/LayoutDefault.vue'
import {ref} from "vue";
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
    },
    goodOfTheDay: Object,
});
let show = ref(false);

</script>

<template>
    <Head title="ПИЩЕПРОМ-СЕРВЕР: пищевое сырьё, пищевые ингредиенты и добавки, стабильно, каталог пищевых технологий" />

    <v-container>
        <v-row>
            <v-col>
                <v-card>
                    <v-img
                        height="300px"
                        src="https://storage.yandexcloud.net/cold-reserve/Goods/Lecithin/%D0%A1%D0%BD%D0%B8%D0%BC%D0%BE%D0%BA%20%D1%8D%D0%BA%D1%80%D0%B0%D0%BD%D0%B0%202024-08-21%20%D0%B2%2019.51.31.png"
                        cover
                        ></v-img>
                    <v-card-title>Лецитин</v-card-title>
                    <v-card-subtitle>подсолнечный</v-card-subtitle>
                    <v-card-text>
                        Концетрат фосфатидный подсолнечный
                        <br />

                        Лецитин играет ключевую роль в производстве шоколада. Благодаря добавлению лецитина в шоколадную массу достигается равномерное распределение жира и предотвращается появление нежелательных мазков. Это позволяет получить шоколад с более гладкой текстурой и лучшим вкусом.

                        Фосфатидные концентраты широко используются при производстве комбикормов для сельскохозяйственных животных и птицы. Это обусловлено их физиологической ролью в питании, так как они содержат до 65% фосфолипидов и являются источником незаменимых жирных кислот, витаминов группы В, Е и F. Кроме того, фосфатидные концентраты способствуют повышению продуктивности животных за счёт улучшения усвоения корма и нормализации обмена веществ. Подсолнечные фосфатидные концентраты обладают рядом преимуществ перед соевыми аналогами благодаря отсутствию антипитательных факторов (ингибиторов трипсина) и аллергенов.
                    </v-card-text>
                </v-card>
                <v-card width="auto"
                        >
                    <v-img src="https://storage.yandexcloud.net/cold-reserve/Goods/Cocoa/butter/buttercocoa-borrowed-01-330.jpg"
                           cover
                    ></v-img>
                    <v-card-title>Какао-масло</v-card-title>
                    <v-card-text>
                        <strong>Состав:</strong>
                        диглицериды и триглицериды, смешанные с жирными кислотами.
                        Содержание жирных кислот: олеиновая кислота — до 43%, стеариновая кислота — до 34%, лауриновая и пальмитиновая кислоты — до 25%, линолевая кислота — 2%, арахиновая кислота — следы.
                        <strong>Консистенция:</strong>
                        при температуре 16–18 °C масло твёрдое и ломкое. Температура плавления — 32–35 °C. При 40 °C масло прозрачное.
                        <strong>Применение:</strong>
                        используется как жировая основа для производства шоколада и других кондитерских изделий, а также в парфюмерной и фармацевтической промышленностях. В частности, служит основой для приготовления суппозиториев (слабительных и обезболивающих свечей), различных лечебных мазей. Широкое применение находит оно и в косметологии, поскольку имеет заживляющее и тонизирующее действие.
                        Какао-масло бывает двух видов:
                        <strong>Нерафинированное масло</strong> – продукт, выжатый из бобов шоколадного дерева и не прошедший последующую очистку.
                        <strong>Рафинированное масло</strong> – продукт, прошедший специальную очистку. Оно более мягкое и деликатное, включает меньшее количество жирных кислот. Такое масло светлее, запах у него полностью отсутствует. После процедуры рафинации повышается срок хранения продукта.
                    </v-card-text>
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

            <v-col>
                <v-card title="Акция дня">
                    <v-card-text>
                        {{goodOfTheDay.good.name}}
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
    </v-container>
</template>
