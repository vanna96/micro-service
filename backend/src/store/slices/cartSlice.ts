import { createSlice, PayloadAction } from '@reduxjs/toolkit';
import { Product, CartItem, ProductUOM, VariantValue } from '@/types/pos-types';
import { INITIAL_CART } from '@/data/pos-data';

interface CartState {
  items: CartItem[];
}

const initialState: CartState = {
  items: INITIAL_CART,
};

export const cartSlice = createSlice({
  name: 'cart',
  initialState,
  reducers: {
    addDirectToCart: (state, action: PayloadAction<Product>) => {
      const product = action.payload;
      const existing = state.items.find(
        (item) => item.product.id === product.id && !item.selectedVariants && !item.selectedUOM
      );
      if (existing) {
        existing.quantity += 1;
      } else {
        state.items.unshift({
          id: product.id,
          product,
          quantity: 1,
          unitPrice: product.price,
        });
      }
    },
    addConfiguredToCart: (
      state,
      action: PayloadAction<{
        product: Product;
        quantity: number;
        selectedUOM: ProductUOM | null;
        selectedVariants: Record<string, VariantValue>;
      }>
    ) => {
      const { product, quantity, selectedUOM, selectedVariants } = action.payload;
      let basePrice = selectedUOM ? selectedUOM.price : product.price;
      const keyParts = [product.id];

      if (selectedUOM) keyParts.push(`UOM:${selectedUOM.shortCode}`);

      if (Object.keys(selectedVariants).length > 0) {
        Object.entries(selectedVariants).forEach(([key, val]) => {
          if (val.priceDelta) basePrice += val.priceDelta;
          keyParts.push(`${key}:${val.label}`);
        });
      }

      const cartItemId = keyParts.join('-');

      const existing = state.items.find((item) => item.id === cartItemId);
      if (existing) {
        existing.quantity += quantity;
      } else {
        state.items.unshift({
          id: cartItemId,
          product,
          quantity,
          selectedUOM: selectedUOM || undefined,
          selectedVariants: Object.keys(selectedVariants).length > 0 ? selectedVariants : undefined,
          unitPrice: basePrice,
        });
      }
    },
    updateQuantity: (state, action: PayloadAction<{ itemId: string; delta: number }>) => {
      const { itemId, delta } = action.payload;
      const itemIndex = state.items.findIndex(i => i.id === itemId);
      if (itemIndex !== -1) {
        state.items[itemIndex].quantity += delta;
        if (state.items[itemIndex].quantity <= 0) {
          state.items.splice(itemIndex, 1);
        }
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

export const { addDirectToCart, addConfiguredToCart, updateQuantity, removeItem, clearCart, setCart } = cartSlice.actions;
export default cartSlice.reducer;
