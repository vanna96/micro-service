import type { StorefrontData } from "@/types/storefront";

const apiBase =
  process.env.LARAVEL_API_URL ??
  process.env.NEXT_PUBLIC_API_URL ??
  "http://127.0.0.1:8880/v1/api";

export async function getStorefront(): Promise<{ data: StorefrontData; apiAvailable: boolean }> {
  const response = await fetch(`${apiBase}/storefront`, {
    cache: "no-store",
    headers: { Accept: "application/json" },
  });

  if (!response.ok) {
    throw new Error(`Laravel API responded with ${response.status}`);
  }

  return { data: (await response.json()) as StorefrontData, apiAvailable: true };
}

export { apiBase };
