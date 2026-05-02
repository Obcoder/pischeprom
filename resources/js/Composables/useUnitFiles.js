import { ref, unref } from 'vue'
import axios from 'axios'

export function useUnitFiles(unitId) {
    const files = ref([])

    const loadingFiles = ref(false)
    const uploadingFile = ref(false)
    const deletingFilePath = ref(null)
    const renamingFilePath = ref(null)

    function getUnitId() {
        const value = unref(unitId)

        if (!value) {
            return null
        }

        const id = Number(value)

        if (!Number.isInteger(id) || id <= 0) {
            console.error('useUnitFiles ожидает unit.id, но получил:', value)
            return null
        }

        return id
    }

    function getFilesUrl() {
        const id = getUnitId()

        if (!id) {
            return null
        }

        return `/api/units/${id}/files`
    }

    async function loadFiles() {
        const url = getFilesUrl()

        if (!url) {
            console.warn('useUnitFiles: loadFiles skipped, unitId is invalid')
            files.value = []
            return
        }

        loadingFiles.value = true

        try {
            const { data } = await axios.get(url)
            files.value = data.files ?? data ?? []
        } catch (error) {
            console.error('Ошибка загрузки файлов:', {
                url,
                status: error.response?.status,
                data: error.response?.data,
                error,
            })

            files.value = []
        } finally {
            loadingFiles.value = false
        }
    }

    async function uploadFile(file) {
        const url = getFilesUrl()

        if (!file || !url) return

        uploadingFile.value = true

        const formData = new FormData()
        formData.append('file', file)

        try {
            await axios.post(url, formData, {
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
        const url = getFilesUrl()

        if (!path || !url) return

        deletingFilePath.value = path

        try {
            await axios.delete(url, {
                data: {
                    path,
                },
            })

            await loadFiles()
        } finally {
            deletingFilePath.value = null
        }
    }

    async function renameFile(path, name) {
        const url = getFilesUrl()

        if (!path || !name || !url) return

        renamingFilePath.value = path

        try {
            await axios.patch(`${url}/rename`, {
                path,
                name,
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
