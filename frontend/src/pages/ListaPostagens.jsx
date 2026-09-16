import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { toast } from "sonner";
import { api, brl } from "@/lib/api";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { EtiquetaModal } from "@/components/EtiquetaModal";
import { Printer, Search, Ban, PackageCheck, Loader2 } from "lucide-react";

const FILTROS = [
  { id: "TODAS", label: "Todas" },
  { id: "CRIADA", label: "Aguardando Postagem" },
  { id: "CANCELADA", label: "Canceladas" },
];

const STATUS_STYLE = {
  CRIADA: "bg-amber-50 text-amber-700 border-amber-200",
  CANCELADA: "bg-rose-50 text-rose-700 border-rose-200",
  POSTADA: "bg-emerald-50 text-emerald-700 border-emerald-200",
};

export default function ListaPostagens() {
  const navigate = useNavigate();
  const [filtro, setFiltro] = useState("TODAS");
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [etiqueta, setEtiqueta] = useState(null);
  const [modalOpen, setModalOpen] = useState(false);

  const load = async (f = filtro) => {
    setLoading(true);
    try {
      const { data } = await api.get("/prepostagens", { params: { status: f } });
      setItems(data.items);
    } catch (e) {
      toast.error("Erro ao carregar postagens.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); /* eslint-disable-next-line */ }, [filtro]);

  const cancelar = async (id) => {
    try {
      await api.post(`/prepostagem/${id}/cancelar`);
      toast.success("Pré-postagem cancelada.");
      load();
    } catch (e) {
      toast.error(e.response?.data?.detail || "Erro ao cancelar.");
    }
  };

  const abrirEtiqueta = (p) => { setEtiqueta(p); setModalOpen(true); };

  return (
    <div className="space-y-6" data-testid="lista-postagens-page">
      <div>
        <h1 className="text-2xl font-extrabold tracking-tight sm:text-3xl">Pré-Postagens</h1>
        <p className="mt-1 text-sm text-muted-foreground">Gerencie etiquetas, rastreie e cancele objetos.</p>
      </div>

      <div className="flex flex-wrap gap-2">
        {FILTROS.map((f) => (
          <button
            key={f.id}
            onClick={() => setFiltro(f.id)}
            data-testid={`filter-${f.id}`}
            className={`rounded-full border px-4 py-1.5 text-sm font-medium transition-colors ${
              filtro === f.id ? "border-blue-600 bg-blue-600 text-white" : "border-border text-muted-foreground hover:border-blue-400"
            }`}
          >
            {f.label}
          </button>
        ))}
      </div>

      <Card className="overflow-hidden">
        {loading ? (
          <div className="flex items-center justify-center py-16 text-muted-foreground"><Loader2 className="h-6 w-6 animate-spin" /></div>
        ) : items.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-16 text-center">
            <PackageCheck className="mb-3 h-10 w-10 text-muted-foreground" />
            <p className="text-sm text-muted-foreground">Nenhuma pré-postagem encontrada.</p>
            <Button className="mt-4 bg-blue-700 hover:bg-blue-800" onClick={() => navigate("/pre-postagem")}>Criar primeira pré-postagem</Button>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border bg-secondary/50 text-left text-xs uppercase tracking-wider text-muted-foreground">
                  <th className="px-4 py-3 font-semibold">Código do Objeto</th>
                  <th className="px-4 py-3 font-semibold">Destinatário</th>
                  <th className="px-4 py-3 font-semibold">Serviço</th>
                  <th className="px-4 py-3 font-semibold">Frete</th>
                  <th className="px-4 py-3 font-semibold">Status</th>
                  <th className="px-4 py-3 text-right font-semibold">Ações</th>
                </tr>
              </thead>
              <tbody>
                {items.map((p, idx) => (
                  <tr key={p.id} className={`border-b border-border ${idx % 2 ? "bg-secondary/20" : ""}`} data-testid={`postagem-row-${idx}`}>
                    <td className="px-4 py-3 font-mono font-semibold">{p.codigo_objeto}</td>
                    <td className="px-4 py-3">{p.destinatario?.nome || "—"}<br /><span className="text-xs text-muted-foreground">{p.destinatario?.cidade}/{p.destinatario?.uf}</span></td>
                    <td className="px-4 py-3"><span className="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-bold text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">{p.servico_tag}</span></td>
                    <td className="px-4 py-3">{brl(p.valor_frete)}</td>
                    <td className="px-4 py-3"><span className={`rounded-full border px-2.5 py-0.5 text-xs font-bold ${STATUS_STYLE[p.status] || ""}`}>{p.status}</span></td>
                    <td className="px-4 py-3">
                      <div className="flex justify-end gap-1">
                        <Button size="icon" variant="ghost" title="Imprimir etiqueta" onClick={() => abrirEtiqueta(p)} data-testid={`action-label-${idx}`}><Printer className="h-4 w-4" /></Button>
                        <Button size="icon" variant="ghost" title="Rastrear" onClick={() => navigate("/rastreamento", { state: {} }) || navigate(`/rastreamento`)} data-testid={`action-track-${idx}`}><Search className="h-4 w-4" /></Button>
                        {p.status !== "CANCELADA" && (
                          <Button size="icon" variant="ghost" title="Cancelar" onClick={() => cancelar(p.id)} data-testid={`action-cancel-${idx}`}><Ban className="h-4 w-4 text-rose-500" /></Button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>

      <EtiquetaModal prepostagem={etiqueta} open={modalOpen} onOpenChange={setModalOpen} />
    </div>
  );
}
