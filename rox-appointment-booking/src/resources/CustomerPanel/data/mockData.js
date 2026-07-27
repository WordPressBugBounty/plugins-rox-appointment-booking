// Mock data for the Customer Panel dummy (Phase B). Mirrors
// dev-resources/exmaple-customer-dashboard.html exactly. Replaced by real
// CustomerPanel/REST endpoints in Phase D.

// Hero "next appointment" summary shown at the top of My Bookings.
export const nextAppointment = {
  title: "Your next appointment is tomorrow at 10:30 AM",
  sub: "Physiotherapy with Indigo Violet · Dhaka Main Branch",
};

// Tab counts (explicit — the Past tab shows a total of 12 while only a few
// sample cards are listed, matching the mockup).
export const bookingCounts = {
  upcoming: 3,
  past: 12,
  cancelled: 1,
};

export const upcomingBookings = [
  {
    id: "u1",
    month: "May",
    day: "12",
    weekday: "Tuesday",
    status: "approved",
    statusLabel: "✓ Approved",
    relativeLabel: "Tomorrow",
    title: "Physiotherapy Session",
    with: "with Indigo Violet · Dhaka Main Branch",
    meta: ["🕐 10:30 AM – 12:00 PM", "⏱ 1h 30m", "📍 123 Gulshan Ave"],
    price: "$54.00",
    actions: [
      { type: "reschedule", label: "Reschedule", variant: "secondary" },
      { type: "cancel", label: "Cancel", variant: "secondary" },
    ],
    detail: {
      info: [
        { label: "Service", value: "Physiotherapy" },
        { label: "Provider", value: "Indigo Violet" },
        { label: "Duration", value: "1h 30m" },
        { label: "Location", value: "Dhaka Main Branch" },
        { label: "Address", value: "123 Gulshan Ave, Dhaka 1212" },
      ],
      payment: [
        { label: "Service charge", value: "$50.00" },
        { label: "Tax (8%)", value: "$4.00" },
      ],
      paymentTotal: "$54.00 · Fully Paid",
      paymentTotalColor: "var(--green-text)",
      notes:
        "Please bring any X-rays or previous medical reports for your physiotherapist's review.",
    },
  },
  {
    id: "u2",
    month: "May",
    day: "18",
    weekday: "Monday",
    status: "pending",
    statusLabel: "⏱ Pending Approval",
    relativeLabel: "In 7 days",
    title: "Dermatology Consultation",
    with: "with Samuel Serif · Dhaka Main Branch",
    meta: ["🕐 2:00 PM – 2:45 PM", "⏱ 45m", "💳 Not Paid"],
    price: "$75.00",
    actions: [{ type: "payNow", label: "Pay Now", variant: "primary" }],
    detail: {
      info: [
        { label: "Service", value: "Dermatology" },
        { label: "Provider", value: "Samuel Serif" },
        { label: "Duration", value: "45m" },
        { label: "Location", value: "Dhaka Main Branch" },
        { label: "Address", value: "123 Gulshan Ave, Dhaka 1212" },
      ],
      payment: [
        { label: "Service charge", value: "$69.44" },
        { label: "Tax (8%)", value: "$5.56" },
      ],
      paymentTotal: "$75.00 · Not Paid",
      paymentTotalColor: "var(--yellow-text)",
      notes: "Avoid applying skincare products before your consultation.",
    },
  },
  {
    id: "u3",
    month: "May",
    day: "24",
    weekday: "Sunday",
    status: "rescheduled",
    statusLabel: "⟳ Rescheduled",
    relativeLabel: null,
    title: "Orthopedics Follow-up",
    with: "with Miles Tone · Sylhet Branch",
    meta: ["🕐 4:15 PM – 5:15 PM", "⏱ 1h", "💳 Fully Paid"],
    price: "$120.00",
    actions: [{ type: "directions", label: "Get Directions", variant: "secondary" }],
    detail: {
      info: [
        { label: "Service", value: "Orthopedics" },
        { label: "Provider", value: "Miles Tone" },
        { label: "Duration", value: "1h" },
        { label: "Location", value: "Sylhet Branch" },
        { label: "Address", value: "45 Zindabazar, Sylhet 3100" },
      ],
      payment: [
        { label: "Service charge", value: "$111.11" },
        { label: "Tax (8%)", value: "$8.89" },
      ],
      paymentTotal: "$120.00 · Fully Paid",
      paymentTotalColor: "var(--green-text)",
      notes: "Please arrive 10 minutes early to complete paperwork.",
    },
  },
];

export const pastBookings = [
  {
    id: "p1",
    month: "Apr",
    day: "28",
    weekday: "Monday",
    status: "completed",
    statusLabel: "✓ Completed",
    completed: true,
    title: "Physiotherapy Session",
    with: "with Indigo Violet · Dhaka Main Branch",
    meta: ["🕐 10:30 AM", "⏱ 1h 30m"],
    price: "$54.00",
    actions: [{ type: "bookAgain", label: "Book Again", variant: "secondary" }],
    detail: {
      info: [
        { label: "Service", value: "Physiotherapy" },
        { label: "Provider", value: "Indigo Violet" },
        { label: "Duration", value: "1h 30m" },
        { label: "Location", value: "Dhaka Main Branch" },
        { label: "Address", value: "123 Gulshan Ave, Dhaka 1212" },
      ],
      payment: [
        { label: "Service charge", value: "$50.00" },
        { label: "Tax (8%)", value: "$4.00" },
      ],
      paymentTotal: "$54.00 · Fully Paid",
      paymentTotalColor: "var(--green-text)",
      notes: "Session completed. Thank you for visiting.",
    },
  },
  {
    id: "p2",
    month: "Apr",
    day: "15",
    weekday: "Tuesday",
    status: "completed",
    statusLabel: "✓ Completed",
    completed: true,
    title: "Nutrition Advice",
    with: "with Joss Sticks · Dhaka Main Branch",
    meta: ["🕐 3:30 PM", "⏱ 30m"],
    price: "$45.00",
    actions: [{ type: "bookAgain", label: "Book Again", variant: "secondary" }],
    detail: {
      info: [
        { label: "Service", value: "Nutrition Advice" },
        { label: "Provider", value: "Joss Sticks" },
        { label: "Duration", value: "30m" },
        { label: "Location", value: "Dhaka Main Branch" },
        { label: "Address", value: "123 Gulshan Ave, Dhaka 1212" },
      ],
      payment: [
        { label: "Service charge", value: "$41.67" },
        { label: "Tax (8%)", value: "$3.33" },
      ],
      paymentTotal: "$45.00 · Fully Paid",
      paymentTotalColor: "var(--green-text)",
      notes: "Follow the diet plan shared via email.",
    },
  },
  {
    id: "p3",
    month: "Mar",
    day: "29",
    weekday: "Saturday",
    status: "completed",
    statusLabel: "✓ Completed",
    completed: true,
    title: "Skin Checks",
    with: "with Weir Doe · Dhaka Main Branch",
    meta: ["🕐 11:00 AM", "⏱ 1h 20m"],
    price: "$95.00",
    actions: [{ type: "bookAgain", label: "Book Again", variant: "secondary" }],
    detail: {
      info: [
        { label: "Service", value: "Skin Checks" },
        { label: "Provider", value: "Weir Doe" },
        { label: "Duration", value: "1h 20m" },
        { label: "Location", value: "Dhaka Main Branch" },
        { label: "Address", value: "123 Gulshan Ave, Dhaka 1212" },
      ],
      payment: [
        { label: "Service charge", value: "$87.96" },
        { label: "Tax (8%)", value: "$7.04" },
      ],
      paymentTotal: "$95.00 · Fully Paid",
      paymentTotalColor: "var(--green-text)",
      notes: "No further action needed.",
    },
  },
];

export const cancelledBookings = [
  {
    id: "c1",
    month: "Apr",
    day: "02",
    weekday: "Wed",
    status: "cancelled",
    statusLabel: "✕ Cancelled",
    relativeLabel: "by you, refund processed",
    title: "Pathology Test",
    with: "with Hanson Deck · Dhaka Main Branch",
    meta: ["🕐 9:00 AM", "⏱ 2h", "💰 $54.00 refunded"],
    price: "$54.00",
    priceStrikethrough: true,
    actions: [{ type: "bookAgain", label: "Book Again", variant: "secondary" }],
    detail: {
      info: [
        { label: "Service", value: "Pathology Test" },
        { label: "Provider", value: "Hanson Deck" },
        { label: "Duration", value: "2h" },
        { label: "Location", value: "Dhaka Main Branch" },
        { label: "Address", value: "123 Gulshan Ave, Dhaka 1212" },
      ],
      payment: [
        { label: "Service charge", value: "$50.00" },
        { label: "Tax (8%)", value: "$4.00" },
      ],
      paymentTotal: "$54.00 · Refunded",
      paymentTotalColor: "var(--teal-text)",
      notes: "This appointment was cancelled and the payment refunded.",
    },
  },
];

export const bookingsByTab = {
  upcoming: upcomingBookings,
  past: pastBookings,
  cancelled: cancelledBookings,
};

// --- Book New cascade mock data (B4) ---
// Mirrors the public booking panel's flow (Location → Category → Service →
// Agent → Date & Time → Confirm). Replaced by real CustomerPanel/REST +
// booking-panel-structure endpoints in Phase D.

export const bookNewLocations = [
  { id: 1, name: "Dhaka Main Branch", address: "123 Gulshan Ave, Dhaka 1212" },
  { id: 2, name: "Sylhet Branch", address: "45 Zindabazar, Sylhet 3100" },
];

export const bookNewCategories = [
  { id: 1, name: "Physiotherapy", icon: "🦵" },
  { id: 2, name: "Dermatology", icon: "🧴" },
  { id: 3, name: "Orthopedics", icon: "🦴" },
  { id: 4, name: "Nutrition", icon: "🥗" },
];

// Services keyed by category id.
export const bookNewServicesByCategory = {
  1: [
    { id: 11, name: "Physiotherapy Session", duration: "1h 30m", price: "$54.00" },
    { id: 12, name: "Sports Injury Rehab", duration: "1h", price: "$60.00" },
  ],
  2: [
    { id: 21, name: "Dermatology Consultation", duration: "45m", price: "$75.00" },
    { id: 22, name: "Full Skin Check", duration: "1h 20m", price: "$95.00" },
  ],
  3: [
    { id: 31, name: "Orthopedics Follow-up", duration: "1h", price: "$120.00" },
  ],
  4: [
    { id: 41, name: "Nutrition Advice", duration: "30m", price: "$45.00" },
  ],
};

// Agents keyed by service id (dummy fallback list per service).
export const bookNewAgents = [
  { id: 101, name: "Indigo Violet", role: "Senior Physiotherapist" },
  { id: 102, name: "Samuel Serif", role: "Dermatologist" },
  { id: 103, name: "Miles Tone", role: "Orthopedic Specialist" },
];

// --- Payment History mock data (B5) — mirrors the mockup's Payments section. ---
export const paymentStats = [
  { label: "Total Paid", value: "$524.00" },
  { label: "Outstanding", value: "$95.20", color: "var(--red-text)" },
  { label: "Refunded", value: "$54.00", color: "var(--teal-text)" },
];

// status: paid | refunded | failed | pending. `sign` prefixes the amount.
export const paymentTransactions = [
  {
    id: "t1",
    status: "paid",
    title: "Physiotherapy Session",
    meta: "Credit Card ending 4242 · April 28, 2025",
    amount: "$54.00",
    sign: "+",
    reference: "RT7282GVVA",
  },
  {
    id: "t2",
    status: "paid",
    title: "Nutrition Advice",
    meta: "Credit Card ending 4242 · April 15, 2025",
    amount: "$45.00",
    sign: "+",
    reference: "RT7282GVVB",
  },
  {
    id: "t3",
    status: "refunded",
    title: "Pathology Test — refunded",
    meta: "Refund to Credit Card · April 03, 2025",
    amount: "$54.00",
    sign: "-",
    reference: "RT7282GVVC",
  },
  {
    id: "t4",
    status: "paid",
    title: "Skin Checks",
    meta: "Cash · March 29, 2025",
    amount: "$95.00",
    sign: "+",
    reference: "CASH-3829",
  },
  {
    id: "t5",
    status: "pending",
    title: "Dermatology Consultation — Pending",
    meta: "Awaiting payment · Due May 18, 2025",
    amount: "$75.00",
    sign: "",
    payable: true,
  },
];

// --- Profile mock data (B6) — mirrors the mockup's Profile section. ---
export const profileDefaults = {
  firstName: "Robert",
  lastName: "Fox",
  email: "simmons@example.com",
  phone: "+44 762 17 22 71",
  dob: "1988-06-12",
  gender: "Male",
};

export const genderOptions = ["Male", "Female", "Prefer not to say"];

export const profileStats = [
  { value: "12", label: "Appointments" },
  { value: "$524", label: "Spent" },
];

