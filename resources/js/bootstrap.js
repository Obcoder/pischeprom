import axios from 'axios'

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'

if (typeof window !== 'undefined') {
    window.axios = axios
}

if (typeof window !== 'undefined') {
    window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
}
