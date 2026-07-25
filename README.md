# SupermarketHub

MVP de um sistema de supermercado para portfólio — API REST em Laravel 12 com Docker, filas Redis, testes, Swagger e arquitetura SOLID.

## Stack

| Camada | Tecnologia |
|---|---|
| Backend | PHP 8.2 + Laravel 12 |
| Banco | MySQL 8.0 |
| Cache/Fila | Redis 7.2 |
| Servidor | Nginx |
| Container | Docker Compose |

## Requisitos

- Docker e Docker Compose

## Como rodar

```bash
# 1. Subir a infraestrutura
docker compose up -d

# 2. Instalar dependências dentro do container
docker compose exec app composer install

# 3. Configurar ambiente
docker compose exec app cp .env.example .env
docker compose exec app php artisan key:generate

# 4. Rodar migrations
docker compose exec app php artisan migrate

# 5. Iniciar worker de filas (em outro terminal)
docker compose exec app php artisan queue:work redis --sleep=3 --tries=3

# 6. Acessar a API
curl http://localhost:8000/api/products
```

## Testes

```bash
docker compose exec app php artisan test
```

## Endpoints

| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/products` | Listar produtos |
| POST | `/api/products` | Criar produto |
| GET | `/api/products/{id}` | Exibir produto |
| PUT | `/api/products/{id}` | Atualizar produto |
| DELETE | `/api/products/{id}` | Remover produto |
| POST | `/api/sales` | Registrar venda |

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
      → StockService::verifyAndPrepare() — verifica estoque
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

## Commits (convencionais)

```
chore: configure Docker infrastructure
feat: initialize Laravel 12 project scaffold
feat: add product, sale, and stock models with database migrations
feat: add service layer with fiscal abstraction and repository pattern
feat: add REST controllers with form requests and Swagger documentation
feat: add async fiscal job, request ID middleware, and error handling
test: add unit and integration tests for sale workflow
chore: configure Swagger documentation and daily logging
refactor: extract repository interfaces to comply with DIP
refactor: extract stock verification into dedicated StockService
```
