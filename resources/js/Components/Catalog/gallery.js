const galleryBase = () => globalThis.location?.href || 'https://catalog.invalid/'

export function safeGalleryUrl(value) {
    if (typeof value !== 'string') return ''
    const candidate = value.trim()
    if (!candidate || /[\u0000-\u001f\u007f\\]/.test(candidate)) return ''
    if (!/^https?:\/\//i.test(candidate) && !/^\/(?!\/)/.test(candidate)) return ''
    try {
        const parsed = new URL(candidate, galleryBase())
        return ['http:', 'https:'].includes(parsed.protocol) && !parsed.username && !parsed.password ? candidate : ''
    } catch { return '' }
}

/** Keep original image URLs separate from thumbnails; unpublished media is visible to staff. */
export function buildCatalogPhotos(node, media = []) {
    const photos = []
    const byOriginal = new Map()
    const images = (Array.isArray(media) ? media : []).filter(item => item?.type === 'image')
    const avatarUrl = safeGalleryUrl(node?.image)
    const avatarKey = avatarUrl ? new URL(avatarUrl, galleryBase()).href : null
    // Older goods can expose only their thumbnail as the avatar. Resolve it
    // through an explicit media link instead of treating it as another original.
    const avatarSources = avatarKey ? images.filter(item => {
        const thumbnail = safeGalleryUrl(item.thumb_url)
        return thumbnail && safeGalleryUrl(item.url) && new URL(thumbnail, galleryBase()).href === avatarKey
    }) : []
    const avatarSource = avatarSources.find(item => [true, 1, '1'].includes(item.is_ava)) || avatarSources[0]
    const append = photo => {
        const url = safeGalleryUrl(photo.url)
        if (!url) return
        const key = new URL(url, galleryBase()).href
        const previous = byOriginal.get(key)
        if (previous) {
            Object.assign(previous, photo, { url: previous.url, thumbnail: safeGalleryUrl(photo.thumbnail) || previous.thumbnail, isAvatar: previous.isAvatar || photo.isAvatar })
            return
        }
        const normalized = { ...photo, url, thumbnail: safeGalleryUrl(photo.thumbnail) || url }
        byOriginal.set(key, normalized)
        photos.push(normalized)
    }
    append({
        id: 'avatar', url: avatarSource?.url || node?.image, thumbnail: node?.thumbnail_url || node?.image,
        title: '', alt: node?.name || 'Фото', caption: '', width: null, height: null, size: null,
        isPublished: null, isAvatar: true,
    })
    for (const item of images) {
        append({
            id: item.id, url: item.url, thumbnail: item.thumb_url,
            title: item.title || '', alt: item.alt || item.title || node?.name || 'Фото', caption: item.caption || '',
            width: item.width, height: item.height, size: item.size,
            isPublished: [true, 1, '1'].includes(item.is_published),
            isAvatar: [true, 1, '1'].includes(item.is_ava),
        })
    }
    return photos
}
