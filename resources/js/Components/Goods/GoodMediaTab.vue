<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from "vue";
import { useGoodMedia } from "@/Composables/useGoodMedia";

const props = defineProps({
    good: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits([
    "changed",
    "ava-updated",
]);

const {
    media,
    folders,

    loading,
    loadingFolders,
    uploading,
    saving,

    deleting,
    publishing,
    settingAva,
    processing,

    fetchMedia,
    fetchFolders,

    createFolder,
    updateFolder,
    deleteFolder,

    uploadMedia,
    updateMedia,
    renameMedia,
    toggleMediaPublish,
    setMediaAva,
    processVideoMedia,
    deleteMedia,
} = useGoodMedia(props.good.id);

// --------------------------------------------------
// STATE
// --------------------------------------------------
const filterType = ref("all");
const filterFolderId = ref("all");
const filterPublished = ref("all");
const search = ref("");

const errorMessage = ref("");

const dialogUpload = ref(false);
const dialogFolder = ref(false);
const dialogEdit = ref(false);
const dialogRename = ref(false);
const dialogDeleteMedia = ref(false);
const dialogDeleteFolder = ref(false);

const selectedMedia = ref(null);
const selectedFolder = ref(null);

// --------------------------------------------------
// FORMS
// --------------------------------------------------
const uploadForm = reactive({
    file: null,
    folder_id: null,
    title: "",
    alt: "",
    caption: "",
    is_published: false,
});

const folderForm = reactive({
    id: null,
    parent_id: null,
    name: "",
    sort_order: 100,
    is_archive: false,
});

const editForm = reactive({
    id: null,
    folder_id: null,
    title: "",
    alt: "",
    caption: "",
    sort_order: 100,
    is_published: false,
});

const renameForm = reactive({
    id: null,
    file_name: "",
});

// --------------------------------------------------
// COMPUTED
// --------------------------------------------------
const folderItems = computed(() => {
    return [
        {
            title: "Без папки",
            value: null,
        },
        ...folders.value.map((folder) => ({
            title: folder.is_archive
                ? `🗄️ ${folder.name}`
                : `📁 ${folder.name}`,
            value: folder.id,
        })),
    ];
});

const filterFolderItems = computed(() => {
    return [
        {
            title: "Все папки",
            value: "all",
        },
        {
            title: "Без папки",
            value: "none",
        },
        ...folders.value.map((folder) => ({
            title: folder.is_archive
                ? `🗄️ ${folder.name}`
                : `📁 ${folder.name}`,
            value: folder.id,
        })),
    ];
});

const filteredMedia = computed(() => {
    let items = [...media.value];

    if (filterType.value !== "all") {
        items = items.filter((item) => item.type === filterType.value);
    }

    if (filterFolderId.value === "none") {
        items = items.filter((item) => !item.folder_id);
    } else if (filterFolderId.value !== "all") {
        items = items.filter((item) => {
            return Number(item.folder_id) === Number(filterFolderId.value);
        });
    }

    if (filterPublished.value === "published") {
        items = items.filter((item) => !!item.is_published);
    }

    if (filterPublished.value === "hidden") {
        items = items.filter((item) => !item.is_published);
    }

    const q = search.value.trim().toLowerCase();

    if (q) {
        items = items.filter((item) => {
            return [
                item.title,
                item.alt,
                item.caption,
                item.file_name,
                item.original_name,
                item.path,
                item.folder?.name,
            ]
                .filter(Boolean)
                .some((value) => String(value).toLowerCase().includes(q));
        });
    }

    return items;
});

const hasActiveVideoProcessing = computed(() => {
    return media.value.some((item) => {
        return item.type === "video" &&
            ["pending", "queued", "processing"].includes(item.processing_status);
    });
});

// --------------------------------------------------
// HELPERS
// --------------------------------------------------
function extractFile(value) {
    if (value instanceof File) {
        return value;
    }

    if (Array.isArray(value) && value[0] instanceof File) {
        return value[0];
    }

    return null;
}

function formatSize(bytes) {
    const value = Number(bytes || 0);

    if (!value) return "—";

    if (value < 1024) {
        return `${value} B`;
    }

    if (value < 1024 * 1024) {
        return `${(value / 1024).toFixed(1)} KB`;
    }

    return `${(value / 1024 / 1024).toFixed(1)} MB`;
}

function formatDate(value) {
    if (!value) return "—";

    try {
        return new Intl.DateTimeFormat("ru-RU", {
            day: "2-digit",
            month: "short",
            year: "numeric",
            hour: "2-digit",
            minute: "2-digit",
        }).format(new Date(value));
    } catch {
        return value;
    }
}

function mediaPreview(item) {
    if (item.type === "image") {
        return item.thumb_url || item.url || null;
    }

    if (item.type === "video") {
        return item.poster_url || item.thumb_url || null;
    }

    return null;
}

function mediaTypeIcon(item) {
    if (item.type === "image") return "mdi-image";
    if (item.type === "video") return "mdi-video";
    if (item.type === "document") return "mdi-file-document";

    return "mdi-file";
}

function mediaOpenUrl(item) {
    return item.video_mp4_url || item.video_hls_url || item.url;
}

function handleError(error) {
    console.error(error);

    const errors = error?.response?.data?.errors || null;

    if (errors) {
        errorMessage.value = Object.values(errors)
            .flat()
            .join("\n");

        return;
    }

    errorMessage.value =
        error?.response?.data?.message ||
        error?.message ||
        "Произошла ошибка";
}

async function reload() {
    errorMessage.value = "";

    try {
        await Promise.all([
            fetchFolders(),
            fetchMedia(),
        ]);
    } catch (error) {
        handleError(error);
    }
}

function formatDuration(seconds) {
    const value = Number(seconds || 0);

    if (!value) {
        return "—";
    }

    const minutes = Math.floor(value / 60);
    const restSeconds = Math.round(value % 60);

    if (minutes <= 0) {
        return `${restSeconds} сек.`;
    }

    return `${minutes} мин. ${String(restSeconds).padStart(2, "0")} сек.`;
}

function mediaResolution(item) {
    if (!item.width || !item.height) {
        return "—";
    }

    return `${item.width}×${item.height}`;
}

// --------------------------------------------------
// UPLOAD
// --------------------------------------------------
function resetUploadForm() {
    uploadForm.file = null;
    uploadForm.folder_id = null;
    uploadForm.title = "";
    uploadForm.alt = props.good.name || "";
    uploadForm.caption = "";
    uploadForm.is_published = false;
}

function openUpload() {
    resetUploadForm();
    dialogUpload.value = true;
}

async function submitUpload() {
    const file = extractFile(uploadForm.file);

    if (!file) {
        return;
    }

    errorMessage.value = "";

    try {
        const saved = await uploadMedia({
            file,
            folder_id: uploadForm.folder_id,
            title: uploadForm.title,
            alt: uploadForm.alt,
            caption: uploadForm.caption,
            is_published: uploadForm.is_published,
        });

        dialogUpload.value = false;

        emit("changed", saved);
    } catch (error) {
        handleError(error);
    }
}

// --------------------------------------------------
// FOLDERS
// --------------------------------------------------
function resetFolderForm() {
    folderForm.id = null;
    folderForm.parent_id = null;
    folderForm.name = "";
    folderForm.sort_order = 100;
    folderForm.is_archive = false;
}

function openCreateFolder(isArchive = false) {
    resetFolderForm();

    folderForm.is_archive = isArchive;
    folderForm.name = isArchive ? "Архив" : "";

    dialogFolder.value = true;
}

function openEditFolder(folder) {
    folderForm.id = folder.id;
    folderForm.parent_id = folder.parent_id || null;
    folderForm.name = folder.name || "";
    folderForm.sort_order = folder.sort_order ?? 100;
    folderForm.is_archive = !!folder.is_archive;

    dialogFolder.value = true;
}

async function submitFolder() {
    errorMessage.value = "";

    const payload = {
        parent_id: folderForm.parent_id,
        name: folderForm.name,
        sort_order: folderForm.sort_order || 100,
        is_archive: folderForm.is_archive,
    };

    try {
        if (folderForm.id) {
            await updateFolder(folderForm.id, payload);
        } else {
            await createFolder(payload);
        }

        dialogFolder.value = false;
    } catch (error) {
        handleError(error);
    }
}

function askDeleteFolder(folder) {
    selectedFolder.value = folder;
    dialogDeleteFolder.value = true;
}

async function confirmDeleteFolder() {
    if (!selectedFolder.value?.id) {
        return;
    }

    errorMessage.value = "";

    try {
        await deleteFolder(selectedFolder.value.id);

        dialogDeleteFolder.value = false;
        selectedFolder.value = null;
    } catch (error) {
        handleError(error);
    }
}

// --------------------------------------------------
// MEDIA EDIT
// --------------------------------------------------
function openEditMedia(item) {
    selectedMedia.value = item;

    editForm.id = item.id;
    editForm.folder_id = item.folder_id || null;
    editForm.title = item.title || "";
    editForm.alt = item.alt || "";
    editForm.caption = item.caption || "";
    editForm.sort_order = item.sort_order ?? 100;
    editForm.is_published = !!item.is_published;

    dialogEdit.value = true;
}

async function submitEditMedia() {
    if (!editForm.id) {
        return;
    }

    errorMessage.value = "";

    try {
        const saved = await updateMedia(editForm.id, {
            folder_id: editForm.folder_id,
            title: editForm.title,
            alt: editForm.alt,
            caption: editForm.caption,
            sort_order: editForm.sort_order || 100,
            is_published: editForm.is_published,
        });

        dialogEdit.value = false;

        emit("changed", saved);
    } catch (error) {
        handleError(error);
    }
}

function openRenameMedia(item) {
    selectedMedia.value = item;

    renameForm.id = item.id;
    renameForm.file_name = item.file_name || item.original_name || "";

    dialogRename.value = true;
}

async function submitRenameMedia() {
    if (!renameForm.id || !renameForm.file_name) {
        return;
    }

    errorMessage.value = "";

    try {
        const saved = await renameMedia(renameForm.id, renameForm.file_name);

        dialogRename.value = false;

        emit("changed", saved);
    } catch (error) {
        handleError(error);
    }
}

async function handleTogglePublish(item) {
    errorMessage.value = "";

    try {
        const saved = await toggleMediaPublish(item);

        emit("changed", saved);
    } catch (error) {
        handleError(error);
    }
}

async function handleSetAva(item) {
    errorMessage.value = "";

    try {
        const saved = await setMediaAva(item);

        emit("ava-updated", saved);
        emit("changed", saved);
    } catch (error) {
        handleError(error);
    }
}

async function handleProcessVideo(item) {
    errorMessage.value = "";

    try {
        const saved = await processVideoMedia(item);

        startAutoRefresh();

        emit("changed", saved);
    } catch (error) {
        handleError(error);
    }
}

function askDeleteMedia(item) {
    selectedMedia.value = item;
    dialogDeleteMedia.value = true;
}

async function confirmDeleteMedia() {
    if (!selectedMedia.value?.id) {
        return;
    }

    errorMessage.value = "";

    try {
        await deleteMedia(selectedMedia.value.id);

        dialogDeleteMedia.value = false;
        selectedMedia.value = null;

        emit("changed");
    } catch (error) {
        handleError(error);
    }
}

let refreshTimer = null;

function startAutoRefresh() {
    if (refreshTimer) {
        return;
    }

    refreshTimer = window.setInterval(async () => {
        if (!hasActiveVideoProcessing.value) {
            stopAutoRefresh();
            return;
        }

        await fetchMedia();
    }, 5000);
}

function stopAutoRefresh() {
    if (!refreshTimer) {
        return;
    }

    window.clearInterval(refreshTimer);
    refreshTimer = null;
}

// --------------------------------------------------
// LIFECYCLE
// --------------------------------------------------
onMounted(async () => {
    await reload();

    if (hasActiveVideoProcessing.value) {
        startAutoRefresh();
    }
});

onBeforeUnmount(() => {
    stopAutoRefresh();
});
</script>

<template>
    <v-card>
        <v-card-title class="d-flex align-center justify-space-between">
            <span>Media товара</span>

            <div class="d-flex align-center ga-2">
                <v-btn
                    icon="mdi-refresh"
                    variant="text"
                    :loading="loading || loadingFolders"
                    @click="reload"
                />

                <v-btn
                    color="blue-grey"
                    variant="tonal"
                    prepend-icon="mdi-folder-plus"
                    @click="openCreateFolder(false)"
                >
                    Папка
                </v-btn>

                <v-btn
                    color="brown"
                    variant="tonal"
                    prepend-icon="mdi-archive-plus"
                    @click="openCreateFolder(true)"
                >
                    Архив
                </v-btn>

                <v-btn
                    color="deep-purple-darken-1"
                    variant="tonal"
                    prepend-icon="mdi-cloud-upload"
                    @click="openUpload"
                >
                    Загрузить
                </v-btn>
            </div>
        </v-card-title>

        <v-card-text>
            <v-alert
                v-if="errorMessage"
                type="error"
                variant="tonal"
                class="mb-4"
                closable
                @click:close="errorMessage = ''"
            >
                {{ errorMessage }}
            </v-alert>

            <v-alert
                type="info"
                variant="tonal"
                class="mb-4"
            >
                Фото сразу создают thumbnail. Видео пока загружается как original со статусом
                <strong>pending</strong>. Обработку видео через FFmpeg подключим следующим шагом.
            </v-alert>

            <!-- FILTERS -->
            <v-row class="mb-2">
                <v-col cols="12" md="4">
                    <v-text-field
                        v-model="search"
                        label="Поиск media"
                        variant="solo-inverted"
                        density="compact"
                        clearable
                        hide-details
                    />
                </v-col>

                <v-col cols="12" md="2">
                    <v-select
                        v-model="filterType"
                        :items="[
                            { title: 'Все типы', value: 'all' },
                            { title: 'Фото', value: 'image' },
                            { title: 'Видео', value: 'video' },
                            { title: 'Документы', value: 'document' },
                        ]"
                        label="Тип"
                        variant="solo-inverted"
                        density="compact"
                        hide-details
                    />
                </v-col>

                <v-col cols="12" md="3">
                    <v-select
                        v-model="filterFolderId"
                        :items="filterFolderItems"
                        label="Папка"
                        variant="solo-inverted"
                        density="compact"
                        hide-details
                    />
                </v-col>

                <v-col cols="12" md="3">
                    <v-select
                        v-model="filterPublished"
                        :items="[
                            { title: 'Все', value: 'all' },
                            { title: 'Опубликованные', value: 'published' },
                            { title: 'Скрытые', value: 'hidden' },
                        ]"
                        label="Публикация"
                        variant="solo-inverted"
                        density="compact"
                        hide-details
                    />
                </v-col>
            </v-row>

            <v-row>
                <!-- FOLDERS -->
                <v-col cols="12" lg="3">
                    <v-card variant="tonal">
                        <v-card-title class="text-subtitle-1">
                            Папки
                        </v-card-title>

                        <v-card-text>
                            <v-list
                                density="compact"
                                lines="two"
                            >
                                <v-list-item
                                    v-for="folder in folders"
                                    :key="folder.id"
                                >
                                    <template #prepend>
                                        <v-icon
                                            :icon="folder.is_archive ? 'mdi-archive' : 'mdi-folder'"
                                        />
                                    </template>

                                    <v-list-item-title>
                                        {{ folder.name }}
                                    </v-list-item-title>

                                    <v-list-item-subtitle>
                                        {{ folder.path }}
                                    </v-list-item-subtitle>

                                    <template #append>
                                        <v-btn
                                            icon="mdi-pencil"
                                            size="x-small"
                                            variant="text"
                                            @click="openEditFolder(folder)"
                                        />

                                        <v-btn
                                            icon="mdi-delete"
                                            size="x-small"
                                            variant="text"
                                            color="red"
                                            :loading="!!deleting[`folder-${folder.id}`]"
                                            @click="askDeleteFolder(folder)"
                                        />
                                    </template>
                                </v-list-item>

                                <v-list-item v-if="!folders.length">
                                    <v-list-item-title class="text-medium-emphasis">
                                        Папок пока нет.
                                    </v-list-item-title>
                                </v-list-item>
                            </v-list>
                        </v-card-text>
                    </v-card>
                </v-col>

                <!-- MEDIA GRID -->
                <v-col cols="12" lg="9">
                    <v-progress-linear
                        v-if="loading"
                        indeterminate
                        class="mb-3"
                    />

                    <v-row v-if="filteredMedia.length">
                        <v-col
                            v-for="item in filteredMedia"
                            :key="item.id"
                            cols="12"
                            sm="6"
                            md="4"
                            xl="3"
                        >
                            <v-card
                                class="h-100 media-card"
                                :class="{ 'media-card--ava': item.is_ava }"
                            >
                                <div class="media-preview">
                                    <v-img
                                        v-if="mediaPreview(item)"
                                        :src="mediaPreview(item)"
                                        height="180"
                                        cover
                                    >
                                        <template #placeholder>
                                            <div class="d-flex align-center justify-center fill-height">
                                                <v-progress-circular indeterminate />
                                            </div>
                                        </template>
                                    </v-img>

                                    <div
                                        v-else
                                        class="media-empty-preview"
                                    >
                                        <v-icon
                                            :icon="mediaTypeIcon(item)"
                                            size="56"
                                            class="mb-2"
                                        />

                                        <div class="text-subtitle-2">
                                            {{ item.type === "video" ? "Видео ожидает обработки" : "Нет preview" }}
                                        </div>

                                        <div class="text-caption text-medium-emphasis mt-1">
                                            {{ item.processing_status || "pending" }}
                                        </div>
                                    </div>

                                    <div class="media-preview__badges">
                                        <v-chip
                                            size="x-small"
                                            variant="flat"
                                        >
                                            <v-icon
                                                :icon="mediaTypeIcon(item)"
                                                size="14"
                                                class="mr-1"
                                            />

                                            {{ item.type }}
                                        </v-chip>

                                        <v-chip
                                            v-if="item.is_ava"
                                            size="x-small"
                                            color="amber"
                                            variant="flat"
                                        >
                                            ava
                                        </v-chip>

                                        <v-chip
                                            v-if="item.type === 'video'"
                                            size="x-small"
                                            :color="item.processing_status === 'done' ? 'green' : 'orange'"
                                            variant="flat"
                                        >
                                            {{ item.processing_status || "pending" }}
                                        </v-chip>
                                    </div>
                                </div>

                                <v-card-text>
                                    <div class="font-weight-medium text-truncate">
                                        {{ item.title || item.file_name || item.original_name || `media #${item.id}` }}
                                    </div>

                                    <div class="text-caption text-medium-emphasis text-truncate">
                                        {{ item.folder?.name || "Без папки" }}
                                    </div>

                                    <div class="text-caption text-medium-emphasis">
                                        {{ formatSize(item.size) }} · {{ formatDate(item.created_at) }}
                                    </div>

                                    <div
                                        v-if="item.type === 'video'"
                                        class="text-caption text-medium-emphasis"
                                    >
                                        {{ formatDuration(item.duration_seconds) }} · {{ mediaResolution(item) }}
                                    </div>

                                    <div
                                        v-if="item.alt"
                                        class="text-caption text-medium-emphasis text-truncate mt-1"
                                    >
                                        alt: {{ item.alt }}
                                    </div>

                                    <div class="d-flex align-center justify-space-between mt-3">
                                        <v-switch
                                            :model-value="!!item.is_published"
                                            :loading="!!publishing[item.id]"
                                            density="compact"
                                            color="green"
                                            inset
                                            hide-details
                                            @update:model-value="() => handleTogglePublish(item)"
                                        />

                                        <div class="d-flex align-center">
                                            <v-btn
                                                v-if="item.type === 'image'"
                                                icon="mdi-star"
                                                size="small"
                                                variant="text"
                                                color="amber-darken-2"
                                                :loading="!!settingAva[item.id]"
                                                @click="handleSetAva(item)"
                                            />

                                            <v-btn
                                                v-if="item.type === 'video'"
                                                icon="mdi-cog-play"
                                                size="small"
                                                variant="text"
                                                color="deep-purple"
                                                :loading="!!processing[item.id]"
                                                :disabled="['queued', 'processing'].includes(item.processing_status)"
                                                @click="handleProcessVideo(item)"
                                            />

                                            <v-btn
                                                icon="mdi-open-in-new"
                                                size="small"
                                                variant="text"
                                                :href="mediaOpenUrl(item)"
                                                target="_blank"
                                            />

                                            <v-menu>
                                                <template #activator="{ props: menuProps }">
                                                    <v-btn
                                                        v-bind="menuProps"
                                                        icon="mdi-dots-vertical"
                                                        size="small"
                                                        variant="text"
                                                    />
                                                </template>

                                                <v-list density="compact">
                                                    <v-list-item
                                                        prepend-icon="mdi-pencil"
                                                        title="Редактировать"
                                                        @click="openEditMedia(item)"
                                                    />

                                                    <v-list-item
                                                        prepend-icon="mdi-rename-box"
                                                        title="Переименовать файл"
                                                        @click="openRenameMedia(item)"
                                                    />

                                                    <v-list-item
                                                        prepend-icon="mdi-delete"
                                                        title="Удалить"
                                                        base-color="red"
                                                        @click="askDeleteMedia(item)"
                                                    />
                                                </v-list>
                                            </v-menu>
                                        </div>
                                    </div>
                                </v-card-text>
                            </v-card>
                        </v-col>
                    </v-row>

                    <v-alert
                        v-else
                        type="info"
                        variant="tonal"
                    >
                        Media-файлов пока нет.
                    </v-alert>
                </v-col>
            </v-row>
        </v-card-text>

        <!-- UPLOAD DIALOG -->
        <v-dialog
            v-model="dialogUpload"
            width="760"
        >
            <v-card>
                <v-card-title>Загрузить media</v-card-title>

                <v-card-text>
                    <v-file-input
                        v-model="uploadForm.file"
                        label="Файл"
                        variant="outlined"
                        density="compact"
                        accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx"
                        prepend-icon="mdi-paperclip"
                        show-size
                        clearable
                    />

                    <v-row>
                        <v-col cols="12" md="6">
                            <v-select
                                v-model="uploadForm.folder_id"
                                :items="folderItems"
                                item-title="title"
                                item-value="value"
                                label="Папка"
                                variant="outlined"
                                density="compact"
                                clearable
                            />
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-switch
                                v-model="uploadForm.is_published"
                                label="Опубликовать"
                                color="green"
                                inset
                                hide-details
                            />
                        </v-col>

                        <v-col cols="12">
                            <v-text-field
                                v-model="uploadForm.title"
                                label="Title"
                                variant="outlined"
                                density="compact"
                            />
                        </v-col>

                        <v-col cols="12">
                            <v-text-field
                                v-model="uploadForm.alt"
                                label="Alt"
                                variant="outlined"
                                density="compact"
                            />
                        </v-col>

                        <v-col cols="12">
                            <v-textarea
                                v-model="uploadForm.caption"
                                label="Caption"
                                variant="outlined"
                                density="compact"
                                rows="3"
                            />
                        </v-col>
                    </v-row>
                </v-card-text>

                <v-card-actions>
                    <v-btn
                        variant="text"
                        @click="dialogUpload = false"
                    >
                        Закрыть
                    </v-btn>

                    <v-btn
                        color="deep-purple-darken-1"
                        variant="tonal"
                        :loading="uploading"
                        :disabled="!extractFile(uploadForm.file)"
                        @click="submitUpload"
                    >
                        Загрузить
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- FOLDER DIALOG -->
        <v-dialog
            v-model="dialogFolder"
            width="620"
        >
            <v-card>
                <v-card-title>
                    {{ folderForm.id ? "Редактировать папку" : "Создать папку" }}
                </v-card-title>

                <v-card-text>
                    <v-text-field
                        v-model="folderForm.name"
                        label="Название папки"
                        variant="outlined"
                        density="compact"
                    />

                    <v-select
                        v-model="folderForm.parent_id"
                        :items="folderItems"
                        item-title="title"
                        item-value="value"
                        label="Родительская папка"
                        variant="outlined"
                        density="compact"
                        clearable
                    />

                    <v-text-field
                        v-model="folderForm.sort_order"
                        label="Сортировка"
                        type="number"
                        variant="outlined"
                        density="compact"
                    />

                    <v-switch
                        v-model="folderForm.is_archive"
                        label="Это архив"
                        color="brown"
                        inset
                    />
                </v-card-text>

                <v-card-actions>
                    <v-btn
                        variant="text"
                        @click="dialogFolder = false"
                    >
                        Закрыть
                    </v-btn>

                    <v-btn
                        color="deep-purple-darken-1"
                        variant="tonal"
                        :loading="saving"
                        :disabled="!folderForm.name"
                        @click="submitFolder"
                    >
                        Сохранить
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- EDIT MEDIA DIALOG -->
        <v-dialog
            v-model="dialogEdit"
            width="760"
        >
            <v-card>
                <v-card-title>Редактировать media</v-card-title>

                <v-card-text>
                    <v-select
                        v-model="editForm.folder_id"
                        :items="folderItems"
                        item-title="title"
                        item-value="value"
                        label="Папка"
                        variant="outlined"
                        density="compact"
                        clearable
                    />

                    <v-text-field
                        v-model="editForm.title"
                        label="Title"
                        variant="outlined"
                        density="compact"
                    />

                    <v-text-field
                        v-model="editForm.alt"
                        label="Alt"
                        variant="outlined"
                        density="compact"
                    />

                    <v-textarea
                        v-model="editForm.caption"
                        label="Caption"
                        variant="outlined"
                        density="compact"
                        rows="3"
                    />

                    <v-text-field
                        v-model="editForm.sort_order"
                        label="Сортировка"
                        type="number"
                        variant="outlined"
                        density="compact"
                    />

                    <v-switch
                        v-model="editForm.is_published"
                        label="Опубликовано"
                        color="green"
                        inset
                    />
                </v-card-text>

                <v-card-actions>
                    <v-btn
                        variant="text"
                        @click="dialogEdit = false"
                    >
                        Закрыть
                    </v-btn>

                    <v-btn
                        color="deep-purple-darken-1"
                        variant="tonal"
                        :loading="saving"
                        @click="submitEditMedia"
                    >
                        Сохранить
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- RENAME MEDIA DIALOG -->
        <v-dialog
            v-model="dialogRename"
            width="560"
        >
            <v-card>
                <v-card-title>Переименовать файл</v-card-title>

                <v-card-text>
                    <v-text-field
                        v-model="renameForm.file_name"
                        label="Новое имя файла"
                        variant="outlined"
                        density="compact"
                        hint="Расширение можно оставить или не писать. Сервер сохранит исходное расширение."
                        persistent-hint
                    />
                </v-card-text>

                <v-card-actions>
                    <v-btn
                        variant="text"
                        @click="dialogRename = false"
                    >
                        Закрыть
                    </v-btn>

                    <v-btn
                        color="deep-purple-darken-1"
                        variant="tonal"
                        :loading="saving"
                        :disabled="!renameForm.file_name"
                        @click="submitRenameMedia"
                    >
                        Переименовать
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- DELETE MEDIA DIALOG -->
        <v-dialog
            v-model="dialogDeleteMedia"
            width="520"
        >
            <v-card>
                <v-card-title>Удалить media?</v-card-title>

                <v-card-text>
                    Удалить файл:
                    <strong>{{ selectedMedia?.file_name || selectedMedia?.original_name }}</strong>
                    ?
                </v-card-text>

                <v-card-actions>
                    <v-btn
                        variant="text"
                        @click="dialogDeleteMedia = false"
                    >
                        Отмена
                    </v-btn>

                    <v-btn
                        color="red"
                        variant="tonal"
                        :loading="selectedMedia?.id ? !!deleting[`media-${selectedMedia.id}`] : false"
                        @click="confirmDeleteMedia"
                    >
                        Удалить
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- DELETE FOLDER DIALOG -->
        <v-dialog
            v-model="dialogDeleteFolder"
            width="520"
        >
            <v-card>
                <v-card-title>Удалить папку?</v-card-title>

                <v-card-text>
                    Удалить папку:
                    <strong>{{ selectedFolder?.name }}</strong>
                    ?

                    <div class="text-caption text-medium-emphasis mt-2">
                        Папку можно удалить только если в ней нет файлов и вложенных папок.
                    </div>
                </v-card-text>

                <v-card-actions>
                    <v-btn
                        variant="text"
                        @click="dialogDeleteFolder = false"
                    >
                        Отмена
                    </v-btn>

                    <v-btn
                        color="red"
                        variant="tonal"
                        :loading="selectedFolder?.id ? !!deleting[`folder-${selectedFolder.id}`] : false"
                        @click="confirmDeleteFolder"
                    >
                        Удалить
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-card>
</template>

<style scoped>
.media-card {
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.media-card--ava {
    border-color: rgb(var(--v-theme-warning));
}

.media-preview {
    position: relative;
}

.media-preview__badges {
    position: absolute;
    top: 8px;
    left: 8px;
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
}

.media-empty-preview {
    height: 180px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: rgba(var(--v-theme-surface-variant), 0.45);
    color: rgba(var(--v-theme-on-surface), 0.65);
}
</style>
