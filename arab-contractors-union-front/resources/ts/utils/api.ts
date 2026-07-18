import { ofetch } from 'ofetch'

export const $api = ofetch.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api/v1',
  async onRequest({ options }) {
    const accessToken = typeof localStorage !== 'undefined'
      ? localStorage.getItem('accessToken')
      : null
    if (accessToken)
      options.headers.append('Authorization', `Bearer ${accessToken}`)
    
    // Ensure we always request JSON from Laravel
    options.headers.append('Accept', 'application/json')
  },
})
