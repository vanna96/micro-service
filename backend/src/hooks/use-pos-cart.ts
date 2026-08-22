import { useState } from "react";
import { Product, CartItem, ProductUOM, VariantValue } from "@/types/pos-types";
import { INITIAL_CART } from "@/data/pos-data";

export function usePosCart() {
  const [cart, setCart] = useState<CartItem[]>(INITIAL_CART);

  // Direct 1-tap add for general products
  const addDirectToCart = (product: Product) => {
    setCart((prev) => {
      const existing = prev.find(
        (item) => item.product.id === product.id && !item.selectedVariants && !item.selectedUOM
      );
      if (existing) {
        return prev.map((item) =>
          item.id === existing.id ? { ...item, quantity: item.quantity + 1 } : item
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
          item.id === cartItemId ? { ...item, quantity: item.quantity + quantity } : item
        );
      }
      return [
        {
          id: cartItemId,
          product,
          quantity,
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
            const newQty = item.quantity + delta;
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
