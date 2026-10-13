import axios from 'axios'

const api = axios.create({
  baseURL: '/api',
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})

// Attach JWT token from localStorage if present
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Unwrap { data: ... } envelope and redirect to login on 401
api.interceptors.response.use(
  (response) => {
    // All backend endpoints return { data: <payload> }
    // Unwrap so callers receive the payload directly via r.data
    if (response.data && typeof response.data === 'object' && 'data' in response.data) {
      response.data = (response.data as { data: unknown }).data
    }
    return response
  },
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      window.location.href = '/login'
    }
    return Promise.reject(error)
  }
)

export default api
