<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            productsCount: 0,
            goodsCount: 0,
        }),
    },
})

const search = ref('')

function submitSearch() {
    router.get('/catalog', {
        search: search.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}
</script>

<template>
    <section class="py-10 py-md-14">
        <v-container>
            <v-row align="center">
                <v-col cols="12" md="7">
                    <div class="text-overline text-primary mb-3">
                        B2B-маркетплейс
                    </div>

                    <h1 class="text-h4 text-md-h2 font-weight-bold mb-4">
                        Пищевое сырьё, ингредиенты и добавки для производства
                    </h1>

                    <p class="text-body-1 text-medium-emphasis mb-6">
                        Каталог товаров для пищевой промышленности:
                        характеристики, фото, направления применения и быстрый выход на нужные позиции.
                    </p>

                    <div class="d-flex flex-column flex-md-row ga-3 mb-6">
                        <v-text-field
                            v-model="search"
                            label="Поиск по товарам"
                            placeholder="Например: лецитин, глицерин, какао-масло"
                            variant="outlined"
                            density="comfortable"
                            hide-details
                            clearable
                            @keyup.enter="submitSearch"
                        />

                        <v-btn
                            color="primary"
                            size="large"
                            height="56"
                            @click="submitSearch"
                        >
                            Найти
                        </v-btn>
                    </div>

                    <div class="d-flex flex-wrap ga-6">
                        <div>
                            <div class="text-h5 font-weight-bold">
                                {{ stats.productsCount }}
                            </div>
                            <div class="text-body-2 text-medium-emphasis">
                                товарных наименований
                            </div>
                        </div>

                        <div>
                            <div class="text-h5 font-weight-bold">
                                {{ stats.goodsCount }}
                            </div>
                            <div class="text-body-2 text-medium-emphasis">
                                товаров в каталоге
                            </div>
                        </div>
                    </div>
                </v-col>

                <v-col cols="12" md="5">
                    <v-card rounded="xl" elevation="2">
                        <v-img
                            src="https://storage.yandexcloud.net/cold-reserve/GIElems/shutterstock_1476761888-3.webp"
                            min-height="360"
                            cover
                        />
                    </v-card>
                </v-col>
            </v-row>
        </v-container>
    </section>
</template>
