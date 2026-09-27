export function isAvitoSessionError(error) {
    // Avito API credentials are independent of the employee's Ameise session.
    return [401, 419].includes(error?.response?.status) && !error?.response?.data?.category
}

export function observeAvitoSessionErrors(client, onError, origin) {
    let active = true
    const interceptor = client.interceptors.response.use(undefined, (error) => {
        const url = error?.config?.url
        if (active && typeof url === 'string') {
            try {
                const base = origin || 'http://localhost'
                const request = new URL(url, base)
                if (request.origin === new URL(base).origin && (request.pathname.startsWith('/api/avito/')
                    || request.pathname === '/broadcasting/auth')) {
                    onError(error)
                }
            } catch { /* An invalid or unrelated URL cannot identify an Ameise session. */ }
        }
        return Promise.reject(error)
    })
    return () => {
        active = false
        client.interceptors.response.eject(interceptor)
    }
}
