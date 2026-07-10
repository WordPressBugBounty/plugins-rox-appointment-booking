import { createReduxStore, register } from "@wordpress/data";

// Initial state
const DEFAULT_STATE = {
  openModal: false,
  products: [], // Store API response
  loading: false,
  error: null,
};

// Actions
const actions = {
  toggleModal() {
    return { type: "TOGGLE_MODAL" };
  },
};

// Reducer
export const store = createReduxStore("my-shop", {
  reducer(state = DEFAULT_STATE, action) {
    switch (action.type) {
      case "TOGGLE_MODAL":
        console.log("called toggle")
        return { ...state, openModal: !state.openModal };

      case "FETCH_PRODUCTS_START":
        return { ...state, loading: true, error: null };

      case "FETCH_PRODUCTS_SUCCESS":
        return { ...state, loading: false, products: action.payload };

      case "FETCH_PRODUCTS_ERROR":
        return { ...state, loading: false, error: action.payload };

      default:
        return state;
    }
  },

  actions,

  selectors: {
    isModalOpen(state) {
      return state.openModal;
    },
    getProducts(state) {
      return state.products;
    },
    isLoading(state) {
      return state.loading;
    },
    getError(state) {
      return state.error;
    },
  },
});

// Register the store
register(store);
