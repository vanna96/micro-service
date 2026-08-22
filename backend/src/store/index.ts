import { configureStore } from '@reduxjs/toolkit';
import cartReducer from './slices/cartSlice';
import posReducer from './slices/posSlice';
import uiReducer from './slices/uiSlice';
import customerReducer from './slices/customerSlice';

export const store = configureStore({
  reducer: {
    cart: cartReducer,
    pos: posReducer,
    ui: uiReducer,
    customer: customerReducer,
  },
});

export type RootState = ReturnType<typeof store.getState>;
export type AppDispatch = typeof store.dispatch;
