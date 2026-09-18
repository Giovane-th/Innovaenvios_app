import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { useState } from "react";
import { Printer, Download, Truck, Loader2, BadgeCheck } from "lucide-react";
import { PREPOST } from "@/constants/testIds";
import { api } from "@/lib/api";
import { toast } from "sonner";

const Barcode = ({ seed, height = 44 }) => {
  let h = 0;
  for (let i = 0; i < seed.length; i++) h = (h * 31 + seed.charCodeAt(i)) & 0xffffff;
  const bars = [];
  const widths = [1, 2, 3, 1, 2, 1, 3, 2];
  for (let i = 0; i < 70; i++) {
    h = (h * 1103515245 + 12345) & 0x7fffffff;
    const w = widths[h % widths.length];
    const black = i % 2 === 0;
    bars.push(
      <div key={i} style={{ width: `${w * 2}px`, height }} className={black ? "bg-slate-900" : "bg-transparent"} />
    );
  }
  return <div className="flex items-end gap-[1px]" style={{ height }}>{bars}</div>;
};

export const EtiquetaModal = ({ prepostagem, open, onOpenChange }) => {
  const [downloading, setDownloading] = useState(false);
  if (!prepostagem) return null;
  const p = prepostagem;
  const rem = p.remetente || {};
  const dest = p.destinatario || {};

  const handlePrint = () => window.print();
  const openOfficialPdf = async (download = false) => {
    setDownloading(true);
    try {
      const response = await api.get(`/prepostagem/${p.id}/etiqueta/pdf`, { responseType: "blob" });
      const url = URL.createObjectURL(new Blob([response.data], { type: "application/pdf" }));
      if (download) {
        const anchor = document.createElement("a");
        anchor.href = url;
        anchor.download = `etiqueta-${p.codigo_objeto}.pdf`;
        anchor.click();
      } else {
        window.open(url, "_blank", "noopener,noreferrer");
      }
      window.setTimeout(() => URL.revokeObjectURL(url), 60000);
    } catch (error) {
      toast.error(error.response?.data?.detail || "Não foi possível abrir o PDF oficial.");
    } finally {
      setDownloading(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent
        data-testid={PREPOST.labelModal}
        className="max-w-lg overflow-hidden p-0"
      >
        <DialogHeader className="border-b border-border px-5 py-3">
          <DialogTitle className="text-base">Etiqueta de Postagem — {p.servico_nome}</DialogTitle>
          <DialogDescription className="text-xs">Objeto {p.codigo_objeto} — {p.simulado ? "prévia de demonstração" : "PDF oficial dos Correios em 10 × 15 cm"}.</DialogDescription>
        </DialogHeader>

        {!p.simulado ? (
          <div className="flex flex-col items-center px-8 py-12 text-center">
            <BadgeCheck className="mb-4 h-14 w-14 text-emerald-600" />
            <h3 className="text-lg font-bold">Etiqueta oficial pronta</h3>
            <p className="mt-2 max-w-sm text-sm text-muted-foreground">
              O arquivo foi gerado pelos Correios no padrão térmico 100 × 150 mm. Abra o PDF e imprima em tamanho real, com escala de 100%.
            </p>
            <p className="mt-4 font-mono text-base font-bold tracking-wider">{p.codigo_objeto}</p>
          </div>
        ) : <div id="etiqueta-print" className="relative p-5">
          <div className="pointer-events-none absolute inset-0 z-10 flex rotate-[-28deg] items-center justify-center text-4xl font-black text-rose-600/25">
            SIMULAÇÃO — NÃO POSTAR
          </div>
          <div className="rounded-lg border-2 border-slate-900 bg-white text-slate-900">
            {/* Chancela */}
            <div className="flex items-stretch border-b-2 border-slate-900">
              <div className="flex items-center gap-2 bg-slate-900 px-3 py-2 text-white">
                <Truck className="h-5 w-5" />
                <span className="text-sm font-extrabold tracking-wide">CORREIOS</span>
              </div>
              <div className="flex flex-1 items-center justify-center bg-amber-400 py-2">
                <span className="text-lg font-black tracking-widest text-slate-900">{p.servico_tag}</span>
              </div>
              <div className="flex items-center px-3 text-right">
                <div>
                  <p className="text-[9px] font-semibold uppercase leading-none text-slate-500">Contrato</p>
                  <p className="font-mono text-xs font-bold">{p.id_prepostagem?.slice(0, 10)}</p>
                </div>
              </div>
            </div>

            {/* Código objeto + barcode */}
            <div className="flex flex-col items-center border-b-2 border-slate-900 py-3">
              <Barcode seed={p.codigo_objeto} />
              <p className="mt-1 font-mono text-lg font-bold tracking-[0.2em]">{p.codigo_objeto}</p>
            </div>

            {/* Destinatário */}
            <div className="border-b-2 border-slate-900 p-3">
              <p className="text-[9px] font-bold uppercase tracking-wider text-slate-500">Destinatário</p>
              <p className="text-sm font-bold">{dest.nome || "—"}</p>
              <p className="text-xs leading-tight">
                {dest.logradouro} {dest.numero} {dest.complemento}
              </p>
              <p className="text-xs leading-tight">{dest.bairro}</p>
              <p className="text-xs leading-tight">{dest.cidade} / {dest.uf}</p>
              <div className="mt-2 flex items-center justify-between">
                <div className="flex items-end gap-[1px]">
                  <Barcode seed={"CEP" + (dest.cep || "")} height={26} />
                </div>
                <p className="font-mono text-xl font-black tracking-widest">{dest.cep || "—"}</p>
              </div>
            </div>

            {/* Remetente */}
            <div className="p-3">
              <p className="text-[9px] font-bold uppercase tracking-wider text-slate-500">Remetente</p>
              <p className="text-xs font-semibold">{rem.nome || "—"}</p>
              <p className="text-[11px] leading-tight text-slate-600">
                {rem.logradouro} {rem.numero} — {rem.bairro}, {rem.cidade}/{rem.uf} — CEP {rem.cep}
              </p>
              <div className="mt-2 flex items-center justify-between border-t border-dashed border-slate-300 pt-2 text-[11px]">
                <span>Peso: <b>{p.peso_kg} kg</b></span>
                <span>Valor declarado: <b>R$ {(p.valor_declarado || 0).toFixed(2)}</b></span>
              </div>
            </div>
          </div>
          <style>{`@media print {
            @page { size: 100mm 150mm; margin: 0; }
            body * { visibility: hidden !important; }
            #etiqueta-print, #etiqueta-print * { visibility: visible !important; }
            #etiqueta-print { position: fixed !important; inset: 0 auto auto 0 !important; width: 100mm !important; height: 150mm !important; padding: 5mm !important; background: white !important; }
          }`}</style>
        </div>}

        <div className="flex gap-2 border-t border-border px-5 py-3">
          <Button variant="outline" className="flex-1" disabled={downloading} onClick={() => p.simulado ? handlePrint() : openOfficialPdf(false)} data-testid="prepost-print-label">
            {downloading ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Printer className="mr-2 h-4 w-4" />} {p.simulado ? "Imprimir prévia" : "Abrir para imprimir"}
          </Button>
          <Button className="flex-1 bg-blue-700 hover:bg-blue-800" disabled={p.simulado || downloading} onClick={() => openOfficialPdf(true)} data-testid={PREPOST.downloadLabel}>
            <Download className="mr-2 h-4 w-4" /> {p.simulado ? "PDF só no modo real" : "Baixar PDF"}
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  );
};
