import React, { createContext, useCallback, useContext, useState } from "react";
import { createPortal } from "react-dom";
import Toast from "../components/ui/Toast.jsx";

// Toast state + host. `useToast()` returns `showToast(type, title, msg)`; toasts
// auto-dismiss after ~5.5s (matches the mockup's timing) or on manual close.
const ToastContext = createContext(null);

let idCounter = 0;

export function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([]);

  const dismiss = useCallback((id) => {
    setToasts((list) => list.filter((t) => t.id !== id));
  }, []);

  const showToast = useCallback(
    (type = "success", title = "", msg = "") => {
      const id = ++idCounter;
      setToasts((list) => [...list, { id, type, title, msg }]);
      setTimeout(() => dismiss(id), 5500);
      return id;
    },
    [dismiss]
  );

  return (
    <ToastContext.Provider value={showToast}>
      {children}
      {createPortal(
        <div className="rox-cp">
          <Toast toasts={toasts} onDismiss={dismiss} />
        </div>,
        document.body
      )}
    </ToastContext.Provider>
  );
}

export function useToast() {
  const ctx = useContext(ToastContext);
  return ctx || (() => {});
}
