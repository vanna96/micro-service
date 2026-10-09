import type { ShopLine, ShopResponse } from "@/types/shop";

export class ShopApiError extends Error {
    constructor(
        message: string,
        public status: number,
        public fields?: Record<string, string[]>,
        public code?: string,
        public data?: unknown,
        public retryAfter?: number,
    ) {
        super(message);
    }
}

export async function shopApi<T>(
    path: string,
    options: RequestInit = {},
): Promise<ShopResponse<T>> {
    const response = await fetch(`/api/shop/${path}`, {
        ...options,
        credentials: "same-origin",
        cache: "no-store",
        headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            ...options.headers,
        },
    });
    const payload = (await response
        .json()
        .catch(() => ({}))) as ShopResponse<T>;
    if (!response.ok || payload.success === false) {
        if (
            response.status === 401 &&
            !path.startsWith("auth/") &&
            typeof window !== "undefined"
        ) {
            window.dispatchEvent(new Event("shop:session-expired"));
        }
        throw new ShopApiError(
            Object.values(payload.errors || {}).flat()[0] ||
                payload.message ||
                "The store is unavailable. Please try again.",
            response.status,
            payload.errors,
            payload.code,
            payload.data,
            payload.retry_after,
        );
    }
    return payload;
}

export function lineKey(
    line: Pick<
        ShopLine,
        "item_id" | "variant_id" | "uom_id" | "option_value_ids"
    >,
): string {
    return [
        line.item_id,
        line.variant_id || 0,
        line.uom_id || 0,
        [...line.option_value_ids].sort((a, b) => a - b).join(","),
    ].join(":");
}

export function cartPayload(lines: ShopLine[]) {
    return lines.map(
        ({ item_id, quantity, variant_id, uom_id, option_value_ids }) => ({
            item_id,
            quantity,
            variant_id,
            uom_id,
            option_value_ids,
        }),
    );
}

export function mergeCart(saved: ShopLine[], guest: ShopLine[]): ShopLine[] {
    const map = new Map(saved.map((line) => [lineKey(line), { ...line }]));
    for (const line of guest) {
        const key = lineKey(line);
        const previous = map.get(key);
        map.set(key, {
            ...line,
            product: previous?.product || line.product,
            quantity: line.quantity + (previous?.quantity || 0),
        });
    }
    return [...map.values()];
}
