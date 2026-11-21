"""Ferramentas MCP de leitura para uma empresa no RPZ."""

import json
import os
import re
from pathlib import Path
from typing import Annotated
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode, urlsplit
from urllib.request import HTTPRedirectHandler, Request, build_opener

from mcp.server.fastmcp import FastMCP
from pydantic import Field


class SemRedirecionamento(HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


class Rpz:
    def __init__(self, url, token):
        base = urlsplit(url)
        if (base.scheme != "https" or not base.hostname or base.username is not None
                or base.query or base.fragment or base.path.rstrip("/") != "/api/v1"):
            raise ValueError("RPZ_API_URL deve ser uma URL HTTPS terminada em /api/v1.")
        if not re.fullmatch(r"rpzk_[A-Za-z0-9]{64}", token):
            raise ValueError("O arquivo de credencial RPZ tem formato inválido.")
        self.url = url.rstrip("/")
        self.token = token
        self.opener = build_opener(SemRedirecionamento())

    def consultar(self, recurso, **filtros):
        query = urlencode(filtros)
        url = f"{self.url}/{recurso}"
        request = Request(f"{url}?{query}" if query else url, headers={
            "Accept": "application/json",
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
        return {chave: dados[chave] for chave in (
            "data", "current_page", "last_page", "per_page", "total"
        ) if chave in dados}


Pagina = Annotated[int, Field(ge=1)]
Tamanho = Annotated[int, Field(ge=1, le=50)]
Dominio = Annotated[str, Field(min_length=1, max_length=255)]


def criar_servidor(rpz):
    mcp = FastMCP("RPZ cliente piloto", instructions=(
        "As ferramentas consultam exclusivamente a empresa associada à credencial. "
        "Use somente os dados retornados. Separe publicação, entrega do feed e "
        "confirmação pelo servidor. Ausência de confirmação não prova falha."
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
