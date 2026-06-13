import { ref, unref } from 'vue'
import axios from 'axios'

export function useUnitFiles(unitId) {
    const files = ref([])
    const folders = ref([])
    const currentFolder = ref('')
    const root = ref('')

    const loadingFiles = ref(false)
    const uploadingFile = ref(false)
    const deletingFilePath = ref(null)
    const renamingFilePath = ref(null)
    const movingPath = ref(null)
    const creatingFolder = ref(false)
    const fileManagerError = ref('')

    function getUnitId() {
        const value = unref(unitId)
        const id = Number(value)
        return Number.isInteger(id) && id > 0 ? id : null
    }

    function getFilesUrl() {
        const id = getUnitId()
        return id ? `/api/units/${id}/files` : null
    }

    async function loadFiles(folder = currentFolder.value) {
        const url = getFilesUrl()

        if (!url) {
            files.value = []
            folders.value = []
            return
        }

        loadingFiles.value = true
        fileManagerError.value = ''

        try {
            const { data } = await axios.get(url, { params: { folder: folder || undefined } })
            files.value = data.files ?? (Array.isArray(data) ? data : [])
            folders.value = data.folders ?? []
            currentFolder.value = data.folder ?? folder ?? ''
            root.value = data.root ?? root.value
        } catch (error) {
            console.error('Ошибка загрузки файлов:', error.response?.data || error)
            fileManagerError.value = error.response?.data?.message || 'Не удалось загрузить файлы'
            files.value = []
            folders.value = []
        } finally {
            loadingFiles.value = false
        }
    }

    async function openFolder(folderPath) {
        const relative = folderPath && root.value ? String(folderPath).replace(`${root.value}/`, '') : folderPath
        await loadFiles(relative || '')
    }

    async function uploadFile(file) {
        const url = getFilesUrl()
        if (!file || !url) return

        uploadingFile.value = true
        fileManagerError.value = ''

        const formData = new FormData()
        formData.append('file', file)
        if (currentFolder.value) formData.append('folder', currentFolder.value)

        try {
            await axios.post(url, formData, { headers: { 'Content-Type': 'multipart/form-data' } })
            await loadFiles()
        } catch (error) {
            fileManagerError.value = error.response?.data?.message || 'Не удалось загрузить файл'
            console.error('Ошибка загрузки файла:', error.response?.data || error)
        } finally {
            uploadingFile.value = false
        }
    }

    async function createFolder(name) {
        const url = getFilesUrl()
        if (!name || !url) return

        creatingFolder.value = true
        fileManagerError.value = ''

        try {
            await axios.post(`${url}/folders`, { name, parent: currentFolder.value || null })
            await loadFiles()
        } catch (error) {
            fileManagerError.value = error.response?.data?.message || 'Не удалось создать папку'
            console.error('Ошибка создания папки:', error.response?.data || error)
        } finally {
            creatingFolder.value = false
        }
    }

    async function deletePath(path, type = 'file') {
        const url = getFilesUrl()
        if (!path || !url) return

        deletingFilePath.value = path
        fileManagerError.value = ''

        try {
            await axios.delete(url, { data: { path, type } })
            await loadFiles()
        } catch (error) {
            fileManagerError.value = error.response?.data?.message || 'Не удалось удалить объект'
        } finally {
            deletingFilePath.value = null
        }
    }

    async function renamePath(path, name, type = 'file') {
        const url = getFilesUrl()
        if (!path || !name || !url) return

        renamingFilePath.value = path
        fileManagerError.value = ''

        try {
            await axios.patch(`${url}/rename`, { path, name, type })
            await loadFiles()
        } catch (error) {
            fileManagerError.value = error.response?.data?.message || 'Не удалось переименовать объект'
        } finally {
            renamingFilePath.value = null
        }
    }

    async function movePath(path, targetFolder, type = 'file') {
        const url = getFilesUrl()
        if (!path || !url) return

        movingPath.value = path
        fileManagerError.value = ''

        try {
            await axios.patch(`${url}/move`, { path, target_folder: targetFolder || null, type })
            await loadFiles()
        } catch (error) {
            fileManagerError.value = error.response?.data?.message || 'Не удалось переместить объект'
        } finally {
            movingPath.value = null
        }
    }

    return {
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
        deleteFile: (path) => deletePath(path, 'file'),
        renameFile: (path, name) => renamePath(path, name, 'file'),
    }
}
