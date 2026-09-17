import { createContext, useCallback, useContext, useEffect, useState } from "react";
import { api } from "@/lib/api";

const AuthContext = createContext(null);

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(() => {
    try { return JSON.parse(localStorage.getItem("innova_user")); } catch { return null; }
  });
  const [loading, setLoading] = useState(Boolean(localStorage.getItem("innova_access_token")));

  const saveSession = useCallback((data) => {
    localStorage.setItem("innova_access_token", data.access_token);
    localStorage.setItem("innova_user", JSON.stringify(data.user));
    setUser(data.user);
  }, []);

  const logout = useCallback(() => {
    localStorage.removeItem("innova_access_token");
    localStorage.removeItem("innova_user");
    setUser(null);
  }, []);

  useEffect(() => {
    const token = localStorage.getItem("innova_access_token");
    if (!token) { setLoading(false); return; }
    api.get("/auth/me")
      .then(({ data }) => {
        localStorage.setItem("innova_user", JSON.stringify(data.user));
        setUser(data.user);
      })
      .catch(logout)
      .finally(() => setLoading(false));
  }, [logout]);

  return (
    <AuthContext.Provider value={{ user, loading, saveSession, logout }}>
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => useContext(AuthContext);
