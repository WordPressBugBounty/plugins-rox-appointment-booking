import React from "react";
import BookingCard from "./BookingCard.jsx";

// Renders a list of BookingCards for the active tab, or an empty state.
export default function BookingList({ bookings = [], onAction, onOpenDetail }) {
  if (!bookings.length) {
    return (
      <div className="empty-state">
        <div className="empty-icon">📭</div>
        <div className="empty-title">No bookings here yet</div>
        <div className="empty-msg">Your bookings will appear in this tab.</div>
      </div>
    );
  }

  return (
    <div className="booking-list">
      {bookings.map((booking) => (
        <BookingCard
          key={booking.id}
          booking={booking}
          onAction={onAction}
          onOpenDetail={onOpenDetail}
        />
      ))}
    </div>
  );
}
