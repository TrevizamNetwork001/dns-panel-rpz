"""Ferramentas MCP de leitura do RPZ."""

import json
import os
import re
from datetime import datetime
from pathlib import Path
from typing import Annotated, Literal
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode, urlsplit
from urllib.request import HTTPRedirectHandler, Request, build_opener
from zoneinfo import ZoneInfo

from mcp.server.fastmcp import FastMCP
from pydantic import Field


HORARIO_BRASILIA = ZoneInfo("America/Sao_Paulo")
CAMPOS_DE_HORARIO = {
    "em", "publicada_em", "ultimo_acesso_feed_em", "expira_em",
    "created_at", "updated_at", "atualizado_em", "last_sync_at",
    "dominios_atualizados_em", "elegivel_para_sincronizar_a_partir_de",
}


def em_horario_de_brasilia(value):
    if isinstance(value, list):
        return [em_horario_de_brasilia(item) for item in value]
    if not isinstance(value, dict):
        return value
    converted = {}
    for key, item in value.items():
        if key in CAMPOS_DE_HORARIO and isinstance(item, str):
            timestamp = datetime.fromisoformat(item)
            if timestamp.tzinfo is not None:
                item = timestamp.astimezone(HORARIO_BRASILIA).isoformat(timespec="seconds")
        converted[key] = em_horario_de_brasilia(item)
    return converted


class SemRedirecionamento(HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


class Rpz:
    def __init__(self, url, token):
        base = urlsplit(url)
        if (base.scheme != "https" or not base.hostname or base.username is not None
                or base.query or base.fragment or base.path.rstrip("/") != "/api/v1"):
            raise ValueError("RPZ_API_URL deve ser uma URL HTTPS terminada em /api/v1.")
        if not re.fullmatch(r"(?:rpzk_[A-Za-z0-9]{64}|rpzo_[a-f0-9]{64})", token):
            raise ValueError("O arquivo de credencial RPZ tem formato inválido.")
        self.administrativo = token.startswith("rpzo_")
        self.url = url.rstrip("/")
        self.token = token
        self.opener = build_opener(SemRedirecionamento())

    def consultar(self, recurso, *, proposta=None, **filtros):
        query = urlencode(filtros)
        url = f"{self.url}/{recurso}"
        payload = json.dumps(proposta).encode() if proposta is not None else None
        request = Request(f"{url}?{query}" if query else url, data=payload, headers={
            "Accept": "application/json",
            "Content-Type": "application/json",
            "Authorization": f"Bearer {self.token}",
        })
        try:
            with self.opener.open(request, timeout=10) as response:
                dados = json.load(response)
        except HTTPError as error:
            error.close()
            mensagem = {
                401: "Confira se a credencial de leitura está válida.",
                403: "A credencial não tem acesso a esta consulta.",
                404: "Confira a URL e se a API de integração está publicada.",
                429: "Aguarde antes de repetir a consulta.",
            }.get(error.code, "Confira a disponibilidade da API.")
            raise RuntimeError(f"RPZ retornou HTTP {error.code}. {mensagem}") from None
        except (URLError, OSError, ValueError):
            raise RuntimeError("Falha de conexão ou resposta inválida da API RPZ.") from None
        if not isinstance(dados, dict) or "data" not in dados:
            raise RuntimeError("A API RPZ respondeu em um formato inesperado.")
        result = {chave: dados[chave] for chave in (
            "data", "current_page", "last_page", "per_page", "total",
            "empresa", "publicacao_empresa", "dominio", "excecao_aplicavel", "lista",
        ) if chave in dados}
        return em_horario_de_brasilia(result) if self.administrativo else result


Pagina = Annotated[int, Field(ge=1)]
Tamanho = Annotated[int, Field(ge=1, le=50)]
Dominio = Annotated[str, Field(min_length=1, max_length=255)]


RESPOSTA_SINCRONIZACAO = (
    "Comece pelo que o RPZ registrou e indique a zona a que os dados pertencem. "
    "Se a última entrega registrada corresponder à revisão publicada dessa zona, use o título "
    "«Entrega em dia» e informe o servidor, a revisão e o horário do download. "
    "Se houver somente registro de acesso ao feed, descreva-o como acesso. "
    "Se faltar o registro do serial carregado, use: «O painel registra o download. "
    "Para saber se o Unbound já está usando essa revisão, consulte o próprio DNS.» "
    "Ajuste a primeira frase ao dado disponível se faltar registro de download. "
    "Quando houver confirmação do resolvedor, informe o serial e o horário registrados. "
    "Cite a zona individual quando ela for relevante para a pergunta. "
)


def criar_servidor(rpz):
    if rpz.administrativo:
        return criar_servidor_operacional(rpz)

    mcp = FastMCP("RPZ cliente piloto", instructions=(
        "As ferramentas consultam exclusivamente a empresa associada à credencial. "
        "Use somente os dados retornados. " + RESPOSTA_SINCRONIZACAO
    ))

    @mcp.tool()
    def listar_listas(pagina: Pagina = 1, por_pagina: Tamanho = 15) -> dict:
        """Lista as políticas RPZ visíveis para a empresa, com paginação."""
        return rpz.consultar("listas", page=pagina, per_page=por_pagina)

    @mcp.tool()
    def listar_servidores(pagina: Pagina = 1, por_pagina: Tamanho = 15) -> dict:
        """Consulta servidores, publicação individual, entrega e confirmação informada."""
        return rpz.consultar("servidores", page=pagina, per_page=por_pagina)

    @mcp.tool()
    def ver_publicacao() -> dict:
        """Consulta o estado, a revisão e o serial da zona RPZ da empresa."""
        return rpz.consultar("publicacao")

    @mcp.tool()
    def consultar_dominio(dominio: Dominio, pagina: Pagina = 1) -> dict:
        """Busca regras para um domínio exato. O resultado descreve as listas visíveis."""
        return rpz.consultar("bloqueios", dominio=dominio, page=pagina, per_page=15)

    return mcp


def criar_servidor_operacional(rpz):
    mcp = FastMCP("RPZ operação", instructions=(
        "Para consultas por nome de cliente, primeiro busque a empresa. Use listar_listas_empresa para responder quais listas ela tem. "
        "Se houver ambiguidade, peça ao operador para escolher. "
        + RESPOSTA_SINCRONIZACAO
        + "Os horários retornados já estão em America/Sao_Paulo. Mostre horas como HH:MM:SS, sem frações de segundo, em Brasília. Inclua a data quando ela for relevante. "
        "Antes de preparar uma exceção, use diagnosticar_dominio. Mostre somente o nome da empresa, sem o ID, e indique domínio, subdomínios, motivo e validade. Use um título curto, ícones e negrito para a informação principal. "
        "Peça ao administrador para responder confirma diretamente à mensagem da prévia no Telegram. Inclua /rpz_confirmar com o ID da solicitação na prévia como referência e alternativa."
    ))

    @mcp.tool()
    def buscar_empresas(
        nome: Annotated[str, Field(min_length=1, max_length=150)],
        pagina: Pagina = 1,
    ) -> dict:
        """Busca empresas por nome ou nome fantasia. Use o ID retornado na consulta de sincronização."""
        return rpz.consultar("operacao/empresas", nome=nome, page=pagina)

    @mcp.tool()
    def ver_sincronizacao(empresa_id: Annotated[int, Field(ge=1)], pagina: Pagina = 1) -> dict:
        """Consulta publicação, último acesso, última entrega e confirmação por servidor da empresa."""
        return rpz.consultar(f"operacao/empresas/{empresa_id}/sincronizacao", page=pagina)

    @mcp.tool()
    def listar_vinculos_listas(
        empresa_id: Annotated[int, Field(ge=1)],
        pagina: Pagina = 1,
        servidor_id: Annotated[int, Field(ge=1)] | None = None,
    ) -> dict:
        """Lista vínculos por servidor. Uma lista nula indica servidor sem lista vinculada."""
        filtros = {"page": pagina}
        if servidor_id is not None:
            filtros["servidor_id"] = servidor_id
        return rpz.consultar(f"operacao/empresas/{empresa_id}/vinculos-listas", **filtros)

    @mcp.tool()
    def buscar_listas_disponiveis(
        empresa_id: Annotated[int, Field(ge=1)],
        nome: Annotated[str, Field(min_length=1, max_length=150)],
        pagina: Pagina = 1,
    ) -> dict:
        """Busca listas compartilhadas ou próprias da empresa para um possível vínculo."""
        return rpz.consultar(f"operacao/empresas/{empresa_id}/listas-disponiveis", nome=nome, page=pagina)

    @mcp.tool()
    def buscar_dominio_em_listas(dominio: Dominio, pagina: Pagina = 1) -> dict:
        """Busca o domínio exato em todas as listas do RPZ. Informe o nome e status da lista e se a regra está ativa."""
        return rpz.consultar("operacao/dominios", dominio=dominio, page=pagina)

    @mcp.tool()
    def listar_listas_empresa(
        empresa_id: Annotated[int, Field(ge=1)],
        pagina: Pagina = 1,
    ) -> dict:
        """Responde quais listas o cliente tem. Mostre as listas próprias e as vinculadas, com os nomes dos servidores, status e datas. Elegibilidade de sincronização indica o horário mais cedo, não uma execução garantida."""
        return rpz.consultar(f"operacao/empresas/{empresa_id}/listas", page=pagina)

    @mcp.tool()
    def consultar_lista_manual(
        empresa_id: Annotated[int, Field(ge=1)],
        lista_id: Annotated[int, Field(ge=1)],
        pagina: Pagina = 1,
    ) -> dict:
        """Consulta origem, status, datas de atualização e domínios de uma lista manual. Encontre o ID com buscar_listas_disponiveis."""
        return rpz.consultar(f"operacao/empresas/{empresa_id}/listas/{lista_id}/conteudo", page=pagina)

    @mcp.tool()
    def preparar_cadastro_lista_manual(
        empresa_id: Annotated[int, Field(ge=1)],
        operacao: Literal["cadastrar", "alterar", "remover"],
        motivo: Annotated[str, Field(min_length=1, max_length=255)],
        lista_id: Annotated[int, Field(ge=1)] | None = None,
        nome: Annotated[str, Field(min_length=1, max_length=255)] | None = None,
        descricao: str | None = None,
        status: Literal["active", "inactive"] | None = None,
    ) -> dict:
        """Prepara cadastro, alteração ou remoção de lista manual própria. Na remoção, destaque os domínios e vínculos que serão excluídos. Mostre o comando /rpz_confirmar com o ID recebido. Use descrição vazia para limpá-la."""
        proposta = {"operacao": operacao, "motivo": motivo}
        proposta.update({
            key: value for key, value in {
                "lista_id": lista_id, "nome": nome, "descricao": descricao, "status": status,
            }.items() if value is not None
        })
        return rpz.consultar(f"operacao/empresas/{empresa_id}/solicitacoes-lista-manual", proposta=proposta)

    @mcp.tool()
    def preparar_dominio_lista_manual(
        empresa_id: Annotated[int, Field(ge=1)],
        lista_id: Annotated[int, Field(ge=1)],
        dominio: Annotated[str, Field(min_length=1, max_length=255)],
        operacao: Literal["incluir", "remover"],
        motivo: Annotated[str, Field(min_length=1, max_length=255)],
    ) -> dict:
        """Prepara a inclusão ou remoção em lista manual própria da empresa. Mostre servidores, empresas e o comando /rpz_confirmar com o ID da prévia."""
        return rpz.consultar(
            f"operacao/empresas/{empresa_id}/listas/{lista_id}/solicitacoes-dominio",
            proposta={"dominio": dominio, "operacao": operacao, "motivo": motivo},
        )

    @mcp.tool()
    def preparar_vinculo_lista(
        empresa_id: Annotated[int, Field(ge=1)],
        servidor_id: Annotated[int, Field(ge=1)],
        lista_id: Annotated[int, Field(ge=1)],
        operacao: Literal["vincular", "desvincular"],
        motivo: Annotated[str, Field(min_length=1, max_length=255)],
    ) -> dict:
        """Prepara vínculo ou desvínculo. Exiba a prévia e peça que o operador responda confirma."""
        return rpz.consultar(
            f"operacao/empresas/{empresa_id}/solicitacoes-vinculo",
            proposta={"servidor_id": servidor_id, "lista_id": lista_id, "operacao": operacao, "motivo": motivo},
        )

    @mcp.tool()
    def diagnosticar_dominio(empresa_id: Annotated[int, Field(ge=1)], dominio: Dominio, pagina: Pagina = 1) -> dict:
        """Encontra listas candidatas e exceção aplicável na configuração da zona da empresa."""
        return rpz.consultar(
            f"operacao/empresas/{empresa_id}/diagnostico-dominio",
            dominio=dominio, page=pagina,
        )

    @mcp.tool()
    def listar_excecoes(empresa_id: Annotated[int, Field(ge=1)], pagina: Pagina = 1) -> dict:
        """Lista exceções de domínio desta empresa e os respectivos motivos."""
        return rpz.consultar(f"operacao/empresas/{empresa_id}/excecoes", page=pagina)

    @mcp.tool()
    def preparar_remocao_excecao(
        empresa_id: Annotated[int, Field(ge=1)],
        dominio: Dominio,
        motivo: Annotated[str, Field(min_length=1, max_length=255)],
    ) -> dict:
        """Prepara a remoção de uma exceção existente sem aplicar. Mostre o comando de confirmação."""
        return rpz.consultar(
            f"operacao/empresas/{empresa_id}/solicitacoes-excecao",
            proposta={"dominio": dominio, "motivo": motivo, "operacao": "remover"},
        )

    @mcp.tool()
    def preparar_excecao(
        empresa_id: Annotated[int, Field(ge=1)],
        dominio: Dominio,
        motivo: Annotated[str, Field(min_length=1, max_length=255)],
    ) -> dict:
        """Prepara uma exceção por empresa, sem aplicar. Mostre a prévia e o comando de confirmação."""
        return rpz.consultar(
            f"operacao/empresas/{empresa_id}/solicitacoes-excecao",
            proposta={"dominio": dominio, "motivo": motivo},
        )

    return mcp


if __name__ == "__main__":
    try:
        rpz = Rpz(os.environ["RPZ_API_URL"], Path(os.environ["RPZ_TOKEN_FILE"]).read_text().strip())
    except KeyError:
        raise SystemExit("Configure RPZ_API_URL e RPZ_TOKEN_FILE.") from None
    except OSError:
        raise SystemExit("Não foi possível ler o arquivo da credencial RPZ.") from None
    except ValueError as error:
        raise SystemExit(str(error)) from None
    criar_servidor(rpz).run(transport="stdio")
