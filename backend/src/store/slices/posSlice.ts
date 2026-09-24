import { createSlice, PayloadAction } from '@reduxjs/toolkit';
import { DiscountType, HeldOrder } from '@/types/pos-types';

interface PosState {
  discountType: DiscountType;
  discountValue: number;
  taxPercent: number;
  serviceFee: number;
  orderType: 'Takeaway' | 'Dine-in' | 'Delivery';
  heldOrders: HeldOrder[];
}

const initialState: PosState = {
  discountType: 'percentage',
  discountValue: 0,
  taxPercent: 0,
  serviceFee: 0,
  orderType: 'Takeaway',
  heldOrders: [],
};

export const posSlice = createSlice({
  name: 'pos',
  initialState,
  reducers: {
    setDiscount: (
      state,
      action: PayloadAction<{ type: DiscountType; value: number }>
    ) => {
      state.discountType = action.payload.type;
      state.discountValue = action.payload.value;
    },
    setDiscountValue: (state, action: PayloadAction<number>) => {
      state.discountValue = action.payload;
    },
    setTaxPercent: (state, action: PayloadAction<number>) => {
      state.taxPercent = action.payload;
    },
    setServiceFee: (state, action: PayloadAction<number>) => {
      state.serviceFee = action.payload;
    },
    setOrderType: (state, action: PayloadAction<'Takeaway' | 'Dine-in' | 'Delivery'>) => {
      state.orderType = action.payload;
    },
    addHeldOrder: (state, action: PayloadAction<HeldOrder>) => {
      state.heldOrders.unshift(action.payload);
    },
    removeHeldOrder: (state, action: PayloadAction<string>) => {
      state.heldOrders = state.heldOrders.filter(h => h.id !== action.payload);
    },
    setHeldOrders: (state, action: PayloadAction<HeldOrder[]>) => {
      state.heldOrders = action.payload;
    },
  },
});

export const {
  setDiscount,
  setDiscountValue,
  setTaxPercent,
  setServiceFee,
  setOrderType,
  addHeldOrder,
  removeHeldOrder,
  setHeldOrders,
} = posSlice.actions;
export default posSlice.reducer;
