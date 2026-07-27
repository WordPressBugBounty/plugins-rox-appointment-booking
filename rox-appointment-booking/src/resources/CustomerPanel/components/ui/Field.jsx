import React from "react";

// Labeled form field (mockup `.field`). type "select" renders a <select> from
// `options` (array of strings); anything else renders an <input> of that type.
export default function Field({
  label,
  type = "text",
  value,
  onChange,
  options = [],
  ...props
}) {
  return (
    <div className="field">
      <label>{label}</label>
      {type === "select" ? (
        <select value={value} onChange={onChange} {...props}>
          {options.map((opt) => (
            <option key={opt} value={opt}>
              {opt}
            </option>
          ))}
        </select>
      ) : (
        <input type={type} value={value} onChange={onChange} {...props} />
      )}
    </div>
  );
}
