import { ref } from "vue";
import axios from "axios";
import { route } from "ziggy-js";

export function useGoodMedia(goodId) {
    const media = ref([]);
    const folders = ref([]);

    const loading = ref(false);
    const loadingFolders = ref(false);
    const uploading = ref(false);
    const saving = ref(false);

    const deleting = ref({});
    const publishing = ref({});
    const settingAva = ref({});
    const processing = ref({});

    async function fetchMedia() {
        loading.value = true;

        try {
            const { data } = await axios.get(
                route("api.goods.media.index", {
                    good: goodId,
                })
            );

            media.value = Array.isArray(data) ? data : [];

            return media.value;
        } finally {
            loading.value = false;
        }
    }

    async function fetchFolders() {
        loadingFolders.value = true;

        try {
            const { data } = await axios.get(
                route("api.goods.media-folders.index", {
                    good: goodId,
                })
            );

            folders.value = Array.isArray(data) ? data : [];

            return folders.value;
        } finally {
            loadingFolders.value = false;
        }
    }

    async function createFolder(payload) {
        saving.value = true;

        try {
            const { data } = await axios.post(
                route("api.goods.media-folders.store", {
                    good: goodId,
                }),
                payload
            );

            folders.value.push(data);

            folders.value.sort((a, b) => {
                if (Number(a.is_archive) !== Number(b.is_archive)) {
                    return Number(a.is_archive) - Number(b.is_archive);
                }

                const orderA = Number(a.sort_order || 100);
                const orderB = Number(b.sort_order || 100);

                if (orderA !== orderB) {
                    return orderA - orderB;
                }

                return String(a.name || "").localeCompare(String(b.name || ""));
            });

            return data;
        } finally {
            saving.value = false;
        }
    }

    async function updateFolder(folderId, payload) {
        saving.value = true;

        try {
            const { data } = await axios.patch(
                route("api.goods.media-folders.update", {
                    good: goodId,
                    folder: folderId,
                }),
                payload
            );

            folders.value = folders.value.map((item) =>
                item.id === data.id ? data : item
            );

            return data;
        } finally {
            saving.value = false;
        }
    }

    async function deleteFolder(folderId) {
        deleting.value[`folder-${folderId}`] = true;

        try {
            await axios.delete(
                route("api.goods.media-folders.destroy", {
                    good: goodId,
                    folder: folderId,
                })
            );

            folders.value = folders.value.filter((item) => item.id !== folderId);
        } finally {
            deleting.value[`folder-${folderId}`] = false;
        }
    }

    async function uploadMedia(payload) {
        uploading.value = true;

        try {
            const fd = new FormData();

            fd.append("file", payload.file);

            if (payload.folder_id) {
                fd.append("folder_id", String(payload.folder_id));
            }

            fd.append("title", payload.title || "");
            fd.append("alt", payload.alt || "");
            fd.append("caption", payload.caption || "");
            fd.append("is_published", payload.is_published ? "1" : "0");

            const { data } = await axios.post(
                route("api.goods.media.store", {
                    good: goodId,
                }),
                fd,
                {
                    headers: {
                        "Content-Type": "multipart/form-data",
                    },
                }
            );

            media.value.unshift(data);

            return data;
        } finally {
            uploading.value = false;
        }
    }

    async function updateMedia(mediaId, payload) {
        saving.value = true;

        try {
            const { data } = await axios.patch(
                route("api.goods.media.update", {
                    good: goodId,
                    media: mediaId,
                }),
                payload
            );

            media.value = media.value.map((item) =>
                item.id === data.id ? data : item
            );

            return data;
        } finally {
            saving.value = false;
        }
    }

    async function renameMedia(mediaId, fileName) {
        saving.value = true;

        try {
            const { data } = await axios.patch(
                route("api.goods.media.rename", {
                    good: goodId,
                    media: mediaId,
                }),
                {
                    file_name: fileName,
                }
            );

            media.value = media.value.map((item) =>
                item.id === data.id ? data : item
            );

            return data;
        } finally {
            saving.value = false;
        }
    }

    async function toggleMediaPublish(item) {
        publishing.value[item.id] = true;

        const previous = !!item.is_published;
        item.is_published = !previous;

        try {
            const { data } = await axios.patch(
                route("api.goods.media.publish", {
                    good: goodId,
                    media: item.id,
                }),
                {
                    is_published: item.is_published,
                }
            );

            media.value = media.value.map((row) =>
                row.id === data.id ? data : row
            );

            return data;
        } catch (error) {
            item.is_published = previous;
            throw error;
        } finally {
            publishing.value[item.id] = false;
        }
    }

    async function setMediaAva(item) {
        settingAva.value[item.id] = true;

        try {
            const { data } = await axios.patch(
                route("api.goods.media.ava", {
                    good: goodId,
                    media: item.id,
                })
            );

            media.value = media.value.map((row) => ({
                ...row,
                is_ava: row.id === data.id,
                is_published: row.id === data.id
                    ? data.is_published
                    : row.is_published,
            }));

            return data;
        } finally {
            settingAva.value[item.id] = false;
        }
    }

    async function processVideoMedia(item) {
        processing.value[item.id] = true;

        const previousStatus = item.processing_status;

        item.processing_status = "queued";

        try {
            const { data } = await axios.patch(
                route("api.goods.media.process", {
                    good: goodId,
                    media: item.id,
                })
            );

            media.value = media.value.map((row) =>
                row.id === data.id ? data : row
            );

            return data;
        } catch (error) {
            item.processing_status = previousStatus;
            throw error;
        } finally {
            processing.value[item.id] = false;
        }
    }

    async function deleteMedia(mediaId) {
        deleting.value[`media-${mediaId}`] = true;

        try {
            await axios.delete(
                route("api.goods.media.destroy", {
                    good: goodId,
                    media: mediaId,
                })
            );

            media.value = media.value.filter((item) => item.id !== mediaId);
        } finally {
            deleting.value[`media-${mediaId}`] = false;
        }
    }

    return {
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
    };
}
