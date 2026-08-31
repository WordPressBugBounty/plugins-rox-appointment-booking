import { createReduxStore, register } from "@wordpress/data";

// Session storage key. Each panel instance persists under its own suffix so
// that two booking surfaces on one page cannot restore each other's progress;
// the bare key stays the default instance's, keeping older saved state valid.
export const SESSION_STORAGE_KEY = "rox_appointment_booking_service_state";

/**
 * The sessionStorage key a panel instance persists under.
 *
 * @param {string|number} instanceId Panel instance id, or falsy for the default.
 * @return {string} Storage key.
 */
export const sessionKeyFor = (instanceId) =>
  instanceId ? `${SESSION_STORAGE_KEY}_${instanceId}` : SESSION_STORAGE_KEY;

// Helper function to deserialize dates in bookingProcess
const deserializeBookingProcess = (bookingProcess) => {
  if (!Array.isArray(bookingProcess)) return [];
  
  return bookingProcess.map(booking => ({
    ...booking,
    date: booking.date ? new Date(booking.date) : null,
  }));
};

// Load state from sessionStorage
const loadStateFromSession = (storageKey) => {
  try {
    const serializedState = sessionStorage.getItem(storageKey);
    if (serializedState === null) {
      return null;
    }
    const parsed = JSON.parse(serializedState);
    
    // Deserialize dates in bookingProcess
    if (parsed && parsed.bookingProcess) {
      parsed.bookingProcess = deserializeBookingProcess(parsed.bookingProcess);
    }
    
    return parsed;
  } catch (err) {
    console.error("Error loading state from sessionStorage:", err);
    return null;
  }
};

// Initial state with defaults
const getInitialState = (storageKey) => {
  const sessionState = loadStateFromSession(storageKey);

  // Reconcile the persisted login against the real WordPress session. The panel
  // login now establishes a WP session, so if the persisted state says logged in
  // but WordPress no longer has a session (e.g. the user logged out from the
  // customer panel or elsewhere), drop the stale login instead of showing them
  // as logged in.
  const appConfig = window.rox_appointment_booking?.config?.app;
  if (sessionState && sessionState.isLoggedIn && appConfig && !appConfig.is_user_logged_in) {
    sessionState.isLoggedIn = false;
    sessionState.loggedInUser = null;
    sessionState.customerInfo = null;
  }

  return {
    // Location & Category
    locations: [],
    selectedLocation: sessionState?.selectedLocation || null,
    selectedLocationId: sessionState?.selectedLocationId || null,
    categories: [],
    selectedCategory: sessionState?.selectedCategory || null,
    
    // Services
    services: [],
    selectedService: sessionState?.selectedService || null,
    
    // Employees/Agents
    agents: [],
    selectedEmployee: sessionState?.selectedEmployee || null,
    viewingEmployeeDetails: null, // Don't persist this
    
    // Date & Time
    selectedDate: sessionState?.selectedDate ? new Date(sessionState.selectedDate) : null,
    selectedStartTime: sessionState?.selectedStartTime || null,
    selectedEndTime: sessionState?.selectedEndTime || null,
    
    // Customer Info
    customerInfo: sessionState?.customerInfo || null,
    
    // Logged-in User
    loggedInUser: sessionState?.loggedInUser || null,
    isLoggedIn: sessionState?.isLoggedIn || false,
    
    // Booking Process
    bookingProcess: sessionState?.bookingProcess || [],
    
    // Extra Services
    extraServices: [],
    selectedExtraServices: sessionState?.selectedExtraServices || [],
    showExtraServices: false,
    currentBookingForExtras: null,
    
    // Step & UI State
    currentStep: sessionState?.currentStep || 1,
    hasLocations: sessionState?.hasLocations ?? true,
    
    // Payment
    paymentSuccess: false,
    payLater: false,
    currency: sessionState?.currency || "usd",
    bookingResponse: null,
    
    // Content/Config - Don't persist, loaded from API
    content: {},
  };
};

// Actions
const actions = {
  // Location actions
  setLocations(locations) {
    return { type: "SET_LOCATIONS", locations };
  },
  setSelectedLocation(location, locationId) {
    return { type: "SET_SELECTED_LOCATION", location, locationId };
  },
  
  // Category actions
  setCategories(categories) {
    return { type: "SET_CATEGORIES", categories };
  },
  setSelectedCategory(category) {
    return { type: "SET_SELECTED_CATEGORY", category };
  },
  
  // Service actions
  setServices(services) {
    return { type: "SET_SERVICES", services };
  },
  setSelectedService(service) {
    return { type: "SET_SELECTED_SERVICE", service };
  },
  
  // Agent actions
  setAgents(agents) {
    return { type: "SET_AGENTS", agents };
  },
  setSelectedEmployee(employee) {
    return { type: "SET_SELECTED_EMPLOYEE", employee };
  },
  setViewingEmployeeDetails(employee) {
    return { type: "SET_VIEWING_EMPLOYEE_DETAILS", employee };
  },
  
  // Date & Time actions
  setSelectedDateTime(date, startTime, endTime) {
    return { type: "SET_SELECTED_DATETIME", date, startTime, endTime };
  },
  
  // Customer Info actions
  setCustomerInfo(customerInfo) {
    return { type: "SET_CUSTOMER_INFO", customerInfo };
  },
  
  // Login actions
  setLoggedInUser(user) {
    return { type: "SET_LOGGED_IN_USER", user };
  },
  logout() {
    return { type: "LOGOUT" };
  },
  
  // Booking Process actions
  setBookingProcess(bookingProcess) {
    return { type: "SET_BOOKING_PROCESS", bookingProcess };
  },
  addBooking(booking) {
    return { type: "ADD_BOOKING", booking };
  },
  updateBooking(bookingId, updates) {
    return { type: "UPDATE_BOOKING", bookingId, updates };
  },
  deleteBooking(bookingId) {
    return { type: "DELETE_BOOKING", bookingId };
  },
  
  // Extra Services actions
  setExtraServices(extraServices) {
    return { type: "SET_EXTRA_SERVICES", extraServices };
  },
  setSelectedExtraServices(selectedExtraServices) {
    return { type: "SET_SELECTED_EXTRA_SERVICES", selectedExtraServices };
  },
  setShowExtraServices(show, currentBooking = null) {
    return { type: "SET_SHOW_EXTRA_SERVICES", show, currentBooking };
  },
  
  // Step actions
  setCurrentStep(step) {
    return { type: "SET_CURRENT_STEP", step };
  },
  nextStep() {
    return { type: "NEXT_STEP" };
  },
  previousStep() {
    return { type: "PREVIOUS_STEP" };
  },
  
  // Config actions
  setContent(content) {
    return { type: "SET_CONTENT", content };
  },
  setHasLocations(hasLocations) {
    return { type: "SET_HAS_LOCATIONS", hasLocations };
  },
  
  // Payment actions
  setPaymentSuccess(success) {
    return { type: "SET_PAYMENT_SUCCESS", success };
  },
  setPayLater(payLater) {
    return { type: "SET_PAY_LATER", payLater };
  },
  setCurrency(currency) {
    return { type: "SET_CURRENCY", currency };
  },
  setBookingResponse(response) {
    return { type: "SET_BOOKING_RESPONSE", response };
  },
  
  // Reset actions
  resetBookingFlow() {
    return { type: "RESET_BOOKING_FLOW" };
  },
  clearSessionData() {
    return { type: "CLEAR_SESSION_DATA" };
  },
};

// Helper function to save state to sessionStorage
const saveStateToSession = (state, storageKey) => {
  try {
    // Only save data that should persist across refreshes
    const stateToPersist = {
      selectedLocation: state.selectedLocation,
      selectedLocationId: state.selectedLocationId,
      selectedCategory: state.selectedCategory,
      selectedService: state.selectedService,
      selectedEmployee: state.selectedEmployee,
      selectedDate: state.selectedDate,
      selectedStartTime: state.selectedStartTime,
      selectedEndTime: state.selectedEndTime,
      customerInfo: state.customerInfo,
      loggedInUser: state.loggedInUser,
      isLoggedIn: state.isLoggedIn,
      bookingProcess: state.bookingProcess,
      selectedExtraServices: state.selectedExtraServices,
      currentStep: state.currentStep,
      hasLocations: state.hasLocations,
      currency: state.currency,
    };
    
    const serializedState = JSON.stringify(stateToPersist);
    sessionStorage.setItem(storageKey, serializedState);
  } catch (err) {
    console.error("Error saving state to sessionStorage:", err);
  }
};

// Reducer
const createReducer = (storageKey) => (state = getInitialState(storageKey), action) => {
  let newState = state;
  
  switch (action.type) {
    case "SET_LOCATIONS":
      newState = { ...state, locations: action.locations };
      break;
      
    case "SET_SELECTED_LOCATION":
      newState = { 
        ...state, 
        selectedLocation: action.location,
        selectedLocationId: action.locationId 
      };
      break;
      
    case "SET_CATEGORIES":
      newState = { ...state, categories: action.categories };
      break;
      
    case "SET_SELECTED_CATEGORY":
      newState = { ...state, selectedCategory: action.category };
      break;
      
    case "SET_SERVICES":
      newState = { ...state, services: action.services };
      break;
      
    case "SET_SELECTED_SERVICE":
      newState = { ...state, selectedService: action.service };
      break;
      
    case "SET_AGENTS":
      newState = { ...state, agents: action.agents };
      break;
      
    case "SET_SELECTED_EMPLOYEE":
      newState = { ...state, selectedEmployee: action.employee };
      break;
      
    case "SET_VIEWING_EMPLOYEE_DETAILS":
      newState = { ...state, viewingEmployeeDetails: action.employee };
      break;
      
    case "SET_SELECTED_DATETIME":
      newState = { 
        ...state, 
        selectedDate: action.date,
        selectedStartTime: action.startTime,
        selectedEndTime: action.endTime
      };
      break;
      
    case "SET_CUSTOMER_INFO":
      newState = { ...state, customerInfo: action.customerInfo };
      break;
      
    case "SET_LOGGED_IN_USER":
      newState = { 
        ...state, 
        loggedInUser: action.user,
        isLoggedIn: true 
      };
      break;
      
    case "LOGOUT":
      newState = { 
        ...state, 
        loggedInUser: null,
        isLoggedIn: false,
        customerInfo: null 
      };
      break;
      
    case "SET_BOOKING_PROCESS":
      newState = { ...state, bookingProcess: action.bookingProcess };
      break;
      
    case "ADD_BOOKING":
      newState = { 
        ...state, 
        bookingProcess: [action.booking, ...state.bookingProcess] 
      };
      break;
      
    case "UPDATE_BOOKING":
      newState = {
        ...state,
        bookingProcess: state.bookingProcess.map((booking) =>
          booking.id === action.bookingId
            ? { ...booking, ...action.updates }
            : booking
        ),
      };
      break;
      
    case "DELETE_BOOKING":
      newState = {
        ...state,
        bookingProcess: state.bookingProcess.filter(
          (booking) => booking.id !== action.bookingId
        ),
      };
      break;
      
    case "SET_EXTRA_SERVICES":
      newState = { ...state, extraServices: action.extraServices };
      break;
      
    case "SET_SELECTED_EXTRA_SERVICES":
      newState = { ...state, selectedExtraServices: action.selectedExtraServices };
      break;
      
    case "SET_SHOW_EXTRA_SERVICES":
      newState = { 
        ...state, 
        showExtraServices: action.show,
        currentBookingForExtras: action.currentBooking 
      };
      break;
      
    case "SET_CURRENT_STEP":
      newState = { ...state, currentStep: action.step };
      break;
      
    case "NEXT_STEP":
      newState = { ...state, currentStep: state.currentStep + 1 };
      break;
      
    case "PREVIOUS_STEP":
      newState = { 
        ...state, 
        currentStep: Math.max(state.currentStep - 1, state.hasLocations ? 1 : 1) 
      };
      break;
      
    case "SET_CONTENT":
      newState = { ...state, content: action.content };
      break;
      
    case "SET_HAS_LOCATIONS":
      newState = { ...state, hasLocations: action.hasLocations };
      break;
      
    case "SET_PAYMENT_SUCCESS":
      newState = { ...state, paymentSuccess: action.success };
      break;
      
    case "SET_PAY_LATER":
      newState = { ...state, payLater: action.payLater };
      break;
      
    case "SET_CURRENCY":
      newState = { ...state, currency: action.currency };
      break;
      
    case "SET_BOOKING_RESPONSE":
      newState = { ...state, bookingResponse: action.response };
      break;
      
    case "RESET_BOOKING_FLOW":
      newState = {
        ...state,
        selectedLocation: null,
        selectedLocationId: null,
        categories: [],
        selectedCategory: null,
        services: [],
        selectedService: null,
        selectedEmployee: null,
        selectedDate: null,
        selectedStartTime: null,
        selectedEndTime: null,
        customerInfo: null,
        currentStep: 1,
      };
      break;
      
    case "CLEAR_SESSION_DATA":
      sessionStorage.removeItem(storageKey);
      newState = getInitialState(storageKey);
      break;
      
    default:
      return state;
  }
  
  // Save to sessionStorage after state update (except for non-persistent actions)
  if (action.type !== "SET_VIEWING_EMPLOYEE_DETAILS" && 
      action.type !== "SET_CONTENT" &&
      action.type !== "SET_LOCATIONS" &&
      action.type !== "SET_CATEGORIES" &&
      action.type !== "SET_SERVICES" &&
      action.type !== "SET_AGENTS" &&
      action.type !== "SET_EXTRA_SERVICES" &&
      action.type !== "SET_PAYMENT_SUCCESS" &&
      action.type !== "SET_PAY_LATER" &&
      action.type !== "SET_BOOKING_RESPONSE" &&
      action.type !== "SET_SHOW_EXTRA_SERVICES") {
    saveStateToSession(newState, storageKey);
  }
  
  return newState;
};

// Selectors
const selectors = {
  // Location selectors
  getLocations(state) {
    return state.locations;
  },
  getSelectedLocation(state) {
    return state.selectedLocation;
  },
  getSelectedLocationId(state) {
    return state.selectedLocationId;
  },
  
  // Category selectors
  getCategories(state) {
    return state.categories;
  },
  getSelectedCategory(state) {
    return state.selectedCategory;
  },
  
  // Service selectors
  getServices(state) {
    return state.services;
  },
  getSelectedService(state) {
    return state.selectedService;
  },
  
  // Agent selectors
  getAgents(state) {
    return state.agents;
  },
  getSelectedEmployee(state) {
    return state.selectedEmployee;
  },
  getViewingEmployeeDetails(state) {
    return state.viewingEmployeeDetails;
  },
  
  // Date & Time selectors
  getSelectedDate(state) {
    return state.selectedDate;
  },
  getSelectedStartTime(state) {
    return state.selectedStartTime;
  },

  getSelectedEndTime(state) {
    return state.selectedEndTime;
  },
  
  // Customer Info selectors
  getCustomerInfo(state) {
    return state.customerInfo;
  },
  
  // Login selectors
  getLoggedInUser(state) {
    return state.loggedInUser;
  },
  getIsLoggedIn(state) {
    return state.isLoggedIn;
  },
  
  // Booking Process selectors
  getBookingProcess(state) {
    return state.bookingProcess;
  },
  
  // Extra Services selectors
  getExtraServices(state) {
    return state.extraServices;
  },
  getSelectedExtraServices(state) {
    return state.selectedExtraServices;
  },
  getShowExtraServices(state) {
    return state.showExtraServices;
  },
  getCurrentBookingForExtras(state) {
    return state.currentBookingForExtras;
  },
  
  // Step selectors
  getCurrentStep(state) {
    return state.currentStep;
  },
  getHasLocations(state) {
    return state.hasLocations;
  },
  
  // Content selectors
  getContent(state) {
    return state.content;
  },
  
  // Payment selectors
  getPaymentSuccess(state) {
    return state.paymentSuccess;
  },
  getPayLater(state) {
    return state.payLater;
  },
  getCurrency(state) {
    return state.currency;
  },
  getBookingResponse(state) {
    return state.bookingResponse;
  },
};

// Create and register store
//
// One store per panel instance rather than one for the page. The panel used to
// share a single registered store, so two booking surfaces on the same page
// (two blocks, a block plus a shortcode, …) read and wrote the same selections
// — picking a service in one changed the other. Each instance now gets its own
// store and its own sessionStorage key, and stores are memoised so a re-mount
// reuses the one already registered instead of registering the name twice.
const stores = new Map();

/**
 * Returns the store for a panel instance, registering it on first use.
 *
 * @param {string|number} instanceId Panel instance id. Falsy gives the shared
 *                                   default, which is what surfaces that never
 *                                   appear twice on a page use.
 * @return {Object} The @wordpress/data store descriptor.
 */
export const getBookingServiceStore = (instanceId) => {
  const key = instanceId ? String(instanceId) : "default";

  if (stores.has(key)) {
    return stores.get(key);
  }

  const name = instanceId
    ? `rox-appointment-booking/service-${key}`
    : "rox-appointment-booking/service";

  const store = createReduxStore(name, {
    reducer: createReducer(sessionKeyFor(instanceId)),
    actions,
    selectors,
  });

  register(store);
  stores.set(key, store);

  return store;
};

// The default instance, kept as a named export for the surfaces that only ever
// render one panel and reach for the store directly.
export const bookingServiceStore = getBookingServiceStore(null);
