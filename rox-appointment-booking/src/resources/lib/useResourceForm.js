/**
 * Shared load + submit logic for the bespoke real-component forms.
 *
 * A bespoke form (`pages/<entity>/<Entity>Form.jsx`) only renders JSX fields;
 * the host wires it to its page-local API through this hook. The hook knows
 * nothing about individual fields — it only:
 *   - loads existing data when an `id` is present (edit mode), and
 *   - submits values through the page's `save` fn, showing the success/error toast.
 *
 * `get`/`save` are the page-local API functions
 * (`pages/<entity>/<entity>Api.js`); they ultimately call `lib/request.js`.
 */

import { useEffect, useState, useCallback } from "react";
import { message } from "antd";

/**
 * @param {object}   options
 * @param {Function} [options.get]  `get(id)` → Promise<record>; only called in edit mode.
 * @param {Function} options.save   `save(values, id)` → Promise<record>; create when `id` is falsy.
 * @param {number}   [options.id]   Record id for edit mode; falsy for create.
 * @param {string}   [options.successMessage] Toast shown on a successful submit.
 * @return {{ initialValues: object, loading: boolean, submitting: boolean, submit: Function }}
 */
export function useResourceForm({
  get,
  save,
  id,
  successMessage = "Saved successfully",
}) {
  const [initialValues, setInitialValues] = useState(null);
  const [loading, setLoading] = useState(Boolean(id));
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    let active = true;
    if (!id || !get) {
      setInitialValues(null);
      setLoading(false);
      return undefined;
    }
    setLoading(true);
    get(id)
      .then((record) => {
        if (active) {
          setInitialValues(record);
        }
      })
      .catch((error) => {
        if (active) {
          message.error(error.message || "Failed to load data");
        }
      })
      .finally(() => {
        if (active) {
          setLoading(false);
        }
      });
    return () => {
      active = false;
    };
  }, [id, get]);

  const submit = useCallback(
    async (values) => {
      setSubmitting(true);
      try {
        const record = await save(values, id);
        message.success(successMessage);
        return record;
      } catch (error) {
        message.error(error.message || "Failed to save");
        throw error;
      } finally {
        setSubmitting(false);
      }
    },
    [save, id, successMessage],
  );

  return { initialValues, loading, submitting, submit };
}
