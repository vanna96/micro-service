// See SHOP.md for the isolated fixture/Next preview setup.
// SHOP_BASE_URL=http://shop.test:3101 SHOP_FIXTURE_URL=http://172.18.0.2:3300 node tests/browser/shop.cjs
const { chromium } = require("playwright");
const assert = require("node:assert/strict");
const fs = require("node:fs");
const base = process.env.SHOP_BASE_URL || "http://shop.test:3101";
const fixture = process.env.SHOP_FIXTURE_URL || "http://127.0.0.1:3300";
const output = process.env.SHOP_SCREENSHOTS || "/tmp/shop-browser-check";
const control = async (data) =>
    (
        await (
            await fetch(`${fixture}/__control`, {
                method: "POST",
                body: JSON.stringify(data || {}),
                headers: { "Content-Type": "application/json" },
            })
        ).json()
    ).data;
const wait = async (fn, message) => {
    for (let i = 0; i < 60; i++) {
        if (await fn()) return;
        await new Promise((r) => setTimeout(r, 100));
    }
    throw new Error(message);
};
(async () => {
    await control({ reset: true });
    fs.mkdirSync(output, { recursive: true });
    const browser = await chromium.launch({
        headless: true,
        args: [
            "--no-proxy-server",
            ...(process.env.SHOP_HOST_MAP
                ? [`--host-resolver-rules=${process.env.SHOP_HOST_MAP}`]
                : []),
        ],
    });
    const errors = [];
    try {
        const context = await browser.newContext({
            viewport: { width: 390, height: 844 },
            isMobile: true,
            hasTouch: true,
            reducedMotion: "reduce",
        });
        const page = await context.newPage();
        page.setDefaultTimeout(10000);
        page.on("pageerror", (e) => errors.push(e.message));
        const go = async (route) => {
            await page.goto(base + route);
            await page.locator(".shop-bottom-nav").waitFor();
        };
        const api = async (path, body, method = "POST") =>
            page.evaluate(
                async ({ path, body, method }) => {
                    const r = await fetch("/api/shop/" + path, {
                        method,
                        ...(method !== "GET"
                            ? { body: JSON.stringify(body) }
                            : {}),
                        headers: { "Content-Type": "application/json" },
                    });
                    return { status: r.status, data: await r.json() };
                },
                { path, body, method },
            );
        await go("/");
        await page.getByText("Fresh apples", { exact: true }).first().waitFor();
        await page.locator(".shop-branch").click();
        await page
            .getByRole("dialog")
            .getByRole("button", { name: /Market branch/ })
            .click();
        await page.locator(".shop-branch").getByText("Market branch").waitFor();
        await page.reload();
        await page.locator(".shop-branch").getByText("Market branch").waitFor();
        assert.equal(
            await page.locator('link[href*="admin-Bly"]').count(),
            0,
            "Storefront must not load admin CSS",
        );
        await page.screenshot({
            path: output + "/home-390.png",
            fullPage: true,
        });
        await go("/shop");
        await page
            .getByRole("button", { name: "Load More", exact: true })
            .click();
        await wait(
            async () =>
                (await page.locator(".shop-product-card").count()) === 23,
            "Catalog pagination",
        );
        await page.locator(".shop-screen").evaluate((el) => {
            el.scrollTop = 420;
        });
        await page.waitForTimeout(100);
        await page
            .locator(".shop-bottom-nav")
            .getByRole("link", { name: "Account", exact: true })
            .click();
        await page.locator('input[name="username"]').waitFor();
        await page.goBack();
        await wait(
            async () =>
                await page
                    .locator(".shop-screen")
                    .evaluate((el) => el.scrollTop >= 400),
            "Browser back restores tab scroll",
        );
        await page.getByRole("button", { name: "List view" }).click();
        assert.equal(await page.locator(".shop-product-list").count(), 23);
        await page.locator('input[type="search"]').fill("apples");
        await wait(
            async () =>
                (await page.locator(".shop-product-card").count()) === 1,
            "Search results",
        );
        await page
            .getByRole("button", { name: "Filters", exact: true })
            .click();
        await page.evaluate(() => {
            Object.defineProperty(window.visualViewport, "height", {
                value: 460,
                configurable: true,
            });
            window.visualViewport.dispatchEvent(new Event("resize"));
        });
        assert(
            (await page.getByRole("dialog").boundingBox()).y +
                (await page.getByRole("dialog").boundingBox()).height <=
                461,
            "Sheet fits the visible keyboard viewport",
        );
        await page.evaluate(() => {
            delete window.visualViewport.height;
            window.visualViewport.dispatchEvent(new Event("resize"));
        });
        await page
            .getByRole("dialog")
            .getByLabel("Sort By")
            .selectOption("price_desc");
        await page.getByRole("button", { name: "Apply filters" }).click();
        await wait(async () => page.url().includes("price_desc"), "Filter URL");
        await page
            .locator(".shop-product-card")
            .getByRole("button", { name: "Add to Cart" })
            .click();
        await go("/cart");
        await page.locator(".shop-cart-line").waitFor();
        assert.equal(
            await page.locator(".shop-counter strong").first().innerText(),
            "1",
            "Guest cart survives reload",
        );
        await page
            .getByRole("button", { name: /^Sign in/ })
            .click();
        await page.locator('input[name="username"]').fill("shopper");
        await page.locator('input[name="password"]').fill("secret123");
        await page
            .locator("form")
            .getByRole("button", { name: /^Sign in/ })
            .click();
        await page.waitForURL("**/cart");
        await wait(
            async () => (await control()).carts[1][0].quantity === 3,
            "Merged saved + guest quantities",
        );
        assert.equal(
            await page.locator(".shop-counter strong").first().innerText(),
            "3",
        );
        const cookies = await context.cookies(base + "/api/shop/auth/me");
        assert.equal(cookies.length, 2);
        assert(
            cookies.every(
                (c) =>
                    c.httpOnly &&
                    c.path === "/api/shop" &&
                    !c.domain.startsWith("."),
            ),
        );
        assert.equal(
            await page.evaluate(() => document.cookie.includes("shop_access")),
            false,
        );
        await control({ syncFailure: true });
        await page
            .getByRole("button", { name: "Increase quantity", exact: true })
            .click();
        await page
            .getByText("Cart save temporarily unavailable.", { exact: true })
            .waitFor();
        await page.reload();
        await page
            .getByText("Cart save temporarily unavailable.", { exact: true })
            .waitFor();
        assert.equal(
            await page.locator(".shop-counter strong").first().innerText(),
            "4",
            "Unsaved customer cart survives reload",
        );
        await control({ syncFailure: false });
        await page.getByRole("button", { name: "Retry", exact: true }).click();
        await wait(
            async () => (await control()).carts[1][0].quantity === 4,
            "Customer cart sync recovers",
        );
        await go("/products/2");
        await page.getByRole("button", { name: /^Large/ }).click();
        await page
            .locator(".shop-unit-options")
            .getByRole("button", { name: /Pack/ })
            .click();
        await page.getByRole("button", { name: /Add to Cart/ }).click();
        await wait(
            async () =>
                (await control()).carts[1].some(
                    (l) =>
                        l.item_id === 2 &&
                        l.variant_id === 2 &&
                        l.uom_id === 2 &&
                        l.option_value_ids[0] === 2,
                ),
            "Variant/unit configuration saved",
        );
        await page
            .getByRole("button", { name: "Favorites", exact: true })
            .click();
        await go("/account/favorites");
        await page.locator(".shop-product-card").waitFor();
        assert.equal(await page.locator(".shop-product-card").count(), 1);
        await go("/account/addresses");
        await page
            .getByRole("button", { name: "Add address", exact: true })
            .first()
            .click();
        const dialog = page.getByRole("dialog");
        await dialog.locator('input[name="label"]').fill("Office");
        await dialog
            .locator('input[name="recipient_name"]')
            .fill("Test shopper");
        await dialog.locator('input[name="phone"]').fill("12345678");
        await dialog
            .locator('input[name="address_line"]')
            .fill("456 Office Road");
        await dialog.locator('input[name="city"]').fill("Phnom Penh");
        await dialog.getByRole("button", { name: "Save", exact: true }).click();
        await wait(
            async () => (await control()).addresses[1].length === 2,
            "Address saved",
        );
        await go("/cart");
        await page
            .getByRole("button", { name: /^Checkout/ })
            .click();
        await control({ price: 3 });
        await page.getByRole("button", { name: /Place order/ }).click();
        await page
            .getByText("Prices have changed.", { exact: false })
            .waitFor();
        assert.equal(
            (await control()).carts[1].length,
            2,
            "Price change preserves cart",
        );
        await page
            .getByRole("button", { name: "Review cart", exact: true })
            .click();
        await page
            .locator(".shop-cart-line")
            .filter({ hasText: "Fresh apples" })
            .getByText("$ 3.00", { exact: true })
            .waitFor();
        await page
            .getByRole("button", { name: /^Checkout/ })
            .click();
        await control({ orderFailure: "Insufficient stock for Fresh apples." });
        await page.getByRole("button", { name: /Place order/ }).click();
        await page.getByText("Insufficient stock", { exact: false }).waitFor();
        assert.equal((await control()).orders.length, 0);
        await control({ orderFailure: "", orderDelay: 750 });
        await page.getByRole("button", { name: /Place order/ }).click();
        await page.getByRole("button", { name: /Saving Order/ }).waitFor();
        assert(
            await page
                .getByRole("button", { name: /Saving Order/ })
                .isDisabled(),
            "Submission locked",
        );
        await page
            .getByRole("link", { name: "View order", exact: true })
            .click();
        await page.getByText("TEST-001", { exact: true }).waitFor();
        assert.equal((await control()).orders.length, 1);
        assert.equal(
            (await control()).orders[0].delivery_address.address_line,
            "123 Market Street",
        );
        await page.reload();
        await page.getByText("TEST-001", { exact: true }).waitFor();
        await go("/account/notifications");
        await page
            .getByRole("button", { name: /Welcome to Orange Market/ })
            .click();
        await wait(
            async () => (await control()).reads.length > 0,
            "Notification marked read",
        );
        await go("/account/language");
        await page.getByRole("button", { name: /ភាសាខ្មែរ/ }).click();
        await go("/");
        assert.equal(await page.locator("html").getAttribute("lang"), "km");
        await page
            .getByText("ផ្លែប៉ោមស្រស់", { exact: true })
            .first()
            .waitFor();
        await page.screenshot({
            path: output + "/home-khmer-390.png",
            fullPage: true,
        });
        await page.evaluate(() =>
            localStorage.setItem("shop.language", JSON.stringify("en")),
        );
        await go("/account");
        await page
            .getByRole("button", { name: "Sign out", exact: true })
            .click();
        await page.locator('input[name="username"]').waitFor();
        await page
            .getByRole("button", { name: "Create account", exact: true })
            .click();
        await page.locator('input[name="name"]').fill("Second shopper");
        await page.locator('input[name="username"]').fill("second");
        await page.locator('input[name="email"]').fill("second@example.test");
        await page.locator('input[name="phone"]').fill("12345678");
        await page.locator('input[name="password"]').fill("secret123");
        await page
            .locator('input[name="password_confirmation"]')
            .fill("secret123");
        await page
            .locator("form")
            .getByRole("button", { name: "Create account", exact: true })
            .click();
        await page.getByText("Second shopper", { exact: true }).waitFor();
        await go("/cart");
        await page.getByText("Your cart is empty", { exact: false }).waitFor();
        await go("/account/favorites");
        assert.equal(await page.locator(".shop-product-card").count(), 0);
        const current = await context.cookies(base + "/api/shop");
        await context.addCookies(
            current
                .filter((c) => c.name === "shop_access")
                .map((c) => ({ ...c, value: "stale-2" })),
        );
        assert.equal(
            (await api("auth/me", null, "GET")).status,
            200,
            "Expired access refreshes",
        );
        assert.equal((await control()).refreshes, 1);
        await control({ expired: true });
        assert.equal(
            (await api("orders", null, "GET")).status,
            401,
            "Expired refresh refuses personal data",
        );
        await go("/account");
        await page.locator('input[name="username"]').waitFor();
        await control({ unavailable: true });
        await page.goto(base + "/");
        await page
            .getByText("The store is temporarily unavailable.", {
                exact: false,
            })
            .first()
            .waitFor();
        await control({ unavailable: false });
        await page
            .getByRole("button", { name: "Retry", exact: true })
            .first()
            .click();
        await page.getByText("Fresh apples", { exact: true }).first().waitFor();
        for (const width of [360, 430, 1280]) {
            await page.setViewportSize({ width, height: 900 });
            await page.screenshot({
                path: `${output}/home-${width}.png`,
                fullPage: true,
            });
            assert.equal(
                await page.evaluate(
                    () => document.documentElement.scrollWidth > innerWidth,
                ),
                false,
                "No horizontal overflow",
            );
            assert(
                (await page.locator(".shop-app").boundingBox()).width <= 480,
            );
        }
        // BFF origin enforcement, tenant selection, route/method allowlist and token stripping.
        // Chromium's host resolver does not apply to Playwright's Node HTTP client.
        const request = Object.fromEntries(
            ["get", "post", "put"].map((method) => [
                method,
                (url, options = {}) =>
                    context.request[method](
                        url.replace(base, process.env.SHOP_API_ORIGIN || base),
                        {
                            ...options,
                            headers: {
                                Host: new URL(base).host,
                                ...options.headers,
                            },
                        },
                    ),
            ]),
        );
        const rejected = await request.post(base + "/api/shop/cart/price", {
            headers: { Origin: "https://other.test" },
            data: { items: [] },
        });
        assert.equal(rejected.status(), 403);
        const accepted = await request.post(
            base + "/api/shop/cart/price?tenant=evil",
            {
                headers: { Origin: base },
                data: { items: [], tenant_id: "evil", tenant: "evil" },
            },
        );
        assert.equal(accepted.status(), 200);
        const upstream = (await control()).requests.at(-1);
        assert.equal(upstream.host, new URL(base).host);
        assert(!("tenant_id" in upstream.body));
        assert(!("tenant" in upstream.body));
        assert.equal(
            (
                await request.put(base + "/api/shop/bootstrap", {
                    headers: { Origin: base },
                    data: {},
                })
            ).status(),
            405,
        );
        assert.equal(
            (await request.get(base + "/api/shop/admin")).status(),
            404,
        );
        const login = await request.post(base + "/api/shop/auth/login", {
            headers: { Origin: base, "X-Forwarded-Proto": "http" },
            data: { username: "shopper", password: "secret123" },
        });
        const logged = await login.json();
        assert(!logged.data.access_token && !logged.data.refresh_token);
        const secureLogin = await request.post(base + "/api/shop/auth/login", {
            headers: {
                Origin: base.replace("http:", "https:"),
                "X-Forwarded-Proto": "https",
            },
            data: { username: "shopper", password: "secret123" },
        });
        assert.equal(secureLogin.status(), 200);
        assert(
            secureLogin
                .headersArray()
                .filter((h) => h.name.toLowerCase() === "set-cookie")
                .every((h) => h.value.includes("; Secure")),
        );
        const signedOut = await request.post(base + "/api/shop/auth/logout", {
            headers: { Origin: base },
            data: {},
        });
        assert(
            signedOut
                .headersArray()
                .filter((h) => h.name.toLowerCase() === "set-cookie")
                .every((h) => h.value.includes("Max-Age=0")),
        );
        await page.goto(
            process.env.SHOP_CENTRAL_URL ||
                base.replace("shop.test", "vanna-pos.duckdns.org"),
        );
        await page.locator(".shop-store-card").waitFor();
        assert.equal(
            await page.locator(".shop-store-card").getAttribute("href"),
            base + "/",
        );
        assert.equal(
            await page.locator(".shop-bottom-nav").count(),
            0,
            "Main domain is a store picker",
        );
        assert.equal(errors.length, 0, errors.join("\n"));
        process.stdout.write(
            "Shopping browser checks passed: catalog, configurations, guest persistence, login merge, addresses, checkout recovery, receipt, notifications, Khmer, customer isolation, refresh and BFF controls.\n",
        );
    } finally {
        await browser.close();
    }
})().catch((e) => {
    process.stderr.write(e.stack + "\n");
    process.exitCode = 1;
});
