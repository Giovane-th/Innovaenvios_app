import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { api, brl } from "@/lib/api";
import { useSettings } from "@/context/SettingsContext";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import {
  Package, Clock, Ban, Wallet, ArrowRight, Calculator, Search, FilePlus2, PlugZap,
} from "lucide-react";

const StatCard = ({ icon: Icon, label, value, accent }) => (
  <Card className="animate-fade-up p-5">
    <div className="flex items-center justify-between">
      <div>
        <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">{label}</p>
        <p className="mt-2 text-2xl font-extrabold tracking-tight text-foreground">{value}</p>
      </div>
      <div className={`flex h-11 w-11 items-center justify-center rounded-xl ${accent}`}>
        <Icon className="h-5 w-5" />
      </div>
    </div>
  </Card>
);

export default function Dashboard() {
  const [stats, setStats] = useState(null);
  const { settings } = useSettings();
  const navigate = useNavigate();

  useEffect(() => {
    api.get("/dashboard/stats").then(({ data }) => setStats(data)).catch(() => {});
  }, []);

  const actions = [
    { label: "Calcular Frete", desc: "Compare SEDEX, PAC e Mini Envios", icon: Calculator, to: "/frete", color: "bg-blue-600" },
    { label: "Rastrear Objeto", desc: "Acompanhe entregas em tempo real", icon: Search, to: "/rastreamento", color: "bg-slate-800" },
    { label: "Nova Pré-Postagem", desc: "Gere etiquetas do seu contrato", icon: FilePlus2, to: "/pre-postagem", color: "bg-amber-500" },
  ];

  return (
    <div className="space-y-8" data-testid="dashboard-page">
      {/* Hero */}
      <div className="relative overflow-hidden rounded-2xl bg-slate-950">
        <img
          src="https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?crop=entropy&cs=srgb&fm=jpg&q=85&w=1400"
          alt="Centro logístico"
          className="absolute inset-0 h-full w-full object-cover opacity-25"
        />
        <div className="relative z-10 max-w-xl p-6 sm:p-10">
          <p className="text-xs font-bold uppercase tracking-[0.2em] text-amber-400">Painel de Despacho Logístico</p>
          <h1 className="mt-2 text-2xl font-extrabold tracking-tight text-white sm:text-3xl">
            Bem-vindo à central InnovaEnvios
          </h1>
          <p className="mt-3 text-sm leading-relaxed text-slate-300">
            Calcule fretes com o preço do seu contrato, rastreie encomendas e emita etiquetas oficiais dos
            Correios — tudo em um só lugar.
          </p>
          {!settings?.conectado && (
            <Button
              onClick={() => navigate("/contrato")}
              className="mt-5 rounded-full bg-amber-500 text-slate-950 hover:bg-amber-400"
              data-testid="dashboard-connect-cta"
            >
              <PlugZap className="mr-2 h-4 w-4" /> Conectar meu contrato Correios
            </Button>
          )}
        </div>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard icon={Package} label="Pré-Postagens" value={stats?.total_postagens ?? "—"} accent="bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300" />
        <StatCard icon={Clock} label="Aguardando Postagem" value={stats?.aguardando_postagem ?? "—"} accent="bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300" />
        <StatCard icon={Ban} label="Canceladas" value={stats?.canceladas ?? "—"} accent="bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300" />
        <StatCard icon={Wallet} label="Frete Acumulado" value={stats ? brl(stats.valor_total_frete) : "—"} accent="bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300" />
      </div>

      {/* Quick actions */}
      <div>
        <h2 className="mb-3 text-lg font-bold tracking-tight">Ações rápidas</h2>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          {actions.map((a) => (
            <button
              key={a.to}
              onClick={() => navigate(a.to)}
              data-testid={`quick-${a.to.replace("/", "")}`}
              className="group flex items-start gap-4 rounded-xl border border-border bg-card p-5 text-left transition-all hover:border-blue-400 hover:shadow-lg"
            >
              <div className={`flex h-11 w-11 items-center justify-center rounded-xl text-white ${a.color}`}>
                <a.icon className="h-5 w-5" />
              </div>
              <div className="flex-1">
                <p className="font-semibold text-foreground">{a.label}</p>
                <p className="text-xs text-muted-foreground">{a.desc}</p>
              </div>
              <ArrowRight className="h-4 w-4 text-muted-foreground transition-transform group-hover:translate-x-1" />
            </button>
          ))}
        </div>
      </div>

      {/* Recent */}
      <div>
        <h2 className="mb-3 text-lg font-bold tracking-tight">Pré-postagens recentes</h2>
        <Card className="divide-y divide-border">
          {stats?.recentes?.length ? (
            stats.recentes.map((r) => (
              <div key={r.id} className="flex items-center justify-between px-5 py-3">
                <div className="flex items-center gap-3">
                  <span className="font-mono text-sm font-semibold">{r.codigo_objeto}</span>
                  <span className="text-sm text-muted-foreground">{r.destinatario?.nome || "—"}</span>
                </div>
                <span className="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-bold text-blue-700 dark:bg-blue-950/50 dark:text-blue-300">
                  {r.servico_tag}
                </span>
              </div>
            ))
          ) : (
            <div className="px-5 py-10 text-center text-sm text-muted-foreground">
              Nenhuma pré-postagem criada ainda. Comece emitindo sua primeira etiqueta.
            </div>
          )}
        </Card>
      </div>
    </div>
  );
}
