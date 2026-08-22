import { createSlice, PayloadAction } from '@reduxjs/toolkit';
import { Customer } from '@/types/pos-types';

interface CustomerState {
  profile: Customer;
}

const initialState: CustomerState = {
  profile: {
    name: 'Jonathan Michael',
    tier: 'Platinum Member',
    points: 2450,
    status: 'Active',
    avatar: '/assets/user-47-C1F3Gd9o.png',
  },
};

export const customerSlice = createSlice({
  name: 'customer',
  initialState,
  reducers: {
    setCustomer: (state, action: PayloadAction<Customer>) => {
      state.profile = action.payload;
    },
  },
});

export const { setCustomer } = customerSlice.actions;
export default customerSlice.reducer;
