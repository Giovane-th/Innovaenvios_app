import { useState } from "react";
import { useLocation, useNavigate } from "react-router-dom";
import { toast } from "sonner";
import { api, brl, maskCep } from "@/lib/api";
import { PREPOST } from "@/constants/testIds";
import { useSettings } from "@/context/SettingsContext";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { EtiquetaModal } from "@/components/EtiquetaModal";
import { FilePlus2, Loader2, Plus, Trash2, Package } from "lucide-react";

const SERVICOS = [
  { co: "03220", nome: "SEDEX (03220)" },
  { co: "03298", nome: "PAC (03298)" },
  { co: "04227", nome: "Mini Envios (04227)" },
];

const REM_DEFAULT = {
  nome: "", cpf_cnpj: "", logradouro: "", numero: "", complemento: "", bairro: "",
  cidade: "", uf: "", cep: "",
};
const DEST_DEFAULT = { nome: "", cpf_cnpj: "", logradouro: "", numero: "", complemento: "", bairro: "", cidade: "", uf: "", cep: "" };

const Field = ({ label, value, onChange, testid, ...rest }) => (
  <div className="space-y-1.5">
    <Label className="text-xs">{label}</Label>
    <Input value={value} onChange={onChange} data-testid={testid} {...rest} />
  </div>
);

export default function PrePostagem() {
  const location = useLocation();
  const navigate = useNavigate();
  const { settings } = useSettings();
  const prefill = location.state || {};

  const [remetente, setRemetente] = useState(REM_DEFAULT);
  const [destinatario, setDestinatario] = useState(DEST_DEFAULT);
  const [servico, setServico] = useState(prefill.servico || "03220");
  const [peso, setPeso] = useState(prefill.peso_kg || 0.5);
  const [dim, setDim] = useState({ comprimento: 20, largura: 15, altura: 10 });
  const [itens, setItens] = useState([{ descricao: "Camiseta Algodão", quantidade: 1, valor: 79.9 }]);
  const [loading, setLoading] = useState(false);
  const [etiqueta, setEtiqueta] = useState(null);
  const [modalOpen, setModalOpen] = useState(false);

  const setR = (k, v) => setRemetente((s) => ({ ...s, [k]: v }));
  const setD = (k, v) => setDestinatario((s) => ({ ...s, [k]: v }));
  const setItem = (i, k, v) => setItens((arr) => arr.map((it, idx) => (idx === i ? { ...it, [k]: v } : it)));
  const addItem = () => setItens((a) => [...a, { descricao: "", quantidade: 1, valor: 0 }]);
  const removeItem = (i) => setItens((a) => a.filter((_, idx) => idx !== i));

  const totalDeclarado = itens.reduce((s, it) => s + (Number(it.valor) || 0) * (Number(it.quantidade) || 0), 0);

  const submit = async () => {
    const requiredAddress = (address) => address.nome && address.cep && address.logradouro && address.numero && address.bairro && address.cidade && address.uf;
    if (!requiredAddress(remetente) || !requiredAddress(destinatario)) {
      toast.error("Preencha todos os campos de endereço do remetente e do destinatário.");
      return;
    }
    if (settings?.conectado && !remetente.cpf_cnpj) {
      toast.error("Informe o CPF/CNPJ válido do remetente para emitir nos Correios.");
      return;
    }
    setLoading(true);
    try {
      const { data } = await api.post("/prepostagem", {
        remetente, destinatario, servico,
        peso_kg: Number(peso),
        comprimento: Number(dim.comprimento), largura: Number(dim.largura), altura: Number(dim.altura),
        itens: itens.map((i) => ({ descricao: i.descricao, quantidade: Number(i.quantidade), valor: Number(i.valor) })),
        valor_frete: Number(prefill.valor_frete || 0),
      });
      const { data: label } = await api.post(`/prepostagem/${data.id}/etiqueta`);
      setEtiqueta({ ...data, ...label });
      setModalOpen(true);
      toast.success(`Pré-postagem criada! Objeto ${data.codigo_objeto}`);
    } catch (e) {
      toast.error(e.response?.data?.detail || "Erro ao criar pré-postagem.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="space-y-6" data-testid="prepostagem-page">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-extrabold tracking-tight sm:text-3xl">Nova Pré-Postagem & Etiqueta</h1>
          <p className="mt-1 text-sm text-muted-foreground">Gere a etiqueta oficial com declaração de conteúdo.</p>
        </div>
        <Button variant="outline" onClick={() => navigate("/postagens")}>Ver todas as postagens</Button>
      </div>

      {!settings?.conectado && (
        <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-xs font-medium text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
          Modo demonstração: a etiqueta gerada é uma simulação de alta fidelidade. Conecte seu contrato para emissão real.
        </div>
      )}

      <div className="grid gap-6 lg:grid-cols-2">
        <Card className="space-y-4 p-6">
          <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground">Remetente</h2>
          <div className="grid grid-cols-2 gap-3">
            <Field label="Nome / Razão social *" value={remetente.nome} onChange={(e) => setR("nome", e.target.value)} />
            <Field label="CPF/CNPJ *" value={remetente.cpf_cnpj} onChange={(e) => setR("cpf_cnpj", e.target.value)} />
          </div>
          <div className="grid grid-cols-3 gap-3">
            <div className="col-span-2"><Field label="Logradouro" value={remetente.logradouro} onChange={(e) => setR("logradouro", e.target.value)} /></div>
            <Field label="Número" value={remetente.numero} onChange={(e) => setR("numero", e.target.value)} />
          </div>
          <div className="grid grid-cols-3 gap-3">
            <Field label="Bairro" value={remetente.bairro} onChange={(e) => setR("bairro", e.target.value)} />
            <Field label="Cidade" value={remetente.cidade} onChange={(e) => setR("cidade", e.target.value)} />
            <div className="grid grid-cols-2 gap-2">
              <Field label="UF" value={remetente.uf} onChange={(e) => setR("uf", e.target.value.toUpperCase().slice(0,2))} />
              <Field label="CEP" value={remetente.cep} onChange={(e) => setR("cep", maskCep(e.target.value))} />
            </div>
          </div>
        </Card>

        <Card className="space-y-4 p-6">
          <h2 className="text-sm font-bold uppercase tracking-wider text-muted-foreground">Destinatário</h2>
          <div className="grid grid-cols-3 gap-3">
            <div className="col-span-2"><Field label="Nome completo *" value={destinatario.nome} onChange={(e) => setD("nome", e.target.value)} testid="dest-nome" /></div>
            <Field label="CPF/CNPJ" value={destinatario.cpf_cnpj} onChange={(e) => setD("cpf_cnpj", e.target.value)} />
          </div>
          <div className="grid grid-cols-3 gap-3">
            <div className="col-span-2"><Field label="Logradouro" value={destinatario.logradouro} onChange={(e) => setD("logradouro", e.target.value)} /></div>
            <Field label="Número" value={destinatario.numero} onChange={(e) => setD("numero", e.target.value)} />
          </div>
          <div className="grid grid-cols-3 gap-3">
            <Field label="Bairro" value={destinatario.bairro} onChange={(e) => setD("bairro", e.target.value)} />
            <Field label="Cidade *" value={destinatario.cidade} onChange={(e) => setD("cidade", e.target.value)} testid="dest-cidade" />
            <div className="grid grid-cols-2 gap-2">
              <Field label="UF" value={destinatario.uf} onChange={(e) => setD("uf", e.target.value.toUpperCase().slice(0,2))} />
              <Field label="CEP *" value={destinatario.cep} onChange={(e) => setD("cep", maskCep(e.target.value))} testid="dest-cep" className="font-mono" />
            </div>
          </div>
        </Card>
      </div>

      <div className="grid gap-6 lg:grid-cols-2">
        <Card className="space-y-4 p-6">
          <h2 className="text-sm font-bold uppercase tracking-wider text-muted-foreground">Serviço e volume</h2>
          <div className="space-y-1.5">
            <Label className="text-xs">Serviço Correios</Label>
            <Select value={servico} onValueChange={setServico}>
              <SelectTrigger data-testid="prepost-servico-select"><SelectValue /></SelectTrigger>
              <SelectContent>
                {SERVICOS.map((s) => <SelectItem key={s.co} value={s.co}>{s.nome}</SelectItem>)}
              </SelectContent>
            </Select>
          </div>
          <div className="grid grid-cols-4 gap-3">
            <Field label="Peso (kg)" type="number" step="0.1" value={peso} onChange={(e) => setPeso(e.target.value)} />
            <Field label="Compr." type="number" value={dim.comprimento} onChange={(e) => setDim({ ...dim, comprimento: e.target.value })} />
            <Field label="Larg." type="number" value={dim.largura} onChange={(e) => setDim({ ...dim, largura: e.target.value })} />
            <Field label="Alt." type="number" value={dim.altura} onChange={(e) => setDim({ ...dim, altura: e.target.value })} />
          </div>
        </Card>

        <Card className="space-y-3 p-6">
          <div className="flex items-center justify-between">
            <h2 className="text-sm font-bold uppercase tracking-wider text-muted-foreground">Declaração de conteúdo</h2>
            <Button size="sm" variant="outline" onClick={addItem} data-testid="prepost-add-item"><Plus className="mr-1 h-4 w-4" /> Item</Button>
          </div>
          {itens.map((it, i) => (
            <div key={i} className="grid grid-cols-12 items-end gap-2">
              <div className="col-span-6"><Field label={i === 0 ? "Descrição" : ""} value={it.descricao} onChange={(e) => setItem(i, "descricao", e.target.value)} /></div>
              <div className="col-span-2"><Field label={i === 0 ? "Qtd" : ""} type="number" value={it.quantidade} onChange={(e) => setItem(i, "quantidade", e.target.value)} /></div>
              <div className="col-span-3"><Field label={i === 0 ? "Valor R$" : ""} type="number" step="0.01" value={it.valor} onChange={(e) => setItem(i, "valor", e.target.value)} /></div>
              <button className="col-span-1 flex h-10 items-center justify-center text-muted-foreground hover:text-rose-500" onClick={() => removeItem(i)}><Trash2 className="h-4 w-4" /></button>
            </div>
          ))}
          <div className="flex items-center justify-between border-t border-border pt-3 text-sm">
            <span className="text-muted-foreground">Total declarado</span>
            <span className="font-bold">{brl(totalDeclarado)}</span>
          </div>
        </Card>
      </div>

      <div className="flex items-center justify-between rounded-xl border border-border bg-card p-5">
        <div className="flex items-center gap-3">
          <Package className="h-5 w-5 text-blue-600" />
          <div>
            <p className="text-sm font-semibold">Pronto para emitir</p>
            <p className="text-xs text-muted-foreground">A etiqueta será gerada com código de rastreio e código de barras.</p>
          </div>
        </div>
        <Button onClick={submit} disabled={loading} data-testid={PREPOST.submit} className="bg-amber-500 text-slate-950 hover:bg-amber-400">
          {loading ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <FilePlus2 className="mr-2 h-4 w-4" />}
          Criar & Gerar Etiqueta
        </Button>
      </div>

      <EtiquetaModal prepostagem={etiqueta} open={modalOpen} onOpenChange={setModalOpen} />
    </div>
  );
}
