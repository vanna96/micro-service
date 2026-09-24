import { useCallback, useMemo } from "react";
import { CartItem, CurrencyInfo, DiscountType } from "@/types/pos-types";

export function usePosCalculations(
  cart: CartItem[],
  discountType: DiscountType,
  discountValue: number,
  taxPercent: number,
  serviceFee: number,
  baseCurrency: CurrencyInfo | null,
  currencyRates: Record<string, number>
) {
  const convertToBase = useCallback((item: CartItem) => {
    if (item.promotionReward) return 0;
    const itemCurrency = item.product.currency?.code;
    const baseCode = baseCurrency?.code;
    if (!itemCurrency || !baseCode || itemCurrency === baseCode) return item.unitPrice;

    const rate = Number(currencyRates[itemCurrency]);
    return rate > 0 ? item.unitPrice / rate : 0;
  }, [baseCurrency, currencyRates]);

  const totalItemCount = useMemo(() => {
    return cart.reduce((sum, item) => sum + item.quantity, 0);
  }, [cart]);

  const subTotal = useMemo(() => {
    return cart.reduce((sum, item) => sum + convertToBase(item) * item.quantity, 0);
  }, [cart, convertToBase]);

  const discountAmount = useMemo(() => {
    if (subTotal <= 0) return 0;

    const safeValue = Math.max(0, discountValue || 0);
    const amount = discountType === "percentage"
      ? (subTotal * Math.min(100, safeValue)) / 100
      : safeValue;

    return Math.min(subTotal, amount);
  }, [discountType, discountValue, subTotal]);

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
