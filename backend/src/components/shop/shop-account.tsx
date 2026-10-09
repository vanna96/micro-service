import Link from "next/link";
import { useRouter } from "next/router";
import {
    Bell,
    Check,
    ChevronRight,
    CircleHelp,
    Heart,
    Languages,
    LogOut,
    MapPin,
    Pencil,
    Plus,
    Printer,
    ReceiptText,
    ShieldCheck,
    Sparkles,
    Trash2,
} from "lucide-react";
import { useRef, useState, type FormEvent } from "react";
import { shopApi, ShopApiError } from "@/lib/shop-api";
import type {
    ShopAddress,
    ShopNotification,
    ShopOrder,
    ShopUser,
} from "@/types/shop";
import { useShop } from "./shop-provider";
import {
    EmptyState,
    ErrorState,
    LoadingCards,
    ProductCard,
    Sheet,
    ShopHeader,
    ShopImage,
    useShopResource,
} from "./shop-ui";

export function AccountScreen({
    section,
    id,
}: {
    section?: string;
    id?: string;
}) {
    const { t, user, ready } = useShop();
    if (section === "language") return <LanguageScreen />;
    if (section === "help") return <HelpScreen />;
    if (section === "terms" || section === "privacy")
        return <LegalScreen section={section} />;
    if (section === "data-deletion") return <DataDeletionScreen />;
    if (section === "onboarding") return <OnboardingScreen />;
    if (!ready)
        return (
            <>
                <ShopHeader title={t("Account")} />
                <div className="shop-content">
                    <LoadingCards />
                </div>
            </>
        );
    if (!user) return <AuthScreen />;
    if (section === "profile") return <ProfileScreen key={user.id} />;
    if (section === "addresses") return <AddressesScreen />;
    if (section === "favorites") return <FavoritesScreen />;
    if (section === "orders")
        return id ? <ReceiptScreen id={id} /> : <OrdersScreen />;
    if (section === "notifications") return <NotificationsScreen />;
    return <AccountHome />;
}

function AuthScreen() {
    const { t, authenticate, bootstrap, sessionError } = useShop();
    const router = useRouter();
    const [mode, setMode] = useState<"login" | "register">("login");
    const [busy, setBusy] = useState(false);
    const [resendBusy, setResendBusy] = useState(false);
    const [error, setError] = useState(() =>
        typeof router.query.facebook_error === "string"
            ? router.query.facebook_error
            : "",
    );
    const [notice, setNotice] = useState("");
    const [verification, setVerification] = useState<{
        email: string;
        maskedEmail: string;
    } | null>(null);
    const finishSignIn = async () => {
        const next =
            typeof router.query.next === "string" &&
            /^\/(?!\/)/.test(router.query.next)
                ? router.query.next
                : router.asPath.startsWith("/account/")
                  ? router.asPath
                  : "/account";
        await router.replace(next);
    };
    const submit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (busy || sessionError) return;
        const fields = Object.fromEntries(
            new FormData(event.currentTarget),
        ) as Record<string, string>;
        setBusy(true);
        setError("");
        try {
            const result = await authenticate(mode, fields);
            if (result.requiresVerification && result.email) {
                setVerification({
                    email: result.email,
                    maskedEmail: result.maskedEmail || result.email,
                });
                return;
            }
            await finishSignIn();
        } catch (error) {
            if (
                error instanceof ShopApiError &&
                ["EMAIL_NOT_VERIFIED", "EMAIL_DELIVERY_FAILED"].includes(
                    error.code || "",
                ) &&
                error.data &&
                typeof error.data === "object" &&
                "email" in error.data
            ) {
                const data = error.data as {
                    email: string;
                    masked_email?: string;
                };
                setVerification({
                    email: data.email,
                    maskedEmail: data.masked_email || data.email,
                });
            }
            setError(error instanceof Error ? error.message : t("Retry"));
        } finally {
            setBusy(false);
        }
    };
    const verify = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!verification || busy) return;
        const fields = new FormData(event.currentTarget);
        setBusy(true);
        setError("");
        setNotice("");
        try {
            await shopApi("auth/verify-email", {
                method: "POST",
                body: JSON.stringify({
                    email: verification.email,
                    code: fields.get("code"),
                }),
            });
            router.reload();
        } catch (error) {
            setError(error instanceof Error ? error.message : t("Retry"));
        } finally {
            setBusy(false);
        }
    };
    const resend = async () => {
        if (!verification || resendBusy) return;
        setResendBusy(true);
        setError("");
        setNotice("");
        try {
            await shopApi("auth/resend-verification", {
                method: "POST",
                body: JSON.stringify({ email: verification.email }),
            });
            setNotice(t("A new verification code was sent."));
        } catch (error) {
            setError(error instanceof Error ? error.message : t("Retry"));
        } finally {
            setResendBusy(false);
        }
    };
    if (verification) {
        return (
            <>
                <ShopHeader title={t("Verify your email")} back />
                <div className="shop-content">
                    <div className="shop-auth-intro shop-verification-intro">
                        <span>
                            <ShieldCheck size={35} strokeWidth={1.5} />
                        </span>
                        <h2>{t("Verify your email")}</h2>
                        <p>
                            {t("Enter the 6-digit code sent to")} <br />
                            <strong>{verification.maskedEmail}</strong>
                        </p>
                    </div>
                    <form className="shop-form" onSubmit={verify}>
                        {error && <ErrorState error={error} />}
                        {notice && <p className="shop-form-notice">{notice}</p>}
                        <label>
                            {t("Verification code")}
                            <input
                                className="shop-otp-input"
                                name="code"
                                type="text"
                                inputMode="numeric"
                                autoComplete="one-time-code"
                                pattern="[0-9]{6}"
                                minLength={6}
                                maxLength={6}
                                autoFocus
                                required
                            />
                        </label>
                        <button className="shop-button" disabled={busy}>
                            <ShieldCheck size={18} />
                            {t(busy ? "Verifying…" : "Verify and continue")}
                        </button>
                        <button
                            type="button"
                            className="shop-text-button"
                            disabled={resendBusy}
                            onClick={() => void resend()}
                        >
                            {t(resendBusy ? "Sending…" : "Resend code")}
                        </button>
                        <button
                            type="button"
                            className="shop-text-button shop-verification-back"
                            onClick={() => {
                                setVerification(null);
                                setError("");
                                setNotice("");
                            }}
                        >
                            {t("Use a different email")}
                        </button>
                    </form>
                </div>
            </>
        );
    }
    return (
        <>
            <ShopHeader
                title={t("Account")}
                action={
                    <Link
                        className="shop-icon-button"
                        href="/account/language"
                        aria-label={t("Language")}
                    >
                        <Languages size={22} />
                    </Link>
                }
            />
            <div className="shop-content">
                <div className="shop-auth-intro">
                    <span>
                        <ShoppingLogo />
                    </span>
                    <h2>{t("Welcome Back")}</h2>
                    <p>{bootstrap?.store.name}</p>
                </div>
                <div className="shop-segment">
                    <button
                        aria-pressed={mode === "login"}
                        onClick={() => setMode("login")}
                    >
                        {t("Sign in")}
                    </button>
                    <button
                        aria-pressed={mode === "register"}
                        onClick={() => setMode("register")}
                    >
                        {t("Create account")}
                    </button>
                </div>
                <form className="shop-form" onSubmit={submit}>
                    {error && <ErrorState error={error} />}
                    {mode === "register" && (
                        <label>
                            {t("Name")}
                            <input
                                name="name"
                                required
                                maxLength={255}
                                autoComplete="name"
                            />
                        </label>
                    )}
                    <label>
                        {t(
                            mode === "register"
                                ? "Username"
                                : "Email / Username",
                        )}
                        <input
                            name="username"
                            required
                            maxLength={255}
                            autoComplete="username"
                        />
                    </label>
                    {mode === "register" && (
                        <>
                            <label>
                                {t("Email")}
                                <input
                                name="email"
                                type="email"
                                required
                                    maxLength={255}
                                    autoComplete="email"
                                />
                            </label>
                            <div className="shop-form-row">
                                <label>
                                    {t("Country code")}
                                    <input
                                        name="country_code"
                                        defaultValue="+855"
                                        pattern="\+[0-9]{1,4}"
                                        required
                                        autoComplete="tel-country-code"
                                    />
                                </label>
                                <label>
                                    {t("Phone")}
                                    <input
                                        name="phone"
                                        type="tel"
                                        required
                                        maxLength={30}
                                        autoComplete="tel-national"
                                    />
                                </label>
                            </div>
                        </>
                    )}
                    <label>
                        {t("Password")}
                        <input
                            name="password"
                            type="password"
                            minLength={mode === "register" ? 6 : undefined}
                            required
                            autoComplete={
                                mode === "register"
                                    ? "new-password"
                                    : "current-password"
                            }
                        />
                    </label>
                    {mode === "register" && (
                        <label>
                            {t("Password confirmation")}
                            <input
                                name="password_confirmation"
                                type="password"
                                minLength={6}
                                required
                                autoComplete="new-password"
                            />
                        </label>
                    )}
                    <button
                        className="shop-button"
                        disabled={busy || !!sessionError}
                    >
                        {t(
                            busy
                                ? "Loading…"
                                : mode === "register"
                                  ? "Create account"
                                  : "Sign in",
                        )}
                    </button>
                </form>
                {mode === "login" && (bootstrap?.store.facebook_login_enabled || bootstrap?.store.google_login_enabled) && (
                    <div className="shop-social-login">
                        <span>{t("or")}</span>
                        {bootstrap?.store.facebook_login_enabled && (
                            <a
                                className="shop-facebook-button"
                                href={`/api/shop/auth/facebook/start?next=${encodeURIComponent(
                                    typeof router.query.next === "string" &&
                                        /^\/(?!\/)/.test(router.query.next)
                                        ? router.query.next
                                        : "/account",
                                )}`}
                            >
                                <FacebookMark />
                                {t("Continue with Facebook")}
                            </a>
                        )}
                        {bootstrap?.store.google_login_enabled && (
                            <a
                                className="shop-google-button"
                                href={`/api/shop/auth/google/start?next=${encodeURIComponent(
                                    typeof router.query.next === "string" &&
                                        /^\/(?!\/)/.test(router.query.next)
                                        ? router.query.next
                                        : "/account",
                                )}`}
                            >
                                <GoogleMark />
                                {t("Continue with Google")}
                            </a>
                        )}
                    </div>
                )}
                <p className="shop-terms">
                    <Link href="/account/terms">{t("Terms & Conditions")}</Link>{" "}
                    · <Link href="/account/privacy">{t("Privacy Policy")}</Link>
                </p>
            </div>
        </>
    );
}
function ShoppingLogo() {
    return <Sparkles size={35} strokeWidth={1.4} />;
}

function FacebookMark() {
    return (
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path
                fill="currentColor"
                d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.03 1.79-4.7 4.53-4.7 1.31 0 2.68.24 2.68.24v2.96h-1.51c-1.49 0-1.96.93-1.96 1.89v2.27h3.33l-.53 3.49h-2.8V24C19.61 23.1 24 18.1 24 12.07Z"
            />
        </svg>
    );
}

function GoogleMark() {
    return (
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" />
            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
        </svg>
    );
}

function AccountHome() {
    const { t, user, favorites, cart, logout } = useShop();
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState("");
    const orders = useShopResource<ShopOrder[]>("orders?per_page=1");
    const rows = [
        {
            title: "Orders",
            detail: "Track past purchases & receipts",
            icon: ReceiptText,
            route: "orders",
            color: "#ea580c",
        },
        {
            title: "Delivery Addresses",
            detail: "Saved delivery destinations",
            icon: MapPin,
            route: "addresses",
            color: "#2563eb",
        },
        {
            title: "My Favorites",
            detail: "Products you saved for later",
            icon: Heart,
            route: "favorites",
            color: "#e11d48",
        },
        {
            title: "Language",
            detail: "Display and receipt language",
            icon: Languages,
            route: "language",
            color: "#9333ea",
        },
        {
            title: "Notifications",
            detail: "Order and account updates",
            icon: Bell,
            route: "notifications",
            color: "#0891b2",
        },
        {
            title: "App Tour & Guide",
            detail: "Fresh & Smart Shopping",
            icon: Sparkles,
            route: "onboarding",
            color: "#ea580c",
        },
        {
            title: "Help & FAQ",
            detail: "Customer assistance and guide",
            icon: CircleHelp,
            route: "help",
            color: "#0d9488",
        },
        {
            title: "Terms & Conditions",
            detail: "Terms of service",
            icon: ReceiptText,
            route: "terms",
            color: "#64748b",
        },
        {
            title: "Privacy Policy",
            detail: "How we handle your data",
            icon: ShieldCheck,
            route: "privacy",
            color: "#64748b",
        },
    ];
    return (
        <>
            <ShopHeader title={t("Account")}>
                <Link className="shop-profile-banner" href="/account/profile">
                    <ShopImage
                        src={user?.profile_image_url || "/shop/profile.png"}
                        alt={user?.name || ""}
                    />
                    <span>
                        <strong>{user?.name}</strong>
                        <small>{user?.email || user?.phone}</small>
                        <small>@{user?.username}</small>
                    </span>
                    <Pencil size={18} />
                </Link>
            </ShopHeader>
            <div className="shop-content">
                <div className="shop-stats">
                    {[
                        {
                            title: "Orders",
                            count: orders.meta?.total || 0,
                            href: "/account/orders",
                        },
                        {
                            title: "Favorites",
                            count: favorites.length,
                            href: "/account/favorites",
                        },
                        {
                            title: "Cart",
                            count: cart.reduce(
                                (sum, line) => sum + line.quantity,
                                0,
                            ),
                            href: "/cart",
                        },
                    ].map((stat) => (
                        <Link key={stat.title} href={stat.href}>
                            <strong>{stat.count}</strong>
                            <span>{t(stat.title)}</span>
                        </Link>
                    ))}
                </div>
                <div className="shop-settings-list">
                    {rows.map((row) => (
                        <Link key={row.route} href={`/account/${row.route}`}>
                            <span
                                className="shop-setting-icon"
                                style={{
                                    color: row.color,
                                    background: row.color + "12",
                                }}
                            >
                                <row.icon size={21} />
                            </span>
                            <span>
                                <strong>{t(row.title)}</strong>
                                <small>{t(row.detail)}</small>
                            </span>
                            <ChevronRight size={18} />
                        </Link>
                    ))}
                </div>
                {error && <ErrorState error={error} />}
                <button
                    className="shop-button shop-button-secondary"
                    disabled={busy}
                    onClick={async () => {
                        setBusy(true);
                        try {
                            await logout();
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
                    <LogOut size={18} />
                    {t("Sign out")}
                </button>
            </div>
        </>
    );
}

function ProfileScreen() {
    const { t, user, updateUser, announce } = useShop();
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState("");
    const [photo, setPhoto] = useState<string | null>(null);
    return (
        <>
            <ShopHeader title={t("Profile")} back />
            <div className="shop-content">
                <form
                    className="shop-form shop-panel"
                    onSubmit={async (event) => {
                        event.preventDefault();
                        setBusy(true);
                        setError("");
                        const fields = Object.fromEntries(
                            new FormData(event.currentTarget),
                        ) as Record<string, string>;
                        if (!fields.password) delete fields.password;
                        if (photo) fields.profile = photo;
                        try {
                            const response = await shopApi<ShopUser>(
                                "profile",
                                {
                                    method: "PATCH",
                                    body: JSON.stringify(fields),
                                },
                            );
                            updateUser(response.data);
                            announce(t("Saved successfully"));
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
                    <div className="shop-profile-photo">
                        <ShopImage
                            src={
                                photo ||
                                user?.profile_image_url ||
                                "/shop/profile.png"
                            }
                            alt={user?.name || ""}
                        />
                    </div>
                    <label>
                        {t("Profile photo")}
                        <input
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            onChange={(event) => {
                                const file = event.target.files?.[0];
                                if (!file) return;
                                if (file.size > 3 * 1024 * 1024) {
                                    setError(
                                        t("Choose an image smaller than 3 MB."),
                                    );
                                    return;
                                }
                                const reader = new FileReader();
                                reader.onload = () =>
                                    setPhoto(String(reader.result));
                                reader.readAsDataURL(file);
                            }}
                        />
                    </label>
                    {[
                        ["name", "Name"],
                        ["username", "Username"],
                        ["email", "Email"],
                        ["phone", "Phone"],
                        ["country_code", "Country code"],
                    ].map(([field, label]) => (
                        <label key={field}>
                            {t(label)}
                            <input
                                name={field}
                                type={
                                    field === "email"
                                        ? "email"
                                        : field === "phone"
                                          ? "tel"
                                          : "text"
                                }
                                required={
                                    field === "name" || field === "username"
                                }
                                defaultValue={
                                    String(
                                        user?.[field as keyof ShopUser] || "",
                                    )
                                }
                                maxLength={255}
                            />
                        </label>
                    ))}
                    <label>
                        {t("New password")}
                        <input
                            type="password"
                            name="password"
                            minLength={6}
                            autoComplete="new-password"
                        />
                    </label>
                    {error && <ErrorState error={error} />}
                    <button className="shop-button" disabled={busy}>
                        {t(busy ? "Loading…" : "Save changes")}
                    </button>
                </form>
            </div>
        </>
    );
}

function AddressesScreen() {
    const { t } = useShop();
    const resource = useShopResource<ShopAddress[]>("addresses");
    const [editing, setEditing] = useState<ShopAddress | "new" | null>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState("");
    const mutate = async (path: string, method: string) => {
        setBusy(true);
        setError("");
        try {
            await shopApi(path, { method, body: "{}" });
            resource.reload();
        } catch (error) {
            setError(error instanceof Error ? error.message : t("Retry"));
        } finally {
            setBusy(false);
        }
    };
    return (
        <>
            <ShopHeader
                title={t("Delivery Addresses")}
                back
                action={
                    <button
                        className="shop-icon-button"
                        aria-label={t("Add address")}
                        onClick={() => setEditing("new")}
                    >
                        <Plus size={22} />
                    </button>
                }
            />
            <div className="shop-content">
                {resource.loading && <LoadingCards />}
                {(error || resource.error) && (
                    <ErrorState
                        error={error || resource.error}
                        retry={resource.reload}
                    />
                )}
                {resource.data?.map((address) => (
                    <article className="shop-panel" key={address.id}>
                        <div className="shop-section-title">
                            <h2>{address.label}</h2>
                            {address.is_default && (
                                <span className="shop-pill">
                                    {t("Default")}
                                </span>
                            )}
                        </div>
                        <p>
                            <strong>{address.recipient_name}</strong>
                            <br />
                            {address.address_line}, {address.city}
                            <br />
                            {address.country_code} {address.phone}
                        </p>
                        {address.note && (
                            <p className="shop-muted">{address.note}</p>
                        )}
                        <div className="shop-address-actions">
                            <button
                                disabled={busy}
                                onClick={() => setEditing(address)}
                            >
                                <Pencil size={16} />
                                {t("Edit")}
                            </button>
                            {!address.is_default && (
                                <button
                                    disabled={busy}
                                    onClick={() =>
                                        void mutate(
                                            `addresses/${address.id}/default`,
                                            "POST",
                                        )
                                    }
                                >
                                    <Check size={16} />
                                    {t("Set as Default")}
                                </button>
                            )}
                            <button
                                disabled={busy}
                                onClick={() => {
                                    if (window.confirm(t("Delete Address")))
                                        void mutate(
                                            `addresses/${address.id}`,
                                            "DELETE",
                                        );
                                }}
                            >
                                <Trash2 size={16} />
                                {t("Delete")}
                            </button>
                        </div>
                    </article>
                ))}
                {resource.data?.length === 0 && (
                    <EmptyState
                        title="No addresses yet"
                        icon={<MapPin size={45} strokeWidth={1.2} />}
                    />
                )}
                <button
                    className="shop-button"
                    onClick={() => setEditing("new")}
                >
                    <Plus size={19} />
                    {t("Add address")}
                </button>
            </div>
            {editing && (
                <AddressEditor
                    address={editing === "new" ? null : editing}
                    close={() => setEditing(null)}
                    saved={() => {
                        setEditing(null);
                        resource.reload();
                    }}
                />
            )}
        </>
    );
}
function AddressEditor({
    address,
    close,
    saved,
}: {
    address: ShopAddress | null;
    close: () => void;
    saved: () => void;
}) {
    const { t, user } = useShop();
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState("");
    const defaults = {
        label: address?.label || "Home",
        recipient_name: address?.recipient_name || user?.name || "",
        country_code: address?.country_code || user?.country_code || "+855",
        phone: address?.phone || user?.phone || "",
        address_line: address?.address_line || "",
        city: address?.city || "",
        note: address?.note || "",
    };
    return (
        <Sheet
            title={address ? "Edit address" : "Add address"}
            close={() => !busy && close()}
        >
            <form
                className="shop-form shop-sheet-content"
                onSubmit={async (event) => {
                    event.preventDefault();
                    setBusy(true);
                    setError("");
                    const data = new FormData(event.currentTarget);
                    const fields = {
                        ...Object.fromEntries(data),
                        is_default: data.get("is_default") === "on",
                    };
                    try {
                        await shopApi(
                            address ? `addresses/${address.id}` : "addresses",
                            {
                                method: address ? "PATCH" : "POST",
                                body: JSON.stringify(fields),
                            },
                        );
                        saved();
                    } catch (error) {
                        setError(
                            error instanceof Error ? error.message : t("Retry"),
                        );
                    } finally {
                        setBusy(false);
                    }
                }}
            >
                {[
                    ["label", "Label"],
                    ["recipient_name", "Recipient name"],
                    ["country_code", "Country code"],
                    ["phone", "Phone"],
                    ["address_line", "Address"],
                    ["city", "City"],
                    ["note", "Notes"],
                ].map(([field, label]) => (
                    <label key={field}>
                        {t(label)}
                        <input
                            name={field}
                            defaultValue={
                                defaults[field as keyof typeof defaults]
                            }
                            required={field !== "note"}
                            pattern={
                                field === "country_code"
                                    ? "\\+[0-9]{1,4}"
                                    : undefined
                            }
                            maxLength={
                                field === "note"
                                    ? 500
                                    : field === "label"
                                      ? 100
                                      : field === "phone"
                                        ? 30
                                        : field === "city"
                                          ? 120
                                          : 255
                            }
                        />
                    </label>
                ))}
                <label className="shop-checkbox">
                    <input
                        type="checkbox"
                        name="is_default"
                        defaultChecked={address?.is_default || false}
                    />
                    {t("Default address")}
                </label>
                {error && <ErrorState error={error} />}
                <button className="shop-button" disabled={busy}>
                    {t(busy ? "Loading…" : "Save")}
                </button>
            </form>
        </Sheet>
    );
}

function FavoritesScreen() {
    const { t, favorites } = useShop();
    return (
        <>
            <ShopHeader title={t("My Favorites")} back />
            <div className="shop-content">
                {favorites.length ? (
                    <div className="shop-grid">
                        {favorites.map((product) => (
                            <ProductCard key={product.id} product={product} />
                        ))}
                    </div>
                ) : (
                    <EmptyState
                        title="No saved favorites"
                        icon={<Heart size={45} strokeWidth={1.2} />}
                    />
                )}
            </div>
        </>
    );
}

function OrdersScreen() {
    const { t, money, locale, bootstrap } = useShop();
    const [page, setPage] = useState(1);
    const resource = useShopResource<ShopOrder[]>(
        `orders?page=${page}&per_page=20`,
    );
    return (
        <>
            <ShopHeader title={t("Orders")} back />
            <div className="shop-content">
                {resource.loading && <LoadingCards />}
                {resource.error && (
                    <ErrorState
                        error={resource.error}
                        retry={resource.reload}
                    />
                )}
                {resource.data?.map((order) => (
                    <Link
                        className="shop-panel shop-order-card"
                        key={order.id}
                        href={`/account/orders/${order.id}`}
                    >
                        <div>
                            <ReceiptText size={23} />
                            <strong>{order.order_number}</strong>
                            <ChevronRight size={17} />
                        </div>
                        <p>
                            {new Date(order.created_at).toLocaleDateString(
                                locale === "km" ? "km-KH" : "en-GB",
                            )}
                        </p>
                        <div>
                            <span className="shop-pill">{t(order.status)}</span>
                            <strong>
                                {money(
                                    order.total,
                                    bootstrap?.currencies.find(
                                        (currency) =>
                                            currency.code ===
                                            order.currency_code,
                                    ) || {
                                        code: order.currency_code,
                                        symbol: order.currency_code,
                                        decimal_places:
                                            order.currency_code === "KHR"
                                                ? 0
                                                : 2,
                                    },
                                )}
                            </strong>
                        </div>
                    </Link>
                ))}
                {resource.data?.length === 0 && (
                    <EmptyState
                        title="No orders yet"
                        icon={<ReceiptText size={44} strokeWidth={1.2} />}
                    />
                )}
                {resource.meta && resource.meta.last_page > 1 && (
                    <div className="shop-pagination">
                        <button
                            disabled={page === 1}
                            onClick={() => setPage(page - 1)}
                        >
                            {t("Previous")}
                        </button>
                        <span>
                            {page} / {resource.meta.last_page}
                        </span>
                        <button
                            disabled={page === resource.meta.last_page}
                            onClick={() => setPage(page + 1)}
                        >
                            {t("Next")}
                        </button>
                    </div>
                )}
            </div>
        </>
    );
}
function ReceiptScreen({ id }: { id: string }) {
    const { t, name, bootstrap, money, locale } = useShop();
    const resource = useShopResource<ShopOrder>(`orders/${id}`);
    const order = resource.data;
    const receiptRef = useRef<HTMLElement>(null);
    const amount = (value: number) =>
        money(
            value,
            bootstrap?.currencies.find(
                (currency) => currency.code === order?.currency_code,
            ) || {
                code: order?.currency_code || "",
                symbol: order?.currency_code || "",
                decimal_places: order?.currency_code === "KHR" ? 0 : 2,
            },
        );
    const printReceipt = () => {
        if (!receiptRef.current) return;

        const previousTitle = document.title;
        const printRoot = document.createElement("div");
        printRoot.className = "shop-app shop-print-root";
        printRoot.dataset.locale = locale;
        printRoot.appendChild(receiptRef.current.cloneNode(true));
        document.body.appendChild(printRoot);
        document.body.classList.add("shop-print-active");

        let cleanedUp = false;
        let fallbackTimer: number | undefined;
        const cleanup = () => {
            if (cleanedUp) return;
            cleanedUp = true;
            if (fallbackTimer) window.clearTimeout(fallbackTimer);
            window.removeEventListener("afterprint", cleanup);
            document.body.classList.remove("shop-print-active");
            printRoot.remove();
            document.title = previousTitle;
        };

        document.title = `Receipt-${order?.order_number || id}`;
        window.addEventListener("afterprint", cleanup, { once: true });

        try {
            window.print();
            if (!cleanedUp)
                fallbackTimer = window.setTimeout(cleanup, 120000);
        } catch (error) {
            cleanup();
            throw error;
        }
    };
    return (
        <>
            <ShopHeader title={t("View receipt")} back />
            <div className="shop-content">
                {resource.loading && <LoadingCards />}
                {resource.error && (
                    <ErrorState
                        error={resource.error}
                        retry={resource.reload}
                    />
                )}
                {order && (
                    <>
                        <article
                            ref={receiptRef}
                            className="shop-panel shop-receipt"
                        >
                            <div className="shop-receipt-heading">
                                <ReceiptText size={37} />
                                <h2>{bootstrap?.store.name}</h2>
                                <strong>{order.order_number}</strong>
                                <p>
                                    {new Date(order.created_at).toLocaleString(
                                        locale === "km" ? "km-KH" : "en-GB",
                                    )}
                                </p>
                                <span className="shop-pill">
                                    {t(order.status)}
                                </span>
                            </div>
                            {order.items.map((item) => (
                                <div
                                    className="shop-receipt-line"
                                    key={item.id}
                                >
                                    <span>
                                        {name(item)}
                                        <small>× {item.quantity}</small>
                                    </span>
                                    <strong>{amount(item.line_total)}</strong>
                                </div>
                            ))}
                            <div className="shop-totals">
                                <div>
                                    <span>{t("Subtotal")}</span>
                                    <strong>{amount(order.subtotal)}</strong>
                                </div>
                                {order.discount_total > 0 && (
                                    <div className="shop-savings">
                                        <span>{t("Savings")}</span>
                                        <strong>
                                            −{amount(order.discount_total)}
                                        </strong>
                                    </div>
                                )}
                                <div className="shop-total">
                                    <span>{t("Total")}</span>
                                    <strong>{amount(order.total)}</strong>
                                </div>
                                <div>
                                    <span>{t("Payment Method")}</span>
                                    <span>{t(order.payment_method)}</span>
                                </div>
                                <div>
                                    <span>{t("Delivery")}</span>
                                    <span>{t(order.delivery_method)}</span>
                                </div>
                            </div>
                            {order.delivery_address && (
                                <section>
                                    <h3>{t("Delivery address")}</h3>
                                    <p>
                                        {order.delivery_address.recipient_name}
                                        <br />
                                        {
                                            order.delivery_address.address_line
                                        }, {order.delivery_address.city}
                                        <br />
                                        {
                                            order.delivery_address.country_code
                                        }{" "}
                                        {order.delivery_address.phone}
                                    </p>
                                </section>
                            )}
                            {order.note && <p>{order.note}</p>}
                        </article>
                        <button
                            type="button"
                            className="shop-button shop-button-secondary shop-print-button"
                            onClick={printReceipt}
                        >
                            <Printer size={18} />
                            {t("Print receipt")}
                        </button>
                    </>
                )}
            </div>
        </>
    );
}

function NotificationsScreen() {
    const { t } = useShop();
    const [page, setPage] = useState(1);
    const resource = useShopResource<ShopNotification[]>(
        `notifications?page=${page}`,
    );
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState("");
    const mark = async (path: string) => {
        setBusy(true);
        setError("");
        try {
            await shopApi(path, { method: "POST", body: "{}" });
            resource.reload();
        } catch (error) {
            setError(error instanceof Error ? error.message : t("Retry"));
        } finally {
            setBusy(false);
        }
    };
    return (
        <>
            <ShopHeader title={t("Notifications")} back />
            <div className="shop-content">
                {!!resource.meta?.unread_count && (
                    <button
                        className="shop-text-button"
                        disabled={busy}
                        onClick={() => void mark("notifications/read-all")}
                    >
                        {t("Mark all read")} ({resource.meta.unread_count})
                    </button>
                )}
                {resource.loading && <LoadingCards />}
                {(error || resource.error) && (
                    <ErrorState
                        error={error || resource.error}
                        retry={resource.reload}
                    />
                )}
                {resource.data?.map((notification) => (
                    <button
                        className={`shop-panel shop-notification${notification.read_at ? "" : " unread"}`}
                        key={notification.id}
                        disabled={busy || !!notification.read_at}
                        onClick={() =>
                            void mark(`notifications/${notification.id}/read`)
                        }
                    >
                        <Bell size={21} />
                        <span>
                            <strong>{notification.title}</strong>
                            <p>{notification.message}</p>
                            <small>
                                {new Date(
                                    notification.created_at,
                                ).toLocaleString()}
                            </small>
                        </span>
                    </button>
                ))}
                {resource.data?.length === 0 && (
                    <EmptyState
                        title="No notifications"
                        icon={<Bell size={45} strokeWidth={1.2} />}
                    />
                )}
                {resource.meta && resource.meta.last_page > 1 && (
                    <div className="shop-pagination">
                        <button
                            disabled={page === 1}
                            onClick={() => setPage(page - 1)}
                        >
                            {t("Previous")}
                        </button>
                        <span>
                            {page} / {resource.meta.last_page}
                        </span>
                        <button
                            disabled={page === resource.meta.last_page}
                            onClick={() => setPage(page + 1)}
                        >
                            {t("Next")}
                        </button>
                    </div>
                )}
            </div>
        </>
    );
}

function LanguageScreen() {
    const { t, locale, setLocale } = useShop();
    return (
        <>
            <ShopHeader title={t("Language")} back />
            <div className="shop-content">
                {[
                    { code: "en", name: "English", subtitle: "English" },
                    { code: "km", name: "ភាសាខ្មែរ", subtitle: "Khmer" },
                ].map((language) => (
                    <button
                        className="shop-choice shop-panel"
                        key={language.code}
                        onClick={() => setLocale(language.code as "en" | "km")}
                    >
                        <span className="shop-choice-icon">
                            <Languages size={23} />
                        </span>
                        <span>
                            <strong>{language.name}</strong>
                            <small>{language.subtitle}</small>
                        </span>
                        {locale === language.code && <Check size={22} />}
                    </button>
                ))}
            </div>
        </>
    );
}
function HelpScreen() {
    const { t } = useShop();
    return (
        <>
            <ShopHeader title={t("Help & FAQ")} back />
            <div className="shop-content">
                {[
                    [
                        "How do I shop?",
                        "Choose a branch, browse products, select any required options, and add items to your cart.",
                    ],
                    [
                        "How do I checkout?",
                        "Sign in, choose delivery or pickup and a payment method, then review your total before placing an order.",
                    ],
                    [
                        "Where are my receipts?",
                        "Open Account → Orders to view your purchases and print receipts.",
                    ],
                    [
                        "My cart price changed",
                        "Prices and promotions are checked by the store at checkout. Review the latest cart before trying again.",
                    ],
                ].map(([question, answer]) => (
                    <details className="shop-panel" key={question}>
                        <summary>{t(question)}</summary>
                        <p>{t(answer)}</p>
                    </details>
                ))}
            </div>
        </>
    );
}
function LegalScreen({ section }: { section: "terms" | "privacy" }) {
    const { t, bootstrap } = useShop();
    const resource = useShopResource<{
        terms_conditions: string;
        privacy_policy: string;
        contact_email: string;
    }>("legal");
    const content =
        resource.data?.[
            section === "terms" ? "terms_conditions" : "privacy_policy"
        ] || "";
    return (
        <>
            <ShopHeader
                title={t(
                    section === "terms"
                        ? "Terms & Conditions"
                        : "Privacy Policy",
                )}
                back
            />
            <div className="shop-content">
                {resource.loading && <p>{t("Loading…")}</p>}
                {resource.error && (
                    <ErrorState
                        error={resource.error}
                        retry={resource.reload}
                    />
                )}
                {resource.data && (
                    <article className="shop-panel shop-description">
                        {content ? (
                            content
                                .replace(
                                    /<\/(?:p|div|li)>|<br\s*\/?\s*>/gi,
                                    "\n",
                                )
                                .replace(/<[^>]*>/g, "")
                                .replace(/&nbsp;/g, " ")
                                .replace(/&amp;/g, "&")
                        ) : section === "privacy" ? (
                            <DefaultPrivacyPolicy
                                storeName={bootstrap?.store.name || "This store"}
                                contactEmail={resource.data.contact_email}
                            />
                        ) : (
                            t("Document is empty.")
                        )}
                    </article>
                )}
            </div>
        </>
    );
}

function DefaultPrivacyPolicy({
    storeName,
    contactEmail,
}: {
    storeName: string;
    contactEmail: string;
}) {
    return (
        <>
            <h2>Privacy Policy</h2>
            <p>Last updated: October 4, 2026</p>
            <p>
                {storeName} collects the information needed to provide customer
                accounts and shopping services. This may include your name,
                username, email address, phone number, profile image, delivery
                addresses, cart, favorites, orders, and payment-method selection.
            </p>
            <h3>Facebook Login</h3>
            <p>
                If you choose Facebook Login, we receive your Facebook user ID,
                public profile information, profile image, and email address when
                Facebook makes it available and you authorize access. We use this
                information only to create, link, and secure your customer account.
                We do not post to Facebook on your behalf.
            </p>
            <h3>How information is used</h3>
            <p>
                Information is used to authenticate you, process and deliver
                orders, provide receipts and support, remember your preferences,
                prevent fraud, and meet accounting or legal obligations. Data may
                be processed by service providers that operate the store, hosting,
                communications, or payments, and may be disclosed when required by
                law. Customer personal information is not sold.
            </p>
            <h3>Retention and your choices</h3>
            <p>
                Account information is retained while your account is active and
                as needed for the purposes above. You may request access,
                correction, or deletion. Some transaction records may be retained
                where accounting, legal, fraud-prevention, or dispute-resolution
                obligations require it.
            </p>
            <p>
                <Link href="/account/data-deletion">
                    View customer data deletion instructions
                </Link>
                {contactEmail ? (
                    <>
                        {" "}or contact {" "}
                        <a href={`mailto:${contactEmail}`}>{contactEmail}</a>.
                    </>
                ) : (
                    "."
                )}
            </p>
        </>
    );
}

function DataDeletionScreen() {
    const { t } = useShop();
    const resource = useShopResource<{
        contact_email: string;
    }>("legal");
    const contactEmail = resource.data?.contact_email?.trim() || "";

    return (
        <>
            <ShopHeader title={t("User Data Deletion")} back />
            <div className="shop-content">
                <article className="shop-panel shop-description">
                    <h2>{t("Request deletion of your customer data")}</h2>
                    <p>
                        {t(
                            "You can request deletion of your customer account and the personal data connected to it at any time.",
                        )}
                    </p>
                    <ol>
                        <li>
                            {t(
                                "Send a deletion request from the email address used for your customer account.",
                            )}
                        </li>
                        <li>
                            {t(
                                "Use the subject “Customer data deletion request” and include your account name.",
                            )}
                        </li>
                        <li>
                            {t(
                                "The store will verify your identity before deleting or anonymizing the account data.",
                            )}
                        </li>
                    </ol>
                    {resource.loading && <p>{t("Loading…")}</p>}
                    {resource.error && (
                        <ErrorState
                            error={resource.error}
                            retry={resource.reload}
                        />
                    )}
                    {contactEmail ? (
                        <p>
                            {t("Send your request to")}: {" "}
                            <a
                                href={`mailto:${contactEmail}?subject=${encodeURIComponent("Customer data deletion request")}`}
                            >
                                {contactEmail}
                            </a>
                        </p>
                    ) : (
                        <p>
                            {t(
                                "Contact the store using the contact information printed on your receipt and request customer data deletion.",
                            )}
                        </p>
                    )}
                    <p>
                        {t(
                            "Transaction records may be retained where required for accounting, legal, fraud-prevention, or dispute-resolution obligations.",
                        )}
                    </p>
                </article>
            </div>
        </>
    );
}
function OnboardingScreen() {
    const { t, locale } = useShop();
    const [page, setPage] = useState(0);
    const router = useRouter();
    const slides = [
        {
            image: "onboard_fresh_grocery.jpg",
            title: "Farm-Fresh Groceries",
            khTitle: "ទំនិញស្រស់ៗពីកសិដ្ឋាន",
            text: "Find your everyday essentials and choose products from your local store.",
            khText: "ស្វែងរកទំនិញប្រចាំថ្ងៃ និងជ្រើសរើសផលិតផលពីហាងរបស់អ្នក។",
        },
        {
            image: "onboard_fast_delivery.jpg",
            title: "Shop Your Way",
            khTitle: "ទិញតាមរបៀបរបស់អ្នក",
            text: "Save your addresses and choose home delivery or pickup at checkout.",
            khText: "រក្សាទុកអាសយដ្ឋាន និងជ្រើសរើសការដឹកជញ្ជូនដល់ផ្ទះ ឬមកយកនៅហាង។",
        },
        {
            image: "onboard_smart_pay.jpg",
            title: "Smart POS & Checkout",
            khTitle: "ការទូទាត់ឆ្លាតវៃ និងងាយស្រួល",
            text: "Review promotions and totals, place your order, and keep your receipts in Account.",
            khText: "ពិនិត្យប្រូម៉ូសិន និងតម្លៃសរុប រួចបញ្ជាទិញ និងរក្សាវិក្កយបត្រក្នុងគណនី។",
        },
    ];
    const slide = slides[page];
    return (
        <>
            <ShopHeader title={t("App Tour & Guide")} back />
            <div className="shop-onboarding">
                <ShopImage src={`/shop/${slide.image}`} alt="" />
                <h2>{locale === "km" ? slide.khTitle : slide.title}</h2>
                <p>{locale === "km" ? slide.khText : slide.text}</p>
                <div className="shop-dots">
                    {slides.map((_, index) => (
                        <button
                            key={index}
                            aria-label={String(index + 1)}
                            aria-pressed={page === index}
                            onClick={() => setPage(index)}
                        />
                    ))}
                </div>
                <button
                    className="shop-button"
                    onClick={() =>
                        page < 2 ? setPage(page + 1) : void router.push("/")
                    }
                >
                    {t(page < 2 ? "Next" : "Get Started")}
                </button>
                <Link className="shop-text-button" href="/">
                    {t("Skip")}
                </Link>
            </div>
        </>
    );
}
