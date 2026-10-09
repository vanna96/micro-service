import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useRef,
    useState,
    type ReactNode,
} from "react";
import {
    cartPayload,
    lineKey,
    mergeCart,
    shopApi,
    ShopApiError,
} from "@/lib/shop-api";
import khmer from "@/lib/i18n/shop-km.json";
import type {
    LocalizedName,
    ShopBootstrap,
    ShopCurrency,
    ShopLine,
    ShopProduct,
    ShopUser,
} from "@/types/shop";

type ShopContextValue = {
    catalogPages: Map<string, { page: number; products: ShopProduct[] }>;
    bootstrap: ShopBootstrap | null;
    branch: number;
    selectBranch: (id: number) => Promise<void>;
    locale: "en" | "km";
    setLocale: (value: "en" | "km") => void;
    t: (text: string) => string;
    name: (value: LocalizedName) => string;
    money: (value: number, currency?: ShopCurrency | null) => string;
    user: ShopUser | null;
    ready: boolean;
    sessionError: string;
    restore: () => Promise<void>;
    authenticate: (
        mode: "login" | "register",
        data: Record<string, string>,
    ) => Promise<ShopAuthenticationResult>;
    logout: () => Promise<void>;
    cart: ShopLine[];
    updateCart: (lines: ShopLine[]) => void;
    add: (line: ShopLine) => void;
    syncError: string;
    flushCart: () => Promise<void>;
    favorites: ShopProduct[];
    toggleFavorite: (product: ShopProduct) => Promise<void>;
    message: ShopNotice | null;
    announce: (message: string, options?: ShopNoticeOptions) => void;
    updateUser: (user: ShopUser) => void;
};

export type ShopAuthenticationResult = {
    requiresVerification: boolean;
    email?: string;
    maskedEmail?: string;
};

type ShopNotice = {
    title: string;
    detail?: string;
    action?: "cart";
};

type ShopNoticeOptions = Omit<ShopNotice, "title">;
const ShopContext = createContext<ShopContextValue | null>(null);

function readLocal<T>(key: string, fallback: T): T {
    try {
        return JSON.parse(localStorage.getItem(key) || "null") ?? fallback;
    } catch {
        return fallback;
    }
}
function writeLocal(key: string, value: unknown) {
    try {
        localStorage.setItem(key, JSON.stringify(value));
    } catch {
        /* Private browsing can disable storage. */
    }
}
function removeLocal(key: string) {
    try {
        localStorage.removeItem(key);
    } catch {
        /* Storage can be disabled. */
    }
}
function validCart(value: unknown): value is ShopLine[] {
    return (
        Array.isArray(value) &&
        value.every(
            (line) =>
                line &&
                Number.isInteger(line.item_id) &&
                Number.isInteger(line.quantity) &&
                line.quantity > 0 &&
                Array.isArray(line.option_value_ids),
        )
    );
}

export function ShopProvider({
    initial,
    children,
}: {
    initial: ShopBootstrap | null;
    children: ReactNode;
}) {
    const [bootstrap, setBootstrap] = useState(initial);
    const [catalogPages] = useState(
        () => new Map<string, { page: number; products: ShopProduct[] }>(),
    );
    const initialRef = useRef(initial);
    const [branch, setBranch] = useState(0);
    const [locale, setLocaleState] = useState<"en" | "km">("en");
    const [user, setUser] = useState<ShopUser | null>(null);
    const [ready, setReady] = useState(false);
    const [sessionError, setSessionError] = useState("");
    const [cart, setCart] = useState<ShopLine[]>([]);
    const [favorites, setFavorites] = useState<ShopProduct[]>([]);
    const [syncError, setSyncError] = useState("");
    const [message, setMessage] = useState<ShopNotice | null>(null);
    const cartRef = useRef<ShopLine[]>([]);
    const userRef = useRef<ShopUser | null>(null);
    const alive = useRef(true);
    const sequence = useRef(0);
    const saveQueue = useRef<Promise<void>>(Promise.resolve());
    const messageTimer = useRef<ReturnType<typeof setTimeout> | undefined>(
        undefined,
    );
    const storeId = initial?.store.id;
    const guestKey = `shop.${storeId}.guest-cart`;
    const t = useCallback(
        (text: string) =>
            locale === "km"
                ? (khmer as Record<string, string>)[text] || text
                : text,
        [locale],
    );
    const name = useCallback(
        (value: LocalizedName) =>
            locale === "km" && value.foreign_name
                ? value.foreign_name
                : value.name,
        [locale],
    );
    const money = useCallback(
        (amount: number, currency?: ShopCurrency | null) => {
            const selected = currency || bootstrap?.currency;
            if (!selected)
                return amount.toLocaleString(
                    locale === "km" ? "km-KH" : "en-US",
                    { maximumFractionDigits: 2 },
                );
            return `${selected.symbol || selected.code} ${amount.toLocaleString(locale === "km" ? "km-KH" : "en-US", { minimumFractionDigits: selected.decimal_places, maximumFractionDigits: selected.decimal_places })}`;
        },
        [bootstrap, locale],
    );
    const announce = useCallback((text: string, options: ShopNoticeOptions = {}) => {
        clearTimeout(messageTimer.current);
        if (!text) {
            setMessage(null);
            return;
        }
        setMessage({ title: text, ...options });
        messageTimer.current = setTimeout(() => setMessage(null), 3000);
    }, []);
    const commitCart = useCallback((lines: ShopLine[]) => {
        cartRef.current = lines;
        setCart(lines);
    }, []);

    const flushCart = useCallback(async () => {
        const customer = userRef.current;
        if (!customer) return;
        const lines = cartPayload(cartRef.current);
        const pendingKey = `shop.${storeId}.customer.${customer.id}.pending-cart`;
        writeLocal(pendingKey, cartRef.current);
        const operation = saveQueue.current
            .catch(() => {})
            .then(async () => {
                if (userRef.current?.id !== customer.id || !alive.current)
                    return;
                await shopApi("cart/sync", {
                    method: "POST",
                    body: JSON.stringify({ items: lines }),
                });
                if (
                    userRef.current?.id === customer.id &&
                    JSON.stringify(cartPayload(cartRef.current)) ===
                        JSON.stringify(lines)
                )
                    removeLocal(pendingKey);
                if (alive.current) setSyncError("");
            });
        saveQueue.current = operation;
        try {
            await operation;
        } catch (error) {
            if (alive.current)
                setSyncError(
                    error instanceof Error
                        ? error.message
                        : "Unable to save cart",
                );
            throw error;
        }
    }, [storeId]);

    const updateCart = useCallback(
        (lines: ShopLine[]) => {
            commitCart(lines);
            if (userRef.current) void flushCart().catch(() => {});
            else writeLocal(guestKey, lines);
        },
        [commitCart, flushCart, guestKey],
    );

    const restore = useCallback(async () => {
        if (!storeId) {
            setReady(true);
            return;
        }
        const version = ++sequence.current;
        setSessionError("");
        try {
            const profile = await shopApi<ShopUser>("auth/me");
            const [saved, likes] = await Promise.all([
                shopApi<ShopLine[]>("cart"),
                shopApi<{ products: ShopProduct[] }>("favorites"),
            ]);
            if (!alive.current || version !== sequence.current) return;
            userRef.current = profile.data;
            setUser(profile.data);
            const pending = readLocal<unknown>(
                `shop.${storeId}.customer.${profile.data.id}.pending-cart`,
                null,
            );
            const guest = readLocal<unknown>(guestKey, []);
            const guestCart = validCart(guest) ? guest : [];
            commitCart(validCart(pending) ? pending : mergeCart(saved.data, guestCart));
            setFavorites(likes.data.products);
            if (validCart(pending) || guestCart.length) {
                writeLocal(guestKey, []);
                await flushCart().catch(() => {});
            }
        } catch (error) {
            if (!alive.current || version !== sequence.current) return;
            if (error instanceof ShopApiError && error.status === 401) {
                userRef.current = null;
                setUser(null);
                setFavorites([]);
                const saved = readLocal<ShopLine[]>(guestKey, []);
                commitCart(
                    Array.isArray(saved)
                        ? saved.filter(
                              (line) =>
                                  Number.isInteger(line.item_id) &&
                                  Number.isInteger(line.quantity) &&
                                  line.quantity > 0 &&
                                  Array.isArray(line.option_value_ids),
                          )
                        : [],
                );
            } else
                setSessionError(
                    error instanceof Error
                        ? error.message
                        : "Unable to restore your account",
                );
        } finally {
            if (alive.current && version === sequence.current) setReady(true);
        }
    }, [commitCart, flushCart, guestKey, storeId]);

    useEffect(() => {
        alive.current = true;
        Promise.resolve().then(() => {
            const savedLocale = readLocal<string>("shop.language", "en");
            if (alive.current)
                setLocaleState(savedLocale === "km" ? "km" : "en");
            const savedBranch = readLocal<number>(`shop.${storeId}.branch`, 0);
            if (
                savedBranch &&
                initialRef.current?.branches.some(
                    (value) => value.id === savedBranch,
                )
            ) {
                shopApi<ShopBootstrap>(`bootstrap?branch_id=${savedBranch}`)
                    .then((response) => {
                        if (alive.current) {
                            setBootstrap(response.data);
                            setBranch(savedBranch);
                        }
                    })
                    .catch(() => {});
            }
            void restore();
        });
        return () => {
            alive.current = false;
            clearTimeout(messageTimer.current);
        };
    }, [restore, storeId]);

    useEffect(() => {
        document.documentElement.lang = locale;
    }, [locale]);

    useEffect(() => {
        const expired = () => {
            ++sequence.current;
            userRef.current = null;
            setUser(null);
            setFavorites([]);
            commitCart([]);
            setSyncError("");
            setReady(true);
            announce(t("Please sign in to continue."));
        };
        window.addEventListener("shop:session-expired", expired);
        return () =>
            window.removeEventListener("shop:session-expired", expired);
    }, [announce, commitCart, t]);
    const setLocale = useCallback((value: "en" | "km") => {
        setLocaleState(value);
        writeLocal("shop.language", value);
    }, []);
    const branchSequence = useRef(0);
    const selectBranch = useCallback(
        async (id: number) => {
            const version = ++branchSequence.current;
            const response = await shopApi<ShopBootstrap>(
                `bootstrap${id ? `?branch_id=${id}` : ""}`,
            );
            if (version !== branchSequence.current || !alive.current) return;
            setBootstrap(response.data);
            setBranch(id);
            writeLocal(`shop.${storeId}.branch`, id);
        },
        [storeId],
    );

    const authenticate = useCallback(
        async (mode: "login" | "register", data: Record<string, string>) => {
            const version = ++sequence.current;
            const response = await shopApi<{
                user?: ShopUser;
                requires_verification?: boolean;
                email?: string;
                masked_email?: string;
            }>(`auth/${mode}`, {
                method: "POST",
                body: JSON.stringify(data),
            });
            if (response.data.requires_verification) {
                return {
                    requiresVerification: true,
                    email: response.data.email,
                    maskedEmail: response.data.masked_email,
                };
            }
            const profile = await shopApi<ShopUser>("auth/me");
            const [saved, likes] = await Promise.all([
                shopApi<ShopLine[]>("cart"),
                shopApi<{ products: ShopProduct[] }>("favorites"),
            ]);
            if (!alive.current || version !== sequence.current)
                return { requiresVerification: false };
            const pending = readLocal<unknown>(
                `shop.${storeId}.customer.${profile.data.id}.pending-cart`,
                null,
            );
            const merged = mergeCart(
                validCart(pending) ? pending : saved.data,
                userRef.current ? [] : cartRef.current,
            );
            userRef.current = profile.data;
            setUser(profile.data);
            commitCart(merged);
            setFavorites(likes.data.products);
            setSessionError("");
            setReady(true);
            writeLocal(guestKey, []);
            await flushCart().catch(() => {});
            return { requiresVerification: false };
        },
        [commitCart, flushCart, guestKey, storeId],
    );

    const logout = useCallback(async () => {
        await saveQueue.current.catch(() => {});
        await shopApi("auth/logout", { method: "POST", body: "{}" });
        ++sequence.current;
        userRef.current = null;
        setUser(null);
        commitCart([]);
        setFavorites([]);
        setSyncError("");
    }, [commitCart]);
    const toggleFavorite = useCallback(async (product: ShopProduct) => {
        const customer = userRef.current;
        if (!customer)
            throw new ShopApiError("Please sign in to save favorites.", 401);
        const response = await shopApi<{ is_favorite: boolean }>(
            "favorites/toggle",
            { method: "POST", body: JSON.stringify({ item_id: product.id }) },
        );
        if (!alive.current || userRef.current?.id !== customer.id) return;
        setFavorites((current) =>
            response.data.is_favorite
                ? [...current.filter((item) => item.id !== product.id), product]
                : current.filter((item) => item.id !== product.id),
        );
    }, []);
    const add = useCallback(
        (line: ShopLine) => {
            const previous = cartRef.current.find(
                (value) => lineKey(value) === lineKey(line),
            );
            updateCart(
                previous
                    ? cartRef.current.map((value) =>
                          lineKey(value) === lineKey(line)
                              ? {
                                    ...value,
                                    quantity: value.quantity + line.quantity,
                                }
                              : value,
                      )
                    : [...cartRef.current, line],
            );
            const product = line.product;
            const unit = product?.uom_group?.units.find(
                (value) => value.id === line.uom_id,
            );
            const selectedOptions = product?.option_groups
                .flatMap((group) => group.values)
                .filter((value) => line.option_value_ids.includes(value.id))
                .map((value) => name(value));
            const configuration = [
                unit ? name(unit) : "",
                ...(selectedOptions || []),
            ]
                .filter(Boolean)
                .join(" · ");
            const status = `×${line.quantity} ${t("Added to cart")}`;
            announce(product ? name(product) : t("Added to cart"), {
                detail: configuration
                    ? `${configuration} • ${status}`
                    : status,
                action: "cart",
            });
        },
        [announce, name, t, updateCart],
    );
    const updateUser = useCallback((value: ShopUser) => {
        if (!alive.current || userRef.current?.id !== value.id) return;
        userRef.current = value;
        setUser(value);
    }, []);

    return (
        <ShopContext.Provider
            value={{
                catalogPages,
                bootstrap,
                branch,
                selectBranch,
                locale,
                setLocale,
                t,
                name,
                money,
                user,
                ready,
                sessionError,
                restore,
                authenticate,
                logout,
                cart,
                updateCart,
                add,
                syncError,
                flushCart,
                favorites,
                toggleFavorite,
                message,
                announce,
                updateUser,
            }}
        >
            {children}
        </ShopContext.Provider>
    );
}

export function useShop() {
    const context = useContext(ShopContext);
    if (!context) throw new Error("ShopProvider is required");
    return context;
}
