import { Navigate, Outlet } from "react-router-dom";
import { useAuth } from "@/context/AuthContext";

export default function ProtectedRoute({ admin = false, allowPending = false }) {
  const { user, loading } = useAuth();
  if (loading) return <div className="flex min-h-screen items-center justify-center text-sm text-slate-500">Carregando...</div>;
  if (!user) return <Navigate to="/login" replace />;
  const approved = user.role === "admin" || (user.status || "aprovado") === "aprovado";
  if (!approved && !allowPending) return <Navigate to="/aguardando" replace />;
  if (approved && allowPending) return <Navigate to="/" replace />;
  if (admin && user.role !== "admin") return <Navigate to="/" replace />;
  return <Outlet />;
}
