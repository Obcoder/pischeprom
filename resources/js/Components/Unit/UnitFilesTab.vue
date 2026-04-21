<script setup>
import { ref, onMounted, computed } from 'vue'
import { useUnitFiles } from '@/Composables/useUnitFiles'

const props = defineProps({
    unitId: {
        type: Number,
        required: true,
    },
})

const fileInput = ref(null)

const renameDialog = ref(false)
const fileToRename = ref(null)
const newBaseFileName = ref('')
const fileExtension = ref('')

const {
    files,
    loadingFiles,
    uploadingFile,
    deletingFilePath,
    renamingFilePath,
    loadFiles,
    uploadFile,
    deleteFile,
    renameFile,
} = useUnitFiles(props.unitId)

const isRenameDisabled = computed(() => {
    const trimmedBaseName = newBaseFileName.value.trim()

    if (!fileToRename.value) return true
    if (!trimmedBaseName) return true
    if (/^\.{1,}$/.test(trimmedBaseName)) return true

    return false
})

async function onFileSelected(event) {
    const file = event.target.files?.[0]
    if (!file) return

    await uploadFile(file)
    event.target.value = ''
}

function splitFileName(fileName) {
    const lastDotIndex = fileName.lastIndexOf('.')

    if (lastDotIndex <= 0) {
        return {
            baseName: fileName,
            extension: '',
        }
    }

    return {
        baseName: fileName.slice(0, lastDotIndex),
        extension: fileName.slice(lastDotIndex),
    }
}

function openRenameDialog(file) {
    fileToRename.value = file

    const { baseName, extension } = splitFileName(file.name)

    newBaseFileName.value = baseName
    fileExtension.value = extension
    renameDialog.value = true
}

function closeRenameDialog() {
    renameDialog.value = false
    fileToRename.value = null
    newBaseFileName.value = ''
    fileExtension.value = ''
}

async function submitRename() {
    if (isRenameDisabled.value) return

    const file = fileToRename.value   // ✅ сохраняем
    const trimmedBaseName = newBaseFileName.value.trim()

    if (!file) return

    const finalName = `${trimmedBaseName}${fileExtension.value}`

    await renameFile(file.path, finalName)

    closeRenameDialog()
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
                    <div class="d-flex align-center ga-1">
                        <v-btn
                            icon="mdi-pencil-outline"
                            variant="text"
                            color="primary"
                            :loading="renamingFilePath === file.path"
                            @click="openRenameDialog(file)"
                        />

                        <v-btn
                            icon="mdi-delete-outline"
                            variant="text"
                            color="error"
                            :loading="deletingFilePath === file.path"
                            @click="deleteFile(file.path)"
                        />
                    </div>
                </template>
            </v-list-item>
        </v-list>

        <v-dialog v-model="renameDialog" max-width="600">
            <v-card rounded="xl">
                <v-card-title>Rename file</v-card-title>

                <v-card-text>
                    <v-text-field
                        v-model="newBaseFileName"
                        label="New file name"
                        variant="outlined"
                        density="comfortable"
                        autofocus
                    />

                    <div class="text-caption text-medium-emphasis mt-2">
                        Extension: {{ fileExtension || 'none' }}
                    </div>

                    <div
                        v-if="newBaseFileName && isRenameDisabled"
                        class="text-caption text-error mt-2"
                    >
                        Enter a valid file name.
                    </div>
                </v-card-text>

                <v-card-actions class="justify-end">
                    <v-btn variant="text" @click="closeRenameDialog">
                        Cancel
                    </v-btn>

                    <v-btn
                        color="primary"
                        :loading="fileToRename && renamingFilePath === fileToRename.path"
                        :disabled="isRenameDisabled"
                        @click="submitRename"
                    >
                        Save
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>
