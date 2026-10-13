# Arquitetura — Financial Management & Transaction Reconciliation System

## 1. Visão Geral

O sistema adota uma arquitetura **monolítica em camadas** (Layered Monolith), onde o backend Symfony e o frontend React residem no mesmo repositório, mas são desenvolvidos, buildados e servidos de forma independente dentro do mesmo container Docker.

```
┌─────────────────────────────────────────────────────────┐
│                    Docker Environment                    │
│                                                         │
│  ┌─────────────────┐      ┌────────────────────────┐   │
│  │   React / Vite  │ HTTP │   Symfony Application  │   │
│  │   (port 5173)   │─────▶│      (port 8080)       │   │
│  └─────────────────┘      └───────────┬────────────┘   │
│                                        │                │
│                            ┌───────────▼────────────┐   │
│                            │   PostgreSQL 16         │   │
│                            │      (port 5432)        │   │
│                            └────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
```

### Por que Monólito?

A escolha por arquitetura monolítica se justifica pelos seguintes fatores:

1. **Escopo de portfólio**: O sistema tem escopo bem definido e limitado, sem necessidade de escalar componentes independentemente.
2. **Simplicidade operacional**: Um único `docker compose up` inicializa todo o ambiente.
3. **Coesão do domínio**: Os módulos (contas, transações, conciliação) são fortemente acoplados em termos de dados, tornando a separação em microsserviços prejudicial sem ganho real.
4. **Velocidade de desenvolvimento**: Sem overhead de comunicação entre serviços, contratos de API internos ou service discovery.

---

## 2. Camadas da Arquitetura Backend (Symfony)

### 2.1 Controller (Camada de Apresentação)

**Responsabilidades:**
- Receber requisições HTTP e extrair dados do request (body, query params, route params)
- Delegar a lógica de negócio ao Service correspondente
- Formatar a resposta HTTP (status code, headers, JSON body)
- Não conter lógica de negócio; apenas orquestrar

**Localização:** `src/Controller/`

**Convenções:**
- Um controller por recurso (ex: `AccountController`, `TransactionController`)
- Métodos mapeados por ação (ex: `list`, `create`, `show`, `update`)
- Utilizar `JsonResponse` do Symfony para todas as respostas
- Utilizar `#[Route]` attributes para declarar rotas

**Exemplo de responsabilidade:**
```php
#[Route('/api/accounts', methods: ['POST'])]
public function create(Request $request): JsonResponse
{
    $dto = $this->serializer->deserialize($request->getContent(), CreateAccountDto::class, 'json');
    $errors = $this->validator->validate($dto);
    if (count($errors) > 0) {
        return $this->json(['violations' => $this->formatErrors($errors)], 422);
    }
    $account = $this->accountService->create($dto, $this->getUser());
    return $this->json($account, 201);
}
```

---

### 2.2 Service (Camada de Aplicação / Lógica de Negócio)

**Responsabilidades:**
- Implementar os casos de uso do sistema
- Orquestrar chamadas ao repositório, aplicar regras de negócio e coordenar entidades
- Ser o único ponto de entrada para lógica de negócio (controllers e outros services chamam esta camada)
- Gerenciar transações de banco de dados quando necessário

**Localização:** `src/Service/`

**Convenções:**
- Um service por domínio (ex: `AccountService`, `ReconciliationService`, `CsvImportService`)
- Injetado via construtor (Dependency Injection)
- Lançar exceções de domínio customizadas em vez de retornar null ou códigos de erro

**Principais services:**
| Service | Responsabilidade principal |
|---|---|
| `AccountService` | CRUD de contas, cálculo de saldo |
| `TransactionService` | CRUD de transações, validação de status |
| `CategoryService` | CRUD de categorias |
| `CsvImportService` | Parse, validação, preview e persistência de extratos |
| `ReconciliationService` | Geração de sugestões, confirmação e desfazimento |
| `DashboardService` | Consolidação de dados para o painel principal |

---

### 2.3 Repository (Camada de Acesso a Dados)

**Responsabilidades:**
- Encapsular toda a lógica de acesso ao banco de dados
- Prover métodos de consulta legíveis pelo domínio (ex: `findByAccountAndDateRange`)
- Utilizar Doctrine ORM para queries e DQL quando necessário
- Isolar a camada de serviço dos detalhes do ORM

**Localização:** `src/Repository/`

**Convenções:**
- Estender `ServiceEntityRepository` do Doctrine
- Métodos de query com nomes expressivos do domínio
- Consultas de agregação (soma de transações para saldo) nesta camada

**Exemplo:**
```php
public function calculateBalance(Account $account): string
{
    return $this->createQueryBuilder('t')
        ->select('SUM(CASE WHEN t.type = :credit THEN t.amount ELSE -t.amount END)')
        ->where('t.account = :account')
        ->setParameters(['account' => $account, 'credit' => 'credit'])
        ->getQuery()
        ->getSingleScalarResult() ?? '0.00';
}
```

---

### 2.4 Entity (Camada de Domínio)

**Responsabilidades:**
- Representar as entidades do domínio com seus atributos e relacionamentos
- Conter apenas lógica de negócio inerente à entidade (ex: `isReconciled()`, `canBeEdited()`)
- Ser mapeada pelo Doctrine ORM para as tabelas do banco de dados

**Localização:** `src/Entity/`

**Entidades principais:** `User`, `Account`, `Category`, `Transaction`, `ImportedStatement`, `StatementEntry`, `Reconciliation`

---

### 2.5 DTO (Data Transfer Object)

**Responsabilidades:**
- Representar os dados de entrada da API (request bodies)
- Carregar as annotations/attributes de validação do Symfony Validator
- Desacoplar a estrutura da requisição HTTP da estrutura da entidade de domínio

**Localização:** `src/Dto/`

**Exemplos:** `CreateAccountDto`, `CreateTransactionDto`, `ConfirmReconciliationDto`, `LoginDto`

---

### 2.6 Validator (Validação customizada)

**Responsabilidades:**
- Implementar regras de validação que vão além das constraints padrão do Symfony
- Exemplos: verificar se uma conta pertence ao usuário autenticado antes de vincular uma transação

**Localização:** `src/Validator/`

---

### 2.7 Security (Autenticação e Autorização)

**Responsabilidades:**
- Implementar o `UserInterface` e `PasswordAuthenticatedUserInterface` do Symfony na entidade `User`
- Configurar o firewall e providers em `security.yaml`
- Garantir que todas as rotas `/api/*` (exceto `/api/login`) exigem autenticação
- Implementar o `Voter` para verificar propriedade de recursos (ex: usuário só acessa suas próprias contas)

**Localização:** `src/Security/`, `config/packages/security.yaml`

---

## 3. Comunicação React ↔ Symfony

### 3.1 Protocolo e Formato

- Toda comunicação é feita via **HTTP/1.1 ou HTTP/2**
- Formato de dados: **JSON** (`Content-Type: application/json`)
- O React consume a API via `fetch` nativo ou biblioteca cliente HTTP (`axios` ou similar)
- O Symfony responde sempre com `JsonResponse`

### 3.2 Gerenciamento de Estado no Frontend

```
┌─────────────────────────────────────────────────────────┐
│                    React Frontend                        │
│                                                         │
│  ┌──────────────┐    ┌──────────────┐                  │
│  │   UI Layer   │───▶│  React Query │                  │
│  │  Components  │    │  (cache +    │                  │
│  │   (tsx)      │    │   fetching)  │                  │
│  └──────────────┘    └──────┬───────┘                  │
│                              │ HTTP                     │
└──────────────────────────────┼──────────────────────────┘
                               │
                    ┌──────────▼──────────┐
                    │   Symfony API       │
                    │   /api/*            │
                    └─────────────────────┘
```

- **React Query (TanStack Query)**: Gerenciamento de cache, refetch, loading e error states para chamadas à API
- **React Context**: Estado global de autenticação (usuário logado, token)
- **React Hook Form**: Gerenciamento de formulários com validação client-side

### 3.3 Proxy de Desenvolvimento

Durante o desenvolvimento, o Vite está configurado com proxy reverso para encaminhar chamadas `/api/*` para o servidor Symfony, evitando problemas de CORS:

```typescript
// vite.config.ts
server: {
  proxy: {
    '/api': {
      target: 'http://app:8080',
      changeOrigin: true,
    }
  }
}
```

---

## 4. Estratégia de Autenticação

O sistema utiliza **autenticação baseada em sessão com cookie HttpOnly**, implementada pelo componente de segurança nativo do Symfony.

### Fluxo de autenticação:

```
1. Cliente envia POST /api/login com { email, password }
2. Symfony valida credenciais contra a entidade User
3. Em caso de sucesso, cria sessão no servidor e envia cookie de sessão HttpOnly
4. Cliente armazena estado de "autenticado" em React Context (não armazena o cookie — ele é gerenciado automaticamente pelo browser)
5. Requisições subsequentes enviam o cookie automaticamente
6. Symfony verifica a sessão a cada requisição
7. POST /api/logout invalida a sessão no servidor
```

### Alternativa JWT (para versões futuras):

Caso o sistema precise ser desacoplado (ex: app mobile consumindo a mesma API), a estratégia pode ser migrada para JWT com `lexik/jwt-authentication-bundle`. Nesse caso:
- O token JWT seria retornado no body do login
- O frontend armazenaria em memória (não em localStorage por segurança)
- Cada requisição enviaria o token no header `Authorization: Bearer <token>`

---

## 5. Fluxo de Requisição Completo

Exemplo: `POST /api/transactions` (cadastrar transação)

```
Browser
  │
  │ 1. fetch('POST /api/transactions', { body: {...} })
  ▼
[Vite Dev Proxy] ──────────────────────────────────
  │
  │ 2. Encaminha para http://app:8080/api/transactions
  ▼
[Symfony Kernel]
  │
  │ 3. Firewall verifica cookie de sessão → autenticado
  │ 4. Router despacha para TransactionController::create()
  ▼
[TransactionController]
  │
  │ 5. Desserializa JSON → CreateTransactionDto
  │ 6. Valida DTO com Symfony Validator
  │    → Se inválido: return JsonResponse(violations, 422)
  │ 7. Chama TransactionService::create(dto, user)
  ▼
[TransactionService]
  │
  │ 8. Verifica se a conta pertence ao usuário (AccountRepository)
  │ 9. Verifica se a categoria pertence ao usuário (CategoryRepository)
  │ 10. Cria entidade Transaction
  │ 11. Persiste via TransactionRepository
  ▼
[TransactionRepository]
  │
  │ 12. EntityManager::persist() + flush()
  ▼
[PostgreSQL]
  │
  │ 13. INSERT INTO transaction VALUES (...)
  ▼
[Resposta sobe a cadeia]
  │
  │ 14. Controller recebe Transaction entity
  │ 15. Serializa para JSON
  │ 16. return JsonResponse(transactionData, 201)
  ▼
Browser recebe HTTP 201 + JSON da transação criada
```

---

## 6. Organização de Pastas

```
financial-management/
├── docs/                          # Documentação técnica
│   ├── requirements.md
│   ├── architecture.md
│   ├── database.md
│   ├── api.md
│   ├── use-cases.md
│   ├── reconciliation-flow.md
│   ├── progress.md
│   └── diagrams/
│       ├── architecture.mmd
│       ├── entity-relationship.mmd
│       ├── reconciliation-sequence.mmd
│       ├── csv-import-activity.mmd
│       ├── use-case-overview.puml
│       ├── use-case-accounts.puml
│       ├── use-case-transactions.puml
│       ├── use-case-import.puml
│       └── use-case-reconciliation.puml
│
├── backend/                       # Aplicação Symfony
│   ├── bin/
│   │   └── console
│   ├── config/
│   │   ├── packages/
│   │   │   ├── doctrine.yaml
│   │   │   ├── security.yaml
│   │   │   └── validator.yaml
│   │   ├── routes.yaml
│   │   └── services.yaml
│   ├── migrations/                # Migrações Doctrine
│   ├── src/
│   │   ├── Controller/
│   │   │   ├── AuthController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── AccountController.php
│   │   │   ├── CategoryController.php
│   │   │   ├── TransactionController.php
│   │   │   ├── ImportController.php
│   │   │   └── ReconciliationController.php
│   │   ├── Dto/
│   │   │   ├── CreateAccountDto.php
│   │   │   ├── CreateTransactionDto.php
│   │   │   ├── ConfirmReconciliationDto.php
│   │   │   └── ...
│   │   ├── Entity/
│   │   │   ├── User.php
│   │   │   ├── Account.php
│   │   │   ├── Category.php
│   │   │   ├── Transaction.php
│   │   │   ├── ImportedStatement.php
│   │   │   ├── StatementEntry.php
│   │   │   └── Reconciliation.php
│   │   ├── Repository/
│   │   │   ├── AccountRepository.php
│   │   │   ├── TransactionRepository.php
│   │   │   ├── ReconciliationRepository.php
│   │   │   └── ...
│   │   ├── Service/
│   │   │   ├── AccountService.php
│   │   │   ├── TransactionService.php
│   │   │   ├── CsvImportService.php
│   │   │   ├── ReconciliationService.php
│   │   │   └── DashboardService.php
│   │   ├── Security/
│   │   │   └── Voter/
│   │   │       └── AccountVoter.php
│   │   └── Validator/
│   ├── tests/
│   │   ├── Unit/
│   │   └── Integration/
│   └── composer.json
│
├── frontend/                      # Aplicação React
│   ├── public/
│   ├── src/
│   │   ├── components/
│   │   │   ├── common/            # Botões, inputs, modais reutilizáveis
│   │   │   ├── dashboard/
│   │   │   ├── accounts/
│   │   │   ├── transactions/
│   │   │   ├── imports/
│   │   │   └── reconciliation/
│   │   ├── hooks/                 # Custom hooks (useAccounts, useTransactions...)
│   │   ├── pages/                 # Componentes de página (roteados)
│   │   ├── services/              # Funções de chamada à API
│   │   ├── types/                 # Tipos TypeScript (interfaces, enums)
│   │   ├── context/               # React Context (AuthContext)
│   │   ├── utils/                 # Utilitários (formatação de moeda, datas)
│   │   └── App.tsx
│   ├── package.json
│   ├── tsconfig.json
│   ├── vite.config.ts
│   └── tailwind.config.ts
│
└── docker/
    ├── docker-compose.yml
    ├── docker-compose.override.yml
    ├── php/
    │   └── Dockerfile
    └── nginx/
        └── default.conf
```

---

## 7. Decisões Técnicas

### 7.1 Por que Monólito?

Ver seção 1 (Visão Geral). Resumidamente: escopo pequeno e bem definido, forte coesão de domínio, facilidade de desenvolvimento e demonstração.

### 7.2 Por que Symfony 7?

- Framework PHP mais robusto do mercado para aplicações enterprise
- Excelente sistema de Dependency Injection (autowiring)
- Symfony Security é maduro e battle-tested
- Doctrine ORM integrado nativamente
- Suporte a PHP 8.4 e todas as features modernas da linguagem
- Excelente documentação e comunidade ativa

### 7.3 Por que Tailwind CSS?

- Abordagem utility-first elimina decisões sobre nomes de classes CSS
- Consistência visual garantida pelo design system embutido
- Purge de CSS não utilizados resulta em bundles mínimos em produção
- Integração nativa com Vite e excelente experiência de desenvolvimento

### 7.4 Por que Recharts?

- Biblioteca de gráficos construída sobre SVG e React, totalmente declarativa
- API composable (componente `<LineChart>` composto de `<Line>`, `<XAxis>` etc.)
- Bundle size aceitável (~200kb) comparado a alternativas como Chart.js
- TypeScript nativo, sem necessidade de `@types`

### 7.5 Por que PostgreSQL?

- Suporte nativo ao tipo `DECIMAL`/`NUMERIC` essencial para valores monetários
- Índices parciais (`WHERE` clause em `CREATE INDEX`) para a regra de conciliação única
- Suporte a `TIMESTAMPTZ` para correto armazenamento de timestamps com timezone
- Extensão `uuid-ossp` para geração de UUIDs no banco
- Melhor suporte a transações ACID comparado a MySQL para cenários de conciliação

---

## 8. Referência ao Diagrama

O diagrama visual da arquitetura está disponível em:

```
docs/diagrams/architecture.mmd
```

Para visualizar, use um renderer Mermaid compatível (ex: extensão Mermaid Preview do VS Code, ou o editor online em https://mermaid.live).
