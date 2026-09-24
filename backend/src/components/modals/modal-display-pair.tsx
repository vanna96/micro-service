import React, { useState, useEffect } from "react";
import { getEcho } from "@/lib/echo";
import {
  syncPosDisplay,
  fetchPosDisplayPromotions,
  savePosDisplayPromotions,
  PromoSlide,
} from "@/lib/pos-api";
import { useTranslation } from "@/lib/i18n/i18n";

interface ModalDisplayPairProps {
  clientToken: string;
  onClose: () => void;
  branchName?: string;
  activeCartCount: number;
}

export function ModalDisplayPair({
  clientToken,
  onClose,
  branchName,
  activeCartCount,
}: ModalDisplayPairProps) {
  const { t } = useTranslation();
  const [activeTab, setActiveTab] = useState<"connection" | "promotions">("connection");
  const [copied, setCopied] = useState(false);
  const [isConnected, setIsConnected] = useState(false);
  const [testSent, setTestSent] = useState(false);
  const [isEditingIp, setIsEditingIp] = useState(false);

  // Promotions & Media Setup State
  const [selectedPlacement, setSelectedPlacement] = useState<string>("second_screen");
  const [promoSlides, setPromoSlides] = useState<PromoSlide[]>([]);
  const [isLoadingSlides, setIsLoadingSlides] = useState(false);
  const [isSavingSlides, setIsSavingSlides] = useState(false);
  const [saveSuccess, setSaveSuccess] = useState(false);

  // Follow the hostname currently serving the POS whenever it is network-accessible.
  // The configured address is used only when this browser opened the POS on localhost.
  const browserHost = typeof window !== "undefined" ? window.location.hostname : "";
  const isLoopbackHost =
    browserHost === "localhost" ||
    browserHost.endsWith(".localhost") ||
    browserHost === "127.0.0.1" ||
    browserHost === "::1";
  const currentNetworkHost = browserHost && !isLoopbackHost ? browserHost : "";
  const defaultLanIp = currentNetworkHost || process.env.NEXT_PUBLIC_LAN_IP?.trim() || browserHost || "localhost";
  const [lanIp, setLanIp] = useState<string>(() => {
    if (typeof window !== "undefined") {
      if (currentNetworkHost) {
        return currentNetworkHost;
      }

      const saved = localStorage.getItem("vpos_lan_ip");
      // Remove the former hardcoded default when it is still cached in the browser.
      if (saved === "192.168.0.66") {
        localStorage.removeItem("vpos_lan_ip");
      } else if (saved) {
        return saved;
      }
    }
    return defaultLanIp;
  });

  const [ipInputDraft, setIpInputDraft] = useState(lanIp);

  const port = typeof window !== "undefined" && window.location.port ? `:${window.location.port}` : ":8880";
  const protocol = typeof window !== "undefined" ? window.location.protocol : "http:";

  // Wi-Fi URL specifically for phone/tablet scanner
  const wifiDisplayUrl = `${protocol}//${lanIp}${port}/pos/display?token=${encodeURIComponent(clientToken)}`;

  // Local URL for clicking from this computer
  const localDisplayUrl = typeof window !== "undefined"
    ? `${window.location.origin}/pos/display?token=${encodeURIComponent(clientToken)}`
    : wifiDisplayUrl;

  useEffect(() => {
    if (typeof window !== "undefined") {
      const echo = getEcho();
      if (echo) {
        setIsConnected(true);
      }
    }
  }, [clientToken]);

  // Load promotions when switching to promotions tab or changing target placement
  useEffect(() => {
    if (activeTab === "promotions") {
      setIsLoadingSlides(true);
      fetchPosDisplayPromotions(clientToken, selectedPlacement).then((slides) => {
        setPromoSlides(slides || []);
        setIsLoadingSlides(false);
      });
    }
  }, [activeTab, selectedPlacement, clientToken]);

  const handleSaveIp = (e: React.FormEvent) => {
    e.preventDefault();
    const cleanIp = ipInputDraft.trim();
    if (cleanIp) {
      setLanIp(cleanIp);
      localStorage.setItem("vpos_lan_ip", cleanIp);
      setIsEditingIp(false);
    }
  };

  const handleResetIp = () => {
    setLanIp(defaultLanIp);
    setIpInputDraft(defaultLanIp);
    localStorage.removeItem("vpos_lan_ip");
    setIsEditingIp(false);
  };

  const copyWifiUrl = () => {
    if (navigator?.clipboard && wifiDisplayUrl) {
      navigator.clipboard.writeText(wifiDisplayUrl);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    }
  };

  const sendTestPing = async () => {
    setTestSent(true);
    await syncPosDisplay({
      token: clientToken,
      action: "cart_updated",
      totals: {
        subTotal: 12.5,
        discountAmount: 1.5,
        gstAmount: 1.1,
        totalPayable: 12.1,
        totalItemCount: 1,
        currencySymbol: "$",
      },
      cart: [
        {
          id: "test-item",
          product: {
            id: 999,
            name: "✨ Wireless Screen Test Product",
            price: 12.5,
            sku: "TEST-CFD",
          },
          quantity: 1,
          unitPrice: 12.5,
        },
      ],
      branch: { name: branchName || "Main Terminal" },
    });
    setTimeout(() => setTestSent(false), 3000);
  };

  // Promotion Slides Management
  const handleAddSlide = () => {
    const newSlide: PromoSlide = {
      id: `custom-${Date.now()}`,
      type: "gradient",
      badge: "🔥 SPECIAL OFFER",
      badgeBg: "rgba(255, 255, 255, 0.95)",
      badgeColor: "#4f46e5",
      title: "New Promotional Deal",
      subtitle: "Exclusive discount available at counter today.",
      discount: "20% OFF",
      mediaUrl: "",
      gradient: "linear-gradient(135deg, #1e1b4b 0%, #312e81 45%, #4338ca 100%)",
      icon: "ri-gift-line",
      tag: "Limited time promotion",
      placement: selectedPlacement,
    };
    setPromoSlides([...promoSlides, newSlide]);
  };

  const handleRemoveSlide = (index: number) => {
    setPromoSlides(promoSlides.filter((_, i) => i !== index));
  };

  const handleUpdateSlide = (index: number, field: keyof PromoSlide, value: any) => {
    const updated = [...promoSlides];
    updated[index] = { ...updated[index], [field]: value };
    setPromoSlides(updated);
  };

  const handleSavePromotions = async () => {
    setIsSavingSlides(true);
    const res = await savePosDisplayPromotions(promoSlides, clientToken, selectedPlacement);
    setIsSavingSlides(false);
    if (res.success) {
      setSaveSuccess(true);
      setTimeout(() => setSaveSuccess(false), 3500);
    }
  };

  // High-contrast clean QR code encoding the current Wi-Fi LAN IP
  const qrApiUrl = `https://api.qrserver.com/v1/create-qr-code/?size=260x260&margin=10&data=${encodeURIComponent(
    wifiDisplayUrl
  )}`;

  return (
    <div
      className="modal fade show d-block"
      tabIndex={-1}
      style={{ zIndex: 1070, backgroundColor: "rgba(0,0,0,0.65)" }}
    >
      <div
        className="modal-dialog modal-dialog-centered"
        style={{ maxWidth: activeTab === "promotions" ? "680px" : "500px", transition: "max-width 0.25s ease" }}
      >
        <div className="modal-content border-0 shadow-2xl rounded-4 overflow-hidden bg-white">
          {/* Header */}
          <div className="modal-header border-bottom bg-light px-4 py-3 d-flex align-items-center justify-content-between">
            <div className="d-flex align-items-center gap-2">
              <div
                className="rounded-circle d-flex align-items-center justify-content-center"
                style={{ width: "36px", height: "36px", background: "#eef2ff", color: "#4f46e5" }}
              >
                <i className={activeTab === "promotions" ? "ri-slideshow-line fs-18" : "ri-tv-2-line fs-18"}></i>
              </div>
              <div>
                <h6 className="modal-title fw-bold mb-0 text-dark fs-15">
                  {activeTab === "promotions" ? t("promotionsAndVideoSetup") : t("customerDisplayPairing")}
                </h6>
                <small className="text-muted fs-11">
                  {activeTab === "promotions"
                    ? t("managePromoSlides")
                    : t("pairAnyDeviceWireless")}
                </small>
              </div>
            </div>
            <button
              type="button"
              className="btn-close"
              onClick={onClose}
              aria-label="Close"
            ></button>
          </div>

          {/* Navigation Tabs */}
          <div className="px-4 pt-3 pb-1 bg-white border-bottom">
            <ul className="nav nav-pills nav-fill p-1 bg-light rounded-3 border">
              <li className="nav-item">
                <button
                  type="button"
                  className={`nav-link py-1.5 fs-12 fw-bold d-flex align-items-center justify-content-center gap-1.5 ${
                    activeTab === "connection" ? "active shadow-xs" : "text-muted"
                  }`}
                  onClick={() => setActiveTab("connection")}
                >
                  <i className="ri-qr-code-line"></i>
                  <span>{t("connectionAndWifi")}</span>
                </button>
              </li>
              <li className="nav-item">
                <button
                  type="button"
                  className={`nav-link py-1.5 fs-12 fw-bold d-flex align-items-center justify-content-center gap-1.5 ${
                    activeTab === "promotions" ? "active shadow-xs" : "text-muted"
                  }`}
                  onClick={() => setActiveTab("promotions")}
                >
                  <i className="ri-movie-line"></i>
                  <span>{t("promotionsAndVideoReel")}</span>
                </button>
              </li>
            </ul>
          </div>

          {/* Modal Body */}
          <div className="modal-body p-4" style={{ maxHeight: "75vh", overflowY: "auto" }}>
            {activeTab === "connection" ? (
              /* TAB 1: CONNECTION & QR CODE */
              <>
                {/* Status bar */}
                <div className="d-flex align-items-center justify-content-between p-2.5 rounded-3 mb-3 bg-light border">
                  <div className="d-flex align-items-center gap-2">
                    <span
                      className={`d-inline-block rounded-circle ${
                        isConnected ? "bg-success" : "bg-warning"
                      }`}
                      style={{
                        width: "8px",
                        height: "8px",
                        boxShadow: isConnected ? "0 0 6px #10b981" : "none",
                      }}
                    ></span>
                    <div>
                      <div className="fs-12 fw-bold text-dark">
                        {isConnected ? t("soketiConnected") : t("connectingToSocket")}
                      </div>
                      <div className="fs-11 text-muted">
                        {t("stationToken")}:{" "}
                        <span className="font-monospace text-primary fw-medium">
                          {clientToken.slice(0, 8)}...
                        </span>
                      </div>
                    </div>
                  </div>
                  <span className="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1 fs-11">
                    Port 6001 • Wireless
                  </span>
                </div>

                {/* QR Code section */}
                <div className="text-center my-2">
                  <div className="bg-white p-2.5 rounded-4 border d-inline-block shadow-sm">
                    <img
                      src={qrApiUrl}
                      alt="Scan QR with phone"
                      width={210}
                      height={210}
                      style={{ display: "block" }}
                    />
                  </div>
                  <p className="text-muted fs-12 mt-2 mb-0">
                    {t("scanWithPhoneCamera")}
                  </p>
                </div>

                {/* LAN IP Config Box */}
                <div className="p-3 rounded-3 bg-light border mb-3">
                  <div className="d-flex align-items-center justify-content-between mb-1">
                    <span className="fs-12 fw-bold text-dark d-flex align-items-center gap-1">
                      <i className="ri-wifi-line text-primary"></i> {t("wifiHostIp")}
                    </span>
                    {!isEditingIp ? (
                      <button
                        type="button"
                        className="btn btn-link btn-sm p-0 fs-11 text-decoration-none fw-semibold"
                        onClick={() => {
                          setIpInputDraft(lanIp);
                          setIsEditingIp(true);
                        }}
                      >
                        {t("changeIp")}
                      </button>
                    ) : (
                      <button
                        type="button"
                        className="btn btn-link btn-sm p-0 fs-11 text-muted text-decoration-none"
                        onClick={() => setIsEditingIp(false)}
                      >
                        {t("cancel")}
                      </button>
                    )}
                  </div>

                  {isEditingIp ? (
                    <form onSubmit={handleSaveIp} className="mt-2">
                      <div className="input-group input-group-sm mb-2">
                        <input
                          type="text"
                          className="form-control font-monospace fs-12"
                          placeholder="e.g. 192.168.0.66"
                          value={ipInputDraft}
                          onChange={(e) => setIpInputDraft(e.target.value)}
                          required
                        />
                        <button type="submit" className="btn btn-primary btn-sm px-3 fw-bold">
                          {t("apply")}
                        </button>
                      </div>
                      <div className="d-flex justify-content-between align-items-center">
                        <small className="text-muted fs-11">
                          {t("findWifiIpHint")}
                        </small>
                        <button
                          type="button"
                          className="btn btn-link btn-sm p-0 fs-11 text-danger text-decoration-none"
                          onClick={handleResetIp}
                        >
                          {t("resetDefault")}
                        </button>
                      </div>
                    </form>
                  ) : (
                    <div className="d-flex align-items-center justify-content-between">
                      <span className="font-monospace fs-12 fw-bold text-primary">
                        {lanIp}
                      </span>
                      <span className="text-muted fs-11">
                        {t("encodedIntoQr")}
                      </span>
                    </div>
                  )}
                </div>

                {/* Direct Wi-Fi URL Box with Copy Button */}
                <div className="p-2.5 rounded-3 bg-light border mb-3 d-flex align-items-center justify-content-between gap-2">
                  <span className="font-monospace fs-11 text-dark fw-bold text-truncate">
                    {wifiDisplayUrl}
                  </span>
                  <button
                    type="button"
                    onClick={copyWifiUrl}
                    className={`btn btn-sm ${
                      copied ? "btn-success" : "btn-outline-secondary"
                    } px-3 py-1 rounded-pill fs-11 d-flex align-items-center gap-1 flex-shrink-0`}
                  >
                    <i className={copied ? "ri-check-line" : "ri-file-copy-line"}></i>
                    <span>{copied ? t("copied") : t("copyUrl")}</span>
                  </button>
                </div>

                {/* Action buttons */}
                <div className="d-grid gap-2">
                  <a
                    href={localDisplayUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="btn btn-primary d-flex align-items-center justify-content-center gap-2 fw-semibold py-2 rounded-3 text-white text-decoration-none"
                  >
                    <i className="ri-external-link-line fs-14"></i>
                    <span>{t("openInNewTabThisComputer")}</span>
                  </a>

                  <button
                    type="button"
                    onClick={sendTestPing}
                    disabled={testSent}
                    className="btn btn-outline-secondary btn-sm d-flex align-items-center justify-content-center gap-1.5 py-1.5 rounded-3"
                  >
                    <i className="ri-broadcast-line text-primary"></i>
                    <span>{testSent ? t("testPacketDispatched") : t("sendTestPingToDisplay")}</span>
                  </button>
                </div>
              </>
            ) : (
              /* TAB 2: PROMOTIONS & VIDEO SETUP (BACKEND SETUP) */
              <div>
                {/* Target Type/Placement Switcher */}
                <div className="d-flex align-items-center justify-content-between p-2.5 rounded-3 bg-light border mb-3">
                  <div className="d-flex align-items-center gap-2">
                    <span className="fs-12 fw-bold text-dark">{t("targetScreenChannel")}</span>
                    <div className="btn-group btn-group-sm">
                      <button
                        type="button"
                        className={`btn ${
                          selectedPlacement === "second_screen" ? "btn-primary" : "btn-outline-secondary"
                        } fw-bold`}
                        onClick={() => setSelectedPlacement("second_screen")}
                      >
                        <i className="ri-tv-line me-1"></i> {t("secondScreenTab")}
                      </button>
                      <button
                        type="button"
                        className={`btn ${
                          selectedPlacement === "mobile" ? "btn-primary" : "btn-outline-secondary"
                        } fw-bold`}
                        onClick={() => setSelectedPlacement("mobile")}
                      >
                        <i className="ri-smartphone-line me-1"></i> {t("mobileAppTab")}
                      </button>
                      <button
                        type="button"
                        className={`btn ${
                          selectedPlacement === "web" ? "btn-primary" : "btn-outline-secondary"
                        } fw-bold`}
                        onClick={() => setSelectedPlacement("web")}
                      >
                        <i className="ri-global-line me-1"></i> {t("websiteTab")}
                      </button>
                    </div>
                  </div>

                  <button
                    type="button"
                    className="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fs-11 fw-bold d-flex align-items-center gap-1"
                    onClick={handleAddSlide}
                  >
                    <i className="ri-add-line"></i> {t("addSlide")}
                  </button>
                </div>

                {saveSuccess && (
                  <div className="alert alert-success py-2 px-3 rounded-3 fs-12 mb-3 d-flex align-items-center justify-content-between">
                    <span>
                      <i className="ri-checkbox-circle-fill me-1.5 text-success"></i>
                      {t("promotionsSavedSuccess")}
                    </span>
                    <i className="ri-check-double-line fs-16"></i>
                  </div>
                )}

                {isLoadingSlides ? (
                  <div className="text-center py-4 text-muted fs-13">
                    <div className="spinner-border spinner-border-sm text-primary me-2"></div>
                    {t("loadingBackendSlides")}
                  </div>
                ) : (
                  /* Slide Cards List */
                  <div className="d-flex flex-column gap-3">
                    {promoSlides.map((slide, idx) => (
                      <div key={slide.id || idx} className="card border rounded-3 p-3 bg-white shadow-xs">
                        <div className="d-flex align-items-center justify-content-between pb-2 mb-2 border-bottom">
                          <div className="d-flex align-items-center gap-2">
                            <span className="badge bg-light text-dark border font-monospace fs-11">
                              #{idx + 1}
                            </span>
                            <span
                              className="badge rounded-pill px-2.5 py-1 fs-10 fw-bold text-uppercase"
                              style={{
                                backgroundColor: slide.badgeBg || "#eef2ff",
                                color: slide.badgeColor || "#4f46e5",
                              }}
                            >
                              {slide.badge}
                            </span>
                            {slide.discount && (
                              <span className="badge bg-warning text-dark px-2 py-0.5 rounded-pill fs-10 fw-bold">
                                {slide.discount}
                              </span>
                            )}
                          </div>

                          <div className="d-flex align-items-center gap-2">
                            <select
                              className="form-select form-select-sm py-0 fs-11"
                              style={{ width: "105px" }}
                              value={slide.type}
                              onChange={(e) =>
                                handleUpdateSlide(idx, "type", e.target.value as any)
                              }
                            >
                              <option value="video">🎬 {t("video")}</option>
                              <option value="gradient">🎨 {t("gradient")}</option>
                              <option value="image">🖼 {t("image")}</option>
                            </select>

                            <button
                              type="button"
                              className="btn btn-sm btn-outline-danger p-0 d-flex align-items-center justify-content-center rounded-circle"
                              style={{ width: "24px", height: "24px" }}
                              onClick={() => handleRemoveSlide(idx)}
                              title={t("deleteSlide")}
                            >
                              <i className="ri-delete-bin-line fs-12"></i>
                            </button>
                          </div>
                        </div>

                        {/* Slide Fields Grid */}
                        <div className="row g-2">
                          <div className="col-8">
                            <label className="fs-11 fw-semibold text-muted mb-0.5">{t("title")}</label>
                            <input
                              type="text"
                              className="form-control form-control-sm fs-12"
                              value={slide.title}
                              onChange={(e) => handleUpdateSlide(idx, "title", e.target.value)}
                              placeholder="Promotion Headline"
                              required
                            />
                          </div>
                          <div className="col-4">
                            <label className="fs-11 fw-semibold text-muted mb-0.5">{t("discountTag")}</label>
                            <input
                              type="text"
                              className="form-control form-control-sm fs-12"
                              value={slide.discount || ""}
                              onChange={(e) => handleUpdateSlide(idx, "discount", e.target.value)}
                              placeholder="e.g. 50% OFF 2ND"
                            />
                          </div>

                          <div className="col-6">
                            <label className="fs-11 fw-semibold text-muted mb-0.5">{t("categoryBadge")}</label>
                            <input
                              type="text"
                              className="form-control form-control-sm fs-12"
                              value={slide.badge}
                              onChange={(e) => handleUpdateSlide(idx, "badge", e.target.value)}
                              placeholder="e.g. 🍹 HAPPY HOUR"
                              required
                            />
                          </div>
                          <div className="col-6">
                            <label className="fs-11 fw-semibold text-muted mb-0.5">{t("footerTag")}</label>
                            <input
                              type="text"
                              className="form-control form-control-sm fs-12"
                              value={slide.tag || ""}
                              onChange={(e) => handleUpdateSlide(idx, "tag", e.target.value)}
                              placeholder="e.g. Mix & match beverage"
                            />
                          </div>

                          <div className="col-12">
                            <label className="fs-11 fw-semibold text-muted mb-0.5">{t("subtitleOfferDetails")}</label>
                            <input
                              type="text"
                              className="form-control form-control-sm fs-12"
                              value={slide.subtitle || ""}
                              onChange={(e) => handleUpdateSlide(idx, "subtitle", e.target.value)}
                              placeholder="Description of the promotion"
                            />
                          </div>

                          {/* Media URL for Video / Image */}
                          {slide.type === "video" && (
                            <div className="col-12">
                              <div className="d-flex align-items-center justify-content-between mb-0.5">
                                <label className="fs-11 fw-semibold text-primary mb-0">
                                  <i className="ri-movie-line me-1"></i> {t("videoMediaUrl")}
                                </label>
                                <div className="d-flex align-items-center gap-1.5">
                                  <button
                                    type="button"
                                    className="btn btn-link btn-sm p-0 fs-10 text-decoration-none text-success fw-bold"
                                    onClick={() => handleUpdateSlide(idx, "mediaUrl", "/uploads/slider/videos/store-promo.mp4")}
                                  >
                                    MinIO S3 Video
                                  </button>
                                  <span className="text-muted fs-10">•</span>
                                  <button
                                    type="button"
                                    className="btn btn-link btn-sm p-0 fs-10 text-decoration-none text-muted"
                                    onClick={() => handleUpdateSlide(idx, "mediaUrl", "/videos/store-promo.mp4")}
                                  >
                                    Local Reel
                                  </button>
                                </div>
                              </div>
                              <input
                                type="text"
                                className="form-control form-control-sm fs-12 font-monospace"
                                value={slide.mediaUrl || ""}
                                onChange={(e) => handleUpdateSlide(idx, "mediaUrl", e.target.value)}
                                placeholder="e.g. /videos/store-promo.mp4"
                              />
                            </div>
                          )}

                          {slide.type === "gradient" && (
                            <div className="col-12">
                              <label className="fs-11 fw-semibold text-muted mb-0.5">{t("cssGradient")}</label>
                              <input
                                type="text"
                                className="form-control form-control-sm fs-11 font-monospace"
                                value={slide.gradient || ""}
                                onChange={(e) => handleUpdateSlide(idx, "gradient", e.target.value)}
                                placeholder="linear-gradient(...)"
                              />
                            </div>
                          )}
                        </div>
                      </div>
                    ))}
                  </div>
                )}

                {/* Save to Backend Action Button */}
                <div className="mt-3 pt-3 border-top d-flex align-items-center justify-content-between">
                  <span className="fs-11 text-muted">
                    {t("channel")} <strong className="text-dark">{selectedPlacement}</strong> • {promoSlides.length} {t("slidesConfigured")}
                  </span>
                  <button
                    type="button"
                    className="btn btn-primary px-4 py-2 fw-bold rounded-3 d-flex align-items-center gap-2"
                    onClick={handleSavePromotions}
                    disabled={isSavingSlides || isLoadingSlides}
                  >
                    {isSavingSlides ? (
                      <>
                        <span className="spinner-border spinner-border-sm"></span>
                        <span>{t("saving")}</span>
                      </>
                    ) : (
                      <>
                        <i className="ri-save-line fs-14"></i>
                        <span>{t("saveAndSyncDisplay")}</span>
                      </>
                    )}
                  </button>
                </div>
              </div>
            )}
          </div>

          {/* Footer */}
          <div className="modal-footer bg-light border-top px-4 py-2.5 d-flex justify-content-between">
            <span className="fs-11 text-muted">
              {activeTab === "promotions"
                ? t("promotionsBroadcastHint")
                : t("liveUpdatesScanHint")}
            </span>
            <button type="button" className="btn btn-sm btn-light border px-3" onClick={onClose}>
              {t("done")}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
