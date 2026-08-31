import { __ } from "@wordpress/i18n";

// The panel's own arrow glyphs, redrawn here rather than imported: the shared
// Icon component pulls in the whole icon set, which an editor bundle has no
// other use for.
const NavArrow = ({ back }) => (
	<svg width="14" height="14" viewBox="0 0 16 11" fill="none">
		<path
			stroke="currentColor"
			strokeLinecap="round"
			strokeLinejoin="round"
			strokeWidth={1.5}
			d={
				back
					? "M.75 5.125h14M5.125 9.5S.75 6.278.75 5.125 5.125.75 5.125.75"
					: "M14.75 5.125h-14M10.375 9.5s4.375-3.222 4.375-4.375S10.375.75 10.375.75"
			}
		/>
	</svg>
);

/**
 * The booking panel's navigation row, drawn statically for a block preview.
 *
 * Uses the panel's own class names, so it is painted by the same stylesheet
 * rules — and therefore the same `--rox-nav-*` variables — as the published
 * panel. Belongs inside a `.main` that carries the `with-navigation` class,
 * which reserves the row's height.
 *
 * @return {JSX.Element} The row.
 */
const NavButtonsPreview = () => (
	<div className="navigation-buttons">
		<div className="back-button">
			<div className="back-arrow">
				<NavArrow back />
			</div>
			<div className="back-text">{__("Back", "rox-appointment-booking")}</div>
		</div>
		<button type="button" className="next-button">
			<div className="next-text">{__("Next", "rox-appointment-booking")}</div>
			<div className="next-arrow">
				<NavArrow />
			</div>
		</button>
	</div>
);

export default NavButtonsPreview;
