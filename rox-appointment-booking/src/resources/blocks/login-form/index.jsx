import { registerBlockType } from "@wordpress/blocks";
import metadata from "./block.json";
import Edit from "./edit.jsx";
import "./editor.scss";

// Colored block icon (an account card with a key accent). Set here on
// registerBlockType because block.json `icon` only supports dashicon slugs.
const icon = (
	<svg
		width="24"
		height="24"
		viewBox="0 0 24 24"
		fill="none"
		xmlns="http://www.w3.org/2000/svg"
	>
		<rect x="2" y="4" width="20" height="16" rx="2.5" fill="#EEF2FF" />
		<rect x="5" y="8" width="9" height="2.5" rx="1.25" fill="#9AA0AC" />
		<rect x="5" y="13" width="6" height="2.5" rx="1.25" fill="#C7D2FE" />
		<circle cx="17.5" cy="13.5" r="3" fill="#3560FB" />
		<path
			d="M17.5 13.5v3.2"
			stroke="#fff"
			strokeWidth="1.2"
			strokeLinecap="round"
		/>
		<path
			d="M17.5 15.6h1.2"
			stroke="#fff"
			strokeWidth="1.2"
			strokeLinecap="round"
		/>
	</svg>
);

registerBlockType(metadata.name, {
	icon,
	edit: Edit,
	// Dynamic block: markup is produced by the PHP render_callback.
	save: () => null,
});
