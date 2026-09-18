import { Clock3, LogOut, Truck } from "lucide-react";
import { useNavigate } from "react-router-dom";
import { useAuth } from "@/context/AuthContext";

export default function Aguardando() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-950 px-4">
      <div className="w-full max-w-md rounded-2xl bg-white p-8 text-center shadow-2xl">
        <div className="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-600"><Truck className="h-7 w-7 text-white" /></div>
        <Clock3 className="mx-auto mb-3 h-9 w-9 text-amber-500" />
        <h1 className="text-xl font-extrabold text-slate-950">Aguardando aprovação</h1>
        <p className="mt-3 text-sm leading-6 text-slate-600">Olá, {user?.nome}. Seu cadastro foi recebido e precisa ser aprovado pelo administrador antes de você criar envios e etiquetas.</p>
        <button onClick={() => { logout(); navigate("/login"); }} className="mt-6 inline-flex items-center gap-2 rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50"><LogOut className="h-4 w-4" /> Sair</button>
      </div>
    </div>
  );
}
