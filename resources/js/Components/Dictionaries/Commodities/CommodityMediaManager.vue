<script setup>
import { ref, watch } from 'vue'
import { useCommodityMedia } from '@/Composables/useCommodityMedia.js'

const props = defineProps({
    commodityId: {
        type: Number,
        required: true,
    },
    items: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits([
    'refresh',
])

const {
    media,
    loadingMedia,
    uploadingMedia,
    deletingMedia,

    indexCommodityMedia,
    uploadCommodityMedia,
    renameCommodityMedia,
    setCommodityAva,
    deleteCommodityMedia,
} = useCommodityMedia()

const files = ref([])

const renameDialog = ref(false)
const renameItem = ref(null)
const renameFilename = ref('')

watch(
    () => props.items,
    (value) => {
        media.value = value || []
    },
    {
        immediate: true,
        deep: true,
    }
)

async function refresh() {
    await indexCommodityMedia(props.commodityId)
    emit('refresh')
}

async function upload() {
    if (!files.value?.length) {
        return
    }

    await uploadCommodityMedia(props.commodityId, files.value)

    files.value = []

    emit('refresh')
}

function openRename(item) {
    renameItem.value = item
    renameFilename.value = item.filename
    renameDialog.value = true
}

async function rename() {
    if (!renameItem.value?.id) {
        return
    }

    await renameCommodityMedia(
        props.commodityId,
        renameItem.value.id,
        renameFilename.value
    )

    renameDialog.value = false
    renameItem.value = null
    renameFilename.value = ''

    await refresh()
}

async function makeAva(item) {
    await setCommodityAva(props.commodityId, item.id)
    await refresh()
}

async function remove(item) {
    if (!confirm(`Удалить изображение "${item.filename}"?`)) {
        return
    }

    await deleteCommodityMedia(props.commodityId, item.id)
    await refresh()
}

function formatSize(size) {
    if (!size) {
        return '0 KB'
    }

    if (size < 1024 * 1024) {
        return `${Math.round(size / 1024)} KB`
    }

    return `${(size / 1024 / 1024).toFixed(2)} MB`
}
</script>

<template>
    <v-card class="border rounded">
        <v-card-title class="d-flex align-center justify-space-between">
            <span>Media Commodity</span>

            <v-btn
                icon="mdi-refresh"
                variant="text"
                density="compact"
                :loading="loadingMedia"
                @click="refresh"
            />
        </v-card-title>

        <v-card-text>
            <v-row align="center">
                <v-col cols="12" md="9">
                    <v-file-input
                        v-model="files"
                        label="Добавить изображения"
                        accept="image/*"
                        multiple
                        show-size
                        clearable
                        variant="outlined"
                        density="compact"
                        hide-details
                    />
                </v-col>

                <v-col cols="12" md="3">
                    <v-btn
                        block
                        text="Загрузить"
                        color="primary"
                        variant="elevated"
                        :loading="uploadingMedia"
                        :disabled="!files?.length"
                        @click="upload"
                    />
                </v-col>
            </v-row>

            <v-row class="mt-4">
                <v-col
                    v-for="item in media"
                    :key="item.id"
                    cols="12"
                    sm="6"
                    md="4"
                    lg="3"
                >
                    <v-card
                        class="h-100 border rounded"
                        :class="{ 'border-primary': item.is_ava }"
                    >
                        <v-img
                            :src="item.url"
                            height="160"
                            cover
                            class="bg-grey-lighten-3"
                        />

                        <v-card-text class="py-2">
                            <div class="d-flex align-center ga-2">
                                <v-chip
                                    v-if="item.is_ava"
                                    size="x-small"
                                    color="primary"
                                    variant="elevated"
                                >
                                    ava
                                </v-chip>

                                <span class="text-caption text-truncate">
                                    {{ item.filename }}
                                </span>
                            </div>

                            <div class="text-caption text-grey mt-1">
                                {{ formatSize(item.size) }}
                            </div>
                        </v-card-text>

                        <v-card-actions class="pt-0">
                            <v-btn
                                text="ava"
                                size="small"
                                variant="tonal"
                                color="primary"
                                :disabled="item.is_ava"
                                @click="makeAva(item)"
                            />

                            <v-btn
                                icon="mdi-pencil"
                                size="small"
                                variant="text"
                                @click="openRename(item)"
                            />

                            <v-spacer />

                            <v-btn
                                icon="mdi-delete"
                                size="small"
                                variant="text"
                                color="error"
                                :loading="deletingMedia"
                                @click="remove(item)"
                            />
                        </v-card-actions>
                    </v-card>
                </v-col>

                <v-col v-if="!media.length" cols="12">
                    <v-alert type="info" variant="tonal" density="compact">
                        Изображений пока нет.
                    </v-alert>
                </v-col>
            </v-row>
        </v-card-text>
    </v-card>

    <v-dialog v-model="renameDialog" width="560">
        <v-card>
            <v-card-title>Переименовать файл</v-card-title>

            <v-card-text>
                <v-text-field
                    v-model="renameFilename"
                    label="Новое имя файла"
                    variant="outlined"
                    density="compact"
                    hide-details
                />
            </v-card-text>

            <v-card-actions>
                <v-spacer />

                <v-btn
                    text="Отмена"
                    variant="text"
                    @click="renameDialog = false"
                />

                <v-btn
                    text="Сохранить"
                    color="primary"
                    variant="elevated"
                    @click="rename"
                />
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
