import Echo from "laravel-echo";
import Pusher from "pusher-js";

declare global {
  interface Window {
    Pusher: typeof Pusher;
    Echo: Echo<"pusher"> | undefined;
  }
}

export function getEcho(): Echo<"pusher"> | null {
  if (typeof window === "undefined") return null;

  if (window.Echo) return window.Echo;

  window.Pusher = Pusher;

  // Resolve hostname dynamically from browser window so tablets on Wi-Fi (e.g. 192.168.x.x)
  // automatically connect to Soketi on that same machine without hardcoded IPs.
  const host = window.location.hostname || "localhost";
  const isHttps = window.location.protocol === "https:";
  const currentPort = window.location.port ? Number(window.location.port) : (isHttps ? 443 : 80);
  const configuredPort = process.env.NEXT_PUBLIC_PUSHER_PORT ? Number(process.env.NEXT_PUBLIC_PUSHER_PORT) : null;
  // Public clients should use the page's origin because Nginx already proxies
  // Pusher's /app/ path to Soketi. Only direct Next.js development on port 3000
  // needs to connect to Soketi's configured port.
  const port = currentPort === 3000 ? (configuredPort || 6001) : currentPort;
  const key = process.env.NEXT_PUBLIC_PUSHER_APP_KEY || "pos_key";
  const cluster = process.env.NEXT_PUBLIC_PUSHER_APP_CLUSTER || "mt1";

  try {
    const pusherClient = new Pusher(key, {
      cluster: cluster,
      wsHost: host,
      wsPort: port,
      wssPort: port,
      forceTLS: isHttps,
      disableStats: true,
      enabledTransports: ["ws", "wss"],
    });

    const echoInstance = new Echo({
      broadcaster: "pusher",
      client: pusherClient,
    });

    window.Echo = echoInstance;
    return echoInstance;
  } catch (error) {
    console.warn("Unable to initialize Echo WebSocket connection:", error);
    return null;
  }
}

export function disconnectEcho() {
  if (typeof window !== "undefined" && window.Echo) {
    window.Echo.disconnect();
    window.Echo = undefined;
  }
}
