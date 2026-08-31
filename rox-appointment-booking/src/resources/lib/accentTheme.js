/**
 * Panel accent helpers, shared by every surface that mounts the booking panel.
 *
 * The panel stylesheet derives every accent shade from one `--rox-accent`
 * custom property, but antd renders toasts, tooltips, the payment switch and
 * spinners in a portal on <body> where that variable cannot reach. These two
 * helpers bridge the gap: read the accent back off the cascade, then hand it to
 * antd as a theme token.
 */

/**
 * The accent a surface chose, read from the cascade rather than a data
 * attribute: the Elementor controls write it as a CSS variable (the only form
 * an Elementor Global Colour survives into) and the blocks write it as an
 * inline style, so the computed value is the one place every surface agrees.
 *
 * @param {Element} element Mount node.
 * @return {string} The accent, or '' when no surface set one.
 */
export const readAccent = (element) => {
  try {
    return window
      .getComputedStyle(element)
      .getPropertyValue("--rox-accent")
      .trim();
  } catch (e) {
    return "";
  }
};

/**
 * Re-points antd's primary colour at the panel accent. antd generates its CSS
 * from these tokens at runtime and never reads a custom property, so this is
 * the only way its components follow a recoloured panel.
 *
 * @param {Object} base   The surface's base antd theme config.
 * @param {string} accent Accent colour, or '' to leave the theme alone.
 * @return {Object} Theme config to hand to ConfigProvider.
 */
export const withAccent = (base, accent) => {
  if (!accent) {
    return base;
  }

  return {
    ...base,
    token: { ...base.token, colorPrimary: accent },
    components: {
      ...base.components,
      Button: {
        ...base.components.Button,
        colorPrimary: accent,
        colorPrimaryHover: accent,
        colorPrimaryActive: accent,
      },
      Input: { ...base.components.Input, activeBorderColor: accent },
    },
  };
};
