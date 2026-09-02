import React from "react";
import { Customer } from "@/types/pos-types";

interface PosTopbarProps {
  currentDate: string;
  currentTime: string;
  heldCount: number;
  isFullscreen: boolean;
  onToggleFullscreen: () => void;
  onOpenHoldModal: () => void;
  openDropdown: string | null;
  onToggleDropdown: (name: string) => void;
  cashierName?: string;
}

export function PosTopbar({
  currentDate,
  currentTime,
  heldCount,
  isFullscreen,
  onToggleFullscreen,
  onOpenHoldModal,
  openDropdown,
  onToggleDropdown,
  cashierName = "Lucas Ethan",
}: PosTopbarProps) {
  return (
    <header className="pos-topbar">
      {/* Brand & Terminal Info */}
      <div className="d-flex align-items-center gap-2.5 flex-shrink-0">
        <div className="d-flex align-items-center gap-2 flex-shrink-0">
          <img
            src="/assets/main-logo-CWEU2RA-.png"
            alt="GotPOS"
            height="22"
            className="logo-dark d-block flex-shrink-0"
            style={{ height: "22px", width: "auto", objectFit: "contain" }}
          />
          <span className="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-0.5 fs-11 fw-semibold d-none d-sm-inline-block">
            POS PRO
          </span>
        </div>

        <div className="vr d-none d-xl-block mx-1" style={{ height: "20px" }}></div>

        {/* Full Terminal & Live Clock on Desktop (>= 1200px) */}
        <div className="d-none d-xl-flex align-items-center gap-3 text-muted fs-13 flex-shrink-0">
          <div className="d-flex align-items-center gap-2">
            <span
              className="d-inline-block rounded-circle bg-success"
              style={{ width: "8px", height: "8px" }}
            ></span>
            <span className="fw-medium text-body">Terminal #01</span>
          </div>
          <span>•</span>
          <div className="d-flex align-items-center gap-2">
            <i className="ri-calendar-line text-primary"></i>
            <span>{currentDate || "18 Aug 2026"}</span>
          </div>
          <span>•</span>
          <div className="d-flex align-items-center gap-2 font-monospace fw-semibold text-body">
            <i className="ri-time-line text-primary"></i>
            <span>{currentTime || "01:55:00 PM"}</span>
          </div>
        </div>

        {/* Compact Clock on Tablets (768px - 1199px) */}
        <div className="d-none d-lg-flex d-xl-none align-items-center gap-2 text-muted fs-12 font-monospace fw-semibold flex-shrink-0">
          <span
            className="d-inline-block rounded-circle bg-success"
            style={{ width: "7px", height: "7px" }}
          ></span>
          <i className="ri-time-line text-primary"></i>
          <span>{currentTime || "01:55 PM"}</span>
        </div>
      </div>

      {/* Top Controls with Clean Responsive Gaps */}
      <div className="pos-topbar-controls flex-shrink-0">
        {/* Shift Status Badge */}
        <span className="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 fs-12 d-none d-lg-inline-flex align-items-center gap-1.5 flex-shrink-0">
          <span
            className="d-inline-block rounded-circle bg-success"
            style={{ width: "6px", height: "6px" }}
          ></span>
          <span className="d-none d-lg-inline">Shift </span>Active
        </span>

        {/* Hold Orders Button with dynamic list count */}
        <button
          type="button"
          className="btn btn-sm btn-light border d-flex align-items-center gap-1.5 rounded-pill px-2.5 py-1 flex-shrink-0"
          onClick={onOpenHoldModal}
          title="View Held Orders & Restore"
        >
          <i className="ri-pause-circle-line text-warning fs-14"></i>
          <span className="fs-12 fw-medium">Hold ({heldCount})</span>
        </button>

        {/* Fullscreen Button */}
        <button
          type="button"
          className="btn btn-sm btn-light border size-8 rounded-circle d-flex align-items-center justify-content-center p-0 flex-shrink-0"
          onClick={onToggleFullscreen}
          title="Toggle Fullscreen"
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
            title="Notifications"
          >
            <i className="ri-notification-3-line"></i>
            <span className="position-absolute top-0 end-0 p-1 bg-danger border border-light rounded-circle"></span>
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
              <h6 className="mb-0 fs-14 fw-bold">Notifications</h6>
              <span className="badge bg-primary rounded-pill">3 New</span>
            </div>
            <div className="p-2" style={{ maxHeight: "250px", overflowY: "auto" }}>
              <div className="p-2 border-bottom d-flex gap-2">
                <div className="avatar size-8 bg-success-subtle text-success rounded-circle d-flex align-items-center justify-content-center flex-shrink-0">
                  <i className="ri-check-line"></i>
                </div>
                <div>
                  <p className="mb-0 fs-12 fw-medium">Order #GOT1697 completed</p>
                  <small className="text-muted fs-11">Paid $245.00 via UPI • 2m ago</small>
                </div>
              </div>
              <div className="p-2 border-bottom d-flex gap-2">
                <div className="avatar size-8 bg-warning-subtle text-warning rounded-circle d-flex align-items-center justify-content-center flex-shrink-0">
                  <i className="ri-alert-line"></i>
                </div>
                <div>
                  <p className="mb-0 fs-12 fw-medium">Low stock: Men’s Jeans (3 left)</p>
                  <small className="text-muted fs-11">SKU JN-MEN-006 • 15m ago</small>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Cashier Profile */}
        <div className="d-flex align-items-center gap-2 ps-2 border-start flex-shrink-0">
          <img
            src="/assets/user-71-RNjOCE17.png"
            alt="Cashier"
            className="rounded-circle border flex-shrink-0"
            style={{ width: "32px", height: "32px", objectFit: "cover" }}
          />
          <div className="d-none d-xxl-block text-start lh-1">
            <span className="fs-13 fw-semibold d-block">{cashierName}</span>
            <small className="text-muted fs-11">Cashier</small>
          </div>
        </div>
      </div>
    </header>
  );
}
