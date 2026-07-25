# SupermarketHub

MVP de um sistema de supermercado para portfólio — API REST em Laravel 12 com Docker, filas Redis, testes, Swagger e arquitetura SOLID.  
Frontend em React + TypeScript com Vite.

## Stack

| Camada     | Tecnologia                      |
| ---------- | ------------------------------- |
| Backend    | PHP 8.2 + Laravel 12            |
| Frontend   | React 19 + TypeScript + Vite    |
| Banco      | MySQL 8.0                       |
| Cache/Fila | Redis 7.2                       |
| Servidor   | Nginx, Nginx (frontend prod)    |
| Container  | Docker Compose                  |

## Requisitos

- Docker e Docker Compose

## Como rodar

```bash
# 1. Subir toda a infraestrutura
docker compose up -d

# 2. Instalar dependências do backend
docker compose exec app composer install

# 3. Configurar ambiente
docker compose exec app cp .env.example .env
docker compose exec app php artisan key:generate

# 4. Rodar migrations
docker compose exec app php artisan migrate

# 5. Iniciar worker de filas (em outro terminal)
docker compose exec app php artisan queue:work redis --sleep=3 --tries=3

# 6. Instalar dependências do frontend e iniciar dev server
docker compose run --rm frontend-dev npm install
docker compose up -d frontend-dev

# Acessar:
#   Frontend (dev): http://localhost:5173
#   API:            http://localhost:8000/api
#   Swagger:        http://localhost:8000/api/documentation

# Para produção (build estático servido por Nginx):
docker compose up -d frontend
# Acessar: http://localhost:3000
```

## Testes

```bash
docker compose exec app php artisan test
```

## Endpoints da API

| Método | Rota                | Descrição         | Auth         |
| ------ | ------------------- | ----------------- | ------------ |
| POST   | `/api/register`     | Cadastrar usuário | —            |
| POST   | `/api/login`        | Login             | —            |
| POST   | `/api/logout`       | Logout            | Bearer Token |
| GET    | `/api/me`            | Dados do usuário  | Bearer Token |
| GET    | `/api/products`      | Listar produtos   | Bearer Token |
| POST   | `/api/products`      | Criar produto     | Bearer Token |
| GET    | `/api/products/{id}` | Exibir produto    | Bearer Token |
| PUT    | `/api/products/{id}` | Atualizar produto | Bearer Token |
| DELETE | `/api/products/{id}` | Remover produto   | Bearer Token |
| POST   | `/api/sales`         | Registrar venda   | Bearer Token |

## Documentação Swagger

```bash
docker compose exec app php artisan l5-swagger:generate
```

Acessar: http://localhost:8000/api/documentation

## Arquitetura

```
POST /api/sales
  → SaleRequest (validação)
  → SaleController::store
    → SaleService::createSale()
      → StockService::verifyAndPrepare() — verifica estoque (com lockForUpdate)
      → DB::transaction()
        → SaleRepository::create()
        → SaleItem::create() para cada item
        → ProductRepository::decrementStock()
        → StockMovement::create() (type='out')
      → Log: 'Venda finalizada'
      → IssueFiscalDocumentJob::dispatch() — async para Redis
        → MockFiscalProvider::emitInvoice() — gera protocolo NF-XXXX
        → Log: 'Job de nota fiscal executado'
```
