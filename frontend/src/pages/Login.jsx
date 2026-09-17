import { useEffect, useRef, useState } from "react";
import { Link, Navigate, useNavigate } from "react-router-dom";
import { Truck, Loader2, LogIn } from "lucide-react";
import { api } from "@/lib/api";
import { useAuth } from "@/context/AuthContext";

export default function Login() {
  const { user, saveSession } = useAuth();
  const navigate = useNavigate();
  const googleRef = useRef(null);
  const [form, setForm] = useState({ email: "", senha: "" });
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const googleClientId = process.env.REACT_APP_GOOGLE_CLIENT_ID;

  useEffect(() => {
    if (!googleClientId) return;
    const initialize = () => {
      if (!window.google || !googleRef.current) return;
      window.google.accounts.id.initialize({
        client_id: googleClientId,
        callback: async ({ credential }) => {
          try {
            setBusy(true); setError("");
            const { data } = await api.post("/auth/google", { credential });
            saveSession(data); navigate("/", { replace: true });
          } catch (e) { setError(e.response?.data?.detail || "Não foi possível entrar com Google."); }
          finally { setBusy(false); }
        },
      });
      window.google.accounts.id.renderButton(googleRef.current, { theme: "outline", size: "large", width: 360, text: "signin_with" });
    };
    if (window.google) { initialize(); return; }
    const script = document.createElement("script");
    script.src = "https://accounts.google.com/gsi/client";
    script.async = true; script.defer = true; script.onload = initialize;
    document.head.appendChild(script);
    return () => { script.onload = null; };
  }, [googleClientId, navigate, saveSession]);

  if (user) return <Navigate to="/" replace />;

  const submit = async (event) => {
    event.preventDefault(); setBusy(true); setError("");
    try {
      const { data } = await api.post("/auth/login", form);
      saveSession(data); navigate("/", { replace: true });
    } catch (e) { setError(e.response?.data?.detail || "Não foi possível entrar."); }
    finally { setBusy(false); }
  };

  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-950 px-4 py-10">
      <div className="w-full max-w-md rounded-2xl bg-white p-7 shadow-2xl sm:p-9">
        <div className="mb-7 flex items-center gap-3">
          <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-600"><Truck className="h-6 w-6 text-white" /></div>
          <div><h1 className="text-xl font-extrabold text-slate-950">InnovaEnvios</h1><p className="text-sm text-slate-500">Acesse sua conta</p></div>
        </div>
        <form className="space-y-4" onSubmit={submit}>
          <label className="block text-sm font-semibold text-slate-700">E-mail<input type="email" required autoComplete="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} className="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 outline-none focus:border-blue-600" /></label>
          <label className="block text-sm font-semibold text-slate-700">Senha<input type="password" required autoComplete="current-password" value={form.senha} onChange={(e) => setForm({ ...form, senha: e.target.value })} className="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 outline-none focus:border-blue-600" /></label>
          {error && <p className="rounded-lg bg-rose-50 p-3 text-sm text-rose-700">{error}</p>}
          <button disabled={busy} className="flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 py-2.5 font-bold text-white hover:bg-blue-700 disabled:opacity-60">{busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <LogIn className="h-4 w-4" />} Entrar</button>
        </form>
        {googleClientId && <><div className="my-5 flex items-center gap-3 text-xs text-slate-400"><span className="h-px flex-1 bg-slate-200" />ou<span className="h-px flex-1 bg-slate-200" /></div><div ref={googleRef} className="flex justify-center" /></>}
        <p className="mt-6 text-center text-sm text-slate-600">Ainda não tem conta? <Link to="/cadastro" className="font-bold text-blue-600">Criar conta</Link></p>
      </div>
    </div>
  );
}
