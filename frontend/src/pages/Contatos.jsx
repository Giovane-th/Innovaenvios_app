import { useEffect, useMemo, useState } from "react";
import { api } from "@/lib/api";
import { toast } from "sonner";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Loader2, Search, Trash2, UsersRound } from "lucide-react";

export default function Contatos() {
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [busca, setBusca] = useState("");
  const load = async () => {
    setLoading(true);
    try { const { data } = await api.get("/contatos"); setItems(data.items || []); }
    catch { toast.error("Não foi possível carregar a carteira."); }
    finally { setLoading(false); }
  };
  useEffect(() => { load(); }, []);
  const filtered = useMemo(() => {
    const term = busca.toLowerCase();
    return items.filter((item) => [item.nome, item.cpf_cnpj, item.cep, item.cidade].some((v) => String(v || "").toLowerCase().includes(term)));
  }, [items, busca]);
  const remove = async (id) => {
    if (!window.confirm("Excluir este contato da carteira?")) return;
    try { await api.delete(`/contatos/${id}`); setItems((current) => current.filter((item) => item.id !== id)); toast.success("Contato excluído."); }
    catch (error) { toast.error(error.response?.data?.detail || "Não foi possível excluir."); }
  };
  return <div className="space-y-6">
    <div><h1 className="text-2xl font-extrabold tracking-tight sm:text-3xl">Carteira de Clientes</h1><p className="mt-1 text-sm text-muted-foreground">Remetentes e destinatários são salvos automaticamente após cada emissão.</p></div>
    <div className="relative max-w-xl"><Search className="absolute left-3 top-3 h-4 w-4 text-muted-foreground" /><Input className="pl-9" placeholder="Buscar por nome, documento, CEP ou cidade" value={busca} onChange={(e) => setBusca(e.target.value)} /></div>
    {loading ? <div className="flex justify-center py-16"><Loader2 className="h-6 w-6 animate-spin" /></div> : filtered.length === 0 ?
      <Card className="flex flex-col items-center py-16 text-center"><UsersRound className="mb-3 h-10 w-10 text-muted-foreground" /><p className="text-sm text-muted-foreground">Nenhum contato salvo.</p></Card> :
      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">{filtered.map((item) => <Card key={item.id} className="p-5">
        <div className="flex items-start justify-between gap-3"><div><span className={`rounded-full px-2 py-1 text-[10px] font-bold uppercase ${item.tipo === "remetente" ? "bg-blue-50 text-blue-700" : "bg-emerald-50 text-emerald-700"}`}>{item.tipo}</span><h2 className="mt-3 font-bold">{item.nome}</h2><p className="text-xs text-muted-foreground">{item.cpf_cnpj || "Sem documento"}</p></div><Button size="icon" variant="ghost" onClick={() => remove(item.id)} title="Excluir"><Trash2 className="h-4 w-4 text-rose-500" /></Button></div>
        <p className="mt-4 text-sm">{item.logradouro}, {item.numero}{item.complemento ? ` — ${item.complemento}` : ""}</p><p className="text-sm text-muted-foreground">{item.bairro} — {item.cidade}/{item.uf}</p><p className="mt-2 font-mono text-xs">CEP {item.cep}</p>
      </Card>)}</div>}
  </div>;
}
