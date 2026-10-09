// POS UI smoke checks against the same disposable fixture used by shop.cjs.
const { chromium } = require("playwright");
const assert = require("node:assert/strict");
const base = process.env.SHOP_BASE_URL || "http://shop.test:3101";
const central =
    process.env.SHOP_CENTRAL_URL || "http://vanna-pos.duckdns.org:3101";
const fixture = process.env.SHOP_FIXTURE_URL || "http://127.0.0.1:3300";
const control = async (data) =>
    fetch(fixture + "/__control", {
        method: "POST",
        body: JSON.stringify(data),
        headers: { "Content-Type": "application/json" },
    });
(async () => {
    await control({ reset: true });
    const browser = await chromium.launch({
        headless: true,
        args: [
            "--no-proxy-server",
            `--unsafely-treat-insecure-origin-as-secure=${base},${central}`,
            ...(process.env.SHOP_HOST_MAP
                ? [`--host-resolver-rules=${process.env.SHOP_HOST_MAP}`]
                : []),
        ],
    });
    try {
        const context = await browser.newContext({
            viewport: { width: 1440, height: 950 },
        });
        // The disposable HTTP fixture lacks HTTPS-only randomUUID used by the existing POS.
        await context.addInitScript(() => {
            if (!crypto.randomUUID)
                crypto.randomUUID = () => {
                    const bytes = crypto.getRandomValues(new Uint8Array(16));
                    bytes[6] = (bytes[6] & 15) | 64;
                    bytes[8] = (bytes[8] & 63) | 128;
                    const hex = Array.from(bytes, (b) =>
                        b.toString(16).padStart(2, "0"),
                    ).join("");
                    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
                };
        });
        const page = await context.newPage(),
            errors = [];
        const consoleErrors = [];
        page.on("console", (message) => {
            if (message.type() === "error") consoleErrors.push(message.text());
        });
        page.setDefaultTimeout(10000);
        page.on("pageerror", (e) => errors.push(e.message));
        await page.route("**/next/auth/**", async (route) => {
            const path = new URL(route.request().url()).pathname;
            let data = { success: true };
            if (path.endsWith("/csrf")) data = { token: "fixture-csrf" };
            if (path.endsWith("/login")) await control({ portal: true });
            if (path.endsWith("/logout")) await control({ portal: false });
            if (path.endsWith("/tenant-handoff")) data.url = base + "/admin";
            await route.fulfill({
                contentType: "application/json",
                body: JSON.stringify(data),
            });
        });
        await page.route("**/api/pos/**", async (route) => {
            const path = new URL(route.request().url()).pathname;
            let data = [];
            if (path.endsWith("/sales"))
                data = { current: null, held: [], history: [] };
            if (path.endsWith("/sales/current")) data = {};
            if (path.endsWith("/customers"))
                data = {
                    customers: [],
                    price_lists: [],
                    currencies: [],
                    currency: { code: "USD", symbol: "$", decimal_places: 2 },
                    company: { name: "Orange Market" },
                };
            if (path.endsWith("/display/state"))
                data = { token: "fixture-display", action: "idle" };
            await route.fulfill({
                contentType: "application/json",
                body: JSON.stringify({
                    success: true,
                    data,
                    meta: { current_page: 1, last_page: 1, total: 0 },
                    currency: { code: "USD", symbol: "$", decimal_places: 2 },
                    currencies: [],
                }),
            });
        });
        await page.goto(central + "/admin");
        await page.locator("#username").fill("shopper");
        await page.locator("#password").fill("secret123");
        await page.locator('button[type="submit"]').click();
        await page
            .locator('[role="button"]')
            .filter({ hasText: "Orange Market" })
            .click();
        await page.waitForURL(base + "/admin");
        try {
            await page.locator(".pos-dashboard").waitFor();
        } catch (e) {
            process.stderr.write(
                JSON.stringify({
                    url: page.url(),
                    body: (await page.locator("body").innerText()).slice(
                        0,
                        1000,
                    ),
                    errors,
                    consoleErrors,
                }) + "\n",
            );
            throw e;
        }
        assert.equal(
            await page.locator('link[href="/pos/vpos-custom.css"]').count(),
            1,
        );
        await page.locator('a[href^="/admin/display?token="]').waitFor();
        const displayHref = await page
            .locator('a[href^="/admin/display?token="]')
            .getAttribute("href");
        assert(
            displayHref.includes("token="),
            "Cashier pairing points at new display route",
        );
        await page.goto(base + "/admin/display?token=fixture-display");
        await page.waitForFunction(
            () =>
                localStorage.getItem("vpos_display_token") ===
                "fixture-display",
        );
        assert.equal(await page.locator(".shop-bottom-nav").count(), 0);
        await page.goto(central + "/admin");
        await page.getByRole("button", { name: /Sign Out|Sign out/i }).click();
        await page.locator("#username").waitFor();
        assert.equal(errors.length, 0, errors.join("\n"));
        process.stdout.write(
            "POS browser checks passed: staff login, store chooser, cashier, display pairing and logout at /admin.\n",
        );
    } finally {
        await browser.close();
    }
})().catch((e) => {
    process.stderr.write(e.stack + "\n");
    process.exitCode = 1;
});
