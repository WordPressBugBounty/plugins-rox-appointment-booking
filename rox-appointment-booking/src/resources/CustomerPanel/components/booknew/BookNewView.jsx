import React, { useEffect, useMemo, useState } from "react";
import Button from "../ui/Button.jsx";
import Stepper from "../ui/Stepper.jsx";
import MiniCalendar from "../ui/MiniCalendar.jsx";
import SlotPicker from "../ui/SlotPicker.jsx";
import ChoiceCard from "./ChoiceCard.jsx";
import { useToast } from "../../context/ToastContext.jsx";
import { apiGet, apiPost } from "../../data/api.js";
import { daySlotsFor, isDateOff } from "../../data/schedule.js";
import BookNewBootSkeleton, {
  ChoiceListSkeleton,
} from "../skeletons/BookNewSkeleton.jsx";

// Book New view (D4) — real booking cascade for a logged-in customer, mirroring
// the public booking panel: (Location →) Category → Service → Agent → Date & Time
// → Confirm. Each level is fetched from the public endpoints; the location step
// only appears when booking-panel-structure reports it (Pro + module + ≥1
// location). Confirm submits POST /public/booking (customer books as self, pay
// later). Identity + amount are resolved server-verified fields, never spoofed.
const EMPTY = {
  location: null,
  category: null,
  service: null,
  agent: null,
  day: null,
  slot: null,
  slotLabel: null,
};

function today() {
  return new Date().toISOString().slice(0, 10);
}

// Service price with its currency symbol (both come from public/service).
function priceLabel(svc) {
  if (!svc) return "—";
  return `${svc.currency_symbol || ""}${svc.price}`;
}

// Find an option by id in a public-endpoint list (ids come back as strings).
function byId(list, id) {
  return (list || []).find((item) => Number(item.id) === Number(id)) || null;
}

export default function BookNewView({ onNavigate, prefill = null }) {
  const showToast = useToast();

  // Bootstrap: resolve identity + whether the Location step applies.
  const [boot, setBoot] = useState({ loading: true, error: null });
  const [customer, setCustomer] = useState(null);
  const [locationEnabled, setLocationEnabled] = useState(false);

  const [step, setStep] = useState(0);
  const [sel, setSel] = useState(EMPTY);
  const [submitting, setSubmitting] = useState(false);

  // Options per cascade level + their loading flags.
  const [locations, setLocations] = useState([]);
  const [categories, setCategories] = useState([]);
  const [services, setServices] = useState([]);
  const [agents, setAgents] = useState([]);
  // Schedule (weekly timeslots + day-offs + holidays + special days + booked
  // slots) — the single source for both the calendar's disabled days and the
  // per-day time-slot list (mirrors the public panel's Calendar.jsx).
  const [schedule, setSchedule] = useState(null);
  const [scheduleLoading, setScheduleLoading] = useState(false);
  const [optLoading, setOptLoading] = useState(false);

  // Agent-optional (Pro) services skip the Agent step entirely — they book
  // against service capacity with no agent. The step is dropped from the cascade
  // the moment such a service is chosen; because Agent always sits directly after
  // Service, the next() index still lands correctly (Agent's slot becomes Date).
  const agentless = !!(sel.service && sel.service.allow_without_agent);

  const steps = useMemo(() => {
    const base = [
      { key: "category", label: "Category" },
      { key: "service", label: "Service" },
      ...(agentless ? [] : [{ key: "agent", label: "Agent" }]),
      { key: "datetime", label: "Date & Time" },
      { key: "confirm", label: "Confirm" },
    ];
    return locationEnabled
      ? [{ key: "location", label: "Location" }, ...base]
      : base;
  }, [locationEnabled, agentless]);

  const currentKey = steps[step] ? steps[step].key : null;

  // Resolve who is booking + whether the location step applies, once.
  useEffect(() => {
    let active = true;
    (async () => {
      try {
        const [me, struct] = await Promise.all([
          apiGet("public/customer/me"),
          apiGet("booking-panel-structure"),
        ]);
        if (!active) return;
        setCustomer((me && me.data) || null);
        setLocationEnabled(!!(struct && struct.data && struct.data.location));
        setBoot({ loading: false, error: null });
      } catch (e) {
        if (active) {
          setBoot({
            loading: false,
            error: "We couldn't start a new booking. Please try again.",
          });
        }
      }
    })();
    return () => {
      active = false;
    };
  }, []);

  // "Book Again": resolve the previous booking's ids into real selections and
  // jump to Date & Time, so only a new slot has to be picked. Runs once, after
  // bootstrap (it needs to know whether the Location step applies).
  const [prefilling, setPrefilling] = useState(!!prefill);

  useEffect(() => {
    if (!prefill || boot.loading || boot.error) return undefined;
    let active = true;

    (async () => {
      try {
        // The lists are paginated, so ask for a page big enough to contain the
        // booked service/category/location. Reading a single service via
        // `?id=` isn't usable here: that detailed shape returns
        // allow_without_agent as an array, which is always truthy in JS.
        const [svcRes, catRes, locRes, agentRes] = await Promise.all([
          apiGet("public/service", {
            per_page: 100,
            ...(prefill.categoryId ? { cat_id: prefill.categoryId } : {}),
            ...(prefill.locationId && locationEnabled
              ? { location_id: prefill.locationId }
              : {}),
          }),
          prefill.categoryId ? apiGet("public/category", { per_page: 100 }) : null,
          prefill.locationId && locationEnabled
            ? apiGet("public/location", { per_page: 100 })
            : null,
          prefill.agentId
            ? apiGet("public/agent", { mode: "list", service_id: prefill.serviceId })
            : null,
        ]);
        if (!active) return;

        const svc = byId(svcRes && svcRes.data, prefill.serviceId);
        if (!svc) {
          showToast(
            "error",
            "Couldn't pre-fill",
            "That service is no longer available. Please pick one below."
          );
          setPrefilling(false);
          return;
        }

        const cat = byId(catRes && catRes.data, prefill.categoryId);
        const loc = byId(locRes && locRes.data, prefill.locationId);
        const agent = byId(agentRes && agentRes.data, prefill.agentId);
        const noAgentNeeded = !!svc.allow_without_agent;

        setSel({
          ...EMPTY,
          location: loc ? { id: loc.id, name: loc.name } : null,
          category: cat ? { id: cat.id, name: cat.name } : null,
          service: {
            id: svc.id,
            name: svc.name,
            price: svc.price,
            duration: svc.duration,
            currency_symbol: svc.currency_symbol,
            allow_without_agent: svc.allow_without_agent,
          },
          agent: agent ? { id: agent.id, name: agent.name } : null,
        });

        // steps = [location?] category, service, [agent?], datetime, confirm.
        const offset = locationEnabled ? 1 : 0;
        // Land on Agent when the service still needs one but the old provider
        // is gone; otherwise straight on Date & Time.
        setStep(
          !noAgentNeeded && !agent ? offset + 2 : offset + 2 + (noAgentNeeded ? 0 : 1)
        );
        setPrefilling(false);
      } catch (e) {
        if (active) setPrefilling(false);
      }
    })();

    return () => {
      active = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [prefill, boot.loading, boot.error, locationEnabled]);

  // Fetch the options for whichever choice step is active. Runs on entry (and
  // after an upstream choice advances into a new step, so the query reflects the
  // fresh selection). Slots have their own effect below.
  useEffect(() => {
    if (boot.loading || prefilling) return undefined;
    let active = true;

    const load = (path, params, setter) => {
      setOptLoading(true);
      apiGet(path, params)
        .then((r) => active && setter((r && r.data) || []))
        .catch(() => active && setter([]))
        .finally(() => active && setOptLoading(false));
    };

    if (currentKey === "location") {
      load("public/location", undefined, setLocations);
    } else if (currentKey === "category") {
      load("public/category", undefined, setCategories);
    } else if (currentKey === "service") {
      load(
        "public/service",
        {
          ...(sel.category ? { cat_id: sel.category.id } : {}),
          ...(sel.location ? { location_id: sel.location.id } : {}),
        },
        setServices
      );
    } else if (currentKey === "agent") {
      load(
        "public/agent",
        { mode: "list", ...(sel.service ? { service_id: sel.service.id } : {}) },
        setAgents
      );
    }

    return () => {
      active = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [currentKey, boot.loading, prefilling]);

  // Load the service (+agent) schedule once the Date & Time step is reached, so
  // the calendar can disable days off / holidays (mirrors the public panel's
  // Calendar.jsx isDayOff rules).
  useEffect(() => {
    if (currentKey !== "datetime" || !sel.service) return undefined;
    let active = true;
    setSchedule(null);
    setScheduleLoading(true);
    apiGet("public/appointment-schedule", {
      service_id: sel.service.id,
      ...(sel.agent ? { agent_id: sel.agent.id } : {}),
    })
      .then((r) => active && setSchedule((r && r.data) || null))
      .catch(() => active && setSchedule(null))
      .finally(() => active && setScheduleLoading(false));
    return () => {
      active = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [currentKey, sel.service && sel.service.id, sel.agent && sel.agent.id]);

  // Whether a calendar date is unbookable (holiday > special day off > weekly
  // day_off) — shared with Reschedule via data/schedule.js.
  const isDateDisabled = (dateStr) => isDateOff(schedule, dateStr);

  // Slots for the picked day, derived from the schedule (booked ones disabled).
  const daySlots = useMemo(
    () => daySlotsFor(schedule, sel.day),
    [schedule, sel.day]
  );

  const next = () => setStep((s) => Math.min(s + 1, steps.length - 1));
  const back = () => {
    if (step === 0) {
      onNavigate("bookings");
      return;
    }
    setStep((s) => s - 1);
  };

  // Pick a value and advance. Selecting a level resets everything downstream so
  // a changed branch can't keep a stale service/agent/slot.
  const choose = (patch, resets = {}) => {
    setSel((prev) => ({ ...prev, ...patch, ...resets }));
    next();
  };

  const selectSlot = (value) => {
    const found = daySlots.find((s) => s.value === value);
    setSel((p) => ({ ...p, slot: value, slotLabel: found ? found.label : value }));
  };

  const stepperSteps = steps.map((def, i) => ({
    label: def.label,
    state: i < step ? "done" : i === step ? "active" : "",
  }));

  const heading = (title, sub) => (
    <>
      <h3 className="booknew-heading">{title}</h3>
      <p className="booknew-sub">{sub}</p>
    </>
  );

  const emptyLine = (text) => (
    <div style={{ fontSize: "13px", color: "var(--text-muted)", padding: "8px 0" }}>
      {text}
    </div>
  );

  const renderStep = () => {
    switch (currentKey) {
      case "location":
        return (
          <>
            {heading("Choose a location", "Where would you like to be seen?")}
            {optLoading
              ? (
                <ChoiceListSkeleton />
              )
              :locations.length
              ? (
                <div className="choice-list">
                  {locations.map((loc) => (
                    <ChoiceCard
                      key={loc.id}
                      icon="📍"
                      title={loc.name}
                      selected={sel.location && sel.location.id === loc.id}
                      onClick={() =>
                        choose(
                          { location: { id: loc.id, name: loc.name } },
                          { category: null, service: null, agent: null, day: null, slot: null, slotLabel: null }
                        )
                      }
                    />
                  ))}
                </div>
              )
              : emptyLine("No locations are available right now.")}
          </>
        );

      case "category":
        return (
          <>
            {heading("Choose a category", "What kind of service do you need?")}
            {optLoading
              ? (
                <ChoiceListSkeleton />
              )
              :categories.length
              ? (
                <div className="choice-list">
                  {categories.map((cat) => (
                    <ChoiceCard
                      key={cat.id}
                      icon="📋"
                      title={cat.name}
                      subtitle={cat.description || undefined}
                      meta={
                        cat.services_count != null
                          ? `${cat.services_count} service${
                              Number(cat.services_count) === 1 ? "" : "s"
                            }`
                          : undefined
                      }
                      selected={sel.category && sel.category.id === cat.id}
                      onClick={() =>
                        choose(
                          { category: { id: cat.id, name: cat.name } },
                          { service: null, agent: null, day: null, slot: null, slotLabel: null }
                        )
                      }
                    />
                  ))}
                </div>
              )
              : emptyLine("No categories are available right now.")}
          </>
        );

      case "service":
        return (
          <>
            {heading(
              "Choose a service",
              sel.category ? `${sel.category.name} services` : "Available services"
            )}
            {optLoading
              ? (
                <ChoiceListSkeleton />
              )
              :services.length
              ? (
                <div className="choice-list">
                  {services.map((svc) => (
                    <ChoiceCard
                      key={svc.id}
                      icon="🩺"
                      title={svc.name}
                      subtitle={svc.duration ? `${svc.duration} min` : undefined}
                      meta={priceLabel(svc)}
                      selected={sel.service && sel.service.id === svc.id}
                      onClick={() =>
                        choose(
                          {
                            service: {
                              id: svc.id,
                              name: svc.name,
                              price: svc.price,
                              duration: svc.duration,
                              currency_symbol: svc.currency_symbol,
                              allow_without_agent: svc.allow_without_agent,
                            },
                          },
                          { agent: null, day: null, slot: null, slotLabel: null }
                        )
                      }
                    />
                  ))}
                </div>
              )
              : emptyLine("No services are available for this selection.")}
          </>
        );

      case "agent":
        return (
          <>
            {heading("Choose a provider", "Pick who you'd like to see.")}
            {optLoading
              ? (
                <ChoiceListSkeleton />
              )
              :agents.length
              ? (
                <div className="choice-list">
                  {agents.map((agent) => (
                    <ChoiceCard
                      key={agent.id}
                      icon="👤"
                      title={agent.name}
                      selected={sel.agent && sel.agent.id === agent.id}
                      onClick={() =>
                        choose(
                          { agent: { id: agent.id, name: agent.name } },
                          { day: null, slot: null, slotLabel: null }
                        )
                      }
                    />
                  ))}
                </div>
              )
              : emptyLine("No providers are available for this service.")}
          </>
        );

      case "datetime":
        return (
          <>
            {heading("Pick a date & time", "Choose an available slot.")}
            <div className="booknew-dt">
              <div className="booknew-dt-cal">
                <MiniCalendar
                  value={sel.day}
                  onChange={(day) =>
                    setSel((p) => ({ ...p, day, slot: null, slotLabel: null }))
                  }
                  minDate={today()}
                  isDisabled={isDateDisabled}
                />
              </div>
              <div className="booknew-dt-slots">
                <h3 style={{ margin: "0 0 12px", fontSize: "14px" }}>
                  Available time slots
                </h3>
                {scheduleLoading ? (
                  emptyLine("Loading availability…")
                ) : !sel.day ? (
                  emptyLine("Pick a date to see available slots.")
                ) : daySlots.length ? (
                  <SlotPicker
                    slots={daySlots}
                    selected={sel.slot}
                    onSelect={selectSlot}
                  />
                ) : (
                  emptyLine("No available slots on this date. Try another date.")
                )}
              </div>
            </div>
            <div className="booknew-foot">
              <Button variant="secondary" onClick={back}>
                Back
              </Button>
              <Button
                variant="primary"
                disabled={!sel.day || !sel.slot}
                onClick={next}
              >
                Continue
              </Button>
            </div>
          </>
        );

      case "confirm": {
        const rows = [
          ["Location", sel.location && sel.location.name],
          ["Category", sel.category && sel.category.name],
          ["Service", sel.service && sel.service.name],
          ["Provider", sel.agent && sel.agent.name],
          ["Date", sel.day],
          ["Time", sel.slotLabel || sel.slot],
        ];
        return (
          <>
            {heading("Review & confirm", "Confirm your appointment details.")}
            <div className="booknew-summary">
              {rows.map(([label, value]) => (
                <div className="detail-row" key={label}>
                  <span className="label">{label}</span>
                  <span className="value">{value || "—"}</span>
                </div>
              ))}
              <div className="detail-row">
                <span className="label">Total</span>
                <span
                  className="value"
                  style={{ fontWeight: 700, color: "var(--primary)" }}
                >
                  {priceLabel(sel.service)}
                </span>
              </div>
            </div>
            <p className="booknew-sub" style={{ marginBottom: "18px" }}>
              💳 You'll receive a secure payment link by email after confirming.
            </p>
            <div className="booknew-foot">
              <Button variant="secondary" onClick={back} disabled={submitting}>
                Back
              </Button>
              <Button variant="primary" onClick={confirm} disabled={submitting}>
                {submitting ? "Confirming…" : "Confirm Booking"}
              </Button>
            </div>
          </>
        );
      }

      default:
        return null;
    }
  };

  const confirm = async () => {
    if (!customer || !customer.email) {
      showToast(
        "error",
        "Booking failed",
        "We couldn't verify your account details. Please refresh and try again."
      );
      return;
    }
    setSubmitting(true);
    const price = Number(sel.service && sel.service.price) || 0;
    const payload = {
      first_name: customer.first_name,
      last_name: customer.last_name,
      email: customer.email,
      phone: customer.phone,
      payment_type: "later",
      amount: price,
      original_amount: price,
      appointments: [
        {
          service_id: sel.service.id,
          agent_id: sel.agent ? sel.agent.id : null,
          category_id: sel.category ? sel.category.id : null,
          location_id: sel.location ? sel.location.id : null,
          date: sel.day,
          start_time: sel.slot,
          extra_service_ids: [],
          total_attendees: 1,
        },
      ],
    };

    try {
      await apiPost("public/booking", payload);
      showToast(
        "success",
        "Booking confirmed",
        `${sel.service.name}${sel.agent ? ` with ${sel.agent.name}` : ""} on ${
          sel.day
        } at ${sel.slotLabel || sel.slot}.`
      );
      setSel(EMPTY);
      setStep(0);
      onNavigate("bookings");
    } catch (e) {
      showToast(
        "error",
        "Booking failed",
        (e && e.message) ||
          "Something went wrong while booking. Please try again."
      );
    } finally {
      setSubmitting(false);
    }
  };

  // Selection steps carry a standalone Back control; datetime/confirm render
  // their own foot with a primary action.
  const isChoiceStep = ["location", "category", "service", "agent"].includes(
    currentKey
  );

  return (
    <div className="booknew-wrap">
      <h1 className="page-title">Book a New Appointment</h1>
      <p className="page-sub">Follow the steps to schedule your appointment.</p>

      <div className="card">
        {boot.loading || prefilling ? (
          <BookNewBootSkeleton />
        ) : boot.error ? (
          <div className="booknew-body">
            {emptyLine(boot.error)}
            <div className="booknew-foot">
              <Button variant="secondary" onClick={() => onNavigate("bookings")}>
                Back to My Bookings
              </Button>
            </div>
          </div>
        ) : (
          <>
            <Stepper steps={stepperSteps} />
            <div className="booknew-body">
              {renderStep()}
              {isChoiceStep && (
                <div className="booknew-foot">
                  <Button variant="secondary" onClick={back}>
                    Back
                  </Button>
                </div>
              )}
            </div>
          </>
        )}
      </div>
    </div>
  );
}
