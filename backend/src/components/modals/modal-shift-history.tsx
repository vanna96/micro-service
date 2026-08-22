import React, { useState, useMemo } from "react";
import { Invoice } from "@/types/pos-types";

interface ModalShiftHistoryProps {
  invoices: Invoice[];
  onViewInvoice: () => void;
  onClose: () => void;
}

export function ModalShiftHistory({
  invoices,
  onViewInvoice,
  onClose,
}: ModalShiftHistoryProps) {
  const [search, setSearch] = useState<string>("");
  const [filter, setFilter] = useState<string>("all");

  const filteredInvoices = useMemo(() => {
    return invoices.filter((inv) => {
      const matchSearch =
        search === "" ||
        inv.id.toLowerCase().includes(search.toLowerCase()) ||
        inv.customer.toLowerCase().includes(search.toLowerCase());
      const matchFilter =
        filter === "all" || inv.paymentMethod.toLowerCase() === filter.toLowerCase();
      return matchSearch && matchFilter;
    });
  }, [invoices, search, filter]);

  return (
    <div className="modal fade show d-block" tabIndex={-1} style={{ zIndex: 1060 }}>
      <div className="modal-dialog modal-dialog-centered modal-xl">
        <div className="modal-content shadow-lg border-0 rounded-4">
          <div className="modal-header border-bottom bg-light px-4 py-3">
            <h6 className="modal-title fw-bold mb-0">Shift Sales & Invoices</h6>
            <button type="button" className="btn-close" onClick={onClose}></button>
          </div>
          <div className="modal-body p-4">
            <div className="d-flex gap-2 mb-3">
              <input
                type="text"
                className="form-control form-control-sm"
                placeholder="Search invoices by ID or customer..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
              />
              <select
                className="form-select form-select-sm"
                style={{ width: "160px" }}
                value={filter}
                onChange={(e) => setFilter(e.target.value)}
              >
                <option value="all">All Methods</option>
                <option value="cash">Cash</option>
                <option value="card">Card</option>
                <option value="upi">UPI</option>
              </select>
            </div>
            <div className="table-responsive border rounded-3">
              <table className="table table-hover align-middle mb-0 fs-13">
                <thead className="table-light">
                  <tr>
                    <th>Invoice</th>
                    <th>Customer</th>
                    <th>Method</th>
                    <th>Time</th>
                    <th>Amount</th>
                    <th className="text-end">Action</th>
                  </tr>
                </thead>
                <tbody>
                  {filteredInvoices.map((inv) => (
                    <tr key={inv.id}>
                      <td>
                        <span className="fw-bold text-primary font-monospace">
                          {inv.id}
                        </span>
                      </td>
                      <td>{inv.customer}</td>
                      <td>
                        <span className="badge bg-light border text-dark">
                          {inv.paymentMethod}
                        </span>
                      </td>
                      <td>
                        {inv.date} {inv.time}
                      </td>
                      <td className="fw-bold font-monospace">${inv.amount}</td>
                      <td className="text-end">
                        <button
                          type="button"
                          className="btn btn-xs btn-outline-primary"
                          onClick={onViewInvoice}
                        >
                          View
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
