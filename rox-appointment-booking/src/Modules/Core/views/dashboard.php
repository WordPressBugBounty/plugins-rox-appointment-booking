<?php defined('ABSPATH') || exit; ?>

<?php if (rox_appointment_booking_is_customer()) : ?>
	<div id="rox-appointment-booking-customer-panel-root" class="rox-appointment-booking-customer-panel-root"></div>
<?php else : ?>
	<div id="rox-appointment-booking-app-root" class="rox-appointment-booking-dashboard-root"></div>
<?php endif; ?>