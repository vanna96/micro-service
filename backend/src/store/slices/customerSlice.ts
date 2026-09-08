import { createSlice, PayloadAction } from '@reduxjs/toolkit';
import { Customer } from '@/types/pos-types';

interface CustomerState {
  profile: Customer;
}

export const emptyCustomer: Customer = {
  id: '',
  code: '',
  name: '',
  email: '',
  phone: '',
  status: '',
  avatar: '',
  priceListId: null,
  priceList: null,
};

const initialState: CustomerState = {
  profile: emptyCustomer,
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
