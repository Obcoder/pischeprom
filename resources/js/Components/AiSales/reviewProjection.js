export const candidateReviewStatuses = [
    'exact_existing_unit',
    'probable_existing_review',
    'new_unit_review',
]

const candidateQueueCategories = new Set([
    'candidate_ingestion_review',
    'candidate_duplicate_review',
    'new_unit_review',
])

export function isCandidateReview(candidate) {
    return candidateReviewStatuses.includes(candidate?.status)
}

export function normalizeReviewItems(items = []) {
    const candidateRuns = new Set(
        items
            .filter(item => ['candidate_duplicate_review', 'new_unit_review'].includes(item?.category))
            .map(item => item?.run_id)
            .filter(Boolean),
    )
    const seen = new Set()

    return items.filter(item => {
        if (item?.category === 'candidate_ingestion_review'
            && item?.run_id
            && candidateRuns.has(item.run_id)) {
            return false
        }

        const key = [item?.campaign_id, item?.source_type, item?.source_id, item?.category].join(':')
        if (seen.has(key)) return false
        seen.add(key)

        return true
    })
}

export function reviewBadgeCount(items = [], candidates = []) {
    const normalized = normalizeReviewItems(items)
    const candidateQueueCount = normalized.filter(item => candidateQueueCategories.has(item?.category)).length
    const nonCandidateQueueCount = normalized.length - candidateQueueCount
    const candidateCount = new Set(
        candidates.filter(isCandidateReview).map(candidate => candidate.id),
    ).size

    return nonCandidateQueueCount + Math.max(candidateQueueCount, candidateCount)
}
