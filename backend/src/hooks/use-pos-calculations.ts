import { useMemo } from "react";
import { CartItem } from "@/types/pos-types";

export function usePosCalculations(
  cart: CartItem[],
  discountPercent: number,
  taxPercent: number,
  serviceFee: number
) {
  const totalItemCount = useMemo(() => {
    return cart.reduce((sum, item) => sum + item.quantity, 0);
  }, [cart]);

  const subTotal = useMemo(() => {
    return cart.reduce((sum, item) => sum + item.unitPrice * item.quantity, 0);
  }, [cart]);

  const discountAmount = useMemo(() => {
    return subTotal > 0 ? (subTotal * (discountPercent || 0)) / 100 : 0;
  }, [discountPercent, subTotal]);

  const gstAmount = useMemo(() => {
    return subTotal > 0 ? (subTotal - discountAmount) * ((taxPercent || 0) / 100) : 0;
  }, [subTotal, discountAmount, taxPercent]);

  const totalPayable = useMemo(() => {
    return Math.max(0, subTotal - discountAmount + gstAmount + (serviceFee || 0));
  }, [subTotal, discountAmount, gstAmount, serviceFee]);

  return {
    totalItemCount,
    subTotal,
    discountAmount,
    gstAmount,
    totalPayable,
  };
}
