export function publicCategoryUrl(category, publicUrls, legacyUrl) {
    return publicUrls?.[String(category?.id)] || category?.public_url || legacyUrl
}
