import { useState } from "react";
import { Product, CartItem, ProductUOM, VariantValue } from "@/types/pos-types";

export function usePosCart() {
  const [cart, setCart] = useState<CartItem[]>([]);

  // Direct 1-tap add for general products
  const addDirectToCart = (product: Product) => {
    const isStockControlled = product.stockControl !== false;
    const maxStock = isStockControlled && typeof product.stock === "number" ? Math.max(0, product.stock) : null;
    if (isStockControlled && maxStock !== null && maxStock <= 0) return;

    setCart((prev) => {
      const existing = prev.find(
        (item) => item.product.id === product.id && !item.selectedVariants && !item.selectedUOM
      );
      if (existing) {
        if (isStockControlled && maxStock !== null && existing.quantity >= maxStock) return prev;
        return prev.map((item) =>
          item.id === existing.id
            ? { ...item, quantity: isStockControlled && maxStock !== null ? Math.min(item.quantity + 1, maxStock) : item.quantity + 1 }
            : item
        );
      }
      return [
        {
          id: product.id,
          product,
          quantity: 1,
          unitPrice: product.price,
        },
        ...prev,
      ];
    });
  };

  // Add customized item with UOM or Variant selections
  const addConfiguredToCart = (
    product: Product,
    quantity: number,
    selectedUOM: ProductUOM | null,
    selectedVariants: Record<string, VariantValue>
  ) => {
    const isStockControlled = product.stockControl !== false;
    let lineMax: number | null = null;
    if (isStockControlled && typeof product.stock === "number") {
      const factor = Number(selectedUOM?.conversionFactorToBase ?? 1) || 1;
      lineMax = Math.max(0, Math.floor(product.stock / factor));
    }
    if (isStockControlled && lineMax !== null && lineMax <= 0) return;

    let basePrice = selectedUOM ? selectedUOM.price : product.price;
    const keyParts: string[] = [product.id];

    if (selectedUOM) {
      keyParts.push(`UOM:${selectedUOM.shortCode}`);
    }

    if (Object.keys(selectedVariants).length > 0) {
      Object.entries(selectedVariants).forEach(([key, val]) => {
        if (val.priceDelta) basePrice += val.priceDelta;
        keyParts.push(`${key}:${val.label}`);
      });
    }

    const cartItemId = keyParts.join("-");

    setCart((prev) => {
      const existing = prev.find((item) => item.id === cartItemId);
      if (existing) {
        return prev.map((item) =>
          item.id === cartItemId
            ? { ...item, quantity: lineMax !== null ? Math.min(item.quantity + quantity, lineMax) : item.quantity + quantity }
            : item
        );
      }
      const initialQty = lineMax !== null ? Math.min(quantity, lineMax) : quantity;
      if (initialQty <= 0) return prev;
      return [
        {
          id: cartItemId,
          product,
          quantity: initialQty,
          selectedUOM: selectedUOM ? { ...selectedUOM } : undefined,
          selectedVariants: Object.keys(selectedVariants).length > 0 ? { ...selectedVariants } : undefined,
          unitPrice: basePrice,
        },
        ...prev,
      ];
    });
  };

  // Update item quantity
  const updateQuantity = (itemId: string, delta: number) => {
    setCart((prev) =>
      prev
        .map((item) => {
          if (item.id === itemId) {
            const isStockControlled = item.product.stockControl !== false;
            let maxQuantity: number | null = null;
            if (isStockControlled) {
              if (item.selectedVariant) {
                maxQuantity = Math.max(0, Number(item.selectedVariant.stock ?? 0));
              } else if (typeof item.product.stock === "number") {
                const factor = Number(item.selectedUOM?.conversionFactorToBase ?? 1) || 1;
                maxQuantity = Math.max(0, Math.floor(item.product.stock / factor));
              }
            }

            const newQty = delta > 0 && maxQuantity !== null
              ? Math.min(item.quantity + delta, maxQuantity)
              : item.quantity + delta;

            return newQty > 0 ? { ...item, quantity: newQty } : null;
          }
          return item;
        })
        .filter(Boolean) as CartItem[]
    );
  };

  // Remove single line item
  const removeItem = (itemId: string) => {
    setCart((prev) => prev.filter((item) => item.id !== itemId));
  };

  // Clear entire cart
  const clearCart = () => {
    setCart([]);
  };

  return {
    cart,
    setCart,
    addDirectToCart,
    addConfiguredToCart,
    updateQuantity,
    removeItem,
    clearCart,
  };
}
