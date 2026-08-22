import React from "react";

interface ModalConfirmClearProps {
  onConfirm: () => void;
  onClose: () => void;
}

export function ModalConfirmClear({ onConfirm, onClose }: ModalConfirmClearProps) {
  return (
    <div className="modal fade show d-block" tabIndex={-1} style={{ zIndex: 1060 }}>
      <div className="modal-dialog modal-dialog-centered" style={{ maxWidth: "340px" }}>
        <div className="modal-content shadow-lg border-0 rounded-4 text-center p-4">
          <div
            className="size-14 rounded-circle bg-danger-subtle text-danger mx-auto d-flex align-items-center justify-content-center mb-3"
            style={{ width: "56px", height: "56px" }}
          >
            <i className="ri-delete-bin-line fs-28"></i>
          </div>
          <h6 className="fw-bolder mb-1">Clear Current Cart?</h6>
          <p className="text-muted fs-12 mb-4">
            All items in order #GOT-1698 will be removed.
          </p>
          <div className="d-flex gap-2">
            <button type="button" className="btn btn-light flex-fill" onClick={onClose}>
              Cancel
            </button>
            <button
              type="button"
              className="btn btn-danger flex-fill fw-semibold"
              onClick={onConfirm}
            >
              Yes, Clear
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
