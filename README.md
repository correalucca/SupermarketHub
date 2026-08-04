# SupermarketHub

[![CI](https://github.com/correalucca/SupermarketHub/actions/workflows/ci.yml/badge.svg)](https://github.com/correalucca/SupermarketHub/actions/workflows/ci.yml)

MVP de sistema de supermercado para portfólio — API REST em **Laravel 12** com arquitetura em camadas, filas **Redis**, testes unitários e de integração, documentação **OpenAPI (Swagger)** e frontend em **React + TypeScript**.

## Funcionalidades

- Autenticação com Laravel Sanctum (`register`, `login`, `logout`, `me`)
- CRUD de produtos com validação de SKU único
- Registro de vendas com controle **transacional** de estoque (lock pessimista)
- Emissão de nota fiscal assíncrona via fila (`IssueFiscalDocumentJob` + provedor mock)
- Logs estruturados e `X-Request-Id` em toda a API
- Contratos de erro JSON padronizados (`{ success, message, code }`)

## Stack

| Camada          | Tecnologia                     |
| --------------- | ------------------------------ |
| Backend         | PHP 8.2 + Laravel 12           |
| Frontend        | React 19 + TypeScript + Vite   |
| Banco de dados  | MySQL 8.0 (SQLite em testes)   |
| Cache/Fila      | Redis 7.2                      |
| Servidor web    | Nginx                          |
| Contêineres     | Docker Compose                 |
| Documentação    | OpenAPI 3 + l5-swagger         |

## Arquitetura

O código é organizado em camadas seguindo os princípios SOLID e a inversão de dependência:

| Camada            | Pasta                                  | Responsabilidade                                  |
| ----------------- | -------------------------------------- | ------------------------------------------------- |
| Interface         | `app/Http/Controllers`, `app/Http/Requests` | HTTP, validação e contratos de resposta          |
| Aplicação         | `app/Services`                         | Orquestração de casos de uso (venda, estoque)     |
| Domínio           | `app/Exceptions`, `app/Enums`, `app/Models` | Regras de negócio (`InsufficientStockException`) |
| Infraestrutura    | `app/Repositories`, `app/Jobs`         | Persistência, filas e serviços externos           |
| Contratos         | `app/Contracts`                        | Interfaces que invertem a dependência entre camadas |

Fluxo de uma venda:

```text
POST /api/sales
  → SaleRequest (validação)
  → SaleController::store
    → SaleService::createSale()
      → StockService::verifyAndPrepare() — verifica estoque (lockForUpdate)
      → DB::transaction()
        → SaleRepository::create()
        → SaleItem::create() para cada item
        → ProductRepository::decrementStock()
        → StockMovement::create() (type='out')
      → IssueFiscalDocumentJob::dispatch() — fila Redis
        → MockFiscalProvider::emitInvoice() — protocolo NF-XXXX
```

## Requisitos

- Docker 24+ com Docker Compose v2
- Git

## Como rodar

### 1. Subir a infraestrutura

```bash
docker compose up -d
```

Sobe os serviços: `app` (PHP-FPM), `nginx` (proxy da API), `db` (MySQL 8), `redis` e o `frontend-dev` (Vite). Aguarde o serviço `db` ficar *healthy*.

### 2. Instalar as dependências do backend

```bash
docker compose exec app composer install
```

### 3. Configurar o ambiente

```bash
docker compose exec app cp .env.example .env
```

Edite o `.env` (em `src/.env`) apontando para os serviços do Docker:

```env
APP_NAME=SupermarketHub
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=supermarket
DB_USERNAME=supermarket
DB_PASSWORD=root

QUEUE_CONNECTION=redis
REDIS_HOST=redis
REDIS_PORT=6379
CACHE_STORE=redis
```

> **Alternativa (SQLite):** para desenvolvimento rápido, crie `database/database.sqlite` e use `DB_CONNECTION=sqlite` no lugar do bloco MySQL.

### 4. Gerar a chave da aplicação, rodar as migrations e gerar as docs Swagger

```bash
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan l5-swagger:generate
```

### 5. Iniciar o worker de filas (emissão de nota fiscal)

Em um segundo terminal:

```bash
docker compose exec app php artisan queue:work redis --sleep=3 --tries=3
```

### 6. Frontend

- **Desenvolvimento** — o serviço `frontend-dev` já instala as dependências e inicia o Vite no `docker compose up`:

```bash
docker compose up -d frontend-dev
```

- **Produção** (build estático servido por Nginx):

```bash
docker compose up -d frontend
```

### Acessos

| Recurso             | URL                              |
| ------------------- | -------------------------------- |
| Frontend (dev)      | http://localhost:5173            |
| Frontend (produção) | http://localhost:3000            |
| API                 | http://localhost:8000/api        |
| Swagger UI          | http://localhost:8000/api/documentation |

## Fluxo rápido de uso

### 1. Registrar usuário

```bash
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Admin","email":"admin@example.com","password":"secret123"}'
```

### 2. Login (capture o `token` da resposta)

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"secret123"}'
```

### 3. Criar produto (use o `TOKEN` obtido)

```bash
curl -X POST http://localhost:8000/api/products \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"sku":"ARROZ-1","name":"Arroz 5kg","price":25.00,"category":"Alimentos","stock_quantity":100}'
```

### 4. Registrar venda

O estoque é baixado de forma transacional e a nota fiscal é emitida de forma assíncrona via fila:

```bash
curl -X POST http://localhost:8000/api/sales \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"items":[{"product_id":1,"quantity":3}]}'
```

### 5. Conferir o resultado

O protocolo fiscal (`NF-XXXX`) aparece no log do `queue:work`, e o estoque reduzido pode ser conferido em `GET /api/products`.

## Testes

### Backend (Laravel)

A suíte roda por padrão em **SQLite em memória** (configuração em `phpunit.xml`) e também é validada contra **MySQL real** (mesmo comportamento do CI), o que exercita `lockForUpdate` (FOR UPDATE), constraints e tipos de produção.

```bash
# Suíte completa em SQLite (padrão)
docker compose exec app php artisan test

# Suíte completa em MySQL real (integração)
docker compose exec app bash -c "DB_CONNECTION=mysql DB_HOST=db DB_PORT=3306 DB_DATABASE=supermarket_test DB_USERNAME=root DB_PASSWORD=root php artisan test"

# Cobertura (98.8% — falha abaixo de 90%)
docker compose exec app php artisan test --coverage --min=90
```

Cobertura atual:

- **Unit:** `StockService` (verificação de estoque, lock e totais), `SaleService` (transação, rollback, persistência e dispatch do job) e `IssueFiscalDocumentJob` (emissão + persistência do protocolo).
- **Feature (API):** autenticação completa, CRUD de produtos, fluxo de venda (estoque insuficiente, rollback e oversell), contratos de erro `401/404/422/500`, middleware `X-Request-Id`, persistência do protocolo fiscal e documentação Swagger.
- **Concorrência (MySQL):** valida que `findWithLock` bloqueia uma segunda transação enquanto a linha está travada (prevenção de oversell).

### Frontend (React)

Suíte com **Vitest + Testing Library** (25 testes). Como o bind-mount do `frontend-dev` torna o I/O das dependências muito lento no Windows, os testes rodam num container dedicado com `node_modules` no filesystem da imagem:

```bash
docker compose run --rm frontend-tests
```

## Endpoints da API

| Método | Rota                        | Descrição         | Auth         |
| ------ | --------------------------- | ----------------- | ------------ |
| POST   | `/api/register`             | Cadastrar usuário | —            |
| POST   | `/api/login`                | Login (token)     | —            |
| POST   | `/api/logout`               | Revogar token     | Bearer Token |
| GET    | `/api/me`                   | Dados do usuário  | Bearer Token |
| GET    | `/api/products`             | Listar produtos   | Bearer Token |
| POST   | `/api/products`             | Criar produto     | Bearer Token |
| GET    | `/api/products/{product}`   | Exibir produto    | Bearer Token |
| PUT    | `/api/products/{product}`   | Atualizar produto | Bearer Token |
| DELETE | `/api/products/{product}`   | Remover produto   | Bearer Token |
| POST   | `/api/sales`                | Registrar venda   | Bearer Token |

**Exemplo de venda:**

```bash
curl -X POST http://localhost:8000/api/sales \
  -H "Authorization: Bearer SEU_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"items":[{"product_id":1,"quantity":3}]}'
```

```json
{
  "success": true,
  "message": "Venda finalizada com sucesso.",
  "data": {
    "id": 1,
    "total": 75.00,
    "status": "completed"
  }
}
```

**Contratos de erro** (JSON `{ success, message, code }`):

| Situação                       | HTTP |
| ------------------------------ | ---- |
| Não autenticado                | 401  |
| Recurso não encontrado/rota    | 404  |
| Erro de validação              | 422  |
| Estoque insuficiente           | 422  |
| Erro interno                   | 500  |

## Documentação Swagger

As rotas são anotadas com atributos OpenAPI (`#[OA\...]`). Para (re)gerar o documento:

```bash
docker compose exec app php artisan l5-swagger:generate
```

O JSON é gerado em `storage/api-docs/api-docs.json` e a UI fica disponível em http://localhost:8000/api/documentation.

## Estrutura de pastas (resumo)

```text
src/
├── app/
│   ├── Contracts/               # Interfaces (repositórios, serviços, provedor fiscal)
│   ├── Exceptions/              # Exceções de domínio (InsufficientStockException)
│   ├── Enums/                   # StockMovementType
│   ├── Http/
│   │   ├── Controllers/         # Auth, Product, Sale
│   │   ├── Middleware/          # RequestIdMiddleware
│   │   └── Requests/            # ProductRequest, SaleRequest
│   ├── Jobs/                    # IssueFiscalDocumentJob
│   ├── Models/                  # Product, Sale, SaleItem, StockMovement, User
│   ├── Repositories/            # Persistência (Eloquent)
│   └── Services/                # Casos de uso (Sale, Stock) + MockFiscalProvider
├── config/
├── database/                    # Migrations e factories
├── routes/
├── tests/
│   ├── Feature/                 # Testes de API
│   └── Unit/                    # Testes de serviços e jobs
└── phpunit.xml
```

## Comandos úteis

```bash
# Logs da API
docker compose logs -f app

# Tinker (REPL)
docker compose exec app php artisan tinker

# Limpar caches
docker compose exec app php artisan optimize:clear
```

## Timeline do projeto

A evolução do projeto foi entregue em **etapas/camadas**, refletida no histórico de commits (nomenclatura convencional, ex.: `feat:`, `fix:`, `test:`, `build:`, `docs:`, `ci:`):

| Etapa | Camada            | Entregas                                                                                      |
| ----- | ----------------- | --------------------------------------------------------------------------------------------- |
| 1     | **Fundação**      | Scaffold Laravel 12 + Docker Compose (PHP-FPM, Nginx, MySQL 8, Redis), migrations de usuários, produtos, estoque e vendas |
| 2     | **API**           | Autenticação Sanctum (`register`, `login`, `logout`, `me`), CRUD de produtos com SKU único, vendas com controle transacional de estoque e `lockForUpdate` |
| 3     | **Robustez**      | `InsufficientStockException` de domínio (422), contratos de erro JSON padronizados, middleware `X-Request-Id`, fila Redis para emissão de nota fiscal |
| 4     | **Documentação**  | OpenAPI 3 com l5-swagger (atributos `#[OA\...]`) e UI em `/api/documentation`                  |
| 5     | **Testes backend**| Unit (serviços e job) + Feature (API): 54 testes, contratos `401/404/422/500`, rollback/oversell, integração com MySQL real, teste de concorrência (row lock) e cobertura de 98.8% |
| 6     | **CI/CD**         | GitHub Actions: suíte SQLite com cobertura mínima de 90%, suíte de integração MySQL 8 e job do frontend (testes + build) |
| 7     | **Frontend**      | SPA React 19 + TypeScript + Vite (login, cadastro, CRUD de produtos e nova venda), com suíte de 25 testes (Vitest + Testing Library) |

> Cada camada foi commitada de forma atômica e com mensagem convencional, permitindo rastrear a linha do tempo do projeto pelo `git log`.
