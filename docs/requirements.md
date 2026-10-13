# Requisitos — Financial Management & Transaction Reconciliation System

## 1. Objetivo do Sistema

O **Financial Management & Transaction Reconciliation System** é uma aplicação web fullstack desenvolvida com o objetivo de demonstrar competências técnicas avançadas em desenvolvimento de software para composição de portfólio profissional.

O sistema permite que um usuário autenticado gerencie suas finanças pessoais ou empresariais por meio de:

- Cadastro e acompanhamento de contas financeiras (corrente, poupança, investimento, carteira etc.)
- Registro manual de transações financeiras (receitas e despesas)
- Importação de extratos bancários em formato CSV
- Conciliação automática e manual entre transações registradas e registros importados do extrato
- Visualização consolidada de saldo, fluxo de caixa e indicadores financeiros no dashboard

O sistema foi projetado para evidenciar boas práticas de arquitetura de software, modelagem de dados, design de APIs REST, testes automatizados e containerização com Docker.

---

## 2. Escopo Funcional

Os seguintes módulos compõem o escopo da versão 1.0 do sistema:

| Módulo | Descrição |
|---|---|
| **Autenticação** | Login, logout e controle de sessão do usuário |
| **Dashboard** | Visão consolidada de saldos, transações recentes e indicadores |
| **Contas** | Cadastro, edição e listagem de contas financeiras |
| **Categorias** | Gerenciamento de categorias de despesas e receitas |
| **Transações** | Registro, edição e listagem de transações financeiras |
| **Importação CSV** | Upload, validação, preview e confirmação de extratos bancários |
| **Conciliação** | Sugestão automática, confirmação manual e desfazimento de conciliações |

---

## 3. Requisitos Funcionais

### 3.1 Módulo de Autenticação

**RF001 — Login de usuário**
O sistema deve permitir que um usuário se autentique fornecendo e-mail e senha. Em caso de credenciais válidas, deve ser criada uma sessão autenticada. Em caso de credenciais inválidas, deve ser retornada mensagem de erro genérica sem revelar qual campo está incorreto.

**RF002 — Logout de usuário**
O sistema deve permitir que o usuário encerre sua sessão, invalidando o token ou cookie de autenticação no servidor.

**RF003 — Recuperação do usuário autenticado**
O sistema deve expor um endpoint que retorne os dados do usuário atualmente autenticado (id, nome, e-mail), para ser consumido pelo frontend após recarregamento da página.

### 3.2 Módulo de Dashboard

**RF004 — Exibição do dashboard financeiro**
O sistema deve exibir um painel com os seguintes dados consolidados:
- Saldo total de todas as contas ativas
- Saldo individual por conta
- Total de receitas do mês corrente
- Total de despesas do mês corrente
- Saldo líquido do mês corrente (receitas - despesas)

**RF005 — Listagem de transações recentes**
O dashboard deve exibir as últimas 10 transações registradas no sistema, com data, descrição, valor e status de conciliação.

### 3.3 Módulo de Contas

**RF006 — Cadastro de conta financeira**
O sistema deve permitir o cadastro de contas financeiras com os seguintes atributos: nome, tipo (corrente, poupança, investimento, carteira, outro), saldo inicial e moeda (padrão: BRL).

**RF007 — Edição de conta financeira**
O sistema deve permitir a edição dos atributos nome, tipo e saldo inicial de uma conta existente. O saldo atual não deve ser editado diretamente; ele é sempre calculado dinamicamente.

**RF008 — Listagem de contas**
O sistema deve listar todas as contas cadastradas pelo usuário autenticado, exibindo nome, tipo, saldo inicial e saldo atual calculado.

### 3.4 Módulo de Categorias

**RF009 — Cadastro de categoria**
O sistema deve permitir o cadastro de categorias de transação com nome e tipo (receita ou despesa). Cada categoria pertence ao usuário autenticado.

**RF010 — Edição de categoria**
O sistema deve permitir a edição do nome e do tipo de uma categoria existente, desde que ela não tenha transações vinculadas que tornem a mudança de tipo incoerente.

**RF011 — Listagem de categorias**
O sistema deve listar todas as categorias do usuário autenticado, podendo filtrar por tipo (receita/despesa).

### 3.5 Módulo de Transações

**RF012 — Cadastro de transação manual**
O sistema deve permitir o registro de transações financeiras com os seguintes atributos: conta vinculada, categoria, tipo (receita/despesa), valor, data, descrição e observações opcionais.

**RF013 — Edição de transação**
O sistema deve permitir a edição de uma transação, com a restrição de que transações com status `reconciled` não podem ser editadas livremente — o usuário deve primeiro desfazer a conciliação.

**RF014 — Listagem e filtragem de transações**
O sistema deve listar transações com suporte a filtros por: conta, categoria, tipo, período (data inicial e final), status de conciliação e texto livre na descrição. A listagem deve ser paginada.

### 3.6 Módulo de Importação de Extrato CSV

**RF015 — Upload de arquivo CSV**
O sistema deve aceitar o upload de arquivos CSV contendo registros de extrato bancário. O arquivo deve conter as colunas: data, descrição, valor, tipo (débito/crédito) e, opcionalmente, referência externa.

**RF016 — Validação do arquivo importado**
O sistema deve validar o formato do CSV, verificando: presença das colunas obrigatórias, formato de data, formato numérico dos valores e ausência de linhas completamente vazias. Erros de validação devem ser retornados com número de linha e descrição do problema.

**RF017 — Preview da importação**
Antes de confirmar a importação, o sistema deve exibir um preview com os registros a serem importados, destacando possíveis duplicatas (identificadas pela `externalReference`) e registros com inconsistências.

**RF018 — Confirmação da importação**
O usuário deve confirmar explicitamente a importação após revisar o preview. Somente registros válidos e não duplicados devem ser persistidos como `StatementEntry`.

**RF019 — Idempotência por referência externa**
O sistema deve garantir que registros com a mesma `externalReference` (para a mesma conta) não sejam importados mais de uma vez, mesmo que o mesmo arquivo seja enviado novamente.

**RF020 — Histórico de importações**
O sistema deve manter o histórico de todas as importações realizadas, incluindo data, nome do arquivo, quantidade de registros importados e status (pendente, concluída, com erros).

### 3.7 Módulo de Conciliação

**RF021 — Geração de sugestões de conciliação**
O sistema deve comparar automaticamente as transações registradas com os `StatementEntry` importados e gerar sugestões de conciliação com base em critérios configuráveis: mesma conta, mesmo valor, data próxima (tolerância em dias) e similaridade de descrição.

**RF022 — Confirmação de conciliação**
O usuário deve poder confirmar uma sugestão de conciliação, vinculando um `StatementEntry` a uma `Transaction`. Ao confirmar, ambos os registros devem ter seus status atualizados para `reconciled`.

**RF023 — Rejeição de sugestão de conciliação**
O usuário deve poder rejeitar uma sugestão de conciliação, marcando o `StatementEntry` como `ignored` ou mantendo-o como `pending` para análise futura.

**RF024 — Desfazimento de conciliação**
O sistema deve permitir que o usuário desfaça uma conciliação previamente confirmada. A operação deve registrar o timestamp em `undoneAt` e reverter o status da transação e do `StatementEntry` para `pending`.

**RF025 — Listagem de conciliações**
O sistema deve listar todas as conciliações realizadas, com suporte a filtros por conta, período e status. A listagem deve ser paginada.

---

## 4. Requisitos Não Funcionais

**RNF001 — Segurança de autenticação**
Todas as rotas da API, exceto `/api/login`, devem exigir autenticação válida. Tentativas não autorizadas devem retornar HTTP 401. A senha do usuário deve ser armazenada como hash utilizando bcrypt com custo mínimo de 12.

**RNF002 — Proteção contra CSRF**
O sistema deve implementar proteção contra ataques CSRF nas operações de mutação (POST, PUT, PATCH, DELETE).

**RNF003 — Performance da API**
Os endpoints da API devem responder em menos de 500ms para operações simples (CRUD) e menos de 2000ms para operações complexas (geração de sugestões de conciliação, consolidação de dashboard), sob carga normal de uso.

**RNF004 — Precisão de valores monetários**
Todos os valores monetários devem ser armazenados como `DECIMAL(15,2)` no banco de dados e trafegar como strings numéricas no JSON da API, nunca como `float`, para evitar erros de arredondamento de ponto flutuante.

**RNF005 — Usabilidade da interface**
A interface deve ser responsiva para resoluções a partir de 1024px de largura. As operações principais (cadastro de transação, confirmação de conciliação) devem ser realizáveis em no máximo 3 cliques a partir da tela principal.

**RNF006 — Testabilidade**
O código de backend deve possuir cobertura mínima de 80% por testes automatizados, cobrindo pelo menos: Services, Repositories e Controllers. O frontend deve possuir testes unitários para componentes críticos.

**RNF007 — Containerização**
A aplicação completa deve ser executável via `docker compose up` em ambiente de desenvolvimento, sem necessidade de instalação manual de dependências além do Docker.

**RNF008 — Documentação técnica**
O projeto deve manter documentação técnica atualizada na pasta `/docs`, incluindo requisitos, arquitetura, modelo de dados, API e diagramas.

**RNF009 — Logs estruturados**
O backend deve emitir logs estruturados (JSON) para operações relevantes: autenticação, importação, conciliação e erros. O nível de log deve ser configurável por variável de ambiente.

**RNF010 — Validação de entrada**
Todos os dados recebidos pela API devem ser validados antes de serem processados. Erros de validação devem retornar HTTP 422 com lista detalhada de violações por campo.

---

## 5. Restrições Tecnológicas

| Camada | Tecnologia | Versão |
|---|---|---|
| Linguagem backend | PHP | 8.4 |
| Framework backend | Symfony | 7.x |
| ORM | Doctrine ORM | 3.x |
| Linguagem frontend | TypeScript | 5.x |
| Framework frontend | React | 18.x |
| Build tool | Vite | 5.x |
| Estilização | Tailwind CSS | 3.x |
| Gráficos | Recharts | 2.x |
| Banco de dados | PostgreSQL | 16 |
| Containerização | Docker + Docker Compose | — |
| Gerenciador de pacotes PHP | Composer | 2.x |
| Gerenciador de pacotes JS | npm | 10.x |

### Justificativas das escolhas tecnológicas

- **PHP 8.4 + Symfony 7**: Stack madura, amplamente adotada no mercado, com excelente suporte a DDD, injeção de dependência e testes.
- **React + TypeScript**: Padrão de mercado para frontends modernos, com type safety que reduz bugs em tempo de desenvolvimento.
- **Vite**: Build tool de alta performance que melhora significativamente a experiência de desenvolvimento (HMR instantâneo).
- **Tailwind CSS**: Abordagem utility-first que permite consistência visual sem overhead de CSS customizado.
- **PostgreSQL 16**: Banco relacional robusto com suporte nativo a tipos como `DECIMAL`, `UUID` e `TIMESTAMPTZ`.
- **Docker**: Garante reprodutibilidade do ambiente de desenvolvimento e facilita o deployment.

---

## 6. Regras de Negócio

**RN001 — Cálculo dinâmico de saldo**
O saldo atual de uma conta nunca é armazenado diretamente. Ele é sempre calculado como:
```
saldo_atual = saldo_inicial + SUM(valor) das transações com tipo = 'credit' − SUM(valor) das transações com tipo = 'debit'
```
Essa abordagem evita inconsistências causadas por edições ou exclusões de transações.

**RN002 — Precisão decimal obrigatória**
Valores monetários devem ser armazenados como `DECIMAL(15,2)` ou `NUMERIC(15,2)`. O uso de `FLOAT` ou `DOUBLE` é proibido para evitar erros de arredondamento em operações financeiras.

**RN003 — Conciliação não duplicada**
Um `StatementEntry` só pode estar associado a uma `Transaction` ativa por vez. O banco de dados deve garantir isso por meio de índice único parcial:
```sql
CREATE UNIQUE INDEX idx_reconciliation_active
  ON reconciliation(statement_entry_id)
  WHERE undone_at IS NULL;
```

**RN004 — Transação conciliada é imutável**
Uma `Transaction` com status `reconciled` não pode ser editada ou excluída diretamente. O usuário deve primeiro executar a operação de desfazer conciliação (UC014), que revertará o status para `pending`, permitindo edição posterior.

**RN005 — Idempotência na importação**
A importação é idempotente com base no campo `externalReference` por conta. Se um registro com a mesma `externalReference` já existir para a mesma conta, ele será ignorado durante a confirmação da importação, mesmo que o arquivo seja reenviado.

**RN006 — Pertencimento de dados ao usuário**
Todas as entidades (contas, categorias, transações, importações) pertencem ao usuário autenticado. Nenhum usuário pode acessar dados de outro usuário. O isolamento deve ser garantido em nível de serviço e de query no repositório.

**RN007 — Tipo de transação e impacto no saldo**
Transações do tipo `credit` (receita) somam ao saldo da conta. Transações do tipo `debit` (despesa) subtraem do saldo. O valor da transação é sempre armazenado como positivo; o sinal é determinado pelo tipo.

---

## 7. Critérios de Aceite por Módulo

### 7.1 Autenticação
- [ ] Login com credenciais válidas cria sessão e retorna dados do usuário
- [ ] Login com credenciais inválidas retorna HTTP 401 com mensagem genérica
- [ ] Acesso a rotas protegidas sem sessão retorna HTTP 401
- [ ] Logout invalida a sessão no servidor
- [ ] `/api/me` retorna dados do usuário autenticado ou HTTP 401

### 7.2 Dashboard
- [ ] Dashboard exibe saldo total consolidado de todas as contas ativas
- [ ] Dashboard exibe receitas e despesas do mês corrente
- [ ] Dashboard exibe as últimas 10 transações
- [ ] Dados do dashboard são filtrados por usuário autenticado

### 7.3 Contas
- [ ] Cadastro com campos obrigatórios ausentes retorna HTTP 422
- [ ] Cadastro bem-sucedido retorna HTTP 201 com dados da conta criada
- [ ] Saldo atual é calculado corretamente a partir do saldo inicial e das transações
- [ ] Edição retorna HTTP 200 com dados atualizados
- [ ] Listagem retorna somente contas do usuário autenticado

### 7.4 Categorias
- [ ] Cadastro com nome duplicado para o mesmo usuário retorna HTTP 422
- [ ] Listagem filtrável por tipo (receita/despesa)
- [ ] Edição bem-sucedida retorna HTTP 200

### 7.5 Transações
- [ ] Cadastro vincula corretamente à conta e à categoria informadas
- [ ] Tentativa de edição de transação `reconciled` retorna HTTP 422
- [ ] Listagem com filtros retorna somente registros correspondentes
- [ ] Paginação funciona corretamente

### 7.6 Importação CSV
- [ ] Upload de CSV inválido retorna erros por linha
- [ ] Preview lista registros válidos e destaca duplicatas
- [ ] Confirmação persiste somente registros novos (idempotência)
- [ ] Reimportação do mesmo arquivo não cria duplicatas

### 7.7 Conciliação
- [ ] Sugestões são geradas apenas para transações e entradas da mesma conta
- [ ] Confirmação atualiza status de ambos os registros para `reconciled`
- [ ] Não é possível conciliar um `StatementEntry` já conciliado
- [ ] Desfazimento reverte status e registra `undoneAt`
- [ ] Histórico de conciliações é paginado e filtrável
