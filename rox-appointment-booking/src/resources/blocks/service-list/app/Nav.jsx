import React from "react";

/**
 * Bespoke Next/Back nav for the Service List block. Uses block-owned classes so
 * the `--rab-*` customization vars (printed by PHP) apply without touching the
 * shortcode's `bookingstyle.scss`.
 */
const Nav = ({
  onBack,
  onNext,
  backLabel,
  nextLabel,
  nextDisabled = false,
  showBack = true,
  showNext = true,
}) => (
  <div className="rab-sl-nav">
    {showBack ? (
      <div className="rab-sl-back" onClick={onBack}>
        <span className="rab-sl-back-arrow">←</span>
        <span className="rab-sl-back-text">{backLabel || "Back"}</span>
      </div>
    ) : (
      <span />
    )}
    {showNext ? (
      <button
        type="button"
        className={`rab-sl-next ${nextDisabled ? "is-disabled" : ""}`}
        onClick={nextDisabled ? undefined : onNext}
        disabled={nextDisabled}
      >
        <span className="rab-sl-next-text">{nextLabel || "Next"}</span>
        <span className="rab-sl-next-arrow">→</span>
      </button>
    ) : null}
  </div>
);

export default Nav;
