<script setup>
import { ref, onMounted } from 'vue'
import { useUnitFiles } from '@/Composables/useUnitFiles'

const props = defineProps({
    unitId: {
        type: Number,
        required: true,
    },
})

const fileInput = ref(null)

const {
    files,
    loadingFiles,
    uploadingFile,
    deletingFilePath,
    loadFiles,
    uploadFile,
    deleteFile,
} = useUnitFiles(props.unitId)

async function onFileSelected(event) {
    const file = event.target.files?.[0]
    if (!file) return

    await uploadFile(file)
    event.target.value = ''
}

onMounted(loadFiles)
</script>

<template>
    <div>
        <div class="d-flex justify-space-between align-center mb-4">
            <div class="text-subtitle-1 font-weight-medium">Files</div>

            <div class="d-flex ga-2">
                <input
                    ref="fileInput"
                    type="file"
                    class="d-none"
                    @change="onFileSelected"
                />

                <v-btn
                    color="primary"
                    variant="tonal"
                    :loading="uploadingFile"
                    @click="fileInput?.click()"
                >
                    Upload file
                </v-btn>
            </div>
        </div>

        <v-skeleton-loader
            v-if="loadingFiles"
            type="list-item-three-line"
        />

        <v-list v-else density="comfortable">
            <v-list-item
                v-for="file in files"
                :key="file.path"
            >
                <template #title>
                    <a
                        :href="file.url"
                        target="_blank"
                        class="text-decoration-none"
                    >
                        {{ file.name }}
                    </a>
                </template>

                <template #subtitle>
                    {{ Math.round((file.size || 0) / 1024) }} KB
                </template>

                <template #append>
                    <v-btn
                        icon="mdi-delete-outline"
                        variant="text"
                        color="error"
                        :loading="deletingFilePath === file.path"
                        @click="deleteFile(file.path)"
                    />
                </template>
            </v-list-item>
        </v-list>
    </div>
</template>
