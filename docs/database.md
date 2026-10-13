# Modelo de Dados — Financial Management & Transaction Reconciliation System

## 1. Visão Geral

O banco de dados é PostgreSQL 16 e o acesso é feito via Doctrine ORM. O modelo é relacional normalizado, com as seguintes entidades principais:

| Entidade | Tabela | Descrição |
|---|---|---|
| `User` | `users` | Usuário autenticado do sistema |
| `Account` | `accounts` | Conta financeira (corrente, poupança etc.) |
| `Category` | `categories` | Categoria de transação (receita/despesa) |
| `Transaction` | `transactions` | Registro de movimentação financeira |
| `ImportedStatement` | `imported_statements` | Cabeçalho de uma importação de extrato CSV |
| `StatementEntry` | `statement_entries` | Linha individual de um extrato importado |
| `Reconciliation` | `reconciliations` | Vínculo entre transação e entrada de extrato |

---

## 2. Entidades

### 2.1 User

Representa o usuário autenticado do sistema.

**Tabela:** `users`

| Coluna | Tipo | Constraints | Descrição |
|---|---|---|---|
| `id` | `SERIAL` | PK | Identificador único |
| `name` | `VARCHAR(100)` | NOT NULL | Nome completo |
| `email` | `VARCHAR(180)` | NOT NULL, UNIQUE | E-mail (usado como login) |
| `password` | `VARCHAR(255)` | NOT NULL | Hash bcrypt da senha |
| `roles` | `JSON` | NOT NULL, DEFAULT '["ROLE_USER"]' | Roles de autorização |
| `created_at` | `TIMESTAMPTZ` | NOT NULL, DEFAULT NOW() | Data de criação |
| `updated_at` | `TIMESTAMPTZ` | NOT NULL, DEFAULT NOW() | Última atualização |

**Índices:**
- `PK` em `id`
- `UNIQUE` em `email`

**Relacionamentos:**
- `1 User → N Account`
- `1 User → N Category`

---

### 2.2 Account

Representa uma conta financeira do usuário (conta corrente, poupança, carteira etc.).

**Tabela:** `accounts`

| Coluna | Tipo | Constraints | Descrição |
|---|---|---|---|
| `id` | `SERIAL` | PK | Identificador único |
| `user_id` | `INTEGER` | NOT NULL, FK → users.id | Proprietário da conta |
| `name` | `VARCHAR(100)` | NOT NULL | Nome da conta (ex: "Bradesco Corrente") |
| `type` | `VARCHAR(20)` | NOT NULL | Tipo: `checking`, `savings`, `investment`, `wallet`, `other` |
| `initial_balance` | `DECIMAL(15,2)` | NOT NULL, DEFAULT 0.00 | Saldo inicial definido pelo usuário |
| `currency` | `CHAR(3)` | NOT NULL, DEFAULT 'BRL' | Código ISO 4217 da moeda |
| `is_active` | `BOOLEAN` | NOT NULL, DEFAULT TRUE | Conta ativa ou arquivada |
| `created_at` | `TIMESTAMPTZ` | NOT NULL, DEFAULT NOW() | Data de criação |
| `updated_at` | `TIMESTAMPTZ` | NOT NULL, DEFAULT NOW() | Última atualização |

**Índices:**
- `PK` em `id`
- `INDEX` em `user_id`
- `INDEX` em `(user_id, is_active)` — listagem de contas ativas do usuário

**Relacionamentos:**
- `N Account → 1 User`
- `1 Account → N Transaction`
- `1 Account → N ImportedStatement`

**Regra de negócio:**
O saldo atual (`current_balance`) não é armazenado. É calculado dinamicamente:
```sql
SELECT
  a.initial_balance +
  COALESCE(SUM(
    CASE WHEN t.type = 'credit' THEN t.amount ELSE -t.amount END
  ), 0) AS current_balance
FROM accounts a
LEFT JOIN transactions t ON t.account_id = a.id
WHERE a.id = :account_id
GROUP BY a.id, a.initial_balance;
```

---

### 2.3 Category

Representa uma categoria de transação (ex: "Alimentação", "Salário").

**Tabela:** `categories`

| Coluna | Tipo | Constraints | Descrição |
|---|---|---|---|
| `id` | `SERIAL` | PK | Identificador único |
| `user_id` | `INTEGER` | NOT NULL, FK → users.id | Proprietário da categoria |
| `name` | `VARCHAR(100)` | NOT NULL | Nome da categoria |
| `type` | `VARCHAR(10)` | NOT NULL | Tipo: `income` (receita) ou `expense` (despesa) |
| `color` | `CHAR(7)` | NULLABLE | Cor hex para visualização (ex: `#FF5733`) |
| `created_at` | `TIMESTAMPTZ` | NOT NULL, DEFAULT NOW() | Data de criação |
| `updated_at` | `TIMESTAMPTZ` | NOT NULL, DEFAULT NOW() | Última atualização |

**Índices:**
- `PK` em `id`
- `INDEX` em `user_id`
- `UNIQUE` em `(user_id, name)` — evita categorias duplicadas para o mesmo usuário

**Relacionamentos:**
- `N Category → 1 User`
- `1 Category → N Transaction` (opcional — transação pode não ter categoria)

---

### 2.4 Transaction

Representa uma movimentação financeira registrada manualmente pelo usuário.

**Tabela:** `transactions`

| Coluna | Tipo | Constraints | Descrição |
|---|---|---|---|
| `id` | `SERIAL` | PK | Identificador único |
| `account_id` | `INTEGER` | NOT NULL, FK → accounts.id | Conta vinculada |
| `category_id` | `INTEGER` | NULLABLE, FK → categories.id | Categoria (opcional) |
| `type` | `VARCHAR(10)` | NOT NULL | Tipo: `credit` (receita) ou `debit` (despesa) |
| `amount` | `DECIMAL(15,2)` | NOT NULL, CHECK (amount > 0) | Valor absoluto positivo |
| `date` | `DATE` | NOT NULL | Data da transação |
| `description` | `VARCHAR(255)` | NOT NULL | Descrição da transação |
| `notes` | `TEXT` | NULLABLE | Observações livres |
| `status` | `VARCHAR(20)` | NOT NULL, DEFAULT 'pending' | Status: `pending`, `reconciled`, `divergent`, `ignored` |
| `created_at` | `TIMESTAMPTZ` | NOT NULL, DEFAULT NOW() | Data de criação do registro |
| `updated_at` | `TIMESTAMPTZ` | NOT NULL, DEFAULT NOW() | Última atualização |

**Índices:**
- `PK` em `id`
- `INDEX` em `account_id`
- `INDEX` em `category_id`
- `INDEX` em `(account_id, date)` — filtragem por conta e período
- `INDEX` em `(account_id, status)` — filtragem por conta e status de conciliação

**Relacionamentos:**
- `N Transaction → 1 Account`
- `N Transaction → 0..1 Category`
- `1 Transaction → 0..1 Reconciliation`

**Regra de negócio:**
- `amount` é sempre positivo; o impacto no saldo é determinado pelo `type`
- Transações com `status = 'reconciled'` não podem ser editadas ou excluídas diretamente

---

### 2.5 ImportedStatement

Representa o cabeçalho (metadados) de uma importação de extrato CSV.

**Tabela:** `imported_statements`

| Coluna | Tipo | Constraints | Descrição |
|---|---|---|---|
| `id` | `SERIAL` | PK | Identificador único |
| `account_id` | `INTEGER` | NOT NULL, FK → accounts.id | Conta para a qual o extrato foi importado |
| `user_id` | `INTEGER` | NOT NULL, FK → users.id | Usuário que realizou a importação |
| `filename` | `VARCHAR(255)` | NOT NULL | Nome original do arquivo CSV |
| `imported_at` | `TIMESTAMPTZ` | NOT NULL, DEFAULT NOW() | Data e hora da importação |
| `total_entries` | `INTEGER` | NOT NULL, DEFAULT 0 | Total de linhas no arquivo |
| `valid_entries` | `INTEGER` | NOT NULL, DEFAULT 0 | Total de linhas válidas importadas |
| `skipped_entries` | `INTEGER` | NOT NULL, DEFAULT 0 | Total de linhas ignoradas (duplicatas/erros) |
| `status` | `VARCHAR(20)` | NOT NULL, DEFAULT 'pending' | Status: `pending`, `confirmed`, `error` |
| `error_log` | `JSON` | NULLABLE | Lista de erros de validação |

**Índices:**
- `PK` em `id`
- `INDEX` em `account_id`
- `INDEX` em `user_id`
- `INDEX` em `imported_at` — para histórico ordenado

**Relacionamentos:**
- `N ImportedStatement → 1 Account`
- `1 ImportedStatement → N StatementEntry`

---

### 2.6 StatementEntry

Representa uma linha individual de um extrato bancário importado.

**Tabela:** `statement_entries`

| Coluna | Tipo | Constraints | Descrição |
|---|---|---|---|
| `id` | `SERIAL` | PK | Identificador único |
| `imported_statement_id` | `INTEGER` | NOT NULL, FK → imported_statements.id | Importação de origem |
| `account_id` | `INTEGER` | NOT NULL, FK → accounts.id | Conta vinculada (desnormalizado para facilitar consultas) |
| `date` | `DATE` | NOT NULL | Data da movimentação no extrato |
| `description` | `VARCHAR(255)` | NOT NULL | Descrição original do extrato |
| `amount` | `DECIMAL(15,2)` | NOT NULL, CHECK (amount > 0) | Valor absoluto positivo |
| `type` | `VARCHAR(10)` | NOT NULL | Tipo: `credit` ou `debit` |
| `external_reference` | `VARCHAR(100)` | NULLABLE | Referência externa (ID do banco, número do documento) |
| `status` | `VARCHAR(20)` | NOT NULL, DEFAULT 'pending' | Status: `pending`, `reconciled`, `ignored` |
| `created_at` | `TIMESTAMPTZ` | NOT NULL, DEFAULT NOW() | Data de criação do registro |

**Índices:**
- `PK` em `id`
- `INDEX` em `imported_statement_id`
- `INDEX` em `account_id`
- `INDEX` em `(account_id, status)` — para busca de entradas pendentes
- `UNIQUE` em `(account_id, external_reference)` WHERE `external_reference IS NOT NULL` — garante idempotência da importação

**Relacionamentos:**
- `N StatementEntry → 1 ImportedStatement`
- `1 StatementEntry → 0..1 Reconciliation`

---

### 2.7 Reconciliation

Representa o vínculo confirmado entre uma `Transaction` e um `StatementEntry`.

**Tabela:** `reconciliations`

| Coluna | Tipo | Constraints | Descrição |
|---|---|---|---|
| `id` | `SERIAL` | PK | Identificador único |
| `transaction_id` | `INTEGER` | NOT NULL, FK → transactions.id | Transação vinculada |
| `statement_entry_id` | `INTEGER` | NOT NULL, FK → statement_entries.id | Entrada do extrato vinculada |
| `reconciled_by` | `INTEGER` | NOT NULL, FK → users.id | Usuário que confirmou a conciliação |
| `reconciled_at` | `TIMESTAMPTZ` | NOT NULL, DEFAULT NOW() | Data e hora da conciliação |
| `undone_at` | `TIMESTAMPTZ` | NULLABLE | Data de desfazimento (NULL = conciliação ativa) |
| `undone_by` | `INTEGER` | NULLABLE, FK → users.id | Usuário que desfez a conciliação |
| `notes` | `TEXT` | NULLABLE | Observações sobre a conciliação |

**Índices:**
- `PK` em `id`
- `INDEX` em `transaction_id`
- `INDEX` em `statement_entry_id`
- `UNIQUE PARTIAL` em `statement_entry_id` WHERE `undone_at IS NULL` — garante que um StatementEntry não tenha duas conciliações ativas
- `UNIQUE PARTIAL` em `transaction_id` WHERE `undone_at IS NULL` — garante que uma Transaction não tenha duas conciliações ativas simultâneas

**DDL dos índices únicos parciais:**
```sql
CREATE UNIQUE INDEX idx_reconciliation_active_entry
  ON reconciliations(statement_entry_id)
  WHERE undone_at IS NULL;

CREATE UNIQUE INDEX idx_reconciliation_active_transaction
  ON reconciliations(transaction_id)
  WHERE undone_at IS NULL;
```

**Relacionamentos:**
- `N Reconciliation → 1 Transaction`
- `N Reconciliation → 1 StatementEntry`
- `N Reconciliation → 1 User` (reconciled_by)

---

## 3. Relacionamentos e Cardinalidades

```
User         1 ─── * Account
User         1 ─── * Category
Account      1 ─── * Transaction
Account      1 ─── * ImportedStatement
Category     1 ─── 0..* Transaction
Transaction  1 ─── 0..1 Reconciliation  (via UNIQUE PARTIAL index)
ImportedStatement 1 ─── * StatementEntry
StatementEntry    1 ─── 0..1 Reconciliation  (via UNIQUE PARTIAL index)
```

---

## 4. Estratégia de Cálculo de Saldo

O saldo de uma conta nunca é armazenado como campo persistido. Ele é **calculado dinamicamente** para garantir consistência em todas as situações (edição, exclusão, desfazimento de conciliação).

### Fórmula:

```
saldo_atual = saldo_inicial + receitas_totais - despesas_totais
```

Onde:
- `saldo_inicial` = `Account.initial_balance`
- `receitas_totais` = `SUM(amount)` de todas as `Transaction` com `type = 'credit'` vinculadas à conta
- `despesas_totais` = `SUM(amount)` de todas as `Transaction` com `type = 'debit'` vinculadas à conta

### Query SQL:

```sql
SELECT
  a.id,
  a.name,
  a.initial_balance,
  COALESCE(
    a.initial_balance +
    SUM(CASE WHEN t.type = 'credit' THEN t.amount ELSE 0 END) -
    SUM(CASE WHEN t.type = 'debit' THEN t.amount ELSE 0 END),
    a.initial_balance
  ) AS current_balance
FROM accounts a
LEFT JOIN transactions t ON t.account_id = a.id
WHERE a.id = :account_id
  AND a.user_id = :user_id
GROUP BY a.id, a.name, a.initial_balance;
```

### Considerações de performance:

- Para contas com muitas transações, pode-se implementar uma **tabela de cache de saldo** materializada periodicamente. Para a v1.0, o cálculo dinâmico é suficiente.
- O índice em `(account_id, type)` na tabela `transactions` garante que a query de saldo seja executada eficientemente.

---

## 5. Regras de Integridade de Conciliação

### 5.1 Um para um ativo

Um `StatementEntry` pode ter várias conciliações na tabela `reconciliations` ao longo do tempo (pois o desfazimento registra `undone_at` em vez de deletar), mas **somente uma conciliação ativa** (onde `undone_at IS NULL`) por vez.

Isso é garantido pelo índice único parcial:
```sql
CREATE UNIQUE INDEX idx_reconciliation_active_entry
  ON reconciliations(statement_entry_id)
  WHERE undone_at IS NULL;
```

### 5.2 Idempotência de importação

A unicidade de `(account_id, external_reference)` na tabela `statement_entries` garante que o mesmo registro bancário não seja importado duas vezes:

```sql
CREATE UNIQUE INDEX idx_statement_entry_external_ref
  ON statement_entries(account_id, external_reference)
  WHERE external_reference IS NOT NULL;
```

### 5.3 Valor monetário nunca como float

Todos os valores são `DECIMAL(15,2)`:
- Precisão: 15 dígitos significativos
- Escala: 2 casas decimais (centavos)
- Suporta valores até: 999.999.999.999.999,99

---

## 6. Tipos de Dados Padronizados

| Dado | Tipo PostgreSQL | Justificativa |
|---|---|---|
| Identificadores | `SERIAL` (autoincremento) | Simplicidade; UUID pode ser adicionado em v2 |
| Valores monetários | `DECIMAL(15,2)` | Precisão exata sem erro de ponto flutuante |
| Datas de eventos | `TIMESTAMPTZ` | Preserva timezone; essencial para apps internacionalizáveis |
| Datas de transações | `DATE` | Apenas data, sem hora; transação é um evento de dia |
| Textos curtos | `VARCHAR(n)` | Limita uso de espaço e impõe validação |
| Textos livres | `TEXT` | Sem limite fixo para observações |
| Enumerações | `VARCHAR(20)` com CHECK | Mais flexível que ENUM nativo do PostgreSQL para migrações |
| Dados estruturados | `JSON` | Para logs de erro e roles; evita tabelas auxiliares |
| Flags booleanas | `BOOLEAN` | Nativo PostgreSQL |

---

## 7. Referência ao Diagrama ER

O diagrama entidade-relacionamento visual está disponível em:

```
docs/diagrams/entity-relationship.mmd
```

Para visualizar, use um renderer Mermaid compatível (ex: extensão Mermaid Preview do VS Code, ou https://mermaid.live).

---

## 8. Migrações de Banco de Dados

As migrações são gerenciadas pelo Doctrine Migrations (`doctrine/migrations`). A convenção é:

- Cada alteração de schema gera uma migration com timestamp no nome
- As migrations são versionadas no controle de código
- Em desenvolvimento: `php bin/console doctrine:migrations:migrate`
- Em produção: as migrations são executadas automaticamente no entrypoint do container

**Nunca executar** `doctrine:schema:update --force` fora do ambiente de desenvolvimento inicial.
