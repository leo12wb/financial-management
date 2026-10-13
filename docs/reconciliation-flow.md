# Fluxo de Conciliação Financeira — Financial Management & Transaction Reconciliation System

## 1. O que é Conciliação Financeira?

A **conciliação financeira** é o processo de confrontar os registros de movimentações financeiras lançados manualmente no sistema com os registros que constam no extrato bancário oficial. O objetivo é garantir que:

1. Todas as transações registradas no sistema têm correspondência no extrato bancário
2. Todas as entradas do extrato bancário têm correspondência nos registros do sistema
3. Divergências (valores diferentes, registros ausentes) são identificadas e tratadas

No contexto deste sistema, a conciliação conecta dois tipos de registros:

| Registro | Origem | Entidade |
|---|---|---|
| **Transação** | Lançamento manual pelo usuário | `Transaction` |
| **Entrada de extrato** | Importação de arquivo CSV bancário | `StatementEntry` |

Uma conciliação bem-sucedida cria um vínculo (`Reconciliation`) entre esses dois registros, confirmando que representam a mesma movimentação financeira real.

---

## 2. Fluxo Completo Passo a Passo

### Passo 1 — Importar Extrato Bancário

O usuário faz o upload de um arquivo CSV contendo as movimentações do extrato bancário para uma conta específica.

```
Usuário → Upload CSV → POST /api/imports/preview
        → Revisão do preview
        → POST /api/imports/{id}/confirm
        → StatementEntries criados com status "pending"
```

Cada linha do CSV válida se torna um `StatementEntry` com:
- Status inicial: `pending`
- Campos: `date`, `description`, `amount`, `type`, `external_reference`

### Passo 2 — Sistema Gera Sugestões de Correspondência

Após a importação, o sistema compara automaticamente as `Transaction` pendentes com os `StatementEntry` pendentes da mesma conta.

```
GET /api/reconciliations/suggestions?account_id=X
→ ReconciliationService.generateSuggestions(account)
→ Retorna lista de pares (Transaction, StatementEntry) com score
```

O algoritmo de correspondência usa os critérios detalhados na seção 3.

### Passo 3 — Usuário Analisa as Sugestões

A tela de conciliação exibe as sugestões ordenadas por score de confiança. Para cada sugestão, o usuário vê:
- Dados da transação registrada (lado esquerdo)
- Dados do extrato (lado direito)
- Score de confiança e razões do match
- Botões "Confirmar" e "Rejeitar"

### Passo 4 — Usuário Confirma ou Rejeita

**Confirmar (UC012):**
```
POST /api/reconciliations/confirm
{ transactionId: X, statementEntryId: Y }

→ Cria Reconciliation(transaction, statementEntry, reconciledAt, reconciledBy)
→ Transaction.status = "reconciled"
→ StatementEntry.status = "reconciled"
```

**Rejeitar (UC013):**
```
→ StatementEntry.status = "pending" (manter para análise)
   ou
→ StatementEntry.status = "ignored" (descartar permanentemente)
```

### Passo 5 — Monitorar Pendências

Após processar as sugestões, o usuário pode verificar:
- `StatementEntry` com status `pending` sem correspondência: transações faltando no sistema
- `Transaction` com status `pending` sem sugestão: transações sem correspondência no extrato

Essas situações podem indicar divergências que precisam de investigação.

### Passo 6 — Desfazer Conciliação (se necessário)

Se o usuário perceber que confirmou uma conciliação incorreta, pode desfazê-la (UC014):

```
POST /api/reconciliations/{id}/undo

→ Reconciliation.undone_at = NOW()
→ Reconciliation.undone_by = usuário atual
→ Transaction.status = "pending"
→ StatementEntry.status = "pending"
```

---

## 3. Critérios de Correspondência Automática

O `ReconciliationService` utiliza os seguintes critérios para gerar sugestões e calcular o score de confiança (0.0 a 1.0):

### 3.1 Critério Obrigatório — Mesma Conta

```
Transaction.account_id === StatementEntry.account_id
```

Este critério é um filtro hard: pares de contas diferentes nunca são sugeridos, independentemente dos outros critérios.

**Peso no score:** Pré-requisito (não bônus)

---

### 3.2 Critério Principal — Mesmo Valor

```
Transaction.amount === StatementEntry.amount
  E
Transaction.type === StatementEntry.type
```

Valor e tipo devem coincidir exatamente. Um débito de R$ 245,80 só pode ser conciliado com uma entrada de extrato de tipo débito e valor R$ 245,80.

**Peso no score:** +0.50 (o maior peso individual)

**Observação:** Valores são comparados como `DECIMAL(15,2)` — comparação exata, sem tolerância de arredondamento.

---

### 3.3 Critério de Data — Proximidade Temporal

```
|Transaction.date - StatementEntry.date| <= date_tolerance (padrão: 3 dias)
```

O sistema aceita uma tolerância configurável porque:
- A data da transação no sistema pode ser a data do pedido/compra
- A data no extrato bancário pode ser a data de processamento/compensação
- Esses dois momentos podem diferir por 1 a 3 dias úteis

**Cálculo do bônus de data:**
```
dias_diferença = |Transaction.date - StatementEntry.date|

se dias_diferença == 0: bônus = +0.30
se dias_diferença == 1: bônus = +0.20
se dias_diferença == 2: bônus = +0.10
se dias_diferença >= 3: bônus = +0.05
```

---

### 3.4 Critério Auxiliar — Similaridade de Descrição

A similaridade textual entre as descrições é usada como critério de desempate quando há múltiplos candidatos com mesmo valor e data similar.

**Algoritmo:** Distância de Levenshtein normalizada ou comparação por tokens (palavras).

**Exemplo:**
```
Transaction.description: "Supermercado Extra"
StatementEntry.description: "SUPERMERCADO EXTRA"
→ Similaridade: 1.0 (100% após normalização para lowercase)

Transaction.description: "Mercado"
StatementEntry.description: "SUPERMERCADO EXTRA"
→ Similaridade: ~0.3
```

**Peso no score:** Máximo +0.20

---

### 3.5 Cálculo Final do Score

```
score = peso_valor + bônus_data + bônus_descricao

Exemplo:
- Mesmo valor e tipo: +0.50
- Mesma data exata:   +0.30
- Descrição similar:  +0.18
- TOTAL:               0.98 ← Sugestão de alta confiança
```

Sugestões com score abaixo de **0.40** não são exibidas ao usuário por serem pouco confiáveis.

---

## 4. Estados das Entidades

### 4.1 Estados de Transaction

```
                    [Criação manual]
                          │
                          ▼
                       pending ──────────────────────┐
                          │                           │
               [confirmar conciliação]      [marcar como ignorada]
                          │                           │
                          ▼                           ▼
                     reconciled                    ignored
                          │
              [desfazer conciliação]
                          │
                          ▼
                       pending  (volta ao início)
```

| Status | Descrição | Editável? |
|---|---|---|
| `pending` | Transação registrada, aguardando conciliação | Sim |
| `reconciled` | Vinculada a um StatementEntry via Reconciliation | Não (deve desfazer primeiro) |
| `divergent` | Correspondência encontrada, mas com diferenças | Sim |
| `ignored` | Marcada como não precisar de conciliação | Sim |

---

### 4.2 Estados de StatementEntry

```
           [Importação confirmada]
                     │
                     ▼
                  pending ──────────────────────┐
                     │                           │
        [confirmar conciliação]        [rejeitar/ignorar]
                     │                           │
                     ▼                           ▼
                reconciled                    ignored
                     │
         [desfazer conciliação]
                     │
                     ▼
                  pending  (volta ao início)
```

| Status | Descrição |
|---|---|
| `pending` | Importado, aguardando conciliação |
| `reconciled` | Vinculado a uma Transaction via Reconciliation ativa |
| `ignored` | Descartado pelo usuário, não será conciliado |

---

## 5. Regras de Integridade

### 5.1 Unicidade de Conciliação Ativa

Um `StatementEntry` pode ter no máximo **uma conciliação ativa** por vez. A integridade é garantida em dois níveis:

**Nível de aplicação (ReconciliationService):**
```php
$activeReconciliation = $this->reconciliationRepository
    ->findActiveByStatementEntry($statementEntry);

if ($activeReconciliation !== null) {
    throw new ConflictException('Esta entrada já está conciliada.');
}
```

**Nível de banco de dados (índice único parcial):**
```sql
CREATE UNIQUE INDEX idx_reconciliation_active_entry
  ON reconciliations(statement_entry_id)
  WHERE undone_at IS NULL;
```

O mesmo se aplica para `Transaction`:
```sql
CREATE UNIQUE INDEX idx_reconciliation_active_transaction
  ON reconciliations(transaction_id)
  WHERE undone_at IS NULL;
```

### 5.2 Pertencimento à Mesma Conta

O `ReconciliationService` verifica que `Transaction.account_id === StatementEntry.account_id` antes de criar qualquer conciliação.

### 5.3 Pertencimento ao Usuário

O `ReconciliationService` verifica que tanto a `Transaction` quanto o `StatementEntry` pertencem (indiretamente via `account`) ao usuário autenticado.

### 5.4 Imutabilidade de Transações Conciliadas

Transações com `status = 'reconciled'` não podem ser editadas ou excluídas. O `TransactionService` verifica esse status antes de processar qualquer operação de mutação.

---

## 6. Desfazendo uma Conciliação

A operação de desfazimento é uma operação de **registro** — não deleta nada, apenas marca o `undone_at` e reverte os status.

### Sequência de operações:

```
1. Receber POST /api/reconciliations/{id}/undo

2. Buscar Reconciliation pelo id
   → Se não encontrada: HTTP 404
   → Se undone_at IS NOT NULL: HTTP 409 (já desfeita)

3. Abrir transação de banco de dados

4. Registrar desfazimento:
   reconciliation.undone_at = NOW()
   reconciliation.undone_by = usuário atual
   reconciliation.notes = motivo (opcional)

5. Reverter status da Transaction:
   transaction.status = 'pending'
   transaction.updated_at = NOW()

6. Reverter status do StatementEntry:
   statementEntry.status = 'pending'

7. Confirmar transação de banco de dados

8. Retornar HTTP 200
```

### Por que não deletar o registro?

A preservação histórica das conciliações (incluindo as desfeitas) é importante para:
- **Auditoria:** saber quem conciliou o quê e quando, e por que foi desfeito
- **Debug:** rastrear problemas de conciliações incorretas recorrentes
- **Conformidade:** em contextos contábeis, o histórico de operações é fundamental

---

## 7. Referência ao Diagrama

O diagrama de sequência visual da confirmação de conciliação está disponível em:

```
docs/diagrams/reconciliation-sequence.mmd
```

Para visualizar, use um renderer Mermaid compatível (ex: extensão Mermaid Preview do VS Code, ou https://mermaid.live).
