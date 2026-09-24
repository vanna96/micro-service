import { createSlice, PayloadAction } from '@reduxjs/toolkit';
import { Product, CartItem, ProductUOM, ProductVariant, VariantValue } from '@/types/pos-types';

interface CartState {
  items: CartItem[];
}

const initialState: CartState = {
  items: [],
};

export function getCartItemMaxQuantity(item: CartItem, allItems: CartItem[] = []): number | null {
  if (item.product.stockControl === false) return null;

  if (item.selectedVariant) {
    const variantStock = Math.max(0, Number(item.selectedVariant.stock ?? 0));
    const otherVariantQty = allItems
      .filter((i) => i.id !== item.id && i.selectedVariant?.id === item.selectedVariant?.id)
      .reduce((sum, i) => sum + i.quantity, 0);
    const remainingVariant = Math.max(0, variantStock - otherVariantQty);

    if (typeof item.product.stock === "number") {
      const parentStock = Math.max(0, item.product.stock);
      const otherBaseQty = allItems
        .filter((i) => i.id !== item.id && i.product.id === item.product.id)
        .reduce((sum, i) => sum + i.quantity * (Number(i.selectedUOM?.conversionFactorToBase ?? 1) || 1), 0);
      const remainingParent = Math.max(0, parentStock - otherBaseQty);
      return Math.min(remainingVariant, remainingParent);
    }

    return remainingVariant;
  }

  if (typeof item.product.stock === "number") {
    const baseStock = Math.max(0, item.product.stock);
    const factor = Number(item.selectedUOM?.conversionFactorToBase ?? 1) || 1;
    const otherBaseQty = allItems
      .filter((i) => i.id !== item.id && i.product.id === item.product.id)
      .reduce((sum, i) => sum + i.quantity * (Number(i.selectedUOM?.conversionFactorToBase ?? 1) || 1), 0);
    const remainingBase = Math.max(0, baseStock - otherBaseQty);
    return Math.max(0, Math.floor(remainingBase / factor));
  }

  return null;
}

export const cartSlice = createSlice({
  name: 'cart',
  initialState,
  reducers: {
    addDirectToCart: (state, action: PayloadAction<Product>) => {
      const product = action.payload;
      const isStockControlled = product.stockControl !== false;
      const baseStock = isStockControlled && typeof product.stock === "number"
        ? Math.max(0, product.stock)
        : null;

      if (isStockControlled && baseStock !== null && baseStock <= 0) {
        return;
      }

      const existing = state.items.find(
        (item) => item.product.id === product.id && !item.selectedVariants && !item.selectedUOM && !item.promotionReward
      );

      const currentBaseUsed = state.items
        .filter((item) => item.product.id === product.id && item !== existing)
        .reduce((sum, item) => sum + item.quantity * (Number(item.selectedUOM?.conversionFactorToBase ?? 1) || 1), 0);

      const maxForLine = baseStock !== null ? Math.max(0, baseStock - currentBaseUsed) : null;

      if (existing) {
        if (isStockControlled && maxForLine !== null && existing.quantity >= maxForLine) {
          return;
        }
        existing.quantity = isStockControlled && maxForLine !== null
          ? Math.min(existing.quantity + 1, maxForLine)
          : existing.quantity + 1;
      } else {
        if (isStockControlled && maxForLine !== null && maxForLine <= 0) {
          return;
        }
        state.items.unshift({
          id: product.id,
          product,
          quantity: 1,
          unitPrice: product.price,
        });
      }
    },
    addPromotionReward: (
      state,
      action: PayloadAction<{
        product: Product;
        quantity: number;
        promotionId: number;
        promotionName: string;
        uomId: number | null;
      }>
    ) => {
      const { product, quantity, promotionId, promotionName, uomId } = action.payload;
      const selectedUOM = uomId
        ? product.uomList?.find((unit) => unit.id === uomId)
        : undefined;
      const cartItemId = `PROMO:${promotionId}:${product.id}:${uomId || 0}`;
      const existing = state.items.find((item) => item.id === cartItemId);

      if (existing) {
        existing.quantity += quantity;
        return;
      }

      state.items.unshift({
        id: cartItemId,
        product,
        quantity,
        selectedUOM,
        unitPrice: selectedUOM?.price ?? product.price,
        promotionReward: { promotionId, promotionName },
      });
    },
    addConfiguredToCart: (
      state,
      action: PayloadAction<{
        product: Product;
        quantity: number;
        selectedUOM: ProductUOM | null;
        selectedVariants: Record<string, VariantValue>;
        selectedVariant: ProductVariant | null;
      }>
    ) => {
      const { product, quantity, selectedUOM, selectedVariants, selectedVariant } = action.payload;
      const isStockControlled = product.stockControl !== false;
      const factor = Number(selectedUOM?.conversionFactorToBase ?? 1) || 1;
      const baseStock = typeof product.stock === "number" ? Math.max(0, product.stock) : null;

      const keyParts = [product.id];
      if (selectedUOM) keyParts.push(`UOM:${selectedUOM.shortCode}`);
      if (selectedVariant) keyParts.push(`VARIANT:${selectedVariant.id}`);
      if (Object.keys(selectedVariants).length > 0) {
        Object.entries(selectedVariants).forEach(([key, val]) => {
          keyParts.push(`${key}:${val.label}`);
        });
      }
      const cartItemId = keyParts.join('-');

      let lineMax: number | null = null;
      if (isStockControlled) {
        if (selectedVariant) {
          const otherVariantQty = state.items
            .filter((i) => i.id !== cartItemId && i.selectedVariant?.id === selectedVariant.id)
            .reduce((sum, i) => sum + i.quantity, 0);
          const remVariant = Math.max(0, selectedVariant.stock - otherVariantQty);
          if (baseStock !== null) {
            const otherBaseInCart = state.items
              .filter((i) => i.id !== cartItemId && i.product.id === product.id)
              .reduce((sum, i) => sum + i.quantity * (Number(i.selectedUOM?.conversionFactorToBase ?? 1) || 1), 0);
            lineMax = Math.min(remVariant, Math.max(0, baseStock - otherBaseInCart));
          } else {
            lineMax = remVariant;
          }
        } else if (baseStock !== null) {
          const otherBaseInCart = state.items
            .filter((i) => i.id !== cartItemId && i.product.id === product.id)
            .reduce((sum, i) => sum + i.quantity * (Number(i.selectedUOM?.conversionFactorToBase ?? 1) || 1), 0);
          lineMax = Math.max(0, Math.floor((baseStock - otherBaseInCart) / factor));
        }
      }

      if (isStockControlled && lineMax !== null && lineMax <= 0) {
        return;
      }

      let basePrice = selectedUOM ? selectedUOM.price : product.price;
      if (selectedVariant) {
        basePrice = selectedVariant.price;
      }
      if (Object.keys(selectedVariants).length > 0) {
        Object.entries(selectedVariants).forEach(([key, val]) => {
          if (!selectedVariant && val.priceDelta) basePrice += val.priceDelta;
        });
      }

      const existing = state.items.find((item) => item.id === cartItemId);
      if (existing) {
        const nextQty = existing.quantity + quantity;
        existing.quantity = lineMax !== null ? Math.min(nextQty, lineMax) : nextQty;
      } else {
        const initialQty = lineMax !== null ? Math.min(quantity, lineMax) : quantity;
        if (initialQty > 0) {
          state.items.unshift({
            id: cartItemId,
            product,
            quantity: initialQty,
            selectedUOM: selectedUOM || undefined,
            selectedVariants: Object.keys(selectedVariants).length > 0 ? selectedVariants : undefined,
            selectedVariant: selectedVariant || undefined,
            unitPrice: basePrice,
          });
        }
      }
    },
    updateQuantity: (state, action: PayloadAction<{ itemId: string; delta: number }>) => {
      const { itemId, delta } = action.payload;
      const itemIndex = state.items.findIndex(i => i.id === itemId);
      if (itemIndex !== -1) {
        const item = state.items[itemIndex];
        const maxQuantity = getCartItemMaxQuantity(item, state.items);
        item.quantity = delta > 0 && maxQuantity !== null
          ? Math.min(item.quantity + delta, maxQuantity)
          : item.quantity + delta;
        if (item.quantity <= 0) {
          state.items.splice(itemIndex, 1);
        }
      }
    },
    updateUnitPrice: (state, action: PayloadAction<{ itemId: string; unitPrice: number }>) => {
      const { itemId, unitPrice } = action.payload;
      const item = state.items.find((cartItem) => cartItem.id === itemId);

      if (item && !item.promotionReward && Number.isFinite(unitPrice) && unitPrice >= 0) {
        item.unitPrice = unitPrice;
      }
    },
    removeItem: (state, action: PayloadAction<string>) => {
      state.items = state.items.filter(item => item.id !== action.payload);
    },
    clearCart: (state) => {
      state.items = [];
    },
    setCart: (state, action: PayloadAction<CartItem[]>) => {
      state.items = action.payload;
    },
  },
});

export const { addDirectToCart, addPromotionReward, addConfiguredToCart, updateQuantity, updateUnitPrice, removeItem, clearCart, setCart } = cartSlice.actions;
export default cartSlice.reducer;
