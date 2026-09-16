import { useEffect, useState } from "react";
import { toast } from "sonner";
import { api } from "@/lib/api";
import { CONTRACT } from "@/constants/testIds";
import { useSettings } from "@/context/SettingsContext";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import {
  KeyRound, PlugZap, Loader2, CheckCircle2, XCircle, ShieldCheck, Info, Save,
} from "lucide-react";

const STEPS = [
  "Acesse o portal Meu Correios e confirme que possui contrato comercial e cartão de postagem ativos.",
  "No CWS (Correios Web Services), abra 'Gestão de acesso a APIs' e gere o Código de Acesso às APIs.",
  "Solicite ao seu representante a liberação dos serviços: Preço, Prazo, Rastreamento (SRO) e Pré-Postagem.",
  "Informe abaixo Usuário, Código de Acesso, Contrato, Cartão e DR — e clique em 'Testar Conexão'.",
];

export default function Contrato() {
  const { refresh } = useSettings();
  const [form, setForm] = useState({
    usuario: "", codigo_acesso: "", contrato: "", cartao: "", dr: "",
    ambiente: "homologacao", modo_demo: true,
  });
  const [temCodigo, setTemCodigo] = useState(false);
  const [loading, setLoading] = useState(false);
  const [testing, setTesting] = useState(false);
  const [diag, setDiag] = useState(null);

  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));

  const loadCurrent = async () => {
    const { data } = await api.get("/correios/settings");
    setForm({
      usuario: data.usuario || "", codigo_acesso: "", contrato: data.contrato || "",
      cartao: data.cartao || "", dr: data.dr || "", ambiente: data.ambiente || "homologacao",
      modo_demo: data.modo_demo,
    });
    setTemCodigo(data.tem_codigo_acesso);
  };

  useEffect(() => { loadCurrent(); }, []);

  const salvar = async () => {
    setLoading(true);
    try {
      await api.post("/correios/settings", form);
      toast.success("Credenciais salvas com segurança no servidor.");
      await loadCurrent();
      await refresh();
    } catch (e) {
      toast.error("Erro ao salvar credenciais.");
    } finally {
      setLoading(false);
    }
  };

  const testar = async () => {
    setTesting(true);
    setDiag(null);
    try {
      await api.post("/correios/settings", form);
      const { data } = await api.post("/correios/test-connection");
      setDiag(data);
      await refresh();
      if (data.sucesso) toast.success(data.modo === "demo" ? "Modo demonstração ativo." : "Conexão estabelecida!");
      else toast.error("Falha na conexão. Verifique as credenciais.");
    } catch (e) {
      const detail = e.response?.data?.detail || "Erro ao testar conexão.";
      setDiag({ sucesso: false, mensagem: detail, servicos_liberados: [] });
      toast.error(detail);
    } finally {
      setTesting(false);
    }
  };

  const loadDemo = () => {
    setForm({
      usuario: "innovaenvios.demo", codigo_acesso: "DEMO-ACCESS-CODE-2026",
      contrato: "9912345678", cartao: "0076543210", dr: "10",
      ambiente: "homologacao", modo_demo: true,
    });
    toast.info("Dados de exemplo preenchidos. Salve para testar as telas.");
  };

  return (
    <div className="space-y-6" data-testid="contrato-page">
      <div>
        <h1 className="text-2xl font-extrabold tracking-tight sm:text-3xl">Integração Contrato CWS</h1>
        <p className="mt-1 text-sm text-muted-foreground">Conecte o InnovaEnvios ao seu contrato dos Correios de forma segura.</p>
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        {/* Form */}
        <Card className="space-y-5 p-6 lg:col-span-2">
          <div className="flex items-center gap-2">
            <KeyRound className="h-5 w-5 text-blue-600" />
            <h2 className="text-base font-bold">Credenciais do contrato</h2>
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-1.5">
              <Label>Usuário CWS (Meu Correios)</Label>
              <Input data-testid={CONTRACT.username} placeholder="ex: usuario.cws" value={form.usuario} onChange={(e) => set("usuario", e.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Código de Acesso / Senha API CWS</Label>
              <Input data-testid={CONTRACT.accessCode} type="password" placeholder={temCodigo ? "•••••• (salvo)" : "Gerado no portal CWS"} value={form.codigo_acesso} onChange={(e) => set("codigo_acesso", e.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Número do Contrato</Label>
              <Input data-testid={CONTRACT.contractNumber} placeholder="ex: 9912345678" value={form.contrato} onChange={(e) => set("contrato", e.target.value)} className="font-mono" />
            </div>
            <div className="space-y-1.5">
              <Label>Cartão de Postagem</Label>
              <Input data-testid={CONTRACT.postingCard} placeholder="ex: 0076543210" value={form.cartao} onChange={(e) => set("cartao", e.target.value)} className="font-mono" />
            </div>
            <div className="space-y-1.5">
              <Label>Código da DR</Label>
              <Input data-testid={CONTRACT.drInput} placeholder="ex: 10" value={form.dr} onChange={(e) => set("dr", e.target.value)} className="font-mono" />
            </div>
            <div className="space-y-1.5">
              <Label>Ambiente da API</Label>
              <Select value={form.ambiente} onValueChange={(v) => set("ambiente", v)}>
                <SelectTrigger data-testid={CONTRACT.environment}><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="homologacao">Homologação (apihom.correios.com.br)</SelectItem>
                  <SelectItem value="producao">Produção (api.correios.com.br)</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          <div className="flex items-center justify-between rounded-lg border border-border bg-secondary/40 p-4">
            <div>
              <p className="text-sm font-semibold">Modo demonstração</p>
              <p className="text-xs text-muted-foreground">Mantém dados simulados. Desative para conectar de verdade aos Correios.</p>
            </div>
            <Switch data-testid={CONTRACT.demoSwitch} checked={form.modo_demo} onCheckedChange={(v) => set("modo_demo", v)} />
          </div>

          <div className="flex flex-wrap gap-2">
            <Button onClick={testar} disabled={testing} data-testid={CONTRACT.testButton} className="bg-blue-700 hover:bg-blue-800">
              {testing ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <PlugZap className="mr-2 h-4 w-4" />}
              Testar Conexão e Gerar Token
            </Button>
            <Button onClick={salvar} disabled={loading} variant="outline" data-testid={CONTRACT.saveButton}>
              {loading ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Save className="mr-2 h-4 w-4" />}
              Salvar
            </Button>
            <Button onClick={loadDemo} variant="ghost" data-testid={CONTRACT.loadDemo}>Usar dados de exemplo</Button>
          </div>

          {diag && (
            <div className={`rounded-xl border p-4 ${diag.sucesso ? "border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/30" : "border-rose-200 bg-rose-50 dark:border-rose-800 dark:bg-rose-950/30"}`} data-testid="diagnostic-card">
              <div className="flex items-center gap-2">
                {diag.sucesso ? <CheckCircle2 className="h-5 w-5 text-emerald-600" /> : <XCircle className="h-5 w-5 text-rose-600" />}
                <p className={`text-sm font-semibold ${diag.sucesso ? "text-emerald-800 dark:text-emerald-300" : "text-rose-800 dark:text-rose-300"}`}>
                  {diag.modo === "demo" ? "Modo demonstração" : diag.sucesso ? "Conectado aos Correios" : "Falha na conexão"}
                </p>
              </div>
              <p className="mt-1.5 text-xs text-muted-foreground">{diag.mensagem}</p>
              {diag.token_preview && <p className="mt-1 font-mono text-xs text-muted-foreground">Token: {diag.token_preview}</p>}
              {diag.servicos_liberados?.length > 0 && (
                <div className="mt-3 flex flex-wrap gap-2">
                  {diag.servicos_liberados.map((s) => (
                    <span key={s.codigo} className="rounded-full border border-blue-200 bg-blue-50 px-2.5 py-0.5 font-mono text-xs font-semibold text-blue-700 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-300">
                      {s.codigo} · {s.nome}
                    </span>
                  ))}
                </div>
              )}
            </div>
          )}
        </Card>

        {/* Guide */}
        <div className="space-y-4">
          <Card className="p-6">
            <div className="mb-3 flex items-center gap-2">
              <Info className="h-4 w-4 text-blue-600" />
              <h3 className="text-sm font-bold">Como obter suas credenciais</h3>
            </div>
            <ol className="space-y-3">
              {STEPS.map((s, i) => (
                <li key={i} className="flex gap-3 text-xs leading-relaxed text-muted-foreground">
                  <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-blue-600 text-[10px] font-bold text-white">{i + 1}</span>
                  {s}
                </li>
              ))}
            </ol>
          </Card>
          <Card className="border-blue-100 bg-blue-50/50 p-6 dark:border-blue-900 dark:bg-blue-950/20">
            <div className="mb-2 flex items-center gap-2">
              <ShieldCheck className="h-4 w-4 text-blue-600" />
              <h3 className="text-sm font-bold">Segurança</h3>
            </div>
            <p className="text-xs leading-relaxed text-muted-foreground">
              Seu usuário, código de acesso, contrato e cartão ficam armazenados apenas no servidor. O navegador
              nunca recebe essas credenciais nem o token Bearer dos Correios. O token é gerado e renovado
              automaticamente nos bastidores.
            </p>
          </Card>
        </div>
      </div>
    </div>
  );
}
