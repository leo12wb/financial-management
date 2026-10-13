# Progresso do Projeto — Financial Management & Transaction Reconciliation System

## Status Atual

**Fase em andamento:** Fase 1 — Análise e Planejamento
**Última atualização:** 2026-10-13

---

## Fases do Projeto

| Fase | Nome | Status |
|---|---|---|
| Fase 1 | Análise e Planejamento | Em andamento |
| Fase 2 | Infraestrutura (Docker, Symfony, React/Vite) | Pendente |
| Fase 3 | Backend — Entidades, Migrações e Repositórios | Pendente |
| Fase 4 | Backend — Services e Controllers (CRUD) | Pendente |
| Fase 5 | Backend — Importação CSV | Pendente |
| Fase 6 | Backend — Conciliação | Pendente |
| Fase 7 | Frontend — Estrutura, Rotas e Autenticação | Pendente |
| Fase 8 | Frontend — Dashboard e Contas | Pendente |
| Fase 9 | Frontend — Transações e Categorias | Pendente |
| Fase 10 | Frontend — Importação e Conciliação | Pendente |
| Fase 11 | Testes automatizados | Pendente |
| Fase 12 | Revisão final e documentação | Pendente |

---

## Fase 1 — Análise e Planejamento [Em andamento]

### Itens concluídos

- [x] Layout de referência (layout.png) analisado
- [x] Requisitos documentados (`docs/requirements.md`)
- [x] Arquitetura documentada (`docs/architecture.md`)
- [x] Modelo de dados documentado (`docs/database.md`)
- [x] Especificação da API documentada (`docs/api.md`)
- [x] Casos de uso documentados (`docs/use-cases.md`)
- [x] Fluxo de conciliação documentado (`docs/reconciliation-flow.md`)
- [x] Diagramas criados:
  - [x] `docs/diagrams/architecture.mmd`
  - [x] `docs/diagrams/entity-relationship.mmd`
  - [x] `docs/diagrams/use-case-overview.puml`
  - [x] `docs/diagrams/use-case-accounts.puml`
  - [x] `docs/diagrams/use-case-transactions.puml`
  - [x] `docs/diagrams/use-case-import.puml`
  - [x] `docs/diagrams/use-case-reconciliation.puml`
  - [x] `docs/diagrams/reconciliation-sequence.mmd`
  - [x] `docs/diagrams/csv-import-activity.mmd`

### Itens pendentes na Fase 1

- [ ] Revisão final dos documentos
- [ ] Validação do modelo de dados com o layout de referência

---

## Fase 2 — Infraestrutura (Próxima fase)

### Planejamento de tarefas

- [ ] Criar `docker-compose.yml` com serviços: `app` (PHP-FPM), `nginx`, `db` (PostgreSQL), `frontend` (Node/Vite)
- [ ] Criar `Dockerfile` para o serviço PHP com PHP 8.4 e extensões necessárias
- [ ] Criar `Dockerfile` para o serviço frontend com Node 20 e Vite
- [ ] Configurar Nginx como proxy reverso para Symfony e Vite
- [ ] Inicializar projeto Symfony 7 com `composer create-project symfony/skeleton`
- [ ] Instalar dependências Symfony: `doctrine/orm`, `doctrine/migrations`, `symfony/security-bundle`, `symfony/serializer`, `symfony/validator`
- [ ] Inicializar projeto React com Vite: `npm create vite@latest -- --template react-ts`
- [ ] Instalar dependências frontend: `react-router-dom`, `@tanstack/react-query`, `axios`, `tailwindcss`, `recharts`, `react-hook-form`
- [ ] Configurar Tailwind CSS com PostCSS
- [ ] Configurar proxy Vite → Symfony
- [ ] Testar `docker compose up` funcionando end-to-end

---

## Problemas Conhecidos

Nenhum no momento.

---

## Decisões Técnicas Registradas

| Data | Decisão | Justificativa |
|---|---|---|
| 2026-10-13 | Arquitetura monolítica em vez de microsserviços | Escopo definido, sem necessidade de escala independente de componentes |
| 2026-10-13 | Autenticação via sessão + cookie HttpOnly | Mais simples para MVP; JWT pode ser adicionado em versão futura |
| 2026-10-13 | Saldo calculado dinamicamente | Evita inconsistências em edições/exclusões; performance aceitável para o volume esperado |
| 2026-10-13 | `DECIMAL(15,2)` para valores monetários | Precisão exata, sem erros de ponto flutuante |
| 2026-10-13 | Índice único parcial para conciliação ativa | Garante integridade em nível de banco sem overhead de linhas excluídas |
| 2026-10-13 | Histórico de conciliações (soft-delete com `undone_at`) | Preserva auditabilidade das operações |

---

## Próximas Tarefas

1. **Revisar** os documentos da Fase 1 para garantir consistência
2. **Iniciar Fase 2** — Configuração da infraestrutura Docker
3. **Configurar** o ambiente de desenvolvimento local com Docker Compose
4. **Validar** que a estrutura de pastas definida na arquitetura está corretamente refletida no scaffolding inicial
