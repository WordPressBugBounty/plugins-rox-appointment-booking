/**
 * Helper function to move WordPress enqueued CSS with "rox-appointment-booking-" prefix into Shadow DOM
 * This isolates our styles from WordPress parent CSS conflicts
 */
export const moveRoxAppointmentBookingCSSToShadowDOM = (shadowRoot) => {
    // Find all CSS link elements with rox-appointment-booking prefix
    const roxAppointmentBookingCSSLinks = document.querySelectorAll('link[id*="rox-appointment-booking-"]');

    roxAppointmentBookingCSSLinks.forEach(link => {
        if (link.rel === 'stylesheet') {
            // Clone the link element
            const shadowLink = link.cloneNode(true);
            // Add to shadow DOM
            shadowRoot.appendChild(shadowLink);
            // Remove from main document to prevent conflicts
            link.remove();
        }
    });
};