import Head from "next/head";
import Link from "next/link";
import { useRouter } from "next/router";
import {
    ArrowUpRight,
    Check,
    Languages,
    RefreshCw,
    Store,
} from "lucide-react";
import { useEffect, useRef, useState } from "react";
import type { ShopPageProps } from "@/types/shop";
import { useShop } from "./shop-provider";
import {
    EmptyState,
    ErrorState,
    SearchField,
    ShopHeader,
    ShopImage,
    ShopUnavailableState,
} from "./shop-ui";
import { CatalogScreen, ExploreScreen, HomeScreen } from "./shop-catalog";
import { ProductScreen } from "./shop-product";
import { CartScreen } from "./shop-cart";
import { AccountScreen } from "./shop-account";

const tabs = [
    { label: "Home", href: "/", icon: "home.svg" },
    { label: "Explore", href: "/explore", icon: "explore_icon.svg" },
    { label: "Shop", href: "/shop", icon: "shop_icon.svg" },
    { label: "Cart", href: "/cart", icon: "cart_icon.svg" },
    { label: "Account", href: "/account", icon: "account_icon.svg" },
];

const pullRefreshThreshold = 68;
const pullRefreshMaximum = 96;

export default function ShopApp({
    origin,
    central,
    stores,
    initialError,
}: ShopPageProps) {
    const {
        bootstrap,
        t,
        locale,
        cart,
        message,
        sessionError,
        restore,
        syncError,
        flushCart,
        announce,
    } = useShop();
    const router = useRouter();
    const path = router.asPath.split("?")[0];
    const segments = path.split("/").filter(Boolean);
    const scroll = useRef<HTMLDivElement>(null);
    const positions = useRef(new Map<string, number>());
    const location = useRef(path);
    const navigating = useRef(false);
    const pullStart = useRef<number | null>(null);
    const pullDistanceRef = useRef(0);
    const refreshingRef = useRef(false);
    const refreshTimer = useRef<ReturnType<typeof setTimeout> | undefined>(
        undefined,
    );
    const [pullDistance, setPullDistance] = useState(0);
    const [pulling, setPulling] = useState(false);
    const [refreshing, setRefreshing] = useState(false);
    useEffect(() => {
        if (!("serviceWorker" in navigator)) return;
        void navigator.serviceWorker
            .register("/shop-sw.js", { scope: "/" })
            .catch(() => {});
    }, []);
    useEffect(() => {
        const viewport = window.visualViewport;
        const app = scroll.current?.parentElement;
        if (!viewport || !app) return;
        const resize = () => {
            if (Math.abs(viewport.scale - 1) > 0.05) return;
            app.style.setProperty(
                "--shop-viewport-height",
                `${viewport.height}px`,
            );
            app.style.setProperty(
                "--shop-viewport-top",
                `${viewport.offsetTop}px`,
            );
        };
        resize();
        viewport.addEventListener("resize", resize);
        viewport.addEventListener("scroll", resize);
        return () => {
            viewport.removeEventListener("resize", resize);
            viewport.removeEventListener("scroll", resize);
        };
    }, []);
    useEffect(() => {
        const element = scroll.current;
        if (!element) return;

        const updateDistance = (distance: number) => {
            pullDistanceRef.current = distance;
            setPullDistance(distance);
        };
        const stopPull = () => {
            if (pullStart.current === null) return;
            pullStart.current = null;
            setPulling(false);
            if (pullDistanceRef.current < pullRefreshThreshold) {
                updateDistance(0);
                return;
            }
            refreshingRef.current = true;
            setRefreshing(true);
            updateDistance(52);
            refreshTimer.current = setTimeout(() => {
                window.location.reload();
            }, 250);
        };
        const onTouchStart = (event: TouchEvent) => {
            if (
                refreshingRef.current ||
                element.scrollTop > 0 ||
                event.touches.length !== 1
            )
                return;
            pullStart.current = event.touches[0].clientY;
            setPulling(true);
        };
        const onTouchMove = (event: TouchEvent) => {
            if (pullStart.current === null || event.touches.length !== 1)
                return;
            const delta = event.touches[0].clientY - pullStart.current;
            if (delta <= 0 || element.scrollTop > 0) {
                pullStart.current = null;
                setPulling(false);
                updateDistance(0);
                return;
            }
            event.preventDefault();
            updateDistance(
                Math.min(pullRefreshMaximum, Math.round(delta * 0.52)),
            );
        };

        element.addEventListener("touchstart", onTouchStart, {
            passive: true,
        });
        element.addEventListener("touchmove", onTouchMove, {
            passive: false,
        });
        element.addEventListener("touchend", stopPull);
        element.addEventListener("touchcancel", stopPull);

        return () => {
            element.removeEventListener("touchstart", onTouchStart);
            element.removeEventListener("touchmove", onTouchMove);
            element.removeEventListener("touchend", stopPull);
            element.removeEventListener("touchcancel", stopPull);
            clearTimeout(refreshTimer.current);
        };
    }, []);
    useEffect(() => {
        const start = () => {
            if (scroll.current)
                positions.current.set(
                    location.current,
                    scroll.current.scrollTop,
                );
            navigating.current = true;
        };
        const complete = () => {
            requestAnimationFrame(() => {
                navigating.current = false;
            });
        };
        router.events.on("routeChangeStart", start);
        router.events.on("routeChangeComplete", complete);
        router.events.on("routeChangeError", complete);
        return () => {
            router.events.off("routeChangeStart", start);
            router.events.off("routeChangeComplete", complete);
            router.events.off("routeChangeError", complete);
        };
    }, [router.events]);
    // Each tab retains its scroll offset; route changes still create browser history entries.
    useEffect(() => {
        const element = scroll.current;
        if (!element) return;
        const outgoing = location.current;
        location.current = path;
        if (outgoing === path) return;
        const position = positions.current.get(path) || 0;
        const observer = new ResizeObserver(() => {
            if (
                position <= element.scrollHeight - element.clientHeight ||
                !position
            ) {
                element.scrollTop = position;
                observer.disconnect();
            }
        });
        observer.observe(element.firstElementChild || element);
        element.scrollTop = position;
        return () => observer.disconnect();
    }, [path]);
    const total = cart.reduce((sum, line) => sum + line.quantity, 0);
    const active =
        segments[0] === "products"
            ? "/shop"
            : segments[0] === "account"
              ? "/account"
              : path;
    const pullReady = pullDistance >= pullRefreshThreshold;
    const pullLabel = refreshing
        ? t("Refreshing")
        : pullReady
          ? t("Release to refresh")
          : t("Pull to refresh");
    return (
        <div className="shop-app" data-locale={locale}>
            <Head>
                <title>
                    {bootstrap?.store.name || "V-POS"} ·{" "}
                    {t(central ? "Choose a store" : "Fresh & Smart Shopping")}
                </title>
                <meta
                    name="description"
                    content="Shop your local store, browse fresh groceries, and manage your cart and receipts."
                />
                <meta
                    name="viewport"
                    content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content"
                />
                <meta name="theme-color" content="#ffffff" />
                <meta name="mobile-web-app-capable" content="yes" />
                <meta name="apple-mobile-web-app-capable" content="yes" />
                <meta
                    name="apple-mobile-web-app-status-bar-style"
                    content="default"
                />
                <meta
                    name="apple-mobile-web-app-title"
                    content={bootstrap?.store.name || "V-POS"}
                />
                <link rel="manifest" href="/shop/manifest.webmanifest" />
                <link
                    rel="apple-touch-icon"
                    href="/shop/icons/app_icon_1024.png"
                />
                <link rel="icon" href="/shop/icons/app_icon_1024.png" />
            </Head>
            <div
                className={`shop-pull-refresh${pullDistance > 0 ? " is-visible" : ""}${pullReady ? " is-ready" : ""}${refreshing ? " is-refreshing" : ""}`}
                role="status"
                aria-live="polite"
                style={{
                    opacity: Math.min(1, pullDistance / 30),
                    transform: `translate(-50%, ${Math.min(0, pullDistance - 52)}px)`,
                }}
            >
                <RefreshCw size={17} />
                <span>{pullLabel}</span>
            </div>
            <div
                className="shop-screen"
                ref={scroll}
                onScroll={(event) => {
                    if (!navigating.current)
                        positions.current.set(
                            location.current,
                            event.currentTarget.scrollTop,
                        );
                }}
            >
                <div
                    className="shop-screen-inner"
                    style={{
                        transform:
                            pullDistance > 0
                                ? `translateY(${pullDistance}px)`
                                : undefined,
                        transition: pulling
                            ? "none"
                            : "transform 180ms ease-out",
                    }}
                >
                    {initialError ? (
                        <>
                            <ShopHeader title={t("Welcome")} />
                            <div className="shop-content">
                                <ShopUnavailableState
                                    error={initialError}
                                    retry={() => router.reload()}
                                />
                            </div>
                        </>
                    ) : central ? (
                        <StoreDirectory stores={stores || []} origin={origin} />
                    ) : (
                        <>
                            {sessionError && (
                                <div className="shop-session-error">
                                    <ErrorState
                                        error={sessionError}
                                        retry={() => void restore()}
                                    />
                                </div>
                            )}
                            {syncError && (
                                <div className="shop-session-error">
                                    <ErrorState
                                        error={syncError}
                                        retry={() =>
                                            void flushCart().catch(() => {})
                                        }
                                    />
                                </div>
                            )}
                            {segments.length === 0 ? (
                                <HomeScreen />
                            ) : segments[0] === "explore" ? (
                                <ExploreScreen />
                            ) : segments[0] === "shop" ? (
                                <CatalogScreen />
                            ) : segments[0] === "cart" ? (
                                <CartScreen />
                            ) : segments[0] === "products" ? (
                                <ProductScreen
                                    key={segments[1]}
                                    id={segments[1]}
                                />
                            ) : (
                                <AccountScreen
                                    key={segments.join("/")}
                                    section={segments[1]}
                                    id={segments[2]}
                                />
                            )}
                        </>
                    )}
                </div>
            </div>
            {!central && !initialError && (
                <nav
                    className="shop-bottom-nav"
                    aria-label={t("Main navigation")}
                >
                    {tabs.map((tab) => {
                        const isCurrent = active === tab.href;
                        return (
                            <Link
                                href={tab.href}
                                scroll={false}
                                key={tab.href}
                                className={`shop-nav-item ${isCurrent ? "active" : ""}`}
                                aria-label={t(tab.label)}
                                title={t(tab.label)}
                                aria-current={
                                    isCurrent ? "page" : undefined
                                }
                            >
                                <span className="shop-nav-icon-wrapper">
                                    <span
                                        className="shop-nav-icon"
                                        style={{
                                            maskImage: `url(/shop/icons/${tab.icon})`,
                                            WebkitMaskImage: `url(/shop/icons/${tab.icon})`,
                                        }}
                                    />
                                    {tab.href === "/cart" && total > 0 && (
                                        <span className="shop-nav-badge">
                                            {total > 99 ? "99+" : total}
                                        </span>
                                    )}
                                </span>
                                <span className="shop-nav-label sr-only">
                                    {t(tab.label)}
                                </span>
                            </Link>
                        );
                    })}
                </nav>
            )}
            {message && (
                <div
                    className={`shop-toast${message.action === "cart" ? " shop-cart-toast" : ""}`}
                    role="status"
                    aria-live="polite"
                >
                    {message.action === "cart" ? (
                        <>
                            <span className="shop-toast-success-icon">
                                <Check size={18} strokeWidth={3} />
                            </span>
                            <span className="shop-toast-copy">
                                <strong>{message.title}</strong>
                                {message.detail && <small>{message.detail}</small>}
                            </span>
                            <Link
                                href="/cart"
                                scroll={false}
                                className="shop-toast-action"
                                onClick={() => announce("")}
                            >
                                {t("View Cart")}
                            </Link>
                        </>
                    ) : (
                        message.title
                    )}
                </div>
            )}
        </div>
    );
}

function StoreDirectory({
    stores,
    origin,
}: {
    origin: string;
    stores: NonNullable<ShopPageProps["stores"]>;
}) {
    const { t, locale, setLocale } = useShop();
    const [search, setSearch] = useState("");
    const visible = stores.filter((store) =>
        store.name.toLocaleLowerCase().includes(search.toLocaleLowerCase()),
    );
    const storeUrl = (domain: string) => {
        if (!/^[a-z0-9.-]+$/i.test(domain)) return null;
        const base = new URL(origin);
        return `${base.protocol}//${domain}${base.port ? ":" + base.port : ""}/`;
    };
    return (
        <>
            <ShopHeader
                title="V-POS"
                action={
                    <button
                        className="shop-icon-button"
                        aria-label={t("Language")}
                        onClick={() => setLocale(locale === "en" ? "km" : "en")}
                    >
                        <Languages size={21} />
                        <small>{locale === "en" ? "KH" : "EN"}</small>
                    </button>
                }
            />
            <div className="shop-content">
                <div className="shop-directory-intro">
                    <span>
                        <Store size={33} strokeWidth={1.3} />
                    </span>
                    <p className="shop-eyebrow">
                        {t("Fresh & Smart Shopping")}
                    </p>
                    <h2>{t("Choose a store")}</h2>
                    <p>{t("Fresh groceries from your local store.")}</p>
                </div>
                <SearchField
                    value={search}
                    onChange={setSearch}
                    placeholder="Search stores"
                />
                <div className="shop-store-list">
                    {visible.map((store) => {
                        const href = storeUrl(store.domain);
                        return (
                            href && (
                                <a
                                    href={href}
                                    className="shop-store-card"
                                    key={store.id}
                                >
                                    <span className="shop-store-logo">
                                        {store.logo_url ? (
                                            <ShopImage
                                                src={store.logo_url}
                                                alt={store.name}
                                            />
                                        ) : (
                                            <Store
                                                size={27}
                                                strokeWidth={1.5}
                                            />
                                        )}
                                    </span>
                                    <span>
                                        <strong>{store.name}</strong>
                                        <small>{store.domain}</small>
                                        <em>{t("Open store")}</em>
                                    </span>
                                    <ArrowUpRight size={20} />
                                </a>
                            )
                        );
                    })}
                </div>
                {!visible.length && <EmptyState title="No stores found" />}
            </div>
            <footer className="shop-directory-footer">
                V-POS <span>·</span>{" "}
                <Link href="/admin">{t("Staff sign in")}</Link>
            </footer>
        </>
    );
}
