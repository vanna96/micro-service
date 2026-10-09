// Isolated mobile API fixture. Never points at a live Laravel database.
// node tests/browser/shop-fixture.cjs [port]
const http = require("node:http");
const currency = {
    code: "USD",
    symbol: "$",
    decimal_places: 2,
    exchange_rate: 1,
};
const category = {
    id: 1,
    name: "Fresh fruit",
    foreign_name: "ផ្លែឈើស្រស់",
    image_url: "",
};
const branch = {
    id: 1,
    name: "Market branch",
    foreign_name: "ផ្សារ",
    location: "Phnom Penh",
};
const product = {
    id: 1,
    name: "Fresh apples",
    foreign_name: "ផ្លែប៉ោមស្រស់",
    sku: "APPLE",
    description: "Fresh from the market.",
    image_url: "/shop/welcome_image.png",
    galleries: [],
    price: 2,
    formatted_price: "$ 2.00",
    currency,
    rating: 4.8,
    review_count: 8,
    stock: 40,
    stock_control: true,
    sale: true,
    status: "Active",
    is_new_arrival: true,
    is_premium: false,
    category,
    branch,
    uom_group: null,
    option_groups: [],
    variants: [],
    promotions: [],
};
const configured = {
    ...product,
    id: 2,
    name: "Fruit box",
    foreign_name: "ប្រអប់ផ្លែឈើ",
    sku: "BOX",
    price: 5,
    uom_group: {
        units: [
            {
                id: 1,
                name: "Each",
                code: "EA",
                price: 5,
                is_base_unit: true,
                conversion_factor_to_base: 1,
            },
            {
                id: 2,
                name: "Pack",
                code: "PK",
                price: 10,
                is_base_unit: false,
                conversion_factor_to_base: 2,
            },
        ],
    },
    option_groups: [
        {
            id: 1,
            name: "Size",
            type: "variant",
            selection_type: "single",
            is_required: true,
            min_selections: 1,
            max_selections: 1,
            values: [
                { id: 1, name: "Small", price_adjustment: 0, is_default: true },
                {
                    id: 2,
                    name: "Large",
                    price_adjustment: 1,
                    is_default: false,
                },
            ],
        },
    ],
    variants: [
        {
            id: 1,
            name: "Small",
            resolved_price: 5,
            stock: 20,
            is_default: true,
            option_value_ids: [1],
        },
        {
            id: 2,
            name: "Large",
            resolved_price: 6,
            stock: 20,
            is_default: false,
            option_value_ids: [2],
        },
    ],
};
const products = [
    product,
    configured,
    ...Array.from({ length: 21 }, (_, i) => ({
        ...product,
        id: i + 3,
        name: `Market fruit ${i + 3}`,
    })),
];
const line = (id, quantity = 1) => ({
    item_id: id,
    quantity,
    variant_id: null,
    uom_id: null,
    option_value_ids: [],
    product: products.find((p) => p.id === id),
});
const customer = (id) => ({
    id,
    name: id === 1 ? "Test shopper" : "Second shopper",
    username: id === 1 ? "shopper" : "second",
    email: "test@example.test",
    phone: "12345678",
    country_code: "+855",
    profile_image_url: null,
});
const address = {
    id: 1,
    label: "Home",
    recipient_name: "Test shopper",
    country_code: "+855",
    phone: "12345678",
    address_line: "123 Market Street",
    city: "Phnom Penh",
    note: "",
    is_default: true,
};
let state;
function reset() {
    state = {
        carts: { 1: [line(1, 2)], 2: [] },
        likes: {},
        addresses: { 1: [address], 2: [] },
        orders: [],
        requests: [],
        refreshes: 0,
        expired: false,
        price: 2,
        orderFailure: "",
        orderDelay: 0,
        unavailable: false,
        reads: [],
    };
}
reset();
const send = (res, data, status = 200, extra = {}) => {
    res.writeHead(status, { "Content-Type": "application/json" });
    res.end(JSON.stringify({ success: status < 400, data, ...extra }));
};
const pricing = (items) => {
    const subtotal = items.reduce(
        (sum, l) =>
            sum +
            l.quantity *
                (l.item_id === 1
                    ? state.price
                    : l.item_id === 2
                      ? l.uom_id === 2
                          ? 10
                          : l.variant_id === 2
                            ? 6
                            : 5
                      : 2),
        0,
    );
    return {
        subtotal,
        discount_total: 0,
        final_total: subtotal,
        applied_promotion: null,
        auto_add_items: [],
        items: items.map((l) => {
            const unitPrice =
                l.item_id === 1
                    ? state.price
                    : l.item_id === 2
                      ? l.uom_id === 2
                          ? 10
                          : l.variant_id === 2
                            ? 6
                            : 5
                      : 2;
            return {
                ...l,
                name: products.find((p) => p.id === l.item_id)?.name,
                item_variant_id: l.variant_id,
                selected_options: [
                    { values: (l.option_value_ids || []).map((id) => ({ id })) },
                ],
                unit_price: unitPrice,
                line_subtotal: l.quantity * unitPrice,
                line_total: l.quantity * unitPrice,
                discount_amount: 0,
            };
        }),
    };
};
http.createServer(async (req, res) => {
    const url = new URL(req.url, "http://fixture");
    let body = "";
    for await (const chunk of req) body += chunk;
    body = body ? JSON.parse(body) : {};
    if (url.pathname === "/__control") {
        if (body.reset) reset();
        else Object.assign(state, body);
        return send(res, state);
    }
    if (url.pathname === "/next/auth/session") {
        const domain = req.headers.host.split(":")[0];
        res.writeHead(state.portal ? 200 : 401, {
            "Content-Type": "application/json",
        });
        return res.end(
            JSON.stringify({
                authenticated: !!state.portal,
                data: state.portal
                    ? {
                          user: customer(1),
                          tenants: [
                              {
                                  id: "fixture",
                                  name: "Orange Market",
                                  alias: "fixture",
                                  domains: ["shop.test"],
                              },
                          ],
                          tenant:
                              domain === "shop.test"
                                  ? {
                                        id: "fixture",
                                        name: "Orange Market",
                                        domain,
                                    }
                                  : null,
                      }
                    : null,
            }),
        );
    }
    const path = url.pathname.replace(/^\/v1\/api\/(mobile\/)?/, "");
    state.requests.push({
        path,
        method: req.method,
        host: req.headers.host,
        body,
    });
    if (state.unavailable)
        return send(res, null, 503, {
            message: "The store is temporarily unavailable.",
        });
    if (path === "stores")
        return send(res, [
            {
                id: "fixture",
                name: "Orange Market",
                logo_url: "",
                domain: "shop.test",
                domains: ["shop.test"],
            },
        ]);
    if (path === "bootstrap")
        return send(res, {
            store: {
                id: "fixture",
                name: "Orange Market",
                logo_url: "",
                domain: "shop.test",
                domains: ["shop.test"],
            },
            currency,
            currencies: [currency],
            branches: [branch],
            banners: [],
            categories: [category],
            featured_products: products.slice(0, 2),
            new_arrivals: products.slice(0, 2),
            best_sellers: products.slice(0, 8),
            recommended_products: products.slice(0, 2),
        });
    if (path === "categories")
        return send(res, [category], 200, {
            meta: { current_page: 1, last_page: 1, total: 1 },
        });
    if (path === "products") {
        let result = products.filter((p) =>
            p.name
                .toLowerCase()
                .includes((url.searchParams.get("search") || "").toLowerCase()),
        );
        if (url.searchParams.get("sort") === "price_desc")
            result = result.sort((a, b) => b.price - a.price);
        const page = Number(url.searchParams.get("page") || 1),
            size = Number(url.searchParams.get("per_page") || 20);
        return send(res, result.slice((page - 1) * size, page * size), 200, {
            meta: {
                current_page: page,
                last_page: Math.ceil(result.length / size),
                total: result.length,
            },
        });
    }
    if (/^products\/\d+$/.test(path))
        return send(
            res,
            products.find((p) => p.id === Number(path.split("/")[1])),
        );
    if (path === "legal")
        return send(res, {
            terms_conditions: "Test store terms.",
            privacy_policy: "Test store privacy.",
        });
    if (path === "cart/price") return send(res, pricing(body.items));
    if (path === "auth/login" || path === "auth/register") {
        state.expired = false;
        const id = body.username === "second" ? 2 : 1;
        return send(res, {
            access_token: `access-${id}`,
            refresh_token: `refresh-${id}`,
            expires_in: 3600,
            user: customer(id),
        });
    }
    if (path === "auth/refresh") {
        state.refreshes++;
        if (state.expired)
            return send(res, null, 401, { message: "Session expired." });
        const id = Number(body.refresh_token?.split("-").pop()) || 1;
        return send(res, {
            access_token: `rotated-${id}`,
            refresh_token: `refresh-${id}`,
            expires_in: 3600,
        });
    }
    const bearer = req.headers.authorization || "",
        id = Number(bearer.split("-").pop());
    if (!id || state.expired || bearer.includes("stale"))
        return send(res, null, 401, { message: "Please sign in to continue." });
    if (path === "auth/logout") return send(res, null);
    if (path === "auth/me" || path === "profile")
        return send(res, {
            ...customer(id),
            ...(req.method !== "GET" ? body : {}),
        });
    if (path === "cart") return send(res, state.carts[id] || []);
    if (path === "cart/sync") {
        if (state.syncFailure)
            return send(res, null, 503, {
                message: "Cart save temporarily unavailable.",
            });
        state.carts[id] = body.items.map((l) => ({
            ...l,
            product: products.find((p) => p.id === l.item_id),
        }));
        return send(res, { count: body.items.length });
    }
    if (path === "favorites")
        return send(res, {
            products: (state.likes[id] || []).map((i) =>
                products.find((p) => p.id === i),
            ),
        });
    if (path === "favorites/toggle") {
        const likes = (state.likes[id] ||= []);
        const index = likes.indexOf(body.item_id);
        if (index < 0) likes.push(body.item_id);
        else likes.splice(index, 1);
        return send(res, { is_favorite: index < 0 });
    }
    if (path === "addresses" && req.method === "GET")
        return send(res, state.addresses[id] || []);
    if (path === "addresses" && req.method === "POST") {
        const added = { ...body, id: Date.now() };
        (state.addresses[id] ||= []).push(added);
        return send(res, added, 201);
    }
    if (/^addresses\/\d+/.test(path)) {
        const addressId = Number(path.split("/")[1]),
            addresses = state.addresses[id];
        if (req.method === "DELETE")
            state.addresses[id] = addresses.filter((a) => a.id !== addressId);
        else
            addresses.forEach((a) => {
                if (path.endsWith("/default"))
                    a.is_default = a.id === addressId;
                else if (a.id === addressId) Object.assign(a, body);
            });
        return send(
            res,
            addresses.find((a) => a.id === addressId),
        );
    }
    if (path === "orders" && req.method === "POST") {
        if (state.orderDelay)
            await new Promise((resolve) =>
                setTimeout(resolve, state.orderDelay),
            );
        if (state.orderFailure)
            return send(res, null, 422, { message: state.orderFailure });
        const quote = pricing(body.items);
        if (quote.final_total !== body.expected_total)
            return send(res, null, 422, {
                message:
                    "Prices have changed. Review your cart before placing the order.",
            });
        const order = {
            id: state.orders.length + 1,
            customer_id: id,
            order_number: "TEST-001",
            status: "Completed",
            payment_status: "Paid",
            payment_method: body.payment_method,
            delivery_method: body.delivery_method,
            delivery_address: body.address_id
                ? state.addresses[id].find((a) => a.id === body.address_id)
                : null,
            total: quote.final_total,
            subtotal: quote.subtotal,
            discount_total: 0,
            currency_code: "USD",
            total_items: body.items.reduce((n, l) => n + l.quantity, 0),
            created_at: new Date().toISOString(),
            note: body.note,
            items: quote.items.map((l) => ({
                ...l,
                id: l.item_id,
                image_url: "",
                foreign_name: "",
            })),
        };
        state.orders.push(order);
        state.carts[id] = [];
        return send(res, order, 201);
    }
    if (path === "orders")
        return send(
            res,
            state.orders.filter((o) => o.customer_id === id),
            200,
            {
                meta: {
                    current_page: 1,
                    last_page: 1,
                    total: state.orders.length,
                },
            },
        );
    if (/^orders\/\d+$/.test(path))
        return send(
            res,
            state.orders.find(
                (o) =>
                    o.id === Number(path.split("/")[1]) && o.customer_id === id,
            ),
        );
    if (path === "notifications")
        return send(
            res,
            [
                {
                    id: 1,
                    title: "Welcome to Orange Market",
                    message: "Your shopping account is ready.",
                    read_at: state.reads.length
                        ? new Date().toISOString()
                        : null,
                    created_at: new Date().toISOString(),
                },
            ],
            200,
            {
                meta: {
                    current_page: 1,
                    last_page: 1,
                    total: 1,
                    unread_count: state.reads.length ? 0 : 1,
                },
            },
        );
    if (path.startsWith("notifications/")) {
        state.reads.push(1);
        return send(res, null);
    }
    return send(res, null, 404, { message: `Missing fixture: ${path}` });
}).listen(Number(process.argv[2] || 3300), "0.0.0.0", () =>
    process.stdout.write("Shop fixture ready\n"),
);
