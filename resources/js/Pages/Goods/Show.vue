<script setup>
defineProps({
    good: {
        type: Object,
        required: true,
    },
})
</script>

<template>
    <v-container class="py-8">
        <v-row>
            <v-col cols="12" md="5">
                <v-card rounded="xl" elevation="2" class="overflow-hidden">
                    <v-img
                        :src="good.ava_image || good.ava_thumb"
                        height="420"
                        cover
                    />
                </v-card>
            </v-col>

            <v-col cols="12" md="7">
                <div class="mb-4 text-overline text-medium-emphasis">
                    Товар
                </div>

                <h1 class="text-h3 font-weight-bold mb-4">
                    {{ good.name }}
                </h1>

                <div
                    v-if="good.description"
                    class="text-body-1"
                    style="line-height: 1.8;"
                >
                    {{ good.description }}
                </div>

                <div v-else class="text-body-1 text-medium-emphasis">
                    Описание пока не заполнено.
                </div>
            </v-col>
        </v-row>
    </v-container>
</template>
