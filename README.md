# Financial Management & Transaction Reconciliation

Sistema web para gerenciamento de contas financeiras, transações e conciliação bancária.
Desenvolvido como projeto de portfólio profissional.

## Stack

| Camada | Tecnologia |
|--------|------------|
| Backend | PHP 8.2, Symfony 7.2, Doctrine ORM |
| Banco | PostgreSQL 16 |
| Frontend | React 19, TypeScript, Vite, Tailwind CSS v4 |
| Auth | JWT (lexik/jwt-authentication-bundle) |
| Infraestrutura | Docker, Docker Compose, Nginx |
| Testes | PHPUnit |

## Pré-requisitos

- Docker Engine ≥ 27
- Docker Compose v2
- (Opcional, para desenvolvimento local) PHP 8.2+, Composer, Node.js 22+

## Instalação e execução

### 1. Clone o repositório

```bash
git clone <repo-url>
cd financial-management
```

### 2. Configure as variáveis de ambiente

```bash
cp .env.example .env
# Edite .env com seus valores (APP_SECRET, JWT_PASSPHRASE, etc.)
```

### 3. Gere as chaves JWT

```bash
# Com Docker (recomendado)
docker compose run --rm app php bin/console lexik:jwt:generate-keypair

# Ou localmente
php bin/console lexik:jwt:generate-keypair
```

### 4. Suba o ambiente

```bash
docker compose up -d --build
```

### 5. Execute as migrations

```bash
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
```

### 6. Crie o usuário demonstrativo

```bash
docker compose exec app php bin/console app:create-demo-user
```

## Acesso

| Serviço | URL |
|---------|-----|
| Frontend (React) | http://localhost:5173 |
| API (Symfony) | http://localhost:8080/api |
| PostgreSQL | localhost:5432 |

**Credenciais demo:**
- Email: `demo@example.com`
- Senha: `Demo@12345`

## Testes

```bash
# Backend (PHPUnit)
docker compose exec app php bin/phpunit

# Ou localmente
php bin/phpunit

# Frontend (Vite build check)
cd frontend && npm run build
```

## Estrutura do projeto

```
financial-management/
├── assets/                # Assets Symfony (se necessário)
├── bin/                   # Binários Symfony (console)
├── config/                # Configurações Symfony
├── docker/
│   ├── nginx/             # Configuração Nginx
│   └── php/               # Configuração PHP
├── docs/                  # Documentação do projeto
│   └── diagrams/          # Diagramas Mermaid e PlantUML
├── frontend/              # Aplicação React
│   └── src/
│       ├── api/           # Clientes HTTP (Axios)
│       ├── components/    # Componentes reutilizáveis
│       ├── layouts/       # Layout global, Sidebar, Header
│       ├── pages/         # Páginas por módulo
│       ├── hooks/         # Custom hooks
│       ├── types/         # Tipos TypeScript
│       └── utils/         # Formatadores (BRL, datas)
├── migrations/            # Migrations Doctrine
├── public/                # Entry point HTTP
├── src/
│   ├── Controller/Api/    # Controllers REST
│   ├── DTO/               # Data Transfer Objects
│   ├── Entity/            # Entidades Doctrine
│   ├── Enum/              # Enums PHP
│   ├── Repository/        # Repositórios
│   ├── Security/          # Auth handlers
│   ├── Service/           # Lógica de negócio
│   └── Validator/         # Validações customizadas
└── tests/
    ├── Unit/              # Testes unitários
    └── Integration/       # Testes de integração
```

## Visualizar diagramas

- **Mermaid (`.mmd`)**: use [mermaid.live](https://mermaid.live) ou extensão VS Code
- **PlantUML (`.puml`)**: use [plantuml.com/plantuml](https://www.plantuml.com/plantuml) ou extensão VS Code

## Documentação

| Arquivo | Conteúdo |
|---------|----------|
| `docs/requirements.md` | Requisitos funcionais e não funcionais |
| `docs/architecture.md` | Arquitetura e decisões técnicas |
| `docs/database.md` | Modelo de dados e entidades |
| `docs/api.md` | Endpoints REST documentados |
| `docs/use-cases.md` | Casos de uso UC001–UC015 |
| `docs/reconciliation-flow.md` | Fluxo detalhado de conciliação |
| `docs/progress.md` | Status de implementação |

## Formato CSV para importação

```
date,description,amount,type,external_reference
2024-01-15,Salário Janeiro,5000.00,income,SAL-2024-01
2024-01-16,Aluguel,-1500.00,expense,ALU-2024-01
```

- `date`: YYYY-MM-DD
- `amount`: valor positivo; use `type` para indicar direção
- `external_reference`: opcional, usado para detecção de duplicatas
