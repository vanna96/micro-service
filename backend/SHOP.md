The grocery storefront uses the existing Next.js Pages Router. Store domains open the customer app at `/`; the main domain opens the public store picker. Staff use `/admin`, and paired customer displays use `/admin/display`. Old `/pos` and `/pos/display` URLs redirect and retain their query strings.

Nginx proxies only the exact `/admin` and `/admin/display` paths to Next. Laravel continues serving `/admin/login`, `/admin/dashboard`, `/admin/items` and other management routes. `/api/shop/*` goes to Next; `/v1/api/mobile/*` remains Laravel. Reload Nginx after changing `nginx/default.conf`.

Customer tokens are host-only HttpOnly cookies scoped to `/api/shop`, with Secure on HTTPS. The bridge derives the tenant from the original host, checks mutation origins, refreshes credentials and strips tokens from browser responses. Customer carts and favorites stay on the Laravel account; pending cart changes are retained locally per store and customer until saved, including across a reload after a service failure. Guest carts are stored locally per store and merged by product, variant, unit and option configuration on sign-in. POS has its own providers and loads its CSS only on staff screens.

Prices, promotions, currencies and stock come from Laravel. Checkout submits reviewed totals for server revalidation and preserves the cart on failure. Delivery addresses are checked against the customer and saved into the existing sale snapshot. Payment method selection uses the Flutter app's existing sale semantics; this change adds no payment gateway, delivery tracking or PWA installation.

The orange theme, Gilroy/Khmer fonts, icons and onboarding illustrations are based on `vanna96/flutter-app-shop` at commit `b3aca8c2e3b4547a48d2e2f7827db3ba4d2d3861`. The layout fills phones and is capped at 480px on larger screens. Real catalog images/banners are used when configured; missing images have placeholders.

Validation commands, from this directory:

```sh
npm run next:build
npx tsc --noEmit --incremental false
npx eslint src/components/shop src/components/pos/pos-assets.tsx src/lib/shop-api.ts src/lib/shop-server.ts src/types/shop.ts pages/api/shop pages/index.tsx pages/\[...shop\].tsx pages/_app.tsx pages/_document.tsx
APP_CONFIG_CACHE=/tmp/shop-test-config.php vendor/bin/phpunit --filter='NextPortalAuthTest|MobileApiSupportTest'
nginx -t
```

The config-cache override keeps Laravel tests separate from cached production configuration. The full existing `npm run next:lint` also checks legacy POS code, which currently has unrelated lint errors.

The browser suite uses a disposable mobile API fixture and never creates live orders. Install Playwright in a temporary directory, then run these in separate terminals:

```sh
node tests/browser/shop-fixture.cjs 3300
NEXT_BUILD_DIR=.next-shop-check npm run next:build
NEXT_BUILD_DIR=.next-shop-check LARAVEL_API_URL=http://127.0.0.1:3300/v1/api npx next start -p 3101
NODE_PATH=/path/to/playwright/node_modules SHOP_BASE_URL=http://shop.test:3101 SHOP_FIXTURE_URL=http://127.0.0.1:3300 SHOP_HOST_MAP='MAP shop.test 127.0.0.1, MAP vanna-pos.duckdns.org 127.0.0.1' node tests/browser/shop.cjs
NODE_PATH=/path/to/playwright/node_modules SHOP_BASE_URL=http://shop.test:3101 SHOP_CENTRAL_URL=http://vanna-pos.duckdns.org:3101 SHOP_FIXTURE_URL=http://127.0.0.1:3300 SHOP_HOST_MAP='MAP shop.test 127.0.0.1, MAP vanna-pos.duckdns.org 127.0.0.1' node tests/browser/shop-pos.cjs
```

It checks catalog pagination/search/filters, browser back and tab scroll, variant/unit selection, guest persistence, cart merging, registration, addresses, favorites, checkout failures and submission locking, receipts, notifications, Khmer, account isolation, refresh/logout, host forwarding and API origin/cookie controls. Screenshots are written to `/tmp/shop-browser-check` unless `SHOP_SCREENSHOTS` is set. Map `shop.test` in the system hosts file for the Node HTTP checks, or set `SHOP_API_ORIGIN=http://127.0.0.1:3101`. For Docker previews, use the Next container IP in `SHOP_HOST_MAP`, `SHOP_FIXTURE_URL` and `SHOP_API_ORIGIN`. Stop the fixture and preview servers when finished.
