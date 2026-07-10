/**
 * Shared signal for refreshing the admin notification bell.
 *
 * The bell (NotificationContext) fetches once on mount. Mutating REST calls
 * (appointment / order / payment saves) create new notifications server-side,
 * so the request helper fires this event on every successful mutation and the
 * context re-fetches in response. One constant shared by the dispatcher and the
 * listener keeps the event name from drifting.
 */

export const NOTIFICATIONS_REFRESH_EVENT =
  "rox-appointment-booking:refresh-notifications";

/**
 * Tell the notification bell to re-fetch.
 *
 * @return {void}
 */
export function dispatchNotificationsRefresh() {
  window.dispatchEvent(new Event(NOTIFICATIONS_REFRESH_EVENT));
}
