import React from "react";

// Time-slot grid (mockup `.slot-row`). `slots` is an array of
// { value, label, disabled }; `selected` compares against `value`. Defaults to
// the static dummy set (used by the Book New dummy) so callers that don't pass
// real slots keep working.
const DEFAULT_SLOTS = [
  { value: "9:00 AM", label: "9:00 AM" },
  { value: "9:30 AM", label: "9:30 AM" },
  { value: "10:00 AM", label: "10:00 AM" },
  { value: "10:30 AM", label: "10:30 AM" },
  { value: "11:00 AM", label: "11:00 AM" },
  { value: "11:30 AM", label: "11:30 AM", disabled: true },
  { value: "1:00 PM", label: "1:00 PM" },
  { value: "2:00 PM", label: "2:00 PM" },
  { value: "2:30 PM", label: "2:30 PM" },
  { value: "3:00 PM", label: "3:00 PM" },
  { value: "3:30 PM", label: "3:30 PM" },
  { value: "4:00 PM", label: "4:00 PM" },
];

export default function SlotPicker({ slots = DEFAULT_SLOTS, selected, onSelect }) {
  return (
    <div className="slot-row">
      {slots.map((slot) => {
        const classes = [
          "slot",
          slot.disabled && "disabled",
          !slot.disabled && selected === slot.value && "selected",
        ]
          .filter(Boolean)
          .join(" ");
        return (
          <div
            key={slot.value}
            className={classes}
            onClick={() => !slot.disabled && onSelect && onSelect(slot.value)}
          >
            {slot.label}
          </div>
        );
      })}
    </div>
  );
}
