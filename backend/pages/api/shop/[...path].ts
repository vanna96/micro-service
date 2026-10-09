import type { NextApiRequest, NextApiResponse } from "next";
import { laravelShopRequest, shopOrigin } from "@/lib/shop-server";
import { isCentralHost, requestHost } from "@/lib/next-portal-auth";

const routes: [RegExp, string[], boolean][] = [
    [/^stores$/, ["GET"], true],
    [
        /^(bootstrap|legal|currencies|currency|branches|banners|categories|products(?:\/\d+)?|products\/search-by-image)$/,
        ["GET", "POST"],
        true,
    ],
    [/^cart\/price$/, ["POST"], true],
    [
        /^auth\/(login|register|verify-email|resend-verification|refresh)$/,
        ["POST"],
        true,
    ],
    [/^auth\/(facebook|google)\/(start|callback)$/, ["GET"], true],
    [/^auth\/me$/, ["GET"], false],
    [/^auth\/logout$/, ["POST"], false],
    [/^profile$/, ["GET", "PATCH", "PUT"], false],
    [/^addresses$/, ["GET", "POST"], false],
    [/^addresses\/\d+$/, ["PATCH", "PUT", "DELETE"], false],
    [/^addresses\/\d+\/default$/, ["POST"], false],
    [/^favorites$/, ["GET"], false],
    [/^favorites\/toggle$/, ["POST"], false],
    [/^cart$/, ["GET", "DELETE"], false],
    [/^cart\/sync$/, ["POST"], false],
    [/^orders$/, ["GET", "POST"], false],
    [/^orders\/\d+$/, ["GET"], false],
    [/^notifications$/, ["GET"], false],
    [/^notifications\/(read-all|\d+\/read)$/, ["POST"], false],
];
const accessCookie = "shop_access";
const refreshCookie = "shop_refresh";
type TokenPayload = {
    success?: boolean;
    data?: {
        access_token?: string;
        token?: string;
        refresh_token?: string;
        expires_in?: number;
        authorization_url?: string;
        redirect_to?: string;
        [key: string]: unknown;
    };
    message?: string;
};
const refreshes = new Map<string, Promise<TokenPayload | null>>();

function setTokens(
    req: NextApiRequest,
    res: NextApiResponse,
    payload: TokenPayload | null,
) {
    const secure = shopOrigin(req.headers).startsWith("https:")
        ? "; Secure"
        : "";
    const token = payload?.data?.access_token || payload?.data?.token || "";
    res.setHeader("Set-Cookie", [
        `${accessCookie}=${encodeURIComponent(token)}; Path=/api/shop; HttpOnly; SameSite=Lax; Max-Age=${token ? payload?.data?.expires_in || 3600 : 0}${secure}`,
        `${refreshCookie}=${encodeURIComponent(payload?.data?.refresh_token || "")}; Path=/api/shop; HttpOnly; SameSite=Lax; Max-Age=${payload?.data?.refresh_token ? 2592000 : 0}${secure}`,
    ]);
}

async function refresh(
    req: NextApiRequest,
    token: string,
): Promise<TokenPayload | null> {
    const key = `${req.headers.host}:${token}`;
    const existing = refreshes.get(key);
    if (existing) return existing;
    const task = (async () => {
        const response = await laravelShopRequest(
            req.headers,
            "mobile/auth/refresh",
            { method: "POST", body: JSON.stringify({ refresh_token: token }) },
        );
        return response.ok ? ((await response.json()) as TokenPayload) : null;
    })();
    refreshes.set(key, task);
    // Keep the rotated result briefly so concurrent tabs can share the new token.
    try {
        return await task;
    } finally {
        const timer = setTimeout(() => refreshes.delete(key), 10000);
        timer.unref();
    }
}

export default async function handler(
    req: NextApiRequest,
    res: NextApiResponse,
) {
    res.setHeader("Cache-Control", "no-store");
    const path = Array.isArray(req.query.path) ? req.query.path.join("/") : "";
    const rule = routes.find(([pattern]) => pattern.test(path));
    if (!rule)
        return res.status(404).json({ success: false, message: "Not found" });
    if (!rule[1].includes(req.method || "")) {
        res.setHeader("Allow", rule[1].join(", "));
        return res
            .status(405)
            .json({ success: false, message: "Method not allowed" });
    }
    if (
        req.method !== "GET" &&
        (req.headers.origin !== shopOrigin(req.headers) ||
            req.headers["sec-fetch-site"] === "cross-site")
    ) {
        return res
            .status(403)
            .json({ success: false, message: "Invalid request origin" });
    }
    if (path !== "stores" && isCentralHost(requestHost(req.headers)))
        return res
            .status(422)
            .json({ success: false, message: "Choose a store first." });
    const params = new URLSearchParams();
    const queryKeys = [
        "page",
        "per_page",
        "search",
        "sort",
        "category_id",
        "branch_id",
        "featured",
        "new_arrival",
        "premium",
        "next",
        "code",
        "state",
        "error",
        "error_description",
        "error_reason",
    ];
    for (const key of queryKeys)
        if (typeof req.query[key] === "string") params.set(key, req.query[key]);
    const body =
        req.body && typeof req.body === "object" && !Array.isArray(req.body)
            ? { ...req.body }
            : {};
    // Tenant selection is exclusively domain-based; do not trust posted tenant IDs.
    delete body.tenant;
    delete body.tenant_id;
    delete body["x-tenant"];
    const target = path === "stores" ? path : `mobile/${path}`;
    const suffix = params.size ? `?${params}` : "";
    let token = req.cookies[accessCookie];
    try {
        if (path === "auth/refresh") {
            const refreshed = req.cookies[refreshCookie]
                ? await refresh(req, req.cookies[refreshCookie])
                : null;
            setTokens(req, res, refreshed);
            return res
                .status(refreshed ? 200 : 401)
                .json({ success: !!refreshed });
        }
        if (!rule[2] && !token && req.cookies[refreshCookie]) {
            const refreshed = await refresh(req, req.cookies[refreshCookie]);
            setTokens(req, res, refreshed);
            token = refreshed?.data?.access_token || refreshed?.data?.token;
        }
        if (!rule[2] && !token) {
            if (path === "auth/logout") {
                setTokens(req, res, null);
                return res.json({ success: true });
            }
            return res.status(401).json({
                success: false,
                message: "Please sign in to continue.",
            });
        }
        const send = () =>
            laravelShopRequest(req.headers, target + suffix, {
                method: req.method,
                headers:
                    token && !rule[2]
                        ? { Authorization: `Bearer ${token}` }
                        : {},
                ...(req.method !== "GET" ? { body: JSON.stringify(body) } : {}),
            });
        let response = await send();
        if (
            response.status === 401 &&
            !rule[2] &&
            path !== "auth/logout" &&
            req.cookies[refreshCookie]
        ) {
            const refreshed = await refresh(req, req.cookies[refreshCookie]);
            setTokens(req, res, refreshed);
            token = refreshed?.data?.access_token || refreshed?.data?.token;
            if (token) response = await send();
        }
        const payload = (await response.json()) as TokenPayload;
        if (path === "auth/facebook/start" || path === "auth/google/start") {
            const authorizationUrl = payload.data?.authorization_url;
            if (response.ok && typeof authorizationUrl === "string")
                return res.redirect(302, authorizationUrl);

            const provider = path.includes("google") ? "Google" : "Facebook";
            const message = payload.message || `${provider} sign-in is unavailable.`;
            return res.redirect(
                302,
                `/account?${provider.toLowerCase()}_error=${encodeURIComponent(message)}`,
            );
        }
        if (path === "auth/facebook/callback" || path === "auth/google/callback") {
            if (response.ok) {
                setTokens(req, res, payload);
                const redirectTo = payload.data?.redirect_to;
                const destination =
                    typeof redirectTo === "string" && /^\/(?!\/)/.test(redirectTo)
                        ? redirectTo
                        : "/account";
                return res.redirect(302, destination);
            }

            const provider = path.includes("google") ? "Google" : "Facebook";
            const message = payload.message || `${provider} sign-in failed.`;
            return res.redirect(
                302,
                `/account?${provider.toLowerCase()}_error=${encodeURIComponent(message)}`,
            );
        }
        if (response.status === 401 && !rule[2]) setTokens(req, res, null);
        if (
            response.ok &&
            ["auth/login", "auth/register", "auth/verify-email"].includes(
                path,
            )
        ) {
            setTokens(req, res, payload);
            if (payload.data) {
                delete payload.data.token;
                delete payload.data.access_token;
                delete payload.data.refresh_token;
            }
        }
        if (path === "auth/logout") {
            // Revoke the refresh credential too; mobile logout normally revokes only access.
            if (req.cookies[refreshCookie])
                await laravelShopRequest(req.headers, "mobile/auth/logout", {
                    method: "POST",
                    headers: {
                        Authorization: `Bearer ${req.cookies[refreshCookie]}`,
                    },
                    body: "{}",
                });
            setTokens(req, res, null);
        }
        return res.status(response.status).json(payload);
    } catch {
        return res.status(502).json({
            success: false,
            message: "The store is temporarily unavailable. Please try again.",
        });
    }
}

export const config = { api: { bodyParser: { sizeLimit: "6mb" } } };
