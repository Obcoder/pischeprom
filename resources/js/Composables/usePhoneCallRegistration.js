const ENDPOINT = '/api/phone-calls'
const DEFAULT_TARGET_PHONE = '+79650160001'

function currentUrl() {
    if (typeof window === 'undefined') {
        return null
    }

    return window.location.href
}

function currentReferrer() {
    if (typeof document === 'undefined') {
        return null
    }

    return document.referrer || null
}

function buildPayload(payload = {}) {
    return {
        source: 'website',
        target_phone: DEFAULT_TARGET_PHONE,
        url: currentUrl(),
        referrer: currentReferrer(),
        ...payload,
    }
}

function sendWithBeacon(payload) {
    if (typeof navigator === 'undefined' || typeof navigator.sendBeacon !== 'function') {
        return false
    }

    const blob = new Blob([JSON.stringify(payload)], {
        type: 'application/json',
    })

    return navigator.sendBeacon(ENDPOINT, blob)
}

function sendWithFetch(payload) {
    if (typeof fetch !== 'function') {
        return
    }

    fetch(ENDPOINT, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        keepalive: true,
        body: JSON.stringify(payload),
    }).catch((error) => {
        console.warn('[PhoneCallRegistration] failed:', error)
    })
}

export function usePhoneCallRegistration() {
    function registerPhoneCallClick(payload = {}) {
        if (typeof window === 'undefined') {
            return false
        }

        const preparedPayload = buildPayload(payload)

        try {
            if (sendWithBeacon(preparedPayload)) {
                return true
            }
        } catch (error) {
            console.warn('[PhoneCallRegistration] beacon failed:', error)
        }

        sendWithFetch(preparedPayload)

        return true
    }

    return {
        registerPhoneCallClick,
    }
}
