import { createSlice, PayloadAction } from '@reduxjs/toolkit';

interface UiState {
  activeCategory: string;
  searchQuery: string;
  stockFilter: string;
  activeModal: string | null;
  openDropdown: string | null;
  isFullscreen: boolean;
  selectedPayMethod: string;
}

const initialState: UiState = {
  activeCategory: 'all',
  searchQuery: '',
  stockFilter: 'all',
  activeModal: null,
  openDropdown: null,
  isFullscreen: false,
  selectedPayMethod: '',
};

export const uiSlice = createSlice({
  name: 'ui',
  initialState,
  reducers: {
    setActiveCategory: (state, action: PayloadAction<string>) => {
      state.activeCategory = action.payload;
    },
    setSearchQuery: (state, action: PayloadAction<string>) => {
      state.searchQuery = action.payload;
    },
    setStockFilter: (state, action: PayloadAction<string>) => {
      state.stockFilter = action.payload;
    },
    setActiveModal: (state, action: PayloadAction<string | null>) => {
      state.activeModal = action.payload;
    },
    setOpenDropdown: (state, action: PayloadAction<string | null>) => {
      state.openDropdown = action.payload;
    },
    setIsFullscreen: (state, action: PayloadAction<boolean>) => {
      state.isFullscreen = action.payload;
    },
    setSelectedPayMethod: (state, action: PayloadAction<string>) => {
      state.selectedPayMethod = action.payload;
    },
  },
});

export const {
  setActiveCategory,
  setSearchQuery,
  setStockFilter,
  setActiveModal,
  setOpenDropdown,
  setIsFullscreen,
  setSelectedPayMethod,
} = uiSlice.actions;
export default uiSlice.reducer;
