"""Backend tests for InnovaEnvios Correios integration."""
import os
import pytest
import requests

BASE_URL = os.environ.get('REACT_APP_BACKEND_URL', '').rstrip('/') or 'https://api-correios-test.preview.emergentagent.com'
API = f"{BASE_URL}/api"


@pytest.fixture(scope="module")
def client():
    s = requests.Session()
    s.headers.update({"Content-Type": "application/json"})
    return s


# ---------------- Settings ----------------
class TestSettings:
    def test_get_default_settings(self, client):
        r = client.get(f"{API}/correios/settings")
        assert r.status_code == 200
        d = r.json()
        assert d["modo_demo"] is True
        assert d["conectado"] is False
        assert "codigo_acesso_mascarado" in d

    def test_save_and_mask_preserved(self, client):
        payload = {
            "usuario": "user_test",
            "codigo_acesso": "SECRETCODE123",
            "contrato": "9992200000",
            "cartao": "0069999999",
            "dr": "72",
            "ambiente": "homologacao",
            "modo_demo": True,
        }
        r = client.post(f"{API}/correios/settings", json=payload)
        assert r.status_code == 200
        d = r.json()
        assert d["tem_codigo_acesso"] is True
        assert d["codigo_acesso_mascarado"].endswith("D123") or d["codigo_acesso_mascarado"].endswith("E123")
        assert "SECRETCODE" not in d["codigo_acesso_mascarado"]

        # Re-save with empty codigo_acesso -> should preserve existing
        payload2 = {**payload, "codigo_acesso": ""}
        r2 = client.post(f"{API}/correios/settings", json=payload2)
        assert r2.status_code == 200
        assert r2.json()["tem_codigo_acesso"] is True

    def test_test_connection_demo(self, client):
        # Ensure modo_demo true
        client.post(f"{API}/correios/settings", json={
            "usuario": "u", "codigo_acesso": "c", "contrato": "1", "cartao": "1",
            "ambiente": "homologacao", "modo_demo": True,
        })
        r = client.post(f"{API}/correios/test-connection")
        assert r.status_code == 200
        d = r.json()
        assert d["sucesso"] is True
        assert d["modo"] == "demo"
        assert isinstance(d["servicos_liberados"], list)
        assert len(d["servicos_liberados"]) >= 1


# ---------------- Frete ----------------
class TestFrete:
    def test_calcular(self, client):
        r = client.post(f"{API}/frete/calcular", json={
            "cep_origem": "01001000",
            "cep_destino": "20040020",
            "peso_kg": 1.2,
            "servicos": ["03220", "03298", "04227"],
        })
        assert r.status_code == 200
        d = r.json()
        assert d["simulado"] is True
        assert len(d["resultados"]) == 3
        r0 = d["resultados"][0]
        for k in ("preco_balcao", "preco_contrato", "prazo_dias", "mais_rapido", "melhor_custo"):
            assert k in r0
        assert any(x["mais_rapido"] for x in d["resultados"])
        assert any(x["melhor_custo"] for x in d["resultados"])


# ---------------- Rastreamento ----------------
class TestRastreamento:
    def test_valid(self, client):
        r = client.get(f"{API}/rastreamento/OD123456789BR")
        assert r.status_code == 200
        d = r.json()
        assert len(d["eventos"]) >= 2
        assert "etapas" in d and "etapa_atual" in d

    def test_invalid_length(self, client):
        r = client.get(f"{API}/rastreamento/SHORT")
        assert r.status_code == 400


# ---------------- Pré-postagem ----------------
class TestPrepostagem:
    prepost_id = None

    def test_create(self, client):
        payload = {
            "remetente": {"nome": "Remetente Teste", "cidade": "São Paulo", "uf": "SP", "cep": "01001000"},
            "destinatario": {"nome": "TEST_Destinatario", "cidade": "Rio de Janeiro", "uf": "RJ", "cep": "20040020"},
            "servico": "03220",
            "peso_kg": 0.8,
            "itens": [{"descricao": "produto", "quantidade": 1, "valor": 50.0}],
            "valor_frete": 25.50,
        }
        r = client.post(f"{API}/prepostagem", json=payload)
        assert r.status_code == 200
        d = r.json()
        assert "id" in d
        co = d["codigo_objeto"]
        assert len(co) == 13
        assert co.endswith("BR")
        assert d["status"] == "CRIADA"
        assert d["etiqueta_pronta"] is False
        TestPrepostagem.prepost_id = d["id"]

    def test_etiqueta(self, client):
        assert TestPrepostagem.prepost_id
        r = client.post(f"{API}/prepostagem/{TestPrepostagem.prepost_id}/etiqueta")
        assert r.status_code == 200
        assert r.json()["etiqueta_pronta"] is True
        # verify persisted
        g = client.get(f"{API}/prepostagem/{TestPrepostagem.prepost_id}")
        assert g.status_code == 200
        assert g.json()["etiqueta_pronta"] is True

    def test_list_and_filter(self, client):
        r = client.get(f"{API}/prepostagens")
        assert r.status_code == 200
        d = r.json()
        assert "items" in d and d["total"] >= 1
        # filter
        r2 = client.get(f"{API}/prepostagens", params={"status": "CRIADA"})
        assert r2.status_code == 200
        assert all(x["status"] == "CRIADA" for x in r2.json()["items"])

    def test_cancelar(self, client):
        assert TestPrepostagem.prepost_id
        r = client.post(f"{API}/prepostagem/{TestPrepostagem.prepost_id}/cancelar")
        assert r.status_code == 200
        assert r.json()["status"] == "CANCELADA"
        g = client.get(f"{API}/prepostagem/{TestPrepostagem.prepost_id}")
        assert g.json()["status"] == "CANCELADA"


# ---------------- Dashboard ----------------
class TestDashboard:
    def test_stats(self, client):
        r = client.get(f"{API}/dashboard/stats")
        assert r.status_code == 200
        d = r.json()
        for k in ("total_postagens", "aguardando_postagem", "canceladas", "valor_total_frete", "conectado", "recentes"):
            assert k in d
        assert isinstance(d["recentes"], list)
