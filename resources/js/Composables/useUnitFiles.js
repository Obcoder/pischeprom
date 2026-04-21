import { ref } from 'vue'
import axios from 'axios'
import { route } from 'ziggy-js'

export function useUnitFiles(unitId) {
    const files = ref([])
    const loadingFiles = ref(false)
    const uploadingFile = ref(false)
    const deletingFilePath = ref(null)
    const renamingFilePath = ref(null)

    async function loadFiles() {
        loadingFiles.value = true
        try {
            const { data } = await axios.get(route('api.units.files.index', unitId))
            files.value = Array.isArray(data) ? data : []
        } finally {
            loadingFiles.value = false
        }
    }

    async function uploadFile(file) {
        if (!file) return

        uploadingFile.value = true
        try {
            const formData = new FormData()
            formData.append('file', file)

            await axios.post(route('api.units.files.store', unitId), formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            })

            await loadFiles()
        } finally {
            uploadingFile.value = false
        }
    }

    async function deleteFile(path) {
        deletingFilePath.value = path
        try {
            await axios.delete(route('api.units.files.destroy', unitId), {
                data: { path },
            })

            await loadFiles()
        } finally {
            deletingFilePath.value = null
        }
    }

    async function renameFile(path, newName) {
        renamingFilePath.value = path
        try {
            await axios.patch(route('api.units.files.rename', unitId), {
                path,
                new_name: newName,
            })

            await loadFiles()
        } finally {
            renamingFilePath.value = null
        }
    }

    return {
        files,
        loadingFiles,
        uploadingFile,
        deletingFilePath,
        renamingFilePath,
        loadFiles,
        uploadFile,
        deleteFile,
        renameFile,
    }
}
