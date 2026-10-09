import Link from "next/link";
import { useRouter } from "next/router";
import {
    Bell,
    Check,
    ChevronDown,
    Grid2X2,
    List,
    MapPin,
    SlidersHorizontal,
    X,
} from "lucide-react";
import { useEffect, useState } from "react";
import type { ShopCategory, ShopProduct } from "@/types/shop";
import { shopApi } from "@/lib/shop-api";
import { useShop } from "./shop-provider";
import {
    CategoryVisual,
    EmptyState,
    ErrorState,
    LoadingCards,
    ProductCard,
    SearchField,
    Sheet,
    ShopHeader,
    ShopImage,
    useShopResource,
} from "./shop-ui";

const IMAGE_SEARCH_RESULTS_KEY = "imageSearchResults:v2";
const LEGACY_IMAGE_SEARCH_RESULTS_KEY = "imageSearchResults";

function clearCachedImageSearchResults() {
    sessionStorage.removeItem(IMAGE_SEARCH_RESULTS_KEY);
    sessionStorage.removeItem(LEGACY_IMAGE_SEARCH_RESULTS_KEY);
}

function Section({
    title,
    products,
    href,
}: {
    title: string;
    products: ShopProduct[];
    href: string;
}) {
    const { t } = useShop();
    if (!products.length) return null;
    return (
        <section className="shop-section">
            <div className="shop-section-title">
                <h2>{t(title)}</h2>
                <Link href={href}>{t("See All")}</Link>
            </div>
            <div className="shop-carousel">
                {products.map((product) => (
                    <ProductCard key={product.id} product={product} />
                ))}
            </div>
        </section>
    );
}

export function HomeScreen() {
    const { bootstrap, branch, selectBranch, name, t, user } = useShop();
    const router = useRouter();
    const [search, setSearch] = useState("");
    const [selecting, setSelecting] = useState(false);
    const [error, setError] = useState("");
    const [busy, setBusy] = useState(false);
    const [isImageSearching, setIsImageSearching] = useState(false);
    const [bannerIndex, setBannerIndex] = useState(0);
    const banners = bootstrap?.banners || [];

    const handleImageSearch = async (file: File) => {
        setIsImageSearching(true);
        setSearch("");
        clearCachedImageSearchResults();
        try {
            const formData = new FormData();
            formData.append("image", file, file.name || "image.jpg");
            const response = await fetch('/v1/api/mobile/products/search-by-image', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json'
                },
                body: formData
            });
            const data = await response.json();
            if (data.success && data.data) {
                sessionStorage.setItem(IMAGE_SEARCH_RESULTS_KEY, JSON.stringify(data.data));
                void router.push('/shop?image_search=1');
            } else {
                alert(data.message || 'Image search failed');
            }
        } catch (e) {
            console.error(e);
            alert('Image search failed');
        } finally {
            setIsImageSearching(false);
        }
    };

    useEffect(() => {
        if (
            banners.length < 2 ||
            window.matchMedia("(prefers-reduced-motion: reduce)").matches
        )
            return;
        const timer = setInterval(
            () => setBannerIndex((value) => (value + 1) % banners.length),
            6000,
        );
        return () => clearInterval(timer);
    }, [banners.length]);
    const banner = banners[bannerIndex % Math.max(1, banners.length)];
    const selected = bootstrap?.branches.find((value) => value.id === branch);
    return (
        <>
            <ShopHeader title="">
                <div className="shop-header-top">
                    <button
                        className="shop-branch"
                        onClick={() => setSelecting(true)}
                    >
                        <span className="shop-location-icon">
                            <MapPin size={18} />
                        </span>
                        <span className="shop-branch-info">
                            <small>{t("SELECT BRANCH")}</small>
                            <strong>
                                {selected ? name(selected) : t("All Branches")}
                                <ChevronDown size={15} />
                            </strong>
                        </span>
                    </button>
                    <Link
                        className="shop-icon-button shop-bell-button"
                        aria-label={t("Notifications")}
                        href={
                            user
                                ? "/account/notifications"
                                : "/account?next=/account/notifications"
                        }
                    >
                        <Bell size={21} />
                        <span className="shop-bell-dot" aria-hidden="true" />
                    </Link>
                </div>
                <SearchField
                    value={search}
                    onChange={setSearch}
                    onImageSearch={handleImageSearch}
                    isImageSearching={isImageSearching}
                    onSubmit={() =>
                        void router.push(
                            `/shop?search=${encodeURIComponent(search)}`,
                        )
                    }
                />
            </ShopHeader>
            <div className="shop-content">
                {banner && (
                    <section className="shop-banner">
                        <div className="shop-banner-media">
                            {(banner.type ||
                                banner.mediaType ||
                                banner.media_type) === "video" ? (
                                <video
                                    src={banner.image_url}
                                    muted
                                    playsInline
                                    controls
                                    preload="metadata"
                                />
                            ) : (
                                <ShopImage
                                    src={banner.image_url}
                                    alt={
                                        banner.title ||
                                        bootstrap?.store.name ||
                                        ""
                                    }
                                />
                            )}
                        </div>
                        {(banner.title || banner.subtitle) && (
                            <div className="shop-banner-text">
                                {banner.badge && <span>{banner.badge}</span>}
                                <h2>{banner.title}</h2>
                                <p>{banner.subtitle}</p>
                                {banner.discount && (
                                    <strong>{banner.discount}</strong>
                                )}
                            </div>
                        )}
                    </section>
                )}
                {banners.length > 1 && (
                    <div className="shop-dots">
                        {banners.map((_, index) => (
                            <button
                                key={index}
                                aria-label={`${t("Banner")} ${index + 1}`}
                                aria-pressed={
                                    bannerIndex % banners.length === index
                                }
                                onClick={() => setBannerIndex(index)}
                            />
                        ))}
                    </div>
                )}
                {!!bootstrap?.categories.length && (
                    <section className="shop-section">
                        <div className="shop-section-title">
                            <h2>{t("Category")}</h2>
                            <Link href="/explore">{t("See All")}</Link>
                        </div>
                        <div className="shop-category-grid-home">
                            {(bootstrap.categories.length > 7
                                ? bootstrap.categories.slice(0, 7)
                                : bootstrap.categories
                            ).map((category) => (
                                <Link
                                    key={category.id}
                                    href={`/shop?category_id=${category.id}`}
                                    className="shop-category-item"
                                >
                                    <div className="shop-category-icon-box">
                                        <CategoryVisual
                                            category={category}
                                            name={name(category)}
                                        />
                                    </div>
                                    <strong className="shop-category-name">
                                        {name(category)}
                                    </strong>
                                </Link>
                            ))}
                            {bootstrap.categories.length > 7 && (
                                <Link
                                    href="/explore"
                                    className="shop-category-item shop-category-more"
                                >
                                    <div className="shop-category-icon-box shop-category-more-box">
                                        <svg
                                            width="24"
                                            height="24"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            aria-hidden="true"
                                        >
                                            <circle
                                                cx="6.5"
                                                cy="6.5"
                                                r="3.2"
                                                fill="#10b981"
                                            />
                                            <circle
                                                cx="17.5"
                                                cy="6.5"
                                                r="3.2"
                                                fill="#10b981"
                                            />
                                            <circle
                                                cx="6.5"
                                                cy="17.5"
                                                r="3.2"
                                                fill="#10b981"
                                            />
                                            <circle
                                                cx="17.5"
                                                cy="17.5"
                                                r="3.2"
                                                fill="#10b981"
                                            />
                                        </svg>
                                    </div>
                                    <strong className="shop-category-name">
                                        {t("More")}
                                    </strong>
                                </Link>
                            )}
                        </div>
                    </section>
                )}
                <Section
                    title="New Arrivals"
                    products={bootstrap?.new_arrivals || []}
                    href="/shop?new_arrival=1"
                />
                <Section
                    title="Best Selling"
                    products={bootstrap?.best_sellers || []}
                    href="/shop"
                />
                <Section
                    title="Featured"
                    products={bootstrap?.featured_products || []}
                    href="/shop?featured=1"
                />
                <Section
                    title="Recommendations"
                    products={bootstrap?.recommended_products || []}
                    href="/shop?premium=1"
                />
                {bootstrap &&
                    ![
                        ...bootstrap.featured_products,
                        ...bootstrap.new_arrivals,
                        ...bootstrap.best_sellers,
                        ...bootstrap.recommended_products,
                    ].length && (
                        <EmptyState title="No products found">
                            <Link className="shop-button" href="/shop">
                                {t("Explore Products")}
                            </Link>
                        </EmptyState>
                    )}
            </div>
            {selecting && (
                <Sheet
                    title="Select Branch"
                    close={() => !busy && setSelecting(false)}
                >
                    <div className="shop-sheet-content">
                        {error && <ErrorState error={error} />}
                        {[
                            { id: 0, name: t("All Branches"), location: "" },
                            ...(bootstrap?.branches || []),
                        ].map((value) => (
                            <button
                                className="shop-choice"
                                key={value.id}
                                disabled={busy}
                                onClick={async () => {
                                    setBusy(true);
                                    setError("");
                                    try {
                                        await selectBranch(value.id);
                                        setSelecting(false);
                                    } catch (error) {
                                        setError(
                                            error instanceof Error
                                                ? error.message
                                                : t("Retry"),
                                        );
                                    } finally {
                                        setBusy(false);
                                    }
                                }}
                            >
                                <span className="shop-choice-icon">
                                    <MapPin size={22} />
                                </span>
                                <span>
                                    <strong>{name(value)}</strong>
                                    {value.location && (
                                        <small>{value.location}</small>
                                    )}
                                </span>
                                {value.id === branch && <Check size={20} />}
                            </button>
                        ))}
                    </div>
                </Sheet>
            )}
        </>
    );
}

export function ExploreScreen() {
    const { t, name } = useShop();
    const [search, setSearch] = useState("");
    const [debouncedSearch, setDebouncedSearch] = useState("");

    useEffect(() => {
        const timer = setTimeout(() => setDebouncedSearch(search), 800);
        return () => clearTimeout(timer);
    }, [search]);

    const resource = useShopResource<ShopCategory[]>(
        `categories?per_page=100&search=${encodeURIComponent(debouncedSearch)}`,
    );
    return (
        <>
            <ShopHeader title={t("Find Products")}>
                <SearchField value={search} onChange={setSearch} />
            </ShopHeader>
            <div className="shop-content">
                {resource.error && (
                    <ErrorState
                        error={resource.error}
                        retry={resource.reload}
                    />
                )}
                {resource.loading ? (
                    <LoadingCards />
                ) : (
                    <div className="shop-grid shop-category-grid">
                        {resource.data?.map((category, index) => (
                            <Link
                                key={category.id}
                                href={`/shop?category_id=${category.id}`}
                                style={{
                                    background: [
                                        "#fff1e8",
                                        "#eef7eb",
                                        "#fef6e7",
                                        "#f2ebfa",
                                        "#eaf4fa",
                                        "#fceeee",
                                    ][index % 6],
                                }}
                            >
                                <CategoryVisual
                                    category={category}
                                    name={name(category)}
                                />
                                <h2>{name(category)}</h2>
                            </Link>
                        ))}
                    </div>
                )}
                {!resource.loading && resource.data?.length === 0 && (
                    <EmptyState title="No products found" />
                )}
            </div>
        </>
    );
}

const sortOptions = {
    latest: "Latest",
    price_asc: "Price: Low to High",
    price_desc: "Price: High to Low",
    name_asc: "Name: A-Z",
    name_desc: "Name: Z-A",
};
export function CatalogScreen() {
    const { t, name, bootstrap, branch, catalogPages } = useShop();
    const router = useRouter();
    const initialQuery =
        typeof router.query.search === "string" ? router.query.search : "";
    const [search, setSearch] = useState(initialQuery);
    const [filters, setFilters] = useState(false);
    const [list, setList] = useState(false);
    const category =
        typeof router.query.category_id === "string"
            ? router.query.category_id
            : "";
    const sort =
        typeof router.query.sort === "string" ? router.query.sort : "latest";
    const [draft, setDraft] = useState({
        category,
        sort,
        featured: router.query.featured === "1",
        new_arrival: router.query.new_arrival === "1",
        premium: router.query.premium === "1",
    });
    const query = new URLSearchParams({ per_page: "20", sort });
    if (initialQuery) query.set("search", initialQuery);
    if (category) query.set("category_id", category);
    if (branch) query.set("branch_id", String(branch));
    for (const key of ["featured", "new_arrival", "premium"])
        if (router.query[key] === "1") query.set(key, "1");
    const resource = useShopResource<ShopProduct[]>(`products?${query}`);
    const [more, setMore] = useState<{
        key: string;
        page: number;
        products: ShopProduct[];
    }>({ key: "", page: 1, products: [] });
    const [loadingMore, setLoadingMore] = useState(false);
    const [moreError, setMoreError] = useState("");
    const queryKey = query.toString();
    const retained = catalogPages.get(queryKey);
    const extraProducts =
        more.key === queryKey ? more.products : retained?.products || [];
    const imageSearch = router.query.image_search === '1';
    const [imageSearchProducts, setImageSearchProducts] = useState<ShopProduct[] | null>(null);
    useEffect(() => {
        let active = true;
        queueMicrotask(() => {
            if (!active) return;

            if (imageSearch) {
                // The versioned key prevents a previous zero-result response
                // from masking a newer algorithm after deployment.
                const cached = sessionStorage.getItem(IMAGE_SEARCH_RESULTS_KEY);
                if (cached) {
                    try {
                        const parsed = JSON.parse(cached);
                        setImageSearchProducts(Array.isArray(parsed) ? parsed : []);
                    } catch {
                        clearCachedImageSearchResults();
                        setImageSearchProducts([]);
                    }
                } else {
                    setImageSearchProducts([]);
                }
            } else {
                setImageSearchProducts(null);
            }

        });

        return () => {
            active = false;
        };
    }, [imageSearch]);

    const products = imageSearchProducts !== null ? imageSearchProducts : [...(resource.data || []), ...extraProducts];
    const page = more.key === queryKey ? more.page : retained?.page || 1;

    const [isImageSearching, setIsImageSearching] = useState(false);
    const handleImageSearch = async (file: File) => {
        setIsImageSearching(true);
        setSearch("");
        clearCachedImageSearchResults();
        try {
            const formData = new FormData();
            formData.append("image", file, file.name || "image.jpg");
            const response = await fetch('/v1/api/mobile/products/search-by-image', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json'
                },
                body: formData
            });
            const data = await response.json();
            if (data.success && data.data) {
                sessionStorage.setItem(IMAGE_SEARCH_RESULTS_KEY, JSON.stringify(data.data));
                if (!imageSearch) {
                    void router.replace({ pathname: '/shop', query: { ...router.query, image_search: '1' } });
                } else {
                    setImageSearchProducts(data.data);
                }
            } else {
                alert(data.message || 'Image search failed');
            }
        } catch (e) {
            console.error(e);
            alert('Image search failed');
        } finally {
            setIsImageSearching(false);
        }
    };
    useEffect(() => {
        const handler = (url: string) =>
            setSearch(
                new URL(url, "http://shop.local").searchParams.get("search") ||
                    "",
            );
        router.events.on("routeChangeComplete", handler);
        return () => router.events.off("routeChangeComplete", handler);
    }, [router]);
    useEffect(() => {
        if (search === initialQuery) return;
        const timer = setTimeout(
            () => {
                const newQuery: Record<string, string | string[] | undefined> = {
                    ...router.query,
                    search,
                };
                if (newQuery.image_search) {
                    delete newQuery.image_search;
                    clearCachedImageSearchResults();
                    setImageSearchProducts(null);
                }
                if (!search) delete newQuery.search;
                void router.replace(
                    { pathname: "/shop", query: newQuery },
                    undefined,
                    { shallow: true, scroll: false },
                );
            },
            800,
        );
        return () => clearTimeout(timer);
    }, [search, initialQuery, router]);
    const selectedCategory = bootstrap?.categories.find(
        (value) => String(value.id) === category,
    );
    return (
        <>
            <ShopHeader
                title={
                    selectedCategory
                        ? name(selectedCategory)
                        : t("All Products")
                }
            >
                <SearchField
                    value={search}
                    onChange={setSearch}
                    onImageSearch={handleImageSearch}
                    isImageSearching={isImageSearching}
                />
            </ShopHeader>
            <div className="shop-content">
                {imageSearch && (
                    <div style={{ padding: "8px 16px", background: "#f1f5f9", borderRadius: "8px", display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "16px" }}>
                        <span style={{ fontSize: "14px", color: "#334155" }}>Showing visual matches for your image</span>
                        <button
                            onClick={() => {
                                clearCachedImageSearchResults();
                                const newQuery = { ...router.query };
                                delete newQuery.image_search;
                                void router.replace({ pathname: '/shop', query: newQuery });
                                setImageSearchProducts(null);
                            }}
                            style={{ fontSize: "14px", color: "#ef4444", fontWeight: 500, display: "flex", alignItems: "center", gap: "4px" }}
                        >
                            <X size={16} /> {t("Clear")}
                        </button>
                    </div>
                )}
                <div className="shop-catalog-toolbar">
                    <span>
                        {imageSearchProducts !== null
                            ? imageSearchProducts.length
                            : resource.meta?.total ?? "—"} {t("Products")}
                    </span>
                    <div>
                        <button
                            className="shop-icon-button"
                            aria-label={t(list ? "Grid view" : "List view")}
                            onClick={() => setList(!list)}
                        >
                            {list ? <Grid2X2 size={19} /> : <List size={20} />}
                        </button>
                        <button
                            className="shop-filter-button"
                            onClick={() => {
                                setDraft({
                                    category,
                                    sort,
                                    featured: router.query.featured === "1",
                                    new_arrival:
                                        router.query.new_arrival === "1",
                                    premium: router.query.premium === "1",
                                });
                                setFilters(true);
                            }}
                        >
                            <SlidersHorizontal size={17} />
                            {t("Filters")}
                        </button>
                    </div>
                </div>
                {resource.error && (
                    <ErrorState
                        error={resource.error}
                        retry={resource.reload}
                    />
                )}
                {isImageSearching ? (
                    <LoadingCards message={t("Analyzing your image to find visually similar products...")} />
                ) : resource.loading ? (
                    <LoadingCards />
                ) : products.length ? (
                    <div className={list ? "shop-list" : "shop-grid"}>
                        {products.map((product) => (
                            <ProductCard
                                key={product.id}
                                product={product}
                                list={list}
                            />
                        ))}
                    </div>
                ) : (
                    !resource.error && (
                        <EmptyState
                            title={
                                imageSearch
                                    ? "No visually similar products found"
                                    : "No products found"
                            }
                            detail={
                                imageSearch
                                    ? "This image does not match a product in this store's catalog."
                                    : undefined
                            }
                        />
                    )
                )}
                {moreError && <ErrorState error={moreError} />}
                {!imageSearch && resource.meta && page < resource.meta.last_page && (
                    <button
                        className="shop-button shop-button-secondary shop-load-more"
                        disabled={loadingMore}
                        onClick={async () => {
                            setLoadingMore(true);
                            setMoreError("");
                            try {
                                const response = await shopApi<ShopProduct[]>(
                                    `products?${query}&page=${page + 1}`,
                                );
                                const next = {
                                    key: queryKey,
                                    page: page + 1,
                                    products: [
                                        ...extraProducts,
                                        ...response.data,
                                    ],
                                };
                                catalogPages.set(queryKey, next);
                                setMore(next);
                            } catch (error) {
                                setMoreError(
                                    error instanceof Error
                                        ? error.message
                                        : t("Retry"),
                                );
                            } finally {
                                setLoadingMore(false);
                            }
                        }}
                    >
                        {t(loadingMore ? "Loading…" : "Load More")}
                    </button>
                )}
            </div>
            {filters && (
                <Sheet title="Filter & Sort" close={() => setFilters(false)}>
                    <form
                        className="shop-form shop-sheet-content"
                        onSubmit={(event) => {
                            event.preventDefault();
                            const next: Record<string, string> = {
                                search,
                                sort: draft.sort,
                            };
                            if (draft.category)
                                next.category_id = draft.category;
                            for (const key of [
                                "featured",
                                "new_arrival",
                                "premium",
                            ] as const)
                                if (draft[key]) next[key] = "1";
                            void router.push(
                                { pathname: "/shop", query: next },
                                undefined,
                                { shallow: true, scroll: false },
                            );
                            setFilters(false);
                        }}
                    >
                        <label>
                            {t("Category")}
                            <select
                                value={draft.category}
                                onChange={(event) =>
                                    setDraft({
                                        ...draft,
                                        category: event.target.value,
                                    })
                                }
                            >
                                <option value="">{t("All")}</option>
                                {bootstrap?.categories.map((value) => (
                                    <option key={value.id} value={value.id}>
                                        {name(value)}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label>
                            {t("Sort By")}
                            <select
                                value={draft.sort}
                                onChange={(event) =>
                                    setDraft({
                                        ...draft,
                                        sort: event.target.value,
                                    })
                                }
                            >
                                {Object.entries(sortOptions).map(
                                    ([value, title]) => (
                                        <option key={value} value={value}>
                                            {t(title)}
                                        </option>
                                    ),
                                )}
                            </select>
                        </label>
                        {[
                            ["featured", "Featured"],
                            ["new_arrival", "New Arrivals"],
                            ["premium", "Premium"],
                        ].map(([key, title]) => (
                            <label key={key} className="shop-checkbox">
                                <input
                                    type="checkbox"
                                    checked={draft[key as "featured"]}
                                    onChange={(event) =>
                                        setDraft({
                                            ...draft,
                                            [key]: event.target.checked,
                                        })
                                    }
                                />
                                {t(title)}
                            </label>
                        ))}
                        <button className="shop-button">
                            {t("Apply filters")}
                        </button>
                        <button
                            className="shop-text-button"
                            type="button"
                            onClick={() =>
                                setDraft({
                                    category: "",
                                    sort: "latest",
                                    featured: false,
                                    new_arrival: false,
                                    premium: false,
                                })
                            }
                        >
                            {t("Reset")}
                        </button>
                    </form>
                </Sheet>
            )}
        </>
    );
}
