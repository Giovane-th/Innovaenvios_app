import { useState } from "react";
import { toast } from "sonner";
import { api } from "@/lib/api";
import { TRACKING } from "@/constants/testIds";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Search, Loader2, PackageCheck, Truck, Warehouse, MapPin, PackageOpen, CheckCircle2,
} from "lucide-react";

const QUICK = ["NL123456789BR", "QC887654321BR", "OD447788991BR"];

const iconFor = (tipo) => {
  if (tipo === "BDE") return CheckCircle2;
  if (tipo === "OEC") return Truck;
  if (tipo === "BDR") return PackageOpen;
  return Warehouse;
};

export default function Rastreamento() {
  const [codigo, setCodigo] = useState("");
  const [loading, setLoading] = useState(false);
  const [data, setData] = useState(null);

  const rastrear = async (code) => {
    const c = (code || codigo).trim().toUpperCase();
    if (c.length !== 13) {
      toast.error("O código deve ter 13 caracteres (ex: AA123456789BR).");
      return;
    }
    setCodigo(c);
    setLoading(true);
    try {
      const res = await api.get(`/rastreamento/${c}`);
      setData(res.data);
    } catch (e) {
      toast.error(e.response?.data?.detail || "Erro ao rastrear.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="space-y-6" data-testid="rastreamento-page">
      <div>
        <h1 className="text-2xl font-extrabold tracking-tight sm:text-3xl">Rastreamento de Objetos</h1>
        <p className="mt-1 text-sm text-muted-foreground">Acompanhe o histórico de movimentação SRO dos Correios.</p>
      </div>

      <Card className="p-5">
        <div className="flex flex-col gap-3 sm:flex-row">
          <Input
            data-testid={TRACKING.codeInput}
            placeholder="AA123456789BR"
            value={codigo}
            maxLength={13}
            onChange={(e) => setCodigo(e.target.value.toUpperCase())}
            onKeyDown={(e) => e.key === "Enter" && rastrear()}
            className="font-mono uppercase tracking-widest"
          />
          <Button onClick={() => rastrear()} disabled={loading} data-testid={TRACKING.trackButton} className="bg-blue-700 hover:bg-blue-800 sm:w-40">
            {loading ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Search className="mr-2 h-4 w-4" />}
            Rastrear
          </Button>
        </div>
        <div className="mt-3 flex flex-wrap items-center gap-2">
          <span className="text-xs text-muted-foreground">Testar:</span>
          {QUICK.map((q) => (
            <button key={q} onClick={() => rastrear(q)} className="rounded-full border border-border px-2.5 py-1 font-mono text-xs text-muted-foreground transition-colors hover:border-blue-400 hover:text-blue-600">
              {q}
            </button>
          ))}
        </div>
      </Card>

      {data && (
        <div className="space-y-6" data-testid={TRACKING.timeline}>
          {/* Stepper */}
          <Card className="p-6">
            <div className="mb-1 flex items-center gap-2">
              <span className="font-mono text-sm font-bold">{data.codigo}</span>
              <span className={`rounded-full px-2.5 py-0.5 text-xs font-bold ${data.entregue ? "bg-emerald-100 text-emerald-700" : "bg-blue-100 text-blue-700"}`}>
                {data.entregue ? "Entregue" : "Em andamento"}
              </span>
            </div>
            <div className="mt-5 flex items-center">
              {data.etapas.map((et, i) => {
                const done = i < data.etapa_atual;
                return (
                  <div key={et} className="flex flex-1 items-center last:flex-none">
                    <div className="flex flex-col items-center">
                      <div className={`flex h-9 w-9 items-center justify-center rounded-full text-xs font-bold ${done ? "bg-blue-600 text-white" : "bg-secondary text-muted-foreground"}`}>
                        {i + 1}
                      </div>
                      <span className={`mt-2 hidden text-center text-[10px] font-medium sm:block ${done ? "text-foreground" : "text-muted-foreground"}`} style={{ maxWidth: 70 }}>
                        {et}
                      </span>
                    </div>
                    {i < data.etapas.length - 1 && (
                      <div className={`mx-1 h-1 flex-1 rounded ${i < data.etapa_atual - 1 ? "bg-blue-600" : "bg-secondary"}`} />
                    )}
                  </div>
                );
              })}
            </div>
          </Card>

          {/* Timeline */}
          <Card className="p-6">
            <h2 className="mb-4 text-sm font-bold uppercase tracking-wider text-muted-foreground">Histórico de eventos</h2>
            <div className="space-y-0">
              {data.eventos.map((ev, i) => {
                const Icon = iconFor(ev.tipo);
                const first = i === 0;
                return (
                  <div key={i} className="relative flex gap-4 pb-6 last:pb-0">
                    {i < data.eventos.length - 1 && <div className="absolute left-[18px] top-9 h-full w-px bg-border" />}
                    <div className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full ${first ? "bg-blue-600 text-white" : "bg-secondary text-muted-foreground"}`}>
                      <Icon className="h-4 w-4" />
                    </div>
                    <div className="flex-1 pt-1">
                      <p className={`text-sm font-semibold ${first ? "text-blue-700 dark:text-blue-400" : "text-foreground"}`}>{ev.descricao}</p>
                      <p className="mt-0.5 flex items-center gap-1 text-xs text-muted-foreground">
                        <MapPin className="h-3 w-3" /> {ev.unidade}
                      </p>
                      <p className="mt-0.5 text-xs text-muted-foreground">{ev.data} às {ev.hora}</p>
                    </div>
                  </div>
                );
              })}
            </div>
          </Card>
        </div>
      )}
    </div>
  );
}
