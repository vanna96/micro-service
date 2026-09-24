import React, { useRef, useState } from "react";
import { AppliedPromotion, CartItem, Customer, DiscountType, PosCompanyInfo } from "@/types/pos-types";
import { formatCurrency, useCurrency } from "@/lib/currency";
import { useTranslation } from "@/lib/i18n/i18n";

interface ModalInvoicePreviewProps {
  invoiceNumber: string;
  currentDate: string;
  currentTime: string;
  cart: CartItem[];
  currencyRates: Record<string, number>;
  company: PosCompanyInfo | null;
  customer: Customer;
  orderType: string;
  paymentMethod: string;
  discountType: DiscountType;
  discountValue: number;
  subTotal: number;
  discountAmount: number;
  appliedPromotion: AppliedPromotion | null;
  promotionDiscountAmount: number;
  taxPercent: number;
  taxAmount: number;
  serviceFee: number;
  totalPayable: number;
  totalItemCount: number;
  onClose: () => void;
}

export function ModalInvoicePreview({
  invoiceNumber,
  currentDate,
  currentTime,
  cart,
  currencyRates,
  company,
  customer,
  orderType,
  paymentMethod,
  discountType,
  discountValue,
  subTotal,
  discountAmount,
  appliedPromotion,
  promotionDiscountAmount,
  taxPercent,
  taxAmount,
  serviceFee,
  totalPayable,
  totalItemCount,
  onClose,
}: ModalInvoicePreviewProps) {
  const invoiceRef = useRef<HTMLElement>(null);
  const [invoiceFormat, setInvoiceFormat] = useState<"a4" | "thermal">("a4");
  const [frozenDate] = useState(currentDate);
  const [frozenTime] = useState(currentTime);
  const currency = useCurrency();
  const { t } = useTranslation();
  const companyContacts = company?.phone || "";
  const resolvedPromotionDiscount = appliedPromotion
    ? Math.min(discountAmount, Math.max(0, promotionDiscountAmount))
    : 0;
  const manualDiscountAmount = Math.max(0, discountAmount - resolvedPromotionDiscount);
  const foreignCurrencyCodes = Array.from(new Set(
    cart
      .map((item) => item.product.currency?.code)
      .filter((code): code is string => Boolean(code && currency && code !== currency.code))
  ));
  const exchangeRateRemark = currency
    ? foreignCurrencyCodes
        .map((code) => {
          const rate = Number(currencyRates[code]);
          if (!(rate > 0)) return null;

          return `1 ${currency.code} = ${rate.toLocaleString(undefined, {
            maximumFractionDigits: 8,
          })} ${code}`;
        })
        .filter(Boolean)
        .join(" · ")
    : "";
  const printInvoice = () => {
    if (!invoiceRef.current) return;

    const previousTitle = document.title;
    const printPageStyle = document.createElement("style");
    const printRoot = document.createElement("div");
    printRoot.className = `invoice-print-root invoice-print-root--${invoiceFormat}`;
    printRoot.appendChild(invoiceRef.current.cloneNode(true));
    if (invoiceFormat === "thermal") {
      const pageHeight = Math.max(
        100,
        Math.ceil((invoiceRef.current.scrollHeight * 25.4) / 96) + 8
      );
      printPageStyle.textContent = `@media print { @page { size: 80mm ${pageHeight}mm; margin: 4mm; } }`;
    } else {
      printPageStyle.textContent = "@media print { @page { size: A4 portrait; margin: 12mm; } }";
    }
    document.head.appendChild(printPageStyle);
    document.body.appendChild(printRoot);
    document.body.classList.add("invoice-print-active", `invoice-print-${invoiceFormat}`);

    let cleanedUp = false;
    let fallbackTimer: number | undefined;
    const cleanup = () => {
      if (cleanedUp) return;
      cleanedUp = true;
      if (fallbackTimer) window.clearTimeout(fallbackTimer);
      document.body.classList.remove("invoice-print-active", `invoice-print-${invoiceFormat}`);
      printPageStyle.remove();
      printRoot.remove();
      document.title = previousTitle;
    };

    document.title = `${invoiceFormat === "thermal" ? "Receipt" : "Invoice"}-${invoiceNumber}`;
    window.addEventListener("afterprint", cleanup, { once: true });

    try {
      window.print();
      if (!cleanedUp) fallbackTimer = window.setTimeout(cleanup, 120000);
    } catch (error) {
      cleanup();
      throw error;
    }
  };

  return (
    <div className="modal fade show d-block invoice-preview-modal" tabIndex={-1} style={{ zIndex: 1060 }}>
      <div className="modal-dialog modal-dialog-centered modal-lg">
        <div className="modal-content shadow-lg border-0 rounded-4">
          <div className="modal-header border-bottom bg-light px-4 py-3 invoice-preview-modal-header">
            <h6 className="modal-title fw-bold mb-0">{t("invoicePreview")}</h6>
            <button type="button" className="btn-close" onClick={onClose} aria-label="Close"></button>
          </div>

          <div className="modal-body p-4">
            <div className="invoice-format-switch" role="group" aria-label="Invoice format">
              <button
                type="button"
                className={invoiceFormat === "a4" ? "active" : ""}
                onClick={() => setInvoiceFormat("a4")}
              >
                <i className="ri-file-pdf-2-line" aria-hidden="true"></i>
                {t("a4Pdf")}
              </button>
              <button
                type="button"
                className={invoiceFormat === "thermal" ? "active" : ""}
                onClick={() => setInvoiceFormat("thermal")}
              >
                <i className="ri-receipt-line" aria-hidden="true"></i>
                {t("thermal80mm")}
              </button>
            </div>

            {invoiceFormat === "a4" ? (
            <article ref={invoiceRef} className="invoice-preview-sheet border rounded-3 bg-white">
              <header className="invoice-preview-header">
                <div className="invoice-preview-company">
                  <div className="invoice-preview-company-mark" aria-hidden="true">
                    <i className="ri-store-2-line"></i>
                  </div>
                  <div>
                    <h4 className="mb-1 fw-bold">{company?.name || "Company"}</h4>
                    {company?.address && <div className="text-muted fs-12 invoice-preview-address">{company.address}</div>}
                    {companyContacts && <div className="text-muted fs-12 mt-1">{companyContacts}</div>}
                  </div>
                </div>

                <div className="invoice-preview-title text-end">
                  <div className="text-uppercase fw-bolder">{t("invoiceHeading")}</div>
                  <div className="invoice-preview-header-meta">
                    <div className="invoice-preview-header-meta-item invoice-preview-number-row">
                      <span><span>#</span><span>:</span></span>
                      <strong>{invoiceNumber}</strong>
                    </div>
                    <div className="invoice-preview-header-meta-item invoice-preview-time-row">
                      <span><span>{t("time")}</span><span>:</span></span>
                      <strong>{[frozenDate, frozenTime].filter(Boolean).join(" · ")}</strong>
                    </div>
                    <div className="invoice-preview-header-meta-item">
                      <span><span>{t("billTo")}</span><span>:</span></span>
                      <strong>{customer.name || t("walkInCustomer")}</strong>
                    </div>
                    <div className="invoice-preview-header-meta-item">
                      <span><span>{t("order")}</span><span>:</span></span>
                      <strong>{orderType}</strong>
                    </div>
                    <div className="invoice-preview-header-meta-item">
                      <span><span>{t("payment")}</span><span>:</span></span>
                      <strong>{paymentMethod || t("notYet")}</strong>
                    </div>
                  </div>
                </div>
              </header>

              <div className="table-responsive invoice-preview-table-wrap">
                <table className="table table-sm align-middle mb-0 fs-12 invoice-preview-table">
                  <thead>
                    <tr>
                      <th>{t("productItem")}</th>
                      <th>{t("uomOption")}</th>
                      <th className="text-center">{t("qty")}</th>
                      <th className="text-end">{t("price")}</th>
                      <th className="text-end">{t("amount")}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {cart.map((item) => {
                      const itemCurrency = item.product.currency || currency;

                      return (
                        <tr key={item.id}>
                          <td>
                            <strong className="d-block">{item.product.name}</strong>
                            {item.product.sku && <small className="text-muted">{item.product.sku}</small>}
                          </td>
                          <td>
                            {item.selectedUOM
                              ? item.selectedUOM.name
                              : item.selectedVariants
                                ? Object.values(item.selectedVariants).map((value) => value.label).join(", ")
                                : t("standard")}
                          </td>
                          <td className="text-center">{item.quantity}</td>
                          <td className="text-end text-nowrap">{formatCurrency(item.unitPrice, itemCurrency)}</td>
                          <td className="text-end text-nowrap fw-semibold">
                            {formatCurrency(item.unitPrice * item.quantity, itemCurrency)}
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>

              <section className="invoice-preview-summary">
                <div><span>{t("subtotal")}</span><strong>{formatCurrency(subTotal, currency)}</strong></div>
                {(manualDiscountAmount > 0 || !appliedPromotion) && <div className={manualDiscountAmount > 0 ? "invoice-preview-discount" : ""}>
                  <span>
                    {t("discount")}
                    {discountType === "percentage" && ` (${discountValue}%)`}
                    {discountType === "fixed" && " (Fixed)"}
                  </span>
                  <strong>{manualDiscountAmount > 0 ? "-" : ""}{formatCurrency(manualDiscountAmount, currency)}</strong>
                </div>}
                {appliedPromotion && <div className="invoice-preview-discount">
                  <span>{t("promotion")} · {appliedPromotion.name}</span>
                  <strong>-{formatCurrency(resolvedPromotionDiscount, currency)}</strong>
                </div>}
                <div>
                  <span>{t("gstTax")} ({taxPercent}%)</span>
                  <strong>{formatCurrency(taxAmount, currency)}</strong>
                </div>
                <div>
                  <span>{t("serviceFee")}</span>
                  <strong>{formatCurrency(serviceFee, currency)}</strong>
                </div>
                <div className="invoice-preview-total">
                  <span className="d-flex flex-column align-items-start gap-1">
                    <span>{t("grandTotal")} {currency?.code && <small>({currency.code})</small>}</span>
                    <small>{totalItemCount} {totalItemCount === 1 ? t("item") : t("items")} {t("itemsIncluded")}</small>
                  </span>
                  <strong>{formatCurrency(totalPayable, currency)}</strong>
                </div>
              </section>

              <footer className="invoice-preview-footer">
                {exchangeRateRemark && (
                  <div className="invoice-preview-rate-remark">
                    <i className="ri-information-line" aria-hidden="true"></i>
                    <span>{t("exchangeRateUsed")} {exchangeRateRemark}</span>
                  </div>
                )}
                <div className="invoice-preview-footer-message">
                  <i className="ri-heart-3-line" aria-hidden="true"></i>
                  <span>{company?.receiptFooter || t("thankYouBusiness")}</span>
                </div>
              </footer>
            </article>
            ) : (
              <article ref={invoiceRef} className="thermal-receipt">
                <header className="thermal-receipt-header">
                  <h3>{company?.name || "Company"}</h3>
                  {company?.address && <div>{company.address}</div>}
                  {companyContacts && <div>{t("tel")} {companyContacts}</div>}
                  <h4>{t("invoiceHeading")}</h4>
                  <div className="thermal-receipt-meta">
                    <div><span>{t("invoiceHeading")} #:</span><strong>{invoiceNumber}</strong></div>
                    <div><span>{t("date")}</span><strong>{frozenDate}</strong></div>
                    <div><span>{t("time")}:</span><strong>{frozenTime}</strong></div>
                    <div><span>{t("customer")}:</span><strong>{customer.name || t("walkInCustomer")}</strong></div>
                    {customer.code && <div><span>{t("customerId")}</span><strong>{customer.code}</strong></div>}
                    {customer.phone && <div><span>{t("phone")}</span><strong>{customer.phone}</strong></div>}
                    <div><span>{t("order")}:</span><strong>{orderType}</strong></div>
                    <div><span>{t("payment")}:</span><strong>{paymentMethod || t("notYet")}</strong></div>
                  </div>
                </header>

                <div className="thermal-receipt-rule"></div>

                <table className="thermal-receipt-table">
                  <thead>
                    <tr>
                      <th>{t("productItem")}</th>
                      <th className="text-center">{t("qty")}</th>
                      <th className="text-end">{t("amount")}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {cart.map((item) => {
                      const itemCurrency = item.product.currency || currency;
                      const itemDescription = item.selectedUOM
                        ? item.selectedUOM.name
                        : item.selectedVariants
                          ? Object.values(item.selectedVariants).map((value) => value.label).join(", ")
                          : t("standard");

                      return (
                        <tr key={item.id}>
                          <td>
                            <strong>{item.product.name}</strong>
                            <small>{[item.product.sku, itemDescription].filter(Boolean).join(" · ")}</small>
                          </td>
                          <td className="text-center">{item.quantity}</td>
                          <td className="text-end">{formatCurrency(item.unitPrice * item.quantity, itemCurrency)}</td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>

                <section className="thermal-receipt-summary">
                  <div><span>{t("subtotal")}</span><strong>{formatCurrency(subTotal, currency)}</strong></div>
                  {(manualDiscountAmount > 0 || !appliedPromotion) && <div className={manualDiscountAmount > 0 ? "thermal-discount" : ""}><span>{t("discount")}{discountType === "percentage" ? ` (${discountValue}%)` : ""}</span><strong>{manualDiscountAmount > 0 ? "-" : ""}{formatCurrency(manualDiscountAmount, currency)}</strong></div>}
                  {appliedPromotion && <div className="thermal-discount"><span>{t("promotion")} · {appliedPromotion.name}</span><strong>-{formatCurrency(resolvedPromotionDiscount, currency)}</strong></div>}
                  <div><span>{t("gstTax")} ({taxPercent}%)</span><strong>{formatCurrency(taxAmount, currency)}</strong></div>
                  <div><span>{t("serviceFee")}</span><strong>{formatCurrency(serviceFee, currency)}</strong></div>
                  <div className="thermal-receipt-total"><span>{t("grandTotal")}</span><strong>{formatCurrency(totalPayable, currency)}</strong></div>
                  <small>{totalItemCount} {totalItemCount === 1 ? t("item") : t("items")} {t("itemsIncluded")}</small>
                </section>

                <footer className="thermal-receipt-footer">
                  {exchangeRateRemark && <div>{t("exchangeRateUsed")} {exchangeRateRemark}</div>}
                  <strong>{company?.receiptFooter || t("thankYouBusiness")}</strong>
                </footer>
              </article>
            )}

            <div className="d-flex justify-content-end gap-2 mt-3 invoice-preview-actions">
              <button type="button" className="btn btn-outline-secondary" onClick={printInvoice}>
                <i className="ri-printer-line me-1"></i>
                {invoiceFormat === "a4" ? t("printA4") : t("printThermal")}
              </button>
              <button type="button" className="btn btn-primary" onClick={onClose}>{t("close")}</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
