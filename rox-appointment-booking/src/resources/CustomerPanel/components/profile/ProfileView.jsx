import React, { useEffect, useRef, useState } from "react";
import Button from "../ui/Button.jsx";
import Field from "../ui/Field.jsx";
import { useToast } from "../../context/ToastContext.jsx";
import { apiGet, apiPost, apiUpload } from "../../data/api.js";
import { genderOptions } from "../../data/mockData.js";
import ProfileSkeleton from "../skeletons/ProfileSkeleton.jsx";

// Gender round-trips as a lowercase value in the DB (matches the admin form) but
// shows a capitalized label; convert at the API boundary so the <select> and the
// stored record stay in sync.
const GENDER_TO_VALUE = {
  Male: "male",
  Female: "female",
  "Prefer not to say": "prefer_not_to_say",
};
const GENDER_TO_LABEL = {
  male: "Male",
  female: "Female",
  prefer_not_to_say: "Prefer not to say",
};

// Two-letter initials from a name (matches the header avatar).
function initials(name) {
  if (!name) return "";
  return name
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((w) => (w[0] ? w[0].toUpperCase() : ""))
    .join("");
}

// Communication-preference toggle row.
function PrefToggle({ pref, checked, onChange }) {
  return (
    <label className="pref-toggle">
      <div>
        <div className="pref-toggle-title">{pref.title}</div>
        <div className="pref-toggle-desc">{pref.desc}</div>
      </div>
      <input type="checkbox" checked={checked} onChange={onChange} />
    </label>
  );
}

const EMPTY_FORM = {
  firstName: "",
  lastName: "",
  email: "",
  phone: "",
  dob: "",
  gender: "",
};
const EMPTY_PREFS = { email: true, sms: false, marketing: false };

// Map the fetched profile payload into the form + prefs the UI edits.
function toForm(profile) {
  return {
    firstName: profile.firstName || "",
    lastName: profile.lastName || "",
    email: profile.email || "",
    phone: profile.phone || "",
    dob: profile.dob || "",
    gender: GENDER_TO_LABEL[profile.gender] || "",
  };
}

// Profile view (D3): sidebar (avatar/stats/change photo) + personal-info form +
// communication preferences + Save/Cancel. Data is the logged-in customer's real
// profile, read from and saved to GET/POST /customer-panel/profile.
export default function ProfileView({ currentUser = {} }) {
  const showToast = useToast();

  const [profile, setProfile] = useState(null); // last-loaded server payload
  const [form, setForm] = useState(EMPTY_FORM);
  const [prefs, setPrefs] = useState(EMPTY_PREFS);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);
  const [saving, setSaving] = useState(false);
  // Gravatar (or any stored photo URL) can fail to load; fall back to initials.
  const [avatarBroken, setAvatarBroken] = useState(false);
  const [uploading, setUploading] = useState(false);
  const fileRef = useRef(null);

  // Seed the form + prefs from a server profile payload.
  const applyProfile = (p) => {
    setProfile(p);
    setForm(toForm(p));
    setPrefs({ ...EMPTY_PREFS, ...(p.prefs || {}) });
  };

  useEffect(() => {
    let active = true;
    setLoading(true);
    setError(false);
    apiGet("customer-panel/profile")
      .then((res) => {
        if (active && res && res.data) applyProfile(res.data);
      })
      .catch(() => {
        if (active) setError(true);
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => {
      active = false;
    };
  }, []);

  const set = (key) => (e) => setForm((f) => ({ ...f, [key]: e.target.value }));
  const togglePref = (id) => () =>
    setPrefs((p) => ({ ...p, [id]: !p[id] }));

  const handleSave = async () => {
    setSaving(true);
    try {
      const res = await apiPost("customer-panel/profile", {
        firstName: form.firstName,
        lastName: form.lastName,
        phone: form.phone,
        dob: form.dob,
        gender: GENDER_TO_VALUE[form.gender] || "",
        prefs,
      });
      if (res && res.data) applyProfile(res.data);
      showToast("success", "Profile updated", "Your changes have been saved.");
    } catch (e) {
      showToast(
        "error",
        "Couldn't save profile",
        (e && e.message) || "Please try again."
      );
    } finally {
      setSaving(false);
    }
  };

  // Upload the picked image, then swap in the profile the server returns (its
  // avatar now points at the new attachment).
  const handlePhotoChange = async (e) => {
    const file = e.target.files && e.target.files[0];
    // Clear the input so picking the same file again still fires onChange.
    e.target.value = "";
    if (!file) return;

    setUploading(true);
    try {
      const body = new FormData();
      body.append("photo", file);
      const res = await apiUpload("customer-panel/profile/photo", body);
      if (res && res.data) {
        setAvatarBroken(false);
        applyProfile(res.data);
      }
      showToast("success", "Photo updated", "Your profile photo has been saved.");
    } catch (err) {
      showToast(
        "error",
        "Couldn't upload photo",
        (err && err.message) || "Please try again."
      );
    } finally {
      setUploading(false);
    }
  };

  // Reset to the last-loaded server profile.
  const handleCancel = () => {
    if (profile) applyProfile(profile);
  };

  if (loading) {
    return <ProfileSkeleton />;
  }

  if (error) {
    return (
      <div className="empty-state">
        <div className="empty-icon">⚠️</div>
        <div className="empty-title">Couldn't load your profile</div>
        <div className="empty-msg">Please refresh the page and try again.</div>
      </div>
    );
  }

  const fullName = `${form.firstName} ${form.lastName}`.trim();
  const avatarSrc = (profile && profile.avatar) || currentUser.src || "";
  const stats = (profile && profile.stats) || [];
  // Show a blank leading option when no gender is set, so an unset field doesn't
  // masquerade as the first real option.
  const genderFieldOptions = form.gender ? genderOptions : ["", ...genderOptions];

  return (
    <>
      <h1 className="page-title">My Profile</h1>
      <p className="page-sub">
        Manage your personal info, contact details.
      </p>

      <div className="profile-grid">
        <aside className="profile-side">
          <div className="profile-avatar">
            {avatarSrc && !avatarBroken ? (
              <img
                src={avatarSrc}
                alt={fullName}
                onError={() => setAvatarBroken(true)}
              />
            ) : (
              initials(fullName)
            )}
          </div>
          <div className="profile-name">{fullName}</div>
          <div className="profile-email">{form.email}</div>
          <input
            ref={fileRef}
            type="file"
            accept="image/jpeg,image/png,image/webp"
            style={{ display: "none" }}
            onChange={handlePhotoChange}
          />
          <Button
            variant="secondary"
            size="sm"
            style={{ width: "100%" }}
            onClick={() => fileRef.current && fileRef.current.click()}
            disabled={uploading}
          >
            {uploading ? "Uploading…" : "Change Photo"}
          </Button>

          <div className="profile-stats">
            {stats.map((stat) => (
              <div key={stat.label}>
                <div className="profile-stat-value">{stat.value}</div>
                <div className="profile-stat-label">{stat.label}</div>
              </div>
            ))}
          </div>
        </aside>

        <div className="card" style={{ padding: "24px" }}>
          <h2 className="profile-form-title">Personal Information</h2>

          <div className="field-grid">
            <Field label="First Name" value={form.firstName} onChange={set("firstName")} />
            <Field label="Last Name" value={form.lastName} onChange={set("lastName")} />
          </div>
          <Field label="Email" type="email" value={form.email} disabled />
          <Field label="Phone" value={form.phone} onChange={set("phone")} />
          <div className="field-grid">
            <Field label="Date of Birth" type="date" value={form.dob} onChange={set("dob")} />
            <Field
              label="Gender"
              type="select"
              value={form.gender}
              onChange={set("gender")}
              options={genderFieldOptions}
            />
          </div>
          <div className="profile-actions">
            <Button variant="secondary" onClick={handleCancel} disabled={saving}>
              Cancel
            </Button>
            <Button variant="primary" onClick={handleSave} disabled={saving}>
              {saving ? "Saving…" : "Save Changes"}
            </Button>
          </div>
        </div>
      </div>
    </>
  );
}
