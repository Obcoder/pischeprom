import { ref } from 'vue'
import axios from 'axios'

export function useUnitFiles(unitId) {
    const files = ref([])

    const loadingFiles = ref(false)
    const uploadingFile = ref(false)
    const deletingFilePath = ref(null)
    const renamingFilePath = ref(null)

    function unitFilesUrl() {
        if (!unitId) {
            throw new Error('useUnitFiles: unitId is empty')
        }

        return `/api/units/${unitId}/files`
    }

    async function loadFiles() {
        if (!unitId) {
            console.warn('useUnitFiles: loadFiles skipped, unitId is empty')
            files.value = []
            return
        }

        loadingFiles.value = true

        try {
            const { data } = await axios.get(unitFilesUrl())
            files.value = data.files ?? data ?? []
        } catch (error) {
            console.error('Ошибка загрузки файлов:', error)
            files.value = []
        } finally {
            loadingFiles.value = false
        }
    }

    async function uploadFile(file) {
        if (!file || !unitId) return

        uploadingFile.value = true

        const formData = new FormData()
        formData.append('file', file)

        try {
            await axios.post(unitFilesUrl(), formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            })

            await loadFiles()
        } catch (error) {
            console.error('Ошибка загрузки файла:', error)
            throw error
        } finally {
            uploadingFile.value = false
        }
    }

    async function deleteFile(path) {
        if (!path || !unitId) return

        deletingFilePath.value = path

        try {
            await axios.delete(unitFilesUrl(), {
                data: {
                    path,
                },
            })

            await loadFiles()
        } catch (error) {
            console.error('Ошибка удаления файла:', error)
            throw error
        } finally {
            deletingFilePath.value = null
        }
    }

    async function renameFile(path, name) {
        if (!path || !name || !unitId) return

        renamingFilePath.value = path

        try {
            await axios.patch(`${unitFilesUrl()}/rename`, {
                path,
                name,
            })

            await loadFiles()
        } catch (error) {
            console.error('Ошибка переименования файла:', error)
            throw error
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
