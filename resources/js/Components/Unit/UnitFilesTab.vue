<script setup>
import { computed, onMounted, ref, toRef, watch } from 'vue'
import { useUnitFiles } from '@/Composables/useUnitFiles'

const props = defineProps({
    unitId: { type: Number, required: true },
})

const emit = defineEmits(['send-file'])
const unitIdRef = toRef(props, 'unitId')
const fileInput = ref(null)
const folderName = ref('')
const renameDialog = ref(false)
const moveDialog = ref(false)
const selectedItem = ref(null)
const newName = ref('')
const targetFolder = ref('')

const {
    files,
    folders,
    currentFolder,
    root,
    loadingFiles,
    uploadingFile,
    deletingFilePath,
    renamingFilePath,
    movingPath,
    creatingFolder,
    fileManagerError,
    loadFiles,
    openFolder,
    uploadFile,
    createFolder,
    deletePath,
    renamePath,
    movePath,
} = useUnitFiles(unitIdRef)

const breadcrumbs = computed(() => {
    const parts = currentFolder.value ? currentFolder.value.split('/').filter(Boolean) : []
    return [
        { label: 'root', folder: '' },
        ...parts.map((part, index) => ({
            label: part,
            folder: parts.slice(0, index + 1).join('/'),
        })),
    ]
})

async function onFileSelected(event) {
    const file = event.target.files?.[0]
    if (!file) return
    await uploadFile(file)
    event.target.value = ''
}

async function submitFolder() {
    const name = folderName.value.trim()
    if (!name) return
    await createFolder(name)
    folderName.value = ''
}

function itemName(item) {
    return item?.name || ''
}

function openRename(item, type) {
    selectedItem.value = { ...item, type }
    newName.value = itemName(item)
    renameDialog.value = true
}

async function submitRename() {
    if (!selectedItem.value || !newName.value.trim()) return
    await renamePath(selectedItem.value.path, newName.value.trim(), selectedItem.value.type)
    renameDialog.value = false
}

function openMove(item, type) {
    selectedItem.value = { ...item, type }
    targetFolder.value = currentFolder.value
    moveDialog.value = true
}

async function submitMove() {
    if (!selectedItem.value) return
    await movePath(selectedItem.value.path, targetFolder.value, selectedItem.value.type)
    moveDialog.value = false
}

function parentFolder() {
    if (!currentFolder.value) return ''
    const parts = currentFolder.value.split('/').filter(Boolean)
    parts.pop()
    return parts.join('/')
}

function sizeKb(file) {
    return Math.round((file.size || 0) / 1024)
}

onMounted(() => loadFiles())
watch(() => props.unitId, (value, oldValue) => {
    if (value && value !== oldValue) loadFiles('')
})
</script>

<template>
    <div class="unit-files-manager">
        <div class="unit-files-manager__toolbar">
            <div class="unit-files-manager__path">
                <button
                    v-for="crumb in breadcrumbs"
                    :key="crumb.folder || 'root'"
                    type="button"
                    @click="loadFiles(crumb.folder)"
                >
                    {{ crumb.label }}
                </button>
            </div>

            <div class="unit-files-manager__actions">
                <input ref="fileInput" type="file" class="d-none" @change="onFileSelected" />
                <v-text-field v-model="folderName" label="Folder" variant="solo-filled" density="compact" hide-details class="unit-files-manager__folder-input" />
                <button type="button" :disabled="!folderName.trim() || creatingFolder" @click="submitFolder">New folder</button>
                <button type="button" :disabled="uploadingFile" @click="fileInput?.click()">Upload</button>
            </div>
        </div>

        <div v-if="fileManagerError" class="unit-files-manager__error">
            {{ fileManagerError }}
        </div>

        <v-progress-linear v-if="loadingFiles" indeterminate color="teal" />

        <div class="unit-files-manager__grid">
            <article v-if="currentFolder" class="unit-file-item unit-file-item--folder" @click="loadFiles(parentFolder())">
                <v-icon icon="mdi-arrow-up" size="18" />
                <strong>..</strong>
                <span>parent</span>
            </article>

            <article v-for="folder in folders" :key="folder.path" class="unit-file-item unit-file-item--folder">
                <button type="button" class="unit-file-item__main" @click="openFolder(folder.path)">
                    <v-icon icon="mdi-folder" size="18" />
                    <strong>{{ folder.name }}</strong>
                    <span>folder</span>
                </button>
                <div class="unit-file-item__actions">
                    <button type="button" @click="openMove(folder, 'folder')">Move</button>
                    <button type="button" @click="openRename(folder, 'folder')">Rename</button>
                    <button type="button" class="is-danger" :disabled="deletingFilePath === folder.path" @click="deletePath(folder.path, 'folder')">Delete</button>
                </div>
            </article>

            <article v-for="file in files" :key="file.path" class="unit-file-item">
                <a :href="file.url" target="_blank" class="unit-file-item__main">
                    <v-icon icon="mdi-file-outline" size="18" />
                    <strong>{{ file.name }}</strong>
                    <span>{{ file.extension || 'file' }} / {{ sizeKb(file) }} KB</span>
                </a>
                <div class="unit-file-item__actions">
                    <button type="button" @click="emit('send-file', file)">Email</button>
                    <a :href="file.url" target="_blank">Download</a>
                    <button type="button" :disabled="movingPath === file.path" @click="openMove(file, 'file')">Move</button>
                    <button type="button" :disabled="renamingFilePath === file.path" @click="openRename(file, 'file')">Rename</button>
                    <button type="button" class="is-danger" :disabled="deletingFilePath === file.path" @click="deletePath(file.path, 'file')">Delete</button>
                </div>
            </article>

            <div v-if="!folders.length && !files.length && !loadingFiles" class="unit-files-manager__empty">
                В этой папке пока нет файлов.
            </div>
        </div>

        <v-dialog v-model="renameDialog" max-width="560">
            <v-card rounded="xl">
                <v-card-title>Rename</v-card-title>
                <v-card-text>
                    <v-text-field v-model="newName" label="New name" variant="outlined" density="compact" autofocus />
                </v-card-text>
                <v-card-actions class="justify-end">
                    <v-btn variant="text" @click="renameDialog = false">Cancel</v-btn>
                    <v-btn color="teal-darken-3" :disabled="!newName" @click="submitRename">Save</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="moveDialog" max-width="560">
            <v-card rounded="xl">
                <v-card-title>Move</v-card-title>
                <v-card-text>
                    <v-text-field v-model="targetFolder" label="Target folder relative to unit root" variant="outlined" density="compact" placeholder="docs/contracts" />
                    <div class="text-caption text-medium-emphasis">Root: {{ root }}</div>
                </v-card-text>
                <v-card-actions class="justify-end">
                    <v-btn variant="text" @click="moveDialog = false">Cancel</v-btn>
                    <v-btn color="teal-darken-3" @click="submitMove">Move</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<style scoped>
.unit-files-manager {
    display: grid;
    gap: 8px;
}

.unit-files-manager__toolbar {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 8px;
    align-items: center;
}

.unit-files-manager__path,
.unit-files-manager__actions,
.unit-file-item__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    align-items: center;
}

.unit-files-manager button,
.unit-file-item__actions a {
    border: 1px solid rgba(0, 128, 128, 0.22);
    border-radius: 999px;
    background: #00796b;
    color: #effefa;
    cursor: pointer;
    font-size: 0.62rem;
    font-weight: 900;
    letter-spacing: 0.06em;
    padding: 5px 7px;
    text-decoration: none;
    text-transform: uppercase;
}

.unit-files-manager button:disabled {
    cursor: wait;
    opacity: 0.5;
}

.unit-files-manager__folder-input {
    width: 148px;
}

.unit-files-manager__grid {
    display: grid;
    gap: 5px;
    max-height: 380px;
    overflow: auto;
}

.unit-file-item {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 8px;
    align-items: center;
    padding: 6px;
    border: 1px solid rgba(0, 128, 128, 0.13);
    border-radius: 10px;
    background: #fff;
}

.unit-file-item--folder {
    background: #f0fdfa;
}

.unit-file-item__main {
    display: grid;
    grid-template-columns: 20px minmax(0, 1fr) auto;
    gap: 6px;
    align-items: center;
    min-width: 0;
    color: #243b3a;
    text-decoration: none;
}

button.unit-file-item__main {
    width: 100%;
    border: 0;
    border-radius: 0;
    background: transparent;
    color: #243b3a;
    padding: 0;
    text-align: left;
    text-transform: none;
}

.unit-file-item__main strong {
    overflow: hidden;
    font-size: 0.76rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.unit-file-item__main span {
    color: #607d8b;
    font-size: 0.66rem;
}

.unit-file-item__actions .is-danger {
    background: #8a1238;
    border-color: rgba(138, 18, 56, 0.24);
}

.unit-files-manager__empty {
    padding: 10px;
    border: 1px dashed rgba(0, 128, 128, 0.28);
    border-radius: 10px;
    color: #607d8b;
    font-size: 0.74rem;
}

.unit-files-manager__error {
    padding: 6px 8px;
    border: 1px solid rgba(149, 19, 61, 0.24);
    border-radius: 9px;
    background: #fff1f4;
    color: #95133d;
    font-size: 0.72rem;
    font-weight: 800;
}

@media (max-width: 980px) {
    .unit-files-manager__toolbar,
    .unit-file-item {
        grid-template-columns: 1fr;
    }
}
</style>
