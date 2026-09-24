import React, { useMemo, useState } from "react";
import { Customer, CustomerPriceList } from "@/types/pos-types";
import { formatCurrency, useCurrency } from "@/lib/currency";
import { useTranslation } from "@/lib/i18n/i18n";

interface ModalEditCustomerProps {
  customer: Customer;
  customers: Customer[];
  priceLists: CustomerPriceList[];
  isLoading: boolean;
  error: string | null;
  onSaveCustomer: (customer: Customer) => void;
  onClearCustomer: () => void;
  onClose: () => void;
}

export function ModalEditCustomer({
  customer,
  customers,
  priceLists,
  isLoading,
  error,
  onSaveCustomer,
  onClearCustomer,
  onClose,
}: ModalEditCustomerProps) {
  const currency = useCurrency();
  const { t } = useTranslation();
  const defaultPriceList = priceLists.find((priceList) => priceList.isDefault) || null;
  const [customerId, setCustomerId] = useState(customer.id);
  const [priceListId, setPriceListId] = useState<string | null>(customer.priceListId);
  const resolvedPriceListId = priceListId ?? (
    customer.id ? defaultPriceList?.id || "" : ""
  );

  const selectedCustomer = useMemo(
    () => customers.find((option) => option.id === customerId) || null,
    [customerId, customers]
  );
  const selectedPriceList = useMemo(
    () => priceLists.find((option) => option.id === resolvedPriceListId) || null,
    [resolvedPriceListId, priceLists]
  );

  const handleCustomerChange = (nextCustomerId: string) => {
    const nextCustomer = customers.find((option) => option.id === nextCustomerId) || null;
    setCustomerId(nextCustomerId);
    setPriceListId(nextCustomer?.priceListId || null);
  };

  const handleSave = () => {
    if (!selectedCustomer) return;

    onSaveCustomer({
      ...selectedCustomer,
      priceListId: selectedPriceList?.id || null,
      priceList: selectedPriceList,
    });
  };

  return (
    <div className="modal fade show d-block" tabIndex={-1} style={{ zIndex: 1060 }}>
      <div className="modal-dialog modal-dialog-centered">
        <div className="modal-content shadow-lg border-0 rounded-4">
          <div className="modal-header border-bottom bg-light px-4 py-3">
            <h6 className="modal-title fw-bold mb-0">{t("selectCustomer")}</h6>
            <button type="button" className="btn-close" onClick={onClose}></button>
          </div>
          <div className="modal-body p-4">
            {error && <div className="alert alert-danger py-2 fs-12">{error}</div>}

            <div className="mb-3">
              <label className="form-label fs-12 fw-medium text-muted">{t("customer")}</label>
              <select
                className="form-select"
                value={customerId}
                onChange={(event) => handleCustomerChange(event.target.value)}
                disabled={isLoading}
              >
                <option value="">{isLoading ? t("loadingCustomers") : t("selectACustomer")}</option>
                {customers.map((option) => (
                  <option key={option.id} value={option.id}>
                    {option.code} - {option.name}
                  </option>
                ))}
              </select>
            </div>

            {selectedCustomer && (
              <div className="bg-light border rounded-3 p-3 mb-3">
                <div className="fw-semibold fs-13">{selectedCustomer.name}</div>
                <div className="text-muted fs-11 mt-1">
                  {[selectedCustomer.phone, selectedCustomer.email].filter(Boolean).join(" | ") || t("noContactDetails")}
                </div>
              </div>
            )}

            <div className="mb-3">
              <label className="form-label fs-12 fw-medium text-muted">{t("priceList")}</label>
              <select
                className="form-select"
                value={resolvedPriceListId}
                onChange={(event) => setPriceListId(event.target.value)}
                disabled={!selectedCustomer || isLoading}
              >
                <option value="">{t("noPriceList")}</option>
                {priceLists.map((priceList) => (
                  <option key={priceList.id} value={priceList.id}>
                    {priceList.name}
                    {priceList.pricingMethod === "fixed" && priceList.fixedAmount !== null
                      ? ` - ${formatCurrency(priceList.fixedAmount, currency)} ${t("off")}`
                      : priceList.discountPercent > 0
                        ? ` - ${priceList.discountPercent}% ${t("off")}`
                        : ""}
                  </option>
                ))}
              </select>
              {selectedPriceList && (
                <div className="form-text">
                  {selectedPriceList.pricingMethod === "fixed" && selectedPriceList.fixedAmount !== null
                    ? `${formatCurrency(selectedPriceList.fixedAmount, currency)} ${t("discountWillBeApplied")}`
                    : selectedPriceList.discountPercent > 0
                      ? `${selectedPriceList.discountPercent}% ${t("discountWillBeApplied")}`
                      : t("noPriceListDiscount")}
                </div>
              )}
            </div>

            <div className="d-flex gap-2 mt-4">
              {customer.id ? (
                <button type="button" className="btn btn-light" onClick={onClearCustomer}>
                  {t("clear")}
                </button>
              ) : null}
              <button type="button" className="btn btn-light flex-fill" onClick={onClose}>
                {t("cancel")}
              </button>
              <button
                type="button"
                className="btn btn-primary flex-fill fw-semibold"
                onClick={handleSave}
                disabled={!selectedCustomer || isLoading}
              >
                {t("applyCustomer")}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
