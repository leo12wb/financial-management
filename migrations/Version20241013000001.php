<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Initial schema: users, accounts, categories, transactions,
 * imported_statements, statement_entries, reconciliations.
 */
final class Version20241013000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial database schema for Financial Management & Transaction Reconciliation';
    }

    public function up(Schema $schema): void
    {
        // ── users ─────────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE users (
                id         SERIAL PRIMARY KEY,
                email      VARCHAR(180) NOT NULL,
                password   VARCHAR(255) NOT NULL,
                roles      JSON         NOT NULL DEFAULT '[]',
                created_at TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                CONSTRAINT uq_users_email UNIQUE (email)
            )
        SQL);

        // ── accounts ──────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE accounts (
                id                SERIAL PRIMARY KEY,
                user_id           INTEGER      NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                name              VARCHAR(255) NOT NULL,
                institution_name  VARCHAR(255) NOT NULL,
                account_type      VARCHAR(20)  NOT NULL,
                currency          VARCHAR(3)   NOT NULL DEFAULT 'BRL',
                initial_balance   NUMERIC(15,2) NOT NULL DEFAULT 0.00,
                is_active         BOOLEAN      NOT NULL DEFAULT TRUE,
                created_at        TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                updated_at        TIMESTAMPTZ  NULL,
                CONSTRAINT chk_accounts_type CHECK (account_type IN ('checking', 'savings', 'wallet')),
                CONSTRAINT chk_accounts_currency CHECK (LENGTH(currency) = 3)
            )
        SQL);

        $this->addSql('CREATE INDEX idx_accounts_user_active ON accounts (user_id, is_active)');

        // ── categories ────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE categories (
                id         SERIAL PRIMARY KEY,
                user_id    INTEGER      NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                name       VARCHAR(255) NOT NULL,
                type       VARCHAR(10)  NOT NULL,
                is_active  BOOLEAN      NOT NULL DEFAULT TRUE,
                created_at TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                CONSTRAINT chk_categories_type CHECK (type IN ('income', 'expense')),
                CONSTRAINT uq_categories_user_name_type UNIQUE (user_id, name, type)
            )
        SQL);

        $this->addSql('CREATE INDEX idx_categories_user ON categories (user_id)');

        // ── transactions ──────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE transactions (
                id                     SERIAL PRIMARY KEY,
                account_id             INTEGER      NOT NULL REFERENCES accounts(id) ON DELETE RESTRICT,
                category_id            INTEGER      NULL     REFERENCES categories(id) ON DELETE SET NULL,
                description            VARCHAR(500) NOT NULL,
                amount                 NUMERIC(15,2) NOT NULL,
                type                   VARCHAR(10)  NOT NULL,
                transaction_date       DATE         NOT NULL,
                external_reference     VARCHAR(255) NULL,
                source                 VARCHAR(10)  NOT NULL DEFAULT 'manual',
                reconciliation_status  VARCHAR(15)  NOT NULL DEFAULT 'pending',
                created_at             TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                updated_at             TIMESTAMPTZ  NULL,
                CONSTRAINT chk_transactions_type   CHECK (type IN ('income', 'expense')),
                CONSTRAINT chk_transactions_source CHECK (source IN ('manual', 'imported')),
                CONSTRAINT chk_transactions_rec_status CHECK (
                    reconciliation_status IN ('pending', 'reconciled', 'divergent', 'ignored')
                ),
                CONSTRAINT chk_transactions_amount CHECK (amount > 0)
            )
        SQL);

        $this->addSql('CREATE INDEX idx_transactions_account_date ON transactions (account_id, transaction_date)');
        $this->addSql('CREATE INDEX idx_transactions_rec_status   ON transactions (reconciliation_status)');
        $this->addSql('CREATE INDEX idx_transactions_ext_ref      ON transactions (external_reference) WHERE external_reference IS NOT NULL');

        // ── imported_statements ───────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE imported_statements (
                id                SERIAL PRIMARY KEY,
                account_id        INTEGER      NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
                original_filename VARCHAR(255) NOT NULL,
                status            VARCHAR(15)  NOT NULL DEFAULT 'pending',
                total_rows        INTEGER      NOT NULL DEFAULT 0,
                imported_rows     INTEGER      NOT NULL DEFAULT 0,
                rejected_rows     INTEGER      NOT NULL DEFAULT 0,
                created_at        TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                CONSTRAINT chk_imported_statements_status CHECK (
                    status IN ('pending', 'previewed', 'confirmed', 'failed')
                )
            )
        SQL);

        $this->addSql('CREATE INDEX idx_imported_statements_account ON imported_statements (account_id)');

        // ── statement_entries ─────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE statement_entries (
                id                    SERIAL PRIMARY KEY,
                imported_statement_id INTEGER      NOT NULL REFERENCES imported_statements(id) ON DELETE CASCADE,
                transaction_date      DATE         NOT NULL,
                description           VARCHAR(500) NOT NULL,
                amount                NUMERIC(15,2) NOT NULL,
                external_reference    VARCHAR(255) NULL,
                reconciliation_status VARCHAR(15)  NOT NULL DEFAULT 'pending',
                created_at            TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                CONSTRAINT chk_statement_entries_rec_status CHECK (
                    reconciliation_status IN ('pending', 'reconciled', 'divergent', 'ignored')
                )
            )
        SQL);

        $this->addSql('CREATE INDEX idx_statement_entries_statement_status ON statement_entries (imported_statement_id, reconciliation_status)');
        $this->addSql('CREATE INDEX idx_statement_entries_ext_ref ON statement_entries (external_reference) WHERE external_reference IS NOT NULL');

        // ── reconciliations ───────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE reconciliations (
                id                  SERIAL PRIMARY KEY,
                transaction_id      INTEGER     NOT NULL REFERENCES transactions(id) ON DELETE RESTRICT,
                statement_entry_id  INTEGER     NOT NULL REFERENCES statement_entries(id) ON DELETE RESTRICT,
                status              VARCHAR(15) NOT NULL DEFAULT 'pending',
                reconciled_at       TIMESTAMPTZ NULL,
                undone_at           TIMESTAMPTZ NULL,
                created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT chk_reconciliations_status CHECK (
                    status IN ('pending', 'reconciled', 'rejected', 'undone')
                )
            )
        SQL);

        // Prevent a transaction from being reconciled twice simultaneously
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uq_reconciliations_transaction_active
                ON reconciliations (transaction_id)
                WHERE status = 'reconciled'
        SQL);

        // Prevent a statement entry from being reconciled twice simultaneously
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uq_reconciliations_entry_active
                ON reconciliations (statement_entry_id)
                WHERE status = 'reconciled'
        SQL);

        $this->addSql('CREATE INDEX idx_reconciliations_transaction ON reconciliations (transaction_id)');
        $this->addSql('CREATE INDEX idx_reconciliations_entry       ON reconciliations (statement_entry_id)');

        // ── seed default categories ───────────────────────────────────────────
        // NOTE: categories are per-user so we cannot seed here.
        // The demo user command creates the user; categories will be seeded via fixture or manually.
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS reconciliations');
        $this->addSql('DROP TABLE IF EXISTS statement_entries');
        $this->addSql('DROP TABLE IF EXISTS imported_statements');
        $this->addSql('DROP TABLE IF EXISTS transactions');
        $this->addSql('DROP TABLE IF EXISTS categories');
        $this->addSql('DROP TABLE IF EXISTS accounts');
        $this->addSql('DROP TABLE IF EXISTS users');
    }
}
