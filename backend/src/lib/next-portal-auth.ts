import { request as httpRequest, type IncomingHttpHeaders } from "node:http";

export interface PortalUser {
  id: number | string;
  name: string;
  username: string;
  email: string | null;
}

export interface PortalTenant {
  id: string;
  name: string;
  alias: string | null;
  domains: string[];
}

export interface ActivePortalTenant {
  id: string;
  name: string;
  domain: string;
}

export interface PortalSession {
  user: PortalUser;
  tenants: PortalTenant[];
  tenant: ActivePortalTenant | null;
}

function laravelOrigin(): string {
  const apiUrl = process.env.LARAVEL_API_URL || "http://nginx/v1/api";

  return apiUrl.replace(/\/v1\/api\/?$/, "");
}

export async function portalSession(headers: IncomingHttpHeaders): Promise<PortalSession | null> {
  try {
    const endpoint = new URL(`${laravelOrigin()}/next/auth/session`);
    const body = await new Promise<string>((resolve, reject) => {
      const request = httpRequest({
        protocol: endpoint.protocol,
        hostname: endpoint.hostname,
        port: endpoint.port || undefined,
        path: `${endpoint.pathname}${endpoint.search}`,
        method: "GET",
        headers: {
          accept: "application/json",
          cookie: headers.cookie || "",
          host: headers.host || "localhost",
        },
      }, (response) => {
        const chunks: Buffer[] = [];

        response.on("data", (chunk) => chunks.push(Buffer.from(chunk)));
        response.on("end", () => {
          if (!response.statusCode || response.statusCode < 200 || response.statusCode >= 300) {
            reject(new Error(`Portal session request failed with status ${response.statusCode || 0}`));
            return;
          }

          resolve(Buffer.concat(chunks).toString("utf8"));
        });
      });

      request.setTimeout(5000, () => request.destroy(new Error("Portal session request timed out")));
      request.on("error", reject);
      request.end();
    });
    const payload = JSON.parse(body) as {
      authenticated?: boolean;
      data?: PortalSession;
    };

    return payload.authenticated && payload.data ? payload.data : null;
  } catch {
    return null;
  }
}

export function isCentralHost(host: string): boolean {
  return (
    host === "localhost" ||
    host === "127.0.0.1" ||
    /^192\.168\./.test(host) ||
    /^10\./.test(host) ||
    /^172\.(1[6-9]|2[0-9]|3[0-1])\./.test(host)
  );
}

export function requestHost(headers: IncomingHttpHeaders): string {
  return (headers.host || "localhost").split(":")[0].toLowerCase();
}

export function centralPortalUrl(headers: IncomingHttpHeaders): string {
  const configuredHost = process.env.CENTRAL_PORTAL_HOST?.trim() || "localhost";
  const port = (headers.host || "").split(":")[1];
  const proto = String(headers["x-forwarded-proto"] || "http").split(",")[0];

  return `${proto}://${configuredHost}${port ? `:${port}` : ""}/`;
}
