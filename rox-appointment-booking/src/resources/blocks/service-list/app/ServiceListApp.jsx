import React, { useEffect, useState } from "react";
import { useDispatch } from "@wordpress/data";
import { bookingServiceStore } from "../../../redux/booking-service-slice.js";

import Nav from "./Nav.jsx";
import SummaryPanel from "./SummaryPanel.jsx";
import ServicesStep from "./steps/ServicesStep.jsx";
import AgentsStep from "./steps/AgentsStep.jsx";
import InfoStep from "./steps/InfoStep.jsx";
import CompleteStep from "./steps/CompleteStep.jsx";

// Reused (option C): the heavy schedule + payment steps.
import Appointment from "../../../components/BookingService/Appointment.jsx";
import StripePaymentForm from "../../../components/BookingService/StripePaymentForm.jsx";

import { fetchServices, fetchAgents, fetchStructure, submitBooking } from "./api.js";

const STEP_LABELS = [
  "Services",
  "Agents",
  "Date & Time",
  "Information",
  "Payment",
  "Complete",
];

const toISODate = (d) => {
  const date = d instanceof Date ? d : new Date(d);
  return date.toISOString().split("T")[0];
};

const ServiceListApp = ({ config = {} }) => {
  const { setContent } = useDispatch(bookingServiceStore);

  const [step, setStep] = useState(1);

  const [services, setServices] = useState([]);
  const [servicesLoading, setServicesLoading] = useState(true);
  const [selectedService, setSelectedService] = useState(null);

  const [agents, setAgents] = useState([]);
  const [agentsLoading, setAgentsLoading] = useState(false);
  const [selectedAgent, setSelectedAgent] = useState(null);

  const [date, setDate] = useState(null);
  const [startTime, setStartTime] = useState(null);
  const [endTime, setEndTime] = useState(null);

  const [info, setInfo] = useState(null);
  const [infoValid, setInfoValid] = useState(false);

  const [currency, setCurrency] = useState("usd");
  const [payLater, setPayLater] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [response, setResponse] = useState(null);

  // Mount: load all services + feed the shared store `content` so the reused
  // schedule Calendar (which reads it via useSelect) can fetch slots.
  useEffect(() => {
    let active = true;
    (async () => {
      try {
        const [svc, structure] = await Promise.all([
          fetchServices(),
          fetchStructure(),
        ]);
        if (!active) return;
        setServices(svc);
        if (structure) setContent(structure);
      } catch (e) {
        // eslint-disable-next-line no-console
        console.error("Service List: failed to load", e);
      } finally {
        if (active) setServicesLoading(false);
      }
    })();
    return () => {
      active = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const handleSelectService = async (service) => {
    setSelectedService(service);
    setSelectedAgent(null);
    setDate(null);
    setStartTime(null);
    setEndTime(null);
    setStep(2);
    setAgentsLoading(true);
    try {
      const list = await fetchAgents(service.id);
      setAgents(list);
    } catch (e) {
      setAgents([]);
    } finally {
      setAgentsLoading(false);
    }
  };

  const handleSelectAgent = (agent) => {
    setSelectedAgent(agent);
    setStep(3);
  };

  const handleDateTimeSelect = (d, st, et) => {
    setDate(d);
    setStartTime(st);
    setEndTime(et);
  };

  const buildBooking = () => ({
    id: 1,
    service: selectedService,
    employee: selectedAgent,
    date,
    start_time: startTime,
    end_time: endTime,
    customer: info,
    extraServices: [],
  });

  const calculateGrandTotal = () =>
    Number(selectedService?.price || 0).toFixed(2);

  const handleSubmit = async (paymentType = "later", paymentMethodId = null) => {
    if (submitting) return { success: false, message: "Already submitting" };
    setSubmitting(true);
    try {
      const appointments = [
        {
          service_id: selectedService?.id,
          agent_id: selectedAgent?.id,
          price: Number(selectedService?.price || 0),
          date: toISODate(date),
          start_time: startTime,
          end_time: endTime,
          extra_service_ids: [],
        },
      ];

      const payload = {
        date: toISODate(new Date()),
        amount: parseFloat(calculateGrandTotal()),
        payment_type: paymentType,
        ...(paymentMethodId && { payment_method: paymentMethodId }),
        first_name: info?.first_name,
        last_name: info?.last_name,
        email: info?.email,
        phone: info?.phone,
        currency: (currency || "usd").toLowerCase(),
        appointments,
        meta: {},
      };

      const res = await submitBooking(payload);
      if (res?.success) {
        setResponse(res.data);
        setStep(6);
        return { success: true };
      }
      return { success: false, message: res?.message || "Booking failed" };
    } catch (error) {
      return {
        success: false,
        message: error?.message || "Something went wrong",
      };
    } finally {
      setSubmitting(false);
    }
  };

  const renderStep = () => {
    switch (step) {
      case 1:
        return (
          <ServicesStep
            services={services}
            loading={servicesLoading}
            selectedId={selectedService?.id}
            onSelect={handleSelectService}
            showImage={config.showServiceImage !== false}
            layout={config.serviceLayout === "list" ? "list" : "grid"}
          />
        );
      case 2:
        return (
          <AgentsStep
            agents={agents}
            loading={agentsLoading}
            selectedId={selectedAgent?.id}
            onSelect={handleSelectAgent}
          />
        );
      case 3:
        return (
          <Appointment
            onDateTimeSelect={handleDateTimeSelect}
            selectedDate={date}
            selectedStartTime={startTime}
            selectedEndTime={endTime}
            serviceId={selectedService?.id}
            agentId={selectedAgent?.id}
            bookingProcess={[]}
          />
        );
      case 4:
        return <InfoStep value={info} onChange={(f, v) => { setInfo(f); setInfoValid(v); }} />;
      case 5:
        return (
          <StripePaymentForm
            bookings={[buildBooking()]}
            setPayLater={setPayLater}
            calculateGrandTotal={calculateGrandTotal}
            setCurrency={setCurrency}
            currency={currency}
            onBookingSubmit={handleSubmit}
          />
        );
      case 6:
        return <CompleteStep booking={buildBooking()} response={response} />;
      default:
        return null;
    }
  };

  const navFor = () => {
    const backLabel = config.backLabel;
    const nextLabel = config.nextLabel;
    switch (step) {
      case 1:
        return (
          <Nav
            showBack={false}
            nextLabel={nextLabel}
            nextDisabled={!selectedService}
            onNext={() => selectedService && setStep(2)}
          />
        );
      case 2:
        return (
          <Nav
            backLabel={backLabel}
            nextLabel={nextLabel}
            onBack={() => setStep(1)}
            nextDisabled={!selectedAgent}
            onNext={() => selectedAgent && setStep(3)}
          />
        );
      case 3:
        return (
          <Nav
            backLabel={backLabel}
            nextLabel={nextLabel}
            onBack={() => setStep(2)}
            nextDisabled={!date || !startTime}
            onNext={() => date && startTime && setStep(4)}
          />
        );
      case 4:
        return (
          <Nav
            backLabel={backLabel}
            nextLabel={nextLabel}
            onBack={() => setStep(3)}
            nextDisabled={!infoValid}
            onNext={() => infoValid && setStep(5)}
          />
        );
      case 5:
        return (
          <Nav
            backLabel={backLabel}
            nextLabel="Submit"
            onBack={() => setStep(4)}
            nextDisabled={!payLater || submitting}
            onNext={() => handleSubmit("later")}
          />
        );
      default:
        return null;
    }
  };

  const showStepSidebar = Boolean(config.showStepSidebar);
  const showInfoSidebar = Boolean(config.showInfoSidebar);

  return (
    <div className="service-layout-outer rab-sl-app">
      <div className="service-layout">
        {showStepSidebar ? (
          <div className="service-sidebar step-container">
            <div className="rab-sl-steps">
              {STEP_LABELS.map((label, i) => (
                <div
                  key={label}
                  className={`rab-sl-step ${i + 1 === step ? "active" : ""} ${
                    i + 1 < step ? "done" : ""
                  }`}
                >
                  <span className="rab-sl-step-dot">{i + 1}</span>
                  <span className="rab-sl-step-label">{label}</span>
                </div>
              ))}
            </div>
          </div>
        ) : null}

        <div className="main with-navigation">
          <div className="main-content">{renderStep()}</div>
          {navFor()}
        </div>

        {showInfoSidebar ? (
          <div className="rab-sl-summary">
            <SummaryPanel
              service={selectedService}
              agent={selectedAgent}
              date={date}
              startTime={startTime}
              total={calculateGrandTotal()}
              currencySymbol="$"
            />
          </div>
        ) : null}
      </div>
    </div>
  );
};

export default ServiceListApp;
