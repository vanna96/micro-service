import React, { useState } from "react";
import { HeldOrder } from "@/types/pos-types";
import { formatCurrency, useCurrency } from "@/lib/currency";
import { useTranslation } from "@/lib/i18n/i18n";

interface ModalHeldOrdersProps {
  heldOrders: HeldOrder[];
  cartCount: number;
  totalPayable: number;
  onRestoreOrder: (order: HeldOrder) => void;
  onDeleteOrder: (order: HeldOrder) => void;
  onParkCurrentCart: (ref: string, notes: string) => void;
  onClose: () => void;
}

export function ModalHeldOrders({
  heldOrders,
  cartCount,
  totalPayable,
  onRestoreOrder,
  onDeleteOrder,
  onParkCurrentCart,
  onClose,
}: ModalHeldOrdersProps) {
  const currency = useCurrency();
  const { t } = useTranslation();
  const [tab, setTab] = useState<"list" | "park">("list");
  const [refInput, setRefInput] = useState<string>("");
  const [noteInput, setNoteInput] = useState<string>("");

  return (
    <div className="modal fade show d-block" tabIndex={-1} style={{ zIndex: 1060 }}>
      <div className="modal-dialog modal-dialog-centered modal-lg">
        <div className="modal-content shadow-lg border-0 rounded-4 overflow-hidden">
          {/* Header */}
          <div className="modal-header border-bottom bg-light px-4 py-3 d-flex align-items-center justify-content-between">
            <div className="d-flex align-items-center gap-3">
              <div className="avatar size-8 bg-warning-subtle text-warning rounded-circle d-flex align-items-center justify-content-center">
                <i className="ri-pause-circle-fill fs-18"></i>
              </div>
              <div>
                <h6 className="modal-title fw-bold mb-0">{t("heldOrdersAndParkedBills")}</h6>
                <small className="text-muted">
                  {heldOrders.length} {t("ordersCurrentlyHeld")}
                </small>
              </div>
            </div>

            <div className="d-flex align-items-center gap-2">
              <div className="btn-group btn-group-sm">
                <button
                  type="button"
                  className={`btn px-3 py-1 ${
                    tab === "list" ? "btn-primary" : "btn-outline-secondary bg-white"
                  }`}
                  onClick={() => setTab("list")}
                >
                  <i className="ri-list-check-2 me-1"></i>
                  {t("viewHeld")} ({heldOrders.length})
                </button>
                <button
                  type="button"
                  className={`btn px-3 py-1 ${
                    tab === "park" ? "btn-primary" : "btn-outline-secondary bg-white"
                  }`}
                  onClick={() => setTab("park")}
                  disabled={cartCount === 0}
                  title={cartCount === 0 ? t("cartIsEmptyTitle") : t("holdCurrentCart")}
                >
                  <i className="ri-add-circle-line me-1"></i>
                  {t("holdCurrentCart")}
                </button>
              </div>
              <button type="button" className="btn-close ms-2" onClick={onClose}></button>
            </div>
          </div>

          <div className="modal-body p-4">
            {/* TAB 1: LIST OF HELD ORDERS */}
            {tab === "list" && (
              <div>
                {heldOrders.length === 0 ? (
                  <div className="text-center py-5">
                    <img
                      src="/assets/no-order-CCjZwO4J.svg"
                      alt="No held orders"
                      style={{ width: "100px", opacity: 0.5 }}
                      className="mx-auto mb-3"
                    />
                    <h6 className="fw-bold text-muted mb-1">{t("noOrdersOnHold")}</h6>
                    <p className="text-muted fs-12 mb-3">
                      {t("allOrdersCleared")}
                    </p>
                    {cartCount > 0 && (
                      <button
                        type="button"
                        className="btn btn-sm btn-primary rounded-pill px-3"
                        onClick={() => setTab("park")}
                      >
                        {t("parkCurrentActiveCart")}
                      </button>
                    )}
                  </div>
                ) : (
                  <div className="d-flex flex-column gap-3">
                    {heldOrders.map((ho) => (
                      <div
                        key={ho.id}
                        className="border rounded-3 p-3 bg-white hover-shadow transition-all"
                      >
                        <div className="d-flex flex-wrap align-items-center justify-content-between mb-2">
                          <div className="d-flex align-items-center gap-2">
                            <span className="badge bg-warning text-dark font-monospace fw-bold px-2 py-1">
                              {ho.id}
                            </span>
                            <h6 className="fw-bold text-body mb-0 fs-14">
                              {ho.reference}
                            </h6>
                            <span className="badge bg-light border text-muted fs-11">
                              {ho.orderType}
                            </span>
                          </div>
                          <div className="d-flex align-items-center gap-2">
                            <span className="text-muted fs-11">
                              <i className="ri-time-line me-1"></i>
                              {ho.createdAt}
                            </span>
                          </div>
                        </div>

                        {/* Customer & Notes */}
                        <div className="d-flex flex-wrap align-items-center justify-content-between fs-12 text-muted mb-2.5 pb-2 border-bottom">
                          <div className="d-flex align-items-center gap-2">
                            <i className="ri-user-3-line text-primary"></i>
                            <span className="fw-medium text-body">
                              {ho.customer.name}
                            </span>
                            {ho.notes && (
                              <>
                                <span>•</span>
                                <span className="fst-italic text-muted">{ho.notes}</span>
                              </>
                            )}
                          </div>
                          <span className="badge bg-light border text-body fs-11">
                            {ho.cart.reduce((s, i) => s + i.quantity, 0)} {t("items")}
                          </span>
                        </div>

                        {/* Items Preview Chips */}
                        <div className="d-flex flex-wrap gap-1.5 mb-3">
                          {ho.cart.map((item) => (
                            <span
                              key={item.id}
                              className="badge bg-light text-secondary border px-2 py-1 fs-11 fw-normal"
                            >
                              {item.quantity}x {item.product.name}
                              {item.selectedUOM ? ` (${item.selectedUOM.name})` : ""}
                            </span>
                          ))}
                        </div>

                        {/* Footer Amount & Action Buttons */}
                        <div className="d-flex align-items-center justify-content-between pt-2 border-top">
                          <div>
                            <span className="text-muted fs-11 d-block">
                              {t("orderTotal")}
                            </span>
                            <h5 className="fw-bolder text-primary mb-0 font-monospace">
                              {formatCurrency(ho.totalPayable, currency)}
                            </h5>
                          </div>

                          <div className="d-flex align-items-center gap-2">
                            <button
                              type="button"
                              className="btn btn-sm btn-outline-danger px-2.5 py-1.5 rounded-2 d-flex align-items-center gap-1"
                              onClick={() => onDeleteOrder(ho)}
                              title={t("discard")}
                            >
                              <i className="ri-delete-bin-line"></i>
                              <span className="d-none d-sm-inline">{t("discard")}</span>
                            </button>
                            <button
                              type="button"
                              className="btn btn-sm btn-success text-white px-3.5 py-1.5 rounded-2 fw-semibold d-flex align-items-center gap-1.5 shadow-xs"
                              onClick={() => onRestoreOrder(ho)}
                            >
                              <i className="ri-play-circle-line fs-14"></i>
                              <span>{t("resumeSwitchToOrder")}</span>
                            </button>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            )}

            {/* TAB 2: PARK CURRENT CART */}
            {tab === "park" && (
              <div>
                <div className="bg-light p-3 rounded-3 mb-3 border d-flex justify-content-between align-items-center">
                  <div>
                    <span className="text-muted fs-12 d-block">
                      {t("currentActiveCart")}
                    </span>
                    <h6 className="fw-bold mb-0 text-body">
                      {cartCount} {t("items")}
                    </h6>
                  </div>
                  <h5 className="fw-bolder text-primary mb-0 font-monospace">
                    {formatCurrency(totalPayable, currency)}
                  </h5>
                </div>

                <div className="mb-3">
                  <label className="form-label fs-12 fw-bold text-body">
                    {t("holdReferenceName")}{" "}
                    <span className="text-danger">*</span>
                  </label>
                  <input
                    type="text"
                    className="form-control"
                    placeholder={t("enterReferenceName")}
                    value={refInput}
                    onChange={(e) => setRefInput(e.target.value)}
                    autoFocus
                  />
                </div>

                <div className="mb-3">
                  <label className="form-label fs-12 fw-bold text-body">
                    {t("holdReasonRemarks")}
                  </label>
                  <textarea
                    className="form-control"
                    rows={2}
                    placeholder={t("enterOptionalNote")}
                    value={noteInput}
                    onChange={(e) => setNoteInput(e.target.value)}
                  ></textarea>
                </div>

                <div className="d-flex gap-2 mt-4">
                  <button
                    type="button"
                    className="btn btn-light w-50"
                    onClick={() => setTab("list")}
                  >
                    {t("backToList")}
                  </button>
                  <button
                    type="button"
                    className="btn btn-warning w-50 fw-bold text-dark d-flex align-items-center justify-content-center gap-1"
                    onClick={() => onParkCurrentCart(refInput, noteInput)}
                  >
                    <i className="ri-pause-circle-fill"></i>
                    <span>{t("confirmHoldAndClearCart")}</span>
                  </button>
                </div>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
