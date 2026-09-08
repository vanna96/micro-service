import React from "react";
import { CartItem } from "@/types/pos-types";

interface ModalInvoicePreviewProps {
  cart: CartItem[];
  totalPayable: number;
  onClose: () => void;
}

export function ModalInvoicePreview({
  cart,
  totalPayable,
  onClose,
}: ModalInvoicePreviewProps) {
  return (
    <div className="modal fade show d-block" tabIndex={-1} style={{ zIndex: 1060 }}>
      <div className="modal-dialog modal-dialog-centered modal-lg">
        <div className="modal-content shadow-lg border-0 rounded-4">
          <div className="modal-header border-bottom bg-light px-4 py-3">
            <h6 className="modal-title fw-bold mb-0">Invoice Preview</h6>
            <button type="button" className="btn-close" onClick={onClose}></button>
          </div>
          <div className="modal-body p-4">
            <div className="border rounded-3 p-4 bg-white">
              <div className="d-flex justify-content-between mb-4">
                <h5 className="fw-bolder mb-1">Current Sale</h5>
                <img src="/assets/main-logo-CWEU2RA-.png" alt="Logo" height="22" />
              </div>

              <div className="table-responsive mb-3">
                <table className="table table-sm table-bordered fs-12">
                  <thead className="table-light">
                    <tr>
                      <th>Item</th>
                      <th>UOM / Option</th>
                      <th className="text-center">Qty</th>
                      <th className="text-end">Price</th>
                      <th className="text-end">Amount</th>
                    </tr>
                  </thead>
                  <tbody>
                    {cart.map((item) => (
                      <tr key={item.id}>
                        <td>{item.product.name}</td>
                        <td>
                          {item.selectedUOM
                            ? item.selectedUOM.name
                            : item.selectedVariants
                            ? Object.values(item.selectedVariants)
                                .map((v) => v.label)
                                .join(", ")
                            : "Standard"}
                        </td>
                        <td className="text-center">{item.quantity}</td>
                        <td className="text-end">${item.unitPrice.toFixed(2)}</td>
                        <td className="text-end">
                          ${(item.unitPrice * item.quantity).toFixed(2)}
                        </td>
                      </tr>
                    ))}
                    <tr>
                      <td colSpan={4} className="text-end fw-bold">
                        Grand Total
                      </td>
                      <td className="text-end fw-bold text-primary">
                        ${totalPayable.toFixed(2)}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <div className="d-flex justify-content-end gap-2 mt-3">
              <button
                type="button"
                className="btn btn-outline-secondary"
                onClick={() => window.print()}
              >
                <i className="ri-printer-line me-1"></i> Print PDF
              </button>
              <button type="button" className="btn btn-primary" onClick={onClose}>
                Close
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
