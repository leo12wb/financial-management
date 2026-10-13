# Especificação da API REST — Financial Management & Transaction Reconciliation System

## 1. Convenções Gerais

### Base URL
```
http://localhost:8080/api
```

### Formato de dados
- Todas as requisições com body devem incluir o header: `Content-Type: application/json`
- Todas as respostas são em JSON: `Content-Type: application/json`
- Valores monetários trafegam como **string** no formato `"1234.56"` (nunca `float`)
- Datas trafegam no formato **ISO 8601**: `"2024-03-15"` (DATE) ou `"2024-03-15T14:30:00+00:00"` (DATETIME)

### Autenticação
Todas as rotas, exceto `POST /api/login`, exigem sessão autenticada via cookie HttpOnly.
Requisições sem autenticação válida retornam `HTTP 401`.

### Paginação
Endpoints de listagem suportam os seguintes query params:

| Param | Tipo | Padrão | Descrição |
|---|---|---|---|
| `page` | integer | 1 | Página atual |
| `limit` | integer | 20 | Itens por página (máx: 100) |
| `sort` | string | `created_at` | Campo de ordenação |
| `order` | string | `desc` | Direção: `asc` ou `desc` |

**Formato de resposta paginada:**
```json
{
  "data": [...],
  "meta": {
    "page": 1,
    "limit": 20,
    "total": 87,
    "totalPages": 5
  }
}
```

### Formato de Erros Padronizado

**Erro simples (ex: não autorizado, recurso não encontrado):**
```json
{
  "error": "Mensagem descritiva do erro"
}
```

**Erro de validação (HTTP 422):**
```json
{
  "error": "Validation failed",
  "violations": [
    {
      "field": "amount",
      "message": "O valor deve ser maior que zero."
    },
    {
      "field": "date",
      "message": "Formato de data inválido. Use YYYY-MM-DD."
    }
  ]
}
```

### Códigos HTTP utilizados

| Código | Significado |
|---|---|
| `200 OK` | Operação bem-sucedida (leitura, atualização) |
| `201 Created` | Recurso criado com sucesso |
| `204 No Content` | Operação bem-sucedida sem conteúdo de retorno |
| `400 Bad Request` | Requisição malformada (JSON inválido, params ausentes) |
| `401 Unauthorized` | Não autenticado |
| `403 Forbidden` | Autenticado, mas sem permissão para o recurso |
| `404 Not Found` | Recurso não encontrado |
| `409 Conflict` | Conflito de estado (ex: conciliar entry já conciliado) |
| `422 Unprocessable Entity` | Dados inválidos (falha de validação) |
| `500 Internal Server Error` | Erro inesperado no servidor |

---

## 2. Autenticação

### POST /api/login

Autentica o usuário com e-mail e senha.

**Request Body:**
```json
{
  "email": "usuario@exemplo.com",
  "password": "senha_segura"
}
```

**Response 200 OK:**
```json
{
  "user": {
    "id": 1,
    "name": "João Silva",
    "email": "usuario@exemplo.com",
    "roles": ["ROLE_USER"]
  }
}
```

**Respostas possíveis:**
| Código | Situação |
|---|---|
| `200 OK` | Login bem-sucedido, sessão criada |
| `401 Unauthorized` | Credenciais inválidas |
| `422 Unprocessable Entity` | Campos ausentes ou formato inválido |

---

### POST /api/logout

Encerra a sessão do usuário autenticado.

**Request Body:** Nenhum

**Response 204 No Content**

**Respostas possíveis:**
| Código | Situação |
|---|---|
| `204 No Content` | Logout bem-sucedido |
| `401 Unauthorized` | Sessão não existe ou já expirou |

---

### GET /api/me

Retorna os dados do usuário atualmente autenticado.

**Response 200 OK:**
```json
{
  "id": 1,
  "name": "João Silva",
  "email": "usuario@exemplo.com",
  "roles": ["ROLE_USER"],
  "createdAt": "2024-01-10T08:00:00+00:00"
}
```

**Respostas possíveis:**
| Código | Situação |
|---|---|
| `200 OK` | Dados do usuário retornados |
| `401 Unauthorized` | Não autenticado |

---

## 3. Dashboard

### GET /api/dashboard

Retorna todos os dados consolidados para o painel principal.

**Query Params:**
| Param | Tipo | Padrão | Descrição |
|---|---|---|---|
| `month` | string | mês atual | Mês no formato `YYYY-MM` |

**Response 200 OK:**
```json
{
  "summary": {
    "totalBalance": "15420.50",
    "monthIncome": "8500.00",
    "monthExpenses": "3240.75",
    "monthNet": "5259.25"
  },
  "accounts": [
    {
      "id": 1,
      "name": "Bradesco Corrente",
      "type": "checking",
      "currentBalance": "8320.50",
      "currency": "BRL"
    }
  ],
  "recentTransactions": [
    {
      "id": 42,
      "description": "Supermercado Extra",
      "amount": "245.80",
      "type": "debit",
      "date": "2024-03-14",
      "status": "pending",
      "account": { "id": 1, "name": "Bradesco Corrente" },
      "category": { "id": 5, "name": "Alimentação" }
    }
  ]
}
```

**Respostas possíveis:**
| Código | Situação |
|---|---|
| `200 OK` | Dados retornados |
| `401 Unauthorized` | Não autenticado |

---

### GET /api/dashboard/summary

Retorna apenas os valores consolidados do mês (sem transações recentes).

**Query Params:** `month` (opcional, padrão: mês atual)

**Response 200 OK:**
```json
{
  "totalBalance": "15420.50",
  "monthIncome": "8500.00",
  "monthExpenses": "3240.75",
  "monthNet": "5259.25",
  "month": "2024-03"
}
```

---

## 4. Contas Financeiras

### GET /api/accounts

Lista todas as contas do usuário autenticado.

**Query Params:**
| Param | Tipo | Descrição |
|---|---|---|
| `active` | boolean | Filtrar por contas ativas (`true`) ou arquivadas (`false`) |

**Response 200 OK:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Bradesco Corrente",
      "type": "checking",
      "initialBalance": "1000.00",
      "currentBalance": "8320.50",
      "currency": "BRL",
      "isActive": true,
      "createdAt": "2024-01-10T08:00:00+00:00"
    }
  ]
}
```

---

### POST /api/accounts

Cadastra uma nova conta financeira.

**Request Body:**
```json
{
  "name": "Nubank",
  "type": "checking",
  "initialBalance": "500.00",
  "currency": "BRL"
}
```

**Campos obrigatórios:** `name`, `type`
**Campos opcionais:** `initialBalance` (padrão: `"0.00"`), `currency` (padrão: `"BRL"`)

**Tipos válidos para `type`:** `checking`, `savings`, `investment`, `wallet`, `other`

**Response 201 Created:**
```json
{
  "id": 3,
  "name": "Nubank",
  "type": "checking",
  "initialBalance": "500.00",
  "currentBalance": "500.00",
  "currency": "BRL",
  "isActive": true,
  "createdAt": "2024-03-15T10:00:00+00:00"
}
```

**Respostas possíveis:**
| Código | Situação |
|---|---|
| `201 Created` | Conta criada |
| `401 Unauthorized` | Não autenticado |
| `422 Unprocessable Entity` | Campos inválidos |

---

### GET /api/accounts/{id}

Retorna os detalhes de uma conta específica.

**Response 200 OK:** (mesmo formato do item da listagem)

**Respostas possíveis:**
| Código | Situação |
|---|---|
| `200 OK` | Conta encontrada |
| `403 Forbidden` | Conta de outro usuário |
| `404 Not Found` | Conta não existe |

---

### PUT /api/accounts/{id}

Atualiza todos os campos editáveis de uma conta.

**Request Body:**
```json
{
  "name": "Nubank Atualizado",
  "type": "savings",
  "initialBalance": "750.00"
}
```

**Response 200 OK:** (dados atualizados)

---

### PATCH /api/accounts/{id}

Atualiza parcialmente uma conta (apenas os campos enviados).

**Request Body (exemplo — arquivar conta):**
```json
{
  "isActive": false
}
```

**Response 200 OK:** (dados atualizados)

---

## 5. Categorias

### GET /api/categories

Lista todas as categorias do usuário.

**Query Params:**
| Param | Tipo | Descrição |
|---|---|---|
| `type` | string | Filtrar por `income` ou `expense` |

**Response 200 OK:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Alimentação",
      "type": "expense",
      "color": "#FF5733",
      "createdAt": "2024-01-10T08:00:00+00:00"
    }
  ]
}
```

---

### POST /api/categories

Cria uma nova categoria.

**Request Body:**
```json
{
  "name": "Alimentação",
  "type": "expense",
  "color": "#FF5733"
}
```

**Campos obrigatórios:** `name`, `type`

**Response 201 Created:** (dados da categoria criada)

**Respostas possíveis:**
| Código | Situação |
|---|---|
| `201 Created` | Categoria criada |
| `422 Unprocessable Entity` | Nome duplicado ou campos inválidos |

---

### PUT /api/categories/{id}

Atualiza uma categoria existente.

**Request Body:**
```json
{
  "name": "Alimentação e Bebidas",
  "type": "expense",
  "color": "#E74C3C"
}
```

**Response 200 OK:** (dados atualizados)

---

## 6. Transações

### GET /api/transactions

Lista transações com filtros e paginação.

**Query Params:**
| Param | Tipo | Descrição |
|---|---|---|
| `account_id` | integer | Filtrar por conta |
| `category_id` | integer | Filtrar por categoria |
| `type` | string | `credit` ou `debit` |
| `status` | string | `pending`, `reconciled`, `divergent`, `ignored` |
| `date_from` | string | Data inicial (YYYY-MM-DD) |
| `date_to` | string | Data final (YYYY-MM-DD) |
| `search` | string | Texto livre na descrição |
| `page` | integer | Página (padrão: 1) |
| `limit` | integer | Itens por página (padrão: 20) |
| `sort` | string | Campo: `date`, `amount`, `created_at` |
| `order` | string | `asc` ou `desc` |

**Response 200 OK:**
```json
{
  "data": [
    {
      "id": 42,
      "account": { "id": 1, "name": "Bradesco Corrente" },
      "category": { "id": 5, "name": "Alimentação" },
      "type": "debit",
      "amount": "245.80",
      "date": "2024-03-14",
      "description": "Supermercado Extra",
      "notes": null,
      "status": "pending",
      "createdAt": "2024-03-14T18:30:00+00:00",
      "updatedAt": "2024-03-14T18:30:00+00:00"
    }
  ],
  "meta": {
    "page": 1,
    "limit": 20,
    "total": 87,
    "totalPages": 5
  }
}
```

---

### POST /api/transactions

Cria uma nova transação manualmente.

**Request Body:**
```json
{
  "accountId": 1,
  "categoryId": 5,
  "type": "debit",
  "amount": "245.80",
  "date": "2024-03-14",
  "description": "Supermercado Extra",
  "notes": "Compra mensal"
}
```

**Campos obrigatórios:** `accountId`, `type`, `amount`, `date`, `description`
**Campos opcionais:** `categoryId`, `notes`

**Response 201 Created:** (dados da transação criada)

**Respostas possíveis:**
| Código | Situação |
|---|---|
| `201 Created` | Transação criada |
| `403 Forbidden` | Conta ou categoria de outro usuário |
| `422 Unprocessable Entity` | Campos inválidos |

---

### GET /api/transactions/{id}

Retorna os detalhes de uma transação específica.

**Response 200 OK:** (dados completos da transação, mesmo formato da listagem)

---

### PUT /api/transactions/{id}

Atualiza uma transação existente.

**Restrição:** Transações com `status = 'reconciled'` retornam `HTTP 422`.

**Request Body:**
```json
{
  "accountId": 1,
  "categoryId": 5,
  "type": "debit",
  "amount": "260.00",
  "date": "2024-03-14",
  "description": "Supermercado Extra - Ajustado",
  "notes": null
}
```

**Response 200 OK:** (dados atualizados)

**Respostas possíveis:**
| Código | Situação |
|---|---|
| `200 OK` | Transação atualizada |
| `422 Unprocessable Entity` | Transação conciliada (não editável) ou campos inválidos |
| `404 Not Found` | Transação não encontrada |

---

## 7. Importação de Extratos CSV

### POST /api/imports/preview

Faz o upload de um arquivo CSV e retorna um preview dos registros sem persistir.

**Request:** `multipart/form-data`

| Campo | Tipo | Obrigatório | Descrição |
|---|---|---|---|
| `file` | file | Sim | Arquivo CSV |
| `account_id` | integer | Sim | ID da conta para importação |

**Formato esperado do CSV:**
```
date,description,amount,type,external_reference
2024-03-01,Pagamento salário,5000.00,credit,SAL-2024-03
2024-03-05,Aluguel,-1500.00,debit,ALG-2024-03
```

**Response 200 OK:**
```json
{
  "totalRows": 45,
  "validRows": 43,
  "duplicateRows": 2,
  "errorRows": 0,
  "preview": [
    {
      "row": 1,
      "date": "2024-03-01",
      "description": "Pagamento salário",
      "amount": "5000.00",
      "type": "credit",
      "externalReference": "SAL-2024-03",
      "status": "new",
      "issues": []
    },
    {
      "row": 3,
      "date": "2024-03-05",
      "description": "Aluguel",
      "amount": "1500.00",
      "type": "debit",
      "externalReference": "ALG-2024-03",
      "status": "duplicate",
      "issues": ["Registro já importado anteriormente (externalReference: ALG-2024-03)"]
    }
  ],
  "importId": "temp_abc123"
}
```

**Status por linha:** `new`, `duplicate`, `error`

**Respostas possíveis:**
| Código | Situação |
|---|---|
| `200 OK` | Preview gerado |
| `400 Bad Request` | Arquivo não é CSV ou está vazio |
| `422 Unprocessable Entity` | Formato de colunas inválido |

---

### POST /api/imports/{id}/confirm

Confirma a importação após revisão do preview.

**Nota:** `{id}` é o `importId` retornado pelo endpoint de preview.

**Request Body:**
```json
{
  "accountId": 1,
  "skipDuplicates": true
}
```

**Response 201 Created:**
```json
{
  "importedStatementId": 15,
  "totalImported": 43,
  "totalSkipped": 2,
  "totalErrors": 0,
  "status": "confirmed"
}
```

**Respostas possíveis:**
| Código | Situação |
|---|---|
| `201 Created` | Importação confirmada e persistida |
| `404 Not Found` | Import ID inválido ou expirado |
| `409 Conflict` | Importação já confirmada anteriormente |

---

### GET /api/imports/{id}

Retorna os detalhes de uma importação específica com seus registros.

**Query Params:**
| Param | Tipo | Descrição |
|---|---|---|
| `page` | integer | Paginação dos entries |
| `limit` | integer | Itens por página |
| `status` | string | Filtrar entries: `pending`, `reconciled`, `ignored` |

**Response 200 OK:**
```json
{
  "id": 15,
  "account": { "id": 1, "name": "Bradesco Corrente" },
  "filename": "extrato_marco_2024.csv",
  "importedAt": "2024-03-15T10:30:00+00:00",
  "totalEntries": 45,
  "validEntries": 43,
  "skippedEntries": 2,
  "status": "confirmed",
  "entries": {
    "data": [...],
    "meta": { "page": 1, "limit": 20, "total": 43, "totalPages": 3 }
  }
}
```

---

### GET /api/imports

Lista o histórico de importações do usuário.

**Query Params:** `account_id`, `page`, `limit`, `sort`, `order`

**Response 200 OK:** (listagem paginada de ImportedStatement)

---

## 8. Conciliação

### GET /api/reconciliations

Lista conciliações ativas ou históricas.

**Query Params:**
| Param | Tipo | Descrição |
|---|---|---|
| `account_id` | integer | Filtrar por conta |
| `date_from` | string | Data inicial de conciliação |
| `date_to` | string | Data final de conciliação |
| `include_undone` | boolean | Incluir conciliações desfeitas (padrão: `false`) |
| `page` | integer | Página |
| `limit` | integer | Itens por página |

**Response 200 OK:**
```json
{
  "data": [
    {
      "id": 7,
      "transaction": {
        "id": 42,
        "description": "Supermercado Extra",
        "amount": "245.80",
        "date": "2024-03-14"
      },
      "statementEntry": {
        "id": 108,
        "description": "SUPERMERCADO EXTRA",
        "amount": "245.80",
        "date": "2024-03-14"
      },
      "reconciledAt": "2024-03-15T14:00:00+00:00",
      "undoneAt": null
    }
  ],
  "meta": { "page": 1, "limit": 20, "total": 31, "totalPages": 2 }
}
```

---

### GET /api/reconciliations/suggestions

Retorna sugestões automáticas de conciliação geradas pelo sistema.

**Query Params:**
| Param | Tipo | Descrição |
|---|---|---|
| `account_id` | integer | Obrigatório — conta para gerar sugestões |
| `date_tolerance` | integer | Tolerância em dias (padrão: 3) |
| `page` | integer | Página |
| `limit` | integer | Itens por página |

**Response 200 OK:**
```json
{
  "data": [
    {
      "score": 0.95,
      "transaction": {
        "id": 42,
        "description": "Supermercado Extra",
        "amount": "245.80",
        "date": "2024-03-14",
        "status": "pending"
      },
      "statementEntry": {
        "id": 108,
        "description": "SUPERMERCADO EXTRA",
        "amount": "245.80",
        "date": "2024-03-14",
        "status": "pending"
      },
      "matchReasons": ["Mesmo valor", "Mesma data", "Descrição similar"]
    }
  ],
  "meta": { "page": 1, "limit": 20, "total": 8, "totalPages": 1 }
}
```

**Campo `score`:** Valor de 0.0 a 1.0 indicando confiança na sugestão.

---

### POST /api/reconciliations/confirm

Confirma uma conciliação entre uma transação e uma entrada de extrato.

**Request Body:**
```json
{
  "transactionId": 42,
  "statementEntryId": 108,
  "notes": "Conciliado manualmente após conferência"
}
```

**Response 201 Created:**
```json
{
  "id": 7,
  "transactionId": 42,
  "statementEntryId": 108,
  "reconciledAt": "2024-03-15T14:00:00+00:00",
  "reconciledBy": { "id": 1, "name": "João Silva" }
}
```

**Respostas possíveis:**
| Código | Situação |
|---|---|
| `201 Created` | Conciliação confirmada |
| `404 Not Found` | Transação ou StatementEntry não encontrados |
| `409 Conflict` | Transação ou entry já conciliados ativamente |
| `422 Unprocessable Entity` | Transação e entry de contas diferentes |

---

### POST /api/reconciliations/{id}/undo

Desfaz uma conciliação previamente confirmada.

**Request Body:**
```json
{
  "reason": "Valor divergente após conferência"
}
```

**Response 200 OK:**
```json
{
  "id": 7,
  "undoneAt": "2024-03-16T09:00:00+00:00",
  "undoneBy": { "id": 1, "name": "João Silva" },
  "transactionStatus": "pending",
  "statementEntryStatus": "pending"
}
```

**Respostas possíveis:**
| Código | Situação |
|---|---|
| `200 OK` | Conciliação desfeita |
| `404 Not Found` | Conciliação não encontrada |
| `409 Conflict` | Conciliação já desfeita anteriormente |

---

## 9. Resumo dos Endpoints

| Método | Rota | Descrição | Auth |
|---|---|---|---|
| `POST` | `/api/login` | Login | Não |
| `POST` | `/api/logout` | Logout | Sim |
| `GET` | `/api/me` | Dados do usuário autenticado | Sim |
| `GET` | `/api/dashboard` | Dados consolidados do dashboard | Sim |
| `GET` | `/api/dashboard/summary` | Resumo financeiro do mês | Sim |
| `GET` | `/api/accounts` | Listar contas | Sim |
| `POST` | `/api/accounts` | Criar conta | Sim |
| `GET` | `/api/accounts/{id}` | Detalhar conta | Sim |
| `PUT` | `/api/accounts/{id}` | Atualizar conta | Sim |
| `PATCH` | `/api/accounts/{id}` | Atualizar parcialmente conta | Sim |
| `GET` | `/api/categories` | Listar categorias | Sim |
| `POST` | `/api/categories` | Criar categoria | Sim |
| `PUT` | `/api/categories/{id}` | Atualizar categoria | Sim |
| `GET` | `/api/transactions` | Listar transações | Sim |
| `POST` | `/api/transactions` | Criar transação | Sim |
| `GET` | `/api/transactions/{id}` | Detalhar transação | Sim |
| `PUT` | `/api/transactions/{id}` | Atualizar transação | Sim |
| `POST` | `/api/imports/preview` | Preview de importação CSV | Sim |
| `POST` | `/api/imports/{id}/confirm` | Confirmar importação | Sim |
| `GET` | `/api/imports/{id}` | Detalhar importação | Sim |
| `GET` | `/api/imports` | Histórico de importações | Sim |
| `GET` | `/api/reconciliations` | Listar conciliações | Sim |
| `GET` | `/api/reconciliations/suggestions` | Sugestões de conciliação | Sim |
| `POST` | `/api/reconciliations/confirm` | Confirmar conciliação | Sim |
| `POST` | `/api/reconciliations/{id}/undo` | Desfazer conciliação | Sim |
