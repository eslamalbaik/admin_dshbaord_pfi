import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

// Reverb speaks the Pusher protocol — laravel-echo's 'reverb' broadcaster needs
// the Pusher client registered globally, same as a plain Pusher setup.
;(window as any).Pusher = Pusher

const apiBaseUrl = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000'

let echoInstance: Echo<'reverb'> | null = null

/**
 * Connects (or reuses) the Echo/Reverb socket for the current admin session.
 *
 * Auth is Bearer-token based (see plugins/axios.ts), not cookie/session — Echo's
 * `bearerToken` option handles this natively: it attaches `Authorization: Bearer
 * <token>` to the private-channel auth request without needing a custom authorizer.
 */
export function connectEcho(token: string): Echo<'reverb'> {
  if (echoInstance)
    return echoInstance

  echoInstance = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: Number(import.meta.env.VITE_REVERB_PORT) || 8080,
    wssPort: Number(import.meta.env.VITE_REVERB_PORT) || 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: `${apiBaseUrl}/api/broadcasting/auth`,
    bearerToken: token,
  })

  return echoInstance
}

export function disconnectEcho() {
  echoInstance?.disconnect()
  echoInstance = null
}

export function getEcho(): Echo<'reverb'> | null {
  return echoInstance
}
