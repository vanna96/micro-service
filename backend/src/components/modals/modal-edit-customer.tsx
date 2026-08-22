import React, { useState } from "react";
import { Customer } from "@/types/pos-types";

interface ModalEditCustomerProps {
  customer: Customer;
  onSaveCustomer: (c: Customer) => void;
  onClose: () => void;
}

export function ModalEditCustomer({
  customer,
  onSaveCustomer,
  onClose,
}: ModalEditCustomerProps) {
  const [form, setForm] = useState<Customer>({ ...customer });

  return (
    <div className="modal fade show d-block" tabIndex={-1} style={{ zIndex: 1060 }}>
      <div className="modal-dialog modal-dialog-centered">
        <div className="modal-content shadow-lg border-0 rounded-4">
          <div className="modal-header border-bottom bg-light px-4 py-3">
            <h6 className="modal-title fw-bold mb-0">Customer Profile & Loyalty</h6>
            <button type="button" className="btn-close" onClick={onClose}></button>
          </div>
          <div className="modal-body p-4">
            <form
              onSubmit={(e) => {
                e.preventDefault();
                onSaveCustomer(form);
              }}
            >
              <div className="mb-3">
                <label className="form-label fs-12 fw-medium text-muted">Customer Name</label>
                <input
                  type="text"
                  className="form-control"
                  value={form.name}
                  onChange={(e) => setForm({ ...form, name: e.target.value })}
                  required
                />
              </div>
              <div className="row g-2 mb-3">
                <div className="col-6">
                  <label className="form-label fs-12 fw-medium text-muted">
                    Membership Tier
                  </label>
                  <select
                    className="form-select"
                    value={form.tier}
                    onChange={(e) => setForm({ ...form, tier: e.target.value })}
                  >
                    <option value="Silver Member">Silver Member</option>
                    <option value="Gold Member">Gold Member</option>
                    <option value="Platinum Member">Platinum Member</option>
                    <option value="VIP Member">VIP Member</option>
                  </select>
                </div>
                <div className="col-6">
                  <label className="form-label fs-12 fw-medium text-muted">Points</label>
                  <input
                    type="number"
                    className="form-control"
                    value={form.points}
                    onChange={(e) =>
                      setForm({ ...form, points: parseInt(e.target.value) || 0 })
                    }
                  />
                </div>
              </div>
              <div className="d-flex gap-2 mt-4">
                <button type="button" className="btn btn-light w-50" onClick={onClose}>
                  Cancel
                </button>
                <button type="submit" className="btn btn-primary w-50 fw-semibold">
                  Save Customer
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  );
}
