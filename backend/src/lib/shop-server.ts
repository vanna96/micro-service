import type { IncomingHttpHeaders } from "node:http";
import { request as httpRequest } from "node:http";
import { request as httpsRequest } from "node:https";
import type { GetServerSideProps } from "next";
import { isCentralHost, requestHost } from "@/lib/next-portal-auth";
import type {
    ShopBootstrap,
    ShopPageProps,
    ShopResponse,
    ShopStore,
} from "@/types/shop";

export function shopOrigin(headers: IncomingHttpHeaders): string {
    const protocol = String(headers["x-forwarded-proto"] || "http")
        .split(",")[0]
        .trim();
    return `${protocol === "https" ? "https" : "http"}://${headers.host || "localhost"}`;
}

export async function laravelShopRequest(
    headers: IncomingHttpHeaders,
    path: string,
    options: RequestInit = {},
) {
    const base = process.env.LARAVEL_API_URL || "http://nginx/v1/api";
    const endpoint = new URL(`${base.replace(/\/$/, "")}/${path}`);
    // Node fetch can replace Host with the upstream hostname. Resolve tenants
    // using the original storefront host, as the POS session bridge does.
    return new Promise<Response>((resolve, reject) => {
        const send =
            endpoint.protocol === "https:" ? httpsRequest : httpRequest;
        const request = send(
            endpoint,
            {
                method: options.method || "GET",
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                    Host: headers.host || "localhost",
                    "X-Forwarded-Proto": new URL(
                        shopOrigin(headers),
                    ).protocol.slice(0, -1),
                    ...(headers["x-forwarded-for"]
                        ? {
                              "X-Forwarded-For": String(
                                  headers["x-forwarded-for"],
                              ),
                          }
                        : {}),
                    ...Object.fromEntries(
                        new Headers(options.headers).entries(),
                    ),
                },
            },
            (response) => {
                const chunks: Buffer[] = [];
                response.on("data", (chunk) => chunks.push(Buffer.from(chunk)));
                response.on("error", reject);
                response.on("end", () =>
                    resolve(
                        new Response(
                            [204, 304].includes(response.statusCode || 0)
                                ? null
                                : Buffer.concat(chunks),
                            { status: response.statusCode || 502 },
                        ),
                    ),
                );
            },
        );
        request.setTimeout(15000, () =>
            request.destroy(new Error("Store request timed out")),
        );
        request.on("error", reject);
        request.end(
            typeof options.body === "string" ? options.body : undefined,
        );
    });
}

const supported =
    /^(?:\/|\/explore|\/shop|\/cart|\/products\/\d+|\/account(?:\/(?:profile|addresses|favorites|orders(?:\/\d+)?|notifications|language|help|terms|privacy|data-deletion|onboarding))?)$/;

export const shopServerProps: GetServerSideProps<ShopPageProps> = async ({
    req,
    resolvedUrl,
}) => {
    const pathname = resolvedUrl.split("?")[0].replace(/\/$/, "") || "/";
    if (!supported.test(pathname)) return { notFound: true };
    const central = isCentralHost(requestHost(req.headers));
    if (central && pathname !== "/")
        return { redirect: { destination: "/", permanent: false } };
    try {
        const response = await laravelShopRequest(
            req.headers,
            central ? "stores" : "mobile/bootstrap",
        );
        if (!central && [403, 404].includes(response.status))
            return { notFound: true };
        if (!response.ok) throw new Error("Store service unavailable");
        const payload = (await response.json()) as ShopResponse<
            ShopBootstrap | ShopStore[]
        >;
        return {
            props: {
                origin: shopOrigin(req.headers),
                central,
                bootstrap: central ? null : (payload.data as ShopBootstrap),
                stores: central ? (payload.data as ShopStore[]) : null,
                initialError: null,
            },
        };
    } catch {
        return {
            props: {
                origin: shopOrigin(req.headers),
                central,
                bootstrap: null,
                stores: null,
                initialError:
                    "The store is temporarily unavailable. Please try again.",
            },
        };
    }
};
