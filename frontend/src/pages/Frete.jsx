import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { toast } from "sonner";
import { api, brl, maskCep } from "@/lib/api";
import { FREIGHT } from "@/constants/testIds";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Checkbox } from "@/components/ui/checkbox";
import { Calculator, Zap, TrendingDown, Loader2, Rocket, PiggyBank } from "lucide-react";

const SERVICOS = [
  { co: "03220", nome: "SEDEX", cls: "bg-blue-50 text-blue-700 border-blue-200" },
  { co: "03298", nome: "PAC", cls: "bg-amber-50 text-amber-800 border-amber-300" },
  { co: "04227", nome: "Mini Envios", cls: "bg-purple-50 text-purple-700 border-purple-200" },
];

export default function Frete() {
  const navigate = useNavigate();
  const [form, setForm] = useState({
    cep_origem: "01001-000", cep_destino: "20040-000", peso_kg: 0.5,
    comprimento: 20, largura: 15, altura: 10, valor_declarado: 0,
  });
  const [servicos, setServicos] = useState(["03220", "03298", "04227"]);
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState(null);

  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const toggle = (co) =>
    setServicos((s) => (s.includes(co) ? s.filter((x) => x !== co) : [...s, co]));

  const calcular = async () => {
    if (!form.cep_destino || servicos.length === 0) {
      toast.error("Informe o CEP de destino e ao menos um serviço.");
      return;
    }
    setLoading(true);
    try {
      const { data } = await api.post("/frete/calcular", {
        cep_origem: form.cep_origem,
        cep_destino: form.cep_destino,
        peso_kg: Number(form.peso_kg),
        comprimento: Number(form.comprimento),
        largura: Number(form.largura),
        altura: Number(form.altura),
        valor_declarado: Number(form.valor_declarado),
        servicos,
      });
      setResult(data);
      toast.success(`${data.resultados.length} serviço(s) calculado(s).${data.simulado ? " (simulação)" : ""}`);
    } catch (e) {
      toast.error("Erro ao calcular frete.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="space-y-6" data-testid="frete-page">
      <div>
        <h1 className="text-2xl font-extrabold tracking-tight sm:text-3xl">Cálculo de Frete</h1>
        <p className="mt-1 text-sm text-muted-foreground">Preço e prazo com o desconto do seu contrato Correios.</p>
      </div>

      <div className="grid gap-6 lg:grid-cols-5">
        <Card className="lg:col-span-2 space-y-5 p-6">
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label>CEP Origem</Label>
              <Input data-testid={FREIGHT.originCep} value={form.cep_origem} onChange={(e) => set("cep_origem", maskCep(e.target.value))} className="font-mono" />
            </div>
            <div className="space-y-1.5">
              <Label>CEP Destino</Label>
              <Input data-testid={FREIGHT.destCep} value={form.cep_destino} onChange={(e) => set("cep_destino", maskCep(e.target.value))} className="font-mono" />
            </div>
          </div>
          <div className="space-y-1.5">
            <Label>Peso (kg)</Label>
            <Input data-testid={FREIGHT.weight} type="number" step="0.1" value={form.peso_kg} onChange={(e) => set("peso_kg", e.target.value)} />
          </div>
          <div className="grid grid-cols-3 gap-3">
            <div className="space-y-1.5">
              <Label className="text-xs">Compr. (cm)</Label>
              <Input data-testid={FREIGHT.length} type="number" value={form.comprimento} onChange={(e) => set("comprimento", e.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label className="text-xs">Larg. (cm)</Label>
              <Input data-testid={FREIGHT.width} type="number" value={form.largura} onChange={(e) => set("largura", e.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label className="text-xs">Alt. (cm)</Label>
              <Input data-testid={FREIGHT.height} type="number" value={form.altura} onChange={(e) => set("altura", e.target.value)} />
            </div>
          </div>
          <div className="space-y-1.5">
            <Label>Valor declarado (R$)</Label>
            <Input type="number" step="0.01" value={form.valor_declarado} onChange={(e) => set("valor_declarado", e.target.value)} />
          </div>
          <div className="space-y-2">
            <Label>Serviços</Label>
            <div className="flex flex-col gap-2">
              {SERVICOS.map((s) => (
                <label key={s.co} className="flex cursor-pointer items-center gap-2 rounded-lg border border-border p-2.5 text-sm">
                  <Checkbox checked={servicos.includes(s.co)} onCheckedChange={() => toggle(s.co)} data-testid={`freight-service-${s.co}`} />
                  <span className={`rounded-full border px-2 py-0.5 text-xs font-bold ${s.cls}`}>{s.nome}</span>
                  <span className="font-mono text-xs text-muted-foreground">{s.co}</span>
                </label>
              ))}
            </div>
          </div>
          <Button onClick={calcular} disabled={loading} data-testid={FREIGHT.calcButton} className="w-full bg-blue-700 hover:bg-blue-800">
            {loading ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Calculator className="mr-2 h-4 w-4" />}
            Calcular Frete
          </Button>
        </Card>

        <div className="lg:col-span-3" data-testid={FREIGHT.results}>
          {!result ? (
            <Card className="flex h-full min-h-[300px] flex-col items-center justify-center p-10 text-center">
              <div className="blueprint-grid mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-secondary">
                <Calculator className="h-7 w-7 text-muted-foreground" />
              </div>
              <p className="text-sm text-muted-foreground">Preencha os dados e clique em calcular para comparar os serviços.</p>
            </Card>
          ) : (
            <div className="space-y-4">
              {result.simulado && (
                <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-xs font-medium text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
                  Valores de demonstração. Conecte seu contrato em <b>Integração Contrato CWS</b> para preços reais.
                </div>
              )}
              {result.resultados.map((r) => (
                <Card key={r.codigo_servico} className="animate-fade-up p-5">
                  <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                      <span className="rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-sm font-bold tracking-wider text-blue-700 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-300">
                        {r.servico}
                      </span>
                      {r.mais_rapido && (
                        <span className="inline-flex items-center gap-1 rounded-full bg-blue-600 px-2.5 py-1 text-[11px] font-bold text-white">
                          <Rocket className="h-3 w-3" /> Mais rápido
                        </span>
                      )}
                      {r.melhor_custo && (
                        <span className="inline-flex items-center gap-1 rounded-full bg-emerald-600 px-2.5 py-1 text-[11px] font-bold text-white">
                          <PiggyBank className="h-3 w-3" /> Melhor custo
                        </span>
                      )}
                    </div>
                    <div className="text-right">
                      <p className="text-xs text-muted-foreground line-through">{brl(r.preco_balcao)}</p>
                      <p className="text-2xl font-extrabold text-foreground">{brl(r.preco_contrato)}</p>
                    </div>
                  </div>
                  <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4">
                    <div className="flex gap-6 text-sm">
                      <div>
                        <p className="text-xs text-muted-foreground">Prazo</p>
                        <p className="font-semibold">{r.prazo_dias} dia(s) útil(eis)</p>
                      </div>
                      <div>
                        <p className="text-xs text-muted-foreground">Entrega até</p>
                        <p className="font-semibold">{new Date(r.data_entrega + "T00:00").toLocaleDateString("pt-BR")}</p>
                      </div>
                      <div>
                        <p className="text-xs text-muted-foreground">Economia</p>
                        <p className="inline-flex items-center gap-1 font-semibold text-emerald-600">
                          <TrendingDown className="h-3.5 w-3.5" /> {brl(r.economia)}
                        </p>
                      </div>
                    </div>
                    <Button
                      size="sm"
                      className="bg-amber-500 text-slate-950 hover:bg-amber-400"
                      data-testid={`freight-prepost-${r.codigo_servico}`}
                      onClick={() => navigate("/pre-postagem", { state: { servico: r.codigo_servico, valor_frete: r.preco_contrato, peso_kg: form.peso_kg } })}
                    >
                      <Zap className="mr-1.5 h-4 w-4" /> Gerar Pré-Postagem
                    </Button>
                  </div>
                </Card>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
