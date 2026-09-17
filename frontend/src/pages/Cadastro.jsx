import { useState } from "react";
import { Link, Navigate, useNavigate } from "react-router-dom";
import { Loader2, Truck, UserPlus } from "lucide-react";
import { api } from "@/lib/api";
import { useAuth } from "@/context/AuthContext";

export default function Cadastro() {
  const { user, saveSession } = useAuth();
  const navigate = useNavigate();
  const [form, setForm] = useState({ nome: "", email: "", senha: "", confirmar: "" });
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  if (user) return <Navigate to="/" replace />;

  const submit = async (event) => {
    event.preventDefault(); setError("");
    if (form.senha !== form.confirmar) { setError("As senhas não coincidem."); return; }
    setBusy(true);
    try {
      const { data } = await api.post("/auth/register", { nome: form.nome, email: form.email, senha: form.senha });
      saveSession(data); navigate("/", { replace: true });
    } catch (e) { setError(e.response?.data?.detail || "Não foi possível criar a conta."); }
    finally { setBusy(false); }
  };

  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-950 px-4 py-10">
      <div className="w-full max-w-md rounded-2xl bg-white p-7 shadow-2xl sm:p-9">
        <div className="mb-7 flex items-center gap-3"><div className="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-600"><Truck className="h-6 w-6 text-white" /></div><div><h1 className="text-xl font-extrabold text-slate-950">Criar sua conta</h1><p className="text-sm text-slate-500">É rápido e seguro</p></div></div>
        <form className="space-y-4" onSubmit={submit}>
          {[['nome','Nome completo','text','name'],['email','E-mail','email','email'],['senha','Senha (mínimo 8 caracteres)','password','new-password'],['confirmar','Confirmar senha','password','new-password']].map(([key,label,type,auto]) => <label key={key} className="block text-sm font-semibold text-slate-700">{label}<input type={type} required minLength={key === 'senha' ? 8 : undefined} autoComplete={auto} value={form[key]} onChange={(e) => setForm({ ...form, [key]: e.target.value })} className="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 outline-none focus:border-blue-600" /></label>)}
          {error && <p className="rounded-lg bg-rose-50 p-3 text-sm text-rose-700">{error}</p>}
          <button disabled={busy} className="flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 py-2.5 font-bold text-white hover:bg-blue-700 disabled:opacity-60">{busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <UserPlus className="h-4 w-4" />} Criar conta</button>
        </form>
        <p className="mt-6 text-center text-sm text-slate-600">Já possui conta? <Link to="/login" className="font-bold text-blue-600">Entrar</Link></p>
      </div>
    </div>
  );
}
