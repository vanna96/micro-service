import React, { useEffect, useState } from "react";
import Head from "next/head";
import { useRouter } from "next/router";
import { getEcho } from "@/lib/echo";
import { fetchPosDisplayState, PosDisplayPayload, normalizeMediaUrl } from "@/lib/pos-api";
import { PosDisplayPromotions } from "@/components/pos/pos-display-promotions";
import { formatCurrency } from "@/lib/currency";
import { CurrencyInfo } from "@/types/pos-types";
import { useTranslation } from "@/lib/i18n/i18n";

export default function CustomerDisplayPage() {
  const { t, language, setLanguage } = useTranslation();
  const router = useRouter();
  const [token, setToken] = useState<string>("");
  const [inputToken, setInputToken] = useState<string>("");
  const [displayState, setDisplayState] = useState<PosDisplayPayload>({
    token: "",
    action: "idle",
    cart: [],
    totals: null,
  });
  const [isConnected, setIsConnected] = useState(false);
  const [currentTime, setCurrentTime] = useState("");
  const [currentDate, setCurrentDate] = useState("");
  const [isFullscreen, setIsFullscreen] = useState(false);

  // Live clock
  useEffect(() => {
    const updateTime = () => {
      const now = new Date();
      setCurrentTime(
        now.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit", second: "2-digit" })
      );
      setCurrentDate(
        now.toLocaleDateString([], { weekday: "short", month: "short", day: "numeric" })
      );
    };
    updateTime();
    const timer = setInterval(updateTime, 1000);
    return () => clearInterval(timer);
  }, []);

  // Determine token from query or local storage (supports both cashier token and display token)
  useEffect(() => {
    if (router.isReady) {
      const queryToken = router.query.token as string;
      if (queryToken) {
        setToken(queryToken);
        localStorage.setItem("vpos_display_token", queryToken);
      } else {
        const saved =
          localStorage.getItem("vpos_display_token") ||
          localStorage.getItem("vpos.pos-client-token");
        if (saved) setToken(saved);
      }
    }
  }, [router.isReady, router.query.token]);

  // Initial state fetch & WebSocket subscription
  useEffect(() => {
    if (!token) {
      setIsConnected(false);
      return;
    }

    let isMounted = true;

    const refreshState = () => {
      fetchPosDisplayState(token).then((data) => {
        if (isMounted && data) {
          setDisplayState(data);
        }
      });
    };

    // Hydrate once so a newly opened display receives the current cart.
    refreshState();

    // All subsequent updates arrive through Soketi. After a reconnect, fetch once
    // to recover any event that may have been sent while the socket was offline.
    const echo = getEcho();
    if (echo) {
      const pusher = (echo.connector as any)?.pusher;
      let connection: any = null;
      const handleConnected = () => {
        if (!isMounted) return;
        setIsConnected(true);
        refreshState();
      };
      const handleDisconnected = () => {
        if (isMounted) setIsConnected(false);
      };
      const handleError = (err: any) => {
        console.warn("[CustomerDisplay] Socket error:", err);
        if (isMounted) setIsConnected(false);
      };

      if (pusher?.connection) {
        connection = pusher.connection;
        setIsConnected(connection.state === "connected");
        connection.bind("connected", handleConnected);
        connection.bind("disconnected", handleDisconnected);
        connection.bind("unavailable", handleDisconnected);
        connection.bind("error", handleError);
      } else {
        setIsConnected(true);
      }

      const channel = echo.channel(`pos-display.${token}`);

      const handleSync = (event: PosDisplayPayload | { action: "promotions_updated" }) => {
        if (!isMounted) return;
        // Promotion-only events are consumed by PosDisplayPromotions and do
        // not contain cart state. Applying one here would blank the order.
        if (event.action === "promotions_updated") return;
        setDisplayState(event);
      };

      channel.listen(".PosDisplaySync", handleSync);

      const handleTestSync = (e: Event) => {
        const customEvent = e as CustomEvent<PosDisplayPayload>;
        if (!isMounted || !customEvent.detail) return;
        setDisplayState(customEvent.detail);
      };
      window.addEventListener("pos-display-test-sync", handleTestSync);

      return () => {
        isMounted = false;
        channel.stopListening(".PosDisplaySync", handleSync);
        window.removeEventListener("pos-display-test-sync", handleTestSync);
        connection?.unbind("connected", handleConnected);
        connection?.unbind("disconnected", handleDisconnected);
        connection?.unbind("unavailable", handleDisconnected);
        connection?.unbind("error", handleError);
      };
    }

    return () => {
      isMounted = false;
    };
  }, [token]);

  const toggleFullscreen = () => {
    if (!document.fullscreenElement) {
      document.documentElement.requestFullscreen().catch(() => undefined);
      setIsFullscreen(true);
    } else {
      document.exitFullscreen().catch(() => undefined);
      setIsFullscreen(false);
    }
  };

  const handlePairSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (inputToken.trim()) {
      const clean = inputToken.trim();
      setToken(clean);
      localStorage.setItem("vpos_display_token", clean);
      router.push(`/pos/display?token=${encodeURIComponent(clean)}`, undefined, { shallow: true });
    }
  };

  const cart = displayState.cart || [];
  const totals = displayState.totals;
  const payment = displayState.payment;
  const isIdle = !cart.length && displayState.action !== "payment_success";
  const baseCurrency: CurrencyInfo = {
    code: totals?.currencyCode || "USD",
    symbol: totals?.currencySymbol || "$",
    decimalPlaces: totals?.currencyCode === "KHR" ? 0 : 2,
  };
  const currencySymbol = baseCurrency.symbol;
  const totalItemCount = cart.reduce((sum, item) => sum + (item.quantity || 1), 0);

  return (
    <>
      <Head>
        <title>Customer Display | V-POS</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
        <link rel="icon" href="/branding/v-pos-mark.svg" type="image/svg+xml" />
        <link rel="icon" href="/branding/v-pos-mark-32.png" type="image/png" sizes="32x32" />
        <link rel="shortcut icon" href="/branding/v-pos-mark.svg" type="image/svg+xml" />
      </Head>

      <div className="customer-screen-root">
        {/* Responsive Header */}
        <header className="cfd-header">
          {/* Brand & Branch */}
          <div className="cfd-header-brand">
            <img
              src="/branding/v-pos-logo.svg"
              alt="V-POS"
              className="cfd-logo"
            />
            <span className="cfd-badge d-none d-sm-inline-block">
              {t("customerScreen")}
            </span>
            <div className="cfd-branch-text d-none d-md-flex">
              <i className="ri-map-pin-2-line text-primary"></i>
              <span>{displayState.branch?.name || t("mainTerminal")}</span>
            </div>
          </div>

          {/* Center Info: Live Date & Clock */}
          <div className="cfd-header-center d-none d-lg-flex">
            <span className="text-muted">{currentDate}</span>
            <span>•</span>
            <span className="font-monospace fw-bold text-dark">{currentTime}</span>
          </div>

          {/* Right Controls */}
          <div className="cfd-header-actions">
            {/* Live Indicator */}
            <div className={`cfd-status-pill ${isConnected ? "connected" : "connecting"}`}>
              <span className="cfd-status-dot"></span>
              <span className="d-none d-sm-inline">{isConnected ? t("liveWireless") : t("connecting")}</span>
              <span className="d-inline d-sm-none">{isConnected ? t("live") : "..."}</span>
            </div>

            {/* Mobile Clock if large clock hidden */}
            <div className="d-inline-flex d-lg-none font-monospace fs-12 fw-bold text-muted px-2 py-1 rounded bg-light border">
              {currentTime.slice(0, 5)}
            </div>

            {/* Language Switcher */}
            <button
              type="button"
              className="btn btn-sm btn-light border rounded-pill px-2.5 py-1 d-flex align-items-center gap-1 fs-12 fw-semibold"
              onClick={() => setLanguage(language === "en" ? "km" : "en")}
              title={language === "en" ? "ប្តូរទៅជាភាសាខ្មែរ" : "Switch to English"}
            >
              <i className="ri-global-line text-primary"></i>
              <span>{language === "en" ? "ខ្មែរ" : "EN"}</span>
            </button>

            {/* Fullscreen Button */}
            <button
              type="button"
              className="btn btn-sm btn-light border rounded-circle d-flex align-items-center justify-content-center p-0 cfd-fs-btn"
              onClick={toggleFullscreen}
              title={isFullscreen ? t("exitFullscreen") : t("enterFullscreen")}
            >
              <i className={isFullscreen ? "ri-fullscreen-exit-line" : "ri-fullscreen-line"}></i>
            </button>
          </div>
        </header>
        {/* Main Content Area */}
        <main className="cfd-main-container">
          {!token ? (
            /* Pairing screen when no token in URL */
            <div className="cfd-center-wrap">
              <div className="card shadow-lg border-0 rounded-4 p-4 p-sm-5 text-center cfd-card" style={{ maxWidth: "460px" }}>
                <div className="cfd-icon-circle mx-auto mb-3">
                  <i className="ri-tv-2-line fs-28"></i>
                </div>
                <h4 className="fw-bold mb-1 text-dark">{t("pairCustomerScreen")}</h4>
                <p className="text-muted fs-13 mb-4">
                  {t("enterCashierTokenOrScan")}
                </p>
                <form onSubmit={handlePairSubmit}>
                  <div className="mb-3">
                    <input
                      type="text"
                      className="form-control form-control-lg text-center font-monospace fs-14 border-2"
                      placeholder={t("pasteStationTokenHere")}
                      value={inputToken}
                      onChange={(e) => setInputToken(e.target.value)}
                      required
                    />
                  </div>
                  <button type="submit" className="btn btn-primary btn-lg w-100 fw-bold rounded-3">
                    {t("connectDisplay")}
                  </button>
                </form>
              </div>
            </div>
          ) : isIdle ? (
            /* IDLE WELCOME SCREEN: Mobile & Tablet/Desktop Responsive */
            <>
              {/* Mobile View (< 992px): Centered Welcome Card (No slide on mobile size) */}
              <div className="cfd-center-wrap d-flex d-lg-none">
                <div className="card border shadow-sm rounded-5 p-4 p-sm-5 text-center cfd-card" style={{ maxWidth: "560px", background: "#ffffff" }}>
                  <div className="cfd-logo-circle mx-auto mb-3 mb-sm-4 shadow-sm">
                    <img
                      src="/branding/v-pos-mark.svg"
                      alt="Logo"
                      style={{ width: "48px", height: "48px", objectFit: "contain" }}
                    />
                  </div>
                  <h2 className="fw-bolder text-dark mb-2 fs-22 fs-sm-28">
                    {t("welcomeTo", { branch: displayState.branch?.name || "V-POS" })}
                  </h2>
                  <p className="text-muted fs-14 fs-sm-15 mb-4 mx-auto" style={{ maxWidth: "380px" }}>
                    {t("placeItemsOnCounter")}
                  </p>
                  <div className="d-inline-flex align-items-center gap-2 px-3 py-1.5 rounded-pill bg-light border fs-12 text-muted mx-auto">
                    <span className="cfd-pulse-dot"></span>
                    <span className="fw-medium text-dark">{t("stationActiveReadyForOrder")}</span>
                  </div>
                </div>
              </div>

              {/* Screens Over Mobile Size (>= 992px): Grand Kiosk with Store Welcome + Hero Promo/Video Reel */}
              <div className="cfd-idle-desktop-layout d-none d-lg-flex">
                {/* Left Station Info Card */}
                <div className="card border shadow-sm rounded-4 p-4 p-xl-5 cfd-idle-welcome-card bg-white">
                  <div className="d-flex align-items-center gap-3 mb-4">
                    <div className="cfd-logo-circle shadow-xs" style={{ width: "64px", height: "64px" }}>
                      <img
                        src="/branding/v-pos-mark.svg"
                        alt="Logo"
                        style={{ width: "46px", height: "46px", objectFit: "contain" }}
                      />
                    </div>
                    <div>
                      <span className="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1 fs-11 fw-bold text-uppercase">
                        {t("customerScreen")}
                      </span>
                      <h4 className="fw-bolder text-dark mb-0 mt-1 fs-20">
                        {displayState.branch?.name || t("mainTerminal")}
                      </h4>
                    </div>
                  </div>

                  <div className="my-auto py-2">
                    <h2 className="fw-bolder text-dark mb-2 fs-30 lh-1.2">
                      {t("readyForYourOrder")}
                    </h2>
                    <p className="text-muted fs-15 mb-4" style={{ lineHeight: "1.5" }}>
                      {t("livePricingUpdateRealtime")}
                    </p>

                    <div className="d-inline-flex align-items-center gap-2 px-3.5 py-2 rounded-pill bg-light border fs-13 text-muted mb-4">
                      <span className="cfd-pulse-dot"></span>
                      <span className="fw-semibold text-dark">{t("readyForCheckout")}</span>
                    </div>

                    {/* Supported Payment Methods info */}
                    <div className="p-3.5 rounded-4 bg-light border">
                      <div className="fs-11 fw-bold text-muted mb-2.5 text-uppercase" style={{ letterSpacing: "0.05em" }}>
                        {t("acceptedPaymentOptions")}
                      </div>
                      <div className="d-flex flex-wrap gap-2">
                        <span className="badge bg-white text-dark border px-3 py-1.5 rounded-pill fs-12 fw-medium shadow-xs d-flex align-items-center gap-1.5">
                          <i className="ri-qr-code-line text-danger fs-14"></i> KHQR Bakong
                        </span>
                        <span className="badge bg-white text-dark border px-3 py-1.5 rounded-pill fs-12 fw-medium shadow-xs d-flex align-items-center gap-1.5">
                          <i className="ri-bank-card-line text-primary fs-14"></i> ABA Mobile
                        </span>
                        <span className="badge bg-white text-dark border px-3 py-1.5 rounded-pill fs-12 fw-medium shadow-xs d-flex align-items-center gap-1.5">
                          <i className="ri-visa-line text-info fs-14"></i> Visa / Master
                        </span>
                        <span className="badge bg-white text-dark border px-3 py-1.5 rounded-pill fs-12 fw-medium shadow-xs d-flex align-items-center gap-1.5">
                          <i className="ri-money-dollar-circle-line text-success fs-14"></i> {t("cash")}
                        </span>
                      </div>
                    </div>
                  </div>

                  <div className="pt-3 border-top d-flex align-items-center justify-content-between text-muted fs-13 mt-auto">
                    <span>{t("memberPerksApplied")}</span>
                    <i className="ri-shield-check-line text-success fs-18"></i>
                  </div>
                </div>

                {/* Right Hero Promotional Showcase & Looping Video Reel */}
                <div className="cfd-idle-promo-wrap">
                  <PosDisplayPromotions mode="hero" token={token} type="second_screen" />
                </div>
              </div>
            </>
          ) : displayState.action === "payment_success" ? (
            /* PAYMENT SUCCESS SCREEN */
            <div className="cfd-center-wrap">
              <div className="card shadow-lg border-0 rounded-5 p-4 p-sm-5 text-center cfd-card" style={{ maxWidth: "500px", background: "#ffffff" }}>
                <div className="cfd-success-circle mx-auto mb-3">
                  <i className="ri-check-line text-success fs-36"></i>
                </div>
                <h2 className="fw-bolder text-dark mb-1 fs-24">{t("paymentSuccessfulTitle")}</h2>
                <p className="text-muted fs-14 mb-4">{t("thankYouReceipt")}</p>

                <div className="cfd-receipt-card mb-4 text-start">
                  <div className="d-flex justify-content-between align-items-center mb-3 pb-2.5 border-bottom">
                    <span className="text-muted fs-13 fw-semibold">{t("paymentMethod")}</span>
                    <span className={`badge ${payment?.method === "cash" ? "bg-success-subtle text-success border-success-subtle" : "bg-primary-subtle text-primary border-primary-subtle"} border rounded-pill px-3 py-1.5 fs-12 fw-bold text-uppercase`}>
                      {payment?.method === "card"
                        ? `💳 ${t("card")} (${payment.provider || "Terminal"})`
                        : payment?.method === "qr"
                        ? `📱 KHQR / ${t("qrUpi")}`
                        : payment?.method === "bank"
                        ? `🏦 ${payment.bankName || payment.provider || t("bankTransfer")}`
                        : payment?.method === "later"
                        ? `⏱️ ${t("payLater")}`
                        : `💵 ${t("cash")}`}
                    </span>
                  </div>

                  {/* Purchased Items List with Thumbnails */}
                  {cart && cart.length > 0 && (
                    <div className="cfd-receipt-items mb-3 pb-2.5 border-bottom">
                      <div className="text-muted fs-11 fw-bold text-uppercase mb-2 d-flex justify-content-between">
                        <span>{t("itemsPurchased")}</span>
                        <span>{totalItemCount} {totalItemCount === 1 ? t("pc") : t("pcs")}</span>
                      </div>
                      <div className="d-flex flex-column gap-2" style={{ maxHeight: "150px", overflowY: "auto" }}>
                        {cart.map((item, idx) => {
                          const rawImg =
                            item.product?.image ||
                            (item as any).image ||
                            item.product?.image_url ||
                            (item as any).image_url ||
                            item.product?.thumbnail_url ||
                            (item as any).thumbnail_url;
                          const itemImg = normalizeMediaUrl(rawImg);
                          const qty = item.quantity || 1;
                          const isFree = Boolean(item.promotionReward);
                          const rawUnitPrice = Number(item.unitPrice || item.product?.price || 0);
                          const price = isFree ? 0 : rawUnitPrice;
                          return (
                            <div key={idx} className="d-flex align-items-center justify-content-between gap-2 fs-13">
                              <div className="d-flex align-items-center gap-2 text-truncate" style={{ maxWidth: "70%" }}>
                                <div className="cfd-receipt-thumb-wrap">
                                  <img
                                    src={itemImg}
                                    alt={item.product?.name || "Item"}
                                    className="cfd-receipt-thumb"
                                    onError={(e) => {
                                      e.currentTarget.src = "/assets/no-order-CCjZwO4J.svg";
                                    }}
                                  />
                                </div>
                                <span className="text-dark fw-medium text-truncate">{item.product?.name || "Item"}</span>
                                <span className="badge bg-light text-secondary border px-1.5 py-0 fs-10">x{qty}</span>
                              </div>
                              {isFree ? (
                                <span className="d-flex flex-column align-items-end font-monospace">
                                  <del className="fs-10 text-danger" style={{ opacity: 0.8 }}>
                                    {formatCurrency(qty * rawUnitPrice, baseCurrency)}
                                  </del>
                                  <strong className="text-success fs-12">
                                    {formatCurrency(0, baseCurrency)}
                                  </strong>
                                </span>
                              ) : (
                                <span className="font-monospace fw-semibold text-dark flex-shrink-0 fs-13">
                                  {formatCurrency(qty * price, baseCurrency)}
                                </span>
                              )}
                            </div>
                          );
                        })}
                      </div>
                    </div>
                  )}

                  <div className="d-flex flex-column gap-2.5">
                    <div className="d-flex justify-content-between align-items-center text-muted fs-13">
                      <span className="fw-medium">{t("totalAmountPaid")}</span>
                      <span className="text-dark fw-bold font-monospace fs-16">
                        {formatCurrency(Number(payment?.amount || totals?.totalPayable || 0), baseCurrency)}
                      </span>
                    </div>

                    {payment?.tendered && payment.tendered > 0 && payment.method === "cash" && (
                      <div className="d-flex justify-content-between align-items-center text-muted fs-13">
                        <span className="fw-medium">{t("cashReceived")}</span>
                        <span className="font-monospace fw-bold text-primary fs-15">
                          {formatCurrency(Number(payment.tendered), baseCurrency)}
                        </span>
                      </div>
                    )}

                    {payment?.change && payment.change > 0 ? (
                      <div className="d-flex justify-content-between align-items-center text-success fs-15 fw-bold pt-2.5 mt-1 border-top">
                        <span>{t("changeReturned")}</span>
                        <span className="font-monospace fs-18">
                          {formatCurrency(Number(payment.change), baseCurrency)}
                        </span>
                      </div>
                    ) : null}

                    {payment?.reference && (
                      <div className="d-flex justify-content-between text-muted fs-11 pt-2 mt-1 border-top font-monospace">
                        <span>{t("reference")}</span>
                        <span>{payment.reference}</span>
                      </div>
                    )}
                  </div>
                </div>

                <div className="d-flex align-items-center justify-content-center gap-1.5 text-muted fs-13">
                  <i className="ri-heart-3-line text-danger"></i>
                  <span>{t("haveAWonderfulDay")}</span>
                </div>
              </div>
            </div>
          ) : (
            /* ACTIVE TRANSACTION: RESPONSIVE SPLIT ON DESKTOP, STACK ON MOBILE */
            <div className="cfd-grid-layout">
              {/* Left Column (Desktop) / Main Content (Mobile): Items List */}
              <section className="card border shadow-sm rounded-4 cfd-items-section bg-white">
                {/* Header */}
                <div className="px-3 px-sm-4 py-3 border-bottom bg-light d-flex align-items-center justify-content-between flex-shrink-0">
                  <div className="d-flex align-items-center gap-2">
                    <i className="ri-shopping-cart-2-line text-primary fs-18"></i>
                    <h5 className="mb-0 fw-bold fs-16 text-dark">{t("orderItems")}</h5>
                  </div>
                  <span className="badge bg-white text-dark border shadow-xs rounded-pill px-3 py-1 fs-12 fw-bold">
                    {totalItemCount} {totalItemCount === 1 ? t("itemSingle") : t("itemPlural")}
                  </span>
                </div>

                {/* Items List */}
                <div className="cfd-items-scroll">
                  <div className="d-flex flex-column gap-2.5">
                    {cart.map((item, index) => {
                      const qty = item.quantity || 1;
                      const isFreeReward = Boolean(item.promotionReward);
                      const rawUnitPrice = Number(item.unitPrice ?? item.product?.price ?? 0);
                      const effectiveUnitPrice = isFreeReward ? 0 : rawUnitPrice;
                      const originalLineTotal = qty * rawUnitPrice;
                      const lineTotal = qty * effectiveUnitPrice;
                      const uomName = item.selectedUOM?.shortCode || item.selectedUOM?.name;
                      const variantLabel = item.selectedVariant?.name;
                      const itemCurrency: CurrencyInfo = item.product?.currency || baseCurrency;
                      const rawItemImage =
                        item.product?.image ||
                        (item as any).image ||
                        item.product?.image_url ||
                        (item as any).image_url ||
                        item.product?.thumbnail_url ||
                        (item as any).thumbnail_url;
                      const itemImage = normalizeMediaUrl(rawItemImage);

                      return (
                        <div
                          key={item.id || index}
                          className="cfd-item-row"
                        >
                          {/* Item Thumbnail with Attached Floating Quantity Pill Badge */}
                          <div className="cfd-item-thumb-container">
                            <div className="cfd-item-thumb-wrap">
                              <img
                                src={itemImage}
                                alt={item.product?.name || "Item"}
                                className="cfd-item-thumb"
                                loading="eager"
                                onError={(e) => {
                                  const target = e.currentTarget;
                                  if (target.src !== "/assets/no-order-CCjZwO4J.svg") {
                                    target.src = "/assets/no-order-CCjZwO4J.svg";
                                  }
                                }}
                              />
                            </div>
                            <span className="cfd-qty-badge" title={`Quantity: ${qty}`}>
                              {qty}x
                            </span>
                          </div>

                          {/* Title & metadata */}
                          <div className="cfd-item-meta">
                            <div className="cfd-item-title">
                              {item.product?.name || "Item"}
                            </div>
                            <div className="cfd-item-tags">
                              {uomName && (
                                <span className="badge bg-light text-secondary border px-2 py-0.5 rounded-pill fs-11">
                                  {uomName}
                                </span>
                              )}
                              {variantLabel && (
                                <span className="badge bg-light text-secondary border px-2 py-0.5 rounded-pill fs-11">
                                  {variantLabel}
                                </span>
                              )}
                              {item.promotionReward && (
                                <span className="badge bg-success-subtle text-success border border-success-subtle px-2 py-0.5 rounded-pill fs-11 fw-semibold">
                                  <i className="ri-gift-line me-1" aria-hidden="true"></i>
                                  {t("freeReward")}
                                </span>
                              )}
                              {isFreeReward ? (
                                <small className="d-flex align-items-center gap-1 fs-12 font-monospace">
                                  <del className="text-danger">
                                    {formatCurrency(rawUnitPrice, itemCurrency)}{t("perUnit")}
                                  </del>
                                  <strong className="text-success fw-bold">{t("free")}</strong>
                                </small>
                              ) : (
                                <span className="cfd-unit-price">
                                  {qty > 1 ? (
                                    <>
                                      <span className="fw-bold text-dark">{qty}</span> × {formatCurrency(effectiveUnitPrice, itemCurrency)}
                                    </>
                                  ) : (
                                    `@ ${formatCurrency(effectiveUnitPrice, itemCurrency)}`
                                  )}
                                </span>
                              )}
                            </div>
                          </div>

                          {/* Line total */}
                          <div className="cfd-item-total text-end">
                            {isFreeReward ? (
                              <div className="d-flex flex-column align-items-end font-monospace">
                                <del className="fs-12 text-danger fw-semibold lh-1" style={{ opacity: 0.85 }}>
                                  {formatCurrency(originalLineTotal, itemCurrency)}
                                </del>
                                <strong className="fs-18 text-success fw-bold lh-1 mt-1">
                                  {formatCurrency(0, itemCurrency)}
                                </strong>
                              </div>
                            ) : (
                              formatCurrency(lineTotal, itemCurrency)
                            )}
                          </div>
                        </div>
                      );
                    })}
                  </div>
                </div>
              </section>

              {/* Right Column (Desktop) / Bottom Card (Mobile): Summary & QR Payment */}
              <aside className="cfd-summary-section">
                {/* Promotional Showcase / Video Reel (Hidden on mobile size) */}
                {displayState.action !== "payment_pending" && (
                  <div className="d-none d-lg-block w-100 flex-shrink-0">
                    <PosDisplayPromotions mode="compact" token={token} type="second_screen" />
                  </div>
                )}

                {/* Calculation & Payment Card */}
                <div className="card border shadow-sm rounded-4 cfd-breakdown-card bg-white overflow-hidden">
                  {/* Header */}
                  <div className="px-3 px-sm-4 py-3 border-bottom bg-light d-flex align-items-center justify-content-between flex-shrink-0 cfd-breakdown-header">
                    <h6 className="mb-0 fw-bold fs-15 text-dark">{t("paymentBreakdown")}</h6>
                    {displayState.customer && (
                      <span className="badge bg-white text-dark border shadow-xs rounded-pill px-2.5 py-1 fs-12 fw-semibold">
                        <i className="ri-user-line me-1 text-primary"></i>
                        {displayState.customer.name}
                      </span>
                    )}
                  </div>

                  {/* Body */}
                  <div className="p-3 p-sm-4 d-flex flex-column justify-content-between gap-3 h-100 overflow-auto cfd-breakdown-body">

                  {/* Real-time Payment Modes */}
                  {displayState.action === "payment_pending" && payment?.method === "qr" ? (
                    <div className="cfd-qr-card text-center p-3 rounded-4 bg-light border">
                      <span className="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1 fs-11 mb-2 fw-semibold">
                        {t("scanToPayWithBankingApp")}
                      </span>
                      <h4 className="fw-bolder text-dark mb-2 fs-18 fs-sm-20">{t("khqrMobilePay")}</h4>
                      <div className="bg-white p-2.5 rounded-4 border d-inline-block shadow-sm mx-auto mb-2">
                        <img
                          src={normalizeMediaUrl(payment.qrCodeUrl || "/assets/qr-CvtWFzmv.png")}
                          alt="Scan QR"
                          className="cfd-payment-qr"
                        />
                      </div>
                      <p className="text-muted fs-11 fs-sm-12 mb-0">
                        {t("supportsBankApps")}
                      </p>
                    </div>
                  ) : displayState.action === "payment_pending" && payment?.method === "card" ? (
                    <div className="text-center p-3 p-sm-4 rounded-4 bg-light border">
                      <div
                        className="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-2"
                        style={{ width: "52px", height: "52px", background: "#eff6ff", color: "#3b82f6" }}
                      >
                        <i className="ri-bank-card-fill fs-24"></i>
                      </div>
                      <span className="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1 fs-11 mb-2 fw-semibold">
                        {t("cardTerminalReady")}
                      </span>
                      <h5 className="fw-bold text-dark mb-1 fs-16">
                        {payment.provider ? `${payment.provider} ${t("cardPayment")}` : t("cardPayment")}
                      </h5>
                      <p className="text-muted fs-12 mb-3">
                        {t("pleaseInsertSwipeOrTapCard")}
                      </p>
                      <div className="d-inline-flex align-items-center gap-2 px-3 py-1.5 rounded-pill bg-white border fs-12 text-muted shadow-xs">
                        <span className="cfd-pulse-dot"></span>
                        <span className="fw-medium text-dark">{t("awaitingTerminalApproval")}</span>
                      </div>
                    </div>
                  ) : displayState.action === "payment_pending" && payment?.method === "bank" ? (
                    <div className="text-center p-3 p-sm-4 rounded-4 bg-light border">
                      <div
                        className="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-2"
                        style={{ width: "52px", height: "52px", background: "#fef3c7", color: "#d97706" }}
                      >
                        <i className="ri-bank-line fs-24"></i>
                      </div>
                      <span className="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3 py-1 fs-11 mb-2 fw-semibold">
                        {t("bankTransfer")}
                      </span>
                      <h5 className="fw-bold text-dark mb-1 fs-16">
                        {payment.bankName || payment.provider || t("bankTransferDirect")}
                      </h5>
                      <p className="text-muted fs-12 mb-3">
                        {t("cashierVerifyingTransfer")}
                      </p>
                      <div className="d-inline-flex align-items-center gap-2 px-3 py-1.5 rounded-pill bg-white border fs-12 text-muted shadow-xs">
                        <span className="cfd-pulse-dot"></span>
                        <span className="fw-medium text-dark">{t("awaitingTransferConfirmation")}</span>
                      </div>
                    </div>
                  ) : displayState.action === "payment_pending" && payment?.method === "cash" ? (
                    <div className="cfd-payment-container text-center">
                      <div
                        className="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-2"
                        style={{ width: "56px", height: "56px", background: "#ecfdf5", color: "#10b981" }}
                      >
                        <i className="ri-money-dollar-circle-fill fs-28"></i>
                      </div>
                      <span className="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1 fs-11 mb-2 fw-semibold">
                        {t("cashPayment")}
                      </span>
                      <h5 className="fw-bold text-dark mb-1 fs-17">{t("cashPayment")}</h5>
                      <p className="text-muted fs-12 mb-3">
                        {payment?.tendered && payment.tendered > 0
                          ? t("cashierVerifyingCash")
                          : t("pleaseHandCashToCashier")}
                      </p>

                      <div className="cfd-cash-summary-card text-start">
                        <div className="cfd-summary-row text-muted fs-13">
                          <span className="fw-medium">{t("totalDue")}:</span>
                          <span className="font-monospace fw-bold text-dark fs-16">
                            {formatCurrency(Number(totals?.totalPayable || payment.amount || 0), baseCurrency)}
                          </span>
                        </div>
                        {payment?.tendered && payment.tendered > 0 ? (
                          <>
                            <div className="cfd-summary-row text-muted fs-13">
                              <span className="fw-medium">{t("cashReceived")}:</span>
                              <span className="font-monospace fw-bold text-primary fs-16">
                                {formatCurrency(Number(payment.tendered), baseCurrency)}
                              </span>
                            </div>
                            {payment?.change && payment.change > 0 ? (
                              <div className="cfd-summary-row text-success fs-14 fw-bold">
                                <span>{t("changeReturned")}:</span>
                                <span className="font-monospace fs-18 text-success">
                                  {formatCurrency(Number(payment.change), baseCurrency)}
                                </span>
                              </div>
                            ) : payment?.tendered && payment.tendered >= Number(totals?.totalPayable || payment.amount) ? (
                              <div className="cfd-summary-row text-success fs-13 fw-bold">
                                <span>{t("statusCol")}:</span>
                                <span className="font-monospace fs-13 text-success">
                                  {t("exactCashReceived")}
                                </span>
                              </div>
                            ) : (
                              <div className="cfd-summary-row text-warning fs-13 fw-semibold">
                                <span>{t("remainingBalance")}:</span>
                                <span className="font-monospace fw-bold">
                                  {formatCurrency(Math.max(0, Number((totals?.totalPayable || payment.amount) - payment.tendered)), baseCurrency)}
                                </span>
                              </div>
                            )}
                          </>
                        ) : null}
                      </div>

                      {payment?.change && payment.change > 0 ? (
                        <div className="cfd-status-badge success">
                          <span className="cfd-pulse-dot" style={{ backgroundColor: "#10b981" }}></span>
                          <span className="fw-bold fs-13">{t("changeDueAmount", { amount: formatCurrency(Number(payment.change), baseCurrency) })}</span>
                        </div>
                      ) : payment?.tendered && payment.tendered >= Number(totals?.totalPayable || payment.amount) ? (
                        <div className="cfd-status-badge success">
                          <span className="cfd-pulse-dot" style={{ backgroundColor: "#10b981" }}></span>
                          <span className="fw-bold fs-13">{t("exactCashPaidInFull")}</span>
                        </div>
                      ) : payment?.tendered && payment.tendered > 0 ? (
                        <div className="cfd-status-badge warning">
                          <span className="cfd-pulse-dot" style={{ backgroundColor: "#f59e0b" }}></span>
                          <span className="fw-medium fs-13">{t("remainingAmount", { amount: formatCurrency(Math.max(0, Number((totals?.totalPayable || payment.amount) - payment.tendered)), baseCurrency) })}</span>
                        </div>
                      ) : (
                        <div className="cfd-status-badge neutral">
                          <span className="cfd-pulse-dot" style={{ backgroundColor: "#10b981" }}></span>
                          <span className="fw-medium text-dark fs-13">{t("awaitingCashTender")}</span>
                        </div>
                      )}
                    </div>
                  ) : displayState.action === "payment_pending" && payment?.method === "later" ? (
                    <div className="text-center p-3 p-sm-4 rounded-4 bg-light border">
                      <div
                        className="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-2"
                        style={{ width: "52px", height: "52px", background: "#f1f5f9", color: "#64748b" }}
                      >
                        <i className="ri-time-line fs-24"></i>
                      </div>
                      <span className="badge bg-secondary-subtle text-secondary border rounded-pill px-3 py-1 fs-11 mb-2 fw-semibold">
                        {t("payLater")}
                      </span>
                      <h5 className="fw-bold text-dark mb-1 fs-16">{t("chargeToCustomerAccount")}</h5>
                      <p className="text-muted fs-12 mb-0">
                        {displayState.customer ? t("accountCustomer", { name: displayState.customer.name }) : t("recordingOnStoreAccount")}
                      </p>
                    </div>
                  ) : null}

                  {/* Calculations */}
                  {totals && (
                    <div className="d-flex flex-column gap-2 mt-auto cfd-breakdown-totals">
                      <div className="d-flex justify-content-between text-muted fs-13">
                        <span>{t("subtotal")}</span>
                        <span className="font-monospace text-dark fw-semibold">
                          {formatCurrency(Number(totals.subTotal), baseCurrency)}
                        </span>
                      </div>

                      {Number(totals.discountAmount) > 0 && (
                        <div className="d-flex justify-content-between text-success fs-13 fw-semibold">
                          <span>{t("discountApplied")}</span>
                          <span className="font-monospace">
                            -{formatCurrency(Number(totals.discountAmount), baseCurrency)}
                          </span>
                        </div>
                      )}

                      {Number(totals.gstAmount) > 0 && (
                        <div className="d-flex justify-content-between text-muted fs-13">
                          <span>{t("gstTax")}</span>
                          <span className="font-monospace text-dark fw-semibold">
                            {formatCurrency(Number(totals.gstAmount), baseCurrency)}
                          </span>
                        </div>
                      )}

                      <hr className="my-1.5" />

                      {/* Prominent Large Total Due */}
                      <div className="cfd-total-banner">
                        <span className="cfd-total-label">
                          {t("totalDue")}
                        </span>
                        <div className="cfd-total-value">
                          {formatCurrency(Number(totals.totalPayable), baseCurrency)}
                        </div>
                      </div>
                    </div>
                  )}
                </div>
              </div>
            </aside>
            </div>
          )}
        </main>

        {/* Scoped CSS for 100% smooth mobile and tablet responsiveness */}
        <style jsx>{`
          .customer-screen-root {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            min-height: 100dvh;
            background-color: #f8fafc;
            color: #0f172a;
            font-family: 'Plus Jakarta Sans', 'Inter', system-ui, -apple-system, sans-serif;
            overflow-x: hidden;
          }

          .cfd-header {
            height: 60px;
            background-color: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 16px;
            flex-shrink: 0;
            z-index: 100;
          }

          .cfd-header-brand {
            display: flex;
            align-items: center;
            gap: 10px;
          }

          .cfd-logo {
            height: 28px;
            width: auto;
            object-fit: contain;
          }

          .cfd-badge {
            background-color: #eef2ff;
            color: #4f46e5;
            border: 1px solid #e0e7ff;
            border-radius: 9999px;
            padding: 2px 10px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.04em;
          }

          .cfd-branch-text {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #64748b;
            font-size: 13px;
            font-weight: 500;
          }

          .cfd-header-center {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
          }

          .cfd-header-actions {
            display: flex;
            align-items: center;
            gap: 8px;
          }

          .cfd-status-pill {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 600;
            border: 1px solid;
          }

          .cfd-status-pill.connected {
            background-color: #ecfdf5;
            color: #059669;
            border-color: #a7f3d0;
          }

          .cfd-status-pill.connecting {
            background-color: #fffbeb;
            color: #d97706;
            border-color: #fde68a;
          }

          .cfd-status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background-color: currentColor;
          }

          .cfd-status-pill.connected .cfd-status-dot {
            box-shadow: 0 0 6px #10b981;
          }

          .cfd-fs-btn {
            width: 32px;
            height: 32px;
          }

          .cfd-mobile-total-banner {
            position: sticky;
            top: 0;
            z-index: 90;
            background: linear-gradient(135deg, #4f46e5, #4338ca);
            color: #ffffff;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);
          }

          .cfd-main-container {
            flex: 1;
            padding: 16px;
            display: flex;
            overflow: auto;
          }

          .cfd-center-wrap {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: auto;
          }

          .cfd-icon-circle {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background-color: #eef2ff;
            color: #4f46e5;
            display: flex;
            align-items: center;
            justify-content: center;
          }

          .cfd-logo-circle {
            width: 84px;
            height: 84px;
            border-radius: 50%;
            background: linear-gradient(135deg, #eef2ff, #f8fafc);
            border: 2px solid #e0e7ff;
            display: flex;
            align-items: center;
            justify-content: center;
          }

          .cfd-success-circle {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background-color: #ecfdf5;
            border: 2px solid #10b981;
            display: flex;
            align-items: center;
            justify-content: center;
          }

          .cfd-pulse-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background-color: #10b981;
            box-shadow: 0 0 6px #10b981;
          }

          .cfd-payment-container {
            padding: 24px 20px;
            border-radius: 20px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
          }

          .cfd-receipt-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 22px 24px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.03);
          }

          .cfd-cash-summary-card {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 14px 20px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.03);
            margin-top: 14px;
            margin-bottom: 16px;
          }

          .cfd-summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 9px 0;
          }

          .cfd-summary-row:not(:first-child) {
            border-top: 1px solid #f1f5f9;
          }

          .cfd-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            border-radius: 9999px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
            border: 1px solid;
          }

          .cfd-status-badge.success {
            background-color: #ecfdf5;
            border-color: #a7f3d0;
            color: #059669;
          }

          .cfd-status-badge.warning {
            background-color: #fffbeb;
            border-color: #fde68a;
            color: #d97706;
          }

          .cfd-status-badge.neutral {
            background-color: #ffffff;
            border-color: #e2e8f0;
            color: #475569;
          }

          @media (max-width: 575.98px) {
            .cfd-payment-container {
              padding: 16px 14px;
            }
            .cfd-receipt-card {
              padding: 16px 16px;
            }
            .cfd-cash-summary-card {
              padding: 12px 14px;
            }
          }

          /* Responsive Grid Layout */
          .cfd-grid-layout {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 16px;
          }

          .cfd-items-section {
            display: flex;
            flex-direction: column;
            overflow: hidden;
            width: 100%;
          }

          .cfd-items-scroll {
            padding: 12px;
            overflow-y: auto;
          }

          .cfd-item-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            background-color: #ffffff;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.03);
            gap: 12px;
            transition: all 0.2s ease;
          }

          .cfd-item-row:hover {
            border-color: #cbd5e1;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
          }

          .cfd-item-thumb-container {
            position: relative;
            flex-shrink: 0;
            display: inline-flex;
          }

          .cfd-item-thumb-wrap {
            width: 58px;
            height: 58px;
            border-radius: 14px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 1px solid #e2e8f0;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            flex-shrink: 0;
          }

          .cfd-item-row:hover .cfd-item-thumb-wrap {
            transform: scale(1.04);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.08);
          }

          .cfd-item-thumb {
            width: 100% !important;
            height: 100% !important;
            max-width: 100% !important;
            max-height: 100% !important;
            object-fit: contain;
            border-radius: 10px;
            display: block;
          }

          .cfd-qty-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            min-width: 24px;
            height: 22px;
            padding: 0 5px;
            border-radius: 9999px;
            background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
            color: #ffffff;
            font-size: 11px;
            font-weight: 800;
            font-family: monospace;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 5px rgba(79, 70, 229, 0.35);
            border: 2px solid #ffffff;
            z-index: 2;
            letter-spacing: -0.02em;
          }

          .cfd-receipt-thumb-wrap {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 1px solid #e2e8f0;
            padding: 2px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            overflow: hidden;
          }

          .cfd-receipt-thumb {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 6px;
          }

          .cfd-item-meta {
            flex: 1;
            min-width: 0;
          }

          .cfd-item-title {
            font-weight: 700;
            color: #0f172a;
            font-size: 15px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.3;
          }

          .cfd-item-tags {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 3px;
            flex-wrap: wrap;
          }

          .cfd-unit-price {
            font-size: 12px;
            color: #64748b;
            font-family: monospace;
          }

          .cfd-item-total {
            font-family: monospace;
            font-weight: 800;
            font-size: 17px;
            color: #0f172a;
            flex-shrink: 0;
          }

          .cfd-summary-section {
            display: flex;
            flex-direction: column;
            width: 100%;
          }

          .cfd-payment-qr {
            width: 180px;
            height: 180px;
            display: block;
          }

          .cfd-total-banner {
            padding: 14px 16px;
            border-radius: 14px;
            background: linear-gradient(135deg, #4f46e5, #4338ca);
            color: #ffffff;
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.25);
          }

          .cfd-total-label {
            font-size: 12px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.75);
            text-transform: uppercase;
            letter-spacing: 0.05em;
          }

          .cfd-total-value {
            font-weight: 800;
            font-family: monospace;
            font-size: 32px;
            line-height: 1.1;
            margin-top: 4px;
            letter-spacing: -0.02em;
          }

          /* Mobile & Portrait Tablet View (< 992px): Pin Payment Breakdown to Footer */
          @media (max-width: 991.98px) {
            .customer-screen-root {
              height: 100vh;
              height: 100dvh;
              display: flex;
              flex-direction: column;
              overflow: hidden;
            }

            .cfd-header {
              flex-shrink: 0;
            }

            .cfd-main-container {
              flex: 1;
              min-height: 0;
              padding: 0;
              display: flex;
              flex-direction: column;
              overflow: hidden;
            }

            .cfd-center-wrap {
              padding: 16px;
              height: 100%;
              overflow-y: auto;
            }

            .cfd-grid-layout {
              flex: 1;
              min-height: 0;
              display: flex;
              flex-direction: column;
              height: 100%;
              overflow: hidden;
              gap: 0;
            }

            .cfd-items-section {
              flex: 1;
              min-height: 0;
              display: flex;
              flex-direction: column;
              overflow: hidden;
              border-radius: 0 !important;
              border-left: none !important;
              border-right: none !important;
              border-top: none !important;
              background-color: #f8fafc !important;
            }

            .cfd-items-scroll {
              flex: 1;
              min-height: 0;
              overflow-y: auto;
              -webkit-overflow-scrolling: touch;
              padding: 10px 12px;
            }

            .cfd-summary-section {
              flex-shrink: 0;
              width: 100%;
              position: relative;
              z-index: 100;
              margin-top: auto;
            }

            .cfd-breakdown-card {
              border-top-left-radius: 20px !important;
              border-top-right-radius: 20px !important;
              border-bottom-left-radius: 0 !important;
              border-bottom-right-radius: 0 !important;
              border-left: none !important;
              border-right: none !important;
              border-bottom: none !important;
              border-top: 1px solid #e2e8f0 !important;
              box-shadow: 0 -4px 20px rgba(15, 23, 42, 0.1) !important;
              margin: 0;
              background: #ffffff;
            }

            .cfd-breakdown-header {
              padding: 10px 16px !important;
              background: #f8fafc;
            }

            .cfd-breakdown-body {
              padding: 12px 16px max(14px, env(safe-area-inset-bottom, 14px)) 16px !important;
              gap: 8px !important;
              height: auto !important;
              max-height: 55vh;
              overflow-y: auto;
              -webkit-overflow-scrolling: touch;
            }

            .cfd-breakdown-totals {
              margin-top: 0 !important;
              gap: 6px !important;
            }

            .cfd-total-banner {
              padding: 10px 14px;
              border-radius: 12px;
            }

            .cfd-total-label {
              font-size: 11px;
            }

            .cfd-total-value {
              font-size: 26px;
              margin-top: 2px;
            }
          }

          /* Small Mobile Breakpoint (< 576px) */
          @media (max-width: 575.98px) {
            .cfd-item-row {
              padding: 8px 10px;
              gap: 10px;
            }
            .cfd-item-thumb-wrap {
              width: 52px;
              height: 52px;
              border-radius: 12px;
              padding: 3px;
              flex-shrink: 0;
            }
            .cfd-item-thumb {
              width: 100% !important;
              height: 100% !important;
              object-fit: contain;
              display: block;
            }
            .cfd-qty-badge {
              min-width: 20px;
              height: 19px;
              font-size: 10px;
              top: -5px;
              right: -5px;
              border-width: 1.5px;
            }
            .cfd-item-title {
              font-size: 14px;
            }
            .cfd-item-total {
              font-size: 15px;
            }
            .cfd-breakdown-header {
              padding: 8px 14px !important;
            }
            .cfd-breakdown-header h6 {
              font-size: 14px !important;
            }
            .cfd-breakdown-body {
              padding: 10px 14px max(12px, env(safe-area-inset-bottom, 12px)) 14px !important;
            }
            .cfd-total-banner {
              padding: 8px 12px;
              border-radius: 10px;
            }
            .cfd-total-label {
              font-size: 10px;
            }
            .cfd-total-value {
              font-size: 24px;
              margin-top: 2px;
            }
            .cfd-payment-qr {
              width: 140px;
              height: 140px;
            }
          }

          /* Tablet Landscape & Desktop Breakpoint (>= 992px) */
          @media (min-width: 992px) {
            .customer-screen-root {
              height: 100vh;
              overflow: hidden;
            }

            .cfd-header {
              padding: 0 24px;
            }

            .cfd-main-container {
              padding: 24px;
              overflow: hidden;
            }

            .cfd-grid-layout {
              flex-direction: row;
              height: 100%;
              overflow: hidden;
            }

            .cfd-items-section {
              flex: 1;
              height: 100%;
            }

            .cfd-items-scroll {
              flex: 1;
              padding: 16px;
            }

            .cfd-summary-section {
              width: 440px;
              height: 100%;
              flex-shrink: 0;
              display: flex;
              flex-direction: column;
              gap: 16px;
              overflow: hidden;
            }

            .cfd-breakdown-card {
              flex: 1;
              min-height: 0;
              display: flex;
              flex-direction: column;
              justify-content: space-between;
            }

            .cfd-payment-qr {
              width: 200px;
              height: 200px;
            }

            .cfd-total-value {
              font-size: 38px;
            }

            .cfd-idle-desktop-layout {
              width: 100%;
              height: 100%;
              display: flex;
              gap: 24px;
            }

            .cfd-idle-welcome-card {
              width: 440px;
              height: 100%;
              display: flex;
              flex-direction: column;
              justify-content: space-between;
              flex-shrink: 0;
            }

            .cfd-idle-promo-wrap {
              flex: 1;
              height: 100%;
              min-width: 0;
              display: flex;
            }
          }
        `}</style>
      </div>
    </>
  );
}
