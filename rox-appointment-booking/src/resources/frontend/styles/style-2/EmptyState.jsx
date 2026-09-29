/**
 * Style 2's empty-state block.
 *
 * Style 1's `BookingEmptyState` outright, rather than a shape of Style 2's own
 * drawn to match it: the card is the dot-grid ground, the illustration, the
 * title, the line under it and the actions, and every one of those is already
 * built there. Reproducing it here would have meant a second copy of the same
 * card to keep in step with the first.
 *
 * So this file is only the seam. It takes the props Style 2's steps already
 * pass — title, description, actions — and hands them over with the icon for
 * whichever step is empty.
 */

import BookingEmptyState from "../../../components/common/BookingEmptyState.jsx";

const EmptyState = ({ icon = "noservice", title, description, actions = [] }) => (
	<BookingEmptyState
		icon={icon}
		title={title}
		description={description}
		actions={actions}
	/>
);

export default EmptyState;
