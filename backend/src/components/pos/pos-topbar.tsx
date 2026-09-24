import React from "react";
import { PosBranch } from "@/types/pos-types";
import { useTranslation } from "@/lib/i18n/i18n";

interface PosTopbarProps {
  branches: PosBranch[];
  selectedBranchId: string;
  isBranchLoading: boolean;
  onBranchChange: (branchId: string) => void;
  currentDate: string;
  currentTime: string;
  heldCount: number;
  isFullscreen: boolean;
  onToggleFullscreen: () => void;
  onOpenHoldModal: () => void;
  openDropdown: string | null;
  onToggleDropdown: (name: string) => void;
  cashierName?: string;
  switchTenantUrl?: string;
  onOpenDisplayModal?: () => void;
  customerDisplayUrl?: string;
}

export function PosTopbar({
  branches,
  selectedBranchId,
  isBranchLoading,
  onBranchChange,
  currentDate,
  currentTime,
  heldCount,
  isFullscreen,
  onToggleFullscreen,
  onOpenHoldModal,
  openDropdown,
  onToggleDropdown,
  cashierName = "",
  switchTenantUrl,
  onOpenDisplayModal,
  customerDisplayUrl,
}: PosTopbarProps) {
  const { t, locale, setLocale } = useTranslation();

  return (
    <header className="pos-topbar">
      {/* Brand & Terminal Info */}
      <div className="d-flex align-items-center gap-2.5 flex-shrink-0">
        <div className="d-flex align-items-center gap-2 flex-shrink-0">
          <img
            src="/branding/v-pos-logo.svg"
            alt="V-POS"
            height="30"
            className="logo-dark d-block flex-shrink-0"
            style={{ height: "30px", width: "auto", objectFit: "contain" }}
          />
          <span className="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-0.5 fs-11 fw-semibold d-none d-sm-inline-block">
            POS PRO
          </span>
        </div>

        <div className="vr d-none d-xl-block mx-1" style={{ height: "20px" }}></div>

        {/* Full Terminal & Live Clock on Desktop (>= 1200px) */}
        <div className="d-none d-xl-flex align-items-center gap-3 text-muted fs-13 flex-shrink-0">
          <label className="pos-branch-picker">
            <span
              className="pos-branch-status-dot d-inline-block rounded-circle bg-success"
            ></span>
            <i className="ri-map-pin-2-line text-primary" aria-hidden="true"></i>
            <span className="visually-hidden">{t("branch")}</span>
            <select
              className="pos-branch-select"
              value={selectedBranchId}
              disabled={isBranchLoading}
              onChange={(event) => onBranchChange(event.target.value)}
              aria-label={t("branch")}
              title={t("branch")}
            >
              {isBranchLoading ? (
                <option value="">{t("loadingBranches")}</option>
              ) : (
                <>
                  <option value="all">{t("allBranch")}</option>
                  {branches.map((branch) => (
                    <option key={branch.id} value={branch.id}>
                      {branch.name}
                    </option>
                  ))}
                </>
              )}
            </select>
          </label>
          <span>•</span>
          <div className="d-flex align-items-center gap-2">
            <i className="ri-calendar-line text-primary"></i>
            <span>{currentDate}</span>
          </div>
          <span>•</span>
          <div className="d-flex align-items-center gap-2 font-monospace fw-semibold text-body">
            <i className="ri-time-line text-primary"></i>
            <span>{currentTime}</span>
          </div>
        </div>

        {/* Compact Clock on Tablets (768px - 1199px) */}
        <div className="d-none d-lg-flex d-xl-none align-items-center gap-2 text-muted fs-12 font-monospace fw-semibold flex-shrink-0">
          <span
            className="d-inline-block rounded-circle bg-success"
            style={{ width: "7px", height: "7px" }}
          ></span>
          <i className="ri-time-line text-primary"></i>
          <span>{currentTime}</span>
        </div>
      </div>

      {/* Top Controls with Clean Responsive Gaps */}
      <div className="pos-topbar-controls flex-shrink-0">
        {/* Hold Orders Button with dynamic list count */}
        <button
          type="button"
          className="btn btn-sm btn-light border d-flex align-items-center gap-1.5 rounded-pill px-2.5 py-1 flex-shrink-0"
          onClick={onOpenHoldModal}
          title={t("hold")}
        >
          <i className="ri-pause-circle-line text-warning fs-14"></i>
          <span className="fs-12 fw-medium">{t("hold")} ({heldCount})</span>
        </button>

        {/* Customer Screen Button Group */}
        {onOpenDisplayModal && (
          <div className="btn-group rounded-pill border bg-light p-0.5 flex-shrink-0" role="group">
            <button
              type="button"
              className="btn btn-sm btn-light border-0 d-flex align-items-center gap-1.5 rounded-pill px-2.5 py-1"
              onClick={onOpenDisplayModal}
              title={t("secondScreen")}
            >
              <span
                className="d-inline-block rounded-circle bg-success"
                style={{ width: "6px", height: "6px", boxShadow: "0 0 4px #22c55e" }}
              ></span>
              <i className="ri-tv-2-line text-primary fs-13"></i>
              <span className="fs-12 fw-medium">{t("secondScreen")}</span>
            </button>
            {customerDisplayUrl && (
              <a
                href={customerDisplayUrl}
                target="_blank"
                rel="noopener noreferrer"
                className="btn btn-sm btn-light border-0 d-flex align-items-center justify-content-center px-2 py-1 rounded-pill text-muted text-decoration-none"
                title={t("secondScreen")}
              >
                <i className="ri-external-link-line fs-13 text-primary"></i>
              </a>
            )}
          </div>
        )}

        {/* Language Toggle */}
        <button
          type="button"
          className="btn btn-sm btn-light border d-flex align-items-center gap-1.5 rounded-pill px-2.5 py-1 flex-shrink-0 fw-semibold fs-12"
          onClick={() => setLocale(locale === "en" ? "km" : "en")}
          title={t("language")}
          style={{ minWidth: "54px" }}
        >
          <i className="ri-translate-2 fs-14 text-primary"></i>
          <span>{locale === "en" ? "KM" : "EN"}</span>
        </button>

        {/* Switch Store Button */}
        <a
          href={switchTenantUrl || "http://localhost:8880/"}
          className="btn btn-sm btn-dark rounded-pill px-3 py-1 d-inline-flex align-items-center gap-1.5 fs-12 fw-semibold text-white flex-shrink-0"
          style={{
            background: "#09090b",
            border: "1px solid rgba(255, 255, 255, 0.15)",
            boxShadow: "0 2px 6px rgba(0, 0, 0, 0.12)",
            textDecoration: "none",
            height: "30px",
            marginRight: "12px",
          }}
          title={t("switchStore")}
        >
          <i className="ri-store-2-line fs-13"></i>
          <span>{t("switchStore")}</span>
        </a>

        {/* Fullscreen Button */}
        <button
          type="button"
          className="btn btn-sm btn-light border size-8 rounded-circle d-flex align-items-center justify-content-center p-0 flex-shrink-0"
          onClick={onToggleFullscreen}
          title={isFullscreen ? t("exitFullscreen") : t("enterFullscreen")}
        >
          <i className={isFullscreen ? "ri-fullscreen-exit-line" : "ri-fullscreen-line"}></i>
        </button>

        {/* Notifications Dropdown */}
        <div
          className={`dropdown ${
            openDropdown === "notifications" ? "show" : ""
          } flex-shrink-0`}
        >
          <button
            className="btn btn-sm btn-light border size-8 rounded-circle d-flex align-items-center justify-content-center p-0 position-relative"
            type="button"
            onClick={(e) => {
              e.stopPropagation();
              onToggleDropdown("notifications");
            }}
            title={t("notifications")}
          >
            <i className="ri-notification-3-line"></i>
          </button>
          <div
            className={`dropdown-menu dropdown-menu-end shadow-lg border-0 p-0 ${
              openDropdown === "notifications" ? "show" : ""
            }`}
            style={{
              width: "320px",
              position: "absolute",
              right: 0,
              top: "100%",
              zIndex: 1050,
            }}
          >
            <div className="d-flex align-items-center justify-content-between p-3 border-bottom bg-light rounded-top">
              <h6 className="mb-0 fs-14 fw-bold">{t("notifications")}</h6>
            </div>
            <div className="p-2" style={{ maxHeight: "250px", overflowY: "auto" }}>
              <div className="p-4 text-center text-muted">
                <i className="ri-notification-off-line fs-4 d-block mb-1"></i>
                <span className="fs-12">{t("noNotifications")}</span>
              </div>
            </div>
          </div>
        </div>

        {/* Cashier Profile */}
        <div className="d-flex align-items-center gap-2 ps-2 border-start flex-shrink-0">
          <span
            className="rounded-circle border bg-light d-flex align-items-center justify-content-center flex-shrink-0"
            style={{ width: "32px", height: "32px" }}
          >
            <i className="ri-user-line text-muted"></i>
          </span>
          <div className="d-none d-xxl-block text-start lh-1">
            <span className="fs-13 fw-semibold d-block">{cashierName || t("cashier")}</span>
            <small className="text-muted fs-11">{t("cashier")}</small>
          </div>
        </div>
      </div>
    </header>
  );
}
