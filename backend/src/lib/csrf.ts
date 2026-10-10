export async function getCsrfToken(): Promise<string> {
    const response = await fetch("/next/auth/csrf", {
        credentials: "same-origin",
        headers: { Accept: "application/json" },
        cache: "no-store",
    });
    const payload = (await response.json().catch(() => ({}))) as {
        token?: string;
    };

    if (!response.ok || !payload.token) {
        throw new Error("Unable to start a secure session. Please try again.");
    }

    return payload.token;
}
