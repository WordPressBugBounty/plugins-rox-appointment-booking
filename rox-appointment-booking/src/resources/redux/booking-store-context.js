/**
 * Which booking store the components inside one panel should talk to.
 *
 * The panel's store is per-instance (see `getBookingServiceStore`), so its
 * children can no longer import one fixed store: two panels on a page would
 * both reach for the same selections. They ask this context instead, and
 * `BookingService` provides the store belonging to its own instance.
 *
 * Outside a provider the hooks fall back to the default instance, which keeps
 * any surface that renders a panel child on its own working unchanged.
 */

import { createContext, useContext } from "@wordpress/element";

import {
	bookingServiceStore,
	SESSION_STORAGE_KEY,
} from "./booking-service-slice.js";

const BookingStoreContext = createContext({
	store: bookingServiceStore,
	sessionKey: SESSION_STORAGE_KEY,
});

export const BookingStoreProvider = BookingStoreContext.Provider;

/**
 * The store for the panel this component is rendered inside.
 *
 * @return {Object} @wordpress/data store descriptor.
 */
export const useBookingStore = () => useContext(BookingStoreContext).store;

/**
 * The sessionStorage key that panel persists under. Needed by the few places
 * that read or rewrite the persisted blob directly rather than going through
 * the store.
 *
 * @return {string} Storage key.
 */
export const useBookingSessionKey = () =>
	useContext(BookingStoreContext).sessionKey;
