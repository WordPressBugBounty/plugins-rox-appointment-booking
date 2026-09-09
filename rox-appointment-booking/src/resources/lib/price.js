/**
 * Price coercion for the booking panel.
 *
 * A price does not arrive in one shape. WordPress serialises the DECIMAL
 * columns behind service/extra-service prices as strings ("25.00") on some
 * routes and as numbers on others, and an unset one comes through as null.
 * That matters in two places, both of which used to take the raw value:
 *
 *   - rendering, where `price.toFixed(2)` on a string throws *inside render*
 *     and unmounts the whole panel, leaving the customer a blank frame;
 *   - the running total, where `total += "25.00"` concatenates instead of
 *     adding, so the amount handed to the payment gateway is wrong.
 */

/**
 * The price as a number that is always safe to do arithmetic on.
 *
 * @param {*} value Raw price from the API, in whatever shape it arrived.
 * @return {number} The price, or 0 when it is missing or unparseable.
 */
export const priceValue = (value) => {
  const number = typeof value === "number" ? value : parseFloat(value);

  return Number.isFinite(number) ? number : 0;
};

/**
 * The price as it should be displayed, symbol included.
 *
 * @param {*}      value  Raw price from the API.
 * @param {string} symbol Currency symbol to prefix.
 * @return {string} e.g. "$25.00".
 */
export const formatPrice = (value, symbol = "$") =>
  `${symbol}${priceValue(value).toFixed(2)}`;
