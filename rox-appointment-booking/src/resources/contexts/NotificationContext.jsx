import React, { createContext, useContext, useState, useCallback, useRef } from "react";
import apiFetch from "@wordpress/api-fetch";
import { NOTIFICATIONS_REFRESH_EVENT } from "../lib/notificationEvents.js";

const NotificationContext = createContext(null);

export const NotificationProvider = ({ children, notificationApiUrl }) => {
  const [notificationItems, setNotificationItems] = useState([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [loading, setLoading] = useState(false);
  const [notificationActionUrl, setNotificationActionUrl] = useState(null);
  const isMountedRef = useRef(true);

  // Fetch notifications from API
  const fetchNotifications = useCallback(async () => {
    if (!notificationApiUrl) return;

    setLoading(true);
    try {
      const data = await apiFetch({ url: notificationApiUrl });

      if (isMountedRef.current) {
        setNotificationItems(data?.data || []);

        // Store the action URL from API response
        if (data?.data?.action) {
          setNotificationActionUrl(data.data.action);
        }

        // Calculate unread count
        const unread = data?.data?.items?.filter(item => item.status === 0).length || 0;
        setUnreadCount(unread);
      }
    } catch (error) {
      console.error("Failed to fetch notifications:", error);
    } finally {
      if (isMountedRef.current) {
        setLoading(false);
      }
    }
  }, [notificationApiUrl]);

  // Public method to refresh notifications
  const refreshNotifications = useCallback(() => {
    console.log("Refreshing notifications...");
    fetchNotifications();
  }, [fetchNotifications]);

  // Mark notification as read
  const markAsRead = useCallback(async (notificationId) => {
    if (!notificationActionUrl) return false;

    try {
      const response = await apiFetch({
        url: notificationActionUrl,
        method: "POST",
        data: { notification_id: notificationId },
      });

      if (response.success && isMountedRef.current) {
        // Update local state
        setNotificationItems(prevState => ({
          ...prevState,
          items: prevState.items?.map(notification =>
            notification.id === notificationId
              ? { ...notification, status: 1 }
              : notification
          ) || []
        }));

        // Decrement unread count
        setUnreadCount(prevCount => Math.max(0, prevCount - 1));
        return true;
      }
      return false;
    } catch (error) {
      console.error("Failed to mark notification as read:", error);
      return false;
    }
  }, [notificationActionUrl]);

  // Cleanup on unmount
  React.useEffect(() => {
    return () => {
      isMountedRef.current = false;
    };
  }, []);

  // Initial fetch
  React.useEffect(() => {
    fetchNotifications();
  }, [fetchNotifications]);

  // Re-fetch when a mutating REST call signals new notifications may exist
  // (e.g. an appointment status change). The bell otherwise only loads once on
  // mount, so without this it would not surface notifications created after the
  // page loaded.
  React.useEffect(() => {
    window.addEventListener(NOTIFICATIONS_REFRESH_EVENT, fetchNotifications);
    return () => {
      window.removeEventListener(NOTIFICATIONS_REFRESH_EVENT, fetchNotifications);
    };
  }, [fetchNotifications]);

  const value = {
    notificationItems,
    unreadCount,
    loading,
    refreshNotifications,
    markAsRead,
    notificationActionUrl
  };

  return (
    <NotificationContext.Provider value={value}>
      {children}
    </NotificationContext.Provider>
  );
};

// Custom hook to use notification context
export const useNotifications = () => {
  const context = useContext(NotificationContext);
  if (!context) {
    throw new Error("useNotifications must be used within a NotificationProvider");
  }
  return context;
};
