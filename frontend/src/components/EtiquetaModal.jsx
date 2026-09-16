import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Printer, Download, Truck } from "lucide-react";
import { PREPOST } from "@/constants/testIds";

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
  if (!prepostagem) return null;
  const p = prepostagem;
  const rem = p.remetente || {};
  const dest = p.destinatario || {};

  const handlePrint = () => window.print();

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent
        data-testid={PREPOST.labelModal}
        className="max-w-lg overflow-hidden p-0"
      >
        <DialogHeader className="border-b border-border px-5 py-3">
          <DialogTitle className="text-base">Etiqueta de Postagem — {p.servico_nome}</DialogTitle>
        </DialogHeader>

        <div id="etiqueta-print" className="p-5">
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
        </div>

        <div className="flex gap-2 border-t border-border px-5 py-3">
          <Button variant="outline" className="flex-1" onClick={handlePrint} data-testid="prepost-print-label">
            <Printer className="mr-2 h-4 w-4" /> Imprimir
          </Button>
          <Button className="flex-1 bg-blue-700 hover:bg-blue-800" onClick={handlePrint} data-testid={PREPOST.downloadLabel}>
            <Download className="mr-2 h-4 w-4" /> Baixar PDF
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  );
};
