import React from "react";

/**
 * Bespoke agent list step. Reuses `employees-grid`/`employee-card` CSS for a
 * consistent look; own component.
 */
const AgentsStep = ({ agents = [], selectedId, onSelect, loading = false }) => (
  <div className="main-content-container">
    <span className="main-page-header-title">Select Agent</span>

    {loading ? (
      <div className="rab-sl-loading">Loading agents…</div>
    ) : agents.length > 0 ? (
      <div className="employees-grid">
        {agents.map((agent) => (
          <div
            key={agent.id}
            className={`base-card employee-card ${
              selectedId === agent.id ? "selected" : ""
            }`}
            onClick={() => onSelect(agent)}
          >
            <div className="employee-avatar">
              {agent.thumbnail ? (
                <img src={agent.thumbnail} alt={agent.name} />
              ) : null}
            </div>
            <div className="employee-info">
              <div className="employee-name">{agent.name}</div>
            </div>
          </div>
        ))}
      </div>
    ) : (
      <div className="no-services">
        <p>No agent is available for this service.</p>
      </div>
    )}
  </div>
);

export default AgentsStep;
