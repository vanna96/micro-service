import React, { useEffect, useMemo, useState } from "react";
import { Customer, CustomerPriceList } from "@/types/pos-types";

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
  const defaultPriceList = priceLists.find((priceList) => priceList.isDefault) || null;
  const [customerId, setCustomerId] = useState(customer.id);
  const [priceListId, setPriceListId] = useState(
    customer.priceListId || (customer.id ? defaultPriceList?.id || "" : "")
  );

  useEffect(() => {
    if (customer.id && !priceListId) {
      setPriceListId(customer.priceListId || defaultPriceList?.id || "");
    }
  }, [customer.id, customer.priceListId, defaultPriceList?.id, priceListId]);

  const selectedCustomer = useMemo(
    () => customers.find((option) => option.id === customerId) || null,
    [customerId, customers]
  );
  const selectedPriceList = useMemo(
    () => priceLists.find((option) => option.id === priceListId) || null,
    [priceListId, priceLists]
  );

  const handleCustomerChange = (nextCustomerId: string) => {
    const nextCustomer = customers.find((option) => option.id === nextCustomerId) || null;
    setCustomerId(nextCustomerId);
    setPriceListId(nextCustomer?.priceListId || defaultPriceList?.id || "");
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
            <h6 className="modal-title fw-bold mb-0">Select Customer</h6>
            <button type="button" className="btn-close" onClick={onClose}></button>
          </div>
          <div className="modal-body p-4">
            {error && <div className="alert alert-danger py-2 fs-12">{error}</div>}

            <div className="mb-3">
              <label className="form-label fs-12 fw-medium text-muted">Customer</label>
              <select
                className="form-select"
                value={customerId}
                onChange={(event) => handleCustomerChange(event.target.value)}
                disabled={isLoading}
              >
                <option value="">{isLoading ? "Loading customers..." : "Select a customer"}</option>
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
                  {[selectedCustomer.phone, selectedCustomer.email].filter(Boolean).join(" | ") || "No contact details"}
                </div>
              </div>
            )}

            <div className="mb-3">
              <label className="form-label fs-12 fw-medium text-muted">Price List</label>
              <select
                className="form-select"
                value={priceListId}
                onChange={(event) => setPriceListId(event.target.value)}
                disabled={!selectedCustomer || isLoading}
              >
                <option value="">No price list</option>
                {priceLists.map((priceList) => (
                  <option key={priceList.id} value={priceList.id}>
                    {priceList.name}
                    {priceList.discountPercent > 0 ? ` - ${priceList.discountPercent}% off` : ""}
                  </option>
                ))}
              </select>
              {selectedPriceList && (
                <div className="form-text">
                  {selectedPriceList.discountPercent > 0
                    ? `${selectedPriceList.discountPercent}% discount will be applied to this sale.`
                    : "This price list has no header discount."}
                </div>
              )}
            </div>

            <div className="d-flex gap-2 mt-4">
              {customer.id ? (
                <button type="button" className="btn btn-light" onClick={onClearCustomer}>
                  Clear
                </button>
              ) : null}
              <button type="button" className="btn btn-light flex-fill" onClick={onClose}>
                Cancel
              </button>
              <button
                type="button"
                className="btn btn-primary flex-fill fw-semibold"
                onClick={handleSave}
                disabled={!selectedCustomer || isLoading}
              >
                Apply Customer
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
