import { useEffect, useState } from "react";
import { Check, Loader2, ShieldBan, Users as UsersIcon } from "lucide-react";
import { toast } from "sonner";
import { api } from "@/lib/api";

const badge = { aprovado: "bg-emerald-100 text-emerald-700", pendente: "bg-amber-100 text-amber-700", bloqueado: "bg-rose-100 text-rose-700" };

export default function Usuarios() {
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState("");

  const load = async () => {
    try { const { data } = await api.get("/admin/users"); setUsers(data.users); }
    catch (e) { toast.error(e.response?.data?.detail || "Não foi possível carregar os usuários."); }
    finally { setLoading(false); }
  };
  useEffect(() => { load(); }, []);

  const changeStatus = async (id, status) => {
    setBusy(id + status);
    try {
      const { data } = await api.patch(`/admin/users/${id}/status`, { status });
      setUsers((list) => list.map((user) => user.id === id ? data.user : user));
      toast.success(status === "aprovado" ? "Acesso aprovado." : "Acesso bloqueado.");
    } catch (e) { toast.error(e.response?.data?.detail || "Não foi possível alterar o acesso."); }
    finally { setBusy(""); }
  };

  return (
    <div>
      <div className="mb-6 flex items-center gap-3"><div className="rounded-xl bg-blue-100 p-3 text-blue-700"><UsersIcon className="h-6 w-6" /></div><div><h1 className="text-2xl font-extrabold">Usuários</h1><p className="text-sm text-muted-foreground">Aprove ou bloqueie o acesso ao sistema.</p></div></div>
      {loading ? <div className="flex justify-center py-16"><Loader2 className="h-7 w-7 animate-spin text-blue-600" /></div> :
        <div className="overflow-x-auto rounded-xl border bg-white"><table className="w-full text-left text-sm"><thead className="border-b bg-slate-50 text-xs uppercase text-slate-500"><tr><th className="px-4 py-3">Usuário</th><th className="px-4 py-3">Perfil</th><th className="px-4 py-3">Status</th><th className="px-4 py-3 text-right">Ações</th></tr></thead><tbody>
          {users.map((user) => <tr key={user.id} className="border-b last:border-0"><td className="px-4 py-4"><p className="font-bold text-slate-900">{user.nome}</p><p className="text-xs text-slate-500">{user.email}</p></td><td className="px-4 py-4 capitalize">{user.role}</td><td className="px-4 py-4"><span className={`rounded-full px-2.5 py-1 text-xs font-bold ${badge[user.status] || badge.aprovado}`}>{user.status || "aprovado"}</span></td><td className="px-4 py-4"><div className="flex justify-end gap-2">{user.status !== "aprovado" && <button disabled={Boolean(busy)} onClick={() => changeStatus(user.id, "aprovado")} className="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white disabled:opacity-50"><Check className="h-4 w-4" /> Aprovar</button>}{user.role !== "admin" && user.status !== "bloqueado" && <button disabled={Boolean(busy)} onClick={() => changeStatus(user.id, "bloqueado")} className="inline-flex items-center gap-1 rounded-lg bg-rose-600 px-3 py-2 text-xs font-bold text-white disabled:opacity-50"><ShieldBan className="h-4 w-4" /> Bloquear</button>}</div></td></tr>)}
        </tbody></table></div>}
    </div>
  );
}
