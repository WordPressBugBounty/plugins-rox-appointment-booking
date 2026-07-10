import React, { useEffect, useState } from "react";

/**
 * Bespoke customer-info step (own form, local state — does NOT use the shared
 * redux slice like the shortcode's CustomerInfo). Reuses form CSS classes.
 */
const InfoStep = ({ value, onChange }) => {
  const [form, setForm] = useState(
    value || { first_name: "", last_name: "", email: "", phone: "", notes: "" },
  );

  useEffect(() => {
    const isValid = Boolean(
      form.first_name && form.last_name && form.email && form.phone,
    );
    onChange(form, isValid);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [form]);

  const set = (e) => {
    const { name, value: v } = e.target;
    setForm((prev) => ({ ...prev, [name]: v }));
  };

  const req = <span style={{ color: "#EF7471" }}> *</span>;

  return (
    <div className="main-content-container">
      <span className="main-page-header-title">Customer Information</span>
      <div className="form-wrapper">
        <div className="form-fields">
          <div className="form-group">
            <label htmlFor="rab-sl-first">First Name{req}</label>
            <input
              id="rab-sl-first"
              type="text"
              name="first_name"
              value={form.first_name}
              onChange={set}
              placeholder="Enter first name"
              required
            />
          </div>
          <div className="form-group">
            <label htmlFor="rab-sl-last">Last Name{req}</label>
            <input
              id="rab-sl-last"
              type="text"
              name="last_name"
              value={form.last_name}
              onChange={set}
              placeholder="Enter last name"
              required
            />
          </div>
          <div className="form-group">
            <label htmlFor="rab-sl-email">Email{req}</label>
            <input
              id="rab-sl-email"
              type="email"
              name="email"
              value={form.email}
              onChange={set}
              placeholder="Enter your email"
              required
            />
          </div>
          <div className="form-group">
            <label htmlFor="rab-sl-phone">Phone Number{req}</label>
            <div className="phone-input">
              <input
                id="rab-sl-phone"
                type="tel"
                name="phone"
                value={form.phone}
                onChange={set}
                placeholder="Enter your phone number"
                required
              />
            </div>
          </div>
          <div className="form-group">
            <label htmlFor="rab-sl-notes">Comments</label>
            <textarea
              id="rab-sl-notes"
              name="notes"
              value={form.notes}
              onChange={set}
              placeholder="Add comments"
              rows={4}
            />
          </div>
        </div>
      </div>
    </div>
  );
};

export default InfoStep;
