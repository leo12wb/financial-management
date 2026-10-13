# Casos de Uso — Financial Management & Transaction Reconciliation System

## Convenções

| Campo | Descrição |
|---|---|
| **Identificador** | Código único do caso de uso (UC001, UC002...) |
| **Ator principal** | Quem inicia a ação |
| **Pré-condições** | Estado do sistema antes do caso de uso |
| **Gatilho** | Evento que inicia o caso de uso |
| **Fluxo principal** | Sequência de passos do caminho feliz |
| **Fluxos alternativos** | Variações válidas do fluxo principal |
| **Exceções** | Situações de erro e como são tratadas |
| **Pós-condições** | Estado do sistema após o caso de uso |
| **RFs relacionados** | Requisitos funcionais cobertos |

---

## UC001 — Autenticar Usuário

**Identificador:** UC001
**Nome:** Autenticar Usuário
**Objetivo:** Permitir que o usuário acesse o sistema informando suas credenciais.
**Ator principal:** Visitante (não autenticado)

**Pré-condições:**
- O usuário possui cadastro no sistema com e-mail e senha definidos
- O usuário não está autenticado

**Gatilho:**
O visitante acessa a URL do sistema e é redirecionado para a tela de login, ou acessa diretamente a página de login.

**Fluxo Principal:**
1. O sistema exibe o formulário de login com campos de e-mail e senha
2. O visitante informa seu e-mail e senha
3. O visitante submete o formulário
4. O sistema envia `POST /api/login` com as credenciais
5. O backend valida o e-mail e a senha contra o banco de dados
6. O backend cria uma sessão autenticada e responde com os dados do usuário e cookie de sessão
7. O frontend armazena o estado de autenticação no React Context
8. O sistema redireciona o usuário para o Dashboard

**Fluxos Alternativos:**
- **FA001-A — Usuário já autenticado:** Se o usuário acessar a página de login já autenticado, é redirecionado imediatamente para o Dashboard sem exibir o formulário.

**Tratamento de Exceções:**
- **E001-A — Credenciais inválidas:** O backend retorna HTTP 401. O frontend exibe a mensagem "E-mail ou senha incorretos." sem indicar qual campo está errado.
- **E001-B — Campos em branco:** Validação client-side impede o envio. O sistema destaca os campos obrigatórios.
- **E001-C — E-mail com formato inválido:** Validação client-side exibe "Informe um e-mail válido."
- **E001-D — Serviço indisponível:** O backend retorna HTTP 500. O frontend exibe mensagem genérica "Erro ao conectar ao servidor. Tente novamente."

**Pós-condições:**
- O usuário está autenticado
- O cookie de sessão HttpOnly está presente no browser
- O estado de autenticação no React Context está populado com os dados do usuário
- O usuário visualiza o Dashboard

**Requisitos Relacionados:** RF001

---

## UC002 — Consultar Dashboard

**Identificador:** UC002
**Nome:** Consultar Dashboard
**Objetivo:** Visualizar um painel consolidado com saldos, indicadores financeiros do mês e transações recentes.
**Ator principal:** Usuário autenticado

**Pré-condições:**
- O usuário está autenticado
- O usuário possui pelo menos uma conta cadastrada

**Gatilho:**
O usuário acessa a página inicial do sistema (Dashboard), ou clica no item "Dashboard" no menu de navegação.

**Fluxo Principal:**
1. O sistema carrega a página Dashboard
2. O frontend envia `GET /api/dashboard` com o mês atual
3. O backend consolida os dados: saldo total, receitas do mês, despesas do mês, saldo líquido
4. O backend retorna os dados consolidados, a lista de contas com saldos e as últimas 10 transações
5. O frontend exibe os cards de resumo com os valores financeiros
6. O frontend renderiza os gráficos (receitas vs despesas)
7. O frontend exibe a tabela de transações recentes

**Fluxos Alternativos:**
- **FA002-A — Selecionar outro mês:** O usuário seleciona um mês diferente no seletor. O frontend reenvia `GET /api/dashboard?month=YYYY-MM` e atualiza os indicadores.
- **FA002-B — Nenhuma conta cadastrada:** O sistema exibe mensagem orientando o usuário a cadastrar sua primeira conta, com link direto para o formulário.

**Tratamento de Exceções:**
- **E002-A — Erro ao carregar dados:** O frontend exibe um estado de erro com botão "Tentar novamente".
- **E002-B — Sessão expirada:** O backend retorna HTTP 401. O frontend redireciona para a tela de login.

**Pós-condições:**
- O usuário visualiza seus indicadores financeiros atualizados

**Requisitos Relacionados:** RF004, RF005

---

## UC003 — Cadastrar Conta Financeira

**Identificador:** UC003
**Nome:** Cadastrar Conta Financeira
**Objetivo:** Registrar uma nova conta financeira no sistema para controle de saldo e transações.
**Ator principal:** Usuário autenticado

**Pré-condições:**
- O usuário está autenticado

**Gatilho:**
O usuário clica em "Nova Conta" na tela de Contas ou no Dashboard.

**Fluxo Principal:**
1. O sistema exibe o formulário de cadastro de conta com campos: nome, tipo, saldo inicial, moeda
2. O usuário preenche os campos obrigatórios (nome e tipo)
3. O usuário define opcionalmente o saldo inicial e a moeda
4. O usuário submete o formulário
5. O frontend envia `POST /api/accounts` com os dados
6. O backend valida os dados e cria a conta associada ao usuário autenticado
7. O backend retorna HTTP 201 com os dados da conta criada
8. O frontend fecha o formulário e atualiza a listagem de contas

**Fluxos Alternativos:**
- **FA003-A — Saldo inicial negativo:** O usuário define um saldo inicial negativo (conta com dívida). O sistema aceita o valor e registra.

**Tratamento de Exceções:**
- **E003-A — Campo "nome" em branco:** O sistema exibe "O nome da conta é obrigatório."
- **E003-B — Tipo inválido:** O sistema retorna HTTP 422. O frontend exibe a violação correspondente.
- **E003-C — Formato inválido de saldo inicial:** O sistema exibe "Informe um valor monetário válido (ex: 1500.00)."

**Pós-condições:**
- A nova conta está cadastrada no banco de dados
- A conta aparece na listagem de contas do usuário
- O saldo da nova conta é igual ao saldo inicial informado (pois não há transações)

**Requisitos Relacionados:** RF006

---

## UC004 — Editar Conta Financeira

**Identificador:** UC004
**Nome:** Editar Conta Financeira
**Objetivo:** Atualizar os dados de uma conta financeira existente.
**Ator principal:** Usuário autenticado

**Pré-condições:**
- O usuário está autenticado
- Existe pelo menos uma conta cadastrada

**Gatilho:**
O usuário clica em "Editar" em uma conta na listagem.

**Fluxo Principal:**
1. O sistema exibe o formulário preenchido com os dados atuais da conta
2. O usuário altera os campos desejados (nome, tipo, saldo inicial)
3. O usuário submete o formulário
4. O frontend envia `PUT /api/accounts/{id}` com os dados atualizados
5. O backend valida os dados, verifica o proprietário e atualiza a conta
6. O backend retorna HTTP 200 com os dados atualizados
7. O frontend fecha o formulário e atualiza a listagem

**Fluxos Alternativos:**
- **FA004-A — Arquivar conta:** O usuário desmarca o campo "Conta ativa". O sistema envia `PATCH /api/accounts/{id}` com `{"isActive": false}`. A conta é arquivada e não aparece mais nas consultas padrão.

**Tratamento de Exceções:**
- **E004-A — Tentativa de editar conta de outro usuário:** O backend retorna HTTP 403. O frontend exibe "Você não tem permissão para editar esta conta."
- **E004-B — Conta não encontrada:** O backend retorna HTTP 404.

**Pós-condições:**
- Os dados da conta estão atualizados
- O saldo atual é recalculado com o novo saldo inicial (se alterado)

**Requisitos Relacionados:** RF007

---

## UC005 — Consultar Contas

**Identificador:** UC005
**Nome:** Consultar Contas
**Objetivo:** Visualizar a lista de contas financeiras do usuário com seus saldos atuais.
**Ator principal:** Usuário autenticado

**Pré-condições:**
- O usuário está autenticado

**Gatilho:**
O usuário clica em "Contas" no menu de navegação.

**Fluxo Principal:**
1. O sistema carrega a página de Contas
2. O frontend envia `GET /api/accounts`
3. O backend retorna a lista de contas com saldos calculados dinamicamente
4. O frontend exibe a lista com nome, tipo, saldo inicial e saldo atual de cada conta

**Fluxos Alternativos:**
- **FA005-A — Visualizar contas arquivadas:** O usuário ativa o filtro "Mostrar arquivadas". O frontend reenvia `GET /api/accounts?active=false`.

**Tratamento de Exceções:**
- **E005-A — Nenhuma conta cadastrada:** O sistema exibe estado vazio com mensagem orientativa.

**Pós-condições:**
- O usuário visualiza suas contas atualizadas

**Requisitos Relacionados:** RF008

---

## UC006 — Cadastrar Transação

**Identificador:** UC006
**Nome:** Cadastrar Transação
**Objetivo:** Registrar manualmente uma movimentação financeira em uma conta.
**Ator principal:** Usuário autenticado

**Pré-condições:**
- O usuário está autenticado
- O usuário possui pelo menos uma conta ativa cadastrada

**Gatilho:**
O usuário clica em "Nova Transação" na tela de Transações ou no atalho do Dashboard.

**Fluxo Principal:**
1. O sistema exibe o formulário de transação com campos: conta, tipo (receita/despesa), valor, data, descrição, categoria, observações
2. O usuário seleciona a conta e o tipo (crédito ou débito)
3. O usuário informa o valor, a data e a descrição
4. O usuário seleciona opcionalmente uma categoria
5. O usuário submete o formulário
6. O frontend envia `POST /api/transactions` com os dados
7. O backend valida os dados, verifica que a conta e categoria pertencem ao usuário
8. O backend cria a transação com status `pending`
9. O backend retorna HTTP 201 com os dados da transação
10. O frontend fecha o formulário e atualiza a listagem de transações e os saldos

**Fluxos Alternativos:**
- **FA006-A — Cadastrar receita:** O usuário seleciona tipo "Receita". O sistema salva como `type = 'credit'` e soma ao saldo da conta.
- **FA006-B — Transação sem categoria:** O campo categoria é omitido. O sistema aceita e salva com `category_id = NULL`.

**Tratamento de Exceções:**
- **E006-A — Valor igual a zero ou negativo:** O backend retorna HTTP 422. O frontend exibe "O valor deve ser maior que zero."
- **E006-B — Data futura:** O sistema aceita datas futuras (pré-lançamentos são válidos no domínio financeiro).
- **E006-C — Conta de outro usuário:** O backend retorna HTTP 403.
- **E006-D — Categoria não pertence ao usuário:** O backend retorna HTTP 403.

**Pós-condições:**
- A transação está registrada com status `pending`
- O saldo da conta é atualizado imediatamente (cálculo dinâmico)

**Requisitos Relacionados:** RF012

---

## UC007 — Consultar e Filtrar Transações

**Identificador:** UC007
**Nome:** Consultar e Filtrar Transações
**Objetivo:** Visualizar, pesquisar e filtrar o histórico de transações financeiras.
**Ator principal:** Usuário autenticado

**Pré-condições:**
- O usuário está autenticado

**Gatilho:**
O usuário clica em "Transações" no menu de navegação.

**Fluxo Principal:**
1. O sistema carrega a página de Transações com a listagem paginada
2. O frontend envia `GET /api/transactions` com os filtros padrão (mês atual)
3. O backend retorna as transações paginadas do usuário
4. O frontend exibe a listagem com coluna de conta, tipo, valor, data, descrição, categoria e status

**Fluxos Alternativos:**
- **FA007-A — Aplicar filtro por conta:** O usuário seleciona uma conta no painel de filtros. O frontend reenvia a requisição com `account_id=X`.
- **FA007-B — Filtrar por período:** O usuário define datas de início e fim. O frontend envia `date_from` e `date_to`.
- **FA007-C — Pesquisar por texto:** O usuário digita no campo de busca. O frontend envia `search=texto`.
- **FA007-D — Filtrar por status de conciliação:** O usuário seleciona "Conciliadas" ou "Pendentes".
- **FA007-E — Navegar entre páginas:** O usuário clica em próxima/anterior. O frontend envia `page=N`.

**Tratamento de Exceções:**
- **E007-A — Nenhum resultado para os filtros:** O sistema exibe estado vazio "Nenhuma transação encontrada para os filtros aplicados."

**Pós-condições:**
- O usuário visualiza as transações filtradas

**Requisitos Relacionados:** RF014

---

## UC008 — Gerenciar Categorias

**Identificador:** UC008
**Nome:** Gerenciar Categorias
**Objetivo:** Cadastrar, visualizar e editar categorias de transações (receitas e despesas).
**Ator principal:** Usuário autenticado

**Pré-condições:**
- O usuário está autenticado

**Gatilho:**
O usuário acessa a seção de Categorias no menu ou nas configurações.

**Fluxo Principal (Cadastrar):**
1. O usuário clica em "Nova Categoria"
2. O sistema exibe formulário com campos: nome, tipo (receita/despesa), cor
3. O usuário preenche o nome e seleciona o tipo
4. O usuário submete o formulário
5. O frontend envia `POST /api/categories`
6. O backend valida e cria a categoria
7. O frontend atualiza a listagem

**Fluxo Principal (Editar):**
1. O usuário clica em "Editar" em uma categoria
2. O sistema exibe o formulário preenchido
3. O usuário altera os campos desejados
4. O usuário submete o formulário
5. O frontend envia `PUT /api/categories/{id}`
6. O backend valida e atualiza
7. O frontend atualiza a listagem

**Tratamento de Exceções:**
- **E008-A — Nome duplicado:** O backend retorna HTTP 422. O frontend exibe "Já existe uma categoria com este nome."
- **E008-B — Tentar criar com tipo inválido:** O backend retorna HTTP 422.

**Pós-condições:**
- As categorias estão disponíveis para seleção no formulário de transações

**Requisitos Relacionados:** RF009, RF010, RF011

---

## UC009 — Importar Extrato CSV

**Identificador:** UC009
**Nome:** Importar Extrato CSV
**Objetivo:** Fazer o upload de um arquivo CSV de extrato bancário e importar os registros para o sistema.
**Ator principal:** Usuário autenticado

**Pré-condições:**
- O usuário está autenticado
- O usuário possui uma conta ativa para associar o extrato
- O arquivo CSV está no formato correto (colunas: date, description, amount, type, external_reference)

**Gatilho:**
O usuário acessa a tela de Importação e clica em "Importar Extrato".

**Fluxo Principal:**
1. O sistema exibe o formulário de importação com: seleção de conta e campo de upload de arquivo
2. O usuário seleciona a conta de destino
3. O usuário seleciona ou arrasta o arquivo CSV
4. O frontend envia `POST /api/imports/preview` com o arquivo e o ID da conta
5. O backend analisa o arquivo, valida o formato e detecta duplicatas
6. O backend retorna o preview com o resultado da análise
7. O frontend exibe o preview: total de registros, válidos, duplicatas, erros e uma tabela com os primeiros registros
8. O usuário revisa o preview (UC010)
9. O usuário confirma a importação clicando em "Confirmar Importação"
10. O frontend envia `POST /api/imports/{id}/confirm`
11. O backend persiste os registros válidos como `StatementEntry`
12. O backend retorna o resultado: total importado, total ignorado
13. O frontend exibe a mensagem de sucesso e redireciona para a tela de conciliação ou histórico

**Fluxos Alternativos:**
- **FA009-A — Reimportação do mesmo arquivo:** O sistema detecta todas as linhas como duplicatas (pela `externalReference`) e informa que nenhum registro novo será importado.

**Tratamento de Exceções:**
- **E009-A — Arquivo não é CSV:** O backend retorna HTTP 400. O frontend exibe "O arquivo deve ser no formato CSV."
- **E009-B — Arquivo vazio:** O backend retorna HTTP 400. O frontend exibe "O arquivo está vazio."
- **E009-C — Colunas obrigatórias ausentes:** O backend retorna HTTP 422 com a lista de colunas faltantes.
- **E009-D — Conta de outro usuário:** O backend retorna HTTP 403.

**Pós-condições:**
- Um `ImportedStatement` foi criado com status `confirmed`
- Os `StatementEntry` válidos e não duplicados estão salvos com status `pending`
- O usuário pode iniciar o processo de conciliação

**Requisitos Relacionados:** RF015, RF016, RF017, RF018, RF019, RF020

---

## UC010 — Validar Registros Importados

**Identificador:** UC010
**Nome:** Validar Registros Importados
**Objetivo:** Revisar o preview da importação e identificar registros inválidos ou duplicados antes de confirmar.
**Ator principal:** Usuário autenticado

**Pré-condições:**
- O usuário gerou um preview de importação (UC009, passo 7)

**Gatilho:**
O sistema exibe o preview após processar o upload do CSV.

**Fluxo Principal:**
1. O sistema exibe o resumo da importação: total de linhas, válidas, duplicatas, erros
2. O sistema exibe a tabela de preview com cada linha do CSV e seu status: `new` (verde), `duplicate` (amarelo), `error` (vermelho)
3. Para linhas com `error`, o sistema exibe a descrição do problema
4. O usuário analisa os registros
5. O usuário decide prosseguir com a importação (apenas registros válidos) ou cancelar

**Fluxos Alternativos:**
- **FA010-A — Cancelar importação:** O usuário clica em "Cancelar". O sistema descarta o preview e nenhum dado é persistido.
- **FA010-B — Importação apenas de registros novos:** Quando há duplicatas, a opção padrão é ignorá-las. O usuário confirma sem alterar essa opção.

**Tratamento de Exceções:**
- **E010-A — Todos os registros são inválidos:** O sistema informa que não há registros para importar e desabilita o botão de confirmação.

**Pós-condições:**
- O usuário decidiu confirmar ou cancelar a importação

**Requisitos Relacionados:** RF016, RF017

---

## UC011 — Consultar Sugestões de Conciliação

**Identificador:** UC011
**Nome:** Consultar Sugestões de Conciliação
**Objetivo:** Visualizar as sugestões automáticas de correspondência entre transações registradas e entradas do extrato.
**Ator principal:** Usuário autenticado

**Pré-condições:**
- O usuário está autenticado
- Existem `StatementEntry` com status `pending` para a conta selecionada
- Existem `Transaction` com status `pending` para a conta selecionada

**Gatilho:**
O usuário acessa a tela de Conciliação e seleciona uma conta.

**Fluxo Principal:**
1. O usuário acessa a tela de Conciliação
2. O usuário seleciona a conta para conciliar
3. O frontend envia `GET /api/reconciliations/suggestions?account_id=X`
4. O backend compara transações e entradas do extrato com os critérios: mesma conta, mesmo valor, data próxima (tolerância padrão: 3 dias), similaridade de descrição
5. O backend retorna a lista de sugestões com `score` de confiança
6. O frontend exibe as sugestões ordenadas por score descendente (maior confiança primeiro)
7. Para cada sugestão, são exibidos: dados da transação, dados do extrato, score e razões do match

**Fluxos Alternativos:**
- **FA011-A — Ajustar tolerância de data:** O usuário aumenta a tolerância de datas para 7 dias. O frontend reenvia com `date_tolerance=7`.
- **FA011-B — Nenhuma sugestão encontrada:** O sistema exibe "Nenhuma sugestão de conciliação encontrada. Tente aumentar a tolerância de datas ou verifique se há transações e entradas de extrato pendentes."

**Tratamento de Exceções:**
- **E011-A — Conta sem extratos importados:** O sistema exibe orientação para importar um extrato primeiro.

**Pós-condições:**
- O usuário visualiza as sugestões disponíveis e pode confirmar ou rejeitar cada uma

**Requisitos Relacionados:** RF021

---

## UC012 — Confirmar Conciliação

**Identificador:** UC012
**Nome:** Confirmar Conciliação
**Objetivo:** Confirmar o vínculo entre uma transação registrada e uma entrada de extrato bancário.
**Ator principal:** Usuário autenticado

**Pré-condições:**
- Existe pelo menos uma sugestão de conciliação pendente
- Tanto a `Transaction` quanto o `StatementEntry` estão com status `pending`

**Gatilho:**
O usuário clica em "Confirmar" em uma sugestão de conciliação na tela de Conciliação.

**Fluxo Principal:**
1. O sistema exibe os dados da sugestão: transação (descrição, valor, data) e extrato (descrição, valor, data)
2. O usuário revisa e clica em "Confirmar Conciliação"
3. Opcionalmente, o usuário adiciona uma observação
4. O frontend envia `POST /api/reconciliations/confirm` com `transactionId` e `statementEntryId`
5. O backend verifica que ambos os registros existem, pertencem ao usuário e estão com status `pending`
6. O backend cria um registro na tabela `Reconciliation`
7. O backend atualiza o status da `Transaction` para `reconciled`
8. O backend atualiza o status do `StatementEntry` para `reconciled`
9. O backend retorna HTTP 201 com os dados da conciliação
10. O frontend remove a sugestão da lista e exibe confirmação de sucesso

**Fluxos Alternativos:**
- **FA012-A — Conciliação manual (sem sugestão):** O usuário usa a tela de conciliação manual, seleciona uma transação e uma entrada do extrato diretamente, sem partir de uma sugestão.

**Tratamento de Exceções:**
- **E012-A — Transação já conciliada:** O backend retorna HTTP 409. O frontend exibe "Esta transação já está conciliada."
- **E012-B — StatementEntry já conciliado:** O backend retorna HTTP 409. O frontend exibe "Esta entrada do extrato já está conciliada."
- **E012-C — Transação e extrato de contas diferentes:** O backend retorna HTTP 422. O frontend exibe "A transação e a entrada do extrato pertencem a contas diferentes."

**Pós-condições:**
- Existe um registro ativo na tabela `Reconciliation` vinculando os dois registros
- `Transaction.status = 'reconciled'`
- `StatementEntry.status = 'reconciled'`

**Requisitos Relacionados:** RF022

---

## UC013 — Rejeitar Sugestão de Conciliação

**Identificador:** UC013
**Nome:** Rejeitar Sugestão de Conciliação
**Objetivo:** Descartar uma sugestão de conciliação que o usuário considera incorreta.
**Ator principal:** Usuário autenticado

**Pré-condições:**
- Existe uma sugestão de conciliação exibida na tela
- O StatementEntry está com status `pending`

**Gatilho:**
O usuário clica em "Rejeitar" ou "Ignorar" em uma sugestão de conciliação.

**Fluxo Principal:**
1. O sistema exibe os dados da sugestão
2. O usuário clica em "Rejeitar"
3. O sistema pergunta se o usuário deseja: (a) manter o entry como `pending` para análise futura, ou (b) marcar como `ignored`
4. O usuário seleciona a opção desejada
5. O sistema atualiza o status do `StatementEntry` conforme a escolha
6. A sugestão é removida da lista

**Fluxos Alternativos:**
- **FA013-A — Marcar como ignorado:** O StatementEntry é marcado como `ignored` e não aparecerá mais nas sugestões futuras.
- **FA013-B — Manter como pendente:** O StatementEntry continua como `pending` e poderá aparecer em sugestões futuras com outros critérios.

**Pós-condições:**
- A sugestão rejeitada não é mais exibida na lista
- O StatementEntry tem status `pending` ou `ignored` conforme a escolha do usuário

**Requisitos Relacionados:** RF023

---

## UC014 — Desfazer Conciliação

**Identificador:** UC014
**Nome:** Desfazer Conciliação
**Objetivo:** Reverter uma conciliação confirmada, retornando a transação e a entrada do extrato para o status pendente.
**Ator principal:** Usuário autenticado

**Pré-condições:**
- Existe uma conciliação ativa (com `undone_at = NULL`)
- O usuário é proprietário da conta vinculada

**Gatilho:**
O usuário clica em "Desfazer Conciliação" na tela de detalhes da transação, na listagem de conciliações, ou na tela de histórico de importações.

**Fluxo Principal:**
1. O sistema exibe uma tela de confirmação com os dados da conciliação a ser desfeita
2. O usuário informa opcionalmente o motivo do desfazimento
3. O usuário confirma a operação
4. O frontend envia `POST /api/reconciliations/{id}/undo`
5. O backend verifica que a conciliação existe e está ativa (`undone_at IS NULL`)
6. O backend registra `undone_at` com o timestamp atual e `undone_by` com o ID do usuário
7. O backend atualiza o status da `Transaction` para `pending`
8. O backend atualiza o status do `StatementEntry` para `pending`
9. O backend retorna HTTP 200 com confirmação
10. O frontend atualiza a interface e exibe mensagem de sucesso

**Fluxos Alternativos:**
- **FA014-A — Desfazer a partir da listagem de transações:** O usuário acessa o detalhe de uma transação `reconciled` e clica em "Desfazer Conciliação".

**Tratamento de Exceções:**
- **E014-A — Conciliação já desfeita:** O backend retorna HTTP 409. O frontend exibe "Esta conciliação já foi desfeita."
- **E014-B — Conciliação não encontrada:** O backend retorna HTTP 404.

**Pós-condições:**
- O registro `Reconciliation` tem `undone_at` preenchido (não é deletado — histórico preservado)
- `Transaction.status = 'pending'` — pode ser editada novamente
- `StatementEntry.status = 'pending'` — pode ser conciliado com outra transação
- A transação e a entrada do extrato são elegíveis para nova conciliação

**Requisitos Relacionados:** RF024

---

## UC015 — Consultar Histórico de Importação

**Identificador:** UC015
**Nome:** Consultar Histórico de Importação
**Objetivo:** Visualizar o histórico de arquivos CSV importados, com detalhes de cada importação.
**Ator principal:** Usuário autenticado

**Pré-condições:**
- O usuário está autenticado
- Existe pelo menos uma importação realizada

**Gatilho:**
O usuário acessa a tela de Importações e clica na aba/menu "Histórico".

**Fluxo Principal:**
1. O sistema carrega a página de Histórico de Importações
2. O frontend envia `GET /api/imports`
3. O backend retorna a lista de importações do usuário ordenada por data decrescente
4. O frontend exibe a listagem com: nome do arquivo, data de importação, conta, total de registros, registros válidos, registros ignorados e status
5. O usuário pode clicar em uma importação para ver seus detalhes

**Fluxo de Detalhamento:**
1. O usuário clica em uma importação
2. O frontend envia `GET /api/imports/{id}`
3. O backend retorna os metadados da importação e a lista paginada de `StatementEntry`
4. O frontend exibe a tabela de entries com: data, descrição, valor, tipo e status de conciliação
5. O usuário pode filtrar os entries por status (pendente, conciliado, ignorado)

**Fluxos Alternativos:**
- **FA015-A — Filtrar por conta:** O usuário seleciona uma conta no filtro. O frontend reenvia `GET /api/imports?account_id=X`.

**Tratamento de Exceções:**
- **E015-A — Nenhuma importação realizada:** O sistema exibe estado vazio com orientação para importar o primeiro extrato.

**Pós-condições:**
- O usuário visualiza o histórico completo de importações e pode acompanhar o status de conciliação dos entries

**Requisitos Relacionados:** RF020
